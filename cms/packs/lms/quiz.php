<?php
/**
 * Paquete lms — evaluaciones (cuestionarios y exámenes). Lo carga inc.php.
 *
 * Las evaluaciones son una colección más (Aula → Evaluaciones) con curso, módulo y orden, como las lecciones; el
 * temario las mezcla con ellas ("pasos" del curso) y cuentan para el avance: una evaluación está hecha cuando se
 * aprueba (o, si es de práctica con 0 %, cuando se envía).
 *
 * Preguntas en texto (campo questions, por idioma), una por bloque separado por una línea en blanco:
 *   1. ¿Pregunta? {2}        primera(s) línea(s): el texto; número inicial opcional; {n} = puntos (1 por omisión)
 *   * correcta / - incorrecta   opciones (varias * = opción múltiple con crédito parcial)
 *   = verdadero | = falso    verdadero/falso
 *   = texto | otra forma     respuesta corta (sin mayúsculas, acentos ni espacios de más)
 *   = 42 | = 3.5 ± 0.1       numérica con tolerancia
 *   = ?                      abierta: la califica el instructor
 *   > explicación            se ve al revisar el intento
 *
 * Intentos en data/lms/progress/<alumno>.json, bajo courses.<curso>.exams.<evaluación>:
 *   ['attempts' => [['n', 'start', 'end', 'lang', 'set' => [índices], 'answers' => [i => valor], 'marks' => [i => puntos|null],
 *                    'notes' => [i => comentario], 'points', 'max', 'pct', 'status' => passed|failed|pending, 'late', 'hash',
 *                    'graded', 'by'], …],
 *    'best' => %, 'passed' => fecha, 'extra' => intentos de más dados en el panel, 'open' => intento con tiempo en curso]
 */
declare(strict_types=1);

function lms_quiz_type(): string
{
    $v = (string) (cms_settings()['lms_quiz_type'] ?? '');
    return preg_replace('/[^a-z0-9_-]/i', '', trim($v)) ?: 'evaluaciones';
}

function lms_is_quiz(array $step): bool { return !empty($step['_q']); }

/** Evaluaciones de un curso, en orden. Del índice ligero: sin las preguntas. */
function lms_quizzes(string $course, bool $published = true): array
{
    static $cache = [];
    $key = $course . '|' . (int) $published;
    if (isset($cache[$key])) return $cache[$key];
    if (!cms_type(lms_quiz_type())) return [];
    $f = lms_settings()['lesson_field'];
    $out = [];
    $lessons = null;
    foreach (cms_items(lms_quiz_type(), $published) as $q) {
        if ((string) ($q[$f] ?? $q['course'] ?? '') !== $course) continue;
        // "Va después de la lección": toma el orden de esa lección (+0.5) y, si no tiene módulo propio, el suyo
        if (($after = (string) ($q['after'] ?? '')) !== '') {
            $lessons = $lessons ?? array_column(lms_lessons($course, $published), null, 'slug');
            if (isset($lessons[$after])) {
                $l = $lessons[$after];
                $q['order'] = (is_numeric($l['order'] ?? null) ? (float) $l['order'] : 1e9) + 0.5;
                if (trim((string) cms_f($q, 'module', cms_default_lang())) === '') $q['module'] = $l['module'] ?? '';
            }
        }
        $out[] = $q + ['_q' => true];
    }
    return $cache[$key] = lms_steps_sort($out);
}

function lms_steps_sort(array $out): array
{
    usort($out, function ($a, $b) {
        $oa = is_numeric($a['order'] ?? null) ? (float) $a['order'] : 1e9; $ob = is_numeric($b['order'] ?? null) ? (float) $b['order'] : 1e9;
        return $oa <=> $ob ?: (int) lms_is_quiz($a) <=> (int) lms_is_quiz($b)
            ?: strnatcasecmp((string) cms_f($a, 'title', cms_default_lang()), (string) cms_f($b, 'title', cms_default_lang()));
    });
    return $out;
}

/** Pasos de un curso: lecciones y evaluaciones mezcladas por orden (a igual orden, primero la lección). */
function lms_steps(string $course, bool $published = true): array
{
    $q = lms_quizzes($course, $published);
    return $q ? lms_steps_sort(array_merge(lms_lessons($course, $published), $q)) : lms_lessons($course, $published);
}

/** Curso de una evaluación. */
function lms_quiz_course(array $quiz): string { return (string) ($quiz[lms_settings()['lesson_field']] ?? $quiz['course'] ?? ''); }

function lms_step_url(array $step, ?string $lang = null): string
{
    $lang = $lang ?? (cms_current()['lang'] ?: cms_default_lang());
    return cms_url('item:' . (lms_is_quiz($step) ? lms_quiz_type() : lms_lesson_type()), $lang, (string) $step['slug']);
}

/** Ajustes de una evaluación con sus valores por omisión. */
function lms_quiz_cfg(array $quiz): array
{
    $n = fn(string $k) => is_numeric($quiz[$k] ?? null) ? max(0, (int) $quiz[$k]) : 0;
    return [
        'pass'     => is_numeric($quiz['pass'] ?? null) ? max(0, min(100, (int) $quiz['pass'])) : 70,
        'attempts' => $n('attempts'),
        'time'     => $n('time'),
        'pick'     => $n('pick'),
        'shuffle'  => !empty($quiz['shuffle']),
        'reveal'   => in_array($quiz['reveal'] ?? '', ['siempre', 'aciertos', 'nada'], true) ? (string) $quiz['reveal'] : '',
        'gate'     => !empty($quiz['gate']),
    ];
}

/* ================================================================== formato de las preguntas */

/** Texto comparable: minúsculas, sin acentos, sin signos al final y con un solo espacio. */
function lms_quiz_norm(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
                    'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ç' => 'c', 'ñ' => 'n']);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s, " .,;:¡!¿?\"'«»");
}

/** Número escrito por una persona ("3,5", "1 000", "-2") o null. */
function lms_quiz_num(string $s): ?float
{
    $s = str_replace([' ', "\u{00A0}"], '', trim($s));
    if (preg_match('/^-?\d+,\d+$/', $s)) $s = str_replace(',', '.', $s);
    elseif (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) $s = str_replace(',', '', $s);
    return is_numeric($s) ? (float) $s : null;
}

/**
 * Lee el texto de las preguntas. Devuelve ['questions' => [['text', 'kind' => single|multi|tf|short|num|open,
 * 'options' => [[texto, correcta]], 'answers' => [...], 'tol', 'points', 'explain'], …], 'warnings' => [...]].
 */
function lms_quiz_parse(string $src): array
{
    $qs = []; $warn = [];
    $blocks = preg_split('/\n\s*\n/', trim(str_replace(["\r\n", "\r"], "\n", $src))) ?: [];
    foreach ($blocks as $bi => $block) {
        $lines = array_values(array_filter(array_map('rtrim', explode("\n", $block)), fn($l) => trim($l) !== ''));
        if (!$lines) continue;
        $text = []; $opts = []; $answers = []; $explain = []; $open = false; $tf = null;
        foreach ($lines as $l) {
            $t = ltrim($l);
            if (preg_match('/^([*-])\s+(.+)$/u', $t, $m) && !$answers) $opts[] = [trim($m[2]), $m[1] === '*'];
            elseif (preg_match('/^=\s*(.*)$/u', $t, $m)) {
                $v = trim($m[1]);
                if ($v === '?' || $v === '') { $open = true; continue; }
                foreach (array_map('trim', explode('|', $v)) as $a) if ($a !== '') $answers[] = $a;
            } elseif (preg_match('/^>\s?(.*)$/u', $t, $m)) $explain[] = $m[1];
            elseif (!$opts && !$answers && !$open && !$explain) $text[] = trim($l);
            else $explain[] = $t;   // texto suelto después de las opciones: va con la explicación
        }
        $label = 'Pregunta ' . (count($qs) + 1);
        $first = (string) ($text[0] ?? '');
        $text[0] = preg_replace('/^(?:p(?:regunta)?\s*)?\d{1,3}\s*[.)\-:]\s+/iu', '', $first) ?? $first;
        $points = 1.0;
        $last = count($text) - 1;
        if ($last >= 0 && preg_match('/\s*\{\s*(\d+(?:[.,]\d+)?)\s*(?:pts?|puntos?)?\s*\}\s*$/iu', $text[$last], $m)) {
            $points = (float) str_replace(',', '.', $m[1]);
            $text[$last] = rtrim(substr($text[$last], 0, -strlen($m[0])));
        }
        $text = trim(implode("\n", $text));
        if ($text === '') { $warn[] = $label . ': falta el texto de la pregunta (bloque ' . ($bi + 1) . ').'; continue; }
        $q = ['text' => $text, 'kind' => '', 'options' => [], 'answers' => [], 'tol' => 0.0, 'points' => $points > 0 ? $points : 1.0, 'explain' => trim(implode("\n", $explain))];
        $short = mb_substr(preg_replace('/\s+/u', ' ', $text) ?? $text, 0, 50);
        if ($opts) {
            $nc = count(array_filter($opts, fn($o) => $o[1]));
            if (count($opts) < 2) $warn[] = $label . ' («' . $short . '»): tiene una sola opción.';
            if ($nc === 0) { $warn[] = $label . ' («' . $short . '»): ninguna opción está marcada como correcta con *.'; continue; }
            if ($answers || $open) $warn[] = $label . ' («' . $short . '»): tiene opciones y también una respuesta con =; se usan las opciones.';
            $q['kind'] = $nc > 1 ? 'multi' : 'single';
            $q['options'] = $opts;
        } elseif ($open) {
            $q['kind'] = 'open';
        } elseif ($answers) {
            $one = lms_quiz_norm($answers[0]);
            if (count($answers) === 1 && in_array($one, ['verdadero', 'falso', 'true', 'false', 'v', 'f'], true)) {
                $q['kind'] = 'tf';
                $q['answers'] = [in_array($one, ['verdadero', 'true', 'v'], true) ? 'v' : 'f'];
            } else {
                $nums = []; $tol = 0.0;
                foreach ($answers as $a) {
                    if (preg_match('/^(.+?)\s*(?:±|\+-|\+\/-)\s*(.+)$/u', $a, $m) && lms_quiz_num($m[1]) !== null && lms_quiz_num($m[2]) !== null) { $nums[] = lms_quiz_num($m[1]); $tol = max($tol, abs((float) lms_quiz_num($m[2]))); }
                    elseif (lms_quiz_num($a) !== null) $nums[] = lms_quiz_num($a);
                    else { $nums = null; break; }
                }
                if ($nums) { $q['kind'] = 'num'; $q['answers'] = $nums; $q['tol'] = $tol; }
                else { $q['kind'] = 'short'; $q['answers'] = $answers; }
            }
        } else {
            $warn[] = $label . ' («' . $short . '»): no tiene opciones (* / -) ni respuesta (=).';
            continue;
        }
        $qs[] = $q;
    }
    return ['questions' => $qs, 'warnings' => $warn];
}

/** Preguntas de una evaluación en un idioma (con respaldo al principal). Necesita el elemento completo. */
function lms_quiz_questions(array $quiz, string $lang): array
{
    static $cache = [];
    $src = (string) cms_f($quiz, 'questions', $lang);
    $k = md5($src);
    return $cache[$k] ??= lms_quiz_parse($src) + ['hash' => substr($k, 0, 12)];
}

/** Texto de una pregunta u opción en HTML: escapado, con **negritas**, `código` y saltos de línea. */
function lms_quiz_html(string $s): string
{
    $h = cms_e($s);
    $h = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $h) ?? $h;
    $h = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $h) ?? $h;
    return nl2br($h, false);
}

/* ================================================================== calificar */

/** Puntos de una respuesta (null = abierta, la califica el instructor). */
function lms_quiz_grade_one(array $q, $ans): ?float
{
    $p = (float) $q['points'];
    switch ($q['kind']) {
        case 'single':
            return is_string($ans) && ctype_digit($ans) && !empty($q['options'][(int) $ans][1]) ? $p : 0.0;
        case 'multi':
            $sel = array_unique(array_filter(array_map('strval', (array) $ans), 'ctype_digit'));
            if (!$sel) return 0.0;
            $c = 0; $w = 0; $total = 0;
            foreach ($q['options'] as $o) if ($o[1]) $total++;
            foreach ($sel as $i) { if (!isset($q['options'][(int) $i])) continue; if ($q['options'][(int) $i][1]) $c++; else $w++; }
            return $total ? round(max(0, ($c - $w) / $total) * $p, 2) : 0.0;
        case 'tf':
            return is_string($ans) && $ans === ($q['answers'][0] ?? '') ? $p : 0.0;
        case 'short':
            $a = lms_quiz_norm(is_string($ans) ? $ans : '');
            if ($a === '') return 0.0;
            foreach ($q['answers'] as $ok) if (lms_quiz_norm((string) $ok) === $a) return $p;
            return 0.0;
        case 'num':
            $x = lms_quiz_num(is_string($ans) ? $ans : '');
            if ($x === null) return 0.0;
            foreach ($q['answers'] as $ok) if (abs($x - (float) $ok) <= (float) $q['tol'] + 1e-9) return $p;
            return 0.0;
        case 'open':
            return trim(is_string($ans) ? $ans : '') === '' ? 0.0 : null;
    }
    return 0.0;
}

/** Respuesta enviada, limpia y acotada, según el tipo de pregunta. */
function lms_quiz_clean_answer(array $q, $raw)
{
    if ($q['kind'] === 'multi') return array_values(array_slice(array_filter(array_map('strval', (array) $raw), 'ctype_digit'), 0, 50));
    if (!is_string($raw)) return '';
    $max = $q['kind'] === 'open' ? 8000 : 300;
    return mb_substr(trim(str_replace("\r\n", "\n", $raw)), 0, $max);
}

/** Suma puntos y decide el estado de un intento (marks ya puestos). */
function lms_quiz_total(array $a, int $pass): array
{
    $pts = 0.0; $pending = false;
    foreach ((array) $a['marks'] as $m) { if ($m === null) $pending = true; else $pts += (float) $m; }
    $a['points'] = round($pts, 2);
    $a['pct'] = $a['max'] > 0 ? (int) floor($pts * 100 / $a['max'] + 1e-9) : 0;
    $a['status'] = $pending ? 'pending' : (empty($a['late']) && $a['pct'] >= $pass ? 'passed' : 'failed');
    return $a;
}

/** Recalcula la mejor calificación y la fecha de aprobado de una evaluación. */
function lms_quiz_summary(array $e): array
{
    $best = null; $passed = '';
    foreach ((array) ($e['attempts'] ?? []) as $a) {
        if (($a['status'] ?? '') === 'pending') continue;
        $best = max($best ?? 0, (int) ($a['pct'] ?? 0));
        if (($a['status'] ?? '') === 'passed' && ($passed === '' || (string) $a['end'] < $passed)) $passed = (string) $a['end'];
    }
    if ($best === null) unset($e['best']); else $e['best'] = $best;
    if ($passed === '') unset($e['passed']); else $e['passed'] = $passed;
    return $e;
}

/* ================================================================== conjunto de preguntas de un intento */

/** Orden estable al azar de una lista, a partir de una semilla (sin tocar el generador global). */
function lms_quiz_shuffle(array $keys, string $seed): array
{
    usort($keys, fn($a, $b) => strcmp(hash_hmac('md5', $seed . '|' . $a, cms_secret()), hash_hmac('md5', $seed . '|' . $b, cms_secret())));
    return $keys;
}

/** Preguntas (índices) del intento n de un alumno: todas, o "pick" al azar, mezcladas si se pidió. */
function lms_quiz_make_set(array $quiz, int $count, string $uid, int $n): array
{
    $cfg = lms_quiz_cfg($quiz);
    $idx = range(0, max(0, $count - 1));
    if (!$count) return [];
    $seed = 'set|' . $uid . '|' . $quiz['slug'] . '|' . $n . '|' . bin2hex(random_bytes(4));
    if ($cfg['pick'] > 0 && $cfg['pick'] < $count) { $idx = array_slice(lms_quiz_shuffle($idx, $seed), 0, $cfg['pick']); if (!$cfg['shuffle']) sort($idx); }
    elseif ($cfg['shuffle']) $idx = lms_quiz_shuffle($idx, $seed);
    return array_values($idx);
}

/** Firma del conjunto de preguntas que se dibujó (para intentos sin tiempo, que no se guardan al empezar). */
function lms_quiz_sign(string $uid, string $quiz, int $n, string $set, string $lang): string
{
    return substr(hash_hmac('sha256', 'quiz|' . $uid . '|' . $quiz . '|' . $n . '|' . $set . '|' . $lang, cms_secret()), 0, 32);
}

/** Orden de las opciones de una pregunta en un intento (mezcladas si se pidió). */
function lms_quiz_option_order(array $quiz, array $q, string $uid, int $n, int $qi): array
{
    $keys = array_keys($q['options']);
    return lms_quiz_cfg($quiz)['shuffle'] ? lms_quiz_shuffle($keys, 'opt|' . $uid . '|' . $quiz['slug'] . '|' . $n . '|' . $qi) : $keys;
}

/* ================================================================== estado de un alumno en una evaluación */

function lms_quiz_record(string $uid, string $course, string $quiz): array
{
    return (array) (lms_progress($uid)['courses'][$course]['exams'][$quiz] ?? []);
}

/** Segundos de gracia tras el tiempo límite (el envío automático tarda un poco en llegar). */
if (!defined('LMS_QUIZ_GRACE')) define('LMS_QUIZ_GRACE', 45);

/**
 * Estado de quien ve la evaluación: intentos usados y permitidos, mejor calificación, si aprobó, intento con tiempo
 * en curso (cierra el vencido), si puede empezar otro y por qué no.
 */
function lms_quiz_state(array $quiz, ?array $user, string $course): array
{
    $cfg = lms_quiz_cfg($quiz);
    $e = $user ? lms_quiz_record($user['id'], $course, (string) $quiz['slug']) : [];
    if ($user && !empty($e['open']) && (int) $e['open']['deadline'] + LMS_QUIZ_GRACE < time()) {   // se le acabó el tiempo sin enviar
        lms_quiz_finish($user['id'], $course, $quiz, [], (array) $e['open'], true);
        $e = lms_quiz_record($user['id'], $course, (string) $quiz['slug']);
    }
    $attempts = (array) ($e['attempts'] ?? []);
    $allowed = $cfg['attempts'] > 0 ? $cfg['attempts'] + (int) ($e['extra'] ?? 0) : 0;
    $left = $allowed ? max(0, $allowed - count($attempts)) : -1;   // -1 = sin límite
    $pending = (bool) array_filter($attempts, fn($a) => ($a['status'] ?? '') === 'pending');
    return [
        'cfg' => $cfg, 'attempts' => $attempts, 'used' => count($attempts), 'allowed' => $allowed, 'left' => $left,
        'best' => isset($e['best']) ? (int) $e['best'] : null, 'passed' => (string) ($e['passed'] ?? ''), 'pending' => $pending,
        'open' => !empty($e['open']) ? (array) $e['open'] : null,
        'can_start' => $user !== null && $left !== 0 && !$pending,
        'last' => $attempts ? end($attempts) : null,
    ];
}

/** ¿Lo anterior del curso está hecho? (para las evaluaciones con "Se abre solo al terminar todo lo anterior"). */
function lms_quiz_gate_ok(array $quiz, string $course, array $st): bool
{
    if (!lms_quiz_cfg($quiz)['gate'] || lms_staff()) return true;
    foreach (lms_steps($course) as $s) {
        if ($s['slug'] === $quiz['slug'] && lms_is_quiz($s)) return true;
        if (!lms_step_done($st, $s)) return false;
    }
    return true;
}

/** ¿Este paso está hecho, según las estadísticas de un curso (lms_stats_from)? */
function lms_step_done(array $st, array $step): bool
{
    return lms_is_quiz($step) ? !empty($st['exams'][$step['slug']]['passed']) : !empty($st['lessons'][$step['slug']]);
}

/* ================================================================== empezar y enviar */

/** Empieza un intento con tiempo: guarda la hora, el conjunto de preguntas y el idioma. */
function lms_quiz_start(string $uid, string $course, array $quiz, string $lang): bool
{
    $p = lms_progress($uid);
    $e = (array) ($p['courses'][$course]['exams'][$quiz['slug']] ?? []);
    if (!empty($e['open'])) return true;
    $qs = lms_quiz_questions($quiz, $lang);
    $n = count((array) ($e['attempts'] ?? [])) + 1;
    $e['open'] = ['n' => $n, 'start' => date('Y-m-d H:i:s'), 'deadline' => time() + lms_quiz_cfg($quiz)['time'] * 60, 'lang' => $lang,
                  'set' => lms_quiz_make_set($quiz, count($qs['questions']), $uid, $n), 'hash' => $qs['hash']];
    $p['courses'][$course] = array_replace(['lessons' => []], (array) ($p['courses'][$course] ?? []));
    if (empty($p['courses'][$course]['enrolled'])) { $p['courses'][$course]['enrolled'] = date('Y-m-d H:i'); $p['courses'][$course]['by'] = 'alumno'; }
    $p['courses'][$course]['exams'][$quiz['slug']] = $e;
    return lms_progress_save($uid, $p);
}

/**
 * Califica y guarda un intento. $open: ['n', 'start', 'lang', 'set', 'hash', 'deadline'?]. Devuelve el intento guardado
 * o null si no se pudo. Las abiertas quedan pendientes; con aviso al instructor si Ajustes → Aula tiene correo.
 */
function lms_quiz_finish(string $uid, string $course, array $quiz, array $posted, array $open, bool $expired = false): ?array
{
    $lang = in_array($open['lang'] ?? '', cms_langs(), true) ? (string) $open['lang'] : cms_default_lang();
    $qs = lms_quiz_questions($quiz, $lang)['questions'];
    $set = array_values(array_filter(array_map('intval', (array) ($open['set'] ?? [])), fn($i) => isset($qs[$i])));
    $a = ['n' => (int) $open['n'], 'start' => (string) $open['start'], 'end' => date('Y-m-d H:i:s'), 'lang' => $lang, 'set' => $set,
          'answers' => [], 'marks' => [], 'max' => 0.0, 'hash' => (string) ($open['hash'] ?? '')];
    foreach ($set as $i) {
        $ans = lms_quiz_clean_answer($qs[$i], $posted[$i] ?? ($qs[$i]['kind'] === 'multi' ? [] : ''));
        $a['answers'][$i] = $ans;
        $a['marks'][$i] = lms_quiz_grade_one($qs[$i], $ans);
        $a['max'] += (float) $qs[$i]['points'];
    }
    if ($expired || (!empty($open['deadline']) && time() > (int) $open['deadline'] + LMS_QUIZ_GRACE)) $a['late'] = true;
    $a = lms_quiz_total($a, lms_quiz_cfg($quiz)['pass']);

    $p = lms_progress($uid);
    $c = array_replace(['lessons' => []], (array) ($p['courses'][$course] ?? []));
    if (empty($c['enrolled'])) { $c['enrolled'] = date('Y-m-d H:i'); $c['by'] = 'alumno'; }
    $e = (array) ($c['exams'][$quiz['slug']] ?? []);
    $e['attempts'] = array_values((array) ($e['attempts'] ?? []));
    foreach ($e['attempts'] as $x) if ((int) ($x['n'] ?? 0) === $a['n']) return null;   // doble envío del mismo intento
    $e['attempts'][] = $a;
    unset($e['open']);
    $c['exams'][$quiz['slug']] = lms_quiz_summary($e);
    $c['last'] = (string) $quiz['slug'];
    $p['courses'][$course] = $c;
    lms_course_recheck($p, $course, $uid);
    if (!lms_progress_save($uid, $p)) return null;

    if ($a['status'] === 'pending' && lms_settings()['notify_to'] !== '' && ($u = lms_user_get($uid))) {
        lms_mail(lms_settings()['notify_to'], 'Evaluación por calificar: ' . cms_f($quiz, 'title', cms_default_lang()) . ' · ' . $u['name'],
            $u['name'] . ' (' . $u['email'] . ") envió «" . cms_f($quiz, 'title', cms_default_lang()) . "», que tiene preguntas abiertas.\n\nCalificar: "
            . cms_origin() . CMS_BASE . '/admin/?p=pack:' . basename(__DIR__) . '&tab=evaluaciones&quiz=' . rawurlencode((string) $quiz['slug']) . '&id=' . $uid . '&n=' . $a['n'] . "\n");
    }
    return $a;
}

/**
 * Califica a mano un intento (preguntas abiertas o corrección de cualquier otra). $marks: [i => puntos], $notes:
 * [i => comentario]. Devuelve el intento o null.
 */
function lms_quiz_grade(string $uid, string $course, array $quiz, int $n, array $marks, array $notes, string $by): ?array
{
    $p = lms_progress($uid);
    $e = (array) ($p['courses'][$course]['exams'][$quiz['slug']] ?? []);
    $qs = null;
    foreach ((array) ($e['attempts'] ?? []) as $k => $a) {
        if ((int) $a['n'] !== $n) continue;
        $qs = $qs ?? lms_quiz_questions($quiz, (string) ($a['lang'] ?? cms_default_lang()))['questions'];
        foreach ((array) $a['set'] as $i) {
            if (array_key_exists($i, $marks) && $marks[$i] !== '' && is_numeric($marks[$i])) {
                $max = (float) ($qs[$i]['points'] ?? 1);
                $a['marks'][$i] = round(max(0, min($max, (float) $marks[$i])), 2);
            }
            if (array_key_exists($i, $notes)) { $t = mb_substr(trim((string) $notes[$i]), 0, 2000); if ($t === '') unset($a['notes'][$i]); else $a['notes'][$i] = $t; }
        }
        $a = lms_quiz_total($a, lms_quiz_cfg($quiz)['pass']);
        $a['graded'] = date('Y-m-d H:i'); $a['by'] = $by;
        $e['attempts'][$k] = $a;
        $p['courses'][$course]['exams'][$quiz['slug']] = lms_quiz_summary($e);
        lms_course_recheck($p, $course, $uid);
        return lms_progress_save($uid, $p) ? $a : null;
    }
    return null;
}

/** Panel: un intento más para este alumno, o borrar todos sus intentos (vuelve a empezar). */
function lms_quiz_reset(string $uid, string $course, string $quiz, string $what): bool
{
    $p = lms_progress($uid);
    if (!isset($p['courses'][$course])) return true;
    $e = (array) ($p['courses'][$course]['exams'][$quiz] ?? []);
    if ($what === 'extra') $e['extra'] = (int) ($e['extra'] ?? 0) + 1;
    elseif ($what === 'close') unset($e['open']);
    else $e = [];
    if ($e) $p['courses'][$course]['exams'][$quiz] = lms_quiz_summary($e); else unset($p['courses'][$course]['exams'][$quiz]);
    lms_course_recheck($p, $course, $uid);
    return lms_progress_save($uid, $p);
}

/** Respuesta en texto legible (panel, CSV). */
function lms_quiz_answer_text(array $q, $ans): string
{
    switch ($q['kind']) {
        case 'single': return is_string($ans) && isset($q['options'][(int) $ans]) && $ans !== '' ? (string) $q['options'][(int) $ans][0] : '';
        case 'multi': return implode(' · ', array_map(fn($i) => (string) ($q['options'][(int) $i][0] ?? '?'), (array) $ans));
        case 'tf': return $ans === 'v' ? lms_tx('true') : ($ans === 'f' ? lms_tx('false') : '');
        default: return is_string($ans) ? $ans : '';
    }
}

/** Respuesta correcta en texto legible. */
function lms_quiz_correct_text(array $q): string
{
    switch ($q['kind']) {
        case 'single': case 'multi': return implode(' · ', array_map(fn($o) => (string) $o[0], array_filter($q['options'], fn($o) => $o[1])));
        case 'tf': return ($q['answers'][0] ?? '') === 'v' ? lms_tx('true') : lms_tx('false');
        case 'num': return implode(' | ', array_map(fn($x) => rtrim(rtrim(number_format((float) $x, 4, '.', ''), '0'), '.') . ($q['tol'] > 0 ? ' ± ' . $q['tol'] : ''), $q['answers']));
        case 'short': return implode(' | ', $q['answers']);
    }
    return '';
}

/** Calificación de un intento para mostrar: "85 %" o "Por calificar". */
function lms_quiz_pct_text(array $a): string
{
    return ($a['status'] ?? '') === 'pending' ? lms_tx('q_pending') : (int) ($a['pct'] ?? 0) . ' %';
}

/** Puntos con un decimal solo si hace falta. */
function lms_quiz_pts(float $x): string { return rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.'); }
