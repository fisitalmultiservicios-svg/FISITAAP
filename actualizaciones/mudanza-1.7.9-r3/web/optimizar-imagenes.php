<?php
declare(strict_types=1);
if(function_exists('opcache_invalidate'))opcache_invalidate(__DIR__.'/config.php',true);
require __DIR__.'/app/bootstrap.php';
$user=require_login(['master']);
if(!$app->one('SELECT id FROM users WHERE id=? AND role="master" AND is_active=1',[(int)$user['id']])){http_response_code(403);exit('Acceso denegado.');}
if(($config['migration_mode']??false)!==true){http_response_code(403);exit('Habilita migration_mode únicamente en el config.php del servidor nuevo, siguiendo la guía de mudanza.');}
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'self'");
$error='';
if(is_post()){
    verify_csrf();
    try{
        if(!function_exists('imagewebp'))throw new RuntimeException('Pide al hosting activar GD con soporte WebP.');
        if(($_POST['action']??'')==='start'){
            $queue=[];$root=__DIR__.'/uploads';$scanned=0;
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file){
                if(++$scanned>50000)throw new RuntimeException('Hay demasiados archivos para este asistente. Solicita procesamiento por consola al proveedor.');
                $path=$file->getPathname();
                if(!$file->isFile()||$file->isLink()||str_contains($path,'/uploads/demo/')||preg_match('/\.(?:optimized|thumb)\.webp$/i',$path)||!preg_match('/\.(?:jpe?g|png|webp)$/i',$path))continue;
                if(count($queue)>=10000)throw new RuntimeException('Hay más de 10.000 imágenes. Solicita procesamiento por consola al proveedor.');
                $queue[]=$path;
            }
            $_SESSION['image_batch']=['queue'=>$queue,'total'=>count($queue),'processed'=>0,'before'=>0,'after'=>0,'warnings'=>[]];
        }elseif(($_POST['action']??'')==='next'&&isset($_SESSION['image_batch'])){
            $batch=&$_SESSION['image_batch'];$start=microtime(true);
            for($i=0;$i<3&&!empty($batch['queue'])&&microtime(true)-$start<6;$i++){
                $file=array_shift($batch['queue']);
                try{
                    if(filesize($file)>20*1024*1024)throw new RuntimeException('Archivo superior a 20 MB: revisa su tamaño manualmente.');
                    $result=image_optimize_existing($file);$batch['before']+=$result['before'];$batch['after']+=$result['after'];
                }catch(Throwable $failure){if(count($batch['warnings'])<30)$batch['warnings'][]=basename($file).': '.$failure->getMessage();}
                $batch['processed']++;
            }
        }
    }catch(Throwable $failure){$error=$failure->getMessage();}
}
$batch=$_SESSION['image_batch']??null;
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Optimizar imágenes · FISITAAP</title><style>body{font:16px/1.5 system-ui;background:#f4f6f8;color:#203f57;max-width:800px;margin:30px auto;padding:20px}main{background:white;border:1px solid #dce7ee;padding:24px}button{background:#17643c;color:white;border:0;padding:12px 18px;cursor:pointer}.error{color:#9b2626}</style></head><body><main><h1>Optimizar imágenes existentes</h1><p>Solo en el hosting nuevo. Conserva antes el respaldo privado de uploads. Se crean versiones WebP y miniaturas sin cambiar los enlaces de la base ni borrar originales. No se procesan fotos de pruebas de demo ni animaciones.</p><p>Las páginas usarán las versiones más livianas; conservar originales ocupa espacio adicional en el servidor. Los objetivos de tamaño dependen del contenido de cada foto y no son límites garantizados.</p>
<?php if($error): ?><p class="error"><?=e($error)?></p><?php endif; ?>
<?php if($batch): ?><p>Procesadas: <strong><?=(int)$batch['processed']?> / <?=(int)$batch['total']?></strong></p><p>Peso de las imágenes principales procesadas: <?=number_format($batch['before']/1024,1)?> KB → <?=number_format($batch['after']/1024,1)?> KB. Las miniaturas y respaldos ocupan espacio adicional.</p>
<?php if($batch['queue']): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="next"><button>Procesar siguiente lote</button></form><?php else: ?><p><strong>Proceso terminado.</strong> Revisa las fotos y el catálogo. Si aparece alguna advertencia, la imagen original sigue disponible. Si aún debes descargar uploads necesarios, hazlo después de esta optimización para incluir también las nuevas versiones.</p><?php endif; ?>
<?php if($batch['warnings']): ?><h2>Imágenes para revisar</h2><ul><?php foreach($batch['warnings'] as $warning): ?><li><?=e($warning)?></li><?php endforeach; ?></ul><?php endif; ?>
<?php endif; ?><form method="post" style="margin-top:20px"><?=csrf_field()?><input type="hidden" name="action" value="start"><button><?=$batch?'Revisar otra vez':'Buscar imágenes para optimizar'?></button></form><p>Cuando termines, elimina optimizar-imagenes.php y deshabilita migration_mode siguiendo la guía.</p></main></body></html>
