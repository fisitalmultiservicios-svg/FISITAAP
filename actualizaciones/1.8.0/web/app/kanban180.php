<?php
declare(strict_types=1);

function qa180_board_stage(string $state):?string {
    return match($state){'new','confirmed'=>'todo','preparing'=>'preparing','ready','sent'=>'ready',default=>null};
}
function qa180_board_can_dispatch(App $app,array $user,array $tenant):bool {
    if(qa180_demo($tenant)||!module_enabled_v12($app,$tenant,'express')||!module_enabled_v12($app,$tenant,'drivers'))return false;
    try{qa180_permission($app,$user,'express','write');return true;}catch(DomainException){return false;}
}
function qa180_board_drivers(App $app,array $tenant,array $branch):array {
    return $app->all('SELECT u.id,u.name,p.is_online,EXISTS(SELECT 1 FROM delivery_jobs j WHERE j.assigned_driver_id=u.id AND j.status IN("assigned","arrived","picked_up","in_transit","issue")) busy FROM driver_affiliations a JOIN users u ON u.id=a.user_id AND u.role="driver" AND u.is_active=1 JOIN driver_profiles p ON p.user_id=u.id WHERE a.tenant_id=? AND a.status="active" AND (a.all_branches=1 OR EXISTS(SELECT 1 FROM driver_affiliation_branches b WHERE b.affiliation_id=a.id AND b.branch_id=?)) ORDER BY p.is_online DESC,u.name',[$tenant['id'],$branch['id']]);
}

/** A POS Express gets a delivery snapshot linked to the SAME cash account. */
function qa180_board_pos_order(App $app,array $user,array $tenant,array $branch,array $ticket):int {
    if($ticket['source_order_id'])return (int)$ticket['source_order_id'];
    if($ticket['sale_type']!=='express'||$ticket['status']==='void')throw new DomainException('Esta cuenta no es un Express disponible.');
    if(!in_array($ticket['fulfillment180'],['ready','sent'],true))throw new DomainException('Marca el pedido como preparado antes de asignarlo.');
    $customer=pos_customer_148($app,$tenant,(int)$ticket['customer_id']);
    if(empty($customer['phone'])||trim((string)$ticket['delivery_address'])==='')throw new DomainException('Completa el cliente, teléfono y dirección en la cuenta antes de asignar Express.');
    $items=$app->all('SELECT i.*,p.unit_label FROM pos_ticket_items i LEFT JOIN products p ON p.id=i.product_id WHERE i.ticket_id=? ORDER BY i.id',[$ticket['id']]);
    if(!$items)throw new DomainException('El pedido no tiene artículos.');
    $fee=op180_ready($app)?pos_cents_148($ticket['delivery_fee180']):0;$subtotal=pos_cents_148($ticket['subtotal'])+pos_cents_148($ticket['service_total']);
    if($subtotal+pos_cents_148($ticket['tax_total'])-pos_cents_148($ticket['discount_total'])!==pos_cents_148($ticket['total']))throw new DomainException('Revisa los importes de la cuenta antes de asignar Express.');
    $method=$ticket['status']==='paid'?'Pagado en caja':'Cobro coordinado en caja';
    $notes='Cuenta de caja: '.$ticket['ticket_number'].($ticket['notes']?"\n".$ticket['notes']:'');
    $app->exec('INSERT INTO orders(tenant_id,branch_id,user_id,order_number,customer_name,customer_phone,address,delivery_type,payment_method,subtotal,tax_total,discount_total,total,status,notes) VALUES(?,?,?,?,?,?,?,"coordinated",?,?,?,?,?,"ready",?)',[$tenant['id'],$branch['id'],$customer['id'],'POS-'.$ticket['id'],$customer['name'],$customer['phone'],$ticket['delivery_address'],$method,$subtotal/100,$ticket['tax_total'],$ticket['discount_total'],$ticket['total'],$notes]);
    $order=(int)$app->db->lastInsertId();
    if(op180_ready($app))$app->exec('UPDATE orders SET subtotal=subtotal-?,delivery_total=?,maps_url180=? WHERE id=?',[$fee/100,$fee/100,$ticket['maps_url180'],$order]);
    foreach($items as $item)if(!($fee>0&&empty($item['product_id'])&&$item['product_name']==='Entrega Express'))$app->exec('INSERT INTO order_items(order_id,product_id,product_name,quantity,unit_label,unit_price,tax_rate,line_subtotal,line_tax,options_json,notes,pricing_json180) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$order,$item['product_id'],$item['product_name'],$item['quantity'],$item['unit_label']?:'unidad',$item['unit_price'],$item['tax_rate'],$item['line_subtotal'],$item['line_tax'],$item['options_json'],$item['notes'],$item['pricing_json']]);
    if((float)$ticket['service_total']!==0.0)$app->exec('INSERT INTO order_items(order_id,product_name,quantity,unit_label,unit_price,line_subtotal,line_tax) VALUES(?,"Servicio de la cuenta",1,"servicio",?,?,0)',[$order,$ticket['service_total'],$ticket['service_total']]);
    $app->exec('INSERT INTO fisitaap_order_receipts180(tenant_id,branch_id,order_id,ticket_id,received_by) VALUES(?,?,?,?,?)',[$tenant['id'],$branch['id'],$order,$ticket['id'],$user['id']]);
    $app->exec('UPDATE pos_tickets SET source_order_id=?,revision=revision+1 WHERE id=?',[$order,$ticket['id']]);
    if(op180_ready($app)){$o=$app->one('SELECT * FROM orders WHERE id=?',[$order]);op180_pin($app,$o);op180_event($app,$o,'ready','Pedido de caja preparado para asignar Express.',(int)$user['id']);}
    return $order;
}

/** Caller owns the branch/order transaction: publication + assignment is atomic. */
function qa180_board_dispatch(App $app,array $user,array $tenant,array $branch,array $order):string {
    qa180_permission($app,$user,'express','write');
    if(qa180_demo($tenant))throw new DomainException('La asignación de motorizados reales no se ejecuta en las demos.');
    if(!module_enabled_v12($app,$tenant,'express')||!module_enabled_v12($app,$tenant,'drivers'))throw new DomainException('Activa Express y la red de motorizados para asignar entregas.');
    if(!in_array($order['delivery_type'],['delivery','coordinated'],true))throw new DomainException('Este pedido es para retirar en el negocio y no necesita Express.');
    $mode=(string)($_POST['dispatch_mode']??'');
    if(!in_array($mode,['pool','driver'],true))throw new DomainException('Elige cómo asignar el Express.');
    $payout=r2_number($_POST['payout']??'',0);$_POST['payout']=$payout;
    $existing=$app->one('SELECT id,status FROM delivery_jobs WHERE order_id=? AND tenant_id=? FOR UPDATE',[$order['id'],$tenant['id']]);
    if($existing&&!in_array($existing['status'],['cancelled','expired'],true))return 'Este pedido ya tiene un Express. Consulta su seguimiento en Express pendientes.';
    if(!in_array($order['status'],['ready','sent'],true))throw new DomainException('Marca el pedido como preparado antes de asignar Express.');
    if(!empty($tenant['physical_store_enabled']))qa180_order_receive($app,$tenant,$user,(int)$order['id']);
    if($order['status']==='sent')order_status_qa($app,(int)$tenant['id'],(int)$order['id'],'ready',(int)$user['id']);
    $pin=express_publish_qa($app,$tenant,(int)$order['id']);
    $job=$app->one('SELECT id FROM delivery_jobs WHERE order_id=? AND tenant_id=?',[$order['id'],$tenant['id']]);
    if($mode==='driver')express_assign_qa($app,$tenant,(int)($_POST['driver_id']??0),(int)$job['id'],false);
    order_status_qa($app,(int)$tenant['id'],(int)$order['id'],'sent',(int)$user['id']);
    qa180_audit($app,$user,'express_'.$mode,'delivery_job',(int)$job['id'],($mode==='driver'?'Asignó directamente':'Liberó a los afiliados autorizados').' el Express del pedido #'.$order['order_number']);
    return ($mode==='driver'?'Express asignado al motorizado.':'Express disponible para los afiliados autorizados de esta sucursal.').' PIN de entrega: '.$pin.'. Compártelo con el cliente.';
}

function qa180_board_web_action(App $app,array $user,array $tenant,array $branch,array $order,string $action):string {
    if($action==='dispatch')return qa180_board_dispatch($app,$user,$tenant,$branch,$order);
    if($action==='prepare'){
        if(!in_array($order['status'],['new','confirmed'],true))throw new DomainException('El pedido ya cambió de etapa. Recarga el tablero.');
        qa180_order_receive($app,$tenant,$user,(int)$order['id']);
        order_status_qa($app,(int)$tenant['id'],(int)$order['id'],'preparing',(int)$user['id']);
        qa180_audit($app,$user,'order_prepare','order',(int)$order['id'],'Inició la preparación del pedido #'.$order['order_number']);
        return 'Pedido en preparación.';
    }
    if($action==='prepared'){
        if($order['status']!=='preparing')throw new DomainException('Primero inicia la preparación del pedido.');
        order_status_qa($app,(int)$tenant['id'],(int)$order['id'],'ready',(int)$user['id']);qa180_audit($app,$user,'order_prepared','order',(int)$order['id'],'Marcó preparado el pedido #'.$order['order_number']);return 'Pedido preparado.';
    }
    if($action==='handover'){
        if(($order['delivery_type']!=='pickup'&&r17_delivery($app,$tenant))||!in_array($order['status'],['ready','sent'],true))throw new DomainException('Este pedido necesita asignación de Express.');
        if($app->one('SELECT id FROM delivery_jobs WHERE order_id=? AND status NOT IN("cancelled","expired")',[$order['id']]))throw new DomainException('Esta entrega se confirma desde el portal del motorizado con su PIN.');
        order_status_qa($app,(int)$tenant['id'],(int)$order['id'],'delivered',(int)$user['id']);qa180_audit($app,$user,'order_handover','order',(int)$order['id'],'Entregó el retiro #'.$order['order_number']);return 'Retiro entregado al cliente.';
    }
    throw new DomainException('Acción no disponible.');
}
function qa180_board_pos_action(App $app,array $user,array $tenant,array $branch,array $ticket,string $action):string {
    if($action==='dispatch'){
        $orderId=qa180_board_pos_order($app,$user,$tenant,$branch,$ticket);
        $order=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=? FOR UPDATE',[$orderId,$tenant['id']]);
        return qa180_board_dispatch($app,$user,$tenant,$branch,$order);
    }
    $state=$ticket['fulfillment180'];
    if($action==='prepare'){
        if(!in_array($state,['new','confirmed'],true))throw new DomainException('La preparación ya cambió. Recarga el tablero.');
        $ids=array_column($app->all('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND kitchen_status="pending"',[$ticket['id']]),'id');
        if($ids)qa180_print_commands($app,$tenant,$branch,$user,(int)$ticket['id'],$ids,'pos-board-'.$ticket['id'].'-'.hash('sha256',implode(',',$ids)));
        $next='preparing';
    }elseif($action==='prepared'){
        if($state!=='preparing')throw new DomainException('Primero inicia la preparación del pedido.');$next='ready';
    }elseif($action==='handover'){
        if($ticket['sale_type']==='express'||!in_array($state,['ready','sent'],true))throw new DomainException('Este pedido necesita asignación de Express.');$next='delivered';
    }else throw new DomainException('Acción no disponible.');
    $app->exec('UPDATE pos_ticket_items SET kitchen_status=?,sent_at=COALESCE(sent_at,NOW()) WHERE ticket_id=? AND kitchen_status<>"served"',[$next==='delivered'?'served':$next,$ticket['id']]);
    $app->exec('UPDATE pos_tickets SET fulfillment180=?,fulfillment_state=?,revision=revision+1 WHERE id=?',[$next,$next==='preparing'?'preparing':($next==='ready'?'ready':'delivered'),$ticket['id']]);
    qa180_audit($app,$user,'kitchen_status','pos_ticket',(int)$ticket['id'],'Cambió la preparación de '.$ticket['ticket_number'].' a '.$next);
    return $action==='prepare'?'Pedido en preparación.':($action==='prepared'?'Pedido preparado.':'Pedido entregado.');
}

function qa180_board_render(App $app,array $user,array $tenant,array $branch,string $section):void {
    $origin=(string)($_GET['origin']??'');if(!in_array($origin,['','web','table','quick','express'],true))$origin='';
    $days=(int)($_GET['days']??2);$days=in_array($days,[2,7,30],true)?$days:2;$from=date('Y-m-d H:i:s',strtotime('-'.$days.' days'));$cards=[];
    if(in_array($origin,['','web','express'],true)){
        $source=$origin==='web'?' AND (t.id IS NULL OR t.sale_type<>"express")':($origin==='express'?' AND t.sale_type="express"':'');
        $rows=$app->all('SELECT o.*,r.ticket_id,t.sale_type FROM orders o LEFT JOIN fisitaap_order_receipts180 r ON r.order_id=o.id LEFT JOIN pos_tickets t ON t.id=r.ticket_id AND t.tenant_id=o.tenant_id LEFT JOIN delivery_jobs j ON j.order_id=o.id AND j.tenant_id=o.tenant_id WHERE o.tenant_id=? AND o.branch_id=? AND o.created_at>=? AND o.status IN("new","confirmed","preparing","ready","sent") AND (j.id IS NULL OR j.status IN("cancelled","expired"))'.$source.' ORDER BY o.created_at,o.id LIMIT 200',[$tenant['id'],$branch['id'],$from]);
        foreach($rows as $row)$cards[]=['origin'=>'web','id'=>$row['id'],'number'=>$row['order_number'],'customer'=>$row['customer_name'],'total'=>$row['total'],'state'=>$row['status'],'delivery'=>$row['delivery_type'],'label'=>$row['sale_type']==='express'?'Express de caja':'Pedido web','payment'=>$row['payment_method'],'created'=>$row['created_at'],'revision'=>$row['revision180'],'ticket'=>$row['ticket_id'],'payout'=>$row['delivery_total'],'address'=>$row['address']];
    }
    if($origin!=='web'){
        $params=[$tenant['id'],$branch['id'],$from];$where='';if($origin!==''){$where=' AND t.sale_type=?';$params[]=$origin;}
        foreach($app->all('SELECT t.*,u.name customer,r.name table_name FROM pos_tickets t LEFT JOIN users u ON u.id=t.customer_id LEFT JOIN restaurant_tables r ON r.id=t.table_id WHERE t.tenant_id=? AND t.branch_id=? AND t.created_at>=? AND t.status NOT IN("void","merged") AND t.fulfillment180 IN("new","confirmed","preparing","ready","sent") AND t.source_order_id IS NULL AND EXISTS(SELECT 1 FROM pos_ticket_items i WHERE i.ticket_id=t.id)'.$where.' ORDER BY t.created_at,t.id LIMIT 200',$params) as $row)$cards[]=['origin'=>'pos','id'=>$row['id'],'number'=>$row['ticket_number'],'customer'=>$row['customer']?:'Consumidor final','total'=>$row['total'],'state'=>$row['fulfillment180'],'delivery'=>$row['sale_type'],'label'=>$row['table_name']?:($row['sale_type']==='express'?'Express de caja':'Venta de caja'),'payment'=>$row['status']==='paid'?'Pagado':'Cobro pendiente','created'=>$row['created_at'],'revision'=>$row['revision'],'ticket'=>$row['id'],'payout'=>0,'address'=>$row['delivery_address']];
    }
    $items=[];$webIds=[];$posIds=[];
    foreach($cards as $card)if($card['origin']==='web')$webIds[]=$card['id'];else $posIds[]=$card['id'];
    foreach([['web','order_items','order_id',$webIds],['pos','pos_ticket_items','ticket_id',$posIds]] as [$kind,$table,$fk,$ids])if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));foreach($app->all('SELECT '.$fk.' parent,product_name,quantity,options_json,notes FROM '.$table.' WHERE '.$fk.' IN('.$marks.') ORDER BY id',$ids) as $item)$items[$kind.'-'.$item['parent']][]=$item;}
    $may=true;try{qa180_permission($app,$user,$section,'write');}catch(DomainException){$may=false;}
    $dispatch=false; // Dispatch is exclusive to the orders list.
    $active=(int)$app->one('SELECT COUNT(*) n FROM delivery_jobs j JOIN orders o ON o.id=j.order_id AND o.tenant_id=j.tenant_id WHERE j.tenant_id=? AND o.branch_id=? AND j.status IN("available","assigned","arrived","picked_up","in_transit","issue") AND o.status NOT IN("cancelled","delivered")',[$tenant['id'],$branch['id']])['n'];
    admin_shell_start('Pedidos · '.$branch['name'],$user,$tenant,$section);
    echo '<section class="qa180-board-toolbar"><div><span class="qa180-board-eyebrow">OPERACIÓN DEL DÍA</span><h2>Prepara y entrega, en tres pasos</h2><p>Elige un pedido para comenzar. El cobro se gestiona desde su cuenta.</p></div><a class="btn btn-light" href="'.e(url('admin/express-pendientes')).'">Express activos <span>'.(int)$active.'</span></a><form method="get" class="qa180-filter"><label>Origen<select class="input" name="origin">';
    foreach([''=>'Todos los pedidos','web'=>'Pedidos web','table'=>'Mesas','quick'=>'Venta rápida','express'=>'Express de caja'] as $key=>$label)echo '<option value="'.$key.'" '.($origin===$key?'selected':'').'>'.$label.'</option>';
    echo '</select></label><label>Periodo<select class="input" name="days">';foreach([2,7,30] as $day)echo '<option value="'.$day.'" '.($days===$day?'selected':'').'>Últimos '.$day.' días</option>';echo '</select></label><button class="btn">Aplicar filtros</button></form></section>';
    $stages=['todo'=>['01','Por preparar','Nuevos y recibidos'],'preparing'=>['02','En preparación','Pedidos en cocina o preparación'],'ready'=>['03','Preparados','Listos para entregar o asignar']];
    echo '<div class="qa180-kanban qa180-board" aria-label="Tablero de preparación">';
    foreach($stages as $stage=>[$number,$label,$hint]){
        $group=array_values(array_filter($cards,fn($c)=>qa180_board_stage($c['state'])===$stage));
        echo '<section class="qa180-kanban-column qa180-board-column" data-board-stage="'.$stage.'"><header><span class="qa180-board-step">'.$number.'</span><div><h2>'.$label.'</h2><p>'.$hint.'</p></div><span class="qa180-board-count">'.count($group).'</span></header><div class="qa180-board-stack">';
        if(!$group)echo '<div class="qa180-board-empty"><span aria-hidden="true">'.($stage==='ready'?'✓':'—').'</span><strong>Sin pedidos '.($stage==='todo'?'pendientes':($stage==='preparing'?'en preparación':'preparados')).'</strong><p>Los pedidos aparecerán aquí al avanzar.</p></div>';
        foreach($group as $card)qa180_board_card($card,$items[$card['origin'].'-'.$card['id']]??[],$stage,$may,$dispatch);
        echo '</div></section>';
    }
    echo '</div><p class="qa180-board-footnote">Hasta 200 pedidos abiertos por origen en el periodo seleccionado. Los Express asignados continúan en Express pendientes; las operaciones finalizadas se consultan en Reportes.</p>';
    if($dispatch)qa180_board_dispatch_dialog($app,$tenant,$branch);
    admin_shell_end();
}
function qa180_board_card(array $card,array $items,string $stage,bool $may,bool $dispatch):void {
    $minutes=max(0,(int)((time()-strtotime($card['created']))/60));$delivery=in_array($card['delivery'],['delivery','coordinated','express'],true);
    echo '<article class="qa180-order-card" data-board-order="'.e($card['origin'].'-'.$card['id']).'"><div class="qa180-order-meta"><span>'.e($card['label']).'</span><time>'.($minutes>=60?(int)floor($minutes/60).' h ':$minutes.' min').'</time></div><h3>#'.e($card['number']).'</h3><p class="qa180-order-customer">'.e($card['customer']).'</p><div class="qa180-order-total"><strong>'.money((float)$card['total']).'</strong><span>'.($delivery?'Entrega Express':($card['delivery']==='pickup'?'Retiro en el negocio':e($card['label']))).'</span></div><div class="qa180-order-items">';
    foreach(array_slice($items,0,3) as $item)echo '<p><b>'.e(rtrim(rtrim(number_format((float)$item['quantity'],3,'.',''),'0'),'.')).'×</b> '.e($item['product_name']).order_item_details_html($item).'</p>';
    if(count($items)>3){echo '<details><summary>Ver '.(count($items)-3).' artículos más</summary>';foreach(array_slice($items,3) as $item)echo '<p>'.e($item['quantity'].' × '.$item['product_name']).order_item_details_html($item).'</p>';echo '</details>';}
    echo '</div><p class="qa180-order-payment">'.e($card['payment']).'</p><footer>';
    if($may){
        if($stage==='todo')echo qa180_order_form($card,'prepare','','Preparar');
        elseif($stage==='preparing')echo qa180_order_form($card,'prepared','','Preparado');
        elseif($delivery&&$dispatch)echo '<button class="btn" type="button" data-board-dispatch data-origin="'.e($card['origin']).'" data-id="'.(int)$card['id'].'" data-revision="'.(int)$card['revision'].'" data-number="'.e($card['number']).'" data-payout="'.e($card['payout']).'">Asignar a Express</button>';
        elseif(!$delivery||!$dispatch)echo '<a class="btn btn-light" href="'.e(url('admin/pedidos')).'">Continuar desde Pedidos</a>';
        else echo '<p class="muted">Preparado para que un encargado asigne la entrega.</p>';
    }
    echo '<details class="qa180-order-more"><summary>Más opciones</summary>';
    if($card['ticket'])echo '<a href="'.e(url('admin/ventas?screen=order&ticket='.(int)$card['ticket'])).'">Ver cuenta</a>';
    if($may&&$card['origin']==='web'&&in_array($card['state'],['new','confirmed','preparing','ready'],true))echo qa180_order_form($card,'status','cancelled','Cancelar pedido');
    echo '</details></footer></article>';
}
function qa180_board_dispatch_dialog(App $app,array $tenant,array $branch):void {
    $drivers=qa180_board_drivers($app,$tenant,$branch);
    echo '<dialog class="qa180-dispatch-dialog" id="qa180Dispatch" aria-labelledby="qa180DispatchTitle"><form method="post">'.csrf_field().pos_hidden_148('action','dispatch').pos_hidden_148('origin','web').pos_hidden_148('id',0).pos_hidden_148('revision',0).'<header><div><span class="qa180-board-eyebrow">ENTREGA DEL PEDIDO</span><h2 id="qa180DispatchTitle">Asignar a Express</h2><p data-dispatch-order></p></div><button class="icon-btn" type="button" data-dispatch-close aria-label="Cerrar asignación">×</button></header><fieldset><legend>¿Quién realizará la entrega?</legend><label class="qa180-dispatch-choice"><input type="radio" name="dispatch_mode" value="pool" checked><span><strong>Liberar a los afiliados</strong><small>El primer motorizado autorizado que acepte se queda con la entrega.</small></span></label><label class="qa180-dispatch-choice"><input type="radio" name="dispatch_mode" value="driver"><span><strong>Asignar a un motorizado</strong><small>Elige directamente a un afiliado autorizado de esta sucursal.</small></span></label></fieldset><label data-dispatch-drivers hidden>Motorizado<select class="input" name="driver_id" disabled><option value="">Seleccionar motorizado</option>';
    foreach($drivers as $driver)echo '<option value="'.(int)$driver['id'].'" '.($driver['busy']?'disabled':'').'>'.e($driver['name']).' · '.($driver['busy']?'Con entrega activa':($driver['is_online']?'Disponible':'Desconectado')).'</option>';
    echo '</select></label>';
    if(!$drivers)echo '<p class="muted">No hay afiliados autorizados actualmente. Un Express liberado estará disponible cuando un afiliado autorizado se conecte.</p>';
    echo '<label>Pago al motorizado<input class="input" name="payout" type="number" min="0" step="0.01" required></label><p class="qa180-dispatch-note">Este monto no cambia el total del pedido. Al confirmar recibirás el PIN que debes compartir con el cliente.</p><footer><button class="btn btn-light" type="button" data-dispatch-close>Volver</button><button class="btn" type="submit">Confirmar asignación</button></footer></form></dialog>';
}

function qa180_board_pending(App $app,array $user,array $tenant,array $branch):void {
    if(is_post()){
        verify_csrf();flash('info','Prepara y asigna los pedidos desde el tablero. Las entregas asignadas se confirman con el PIN del cliente.');redirect(url('admin/pedidos'));
    }
    $rows=$app->all('SELECT t.id,t.ticket_number,t.fulfillment180,t.status,u.name customer_name FROM pos_tickets t LEFT JOIN users u ON u.id=t.customer_id WHERE t.tenant_id=? AND t.branch_id=? AND t.source_order_id IS NULL AND t.sale_type="express" AND t.status NOT IN("void","merged") AND t.fulfillment180 NOT IN("delivered","cancelled") ORDER BY t.created_at LIMIT 200',[$tenant['id'],$branch['id']]);
    admin_shell_start('Express pendientes de entrega',$user,$tenant,'express-pendientes');
    echo '<p>Sucursal: <strong>'.e($branch['name']).'</strong>. El cobro y la entrega se registran por separado.</p><p><a class="btn btn-light" href="'.e(url('admin/pedidos')).'">Abrir tablero de preparación</a></p>';
    echo '<div data-live-region="pending-express">';$active=express_web_pending_v12($app,$user,$tenant,$branch);
    if($rows){echo '<h2>Express de caja por preparar o asignar</h2><div class="p148-account-grid">';foreach($rows as $row)echo '<article class="card admin-card"><h3>'.e($row['ticket_number'].' · '.$row['customer_name']).'</h3><p>'.e(['new'=>'Por preparar','confirmed'=>'Por preparar','preparing'=>'En preparación','ready'=>'Preparado','sent'=>'Preparado para asignar'][$row['fulfillment180']]??'Por preparar').'</p><a class="btn btn-light" href="'.e(url('admin/pedidos?origin=express&days=30')).'">Preparar / asignar en el tablero</a></article>';echo '</div>';}
    if(!$rows&&!$active)echo '<p>No hay Express pendientes en esta sucursal.</p>';
    echo '</div>';
    admin_shell_end();
}
