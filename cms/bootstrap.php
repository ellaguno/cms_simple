<?php
/**
 * cms_simple — arranque del núcleo.
 *
 * Estructura esperada:
 *   /cms/       núcleo (este directorio; no se edita por sitio)
 *   /site/      tema y configuración del sitio (config.php, inc/layout.php, templates/, assets/)
 *   /data/      contenido en JSON (se crea solo)
 *   /uploads/   archivos subidos desde el admin
 *   /admin/     punto de entrada del panel (admin/index.php)
 *   /index.php  punto de entrada público
 */
declare(strict_types=1);

const CMS_VERSION = '1.20.0';

define('CMS_DIR', __DIR__);
define('CMS_ROOT', dirname(__DIR__));
define('CMS_DATA', CMS_ROOT . '/data');
define('CMS_UPLOADS', CMS_ROOT . '/uploads');
define('CMS_THEMES', CMS_ROOT . '/themes');

/**
 * Carpeta del tema activo. Un sitio puede tener varios temas en themes/<clave>/ y elegir uno en Admin → Diseño
 * (queda en data/settings.json → 'theme'); si no hay ninguno, se usa site/ como toda la vida.
 * Se resuelve aquí, antes que nada, porque CMS_SITE es una constante que usa todo el núcleo.
 */
function cms_active_theme_dir(): string
{
    $legacy = CMS_ROOT . '/site';
    $ok = fn(string $d) => is_dir($d) && (is_file($d . '/config.php') || is_file($d . '/theme.json'));
    $s = @file_get_contents(CMS_DATA . '/settings.json');
    $k = '';
    if (is_string($s) && $s !== '') { $j = json_decode($s, true); if (is_array($j)) $k = (string) ($j['theme'] ?? ''); }
    if ($k !== '' && preg_match('/^[a-z0-9_-]+$/i', $k) && $ok(CMS_THEMES . '/' . $k)) return CMS_THEMES . '/' . $k;
    if ($ok($legacy)) return $legacy;
    foreach (glob(CMS_THEMES . '/*', GLOB_ONLYDIR) ?: [] as $d) if ($ok($d)) return $d;
    return $legacy;
}
define('CMS_SITE', cms_active_theme_dir());
/** Ruta relativa del tema activo desde la raíz del sitio: "site" o "themes/<clave>". */
define('CMS_SITE_REL', trim(str_replace('\\', '/', substr(CMS_SITE, strlen(CMS_ROOT))), '/') ?: 'site');

/** Ruta base donde está instalado el sitio ("" en la raíz, "/pruebas" en una subcarpeta). */
function cms_detect_base(): string
{
    $root = str_replace('\\', '/', (string) realpath(CMS_ROOT));
    $doc = isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''
        ? str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    if ($doc !== '' && strpos($root, $doc) === 0) return rtrim(substr($root, strlen($doc)), '/');
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = preg_replace('#/admin$#', '', $dir);
    return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
}
define('CMS_BASE', cms_detect_base());
/** URL pública de la carpeta del tema activo ("/site" o "/themes/<clave>"). */
define('CMS_SITE_BASE', CMS_BASE . '/' . CMS_SITE_REL);

/** Configuración del sitio (site/config.php) con valores por defecto. */
function cms_config(?string $key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $user = is_file(CMS_SITE . '/config.php') ? (array) require CMS_SITE . '/config.php' : [];
        $cfg = array_replace([
            'name' => 'Mi sitio',
            'langs' => ['es'],
            'default_lang' => 'es',
            'timezone' => 'America/Mexico_City',
            'types' => [],
            'pages' => [],
            'settings' => [],
            'strings_groups' => [],
            'form' => ['required' => ['nombre', 'correo'], 'email_field' => 'correo', 'name_field' => 'nombre', 'honeypot' => 'empresa_web2'],
            'admin_logo' => '',
            'max_image_width' => 1800,
            'code_editor' => true,
        ], $user);
        if (!in_array($cfg['default_lang'], $cfg['langs'], true)) array_unshift($cfg['langs'], $cfg['default_lang']);
    }
    if ($key === null) return $cfg;
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

function cms_langs(): array { return cms_config('langs'); }
function cms_default_lang(): string { return cms_config('default_lang'); }

/** Idiomas activos (el predeterminado siempre; los demás según Ajustes → languages). */
function cms_active_langs(): array
{
    $S = cms_settings();
    return array_values(array_filter(cms_langs(), fn($l) => $l === cms_default_lang() || !empty($S['languages'][$l])));
}

mb_internal_encoding('UTF-8');
date_default_timezone_set((string) cms_config('timezone'));

require_once CMS_DIR . '/lib/Parsedown.php';
require_once CMS_DIR . '/lib/storage.php';
require_once CMS_DIR . '/lib/url.php';
require_once CMS_DIR . '/lib/html.php';
require_once CMS_DIR . '/lib/icons.php';
require_once CMS_DIR . '/lib/seo.php';
require_once CMS_DIR . '/lib/map.php';
require_once CMS_DIR . '/lib/sections.php';
require_once CMS_DIR . '/lib/packs.php';
require_once CMS_DIR . '/lib/styles.php';
if (is_file(CMS_SITE . '/inc/functions.php')) require_once CMS_SITE . '/inc/functions.php';
