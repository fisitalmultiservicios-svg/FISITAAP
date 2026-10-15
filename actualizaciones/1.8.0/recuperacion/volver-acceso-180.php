<?php
declare(strict_types=1);
// Only closes the browser's own authenticated session, after explicit POST+CSRF.
ob_start();ini_set('display_errors','0');
try{
    $root=__DIR__;$code=$root;$entry=(string)file_get_contents($root.'/index.php',false,null,0,8192);
    if(preg_match('~define\(\s*[\'"]FISITAAP_CODE_PREFIX[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)~',$entry,$match)){
        if(!preg_match('~^releases/[a-zA-Z0-9._-]+$~D',$match[1]))throw new RuntimeException('Unsupported entry');
        $code=realpath($root.'/'.$match[1]);if(!$code||!str_starts_with($code,realpath($root).DIRECTORY_SEPARATOR))throw new RuntimeException('Unavailable entry');
        define('FISITAAP_SITE_ROOT',$root);define('FISITAAP_CODE_PREFIX',$match[1]);
    }
    require_once $code.'/app/bootstrap.php';ini_set('display_errors','0');
    if(!isset($app))throw new RuntimeException('Unavailable app');
    $error='';
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        $token=$_POST['csrf']??null;
        if(is_string($token)&&isset($_SESSION['csrf'])&&hash_equals((string)$_SESSION['csrf'],$token)){
            $_SESSION=[];session_regenerate_id(true);ob_end_clean();header('Cache-Control: private, no-store');header('Location: '.url('owner-login'));exit;
        }
        $error='El formulario venció. Pulsa de nuevo el botón para volver al acceso.';
    }elseif(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET, POST');exit;}
    $csrf=csrf_token();ob_end_clean();
    header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Volver al acceso · FISITAAP</title><style>body{font:16px/1.6 system-ui;background:#f3f6f8;color:#193647;padding:24px}main{max-width:560px;margin:8vh auto;background:white;border:1px solid #dbe3e9;padding:28px}button{font:inherit;background:#176b4a;color:white;border:0;padding:12px 18px;cursor:pointer}</style></head><body><main><h1>Volver al acceso de FISITAAP</h1><p>Cierra la sesión de este navegador para poder ingresar con otra cuenta.</p><p>'.e($error).'</p><form method="post"><input type="hidden" name="csrf" value="'.e($csrf).'"><button>Volver al acceso</button></form></main></body></html>';
}catch(Throwable $error){while(ob_get_level())ob_end_clean();http_response_code(503);header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: no-store');echo 'No se pudo abrir la recuperación. Revisa que este archivo esté junto al index.php principal y que config.php esté disponible.';}
