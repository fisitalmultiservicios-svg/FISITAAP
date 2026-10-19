<?php
declare(strict_types=1);

function op180_phone(string $phone):string {
    $digits=preg_replace('/\D/','',$phone);if(str_starts_with($digits,'506')&&strlen($digits)===11)$digits=substr($digits,3);
    if(!preg_match('/^[2-9][0-9]{7}$/D',$digits))throw new DomainException('Escribe un teléfono válido de Costa Rica de 8 dígitos.');return $digits;
}
function op180_maps(string $url):string {
    $url=trim($url);if($url==='')return '';if(strlen($url)>1500)throw new DomainException('El enlace de Google Maps es demasiado largo.');
    $p=parse_url($url);$host=strtolower($p['host']??'');
    if(($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port'])||!in_array($host,['maps.app.goo.gl','goo.gl','maps.google.com','www.google.com','google.com'],true))throw new DomainException('Pega un enlace HTTPS de Google Maps usando Compartir → Copiar enlace.');
    if(in_array($host,['google.com','www.google.com'],true)&&!str_starts_with($p['path']??'','/maps'))throw new DomainException('El enlace debe abrir una ubicación de Google Maps.');return $url;
}
function op180_pin(App $app,array $order,bool $allowLegacy=false):string {
    if(!op180_ready($app))return '';
    $key=hash('sha256',$app->config['app_key'],true);$encoded=(string)($order['pin_cipher180']??'');
    if($encoded!==''){$raw=base64_decode($encoded,true);if($raw!==false&&strlen($raw)>28){$pin=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if(is_string($pin)&&preg_match('/^[0-9]{4}$/D',$pin))return $pin;}throw new DomainException('No se pudo leer el PIN. Administración debe revisar la configuración de cifrado.');}
    if(!$allowLegacy&&$app->one('SELECT id FROM delivery_jobs WHERE order_id=? AND status NOT IN("cancelled","expired")',[$order['id']]))return '';
    $pin=(string)random_int(1000,9999);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($pin,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
    if($cipher===false)throw new DomainException('No se pudo proteger el PIN.');
    $app->exec('UPDATE orders SET pin_cipher180=? WHERE id=? AND tenant_id=?',[base64_encode($iv.$tag.$cipher),$order['id'],$order['tenant_id']]);return $pin;
}
function op180_browser():string {if(empty($_SESSION['checkout_browser180']))$_SESSION['checkout_browser180']=bin2hex(random_bytes(24));return hash('sha256',$_SESSION['checkout_browser180']);}
function op180_contact(App $app,array $tenant,string $name,string $phone):array {
    $phone=op180_phone($phone);$name=mb_substr(trim($name),0,150);if($name==='')throw new DomainException('Escribe tu nombre.');
    $owns=!$app->db->inTransaction();if($owns)$app->db->beginTransaction();
    try{
        $app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
        $contact=$app->one('SELECT u.* FROM fisitaap_guest_contacts180 c JOIN users u ON u.id=c.user_id AND u.role="customer" AND u.is_active=1 WHERE c.tenant_id=? AND c.phone=?',[$tenant['id'],$phone]);
        if(!$contact){foreach($app->all('SELECT u.* FROM users u JOIN tenant_customers tc ON tc.user_id=u.id WHERE tc.tenant_id=? AND u.role="customer" AND u.is_active=1 AND u.phone LIKE ? ORDER BY u.id LIMIT 100',[$tenant['id'],'%'.substr($phone,-4).'%']) as $candidate){try{if(op180_phone((string)$candidate['phone'])===$phone){$contact=$candidate;break;}}catch(DomainException){}}}
        if(!$contact){if(!rate_limit_persistent($app,'delivery_new_contact',client_identity(),20,3600))throw new DomainException('Espera antes de registrar otro contacto.');$contact=r2_new_customer($app,$tenant,$name,$phone);}
        $app->exec('INSERT INTO fisitaap_guest_contacts180(tenant_id,phone,user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)',[$tenant['id'],$phone,$contact['id']]);
        if($owns)$app->db->commit();unset($contact['password_hash']);return $contact;
    }catch(Throwable $e){if($owns&&$app->db->inTransaction())$app->db->rollBack();throw $e;}
}
function op180_customer_checkout(App $app,array $tenant,array $raw):array {
    $user=op180_contact($app,$tenant,(string)($raw['guest_name']??''),(string)($raw['guest_phone']??''));
    // A phone identifies a delivery contact; it never authenticates that person's account.
    $user['name']=mb_substr(trim((string)$raw['guest_name']),0,150);$user['phone']=op180_phone((string)$raw['guest_phone']);$user['guest180']=true;return $user;
}
function op180_address_lookup(App $app):never {
    verify_csrf();$tenant=tenant_by_slug($app,(string)($_POST['tenant']??''));
    if(!$tenant||!$tenant['is_active']||(!empty($GLOBALS['demo_context'])&&(int)$GLOBALS['demo_context']['tenant_id']!==(int)$tenant['id']))json_response(['ok'=>false,'error'=>'Negocio no disponible.'],404);
    if(!rate_limit_persistent($app,'delivery_addresses',client_identity(),12,300))json_response(['ok'=>false,'error'=>'Espera antes de consultar otra dirección.'],429);
    try{
        $phone=op180_phone((string)($_POST['phone']??''));$user=$app->one('SELECT c.user_id FROM fisitaap_guest_contacts180 c JOIN users u ON u.id=c.user_id AND u.role="customer" AND u.is_active=1 WHERE c.tenant_id=? AND c.phone=?',[$tenant['id'],$phone]);
        if(!$user){foreach($app->all('SELECT u.id user_id,u.phone FROM users u JOIN tenant_customers tc ON tc.user_id=u.id WHERE tc.tenant_id=? AND u.role="customer" AND u.is_active=1 AND u.phone LIKE ? ORDER BY u.id LIMIT 100',[$tenant['id'],'%'.substr($phone,-4).'%']) as $candidate){try{if(op180_phone((string)$candidate['phone'])===$phone){$user=$candidate;break;}}catch(DomainException){}}}
        $addresses=$user?$app->all('SELECT id,label,address,maps_url180,is_default FROM customer_addresses WHERE user_id=? ORDER BY is_default DESC,id LIMIT 20',[$user['user_id']]):[];
        json_response(['ok'=>true,'addresses'=>$addresses]);
    }catch(DomainException $e){json_response(['ok'=>false,'error'=>$e->getMessage()],422);}
}
function op180_address_save(App $app,int $user,string $address,string $maps,string $label='Casa',bool $default=false):int {
    $address=mb_substr(trim($address),0,2000);$maps=op180_maps($maps);$label=mb_substr(trim($label),0,100);
    if($address===''||$label==='')throw new DomainException('Escribe nombre y dirección.');
    $app->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$user]);
    $old=$app->one('SELECT id FROM customer_addresses WHERE user_id=? AND address=? AND COALESCE(maps_url180,"")=?',[$user,$address,$maps]);
    if(!$app->one('SELECT id FROM customer_addresses WHERE user_id=? LIMIT 1',[$user]))$default=true;
    if($default)$app->exec('UPDATE customer_addresses SET is_default=0 WHERE user_id=?',[$user]);
    if($old){if($default)$app->exec('UPDATE customer_addresses SET is_default=1 WHERE id=?',[$old['id']]);return (int)$old['id'];}
    $app->exec('INSERT INTO customer_addresses(user_id,label,address,maps_url180,is_default) VALUES(?,?,?,?,?)',[$user,$label,$address,$maps?:null,$default?1:0]);return (int)$app->db->lastInsertId();
}
function op180_account_kind(string $selected='bank'):string {
    $out='<label>Tipo de cuenta<select class="input" name="kind">';foreach(['principal'=>'Caja principal','petty'=>'Caja chica','bank'=>'Banco / SINPE','cash'=>'Otra caja de efectivo','other'=>'Otra cuenta'] as $key=>$label)$out.='<option value="'.$key.'" '.($key===$selected?'selected':'').'>'.$label.'</option>';return $out.'</select></label>';
}
function op180_customer_address_fields():string {
    return '<fieldset class="op180-address-fields"><legend>Dirección principal (opcional al crear)</legend>'.field('Dirección','address','').field('Ubicación de Google Maps','maps_url','',false, 'url').'</fieldset><p class="muted">Después de guardar podrás agregar otras direcciones y elegir la principal desde la ficha del cliente.</p>';
}
function op180_customer_addresses(App $app,array $tenant,int $customer):void {
    echo '<h3>Direcciones de entrega</h3>'.r2_form('address_save').pos_hidden_148('customer_id',$customer).field('Nombre de esta dirección','label','Casa',true).field('Dirección','address','',true).field('Enlace de Google Maps','maps_url','',false,'url').'<label><input type="checkbox" name="is_default"> Usar como principal</label><button class="btn">Guardar dirección</button></form>';
    foreach($app->all('SELECT * FROM customer_addresses WHERE user_id=? ORDER BY is_default DESC,id',[$customer]) as $a)echo '<article class="card admin-card"><strong>'.e($a['label']).($a['is_default']?' · Principal':'').'</strong><p>'.e($a['address']).'</p>'.($a['maps_url180']?'<a target="_blank" rel="noopener noreferrer" href="'.e($a['maps_url180']).'">Abrir ubicación en Maps</a>':'').r2_form('address_default').pos_hidden_148('customer_id',$customer).pos_hidden_148('address_id',$a['id']).'<button class="btn btn-light">Elegir como principal</button></form></article>';
}
function op180_checkout_validate(App $app,array $tenant,array $user,array $raw):void {
    if(trim((string)$user['name'])==='')throw new DomainException('Completa tu nombre.');op180_phone((string)($user['phone']??''));op180_maps((string)($raw['maps_url']??''));
    if(preg_match('/sinpe/i',(string)($raw['payment']??''))){
        $token=(string)($raw['proof_token']??'');$proof=$app->one('SELECT * FROM fisitaap_order_uploads180 WHERE token_hash=? AND tenant_id=? AND operation_key=? AND browser_hash=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 DAY)',[hash('sha256',$token),$tenant['id'],$raw['checkout_key']??'',op180_browser()]);
        if(!preg_match('/^[a-f0-9]{64}$/D',$token)||!$proof||!is_file(ROOT_PATH.'/'.$proof['path']))throw new DomainException('Adjunta la imagen del comprobante SINPE antes de enviar el pedido.');
    }
}
function op180_checkout_saved(App $app,array $tenant,array $user,array $raw,int $id):void {
    $maps=op180_maps((string)($raw['maps_url']??''));$app->exec('UPDATE orders SET maps_url180=? WHERE id=? AND tenant_id=?',[$maps?:null,$id,$tenant['id']]);
    if(!empty($raw['address']))op180_address_save($app,(int)$user['id'],(string)$raw['address'],$maps,'Principal');
    if(preg_match('/sinpe/i',(string)$raw['payment'])){
        $proof=$app->one('SELECT * FROM fisitaap_order_uploads180 WHERE token_hash=? AND tenant_id=? FOR UPDATE',[hash('sha256',(string)$raw['proof_token']),$tenant['id']]);
        if(!$proof||($proof['order_id']&&(int)$proof['order_id']!==$id))throw new DomainException('Ese comprobante ya pertenece a otro pedido.');
        $app->exec('UPDATE fisitaap_order_uploads180 SET order_id=? WHERE token_hash=?',[$id,$proof['token_hash']]);
    }
    $order=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=?',[$id,$tenant['id']]);op180_pin($app,$order);op180_event($app,$order,'new','Pedido enviado; pendiente de aceptación del vendedor.',(int)$user['id']);
}
function op180_checkout_result(App $app,array $order):array {
    $signature=hash_hmac('sha256','order:'.$order['id'],$app->config['app_key']);
    return ['delivery_pin'=>$order['delivery_type']==='pickup'?null:op180_pin($app,$order),'tracking_url'=>url('pedido/seguimiento?id='.(int)$order['id'].'&token='.$signature)];
}
function op180_proof_upload(App $app):never {
    verify_csrf();$tenant=tenant_by_slug($app,(string)($_POST['tenant']??''));$key=(string)($_POST['checkout_key']??'');
    if(!$tenant||!$tenant['is_active']||(!empty($GLOBALS['demo_context'])&&(int)$GLOBALS['demo_context']['tenant_id']!==(int)$tenant['id'])||!preg_match('/^[a-f0-9]{32}$/D',$key))json_response(['ok'=>false,'error'=>'Recarga el carrito.'],422);
    if(!rate_limit_persistent($app,'order_proof',client_identity(),15,300))json_response(['ok'=>false,'error'=>'Espera antes de adjuntar otro comprobante.'],429);
    try{
        // Bounded cleanup only for abandoned, expired uploads; attached evidence is retained.
        foreach($app->all('SELECT token_hash,path FROM fisitaap_order_uploads180 WHERE order_id IS NULL AND created_at<DATE_SUB(NOW(),INTERVAL 2 DAY) LIMIT 20') as $expired){$cleanup=$app->db->prepare('DELETE FROM fisitaap_order_uploads180 WHERE token_hash=? AND order_id IS NULL AND created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)');$cleanup->execute([$expired['token_hash']]);if($cleanup->rowCount()===1&&preg_match('~^uploads/private-proofs/[a-f0-9]{48}\.webp$~D',$expired['path'])&&is_file(ROOT_PATH.'/'.$expired['path'])&&!is_link(ROOT_PATH.'/'.$expired['path']))unlink(ROOT_PATH.'/'.$expired['path']);}
        $file=$_FILES['proof']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']>6*1024*1024||!is_uploaded_file($file['tmp_name']))throw new DomainException('Adjunta un JPG, PNG o WebP de hasta 6 MB.');
        $source=image_source($file['tmp_name']);$w=imagesx($source);$h=imagesy($source);$ratio=min(1,1600/max($w,$h));$image=imagecreatetruecolor((int)round($w*$ratio),(int)round($h*$ratio));imagecopyresampled($image,$source,0,0,0,0,imagesx($image),imagesy($image),$w,$h);imagedestroy($source);
        $directory=ROOT_PATH.'/uploads/private-proofs';if(!is_dir($directory)&&!mkdir($directory,0755,true))throw new DomainException('No se pudo preparar la carpeta privada.');
        if(!is_file($directory.'/.htaccess')&&file_put_contents($directory.'/.htaccess',"Require all denied\n")===false)throw new DomainException('No se pudo proteger la carpeta de comprobantes.');
        $path='uploads/private-proofs/'.bin2hex(random_bytes(24)).'.webp';if(!imagewebp($image,ROOT_PATH.'/'.$path,82))throw new DomainException('No se pudo guardar el comprobante.');imagedestroy($image);
        $token=bin2hex(random_bytes(32));$app->exec('INSERT INTO fisitaap_order_uploads180(token_hash,tenant_id,browser_hash,operation_key,path) VALUES(?,?,?,?,?)',[hash('sha256',$token),$tenant['id'],op180_browser(),$key,$path]);json_response(['ok'=>true,'token'=>$token]);
    }catch(Throwable $e){json_response(['ok'=>false,'error'=>$e instanceof PDOException?'No se pudo registrar el comprobante.':$e->getMessage()],422);}
}
function op180_proof_view(App $app):never {
    $user=require_login();$id=(int)($_GET['order']??0);$order=$app->one('SELECT * FROM orders WHERE id=?',[$id]);
    $allowed=$order&&(($user['role']==='customer'&&(int)$order['user_id']===(int)$user['id'])||$user['role']==='master');
    if($order&&(int)($user['tenant_id']??0)===(int)$order['tenant_id']){try{qa180_permission($app,$user,'pedidos','view');$allowed=true;}catch(DomainException){}}
    if(!$allowed){http_response_code(404);exit;}
    $proof=$app->one('SELECT path FROM fisitaap_order_uploads180 WHERE tenant_id=? AND order_id=?',[$order['tenant_id'],$id]);
    if(!$proof||!is_file(ROOT_PATH.'/'.$proof['path'])){http_response_code(404);exit;}
    header('Content-Type: image/webp');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');readfile(ROOT_PATH.'/'.$proof['path']);exit;
}
