<?php
declare(strict_types=1);

$rules = cms_json_read(CMS_DATA . '/redirects.json', []);

if (admin_is_post()) {
    admin_csrf_check();
    $from = $_POST['from'] ?? [];
    $to = $_POST['to'] ?? [];
    $new = [];
    $seen = [];
    foreach ((array) $from as $i => $f) {
        $f = trim((string) $f);
        $d = trim((string) ($to[$i] ?? ''));
        if ($f === '' || $d === '') continue;
        $f = preg_replace('#^https?://[^/]+#i', '', $f); // acepta URL completa antigua
        $f = trim($f, '/ ');
        if (CMS_BASE !== '' && stripos('/' . $f, CMS_BASE . '/') === 0) $f = trim(substr('/' . $f, strlen(CMS_BASE)), '/');
        $key = strtolower($f);
        if ($f === '' || isset($seen[$key])) continue;
        $seen[$key] = true;
        $new[] = ['from' => $f, 'to' => $d];
    }
    if (cms_json_write(CMS_DATA . '/redirects.json', $new)) admin_flash(count($new) . ' redirección(es) guardada(s).');
    else admin_flash('No se pudo guardar redirects.json.', 'err');
    admin_redirect(admin_url('redirects'));
}

admin_header('Redirecciones 301', 'redirects');
?>
<p class="ad-help">Sirven para no perder posicionamiento cuando cambian las URLs (por ejemplo, las del sitio anterior). <strong>Origen</strong> es la ruta antigua dentro de este dominio (puedes pegar la URL completa: se queda sólo la ruta). <strong>Destino</strong> es una ruta de este sitio (<code>/portafolio/branding-corporativo-para-interlace</code>) o una URL completa. Se responde con código 301 (permanente).</p>
<form method="post" class="ad-form">
  <?= admin_csrf_field() ?>
  <section class="ad-box">
    <div class="ad-menu-rows" data-menu-rows data-lang="x">
      <div class="ad-redirect-head"><span>Origen (ruta antigua)</span><span>Destino</span><span></span></div>
<?php foreach ($rules as $r): ?>
      <div class="ad-menu-row ad-redirect-row">
        <input type="text" name="from[]" value="<?= cms_e($r['from'] ?? '') ?>" placeholder="ej. portafolio/interlace-2021">
        <input type="text" name="to[]" value="<?= cms_e($r['to'] ?? '') ?>" placeholder="ej. /portafolio/branding-corporativo-para-interlace">
        <button type="button" class="ad-icon ad-icon-danger" data-remove title="Quitar">✕</button>
      </div>
<?php endforeach; ?>
    </div>
    <button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-add-row>+ Agregar redirección</button>
  </section>
  <template id="menu-row-tpl"><div class="ad-menu-row ad-redirect-row">
    <input type="text" name="from[]" placeholder="ej. portafolio/interlace-2021"><input type="text" name="to[]" placeholder="ej. /portafolio/branding-corporativo-para-interlace">
    <button type="button" class="ad-icon ad-icon-danger" data-remove title="Quitar">✕</button>
  </div></template>
  <p><button class="ad-btn" type="submit">Guardar redirecciones</button></p>
</form>
<p class="ad-help">Consejo: antes de apagar el sitio anterior, copia sus URLs indexadas (Google Search Console → Páginas) y crea aquí una redirección para cada una hacia la página equivalente.</p>
<?php admin_footer();
