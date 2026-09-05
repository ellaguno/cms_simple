<?php
/** Editor genérico de un elemento, guiado por el esquema del tipo. */
declare(strict_types=1);
$type = (string) ($_GET['type'] ?? '');
$def = cms_type($type);
if (!$def) { admin_flash('Tipo de contenido desconocido.', 'err'); admin_redirect(admin_url()); }
$fields = (array) ($def['fields'] ?? []);
$titleField = $def['title_field'] ?? 'title';
$dl = cms_default_lang();
$tree = !empty($def['tree']);

$orig = cms_slugify((string) ($_GET['slug'] ?? ''));
if ($tree) {
    // selector de página padre: todas las del tipo menos la propia y sus descendientes
    $all = cms_items($type, false);
    $opts = ['' => '— Raíz del sitio —'];
    $isDesc = function (string $slug) use ($all, $orig): bool { $n = 0; while ($slug !== '' && $n++ < 20) { if ($slug === $orig) return true; $slug = (string) ($all[$slug]['parent'] ?? ''); } return false; };
    $paths = [];
    foreach ($all as $sl => $it) $paths[$sl] = $it['path'] ?? $sl;
    asort($paths);
    foreach ($paths as $sl => $pth) if ($sl !== $orig && !$isDesc($sl)) $opts[$sl] = str_repeat('· ', substr_count($pth, '/')) . cms_f($all[$sl], $titleField, $dl) . '  (/' . $pth . ')';
    $fields = ['parent' => ['type' => 'select', 'label' => 'Página padre', 'sidebar' => true, 'options' => $opts, 'help' => 'La URL se forma con la ruta del padre + la URL de esta página.']] + $fields;
}
$item = $orig ? cms_item($type, $orig, false) : null;
$is_new = !$item;
if ($orig && !$item) { admin_flash('El elemento no existe.', 'err'); admin_redirect(admin_url('content', ['type' => $type])); }
if ($is_new) {
    $item = ['slug' => '', 'status' => 'draft'];
    foreach ($fields as $name => $fd) {
        $d = $fd['default'] ?? (($fd['type'] ?? '') === 'date' ? date('Y-m-d') : '');
        if ($name === 'order' && ($fd['type'] ?? '') === 'number' && !isset($fd['default'])) $d = count(cms_items($type, false)) + 1;
        $item[$name] = !empty($fd['i18n']) ? array_fill_keys(cms_langs(), $d) : $d;
    }
    if ($tree && isset($_GET['parent'])) $item['parent'] = cms_slugify((string) $_GET['parent']);
}

$errors = [];
if (admin_is_post() && admin_post('action') === 'restore' && !$is_new) {
    admin_csrf_check();
    $vf = cms_versions_dir($type, $orig) . '/' . preg_replace('/[^0-9-]/', '', admin_post('version')) . '.json';
    $old = is_file($vf) ? json_decode((string) file_get_contents($vf), true) : null;
    if (is_array($old) && !empty($old['slug'])) {
        $old['slug'] = $orig; $old['updated'] = date('Y-m-d');
        if (cms_item_save($type, $old)) { admin_flash('Versión restaurada (la que estaba quedó guardada como versión).'); admin_redirect(admin_url('edit', ['type' => $type, 'slug' => $orig])); }
    }
    admin_flash('No se pudo restaurar esa versión.', 'err');
    admin_redirect(admin_url('edit', ['type' => $type, 'slug' => $orig]));
}
if (admin_is_post()) {
    admin_csrf_check();
    [$item, $errors] = admin_read_item($type, $def, $fields, $item, $orig);
    $slug = $item['slug'];
    if (!$errors) {
        if ($tree) { $all2 = cms_items($type, false); $all2[$slug] = $item; $item['path'] = cms_tree_path($type, $all2, $slug); }
        if (cms_item_save($type, $item)) {
            if ($orig && $orig !== $slug) {
                cms_item_delete($type, $orig);
                if (is_dir(cms_versions_dir($type, $orig)) && !is_dir(cms_versions_dir($type, $slug))) @rename(cms_versions_dir($type, $orig), cms_versions_dir($type, $slug));
                if ($tree) foreach (cms_items($type, false) as $ch) if (($ch['parent'] ?? '') === $orig) { $ch['parent'] = $slug; cms_json_write(cms_content_dir($type) . '/' . $ch['slug'] . '.json', $ch); }
            }
            if ($tree) cms_tree_rebuild($type);
            admin_flash('Guardado.');
            admin_redirect(admin_url('edit', ['type' => $type, 'slug' => $slug]));
        }
        $errors[] = 'No se pudo escribir en data/content/' . $type . '/. Revisa permisos.';
    }
}

$main = array_filter($fields, fn($f) => empty($f['sidebar']));
$side = array_filter($fields, fn($f) => !empty($f['sidebar']));
$builder = (bool) array_filter($fields, fn($f) => ($f['type'] ?? '') === 'sections');
$singular = $def['label_singular'] ?? 'Elemento';
admin_header(($is_new ? 'Nuevo: ' : 'Editar: ') . $singular, 'content:' . $type);
foreach ($errors as $e) echo '<div class="ad-flash err">' . cms_e($e) . '</div>';
$titleInputName = !empty($fields[$titleField]['i18n']) ? $titleField . '[' . $dl . ']' : $titleField;
?>
<form method="post" class="ad-form ad-form-wide<?= $builder ? ' ad-builder' : '' ?>" data-slug-source="<?= cms_e($titleInputName) ?>"<?= $builder ? ' data-builder' : '' ?>>
  <?= admin_csrf_field() ?>
  <?php admin_lang_switch(); ?>
<?php if ($builder): ?>
  <div class="ad-builder-bar">
    <span class="ad-help">Vista previa en vivo: se actualiza sola al editar. Clic en una sección de la vista previa para abrirla aquí.</span>
    <span class="ad-builder-devices"><button type="button" class="on" data-device="desktop" title="Escritorio">▭</button><button type="button" data-device="tablet" title="Tableta">▯</button><button type="button" data-device="mobile" title="Móvil">▮</button></span>
    <span class="ad-builder-devices"><button type="button" data-toggle-panel="nav" title="Ocultar o mostrar el menú del panel">◧ Menú</button><button type="button" data-toggle-panel="side" title="Ocultar o mostrar la columna de ajustes de la página">◨ Ajustes</button></span>
    <button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-preview-refresh>Actualizar vista previa</button>
    <button type="submit" class="ad-btn ad-btn-sm">Guardar</button>
    <button type="submit" class="ad-btn ad-btn-sm" formaction="<?= admin_url('preview', ['type' => $type, 'slug' => $orig]) ?>" formtarget="cms-preview" formnovalidate hidden data-preview-submit>Vista previa</button>
  </div>
<?php endif; ?>
  <div class="ad-grid-main">
    <div class="ad-form-main">
<?php foreach ($main as $name => $fd) admin_field($name, $fd, $item[$name] ?? ''); ?>
    </div>
<?php if ($builder): ?>
    <div class="ad-builder-preview"><iframe name="cms-preview" class="ad-preview-frame" title="Vista previa" data-preview-frame src="about:blank"></iframe></div>
<?php endif; ?>
    <aside class="ad-sidebar">
      <?php admin_seo_fields($item); ?>
      <div class="ad-field"><label>Estado</label>
        <select name="status"><option value="draft"<?= $item['status'] !== 'published' ? ' selected' : '' ?>>Borrador</option><option value="published"<?= $item['status'] === 'published' ? ' selected' : '' ?>>Publicado</option></select></div>
      <div class="ad-field"><label>Publicar a partir de <small class="ad-help">(vacío = de inmediato)</small></label><input type="date" name="publish_at" value="<?= cms_e($item['publish_at'] ?? '') ?>" min="<?= date('Y-m-d', time() + 86400) ?>"></div>
      <div class="ad-field"><label>URL (slug)</label><input type="text" name="slug" value="<?= cms_e($item['slug']) ?>" data-slug placeholder="se genera del título"><p class="ad-help"><?php if ($tree): $pp = ($item['parent'] ?? '') !== '' ? (cms_items($type, false)[$item['parent']]['path'] ?? $item['parent']) . '/' : ''; $sg = cms_segment($def, $dl); ?>/<?= $sg !== '' ? cms_e($sg) . '/' : '' ?><span data-parent-path><?= cms_e($pp) ?></span><?php else: ?>/<?= cms_e(cms_segment($def, $dl)) ?>/<?php endif; ?><span data-slug-preview><?= cms_e($item['slug']) ?></span></p></div>
<?php foreach ($side as $name => $fd) admin_field($name, $fd, $item[$name] ?? ''); ?>
      <div class="ad-field ad-sticky-save">
        <button class="ad-btn" type="submit">Guardar</button>
        <a class="ad-btn ad-btn-light" href="<?= admin_url('content', ['type' => $type]) ?>">Volver</a>
<?php if (!$is_new): foreach (cms_active_langs() as $l): ?>        <a class="ad-btn ad-btn-light" href="<?= cms_e(cms_item_url($type, $item, $l)) ?>" target="_blank" rel="noopener"><?= cms_item_is_live($item) ? 'Ver' : 'Vista previa' ?> <?= strtoupper($l) ?></a>
<?php endforeach; endif; ?>
      </div>
<?php if (!$is_new): ?>      <p class="ad-help">Creado: <?= cms_e($item['created'] ?? '—') ?> · Actualizado: <?= cms_e($item['updated'] ?? '—') ?></p>
<?php $versions = cms_item_versions($type, $item['slug']); if ($versions): ?>
      <details class="ad-versions"><summary>Versiones anteriores (<?= count($versions) ?>)</summary>
        <ul class="ad-list">
<?php foreach ($versions as $v): ?>
          <li><span><?= cms_e($v['when']) ?> <small class="ad-help"><?= cms_e($v['status'] === 'published' ? 'publicado' : 'borrador') ?></small></span>
            <button class="ad-btn ad-btn-sm ad-btn-light" type="submit" form="restore-<?= cms_e($v['name']) ?>" data-confirm="¿Restaurar la versión del <?= cms_e($v['when']) ?>? La versión actual quedará guardada.">Restaurar</button></li>
<?php endforeach; ?>
        </ul>
      </details>
<?php endif; endif; ?>
<?php if (!empty($item['import']) && is_array($item['import'])): $imp = $item['import']; ?>
      <details class="ad-versions ad-import-ref"><summary>Diseño importado<?= !empty($imp['source']) ? ' · ' . cms_e($imp['source']) : '' ?></summary>
<?php if (!empty($imp['screens'])): ?>        <div class="ad-import-screens"><?php foreach ((array) $imp['screens'] as $i => $sc): ?><a href="<?= cms_e(cms_img($sc)) ?>" target="_blank" rel="noopener" title="Pantalla <?= $i + 1 ?>"><img src="<?= cms_e(cms_img($sc)) ?>" alt="Pantalla <?= $i + 1 ?>" loading="lazy"></a><?php endforeach; ?></div>
<?php endif; if (!empty($imp['notes'])): ?>        <p class="ad-help"><strong>Notas del análisis</strong></p><ul class="ad-list ad-help"><?php foreach ((array) $imp['notes'] as $n): ?><li><?= cms_e($n) ?></li><?php endforeach; ?></ul>
<?php endif; if (!empty($imp['unmapped'])): ?>        <p class="ad-help"><strong>Sin bloque equivalente</strong></p><ul class="ad-list ad-help"><?php foreach ((array) $imp['unmapped'] as $n): ?><li><?= cms_e($n) ?></li><?php endforeach; ?></ul>
<?php endif; if (!empty($imp['palette'])): ?>        <p class="ad-help"><strong>Paleta:</strong> <?php foreach ((array) $imp['palette'] as $k => $c): ?><span class="ad-pill" style="border-left:12px solid <?= cms_e((string) $c) ?>"><?= cms_e($k) ?> <?= cms_e((string) $c) ?></span> <?php endforeach; ?></p>
<?php endif; ?>
      </details>
<?php endif; ?>
    </aside>
  </div>
</form>
<?php if (!$is_new) foreach (cms_item_versions($type, $item['slug']) as $v): ?>
<form method="post" id="restore-<?= cms_e($v['name']) ?>" class="ad-inline" data-confirm="¿Restaurar la versión del <?= cms_e($v['when']) ?>? La versión actual quedará guardada."><?= admin_csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="version" value="<?= cms_e($v['name']) ?>"></form>
<?php endforeach; ?>
<?php admin_footer();
