<?php
// Servidor de desarrollo: php -S 127.0.0.1:8080 _router-dev.php  (emula el .htaccess)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && (is_file($file) || (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')))) {
    if (is_dir($file)) { $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php'; require rtrim($file, '/') . '/index.php'; return true; }
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
