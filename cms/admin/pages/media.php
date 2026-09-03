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
        $abs = media_safe_path(admin_post('path'));
        if ($abs && unlink($abs)) {
            $w = preg_replace('/\.[^.]+$/', '.webp', $abs); if ($w !== $abs && is_file($w)) @unlink($w);
            @rmdir(dirname($abs)); @rmdir(dirname($abs, 2)); // limpia carpetas vacías AAAA/MM
            admin_flash('Archivo eliminado.');
        } else admin_flash('No se pudo eliminar el archivo.', 'err');
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
    admin_redirect(admin_url('media', array_filter(['type' => (string) ($_POST['type'] ?? '')])));
}

$type = in_array($_GET['type'] ?? '', ['image', 'pdf', 'video'], true) ? $_GET['type'] : '';
$all = media_list();
$items = $type ? array_filter($all, fn($m) => $m['type'] === $type) : $all;
$total = array_sum(array_column($all, 'size'));
$counts = ['image' => 0, 'pdf' => 0, 'video' => 0];
foreach ($all as $m) if (isset($counts[$m['type']])) $counts[$m['type']]++;

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
  <span class="ad-help">Espacio usado: <?= media_human((int) $total) ?></span>
</p>

<?php if (!$items): ?><p class="ad-help">No hay archivos<?= $type ? ' de este tipo' : '' ?>. Los archivos que subas desde el editor también aparecen aquí.</p><?php endif; ?>
<div class="ad-media-grid">
<?php foreach ($items as $m): $used = media_in_use($m['path']); ?>
  <div class="ad-media">
    <a class="ad-media-thumb" href="<?= cms_e($m['url']) ?>" target="_blank" rel="noopener" title="Abrir">
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
        <form method="post" class="ad-inline" data-confirm="<?= $used ? '¡Este archivo está en uso en el contenido! ' : '' ?>¿Eliminar <?= cms_e($m['name']) ?>? No se puede deshacer.">
          <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="path" value="<?= cms_e($m['path']) ?>"><input type="hidden" name="type" value="<?= cms_e($type) ?>">
          <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<p class="ad-help">Las imágenes del tema están en <code>site/assets/img/</code> y no se administran desde aquí. Para usar un PDF o video en una entrada, copia su URL y pégala como enlace en el editor.</p>
<?php admin_footer();
