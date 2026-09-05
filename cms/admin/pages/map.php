<?php
/** Mapa del sitio: árbol de todo lo publicado y en borrador, con origen y acceso a editar. */
declare(strict_types=1);

$lang = (string) ($_GET['lang'] ?? cms_default_lang());
if (!in_array($lang, cms_langs(), true)) $lang = cms_default_lang();

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action'); $type = admin_post('type'); $slug = cms_slugify(admin_post('slug'));
    $def = cms_type($type); $it = $def ? cms_item($type, $slug, false) : null;
    if ($it && $action === 'move' && !empty($def['tree'])) {
        // mover bajo otra página (o a la raíz); nunca bajo sí misma ni bajo un descendiente
        $parent = cms_slugify(admin_post('parent'));
        $all = cms_items($type, false); $ok = $parent === '' || (isset($all[$parent]) && $parent !== $slug);
        $p = $parent; $n = 0; while ($ok && $p !== '' && $n++ < 30) { if ($p === $slug) $ok = false; $p = (string) ($all[$p]['parent'] ?? ''); }
        if ($ok && $parent === '' && in_array($slug, cms_reserved_segments(), true)) { $ok = false; admin_flash('La URL "' . $slug . '" está reservada en la raíz.', 'err'); }
        if ($ok) { $it['parent'] = $parent; $it['updated'] = date('Y-m-d'); cms_item_save($type, $it); cms_tree_rebuild($type); admin_flash('Página movida.'); }
        elseif (!$ok && $parent !== '') admin_flash('No se puede mover una página dentro de sí misma.', 'err');
    } elseif ($it && $action === 'order') {
        // subir/bajar entre hermanos: renumera 'order' de los hermanos
        $dir = admin_post('dir') === 'up' ? -1 : 1;
        $siblings = array_values(array_filter(cms_items($type, false), fn($x) => (string) ($x['parent'] ?? '') === (string) ($it['parent'] ?? '')));
        $i = array_search($slug, array_column($siblings, 'slug'), true);
        if ($i !== false) {
            if (isset($siblings[$i + $dir])) [$siblings[$i], $siblings[$i + $dir]] = [$siblings[$i + $dir], $siblings[$i]];
            foreach ($siblings as $k => $sb) if (($sb['order'] ?? null) !== $k + 1) { $sb['order'] = $k + 1; cms_item_save($type, $sb); }
            admin_flash('Orden actualizado.');
        }
    } elseif ($it && $action === 'status') {
        $it['status'] = admin_post('status') === 'published' ? 'published' : 'draft'; $it['updated'] = date('Y-m-d');
        cms_item_save($type, $it); admin_flash($it['status'] === 'published' ? 'Publicado.' : 'Pasado a borrador.');
    } elseif ($action === 'menu') {
        $label = admin_post('label'); $url = admin_post('url');
        if ($label !== '' && $url !== '') {
            $m = cms_json_read(CMS_DATA . '/menu.json', []); if (!$m && is_file(CMS_SITE . '/defaults/menu.json')) $m = cms_json_read(CMS_SITE . '/defaults/menu.json');
            $m[$lang] = (array) ($m[$lang] ?? []);
            if (!in_array($url, array_column($m[$lang], 'url'), true)) { $m[$lang][] = ['label' => $label, 'url' => $url]; cms_json_write(CMS_DATA . '/menu.json', $m); admin_flash('Añadido al menú.'); }
            else admin_flash('Ese enlace ya está en el menú.');
        }
    }
    admin_redirect(admin_url('map', ['lang' => $lang]));
}

$map = cms_site_map($lang);
$menuUrls = array_column(cms_menu($lang), 'url');
$treeTypes = array_keys(array_filter(cms_config('types'), fn($d) => !empty($d['tree'])));
$counts = cms_map_counts($map);
$icons = ['home' => '⌂', 'page' => '▭', 'type' => '▤', 'item' => '·', 'static' => '▣', 'external' => '↗'];
$statusLabel = ['published' => 'publicado', 'draft' => 'borrador', 'scheduled' => 'programado'];

function admin_map_node(array $n, array $icons, array $statusLabel, int $depth = 0, array $ctx = []): void
{
    $has = !empty($n['children']);
    $open = $depth < 1 || $n['kind'] === 'type' && count($n['children']) <= 12;
    $types = cms_config('types');
    $isTreeItem = $n['kind'] === 'item' && !empty($types[$n['type']]['tree']);
    $isTreeType = $n['kind'] === 'type' && !empty($types[$n['type']]['tree']);
    $drag = $isTreeItem ? ' draggable="true" data-map-drag="' . cms_e($n['type'] . ':' . $n['slug']) . '"' : '';
    $drop = $isTreeItem ? ' data-map-drop="' . cms_e($n['type'] . ':' . $n['slug']) . '"' : ($isTreeType ? ' data-map-drop="' . cms_e($n['type'] . ':') . '"' : '');
    echo '<li class="ad-map-node ad-map-' . cms_e($n['kind']) . '"' . $drag . $drop . '>';
    if ($has) echo '<details' . ($open ? ' open' : '') . '><summary class="ad-map-row">';
    else echo '<div class="ad-map-row">';
    echo '<span class="ad-map-icon" aria-hidden="true">' . $icons[$n['kind']] . '</span>';
    echo '<span class="ad-map-label">' . cms_e($n['label']);
    if ($n['kind'] === 'type') echo ' <small class="ad-help">' . (int) ($n['count_pub'] ?? 0) . '/' . (int) ($n['count'] ?? 0) . ' publicados</small>';
    echo '</span>';
    if ($n['url'] !== '') echo '<a class="ad-map-url" href="' . cms_e($n['url']) . '" target="_blank" rel="noopener">' . cms_e(preg_replace('#^https?://[^/]+#', '', $n['url'])) . '</a>';
    elseif ($n['kind'] === 'type') echo '<span class="ad-map-url ad-help">/' . cms_e($n['segment'] ?? '') . '/…</span>';
    if ($n['status'] !== '' && $n['kind'] === 'item') echo '<span class="ad-pill ' . ($n['status'] === 'published' ? 'on' : ($n['status'] === 'scheduled' ? 'warn' : '')) . '">' . $statusLabel[$n['status']] . '</span>';
    if ($n['noindex']) echo '<span class="ad-pill" title="No aparece en buscadores ni en el sitemap">noindex</span>';
    echo '<span class="ad-map-source">' . cms_e($n['source']) . ($n['updated'] ? ' · ' . cms_e($n['updated']) : '') . '</span>';
    // acciones
    echo '<span class="ad-map-actions">';
    if ($n['edit'] !== '') echo '<a class="ad-btn ad-btn-sm ad-btn-light" href="' . cms_e($n['edit']) . '">Editar</a>';
    if ($n['kind'] === 'item' || $isTreeType || $n['kind'] === 'home' && $ctx['treeTypes']) {
        echo '<details class="ad-map-menu"><summary class="ad-btn ad-btn-sm ad-btn-light" title="Más acciones">⋯</summary><div class="ad-map-menu-box">';
        $f = fn(string $action, array $fields, string $label, string $confirm = '') => '<form method="post"' . ($confirm ? ' data-confirm="' . cms_e($confirm) . '"' : '') . '>' . admin_csrf_field() . '<input type="hidden" name="action" value="' . $action . '">' . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . cms_e($k) . '" value="' . cms_e($v) . '">', array_keys($fields), $fields)) . '<button type="submit">' . cms_e($label) . '</button></form>';
        if ($n['kind'] === 'home') foreach ($ctx['treeTypes'] as $tt) echo '<a href="' . admin_url('edit', ['type' => $tt]) . '">+ Nueva ' . cms_e(mb_strtolower($types[$tt]['label_singular'] ?? 'página')) . ' en la raíz</a>';
        if ($isTreeType) echo '<a href="' . admin_url('edit', ['type' => $n['type']]) . '">+ Nueva ' . cms_e(mb_strtolower($types[$n['type']]['label_singular'] ?? 'página')) . '</a>';
        if ($isTreeItem) {
            echo '<a href="' . admin_url('edit', ['type' => $n['type'], 'parent' => $n['slug']]) . '">+ Nueva página hija</a>';
            echo $f('order', ['type' => $n['type'], 'slug' => $n['slug'], 'dir' => 'up'], '↑ Subir entre hermanas') . $f('order', ['type' => $n['type'], 'slug' => $n['slug'], 'dir' => 'down'], '↓ Bajar entre hermanas');
            echo '<span class="ad-help">Para mover bajo otra página: arrástrala sobre ella (o sobre la colección para la raíz).</span>';
        }
        if ($n['kind'] === 'item') {
            echo $n['status'] === 'draft' ? $f('status', ['type' => $n['type'], 'slug' => $n['slug'], 'status' => 'published'], '● Publicar') : $f('status', ['type' => $n['type'], 'slug' => $n['slug'], 'status' => 'draft'], '○ Pasar a borrador');
            $rel = preg_replace('#^https?://[^/]+#', '', $n['url']);
            if (CMS_BASE !== '' && strpos($rel, CMS_BASE) === 0) $rel = substr($rel, strlen(CMS_BASE));
            if (!in_array($rel, $ctx['menuUrls'], true) && !in_array(rtrim($rel, '/') . '/', $ctx['menuUrls'], true)) echo $f('menu', ['label' => $n['label'], 'url' => $rel], '☰ Añadir al menú');
            else echo '<span class="ad-help">Ya está en el menú</span>';
        }
        echo '</div></details>';
    }
    echo '</span>';
    if ($has) {
        echo '</summary><ul class="ad-map-children">';
        foreach ($n['children'] as $c) admin_map_node($c, $icons, $statusLabel, $depth + 1, $ctx);
        echo '</ul></details>';
    } else echo '</div>';
    echo '</li>';
}

admin_header('Mapa del sitio', 'map');
?>
<p class="ad-help">Todo lo que responde en el sitio, de dónde sale cada cosa y en qué estado está. Las ramas se pliegan y despliegan. Con ⋯ creas páginas hijas, publicas, ordenas o añades al menú; arrastra una página sobre otra para moverla.
<?php if (count(cms_langs()) > 1): ?> Idioma: <?php foreach (cms_active_langs() as $l): ?><a href="<?= admin_url('map', ['lang' => $l]) ?>"<?= $l === $lang ? ' class="on"' : '' ?>><?= strtoupper($l) ?></a> <?php endforeach; endif; ?></p>
<div class="ad-map-legend">
  <span><span class="ad-pill on">publicado</span> <?= (int) $counts['published'] ?></span>
  <span><span class="ad-pill">borrador</span> <?= (int) $counts['draft'] ?></span>
  <span><span class="ad-pill warn">programado</span> <?= (int) $counts['scheduled'] ?></span>
  <span class="ad-help">▭ plantilla fija · ▤ colección de contenido · ▣ carpeta fuera del CMS · ↗ enlace externo</span>
  <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= CMS_BASE ?>/sitemap.xml" target="_blank" rel="noopener">sitemap.xml ↗</a>
</div>
<ul class="ad-map">
<?php admin_map_node($map, $icons, $statusLabel, 0, ['menuUrls' => $menuUrls, 'treeTypes' => $treeTypes]); ?>
</ul>
<form method="post" id="map-move" hidden><?= admin_csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="type"><input type="hidden" name="slug"><input type="hidden" name="parent"></form>
<?php admin_footer();
