<?php
/**
 * Paquete lms — /aula: el tablero del alumno. Sus cursos con el avance y el botón para seguir, y los demás cursos
 * a los que puede entrar. El tema puede reemplazarlo con templates/lms/aula.php.
 */
$user = lms_user();
$mine = []; $others = [];
$prog = lms_progress($user['id'])['courses'];
foreach (lms_visible_courses($user) as $c) {
    $s = (string) $c['slug'];
    if (!empty($prog[$s]['enrolled']) || !empty($prog[$s]['lessons'])) $mine[] = $c;
    elseif (lms_course_access($c) !== 'inscritos') $others[] = $c;
}
?>
<section class="phead lms-head">
  <div class="wrap phead-in">
    <p class="kicker lms-kicker"><?= cms_e(lms_tx('my_classroom')) ?></p>
    <h1><?= cms_e(lms_tx('hello', (string) strtok((string) $user['name'], ' '))) ?></h1>
    <p class="lead"><?= cms_e(lms_tx('dash_lead')) ?></p>
    <?= lms_notice() ?>
    <div class="btnrow lms-userbar">
      <a class="btn btn-ghost btn-sm" href="<?= cms_e(lms_url('cuenta')) ?>"><?= lms_icon('user') ?> <?= cms_e(lms_tx('account')) ?></a>
      <form method="post" action="<?= cms_e(lms_url('salir')) ?>" class="lms-inline"><?= lms_csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= cms_e(lms_tx('logout')) ?></button></form>
    </div>
  </div>
</section>
<section class="sec">
  <div class="wrap">
    <div class="sec-head"><h2 style="font-size:1.5rem"><?= cms_e(lms_tx('my_courses')) ?></h2></div>
<?php if (!$mine): ?>
    <p class="lead"><?= cms_e(lms_tx('no_courses')) ?></p>
<?php else: ?>
    <div class="lms-grid-c">
<?php foreach ($mine as $c) echo lms_course_card($c, $lang, $user); ?>
    </div>
<?php endif; ?>
  </div>
</section>
<?php
$certs = [];
foreach ($mine as $c) if (!empty($prog[$c['slug']]['completed']) && lms_cert_on($c)) $certs[] = $c;
if ($certs): ?>
<section class="sec">
  <div class="wrap">
    <div class="sec-head"><h2 style="font-size:1.5rem"><?= cms_e(lms_tx('my_certs')) ?></h2></div>
    <ul class="lms-certs">
<?php foreach ($certs as $c): $cc = (array) ($prog[$c['slug']]['cert'] ?? []); ?>
      <li><a href="<?= cms_e(lms_cert_url((string) $c['slug'])) ?>" target="_blank" rel="noopener"><?= lms_icon('cert') ?><span><strong><?= cms_e((string) cms_f($c, 'title', $lang)) ?></strong><small><?= cms_e(lms_tx('completed_on', lms_date((string) $prog[$c['slug']]['completed']))) ?><?= !empty($cc['code']) ? ' · ' . cms_e((string) $cc['code']) : '' ?></small></span></a></li>
<?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>
<?php if ($others): ?>
<section class="sec sec-alt">
  <div class="wrap">
    <div class="sec-head"><h2 style="font-size:1.5rem"><?= cms_e(lms_tx('other_courses')) ?></h2></div>
    <div class="lms-grid-c">
<?php foreach ($others as $c) echo lms_course_card($c, $lang, $user); ?>
    </div>
  </div>
</section>
<?php endif; ?>
