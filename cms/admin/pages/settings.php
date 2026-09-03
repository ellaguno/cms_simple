<?php
/** Ajustes: sección genérica (contacto, redes, idiomas, SEO) + secciones del sitio (config 'settings'). */
declare(strict_types=1);

$S = cms_json_read(CMS_DATA . '/settings.json', []) ?: cms_settings();
$socials = ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'instagram' => 'Instagram', 'behance' => 'Behance', 'youtube' => 'YouTube'];
$siteSections = (array) cms_config('settings');
$langNames = ['es' => 'Español', 'en' => 'English', 'fr' => 'Français', 'pt' => 'Português', 'de' => 'Deutsch', 'it' => 'Italiano'];

if (admin_is_post() && admin_post('action') === 'webp') {
    admin_csrf_check();
    $n = 0; $t0 = microtime(true);
    foreach ([CMS_SITE . '/assets/img', CMS_UPLOADS] as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if ($f->isFile() && preg_match('/\.(jpe?g|png)$/i', $f->getFilename()) && cms_webp_make($f->getPathname())) $n++;
    }
    admin_flash("Versiones WebP listas ($n imágenes revisadas en " . round(microtime(true) - $t0, 1) . ' s).');
    admin_redirect(admin_url('settings'));
}

if (admin_is_post()) {
    admin_csrf_check();
    $S['site_name'] = admin_post('site_name') ?: cms_config('name');
    $S['email'] = admin_post('email');
    $S['form_to'] = admin_post('form_to');
    $S['phone'] = admin_post('phone');
    $S['phone_href'] = admin_post('phone_href');
    $S['whatsapp'] = preg_replace('/\D+/', '', admin_post('whatsapp'));
    $S['author_name'] = admin_post('author_name');
    $S['country'] = admin_post('country');
    $S['google_verification'] = admin_post('google_verification');
    foreach (['logo', 'favicon', 'og_image'] as $k) $S[$k] = admin_post($k);
    $S['languages'] = [];
    foreach (cms_langs() as $l) if ($l !== cms_default_lang()) $S['languages'][$l] = !empty($_POST['lang_' . $l]);
    foreach ($socials as $k => $_) $S['social'][$k] = admin_post('social_' . $k);
    $S['other_sites'] = [];
    foreach (cms_lines(admin_post('other_sites')) as $line) {
        [$label, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
        if ($url === '') $url = $label;
        if ($url !== '') $S['other_sites'][] = ['label' => $label ?: $url, 'url' => preg_match('#^https?://#', $url) ? $url : 'https://' . $url];
    }
    foreach ($siteSections as $sec => $fields) foreach ((array) $fields as $name => $fd) $S[$name] = admin_read_field($name, (array) $fd);
    if ($S['email'] !== '' && !filter_var($S['email'], FILTER_VALIDATE_EMAIL)) admin_flash('El correo de contacto no es válido.', 'err');
    elseif (cms_json_write(CMS_DATA . '/settings.json', $S)) { admin_flash('Ajustes guardados.'); admin_redirect(admin_url('settings')); }
    else admin_flash('No se pudieron guardar los ajustes.', 'err');
}

admin_header('Ajustes', 'settings');
?>
<form method="post" class="ad-form">
  <?= admin_csrf_field() ?>
  <?php if (array_filter($siteSections, fn($f) => (bool) array_filter((array) $f, fn($d) => !empty($d['i18n'])))) admin_lang_switch(); ?>
  <div class="ad-two">
    <section class="ad-box">
      <h2>Contacto</h2>
      <div class="ad-field"><label>Correo de contacto (se muestra en el sitio)</label><input type="email" name="email" value="<?= cms_e($S['email'] ?? '') ?>"></div>
      <div class="ad-field"><label>Correo que recibe el formulario (vacío = el mismo)</label><input type="email" name="form_to" value="<?= cms_e($S['form_to'] ?? '') ?>"></div>
      <div class="ad-field"><label>Teléfono (texto)</label><input type="text" name="phone" value="<?= cms_e($S['phone'] ?? '') ?>"></div>
      <div class="ad-field"><label>Teléfono para marcar (con lada internacional)</label><input type="text" name="phone_href" value="<?= cms_e($S['phone_href'] ?? '') ?>" placeholder="+52..."></div>
      <div class="ad-field"><label>WhatsApp (sólo dígitos, con lada de país)</label><input type="text" name="whatsapp" value="<?= cms_e($S['whatsapp'] ?? '') ?>" placeholder="5215512345678"><p class="ad-help">Vacío = sin enlace de WhatsApp.</p></div>
    </section>
    <section class="ad-box">
      <h2>Redes sociales</h2>
      <p class="ad-help">Sólo se muestran las que tienen URL.</p>
<?php foreach ($socials as $k => $label): ?>
      <div class="ad-field"><label><?= $label ?></label><input type="url" name="social_<?= $k ?>" value="<?= cms_e($S['social'][$k] ?? '') ?>" placeholder="https://"></div>
<?php endforeach; ?>
    </section>
  </div>
  <div class="ad-two">
    <section class="ad-box">
      <h2>General</h2>
      <div class="ad-field"><label>Nombre del sitio</label><input type="text" name="site_name" value="<?= cms_e($S['site_name'] ?? cms_config('name')) ?>"></div>
      <div class="ad-field"><label>Nombre del autor (blog y datos estructurados)</label><input type="text" name="author_name" value="<?= cms_e($S['author_name'] ?? '') ?>"></div>
      <div class="ad-field"><label>País (código, para datos estructurados)</label><input type="text" name="country" value="<?= cms_e($S['country'] ?? 'MX') ?>" maxlength="2"></div>
<?php foreach (cms_langs() as $l): if ($l === cms_default_lang()) continue; ?>
      <div class="ad-field"><label class="ad-check"><input type="checkbox" name="lang_<?= $l ?>" value="1"<?= !empty($S['languages'][$l]) ? ' checked' : '' ?>> Activar la versión en <?= cms_e($langNames[$l] ?? strtoupper($l)) ?> (/<?= $l ?>/)</label></div>
<?php endforeach; ?>
      <div class="ad-field"><label>Código de verificación de Google Search Console</label><input type="text" name="google_verification" value="<?= cms_e($S['google_verification'] ?? '') ?>"></div>
      <div class="ad-field"><label>Otros sitios (enlaces del footer): una por línea, "Texto | URL"</label><textarea name="other_sites" rows="4"><?= cms_e(implode("\n", array_map(fn($o) => ($o['label'] ?? '') . ' | ' . ($o['url'] ?? ''), (array) ($S['other_sites'] ?? [])))) ?></textarea></div>
    </section>
    <section class="ad-box">
      <h2>Marca y SEO</h2>
      <?php admin_field('logo', ['type' => 'image', 'label' => 'Logotipo'], $S['logo'] ?? ''); ?>
      <?php admin_field('favicon', ['type' => 'image', 'label' => 'Favicon (PNG cuadrado)'], $S['favicon'] ?? ''); ?>
      <?php admin_field('og_image', ['type' => 'image', 'label' => 'Imagen al compartir en redes (páginas sin imagen propia; ideal 1200×630)'], $S['og_image'] ?? ''); ?>
    </section>
  </div>
<?php foreach ($siteSections as $sec => $fields): ?>
  <section class="ad-box">
    <h2><?= cms_e($sec) ?></h2>
<?php foreach ((array) $fields as $name => $fd) admin_field($name, (array) $fd, $S[$name] ?? ($fd['default'] ?? '')); ?>
  </section>
<?php endforeach; ?>
  <p><button class="ad-btn" type="submit">Guardar ajustes</button></p>
</form>
<form method="post" class="ad-box">
  <?= admin_csrf_field() ?><input type="hidden" name="action" value="webp">
  <h2>Rendimiento</h2>
  <p class="ad-help">Genera o actualiza las versiones WebP de las imágenes de <code>site/assets/img</code> y <code>uploads/</code>. El sitio las sirve automáticamente; las imágenes nuevas se convierten al subirlas.</p>
  <button class="ad-btn ad-btn-light" type="submit">Generar versiones WebP</button>
</form>
<?php admin_footer();
