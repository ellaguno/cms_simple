<?php
/**
 * Paquete lms — /aula: el tablero del alumno. Sus cursos con el avance y el botón para seguir, y los demás cursos
 * a los que puede entrar (los primeros seis, con las industrias y el enlace al listado completo cuando hay más).
 * El tema puede reemplazarlo con templates/lms/aula.php.
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
<?php if ($others):
$list = cms_url('list:' . lms_course_type(), $lang);
$inds = [];
foreach ($others as $c) foreach (lms_course_industries($c, $lang) as $k => $n) $inds[$k] = $n;
$inds = array_intersect_key(lms_industries($lang), $inds);
$tops = [];
foreach ($others as $c) foreach (array_keys(lms_course_tracks($c, $lang)) as $k) $tops[(string) strtok($k, '/')] = 1;
$tops = array_intersect_key(array_filter(lms_tracks($lang), fn($x) => $x['parent'] === ''), $tops); ?>
<section class="sec sec-alt">
  <div class="wrap">
    <div class="sec-head"><h2 style="font-size:1.5rem"><?= cms_e(lms_tx('other_courses')) ?></h2></div>
<?php if (count($tops) > 1): ?>
    <div class="lms-chips" aria-label="<?= cms_e(lms_setting_text('lms_tracks_label') ?: lms_tx('tracks')) ?>">
<?php foreach ($tops as $k => $tr): ?>      <a class="lms-chip" href="<?= cms_e($list . '?track=' . rawurlencode($k)) ?>"><?= cms_e($tr['name']) ?></a>
<?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if (count($inds) > 1): ?>
    <div class="lms-chips" aria-label="<?= cms_e(lms_tx('by_industry')) ?>">
<?php foreach ($inds as $k => $n): ?>      <a class="lms-chip" href="<?= cms_e($list . '?industria=' . rawurlencode($k)) ?>"><?= cms_e($n) ?></a>
<?php endforeach; ?>
    </div>
<?php endif; ?>
    <div class="lms-grid-c">
<?php foreach (array_slice($others, 0, 6) as $c) echo lms_course_card($c, $lang, $user); ?>
    </div>
<?php if (count($others) > 6): ?>
    <p class="lms-more"><a class="btn btn-ghost" href="<?= cms_e($list) ?>"><?= cms_e(lms_tx('all_courses_n', count($others))) ?> <?= lms_icon('arrow') ?></a></p>
<?php endif; ?>
  </div>
</section>
<?php endif; ?>
