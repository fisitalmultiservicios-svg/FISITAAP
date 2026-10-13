<?php
declare(strict_types=1);
// Honor disabling this one-time tool even if the host caches PHP configuration.
if(function_exists('opcache_invalidate'))opcache_invalidate(__DIR__.'/config.php',true);
require __DIR__.'/app/bootstrap.php';
$user=require_login(['master']);
if(!$app->one('SELECT id FROM users WHERE id=? AND role="master" AND is_active=1',[(int)$user['id']])){http_response_code(403);exit('Acceso denegado.');}
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'self'");
if(($config['migration_mode']??false)!==true){http_response_code(403);exit('Esta herramienta solo se usa en la copia del servidor nuevo. Agrega migration_mode => true en su config.php siguiendo la guía. No lo actives en el servidor anterior.');}
require_once __DIR__.'/app/data_tools179.php';
require_once __DIR__.'/app/demo_sandbox.php';
$tenants=$app->all('SELECT id,name,slug FROM tenants ORDER BY id');$keep=[];$remove=[];$errors=[];$message='';$ventanita=[];$demos=[];
foreach($tenants as $tenant){
    if(in_array($tenant['slug'],['restaurante-demo','tienda-demo'],true))$demos[$tenant['slug']]=$tenant;
    elseif(preg_replace('/[^a-z0-9]/','',strtolower($tenant['name']))==='laventanita'||in_array($tenant['slug'],['laventanita','la-ventanita'],true))$ventanita[]=$tenant;
}
if(count($ventanita)!==1)$errors[]='Se requiere exactamente un negocio La Ventanita. No se ejecutará limpieza con un negocio ambiguo o ausente.';
if(count($demos)!==2)$errors[]='Faltan uno o ambos demos: restaurante-demo y tienda-demo. Deben venir en el respaldo del sitio anterior.';
if(!$errors){$keep=array_merge(array_values($demos),$ventanita);$ids=array_map(static fn($t)=>(int)$t['id'],$keep);foreach($tenants as $t)if(!in_array((int)$t['id'],$ids,true))$remove[]=$t;}
if(is_post()&&($_POST['action']??'')==='media'){
    verify_csrf();
    if($errors||$remove||$app->setting('demo_sandbox_active','0')!=='1')$errors[]='Primero prepara la copia, sin abrir aún pruebas de demo, para conservar únicamente los archivos de los negocios elegidos.';
    else{try{require_once __DIR__.'/app/migration_media.php';fm_media_archive($app);}catch(Throwable $error){$errors[]=$error->getMessage();}}
}
if(is_post()&&($_POST['action']??'')!=='media'){
    verify_csrf();
    if($errors||($_POST['confirmation']??'')!=='SOLO LA VENTANITA Y DEMOS'||!hash_equals((string)$config['db_name'],(string)($_POST['database']??'')))$errors[]='Confirma el nombre de la base nueva y el texto de preparación.';
    else{
        $locked=false;
        try{
            $locked=(int)$app->one('SELECT GET_LOCK("fisitaap_migration179",1) ok')['ok']===1;
            if(!$locked)throw new RuntimeException('Ya hay otra preparación en curso.');
            // Save the table schema before filtering; business records are transactional.
            demo_schema($app);
            $deleted=fm_filter($app,$ids,true);
            $message='Preparación completada. Se conservaron La Ventanita, los dos demos y las cuentas maestras. Los demos ya abren copias independientes. Registros eliminados de la copia nueva: '.array_sum($deleted).'.';
            $remove=[];
        }catch(Throwable $error){$errors[]='No se completó la preparación: '.$error->getMessage();error_log('FISITAAP migration prepare: '.$error->getMessage());}
        finally{if($locked)$app->one('SELECT RELEASE_LOCK("fisitaap_migration179")');}
    }
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Preparar copia nueva · FISITAAP</title><style>body{font:16px/1.5 system-ui;color:#203f57;background:#f4f6f8;max-width:800px;margin:30px auto;padding:0 18px}main{background:white;border:1px solid #dce7ee;padding:24px}input{display:block;box-sizing:border-box;width:100%;padding:10px;margin:8px 0 18px}button{padding:12px 18px;background:#203f57;color:white;border:0;font:inherit}li,p{overflow-wrap:anywhere}.error{color:#a02626}.success{color:#17643c}</style></head><body><main>
<h1>Preparar la base del servidor nuevo</h1>
<p><strong>Usa esta página únicamente después de importar el respaldo en una base nueva.</strong> Eliminará de esta copia los otros negocios y sus registros. Conserva tu respaldo privado y el servidor anterior. No se utiliza en el sitio en funcionamiento.</p>
<p>Base seleccionada: <strong><?=e($config['db_name'])?></strong></p>
<?php if($message): ?><p class="success"><?=e($message)?></p><p>Ahora compara los datos de La Ventanita, optimiza las fotos y descarga uploads necesarios antes de abrir los demos. Al terminar todas las pruebas, quita migration_mode del config.php y elimina las herramientas temporales.</p><?php endif; ?>
<?php if($errors): ?><ul class="error"><?php foreach($errors as $error): ?><li><?=e($error)?></li><?php endforeach; ?></ul><?php endif; ?>
<h2>Se conservan</h2><ul><?php foreach($keep as $t): ?><li><?=e($t['name'])?> · /<?=e($t['slug'])?></li><?php endforeach; ?><li>Tu acceso maestro, configuración de plataforma y los clientes y colaboradores relacionados con los negocios conservados.</li></ul>
<h2>Se retiran de esta copia</h2><ul><?php foreach($remove as $t): ?><li><?=e($t['name'])?> · /<?=e($t['slug'])?></li><?php endforeach; ?><?php if(!$remove): ?><li>No hay otros negocios.</li><?php endif; ?></ul>
<?php if(!$message&&!$errors): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><label>Escribe el nombre completo de la base nueva<input name="database" required autocomplete="off"></label><label>Escribe SOLO LA VENTANITA Y DEMOS<input name="confirmation" required autocomplete="off"></label><button>Preparar esta copia nueva</button></form><?php endif; ?>
<?php if(!$remove&&!$errors&&$app->setting('demo_sandbox_active','0')==='1'): ?><h2>Imágenes y documentos necesarios</h2><p><a href="<?=e(url('optimizar-imagenes.php'))?>">Optimizar fotos anteriores por lotes</a>. Completa ese paso antes de descargar las imágenes.</p><p>Este botón descarga los archivos locales de uploads que aparecen referenciados en los datos conservados. No borra las imágenes originales. Si faltan archivos locales, el ZIP incluirá FISITAAP-ARCHIVOS-FALTANTES.txt: revísalo antes de sustituir uploads. Archivos enlazados externamente o agregados manualmente fuera de la base necesitan revisión aparte.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="media"><button>Descargar uploads necesarios</button></form><?php endif; ?>
<p>No se borran imágenes automáticamente: pueden estar compartidas entre páginas. La guía explica cómo verificar y sustituir uploads usando el ZIP descargado.</p>
</main></body></html>
