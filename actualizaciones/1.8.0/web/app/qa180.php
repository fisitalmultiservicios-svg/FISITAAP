<?php
declare(strict_types=1);
require_once __DIR__.'/operations180.php';
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
function qa180_activate(App $app):void {
    $user=require_login(['master']);$message='';
    if(!$app->one('SELECT id FROM users WHERE id=? AND role="master" AND is_active=1',[$user['id']]))throw new DomainException('Acceso no autorizado.');
    if(is_post()){
        verify_csrf();try{
            if(($_POST['backup']??'')!=='yes')throw new DomainException('Confirma el respaldo y la actualización de las cajas.');
            if($app->one('SELECT id FROM pos_shifts WHERE status="open" LIMIT 1'))throw new DomainException('Cierra y sincroniza todos los turnos antes de activar la actualización.');
            if($app->one('SELECT t.id FROM pos_tickets t JOIN pos_ticket_items i ON i.ticket_id=t.id WHERE t.status NOT IN("paid","void","merged") LIMIT 1'))throw new DomainException('Cobra o cancela las cuentas con productos antes de activar, para actualizar las opciones de catálogo con seguridad.');
            schema180($app);
            qa180_modifiers_upgrade($app);
            qa180_register_upgrade($app);
            qa180_floor_upgrade($app);
            $app->exec('INSERT INTO settings(`key`,`value`) VALUES("qa180_active","1") ON DUPLICATE KEY UPDATE `value`="1"');
            audit($app,null,(int)$user['id'],'activate180','configuration',null);
            $message='Actualización 1.8.0 activada. Revisa las cajas asignadas a cada equipo antes de cobrar.';
        }catch(Throwable $error){$message=$error instanceof PDOException?'No se completó la activación. Conserva el respaldo y revisa el registro de errores del hosting. Puedes repetir este paso.':$error->getMessage();}
    }
    layout_start('Actualizar FISITAAP 1.8.0');
    echo '<main class="auth-page"><section class="card auth-card"><h1>Actualizar 1.8.0</h1><p>Incluye las correcciones del QA manual. Los cobros y movimientos de efectivo se realizan desde Windows o Android autorizados.</p><p role="status">'.e($message).'</p><form method="post" action="'.e(url('activar-180.php')).'">'.csrf_field().'<label><input type="checkbox" name="backup" value="yes" required> Guardé respaldo, cerré y sincronicé los turnos y actualicé las aplicaciones de las cajas.</label><button class="btn">Aplicar actualización</button></form></section></main>';layout_end();
}
function qa180_dispatch(App $app,string $path):void {
    if($path==='master/actualizar-180'){qa180_activate($app);exit;}
    if(!qa180_active($app))return;
    if(preg_match('~(?:^|/)blog(?:/|$)~',$path)){http_response_code(404);simple_error('Página no disponible','Vuelve al catálogo o al inicio.');exit;}
    if($path==='logout'){
        if(!is_post())redirect(url('owner-login'));verify_csrf();$user=current_user();if($user)audit($app,$user['tenant_id']?(int)$user['tenant_id']:null,(int)$user['id'],'logout','user',(int)$user['id']);$_SESSION=[];session_regenerate_id(true);redirect(url('owner-login'));
    }
    if($path==='master'){qa180_welcome($app,require_login(['master']),null);exit;}
    if($path==='master/bienvenida'){qa180_welcome_editor($app,require_login(['master']));exit;}
    if($path==='master/kpis'){master_dashboard($app,require_login(['master']));exit;}
    if($path==='master/contactos'){qa180_contacts($app,require_login(['master']),null);exit;}
    if($path==='master/auditoria'){qa180_log($app,require_login(['master']),null);exit;}
    if($path==='master/escritorio'){qa180_devices($app,require_login(['master']),null);exit;}
    if(in_array($path,['admin','admin/bienvenida'],true)){$user=require_login(['tenant_admin','manager','editor','cashier','kitchen']);$tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$user['tenant_id']]);if(!$tenant)throw new DomainException('Negocio no disponible.');qa180_welcome($app,$user,$tenant);exit;}
    if($path==='api/native/bind'&&is_post())qa180_bind($app);
    if($path==='api/native/print-test'&&is_post())qa180_print_test($app);
    if($path==='api/pos-options')qa180_pos_options($app);
    $handlers=['turnos','caja-movimiento','cxc','cxp','cuentas-dinero','compras','contactos','auditoria','kpis','clientes','clientes-crear','clientes-buscar','clientes-historial','credito','extras','opcionales','producto-extras','categoria-extras','escritorio','salones','impresion'];
    if(!str_starts_with($path,'admin/'))return;
    $section=explode('/',$path)[1];if(!in_array($section,$handlers,true))return;
    try{
        [$user,$tenant]=qa180_business($app,['caja-movimiento'=>'turnos','cuentas-dinero'=>'configuracion','producto-extras'=>'productos','categoria-extras'=>'categorias'][$section]??$section);
        if($section==='turnos')qa180_shifts($app,$user,$tenant);
        elseif($section==='caja-movimiento')qa180_cash_move($app,$user,$tenant);
        elseif(in_array($section,['cxc','cxp'],true))qa180_accounts($app,$user,$tenant,$section);
        elseif($section==='cuentas-dinero')qa180_money_accounts($app,$user,$tenant);
        elseif($section==='compras')qa180_purchases($app,$user,$tenant);
        elseif($section==='contactos')qa180_contacts($app,$user,$tenant);
        elseif($section==='auditoria')qa180_log($app,$user,$tenant);
        elseif($section==='kpis')tenant_dashboard($app,$user,$tenant);
        elseif(in_array($section,['clientes','clientes-crear','clientes-buscar','clientes-historial','credito'],true))qa180_customers($app,$user,$tenant,$section);
        elseif(in_array($section,['extras','opcionales'],true))qa180_modifiers($app,$user,$tenant,$section==='extras'?'extra':'optional');
        elseif(in_array($section,['producto-extras','categoria-extras'],true))qa180_modifier_assignment($app,$user,$tenant,$section==='categoria-extras');
        elseif($section==='escritorio')qa180_devices($app,$user,$tenant);
        elseif($section==='impresion')qa180_printers($app,$user,$tenant);
        elseif($section==='salones')r3_floor($app,$user,$tenant);
    }catch(Throwable $error){http_response_code(422);simple_error('Revisa la operación',$error instanceof PDOException?'No se pudo completar la operación. Revisa su estado antes de volver a intentar.':$error->getMessage());}
    exit;
}
