<?php
declare(strict_types=1);
$performanceStart179=hrtime(true);
$sessionStart179=hrtime(true);

define('ROOT_PATH', dirname(__DIR__));

if (!file_exists(ROOT_PATH . '/config.php')) {
    if (!str_starts_with($_SERVER['REQUEST_URI'] ?? '/', '/install')) {
        header('Location: ./install/');
        exit;
    }
    return;
}

$config = require ROOT_PATH . '/config.php';

ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Costa_Rica');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$healthRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && in_array(trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/'), ['api/desktop/status','api/desktop/pair'], true);
// Desktop connectivity probes are public reads. They need no session files or cookies.
if ($healthRequest) $_SESSION=[];
else {
session_name('fisitaap_session');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
$sessionStart179=hrtime(true);
session_start();
}
$sessionDuration179=(hrtime(true)-$sessionStart179)/1e6;
// Detailed timings are opt-in, authenticated, GET-only and never expose SQL values.
if (($_SERVER['REQUEST_METHOD']??'GET')==='GET'
    && ($_SERVER['HTTP_X_FISITAAP_PERFORMANCE']??'')==='1'
    && in_array($_SESSION['user']['role']??'',['master','tenant_admin'],true)) {
    $GLOBALS['fisitaap_performance179']=['start'=>$performanceStart179,'session'=>$sessionDuration179,'db'=>0.0,'count'=>0,'slow'=>[]];
    ob_start();
    register_shutdown_function(static function():void {
        $p=$GLOBALS['fisitaap_performance179'];
        if(headers_sent())return;
        $metrics=['php;dur='.round((hrtime(true)-$p['start'])/1e6,1),'session;dur='.round($p['session'],1),'db;dur='.round($p['db'],1),'queries;desc="'.$p['count'].'"'];
        foreach($p['slow'] as $i=>$q)$metrics[]='sql'.$i.';dur='.round($q['ms'],1).';desc="'.$q['label'].'"';
        header('Server-Timing: '.implode(', ',$metrics));
        header('Cache-Control: private, no-store');
    });
}

require_once ROOT_PATH . '/app/core.php';
require_once ROOT_PATH . '/app/session_reads176.php';

$db = Database::connect($config);
$app = new App($db, $config);
