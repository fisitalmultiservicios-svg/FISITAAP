<?php
declare(strict_types=1);
// Read-only: no configuration, bootstrap, sessions, database or installation.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
$root = __DIR__;
$docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$checks = [
    'index.php principal encontrado' => is_file($root.'/index.php'),
    'app/bootstrap.php encontrado' => is_file($root.'/app/bootstrap.php'),
    'Carpeta actualizador-180 encontrada' => is_dir($root.'/actualizador-180'),
    'index.php del actualizador encontrado' => is_file($root.'/actualizador-180/index.php'),
    'index.php del actualizador legible' => is_file($root.'/actualizador-180/index.php') && is_readable($root.'/actualizador-180/index.php'),
    'manifest.json del paquete encontrado' => is_file($root.'/actualizador-180/manifest.json'),
    'files.php del paquete encontrado' => is_file($root.'/actualizador-180/files.php'),
    'payload del paquete encontrado' => is_dir($root.'/actualizador-180/payload'),
    '.htaccess principal encontrado' => is_file($root.'/.htaccess'),
];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Comprobar ruta 1.8.0</title><style>body{font:16px/1.5 system-ui;color:#193647;background:#f3f6f8;padding:20px}main{max-width:760px;margin:auto;background:white;padding:24px;border:1px solid #dae3e9}td,th{padding:8px;text-align:left;border-bottom:1px solid #ddd}table{width:100%}</style></head><body><main><h1>Comprobar ruta 1.8.0</h1><p>Identificador: <strong>FISITAAP-RUTA-180-20261014</strong></p><p>Este archivo comprueba únicamente la ubicación de los archivos. No instala cambios ni accede a tus datos.</p><table><thead><tr><th>Comprobación</th><th>Resultado</th></tr></thead><tbody><?php foreach($checks as $label=>$ok):?><tr><td><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?></td><td><?=$ok?'Sí':'No'?></td></tr><?php endforeach?></tbody></table><p>La carpeta de este archivo coincide con la raíz que indica el servidor: <strong><?=$docroot===false?'No disponible':($docroot===realpath($root)?'Sí':'No')?></strong>.</p><p>Comparte estos resultados. Al terminar, elimina comprobar-ruta-180.php y prueba-ruta-180.txt, y el archivo prueba-ruta-180.txt de la carpeta actualizador-180.</p></main></body></html>
