<?php
/**
 * cms_simple — punto de entrada del cron del hosting.
 *
 * Dos formas de llamarlo, ambas cada 15 o 30 minutos (cada paquete decide si le toca hacer algo):
 *   por HTTP:  curl -s "https://tu-sitio/_cms/cron?token=…"  (la URL con su token sale en la página del paquete)
 *   por CLI:   php /ruta/al/sitio/cms/cron.php
 * Ejecuta el gancho 'cron' de los paquetes activos, con una función $log para dejar constancia; el resumen de la última
 * ejecución queda en data/cron.json (lo enseñan las páginas de los paquetes). Un archivo de bloqueo evita solapes.
 */
declare(strict_types=1);
$cli = PHP_SAPI === 'cli';
if ($cli) { $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REQUEST_URI'] = '/'; }
if (!defined('CMS_DIR')) require_once __DIR__ . '/bootstrap.php';

if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
    if (!hash_equals(cms_cron_token(), (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit("Token incorrecto.\n"); }
}
ignore_user_abort(true);
@set_time_limit(0);

$lock = CMS_DATA . '/cron.lock';
if (is_file($lock) && filemtime($lock) > time() - 3600) exit("Ya hay una ejecución en curso.\n");
@touch($lock);
$t0 = microtime(true);
$log = [];
$logger = function (string $msg) use (&$log): void { $log[] = date('H:i:s') . ' ' . $msg; };
try {
    cms_do('cron', $logger);
} catch (\Throwable $e) {
    $log[] = date('H:i:s') . ' ERROR: ' . $e->getMessage();
}
@unlink($lock);
$out = ['last' => date('Y-m-d H:i:s'), 'seconds' => round(microtime(true) - $t0, 1), 'log' => array_slice($log, -50), 'hooks' => count(cms_hook_fns('cron'))];
cms_json_write(CMS_DATA . '/cron.json', $out);
echo $log ? implode("\n", $log) . "\n" : "Nada que hacer (" . $out['hooks'] . " tareas registradas).\n";
