<?php
/**
 * Paquete lms — página de un curso: descripción, avance de quien lo ve, botón para empezar o continuar y temario
 * con el estado de cada lección. El tema puede reemplazarla con templates/curso.php o templates/lms/curso.php.
 */
$user = lms_user();
$staff = lms_staff();
$slug = (string) $item['slug'];
$lessons = lms_lessons($slug);
$steps = lms_steps($slug);
$nq = count($steps) - count($lessons);
$st = lms_stats($slug, $user);
$access = lms_course_access($item);
$canTake = lms_can_take($item);
$title = (string) cms_f($item, 'title', $lang);
$lt = lms_lesson_type();
$lurl = fn(array $l) => lms_step_url($l, $lang);
$first = $steps[0] ?? null;
$listTitle = $t($type . '_title', $def['label'] ?? lms_tx('courses'));
$goals = (array) cms_f($item, 'goals', $lang, []);
$S_contact = cms_url('home', $lang);
foreach (cms_config('pages') as $pk => $pd) if (($pd['schema'] ?? '') === 'ContactPage') { $S_contact = cms_url('page:' . $pk, $lang); break; }
?>
<section class="phead lms-head">
  <div class="wrap phead-in">
    <nav class="crumbs" aria-label="Ruta"><a href="<?= cms_e(cms_url('home', $lang)) ?>"><?= cms_e($t('crumb_home', $lang === 'en' ? 'Home' : 'Inicio')) ?></a><span>/</span><a href="<?= cms_e(cms_url('list:' . $type, $lang)) ?>"><?= cms_e($listTitle) ?></a><span>/</span><em style="font-style:normal"><?= cms_e($title) ?></em></nav>
    <h1><?= cms_e($title) ?></h1>
    <?php if (($ex = (string) cms_f($item, 'excerpt', $lang)) !== ''): ?><p class="lead"><?= cms_e($ex) ?></p><?php endif; ?>
    <p class="lms-tags">
      <?php if (($lv = (string) cms_f($item, 'level', $lang)) !== ''): ?><span class="tag tag-steel"><?= cms_e($lv) ?></span><?php endif; ?>
      <?php if (($du = (string) cms_f($item, 'duration', $lang)) !== ''): ?><span class="tag tag-steel"><?= cms_e($du) ?></span><?php endif; ?>
      <?php if (!empty($item['soon'])): ?><span class="tag tag-warn"><?= cms_e(lms_tx('soon')) ?></span><?php endif; ?>
      <span class="tag tag-steel"><?= cms_e(count($lessons) === 1 ? lms_tx('lesson_1') : lms_tx('lessons_n', count($lessons))) ?></span>
      <?php if ($nq): ?><span class="tag tag-steel"><?= cms_e($nq === 1 ? lms_tx('quiz_1') : lms_tx('quizzes_n', $nq)) ?></span><?php endif; ?>
      <?php if ($st['completed'] !== ''): ?><span class="tag tag-ok"><?= cms_e(lms_tx('completed')) ?></span><?php endif; ?>
    </p>
    <?php if (($au = (string) cms_f($item, 'audience', $lang)) !== ''): ?><p class="lms-for"><strong><?= cms_e(lms_tx('for')) ?></strong> <?= cms_e($au) ?></p><?php endif; ?>
    <?php if ($topics = array_filter((array) cms_f($item, 'topics', $lang, []))): ?><p class="lms-topics"><?php foreach ($topics as $tp): ?><span><?= cms_e((string) $tp) ?></span><?php endforeach; ?></p><?php endif; ?>
<?php if ($user && $st['started'] && $st['total'] > 0): ?>
    <div class="lms-progress"><?= lms_bar($st['pct'], lms_progress_text($st)) ?><small><?= cms_e(lms_progress_text($st)) ?></small></div>
<?php endif; ?>
    <?= lms_notice() ?>
    <div class="btnrow">
<?php if ($first && $canTake): $to = $st['next'] ?? $first; ?>
      <a class="btn" href="<?= cms_e($lurl($to)) ?>"><?= cms_e(!$st['started'] ? lms_tx('start') : ($st['next'] ? lms_tx('continue') : lms_tx('review'))) ?> <?= lms_icon('arrow') ?></a>
      <?php if (!$user && !$staff): ?><a class="btn btn-ghost" href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= cms_e(lms_tx('login_to_save')) ?></a><?php endif; ?>
<?php elseif ($first && !$user): ?>
      <a class="btn" href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= cms_e(lms_tx('login_to_start')) ?> <?= lms_icon('arrow') ?></a>
      <?php if (lms_settings()['signup'] && $access !== 'inscritos'): ?><a class="btn btn-ghost" href="<?= cms_e(lms_url('registro')) ?>"><?= cms_e(lms_tx('signup')) ?></a><?php endif; ?>
<?php elseif ($first && empty($item['soon'])): ?>
      <a class="btn btn-ghost" href="<?= cms_e($S_contact) ?>"><?= cms_e(lms_tx('ask_enroll')) ?></a>
<?php endif; ?>
    </div>
    <?php if (!empty($item['soon'])): ?><p class="note lms-note"><?= cms_e(lms_tx('soon_text')) ?></p><?php endif; ?>
    <?php if ($first && !$canTake && $access === 'inscritos' && empty($item['soon'])): ?><p class="note lms-note"><?= cms_e(lms_tx('only_enrolled')) ?></p><?php endif; ?>
    <?php if ($st['completed'] !== ''): ?><p class="note lms-note"><?= cms_e(lms_tx('completed_on', lms_date($st['completed']))) ?></p><?php endif; ?>
    <?php if ($user && $st['completed'] !== '' && lms_cert_on($item)): ?><div class="btnrow lms-cert-row"><a class="btn" href="<?= cms_e(lms_cert_url($slug)) ?>" target="_blank" rel="noopener"><?= lms_icon('cert') ?> <?= cms_e(lms_tx('cert_get')) ?></a></div><?php endif; ?>
    <?php if ($staff): ?><p class="note lms-note"><?= cms_e($user ? lms_tx('staff_learner', (string) $user['name']) : lms_tx('staff_view')) ?></p><?php endif; ?>
  </div>
</section>
<section class="sec">
  <div class="wrap">
    <div class="lms-course">
      <div class="lms-course-main">
<?php if (!empty($item['image'])): ?>
        <figure class="lms-cover"><?= cms_picture((string) $item['image'], $title, '', true) ?></figure>
<?php endif; ?>
        <div class="rte"><?= cms_content((string) cms_f($item, 'body', $lang)) ?></div>
<?php if ($canTake && ($cfiles = lms_course_files($item))): ?>
        <div class="lms-files">
          <h2 class="lms-h3"><?= cms_e(lms_tx('course_materials')) ?></h2>
          <ul>
<?php foreach ($cfiles as [$fl, $fu]): ?>            <li><a href="<?= cms_e($fu) ?>" target="_blank" rel="noopener"><?= lms_icon('file') ?><span><?= cms_e($fl) ?></span></a></li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>
<?php if ($goals): ?>
        <h2 class="lms-h2"><?= cms_e(lms_tx('you_learn')) ?></h2>
        <ul class="lms-goals">
<?php foreach ($goals as $g): ?>          <li><?= lms_icon('done') ?><span><?= cms_e((string) $g) ?></span></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </div>
      <aside class="lms-course-side">
        <div class="card lms-syllabus">
          <h2 class="lms-h3"><?= cms_e(lms_tx('contents')) ?></h2>
          <?= lms_syllabus($item, $steps, $st, $lang) ?>
        </div>
      </aside>
    </div>
  </div>
</section>
