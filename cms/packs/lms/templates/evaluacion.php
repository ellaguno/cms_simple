<?php
/**
 * Paquete lms — una evaluación: instrucciones, datos (preguntas, calificación para aprobar, tiempo, intentos), el
 * formulario para contestarla (con reloj si tiene tiempo límite), el resultado del intento con su revisión según lo
 * que permita la evaluación, los intentos anteriores y el temario del curso. Quien tiene sesión en el panel ve las
 * preguntas con sus respuestas correctas y los avisos del formato. El tema puede reemplazarla con
 * templates/evaluacion.php o templates/lms/evaluacion.php.
 */
$user = lms_user();
$staff = lms_staff();
$cslug = lms_quiz_course($item);
$course = lms_course($cslug) ?? ($staff ? lms_course($cslug, false) : null);
$ct = lms_course_type();
$title = (string) cms_f($item, 'title', $lang);
$courseTitle = $course ? (string) cms_f($course, 'title', $lang) : '';
$courseUrl = $course ? cms_url('item:' . $ct, $lang, $course['slug']) : cms_url('list:' . $ct, $lang);
$quizUrl = cms_url('item:' . lms_quiz_type(), $lang, $item['slug']);
$steps = $course ? lms_steps($cslug) : [];
$st = $course ? lms_stats($cslug, $user) : lms_stats_from([], []);
$can = $course && lms_can_view($course, $item);
$gate = !$course || lms_quiz_gate_ok($item, $cslug, $st);
$learner = $user;   // quien entró como alumno presenta la evaluación aunque también tenga sesión en el panel
$answers = $staff && (!$user || isset($_GET['respuestas']));   // vista del panel con las respuestas correctas
$S = lms_quiz_state($item, $can && $gate ? $learner : null, $cslug);
$cfg = $S['cfg'];
$parsed = lms_quiz_questions($item, $lang);
$total = count($parsed['questions']);
$nq = $cfg['pick'] > 0 && $cfg['pick'] < $total ? $cfg['pick'] : $total;
$sp = null;
foreach ($steps as $i => $l) if (lms_is_quiz($l) && $l['slug'] === $item['slug']) { $sp = $i; break; }
$prev = $sp !== null && $sp > 0 ? $steps[$sp - 1] : null;
$next = $sp !== null && $sp + 1 < count($steps) ? $steps[$sp + 1] : null;
$module = (string) cms_f($item, 'module', $lang);
$listTitle = $t($ct . '_title', lms_tx('courses'));

// intento que se revisa (?intento=n) o, si ya hay intentos y no se pide uno nuevo, el último
$view = null;
foreach ($S['attempts'] as $a) if ((int) $a['n'] === (int) ($_GET['intento'] ?? 0)) $view = $a;
$fresh = isset($_GET['nuevo']);
if (!$view && !$fresh && !$S['open'] && $S['last']) $view = $S['last'];

// qué ve el alumno al revisar
$showMarks = $cfg['reveal'] !== 'nada';
$showCorrect = $cfg['reveal'] === 'siempre' || ($cfg['reveal'] === '' && ($S['passed'] !== '' || $S['left'] === 0));

// modo de la página
if (!$can) $mode = 'locked';
elseif (!$gate) $mode = 'gate';
elseif ($answers) $mode = 'staff';
elseif (!$total) $mode = 'empty';
elseif (!$learner) $mode = 'login';
elseif ($S['open']) $mode = 'form';
elseif ($S['can_start'] && !$view) $mode = $cfg['time'] > 0 ? 'start' : 'form';
else $mode = 'summary';

$tag = fn(string $status) => '<span class="tag ' . ['passed' => 'tag-ok', 'failed' => 'tag-warn', 'pending' => 'tag-steel'][$status] . '">' . cms_e(lms_tx(['passed' => 'q_passed', 'failed' => 'q_failed', 'pending' => 'q_pending'][$status])) . '</span>';

/** Un control de respuesta (formulario) o, en la vista del panel, la pregunta con su respuesta correcta. */
$renderQuestion = function (array $q, int $i, int $pos, int $n, bool $preview) use ($item, $learner): string {
    $pts = (float) $q['points'];
    $h = '<fieldset class="lms-q lms-q-' . $q['kind'] . '" data-q="' . $i . '"><legend class="lms-q-head"><span class="lms-q-n">' . $pos . '</span><span class="lms-q-text">' . lms_quiz_html($q['text']) . '</span>'
        . ($pts != 1 ? '<small class="lms-q-pts">' . cms_e(lms_tx('q_points', lms_quiz_pts($pts))) . '</small>' : '') . '</legend>';
    $name = 'a[' . $i . ']';
    $uid = $learner['id'] ?? 'staff';
    switch ($q['kind']) {
        case 'single': case 'multi':
            if ($q['kind'] === 'multi') $h .= '<p class="lms-q-hint">' . cms_e(lms_tx('q_multi_hint')) . '</p>';
            $h .= '<div class="lms-opts">';
            foreach (lms_quiz_option_order($item, $q, $uid, $n, $i) as $k) {
                [$txt, $ok] = $q['options'][$k];
                $in = $q['kind'] === 'multi' ? '<input type="checkbox" name="' . $name . '[]" value="' . $k . '"' . ($preview ? ' disabled' . ($ok ? ' checked' : '') : '') . '>'
                    : '<input type="radio" name="' . $name . '" value="' . $k . '"' . ($preview ? ' disabled' . ($ok ? ' checked' : '') : '') . '>';
                $h .= '<label class="lms-opt' . ($preview && $ok ? ' is-right' : '') . '">' . $in . '<span>' . lms_quiz_html($txt) . '</span></label>';
            }
            $h .= '</div>';
            break;
        case 'tf':
            $h .= '<div class="lms-opts lms-opts-row">';
            foreach (['v' => lms_tx('true'), 'f' => lms_tx('false')] as $k => $lab) {
                $ok = ($q['answers'][0] ?? '') === $k;
                $h .= '<label class="lms-opt' . ($preview && $ok ? ' is-right' : '') . '"><input type="radio" name="' . $name . '" value="' . $k . '"' . ($preview ? ' disabled' . ($ok ? ' checked' : '') : '') . '><span>' . cms_e($lab) . '</span></label>';
            }
            $h .= '</div>';
            break;
        case 'short': case 'num':
            $h .= '<input class="lms-q-input" type="text" name="' . $name . '" autocomplete="off" maxlength="300" placeholder="' . cms_e(lms_tx('q_answer_ph')) . '"' . ($q['kind'] === 'num' ? ' inputmode="decimal"' : '') . ($preview ? ' disabled value="' . cms_e(lms_quiz_correct_text($q)) . '"' : '') . '>';
            break;
        case 'open':
            $h .= '<textarea class="lms-q-input" name="' . $name . '" rows="5" maxlength="8000" placeholder="' . cms_e(lms_tx('q_answer_ph')) . '"' . ($preview ? ' disabled' : '') . '></textarea>';
            break;
    }
    if ($preview && $q['explain'] !== '') $h .= '<p class="lms-q-explain">' . lms_quiz_html($q['explain']) . '</p>';
    return $h . '</fieldset>';
};

/** Revisión de un intento, pregunta por pregunta. */
$renderReview = function (array $a) use ($item, $showCorrect): string {
    $qs = lms_quiz_questions($item, (string) ($a['lang'] ?? cms_default_lang()));
    $h = ($a['hash'] ?? '') !== '' && $a['hash'] !== $qs['hash'] ? '<p class="note lms-note">' . cms_e(lms_tx('q_changed')) . '</p>' : '';
    $h .= '<ol class="lms-review">';
    foreach ((array) $a['set'] as $pos => $i) {
        $q = $qs['questions'][$i] ?? null;
        if (!$q) continue;
        $m = $a['marks'][$i] ?? null;
        $pts = (float) $q['points'];
        $cls = $m === null ? 'pending' : ($m >= $pts ? 'right' : ($m > 0 ? 'partial' : 'wrong'));
        $label = ['pending' => 'q_pending', 'right' => 'q_right', 'partial' => 'q_partial', 'wrong' => 'q_wrong'][$cls];
        $ans = lms_quiz_answer_text($q, $a['answers'][$i] ?? '');
        $h .= '<li class="lms-rq is-' . $cls . '"><div class="lms-rq-head">' . lms_icon($cls === 'right' ? 'done' : ($cls === 'pending' ? 'clock' : 'x'))
            . '<span class="lms-q-text">' . lms_quiz_html($q['text']) . '</span><small>' . cms_e(lms_tx($label)) . ($m !== null ? ' · ' . lms_quiz_pts((float) $m) . '/' . lms_quiz_pts($pts) : '') . '</small></div>';
        $h .= '<p class="lms-rq-ans"><strong>' . cms_e(lms_tx('q_your')) . '</strong> ' . ($ans !== '' ? nl2br(cms_e($ans), false) : '<em>' . cms_e(lms_tx('q_blank')) . '</em>') . '</p>';
        if ($showCorrect && $q['kind'] !== 'open' && $cls !== 'right') $h .= '<p class="lms-rq-ok"><strong>' . cms_e(lms_tx('q_correct')) . '</strong> ' . cms_e(lms_quiz_correct_text($q)) . '</p>';
        if ($showCorrect && $q['explain'] !== '') $h .= '<p class="lms-q-explain">' . lms_quiz_html($q['explain']) . '</p>';
        if (!empty($a['notes'][$i])) $h .= '<p class="lms-rq-note"><strong>' . cms_e(lms_tx('q_feedback')) . '</strong> ' . nl2br(cms_e((string) $a['notes'][$i]), false) . '</p>';
        $h .= '</li>';
    }
    return $h . '</ol>';
};
?>
<section class="phead lms-head lms-head-lesson">
  <div class="wrap phead-in">
    <nav class="crumbs" aria-label="Ruta"><a href="<?= cms_e(cms_url('list:' . $ct, $lang)) ?>"><?= cms_e($listTitle) ?></a><span>/</span><a href="<?= cms_e($courseUrl) ?>"><?= cms_e($courseTitle) ?></a><?php if ($module !== ''): ?><span>/</span><span><?= cms_e($module) ?></span><?php endif; ?></nav>
    <p class="kicker lms-kicker"><?= cms_e(lms_tx('quiz')) ?><?php if ($nq): ?> · <?= cms_e($nq === 1 ? lms_tx('q_count_1') : lms_tx('q_count', $nq)) ?><?php endif; ?></p>
    <h1 class="lms-lesson-title"><?= cms_e($title) ?><?php if ($S['passed'] !== ''): ?> <span class="tag tag-ok"><?= cms_e(lms_tx('q_passed')) ?></span><?php endif; ?></h1>
    <p class="lms-tags">
      <span class="tag tag-steel"><?= cms_e($cfg['pass'] > 0 ? lms_tx('q_pass', $cfg['pass']) : lms_tx('q_practice')) ?></span>
      <?php if ($cfg['time'] > 0): ?><span class="tag tag-steel"><?= cms_e(lms_tx('q_time', $cfg['time'])) ?></span><?php endif; ?>
      <span class="tag tag-steel"><?= cms_e(!$S['allowed'] ? lms_tx('q_attempts_inf') : ($learner ? lms_tx('q_attempts', $S['used'], $S['allowed']) : ($S['allowed'] === 1 ? lms_tx('q_attempts_1') : lms_tx('q_attempts_max', $S['allowed'])))) ?></span>
      <?php if ($S['best'] !== null): ?><span class="tag tag-steel"><?= cms_e(lms_tx('q_best', $S['best'])) ?></span><?php endif; ?>
    </p>
    <?= lms_notice() ?>
    <?php if ($answers): ?><p class="note lms-note"><?= cms_e(lms_tx('q_staff')) ?></p><?php elseif ($staff): ?><p class="note lms-note"><?= cms_e(lms_tx('staff_learner', (string) $user['name'])) ?> <a href="<?= cms_e($quizUrl . '?respuestas=1') ?>"><?= cms_e(lms_tx('q_answers_link')) ?></a></p><?php endif; ?>
  </div>
</section>
<section class="sec lms-sec">
  <div class="wrap">
    <div class="lms-course">
      <div class="lms-course-main lms-quiz">
<?php if ($mode === 'locked'): ?>
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
<?php elseif ($mode === 'gate'): ?>
        <div class="card lms-locked">
          <span class="card-ico"><?= lms_icon('lock') ?></span>
          <h2 class="lms-h3"><?= cms_e(lms_tx('q_gate')) ?></h2>
          <?php if ($st['next']): ?><div class="btnrow"><a class="btn" href="<?= cms_e(lms_step_url($st['next'], $lang)) ?>"><?= cms_e(lms_tx('q_gate_next')) ?> <?= lms_icon('arrow') ?></a></div><?php endif; ?>
        </div>
<?php else: ?>
<?php if ($mode !== 'summary' && ($intro = (string) cms_f($item, 'intro', $lang)) !== ''): ?>
        <div class="rte lms-body"><?= cms_content($intro) ?></div>
<?php endif; ?>
<?php if ($mode === 'staff'): ?>
<?php foreach ($parsed['warnings'] as $w): ?>        <p class="form-msg err lms-msg"><?= cms_e($w) ?></p>
<?php endforeach; ?>
<?php if (!$total): ?>        <p class="note"><?= cms_e(lms_tx('q_empty')) ?></p><?php endif; ?>
        <div class="lms-qform is-preview">
<?php foreach ($parsed['questions'] as $i => $q) echo $renderQuestion($q, $i, $i + 1, 0, true); ?>
        </div>
<?php elseif ($mode === 'empty'): ?>
        <p class="note"><?= cms_e(lms_tx('q_empty')) ?></p>
<?php elseif ($mode === 'login'): ?>
        <div class="card lms-locked">
          <span class="card-ico"><?= lms_icon('quiz') ?></span>
          <h2 class="lms-h3"><?= cms_e(lms_tx('q_login')) ?></h2>
          <div class="btnrow">
            <a class="btn" href="<?= cms_e(lms_url('entrar', null, ['r' => lms_here()])) ?>"><?= cms_e(lms_tx('login')) ?></a>
            <?php if (lms_settings()['signup'] && $course && lms_course_access($course) !== 'inscritos'): ?><a class="btn btn-ghost" href="<?= cms_e(lms_url('registro')) ?>"><?= cms_e(lms_tx('signup')) ?></a><?php endif; ?>
          </div>
        </div>
<?php elseif ($mode === 'start'): ?>
        <form method="post" action="<?= cms_e(lms_url('evaluacion')) ?>" class="card lms-start">
          <?= lms_csrf_field() ?><input type="hidden" name="quiz" value="<?= cms_e($item['slug']) ?>"><input type="hidden" name="action" value="start">
          <p><?= lms_icon('clock') ?> <?= cms_e(lms_tx('q_start_timed', $cfg['time'])) ?></p>
          <button class="btn" type="submit"><?= cms_e(lms_tx('q_start')) ?> <?= lms_icon('arrow') ?></button>
        </form>
<?php elseif ($mode === 'form'):
    if ($S['open']) { $n = (int) $S['open']['n']; $set = array_map('intval', (array) $S['open']['set']); $qlang = (string) $S['open']['lang']; }
    else { $n = $S['used'] + 1; $qlang = $lang; $set = lms_quiz_make_set($item, $total, $learner['id'], $n); }
    $qset = lms_quiz_questions($item, $qlang)['questions'];
    $setStr = implode(',', $set); ?>
        <form method="post" action="<?= cms_e(lms_url('evaluacion')) ?>" class="lms-qform" data-lms-quiz data-confirm-blank="<?= cms_e(lms_tx('q_unanswered')) ?>">
          <?= lms_csrf_field() ?><input type="hidden" name="quiz" value="<?= cms_e($item['slug']) ?>">
<?php if (!$S['open']): ?>
          <input type="hidden" name="set" value="<?= cms_e($setStr) ?>"><input type="hidden" name="qlang" value="<?= cms_e($qlang) ?>"><input type="hidden" name="t0" value="<?= time() ?>">
          <input type="hidden" name="sig" value="<?= cms_e(lms_quiz_sign($learner['id'], (string) $item['slug'], $n, $setStr, $qlang)) ?>">
<?php else: ?>
          <div class="lms-timer" data-deadline="<?= (int) $S['open']['deadline'] ?>" data-now="<?= time() ?>" role="timer" aria-live="off"><?= lms_icon('clock') ?> <span><?= cms_e(lms_tx('q_time_left')) ?></span> <b>--:--</b></div>
<?php endif; ?>
<?php foreach ($set as $pos => $i) if (isset($qset[$i])) echo $renderQuestion($qset[$i], $i, $pos + 1, $n, false); ?>
          <div class="lms-actions"><button class="btn" type="submit"><?= lms_icon('done') ?> <?= cms_e(lms_tx('q_submit')) ?></button></div>
        </form>
<?php else: /* summary: resultado del intento que se revisa */ ?>
<?php if ($view): ?>
        <div class="card lms-result is-<?= cms_e((string) $view['status']) ?>">
          <p class="lms-result-head"><span><?= cms_e(lms_tx('q_result', (int) $view['n'])) ?></span> <?= $view['status'] !== 'pending' ? $tag((string) $view['status']) : '' ?><?php if (!empty($view['late'])): ?> <span class="tag tag-warn"><?= cms_e(lms_tx('q_late')) ?></span><?php endif; ?></p>
          <p class="lms-score"><b><?= cms_e(lms_quiz_pct_text($view)) ?></b><?php if ($view['status'] !== 'pending'): ?><small><?= cms_e(lms_tx('q_score', lms_quiz_pts((float) $view['points']), lms_quiz_pts((float) $view['max']))) ?></small><?php endif; ?></p>
          <?php if ($view['status'] === 'pending'): ?><p class="note"><?= cms_e(lms_tx('q_wait')) ?></p><?php endif; ?>
          <div class="btnrow">
            <?php if ($S['can_start']): ?><a class="btn<?= $view['status'] === 'passed' ? ' btn-ghost' : '' ?>" href="<?= cms_e($quizUrl . '?nuevo=1') ?>"><?= cms_e(lms_tx('q_retry')) ?></a><?php endif; ?>
            <?php if ($S['passed'] !== '' || $cfg['pass'] === 0): ?><a class="btn" href="<?= cms_e($next ? lms_step_url($next, $lang) : $courseUrl) ?>"><?= cms_e(lms_tx('q_next')) ?> <?= lms_icon('arrow') ?></a><?php endif; ?>
          </div>
          <?php if (!$S['can_start'] && $S['left'] === 0 && $S['passed'] === ''): ?><p class="note"><?= cms_e(lms_tx('q_no_more')) ?></p><?php endif; ?>
        </div>
<?php if ($showMarks) echo $renderReview($view); ?>
<?php elseif ($S['pending']): ?>
        <p class="note"><?= cms_e(lms_tx('q_wait')) ?></p>
<?php elseif ($S['left'] === 0): ?>
        <p class="note"><?= cms_e(lms_tx('q_no_more')) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if ($learner && $S['attempts']): ?>
        <div class="lms-attempts">
          <h2 class="lms-h3"><?= cms_e(lms_tx('q_history')) ?></h2>
          <table>
            <thead><tr><th><?= cms_e(lms_tx('q_attempt')) ?></th><th><?= cms_e(lms_tx('q_date')) ?></th><th><?= cms_e(lms_tx('q_grade')) ?></th><th><?= cms_e(lms_tx('q_status')) ?></th><th></th></tr></thead>
            <tbody>
<?php foreach (array_reverse($S['attempts']) as $a): ?>
              <tr<?= $view && (int) $view['n'] === (int) $a['n'] ? ' class="is-current"' : '' ?>><td><?= (int) $a['n'] ?></td><td><?= cms_e(lms_date((string) $a['end'])) ?> <?= cms_e(substr((string) $a['end'], 11, 5)) ?></td><td><?= cms_e(lms_quiz_pct_text($a)) ?></td><td><?= $tag((string) $a['status']) ?></td>
                <td><?php if (!$view || (int) $view['n'] !== (int) $a['n']): ?><a href="<?= cms_e($quizUrl . '?intento=' . (int) $a['n']) ?>"><?= cms_e(lms_tx('q_review')) ?></a><?php endif; ?></td></tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
<?php endif; ?>
        <nav class="lms-pager" aria-label="Lecciones">
          <?php if ($prev): ?><a class="lms-pager-prev" href="<?= cms_e(lms_step_url($prev, $lang)) ?>"><small><?= lms_icon('back') ?> <?= cms_e(lms_tx('prev')) ?></small><span><?= cms_e((string) cms_f($prev, 'title', $lang)) ?></span></a><?php else: ?><span></span><?php endif; ?>
          <?php if ($next): ?><a class="lms-pager-next" href="<?= cms_e(lms_step_url($next, $lang)) ?>"><small><?= cms_e(lms_tx('next')) ?> <?= lms_icon('arrow') ?></small><span><?= cms_e((string) cms_f($next, 'title', $lang)) ?></span></a><?php else: ?><a class="lms-pager-next" href="<?= cms_e($courseUrl) ?>"><small><?= cms_e(lms_tx('back_course')) ?> <?= lms_icon('arrow') ?></small><span><?= cms_e($courseTitle) ?></span></a><?php endif; ?>
        </nav>
      </div>
      <aside class="lms-course-side">
        <div class="card lms-syllabus">
          <h2 class="lms-h3"><a href="<?= cms_e($courseUrl) ?>"><?= cms_e($courseTitle) ?></a></h2>
<?php if ($learner && $st['total'] > 0): ?>
          <div class="lms-progress"><?= lms_bar($st['pct']) ?><small><?= cms_e(lms_progress_text($st)) ?></small></div>
<?php endif; ?>
          <?= $course ? lms_syllabus($course, $steps, $st, $lang, 'q:' . $item['slug']) : '' ?>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php if ($mode === 'form'): ?>
<script>
(function () {
  var f = document.querySelector('[data-lms-quiz]'); if (!f) return;
  var sent = false;
  function blank() {
    var miss = 0;
    f.querySelectorAll('.lms-q').forEach(function (q) {
      var ok = false;
      q.querySelectorAll('input,textarea').forEach(function (el) { if ((el.type === 'radio' || el.type === 'checkbox') ? el.checked : el.value.trim() !== '') ok = true; });
      if (!ok) miss++;
    });
    return miss;
  }
  f.addEventListener('submit', function (e) {
    if (sent) { e.preventDefault(); return; }
    if (!f.dataset.auto && blank() && !confirm(f.dataset.confirmBlank)) { e.preventDefault(); return; }
    sent = true;
    var b = f.querySelector('button[type=submit]'); if (b) b.disabled = true;
  });
  var t = f.querySelector('.lms-timer'); if (!t) return;
  var end = Date.now() + (parseInt(t.dataset.deadline, 10) - parseInt(t.dataset.now, 10)) * 1000, out = t.querySelector('b');
  (function tick() {
    var s = Math.max(0, Math.round((end - Date.now()) / 1000));
    out.textContent = Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2);
    t.classList.toggle('is-low', s <= 60);
    if (s <= 0) { f.dataset.auto = '1'; if (!sent) { sent = true; f.submit(); } return; }
    setTimeout(tick, 500);
  })();
})();
</script>
<?php endif; ?>
