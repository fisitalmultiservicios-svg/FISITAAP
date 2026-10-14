<?php
declare(strict_types=1);
require_once __DIR__.'/fisichat_public180.php';

function r2_chat_profile(array $user=[]):string {return match($user['role']??''){ 'master'=>'maestro','tenant_admin','manager','editor','cashier','kitchen'=>'dueno',default=>'cliente' };}
function r2_chat_context(App $app,string $profile,?array $tenant,?array $u,string $branch=''):array {
    if($profile==='tecnico')return ['perfil'=>'Vendedor y técnico','producto'=>'FISITAAP','ayuda'=>'Catálogo web, administración de negocios, permisos, POS, compras, clientes y Windows. Para instalar: respaldo de archivos y base de datos, ZIP en la raíz de cPanel y acceso MAESTRO al instalador. Para operar sin internet: un solo equipo principal por negocio, Windows o Android; las otras cajas requieren internet. La IA y los pedidos de la web requieren internet.'];
    if($profile==='maestro'){
        if(!$u||$u['role']!=='master'||$u['force_password_change'])throw new DomainException('Este perfil requiere la cuenta MAESTRO.');
        return ['perfil'=>'Maestro','negocios'=>$app->all('SELECT name,plan,is_active,expires_at FROM tenants WHERE slug NOT IN ("tienda-demo","restaurante-demo") AND slug NOT REGEXP "^demo-(restaurante|tienda)-[0-9a-f]{32}$" ORDER BY name LIMIT 100'),'resumen'=>$app->one('SELECT COUNT(*) negocios,SUM(is_active) activos FROM tenants WHERE slug NOT IN ("tienda-demo","restaurante-demo") AND slug NOT REGEXP "^demo-(restaurante|tienda)-[0-9a-f]{32}$"'),'ayuda'=>'El panel maestro administra negocios, planes, suscripciones, contenido y reportes. Este asistente consulta; los cambios se hacen desde el panel.'];
    }
    if($profile==='dueno'){
        if(!$u||$u['force_password_change']||!in_array($u['role'],['tenant_admin','manager','editor','cashier','kitchen'],true))throw new DomainException('Ingresa con tu cuenta del negocio.');
        if(!$tenant||(int)$tenant['id']!==(int)$u['tenant_id'])throw new DomainException('Negocio no autorizado.');
        if(qa180_active($app))qa180_permission($app,$u,'fisichat','view');elseif($u['role']!=='tenant_admin'&&!role_can($u['role'],'fisichat'))throw new DomainException('Tu rol no permite FISIChat.');
        $may=static fn(string $section):bool=>qa180_active($app)?r3_report_can($app,$u,$tenant,$section):role_can($u['role'],$section);
        $data=['perfil'=>'Colaborador autorizado del negocio','negocio'=>$tenant['name'],'ayuda'=>'Las funciones disponibles dependen del rol. Para cobrar: abrir turno, buscar productos, seleccionar cliente y confirmar el pago. Los cambios se realizan desde las pantallas del panel.'];
        if($may('reportes')&&$may('ventas'))$data['ventas_mes']=$app->one('SELECT COALESCE(SUM(total),0) total,COUNT(*) cantidad FROM sales WHERE tenant_id=? AND status="completed" AND created_at>=DATE_FORMAT(NOW(),"%Y-%m-01")',[$tenant['id']]);
        if($may('productos'))$data['productos']=$app->all('SELECT name,price,status FROM products WHERE tenant_id=? ORDER BY name LIMIT 80',[$tenant['id']]);if(qa180_active($app)&&$may('inventario')){$branch=physical_branch_v14($app,$tenant);$data['stock_bajo']=$app->all('SELECT p.name,(i.quantity-i.reserved_quantity) available,GREATEST(i.min_stock,i.reorder_point) minimum FROM product_inventory i JOIN products p ON p.id=i.product_id AND p.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.branch_id=? AND i.track_stock=1 AND (i.quantity-i.reserved_quantity)<=GREATEST(i.min_stock,i.reorder_point) ORDER BY available,p.name LIMIT 20',[$tenant['id'],$branch['id']]);}return $data;
    }
    return fc_public_store_context($app,$tenant,$u,$branch);
}

function r2_chat_api(App $app):never { fc_chat_api($app); }
function r2_chat_widget(App $app,?array $t=null):void {
    $path=request_path();$inside=$path==='admin'||$path==='master'||str_starts_with($path,'admin/')||str_starts_with($path,'master/');
    $u=fc_chat_actor($app);if($inside&&(!$u||$u['force_password_change']))return;$profile=$inside?r2_chat_profile($u??[]):($t?'cliente':'tecnico');
    if($inside&&$profile==='dueno'&&$u&&$u['role']!=='tenant_admin'&&!role_can($u['role'],'fisichat'))return;
    if($inside&&$profile==='dueno'&&$u)$t=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$u['tenant_id']]);
    fc_chat_ui($app,$profile,$t,$u,false);
}
function r2_chat_page(App $app):void {
    $u=fc_chat_actor($app);$profile=(string)($_GET['perfil']??'tecnico');if($profile==='vendedor')$profile='tecnico';
    if(!in_array($profile,['tecnico','maestro','dueno','cliente'],true))$profile='tecnico';
    $slug=$_GET['tienda']??'';$t=is_string($slug)&&$slug!==''?tenant_by_slug($app,$slug):null;
    if($slug!==''&&(!$t||!$t['is_active'])){http_response_code(404);simple_error('Negocio no disponible','Vuelve al inicio para elegir un negocio.');return;}
    if($profile==='dueno'&&$u)$t=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$u['tenant_id']]);
    try{if(in_array($profile,['dueno','maestro'],true))r2_chat_context($app,$profile,$t,$u);}catch(DomainException $ex){http_response_code(403);simple_error('Acceso restringido',$ex->getMessage());return;}
    layout_start('FISIChat',$t);echo '<main class="fc-chat-page"><a class="fc-back" href="'.e($profile==='maestro'?url('master'):($profile==='dueno'?url('admin'):($t?url($t['slug']):url()))).'">← Volver</a>';
    fc_chat_ui($app,$profile,$t,$u,true);echo '</main>';layout_end();
}
