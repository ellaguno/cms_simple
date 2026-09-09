<?php
/**
 * Diseño: variaciones de estilo del tema activo ("pieles"), con muestra en vivo y aplicación con un clic.
 * Aplicar una variación guarda su clave en Ajustes ('style') y, salvo que se pida conservarlos, copia sus
 * colores y tipografías a Ajustes → Diseño para que el sitio se vea tal cual la muestra.
 */
declare(strict_types=1);
$styles = cms_styles();

if (admin_is_post()) {
    admin_csrf_check();
    $k = (string) admin_post('style');
    $S = cms_json_read(CMS_DATA . '/settings.json', []);
    if ($k === '') {
        unset($S['style']);
        if (cms_json_write(CMS_DATA . '/settings.json', $S)) admin_flash('Se quitó la variación: el sitio usa el estilo base del tema.');
        else admin_flash('No se pudo guardar.', 'err');
    } elseif (isset($styles[$k])) {
        $S['style'] = $k;
        if (!admin_post('keep')) foreach ((array) ($styles[$k]['settings'] ?? []) as $sk => $sv) {
            if (preg_match('/^[a-z0-9_]+$/i', (string) $sk)) $S[$sk] = is_scalar($sv) ? (string) $sv : '';
        }
        if (cms_json_write(CMS_DATA . '/settings.json', $S)) admin_flash('Estilo aplicado: ' . ($styles[$k]['label'] ?? $k) . '.');
        else admin_flash('No se pudo guardar en data/settings.json.', 'err');
    } else admin_flash('Esa variación no existe.', 'err');
    admin_redirect(admin_url('diseno'));
}

$active = cms_style_key();
$themeName = cms_config('name');
$themeInfo = cms_json_read(CMS_SITE . '/theme.json', []);
admin_header('Diseño', 'diseno');
?>
<p class="ad-actions">
  <span class="ad-pill on">Tema: <?= cms_e($themeInfo['label'] ?? basename(CMS_SITE)) ?></span>
  <span class="ad-help">Las variaciones cambian colores, tipografías, esquinas y espacios de todo el sitio. El contenido y las páginas no se tocan.</span>
</p>
<?php if (!$styles): ?>
<div class="ad-box">
  <h2>Este tema no trae variaciones</h2>
  <p class="ad-help">Un tema puede incluirlas en <code><?= cms_e(basename(CMS_SITE)) ?>/styles/&lt;clave&gt;.json</code>: nombre, descripción, las variables CSS que cambia y los colores y tipografías que sugiere. Mientras tanto, ajusta el diseño en <a href="<?= admin_url('settings') ?>">Ajustes → Diseño</a>.</p>
</div>
<?php else: ?>
<div class="ad-styles">
<?php foreach ($styles as $k => $st): $on = $k === $active; ?>
  <article class="ad-style<?= $on ? ' is-on' : '' ?>">
    <div class="ad-demo" data-demo="<?= cms_e(admin_url('demo', ['style' => $k])) ?>"><a class="ad-demo-open" href="<?= cms_e(admin_url('demo', ['style' => $k])) ?>" target="_blank" rel="noopener" title="Ver la muestra a tamaño real">Abrir ↗</a></div>
    <div class="ad-style-body">
      <div class="ad-style-head">
        <strong><?= cms_e($st['label']) ?></strong>
        <?php if ($on): ?><span class="ad-pill on">En uso</span><?php endif; ?>
      </div>
      <p class="ad-help"><?= cms_e($st['desc']) ?></p>
      <div class="ad-style-swatches" aria-hidden="true">
<?php foreach (['--lz-primary', '--lz-accent', '--lz-bg', '--lz-light'] as $v): $c = (string) ($st['vars'][$v] ?? ''); if (!preg_match('/^#[0-9a-f]{3,8}$/i', $c)) continue; ?>
        <span style="background:<?= cms_e($c) ?>"></span>
<?php endforeach; ?>
<?php if (!empty($st['fonts'])): ?>        <small><?= cms_e(implode(' · ', array_map('cms_font_name', (array) $st['fonts']))) ?></small><?php endif; ?>
      </div>
      <form method="post" class="ad-style-form">
        <?= admin_csrf_field() ?><input type="hidden" name="style" value="<?= cms_e($k) ?>">
        <button class="ad-btn ad-btn-sm<?= $on ? ' ad-btn-light' : '' ?>" type="submit"<?= $on ? ' disabled' : '' ?>><?= $on ? 'En uso' : 'Usar esta' ?></button>
        <label class="ad-check ad-help" title="Deja tus colores y tipografías de Ajustes → Diseño tal como están"><input type="checkbox" name="keep" value="1"> conservar mis colores</label>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php if ($active !== ''): ?>
<form method="post" class="ad-actions"><?= admin_csrf_field() ?><input type="hidden" name="style" value="">
  <button class="ad-btn ad-btn-light ad-btn-sm" type="submit">Quitar la variación y usar el estilo base del tema</button>
</form>
<?php endif; ?>
<p class="ad-help">Al aplicar una variación se copian sus colores y tipografías a <a href="<?= admin_url('settings') ?>">Ajustes → Diseño</a>, donde puedes seguir afinándolos. Marca «conservar mis colores» si ya tienes los tuyos y solo quieres el resto (esquinas, espacios y tipografías del conjunto).</p>
<?php endif; admin_footer();
