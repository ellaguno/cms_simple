<?php
/**
 * cms_simple — enrutador público.
 * Rutas (por idioma, con prefijo /xx para los no predeterminados):
 *   /                          → plantilla "home" o, con config 'home_item' => [tipo, slug], ese elemento (constructor)
 *   /{segmento-tipo}/          → plantilla de listado del tipo (template_list)
 *   /{segmento-tipo}/{slug}    → plantilla de detalle (template_single)
 *   /{segmento-página}         → páginas estáticas de config 'pages'
 *   /{ruta/completa}           → tipos en árbol ('tree' => true; con 'routes' vacío cuelgan de la raíz)
 *   /sitemap.xml  /robots.txt  /llms.txt (site/llms.txt, si existe)  /_cms/form (POST del formulario de contacto)
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once CMS_SITE . '/inc/layout.php';

// ---- ruta solicitada
if (isset($_GET['p'])) {
    $path = (string) $_GET['p'];
} else {
    $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (CMS_BASE !== '' && strpos($path, CMS_BASE) === 0) $path = substr($path, strlen(CMS_BASE));
    $path = preg_replace('#^/index\.php#', '', $path);
}
$path = trim(rawurldecode($path), '/');

if ($path !== '' && ($to = cms_redirect_for($path)) !== null) { header('Location: ' . $to, true, 301); exit; }
if ($path === 'robots.txt') { cms_robots(); exit; }
if ($path === 'sitemap.xml') { cms_sitemap(); exit; }
if ($path === 'llms.txt' && is_file(CMS_SITE . '/llms.txt')) { header('Content-Type: text/plain; charset=utf-8'); echo str_replace('{{site}}', cms_site_url(), (string) file_get_contents(CMS_SITE . '/llms.txt')); exit; }
if ($path === '_cms/form') { require CMS_DIR . '/form.php'; exit; }

// ---- idioma
$lang = cms_default_lang();
foreach (cms_langs() as $l) {
    if ($l === cms_default_lang()) continue;
    if ($path === $l || strpos($path, $l . '/') === 0) { $lang = $l; $path = trim(substr($path, strlen($l)), '/'); break; }
}
if (!in_array($lang, cms_active_langs(), true)) { header('Location: ' . cms_url('home', cms_default_lang()), true, 302); exit; }

$S = cms_settings();
$site = $S['site_name'] ?? cms_config('name');
$t = fn(string $k, $d = '') => cms_t($k, $lang, $d);
$seg = $path === '' ? [] : explode('/', $path);

$alt = function (string $route, ?string $slug = null): array {
    $out = [];
    foreach (cms_active_langs() as $l) $out[$l] = cms_url($route, $l, $slug);
    return $out;
};
$home_crumb = [$t('crumb_home', $lang === 'en' ? 'Home' : 'Inicio'), cms_url('home', $lang)];

/** Borrador o programado con token de vista previa válido (?preview=…). */
function cms_preview_item(string $type, string $slug): ?array
{
    $tok = (string) ($_GET['preview'] ?? '');
    if ($tok === '') return null;
    $it = cms_item($type, $slug, false);
    if (!$it || cms_item_is_live($it)) return null;
    return hash_equals(cms_preview_token($type, $slug), $tok) ? $it : null;
}

$page = ['lang' => $lang, 'path' => $path, 'route' => '404'];
$template = null;
$item = null;
$type = null;
$def = null;

if ($seg === []) {
    $template = 'home';
    $page += ['title' => $t('home_meta_title', $site), 'desc' => $t('home_meta_desc'), 'alt' => $alt('home')];
    $page['route'] = 'home';
    $website = ['@type' => 'WebSite', '@id' => cms_site_url() . '/#website', 'url' => cms_site_url() . '/', 'name' => $site,
        'inLanguage' => $lang === 'en' ? 'en' : $lang . '-MX', 'publisher' => ['@id' => cms_site_url() . '/#organization']];
    $page['jsonld'] = [cms_jsonld_graph(cms_jsonld_org(), $website)];
    // portada del constructor: config 'home_item' => [tipo, slug]
    $hi = cms_config('home_item');
    if (is_array($hi) && ($def = cms_type((string) $hi[0]))) {
        $type = (string) $hi[0];
        $item = cms_item($type, (string) $hi[1]) ?: cms_preview_item($type, (string) $hi[1]);
        if ($item) {
            $template = $def['template_single'] ?? rtrim($type, 's');
            $page['title'] = cms_f($item, 'seo_title', $lang) ?: $t('home_meta_title', $site);
            $page['desc'] = cms_f($item, 'seo_desc', $lang) ?: ((string) cms_f($item, $def['excerpt_field'] ?? 'excerpt', $lang) ?: $t('home_meta_desc'));
            if (!empty($item[$def['image_field'] ?? 'image'])) $page['og_image'] = $item[$def['image_field'] ?? 'image'];
            if (!cms_item_is_live($item)) { $page['noindex'] = true; $page['preview'] = true; }
        } else { $type = null; $def = null; }
    }
} else {
    // tipos de contenido
    foreach (cms_config('types') as $k => $d) {
        $d += ['key' => $k];
        if ($seg[0] !== cms_segment($d, $lang)) continue;
        if (count($seg) === 1 && empty($d['no_list'])) {
            $template = $d['template_list'] ?? $k;
            $type = $k; $def = $d;
            $filtered = ($_GET['q'] ?? '') !== '' || ($_GET['tag'] ?? '') !== '' || ($_GET['cat'] ?? '') !== '';
            $label = $t($k . '_title', $d['label'] ?? $k);
            $page += ['title' => $t($k . '_meta_title', $label) . ' · ' . $site, 'desc' => $t($k . '_meta_desc'), 'alt' => $alt('list:' . $k), 'noindex' => $filtered];
            $page['route'] = 'list:' . $k;
            $page['jsonld'] = [cms_jsonld_graph(cms_jsonld_org(), cms_jsonld_breadcrumbs([$home_crumb, [$label, cms_url('list:' . $k, $lang)]]))];
        } elseif (count($seg) === 2 && (($item = cms_item($k, $seg[1])) || ($item = cms_preview_item($k, $seg[1])))) {
            $template = $d['template_single'] ?? rtrim($k, 's');
            $type = $k; $def = $d;
            $title = (string) cms_f($item, $d['title_field'] ?? 'title', $lang);
            $url = cms_url('item:' . $k, $lang, $item['slug']);
            $label = $t($k . '_title', $d['label'] ?? $k);
            $page += ['slug' => $item['slug'],
                'title' => cms_f($item, 'seo_title', $lang) ?: $title . ' · ' . $site,
                'desc' => cms_f($item, 'seo_desc', $lang) ?: (string) cms_f($item, $d['excerpt_field'] ?? 'excerpt', $lang),
                'alt' => $alt('item:' . $k, $item['slug']),
                'og_image' => $item[$d['image_field'] ?? 'image'] ?? '',
                'og_type' => in_array($d['schema'] ?? '', ['Article', 'BlogPosting', 'NewsArticle'], true) ? 'article' : 'website',
                'noindex' => !empty($d['noindex'])];
            $page['route'] = 'item:' . $k;
            if (!cms_item_is_live($item)) { $page['noindex'] = true; $page['preview'] = true; }
            $page['jsonld'] = [cms_jsonld_graph(cms_jsonld_org(), cms_jsonld_breadcrumbs([$home_crumb, [$label, cms_url('list:' . $k, $lang)], [$title, $url]]), cms_jsonld_item($d, $item, $lang, $url))];
        }
        break;
    }
    // páginas estáticas
    if ($template === null && count($seg) === 1) {
        foreach (cms_config('pages') as $k => $d) {
            $d += ['key' => $k];
            if ($seg[0] !== cms_segment($d, $lang)) continue;
            $template = $d['template'] ?? $k;
            $label = $t($k . '_title', $d['label'] ?? $k);
            $page += ['title' => $t($k . '_meta_title', $label) . ' · ' . $site, 'desc' => $t($k . '_meta_desc'), 'alt' => $alt('page:' . $k), 'noindex' => !empty($d['noindex'])];
            $page['route'] = 'page:' . $k;
            $page['jsonld'] = [cms_jsonld_graph(cms_jsonld_org(), cms_jsonld_breadcrumbs([$home_crumb, [$label, cms_url('page:' . $k, $lang)]]),
                !empty($d['schema']) ? ['@type' => $d['schema'], 'url' => cms_abs_url(cms_url('page:' . $k, $lang)), 'name' => $label, 'mainEntity' => ['@id' => cms_site_url() . '/#organization']] : null)];
            break;
        }
    }
}

// tipos en árbol: /ruta/completa (raíz) o /segmento/ruta/completa
if ($template === null && $seg !== []) {
    foreach (cms_config('types') as $k => $d) {
        if (empty($d['tree'])) continue;
        $d += ['key' => $k];
        $tseg = cms_segment($d, $lang);
        $rel = $path;
        if ($tseg !== '') { if (strpos($path, $tseg . '/') !== 0) continue; $rel = substr($path, strlen($tseg) + 1); }
        $item = cms_tree_item($k, $rel);
        if ($item && cms_is_home_item($k, $item['slug'])) { header('Location: ' . cms_url('home', $lang), true, 301); exit; }
        if (!$item && ($_GET['preview'] ?? '') !== '') { $cand = cms_tree_item($k, $rel, false); if ($cand && !cms_item_is_live($cand) && hash_equals(cms_preview_token($k, $cand['slug']), (string) $_GET['preview'])) $item = $cand; }
        if (!$item) continue;
        $template = $d['template_single'] ?? rtrim($k, 's');
        $type = $k; $def = $d;
        $title = (string) cms_f($item, $d['title_field'] ?? 'title', $lang);
        $url = cms_url('item:' . $k, $lang, $item['slug']);
        $crumbs = [$home_crumb];
        foreach (cms_tree_ancestors($k, $item, false) as $a) $crumbs[] = [(string) cms_f($a, $d['title_field'] ?? 'title', $lang), cms_url('item:' . $k, $lang, $a['slug'])];
        $crumbs[] = [$title, $url];
        $page += ['slug' => $item['slug'], 'title' => cms_f($item, 'seo_title', $lang) ?: $title . ' · ' . $site,
            'desc' => cms_f($item, 'seo_desc', $lang) ?: (string) cms_f($item, $d['excerpt_field'] ?? 'excerpt', $lang),
            'alt' => $alt('item:' . $k, $item['slug']), 'og_image' => $item[$d['image_field'] ?? 'image'] ?? '', 'og_type' => 'website',
            'noindex' => !empty($d['noindex']) || !cms_item_is_live($item), 'crumbs' => $crumbs];
        $page['route'] = 'item:' . $k;
        if (!cms_item_is_live($item)) $page['preview'] = true;
        $page['jsonld'] = [cms_jsonld_graph(cms_jsonld_org(), cms_jsonld_breadcrumbs($crumbs), cms_jsonld_item($d, $item, $lang, $url))];
        break;
    }
}

if ($template === null || !is_file(CMS_SITE . '/templates/' . $template . '.php')) {
    http_response_code(404);
    $template = '404';
    $page += ['title' => $t('not_found_title', '404') . ' · ' . $site, 'desc' => '', 'alt' => $alt('home'), 'noindex' => true];
}
$page['canonical'] = cms_abs_url($page['alt'][$lang] ?? cms_url('home', $lang));
if (is_array($item ?? null) && is_array($item['sections'] ?? null)) $page['sections'] = $item['sections'];

site_header($page);
require CMS_SITE . '/templates/' . $template . '.php';
site_footer($page);
