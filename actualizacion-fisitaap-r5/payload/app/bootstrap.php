<?php
declare(strict_types=1);

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
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
}

require_once ROOT_PATH . '/app/core.php';

$db = Database::connect($config);
$app = new App($db, $config);
