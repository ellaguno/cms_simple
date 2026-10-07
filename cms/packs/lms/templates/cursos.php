<?php
/**
 * Paquete lms — listado de cursos (/cursos). El tema puede reemplazarlo con templates/cursos.php o templates/lms/cursos.php.
 * Disponibles: $lang, $page, $type, $def, $t (textos), $S (ajustes).
 */
$user = lms_user();
$courses = array_values(cms_items($type));
$title = $t($type . '_title', lms_tx('courses'));
$lead = $t($type . '_lead', lms_tx('courses_lead'));
?>
<section class="phead lms-head">
  <div class="wrap phead-in">
    <nav class="crumbs" aria-label="Ruta"><a href="<?= cms_e(cms_url('home', $lang)) ?>"><?= cms_e($t('crumb_home', $lang === 'en' ? 'Home' : 'Inicio')) ?></a><span>/</span><em style="font-style:normal"><?= cms_e($title) ?></em></nav>
    <h1><?= cms_e($title) ?></h1>
    <?php if ($lead !== ''): ?><p class="lead"><?= cms_e($lead) ?></p><?php endif; ?>
    <div class="btnrow lms-userbar">
<?php if ($user): ?>
      <a class="btn" href="<?= cms_e(lms_url()) ?>"><?= lms_icon('user') ?> <?= cms_e(lms_tx('my_classroom')) ?></a>
<?php else: ?>
      <a class="btn btn-ghost" href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= lms_icon('user') ?> <?= cms_e(lms_tx('login')) ?></a>
<?php endif; ?>
    </div>
  </div>
</section>
<section class="sec">
  <div class="wrap">
<?php if (!$courses): ?>
    <p class="lead"><?= cms_e(lms_tx('courses_empty')) ?></p>
<?php else: ?>
    <div class="grid g3 lms-grid">
<?php foreach ($courses as $c): echo lms_course_card($c, $lang, $user); endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>
