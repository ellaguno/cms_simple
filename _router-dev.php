<?php
// Servidor de desarrollo: php -S 127.0.0.1:8080 _router-dev.php  (emula el .htaccess)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . rawurldecode($path);
// descargas contadas (Estadísticas): como el bloque "cms_simple descargas" del .htaccess
if (preg_match('#^/uploads/(.+\.(pdf|docx?|xlsx?|pptx?|ppsx?|od[tsp]|rtf|csv|epub|zip|rar|7z|gz|tgz|mp3|m4a|aac|wav|ogg|oga|opus|flac))$#i', $path, $m) && !preg_match('/(^|&)cmsdirect=1(&|$)/', (string) ($_SERVER['QUERY_STRING'] ?? ''))) {
    $_GET['p'] = '_cms/dl/' . rawurldecode($m[1]); $_SERVER['SCRIPT_NAME'] = '/index.php'; require __DIR__ . '/index.php'; return true;
}
if ($path !== '/' && (is_file($file) || (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')))) {
    if (is_dir($file)) { $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php'; require rtrim($file, '/') . '/index.php'; return true; }
    return false;
}
// carpetas propias con index.html (Archivos y carpetas): como hace Apache con DirectoryIndex
if ($path !== '/' && is_dir($file) && is_file(rtrim($file, '/') . '/index.html')) {
    if (substr($path, -1) !== '/') { header('Location: ' . $path . '/', true, 301); return true; }
    header('Content-Type: text/html; charset=utf-8'); readfile(rtrim($file, '/') . '/index.html'); return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
