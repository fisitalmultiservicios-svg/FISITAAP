<?php
declare(strict_types=1);
// Standalone, authenticated, read-only diagnosis. It does not replace application files.
$fwdRoot=__DIR__;$fwdAuthorized=false;$fwdDone=false;$fwdChecks=[];$fwdStage='Arranque';
$fwdBuffer=ob_get_level();ob_start();$fwdReserve=str_repeat(' ',131072);
ini_set('display_errors','0');
function fwd_escape(string $text):string{return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function fwd_safe(string $text):string{
    global $config,$fwdRoot;
    foreach((array)($config??[]) as $key=>$value)if(preg_match('/pass|secret|token|api.?key|app.?key/i',(string)$key)&&is_string($value)&&$value!=='')$text=str_replace($value,'[privado]',$text);
    $text=str_replace($fwdRoot,'[sitio]',$text);
    $text=preg_replace('~(?:https?://|/)[^\s<>"\']*\?[^\s<>"\']*~','[dirección sin parámetros]',$text)??$text;
    return function_exists('mb_substr')?mb_substr($text,0,1800):substr($text,0,1800);
}
function fwd_failure(Throwable $error):string{
    global $fwdRoot;
    $file=str_replace($fwdRoot.'/','',$error->getFile());
    $lines=[get_class($error).': '.fwd_safe($error->getMessage()),'Archivo: '.fwd_safe($file).' · línea '.$error->getLine()];
    foreach(array_slice($error->getTrace(),0,6) as $frame)$lines[]=(string)($frame['function']??'').' · '.fwd_safe(str_replace($fwdRoot.'/','',(string)($frame['file']??''))).':'.(int)($frame['line']??0);
    return implode("\n",$lines);
}
function fwd_finish(int $status=200):never{
    global $fwdAuthorized,$fwdDone,$fwdChecks,$fwdBuffer,$app,$fwdCodeLabel;
    $fwdDone=true;
    if(isset($app)&&$app instanceof App)try{if($app->db->inTransaction())$app->db->rollBack();}catch(Throwable $ignored){}
    if(session_status()===PHP_SESSION_ACTIVE)session_abort();
    while(ob_get_level()>$fwdBuffer)ob_end_clean();
    header_remove('Location');header_remove('Content-Security-Policy');http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comprobar bienvenida · FISITAAP</title><style>body{font:16px/1.5 system-ui;margin:0;padding:20px;background:#f3f6f8;color:#193647}main{max-width:850px;margin:auto;background:#fff;border:1px solid #dbe3e9;padding:24px}section{padding:14px 0;border-bottom:1px solid #e0e7eb}h2{font-size:17px}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f4f6f8;padding:12px;font-size:13px}.error{color:#a42e20}.ok{color:#176534}a{color:#155f91}</style></head><body><main><h1>Comprobar bienvenida</h1><p>Identificador: <strong>FISITAAP-BIENVENIDA-180-D2</strong></p>';
    if(!$fwdAuthorized){echo '<p>Ingresa con tu cuenta maestra o de dueño en <a href="owner-login">Acceso negocios</a> y vuelve a abrir esta dirección. Si el acceso también falla, revisa el archivo error_log de public_html.</p></main></body></html>';exit;}
    echo '<p>Solo comprobación: transacción de lectura, sin guardar cambios en la base ni en los archivos. No se muestra la imagen ni se llama al servicio de IA.</p><p>PHP: '.fwd_escape(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION).' · Código activo: '.fwd_escape($fwdCodeLabel??'no identificado').'</p>';
    foreach($fwdChecks as $row)echo '<section><h2 class="'.($row['ok']?'ok':'error').'">'.($row['ok']?'✓ ':'Error · ').fwd_escape($row['label']).'</h2><pre>'.fwd_escape($row['detail']).'</pre></section>';
    echo '<p>Comparte una captura de estos resultados. Esta comprobación cubre la bienvenida de la cuenta conectada; si falla otra cuenta, ábrela también desde esa sesión.</p><p>Al terminar, elimina únicamente diagnostico-bienvenida-180.php.</p></main></body></html>';exit;
}
function fwd_check(string $label,callable $operation):bool{
    global $fwdChecks,$fwdStage;
    $fwdStage=$label;$level=ob_get_level();ob_start();
    try{$detail=$operation();$fwdChecks[]=['label'=>$label,'ok'=>true,'detail'=>is_string($detail)?$detail:'Correcto'];return true;}
    catch(Throwable $error){$fwdChecks[]=['label'=>$label,'ok'=>false,'detail'=>fwd_failure($error)];return false;}
    finally{while(ob_get_level()>$level)ob_end_clean();}
}
register_shutdown_function(static function():void{
    global $fwdDone,$fwdAuthorized,$fwdChecks,$fwdStage,$fwdReserve;
    if($fwdDone)return;$fwdReserve='';$last=error_get_last();
    if($fwdAuthorized)$fwdChecks[]=['label'=>$fwdStage,'ok'=>false,'detail'=>$last&&in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR],true)?fwd_safe((string)$last['message'])."\nArchivo: ".fwd_safe((string)$last['file']).' · línea '.(int)$last['line']:'La aplicación interrumpió esta comprobación. Estado HTTP: '.(int)http_response_code().'.'];
    fwd_finish($fwdAuthorized?200:503);
});
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){header('Allow: GET');fwd_finish(405);}
try{
    $entry=(string)file_get_contents($fwdRoot.'/index.php',false,null,0,8192);$code=$fwdRoot;$fwdCodeLabel='app de la raíz';
    if(preg_match('~define\(\s*[\'"]FISITAAP_CODE_PREFIX[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)~',$entry,$match)){
        if(!preg_match('~^releases/[a-zA-Z0-9._-]+$~D',$match[1]))throw new RuntimeException('Ruta de versión no reconocida.');
        $resolved=realpath($fwdRoot.'/'.$match[1]);if(!$resolved||!str_starts_with($resolved,realpath($fwdRoot).DIRECTORY_SEPARATOR))throw new RuntimeException('Directorio de versión no disponible.');
        $code=$resolved;$fwdCodeLabel=$match[1];define('FISITAAP_SITE_ROOT',$fwdRoot);define('FISITAAP_CODE_PREFIX',$match[1]);
    }
    if(!is_file($fwdRoot.'/config.php'))throw new RuntimeException('Falta configuración privada.');
    require_once $code.'/app/bootstrap.php';ini_set('display_errors','0');
    if(!isset($app))throw new RuntimeException('No se pudo iniciar la aplicación.');
    $id=(int)(current_user()['id']??0);
    $user=$id?$app->one('SELECT id,tenant_id,name,role,is_active,force_password_change FROM users WHERE id=?',[$id]):null;
    if(!$user||!$user['is_active']||$user['force_password_change']||!in_array($user['role'],['master','tenant_admin'],true))fwd_finish(403);
    $fwdAuthorized=true;
    // Keep a request-local snapshot, never save diagnostic session mutations.
    if(session_status()===PHP_SESSION_ACTIVE)session_abort();$_SESSION['user']=$user;unset($GLOBALS['fisitaap_read_session176']);
    $app->db->exec('SET TRANSACTION READ ONLY');$app->db->beginTransaction();
    if(!fwd_check('Cargar funciones de la versión activa',static function()use($code):string{
        foreach(['admin','v1','admin_v1','branches_v1','modules_v12','physical_v14','pos_v141','manuals_v142','fisichat_v143','restructure_r1','restructure_r2','qa180'] as $part)require_once $code.'/app/'.$part.'.php';
        // The normal index declares this view helper outside the app libraries.
        // No router or index is executed by this isolated diagnostic.
        if(!function_exists('render_flashes')){function render_flashes():void{foreach(flashes() as [$type,$message])echo '<div class="alert alert-'.e($type).'">'.e($message).'</div>';}}
        if(!function_exists('qa180_welcome'))throw new RuntimeException('Esta copia no contiene la bienvenida 1.8.0.');
        return 'Funciones de bienvenida y panel cargadas.';
    }))fwd_finish();
    $target=$user['role']==='master'?'master':'admin';$_SERVER['REQUEST_URI']='/'.$target;$_GET=[];$_POST=[];
    if(!fwd_check('Contexto de la cuenta y negocio',static function()use($app,$target,$user,&$tenant):string{
        qa180_request_context($app,$target);$tenant=null;
        if($user['role']!=='master'){
            $id=(int)($user['tenant_id']??0);$tenant=$id?$app->one('SELECT * FROM tenants WHERE id=?',[$id]):null;
            if(!$tenant)throw new DomainException('La cuenta dueña apunta al negocio #'.$id.', pero ese negocio no existe. La cuenta maestra debe revisar la asociación del usuario.');
            if(!$tenant['is_active'])throw new DomainException('El negocio #'.$id.' ('.$tenant['name'].') está suspendido. La cuenta maestra debe revisar su estado.');
        }
        return 'Perfil: '.($target==='master'?'Maestro':'Dueño de negocio').'. Versión 1.8.0: '.(qa180_active($app)?'activa':'pendiente').'.';
    }))fwd_finish();
    fwd_check('Leer la imagen de bienvenida',static function()use($app):string{
        $image=r2_setting($app,0,'welcome_image180');$resolved=public_media_url($image);
        return $image===''?'Se usa la bienvenida predeterminada.':'Hay una imagen guardada. Su dirección se resolvió sin error.';
    });
    $renderState=$GLOBALS['fc_rendered']??null;
    fwd_check('Abrir bienvenida con la imagen actual',static function()use($app,$user,$tenant):string{qa180_welcome($app,$user,$tenant);return 'La bienvenida completa de esta cuenta se pudo generar.';});
    if($renderState===null)unset($GLOBALS['fc_rendered']);else $GLOBALS['fc_rendered']=$renderState;
    fwd_check('Generar panel sin la imagen personalizada',static function()use($app,$user,$tenant):string{admin_shell_start('Bienvenida',$user,$tenant,'dashboard');echo '<section>Comprobación sin imagen personalizada</section>';admin_shell_end();return 'La estructura del panel y el chat se pudieron generar sin la imagen de bienvenida.';});
    fwd_finish();
}catch(Throwable $error){if($fwdAuthorized)$fwdChecks[]=['label'=>$fwdStage,'ok'=>false,'detail'=>fwd_failure($error)];fwd_finish($fwdAuthorized?200:503);}
