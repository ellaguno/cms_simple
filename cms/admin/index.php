<?php
/**
 * cms_simple — despachador del panel de administración.
 * URL: /admin/?p=<página>   (dashboard por defecto)
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/fields.php';
require_once __DIR__ . '/inc/media.php';

$pages = ['dashboard', 'map', 'importar', 'manual', 'login', 'logout', 'content', 'edit', 'preview', 'demo', 'diseno', 'catalogo', 'actualizar', 'media', 'menu', 'strings', 'settings', 'redirects', 'backup', 'users', 'password', 'upload', 'code', 'categorias', 'render', 'archivos', 'estadisticas'];
$p = (string) ($_GET['p'] ?? 'dashboard');
// página propia de un paquete activo: ?p=pack:<nombre> → <paquete>/<archivo declarado en 'admin' => ['file' => …]>
$packPage = '';
if (preg_match('/^pack:([a-z0-9_-]+)$/i', $p, $m)) {
    $pk = cms_packs()[$m[1]] ?? null;
    $f = (string) ($pk['admin']['file'] ?? '');
    if ($pk && $f !== '' && preg_match('/^[a-z0-9_\/-]+\.php$/i', $f) && strpos($f, '..') === false && is_file($pk['dir'] . '/' . $f)) $packPage = $pk['dir'] . '/' . $f;
}
if ($packPage === '' && !in_array($p, $pages, true)) $p = 'dashboard';
if (!in_array($p, ['login', 'logout'], true)) admin_require_login();
if (admin_user()) {
    // quien entra al panel no cuenta en las estadísticas del sitio (lib/stats.php), ni después de cerrar la sesión
    if (!isset($_COOKIE['cms_staff'])) setcookie('cms_staff', '1', ['expires' => time() + 86400 * 400, 'path' => (CMS_BASE ?: '/'), 'httponly' => true, 'samesite' => 'Lax']);
    // el bloque de descargas del .htaccess de la raíz: el actualizador solo instala cms/, así que se pone (o se quita) desde aquí
    if (cms_stats_htaccess_state() !== (cms_stats_enabled() ? 'ok' : 'missing')) cms_stats_htaccess_sync();
}
if ($packPage !== '') { $pack = $pk; require $packPage; exit; }
require __DIR__ . '/pages/' . $p . '.php';
