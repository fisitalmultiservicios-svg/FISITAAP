<?php
declare(strict_types=1);
require_once __DIR__.'/academy_r17.php';
require_once __DIR__.'/personnel_r17.php';

function r17_ready(App $app):bool {return $app->setting('platform_r17_ready','0')==='1';}
function r17_menu(array $tenant):bool {return ($tenant['catalog_label']??'')==='Menú';}
function r17_delivery(App $app,array $tenant):bool {return module_enabled_v12($app,$tenant,'express')&&module_enabled_v12($app,$tenant,'drivers')&&!is_demo_tenant_v12($tenant);}
function r17_section(App $app,array $tenant,string $section):bool {
    if(!r17_menu($tenant)&&in_array($section,['mesas','salones','cocina','express-pendientes'],true))return false;
    if(!r17_delivery($app,$tenant)&&in_array($section,['express','express-pendientes','afiliados','invitaciones','entregas-historial','repartidor'],true))return false;
    return true;
}
function r17_schema(App $app):void {
    $tables=[
        'fisitaap_address_zones_r17'=>'tenant_id BIGINT UNSIGNED NOT NULL,address_id BIGINT UNSIGNED NOT NULL,zone_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(tenant_id,address_id),INDEX zone_scope(tenant_id,zone_id)',
        'fisitaap_customer_archive_r17'=>'tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,archived_by BIGINT UNSIGNED NOT NULL,archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,reason VARCHAR(500) NOT NULL,PRIMARY KEY(tenant_id,user_id)',
        'fisitaap_learning_r17'=>'tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 0,user_id BIGINT UNSIGNED NOT NULL,course_key VARCHAR(30) NOT NULL,step_key VARCHAR(80) NOT NULL,done TINYINT NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(user_id,course_key,step_key)',
        'fisitaap_setup_r17'=>'tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,step INT NOT NULL DEFAULT 0,completed_at DATETIME NULL,paused TINYINT NOT NULL DEFAULT 0',
    ];
    foreach($tables as $table=>$definition)$app->db->exec('CREATE TABLE IF NOT EXISTS '.$table.' ('.$definition.') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    if(!$app->one('SHOW COLUMNS FROM users LIKE "username_r17"'))$app->db->exec('ALTER TABLE users ADD username_r17 VARCHAR(80) NULL, ADD UNIQUE KEY username_r17(username_r17)');
    // Repair only never-used automatically generated accounts awaiting initial access.
    foreach($app->all('SELECT u.id,u.phone FROM users u JOIN fisitaap_guest_contacts180 c ON c.user_id=u.id WHERE c.phone_login181=2 AND u.role="customer" AND u.last_login_at IS NULL AND u.email LIKE "%@registro.fisitaap.local" AND NOT EXISTS(SELECT 1 FROM password_resets pr WHERE pr.user_id=u.id AND pr.used_at IS NOT NULL) GROUP BY u.id') as $u){
        try{$phone=op180_phone((string)$u['phone']);}catch(DomainException){continue;}
        $app->exec('UPDATE users SET password_hash=? WHERE id=? AND last_login_at IS NULL',[password_hash($phone,PASSWORD_DEFAULT),$u['id']]);
        $app->exec('UPDATE fisitaap_guest_contacts180 SET phone_login181=1 WHERE user_id=? AND phone_login181=2',[$u['id']]);
    }
    foreach($app->all('SELECT u.id,u.phone,s.tenant_id FROM users u JOIN fisitaap_r2_customer_sources s ON s.user_id=u.id WHERE u.role="customer" AND u.last_login_at IS NULL AND u.email LIKE "%@registro.fisitaap.local" AND s.source IN("panel","caja") AND NOT EXISTS(SELECT 1 FROM fisitaap_guest_contacts180 c WHERE c.user_id=u.id) AND NOT EXISTS(SELECT 1 FROM password_resets p WHERE p.user_id=u.id AND p.used_at IS NOT NULL)') as $u){try{$phone=op180_phone((string)$u['phone']);}catch(DomainException){continue;}$app->exec('UPDATE users SET password_hash=? WHERE id=? AND last_login_at IS NULL',[password_hash($phone,PASSWORD_DEFAULT),$u['id']]);$app->exec('INSERT IGNORE INTO fisitaap_guest_contacts180(tenant_id,phone,user_id,phone_login181) VALUES(?,?,?,1)',[$u['tenant_id'],$phone,$u['id']]);}
    hr17_schema($app);
    $app->exec('INSERT INTO settings(`key`,`value`) VALUES("platform_r17_ready","1") ON DUPLICATE KEY UPDATE value="1"');
}
function r17_zone_select(App $app,array $tenant,mixed $selected=0):string {
    return '<label>Zona de envío'.r2_select('zone_id',$app->all('SELECT id,name FROM delivery_zones WHERE tenant_id=? AND is_active=1 ORDER BY name',[$tenant['id']]),$selected,'name',true).'</label><small>Elige una zona creada en Administración → Zonas de envío.</small>';
}
function r17_address_zone(App $app,int $tenant,int $address,int $zone):void {
    if(!$app->db->inTransaction())throw new LogicException('La dirección y su zona deben guardarse juntas.');
    if(!$app->one('SELECT id FROM delivery_zones WHERE id=? AND tenant_id=? AND is_active=1',[$zone,$tenant]))throw new DomainException('Selecciona una zona de envío activa de este negocio.');
    if(!$app->one('SELECT a.id FROM customer_addresses a JOIN tenant_customers c ON c.user_id=a.user_id WHERE a.id=? AND c.tenant_id=?',[$address,$tenant]))throw new DomainException('Dirección no disponible.');
    $app->exec('INSERT INTO fisitaap_address_zones_r17(tenant_id,address_id,zone_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE zone_id=VALUES(zone_id)',[$tenant,$address,$zone]);
}
function r17_staff_identity(App $app,string $login):?array {
    $login=mb_strtolower(trim($login));
    return $app->one('SELECT * FROM users WHERE is_active=1 AND (LOWER(email)=?'.(r17_ready($app)?' OR username_r17=?':'').')',r17_ready($app)?[$login,$login]:[$login]);
}
function r17_email_unique(App $app,string $email,int $except=0):void {
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$app->one('SELECT id FROM users WHERE LOWER(TRIM(email))=? AND id<>?',[mb_strtolower(trim($email)),$except]))throw new DomainException('Ese correo ya está en uso o no es válido. Cada usuario debe tener un correo único en FISITAAP.');
}
function r17_customer_identity(App $app,string $phone,?int $tenant=null):?array {
    try{$phone=op180_phone($phone);}catch(DomainException){return null;}
    $rows=$app->all('SELECT DISTINCT u.* FROM fisitaap_guest_contacts180 c JOIN users u ON u.id=c.user_id JOIN tenant_customers tc ON tc.user_id=u.id AND tc.tenant_id=c.tenant_id LEFT JOIN fisitaap_customer_archive_r17 a ON a.tenant_id=c.tenant_id AND a.user_id=u.id WHERE c.phone=? AND u.role="customer" AND u.is_active=1 AND a.user_id IS NULL'.($tenant?' AND c.tenant_id=?':'').' LIMIT 2',$tenant?[$phone,$tenant]:[$phone]);
    if(count($rows)>1)throw new DomainException('Ese teléfono tiene cuentas en varias tiendas. Ingresa desde el botón de clientes del catálogo de tu tienda.');
    return $rows[0]??null;
}
function r17_dispatch(App $app,string $path):void {
    if(!r17_ready($app))return;
    if(in_array($path,['ayuda','manuales','academiaap','api/academiaap','admin/configurador','api/personal/marcar','admin/personal'],true)){
        if($path==='api/personal/marcar'){hr17_clock_api($app);exit;}
        if(in_array($path,['ayuda','manuales','academiaap','api/academiaap'],true)){academy17_page($app,$path);exit;}
        try{[$u,$t]=qa180_business($app,$path==='admin/personal'?'personal':'dashboard');
        if($path==='admin/personal')hr17_page($app,$u,$t);else academy17_setup($app,$u,$t);}catch(DomainException $e){http_response_code(422);simple_error('Revisa la operación',$e->getMessage());}exit;
    }
    if($path==='admin/usuarios'&&($u=current_user())&&!empty($u['tenant_id'])){$t=$app->one('SELECT * FROM tenants WHERE id=?',[$u['tenant_id']]);if($t&&module_enabled_v12($app,$t,'personnel')){require_module_v12($app,$t,'team');redirect(url('admin/personal'));}}
    if(str_starts_with($path,'admin/')&&($u=current_user())&&!empty($u['tenant_id'])){
        $t=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$u['tenant_id']]);
        if($t&&!r17_section($app,$t,explode('/',$path)[1]??'')){http_response_code(403);simple_error('Función no disponible','Esta función no corresponde al tipo de tienda o sus módulos.');exit;}
    }
}
