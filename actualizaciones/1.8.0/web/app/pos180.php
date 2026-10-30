<?php
declare(strict_types=1);

function qa180_start_sale(App $app,array $user,array $tenant,array $branch,string $type):int {
    verify_csrf();qa180_permission($app,$user,'ventas','write');$post=$_POST;
    $options=array_values(array_unique(array_map('intval',(array)($post['option_ids']??[]))));sort($options);
    $hash=hash('sha256',json_encode([$user['id'],$branch['id'],$type,(int)($post['product_id']??0),(float)($post['quantity']??1),$options,(string)($post['notes']??'')],JSON_THROW_ON_ERROR));
    $app->db->beginTransaction();try{
        $id=pos_open_148($app,$tenant,$user,$branch,$type,$type==='table'?(int)($_POST['table_id']??0):null);$ticket=pos_ticket_get_148($app,$tenant,$branch,$id,true);
        if((int)$ticket['opened_by']!==(int)$user['id'])throw new DomainException('Esta cuenta pertenece a otro usuario.');
        if($ticket['start_payload_hash']){if(!hash_equals($ticket['start_payload_hash'],$hash))throw new DomainException('La misma referencia ya abrió una venta con otro producto.');$app->db->commit();return $id;}
        if($app->one('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND NOT(product_id IS NULL AND product_name="Entrega Express" AND kitchen_status="served") LIMIT 1',[$id]))throw new DomainException('La venta ya tiene productos. Abre su detalle para continuar.');
        $_POST['action']='add_item';$_POST['revision']=$ticket['revision'];pos_edit_148($app,$tenant,$user,$branch,$id,false);
        $app->exec('UPDATE pos_tickets SET start_payload_hash=? WHERE id=? AND tenant_id=?',[$hash,$id,$tenant['id']]);$app->db->commit();return $id;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}finally{$_POST=$post;}
}
function qa180_cash_tools(App $app,array $user,array $tenant,array $branch):void {
    if(request_path()==='admin/salones')return;
    $register=qa180_native($app,$user);if(!$register){echo '<p class="qa180-native-status" data-native-status>Puedes preparar ventas y mesas. Para cobrar o mover efectivo, abre FISITAAP Windows o Android con una caja autorizada.</p>';return;}
    $shift=$app->one('SELECT s.id,u.name FROM pos_shifts s JOIN users u ON u.id=s.user_id WHERE s.tenant_id=? AND s.register_id=? AND s.status="open" ORDER BY s.id DESC LIMIT 1',[$tenant['id'],$register['id']]);
    echo '<div class="qa180-cash-tools"><span>'.e($register['name']).' · '.e($user['name']).' · '.($shift?'Turno #'.(int)$shift['id'].' de '.e($shift['name']):'Sin turno abierto').'</span><a class="r3-button" href="'.e(url('admin/turnos')).'">'.($shift?'Contar / cerrar turno':'Iniciar turno').'</a>';
    if($shift)echo '<button class="r3-button" type="button" data-dialog-open="qa180Cash">Entrada / salida de efectivo</button>';echo '</div>';
    if(!$shift)return;
    echo '<dialog id="qa180Cash" class="r3-dialog"><h2>Movimiento de efectivo</h2><form method="post" action="'.e(url('admin/caja-movimiento')).'">'.csrf_field().pos_hidden_148('operation_key',bin2hex(random_bytes(16))).'<label>Movimiento<select class="input" name="direction"><option value="in">Entrada a la caja</option><option value="out">Salida de la caja</option></select></label>'.field('Monto','amount','',true,'number','.01').field('Motivo','reason','',true).field('Referencia (opcional)','reference','').'<label><input type="checkbox" name="confirmed" value="yes" required> Confirmo el movimiento en esta caja y turno.</label><footer><button class="r3-button" type="button" data-dialog-close>Cancelar</button><button class="r3-button primary">Guardar movimiento</button></footer></form></dialog>';
}
function qa180_pos_start(App $app,array $user,array $tenant,array $branch):void {
    $products=r2_catalog_prices($app,$tenant,pos_catalog_products_v141($app,$tenant,$branch));$categories=$app->all('SELECT id,name FROM categories WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,name',[$tenant['id']]);
    r3_shell_start($app,$user,$tenant,$branch,'Venta rápida');r3_pos_nav($tenant);
    $r14Zero='<div class="r3-sale-total r15-billing-footer"><div class="ticket-summary"><div class="summary-row"><span>Productos</span><span>0</span></div><div class="summary-row"><span>Impuestos</span><span>Se calculan al agregar productos</span></div><div class="summary-row total"><span>Total</span><strong>'.r15_money(0).'</strong></div></div><div class="ticket-actions"><button class="btn primary" disabled>Cobrar '.r15_money(0).'</button></div></div>';
    echo '<div class="r3-pos-grid" data-r14-pos data-r14-ticket="new-'.(int)$tenant['id'].'-'.(int)$branch['id'].'"><section class="r3-panel r3-cart-panel"><header class="r14-invoice-heading"><h2>Facturación</h2><span>Nueva venta</span></header><div class="r3-customer-strip">'.r3_icon('person').'<span>Cliente:</span><strong>Consumidor final</strong><span class="r3-spacer"></span><span>Moneda: <b>CRC ₡</b></span></div><form class="r3-search" method="get">'.r3_icon('search').'<input name="q" value="'.e($_GET['q']??'').'" placeholder="Escanea un código o busca un producto…" aria-label="Buscar producto" autofocus><button class="r3-button primary">Buscar / agregar</button></form><div class="r3-sale-table-wrap"><table class="r3-sale-table"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Importe</th></tr></thead><tbody><tr><td colspan="4" class="r3-empty">Busca un producto o agrégalo desde el acceso rápido. Se abrirá tu venta al añadir el primero.</td></tr></tbody></table></div><p class="r3-hint">Puedes elegir o crear el cliente, editar cantidades, precios autorizados, extras y notas dentro de la venta.</p>'.$r14Zero.'</section><aside class="r3-quick"><section class="r3-panel"><h3>Carga rápida</h3><div class="r3-categories"><a class="r3-button '.(empty($_GET['category'])?'selected':'').'" href="'.e(url('admin/ventas')).'">Todos</a>';
    foreach($categories as $category)echo '<a class="r3-button '.((int)($_GET['category']??0)===(int)$category['id']?'selected':'').'" href="'.e(url('admin/ventas?category='.(int)$category['id'])).'">'.e($category['name']).'</a>';echo '</div><div class="r3-quick-products">';
    foreach($products as $product)echo '<form class="pos-product r3-quick-product" data-sku="'.e($product['sku']).'" method="post" action="'.e(url('admin/ventas')).'">'.csrf_field().pos_hidden_148('action',($tenant['catalog_label']??'')==='Menú'?'quick':'catalog').pos_hidden_148('open_key',bin2hex(random_bytes(16))).pos_hidden_148('start_product',1).pos_hidden_148('product_id',$product['id']).pos_hidden_148('quantity',max(.001,(float)$product['min_qty'])).'<img loading="lazy" src="'.e(public_media_url($product['image'])).'" alt=""><div><strong>'.e($product['name']).'</strong><small>'.e(r15_product_caption($product,$categories)).'</small><b>'.r15_money((float)($product['sale_price']?:$product['price'])).'</b></div><button class="r3-button green" aria-label="Agregar '.e($product['name']).'">+</button></form>';
    if(!$products)echo '<p>'.(empty($_GET['category'])&&trim((string)($_GET['q']??''))===''?'Elige una categoría o busca un producto para comenzar.':'No encontramos productos con este filtro.').'</p>';echo '</div></section></aside></div>';r3_shell_end();
}
function qa180_open_hash(array $user,array $branch,string $type,?int $table,array $post):string {
     $payload=[$user['id'],$branch['id'],$type,$table,(string)($post['diner_name']??''),(int)($post['customer_id']??0),!empty($post['create_customer']),(string)($post['new_name']??''),(string)($post['new_phone']??''),(string)($post['delivery_address']??''),(int)($post['zone_id']??0)];if(isset($post['delivery_fee'])||isset($post['maps_url']))$payload[]=[(string)($post['delivery_fee']??0),(string)($post['maps_url']??'')];return hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR));
}
function qa180_open_record(App $app,array $tenant,array $branch,array $user,string $key,string $hash,int $id):void {
    $app->exec('INSERT INTO fisitaap_operations180(tenant_id,branch_id,user_id,operation_key,payload_hash,kind,result_id) VALUES(?,?,?,?,?,"account_open",?)',[$tenant['id'],$branch['id'],$user['id'],$key,$hash,$id]);
}

function qa180_item_display(array $items):array {
    foreach($items as &$item)unset($item['unit_cost']);unset($item);return $items;
}
