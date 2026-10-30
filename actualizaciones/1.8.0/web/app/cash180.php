<?php
declare(strict_types=1);

function qa180_denominations():array {return [5,10,25,50,100,500,1000,2000,5000,10000,20000];}
function qa180_shift_open(App $app,array $user,array $branch,array $form):int {
    qa180_permission($app,$user,'turnos');$register=qa180_native($app,$user,true);
    if((int)$register['branch_id']!==(int)$branch['id'])throw new DomainException('Selecciona la sucursal asignada a este equipo.');
    $key=(string)($form['opening_key']??'');$cash=pos_cents_148($form['opening_cash']??0);
    if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('Recarga el formulario de apertura.');
    $app->db->beginTransaction();
    try{
        $app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$branch['id'],$user['tenant_id']]);
        $app->one('SELECT id FROM fisitaap_registers180 WHERE id=? AND tenant_id=? FOR UPDATE',[$register['id'],$user['tenant_id']]);
        $prior=$app->one('SELECT * FROM pos_shifts WHERE tenant_id=? AND user_id=? AND opening_key=?',[$user['tenant_id'],$user['id'],$key]);
        if($prior){if((int)$prior['register_id']!==(int)$register['id']||pos_cents_148($prior['opening_cash'])!==$cash)throw new DomainException('Esta apertura ya corresponde a otra caja o monto.');$app->db->commit();return (int)$prior['id'];}
        if($app->one('SELECT id FROM pos_shifts WHERE tenant_id=? AND register_id=? AND status="open"',[$user['tenant_id'],$register['id']]))throw new DomainException('Esta caja ya tiene un turno abierto.');
        $app->exec('INSERT INTO pos_shifts(tenant_id,branch_id,user_id,name,opening_cash,status,opened_at,register_id,device_id,opening_key) VALUES(?,?,?,?,?,"open",NOW(),?,?,?)',[$user['tenant_id'],$branch['id'],$user['id'],$register['name'],$cash/100,$register['id'],$register['device_id'],$key]);$id=(int)$app->db->lastInsertId();
        if($register['device_id'])$app->exec('INSERT INTO fisitaap_r2_shift_links(device_id,shift_uuid,shift_id) VALUES(?,?,?)',[$register['device_id'],qa180_uuid(),$id]);
        qa180_audit($app,$user,'shift_open','shift',$id,'Apertura de '.$register['name'].' con '.money($cash/100));
        $app->db->commit();return $id;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_shift_close(App $app,array $user,array $branch,int $id,array $form):int {
    qa180_permission($app,$user,'turnos');$register=qa180_native($app,$user,true);$key=(string)($form['closing_key']??'');
    if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('Recarga el contador de cierre.');
    $denoms=[];$cash=0;
    foreach(qa180_denominations() as $value){$count=(string)($form['denominations'][$value]??'0');if(!preg_match('/^\d{1,6}$/D',$count))throw new DomainException('Las cantidades de monedas y billetes deben ser números enteros positivos.');$denoms[$value]=(int)$count;$cash+=$value*(int)$count*100;}
    $cash+=pos_cents_148($form['other_cash']??0);if($cash>99999999900)throw new DomainException('El conteo supera el límite permitido.');
    $hash=hash('sha256',json_encode([$cash,$denoms],JSON_THROW_ON_ERROR));
    $app->db->beginTransaction();
    try{
        $app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$branch['id'],$user['tenant_id']]);
        $shift=$app->one('SELECT * FROM pos_shifts WHERE id=? AND tenant_id=? AND branch_id=? AND register_id=? FOR UPDATE',[$id,$user['tenant_id'],$branch['id'],$register['id']]);
        if(!$shift||(int)$shift['user_id']!==(int)$user['id'])throw new DomainException('El turno no pertenece a tu caja o usuario.');
        if($shift['status']==='closed'){
            if($shift['closing_key']!==$key||!hash_equals((string)$shift['closing_payload_hash'],$hash))throw new DomainException('El turno ya se cerró con otro conteo. Revisa el detalle.');
            $app->db->commit();return $id;
        }
        if(qa180_pending_shift($app,$shift))throw new DomainException('Hay cuentas o mesas abiertas de este turno. Cobra, traslada o cancela esas cuentas antes de cerrar.');
        $expected=qa180_cash_expected($app,$shift);
        $app->exec('UPDATE pos_shifts SET status="closed",closed_at=NOW(),closing_cash=?,expected_cash=?,difference_amount=?,closing_key=?,closing_payload_hash=?,denominations_json=? WHERE id=? AND tenant_id=?',[$cash/100,$expected/100,($cash-$expected)/100,$key,$hash,json_encode($denoms,JSON_THROW_ON_ERROR),$id,$user['tenant_id']]);
        $app->exec('UPDATE pos_tickets SET shift_id=NULL,register_id=NULL WHERE tenant_id=? AND shift_id=? AND table_id IS NULL AND paid_total=0 AND status NOT IN("paid","void","merged") AND NOT EXISTS(SELECT 1 FROM pos_ticket_items i WHERE i.ticket_id=pos_tickets.id)',[$user['tenant_id'],$id]);
        qa180_audit($app,$user,'shift_close','shift',$id,'Cierre de '.$shift['name'].': contado '.money($cash/100).', diferencia '.money(($cash-$expected)/100));
        $app->db->commit();return $id;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_shifts(App $app,array $user,array $tenant):void {
    $branch=physical_branch_v14($app,$tenant);$register=qa180_native($app,$user);$closed=null;
    if(is_post()){
        verify_csrf();try{
            if(($_POST['action']??'')==='open')qa180_shift_open($app,$user,$branch,$_POST);
            elseif(($_POST['action']??'')==='close')$closed=qa180_shift_close($app,$user,$branch,(int)($_POST['id']??0),$_POST);
            else throw new DomainException('Operación de turno desconocida.');
            flash('success',$closed?'Turno cerrado. El detalle se conserva en Turnos.':'Turno abierto.');
        }catch(Throwable $error){flash('error',$error instanceof PDOException?'No se pudo guardar el turno. Revisa su estado antes de intentar de nuevo.':$error->getMessage());}
        $return=(string)($_POST['return_to_r18']??'');if(!$closed&&preg_match('~^admin/(?:ventas|mesas)(?:\?[^#\r\n]{0,400})?$~D',$return))redirect(url($return));
        redirect(url('admin/turnos'.($closed?'?detail='.$closed.'&closed=1':'')));
    }
    $current=$register?$app->one('SELECT * FROM pos_shifts WHERE tenant_id=? AND branch_id=? AND register_id=? AND status="open" ORDER BY id DESC LIMIT 1',[$tenant['id'],$branch['id'],$register['id']]):null;
    admin_shell_start('Turnos',$user,$tenant,'turnos');
    if($register){
        echo '<section class="card admin-card"><h2>'.e($register['name']).'</h2>';
        if($current&&(int)$current['user_id']!==(int)$user['id']){echo '<p>Este turno lo abrió otro usuario. Solo esa persona puede cerrarlo.</p>';}elseif($current){
            $expected=qa180_cash_expected($app,$current);
            echo '<form method="post" data-cash-counter data-expected="'.$expected.'">'.csrf_field().pos_hidden_148('action','close').pos_hidden_148('id',$current['id']).pos_hidden_148('closing_key',bin2hex(random_bytes(16))).'<h3>Contar efectivo y cerrar</h3><div class="qa180-coins">';
            foreach(qa180_denominations() as $value)echo '<label>'.money((float)$value).'<input class="input" name="denominations['.$value.']" type="number" value="0" min="0" max="999999" step="1" data-denomination="'.$value.'"></label>';
            echo '</div>'.field('Otro efectivo','other_cash',0,false,'number','.01').'<p>Contado: <strong data-counted>₡0,00</strong> · Diferencia: <strong data-difference>'.money(-$expected/100).'</strong></p><button class="btn">Cerrar turno</button></form>';
        }else echo r2_form('open').pos_hidden_148('opening_key',bin2hex(random_bytes(16))).field('Fondo inicial de caja','opening_cash',0,true,'number','.01').'<button class="btn">Iniciar turno</button></form>';
        echo '</section>';
    }else echo '<section class="card admin-card"><p>Consulta aquí los turnos. Para abrir, contar o cerrar efectivo, usa FISITAAP Windows o Android con una caja autorizada.</p></section>';
    [$from,$to]=valid_report_dates_v1();$manage=in_array($user['role'],['tenant_admin','manager'],true);$who=$manage?(int)($_GET['user_id']??0):(int)$user['id'];
    $rows=$app->all('SELECT s.*,u.name cashier,r.name register_name FROM pos_shifts s JOIN users u ON u.id=s.user_id LEFT JOIN fisitaap_registers180 r ON r.id=s.register_id AND r.tenant_id=s.tenant_id WHERE s.tenant_id=? AND s.branch_id=? AND s.opened_at>=? AND s.opened_at<DATE_ADD(?,INTERVAL 1 DAY) AND (?=0 OR s.user_id=?) ORDER BY s.id DESC LIMIT 200',[$tenant['id'],$branch['id'],$from,$to,$who,$who]);
    echo '<section class="card admin-card"><h2>Historial de turnos</h2><form method="get"><div class="split">'.field('Desde','from',$from,true,'date').field('Hasta','to',$to,true,'date').'</div><button class="btn btn-light">Filtrar</button></form><div class="table-wrap"><table class="table"><tr><th>Caja / cajero</th><th>Apertura</th><th>Estado</th><th>Contado</th><th>Diferencia</th><th></th></tr>';
    foreach($rows as $row)echo '<tr><td>'.e(($row['register_name']??$row['name']).' · '.$row['cashier']).'</td><td>'.e($row['opened_at']).'</td><td>'.e($row['status']==='open'?'Abierto':'Cerrado').'</td><td>'.money((float)$row['closing_cash']).'</td><td>'.money((float)$row['difference_amount']).'</td><td><a class="btn btn-light" href="?detail='.(int)$row['id'].'">Detalle</a></td></tr>';
    echo '</table></div></section>';
    $detail=$app->one('SELECT * FROM pos_shifts WHERE id=? AND tenant_id=? AND branch_id=? AND (?=0 OR user_id=?)',[(int)($_GET['detail']??0),$tenant['id'],$branch['id'],$who,$who]);
    if($detail){
        echo '<section class="card admin-card"><h2>Detalle de turno · '.e($detail['name']).'</h2><p>Fondo inicial: '.money((float)$detail['opening_cash']).' · Esperado: '.money((float)$detail['expected_cash']).' · Contado: '.money((float)$detail['closing_cash']).' · Diferencia: '.money((float)$detail['difference_amount']).'</p><div class="table-wrap"><table class="table"><tr><th>Movimiento</th><th>Importe</th><th>Motivo</th><th>Fecha</th></tr>';
        foreach($app->all('SELECT * FROM fisitaap_money_entries180 WHERE tenant_id=? AND shift_id=? ORDER BY id',[$tenant['id'],$detail['id']]) as $entry)echo '<tr><td>'.e($entry['kind']).'</td><td>'.money((float)$entry['amount']).'</td><td>'.e($entry['reason']).'</td><td>'.e($entry['created_at']).'</td></tr>';
        echo '</table></div><a class="btn btn-light" href="'.url('admin/turno-resumen?id='.$detail['id']).'">Imprimir resumen de cierre</a></section>';
    }
    admin_shell_end();
}
function qa180_cash_move(App $app,array $user,array $tenant):void {
    $branch=physical_branch_v14($app,$tenant);qa180_permission($app,$user,'turnos');qa180_native($app,$user,true);
    if(!is_post()){redirect(url('admin/ventas'));}verify_csrf();
    try{
        if(empty($_POST['confirmed']))throw new DomainException('Confirma el movimiento antes de aplicarlo.');
        $out=($_POST['direction']??'')==='out';$amount=pos_cents_148($_POST['amount']??0);
        $app->db->beginTransaction();
        $id=qa180_money($app,$user,$branch,0,$out?-$amount:$amount,(string)($_POST['operation_key']??''),$out?'cash_out':'cash_in',trim((string)($_POST['reason']??'')),trim((string)($_POST['reference']??'')));
        if(!$app->one('SELECT id FROM audit_logs WHERE tenant_id=? AND entity_type="cash_movement" AND entity_id=?',[$tenant['id'],$id]))qa180_audit($app,$user,$out?'cash_out':'cash_in','cash_movement',$id,($out?'Salida':'Entrada').' de '.money($amount/100).' · '.trim((string)$_POST['reason']));
        $app->db->commit();flash('success','Movimiento de efectivo registrado.');
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();flash('error',$error instanceof PDOException?'No se confirmó el movimiento. Revisa el turno antes de repetir.':$error->getMessage());}
    redirect(url('admin/ventas'));
}
