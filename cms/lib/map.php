<?php
/**
 * cms_simple — mapa del sitio: árbol calculado a partir de la configuración, el contenido, el menú y las carpetas.
 *
 * Nodo: ['kind' => home|page|type|item|static|external, 'label', 'url' (relativa, con CMS_BASE), 'route',
 *        'status' => published|draft|scheduled|'', 'noindex' => bool, 'source' => texto, 'updated' => 'AAAA-MM-DD',
 *        'edit' => url del panel o '', 'children' => [nodos], 'type' => clave del tipo, 'slug']
 */
declare(strict_types=1);

/** Carpetas del sitio fuera del CMS (tienen index.php/index.html y no son del núcleo). */
function cms_static_dirs(): array
{
    $skip = ['cms', 'site', 'data', 'uploads', 'admin', 'api', 'tools', 'cache', 'vendor', 'node_modules'];
    $out = [];
    foreach (glob(CMS_ROOT . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        $n = basename($d);
        if ($n[0] === '.' || in_array($n, $skip, true)) continue;
        if (is_file($d . '/index.php') || is_file($d . '/index.html')) $out[] = $n;
    }
    return $out;
}

function cms_map_item_status(array $it): string
{
    if (($it['status'] ?? 'draft') !== 'published') return 'draft';
    if (!empty($it['publish_at']) && $it['publish_at'] > date('Y-m-d')) return 'scheduled';
    return 'published';
}

function cms_map_item_node(string $type, array $def, array $it, string $lang): array
{
    return [
        'kind' => 'item', 'type' => $type, 'slug' => $it['slug'],
        'label' => (string) (cms_f($it, $def['title_field'] ?? 'title', $lang) ?: $it['slug']),
        'url' => cms_url('item:' . $type, $lang, $it['slug']),
        'route' => 'item:' . $type,
        'status' => cms_map_item_status($it),
        'noindex' => !empty($def['noindex']),
        'source' => 'JSON · ' . ($def['template_single'] ?? rtrim($type, 's')) . '.php',
        'updated' => (string) ($it['updated'] ?? $it['date'] ?? ''),
        'edit' => ADMIN_URL . '/?p=edit&type=' . rawurlencode($type) . '&slug=' . rawurlencode($it['slug']),
        'children' => [],
    ];
}

/** Elementos de un tipo en árbol (tipos con 'tree': por parent) o planos. */
function cms_map_type_children(string $type, array $def, string $lang): array
{
    $items = cms_items($type, false);
    $nodes = [];
    foreach ($items as $it) $nodes[$it['slug']] = cms_map_item_node($type, $def, $it, $lang);
    if (empty($def['tree'])) return array_values($nodes);
    $roots = [];
    foreach ($items as $it) {
        $parent = (string) ($it['parent'] ?? '');
        if ($parent !== '' && isset($nodes[$parent]) && $parent !== $it['slug']) $nodes[$parent]['children'][] = &$nodes[$it['slug']];
        else $roots[] = &$nodes[$it['slug']];
    }
    return $roots;
}

/** Árbol completo del sitio para un idioma. */
function cms_site_map(string $lang): array
{
    $S = cms_settings();
    $t = fn(string $k, $d = '') => cms_t($k, $lang, $d);
    $root = ['kind' => 'home', 'label' => $t('home_meta_title', $S['site_name'] ?? cms_config('name')), 'url' => cms_url('home', $lang), 'route' => 'home',
        'status' => 'published', 'noindex' => false, 'source' => 'plantilla home.php', 'updated' => '', 'edit' => ADMIN_URL . '/?p=strings', 'children' => []];

    // páginas fijas
    foreach (cms_config('pages') as $k => $d) {
        $root['children'][] = ['kind' => 'page', 'label' => $t($k . '_title', $d['label'] ?? $k), 'url' => cms_url('page:' . $k, $lang), 'route' => 'page:' . $k,
            'status' => 'published', 'noindex' => !empty($d['noindex']), 'source' => 'plantilla ' . ($d['template'] ?? $k) . '.php', 'updated' => '',
            'edit' => ADMIN_URL . '/?p=strings', 'children' => []];
    }
    // tipos de contenido
    foreach (cms_config('types') as $k => $d) {
        $d += ['key' => $k];
        $children = cms_map_type_children($k, $d, $lang);
        $all = cms_items($k, false);
        $pub = count(array_filter($all, fn($i) => cms_map_item_status($i) === 'published'));
        $node = ['kind' => 'type', 'type' => $k, 'label' => $d['label'] ?? $k, 'route' => empty($d['no_list']) ? 'list:' . $k : '',
            'url' => empty($d['no_list']) ? cms_url('list:' . $k, $lang) : '', 'status' => '', 'noindex' => !empty($d['noindex']),
            'source' => (!empty($d['tree']) ? 'árbol · ' : 'colección · ') . (empty($d['no_list']) ? 'índice ' . ($d['template_list'] ?? $k) . '.php' : 'sin índice público'),
            'updated' => '', 'edit' => ADMIN_URL . '/?p=content&type=' . rawurlencode($k), 'children' => $children,
            'count' => count($all), 'count_pub' => $pub, 'segment' => cms_segment($d, $lang)];
        $root['children'][] = $node;
    }
    // carpetas fuera del CMS
    foreach (cms_static_dirs() as $dir) {
        $root['children'][] = ['kind' => 'static', 'label' => $dir . '/', 'url' => CMS_BASE . '/' . $dir . '/', 'route' => '', 'status' => 'published', 'noindex' => false,
            'source' => 'carpeta estática (fuera del CMS)', 'updated' => date('Y-m-d', (int) @filemtime(CMS_ROOT . '/' . $dir)), 'edit' => '', 'children' => []];
    }
    // enlaces externos del menú
    foreach (cms_menu($lang) as $m) {
        $u = (string) ($m['url'] ?? '');
        if (preg_match('#^https?://#i', $u) && strpos($u, cms_site_url()) !== 0) {
            $root['children'][] = ['kind' => 'external', 'label' => (string) ($m['label'] ?? $u), 'url' => $u, 'route' => '', 'status' => 'published', 'noindex' => false,
                'source' => 'enlace externo (menú)', 'updated' => '', 'edit' => ADMIN_URL . '/?p=menu', 'children' => []];
        }
    }
    return $root;
}

/** Cuenta nodos por estado en un subárbol. */
function cms_map_counts(array $node, array &$c = ['published' => 0, 'draft' => 0, 'scheduled' => 0]): array
{
    if ($node['kind'] === 'item') $c[$node['status']] = ($c[$node['status']] ?? 0) + 1;
    foreach ($node['children'] as $ch) cms_map_counts($ch, $c);
    return $c;
}
