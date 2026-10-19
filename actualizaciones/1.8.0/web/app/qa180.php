<?php
declare(strict_types=1);
require_once __DIR__.'/operations180.php';
require_once __DIR__.'/updates180.php';
require_once __DIR__.'/cash180.php';
require_once __DIR__.'/finance180.php';
require_once __DIR__.'/purchases180.php';
require_once __DIR__.'/panel180.php';
require_once __DIR__.'/chat180.php';
require_once __DIR__.'/customers180.php';
require_once __DIR__.'/modifiers180.php';
require_once __DIR__.'/devices180.php';
require_once __DIR__.'/pos180.php';
require_once __DIR__.'/floor180.php';
require_once __DIR__.'/offline180.php';
require_once __DIR__.'/printing180.php';
require_once __DIR__.'/coupons180.php';
require_once __DIR__.'/orders180.php';
require_once __DIR__.'/reports180.php';
require_once __DIR__.'/settings180.php';
require_once __DIR__.'/loyalty180.php';
require_once __DIR__.'/kpis180.php';
require_once __DIR__.'/operaciones180.php';

function qa180_ui_context(App $app):array {
    $user=current_user();$context=['base'=>url(),'enabled'=>true,'user_id'=>(int)($user['id']??0),'nonce'=>$_SESSION['native_nonce180']??null,'native'=>null];
    if($user&&in_array($user['role'],['tenant_admin','manager','editor','cashier','kitchen'],true)){
        $register=qa180_native($app,$user,false,'print');
        if($register)$context['native']=['id'=>(int)$register['id'],'name'=>$register['name'],'device_id'=>(int)($register['device_id']??0),'branch_id'=>(int)$register['branch_id']];
    }
    return $context;
}

function qa180_business(App $app,string $section):array {
    $user=require_login(['tenant_admin','manager','editor','cashier','kitchen']);
    $tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$user['tenant_id']]);
    if(!$tenant)throw new DomainException('El negocio no está disponible.');
    require_section_permission($user,$section);require_module_v12($app,$tenant,section_module_v12($section));
    return [$user,$tenant];
}
function qa180_activate(App $app):void { qa180_updates_page($app,true); }
function qa180_dispatch(App $app,string $path):void {
    if(in_array($path,['master/actualizar-180','master/actualizar-sistema'],true)){qa180_updates_page($app,true);exit;}
    if($path==='admin/actualizar-sistema'){qa180_updates_page($app,false);exit;}
    // Shared welcome is a platform view, independent of each tenant's activation.
    if(qa180_active($app,0)){
        if($path==='master'){qa180_welcome($app,require_login(['master']),null);exit;}
        if($path==='master/bienvenida'){qa180_welcome_editor($app,require_login(['master']));exit;}
        if(in_array($path,['admin','admin/bienvenida'],true)){if((current_user()['role']??'')==='master')redirect(url('master'));$user=require_login(['tenant_admin','manager','editor','cashier','kitchen']);$tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$user['tenant_id']]);if(!$tenant)reject_business_login180('Tienda fuera de servicio. La cuenta maestra debe revisar la asociación y el estado del negocio.');qa180_welcome($app,$user,$tenant);exit;}
    }
    if(!qa180_active($app))return;
    if(preg_match('~(?:^|/)blog(?:/|$)|^(?:admin|master)/posts(?:/|$)~',$path)){http_response_code(404);simple_error('Página no disponible','Vuelve al catálogo o al inicio.');exit;}
    if(op180_ready($app)){
        if($path==='api/live180')op180_live($app);
        if($path==='api/incoming180')op180_incoming($app);
        if($path==='api/delivery-addresses180')op180_address_lookup($app);
        if($path==='api/order-proof180'){if(is_post())op180_proof_upload($app);else op180_proof_view($app);}
        if($path==='admin/pedido-detalle180'){op180_order_detail($app);exit;}
        if($path==='pedido/seguimiento'){op180_order_detail($app,true);exit;}
    }
    if($path==='logout'){
        if(!is_post())redirect(url('owner-login'));verify_csrf();$user=current_user();if($user)audit($app,$user['tenant_id']?(int)$user['tenant_id']:null,(int)$user['id'],'logout','user',(int)$user['id']);$_SESSION=[];session_regenerate_id(true);redirect(url('owner-login'));
    }
    if($path==='master/kpis'){master_dashboard($app,require_login(['master']));exit;}
    if($path==='master/contactos'){qa180_contacts($app,require_login(['master']),null);exit;}
    if($path==='master/auditoria'){qa180_log($app,require_login(['master']),null);exit;}
    if($path==='master/escritorio'){qa180_devices($app,require_login(['master']),null);exit;}
    if($path==='api/native/bind'&&is_post())qa180_bind($app);
    if($path==='api/gift-preview'&&is_post())qa180_gift_preview($app);
    if($path==='api/native/print-test'&&is_post())qa180_print_test($app);
    if($path==='api/coupon-preview'&&is_post())qa180_coupon_preview($app);
    if($path==='api/pos-options')qa180_pos_options($app);
    $handlers=['turnos','caja-movimiento','cxc','cxp','cuentas-dinero','compras','contactos','auditoria','kpis','clientes','clientes-crear','clientes-buscar','clientes-historial','credito','extras','opcionales','producto-extras','categoria-extras','escritorio','salones','impresion','cupones','pedidos','cocina','reportes','configuracion','contenido'];
    if(!str_starts_with($path,'admin/'))return;
    $section=explode('/',$path)[1];if(!in_array($section,$handlers,true))return;
    try{
        [$user,$tenant]=qa180_business($app,['cupones'=>'promociones','caja-movimiento'=>'turnos','cuentas-dinero'=>'configuracion','producto-extras'=>'productos','categoria-extras'=>'categorias'][$section]??$section);
        if(in_array($section,['pedidos','cocina'],true))qa180_orders($app,$user,$tenant,$section);
        elseif($section==='turnos')qa180_shifts($app,$user,$tenant);
        elseif($section==='caja-movimiento')qa180_cash_move($app,$user,$tenant);
        elseif(in_array($section,['cxc','cxp'],true))qa180_accounts($app,$user,$tenant,$section);
        elseif($section==='cuentas-dinero')qa180_money_accounts($app,$user,$tenant);
        elseif($section==='compras')qa180_purchases($app,$user,$tenant);
        elseif($section==='contactos')qa180_contacts($app,$user,$tenant);
        elseif($section==='auditoria')qa180_log($app,$user,$tenant);
        elseif($section==='configuracion')qa180_settings($app,$user,$tenant);
        elseif($section==='contenido')qa180_design($app,$user,$tenant);
        elseif($section==='reportes')qa180_reports($app,$user,$tenant);
        elseif($section==='kpis')qa180_kpis($app,$user,$tenant);
        elseif(in_array($section,['clientes','clientes-crear','clientes-buscar','clientes-historial','credito'],true))qa180_customers($app,$user,$tenant,$section);
        elseif(in_array($section,['extras','opcionales'],true))qa180_modifiers($app,$user,$tenant,$section==='extras'?'extra':'optional');
        elseif(in_array($section,['producto-extras','categoria-extras'],true))qa180_modifier_assignment($app,$user,$tenant,$section==='categoria-extras');
        elseif($section==='escritorio')qa180_devices($app,$user,$tenant);
        elseif($section==='cupones')qa180_coupons($app,$user,$tenant);
        elseif($section==='impresion')qa180_printers($app,$user,$tenant);
        elseif($section==='salones')r3_floor($app,$user,$tenant);
    }catch(Throwable $error){http_response_code(422);simple_error('Revisa la operación',$error instanceof PDOException?'No se pudo completar la operación. Revisa su estado antes de volver a intentar.':$error->getMessage());}
    exit;
}
