<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$session=current_user();if(!$session){header('Location: '.url('owner-login'));exit;}
$actor=$app->one('SELECT id,role,is_active FROM users WHERE id=?',[(int)$session['id']]);
if(!$actor||!$actor['is_active']||$actor['role']!=='master'){http_response_code(403);exit('Solo el maestro puede instalar esta actualización.');}
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: SAMEORIGIN');
require __DIR__.'/files.php';
function update180_path(string $name):string {
    if(!in_array($name,UPDATE180_FILES,true)&&!array_key_exists($name,UPDATE180_LEGACY))throw new RuntimeException('Archivo no permitido.');
    $root=realpath(dirname(__DIR__));$path=$root.'/'.$name;$cursor=dirname($path);
    while(!file_exists($cursor)&&$cursor!==$root)$cursor=dirname($cursor);$parent=realpath($cursor);
    if(!$parent||($parent!==$root&&!str_starts_with($parent,$root.DIRECTORY_SEPARATOR))||is_link($path))throw new RuntimeException('Ruta no segura: '.$name);
    $cursor=$root;foreach(explode('/',dirname($name)) as $part){if($part==='.')continue;$cursor.='/'.$part;if(is_link($cursor))throw new RuntimeException('Directorio enlazado no permitido: '.$name);}
    return $path;
}
function update180_manifest():array {
    $m=json_decode((string)file_get_contents(__DIR__.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if(($m['version']??'')!=='1.8.0'||array_keys($m['files']??[])!==UPDATE180_FILES)throw new RuntimeException('Paquete incompleto. Sube el ZIP original.');
    foreach($m['files'] as $name=>$info){$payload=__DIR__.'/payload/'.$name;if(!is_file($payload)||is_link($payload)||hash_file('sha256',$payload)!==$info['new'])throw new RuntimeException('Falló la integridad del paquete: '.$name);}
    return $m;
}
function update180_states(array $m):array {
    $entry=dirname(__DIR__).'/index.php';
    if(!is_file($entry)||hash_file('sha256',$entry)!==$m['files']['index.php']['new']){
        foreach(UPDATE180_LEGACY as $name=>$hashes){$path=update180_path($name);if(is_file($path)&&!in_array(hash_file('sha256',$path),$hashes,true))throw new RuntimeException('Hay archivos modificados fuera de los paquetes conocidos: '.$name.'. No se reemplazó nada. Conserva el respaldo y solicita revisión.');}
    }
    $out=[];foreach($m['files'] as $name=>$info){$target=update180_path($name);$hash=is_file($target)?hash_file('sha256',$target):null;
        $out[$name]=$hash===$info['new']?'actualizado':($hash===null||in_array($hash,$info['old'],true)?'compatible':'distinto');
        $parent=dirname($target);while(!is_dir($parent))$parent=dirname($parent);if(!is_writable($parent)||(is_file($target)&&!is_writable($target)))throw new RuntimeException('Revisa los permisos de '.$name);
    }return $out;
}
function update180_write(string $path,string $data):void {
    $new=[];$cursor=dirname($path);while(!is_dir($cursor)){$new[]=$cursor;$cursor=dirname($cursor);}
    foreach(array_reverse($new) as $directory)if((!is_dir($directory)&&!mkdir($directory,0755))||!chmod($directory,0755))throw new RuntimeException('No se pudo preparar el directorio.');
    $mode=is_file($path)?fileperms($path)&0777:0644;$temp=tempnam(dirname($path),'.fisitaap-180-');if(!$temp)throw new RuntimeException('No se pudo preparar el archivo.');
    try{if(file_put_contents($temp,$data)!==strlen($data)||!chmod($temp,$mode)||!rename($temp,$path))throw new RuntimeException('No se pudo guardar el archivo.');if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);}finally{if(is_file($temp))unlink($temp);}
}
function update180_active(App $app):bool {return ($app->one('SELECT `value` FROM settings WHERE `key`="qa180_active"')['value']??'0')==='1';}
function update180_restore(string $folder,array $record):void {
    foreach(array_merge(['index.php'],array_values(array_diff(UPDATE180_FILES,['index.php']))) as $name){$path=update180_path($name);$hash=$record['files'][$name];if($hash===null){if(is_file($path)&&!unlink($path))throw new RuntimeException('No se pudo retirar '.$name);}else update180_write($path,(string)file_get_contents($folder.'/'.$name.'.backup180'));}
}
function update180_apply(App $app,array $m):string {
    $states=update180_states($m);if(in_array('distinto',$states,true))throw new RuntimeException('Hay archivos modificados fuera de los paquetes conocidos. No se reemplazó nada. Conserva el respaldo y solicita revisión de los archivos marcados distinto.');
    if(count(array_filter($states,fn($s)=>$s==='actualizado'))===count($states)){require_once dirname(__DIR__).'/releases/1.8.0-r8/app/updates180.php';qa180_publish($app);return 'Los archivos 1.8.0 ya están instalados. La actualización está publicada para que cada negocio la active desde Administración → Actualizar sistema.';}
    $folder=__DIR__.'/backups/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6));if(!mkdir($folder,0750,true))throw new RuntimeException('No se pudo crear el respaldo.');$record=['version'=>'1.8.0','files'=>[]];
    foreach(UPDATE180_FILES as $name){$target=update180_path($name);$hash=is_file($target)?hash_file('sha256',$target):null;$record['files'][$name]=$hash;
        if($hash!==null){$backup=$folder.'/'.$name.'.backup180';if(!is_dir(dirname($backup))&&!mkdir(dirname($backup),0750,true))throw new RuntimeException('No se pudo preparar el respaldo.');if(!copy($target,$backup)||hash_file('sha256',$backup)!==$hash)throw new RuntimeException('No se pudo verificar el respaldo de '.$name);}
    }
    if(file_put_contents($folder.'/record.json',json_encode($record,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('No se pudo registrar el respaldo.');
    try{
        foreach(array_merge(array_values(array_diff(UPDATE180_FILES,['index.php','.htaccess'])),['.htaccess','index.php']) as $name)update180_write(update180_path($name),(string)file_get_contents(__DIR__.'/payload/'.$name));
        foreach($m['files'] as $name=>$info)if(hash_file('sha256',update180_path($name))!==$info['new'])throw new RuntimeException('Falló la comprobación después de copiar.');
        if(file_put_contents(__DIR__.'/backups/latest.json',json_encode(['directory'=>basename($folder)],JSON_THROW_ON_ERROR))===false)throw new RuntimeException('No se pudo registrar la restauración.');

    }catch(Throwable $error){update180_restore($folder,$record);throw $error;}
    require_once dirname(__DIR__).'/releases/1.8.0-r8/app/updates180.php';
    try{qa180_publish($app);}catch(Throwable $error){throw new RuntimeException('Los archivos se instalaron. La publicación no terminó; los negocios conservan su versión. Repite Instalar archivos para completar la preparación. Conserva el respaldo.');}
    return 'Archivos 1.8.0 instalados y comprobados. Actualización publicada: cada dueño puede activarla desde Administración → Actualizar sistema cuando sus propias cajas estén listas.';
}
function update180_rollback(App $app,array $m):string {
    if(update180_active($app))throw new RuntimeException('La actualización ya se publicó y preparó la base de datos. Para volver atrás se necesita revisar el respaldo completo de cPanel y las operaciones posteriores.');
    $latest=json_decode((string)file_get_contents(__DIR__.'/backups/latest.json'),true,512,JSON_THROW_ON_ERROR);$directory=(string)($latest['directory']??'');if(!preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{12}$/D',$directory))throw new RuntimeException('Respaldo no válido.');$folder=__DIR__.'/backups/'.$directory;$record=json_decode((string)file_get_contents($folder.'/record.json'),true,512,JSON_THROW_ON_ERROR);
    if(($record['version']??'')!=='1.8.0'||array_keys($record['files']??[])!==UPDATE180_FILES)throw new RuntimeException('El respaldo no corresponde a esta versión.');
    foreach($m['files'] as $name=>$info){$path=update180_path($name);if(!is_file($path)||hash_file('sha256',$path)!==$info['new'])throw new RuntimeException('Hay cambios posteriores: '.$name.'. No se sobrescribirán.');$hash=$record['files'][$name];$backup=$folder.'/'.$name.'.backup180';if($hash!==null&&(!is_file($backup)||is_link($backup)||hash_file('sha256',$backup)!==$hash))throw new RuntimeException('Respaldo incompleto o modificado.');}
    update180_restore($folder,$record);return 'Archivos anteriores restaurados. La base de datos, config.php y uploads se conservaron.';
}
$message='';$error='';$states=[];
try{
    if(PHP_VERSION_ID<80200||!extension_loaded('pdo_mysql')||!extension_loaded('mbstring')||!extension_loaded('SimpleXML'))throw new RuntimeException('Selecciona PHP 8.2 o 8.3 con PDO MySQL, mbstring y SimpleXML en cPanel.');
    $manifest=update180_manifest();
    if(is_post()){
        $token=(string)($_POST['csrf']??'');if($token===''||!hash_equals((string)($_SESSION['csrf']??''),$token)){http_response_code(403);exit('Sesión vencida. Recarga esta página y vuelve a intentar.');}
        if(($_POST['backup']??'')!=='yes')throw new RuntimeException('Confirma el respaldo completo.');
        if(!is_dir(__DIR__.'/backups')&&!mkdir(__DIR__.'/backups',0750,true))throw new RuntimeException('No se pudo preparar el respaldo.');if(file_put_contents(__DIR__.'/backups/.htaccess',"Require all denied\n")===false)throw new RuntimeException('No se pudo proteger el respaldo.');$lock=fopen(__DIR__.'/backups/update.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Otra instalación está en curso.');
        try{$message=match((string)($_POST['operation']??'')){'apply'=>update180_apply($app,$manifest),'rollback'=>update180_rollback($app,$manifest),default=>throw new RuntimeException('Operación desconocida.')};}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    $states=update180_states($manifest);
}catch(Throwable $failure){error_log('FISITAAP updater180: '.get_class($failure));$error=$failure instanceof PDOException?'La base de datos no respondió. No cambies config.php; revisa el registro del hosting.':$failure->getMessage();}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Actualizar FISITAAP 1.8.0-R8</title><style>body{font:16px system-ui;background:#f3f6f8;color:#193647;margin:0;padding:20px}main{max-width:850px;margin:auto;background:white;border:1px solid #dae3e9;padding:24px}p{line-height:1.6}button,a.button{display:inline-block;padding:12px 18px;margin:10px 5px 10px 0;background:#176bb0;color:white;border:0;text-decoration:none;font:inherit}button:disabled{opacity:.4}.notice{padding:14px;background:#eaf5eb}.error{background:#fff0ee;color:#842a20}td,th{text-align:left;padding:8px;border-bottom:1px solid #eee;overflow-wrap:anywhere}table{width:100%;table-layout:fixed}label{display:block;margin:20px 0}</style></head><body><main><h1>Actualización 1.8.0-R8</h1><p>Publica los archivos una vez. Cada negocio mantiene su funcionamiento anterior y activa los cambios desde Administración → Actualizar sistema cuando pueda.</p><p>Antes de publicar: descarga un respaldo completo de los archivos y exporta la base de datos desde cPanel. La publicación conserva los turnos de los negocios. Cada dueño sincronizará y cerrará sus cajas cuando active su actualización. Este instalador respalda los archivos que reemplaza y conserva config.php y uploads.</p>
<?php if($message):?><p class="notice"><?=e($message)?></p><?php endif?><?php if($error):?><p class="notice error"><?=e($error)?></p><?php endif?>
<form method="post"><?=csrf_field()?><label><input type="checkbox" name="backup" value="yes" required> Guardé respaldo de archivos y base de datos.</label><button name="operation" value="apply" <?=(!$states||in_array('distinto',$states,true)||$error)?'disabled':''?>>Instalar archivos</button><?php if(is_file(__DIR__.'/backups/latest.json')&&!update180_active($app)):?><button name="operation" value="rollback">Restaurar antes de publicar</button><?php endif?></form><a class="button" href="<?=e(url('activar-180.php'))?>">Ver actualizaciones por negocio</a><a class="button" href="<?=e(url('master'))?>">Abrir panel</a><details><summary>Comprobación de archivos</summary><table><tr><th>Archivo</th><th>Estado</th></tr><?php foreach($states as $name=>$state):?><tr><td><?=e($name)?></td><td><?=e($state)?></td></tr><?php endforeach?></table></details><p>Conserva el respaldo fuera de public_html. Al terminar las pruebas, retira la carpeta actualizador-180 del sitio. La aplicación utiliza releases/1.8.0-r8: conserva esa carpeta, app, assets, config.php y uploads.</p></main></body></html>
