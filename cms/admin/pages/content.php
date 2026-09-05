<?php
declare(strict_types=1);
$type = (string) ($_GET['type'] ?? '');
$def = cms_type($type);
if (!$def) { admin_flash('Tipo de contenido desconocido.', 'err'); admin_redirect(admin_url()); }

if (admin_is_post()) {
    admin_csrf_check();
    if (admin_post('action') === 'delete') {
        if (cms_item_delete($type, admin_post('slug'))) admin_flash('Elemento eliminado.');
        else admin_flash('No se pudo eliminar.', 'err');
    } elseif (admin_post('action') === 'duplicate') {
        $src = cms_item($type, admin_post('slug'), false);
        if ($src) {
            $base = $src['slug'] . '-copia'; $slug = $base; $n = 2;
            while (is_file(cms_content_dir($type) . '/' . $slug . '.json')) $slug = $base . '-' . $n++;
            $tf = $def['title_field'] ?? 'title';
            $copy = $src; $copy['slug'] = $slug; $copy['status'] = 'draft'; $copy['created'] = date('Y-m-d'); $copy['updated'] = date('Y-m-d'); unset($copy['publish_at']);
            if (is_array($copy[$tf] ?? null)) { foreach ($copy[$tf] as $l => $v) if ($v !== '') $copy[$tf][$l] = $v . ' (copia)'; } else $copy[$tf] = (string) ($copy[$tf] ?? '') . ' (copia)';
            if (cms_item_save($type, $copy)) { admin_flash('Copia creada como borrador.'); admin_redirect(admin_url('edit', ['type' => $type, 'slug' => $slug])); }
            admin_flash('No se pudo duplicar.', 'err');
        }
    }
    admin_redirect(admin_url('content', ['type' => $type]));
}

$items = cms_items($type, false);
if (!empty($def['tree'])) uasort($items, fn($a, $b) => strcmp((string) ($a['path'] ?? $a['slug']), (string) ($b['path'] ?? $b['slug'])));
$dl = cms_default_lang();
$titleField = $def['title_field'] ?? 'title';
$cols = (array) ($def['list'] ?? []);
$multi = count(cms_langs()) > 1;
admin_header($def['label'] ?? $type, 'content:' . $type);
?>
<p class="ad-actions"><a class="ad-btn" href="<?= admin_url('edit', ['type' => $type]) ?>">+ <?= cms_e($def['label_singular'] ?? 'Nuevo') ?></a><?php if (!empty($def['help'])): ?> <span class="ad-help"><?= cms_e($def['help']) ?></span><?php endif; ?></p>
<?php if (!$items): ?><p class="ad-help">Aún no hay elementos.</p><?php else: ?>
<table class="ad-table">
  <thead><tr><th><?= cms_e(admin_field_label($titleField, $def['fields'][$titleField] ?? [])) ?></th><?php foreach ($cols as $c): ?><th><?= cms_e(admin_field_label($c, $def['fields'][$c] ?? [])) ?></th><?php endforeach; ?><th>Estado</th><?php if ($multi): ?><th>Traducción</th><?php endif; ?><th></th></tr></thead>
  <tbody>
<?php foreach ($items as $it): $pub = ($it['status'] ?? '') === 'published'; $live = cms_item_is_live($it); ?>
    <tr>
      <td><a href="<?= admin_url('edit', ['type' => $type, 'slug' => $it['slug']]) ?>"><strong><?= cms_e(cms_f($it, $titleField, $dl) ?: $it['slug']) ?></strong></a><small class="ad-help"><?= cms_e(preg_replace('#^https?://[^/]+#', '', cms_url('item:' . $type, $dl, $it['slug']))) ?></small></td>
<?php foreach ($cols as $c): $v = cms_f($it, $c, $dl); ?>
      <td><?= cms_e(is_array($v) ? implode(', ', $v) : (string) $v) ?></td>
<?php endforeach; ?>
      <td><span class="ad-pill <?= $live ? 'on' : ($pub ? 'warn' : '') ?>"><?= $live ? 'Publicado' : ($pub ? 'Programado ' . cms_e($it['publish_at'] ?? '') : 'Borrador') ?></span></td>
<?php if ($multi): $missing = []; foreach (cms_langs() as $l) if ($l !== $dl && empty($it[$titleField][$l])) $missing[] = strtoupper($l); ?>
      <td><?= $missing ? '<span class="ad-help">falta ' . implode(', ', $missing) . '</span>' : '✓' ?></td>
<?php endif; ?>
      <td class="ad-row-actions">
        <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e(cms_item_url($type, $it, $dl)) ?>" target="_blank" rel="noopener"><?= $live ? 'Ver' : 'Vista previa' ?></a>
        <a class="ad-btn ad-btn-sm" href="<?= admin_url('edit', ['type' => $type, 'slug' => $it['slug']]) ?>">Editar</a>
        <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="slug" value="<?= cms_e($it['slug']) ?>"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit" title="Crear una copia como borrador">Duplicar</button></form>
        <form method="post" class="ad-inline" data-confirm="¿Eliminar este elemento? No se puede deshacer.">
          <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?= cms_e($it['slug']) ?>">
          <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button>
        </form>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; admin_footer();
