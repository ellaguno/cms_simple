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

$pages = ['dashboard', 'map', 'importar', 'manual', 'login', 'logout', 'content', 'edit', 'preview', 'demo', 'diseno', 'catalogo', 'actualizar', 'media', 'menu', 'strings', 'settings', 'redirects', 'backup', 'users', 'password', 'upload', 'code', 'categorias', 'render', 'archivos'];
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
if ($packPage !== '') { $pack = $pk; require $packPage; exit; }
require __DIR__ . '/pages/' . $p . '.php';
