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

$pages = ['dashboard', 'map', 'manual', 'login', 'logout', 'content', 'edit', 'preview', 'media', 'menu', 'strings', 'settings', 'redirects', 'users', 'password', 'upload', 'code'];
$p = (string) ($_GET['p'] ?? 'dashboard');
if (!in_array($p, $pages, true)) $p = 'dashboard';
if (!in_array($p, ['login', 'logout'], true)) admin_require_login();
require __DIR__ . '/pages/' . $p . '.php';
