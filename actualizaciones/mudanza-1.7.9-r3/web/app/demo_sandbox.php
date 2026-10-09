<?php
declare(strict_types=1);

const DEMO_COPY_TABLES=['branches','users','categories','products','product_option_groups','product_options','branch_products','product_inventory','tenant_content','posts','delivery_zones','coupons','tenant_modules','loyalty_programs','suppliers','restaurant_tables','pos_table_groups','fisitaap_r1_roles','fisitaap_r1_permissions','fisitaap_r1_user_roles','fisitaap_r2_settings','fisitaap_r2_modifiers','fisitaap_r2_modifier_groups','fisitaap_r2_combo_items','fisitaap_r2_promotions','fisitaap_r2_rooms','fisitaap_r2_floor','fisitaap_r3_room_versions','fisitaap_r3_sectors','fisitaap_r3_objects','fisitaap_r3_table_details'];
function demo_schema(App $app):void {
    $app->db->exec('CREATE TABLE IF NOT EXISTS fisitaap_demo_sessions (token CHAR(32) PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,template_id BIGINT UNSIGNED NOT NULL,browser_hash CHAR(64) NOT NULL,owner_id BIGINT UNSIGNED NOT NULL,customer_id BIGINT UNSIGNED NOT NULL,initial_mode VARCHAR(12) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,expires_at DATETIME NOT NULL,INDEX expiry(expires_at),CONSTRAINT fk_demo_session_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function demo_slug(string $slug):bool{return (bool)preg_match('/^demo-(?:restaurante|tienda)-[a-f0-9]{32}$/D',$slug);}
function demo_base():string{return $GLOBALS['demo_base_url']??rtrim(url(),'/');}
function demo_fail(string $message,int $status=403):never {
    http_response_code($status);header('Cache-Control: private, no-store');
    if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'))json_response(['ok'=>false,'error'=>$message],$status);
    $base=demo_base();echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Demo FISITAAP</title><body style="font:16px/1.5 system-ui;margin:40px"><h1>Demostración</h1><p>'.e($message).'</p><p><a href="'.e($base.'/demo').'">Abrir un demo nuevo</a></p></body></html>';exit;
}
function demo_browser_hash():string{return hash('sha256',(string)($_COOKIE['fisitaap_session']??session_id()));}
function demo_remap_row(array $row,string $table,array $meta,array $mapping,int $owner):?array {
    foreach($meta['refs'] as $ref){
        if($ref['child']!==$table)continue;
        foreach($ref['pairs'] as $column=>$parentColumn){
            if(!isset($row[$column])||$row[$column]===null||$row[$column]==='0'||$row[$column]===0)continue;
            $parent=$ref['parent'];$old=(string)$row[$column];
            if($parentColumn!=='id'){
                if($parentColumn==='tenant_id'){$row[$column]=array_values($mapping['tenants'])[0];continue;}
                continue;
            }
            if(isset($mapping[$parent][$old]))$row[$column]=$mapping[$parent][$old];
            elseif($parent==='users'&&$owner)$row[$column]=$owner;
            elseif(in_array($parent,DEMO_COPY_TABLES,true)||$parent==='tenants')return null;
            else throw new RuntimeException('El catálogo del demo contiene una relación no compatible: '.$table.' → '.$parent.'. No se abrió una copia parcial.');
        }
    }
    return $row;
}
function demo_insert(App $app,string $table,array $row):int {
    $cols=implode(',',array_map('fm_id',array_keys($row)));$placeholders=implode(',',array_fill(0,count($row),'?'));
    $app->exec('INSERT INTO '.fm_id($table).' ('.$cols.') VALUES('.$placeholders.')',array_values($row));return (int)$app->db->lastInsertId();
}
function demo_printer_reset(array $row):array {
    foreach(['receipt_printer_host','receipt_printer_name','receipt_bridge_token','printer_points_json'] as $key)if(array_key_exists($key,$row))$row[$key]=null;
    // Current installations store printers on branches. Older tenant-level
    // fields may exist, but must never be inserted when absent from the schema.
    foreach(['receipt_printer_type'=>'browser','receipt_autoprint'=>0] as $key=>$value)if(array_key_exists($key,$row))$row[$key]=$value;
    return $row;
}
function demo_open(App $app,string $type,string $mode):string {
    require_once __DIR__.'/data_tools179.php';
    if($app->setting('demo_sandbox_active','0')!=='1')throw new DomainException('Primero completa la preparación de la copia nueva desde preparar-mudanza.php.');
    $slug=$type==='restaurante'?'restaurante-demo':'tienda-demo';
    $template=$app->one('SELECT * FROM tenants WHERE slug=? AND is_active=1',[$slug]);
    if(!$template)throw new DomainException('No está disponible la plantilla de este demo.');
    demo_cleanup($app,2);
    if((int)$app->one('SELECT COUNT(*) n FROM fisitaap_demo_sessions WHERE expires_at>NOW()')['n']>=60)throw new DomainException('Hay muchas pruebas abiertas. Intenta nuevamente en unos minutos.');
    $owner=$app->one('SELECT id FROM users WHERE tenant_id=? AND role="tenant_admin" AND is_active=1 ORDER BY id LIMIT 1',[$template['id']]);
    if(!$owner)throw new DomainException('La plantilla necesita un dueño de demostración activo.');
    // Auto-login is bound to the visitor's browser. These disposable accounts
    // share one unknown, random password per copy, never a template credential.
    // Hash once: repeated bcrypt work per collaborator slows shared hosting.
    $demoPasswordHash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
    $meta=fm_meta($app);$sets=fm_sets($app,$meta);$mapping=['tenants'=>[]];$token=bin2hex(random_bytes(16));
    $app->db->beginTransaction();
    try{
        foreach(DEMO_COPY_TABLES as $table)if(isset($meta['tables'][$table])&&in_array('tenant_id',$meta['tables'][$table]['cols'],true))fm_mark($app,$meta,$sets,$table,'b.tenant_id='.(int)$template['id']);
        for($i=0;$i<count(DEMO_COPY_TABLES);$i++){
            $added=0;foreach($meta['refs'] as $ref)if(in_array($ref['child'],DEMO_COPY_TABLES,true)&&in_array($ref['parent'],DEMO_COPY_TABLES,true)&&!in_array('tenant_id',$meta['tables'][$ref['child']]['cols'],true)&&(FM_OWNERS[$ref['child']]??'')===array_key_first($ref['pairs']))$added+=fm_related($app,$meta,$sets,$ref,false);
            if(!$added)break;
        }
        $parent=(int)$template['id'];unset($template['id']);$template['slug']='demo-'.$type.'-'.$token;$template['is_listed']=0;$template['physical_store_enabled']=1;$template['expires_at']=null;$template['email']='demo-'.$token.'@sandbox.invalid';
        $template=demo_printer_reset($template);
        $tenant=demo_insert($app,'tenants',$template);$mapping['tenants'][(string)$parent]=$tenant;$pending=[];$newOwner=0;
        foreach(DEMO_COPY_TABLES as $table)if(isset($sets[$table]))foreach($app->all('SELECT b.* FROM '.fm_id($table).' b JOIN '.fm_id($sets[$table]).' k ON '.fm_pkmatch('b','k',$meta['tables'][$table]['pk'])) as $row)$pending[]=[$table,$row];
        while($pending){
            $next=[];$copied=0;
            foreach($pending as [$table,$old]){
                $row=demo_remap_row($old,$table,$meta,$mapping,$newOwner);if($row===null){$next[]=[$table,$old];continue;}
                if($table==='users'){
                    $row['email']='demo-'.$token.'-user-'.$old['id'].'@sandbox.invalid';$row['force_password_change']=0;$row['email_verified_at']=date('Y-m-d H:i:s');$row['password_hash']=$demoPasswordHash;
                    if(!in_array($row['role'],['tenant_admin','manager','editor','kitchen','cashier','customer'],true))$row['role']='manager';
                }
                if($table==='branches')$row=demo_printer_reset($row);
                if($table==='coupons')$row['uses_count']=0;
                if($table==='fisitaap_r3_room_versions')$row['revision']=0;
                $auto=$meta['tables'][$table]['auto']??null;$oldId=$auto?(string)$old[$auto]:null;if($auto)unset($row[$auto]);
                if($table==='fisitaap_r3_objects'){$uuid=bin2hex(random_bytes(16));$row['id']=substr($uuid,0,8).'-'.substr($uuid,8,4).'-'.substr($uuid,12,4).'-'.substr($uuid,16,4).'-'.substr($uuid,20);}
                $new=demo_insert($app,$table,$row);
                if($oldId!==null)$mapping[$table][$oldId]=$new;
                if($table==='users'&&(int)$old['id']===(int)$owner['id'])$newOwner=$new;
                $copied++;
            }
            if(!$copied)throw new RuntimeException('No se pudieron copiar todas las relaciones del catálogo de demostración.');$pending=$next;
        }
        if(!$newOwner)throw new RuntimeException('No se copió el dueño del demo.');
        $customer=demo_insert($app,'users',['tenant_id'=>$tenant,'name'=>'Cliente de prueba','email'=>'demo-'.$token.'-cliente@sandbox.invalid','phone'=>'88888888','password_hash'=>$demoPasswordHash,'role'=>'customer','is_active'=>1,'email_verified_at'=>date('Y-m-d H:i:s')]);
        $app->exec('INSERT INTO tenant_customers(tenant_id,user_id) VALUES(?,?)',[$tenant,$customer]);
        $app->exec('INSERT INTO fisitaap_demo_sessions(token,tenant_id,template_id,browser_hash,owner_id,customer_id,initial_mode,expires_at) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[$token,$tenant,$parent,demo_browser_hash(),$newOwner,$customer,$mode]);
        $app->db->commit();return $token;
    }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}
    finally{fm_drop_sets($app,$sets);}
}
function demo_runtime(App $app,string $token):void {
    header('Cache-Control: private, no-store, max-age=0');
    // Public navigation must leave the namespace, including older cached links.
    // Only GET can return to the selector; demo writes remain scoped and guarded.
    if(request_path()==='demo'&&($_SERVER['REQUEST_METHOD']??'GET')==='GET')redirect(demo_base().'/demo');
    $row=$app->one('SELECT s.*,t.slug FROM fisitaap_demo_sessions s JOIN tenants t ON t.id=s.tenant_id WHERE s.token=? AND s.expires_at>NOW() AND s.created_at>DATE_SUB(NOW(),INTERVAL 4 HOUR)',[$token]);
    if(!$row||!hash_equals($row['browser_hash'],demo_browser_hash()))demo_fail('Esta prueba terminó o pertenece a otro navegador. Abre un demo nuevo.');
    $GLOBALS['demo_context']=$row;
    if(!isset($_SESSION['user'])){
        $id=$row['initial_mode']==='cliente'?$row['customer_id']:$row['owner_id'];$user=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=? AND is_active=1',[$id,$row['tenant_id']]);
        if(!$user)demo_fail('No se pudo abrir el usuario de demostración.');unset($user['password_hash']);$_SESSION['user']=$user;
    }
    if((int)($_SESSION['user']['tenant_id']??0)!==(int)$row['tenant_id']||!in_array($_SESSION['user']['role'],['tenant_admin','manager','editor','kitchen','cashier','customer'],true))demo_fail('Sesión de demostración inválida.');
    if(time()-(int)($_SESSION['demo_touch']??0)>300){$app->exec('UPDATE fisitaap_demo_sessions SET expires_at=LEAST(DATE_ADD(NOW(),INTERVAL 30 MINUTE),DATE_ADD(created_at,INTERVAL 4 HOUR)) WHERE token=?',[$token]);$_SESSION['demo_touch']=time();}
    $path=request_path();
    if($path==='logout'){
        if(!is_post())redirect(url($row['slug']));verify_csrf();demo_discard($app,$row);$_SESSION=[];session_destroy();redirect(demo_base().'/demo');
    }
    if($path==='demo/modo'){
        if(!is_post())redirect(url($row['slug']));verify_csrf();$id=($_POST['mode']??'')==='cliente'?$row['customer_id']:$row['owner_id'];
        $user=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=? AND is_active=1',[$id,$row['tenant_id']]);if(!$user)demo_fail('Usuario de prueba no disponible.');unset($user['password_hash']);$_SESSION['user']=$user;
        redirect(url($user['role']==='customer'?$row['slug'].'/catalogo':'admin'));
    }
    if($path==='')redirect(url($row['slug']));
    $allowed=$path==='admin'||str_starts_with($path,'admin/')||$path==='account'||str_starts_with($path,'account/')||$path===$row['slug']||str_starts_with($path,$row['slug'].'/')||in_array($path,['api/checkout','api/coupon-preview','api/search-log','api/pos-options','api/pos-quote-r3'],true);
    if(!$allowed)demo_fail('Esta función conecta servicios o cuentas reales y no se ejecuta en la demostración.');
    if(in_array($path,['admin/escritorio','admin/asistente'],true))demo_fail('Puedes probar los menús, las ventas y la impresión de ejemplo. La vinculación de equipos y los servicios externos se configuran en un negocio real.',200);
    foreach(['email','customer_email','driver_email'] as $field)if(!empty($_POST[$field])&&is_string($_POST[$field]))$_POST[$field]='demo-'.$token.'-'.substr(hash('sha256',strtolower($_POST[$field])),0,16).'@sandbox.invalid';
}
function demo_discard(App $app,array $session):void {
    require_once __DIR__.'/data_tools179.php';$id=(int)$session['tenant_id'];
    $tenant=$app->one('SELECT slug FROM tenants WHERE id=?',[$id]);if(!$tenant||!demo_slug($tenant['slug']))throw new RuntimeException('La limpieza solo admite copias temporales de demostración.');
    $meta=fm_meta($app);$sets=fm_sets($app,$meta);
    // Build the disposable tenant's owned descendants only. Never ascend into shared parents.
    foreach($meta['tables'] as $table=>$m){if($table==='tenants')fm_mark($app,$meta,$sets,$table,'b.id='.$id);elseif(in_array('tenant_id',$m['cols'],true))fm_mark($app,$meta,$sets,$table,'b.tenant_id='.$id);}
    for($i=0;$i<count($meta['tables']);$i++){
        $added=0;foreach($meta['refs'] as $ref)if((FM_OWNERS[$ref['child']]??'')===array_key_first($ref['pairs']))$added+=fm_related($app,$meta,$sets,$ref,false);
        if(!$added)break;
    }
    $app->db->beginTransaction();
    try{
        fm_assert_delete_scope($app,$meta,$sets);
        $app->db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach($meta['tables'] as $table=>$m)$app->db->exec('DELETE b FROM '.fm_id($table).' b JOIN '.fm_id($sets[$table]).' k ON '.fm_pkmatch('b','k',$m['pk']));
        $app->db->exec('SET FOREIGN_KEY_CHECKS=1');$app->db->commit();
    }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();$app->db->exec('SET FOREIGN_KEY_CHECKS=1');throw $e;}
    finally{fm_drop_sets($app,$sets);}
    $dir=ROOT_PATH.'/uploads/demo/'.$session['token'];
    if(preg_match('/^[a-f0-9]{32}$/D',(string)$session['token'])&&is_dir($dir)&&!is_link($dir)&&str_starts_with((string)realpath($dir),(string)realpath(ROOT_PATH.'/uploads').'/demo/')){
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file){if($file->isLink()||$file->isFile())unlink($file->getPathname());elseif($file->isDir())rmdir($file->getPathname());}rmdir($dir);
    }
}
function demo_cleanup(App $app,int $limit=10):int {
    $expired=$app->all('SELECT * FROM fisitaap_demo_sessions WHERE expires_at<=NOW() OR created_at<=DATE_SUB(NOW(),INTERVAL 4 HOUR) ORDER BY expires_at LIMIT '.max(1,min(20,$limit)));$done=0;
    foreach($expired as $row){demo_discard($app,$row);$done++;}return $done;
}
function demo_dispatch(App $app,string $path):void {
    header('Cache-Control: private, no-store, max-age=0');
    if($path==='demo/iniciar'){
        if(!is_post())redirect(url('demo'));verify_csrf();
        if(!rate_limit_persistent($app,'demo_start',client_identity('demo'),12,3600))demo_fail('Ya se abrieron varias pruebas desde esta conexión. Espera antes de abrir otra.',429);
        $type=($_POST['type']??'')==='restaurante'?'restaurante':'tienda';$mode=($_POST['mode']??'')==='cliente'?'cliente':'panel';
        try{$token=demo_open($app,$type,$mode);}catch(Throwable $e){error_log('FISITAAP demo open: '.get_class($e).': '.$e->getMessage());demo_fail('No se pudo abrir el demo. Revisa su preparación con la cuenta maestra.',503);}
        $slug='demo-'.$type.'-'.$token;$target=url('demo/s/'.$token.'/'.($mode==='cliente'?$slug.'/catalogo':'admin'));
        session_write_close();
        if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'))json_response(['ok'=>true,'token'=>$token,'url'=>$target]);
        header('Cache-Control: private, no-store');header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'self'");
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Abriendo demo</title><p>Abriendo tu prueba independiente…</p><script>try{sessionStorage.setItem("fisitaap-demo-tab",'.json_encode($token).');location.replace('.json_encode($target,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).')}catch(e){document.body.textContent="Activa el almacenamiento de este navegador para usar el demo."}</script><noscript>El demo necesita JavaScript para crear una sesión independiente.</noscript></html>';exit;
    }
    if(in_array(explode('/',$path)[0],['restaurante-demo','tienda-demo'],true))redirect(url('demo'));
    if(is_post()&&in_array($path,['owner-login','login'],true)&&in_array(strtolower(trim((string)($_POST['email']??''))),['restaurante.demo@fisitaap.com','negocio.demo@fisitaap.com','cliente.demo@fisitaap.com'],true)){
        verify_csrf();redirect(url('demo'));
    }
    $user=current_user();
    if($user&&in_array($user['role'],['tenant_admin','manager','editor','kitchen','cashier'],true)){
        $t=$app->one('SELECT slug FROM tenants WHERE id=?',[$user['tenant_id']]);
        if($t&&(in_array($t['slug'],['restaurante-demo','tienda-demo'],true)||demo_slug($t['slug']))){$_SESSION=[];redirect(url('demo'));}
    }
}
function demo_header():void {
    $row=$GLOBALS['demo_context']??null;if(!$row)return;
    $token=json_encode($row['token']);$restart=json_encode(demo_base().'/demo',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    echo '<script>window.FISITAAP_DEMO=true;try{if(sessionStorage.getItem("fisitaap-demo-tab")!=='.$token.')location.replace('.$restart.')}catch(e){location.replace('.$restart.')}</script>';
}
function demo_banner():void {
    if(empty($GLOBALS['demo_context']))return;
    echo '<aside style="padding:10px 18px;background:#e7eef3;color:#203f57;border-bottom:1px solid #cbd7e0;font:14px/1.5 system-ui"><strong>Demo privada</strong> · Los cambios son temporales. <form method="post" action="'.e(url('demo/modo')).'" style="display:inline">'.csrf_field().'<button name="mode" value="panel">Probar panel</button> <button name="mode" value="cliente">Probar compra</button></form> <form method="post" action="'.e(url('logout')).'" style="display:inline">'.csrf_field().'<button>Terminar y descartar</button></form></aside>';
}
