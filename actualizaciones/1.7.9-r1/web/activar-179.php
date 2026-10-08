<?php
declare(strict_types=1);
// Physical entry point: retain the normal master-session and CSRF checks,
// without relying on the hosting's rewrite rules for the activation POST.
$_SERVER['REQUEST_URI'] = '/master/actualizar-179';
require __DIR__.'/index.php';
