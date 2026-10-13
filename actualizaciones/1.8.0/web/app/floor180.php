<?php
declare(strict_types=1);

function qa180_room_pending(App $app,array $tenant,array $branch,int $room):bool {
    return (bool)$app->one('SELECT t.id FROM pos_tickets t WHERE t.tenant_id=? AND t.branch_id=? AND t.status NOT IN("paid","void","merged") AND (EXISTS(SELECT 1 FROM fisitaap_r2_floor f WHERE f.table_id=t.table_id AND f.room_id=?) OR EXISTS(SELECT 1 FROM fisitaap_ticket_tables180 j JOIN fisitaap_r2_floor f ON f.table_id=j.table_id WHERE j.ticket_id=t.id AND j.tenant_id=t.tenant_id AND f.room_id=?)) LIMIT 1',[$tenant['id'],$branch['id'],$room,$room]);
}
function qa180_table(App $app,array $tenant,array $branch,int $id,bool $lock=false):array {
    $row=$app->one('SELECT t.*,f.room_id,r.is_active room_active FROM restaurant_tables t LEFT JOIN fisitaap_r2_floor f ON f.table_id=t.id LEFT JOIN fisitaap_r2_rooms r ON r.id=f.room_id AND r.tenant_id=t.tenant_id AND r.branch_id=t.branch_id WHERE t.id=? AND t.tenant_id=? AND t.branch_id=? AND t.is_active=1'.($lock?' FOR UPDATE':''),[$id,$tenant['id'],$branch['id']]);
    if(!$row||($row['room_id']&&!$row['room_active']))throw new DomainException('La mesa o su salón no están activos en esta sucursal.');return $row;
}
function qa180_table_accounts(App $app,array $tenant,array $branch,int $table,bool $lock=false):array {
    return $app->all('SELECT DISTINCT t.* FROM pos_tickets t LEFT JOIN fisitaap_ticket_tables180 j ON j.ticket_id=t.id AND j.tenant_id=t.tenant_id AND j.branch_id=t.branch_id WHERE t.tenant_id=? AND t.branch_id=? AND (t.table_id=? OR j.table_id=?) AND t.status NOT IN("paid","void","merged") ORDER BY t.id'.($lock?' FOR UPDATE':''),[$tenant['id'],$branch['id'],$table,$table]);
}
function qa180_floor_rows(App $app,array $tenant,array $branch):array {
    $map='(SELECT p.id ticket_id,p.table_id,p.total,p.paid_total,p.created_at,p.status FROM pos_tickets p WHERE p.tenant_id=? AND p.branch_id=? AND p.table_id IS NOT NULL AND p.status NOT IN("paid","void","merged") UNION SELECT p.id,j.table_id,p.total,p.paid_total,p.created_at,p.status FROM fisitaap_ticket_tables180 j JOIN pos_tickets p ON p.id=j.ticket_id AND p.tenant_id=j.tenant_id AND p.branch_id=j.branch_id WHERE j.tenant_id=? AND j.branch_id=? AND p.status NOT IN("paid","void","merged"))';
    return $app->all('SELECT r.*,f.room_id,f.x,f.y,f.width,f.height,f.shape,d.sector_id,COALESCE(d.rotation,0) rotation,COALESCE(d.basic_order,r.sort_order) basic_order,COALESCE(a.open_count,0) open_count,COALESCE(a.balance,0) balance,a.opened_at,COALESCE(a.payment_count,0) payment_count FROM restaurant_tables r LEFT JOIN fisitaap_r2_floor f ON f.table_id=r.id LEFT JOIN fisitaap_r3_table_details d ON d.table_id=r.id AND d.tenant_id=r.tenant_id LEFT JOIN (SELECT table_id,COUNT(*) open_count,SUM(total-paid_total) balance,MIN(created_at) opened_at,SUM(status="payment") payment_count FROM '.$map.' x GROUP BY table_id) a ON a.table_id=r.id WHERE r.tenant_id=? AND r.branch_id=? AND r.is_active=1 ORDER BY basic_order,r.id',[$tenant['id'],$branch['id'],$tenant['id'],$branch['id'],$tenant['id'],$branch['id']]);
}
function qa180_floor_upgrade(App $app):void {
    foreach($app->all('SELECT DISTINCT t.tenant_id,t.branch_id FROM restaurant_tables t LEFT JOIN fisitaap_r2_floor f ON f.table_id=t.id LEFT JOIN fisitaap_r2_rooms r ON r.id=f.room_id AND r.tenant_id=t.tenant_id AND r.branch_id=t.branch_id WHERE t.is_active=1 AND r.id IS NULL') as $scope){
        $room=$app->one('SELECT id FROM fisitaap_r2_rooms WHERE tenant_id=? AND branch_id=? AND name="Salón anterior"',[$scope['tenant_id'],$scope['branch_id']]);if(!$room){$app->exec('INSERT INTO fisitaap_r2_rooms(tenant_id,branch_id,name,service_rate) VALUES(?,?,"Salón anterior",10)',[$scope['tenant_id'],$scope['branch_id']]);$room=['id'=>(int)$app->db->lastInsertId()];}
        $tables=$app->all('SELECT t.id,t.space_type FROM restaurant_tables t LEFT JOIN fisitaap_r2_floor f ON f.table_id=t.id LEFT JOIN fisitaap_r2_rooms r ON r.id=f.room_id AND r.tenant_id=t.tenant_id AND r.branch_id=t.branch_id WHERE t.tenant_id=? AND t.branch_id=? AND t.is_active=1 AND r.id IS NULL ORDER BY t.id',[$scope['tenant_id'],$scope['branch_id']]);
        foreach($tables as $i=>$table)$app->exec('INSERT INTO fisitaap_r2_floor(table_id,room_id,x,y,width,height,shape) VALUES(?,?,?,?,120,120,?) ON DUPLICATE KEY UPDATE room_id=VALUES(room_id)',[$table['id'],$room['id'],50+($i%6)*150,50+(intdiv($i,6)%4)*145,$table['space_type']==='bar'?'bar':'square']);
    }
}
function qa180_sync_tables(App $app,int $tenant,array $tables):void {
    $tables=array_values(array_unique(array_filter(array_map('intval',$tables))));if(!$tables)return;$marks=implode(',',array_fill(0,count($tables),'?'));
    $map='(SELECT id ticket_id,table_id FROM pos_tickets WHERE tenant_id=? AND table_id IS NOT NULL AND status NOT IN("paid","void","merged") UNION SELECT p.id,j.table_id FROM fisitaap_ticket_tables180 j JOIN pos_tickets p ON p.id=j.ticket_id AND p.tenant_id=j.tenant_id WHERE j.tenant_id=? AND p.status NOT IN("paid","void","merged"))';
    $app->exec('UPDATE restaurant_tables r LEFT JOIN (SELECT table_id,COUNT(*) count FROM '.$map.' x GROUP BY table_id) c ON c.table_id=r.id SET r.status=IF(COALESCE(c.count,0)>0,"occupied","available") WHERE r.tenant_id=? AND r.id IN('.$marks.')',[$tenant,$tenant,$tenant,...$tables]);
}
function qa180_move_table(App $app,array $user,array $tenant,array $branch,int $id):int {
    verify_csrf();qa180_permission($app,$user,'ventas','write');$target=(int)($_POST['target_table']??0);$join=($_POST['action']??'')==='join_tables';$key=(string)($_POST['operation_key']??'');if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('Recarga la mesa antes de confirmar el traslado o la unión.');
    $hash=hash('sha256',json_encode([$user['id'],$branch['id'],$id,$target,$join],JSON_THROW_ON_ERROR));$app->db->beginTransaction();try{
        $app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$branch['id'],$tenant['id']]);
        $prior=$app->one('SELECT * FROM fisitaap_operations180 WHERE tenant_id=? AND operation_key=?',[$tenant['id'],$key]);if($prior){if(!hash_equals($prior['payload_hash'],$hash))throw new DomainException('Esta referencia ya se usó para otra operación.');$app->db->commit();return (int)$prior['result_id'];}
        $main=pos_ticket_get_148($app,$tenant,$branch,$id,true);if(in_array($main['status'],['paid','void','merged'],true)||(int)$main['revision']!==(int)($_POST['revision']??-1))throw new DomainException('La cuenta cambió. Revisa su estado y recarga antes de continuar.');
        if($main['source_order_id'])throw new DomainException('El pedido web mantiene una cuenta independiente.');
        $destination=qa180_table($app,$tenant,$branch,$target,true);$original=$main['table_id']?qa180_table($app,$tenant,$branch,(int)$main['table_id'],true):null;
        $joined=array_column($app->all('SELECT table_id FROM fisitaap_ticket_tables180 WHERE ticket_id=? AND tenant_id=?',[$id,$tenant['id']]),'table_id');$oldTables=array_unique(array_filter([...(array)$joined,(int)$main['table_id']]));
        if($join){
            if(!$original||$original['space_type']==='bar'||$destination['space_type']==='bar')throw new DomainException('La barra mantiene cuentas por comensal. Une mesas del mismo salón.');
            if(!$original['room_id']||(int)$original['room_id']!==(int)$destination['room_id'])throw new DomainException('Solo puedes unir mesas del mismo salón y sucursal.');
            if($main['source_order_id'])throw new DomainException('El pedido web conserva su cuenta original. No se puede unir o mover a otra mesa.');
            if((float)$main['paid_total']>0)throw new DomainException('Esta cuenta tiene pagos parciales. Completa su cobro antes de unirla.');
            if($main['coupon_id'])throw new DomainException('Quita el código de descuento antes de unir mesas; podrás aplicarlo a la cuenta unificada.');
            if(abs(r2_table_rate($app,$target,(bool)$destination['service_enabled'])-(float)$main['service_rate'])>.00001)throw new DomainException('Las mesas tienen reglas de servicio distintas. Conserva sus cuentas separadas.');
            $others=qa180_table_accounts($app,$tenant,$branch,$target,true);
            foreach($others as $other)if((int)$other['id']!==$id){if((float)$other['paid_total']>0)throw new DomainException('La mesa destino tiene pagos parciales. No se puede unir.');if($other['coupon_id'])throw new DomainException('La mesa destino tiene un cupón. Quítalo antes de unir las cuentas.');if(abs((float)$other['service_rate']-(float)$main['service_rate'])>.00001)throw new DomainException('Las cuentas tienen cargos de servicio distintos. Conserva sus cobros separados.');}
            $tables=[...$oldTables,$target];$discount=(float)$main['discount_total'];$notes=(string)$main['notes'];$service=(float)$main['service_total'];
            foreach($others as $other)if((int)$other['id']!==$id){
                $tables[]=(int)$other['table_id'];array_push($tables,...array_column($app->all('SELECT table_id FROM fisitaap_ticket_tables180 WHERE tenant_id=? AND ticket_id=?',[$tenant['id'],$other['id']]),'table_id'));
                $app->exec('UPDATE pos_ticket_items SET original_ticket_id180=COALESCE(original_ticket_id180,ticket_id),ticket_id=? WHERE ticket_id=?',[$id,$other['id']]);
                $discount+=(float)$other['discount_total'];$notes.="\nUnida ".$other['ticket_number'].($other['notes']?': '.$other['notes']:'');
                $service+=(float)$other['service_total'];
                $app->exec('UPDATE pos_tickets SET status="merged",merged_into=?,revision=revision+1 WHERE id=? AND tenant_id=?',[$id,$other['id'],$tenant['id']]);$app->exec('DELETE FROM fisitaap_ticket_tables180 WHERE ticket_id=? AND tenant_id=?',[$other['id'],$tenant['id']]);
            }
            foreach(array_unique(array_filter($tables)) as $table)$app->exec('INSERT IGNORE INTO fisitaap_ticket_tables180(tenant_id,branch_id,ticket_id,table_id) VALUES(?,?,?,?)',[$tenant['id'],$branch['id'],$id,$table]);
            $net=(float)$app->one('SELECT COALESCE(SUM(line_subtotal),0) total FROM pos_ticket_items WHERE ticket_id=?',[$id])['total'];$adjustment=round($service-round($net*(float)$main['service_rate']/100,2),2);
            $app->exec('UPDATE pos_tickets SET discount_total=?,notes=?,service_adjustment180=?,revision=revision+1 WHERE id=? AND tenant_id=?',[$discount,mb_substr(trim($notes),0,1000),$adjustment,$id,$tenant['id']]);pos_recalculate_148($app,$id);
            qa180_audit($app,$user,'table_join','pos_ticket',$id,'Unió '.$destination['name'].' a la cuenta principal '.$main['ticket_number'].' de '.$original['name'].'; conserva productos y comandas');
        }else{
            if(count($oldTables)>1)throw new DomainException('Esta cuenta tiene mesas unidas. Conserva su grupo o termina el cobro antes de trasladarla.');
            foreach(qa180_table_accounts($app,$tenant,$branch,$target,true) as $other)if((int)$other['id']!==$id&&$destination['space_type']!=='bar')throw new DomainException('La mesa destino ya tiene una cuenta. Usa Unir mesas para combinar cuentas sin pagos parciales.');
            if($destination['space_type']==='bar'&&!trim((string)$main['display_label']))throw new DomainException('La barra necesita una cuenta identificada por comensal. Abre la cuenta desde la barra.');
            $app->exec('DELETE FROM fisitaap_ticket_tables180 WHERE tenant_id=? AND ticket_id=?',[$tenant['id'],$id]);$app->exec('INSERT INTO fisitaap_ticket_tables180(tenant_id,branch_id,ticket_id,table_id) VALUES(?,?,?,?)',[$tenant['id'],$branch['id'],$id,$target]);
            $app->exec('UPDATE pos_tickets SET table_id=?,sale_type="table",revision=revision+1 WHERE id=? AND tenant_id=?',[$target,$id,$tenant['id']]);qa180_audit($app,$user,'table_move','pos_ticket',$id,'Trasladó '.$main['ticket_number'].' a '.$destination['name'].'; conserva sus cargos originales');
        }
        qa180_sync_tables($app,(int)$tenant['id'],array_unique([...$oldTables,$target]));
        $app->exec('INSERT INTO fisitaap_operations180(tenant_id,branch_id,user_id,operation_key,payload_hash,kind,result_id) VALUES(?,?,?,?,?,?,?)',[$tenant['id'],$branch['id'],$user['id'],$key,$hash,$join?'table_join':'table_move',$id]);$app->db->commit();return $id;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
