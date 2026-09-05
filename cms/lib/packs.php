<?php
/**
 * cms_simple — paquetes de bloques y efectos, y librerías abiertas.
 *
 * Un paquete es una carpeta autocontenida en cms/packs/<nombre>/ (compartida por todos los sitios) o site/packs/<nombre>/:
 *   pack.php       manifiesto: ['label', 'version', 'desc', 'assets' => ['css' => [...], 'js' => [...]] (siempre que se use algo del paquete),
 *                  'effects' => ['clave' => ['label', 'desc', 'assets' => [...], 'libs' => [...]]]]
 *   blocks.php     definiciones de bloques como en site/blocks.php; cada bloque puede traer 'assets' y 'libs'
 *   blocks/*.php   vistas;  assets/  CSS y JS;  LICENSES.md
 * El tema los activa en config: 'packs' => ['visual', 'motion'] (o ['motion' => ['site' => ['cursor']]] con opciones).
 * Los bloques de un paquete se llaman paquete/bloque; los efectos, paquete/efecto. Los recursos (CSS, JS y librerías)
 * se cargan solo en las páginas cuyas secciones los usan (cms_sections_assets()).
 */
declare(strict_types=1);

/** Librerías abiertas conocidas: nombre => versión, licencia, archivos y global que definen. */
function cms_libs(): array
{
    static $libs = null;
    if ($libs !== null) return $libs;
    $libs = [
        'gsap'          => ['version' => '3.13.0', 'license' => 'GreenSock Standard (gratuita, uso comercial incluido)', 'global' => 'gsap',
                            'js' => ['https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/gsap.min.js']],
        'scrolltrigger' => ['version' => '3.13.0', 'license' => 'GreenSock Standard', 'global' => 'ScrollTrigger', 'requires' => ['gsap'],
                            'js' => ['https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/ScrollTrigger.min.js']],
        'scrollsmoother' => ['version' => '3.13.0', 'license' => 'GreenSock Standard', 'global' => 'ScrollSmoother', 'requires' => ['gsap', 'scrolltrigger'],
                            'js' => ['https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/ScrollSmoother.min.js']],
        'splittext'     => ['version' => '3.13.0', 'license' => 'GreenSock Standard', 'global' => 'SplitText', 'requires' => ['gsap'],
                            'js' => ['https://cdnjs.cloudflare.com/ajax/libs/gsap/3.13.0/SplitText.min.js']],
        'three'         => ['version' => 'r128', 'license' => 'MIT', 'global' => 'THREE',
                            'js' => ['https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js']],
        'swiper'        => ['version' => '11', 'license' => 'MIT', 'global' => 'Swiper',
                            'css' => ['https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css'], 'js' => ['https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js']],
        'glightbox'     => ['version' => '3.3.0', 'license' => 'MIT', 'global' => 'GLightbox',
                            'css' => ['https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css'], 'js' => ['https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js']],
        'aos'           => ['version' => '2.3.1', 'license' => 'MIT', 'global' => 'AOS',
                            'css' => ['https://unpkg.com/aos@2.3.1/dist/aos.css'], 'js' => ['https://unpkg.com/aos@2.3.1/dist/aos.js']],
    ];
    $extra = (array) cms_config('libs', []);
    foreach ($extra as $k => $d) $libs[$k] = (array) $d + ($libs[$k] ?? []);
    return $libs;
}

/** Paquetes activos: nombre => manifiesto + 'dir', 'url', 'options'. */
function cms_packs(): array
{
    static $packs = null;
    if ($packs !== null) return $packs;
    $packs = [];
    foreach ((array) cms_config('packs', []) as $k => $v) {
        $name = is_int($k) ? (string) $v : (string) $k;
        $opts = is_int($k) ? [] : (array) $v;
        if (!preg_match('/^[a-z0-9_-]+$/i', $name)) continue;
        foreach ([[CMS_SITE . '/packs/' . $name, CMS_BASE . '/site/packs/' . $name], [CMS_DIR . '/packs/' . $name, CMS_BASE . '/cms/packs/' . $name]] as [$dir, $url]) {
            if (!is_file($dir . '/pack.php')) continue;
            $m = (array) require $dir . '/pack.php';
            $packs[$name] = $m + ['name' => $name, 'label' => ucfirst($name), 'dir' => $dir, 'url' => $url, 'options' => $opts, 'assets' => [], 'effects' => []];
            break;
        }
    }
    return $packs;
}

/** Ruta pública de un recurso de un paquete (relativa al paquete, o URL absoluta tal cual). */
function cms_pack_asset(array $pack, string $path): string
{
    if (preg_match('#^(https?:)?//#', $path)) return $path;
    $v = @filemtime($pack['dir'] . '/' . $path);
    return $pack['url'] . '/' . ltrim($path, '/') . ($v ? '?v=' . $v : '');
}

/** Bloques que aportan los paquetes: 'paquete/bloque' => definición (con 'file', 'pack', 'assets', 'libs'). */
function cms_pack_blocks(): array
{
    static $b = null;
    if ($b !== null) return $b;
    $b = [];
    foreach (cms_packs() as $name => $p) {
        if (!is_file($p['dir'] . '/blocks.php')) continue;
        foreach ((array) require $p['dir'] . '/blocks.php' as $k => $d) {
            $d = (array) $d;
            $d['key'] = $name . '/' . $k;
            $d['pack'] = $name;
            $d['file'] = $p['dir'] . '/blocks/' . preg_replace('/[^a-z0-9_-]/i', '', (string) $k) . '.php';
            $d['group'] = $d['group'] ?? ($p['label'] ?? ucfirst($name));
            $d += ['label' => ucfirst((string) $k), 'fields' => []];
            $b[$d['key']] = $d;
        }
    }
    return $b;
}

/** Efectos que aportan los paquetes: 'paquete/efecto' => definición. */
function cms_effects(): array
{
    static $e = null;
    if ($e !== null) return $e;
    $e = [];
    foreach (cms_packs() as $name => $p) {
        foreach ((array) ($p['effects'] ?? []) as $k => $d) $e[$name . '/' . $k] = (array) $d + ['key' => $name . '/' . $k, 'pack' => $name, 'label' => ucfirst((string) $k)];
    }
    return $e;
}

/** Recursos que necesita una lista de secciones: ['css' => [urls], 'js' => [urls], 'libs' => [nombres]]. Incluye los del sitio (config packs → 'site'). */
function cms_sections_assets(array $sections): array
{
    $css = []; $js = []; $libs = []; $packsUsed = [];
    $blocks = cms_blocks(); $effects = cms_effects(); $packs = cms_packs();
    $add = function (array $def, ?array $pack) use (&$css, &$js, &$libs) {
        foreach ((array) ($def['libs'] ?? []) as $l) $libs[] = (string) $l;
        foreach ((array) ($def['assets']['css'] ?? []) as $f) $css[] = $pack ? cms_pack_asset($pack, (string) $f) : (string) $f;
        foreach ((array) ($def['assets']['js'] ?? []) as $f) $js[] = $pack ? cms_pack_asset($pack, (string) $f) : (string) $f;
    };
    foreach ($sections as $sec) {
        if (!is_array($sec) || !empty($sec['hidden'])) continue;
        $def = cms_block((string) ($sec['type'] ?? ''));
        if ($def) {
            $pack = isset($def['pack']) ? ($packs[$def['pack']] ?? null) : null;
            if ($pack) $packsUsed[$def['pack']] = true;
            $add($def, $pack);
            foreach ((array) ($def['effects'] ?? []) as $ek) if (isset($effects[$ek])) { $packsUsed[$effects[$ek]['pack']] = true; $add($effects[$ek], $packs[$effects[$ek]['pack']] ?? null); }
        }
        foreach (cms_section_effect_list((array) ($sec['style'] ?? [])) as $ek) if (isset($effects[$ek])) { $packsUsed[$effects[$ek]['pack']] = true; $add($effects[$ek], $packs[$effects[$ek]['pack']] ?? null); }
    }
    // efectos de todo el sitio (config 'packs' => ['motion' => ['site' => ['cursor']]])
    foreach ($packs as $name => $p) foreach ((array) ($p['options']['site'] ?? []) as $ek) {
        $key = strpos((string) $ek, '/') === false ? $name . '/' . $ek : (string) $ek;
        if (isset($effects[$key])) { $packsUsed[$name] = true; $add($effects[$key], $p); }
    }
    foreach (array_keys($packsUsed) as $name) $add(['assets' => $packs[$name]['assets'] ?? []], $packs[$name]);
    // librerías con sus dependencias, en orden
    $all = cms_libs(); $ordered = [];
    $push = function (string $l) use (&$push, &$ordered, $all) { if (!isset($all[$l]) || in_array($l, $ordered, true)) return; foreach ((array) ($all[$l]['requires'] ?? []) as $r) $push($r); $ordered[] = $l; };
    foreach (array_unique($libs) as $l) $push($l);
    return ['css' => array_values(array_unique($css)), 'js' => array_values(array_unique($js)), 'libs' => $ordered];
}

/** Efectos elegidos en la pestaña Estilo (campo 'effect', separados por espacio). */
function cms_section_effect_list(array $style): array
{
    $v = (string) ($style['effect'] ?? '');
    return array_values(array_filter(array_map('trim', explode(' ', $v))));
}

/** Efectos que aplican a todo el sitio (para data-effect en <body> o inicialización global). */
function cms_site_effects(): array
{
    $out = [];
    foreach (cms_packs() as $name => $p) foreach ((array) ($p['options']['site'] ?? []) as $ek) $out[] = strpos((string) $ek, '/') === false ? $name . '/' . $ek : (string) $ek;
    return $out;
}

/** Etiquetas del <head> para los recursos de la página: configuración de librerías, cargador, CSS y JS de paquetes. */
function cms_assets_head(array $page): string
{
    $sections = (array) ($page['sections'] ?? []);
    $need = cms_sections_assets($sections);
    if (!$need['css'] && !$need['js'] && !$need['libs'] && !cms_packs()) return '';
    $libs = [];
    foreach (cms_libs() as $k => $d) $libs[$k] = ['css' => array_values((array) ($d['css'] ?? [])), 'js' => array_values((array) ($d['js'] ?? [])), 'global' => $d['global'] ?? null, 'requires' => array_values((array) ($d['requires'] ?? []))];
    $h = '<script>window.CMS = window.CMS || {}; CMS.base = ' . json_encode(CMS_BASE) . '; CMS.libs = ' . json_encode($libs, JSON_UNESCAPED_SLASHES) . '; CMS.libsNow = ' . json_encode($need['libs']) . '; CMS.siteEffects = ' . json_encode(cms_site_effects()) . ';</script>' . "\n";
    $h .= '<link rel="stylesheet" href="' . CMS_BASE . '/cms/assets/cms.css?v=' . CMS_VERSION . '">' . "\n";
    $h .= '<script src="' . CMS_BASE . '/cms/assets/cms.js?v=' . CMS_VERSION . '"></script>' . "\n";
    foreach ($need['css'] as $u) $h .= '<link rel="stylesheet" href="' . cms_e($u) . '">' . "\n";
    // las librerías marcadas se cargan por el cargador (en orden y una sola vez); los JS de los bloques van con defer
    foreach ($need['js'] as $u) $h .= '<script defer src="' . cms_e($u) . '"></script>' . "\n";
    return $h;
}
