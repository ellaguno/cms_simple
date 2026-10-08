<?php
/**
 * Paquete lms — SCORM 1.2. Lo carga inc.php.
 *
 * Reproducir: un paquete SCORM (.zip con imsmanifest.xml, hecho en Articulate, iSpring, Captivate, H5P…) se sube en
 * Aula → Alumnos y avance → SCORM y se descomprime en scorm/<nombre>/ (sin archivos .php ni .htaccess del zip; la
 * carpeta queda sin acceso directo). Una lección lo usa con el campo "Paquete SCORM" (scorm/<nombre>). La lección
 * lo abre en un marco servido por /aula/sco/<lección>/<archivo> a quien puede ver la lección, y le ofrece la API de
 * SCORM 1.2 (window.API, assets/lms-scorm.js). Lo que el paquete guarda (estado, calificación, ubicación,
 * suspend_data, tiempo) va a /aula/scorm (POST) y queda en courses.<curso>.scorm.<lección>.<sco>; la lección se
 * marca terminada cuando todos sus SCO están "completed" o "passed".
 *
 * Exportar: lms_scorm_export() arma un paquete SCORM 1.2 de un curso (un solo SCO con un reproductor propio:
 * temario, videos, contenido, materiales y evaluaciones) para subirlo al LMS de un cliente; reporta estado,
 * calificación y avance con suspend_data.
 */
declare(strict_types=1);

/* ================================================================== paquetes subidos */

function lms_scorm_root(): string { return CMS_ROOT . '/scorm'; }

/** Ruta relativa limpia de un paquete ("scorm/curso-x") o '' si no es válida. */
function lms_scorm_rel(string $p): string
{
    $p = trim(str_replace('\\', '/', $p), '/');
    return preg_match('#^scorm/[a-z0-9][a-z0-9_-]*$#i', $p) ? $p : '';
}

/**
 * Lee imsmanifest.xml: ['title', 'version', 'scos' => [['id', 'title', 'href', 'mastery'], …], 'warn' => [...]] o
 * ['error' => …]. Los SCO salen de la organización por omisión, en orden; si no hay organizaciones, de los recursos.
 */
function lms_scorm_manifest(string $rel): array
{
    static $cache = [];
    if (isset($cache[$rel])) return $cache[$rel];
    $f = CMS_ROOT . '/' . $rel . '/imsmanifest.xml';
    if ($rel === '' || !is_file($f)) return $cache[$rel] = ['error' => 'No hay imsmanifest.xml en /' . $rel . '/.'];
    $prev = libxml_use_internal_errors(true);
    $x = simplexml_load_string((string) file_get_contents($f), 'SimpleXMLElement', LIBXML_NONET);
    libxml_use_internal_errors($prev);
    if (!$x) return $cache[$rel] = ['error' => 'imsmanifest.xml no es un XML válido.'];
    // los espacios de nombres varían entre herramientas: se lee por nombre local
    $q = fn($node, string $path) => $node->xpath($path) ?: [];
    $res = [];
    foreach ($q($x, '//*[local-name()="resource"]') as $r) {
        $attrs = $r->attributes();
        $type = '';
        foreach ($r->attributes('http://www.adlnet.org/xsd/adlcp_rootv1p2') as $k => $v) if (strtolower($k) === 'scormtype') $type = (string) $v;
        if ($type === '') foreach ($r->attributes() as $k => $v) if (stripos($k, 'scormtype') !== false) $type = (string) $v;
        $res[(string) $attrs['identifier']] = ['href' => (string) $attrs['href'], 'type' => strtolower($type)];
    }
    $schemaVer = trim((string) (($q($x, '//*[local-name()="metadata"]/*[local-name()="schemaversion"]')[0] ?? '')));
    $orgs = $q($x, '//*[local-name()="organizations"]')[0] ?? null;
    $def = $orgs ? (string) $orgs['default'] : '';
    $org = null;
    foreach ($q($x, '//*[local-name()="organization"]') as $o) if ($org === null || (string) $o['identifier'] === $def) { $org = $o; if ((string) $o['identifier'] === $def) break; }
    $title = $org ? trim((string) (($q($org, '*[local-name()="title"]')[0] ?? ''))) : '';
    $scos = []; $warn = [];
    if ($org) foreach ($q($org, './/*[local-name()="item"][@identifierref]') as $it) {
        $ref = (string) $it['identifierref'];
        if (!isset($res[$ref]) || $res[$ref]['href'] === '') continue;
        $mastery = '';
        foreach ($q($it, '*[local-name()="masteryscore"]') as $m) $mastery = trim((string) $m);
        $params = (string) $it['parameters'];
        $scos[] = ['id' => preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $it['identifier']) ?: 'sco' . count($scos), 'title' => trim((string) (($q($it, '*[local-name()="title"]')[0] ?? ''))) ?: $ref,
                   'href' => $res[$ref]['href'] . $params, 'mastery' => is_numeric($mastery) ? (int) $mastery : null, 'sco' => $res[$ref]['type'] !== 'asset'];
    }
    if (!$scos) foreach ($res as $id => $r) if ($r['href'] !== '' && $r['type'] !== 'asset') $scos[] = ['id' => preg_replace('/[^A-Za-z0-9_.-]/', '_', $id), 'title' => $id, 'href' => $r['href'], 'mastery' => null, 'sco' => true];
    if (!$scos) return $cache[$rel] = ['error' => 'El manifiesto no tiene ningún recurso que abrir.'];
    if ($schemaVer !== '' && stripos($schemaVer, '1.2') === false) $warn[] = 'El paquete dice ser SCORM «' . $schemaVer . '»; el aula habla SCORM 1.2. Muchos paquetes 2004 funcionan igual en su modo básico, pero puede que no guarden todo.';
    return $cache[$rel] = ['title' => $title ?: basename($rel), 'version' => $schemaVer, 'scos' => $scos, 'warn' => $warn];
}

/** Paquetes subidos: rel => manifiesto. */
function lms_scorm_packages(): array
{
    $out = [];
    foreach (glob(lms_scorm_root() . '/*/imsmanifest.xml') ?: [] as $f) { $rel = 'scorm/' . basename(dirname($f)); $out[$rel] = lms_scorm_manifest($rel); }
    ksort($out);
    return $out;
}

/** Cortar el acceso directo a scorm/ (se sirve por /aula/sco/…). */
function lms_scorm_protect(): void
{
    $ht = lms_scorm_root() . '/.htaccess';
    if (!is_dir(lms_scorm_root())) @mkdir(lms_scorm_root(), 0755, true);
    if (!is_file($ht)) @file_put_contents($ht, "# Aula (paquete lms): los paquetes SCORM se sirven por /" . lms_settings()['route'] . "/sco/ a quien puede ver la lección\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
}

/**
 * Descomprime un .zip SCORM en scorm/<nombre>/. Se saltan rutas peligrosas y archivos que el servidor ejecutaría
 * (.php, .phtml, .phar, .cgi, .htaccess…). Devuelve [ok, rel o mensaje de error, avisos].
 */
function lms_scorm_install(string $zipFile, string $name, bool $replace = false): array
{
    if (!class_exists('ZipArchive')) return [false, 'Este servidor no tiene la extensión Zip de PHP.', []];
    $slug = cms_slugify($name) ?: 'paquete';
    $rel = 'scorm/' . $slug;
    $dst = CMS_ROOT . '/' . $rel;
    if (is_dir($dst) && !$replace) return [false, 'Ya hay un paquete «' . $slug . '». Marca «Reemplazar» o ponle otro nombre.', []];
    $z = new ZipArchive();
    if ($z->open($zipFile) !== true) return [false, 'El archivo no es un .zip válido.', []];
    // el manifiesto puede venir en una subcarpeta (zip de una carpeta): se toma esa como raíz
    $prefix = null;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = str_replace('\\', '/', (string) $z->getNameIndex($i));
        if (preg_match('#^(.*/)?imsmanifest\.xml$#i', $n, $m) && ($prefix === null || strlen($m[1] ?? '') < strlen($prefix))) $prefix = $m[1] ?? '';
    }
    if ($prefix === null) { $z->close(); return [false, 'El zip no trae imsmanifest.xml: no es un paquete SCORM.', []]; }
    $tmp = $dst . '.tmp-' . bin2hex(random_bytes(3));
    @mkdir($tmp, 0755, true);
    $skipped = []; $n = 0; $bytes = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $name0 = str_replace('\\', '/', (string) $z->getNameIndex($i));
        if ($prefix !== '' && strpos($name0, $prefix) !== 0) continue;
        $relf = substr($name0, strlen($prefix));
        if ($relf === '' || substr($relf, -1) === '/') continue;
        if (strpos($relf, '..') !== false || $relf[0] === '/' || preg_match('#(^|/)\.#', $relf) || preg_match('/\.(php\d?|phtml|phar|pht|cgi|pl|py|sh|asp|aspx|jsp|htaccess|htpasswd|ini|user\.ini)$/i', $relf)) { $skipped[] = $relf; continue; }
        $stat = $z->statIndex($i);
        $bytes += (int) ($stat['size'] ?? 0);
        if ($bytes > 2 * 1024 * 1024 * 1024) { $z->close(); lms_scorm_rmdir($tmp); return [false, 'El paquete descomprimido pasa de 2 GB.', []]; }
        $out = $tmp . '/' . $relf;
        if (!is_dir(dirname($out))) @mkdir(dirname($out), 0755, true);
        $in = $z->getStream($name0);
        if (!$in) continue;
        $fh = fopen($out, 'wb');
        stream_copy_to_stream($in, $fh);
        fclose($fh); fclose($in);
        $n++;
    }
    $z->close();
    if (!is_file($tmp . '/imsmanifest.xml')) { lms_scorm_rmdir($tmp); return [false, 'No se pudo extraer imsmanifest.xml.', []]; }
    if (is_dir($dst)) lms_scorm_rmdir($dst);
    if (!@rename($tmp, $dst)) { lms_scorm_rmdir($tmp); return [false, 'No se pudo escribir en ' . $rel . '/.', []]; }
    lms_scorm_protect();
    $man = lms_scorm_manifest($rel);
    if (isset($man['error'])) return [false, $man['error'], []];
    $warn = $man['warn'];
    if ($skipped) $warn[] = 'Se omitieron ' . count($skipped) . ' archivo(s) que no se sirven por seguridad: ' . implode(', ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '…' : '') . '.';
    return [true, $rel, $warn];
}

function lms_scorm_rmdir(string $dir): void
{
    if (!is_dir($dir) || strpos(realpath($dir) ?: '', realpath(lms_scorm_root()) ?: '//') !== 0) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($dir);
}

/* ================================================================== datos del alumno */

/** Datos guardados de los SCO de una lección: sco => [cmi…]. */
function lms_scorm_data(string $uid, string $course, string $lesson): array
{
    return (array) (lms_progress($uid)['courses'][$course]['scorm'][$lesson] ?? []);
}

/** Elementos que se guardan de cada SCO (lo demás, como cmi.interactions, solo vive en la sesión). */
function lms_scorm_keep(): array
{
    return ['cmi.core.lesson_status', 'cmi.core.lesson_location', 'cmi.core.score.raw', 'cmi.core.score.min', 'cmi.core.score.max',
            'cmi.core.exit', 'cmi.suspend_data', 'cmi.comments'];
}

/** Suma un tiempo de sesión SCORM ("HHHH:MM:SS.SS") a un total en segundos. */
function lms_scorm_secs(string $t): float
{
    return preg_match('/^(\d{1,4}):(\d{2}):(\d{2}(?:\.\d{1,2})?)$/', trim($t), $m) ? $m[1] * 3600 + $m[2] * 60 + (float) $m[3] : 0.0;
}

function lms_scorm_time(float $s): string
{
    $s = max(0, $s);
    return sprintf('%04d:%02d:%05.2f', (int) floor($s / 3600), (int) floor(fmod($s, 3600) / 60), fmod($s, 60));
}

/**
 * Guarda lo que mandó un SCO. $cmi: elemento => valor (solo los de lms_scorm_keep y cmi.core.session_time).
 * Marca la lección terminada cuando todos sus SCO están completed o passed. Devuelve ['done' => bool, 'status' => …].
 */
function lms_scorm_save(string $uid, string $course, array $lesson, string $sco, array $cmi): array
{
    $man = lms_scorm_manifest(lms_scorm_rel((string) ($lesson['scorm'] ?? '')));
    $ids = array_column((array) ($man['scos'] ?? []), 'id');
    if (!in_array($sco, $ids, true)) return ['done' => false, 'status' => ''];
    $p = lms_progress($uid);
    $c = array_replace(['lessons' => []], (array) ($p['courses'][$course] ?? []));
    if (empty($c['enrolled'])) { $c['enrolled'] = date('Y-m-d H:i'); $c['by'] = 'alumno'; }
    $slug = (string) $lesson['slug'];
    $d = (array) ($c['scorm'][$slug][$sco] ?? []);
    foreach (lms_scorm_keep() as $k) if (array_key_exists($k, $cmi)) $d[$k] = mb_substr((string) $cmi[$k], 0, $k === 'cmi.suspend_data' ? 4096 : ($k === 'cmi.comments' ? 4096 : 255));
    if (isset($cmi['cmi.core.session_time'])) $d['total_time'] = round((float) ($d['total_time'] ?? 0) + lms_scorm_secs((string) $cmi['cmi.core.session_time']), 2);
    $d['updated'] = date('Y-m-d H:i');
    $c['scorm'][$slug][$sco] = $d;
    $c['last'] = $slug;
    // ¿todos los SCO terminados?
    $all = true;
    foreach ($ids as $id) if (!in_array((string) ($c['scorm'][$slug][$id]['cmi.core.lesson_status'] ?? ''), ['completed', 'passed'], true)) $all = false;
    if ($all && empty($c['lessons'][$slug])) $c['lessons'][$slug] = date('Y-m-d H:i');
    $p['courses'][$course] = $c;
    lms_course_recheck($p, $course, $uid);
    lms_progress_save($uid, $p);
    return ['done' => !empty($c['lessons'][$slug]), 'status' => (string) ($d['cmi.core.lesson_status'] ?? '')];
}

/** Resumen de una lección SCORM para mostrar: ['status' => …, 'score' => n|null, 'time' => segundos]. */
function lms_scorm_summary(array $data): array
{
    $rank = ['passed' => 5, 'completed' => 4, 'failed' => 3, 'incomplete' => 2, 'browsed' => 1, 'not attempted' => 0];
    $st = ''; $scores = []; $time = 0.0;
    foreach ($data as $d) {
        $s = (string) ($d['cmi.core.lesson_status'] ?? '');
        if ($st === '' || ($rank[$s] ?? 0) < ($rank[$st] ?? 0)) $st = $s;   // el más atrasado manda
        if (is_numeric($d['cmi.core.score.raw'] ?? null)) {
            $max = is_numeric($d['cmi.core.score.max'] ?? null) && (float) $d['cmi.core.score.max'] > 0 ? (float) $d['cmi.core.score.max'] : 100;
            $scores[] = (float) $d['cmi.core.score.raw'] * 100 / $max;
        }
        $time += (float) ($d['total_time'] ?? 0);
    }
    return ['status' => $st, 'score' => $scores ? (int) round(array_sum($scores) / count($scores)) : null, 'time' => $time];
}

/** Nombre legible de un estado SCORM. */
function lms_scorm_status_text(string $s): string
{
    return lms_tx(['passed' => 'scorm_passed', 'completed' => 'scorm_completed', 'failed' => 'scorm_failed', 'incomplete' => 'scorm_incomplete', 'browsed' => 'scorm_incomplete'][$s] ?? 'scorm_new');
}

/* ================================================================== exportar un curso como SCORM 1.2 */

/** URL absoluta para lo que en el sitio es relativo (imágenes del contenido, enlaces). */
function lms_scorm_abs_html(string $html): string
{
    $origin = cms_origin();
    return preg_replace_callback('/\b(src|href|poster)=("|\')(\/(?!\/)[^"\']*)\2/i', fn($m) => $m[1] . '=' . $m[2] . $origin . $m[3] . $m[2], $html) ?? $html;
}

/**
 * Arma el paquete de un curso. $opt: 'videos' (incluir los MP4 locales; si no, se enlazan los de YouTube/Vimeo y los
 * locales quedan fuera), 'files' (incluir los materiales locales). Devuelve [ruta del zip temporal, avisos] o
 * [null, [error]].
 */
function lms_scorm_export(string $courseSlug, string $lang, array $opt = []): array
{
    if (!class_exists('ZipArchive')) return [null, ['Este servidor no tiene la extensión Zip de PHP.']];
    $course = lms_course($courseSlug, false);
    if (!$course) return [null, ['No existe el curso.']];
    $warn = [];
    $title = (string) cms_f($course, 'title', $lang);
    $tmp = tempnam(sys_get_temp_dir(), 'scorm');
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return [null, ['No se pudo crear el zip.']];
    $added = [];
    $addFile = function (string $path, string $prefix) use ($z, &$added, &$warn, $opt): string {
        $local = lms_local_file($path);
        if (!$local) return preg_match('#^https?://#i', $path) ? $path : cms_origin() . '/' . ltrim(lms_media_url($path), '/');
        $name = $prefix . '/' . (substr(md5($local), 0, 6)) . '-' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($local));
        if (!isset($added[$name])) { $z->addFile($local, $name); $added[$name] = filesize($local); }
        return $name;
    };
    $steps = [];
    foreach (lms_steps($courseSlug, false) as $s) {
        $st = ['id' => (string) $s['slug'], 'title' => (string) cms_f($s, 'title', $lang), 'module' => (string) cms_f($s, 'module', $lang)];
        if (lms_is_quiz($s)) {
            $full = cms_item(lms_quiz_type(), (string) $s['slug'], false) ?? $s;
            $qs = lms_quiz_questions($full, $lang)['questions'];
            $cfg = lms_quiz_cfg($full);
            $st += ['type' => 'quiz', 'pass' => $cfg['pass'], 'intro' => lms_scorm_abs_html((string) cms_f($full, 'intro', $lang)),
                    'questions' => array_values(array_map(fn($q) => ['text' => lms_quiz_html($q['text']), 'kind' => $q['kind'], 'points' => $q['points'],
                        'options' => array_map(fn($o) => [lms_quiz_html($o[0]), $o[1]], $q['options']), 'answers' => $q['kind'] === 'short' ? array_map('lms_quiz_norm', array_map('strval', $q['answers'])) : $q['answers'],
                        'tol' => $q['tol'], 'explain' => lms_quiz_html($q['explain'])], $qs))];
            if (array_filter($qs, fn($q) => $q['kind'] === 'open')) $warn[] = '«' . $st['title'] . '» tiene preguntas abiertas: en el paquete no cuentan para la calificación.';
        } else {
            $full = cms_item(lms_lesson_type(), (string) $s['slug'], false) ?? $s;
            $video = trim((string) ($full['video'] ?? ''));
            $vid = ['kind' => '', 'src' => '', 'poster' => ''];
            if ($video !== '') {
                if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $video, $m)) $vid = ['kind' => 'youtube', 'src' => $m[1], 'poster' => ''];
                elseif (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $video, $m)) $vid = ['kind' => 'vimeo', 'src' => $m[1], 'poster' => ''];
                elseif (lms_local_file($video)) {
                    if (!empty($opt['videos'])) $vid = ['kind' => 'file', 'src' => $addFile($video, 'media'), 'poster' => ($full['poster'] ?? '') !== '' ? $addFile((string) $full['poster'], 'media') : ''];
                    else $warn[] = 'El video de «' . $st['title'] . '» no se incluyó (marca «Incluir los videos»).';
                } else $vid = ['kind' => 'file', 'src' => $video, 'poster' => ''];
            }
            if (!empty($full['scorm'])) $warn[] = '«' . $st['title'] . '» es un paquete SCORM: no se puede meter dentro de otro; queda como lección sin contenido.';
            $files = [];
            foreach (lms_file_list($full) as [$l, $pth]) $files[] = [$l, !empty($opt['files']) || !lms_local_file($pth) ? $addFile($pth, 'files') : ''];
            $st += ['type' => 'lesson', 'summary' => (string) cms_f($full, 'summary', $lang), 'duration' => (string) ($full['duration'] ?? ''), 'video' => $vid,
                    'body' => lms_scorm_abs_html(cms_content((string) cms_f($full, 'body', $lang))), 'files' => array_values(array_filter($files, fn($f) => $f[1] !== ''))];
        }
        $steps[] = $st;
    }
    if (!$steps) { $z->close(); @unlink($tmp); return [null, ['El curso no tiene lecciones publicadas.']]; }
    $cfiles = [];
    foreach (lms_file_list($course) as [$l, $pth]) if (!empty($opt['files']) || !lms_local_file($pth)) $cfiles[] = [$l, $addFile($pth, 'files')];
    $data = ['title' => $title, 'lang' => $lang, 'site' => (string) (cms_settings()['site_name'] ?? cms_config('name')), 'url' => cms_origin() . cms_url('item:' . lms_course_type(), $lang, $courseSlug),
             'excerpt' => (string) cms_f($course, 'excerpt', $lang), 'files' => $cfiles, 'steps' => $steps, 'videoPct' => lms_settings()['video_pct'],
             'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($course['color'] ?? '')) ? $course['color'] : '#0369a1',
             't' => array_combine($k = ['start', 'next', 'prev', 'done', 'mark', 'submit', 'retry', 'score', 'passed', 'failed', 'materials', 'contents', 'quiz', 'correct', 'wrong', 'your', 'right_answer', 'complete', 'resume', 'true', 'false', 'unanswered', 'open_note', 'multi'],
                    array_map(fn($x) => lms_tx('sx_' . $x), $k))];
    $dir = __DIR__ . '/assets/scorm-player';
    $z->addFromString('index.html', str_replace(['{{TITLE}}', '{{LANG}}'], [cms_e($title), cms_e($lang)], (string) file_get_contents($dir . '/index.html')));
    $z->addFile($dir . '/player.js', 'player.js');
    $z->addFile($dir . '/player.css', 'player.css');
    $z->addFromString('course.js', 'window.COURSE = ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ";\n");
    // manifiesto SCORM 1.2: un SCO; los archivos van como dependencias del recurso
    $files = array_merge(['index.html', 'player.js', 'player.css', 'course.js'], array_keys($added));
    $id = 'CMS-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '-', $courseSlug));
    $esc = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES);
    $quizzes = array_filter($steps, fn($s) => $s['type'] === 'quiz');
    $mastery = $quizzes ? '<adlcp:masteryscore>' . (int) round(array_sum(array_map(fn($q) => $q['pass'], $quizzes)) / count($quizzes)) . '</adlcp:masteryscore>' : '';
    $man = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<manifest identifier="' . $esc($id) . '" version="1.0" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd http://www.imsglobal.org/xsd/imsmd_rootv1p2p1 imsmd_rootv1p2p1.xsd http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">' . "\n"
        . "  <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>\n"
        . '  <organizations default="ORG-1"><organization identifier="ORG-1"><title>' . $esc($title) . "</title>\n"
        . '    <item identifier="ITEM-1" identifierref="RES-1" isvisible="true"><title>' . $esc($title) . '</title>' . $mastery . "</item>\n"
        . "  </organization></organizations>\n"
        . '  <resources><resource identifier="RES-1" type="webcontent" adlcp:scormtype="sco" href="index.html">' . "\n"
        . implode('', array_map(fn($f) => '    <file href="' . $esc($f) . '"/>' . "\n", $files))
        . "  </resource></resources>\n</manifest>\n";
    $z->addFromString('imsmanifest.xml', $man);
    $z->close();
    $mb = array_sum($added) / 1048576;
    if ($mb > 200) $warn[] = sprintf('El paquete pesa %.0f MB: algunos LMS limitan el tamaño de subida.', $mb);
    return [$tmp, $warn];
}
