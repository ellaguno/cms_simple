<?php
/**
 * Paquete lms — una lección: video, contenido, materiales, botón para marcarla como terminada (y pasar a la
 * siguiente), anterior/siguiente y el temario del curso con el avance. Si quien la ve no tiene acceso, en lugar del
 * contenido aparece cómo conseguirlo. El tema puede reemplazarla con templates/leccion.php o templates/lms/leccion.php.
 */
$user = lms_user();
$staff = lms_staff();
$course = lms_course(lms_lesson_course($item)) ?? ($staff ? lms_course(lms_lesson_course($item), false) : null);   // curso en borrador: solo para el panel
$lt = lms_lesson_type();
$ct = lms_course_type();
$title = (string) cms_f($item, 'title', $lang);
$courseTitle = $course ? (string) cms_f($course, 'title', $lang) : '';
$courseUrl = $course ? cms_url('item:' . $ct, $lang, $course['slug']) : cms_url('list:' . $ct, $lang);
$lessons = $course ? lms_lessons((string) $course['slug']) : [];
$steps = $course ? lms_steps((string) $course['slug']) : [];
$st = $course ? lms_stats((string) $course['slug'], $user) : lms_stats_from([], []);
$can = $course && lms_can_view($course, $item);
$pos = 0;   // número de lección (sin contar evaluaciones)
foreach ($lessons as $i => $l) if ($l['slug'] === $item['slug']) { $pos = $i + 1; break; }
$sp = null;   // lugar entre los pasos: anterior y siguiente pueden ser evaluaciones
foreach ($steps as $i => $l) if (!lms_is_quiz($l) && $l['slug'] === $item['slug']) { $sp = $i; break; }
$prev = $sp !== null && $sp > 0 ? $steps[$sp - 1] : null;
$next = $sp !== null && $sp + 1 < count($steps) ? $steps[$sp + 1] : null;
$lurl = fn(array $l) => lms_step_url($l, $lang);
$done = !empty($st['lessons'][$item['slug']]);
$module = (string) cms_f($item, 'module', $lang);
$listTitle = $t($ct . '_title', lms_tx('courses'));
// avance del video: quien entró como alumno (aunque también tenga sesión en el panel)
$S = lms_settings();
$track = $user && $can && $course && lms_has_video($item);
$watched = $track ? lms_watched($user['id'], (string) $course['slug'], (string) $item['slug']) : 0;
$needVideo = $track && !$done && $S['video_mode'] === 'exigir' && $watched < $S['video_pct'];
?>
<section class="phead lms-head lms-head-lesson">
  <div class="wrap phead-in">
    <nav class="crumbs" aria-label="Ruta"><a href="<?= cms_e(cms_url('list:' . $ct, $lang)) ?>"><?= cms_e($listTitle) ?></a><span>/</span><a href="<?= cms_e($courseUrl) ?>"><?= cms_e($courseTitle) ?></a><?php if ($module !== ''): ?><span>/</span><span><?= cms_e($module) ?></span><?php endif; ?></nav>
    <?php if ($pos): ?><p class="kicker lms-kicker"><?= cms_e(lms_tx('lesson', $pos, count($lessons))) ?><?php if (($du = (string) ($item['duration'] ?? '')) !== ''): ?> · <?= cms_e($du) ?><?php endif; ?></p><?php endif; ?>
    <h1 class="lms-lesson-title"><?= cms_e($title) ?><?php if ($done): ?> <span class="tag tag-ok"><?= cms_e(lms_tx('done')) ?></span><?php endif; ?></h1>
    <?php if (($sm = (string) cms_f($item, 'summary', $lang)) !== ''): ?><p class="lead"><?= cms_e($sm) ?></p><?php endif; ?>
    <?php if (($au = (string) cms_f($item, 'audience', $lang)) !== ''): ?><p class="lms-for"><strong><?= cms_e(lms_tx('for')) ?></strong> <?= cms_e($au) ?></p><?php endif; ?>
    <?= lms_notice() ?>
    <?php if ($staff): ?><p class="note lms-note"><?= cms_e($user ? lms_tx('staff_learner', (string) $user['name']) : lms_tx('staff_view')) ?></p><?php endif; ?>
  </div>
</section>
<section class="sec lms-sec">
  <div class="wrap">
    <div class="lms-course">
      <div class="lms-course-main">
<?php if (!$can): ?>
        <div class="card lms-locked">
          <span class="card-ico"><?= lms_icon('lock') ?></span>
          <h2 class="lms-h3"><?= cms_e(lms_tx('locked', $courseTitle)) ?></h2>
<?php if (!$user): ?>
          <p><?= cms_e(lms_tx('locked_login')) ?></p>
          <div class="btnrow">
            <a class="btn" href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= cms_e(lms_tx('login')) ?></a>
            <?php if (lms_settings()['signup'] && $course && lms_course_access($course) !== 'inscritos'): ?><a class="btn btn-ghost" href="<?= cms_e(lms_url('registro')) ?>"><?= cms_e(lms_tx('signup')) ?></a><?php endif; ?>
          </div>
<?php else: ?>
          <p><?= cms_e(lms_tx('only_enrolled')) ?></p>
          <div class="btnrow"><a class="btn btn-ghost" href="<?= cms_e($courseUrl) ?>"><?= lms_icon('back') ?> <?= cms_e(lms_tx('back_course')) ?></a></div>
<?php endif; ?>
        </div>
<?php else: ?>
<?php if ($track): ?>
        <div data-lms-watch data-url="<?= cms_e(lms_url('visto')) ?>" data-lesson="<?= cms_e($item['slug']) ?>" data-csrf="<?= cms_e(lms_csrf()) ?>" data-pct="<?= $watched ?>" data-need="<?= $S['video_pct'] ?>" data-done="<?= $done ? '1' : '0' ?>" data-key="<?= cms_e(substr(hash('sha256', $user['id'] . '|' . $item['slug']), 0, 16)) ?>" data-kind="<?= cms_e(preg_match('~vimeo~i', (string) $item['video']) ? 'vimeo' : (preg_match('~youtu~i', (string) $item['video']) ? 'youtube' : 'file')) ?>">
          <?= lms_video($item, $title) ?>
        </div>
<?php if ($S['video_mode'] !== 'manual' || $watched > 0): ?>
        <p class="lms-watch-note<?= $done ? ' is-done' : '' ?>" data-lms-watch-note data-tpl="<?= cms_e(str_replace('999', '%d', lms_tx('video_watched', 999))) ?>" data-done="<?= cms_e(lms_tx('done')) ?>"><?= cms_e($done ? lms_tx('done') : lms_tx('video_watched', $watched)) ?></p>
<?php endif; ?>
        <script src="<?= cms_e(cms_pack_asset(lms_pack(), 'assets/lms-watch.js')) ?>" defer></script>
<?php else: ?>
        <?= lms_video($item, $title) ?>
<?php endif; ?>
        <div class="rte lms-body"><?= cms_content((string) cms_f($item, 'body', $lang)) ?></div>
<?php if ($files = lms_files($item)): ?>
        <div class="lms-files">
          <h2 class="lms-h3"><?= cms_e(lms_tx('materials')) ?></h2>
          <ul>
<?php foreach ($files as [$fl, $fu]): ?>            <li><a href="<?= cms_e($fu) ?>" target="_blank" rel="noopener"><?= lms_icon('file') ?><span><?= cms_e($fl) ?></span></a></li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>
<?php if ($course && lms_can_take($course) && ($cfiles = lms_course_files($course))): ?>
        <div class="lms-files">
          <h2 class="lms-h3"><?= cms_e(lms_tx('course_materials')) ?></h2>
          <ul>
<?php foreach ($cfiles as [$fl, $fu]): ?>            <li><a href="<?= cms_e($fu) ?>" target="_blank" rel="noopener"><?= lms_icon('file') ?><span><?= cms_e($fl) ?></span></a></li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>
        <div class="lms-actions">
<?php if ($user): ?>
          <form method="post" action="<?= cms_e(lms_url('avance')) ?>" class="lms-mark">
            <?= lms_csrf_field() ?><input type="hidden" name="lesson" value="<?= cms_e($item['slug']) ?>">
<?php if ($done): ?>
            <input type="hidden" name="done" value="0">
            <span class="lms-done"><?= lms_icon('done') ?> <?= cms_e(lms_tx('done')) ?></span>
            <button class="btn btn-ghost btn-sm lms-undo" type="submit"><?= cms_e(lms_tx('mark_undo')) ?></button>
<?php else: ?>
            <input type="hidden" name="done" value="1">
            <?php if ($next && lms_can_view($course, $next)): ?><input type="hidden" name="next" value="<?= cms_e($lurl($next)) ?>"><?php endif; ?>
            <button class="btn" type="submit"<?= $needVideo ? ' disabled aria-disabled="true" data-lms-need-video' : '' ?>><?= lms_icon('done') ?> <?= cms_e($next && lms_can_view($course, $next) ? lms_tx(lms_is_quiz($next) ? 'mark_next_q' : 'mark_next') : lms_tx('mark_done')) ?></button>
<?php endif; ?>
          </form>
<?php if ($needVideo): ?>          <p class="note lms-need" data-lms-need-note><?= cms_e(lms_tx('video_need', $S['video_pct'])) ?></p><?php endif; ?>
<?php elseif (!$user && !$staff): ?>
          <p class="note"><a href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= cms_e(lms_tx('login_to_save')) ?></a></p>
<?php endif; ?>
        </div>
<?php endif; ?>
        <nav class="lms-pager" aria-label="Lecciones">
          <?php if ($prev): ?><a class="lms-pager-prev" href="<?= cms_e($lurl($prev)) ?>"><small><?= lms_icon('back') ?> <?= cms_e(lms_tx('prev')) ?></small><span><?= cms_e((string) cms_f($prev, 'title', $lang)) ?></span></a><?php else: ?><span></span><?php endif; ?>
          <?php if ($next): ?><a class="lms-pager-next" href="<?= cms_e($lurl($next)) ?>"><small><?= cms_e(lms_tx('next')) ?> <?= lms_icon('arrow') ?></small><span><?= cms_e((string) cms_f($next, 'title', $lang)) ?></span></a><?php else: ?><a class="lms-pager-next" href="<?= cms_e($courseUrl) ?>"><small><?= cms_e(lms_tx('back_course')) ?> <?= lms_icon('arrow') ?></small><span><?= cms_e($courseTitle) ?></span></a><?php endif; ?>
        </nav>
      </div>
      <aside class="lms-course-side">
        <div class="card lms-syllabus">
          <h2 class="lms-h3"><a href="<?= cms_e($courseUrl) ?>"><?= cms_e($courseTitle) ?></a></h2>
<?php if ($user && $st['total'] > 0): ?>
          <div class="lms-progress"><?= lms_bar($st['pct']) ?><small><?= cms_e(lms_progress_text($st)) ?></small></div>
<?php endif; ?>
          <?= $course ? lms_syllabus($course, $steps, $st, $lang, (string) $item['slug']) : '' ?>
        </div>
      </aside>
    </div>
  </div>
</section>
