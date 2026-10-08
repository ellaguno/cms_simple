<?php
/**
 * Paquete lms — preguntas en los formatos de Moodle: Moodle XML y GIFT, de ida y vuelta con el formato de texto de
 * las evaluaciones (quiz.php). Lo carga inc.php.
 *
 *   opción única / múltiple   multichoice (single true/false; las múltiples con fracción 100/n y -100/n)
 *   verdadero/falso           truefalse
 *   respuesta corta           shortanswer (solo las respuestas al 100 %)
 *   numérica                  numerical (con tolerancia)
 *   abierta                   essay
 * Al importar se descartan, con aviso, los tipos que no existen aquí (emparejar, huecos/cloze, arrastrar…). El texto
 * de Moodle (HTML) se pasa a texto con **negritas**, `código` y saltos de línea.
 */
declare(strict_types=1);

/* ================================================================== de nuestro formato a Moodle */

/** Texto con **negritas** y `código` a HTML (para Moodle XML). */
function lms_mdl_html(string $s): string { return lms_quiz_html($s); }

/** Porcentaje para Moodle con 5 decimales como mucho ("33.33333", "50", "-100"). */
function lms_mdl_frac(float $x): string { return rtrim(rtrim(number_format($x, 5, '.', ''), '0'), '.'); }

function lms_quiz_to_moodle_xml(array $qs, string $title = ''): string
{
    $cd = fn(string $s) => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';
    $x = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n";
    if ($title !== '') $x .= "  <question type=\"category\">\n    <category><text>" . htmlspecialchars('$course$/top/' . $title, ENT_XML1) . "</text></category>\n  </question>\n";
    foreach ($qs as $i => $q) {
        $type = ['single' => 'multichoice', 'multi' => 'multichoice', 'tf' => 'truefalse', 'short' => 'shortanswer', 'num' => 'numerical', 'open' => 'essay'][$q['kind']] ?? '';
        if ($type === '') continue;
        $name = mb_substr(preg_replace('/\s+/u', ' ', str_replace(['**', '`'], '', $q['text'])) ?? '', 0, 60);
        $x .= "  <question type=\"$type\">\n    <name><text>" . htmlspecialchars(($i + 1) . '. ' . $name, ENT_XML1) . "</text></name>\n"
            . "    <questiontext format=\"html\"><text>" . $cd('<p>' . lms_mdl_html($q['text']) . '</p>') . "</text></questiontext>\n"
            . "    <generalfeedback format=\"html\"><text>" . ($q['explain'] !== '' ? $cd('<p>' . lms_mdl_html($q['explain']) . '</p>') : '') . "</text></generalfeedback>\n"
            . "    <defaultgrade>" . lms_quiz_pts((float) $q['points']) . "</defaultgrade>\n    <penalty>0</penalty>\n    <hidden>0</hidden>\n";
        switch ($q['kind']) {
            case 'single': case 'multi':
                $nc = count(array_filter($q['options'], fn($o) => $o[1]));
                $x .= "    <single>" . ($q['kind'] === 'single' ? 'true' : 'false') . "</single>\n    <shuffleanswers>1</shuffleanswers>\n    <answernumbering>abc</answernumbering>\n";
                foreach ($q['options'] as [$t, $ok]) {
                    $f = $q['kind'] === 'single' ? ($ok ? 100 : 0) : ($ok ? 100 / $nc : -100 / $nc);
                    $x .= "    <answer fraction=\"" . lms_mdl_frac($f) . "\" format=\"html\"><text>" . $cd(lms_mdl_html($t)) . "</text><feedback><text></text></feedback></answer>\n";
                }
                break;
            case 'tf':
                $v = ($q['answers'][0] ?? 'v') === 'v';
                $x .= "    <answer fraction=\"" . ($v ? 100 : 0) . "\"><text>true</text><feedback><text></text></feedback></answer>\n"
                    . "    <answer fraction=\"" . ($v ? 0 : 100) . "\"><text>false</text><feedback><text></text></feedback></answer>\n";
                break;
            case 'short':
                $x .= "    <usecase>0</usecase>\n";
                foreach ($q['answers'] as $a) $x .= "    <answer fraction=\"100\"><text>" . htmlspecialchars((string) $a, ENT_XML1) . "</text><feedback><text></text></feedback></answer>\n";
                break;
            case 'num':
                foreach ($q['answers'] as $a) $x .= "    <answer fraction=\"100\"><text>" . lms_quiz_pts((float) $a) . "</text><tolerance>" . lms_quiz_pts((float) $q['tol']) . "</tolerance><feedback><text></text></feedback></answer>\n";
                break;
            case 'open':
                $x .= "    <responseformat>editor</responseformat>\n    <responserequired>1</responserequired>\n    <responsefieldlines>10</responsefieldlines>\n";
                break;
        }
        $x .= "  </question>\n";
    }
    return $x . "</quiz>\n";
}

/** Escapa los caracteres especiales de GIFT. */
function lms_gift_esc(string $s): string
{
    return strtr(str_replace(["\r\n", "\n"], ' ', $s), ['\\' => '\\\\', '~' => '\~', '=' => '\=', '#' => '\#', '{' => '\{', '}' => '\}', ':' => '\:']);
}

function lms_quiz_to_gift(array $qs, string $title = ''): string
{
    $g = $title !== '' ? '// ' . str_replace("\n", ' ', $title) . "\n// Exportado del aula (cms_simple, paquete lms)\n\n" : '';
    foreach ($qs as $i => $q) {
        $name = mb_substr(preg_replace('/\s+/u', ' ', str_replace(['**', '`'], '', $q['text'])) ?? '', 0, 40);
        $head = '::' . lms_gift_esc(($i + 1) . '. ' . $name) . ':: ' . lms_gift_esc($q['text']);
        $fb = $q['explain'] !== '' ? "\n\t####" . lms_gift_esc($q['explain']) : '';
        switch ($q['kind']) {
            case 'single':
                $body = '';
                foreach ($q['options'] as [$t, $ok]) $body .= "\n\t" . ($ok ? '=' : '~') . lms_gift_esc($t);
                break;
            case 'multi':
                $nc = count(array_filter($q['options'], fn($o) => $o[1]));
                $body = '';
                foreach ($q['options'] as [$t, $ok]) $body .= "\n\t~%" . lms_mdl_frac($ok ? 100 / $nc : -100 / $nc) . '%' . lms_gift_esc($t);
                break;
            case 'tf': $body = ($q['answers'][0] ?? 'v') === 'v' ? 'TRUE' : 'FALSE'; break;
            case 'short':
                $body = '';
                foreach ($q['answers'] as $a) $body .= "\n\t=" . lms_gift_esc((string) $a);
                break;
            case 'num':
                $body = '#';
                foreach ($q['answers'] as $k => $a) $body .= (count($q['answers']) > 1 ? "\n\t=" : '') . lms_quiz_pts((float) $a) . ($q['tol'] > 0 ? ':' . lms_quiz_pts((float) $q['tol']) : '');
                break;
            default: $body = '';
        }
        $multiline = strpos($body, "\n") !== false || $fb !== '';
        $g .= $head . ' {' . $body . $fb . ($multiline ? "\n" : '') . "}\n\n";
    }
    return $g;
}

/* ================================================================== de Moodle a nuestro formato */

/** HTML de Moodle a texto de una línea o varias, con **negritas** y `código`. */
function lms_mdl_text(string $h, bool $oneLine = false): string
{
    $h = preg_replace('#<(strong|b)\b[^>]*>(.*?)</\1>#is', '**$2**', $h) ?? $h;
    $h = preg_replace('#<code\b[^>]*>(.*?)</code>#is', '`$1`', $h) ?? $h;
    $h = preg_replace('#<img\b[^>]*alt="([^"]*)"[^>]*>#i', '[$1]', $h) ?? $h;
    $h = preg_replace('#<br\s*/?>|</p>\s*<p[^>]*>|</(li|div|h\d)>#i', "\n", $h) ?? $h;
    $t = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\u{00A0}", ' ', $t);
    $lines = array_values(array_filter(array_map(fn($l) => trim(preg_replace('/[ \t]+/u', ' ', $l) ?? $l), explode("\n", str_replace("\r", '', $t))), fn($l) => $l !== ''));
    if ($oneLine) return implode(' ', $lines);
    // una línea que empiece con los signos del formato se confundiría con una opción o una respuesta
    return implode("\n", array_map(fn($l) => preg_match('/^([*=>-]\s|[=>])/u', $l) ? '· ' . $l : $l, $lines));
}

/** Una pregunta ya leída a nuestro bloque de texto. $q: ['text', 'kind', 'options' => [[t, ok]], 'answers', 'tol', 'points', 'explain']. */
function lms_quiz_block(array $q, int $n): string
{
    $pts = (float) ($q['points'] ?? 1);
    $b = $n . '. ' . $q['text'] . ($pts > 0 && abs($pts - 1) > 1e-9 ? ' {' . lms_quiz_pts($pts) . '}' : '') . "\n";
    $clean = fn(string $s) => str_replace(['|', "\n"], ['/', ' '], trim($s));
    switch ($q['kind']) {
        case 'single': case 'multi':
            foreach ($q['options'] as [$t, $ok]) $b .= ($ok ? '* ' : '- ') . $clean($t) . "\n";
            break;
        case 'tf': $b .= '= ' . ($q['answers'][0] === 'v' ? 'verdadero' : 'falso') . "\n"; break;
        case 'short': $b .= '= ' . implode(' | ', array_map($clean, $q['answers'])) . "\n"; break;
        case 'num': $b .= '= ' . implode(' | ', array_map(fn($a) => lms_quiz_pts((float) $a) . ($q['tol'] > 0 ? ' ± ' . lms_quiz_pts((float) $q['tol']) : ''), $q['answers'])) . "\n"; break;
        case 'open': $b .= "= ?\n"; break;
    }
    if (trim((string) ($q['explain'] ?? '')) !== '') foreach (explode("\n", (string) $q['explain']) as $l) $b .= '> ' . $l . "\n";
    return $b;
}

/** Moodle XML → [texto de preguntas, avisos]. */
function lms_quiz_from_moodle_xml(string $xml): array
{
    $warn = []; $out = [];
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$doc) return ['', ['El archivo no es un XML válido.']];
    $n = 0;
    foreach ($doc->question as $qx) {
        $type = (string) $qx['type'];
        if (in_array($type, ['category', 'description'], true)) continue;
        $name = trim((string) ($qx->name->text ?? ''));
        $text = lms_mdl_text((string) ($qx->questiontext->text ?? ''));
        $q = ['text' => $text !== '' ? $text : $name, 'kind' => '', 'options' => [], 'answers' => [], 'tol' => 0.0,
              'points' => (float) ($qx->defaultgrade ?? 1) ?: 1.0, 'explain' => lms_mdl_text((string) ($qx->generalfeedback->text ?? ''))];
        $label = '«' . mb_substr($name !== '' ? $name : $text, 0, 50) . '»';
        switch ($type) {
            case 'multichoice':
                $single = !in_array(strtolower((string) ($qx->single ?? 'true')), ['false', '0'], true);
                $max = 0.0;
                foreach ($qx->answer as $a) $max = max($max, (float) $a['fraction']);
                foreach ($qx->answer as $a) {
                    $f = (float) $a['fraction'];
                    $q['options'][] = [lms_mdl_text((string) $a->text, true), $single ? ($f > 0 && $f >= $max - 1e-6) : $f > 0];
                }
                if ($single && $max < 100) $warn[] = $label . ': ninguna opción valía 100 %; se tomó como correcta la de más valor.';
                if ($single && count(array_filter($qx->xpath('answer') ?: [], fn($a) => (float) $a['fraction'] > 0 && (float) $a['fraction'] < $max)) > 0) $warn[] = $label . ': tenía opciones con crédito parcial; aquí cuentan como incorrectas.';
                $nc = count(array_filter($q['options'], fn($o) => $o[1]));
                $q['kind'] = $single || $nc === 1 ? 'single' : 'multi';
                if (!$nc) { $warn[] = $label . ': sin opción correcta, se omitió.'; continue 2; }
                break;
            case 'truefalse':
                foreach ($qx->answer as $a) if ((float) $a['fraction'] >= 100) $q['answers'] = [in_array(strtolower(trim((string) $a->text)), ['true', 'verdadero'], true) ? 'v' : 'f'];
                if (!$q['answers']) { $warn[] = $label . ': verdadero/falso sin respuesta correcta, se omitió.'; continue 2; }
                $q['kind'] = 'tf';
                break;
            case 'shortanswer':
                foreach ($qx->answer as $a) if ((float) $a['fraction'] >= 100 && trim((string) $a->text) !== '') {
                    $t = trim((string) $a->text);
                    if (strpos($t, '*') !== false) $warn[] = $label . ': la respuesta «' . $t . '» usa el comodín *, que aquí se toma literal.';
                    $q['answers'][] = $t;
                }
                if (!$q['answers']) { $warn[] = $label . ': respuesta corta sin respuesta al 100 %, se omitió.'; continue 2; }
                $q['kind'] = 'short';
                break;
            case 'numerical':
                foreach ($qx->answer as $a) if ((float) $a['fraction'] >= 100 && is_numeric(trim((string) $a->text))) { $q['answers'][] = (float) trim((string) $a->text); $q['tol'] = max($q['tol'], abs((float) ($a->tolerance ?? 0))); }
                if (!$q['answers']) { $warn[] = $label . ': numérica sin respuesta al 100 %, se omitió.'; continue 2; }
                $q['kind'] = 'num';
                break;
            case 'essay':
                $q['kind'] = 'open';
                break;
            default:
                $warn[] = $label . ': el tipo «' . $type . '» no existe en el aula, se omitió.';
                continue 2;
        }
        $out[] = lms_quiz_block($q, ++$n);
    }
    if (!$out && !$warn) $warn[] = 'No se encontraron preguntas en el archivo.';
    return [implode("\n", $out), $warn];
}

/** Quita los escapes de GIFT (\~ \= \# \{ \} \: \\ y \n). */
function lms_gift_unesc(string $s): string
{
    return trim(preg_replace_callback('/\\\\(.)/s', fn($m) => $m[1] === 'n' ? "\n" : $m[1], $s) ?? $s);
}

/** Parte un texto de GIFT por un carácter que no esté escapado. Devuelve los trozos aún escapados. */
function lms_gift_split(string $s, string $chars): array
{
    $out = []; $cur = ''; $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($c === '\\' && $i + 1 < $len) { $cur .= $c . $s[++$i]; continue; }
        if (strpos($chars, $c) !== false) { $out[] = $cur; $cur = $c; continue; }
        $cur .= $c;
    }
    $out[] = $cur;
    return $out;
}

/** Posición del primer carácter $c no escapado en $s, o -1. */
function lms_gift_pos(string $s, string $c, int $from = 0): int
{
    $len = strlen($s);
    for ($i = $from; $i < $len; $i++) { if ($s[$i] === '\\') { $i++; continue; } if ($s[$i] === $c) return $i; }
    return -1;
}

/** Texto de GIFT ([html], [markdown], [plain] al principio) a nuestro texto. */
function lms_gift_text(string $s, bool $oneLine = false): string
{
    $s = trim($s);
    if (preg_match('/^\[(html|moodle|plain|markdown)\]/i', $s, $m)) {
        $s = substr($s, strlen($m[0]));
        if (strtolower($m[1]) === 'html') return lms_mdl_text(lms_gift_unesc($s), $oneLine);
    }
    $t = lms_gift_unesc($s);
    return $oneLine ? trim(preg_replace('/\s+/u', ' ', $t) ?? $t) : $t;
}

/** GIFT → [texto de preguntas, avisos]. */
function lms_quiz_from_gift(string $src): array
{
    $warn = []; $out = []; $n = 0;
    $src = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $src)) ?? $src;
    $lines = array_filter(explode("\n", $src), fn($l) => !preg_match('#^\s*//#', $l) && !preg_match('/^\s*\$CATEGORY:/', $l));
    foreach (preg_split('/\n\s*\n/', implode("\n", $lines)) ?: [] as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $name = '';
        if (strpos($block, '::') === 0 && ($e = strpos($block, '::', 2)) !== false) { $name = lms_gift_unesc(substr($block, 2, $e - 2)); $block = trim(substr($block, $e + 2)); }
        $open = lms_gift_pos($block, '{');
        $close = $open >= 0 ? lms_gift_pos($block, '}', $open) : -1;
        $label = '«' . mb_substr($name !== '' ? $name : lms_gift_unesc($block), 0, 50) . '»';
        if ($open < 0 || $close < 0) { $warn[] = $label . ': sin bloque de respuestas { }, se omitió.'; continue; }
        $before = trim(substr($block, 0, $open)); $after = trim(substr($block, $close + 1)); $ans = trim(substr($block, $open + 1, $close - $open - 1));
        $text = lms_gift_text($before . ($after !== '' ? ' _____ ' . $after : ''));
        $q = ['text' => $text !== '' ? $text : $name, 'kind' => '', 'options' => [], 'answers' => [], 'tol' => 0.0, 'points' => 1.0, 'explain' => ''];
        if (($gf = strpos($ans, '####')) !== false) { $q['explain'] = lms_gift_text(substr($ans, $gf + 4)); $ans = trim(substr($ans, 0, $gf)); }
        if ($ans === '') $q['kind'] = 'open';
        elseif (preg_match('/^(T|TRUE|F|FALSE)\b/i', $ans, $m)) { $q['kind'] = 'tf'; $q['answers'] = [strtoupper($m[1])[0] === 'T' ? 'v' : 'f']; }
        elseif ($ans[0] === '#') {
            $q['kind'] = 'num';
            foreach (array_filter(lms_gift_split(substr($ans, 1), '='), fn($x) => trim($x, " \t\n=") !== '') as $part) {
                $part = trim(ltrim(trim($part), '='));
                if (preg_match('/^%(-?[\d.]+)%/', $part, $pm)) { if ((float) $pm[1] < 100) continue; $part = substr($part, strlen($pm[0])); }
                $part = trim(explode('#', $part)[0]);
                if (preg_match('/^(-?[\d.]+)\.\.(-?[\d.]+)$/', $part, $r)) { $q['answers'][] = ((float) $r[1] + (float) $r[2]) / 2; $q['tol'] = max($q['tol'], abs((float) $r[2] - (float) $r[1]) / 2); }
                elseif (preg_match('/^(-?[\d.]+)(?::([\d.]+))?$/', $part, $r)) { $q['answers'][] = (float) $r[1]; $q['tol'] = max($q['tol'], (float) ($r[2] ?? 0)); }
            }
            if (!$q['answers']) { $warn[] = $label . ': numérica que no se pudo leer, se omitió.'; continue; }
        } else {
            $parts = array_values(array_filter(lms_gift_split($ans, '=~'), fn($x) => trim($x) !== ''));
            $hasWrong = false; $opts = [];
            foreach ($parts as $p) {
                $p = trim($p);
                $sign = $p[0]; $p = substr($p, 1);
                if ($sign !== '=' && $sign !== '~') { $p = $sign . $p; $sign = '='; }
                $w = $sign === '=' ? 100.0 : 0.0;
                if (preg_match('/^%(-?[\d.]+)%/', $p, $pm)) { $w = (float) $pm[1]; $p = substr($p, strlen($pm[0])); }
                $fbp = lms_gift_pos($p, '#');
                if ($fbp >= 0) $p = substr($p, 0, $fbp);
                if (strpos($p, '->') !== false) { $warn[] = $label . ': las preguntas de emparejar no existen en el aula, se omitió.'; continue 2; }
                if ($sign === '~') $hasWrong = true;
                $opts[] = [lms_gift_text($p, true), $w];
            }
            if (!$hasWrong) { $q['kind'] = 'short'; $q['answers'] = array_values(array_map(fn($o) => $o[0], array_filter($opts, fn($o) => $o[1] >= 100))); }
            else {
                $pos = array_filter($opts, fn($o) => $o[1] > 0);
                $multi = count($pos) > 1 || (count($pos) === 1 && reset($pos)[1] < 100);
                $q['kind'] = $multi ? 'multi' : 'single';
                $q['options'] = array_map(fn($o) => [$o[0], $o[1] > 0], $opts);
                if (!$pos) { $warn[] = $label . ': sin opción correcta, se omitió.'; continue; }
            }
        }
        $out[] = lms_quiz_block($q, ++$n);
    }
    if (!$out && !$warn) $warn[] = 'No se encontraron preguntas en el archivo.';
    return [implode("\n", $out), $warn];
}

/** Lee un archivo de preguntas según su contenido: Moodle XML, GIFT o nuestro formato. Devuelve [texto, avisos, formato]. */
function lms_quiz_import_text(string $raw, string $filename = ''): array
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'xml' || preg_match('/^\s*(<\?xml[^>]*>\s*)?<quiz[\s>]/', $raw)) { [$t, $w] = lms_quiz_from_moodle_xml($raw); return [$t, $w, 'Moodle XML']; }
    if ($ext === 'gift' || ($ext !== 'txt' && preg_match('/\{[^}]*[=~#][^}]*\}|::[^:]+::/', $raw))) { [$t, $w] = lms_quiz_from_gift($raw); return [$t, $w, 'GIFT']; }
    $t = trim(str_replace(["\r\n", "\r"], "\n", $raw));
    return [$t, lms_quiz_parse($t)['warnings'], 'texto del aula'];
}
