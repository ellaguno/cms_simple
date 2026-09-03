<?php
declare(strict_types=1);

$menu = cms_json_read(CMS_DATA . '/menu.json', []);

if (admin_is_post()) {
    admin_csrf_check();
    $new = [];
    foreach (cms_langs() as $l) {
        $labels = $_POST['label'][$l] ?? [];
        $urls = $_POST['url'][$l] ?? [];
        $tabs = $_POST['new_tab'][$l] ?? [];
        $new[$l] = [];
        foreach ((array) $labels as $i => $label) {
            $label = trim((string) $label);
            $url = trim((string) ($urls[$i] ?? ''));
            if ($label === '' && $url === '') continue;
            $row = ['label' => $label, 'url' => $url ?: '/'];
            if (!empty($tabs[$i])) $row['new_tab'] = true;
            $new[$l][] = $row;
        }
    }
    if (cms_json_write(CMS_DATA . '/menu.json', $new)) admin_flash('Menú guardado.');
    else admin_flash('No se pudo guardar el menú.', 'err');
    admin_redirect(admin_url('menu'));
}

$routes = [];
foreach (cms_langs() as $l) {
    $routes[$l] = ['/' => 'Inicio'];
    foreach (cms_config('types') as $k => $d) {
        $d += ['key' => $k];
        if (empty($d['no_list'])) $routes[$l]['/' . cms_segment($d, $l)] = $d['label'] ?? $k;
        foreach (cms_items($k) as $it) $routes[$l]['/' . cms_segment($d, $l) . '/' . $it['slug']] = ($d['label_singular'] ?? $k) . ': ' . (cms_f($it, $d['title_field'] ?? 'title', $l) ?: $it['slug']);
    }
    foreach (cms_config('pages') as $k => $d) $routes[$l]['/' . cms_segment($d + ['key' => $k], $l)] = $d['label'] ?? $k;
}

admin_header('Menú de navegación', 'menu');
?>
<p class="ad-help">Cada idioma tiene su propio menú. Las rutas empiezan con <code>/</code> y son relativas al idioma (en los demás idiomas se antepone <code>/xx</code> automáticamente). También puedes poner una URL completa (https://…). Usa las flechas para cambiar el orden.</p>
<form method="post" class="ad-form">
  <?= admin_csrf_field() ?>
  <div class="ad-two">
<?php foreach (cms_langs() as $l): ?>
    <section class="ad-box">
      <h2>Menú <?= strtoupper($l) ?></h2>
      <div class="ad-menu-rows" data-menu-rows data-lang="<?= $l ?>">
<?php foreach (($menu[$l] ?? []) as $row): ?>
        <div class="ad-menu-row">
          <input type="text" name="label[<?= $l ?>][]" value="<?= cms_e($row['label'] ?? '') ?>" placeholder="Texto">
          <input type="text" name="url[<?= $l ?>][]" value="<?= cms_e($row['url'] ?? '/') ?>" placeholder="/ruta o https://" list="routes-<?= $l ?>">
          <label class="ad-check" title="Abrir en pestaña nueva"><input type="checkbox" name="new_tab[<?= $l ?>][]" value="1"<?= !empty($row['new_tab']) ? ' checked' : '' ?>>↗</label>
          <button type="button" class="ad-icon" data-up title="Subir">▲</button><button type="button" class="ad-icon" data-down title="Bajar">▼</button><button type="button" class="ad-icon ad-icon-danger" data-remove title="Quitar">✕</button>
        </div>
<?php endforeach; ?>
      </div>
      <button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-add-row>+ Agregar enlace</button>
      <datalist id="routes-<?= $l ?>"><?php foreach ($routes[$l] as $u => $lab): ?><option value="<?= cms_e($u) ?>"><?= cms_e($lab) ?></option><?php endforeach; ?></datalist>
    </section>
<?php endforeach; ?>
  </div>
  <template id="menu-row-tpl"><div class="ad-menu-row">
    <input type="text" name="label[__L__][]" placeholder="Texto"><input type="text" name="url[__L__][]" value="/" placeholder="/ruta o https://" list="routes-__L__">
    <label class="ad-check" title="Abrir en pestaña nueva"><input type="checkbox" name="new_tab[__L__][]" value="1">↗</label>
    <button type="button" class="ad-icon" data-up>▲</button><button type="button" class="ad-icon" data-down>▼</button><button type="button" class="ad-icon ad-icon-danger" data-remove>✕</button>
  </div></template>
  <p class="ad-help">Nota: la casilla "↗" sólo se guarda correctamente si se marca en filas que tienen texto; si necesitas abrir en pestaña nueva un enlace que está en medio, guarda primero y vuelve a marcarla.</p>
  <p><button class="ad-btn" type="submit">Guardar menú</button></p>
</form>
<?php admin_footer();
