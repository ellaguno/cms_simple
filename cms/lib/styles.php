<?php
/**
 * cms_simple — variaciones de estilo del tema ("la piel" del sitio).
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
