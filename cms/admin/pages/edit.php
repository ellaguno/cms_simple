<?php
/** Editor genérico de un elemento, guiado por el esquema del tipo. */
declare(strict_types=1);
$type = (string) ($_GET['type'] ?? '');
$def = cms_type($type);
if (!$def) { admin_flash('Tipo de contenido desconocido.', 'err'); admin_redirect(admin_url()); }
$fields = (array) ($def['fields'] ?? []);
$titleField = $def['title_field'] ?? 'title';
$dl = cms_default_lang();

$orig = cms_slugify((string) ($_GET['slug'] ?? ''));
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
}

$errors = [];
if (admin_is_post()) {
    admin_csrf_check();
    $new = ['slug' => '', 'status' => admin_post('status') === 'published' ? 'published' : 'draft'];
    foreach ($fields as $name => $fd) $new[$name] = admin_read_field($name, $fd);
    $titleVal = $new[$titleField] ?? '';
    $titleMain = is_array($titleVal) ? ($titleVal[$dl] ?? '') : (string) $titleVal;
    $slug = cms_slugify(admin_post('slug') ?: $titleMain);
    if ($titleMain === '') $errors[] = 'El campo "' . admin_field_label($titleField, $fields[$titleField] ?? []) . '" es obligatorio' . (count(cms_langs()) > 1 ? ' en ' . strtoupper($dl) : '') . '.';
    foreach ($fields as $name => $fd) {
        if (empty($fd['required']) || $name === $titleField) continue;
        $v = $new[$name]; $v = is_array($v) && !isset($v[0]) ? ($v[$dl] ?? '') : $v;
        if ($v === '' || $v === []) $errors[] = 'El campo "' . admin_field_label($name, $fd) . '" es obligatorio.';
    }
    if ($slug === '') $errors[] = 'No se pudo generar la URL (slug).';
    if ($slug && $slug !== $orig && is_file(cms_content_dir($type) . '/' . $slug . '.json')) $errors[] = 'Ya existe un elemento con la URL "' . $slug . '".';
    $new['slug'] = $slug;
    $new['seo_title'] = admin_read_field('seo_title', ['type' => 'text', 'i18n' => true]);
    $new['seo_desc'] = admin_read_field('seo_desc', ['type' => 'textarea', 'i18n' => true]);
    $new['created'] = $item['created'] ?? date('Y-m-d');
    $new['updated'] = date('Y-m-d');
    $item = $new + $item;
    if (!$errors) {
        if (cms_item_save($type, $item)) {
            if ($orig && $orig !== $slug) cms_item_delete($type, $orig);
            admin_flash('Guardado.');
            admin_redirect(admin_url('edit', ['type' => $type, 'slug' => $slug]));
        }
        $errors[] = 'No se pudo escribir en data/content/' . $type . '/. Revisa permisos.';
    }
}

$main = array_filter($fields, fn($f) => empty($f['sidebar']));
$side = array_filter($fields, fn($f) => !empty($f['sidebar']));
$singular = $def['label_singular'] ?? 'Elemento';
admin_header(($is_new ? 'Nuevo: ' : 'Editar: ') . $singular, 'content:' . $type);
foreach ($errors as $e) echo '<div class="ad-flash err">' . cms_e($e) . '</div>';
$titleInputName = !empty($fields[$titleField]['i18n']) ? $titleField . '[' . $dl . ']' : $titleField;
?>
<form method="post" class="ad-form ad-form-wide" data-slug-source="<?= cms_e($titleInputName) ?>">
  <?= admin_csrf_field() ?>
  <?php admin_lang_switch(); ?>
  <div class="ad-grid-main">
    <div>
<?php foreach ($main as $name => $fd) admin_field($name, $fd, $item[$name] ?? ''); ?>
    </div>
    <aside class="ad-sidebar">
      <?php admin_seo_fields($item); ?>
      <div class="ad-field"><label>Estado</label>
        <select name="status"><option value="draft"<?= $item['status'] !== 'published' ? ' selected' : '' ?>>Borrador</option><option value="published"<?= $item['status'] === 'published' ? ' selected' : '' ?>>Publicado</option></select></div>
      <div class="ad-field"><label>URL (slug)</label><input type="text" name="slug" value="<?= cms_e($item['slug']) ?>" data-slug placeholder="se genera del título"><p class="ad-help">/<?= cms_e(cms_segment($def, $dl)) ?>/<span data-slug-preview><?= cms_e($item['slug']) ?></span></p></div>
<?php foreach ($side as $name => $fd) admin_field($name, $fd, $item[$name] ?? ''); ?>
      <div class="ad-field ad-sticky-save">
        <button class="ad-btn" type="submit">Guardar</button>
        <a class="ad-btn ad-btn-light" href="<?= admin_url('content', ['type' => $type]) ?>">Volver</a>
<?php if (!$is_new): foreach (cms_active_langs() as $l): ?>        <a class="ad-btn ad-btn-light" href="<?= cms_url('item:' . $type, $l, $item['slug']) ?>" target="_blank" rel="noopener">Ver <?= strtoupper($l) ?></a>
<?php endforeach; endif; ?>
      </div>
<?php if (!$is_new): ?>      <p class="ad-help">Creado: <?= cms_e($item['created'] ?? '—') ?> · Actualizado: <?= cms_e($item['updated'] ?? '—') ?></p><?php endif; ?>
    </aside>
  </div>
</form>
<?php admin_footer();
