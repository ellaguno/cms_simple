<?php
/**
 * Ajustes, en pestañas (1.29): General, Contacto y redes, Marca y SEO, Cookies, las secciones del tema (config 'settings')
 * y una pestaña por paquete con ajustes ('settings' en pack.php). Un solo formulario: Guardar guarda todas las pestañas;
 * la barra de guardar queda fija abajo y avisa de cambios sin guardar. ?tab=clave abre una pestaña (las páginas de
 * los paquetes enlazan a la suya). Lo que no es un ajuste vive en su sitio: WebP en Medios, catálogos propios en Catálogo.
 */
declare(strict_types=1);

$S = cms_json_read(CMS_DATA . '/settings.json', []) ?: cms_settings();
$socials = ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'instagram' => 'Instagram', 'behance' => 'Behance', 'youtube' => 'YouTube'];
$langNames = ['es' => 'Español', 'en' => 'English', 'fr' => 'Français', 'pt' => 'Português', 'de' => 'Deutsch', 'it' => 'Italiano'];

// pestañas: clave => ['label' => …, 'sections' => [título => campos]]; las del núcleo dibujan su propio HTML (sections vacío)
$tabs = ['general' => ['label' => 'General', 'sections' => []], 'contacto' => ['label' => 'Contacto y redes', 'sections' => []], 'marca' => ['label' => 'Marca y SEO', 'sections' => []]];
$themeSections = (array) cms_config('settings');
if ($themeSections) {
    $tabs['tema'] = ['label' => count($themeSections) === 1 ? (string) array_key_first($themeSections) : 'Tema', 'sections' => $themeSections];
}
foreach (cms_packs() as $pn => $pk) {
    $secs = (array) ($pk['settings'] ?? []);
    if (!$secs) continue;
    $tabs['pack:' . $pn] = ['label' => (string) ($pk['admin']['label'] ?? $pk['label']), 'sections' => $secs];
}
if (cms_config('cookie_notice', true) !== false) $tabs['cookies'] = ['label' => 'Cookies', 'sections' => ['Aviso de cookies' => [
    'cookie_on'     => ['type' => 'checkbox', 'label' => 'Aviso', 'text' => 'Mostrar la barra de aviso de cookies hasta que la persona la acepte'],
    'cookie_text'   => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto', 'rows' => 2, 'placeholder' => 'Usamos cookies para mejorar tu experiencia. Al seguir navegando aceptas su uso.'],
    'cookie_button' => ['type' => 'text', 'i18n' => true, 'label' => 'Texto del botón', 'placeholder' => 'Aceptar', 'half' => true],
    'cookie_link'   => ['type' => 'text', 'label' => 'Enlace a la política de privacidad o cookies (opcional)', 'placeholder' => '/legal/privacidad', 'half' => true],
    'cookie_pos'    => ['type' => 'select', 'label' => 'Posición', 'options' => ['bottom' => 'Abajo, a todo el ancho', 'corner' => 'Esquina inferior izquierda'], 'default' => 'bottom'],
]]];
// todos los campos declarados (tema, paquetes, cookies), para leerlos del POST de una vez
$declared = [];
foreach ($tabs as $t) foreach ($t['sections'] as $fields) foreach ((array) $fields as $name => $fd) $declared[$name] = (array) $fd;
$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'general');
if (!isset($tabs[$tab])) $tab = 'general';

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
    $S['site_url'] = rtrim(trim(admin_post('site_url')), '/');
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
    foreach ($declared as $name => $fd) $S[$name] = admin_read_field($name, $fd);
    if ($S['email'] !== '' && !filter_var($S['email'], FILTER_VALIDATE_EMAIL)) admin_flash('El correo de contacto no es válido.', 'err');
    elseif (cms_json_write(CMS_DATA . '/settings.json', $S)) { admin_flash('Ajustes guardados.'); admin_redirect(admin_url('settings', ['tab' => $tab])); }
    else admin_flash('No se pudieron guardar los ajustes.', 'err');
}

$anyI18n = count(cms_langs()) > 1 && (bool) array_filter($declared, fn($d) => !empty($d['i18n']));
admin_header('Ajustes', 'settings');
?>
<form method="post" class="ad-form" data-dirty-warn>
  <?= admin_csrf_field() ?><input type="hidden" name="tab" value="<?= cms_e($tab) ?>">
  <nav class="ad-tabs" data-tabs aria-label="Secciones de ajustes">
<?php foreach ($tabs as $k => $t): ?>    <button type="button" data-tab="<?= cms_e($k) ?>"<?= $k === $tab ? ' class="on"' : '' ?>><?= cms_e($t['label']) ?></button>
<?php endforeach; ?>
  </nav>
  <?php if ($anyI18n) admin_lang_switch(); ?>

  <div class="ad-tab-panel" data-tab-panel="general"<?= $tab !== 'general' ? ' hidden' : '' ?>>
    <section class="ad-box">
      <h2>General</h2>
      <div class="ad-field"><label>Nombre del sitio</label><input type="text" name="site_name" value="<?= cms_e($S['site_name'] ?? cms_config('name')) ?>"></div>
      <div class="ad-two">
        <div class="ad-field"><label>Nombre del autor (blog y datos estructurados)</label><input type="text" name="author_name" value="<?= cms_e($S['author_name'] ?? '') ?>"></div>
        <div class="ad-field"><label>País (código, para datos estructurados)</label><input type="text" name="country" value="<?= cms_e($S['country'] ?? 'MX') ?>" maxlength="2"></div>
      </div>
<?php foreach (cms_langs() as $l): if ($l === cms_default_lang()) continue; ?>
      <div class="ad-field"><label class="ad-check"><input type="checkbox" name="lang_<?= $l ?>" value="1"<?= !empty($S['languages'][$l]) ? ' checked' : '' ?>> Activar la versión en <?= cms_e($langNames[$l] ?? strtoupper($l)) ?> (/<?= $l ?>/)</label></div>
<?php endforeach; ?>
      <div class="ad-field"><label>URL canónica del sitio (con https y sin barra final; fija el dominio en canonical, sitemap y datos estructurados aunque entren por www)</label><input type="url" name="site_url" value="<?= cms_e($S['site_url'] ?? '') ?>" placeholder="https://midominio.com"></div>
      <div class="ad-field"><label>Otros sitios (enlaces del footer): una por línea, "Texto | URL"</label><textarea name="other_sites" rows="3"><?= cms_e(implode("\n", array_map(fn($o) => ($o['label'] ?? '') . ' | ' . ($o['url'] ?? ''), (array) ($S['other_sites'] ?? [])))) ?></textarea></div>
    </section>
  </div>

  <div class="ad-tab-panel" data-tab-panel="contacto"<?= $tab !== 'contacto' ? ' hidden' : '' ?>>
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
  </div>

  <div class="ad-tab-panel" data-tab-panel="marca"<?= $tab !== 'marca' ? ' hidden' : '' ?>>
    <section class="ad-box">
      <h2>Marca y SEO</h2>
      <?php admin_field('logo', ['type' => 'image', 'label' => 'Logotipo'], $S['logo'] ?? ''); ?>
      <?php admin_field('favicon', ['type' => 'image', 'label' => 'Favicon (PNG cuadrado)'], $S['favicon'] ?? ''); ?>
      <?php admin_field('og_image', ['type' => 'image', 'label' => 'Imagen al compartir en redes (páginas sin imagen propia; ideal 1200×630)'], $S['og_image'] ?? ''); ?>
      <div class="ad-field"><label>Código de verificación de Google Search Console</label><input type="text" name="google_verification" value="<?= cms_e($S['google_verification'] ?? '') ?>"></div>
      <p class="ad-help">Los títulos y descripciones por página se editan en cada elemento; los de portada y listados, en <a href="<?= admin_url('strings') ?>">Textos del sitio → SEO</a>.</p>
    </section>
  </div>

<?php foreach ($tabs as $k => $t): if (!$t['sections']) continue; ?>
  <div class="ad-tab-panel" data-tab-panel="<?= cms_e($k) ?>"<?= $tab !== $k ? ' hidden' : '' ?>>
<?php foreach ($t['sections'] as $sec => $fields): ?>
    <section class="ad-box">
      <h2><?= cms_e($sec) ?></h2>
      <div class="ad-fields-grid">
<?php foreach ((array) $fields as $name => $fd) admin_field($name, (array) $fd, $S[$name] ?? ($fd['default'] ?? '')); ?>
      </div>
    </section>
<?php endforeach; ?>
  </div>
<?php endforeach; ?>

  <div class="ad-savebar">
    <button class="ad-btn" type="submit">Guardar ajustes</button>
    <span class="ad-help">Guarda todas las pestañas a la vez.</span>
    <span data-dirty-note hidden>Hay cambios sin guardar</span>
  </div>
</form>
<?php admin_footer();
