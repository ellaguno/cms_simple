<?php
/**
 * Categorías (Diseño → Categorías): el registro de categorías de cada colección que las usa ('categories' en el tipo).
 * Nombre por idioma, URL (/coleccion/categoria/), orden, descripción y textos SEO; fusionar dos (mueve los elementos),
 * renombrar (corrige los elementos) y borrar. "Añadir las que faltan" registra las que aparezcan en los elementos.
 */
declare(strict_types=1);
$types = cms_categories_types();
$type = (string) ($_GET['type'] ?? ($types[0] ?? ''));
if (!in_array($type, $types, true)) $type = (string) ($types[0] ?? '');
$def = $type !== '' ? cms_type($type) : null;
$dl = cms_default_lang();
$langs = cms_langs();
$multi = count($langs) > 1;

if (admin_is_post() && $type !== '') {
    admin_csrf_check();
    $action = admin_post('action');
    $slug = cms_slugify(admin_post('slug'));
    $readLangs = function (string $prefix) use ($langs): array { $out = []; foreach ($langs as $l) $out[$l] = trim((string) ($_POST[$prefix][$l] ?? '')); return $out; };
    if ($action === 'add') {
        $labels = $readLangs('label');
        if ($labels[$dl] === '') admin_flash('Escribe el nombre de la categoría' . ($multi ? ' en ' . strtoupper($dl) : '') . '.', 'err');
        else {
            $new = cms_category_create($type, $labels[$dl]);
            if ($new === '') admin_flash('No se pudo crear la categoría.', 'err');
            else { cms_category_update($type, $new, ['label' => $labels]); admin_flash('Categoría "' . $labels[$dl] . '" creada (/' . cms_segment($def, $dl) . '/' . $new . '/).'); }
        }
    } elseif ($action === 'update') {
        $ok = cms_category_update($type, $slug, ['label' => $readLangs('label'), 'desc' => $readLangs('desc'), 'seo_title' => $readLangs('seo_title'), 'seo_desc' => $readLangs('seo_desc'), 'order' => (int) admin_post('order')]);
        admin_flash($ok ? 'Categoría guardada; los elementos que la usan quedaron actualizados.' : 'No se pudo guardar (el nombre' . ($multi ? ' en ' . strtoupper($dl) : '') . ' no puede quedar vacío).', $ok ? 'ok' : 'err');
    } elseif ($action === 'merge') {
        $into = cms_slugify(admin_post('into'));
        $n = cms_category_merge($type, $slug, $into);
        admin_flash($n || ($slug !== $into && cms_category($type, $into)) ? 'Fusionada: ' . $n . ' elemento(s) pasaron a "' . cms_category_label((array) cms_category($type, $into), $dl) . '".' : 'No se pudo fusionar.', 'ok');
    } elseif ($action === 'delete') {
        $n = cms_category_delete($type, $slug);
        admin_flash('Categoría eliminada' . ($n ? '; ' . $n . ' elemento(s) quedaron sin categoría' : '') . '.');
    } elseif ($action === 'rebuild') {
        $cats = cms_categories($type);
        $n = cms_categories_rebuild($type, $cats);
        cms_categories_save($type, $cats);
        admin_flash($n ? $n . ' categoría(s) añadidas desde los elementos.' : 'No faltaba ninguna: todas las que usan los elementos ya están registradas.');
    }
    admin_redirect(admin_url('categorias', ['type' => $type]));
}

admin_header('Categorías', 'categorias');
if ($type === ''): ?>
<p class="ad-help">Ninguna colección usa categorías. Para activarlas, en <code>site/config.php</code> añade <code>'categories' => true</code> al tipo (usa su campo <code>category</code>; con <code>['field' => 'otro']</code> eliges el campo). Las categorías se vuelven subsecciones con URL propia: <code>/coleccion/categoria/</code>.</p>
<?php admin_footer(); exit; endif;
$cats = cms_categories($type, true);
$counts = cms_categories_counts($type);
$seg = cms_segment($def, $dl);
?>
<p class="ad-help">Cada categoría es una subsección con su propia URL, indexable y presente en el mapa y el sitemap: <code>/<?= cms_e($seg) ?>/<em>categoria</em>/</code>. El editor elige de esta lista, así no aparecen "Derecho", "derecho" y "Derecho " como tres categorías. Renombrar corrige todos los elementos; fusionar los mueve.</p>
<?php if (count($types) > 1): ?>
<p class="ad-actions"><?php foreach ($types as $tk): ?><a class="ad-pill <?= $tk === $type ? 'on' : '' ?>" href="<?= admin_url('categorias', ['type' => $tk]) ?>"><?= cms_e(cms_config('types')[$tk]['label'] ?? $tk) ?></a> <?php endforeach; ?></p>
<?php endif; ?>
<div class="ad-grid2">
  <section class="ad-box" style="grid-column:1/-1">
    <h2><?= cms_e($def['label'] ?? $type) ?> <small class="ad-help"><?= count($cats) ?> categorías</small></h2>
<?php if (!$cats): ?><p class="ad-help">Aún no hay categorías. Añade la primera abajo, o pulsa "Añadir las que faltan" si los elementos ya traen alguna escrita.</p><?php else: ?>
    <table class="ad-table">
      <thead><tr><th>Orden</th><th>Nombre</th><th>URL</th><th>Elementos</th><th></th></tr></thead>
      <tbody>
<?php foreach ($cats as $slug => $c): $cnt = $counts[$slug] ?? ['total' => 0, 'live' => 0]; ?>
        <tr id="<?= cms_e((string) $slug) ?>">
          <td><?= (int) ($c['order'] ?? 0) ?></td>
          <td><strong><?= cms_e(cms_category_label($c, $dl)) ?></strong><?php if ($multi): foreach ($langs as $l): if ($l === $dl) continue; $x = trim((string) (($c['label'] ?? [])[$l] ?? '')); ?> <small class="ad-help"><?= strtoupper($l) ?>: <?= $x !== '' ? cms_e($x) : '—' ?></small><?php endforeach; endif; ?>
            <?php if (($d = cms_category_text($c, 'desc', $dl)) !== ''): ?><br><small class="ad-help"><?= cms_e(mb_strimwidth($d, 0, 90, '…')) ?></small><?php endif; ?></td>
          <td><a href="<?= cms_e(cms_url('cat:' . $type, $dl, (string) $slug)) ?>" target="_blank" rel="noopener"><small>/<?= cms_e($seg) ?>/<?= cms_e((string) $slug) ?>/</small></a></td>
          <td><?= (int) $cnt['total'] ?><?= $cnt['total'] !== $cnt['live'] ? ' <small class="ad-help">(' . (int) $cnt['live'] . ' publicados)</small>' : '' ?>
            <?php if ($cnt['total']): ?> <a class="ad-help" href="<?= admin_url('content', ['type' => $type, 'f_' . cms_categories_field($type) => cms_category_label($c, $dl)]) ?>">ver</a><?php endif; ?></td>
          <td class="ad-row-actions">
            <details class="ad-details">
              <summary class="ad-btn ad-btn-sm ad-btn-light">Editar</summary>
              <form method="post" class="ad-form" style="min-width:320px">
                <?= admin_csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="slug" value="<?= cms_e((string) $slug) ?>">
<?php foreach ($langs as $l): ?>
                <div class="ad-field"><label>Nombre<?= $multi ? ' ' . strtoupper($l) : '' ?></label><input type="text" name="label[<?= $l ?>]" value="<?= cms_e((string) (($c['label'] ?? [])[$l] ?? '')) ?>"<?= $l === $dl ? ' required' : '' ?>></div>
<?php endforeach; foreach ($langs as $l): ?>
                <div class="ad-field"><label>Descripción<?= $multi ? ' ' . strtoupper($l) : '' ?> <small class="ad-help">(se muestra bajo el título de la subsección, si el tema la usa)</small></label><textarea name="desc[<?= $l ?>]" rows="2"><?= cms_e((string) (($c['desc'] ?? [])[$l] ?? '')) ?></textarea></div>
<?php endforeach; foreach ($langs as $l): ?>
                <div class="ad-two"><div class="ad-field"><label>Título SEO<?= $multi ? ' ' . strtoupper($l) : '' ?></label><input type="text" name="seo_title[<?= $l ?>]" value="<?= cms_e((string) (($c['seo_title'] ?? [])[$l] ?? '')) ?>"></div>
                <div class="ad-field"><label>Descripción SEO<?= $multi ? ' ' . strtoupper($l) : '' ?></label><input type="text" name="seo_desc[<?= $l ?>]" value="<?= cms_e((string) (($c['seo_desc'] ?? [])[$l] ?? '')) ?>"></div></div>
<?php endforeach; ?>
                <div class="ad-field"><label>Orden</label><input type="number" name="order" value="<?= (int) ($c['order'] ?? 0) ?>" min="0" max="999"></div>
                <button class="ad-btn ad-btn-sm" type="submit">Guardar</button>
              </form>
            </details>
<?php if (count($cats) > 1): ?>
            <details class="ad-details">
              <summary class="ad-btn ad-btn-sm ad-btn-light" title="Mover sus elementos a otra categoría y borrar esta">Fusionar</summary>
              <form method="post" class="ad-inline-form" data-confirm="Los elementos de esta categoría pasarán a la elegida y esta desaparecerá. ¿Seguir?">
                <?= admin_csrf_field() ?><input type="hidden" name="action" value="merge"><input type="hidden" name="slug" value="<?= cms_e((string) $slug) ?>">
                <select name="into"><?php foreach ($cats as $s2 => $c2): if ($s2 === $slug) continue; ?><option value="<?= cms_e((string) $s2) ?>"><?= cms_e(cms_category_label($c2, $dl)) ?></option><?php endforeach; ?></select>
                <button class="ad-btn ad-btn-sm" type="submit">Fusionar en esta</button>
              </form>
            </details>
<?php endif; ?>
            <form method="post" class="ad-inline" data-confirm="<?= $cnt['total'] ? '¿Eliminar la categoría? Sus ' . (int) $cnt['total'] . ' elemento(s) quedarán sin categoría.' : '¿Eliminar la categoría?' ?>">
              <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?= cms_e((string) $slug) ?>">
              <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
    <p><form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="rebuild"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit" title="Registra las categorías que ya aparecen escritas en los elementos y aún no están en esta lista">Añadir las que faltan</button></form></p>
  </section>
  <section class="ad-box">
    <h2>Añadir categoría</h2>
    <form method="post" class="ad-form">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="add">
<?php foreach ($langs as $l): ?>
      <div class="ad-field"><label>Nombre<?= $multi ? ' ' . strtoupper($l) : '' ?></label><input type="text" name="label[<?= $l ?>]"<?= $l === $dl ? ' required' : '' ?> placeholder="<?= $l === 'en' ? 'Law' : 'Derecho' ?>"></div>
<?php endforeach; ?>
      <p class="ad-help">La URL se forma con el nombre<?= $multi ? ' en ' . strtoupper($dl) : '' ?>: "Derecho laboral" → <code>/<?= cms_e($seg) ?>/derecho-laboral/</code>. Si ya existe una con ese nombre, se usa esa.</p>
      <button class="ad-btn" type="submit">Crear categoría</button>
    </form>
  </section>
  <section class="ad-box">
    <h2>Cómo se usa</h2>
    <ul class="ad-list">
      <li>En el editor de cada elemento, el campo <strong><?= cms_e(admin_field_label(cms_categories_field($type), $def['fields'][cms_categories_field($type)] ?? [])) ?></strong> es un selector con esta lista y "+ Nueva categoría…".</li>
      <li>En el <a href="<?= admin_url('map') ?>">Mapa del sitio</a> cada categoría es una rama de la colección; desde ahí se crea un elemento ya asignado.</li>
      <li>Los enlaces viejos con <code>?cat=</code> redirigen (301) a la subsección. Para enlazarla desde el menú, usa su URL.</li>
      <li>En plantillas: <code>cms_url('cat:<?= cms_e($type) ?>', $lang, $slug)</code>, <code>cms_items_in_category()</code>, <code>cms_item_category()</code> y, en el índice, <code>$page['category']</code>.</li>
    </ul>
  </section>
</div>
<?php admin_footer();
