<?php
/**
 * cms_simple — temas del sitio y variaciones de estilo ("la piel").
 *
 * Un sitio puede tener varios temas en themes/<clave>/ (cada uno con lo que tiene site/: config.php, inc/, templates/,
 * blocks/, assets/) y elegir el activo en Admin → Diseño; queda en Ajustes ('theme') y lo resuelve CMS_SITE al arrancar.
 * Un sitio con un solo tema sigue usando site/ sin más, como siempre.
 *
 * Variaciones de estilo del tema activo:
 *
 * Un tema declara variaciones en <tema>/styles/<clave>.json:
 *   {
 *     "label": "Editorial",
 *     "desc": "Serif clásica, mucho aire, esquinas rectas",
 *     "default": true,                      (opcional: la que se usa si no se ha elegido ninguna)
 *     "fonts": ["Lora", "Inter"],           (familias de Google Fonts que necesita)
 *     "vars": { "--lz-radius": "0px", … },  (variables CSS que se aplican a :root)
 *     "settings": { "color_accent": "#8b1e2d", "font": "Inter" }   (valores sugeridos para Ajustes → Diseño)
 *   }
 * La variación activa se guarda en Ajustes ('style'). El núcleo emite sus variables y sus fuentes en el <head>,
 * así que funciona con cualquier tema sin tocarlo. Los ajustes manuales del usuario se emiten después y mandan.
 */
declare(strict_types=1);

/** Variaciones del tema activo: clave => definición (con 'key'). */
function cms_styles(): array
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    foreach (glob(CMS_SITE . '/styles/*.json') ?: [] as $f) {
        $k = basename($f, '.json');
        if (!preg_match('/^[a-z0-9_-]+$/i', $k)) continue;
        $d = cms_json_read($f, null);
        if (!is_array($d)) continue;
        $out[$k] = $d + ['key' => $k, 'label' => ucfirst($k), 'desc' => '', 'vars' => [], 'fonts' => [], 'settings' => []];
    }
    uasort($out, fn($a, $b) => (int) ($b['default'] ?? false) <=> (int) ($a['default'] ?? false));
    return $out;
}

/** Clave de la variación activa (Ajustes → 'style'), o la marcada por defecto, o ''. */
function cms_style_key(): string
{
    $all = cms_styles();
    if (!$all) return '';
    $over = (string) ($GLOBALS['cms_style_override'] ?? '');   // vista previa de una variación desde el panel
    if ($over !== '' && isset($all[$over])) return $over;
    $k = (string) (cms_settings()['style'] ?? '');
    if ($k !== '' && isset($all[$k])) return $k;
    foreach ($all as $key => $d) if (!empty($d['default'])) return (string) $key;
    return '';
}

/** Definición de la variación activa (o de la indicada). */
function cms_style(?string $key = null): array
{
    $all = cms_styles();
    $k = $key !== null && isset($all[$key]) ? $key : cms_style_key();
    return $k !== '' ? $all[$k] : [];
}

/** Nombre de familia tipográfica seguro para Google Fonts. */
function cms_font_name(string $f): string
{
    $f = trim(preg_replace('/[^A-Za-z0-9 ]/', '', $f) ?? '');
    return mb_substr($f, 0, 40);
}

/** Etiquetas del <head> de una variación: sus fuentes de Google y sus variables CSS. */
function cms_style_head(?string $key = null): string
{
    $st = cms_style($key);
    if (!$st) return '';
    $h = '';
    $fonts = array_values(array_filter(array_map('cms_font_name', (array) ($st['fonts'] ?? []))));
    if ($fonts) {
        $q = implode('&', array_map(fn($f) => 'family=' . rawurlencode($f) . ':wght@300;400;500;600;700;800', $fonts));
        $h .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?' . cms_e($q) . '&display=swap">' . "\n";
    }
    $css = '';
    foreach ((array) ($st['vars'] ?? []) as $name => $value) {
        if (!preg_match('/^--[a-z0-9_-]+$/i', (string) $name)) continue;
        $v = preg_replace('/[^A-Za-z0-9 #%.,()"\'\/_+-]/', '', (string) $value) ?? '';
        $v = trim(mb_substr($v, 0, 160));
        if ($v !== '') $css .= $name . ':' . $v . ';';
    }
    // :root:root gana al :root de la hoja del tema aunque se emita antes; los ajustes del usuario usan una más (:root:root:root).
    // En la vista previa del panel la variación se enseña tal cual, así que gana a todo.
    $sel = !empty($GLOBALS['cms_style_override']) ? ':root:root:root:root' : ':root:root';
    if ($css !== '') $h .= '<style id="cms-style">' . $sel . '{' . $css . '}</style>' . "\n";
    return $h;
}

/** Claves de Ajustes que gobiernan las variaciones de un tema (para poder devolverlas a su sitio al cambiar de tema). */
function cms_style_setting_keys(string $dir): array
{
    $keys = [];
    foreach (glob($dir . '/styles/*.json') ?: [] as $f) {
        $d = cms_json_read($f, null);
        if (is_array($d)) foreach ((array) ($d['settings'] ?? []) as $k => $v) if (preg_match('/^[a-z0-9_]+$/i', (string) $k)) $keys[$k] = true;
    }
    return array_keys($keys);
}

/* ------------------------------------------------------------------ temas instalados */

/** Ficha de un tema a partir de su carpeta. */
function cms_theme_info(string $dir, string $key): array
{
    $j = is_file($dir . '/theme.json') ? (cms_json_read($dir . '/theme.json', []) ?: []) : [];
    $shot = '';
    foreach (['screenshot.png', 'screenshot.jpg', 'screenshot.webp'] as $f) if (is_file($dir . '/' . $f)) { $shot = $f; break; }
    return [
        'key' => $key,
        'label' => (string) ($j['label'] ?? ucfirst($key)),
        'desc' => (string) ($j['desc'] ?? ''),
        'version' => (string) ($j['version'] ?? ''),
        'author' => (string) ($j['author'] ?? ''),
        'dir' => $dir,
        'url' => CMS_BASE . '/' . ($key === 'site' ? 'site' : 'themes/' . $key),
        'screenshot' => $shot,
        'styles' => count(glob($dir . '/styles/*.json') ?: []),
    ];
}

/** Temas instalados: 'site' (el clásico, si existe) y los de themes/. */
function cms_themes(): array
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    $ok = fn(string $d) => is_dir($d) && (is_file($d . '/config.php') || is_file($d . '/theme.json'));
    if ($ok(CMS_ROOT . '/site')) $out['site'] = cms_theme_info(CMS_ROOT . '/site', 'site');
    foreach (glob(CMS_THEMES . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        $k = basename($d);
        if (!preg_match('/^[a-z0-9_-]+$/i', $k) || isset($out[$k]) || !$ok($d)) continue;
        $out[$k] = cms_theme_info($d, $k);
    }
    return $out;
}

/** Clave del tema activo. */
function cms_theme_key(): string
{
    return CMS_SITE_REL === 'site' ? 'site' : basename(CMS_SITE);
}

/** Devuelve lo que un archivo PHP del tema exporta con return, en un ámbito aislado. */
function cms_php_array(string $file): array
{
    if (!is_file($file)) return [];
    try { $v = require $file; } catch (\Throwable $e) { return []; }
    return is_array($v) ? $v : [];
}

/** Tipos de contenido y bloques que declara un tema (sin activarlo), para avisar antes de cambiar. */
function cms_theme_contract(string $dir): array
{
    $cfg = cms_php_array($dir . '/config.php');
    return [
        'types' => array_keys((array) ($cfg['types'] ?? [])),
        'blocks' => array_keys(cms_php_array($dir . '/blocks.php')),
    ];
}

/** Tipos y bloques que el contenido usa hoy, para compararlos con los del tema destino. */
function cms_content_usage(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $types = [];
    foreach (cms_config('types') as $k => $d) if (glob(cms_content_dir($k) . '/*.json')) $types[] = (string) $k;
    $blocks = [];
    foreach ($types as $t) foreach (cms_items($t, false) as $it) foreach ((array) ($it['sections'] ?? []) as $sec) {
        $b = (string) ($sec['type'] ?? '');
        if ($b !== '' && strpos($b, '/') === false) $blocks[$b] = true;   // los de paquetes viajan con el núcleo
    }
    return $cache = ['types' => $types, 'blocks' => array_keys($blocks)];
}

/** Qué se perdería al cambiar a un tema: ['types' => [...], 'blocks' => [...]] con lo que el tema destino no trae. */
function cms_theme_warnings(string $dir): array
{
    $c = cms_theme_contract($dir);
    $u = cms_content_usage();
    if (!$c['types'] && !$c['blocks']) return ['types' => [], 'blocks' => [], 'unknown' => true];
    return [
        'types' => $c['types'] ? array_values(array_diff($u['types'], $c['types'])) : [],
        'blocks' => $c['blocks'] ? array_values(array_diff($u['blocks'], $c['blocks'])) : [],
        'unknown' => false,
    ];
}
