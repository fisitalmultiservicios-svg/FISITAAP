<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
require_once __DIR__.'/demo_sandbox.php';
if($app->setting('demo_sandbox_active','0')!=='1')exit;
try { echo demo_cleanup($app,20)." copias temporales eliminadas.\n"; }
catch(Throwable $error){fwrite(STDERR,"No se completó la limpieza de demos; revisar registros.\n");error_log('FISITAAP demo cleanup: '.$error->getMessage());exit(1);}
