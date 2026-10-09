<?php
declare(strict_types=1);
$started=hrtime(true);
require __DIR__.'/app/bootstrap.php';
if(!isset($app))exit;
$boot=(hrtime(true)-$started)/1e6;
$user=require_login(['master','tenant_admin']);
$fresh=$app->one('SELECT id,role,tenant_id FROM users WHERE id=? AND is_active=1',[$user['id']]);
if(!$fresh||$fresh['role']!==$user['role']||(int)$fresh['tenant_id']!==(int)($user['tenant_id']??0)){http_response_code(403);exit('Acceso denegado.');}
session_write_close();
$db=[];
for($i=0;$i<5;$i++){$t=hrtime(true);$app->one('SELECT 1 AS probe');$db[]=(hrtime(true)-$t)/1e6;}
sort($db);
$routes=$user['role']==='master'?['Panel maestro'=>'master','Empresas'=>'master/empresas']:['Panel del negocio'=>'admin','Productos'=>'admin/productos','Ventas'=>'admin/ventas'];
$routes['Archivo CSS']='assets/app.bundle.css?v=1-179r2';
$routes['Archivo JavaScript']='assets/direct-print.js?v=179r2';
$data=[];foreach($routes as $label=>$route)$data[]=['label'=>$label,'url'=>url($route)];
header('Cache-Control: private, no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'self'");
header('X-Content-Type-Options: nosniff');
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico de velocidad</title><style>body{font:16px/1.5 system-ui;margin:30px auto;padding:0 20px;max-width:800px;color:#243442;background:#f4f6f8}main{background:#fff;border:1px solid #dbe2e8;padding:24px}h1{font-size:25px}button{padding:12px 20px;border:0;background:#203f57;color:#fff;font:inherit;cursor:pointer}td,th{padding:12px;text-align:left;border-bottom:1px solid #dbe2e8}table{width:100%;border-collapse:collapse}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f4f6f8;padding:16px}</style></head><body><main><h1>Diagnóstico de velocidad</h1><p>Estas mediciones no modifican ventas ni configuración. Ejecuta la prueba cuando notes la lentitud y copia el resultado del recuadro.</p><p>Arranque del sistema: <strong><?=number_format($boot,1)?> ms</strong><br>Consulta mínima de base de datos (mediana de cinco): <strong><?=number_format($db[2],1)?> ms</strong></p><button type="button" id="measure">Medir desde este navegador</button><table><thead><tr><th>Petición</th><th>Resultado</th></tr></thead><tbody id="results"></tbody></table><pre id="summary">Pulsa Medir.</pre><p>El arranque incluye PHP, apertura de sesión y conexión a la base. Las peticiones incluyen red y respuesta del servidor; no ejecutan el contenido de las páginas descargadas. No son una medición de todas las funciones ni del rendimiento de la impresora.</p></main><script type="application/json" id="performance-data"><?=json_encode(['boot_ms'=>round($boot,1),'db_ms'=>round($db[2],1),'routes'=>$data],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><script src="diagnostico-rendimiento.js?v=2" defer></script></body></html>
