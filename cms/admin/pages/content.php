<?php
declare(strict_types=1);
$type = (string) ($_GET['type'] ?? '');
$def = cms_type($type);
if (!$def) { admin_flash('Tipo de contenido desconocido.', 'err'); admin_redirect(admin_url()); }

$dl = cms_default_lang();
$titleField = $def['title_field'] ?? 'title';
$cols = (array) ($def['list'] ?? []);

/* ---------- filtros del listado (buscador, estado, columnas) y paginación; todo por GET para poder compartir/volver ---------- */
$q = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['published', 'scheduled', 'draft'], true) ? (string) $_GET['status'] : '';
$colFilters = [];
foreach ($cols as $c) { $v = trim((string) ($_GET['f_' . $c] ?? '')); if ($v !== '') $colFilters[$c] = $v; }
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(10, (int) ($def['admin_per_page'] ?? 50));
$state = ['type' => $type, 'q' => $q, 'status' => $status, 'page' => $page > 1 ? (string) $page : ''];
foreach ($colFilters as $c => $v) $state['f_' . $c] = $v;
/** URL del listado conservando los filtros actuales; $over sobrescribe (vacío = quitar). */
$listUrl = fn(array $over = []) => admin_url('content', array_filter($over + $state, fn($v) => $v !== '' && $v !== null));

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
    admin_redirect($listUrl());
}

$all = cms_items($type, false);
if (!empty($def['tree'])) uasort($all, fn($a, $b) => strcmp((string) ($a['path'] ?? $a['slug']), (string) ($b['path'] ?? $b['slug'])));
$multi = count(cms_langs()) > 1;

/** Texto plano de un valor (cadena, lista o campo bilingüe) para buscar en él. */
$flat = function ($v) use (&$flat): string {
    if (is_array($v)) return implode(' ', array_map($flat, $v));
    return is_scalar($v) ? (string) $v : '';
};
$itemStatus = fn(array $it) => cms_item_is_live($it) ? 'published' : ((($it['status'] ?? '') === 'published') ? 'scheduled' : 'draft');
/** Minúsculas y sin acentos, para que "diseno" encuentre "Diseño". */
$fold = fn(string $s) => strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ç' => 'c']);

// 1) buscador: todas las palabras deben aparecer en título (cualquier idioma), slug/ruta o columnas del listado
$words = $q === '' ? [] : (preg_split('/\s+/u', $fold($q)) ?: []);
$items = $all;
if ($words) {
    $items = array_filter($items, function ($it) use ($words, $flat, $fold, $titleField, $cols) {
        $hay = $fold($flat($it[$titleField] ?? '') . ' ' . $it['slug'] . ' ' . ($it['path'] ?? ''));
        foreach ($cols as $c) $hay .= ' ' . $fold($flat($it[$c] ?? ''));
        foreach ($words as $w) if ($w !== '' && mb_strpos($hay, $w) === false) return false;
        return true;
    });
}
// 2) filtros por columna (categoría, autor…): coincidencia exacta con el valor en el idioma predeterminado (o con una de las etiquetas)
foreach ($colFilters as $c => $v) {
    $items = array_filter($items, function ($it) use ($c, $v, $dl) {
        $x = cms_f($it, $c, $dl);
        return is_array($x) ? in_array($v, array_map('strval', $x), true) : (string) $x === $v;
    });
}
// contadores por estado (sobre lo que coincide con búsqueda y filtros de columna)
$counts = ['published' => 0, 'scheduled' => 0, 'draft' => 0];
foreach ($items as $it) $counts[$itemStatus($it)]++;
// 3) estado
if ($status !== '') $items = array_filter($items, fn($it) => $itemStatus($it) === $status);

// opciones de filtro por columna: columnas de texto/selector/etiquetas con pocos valores distintos
$colOptions = [];
foreach ($cols as $c) {
    $ft = $def['fields'][$c]['type'] ?? '';
    if (!in_array($ft, ['text', 'select', 'tags'], true)) continue;
    $vals = [];
    foreach ($all as $it) { $x = cms_f($it, $c, $dl); foreach ((array) $x as $one) { $one = trim((string) $one); if ($one !== '') $vals[$one] = true; } }
    if ($ft === 'select') foreach ((array) ($def['fields'][$c]['options'] ?? []) as $ok => $ov) $vals[is_int($ok) ? (string) $ov : (string) $ok] = true;
    $vals = array_keys($vals);
    if (count($vals) >= 2 && count($vals) <= 60) { natcasesort($vals); $colOptions[$c] = array_values($vals); }
}

// paginación
$total = count($items);
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) { $page = $pages; $state['page'] = $page > 1 ? (string) $page : ''; }
$items = array_slice($items, ($page - 1) * $perPage, $perPage, true);
$filtered = $q !== '' || $status !== '' || $colFilters;

admin_header($def['label'] ?? $type, 'content:' . $type);
?>
<p class="ad-actions"><a class="ad-btn" href="<?= admin_url('edit', ['type' => $type]) ?>">+ <?= cms_e($def['label_singular'] ?? 'Nuevo') ?></a><?php if (!empty($def['help'])): ?> <span class="ad-help"><?= cms_e($def['help']) ?></span><?php endif; ?></p>
<?php if (!$all): ?><p class="ad-help">Aún no hay elementos.</p><?php else: ?>
<form method="get" class="ad-filter" role="search">
  <input type="hidden" name="p" value="content"><input type="hidden" name="type" value="<?= cms_e($type) ?>">
  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= cms_e($status) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= cms_e($q) ?>" placeholder="Buscar por título, URL<?= $cols ? ' o ' . strtolower(implode(', ', array_map(fn($c) => admin_field_label($c, $def['fields'][$c] ?? []), $cols))) : '' ?>…" aria-label="Buscar" data-filter-q>
<?php foreach ($colOptions as $c => $opts): ?>
  <select name="f_<?= cms_e($c) ?>" aria-label="<?= cms_e(admin_field_label($c, $def['fields'][$c] ?? [])) ?>" data-filter-auto>
    <option value="">— <?= cms_e(admin_field_label($c, $def['fields'][$c] ?? [])) ?> —</option>
<?php foreach ($opts as $o): ?>
    <option value="<?= cms_e($o) ?>"<?= ($colFilters[$c] ?? '') === $o ? ' selected' : '' ?>><?= cms_e($o) ?></option>
<?php endforeach; ?>
  </select>
<?php endforeach; ?>
  <button class="ad-btn ad-btn-sm" type="submit">Buscar</button>
  <?php if ($filtered): ?><a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('content', ['type' => $type]) ?>">Limpiar</a><?php endif; ?>
</form>
<p class="ad-actions ad-filter-status">
  <a class="ad-pill <?= $status === '' ? 'on' : '' ?>" href="<?= $listUrl(['status' => '', 'page' => '']) ?>">Todos (<?= array_sum($counts) ?>)</a>
  <a class="ad-pill <?= $status === 'published' ? 'on' : '' ?>" href="<?= $listUrl(['status' => 'published', 'page' => '']) ?>">Publicados (<?= $counts['published'] ?>)</a>
<?php if ($counts['scheduled'] || $status === 'scheduled'): ?>  <a class="ad-pill <?= $status === 'scheduled' ? 'on' : '' ?>" href="<?= $listUrl(['status' => 'scheduled', 'page' => '']) ?>">Programados (<?= $counts['scheduled'] ?>)</a>
<?php endif; ?>  <a class="ad-pill <?= $status === 'draft' ? 'on' : '' ?>" href="<?= $listUrl(['status' => 'draft', 'page' => '']) ?>">Borradores (<?= $counts['draft'] ?>)</a>
<?php if ($total > $perPage): ?>  <span class="ad-help">Mostrando <?= ($page - 1) * $perPage + 1 ?>–<?= min($total, $page * $perPage) ?> de <?= $total ?></span><?php endif; ?>
</p>
<?php if (!$items): ?><p class="ad-help">Nada coincide con la búsqueda. <a href="<?= admin_url('content', ['type' => $type]) ?>">Ver todos</a>.</p><?php else: ?>
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
<?php if ($pages > 1): // paginación compacta: primera, última, y dos a cada lado de la actual
  $show = array_unique(array_filter(array_merge([1, $pages], range(max(1, $page - 2), min($pages, $page + 2))), fn($n) => $n >= 1 && $n <= $pages)); sort($show); ?>
<nav class="ad-pager" aria-label="Páginas del listado">
  <?php if ($page > 1): ?><a href="<?= $listUrl(['page' => $page - 1 > 1 ? (string) ($page - 1) : '']) ?>">‹ Anterior</a><?php endif; ?>
<?php $prev = 0; foreach ($show as $n): if ($prev && $n > $prev + 1): ?>  <span class="ad-pager-gap">…</span>
<?php endif; $prev = $n; if ($n === $page): ?>  <span class="cur" aria-current="page"><?= $n ?></span>
<?php else: ?>  <a href="<?= $listUrl(['page' => $n > 1 ? (string) $n : '']) ?>"><?= $n ?></a>
<?php endif; endforeach; ?>
  <?php if ($page < $pages): ?><a href="<?= $listUrl(['page' => (string) ($page + 1)]) ?>">Siguiente ›</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; endif; admin_footer();
