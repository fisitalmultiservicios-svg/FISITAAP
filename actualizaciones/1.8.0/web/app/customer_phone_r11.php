<?php
declare(strict_types=1);

function r11_ready(App $app):bool {return op180_ready($app)&&$app->setting('experience_r11_ready','0')==='1';}
function r11_whatsapp_number(array $tenant,array $branch=[]):string {
    foreach([$branch['whatsapp']??'',$tenant['whatsapp']??'',$branch['phone']??'',$tenant['phone']??''] as $value){$phone=preg_replace('/\D/','',(string)$value);if(strlen($phone)===8)$phone='506'.$phone;if(preg_match('/^[1-9][0-9]{9,14}$/D',$phone))return $phone;}return '';
}
function r11_customer_session(array $user,array $tenant):void {
    session_regenerate_id(true);$_SESSION['user']=array_intersect_key($user,array_flip(['id','tenant_id','name','email','phone','role']));$_SESSION['active_tenant_slug']=$tenant['slug'];
}
function r11_customer_finish(App $app,array $order):array {
    if(!r11_ready($app))return [];$tenant=$app->one('SELECT * FROM tenants WHERE id=?',[$order['tenant_id']]);if(!$tenant||is_demo_tenant_v12($tenant))return [];
    $user=$app->one('SELECT * FROM users WHERE id=? AND role="customer" AND is_active=1',[$order['user_id']]);if(!$user)return [];
    $phone=op180_phone((string)$order['customer_phone']);$entry=$app->one('SELECT * FROM fisitaap_guest_contacts180 WHERE tenant_id=? AND phone=? AND user_id=?',[$tenant['id'],$phone,$user['id']]);
    if($entry&&(int)$entry['phone_login181']===2&&str_ends_with($user['email'],'@registro.fisitaap.local')&&empty($user['last_login_at'])){
        $app->db->beginTransaction();try{$fresh=$app->one('SELECT * FROM users WHERE id=? FOR UPDATE',[$user['id']]);$current=$app->one('SELECT * FROM fisitaap_guest_contacts180 WHERE tenant_id=? AND phone=? FOR UPDATE',[$tenant['id'],$phone]);
            if((int)$current['phone_login181']===2&&str_ends_with($fresh['email'],'@registro.fisitaap.local')&&empty($fresh['last_login_at'])){$hash=password_hash($phone,PASSWORD_DEFAULT);$app->exec('UPDATE users SET password_hash=? WHERE id=?',[$hash,$user['id']]);$app->exec('UPDATE fisitaap_guest_contacts180 SET phone_login181=1 WHERE tenant_id=? AND phone=? AND user_id=?',[$tenant['id'],$phone,$user['id']]);$user['password_hash']=$hash;}$app->db->commit();
        }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}
    }
    $session=current_user();$authenticated=$session&&$session['role']==='customer'&&(int)$session['id']===(int)$user['id'];
    // Never replace the password of a registered account or bypass a changed password.
    if(!$session&&password_verify($phone,(string)$user['password_hash'])&&$app->one('SELECT user_id FROM fisitaap_guest_contacts180 WHERE tenant_id=? AND phone=? AND user_id=? AND phone_login181=1',[$tenant['id'],$phone,$user['id']])){r11_customer_session($user,$tenant);$authenticated=true;}
    if($authenticated)$_SESSION['active_tenant_slug']=$tenant['slug'];
    return ['customer_logged_in'=>$authenticated,'account_url'=>url('account'),'customer_login_url'=>url($tenant['slug'].'/clientes/ingresar')];
}
function r11_customer_login(App $app,array $tenant):void {
    if(is_demo_tenant_v12($tenant)){redirect(url('demo'));return;}$error='';
    if(is_post()){
        verify_csrf();try{$phone=op180_phone((string)($_POST['phone']??''));if(!rate_limit_persistent($app,'customer_phone_login',client_identity($tenant['id'].':'.$phone),8,300))throw new DomainException('Demasiados intentos. Espera unos minutos antes de volver a ingresar.');
            $contact=$app->one('SELECT u.* FROM fisitaap_guest_contacts180 c JOIN users u ON u.id=c.user_id WHERE c.tenant_id=? AND c.phone=? AND u.role="customer" AND u.is_active=1',[$tenant['id'],$phone]);
            if(!$contact)foreach($app->all('SELECT u.* FROM users u JOIN tenant_customers tc ON tc.user_id=u.id WHERE tc.tenant_id=? AND u.role="customer" AND u.is_active=1 AND u.phone LIKE ? ORDER BY u.id LIMIT 100',[$tenant['id'],'%'.substr($phone,-4).'%']) as $candidate){try{if(op180_phone((string)$candidate['phone'])===$phone){$contact=$candidate;break;}}catch(DomainException){}}
            if($contact&&r17_ready($app)&&$app->one('SELECT user_id FROM fisitaap_customer_archive_r17 WHERE tenant_id=? AND user_id=?',[$tenant['id'],$contact['id']]))$contact=null;
            if(!$contact||!password_verify((string)($_POST['password']??''),(string)$contact['password_hash']))throw new DomainException('Teléfono o contraseña incorrectos.');
            r11_customer_session($contact,$tenant);$app->exec('UPDATE users SET last_login_at=NOW() WHERE id=?',[$contact['id']]);redirect(url('account'));
        }catch(DomainException $e){$error=$e->getMessage();}
    }
    layout_start('Acceso de clientes · '.$tenant['name'],$tenant);public_header($tenant);echo '<main class="auth-page"><section class="card auth-card"><h1>Ingresa a tus pedidos</h1><p>'.e($tenant['name']).'</p><p>Tu usuario es tu teléfono. En cuentas nuevas creadas al pedir, la contraseña inicial también es tu teléfono de 8 dígitos. Si ya la cambiaste, usa tu contraseña actual.</p>'.($error?'<p class="alert alert-error" role="alert">'.e($error).'</p>':'').'<form method="post">'.csrf_field().field('Teléfono','phone',$_POST['phone']??'',true,'tel').field('Contraseña','password','',true,'password').'<button class="btn btn-block">Iniciar sesión</button></form><p><a href="'.e(url($tenant['slug'].'/'.catalog_segment($tenant))).'">Volver al catálogo</a></p><small>El teléfono es una clave inicial fácil de conocer. Puedes cambiarla desde tu cuenta.</small></section></main>';public_footer($tenant);layout_end();
}
function r11_customer_routes(App $app,string $path):void {
    if(!r11_ready($app))return;
    if(preg_match('~^([^/]+)/clientes/ingresar$~D',$path,$match)){$tenant=tenant_by_slug($app,$match[1]);if(!$tenant||!$tenant['is_active']){http_response_code(404);simple_error('Tienda fuera de servicio','Vuelve al catálogo de un negocio activo.');}else r11_customer_login($app,$tenant);exit;}
}

function r11_sync_customer_phone(App $app,int $user,string $phone):void {
    if(!$app->db->inTransaction())throw new LogicException('El cambio de teléfono requiere una transacción.');
    $phone=op180_phone($phone);$entries=$app->all('SELECT tenant_id,MAX(phone_login181) enabled FROM fisitaap_guest_contacts180 WHERE user_id=? GROUP BY tenant_id ORDER BY tenant_id',[$user]);
    foreach($entries as $entry){$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$entry['tenant_id']]);foreach($app->all('SELECT u.id,u.phone FROM users u JOIN tenant_customers tc ON tc.user_id=u.id WHERE tc.tenant_id=? AND u.id<>? AND u.role="customer" AND u.is_active=1 AND u.phone LIKE ? ORDER BY u.id LIMIT 100',[$entry['tenant_id'],$user,'%'.substr($phone,-4).'%']) as $candidate){try{$candidatePhone=op180_phone((string)$candidate['phone']);}catch(DomainException){continue;}if($candidatePhone===$phone)throw new DomainException('Ese teléfono ya pertenece a otro cliente.');}$other=$app->one('SELECT user_id FROM fisitaap_guest_contacts180 WHERE tenant_id=? AND phone=?',[$entry['tenant_id'],$phone]);if($other&&(int)$other['user_id']!==$user)throw new DomainException('Ese teléfono ya pertenece a otro cliente. Conserva tu número o solicita revisión al negocio.');}
    foreach($entries as $entry){$app->exec('DELETE FROM fisitaap_guest_contacts180 WHERE tenant_id=? AND user_id=?',[$entry['tenant_id'],$user]);$app->exec('INSERT INTO fisitaap_guest_contacts180(tenant_id,phone,user_id,phone_login181) VALUES(?,?,?,?)',[$entry['tenant_id'],$phone,$user,$entry['enabled']]);}
}
