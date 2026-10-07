<?php
declare(strict_types=1);
require_once __DIR__.'/catalog_r2.php';
require_once __DIR__.'/gifts_r2.php';
require_once __DIR__.'/business_r2.php';
require_once __DIR__.'/purchases_r2.php';
require_once __DIR__.'/accounts_r2.php';
require_once __DIR__.'/shifts_r2.php';
require_once __DIR__.'/pos_r2.php';
require_once __DIR__.'/desktop_r2.php';
require_once __DIR__.'/chat_r2.php';
require_once __DIR__.'/restructure_r3.php';

function r2_sections():array {return ['extras'=>'Extras','opcionales'=>'Opcionales','combos'=>'Combos','promociones'=>'Promociones','clientes-crear'=>'Crear clientes','clientes-buscar'=>'Buscar clientes','clientes-historial'=>'Historial de clientes','credito'=>'Crédito','proveedores'=>'Proveedores','kpis'=>'KPIs','mesas'=>'Salones y mesas','afiliados'=>'Motorizados afiliados','invitaciones'=>'Invitaciones a motorizados','entregas-historial'=>'Historial de entregas','escritorio'=>'Windows y cajas sin internet','sucursal-detalle'=>'Opciones de sucursal','contenido-anterior'=>'Banners y contenido anterior'];}
function r2_alias(string $section):string {return ['extras'=>'opciones','opcionales'=>'opciones','combos'=>'opciones','promociones'=>'cupones','clientes-crear'=>'clientes','clientes-buscar'=>'clientes','clientes-historial'=>'clientes','credito'=>'clientes','proveedores'=>'compras','kpis'=>'reportes','mesas'=>'ventas','afiliados'=>'express','invitaciones'=>'express','entregas-historial'=>'repartidor','escritorio'=>'configuracion','sucursal-detalle'=>'sucursales','contenido-anterior'=>'contenido'][$section]??$section;}
function r2_groups(array $u,?array $t):array {
    if($u['role']==='master')return ['Plataforma'=>['dashboard'=>'Resumen','empresas'=>'Negocios','planes'=>'Planes y suscripciones','modules'=>'Planes y módulos'],'Web oficial'=>['contenido'=>'Diseño y página','contenido-anterior'=>'Contenido anterior','blog'=>'Blog'],'Analítica'=>['reportes'=>'Reportes'],'Administración'=>['auditoria'=>'Auditoría','configuracion'=>'Configuración','qa'=>'Comprobar sitio','cache'=>'Caché']];
    $physical=$t&&physical_store_v14($t);$groups=['Inicio'=>['dashboard'=>'Resumen'],'Catálogo'=>['categorias'=>'Categorías','productos'=>'Productos','extras'=>'Extras','opcionales'=>'Opcionales','combos'=>'Combos','promociones'=>'Promociones','fidelizacion'=>'Fidelización','cocina'=>'Pantalla de pedidos','pedidos'=>'Historial de pedidos']];
    if($physical){$groups['Ventas']=['ventas'=>'POS / venta rápida','turnos'=>'Turnos'];if(($t['catalog_label']??'')==='Menú')$groups['Ventas']+=['mesas'=>'Salones y mesas','cobro'=>'Cobrar cuentas','express-pendientes'=>'Express pendientes'];}
    $groups['Clientes']=['clientes-crear'=>'Crear','clientes-buscar'=>'Buscar','clientes-historial'=>'Historial','fidelizacion'=>'Fidelización'];if($physical)$groups['Clientes']+=['credito'=>'Crédito','cxc'=>'Cuentas por cobrar'];
    if($physical)$groups['Compras']=['proveedores'=>'Proveedores','compras'=>'Compras','cxp'=>'Cuentas por pagar','inventario'=>'Inventario'];
    $groups['Analítica']=['reportes'=>'Reportes','kpis'=>'KPIs'];$groups['Administración']=['configuracion'=>'Generales','sucursales'=>'Sucursales','contenido'=>'Diseño','roles'=>'Roles','usuarios'=>'Usuarios','delivery'=>'Zonas de envío'];if($physical)$groups['Administración']+=['impresion'=>'Impresión','escritorio'=>'Windows y cajas sin internet'];
    if($physical)$groups['Entregas']=['afiliados'=>'Afiliados','invitaciones'=>'Invitaciones','entregas-historial'=>'Historial'];$groups['Otros recursos']=['blog'=>'Blog','contactos'=>'Contactos','auditoria'=>'Auditoría'];return $groups;
}
function r2_branch_options(App $app,array $u,array $t):void {
    $id=(int)($_GET['branch_id']??$_POST['branch_id']??0);$b=$app->one('SELECT * FROM branches WHERE id=? AND tenant_id=?',[$id,$t['id']]);if(!$b)throw new DomainException('Sucursal no disponible.');
    if(is_post()){verify_csrf();try{$payments=array_values(array_intersect(['SINPE Móvil','Efectivo','Tarjeta al recibir','Transferencia'],(array)($_POST['payment_methods']??[])));$deliveries=array_values(array_intersect(['delivery','pickup','coordinated'],(array)($_POST['delivery_types']??[])));if(!$payments||!$deliveries)throw new DomainException('Selecciona al menos un pago y una entrega.');$app->exec('UPDATE branches SET business_hours=?,payment_methods=?,delivery_types=? WHERE id=? AND tenant_id=?',[mb_substr(trim((string)($_POST['business_hours']??'')),0,2000),json_encode($payments,JSON_UNESCAPED_UNICODE),json_encode($deliveries),$id,$t['id']]);flash('success','Opciones guardadas para la sucursal.');}catch(Throwable $ex){flash('error',$ex->getMessage());}redirect(url('admin/sucursal-detalle?branch_id='.$id));}
    admin_shell_start('Horarios, pago y entrega · '.$b['name'],$u,$t,'sucursales');echo '<section class="card admin-card">'.r2_form('save').pos_hidden_148('branch_id',$id).'<label>Horarios<textarea class="input" name="business_hours">'.e($b['business_hours']).'</textarea></label><h3>Pagos</h3>';$payments=json_decode($b['payment_methods']??'null',true)?:json_decode($t['payment_methods']??'null',true)?:['Efectivo'];foreach(['SINPE Móvil','Efectivo','Tarjeta al recibir','Transferencia'] as $p)echo '<label><input type="checkbox" name="payment_methods[]" value="'.e($p).'" '.(in_array($p,$payments,true)?'checked':'').'> '.e($p).'</label>';echo '<h3>Entregas</h3>';$deliveries=json_decode($b['delivery_types']??'null',true)?:json_decode($t['delivery_types']??'null',true)?:['pickup'];foreach(['delivery'=>'Envío','pickup'=>'Recoger','coordinated'=>'Coordinado'] as $key=>$label)echo '<label><input type="checkbox" name="delivery_types[]" value="'.$key.'" '.(in_array($key,$deliveries,true)?'checked':'').'> '.$label.'</label>';echo '<button class="btn">Guardar opciones</button></form></section>';admin_shell_end();
}
function r2_drivers(App $app,array $u,array $t,string $section):void {
    if(is_post()){if($section==='invitaciones')$_POST['action']='invite';elseif(!in_array($_POST['action']??'',['affiliation_status','affiliation_scope'],true)){http_response_code(403);return;}tenant_express_v12($app,$u,$t);return;}
    admin_shell_start(['afiliados'=>'Motorizados afiliados','invitaciones'=>'Invitaciones','entregas-historial'=>'Historial de entregas'][$section],$u,$t,$section);
    if($section==='invitaciones'){echo '<section class="card admin-card">'.r2_form('invite').field('Correo del motorizado registrado','driver_email','',true,'email').'<button class="btn">Crear invitación</button></form><p>El motorizado verá y aceptará la invitación en su cuenta. Puede registrarse en '.e(url('driver-register')).'.</p></section>';}
    if($section==='afiliados'||$section==='invitaciones'){
        echo '<section class="card admin-card"><table class="table"><tr><th>Motorizado</th><th>Contacto</th><th>Estado</th><th>Acciones</th></tr>';foreach($app->all('SELECT a.*,u.name,u.email,u.phone FROM driver_affiliations a JOIN users u ON u.id=a.user_id WHERE a.tenant_id=? ORDER BY u.name',[$t['id']]) as $r){if($section==='invitaciones'&&!str_starts_with($r['status'],'pending'))continue;echo '<tr><td>'.e($r['name']).'</td><td>'.e($r['email'].' · '.$r['phone']).'</td><td>'.e(['pending_driver'=>'Esperando al motorizado','pending_store'=>'Solicitud del motorizado','active'=>'Activo','paused'=>'Pausado','blocked'=>'Bloqueado','rejected'=>'Rechazado'][$r['status']]??$r['status']).'</td><td>';if($r['status']!=='pending_driver')echo r2_form('affiliation_status').pos_hidden_148('affiliation_id',$r['id']).'<button class="btn btn-light" name="status" value="'.($r['status']==='active'?'paused':'active').'">'.($r['status']==='active'?'Pausar':'Activar').'</button><button class="btn btn-danger" name="status" value="blocked">Bloquear</button></form>';echo '</td></tr>';}echo '</table><a class="btn btn-light" href="'.url('admin/express').'">Sucursales autorizadas y pedidos express</a></section>';
    }else{[$from,$to]=valid_report_dates_v1();$status=(string)($_GET['status']??'');echo '<section class="card admin-card"><form method="get"><div class="split">'.field('Desde','from',$from,true,'date').field('Hasta','to',$to,true,'date').'</div>'.field('Estado (opcional)','status',$status).'<button class="btn btn-light">Filtrar</button></form><table class="table"><tr><th>Pedido</th><th>Motorizado</th><th>Estado</th><th>Fecha</th><th>Pago</th></tr>';foreach($app->all('SELECT j.*,o.order_number,u.name FROM delivery_jobs j JOIN orders o ON o.id=j.order_id LEFT JOIN users u ON u.id=j.assigned_driver_id WHERE j.tenant_id=? AND DATE(j.created_at) BETWEEN ? AND ? AND (?="" OR j.status=?) ORDER BY j.id DESC LIMIT 300',[$t['id'],$from,$to,$status,$status]) as $r)echo '<tr><td>'.e($r['order_number']).'</td><td>'.e($r['name']??'Sin asignar').'</td><td>'.e($r['status']).'</td><td>'.e($r['created_at']).'</td><td>'.money((float)$r['payout']).'</td></tr>';echo '</table></section>';}admin_shell_end();
}
function r2_dispatch(App $app,string $path):void {
    if(!r2_active($app))return;
    r3_dispatch($app,$path);
    if(str_starts_with($path,'api/desktop/'))r2_desktop_api($app,substr($path,12));
    if($path==='api/fisichat-r2')r2_chat_api($app);
    if($path==='account/access'){r2_customer_access($app);exit;}
    if($path==='asistente'){r2_chat_page($app);exit;}
    if($path===''){r2_landing($app,null);exit;}
    if($path==='master/contenido'){r2_design($app,require_login(['master']),null);exit;}
    if($path==='master/contenido-anterior'){master_settings_v1($app,require_login(['master']),'contenido');exit;}
    if(!str_contains($path,'/')&&($t=tenant_by_slug($app,$path))&&$t['is_active']){if((current_user()['role']??'')==='customer')$_SESSION['active_tenant_slug']=$t['slug'];r2_landing($app,$t);exit;}
    if(!str_starts_with($path,'admin/'))return;$section=explode('/',$path)[1];
    $handlers=['turnos','cxc','cxp','extras','opcionales','combos','promociones','clientes','clientes-crear','clientes-buscar','clientes-historial','credito','proveedores','compras','kpis','contenido','contenido-anterior','mesas','escritorio','sucursal-detalle','afiliados','invitaciones','entregas-historial'];if(!in_array($section,$handlers,true))return;
    $u=require_login(['tenant_admin','manager','editor','kitchen','cashier']);$t=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$u['tenant_id']]);if(!$t){http_response_code(403);exit('Negocio no disponible.');}require_section_permission($u,$section);require_module_v12($app,$t,section_module_v12($section));
    if(in_array($section,['turnos','credito','proveedores','compras','cxc','cxp','mesas','escritorio'],true)&&!physical_store_v14($t)){http_response_code(403);simple_error('Modo tienda física inactivo','Este apartado requiere el modo de tienda física.');exit;}
    try{match($section){
        'turnos'=>r2_shifts($app,$u,$t),
        'cxc','cxp'=>r2_accounts($app,$u,$t,$section),
        'extras'=>r2_modifiers($app,$u,$t,'extra'),'opcionales'=>r2_modifiers($app,$u,$t,'optional'),'combos'=>r2_combos($app,$u,$t),'promociones'=>r2_promotions($app,$u,$t),
        'clientes','clientes-crear','clientes-buscar','clientes-historial','credito'=>r2_customers($app,$u,$t,$section),'proveedores'=>r2_suppliers($app,$u,$t),'compras'=>r2_purchases($app,$u,$t),'kpis'=>r2_kpis($app,$u,$t),
        'contenido'=>r2_design($app,$u,$t),'contenido-anterior'=>tenant_content_admin_v6($app,$u,$t),'mesas'=>r2_floor($app,$u,$t),'escritorio'=>r2_desktop_admin($app,$u,$t),'sucursal-detalle'=>r2_branch_options($app,$u,$t),
        'afiliados','invitaciones','entregas-historial'=>r2_drivers($app,$u,$t,$section),default=>null};
    }catch(DomainException $ex){http_response_code(422);simple_error('Revisa los datos',$ex->getMessage());}exit;
}
