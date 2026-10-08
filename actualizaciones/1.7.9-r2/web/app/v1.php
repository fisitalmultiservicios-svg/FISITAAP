<?php
declare(strict_types=1);

function setting_bool(App $app,string $key,bool $fallback=false):bool
{
    $value=strtolower(trim($app->setting($key,$fallback?'1':'0')));
    return in_array($value,['1','true','yes','si','sí','on'],true);
}

function safe_next_url_v1(App $app,?string $candidate):?string
{
    if(!$candidate)return null;$candidate=trim($candidate);if($candidate===''||str_starts_with($candidate,'//'))return null;
    $base=rtrim(url(),'/');if(preg_match('~^[a-z][a-z0-9+.-]*://~i',$candidate)){if(!str_starts_with($candidate,$base.'/')&&$candidate!==$base)return null;$path=(string)parse_url($candidate,PHP_URL_PATH);$appPath=rtrim((string)(parse_url(url(),PHP_URL_PATH)??''),'/');if($appPath!==''&&($path===$appPath||str_starts_with($path,$appPath.'/')))$path=substr($path,strlen($appPath));$query=(string)(parse_url($candidate,PHP_URL_QUERY)??'');$candidate=ltrim($path,'/').($query!==''?'?'.$query:'');}
    $path=(string)(parse_url('/'.ltrim($candidate,'/'),PHP_URL_PATH)??'');if(str_contains($path,'..')||preg_match('~/(?:login|register|owner-login|master-login|logout)/?$~',$path))return null;return ltrim($candidate,'/');
}

function current_url_v1():string
{
    $uri=(string)($_SERVER['REQUEST_URI']??'/');$requestPath=(string)(parse_url($uri,PHP_URL_PATH)??'/');$appPath=rtrim((string)(parse_url(url(),PHP_URL_PATH)??''),'/');if($appPath!==''&&($requestPath===$appPath||str_starts_with($requestPath,$appPath.'/')))$requestPath=substr($requestPath,strlen($appPath));$query=(string)(parse_url($uri,PHP_URL_QUERY)??'');return ltrim($requestPath,'/').($query!==''?'?'.$query:'');
}

function role_home_url_v1(App $app,array $user):string
{
    if (function_exists('restructure_home_r1')) {
        $customHome = restructure_home_r1($app, $user);
        if ($customHome !== null) return $customHome;
    }
    if($user['role']==='master')return url('master');if($user['role']==='driver')return url('driver');if($user['role']==='customer'){if(!empty($_SESSION['active_tenant_slug'])){$tenant=tenant_by_slug($app,(string)$_SESSION['active_tenant_slug']);if($tenant)return url($tenant['slug'].'/'.catalog_segment($tenant));}return url('account');}return url('admin');
}

function role_nav_links_v1(App $app,array $user,?array $tenant=null):array
{
    $role=(string)$user['role'];if($role==='master')return ['master'=>'Resumen','master/empresas'=>'Empresas','master/planes'=>'Planes','master/modules'=>'Planes y módulos','master/contenido'=>'Web oficial','master/reportes'=>'Reportes','master/qa'=>'QA del sitio'];if($role==='driver')return ['driver'=>'Express disponibles'];
    if($role==='customer'){$slug=$tenant['slug']??($_SESSION['active_tenant_slug']??'');$links=[];if($slug){$shop=$tenant?:tenant_by_slug($app,$slug);if($shop)$links[$slug.'/'.catalog_segment($shop)]=$shop['catalog_label'];}else $links['#negocios']='Negocios';$links['account']='Mis pedidos';$links['account#perfil']='Mi cuenta';return $links;}
    $viewLabel=(($tenant['catalog_label']??'Catálogo')==='Menú')?'Vista cocina':'Vista monitor';$map=['dashboard'=>'Resumen','sucursales'=>'Sucursales','pedidos'=>'Pedidos','cocina'=>$viewLabel,'ventas'=>'Ventas','cobro'=>'Cobro','express-pendientes'=>'Express pendientes','turnos'=>'Turnos','inventario'=>'Inventario','compras'=>'Compras','cxc'=>'CxC','cxp'=>'CxP','repartidor'=>'Entregas','express'=>'FISITAPP Express','productos'=>'Productos','opciones'=>'Opciones','categorias'=>'Categorías','clientes'=>'Clientes','fidelizacion'=>'Fidelización','contenido'=>'Página','blog'=>'Blog','contactos'=>'Contactos','reportes'=>'Reportes','usuarios'=>'Equipo','configuracion'=>'Configuración'];$links=[];foreach($map as $section=>$label){if(in_array($section,['cobro','express-pendientes'],true)&&($tenant['catalog_label']??'')!=='Menú')continue;if(in_array($section,['ventas','cobro','express-pendientes','turnos','compras','cxc','cxp'],true)&&(!$tenant||!physical_store_v14($tenant)))continue;if(role_can($role,$section)&&(!$tenant||!function_exists('module_enabled_v12')||module_enabled_v12($app,$tenant,section_module_v12($section))))$links['admin'.($section==='dashboard'?'':'/'.$section)]=$label;}return $links;
}

function search_log_api_v1(App $app):never
{
    $raw=json_input_qa();$_POST=$raw+$_POST;verify_csrf();$tenant=tenant_by_slug($app,(string)($raw['tenant']??''));$query=trim(substr((string)($raw['query']??''),0,190));$count=max(0,(int)($raw['result_count']??0));if(!$tenant||strlen($query)<2)json_response(['ok'=>false],422);if(rate_limit_persistent($app,'search',client_identity((string)$tenant['id']),60,300))$app->exec('INSERT INTO search_logs(tenant_id,query,result_count) VALUES(?,?,?)',[$tenant['id'],$query,$count]);json_response(['ok'=>true]);
}

function track_event_v1(App $app,int $tenantId,string $event):void
{
    if(!in_array($event,['catalog_view','checkout_completed'],true))return;$sessionHash=hash('sha256',session_id().'|'.$tenantId.'|'.date('Y-m-d').'|'.$app->config['app_key']);try{if($event==='catalog_view'&&$app->one('SELECT id FROM page_events WHERE tenant_id=? AND event_type=? AND session_hash=?',[$tenantId,$event,$sessionHash]))return;$app->exec('INSERT INTO page_events(tenant_id,event_type,session_hash) VALUES(?,?,?)',[$tenantId,$event,$sessionHash]);}catch(Throwable){}
}

function smtp_read($socket):string
{
    $response='';
    while(($line=fgets($socket,515))!==false){$response.=$line;if(strlen($line)<4||$line[3]!=='-')break;}
    return $response;
}

function smtp_command($socket,string $command,array $accepted):string
{
    if($command!=='')fwrite($socket,$command."\r\n");
    $response=smtp_read($socket);$code=(int)substr($response,0,3);
    if(!in_array($code,$accepted,true))throw new RuntimeException('SMTP respondió '.$code.'.');
    return $response;
}

function secret_encrypt_v1(App $app,string $value):string
{
    if($value==='')return '';$key=hash('sha256',(string)$app->config['app_key'],true);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('No se pudo proteger la credencial SMTP.');return 'enc:v1:'.base64_encode($iv.$tag.$cipher);
}

function secret_decrypt_v1(App $app,string $value):string
{
    if(!str_starts_with($value,'enc:v1:'))return $value;$raw=base64_decode(substr($value,7),true);if($raw===false||strlen($raw)<29)return '';$key=hash('sha256',(string)$app->config['app_key'],true);$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));return $plain===false?'':$plain;
}

function send_mail_v1(App $app,string $to,string $subject,string $text,?int $tenantId=null):bool
{
    $status='failed';$error=null;
    try{
        if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Destino inválido.');
        $host=trim($app->setting('smtp_host'));$port=(int)$app->setting('smtp_port','587');$user=trim($app->setting('smtp_user'));$pass=secret_decrypt_v1($app,$app->setting('smtp_pass'));$encryption=strtolower($app->setting('smtp_encryption','tls'));$from=trim($app->setting('smtp_from_email',$user));$fromName=trim($app->setting('smtp_from_name','FISITAPP'));
        if($host===''||$user===''||$pass===''||!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('SMTP no configurado.');
        $transport=$encryption==='ssl'?'ssl://':'';$socket=@stream_socket_client($transport.$host.':'.$port,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
        if(!$socket)throw new RuntimeException('No se pudo conectar al servidor SMTP.');
        stream_set_timeout($socket,15);smtp_command($socket,'',[220]);smtp_command($socket,'EHLO '.($_SERVER['SERVER_NAME']??'fisitapp.local'),[250]);
        if($encryption==='tls'){smtp_command($socket,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('No se pudo activar TLS.');smtp_command($socket,'EHLO '.($_SERVER['SERVER_NAME']??'fisitapp.local'),[250]);}
        smtp_command($socket,'AUTH LOGIN',[334]);smtp_command($socket,base64_encode($user),[334]);smtp_command($socket,base64_encode($pass),[235]);smtp_command($socket,'MAIL FROM:<'.$from.'>',[250]);smtp_command($socket,'RCPT TO:<'.$to.'>',[250,251]);smtp_command($socket,'DATA',[354]);
        $safeSubject='=?UTF-8?B?'.base64_encode(str_replace(["\r","\n"],' ',$subject)).'?=';$headers=['From: '.$fromName.' <'.$from.'>','To: <'.$to.'>','Subject: '.$safeSubject,'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: 8bit','Date: '.date(DATE_RFC2822),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.($_SERVER['SERVER_NAME']??'fisitapp.local').'>'];
        $body=preg_replace('/(?m)^\./','..',str_replace(["\r\n","\r"],"\n",$text));fwrite($socket,implode("\r\n",$headers)."\r\n\r\n".str_replace("\n","\r\n",$body)."\r\n.\r\n");$response=smtp_read($socket);if((int)substr($response,0,3)!==250)throw new RuntimeException('El servidor SMTP rechazó el mensaje.');@fwrite($socket,"QUIT\r\n");fclose($socket);$status='sent';
    }catch(Throwable $exception){$error=substr($exception->getMessage(),0,500);}
    try{$app->exec('INSERT INTO mail_logs(tenant_id,recipient,subject,status,error_message,created_at) VALUES(?,?,?,?,?,NOW())',[$tenantId,$to,substr($subject,0,190),$status,$error]);}catch(Throwable){}
    return $status==='sent';
}

function issue_email_verification_v1(App $app,int $userId,string $email):void
{
    $token=bin2hex(random_bytes(32));$app->exec('DELETE FROM email_verifications WHERE user_id=?',[$userId]);$app->exec('INSERT INTO email_verifications(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 24 HOUR))',[$userId,hash('sha256',$token)]);send_mail_v1($app,$email,'Verifica tu correo en FISITAPP',"Confirma tu correo usando este enlace:\n\n".url('verify-email?token='.$token)."\n\nEl enlace vence en 24 horas.");
}

function forgot_password_page_v1(App $app):void
{
    if(is_post()){verify_csrf();$email=strtolower(trim((string)($_POST['email']??'')));if(rate_limit_persistent($app,'forgot',client_identity($email),4,900)){$user=$app->one('SELECT id,email FROM users WHERE email=? AND is_active=1',[$email]);if($user){$token=bin2hex(random_bytes(32));$app->exec('DELETE FROM password_resets WHERE user_id=?',[$user['id']]);$app->exec('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))',[$user['id'],hash('sha256',$token)]);send_mail_v1($app,$user['email'],'Recupera tu contraseña de FISITAPP',"Crea una contraseña nueva usando este enlace:\n\n".url('reset-password?token='.$token)."\n\nEl enlace vence en 60 minutos.");}}flash('success','Si el correo existe, recibirás un enlace de recuperación.');redirect(url('forgot-password'));}
    layout_start('Recuperar contraseña');echo '<main class="auth-page"><section class="card auth-card"><a class="brand" href="'.url().'"><span class="brand-mark">F</span>FISITAPP</a><h1>Recuperar contraseña</h1><p class="muted">Te enviaremos un enlace seguro.</p>';render_flashes();echo '<form method="post">'.csrf_field().'<div class="form-group"><label>Correo</label><input class="input" type="email" name="email" required autocomplete="email"></div><button class="btn btn-block">Enviar enlace</button></form><p class="muted" style="text-align:center"><a href="'.url('login').'">Volver al ingreso</a></p></section></main>';layout_end();
}

function reset_password_page_v1(App $app):void
{
    $token=(string)($_GET['token']??$_POST['token']??'');$row=$token!==''?$app->one('SELECT pr.*,u.email FROM password_resets pr JOIN users u ON u.id=pr.user_id WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at>NOW()', [hash('sha256',$token)]):null;
    if(is_post()){verify_csrf();if(!$row){flash('error','El enlace venció o ya fue utilizado.');redirect(url('forgot-password'));}$password=(string)($_POST['password']??'');if(strlen($password)<12||$password!==($_POST['confirm']??'')){flash('error','Las contraseñas deben coincidir y tener al menos 12 caracteres.');redirect(url('reset-password?token='.rawurlencode($token)));}$app->db->beginTransaction();try{$locked=$app->one('SELECT id,user_id FROM password_resets WHERE id=? AND token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE',[$row['id'],hash('sha256',$token)]);if(!$locked){$app->db->rollBack();flash('error','El enlace venció o ya fue utilizado.');redirect(url('forgot-password'));}$app->exec('UPDATE users SET password_hash=?,force_password_change=0 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$row['user_id']]);$app->exec('UPDATE password_resets SET used_at=NOW() WHERE id=?',[$row['id']]);$app->db->commit();flash('success','Contraseña actualizada. Ya puedes ingresar.');redirect(url('login'));}catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}}
    layout_start('Nueva contraseña');echo '<main class="auth-page"><section class="card auth-card"><h1>Nueva contraseña</h1>';if(!$row)echo '<div class="alert alert-error">El enlace venció o ya fue utilizado.</div><a class="btn btn-block" href="'.url('forgot-password').'">Solicitar otro enlace</a>';else echo '<form method="post">'.csrf_field().'<input type="hidden" name="token" value="'.e($token).'"><div class="form-group"><label>Nueva contraseña</label><input class="input" type="password" name="password" minlength="12" required></div><div class="form-group"><label>Confirmar contraseña</label><input class="input" type="password" name="confirm" minlength="12" required></div><button class="btn btn-block">Guardar contraseña</button></form>';echo '</section></main>';layout_end();
}

function verify_email_page_v1(App $app):void
{
    $token=(string)($_GET['token']??'');$row=$token!==''?$app->one('SELECT * FROM email_verifications WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW()',[hash('sha256',$token)]):null;layout_start('Verificar correo');echo '<main class="auth-page"><section class="card auth-card"><h1>Verificación de correo</h1>';if($row){$app->db->beginTransaction();try{$app->exec('UPDATE users SET email_verified_at=NOW() WHERE id=?',[$row['user_id']]);$app->exec('UPDATE email_verifications SET used_at=NOW() WHERE id=?',[$row['id']]);$app->db->commit();echo '<div class="alert alert-success">Correo verificado correctamente.</div><a class="btn btn-block" href="'.url('login').'">Iniciar sesión</a>';}catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}}else echo '<div class="alert alert-error">El enlace no es válido o ya venció.</div>';echo '</section></main>';layout_end();
}

function customer_account_page_v1(App $app):void
{
    if(!empty($_GET['order'])){customer_account_page($app);return;}
    $session=require_login(['customer']);$user=$app->one('SELECT id,name,email,phone,email_verified_at FROM users WHERE id=? AND role="customer"',[$session['id']]);if(!$user){$_SESSION=[];redirect(url());}
    if(is_post()){verify_csrf();$action=(string)($_POST['action']??'profile');try{if($action==='address_save'){$label=trim((string)$_POST['label']);$address=trim((string)$_POST['address']);$reference=trim((string)($_POST['reference']??''));if($label===''||$address==='')throw new RuntimeException('Nombre y dirección son obligatorios.');if(isset($_POST['is_default']))$app->exec('UPDATE customer_addresses SET is_default=0 WHERE user_id=?',[$user['id']]);$app->exec('INSERT INTO customer_addresses(user_id,label,address,reference,is_default) VALUES(?,?,?,?,?)',[$user['id'],$label,$address,$reference,isset($_POST['is_default'])?1:0]);flash('success','Dirección guardada.');}elseif($action==='address_delete'){$app->exec('DELETE FROM customer_addresses WHERE id=? AND user_id=?',[(int)$_POST['id'],$user['id']]);flash('success','Dirección eliminada.');}elseif($action==='verify'){issue_email_verification_v1($app,(int)$user['id'],$user['email']);flash('success','Enviamos un enlace de verificación.');}else{$name=trim((string)$_POST['name']);$phone=trim((string)$_POST['phone']);if($name===''||$phone==='')throw new RuntimeException('Nombre y teléfono son obligatorios.');$app->exec('UPDATE users SET name=?,phone=? WHERE id=?',[$name,$phone,$user['id']]);$_SESSION['user']['name']=$name;$_SESSION['user']['phone']=$phone;flash('success','Datos actualizados.');}}catch(Throwable $e){flash('error',$e->getMessage());}redirect(url('account'));}
    $orders=$app->all('SELECT o.id,o.order_number,o.total,o.status,o.created_at,t.name tenant_name FROM orders o JOIN tenants t ON t.id=o.tenant_id WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT 100',[$user['id']]);$addresses=$app->all('SELECT * FROM customer_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC',[$user['id']]);$loyaltyHtml=function_exists('loyalty_customer_cards_v12')?loyalty_customer_cards_v12($app,(int)$user['id']):'';layout_start('Mi cuenta');public_header();echo '<main class="section section-soft"><div class="container account-layout"><aside class="card admin-card account-nav"><h2>Mi cuenta</h2><p>'.e($user['name']).'<br><small class="muted">'.e($user['email']).'</small></p><a class="btn btn-light btn-block" href="'.url('account').'">Mis pedidos</a>'.($loyaltyHtml!==''?'<a class="btn btn-light btn-block" href="#fidelizacion">Mi fidelización</a>':'').'<form method="post" action="'.url('logout').'" style="margin-top:10px">'.csrf_field().'<button class="btn btn-light btn-block">Cerrar sesión</button></form></aside><section>';render_flashes();if(function_exists('r2_active')&&r2_active($app)&&str_ends_with($user['email'],'@registro.fisitaap.local'))echo '<section class="card admin-card"><h2>Conserva tu cuenta</h2><p>Guarda un correo y contraseña para volver a ingresar desde cualquier dispositivo.</p><a class="btn" href="'.url('account/access').'">Guardar mi acceso</a></section>';echo $loyaltyHtml;echo '<section class="card admin-card"><h1>Mis pedidos</h1><div class="table-wrap"><table class="table"><tr><th>Pedido</th><th>Negocio</th><th>Total</th><th>Estado</th><th>Fecha</th><th></th></tr>';foreach($orders as $order)echo '<tr><td>#'.e($order['order_number']).'</td><td>'.e($order['tenant_name']).'</td><td>'.money((float)$order['total']).'</td><td><span class="status">'.e($order['status']).'</span></td><td>'.e(date('d/m/Y',strtotime($order['created_at']))).'</td><td><a class="btn btn-light" href="?order='.(int)$order['id'].'">Ver detalle</a></td></tr>';if(!$orders)echo '<tr><td colspan="6">Todavía no tienes pedidos.</td></tr>';echo '</table></div></section><div id="perfil" class="split" style="margin-top:20px"><section class="card admin-card"><h2>Datos de mi cuenta</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="profile">'.field('Nombre','name',$user['name'],true).field('Teléfono','phone',$user['phone'],true).field('Correo','email',$user['email'],false,'email').'<button class="btn">Guardar datos</button></form>';if(!$user['email_verified_at'])echo '<form method="post" style="margin-top:10px">'.csrf_field().'<input type="hidden" name="action" value="verify"><button class="btn btn-light">Verificar correo</button></form>';else echo '<p class="verified">✓ Correo verificado</p>';echo '</section><section class="card admin-card"><h2>Nueva dirección</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="address_save">'.field('Nombre','label','Casa',true).'<div class="form-group"><label>Dirección</label><textarea class="input" name="address" required></textarea></div><div class="form-group"><label>Referencia</label><textarea class="input" name="reference"></textarea></div><label><input type="checkbox" name="is_default"> Usar como predeterminada</label><button class="btn btn-block" style="margin-top:12px">Guardar dirección</button></form></section></div><section class="card admin-card" style="margin-top:20px"><h2>Direcciones guardadas</h2>';foreach($addresses as $address)echo '<article class="address-row"><div><strong>'.e($address['label']).($address['is_default']?' · Predeterminada':'').'</strong><p>'.e($address['address']).'<br><small>'.e($address['reference']).'</small></p></div><form method="post">'.csrf_field().'<input type="hidden" name="action" value="address_delete"><input type="hidden" name="id" value="'.(int)$address['id'].'"><button class="btn btn-danger">Eliminar</button></form></article>';if(!$addresses)echo '<p class="muted">Todavía no guardaste direcciones.</p>';echo '</section></section></div></main>';public_footer();layout_end();
}
