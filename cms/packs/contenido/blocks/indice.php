<?php /** Índice del sitio (mapa HTML). $b: title, subtitle, source, group, sort, columns, limit, tree, pages, show_date, show_count, empty */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$groupBy = in_array($b['group'], ['type', 'year', 'letter', 'category', 'none'], true) ? (string) $b['group'] : 'type';
$sortBy = in_array($b['sort'], ['default', 'title', 'date'], true) ? (string) $b['sort'] : 'default';
$limit = max(0, (int) $b['limit']);
$en = $lang === 'en';
$types = [];
// "Todas" salta las colecciones marcadas 'noindex' (fuera del sitemap: planes, equipo…); elegida una a mano, se lista aunque lo sea
foreach ((array) cms_config('types', []) as $k => $d) if ($b['source'] === $k || ($b['source'] === 'all' && empty($d['noindex']))) $types[$k] = $d + ['key' => $k];
$groups = [];   // clave => ['label', 'url', 'items' => [[title, url, date, slug, parent, type]], 'tree' => bool]
$add = function (string $key, string $label, string $url, array $entry, bool $tree = false) use (&$groups) {
    if (!isset($groups[$key])) $groups[$key] = ['label' => $label, 'url' => $url, 'items' => [], 'tree' => $tree];
    $groups[$key]['items'][] = $entry;
};
if (cms_is_demo()) {   // ejemplo del manual o del selector
    $demo = ['Artículos' => ['Cómo elegir el color de una marca', 'Tres errores al escribir una portada', 'Qué mide una web que funciona', 'Guía corta de tipografía'], 'Proyectos' => ['Identidad para una cafetería', 'Catálogo de una editorial', 'Portal de una clínica'], 'Páginas' => ['Nosotros', 'Servicios', 'Contacto', 'Aviso de privacidad']];
    $i = 0;
    foreach ($demo as $g => $list) foreach ($list as $n => $t) $add($g, $g, '#', [$t, '#', date('Y-m-d', strtotime('-' . (($i++) * 11 + 2) . ' days')), 'demo-' . $i, '', $g]);
} else {
    if (!empty($b['pages']) && in_array($groupBy, ['type', 'none'], true)) {
        $S = cms_settings();
        $add('_pages', $en ? 'Sections' : 'Secciones', '', [(string) cms_t('home_meta_title', $lang, $S['site_name'] ?? cms_config('name')), cms_url('home', $lang), '', '', '', '']);
        foreach ((array) cms_config('pages', []) as $k => $d) if (empty($d['noindex'])) $add('_pages', $en ? 'Sections' : 'Secciones', '', [(string) cms_t($k . '_title', $lang, $d['label'] ?? $k), cms_url('page:' . $k, $lang), '', '', '', '']);
        foreach ((array) cms_config('types', []) as $k => $d) if (empty($d['no_list'])) $add('_pages', $en ? 'Sections' : 'Secciones', '', [(string) cms_t($k . '_title', $lang, $d['label'] ?? $k), cms_url('list:' . $k, $lang), '', '', '', '']);
    }
    foreach ($types as $k => $d) {
        $label = (string) cms_t($k . '_title', $lang, $d['label'] ?? $k);
        $listUrl = empty($d['no_list']) ? cms_url('list:' . $k, $lang) : '';
        $tf = $d['title_field'] ?? 'title';
        foreach (cms_items($k) as $it) {   // del índice ligero: sin cuerpo ni secciones
            if (cms_is_home_item($k, $it['slug'])) continue;
            $title = (string) (cms_f($it, $tf, $lang) ?: $it['slug']);
            $date = (string) ($it['date'] ?? $it['updated'] ?? '');
            $entry = [$title, cms_url('item:' . $k, $lang, $it['slug']), $date, (string) $it['slug'], (string) ($it['parent'] ?? ''), $k];
            if ($groupBy === 'type') $add($k, $label, $listUrl, $entry, !empty($d['tree']) && !empty($b['tree']));
            elseif ($groupBy === 'none') $add('_all', '', '', $entry, !empty($d['tree']) && !empty($b['tree']) && count($types) === 1);
            elseif ($groupBy === 'year') { $y = preg_match('/^\d{4}/', $date, $m) ? $m[0] : ($en ? 'Undated' : 'Sin fecha'); $add($y, $y, '', $entry); }
            elseif ($groupBy === 'letter') { $l = mb_strtoupper(mb_substr(strtr(mb_strtolower($title), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']), 0, 1)); if (!preg_match('/^[A-ZÑ]$/u', $l)) $l = '#'; $add($l, $l, '', $entry); }
            else { $c = cms_f($it, 'category', $lang); $c = is_array($c) ? (string) reset($c) : (string) $c; if ($c === '') $c = $en ? 'Uncategorized' : 'Sin categoría'; $add($c, $c, '', $entry); }
        }
    }
    if ($groupBy === 'year') krsort($groups, SORT_STRING);
    elseif ($groupBy === 'letter' || $groupBy === 'category') { uksort($groups, fn($a, $b2) => $a === '#' ? 1 : ($b2 === '#' ? -1 : strcoll((string) $a, (string) $b2))); }
}
if ($sortBy !== 'default') foreach ($groups as &$g) {
    if ($g['tree']) continue;   // el árbol conserva el orden del tipo
    usort($g['items'], $sortBy === 'title' ? fn($a, $b2) => strcoll(mb_strtolower($a[0]), mb_strtolower($b2[0])) : fn($a, $b2) => strcmp($b2[2], $a[2]));
}
unset($g);
$groups = array_filter($groups, fn($g) => $g['items']);
if (!$groups) {
    if (trim((string) $b['empty']) === '') return;
    echo '<div class="' . cms_e(cms_block_class('container')) . '"><p class="ct-empty">' . cms_e($b['empty']) . '</p></div>';
    return;
}
/** Una lista <ul> (anidada por 'parent' si el grupo es en árbol). */
$list = function (array $items, bool $tree, string $parent = '', int $depth = 0) use (&$list, $b, $lang, $limit): string {
    $rows = $tree ? array_filter($items, fn($e) => $e[4] === $parent) : $items;
    if ($tree && $parent === '') { $known = array_column($items, 3); $rows = array_filter($items, fn($e) => $e[4] === '' || !in_array($e[4], $known, true)); }   // huérfanos (padre en borrador) a la raíz
    if ($limit > 0 && $depth === 0) $rows = array_slice($rows, 0, $limit);
    if (!$rows) return '';
    $h = '<ul class="ct-index-list">';
    foreach ($rows as $e) {
        $h .= '<li><a href="' . cms_e($e[1]) . '">' . cms_e($e[0]) . '</a>';
        if (!empty($b['show_date']) && $e[2] !== '') $h .= ' <time datetime="' . cms_e(substr($e[2], 0, 10)) . '">' . cms_e(ct_date_short($e[2], $lang)) . '</time>';
        if ($tree && $depth < 12) $h .= $list($items, true, $e[3], $depth + 1);
        $h .= '</li>';
    }
    return $h . '</ul>';
};
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <nav class="ct-index ct-index-cols-<?= (int) $b['columns'] ?>" aria-label="<?= $en ? 'Site index' : 'Índice del sitio' ?>">
<?php foreach ($groups as $g): $n = count($g['items']); ?>
                <section class="ct-index-group">
<?php if ($g['label'] !== ''): ?>
                    <h3><?= $g['url'] !== '' ? '<a href="' . cms_e($g['url']) . '">' . cms_e($g['label']) . '</a>' : cms_e($g['label']) ?><?php if (!empty($b['show_count'])): ?> <span class="ct-index-count"><?= $n ?></span><?php endif; ?></h3>
<?php endif; ?>
                    <?= $list($g['items'], $g['tree']) ?>
<?php if ($limit > 0 && $n > $limit && $g['url'] !== ''): ?>
                    <p class="ct-index-more"><a href="<?= cms_e($g['url']) ?>"><?= $en ? 'See all' : 'Ver todos' ?> (<?= $n ?>) →</a></p>
<?php endif; ?>
                </section>
<?php endforeach; ?>
            </nav>
        </div>
