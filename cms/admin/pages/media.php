<?php
declare(strict_types=1);

// Listado en JSON para el selector "Biblioteca" de los formularios
if (isset($_GET['json'])) {
    header('Content-Type: application/json; charset=utf-8');
    $type = $_GET['type'] ?? '';
    $items = array_values(array_filter(media_list(), fn($m) => $type === '' || $m['type'] === $type));
    echo json_encode(['ok' => true, 'items' => $items, 'base' => CMS_BASE]);
    exit;
}

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    if ($action === 'delete') {
        if (media_delete(admin_post('path'))) admin_flash('Archivo eliminado.');
        else admin_flash('No se pudo eliminar el archivo.', 'err');
    } elseif ($action === 'delete_many') {
        $paths = array_filter(array_map('strval', (array) ($_POST['paths'] ?? [])));
        $n = 0; $bad = [];
        foreach ($paths as $pth) { if (media_delete($pth)) $n++; else $bad[] = basename($pth); }
        if ($n) admin_flash($n . ' archivo(s) eliminado(s).');
        if ($bad) admin_flash('No se pudieron eliminar: ' . implode(', ', $bad), 'err');
        if (!$paths) admin_flash('No seleccionaste ningún archivo.', 'err');
    } elseif ($action === 'upload') {
        $files = $_FILES['files'] ?? null;
        $n = 0; $errs = [];
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                $one = ['name' => $name, 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                if ($one['error'] === UPLOAD_ERR_NO_FILE) continue;
                [$ok, $res] = media_store($one);
                if ($ok) $n++; else $errs[] = $name . ': ' . $res;
            }
        } else $errs[] = 'No se recibieron archivos (¿superan el límite de ' . media_human(media_limit_bytes()) . '?).';
        if ($n) admin_flash($n . ' archivo(s) subido(s).');
        foreach ($errs as $e) admin_flash($e, 'err');
    }
    admin_redirect(admin_url('media', array_filter(['type' => (string) ($_POST['type'] ?? ''), 'source' => (string) ($_POST['source'] ?? '')])));
}

$type = in_array($_GET['type'] ?? '', ['image', 'pdf', 'video'], true) ? $_GET['type'] : '';
$source = in_array($_GET['source'] ?? '', ['subidos', 'tema'], true) ? $_GET['source'] : '';
$all = media_list();
$items = array_filter($all, fn($m) => ($type === '' || $m['type'] === $type) && ($source === '' || $m['source'] === $source));
$total = array_sum(array_column(array_filter($all, fn($m) => $m['source'] === 'subidos'), 'size'));
$counts = ['image' => 0, 'pdf' => 0, 'video' => 0, 'tema' => 0];
foreach ($all as $m) { if (isset($counts[$m['type']])) $counts[$m['type']]++; if ($m['source'] === 'tema') $counts['tema']++; }

admin_header('Medios', 'media');
?>
<section class="ad-box ad-dropzone" data-dropzone>
  <form method="post" enctype="multipart/form-data" class="ad-upload-form">
    <?= admin_csrf_field() ?>
    <input type="hidden" name="action" value="upload"><input type="hidden" name="type" value="<?= cms_e($type) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= media_limit_bytes() ?>">
    <label class="ad-btn">Subir archivos <input type="file" name="files[]" multiple accept="image/*,.pdf,video/mp4,video/webm,video/quicktime" hidden data-auto-submit></label>
    <span class="ad-help">o arrastra aquí. Imágenes (JPG, PNG, WEBP, GIF), PDF y video (MP4, WEBM, MOV). Máximo por archivo: <strong><?= media_human(media_limit_bytes()) ?></strong> (límite del servidor). Las imágenes anchas se reducen a 1800 px.</span>
  </form>
</section>

<p class="ad-actions">
  <a class="ad-pill <?= $type === '' ? 'on' : '' ?>" href="<?= admin_url('media') ?>">Todos (<?= count($all) ?>)</a>
  <a class="ad-pill <?= $type === 'image' ? 'on' : '' ?>" href="<?= admin_url('media', ['type' => 'image']) ?>">Imágenes (<?= $counts['image'] ?>)</a>
  <a class="ad-pill <?= $type === 'pdf' ? 'on' : '' ?>" href="<?= admin_url('media', ['type' => 'pdf']) ?>">PDF (<?= $counts['pdf'] ?>)</a>
  <a class="ad-pill <?= $type === 'video' ? 'on' : '' ?>" href="<?= admin_url('media', ['type' => 'video']) ?>">Video (<?= $counts['video'] ?>)</a>
  <a class="ad-pill <?= $source === 'tema' ? 'on' : '' ?>" href="<?= admin_url('media', ['source' => 'tema']) ?>" title="Imágenes del diseño, en site/assets/img/">Del tema (<?= $counts['tema'] ?>)</a>
  <span class="ad-help">Espacio en subidos: <?= media_human((int) $total) ?></span>
</p>

<form method="post" class="ad-media-bulk" id="media-bulk" data-media-bulk hidden>
  <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete_many"><input type="hidden" name="type" value="<?= cms_e($type) ?>"><input type="hidden" name="source" value="<?= cms_e($source) ?>">
  <label class="ad-check"><input type="checkbox" data-media-all> Seleccionar todos los visibles</label>
  <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit" data-media-delete disabled>Eliminar seleccionados</button>
  <span class="ad-help" data-media-count>0 seleccionados</span>
</form>

<?php if (!$items): ?><p class="ad-help">No hay archivos<?= $type ? ' de este tipo' : '' ?>. Los archivos que subas desde el editor también aparecen aquí.</p><?php endif; ?>
<div class="ad-media-grid">
<?php foreach ($items as $m): $used = media_in_use($m['path']); ?>
  <div class="ad-media<?= $m['source'] === 'tema' ? ' ad-media-theme' : '' ?>">
    <label class="ad-media-check" title="Seleccionar"><input type="checkbox" form="media-bulk" name="paths[]" value="<?= cms_e($m['path']) ?>" data-media-item data-used="<?= $used ? '1' : '0' ?>"></label>
    <a class="ad-media-thumb" href="<?= cms_e($m['url']) ?>" target="_blank" rel="noopener" title="Abrir"><?php if ($m['source'] === 'tema'): ?><span class="ad-media-badge ad-media-badge-theme">TEMA</span><?php endif; ?>
<?php if ($m['type'] === 'image'): ?>      <img src="<?= cms_e($m['url']) ?>" alt="" loading="lazy">
<?php elseif ($m['type'] === 'video'): ?>      <video src="<?= cms_e($m['url']) ?>" muted preload="metadata"></video><span class="ad-media-badge">VIDEO</span>
<?php else: ?>      <span class="ad-media-icon">PDF</span>
<?php endif; ?>
    </a>
    <div class="ad-media-info">
      <strong title="<?= cms_e($m['path']) ?>"><?= cms_e($m['name']) ?></strong>
      <small><?= media_human((int) $m['size']) ?> · <?= date('Y-m-d', $m['mtime']) ?><?= $used ? ' · <span class="ad-used">en uso</span>' : '' ?></small>
      <div class="ad-media-actions">
        <button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-copy="<?= cms_e($m['path']) ?>" title="Copiar la ruta para pegarla en un campo de imagen">Copiar ruta</button>
        <button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-copy="<?= cms_e($m['url']) ?>" title="Copiar la URL para usarla en un enlace">Copiar URL</button>
        <form method="post" class="ad-inline" data-confirm="<?= $used ? '¡Este archivo está en uso en el contenido! ' : '' ?><?= $m['source'] === 'tema' ? 'Es una imagen del tema; si el diseño la usa por defecto, dejará de verse. ' : '' ?>¿Eliminar <?= cms_e($m['name']) ?>? No se puede deshacer.">
          <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="path" value="<?= cms_e($m['path']) ?>"><input type="hidden" name="type" value="<?= cms_e($type) ?>">
          <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<p class="ad-help">Las imágenes marcadas TEMA viven en <code>site/assets/img/</code>: son el logotipo, capturas y demás recursos del diseño; se pueden usar en cualquier campo de imagen y borrar si ya no se usan. Para usar un PDF o video en una entrada, copia su URL y pégala como enlace en el editor.</p>
<script>
(function () {
  var bulk = document.querySelector("[data-media-bulk]"), items = document.querySelectorAll("[data-media-item]"), all = document.querySelector("[data-media-all]");
  var btn = document.querySelector("[data-media-delete]"), count = document.querySelector("[data-media-count]");
  if (!bulk || !items.length) return;
  bulk.hidden = false;
  function sync() {
    var sel = Array.prototype.filter.call(items, function (c) { return c.checked; }), used = sel.filter(function (c) { return c.getAttribute("data-used") === "1"; }).length;
    btn.disabled = !sel.length; count.textContent = sel.length + " seleccionado" + (sel.length === 1 ? "" : "s") + (used ? " · " + used + " en uso" : "");
    bulk.setAttribute("data-confirm", "¿Eliminar " + sel.length + " archivo(s)?" + (used ? " " + used + " está(n) en uso en el contenido." : "") + " No se puede deshacer.");
    all.checked = sel.length === items.length;
  }
  items.forEach(function (c) { c.addEventListener("change", sync); });
  all.addEventListener("change", function () { items.forEach(function (c) { c.checked = all.checked; }); sync(); });
  sync();
})();
</script>
<?php admin_footer();
