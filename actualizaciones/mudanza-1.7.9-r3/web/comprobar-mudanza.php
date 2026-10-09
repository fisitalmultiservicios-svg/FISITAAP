<?php
declare(strict_types=1);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if (!is_file(__DIR__.'/config.php')) {
    http_response_code(503);
    exit('Falta config.php. Sube tu configuración privada y actualiza los cuatro datos de la base de datos siguiendo la guía.');
}
try {
    require __DIR__.'/app/bootstrap.php';
} catch (Throwable $error) {
    ini_set('display_errors', '0');
    error_log('FISITAAP migration bootstrap: '.get_class($error));
    http_response_code(503);
    exit('No se pudo conectar. Revisa en config.php el servidor, nombre, usuario y contraseña de la base nueva. Comprueba que importaste el respaldo y asignaste permisos al usuario.');
}
ini_set('display_errors', '0');
$user=current_user();
if (!$user) redirect(url('owner-login'));
if ($user['role']!=='master' || !$app->one('SELECT id FROM users WHERE id=? AND role="master" AND is_active=1', [(int)$user['id']])) {
    http_response_code(403);
    exit('Esta comprobación es para la cuenta maestra.');
}
session_write_close();
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'none'; frame-ancestors 'self'; base-uri 'none'");
$manifest=require __DIR__.'/mudanza-manifest.php';
$issues=[]; $notes=[]; $counts=[]; $balances=[]; $businessStats=[]; $uploadCount=0; $uploadBytes=0;
foreach ($manifest['files'] as $path=>$hash) {
    if (!is_file(__DIR__.'/'.$path) || hash_file('sha256',__DIR__.'/'.$path)!==$hash) $issues[]='Archivo ausente o diferente: '.$path;
}
foreach (['pdo_mysql','mbstring','openssl','curl','fileinfo','gd','dom','libxml'] as $ext) if (!extension_loaded($ext)) $issues[]='Falta la extensión PHP '.$ext;
if (PHP_VERSION_ID<80300) $issues[]='Selecciona PHP 8.3 o 8.4 en cPanel: son las versiones objetivo de este paquete.';
if (!empty($config['debug'])) $issues[]='Pon debug en false en config.php para el sitio público.';
if (trim((string)($config['app_key']??''))==='') $issues[]='Falta app_key. Conserva exactamente la clave del config.php anterior.';
if (!str_starts_with((string)($config['app_url']??''),'https://')) $notes[]='El sitio público debe usar HTTPS con certificado válido. La copia de laboratorio puede usar HTTP.';
if (!ini_get('opcache.enable')) $notes[]='Consulta al hosting si puede activar OPcache para PHP.';
try {
    $tables=array_column($app->all('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE="BASE TABLE"'), 'TABLE_NAME');
    $columns=[];
    foreach ($app->all('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()') as $row) $columns[$row['TABLE_NAME']][]=$row['COLUMN_NAME'];
    foreach ($manifest['tables'] as $table=>$expected) {
        if (!in_array($table,$tables,true)) { $issues[]='Falta la tabla '.$table; continue; }
        $missing=array_diff($expected,$columns[$table]??[]);
        if ($missing) $issues[]='Faltan columnas en '.$table.': '.implode(', ',$missing);
    }
    foreach ($manifest['indexes'] as [$table,$index]) {
        if (!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$index])) $issues[]='Falta el índice '.$index.' en '.$table;
    }
    foreach ($manifest['flags'] as $flag) if ($app->setting($flag,'0')!=='1') $issues[]='No está activa la configuración '.$flag.'. Comprueba que elegiste el respaldo completo del sitio actual.';
    // Read-only, consistent snapshot: this page never repairs or changes business records.
    $app->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $app->db->exec('SET TRANSACTION READ ONLY');
    $app->db->beginTransaction();
    foreach ($manifest['tables'] as $table=>$expected) if (in_array($table,$tables,true)) $counts[$table]=(int)$app->one('SELECT COUNT(*) n FROM `'.$table.'`')['n'];
    foreach (['sales'=>['total'],'orders'=>['total'],'accounts_receivable'=>['amount','balance'],'accounts_payable'=>['amount','balance'],'product_inventory'=>['quantity'],'fisitaap_r3_gift_cards'=>['initial_amount','balance']] as $table=>$fields) {
        if (!in_array($table,$tables,true)) continue;
        foreach ($fields as $field) if (in_array($field,$columns[$table]??[],true)) $balances[$table.'.'.$field]=(string)$app->one('SELECT COALESCE(SUM(`'.$field.'`),0) n FROM `'.$table.'`')['n'];
    }
    if(in_array('tenants',$tables,true))foreach($app->all('SELECT id,name,slug FROM tenants WHERE slug IN("laventanita","la-ventanita","restaurante-demo","tienda-demo") OR LOWER(name)="la ventanita" ORDER BY id') as $business){
        $rows=[];$totals=[];
        foreach($manifest['tables'] as $table=>$expected)if(in_array('tenant_id',$columns[$table]??[],true))$rows[$table]=(int)$app->one('SELECT COUNT(*) n FROM `'.$table.'` WHERE tenant_id=?',[$business['id']])['n'];
        foreach(['sales'=>['total'],'orders'=>['total'],'accounts_receivable'=>['amount','balance'],'accounts_payable'=>['amount','balance'],'product_inventory'=>['quantity']] as $table=>$fields)if(in_array('tenant_id',$columns[$table]??[],true))foreach($fields as $field)if(in_array($field,$columns[$table],true))$totals[$table.'.'.$field]=(string)$app->one('SELECT COALESCE(SUM(`'.$field.'`),0) n FROM `'.$table.'` WHERE tenant_id=?',[$business['id']])['n'];
        $businessStats[]=['business'=>$business,'rows'=>$rows,'totals'=>$totals];
    }
    $app->db->commit();
    if (is_dir(__DIR__.'/uploads')) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/uploads',FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && !$file->isLink() && !str_starts_with($file->getFilename(),'.')) { $uploadCount++; $uploadBytes+=$file->getSize(); }
        }
    } else $issues[]='Falta la carpeta uploads.';
    if (!is_writable(__DIR__.'/uploads')) $issues[]='PHP no puede escribir en uploads. Pide al hosting que revise el propietario y los permisos; no uses 777.';
} catch (Throwable $error) {
    if ($app->db->inTransaction()) $app->db->rollBack();
    $issues[]='No se pudo completar la lectura. Revisa el registro de errores de cPanel y que la importación terminara sin errores.';
    error_log('FISITAAP migration check: '.get_class($error));
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comprobar mudanza · FISITAAP</title><style>body{font:16px/1.5 system-ui;background:#f4f6f8;color:#203f57;max-width:900px;margin:30px auto;padding:0 18px}main{background:white;border:1px solid #dce7ee;padding:24px}h1{font-size:26px}table{width:100%;border-collapse:collapse}td,th{padding:8px;text-align:left;border-bottom:1px solid #dce7ee;overflow-wrap:anywhere}p,li{overflow-wrap:anywhere}.good{color:#17643c}.bad{color:#9b2626}@media print{body{background:white;margin:0}main{border:0}}</style></head><body><main>
<h1>Comprobar mudanza · <?=e($manifest['version'])?></h1>
<p>Solo lee información. No modifica ventas, cuentas ni equipos.</p>
<?php if ($issues): ?><h2 class="bad">Hay puntos que revisar</h2><ul><?php foreach ($issues as $issue): ?><li><?=e($issue)?></li><?php endforeach; ?></ul><?php else: ?><h2 class="good">Archivos y estructura comprobados</h2><?php endif; ?>
<?php if ($notes): ?><ul><?php foreach ($notes as $note): ?><li><?=e($note)?></li><?php endforeach; ?></ul><?php endif; ?>
<p><strong>Esto no confirma que la mudanza esté completa.</strong> Antes de filtrar la copia, compara los totales completos con el respaldo anterior. Después de filtrar, los totales de plataforma cambian: compara especialmente el apartado de La Ventanita, que debe conservar sus cantidades y saldos. Comprueba también acceso, fotos, venta, sincronización e impresión siguiendo la guía.</p>
<p>Archivos de uploads: <strong><?=$uploadCount?></strong> · Tamaño: <strong><?=$uploadBytes?> bytes</strong>. No incluye archivos ocultos de protección; contar archivos no comprueba su contenido.</p>
<h2>Registros por tabla</h2><table><tr><th>Tabla</th><th>Cantidad</th></tr><?php foreach ($counts as $table=>$count): ?><tr><td><?=e($table)?></td><td><?=$count?></td></tr><?php endforeach; ?></table>
<h2>Totales de comparación</h2><p>Son sumas para comparar los dos respaldos, incluyen todos los estados. No representan el cierre contable.</p><table><tr><th>Dato</th><th>Valor</th></tr><?php foreach ($balances as $field=>$value): ?><tr><td><?=e($field)?></td><td><?=e($value)?></td></tr><?php endforeach; ?></table>
<?php foreach($businessStats as $stats): ?><h2><?=e($stats['business']['name'])?> · /<?=e($stats['business']['slug'])?></h2><p>Identificador conservado: <?=(int)$stats['business']['id']?>. Compara estos números con el servidor anterior, después del cierre de cajas.</p><table><tr><th>Registros del negocio</th><th>Cantidad</th></tr><?php foreach($stats['rows'] as $table=>$count): ?><tr><td><?=e($table)?></td><td><?=$count?></td></tr><?php endforeach; ?></table><table><tr><th>Total del negocio</th><th>Valor</th></tr><?php foreach($stats['totals'] as $field=>$value): ?><tr><td><?=e($field)?></td><td><?=e($value)?></td></tr><?php endforeach; ?></table><?php endforeach; ?>
<p>Guarda esta página con Ctrl+P → Guardar como PDF. Después de completar el traslado, elimina comprobar-mudanza.php y mudanza-manifest.php del servidor.</p>
</main></body></html>
