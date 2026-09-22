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

const CMS_VERSION = '1.36.5';

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
/** Firma de la vista previa de un tema (válida esta hora y la anterior); la emite Diseño → Vista previa. */
function cms_theme_preview_token(string $key, int $shift = 0): string
{
    $f = CMS_DATA . '/.secret';
    $secret = is_file($f) ? trim((string) file_get_contents($f)) : '';
    if ($secret === '') { $secret = bin2hex(random_bytes(24)); @mkdir(CMS_DATA, 0755, true); @file_put_contents($f, $secret); }
    return substr(hash_hmac('sha256', 'theme-preview/' . $key . '/' . date('YmdH', time() - $shift * 3600), $secret), 0, 24);
}
/** Clave del tema en vista previa (solo con firma válida), o ''. */
function cms_theme_preview(): string
{
    static $k = null;
    if ($k !== null) return $k;
    $k = '';
    $key = (string) ($_GET['cmstheme'] ?? ''); $tok = (string) ($_GET['cmstoken'] ?? '');
    if ($key !== '' && preg_match('/^[a-z0-9_-]+$/i', $key) && $tok !== '' && (hash_equals(cms_theme_preview_token($key), $tok) || hash_equals(cms_theme_preview_token($key, 1), $tok))) $k = $key;
    return $k;
}
/** Parámetros que mantienen la vista previa del tema al navegar ('' si no hay vista previa). */
function cms_theme_preview_qs(): string
{
    $k = cms_theme_preview();
    return $k === '' ? '' : 'cmstheme=' . rawurlencode($k) . '&cmstoken=' . rawurlencode((string) $_GET['cmstoken']);
}

function cms_active_theme_dir(): string
{
    $legacy = CMS_ROOT . '/site';
    $ok = fn(string $d) => is_dir($d) && (is_file($d . '/config.php') || is_file($d . '/theme.json'));
    $pv = cms_theme_preview();
    if ($pv !== '') { $d = $pv === 'site' ? $legacy : CMS_THEMES . '/' . $pv; if ($ok($d)) return $d; }
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

/**
 * Temas hijos (1.35): un tema puede declarar en theme.json "parent": "lienzo" y aportar solo lo que cambia (CSS,
 * config, algunos bloques o plantillas, contenido inicial). Lo que no tenga se toma del padre: layout, plantillas,
 * bloques, assets, defaults y variaciones. El padre vive en themes/<clave> (o es site/ si se llama "site").
 */
function cms_theme_parent_dir(): string
{
    static $p = null;
    if ($p !== null) return $p;
    $p = '';
    $j = is_file(CMS_SITE . '/theme.json') ? json_decode((string) @file_get_contents(CMS_SITE . '/theme.json'), true) : null;
    $k = is_array($j) ? (string) ($j['parent'] ?? '') : '';
    if ($k === '' || !preg_match('/^[a-z0-9_-]+$/i', $k)) return $p;
    $dir = $k === 'site' ? CMS_ROOT . '/site' : CMS_THEMES . '/' . $k;
    if (!is_dir($dir) && $k !== 'site' && is_file(CMS_ROOT . '/site/theme.json')) {   // el padre puede ser el site/ clásico si su theme.json dice "key": "<clave>"
        $sj = json_decode((string) @file_get_contents(CMS_ROOT . '/site/theme.json'), true);
        if (is_array($sj) && strtolower((string) ($sj['key'] ?? '')) === strtolower($k)) $dir = CMS_ROOT . '/site';
    }
    if (is_dir($dir) && realpath($dir) !== realpath(CMS_SITE) && (is_file($dir . '/config.php') || is_file($dir . '/theme.json'))) $p = $dir;
    return $p;
}
define('CMS_SITE_PARENT', cms_theme_parent_dir());
/** URL pública de la carpeta del tema padre ('' si no hay). */
define('CMS_SITE_PARENT_BASE', CMS_SITE_PARENT !== '' ? CMS_BASE . '/' . trim(str_replace('\\', '/', substr(CMS_SITE_PARENT, strlen(CMS_ROOT))), '/') : '');

/** Ruta absoluta de un archivo del tema: el del tema activo si existe; si no, el del padre; si no, el del activo (inexistente). */
function cms_theme_file(string $rel): string
{
    $rel = ltrim($rel, '/');
    if (is_file(CMS_SITE . '/' . $rel) || CMS_SITE_PARENT === '') return CMS_SITE . '/' . $rel;
    return is_file(CMS_SITE_PARENT . '/' . $rel) ? CMS_SITE_PARENT . '/' . $rel : CMS_SITE . '/' . $rel;
}

/** Configuración del tema padre (para que config.php de un tema hijo la tome de base y cambie solo lo suyo). */
function cms_parent_config(): array
{
    return CMS_SITE_PARENT !== '' && is_file(CMS_SITE_PARENT . '/config.php') ? (array) require CMS_SITE_PARENT . '/config.php' : [];
}

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
        // cabeceras y pies del constructor (1.33): colección interna en todo sitio con bloques; 'layouts' => false la quita
        $builder = is_file(CMS_SITE . '/blocks.php') || (bool) array_filter($cfg['types'], fn($d) => (bool) array_filter((array) ($d['fields'] ?? []), fn($f) => ($f['type'] ?? '') === 'sections'));
        if (($cfg['layouts'] ?? true) !== false && $builder && !isset($cfg['types']['layouts'])) $cfg['types']['layouts'] = cms_layouts_type_def();
    }
    if ($key === null) return $cfg;
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

/** Definición de la colección interna "Cabeceras y pies" (Diseño → Cabeceras y pies; ver lib/layouts.php). */
function cms_layouts_type_def(): array
{
    return [
        'label' => 'Cabeceras y pies', 'label_singular' => 'Cabecera o pie', 'internal' => true, 'no_list' => true, 'noindex' => true,
        'routes' => ['es' => '_layout'], 'template_single' => '_layout', 'title_field' => 'title',
        'sort' => ['field' => 'title', 'dir' => 'asc'], 'list' => ['kind'],
        'help' => 'Cabeceras y pies hechos con bloques (paquete "estructura" o los del tema). Cada página elige los suyos en la barra lateral de su editor; los predeterminados se fijan en Ajustes → Cabecera y pie. El tema los dibuja con cms_layout_header() y cms_layout_footer().',
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Nombre', 'required' => true, 'placeholder' => 'Cabecera principal, Pie de la tienda…'],
            'kind'     => ['type' => 'select', 'label' => 'Qué es', 'sidebar' => true, 'options' => ['header' => 'Cabecera', 'footer' => 'Pie de página']],
            'sections' => ['type' => 'sections', 'label' => 'Bloques', 'help' => 'Una cabecera suele ser un solo bloque "Cabecera del sitio" (logotipo, menú y botón); un pie, un bloque "Pie de página". Puedes añadir más: una barra de aviso, una cinta de logotipos, un llamado a la acción antes del pie…'],
        ],
    ];
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

require_once CMS_DIR . '/lib/hooks.php';
require_once CMS_DIR . '/lib/Parsedown.php';
require_once CMS_DIR . '/lib/storage.php';
require_once CMS_DIR . '/lib/url.php';
require_once CMS_DIR . '/lib/html.php';
require_once CMS_DIR . '/lib/icons.php';
require_once CMS_DIR . '/lib/seo.php';
require_once CMS_DIR . '/lib/categories.php';
require_once CMS_DIR . '/lib/map.php';
require_once CMS_DIR . '/lib/sections.php';
require_once CMS_DIR . '/lib/layouts.php';
require_once CMS_DIR . '/lib/packs.php';
require_once CMS_DIR . '/lib/styles.php';
require_once CMS_DIR . '/lib/registry.php';
require_once CMS_DIR . '/lib/update.php';
if (CMS_SITE_PARENT !== '' && is_file(CMS_SITE_PARENT . '/inc/functions.php')) require_once CMS_SITE_PARENT . '/inc/functions.php';   // tema padre primero
if (is_file(CMS_SITE . '/inc/functions.php')) require_once CMS_SITE . '/inc/functions.php';
// paquetes con código: <paquete>/inc.php se carga una vez por petición si el paquete está activo (ganchos y helpers)
foreach (cms_packs() as $cms_pack) if (is_file($cms_pack['dir'] . '/inc.php')) require_once $cms_pack['dir'] . '/inc.php';
unset($cms_pack);
