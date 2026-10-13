<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'self'");
// No configuration, session or database is needed. Never display private paths.
$serverCode=strtoupper(substr(hash('sha256',__DIR__.'|'.($_SERVER['SERVER_ADDR']??'')),0,12));
require __DIR__.'/app/browser_migration179.php';
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Servidor de prueba · FISITAAP</title><style>body{font:16px/1.5 system-ui;background:#f4f6f8;color:#203f57;margin:0;padding:30px 18px}main{max-width:620px;margin:40px auto;background:#fff;border:1px solid #dce7ee;padding:28px}h1{font-size:26px}button{font:inherit;background:#17643c;color:white;border:0;padding:12px 18px;cursor:pointer}code{font-size:20px}small{display:block;margin-top:20px}</style></head><body><main><h1>Estás en la copia nueva</h1><p>Revisión: <strong>179m2</strong></p><p>Código del servidor: <code><?=$serverCode?></code></p><p>Abre esta dirección en los otros navegadores. Deben mostrar esta misma revisión y el mismo código.</p><p>El botón renueva la caché de archivos de FISITAAP y abre la portada. Conserva tu sesión y tu carrito.</p><button id="refresh" type="button">Renovar archivos y abrir FISITAAP</button><p id="message" role="status"></p><small>Esta página no comprueba el certificado HTTPS. El aviso de seguridad debe revisarse con el hosting antes de publicar el sitio.</small></main>
<?php browser_recovery179((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost')); ?>
<script>document.getElementById('refresh').onclick=async function(){this.disabled=true;document.getElementById('message').textContent='Renovando archivos…';await window.fisitaapRefreshBrowser();location.replace('./?revision=179m2')};</script></body></html>
