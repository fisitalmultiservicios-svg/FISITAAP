<?php
declare(strict_types=1);

function op180_can_accept(App $app,array $user):bool {
    try{qa180_permission($app,$user,'pedidos','write');return true;}catch(DomainException){}
    $grant=restructure_can_r1($app,$user,'ventas','accept_orders');if($grant!==null)return $grant;
    try{qa180_permission($app,$user,'ventas','write');return true;}catch(DomainException){return false;}
}
function op180_live(App $app):never {
    if(!empty($_GET['tracking_id'])){
        $id=(int)$_GET['tracking_id'];$token=(string)($_GET['tracking_token']??'');if(!hash_equals(hash_hmac('sha256','order:'.$id,$app->config['app_key']),$token))json_response(['ok'=>false,'error'=>'Enlace no disponible.'],404);
        $r=$app->one('SELECT o.id,COALESCE(l.revision,0) revision FROM orders o LEFT JOIN fisitaap_live180 l ON l.tenant_id=o.tenant_id WHERE o.id=?',[$id]);if(!$r)json_response(['ok'=>false,'error'=>'Pedido no disponible.'],404);if(session_status()===PHP_SESSION_ACTIVE)session_write_close();json_response(['ok'=>true,'version'=>hash('sha256',json_encode($r)),'incoming'=>0,'poll_ms'=>2000]);
    }
    $user=require_login();$fresh=$app->one('SELECT id,role,is_active,tenant_id,force_password_change FROM users WHERE id=?',[$user['id']]);if(!$fresh||!$fresh['is_active']||$fresh['role']!==$user['role']||$fresh['force_password_change'])json_response(['ok'=>false,'error'=>'Revisa tu acceso.'],403);$scope=(string)($_GET['scope']??'');$revisions=[];$incoming=0;
    if($user['role']==='driver'){
        foreach($app->all('SELECT a.tenant_id,COALESCE(l.revision,0) revision FROM driver_affiliations a JOIN tenants t ON t.id=a.tenant_id AND t.is_active=1 LEFT JOIN fisitaap_live180 l ON l.tenant_id=a.tenant_id WHERE a.user_id=? AND a.status IN("active","paused") ORDER BY a.tenant_id',[$user['id']]) as $row)$revisions[]=$row;
    }elseif($user['role']==='customer'){
        $revisions=$app->all('SELECT tc.tenant_id,COALESCE(l.revision,0) revision FROM tenant_customers tc JOIN tenants t ON t.id=tc.tenant_id AND t.is_active=1 LEFT JOIN fisitaap_live180 l ON l.tenant_id=tc.tenant_id WHERE tc.user_id=? ORDER BY tc.tenant_id',[$user['id']]);
    }elseif($user['role']==='master'){
        $revisions=$app->all('SELECT tenant_id,revision FROM fisitaap_live180 ORDER BY tenant_id');
        if(fc16_ready($app))$revisions[]=['support_event'=>$app->one('SELECT COALESCE(MAX(id),0) n FROM fisitaap_support_events_r16')['n']];
    }else{
        $tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$user['tenant_id']]);if(!$tenant)json_response(['ok'=>false,'error'=>'Negocio no disponible.'],403);
        $fresh=$app->one('SELECT id FROM users WHERE id=? AND tenant_id=? AND is_active=1 AND force_password_change=0',[$user['id'],$tenant['id']]);if(!$fresh)json_response(['ok'=>false,'error'=>'Revisa tu acceso.'],403);
        $branch=physical_branch_v14($app,$tenant);$revisions=$app->all('SELECT tenant_id,revision FROM fisitaap_live180 WHERE tenant_id=?',[$tenant['id']]);
        try{if(!op180_can_accept($app,$user))qa180_permission($app,$user,'pedidos','view');$incoming=(int)$app->one('SELECT COUNT(*) n FROM orders WHERE tenant_id=? AND branch_id=? AND status="new"',[$tenant['id'],$branch['id']])['n'];}catch(DomainException){}
    }
    $version=hash('sha256',json_encode([$user['id'],$scope,$branch['id']??0,$revisions],JSON_THROW_ON_ERROR));
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    header('Cache-Control: private, no-store');json_response(['ok'=>true,'version'=>$version,'incoming'=>$incoming,'poll_ms'=>2000]);
}
function op180_incoming(App $app):never {
    $user=require_login(['tenant_admin','manager','editor','cashier','kitchen']);$tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$user['tenant_id']]);if(!$tenant)json_response(['ok'=>false,'error'=>'Negocio no disponible.'],403);if(!op180_can_accept($app,$user))qa180_permission($app,$user,'pedidos','view');$branch=physical_branch_v14($app,$tenant);if(!$branch)json_response(['ok'=>false,'error'=>'Selecciona una sucursal.'],422);
    if(is_post()){
        verify_csrf();if(!op180_can_accept($app,$user))json_response(['ok'=>false,'error'=>'Tu rol no permite aceptar pedidos.'],403);qa180_update_lock($app,(int)$tenant['id']);
        try{
            $id=(int)($_POST['order_id']??0);$app->db->beginTransaction();$app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$branch['id'],$tenant['id']]);
            $order=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=? AND branch_id=? FOR UPDATE',[$id,$tenant['id'],$branch['id']]);if(!$order)throw new DomainException('Pedido no disponible en esta sucursal.');
            if(in_array($order['status'],['cancelled','delivered'],true))throw new DomainException('El pedido ya terminó.');
            qa180_order_receive($app,$tenant,$user,$id);$app->db->commit();json_response(['ok'=>true,'message'=>r17_menu($tenant)?'Pedido aceptado. Comanda enviada una sola vez a la impresora configurada.':'Pedido aceptado. Puedes tramitarlo desde Pedidos.']);
        }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();json_response(['ok'=>false,'error'=>$e instanceof PDOException?'No se completó la recepción. Revisa el pedido.':$e->getMessage()],422);}
    }
    $rows=$app->all('SELECT id,order_number,customer_name,total,payment_method,created_at FROM orders WHERE tenant_id=? AND branch_id=? AND status="new" ORDER BY id LIMIT 100',[$tenant['id'],$branch['id']]);
    foreach($rows as &$row){$row['proof_url']=preg_match('/sinpe/i',$row['payment_method'])?url('api/order-proof180?order='.$row['id']):null;}$can=op180_can_accept($app,$user);
    json_response(['ok'=>true,'orders'=>$rows,'can_accept'=>$can,'menu_mode'=>r17_menu($tenant)]);
}
function op180_ui(App $app):void {
    if(!op180_ready($app))return;$user=current_user();$staff=$user&&in_array($user['role'],['tenant_admin','manager','editor','cashier','kitchen'],true);
    echo '<link rel="stylesheet" href="'.e(url('assets/operations180.css?v=r9')).'"><script>window.OP180='.r3_json(['base'=>url(),'demo'=>!empty($GLOBALS['demo_context']),'staff'=>$staff,'role'=>$user['role']??'guest','poll'=>2000,'csrf'=>csrf_token()]).';</script><script src="'.e(url('assets/operations180.js?v=r9')).'" defer></script>';
}
function op180_orders_list(App $app,array $user,array $tenant,array $branch,string $section):void {
    $origin=(string)($_GET['origin']??'');$status=(string)($_GET['status']??'');$days=(int)($_GET['days']??2);if(!in_array($days,[2,7,30],true))$days=2;
    admin_shell_start('Pedidos · '.$branch['name'],$user,$tenant,$section);
    echo '<section class="card admin-card"><form method="get" class="qa180-filter"><label>Origen<select class="input" name="origin">';foreach([''=>'Todos','web'=>'Pedidos web','express'=>'Express de caja','quick'=>'Venta rápida','table'=>'Mesas'] as $key=>$label)echo '<option value="'.$key.'" '.($origin===$key?'selected':'').'>'.$label.'</option>';
    echo '</select></label><label>Estado<select class="input" name="status"><option value="">Activos</option>';foreach(qa180_order_states() as $key=>$label)echo '<option value="'.$key.'" '.($status===$key?'selected':'').'>'.$label.'</option>';echo '</select></label><label>Periodo<select class="input" name="days">';foreach([2,7,30] as $day)echo '<option value="'.$day.'" '.($days===$day?'selected':'').'>'.$day.' días</option>';echo '</select></label><button class="btn">Filtrar</button></form></section>';
    $where=$status&&isset(qa180_order_states()[$status])?' AND o.status=?':' AND o.status NOT IN("delivered","cancelled")';$args=[$tenant['id'],$branch['id'],$days];if($status&&isset(qa180_order_states()[$status]))$args[]=$status;
    $cards=[];
    if(!in_array($origin,['quick','table'],true)){
        $source=$origin==='express'?' AND t.sale_type="express"':($origin==='web'?' AND (t.id IS NULL OR t.sale_type<>"express")':'');
        foreach($app->all('SELECT o.*,r.ticket_id,t.sale_type,j.status job_status,u.name driver FROM orders o LEFT JOIN fisitaap_order_receipts180 r ON r.order_id=o.id LEFT JOIN pos_tickets t ON t.id=r.ticket_id LEFT JOIN delivery_jobs j ON j.order_id=o.id LEFT JOIN users u ON u.id=j.assigned_driver_id WHERE o.tenant_id=? AND o.branch_id=? AND o.created_at>=DATE_SUB(NOW(),INTERVAL ? DAY)'.$where.$source.' ORDER BY o.id DESC LIMIT 200',$args) as $r)$cards[]=['origin'=>'web','id'=>$r['id'],'number'=>$r['order_number'],'customer'=>$r['customer_name'],'total'=>$r['total'],'state'=>$r['status'],'delivery'=>$r['delivery_type'],'revision'=>$r['revision180'],'ticket'=>$r['ticket_id'],'payout'=>$r['delivery_total'],'job'=>$r['job_status'],'driver'=>$r['driver'],'payment'=>$r['payment_method'],'created'=>$r['created_at']];
    }
    if($origin!=='web'){
        $params=[$tenant['id'],$branch['id'],$days];$filter='';if(in_array($origin,['quick','table','express'],true)){$filter=' AND t.sale_type=?';$params[]=$origin;}
        if($status&&isset(qa180_order_states()[$status])){$filter.=' AND t.fulfillment180=?';$params[]=$status;}else $filter.=' AND t.fulfillment180 NOT IN("delivered","cancelled")';
        foreach($app->all('SELECT t.*,u.name customer FROM pos_tickets t LEFT JOIN users u ON u.id=t.customer_id WHERE t.tenant_id=? AND t.branch_id=? AND t.created_at>=DATE_SUB(NOW(),INTERVAL ? DAY) AND t.source_order_id IS NULL AND t.status NOT IN("void","merged") AND EXISTS(SELECT 1 FROM pos_ticket_items i WHERE i.ticket_id=t.id)'.$filter.' ORDER BY t.id DESC LIMIT 200',$params) as $r)$cards[]=['origin'=>'pos','id'=>$r['id'],'number'=>$r['ticket_number'],'customer'=>$r['customer']?:'Consumidor final','total'=>$r['total'],'state'=>$r['fulfillment180'],'delivery'=>$r['sale_type'],'revision'=>$r['revision'],'ticket'=>$r['id'],'payout'=>$r['delivery_fee180'],'job'=>null,'driver'=>null,'payment'=>$r['status']==='paid'?'Pagado':'Cobro pendiente','created'=>$r['created_at']];
    }
    $may=true;try{qa180_permission($app,$user,$section,'write');}catch(DomainException){$may=false;}$dispatch=$may&&qa180_board_can_dispatch($app,$user,$tenant);
    echo '<section class="op180-order-list card admin-card" data-live-region="orders"><p class="muted">Los pedidos se actualizan automáticamente. Cobro, preparación y entrega se registran por separado.</p><div class="table-wrap"><table class="table"><thead><tr><th>Pedido / fecha</th><th>Cliente / origen</th><th>Estado actual</th><th>Total / pago</th><th>Acciones</th></tr></thead><tbody>';
    foreach($cards as $c){$shipping=in_array($c['delivery'],['delivery','coordinated','express'],true);$job=$c['job']&&!in_array($c['job'],['cancelled','expired'],true);$stage=qa180_board_stage($c['state']);
        echo '<tr data-order-row="'.e($c['origin'].'-'.$c['id']).'"><td><strong>#'.e($c['number']).'</strong><small>'.e($c['created']).'</small></td><td>'.e($c['customer']).'<small>'.e($c['origin']==='web'?'Pedido / entrega':'Caja · '.$c['delivery']).'</small></td><td><span class="op180-status">'.e($job?(express_job_states_v12()[$c['job']]??$c['job']):(qa180_order_states()[$c['state']]??$c['state'])).'</span>'.($c['driver']?'<small>'.e($c['driver']).'</small>':'').'</td><td>'.money((float)$c['total']).'<small>'.e($c['payment']).'</small></td><td class="op180-actions">';
        if($may&&!$job){if($c['state']==='new'&&$c['origin']==='web')echo qa180_order_form($c,'receive','',r17_menu($tenant)?'Aceptar e imprimir comanda':'Aceptar pedido');elseif(r17_menu($tenant)&&in_array($stage,['todo','preparing'],true))echo '<a class="btn btn-light" href="'.e(url('admin/cocina')).'">Abrir monitor</a>';elseif(!r17_menu($tenant)&&in_array($c['state'],['confirmed','preparing'],true)&&$shipping&&$dispatch)echo qa180_order_form($c,'status','ready','Listo para entregar');elseif(!r17_menu($tenant)&&in_array($c['state'],['confirmed','preparing'],true))echo qa180_order_form($c,'status','delivered','Marcar entregado');elseif($stage==='ready'&&$shipping&&$dispatch)echo '<button class="btn" type="button" data-board-dispatch data-origin="'.e($c['origin']).'" data-id="'.(int)$c['id'].'" data-revision="'.(int)$c['revision'].'" data-number="'.e($c['number']).'" data-payout="'.e($c['payout']).'">Asignar a Express</button>';elseif($stage==='ready'&&(!$shipping||!r17_delivery($app,$tenant)))echo qa180_order_form($c,'handover','','Entregar');}
        if($c['ticket'])echo '<a class="btn btn-light" href="'.e(url('admin/ventas?screen=order&ticket='.(int)$c['ticket'])).'">Ver cuenta</a>';
        if($c['origin']==='web'){
            echo '<a class="btn btn-light" href="'.e(url('admin/pedido-detalle180?id='.(int)$c['id'])).'">Detalle y bitácora</a>';
            if(!in_array($c['state'],['delivered','cancelled'],true)&&op180_can_cancel($app,$user,$section))echo '<button class="btn btn-light" type="button" data-order-cancel="'.(int)$c['id'].'" data-revision="'.(int)$c['revision'].'">Cancelar con justificación</button>';
        }echo '</td></tr>';
    }
    if(!$cards)echo '<tr><td colspan="5">No hay pedidos en este filtro.</td></tr>';echo '</tbody></table></div></section>';
    if($dispatch)qa180_board_dispatch_dialog($app,$tenant,$branch);
    echo '<dialog id="op180Cancel" class="qa180-dispatch-dialog"><form method="post">'.csrf_field().pos_hidden_148('origin','web').pos_hidden_148('id',0).pos_hidden_148('revision',0).pos_hidden_148('action','status').pos_hidden_148('status','cancelled').'<h2>Cancelar pedido</h2><label>Justificación<textarea class="input" name="cancel_reason" required minlength="8" maxlength="500"></textarea></label><p>Quedará registrada con tu usuario y fecha. Un pedido con cobros requiere revisar la devolución.</p><button class="btn btn-light" type="button" data-dialog-close>Volver</button><button class="btn">Confirmar cancelación</button></form></dialog>';
    admin_shell_end();
}
