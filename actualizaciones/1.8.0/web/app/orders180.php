<?php
declare(strict_types=1);
require_once __DIR__.'/kanban180.php';

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
        // A delivery created from POS already reserved its stock as a POS ticket.
        // It has no online_order movements to refund through the web helper.
        if($ticket['sale_type']==='express'&&$order['order_number']==='POS-'.$ticket['id']&&!$app->one('SELECT id FROM inventory_movements WHERE reference_type="pos_void" AND reference_id=? LIMIT 1',[$ticket['id']])){
            foreach($app->all('SELECT i.*,p.track_inventory FROM pos_ticket_items i JOIN products p ON p.id=i.product_id WHERE i.ticket_id=?',[$ticket['id']]) as $item)if($item['track_inventory'])stock_change_v14($app,(int)$order['tenant_id'],(int)$ticket['branch_id'],(int)$item['product_id'],(float)$item['quantity'],'pos_release','pos_void',(int)$ticket['id'],$actor,'Cancelación de '.$ticket['ticket_number'],(float)$item['unit_cost']);
        }
        $app->exec('UPDATE pos_tickets SET status="void",fulfillment180="cancelled",revision=revision+1 WHERE id=?',[$ticket['id']]);
    }else{
        if($ticket['status']==='void')throw new DomainException('La cuenta de este pedido ya está cancelada.');
        $app->exec('UPDATE pos_tickets SET fulfillment180=?,fulfillment_state=?,revision=revision+1 WHERE id=?',[$status,match($status){'confirmed'=>'pending','sent'=>'in_transit',default=>$status},$ticket['id']]);
        if(in_array($status,['preparing','ready','delivered'],true))$app->exec('UPDATE pos_ticket_items SET kitchen_status=? WHERE ticket_id=? AND product_id IS NOT NULL',[$status==='delivered'?'served':$status,$ticket['id']]);
    }
}
function qa180_orders(App $app,array $user,array $tenant,string $section):void {
    $branch=physical_branch_v14($app,$tenant);if(!$branch)throw new DomainException('Selecciona una sucursal activa.');
    if(is_post()){
        verify_csrf();$action=(string)($_POST['action']??'status');
        try {
            $notice='Pedido actualizado.';
            qa180_permission($app,$user,$section,'write');$id=(int)($_POST['id']??0);$app->db->beginTransaction();$app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$branch['id'],$tenant['id']]);
            if(($_POST['origin']??'web')==='web'){
                $order=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=? AND branch_id=? FOR UPDATE',[$id,$tenant['id'],$branch['id']]);if(!$order)throw new DomainException('El pedido no pertenece a esta sucursal.');
                if($action==='dispatch'&&$app->one('SELECT id FROM delivery_jobs WHERE order_id=? AND tenant_id=? AND status NOT IN("cancelled","expired") FOR UPDATE',[$id,$tenant['id']])){$app->db->commit();flash('info','Este pedido ya tiene un Express. Consulta Express pendientes.');redirect(url('admin/'.$section));}
                $received=$app->one('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[$id]);
                if($action==='receive'&&$received){$app->db->commit();flash('info','El pedido ya estaba recibido.');redirect(url('admin/'.$section));}
                if((int)($_POST['revision']??-1)!==(int)$order['revision180'])throw new DomainException('El pedido cambió. Recarga antes de continuar.');
                if(in_array($action,['prepare','prepared','handover','dispatch'],true))$notice=qa180_board_web_action($app,$user,$tenant,$branch,$order,$action);
                elseif($action==='receive'){qa180_order_receive($app,$tenant,$user,$id);}
                else {
                    $next=(string)($_POST['status']??'');$transitions=['new'=>['cancelled'],'confirmed'=>['preparing','cancelled'],'preparing'=>['ready','cancelled'],'ready'=>['sent','delivered','cancelled'],'sent'=>['delivered'],'delivered'=>[],'cancelled'=>[]];
                    if(!in_array($next,$transitions[$order['status']]??[],true))throw new DomainException('Ese cambio no corresponde al estado actual. Primero recibe los pedidos nuevos.');
                    if($next==='delivered'&&$app->one('SELECT id FROM delivery_jobs WHERE order_id=? AND status NOT IN("cancelled","expired")',[$id]))throw new DomainException('Esta entrega se confirma desde el portal del motorizado con su PIN.');
                    order_status_qa($app,(int)$tenant['id'],$id,$next,(int)$user['id']);qa180_audit($app,$user,'order_status','order',$id,'Cambió el pedido #'.$order['order_number'].' a '.qa180_order_states()[$next]);
                }
            }else {
                $ticket=pos_ticket_get_148($app,$tenant,$branch,$id,true);
                if($action==='dispatch'&&$ticket['source_order_id']&&$app->one('SELECT id FROM delivery_jobs WHERE order_id=? AND tenant_id=? AND status NOT IN("cancelled","expired") FOR UPDATE',[$ticket['source_order_id'],$tenant['id']])){$app->db->commit();flash('info','Este pedido ya tiene un Express. Consulta Express pendientes.');redirect(url('admin/'.$section));}
                if($ticket['source_order_id'])throw new DomainException('Actualiza este pedido desde su tarjeta vinculada.');
                if((int)($_POST['revision']??-1)!==(int)$ticket['revision'])throw new DomainException('La cuenta cambió. Recarga antes de continuar.');
                if(in_array($action,['prepare','prepared','handover','dispatch'],true))$notice=qa180_board_pos_action($app,$user,$tenant,$branch,$ticket,$action);
                else {
                $next=(string)($_POST['status']??'');$transitions=['new'=>['confirmed'],'confirmed'=>['preparing'],'preparing'=>['ready'],'ready'=>['sent','delivered'],'sent'=>['delivered']];if(!in_array($next,$transitions[$ticket['fulfillment180']]??[],true))throw new DomainException('Ese cambio no corresponde al estado actual.');
                if($next==='confirmed'){$ids=array_column($app->all('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND kitchen_status="pending"',[$id]),'id');$app->exec('UPDATE pos_ticket_items SET kitchen_status="sent",sent_at=NOW() WHERE ticket_id=? AND kitchen_status="pending"',[$id]);if($ids)qa180_print_commands($app,$tenant,$branch,$user,$id,$ids,'pos-board-'.$id.'-'.hash('sha256',implode(',',$ids)));}
                else $app->exec('UPDATE pos_ticket_items SET kitchen_status=? WHERE ticket_id=? AND kitchen_status NOT IN("pending","served")',[$next==='delivered'?'served':($next==='sent'?'ready':$next),$id]);
                $app->exec('UPDATE pos_tickets SET fulfillment180=?,revision=revision+1 WHERE id=?',[$next,$id]);qa180_audit($app,$user,'kitchen_status','pos_ticket',$id,'Cambió la preparación de '.$ticket['ticket_number'].' a '.qa180_order_states()[$next]);
                }
            }
            $app->db->commit();flash('success',$notice);
        }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();flash('error',$error instanceof PDOException?'No se completó el cambio. Revisa el pedido antes de repetir.':$error->getMessage());}
        redirect(url('admin/'.$section));
    }
    qa180_board_render($app,$user,$tenant,$branch,$section);
}

function qa180_order_form(array $card,string $action,string $status,string $label):string {return '<form method="post">'.csrf_field().pos_hidden_148('origin',$card['origin']).pos_hidden_148('id',$card['id']).pos_hidden_148('revision',$card['revision']).pos_hidden_148('action',$action).pos_hidden_148('status',$status).'<button class="btn btn-light">'.e($label).'</button></form>';}
