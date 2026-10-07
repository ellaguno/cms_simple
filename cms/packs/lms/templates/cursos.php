<?php
/**
 * Paquete lms — listado de cursos (/cursos), al estilo del catálogo de capacitación: encabezado con etiqueta, título
 * con *énfasis*, texto y cifras; rejilla de tarjetas con tapa; y un bloque opcional "Cómo están hechos". Los textos
 * se editan en Ajustes → Aula. El tema puede reemplazarlo con templates/cursos.php o templates/lms/cursos.php.
 * Disponibles: $lang, $page, $type, $def, $t (textos), $S (ajustes).
 */
$user = lms_user();
$courses = array_values(cms_items($type));
$S0 = cms_settings();
$kicker = lms_setting_text('lms_hero_kicker', 'hero_kicker');
$title = lms_setting_text('lms_hero_title', 'hero_title');
$lead = lms_setting_text('lms_hero_lead', 'hero_lead');
$numbers = (!isset($S0['lms_hero_numbers']) || !empty($S0['lms_hero_numbers'])) && $courses ? lms_catalog_numbers($courses) : [];
$listLabel = $t($type . '_title', lms_tx('courses'));
$features = [];
foreach (cms_lines(lms_setting_text('lms_features')) as $line) {
    $p = array_map('trim', explode('|', $line, 3));
    if (count($p) === 3) $features[] = $p;
    elseif (count($p) === 2) $features[] = ['', $p[0], $p[1]];
}
?>
<section class="lms-hero">
  <div class="wrap lms-hero-in">
    <div class="lms-hero-top">
      <nav class="crumbs" aria-label="Ruta"><a href="<?= cms_e(cms_url('home', $lang)) ?>"><?= cms_e($t('crumb_home', $lang === 'en' ? 'Home' : 'Inicio')) ?></a><span>/</span><em style="font-style:normal"><?= cms_e($listLabel) ?></em></nav>
<?php if ($user): ?>
      <a class="lms-hero-user" href="<?= cms_e(lms_url()) ?>"><?= lms_icon('user') ?> <?= cms_e(lms_tx('my_classroom')) ?></a>
<?php else: ?>
      <a class="lms-hero-user" href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= lms_icon('user') ?> <?= cms_e(lms_tx('login')) ?></a>
<?php endif; ?>
    </div>
    <?php if ($kicker !== ''): ?><span class="lms-kicker-pill"><?= cms_e($kicker) ?></span><?php endif; ?>
    <h1><?= lms_emph($title) ?></h1>
    <?php if ($lead !== ''): ?><p class="lead"><?= cms_e($lead) ?></p><?php endif; ?>
<?php if ($numbers): ?>
    <div class="lms-numbers">
<?php foreach ($numbers as [$n, $label]): ?>      <div><b><?= cms_e((string) $n) ?></b><span><?= cms_e($label) ?></span></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>
<section class="sec lms-catalog">
  <div class="wrap">
    <h2 class="lms-eyebrow"><?= cms_e($listLabel) ?></h2>
<?php if (!$courses): ?>
    <p class="lead"><?= cms_e(lms_tx('courses_empty')) ?></p>
<?php else: ?>
    <div class="lms-grid-c">
<?php foreach ($courses as $c) echo lms_course_card($c, $lang, $user); ?>
    </div>
<?php endif; ?>
<?php if ($features): ?>
    <div class="lms-features" style="--n:<?= min(4, count($features)) ?>">
      <div class="lms-features-head">
        <h2><?= cms_e(lms_setting_text('lms_features_title') ?: ($lang === 'en' ? 'How they are made' : 'Cómo están hechos')) ?></h2>
        <?php if (($fs = lms_setting_text('lms_features_sub')) !== ''): ?><p><?= cms_e($fs) ?></p><?php endif; ?>
      </div>
<?php foreach ($features as [$ico, $ft, $fx]): ?>
      <div class="lms-feature"><?php if (($svg = lms_svg($ico)) !== ''): ?><span class="lms-feature-ico"><?= $svg ?></span><?php endif; ?><h3><?= cms_e($ft) ?></h3><p><?= cms_e($fx) ?></p></div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>
