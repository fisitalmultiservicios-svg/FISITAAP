<?php
declare(strict_types=1);

function qa180_order_states():array {return ['new'=>'Nuevo','confirmed'=>'Aceptado','preparing'=>'Preparación','ready'=>'Listo','sent'=>'Despacho / Express','delivered'=>'Entregado','cancelled'=>'Cancelado'];}

/** Copies immutable checkout amounts. Stock was already taken by checkout. */
function qa180_order_receive(App $app,array $tenant,array $actor,int $id,bool $automatic=false):int {
    $owns=!$app->db->inTransaction();if($owns)$app->db->beginTransaction();
    try {
        $scope=$app->one('SELECT branch_id FROM orders WHERE id=? AND tenant_id=?',[$id,$tenant['id']]);if(!$scope)throw new DomainException('Pedido no disponible.');
        $branch=$app->one('SELECT * FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$scope['branch_id'],$tenant['id']]);if(!$branch)throw new DomainException('Sucursal no disponible.');
        $order=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$tenant['id']]);
        $old=$app->one('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=? AND tenant_id=?',[$id,$tenant['id']]);
        if($old){if($owns)$app->db->commit();return (int)$old['ticket_id'];}
        if(in_array($order['status'],['delivered','cancelled'],true))throw new DomainException('Este pedido ya terminó.');
        if(empty($tenant['physical_store_enabled'])){
            if($order['status']==='new'){
                $app->exec('UPDATE orders SET status="confirmed",revision180=revision180+1 WHERE id=?',[$id]);
                qa180_audit($app,$actor,'web_order_receive','order',$id,($automatic?'Aceptó automáticamente':'Aceptó').' el pedido web #'.$order['order_number'].' para su preparación');
            }
            if($owns)$app->db->commit();return 0;
        }
        $items=$app->all('SELECT i.*,p.cost FROM order_items i LEFT JOIN products p ON p.id=i.product_id WHERE i.order_id=? ORDER BY i.id',[$id]);if(!$items)throw new DomainException('El pedido no contiene artículos.');
        $couponUse=$app->one('SELECT coupon_id FROM fisitaap_coupon_uses180 WHERE order_id=? AND tenant_id=?',[$id,$tenant['id']]);
        $newOrder=$order['status']==='new';$status=$newOrder?'confirmed':$order['status'];
        $app->exec('INSERT INTO pos_tickets(tenant_id,branch_id,customer_id,ticket_number,sale_type,opened_by,source_order_id,fulfillment180,display_label,delivery_address,notes,discount_total,coupon_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tenant['id'],$branch['id'],$order['user_id'],'WEB-'.$order['order_number'].'-'.$id,'catalog',$actor['id'],$id,$status,'Web #'.$order['order_number'],$order['address'],$order['notes'],$order['discount_total'],$couponUse['coupon_id']??null]);$ticket=(int)$app->db->lastInsertId();
        $weights=[];foreach($items as $item)$weights[$item['id']]=pos_cents_148($item['line_subtotal']);$fallback=pos_allocate_148(pos_cents_148($order['discount_total']),$weights);
        $newIds=[];foreach($items as $item){
            $recipe=json_decode((string)($item['pricing_json180']??''),true)?:['base'=>pos_cents_148($item['unit_price']),'extra'=>0,'rate'=>(int)round((float)$item['tax_rate']*100),'included'=>false,'bogo'=>false];
            $recipe['coupon_discount180']=$item['pricing_json180']!==null?(int)$item['discount_cents180']:$fallback[$item['id']];
            $app->exec('INSERT INTO pos_ticket_items(ticket_id,product_id,product_name,quantity,unit_price,unit_cost,tax_rate,line_subtotal,line_tax,options_json,notes,pricing_json,kitchen_status,sent_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,"sent",NOW())',[$ticket,$item['product_id'],$item['product_name'],$item['quantity'],$item['unit_price'],$item['cost']??0,$item['tax_rate'],$item['line_subtotal'],$item['line_tax'],$item['options_json'],$item['notes'],json_encode($recipe,JSON_THROW_ON_ERROR)]);$newIds[]=(int)$app->db->lastInsertId();
        }
        if((float)$order['delivery_total']>0)$app->exec('INSERT INTO pos_ticket_items(ticket_id,product_name,quantity,unit_price,line_subtotal,kitchen_status) VALUES(?,"Entrega del pedido web",1,?,?,"served")',[$ticket,$order['delivery_total'],$order['delivery_total']]);
        pos_recalculate_148($app,$ticket);$copied=$app->one('SELECT total FROM pos_tickets WHERE id=?',[$ticket]);if(pos_cents_148($copied['total'])!==pos_cents_148($order['total']))throw new DomainException('El total del pedido requiere revisión antes de recibirlo.');
        $app->exec('INSERT INTO fisitaap_order_receipts180(tenant_id,branch_id,order_id,ticket_id,received_by) VALUES(?,?,?,?,?)',[$tenant['id'],$branch['id'],$id,$ticket,$automatic?null:$actor['id']]);
        $app->exec('UPDATE orders SET status=?,revision180=revision180+1 WHERE id=?',[$status,$id]);
        if($newOrder)qa180_print_commands($app,$tenant,$branch,$actor,$ticket,$newIds,'web-order-'.$id.'-command');
        qa180_audit($app,$actor,'web_order_receive','order',$id,($automatic?'Recibió automáticamente':'Recibió').' el pedido web #'.$order['order_number'].'; abrió una cuenta sin repetir el inventario');
        if($owns)$app->db->commit();return $ticket;
    }catch(Throwable $error){if($owns&&$app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_order_auto_receive(App $app,array $tenant,array $branch,int $id):void {
    if(!empty($branch['web_order_acceptance']))return;
    $actor=$app->one('SELECT * FROM users WHERE tenant_id=? AND role="tenant_admin" AND is_active=1 ORDER BY id LIMIT 1',[$tenant['id']]);if(!$actor)throw new DomainException('El negocio debe tener un dueño activo para recibir pedidos automáticamente.');
    qa180_order_receive($app,$tenant,$actor,$id,true);
}
function qa180_order_prepaid(App $app,array $tickets):int {
    $remaining=0;foreach($tickets as $ticket){if(empty($ticket['source_order_id']))continue;
        $used=$app->one('SELECT COALESCE(-SUM(amount),0) amount FROM fisitaap_r3_gift_entries WHERE order_id=? AND kind="use"',[$ticket['source_order_id']]);
        $settled=$app->one('SELECT COALESCE(SUM(amount),0) amount FROM pos_payments WHERE ticket_id=? AND payment_method="Tarjeta regalo prepagada"',[$ticket['id']]);
        $remaining+=max(0,pos_cents_148($used['amount'])-pos_cents_148($settled['amount']));
    }return $remaining;
}
function qa180_order_before_status(App $app,array $order,string $status,int $actor):void {
    $receipt=$app->one('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=? AND tenant_id=?',[$order['id'],$order['tenant_id']]);if(!$receipt)return;
    $ticket=$app->one('SELECT * FROM pos_tickets WHERE id=? FOR UPDATE',[$receipt['ticket_id']]);
    if($status==='cancelled'){
        if((float)$ticket['paid_total']>0||$app->one('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND paid_quantity>0 LIMIT 1',[$ticket['id']]))throw new DomainException('El pedido ya tiene cobros. Revisa la devolución con administración; no se puede cancelar como un pedido sin pagar.');
        $app->exec('UPDATE pos_tickets SET status="void",fulfillment180="cancelled",revision=revision+1 WHERE id=?',[$ticket['id']]);
    }else{
        if($ticket['status']==='void')throw new DomainException('La cuenta de este pedido ya está cancelada.');
        $app->exec('UPDATE pos_tickets SET fulfillment180=? WHERE id=?',[$status,$ticket['id']]);
        if(in_array($status,['preparing','ready','delivered'],true))$app->exec('UPDATE pos_ticket_items SET kitchen_status=? WHERE ticket_id=? AND product_id IS NOT NULL',[$status==='delivered'?'served':$status,$ticket['id']]);
    }
}
function qa180_orders(App $app,array $user,array $tenant,string $section):void {
    $branch=physical_branch_v14($app,$tenant);if(!$branch)throw new DomainException('Selecciona una sucursal activa.');
    if(is_post()){
        verify_csrf();$action=(string)($_POST['action']??'status');
        try {
            qa180_permission($app,$user,$section,'write');$id=(int)($_POST['id']??0);$app->db->beginTransaction();$app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$branch['id'],$tenant['id']]);
            if(($_POST['origin']??'web')==='web'){
                $order=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=? AND branch_id=? FOR UPDATE',[$id,$tenant['id'],$branch['id']]);if(!$order)throw new DomainException('El pedido no pertenece a esta sucursal.');
                $received=$app->one('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[$id]);
                if($action==='receive'&&$received){$app->db->commit();flash('info','El pedido ya estaba recibido.');redirect(url('admin/'.$section));}
                if((int)($_POST['revision']??-1)!==(int)$order['revision180'])throw new DomainException('El pedido cambió. Recarga antes de continuar.');
                if($action==='receive'){qa180_order_receive($app,$tenant,$user,$id);}
                else {
                    $next=(string)($_POST['status']??'');$transitions=['new'=>['cancelled'],'confirmed'=>['preparing','cancelled'],'preparing'=>['ready','cancelled'],'ready'=>['sent','delivered','cancelled'],'sent'=>['delivered'],'delivered'=>[],'cancelled'=>[]];
                    if(!in_array($next,$transitions[$order['status']]??[],true))throw new DomainException('Ese cambio no corresponde al estado actual. Primero recibe los pedidos nuevos.');
                    order_status_qa($app,(int)$tenant['id'],$id,$next,(int)$user['id']);qa180_audit($app,$user,'order_status','order',$id,'Cambió el pedido #'.$order['order_number'].' a '.qa180_order_states()[$next]);
                }
            }else {
                $ticket=pos_ticket_get_148($app,$tenant,$branch,$id,true);if($ticket['source_order_id'])throw new DomainException('Actualiza este pedido desde su tarjeta web.');
                if((int)($_POST['revision']??-1)!==(int)$ticket['revision'])throw new DomainException('La cuenta cambió. Recarga antes de continuar.');
                $next=(string)($_POST['status']??'');$transitions=['new'=>['confirmed'],'confirmed'=>['preparing'],'preparing'=>['ready'],'ready'=>['sent','delivered'],'sent'=>['delivered']];if(!in_array($next,$transitions[$ticket['fulfillment180']]??[],true))throw new DomainException('Ese cambio no corresponde al estado actual.');
                if($next==='confirmed'){$ids=array_column($app->all('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND kitchen_status="pending"',[$id]),'id');$app->exec('UPDATE pos_ticket_items SET kitchen_status="sent",sent_at=NOW() WHERE ticket_id=? AND kitchen_status="pending"',[$id]);if($ids)qa180_print_commands($app,$tenant,$branch,$user,$id,$ids,'pos-board-'.$id.'-'.hash('sha256',implode(',',$ids)));}
                else $app->exec('UPDATE pos_ticket_items SET kitchen_status=? WHERE ticket_id=? AND kitchen_status NOT IN("pending","served")',[$next==='delivered'?'served':($next==='sent'?'ready':$next),$id]);
                $app->exec('UPDATE pos_tickets SET fulfillment180=?,revision=revision+1 WHERE id=?',[$next,$id]);qa180_audit($app,$user,'kitchen_status','pos_ticket',$id,'Cambió la preparación de '.$ticket['ticket_number'].' a '.qa180_order_states()[$next]);
            }
            $app->db->commit();flash('success','Pedido actualizado.');
        }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();flash('error',$error instanceof PDOException?'No se completó el cambio. Revisa el pedido antes de repetir.':$error->getMessage());}
        redirect(url('admin/'.$section));
    }
    $origin=(string)($_GET['origin']??'');$days=(int)($_GET['days']??2);$days=in_array($days,[2,7,30],true)?$days:2;$from=date('Y-m-d H:i:s',strtotime('-'.$days.' days'));$cards=[];
    if($origin===''||$origin==='web')foreach($app->all('SELECT o.*,r.ticket_id FROM orders o LEFT JOIN fisitaap_order_receipts180 r ON r.order_id=o.id WHERE o.tenant_id=? AND o.branch_id=? AND o.created_at>=? ORDER BY o.created_at LIMIT 200',[$tenant['id'],$branch['id'],$from]) as $row)$cards[]=['origin'=>'web','id'=>$row['id'],'number'=>$row['order_number'],'customer'=>$row['customer_name'],'total'=>$row['total'],'state'=>$row['status'],'delivery'=>$row['delivery_type'],'payment'=>$row['payment_method'],'created'=>$row['created_at'],'revision'=>$row['revision180'],'ticket'=>$row['ticket_id']];
    if($origin!=='web'){$params=[$tenant['id'],$branch['id'],$from];$where='';if(in_array($origin,['table','quick','express'],true)){$where=' AND t.sale_type=?';$params[]=$origin;}
        foreach($app->all('SELECT t.*,u.name customer,r.name table_name FROM pos_tickets t LEFT JOIN users u ON u.id=t.customer_id LEFT JOIN restaurant_tables r ON r.id=t.table_id WHERE t.tenant_id=? AND t.branch_id=? AND t.created_at>=? AND t.source_order_id IS NULL AND EXISTS(SELECT 1 FROM pos_ticket_items i WHERE i.ticket_id=t.id)'.$where.' ORDER BY t.created_at LIMIT 200',$params) as $row)$cards[]=['origin'=>'pos','id'=>$row['id'],'number'=>$row['ticket_number'],'customer'=>$row['customer']?:'Consumidor final','total'=>$row['total'],'state'=>$row['status']==='void'?'cancelled':$row['fulfillment180'],'delivery'=>$row['table_name']?:$row['sale_type'],'payment'=>$row['status']==='paid'?'Cobrado':'Cuenta pendiente','created'=>$row['created_at'],'revision'=>$row['revision'],'ticket'=>$row['id']];
    }
    $items=[];$webIds=[];$posIds=[];foreach($cards as $card)if($card['origin']==='web')$webIds[]=$card['id'];else $posIds[]=$card['id'];
    foreach([['web','order_items','order_id',$webIds],['pos','pos_ticket_items','ticket_id',$posIds]] as [$kind,$table,$fk,$ids])if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));foreach($app->all('SELECT '.$fk.' parent,product_name,quantity,options_json,notes FROM '.$table.' WHERE '.$fk.' IN('.$marks.') ORDER BY id',$ids) as $item)$items[$kind.'-'.$item['parent']][]=$item;}
    $may=true;try{qa180_permission($app,$user,$section,'write');}catch(DomainException){$may=false;}
    admin_shell_start('Pedidos · '.$branch['name'],$user,$tenant,$section);echo '<section class="card admin-card"><form class="qa180-filter" method="get"><label>Origen<select class="input" name="origin">';foreach([''=>'Todos','web'=>'Catálogo web','table'=>'Mesa','quick'=>'Venta rápida','express'=>'Express'] as $key=>$label)echo '<option value="'.$key.'" '.($origin===$key?'selected':'').'>'.$label.'</option>';echo '</select></label><label>Periodo<select class="input" name="days">';foreach([2,7,30] as $day)echo '<option value="'.$day.'" '.($days===$day?'selected':'').'>Últimos '.$day.' días</option>';echo '</select></label><button class="btn">Filtrar</button></form><p>'.(!empty($tenant['physical_store_enabled'])?'Recibir abre una cuenta y envía la comanda una sola vez. El estado de preparación y el cobro se registran por separado.':'Recibir acepta el pedido para su preparación. Los pagos coordinados con el cliente se gestionan fuera de la caja, que este negocio no tiene habilitada.').' Hasta 200 pedidos por origen; consulta periodos más amplios en Reportes.</p></section><div class="qa180-kanban">';
    foreach(qa180_order_states() as $state=>$label){echo '<section class="qa180-kanban-column"><h2>'.$label.'</h2>';foreach($cards as $card){if($card['state']!==$state)continue;$minutes=max(0,(int)((time()-strtotime($card['created']))/60));echo '<article class="card admin-card"><h3>'.e($card['number']).'</h3><p>'.e($card['customer']).'<br>'.e($card['origin']==='web'?'Catálogo web':$card['delivery']).' · '.e($branch['name']).'</p><strong>'.money((float)$card['total']).'</strong><p>'.e($card['payment']).' · '.$minutes.' min</p><details><summary>Artículos</summary>';foreach($items[$card['origin'].'-'.$card['id']]??[] as $item)echo '<p>'.e($item['quantity'].' × '.$item['product_name']).order_item_details_html($item).'</p>';echo '</details>';
            if($card['ticket'])echo '<a class="btn btn-light" href="'.e(url('admin/ventas?screen=order&ticket='.(int)$card['ticket'])).'">Ver cuenta</a>';
            if($may){if($state==='new'&&$card['origin']==='web')echo qa180_order_form($card,'receive','confirmed','Recibir');else{if(!empty($tenant['physical_store_enabled'])&&$card['origin']==='web'&&!$card['ticket']&&in_array($state,['confirmed','preparing','ready','sent'],true))echo qa180_order_form($card,'receive',$state,'Vincular cuenta de caja');$next=['new'=>'confirmed','confirmed'=>'preparing','preparing'=>'ready','ready'=>'sent','sent'=>'delivered'][$state]??null;if($next)echo qa180_order_form($card,'status',$next,qa180_order_states()[$next]);}if($card['origin']==='web'&&in_array($state,['new','confirmed','preparing','ready'],true))echo qa180_order_form($card,'status','cancelled','Cancelar');}
            if($card['origin']==='web'&&$state==='ready'&&in_array($card['delivery'],['delivery','coordinated'],true)&&r3_report_can($app,$user,$tenant,'express'))echo '<a class="btn" href="'.e(url('admin/express?order='.(int)$card['id'])).'">Publicar / asignar Express</a>';echo '</article>';}
        echo '</section>';}
    echo '</div>';admin_shell_end();
}
function qa180_order_form(array $card,string $action,string $status,string $label):string {return '<form method="post">'.csrf_field().pos_hidden_148('origin',$card['origin']).pos_hidden_148('id',$card['id']).pos_hidden_148('revision',$card['revision']).pos_hidden_148('action',$action).pos_hidden_148('status',$status).'<button class="btn btn-light">'.e($label).'</button></form>';}
