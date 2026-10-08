<?php
/**
 * Paquete lms — importar cursos hechos a mano en Archivos y carpetas (p. ej. /capacitacion/arbitraje/).
 *
 * Lee el formato de esas páginas: el index.html del curso con su <h1>, el párrafo .intro y la lista JS
 *   const MODULOS = [ { archivo: "videos/01.mp4", duracion: "9:43", titulo: "…", para: "…", texto: "…" }, … ];
 * y, si la carpeta de arriba es un catálogo (index.html con const CURSOS = [ { clave, estado, liga, color, nombre,
 * titulo, texto, temas: [...], minutos, para }, … ]), los datos de la tarjeta del curso. Crea el curso y una lección
 * por video, que apunta al MP4 en su sitio (no copia nada); la portada sale de portadas/<video>.jpg si existe.
 * Los cursos del catálogo en estado "pronto" pueden crearse como "Próximamente".
 *
 * Además (1.44) trae lo que acompaña al curso, en la carpeta del curso o en aula/<carpeta>/ (como lo entrega
 * publicar_curso.py): contenido/leccion-NN.html (campo Contenido de la lección NN), materiales/* (materiales del
 * curso), evaluaciones/*.txt|.gift|.xml (una evaluación por archivo; *-L03 va después de la lección 3, *final al
 * final) y un curso.json opcional con acceso, nivel, icono, color, modulo, constancia, materiales y evaluaciones
 * ([{archivo, titulo, despues, aprobar, intentos, tiempo, preguntas, mezclar, revelar, requisito}]). Cada archivo
 * de preguntas puede llevar esas mismas opciones en una cabecera entre líneas "---". Un curso ya importado se puede
 * completar con lms_import_complete().
 */
declare(strict_types=1);

/** Valor de una cadena JS ("…" o '…') ya sin comillas ni escapes. */
function lms_imp_str(string $raw): string
{
    $q = $raw[0] ?? '"';
    $in = substr($raw, 1, -1);
    if ($q === "'") $in = str_replace(["\\'", '"'], ["'", '\\"'], $in);
    $v = json_decode('"' . $in . '"');
    return is_string($v) ? $v : stripcslashes($in);
}

/** Lista de objetos de un arreglo JS "const NOMBRE = [ {…}, … ];" con claves simples: cadenas, números y listas de cadenas. */
function lms_imp_array(string $html, string $name): array
{
    if (!preg_match('/\b(?:const|let|var)\s+' . preg_quote($name, '/') . '\s*=\s*\[(.*?)\]\s*;/s', $html, $m)) return [];
    $str = '"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'';
    $out = [];
    preg_match_all('/\{((?:' . $str . '|[^{}"\'])*)\}/s', $m[1], $objs);
    foreach ($objs[1] as $body) {
        $o = [];
        preg_match_all('/([A-Za-z_]\w*)\s*:\s*(' . $str . '|-?\d+(?:\.\d+)?|true|false|\[(?:' . $str . '|[^\]"\'])*\])/s', $body, $kv, PREG_SET_ORDER);
        foreach ($kv as [, $k, $v]) {
            if ($v[0] === '[') { preg_match_all('/' . $str . '/s', $v, $ss); $o[$k] = array_map('lms_imp_str', $ss[0]); }
            elseif ($v[0] === '"' || $v[0] === "'") $o[$k] = lms_imp_str($v);
            elseif ($v === 'true' || $v === 'false') $o[$k] = $v === 'true';
            else $o[$k] = $v + 0;
        }
        if ($o) $out[] = $o;
    }
    return $out;
}

/** Ícono del catálogo si es uno de los del aula. */
function lms_imp_icon(string $k): string { return isset(lms_course_icons()[$k]) ? $k : ''; }

/** Texto plano de un fragmento HTML (espacios colapsados). */
function lms_imp_text(string $html): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

/** Carpetas de Archivos y carpetas que parecen un curso (index.html con MODULOS), con la ruta relativa como clave. */
function lms_import_candidates(): array
{
    $out = [];
    $skip = lms_core_dirs();
    foreach (array_merge(glob(CMS_ROOT . '/*/index.html') ?: [], glob(CMS_ROOT . '/*/*/index.html') ?: [], glob(CMS_ROOT . '/*/*/*/index.html') ?: []) as $f) {
        $rel = trim(substr(dirname($f), strlen(CMS_ROOT)), '/');
        if (in_array(strtolower(explode('/', $rel)[0]), $skip, true)) continue;
        $h = (string) @file_get_contents($f, false, null, 0, 400000);
        if (preg_match('/\bMODULOS\s*=\s*\[/', $h)) $out[$rel] = lms_imp_text(preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $h, $m) ? $m[1] : $rel);
    }
    ksort($out);
    return $out;
}

/** Lo que se crearía al importar la carpeta $rel: ['course' => elemento, 'lessons' => [...], 'soon' => [...], 'warn' => [...]]. */
function lms_import_plan(string $rel, array $opt = []): array
{
    $rel = trim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || strpos($rel, '..') !== false || !isset(lms_import_candidates()[$rel])) return ['error' => 'No es una carpeta de curso (index.html con la lista MODULOS).'];
    $dir = CMS_ROOT . '/' . $rel;
    $html = (string) file_get_contents($dir . '/index.html');
    $mods = lms_imp_array($html, 'MODULOS');
    if (!$mods) return ['error' => 'No se pudo leer la lista MODULOS de ' . $rel . '/index.html.'];
    $folder = basename($rel);
    $h1 = preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m) ? lms_imp_text($m[1]) : $folder;
    $minutes = preg_match('/~\s*(\d+)\s*min/i', $h1, $mm) ? (int) $mm[1] : 0;
    $h1 = trim(preg_replace('/\s*~\s*\d+\s*min\w*\s*$/i', '', $h1) ?? $h1);
    $intro = preg_match('/<p[^>]*class="[^"]*\bintro\b[^"]*"[^>]*>(.*?)<\/p>/is', $html, $m) ? lms_imp_text($m[1]) : '';

    // la tarjeta en el catálogo de la carpeta de arriba, si la hay
    $card = []; $soon = []; $catOrder = 0;
    $parent = dirname($rel);
    $catHtml = $parent !== '.' && is_file(CMS_ROOT . '/' . $parent . '/index.html') ? (string) file_get_contents(CMS_ROOT . '/' . $parent . '/index.html') : '';
    $catalog = $catHtml !== '' ? lms_imp_array($catHtml, 'CURSOS') : [];
    $series = preg_match('/class="serie">([^<$]+)</', $catHtml, $sm) ? lms_imp_text($sm[1]) : '';   // la serie fija de las tapas ("Curso · NextSabi")
    foreach ($catalog as $i => $c) {
        $liga = trim((string) ($c['liga'] ?? ''), '/');
        if ($liga === $folder || (string) ($c['clave'] ?? '') === $folder) { $card = $c; $catOrder = $i + 1; }
        elseif (($c['estado'] ?? '') !== 'ya' && !empty($c['clave'])) $soon[] = $c + ['_order' => $i + 1];
    }
    if (!$minutes && !empty($card['minutos'])) $minutes = (int) $card['minutos'];

    $slug = cms_slugify((string) ($card['clave'] ?? $folder)) ?: cms_slugify($folder);
    $es = cms_default_lang();
    $course = [
        'slug' => $slug, 'title' => [$es => (string) ($card['titulo'] ?? $h1)],
        'excerpt' => [$es => (string) ($card['texto'] ?? $intro)],
        'body' => [$es => $intro !== '' ? '<p>' . cms_e($intro) . '</p>' : ''],
        'goals' => [$es => array_values(array_filter(array_map(fn($m) => (string) ($m['titulo'] ?? ''), $mods)))],
        'topics' => [$es => array_values((array) ($card['temas'] ?? []))],
        'audience' => [$es => (string) ($card['para'] ?? '')],
        'duration' => [$es => $minutes ? ($minutes >= 60 ? intdiv($minutes, 60) . ' h' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' min' : '') : $minutes . ' min') : ''],
        'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($card['color'] ?? '')) ? strtolower((string) $card['color']) : '',
        'order' => $catOrder ?: '', 'access' => '', 'imported_from' => $rel,
        'short' => [$es => (string) ($card['nombre'] ?? '')], 'series' => [$es => $series], 'icon' => lms_imp_icon((string) ($card['icono'] ?? '')),
    ];
    $lessons = []; $warn = [];
    foreach ($mods as $i => $m) {
        $file = trim((string) ($m['archivo'] ?? ''), '/');
        if ($file === '') continue;
        $video = $rel . '/' . $file;
        if (!is_file(CMS_ROOT . '/' . $video)) $warn[] = 'No existe ' . $video;
        $posterRel = $rel . '/' . preg_replace('/\.(mp4|webm|m4v|mov)$/i', '.jpg', str_replace('videos/', 'portadas/', $file));
        $base = cms_slugify(pathinfo($file, PATHINFO_FILENAME));
        $lessons[] = [
            'slug' => $slug . '-' . ($base ?: (string) ($i + 1)), 'title' => [$es => (string) ($m['titulo'] ?? ('Módulo ' . ($i + 1)))],
            'summary' => [$es => (string) ($m['texto'] ?? '')], 'audience' => [$es => (string) ($m['para'] ?? '')],
            'video' => $video, 'poster' => is_file(CMS_ROOT . '/' . $posterRel) ? $posterRel : '',
            'duration' => ($d = (string) ($m['duracion'] ?? '')) !== '' ? $d . (preg_match('/min/i', $d) ? '' : ' min') : '',
            'course' => $slug, 'order' => $i + 1, 'module' => [$es => ''], 'body' => [$es => ''], 'files' => [], 'preview' => false,
        ];
    }
    // lo que acompaña: contra las lecciones que ya existen si el curso ya se importó, o contra las del plan
    $existing = cms_item(lms_course_type(), $slug, false) ? lms_lessons($slug, false) : [];
    $extras = lms_import_extras($rel, $slug, $existing ?: $lessons, $opt);
    return ['course' => $course, 'lessons' => $lessons, 'soon' => $soon, 'series' => $series, 'warn' => $warn, 'rel' => $rel, 'catalog' => $catalog ? $parent : '', 'extras' => $extras];
}

/** Página que reemplaza a una vieja: lleva a la nueva dirección (meta refresh + enlace; sin PHP, sirve en carpetas estáticas). */
function lms_import_redirect_html(string $to, string $title): string
{
    $u = cms_e($to);
    return "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"robots\" content=\"noindex\">\n"
        . "<title>" . cms_e($title) . "</title><meta http-equiv=\"refresh\" content=\"0; url=$u\"><link rel=\"canonical\" href=\"$u\">\n"
        . "<script>location.replace(" . json_encode($to) . " + location.hash);</script></head>\n"
        . "<body><p>Esta página se mudó al aula: <a href=\"$u\">" . cms_e($title) . "</a>.</p></body></html>\n";
}

/**
 * Crea el curso y sus lecciones. Opciones: 'publish' (curso publicado; si no, borrador), 'soon' (crear los cursos
 * "pronto" del catálogo como Próximamente), 'redirect' (cambiar los index.html viejos por una redirección al aula;
 * el original queda en data/backups/lms-import/). Devuelve [ok, mensajes].
 */
function lms_import_run(string $rel, array $opt): array
{
    $plan = lms_import_plan($rel, $opt);
    if (isset($plan['error'])) return [false, [$plan['error']]];
    $ct = lms_course_type(); $lt = lms_lesson_type();
    if (!cms_type($ct) || !cms_type($lt)) return [false, ['Faltan las colecciones de cursos y lecciones (Ajustes → Aula).']];
    $c = $plan['course'];
    $now = date('Y-m-d');
    $msgs = [];
    $old = cms_item($ct, $c['slug'], false);
    if ($old) {
        // el curso ya existe: si es solo la carátula (p. ej. creada como «Próximamente» al importar otro curso del
        // catálogo, sin lecciones), se llena con los datos del curso y deja de ser «Próximamente»; si ya tiene
        // lecciones, se respeta lo que tiene y solo se agrega lo que falta
        $empty = !lms_lessons($c['slug'], false);
        if ($empty) {
            $c = array_replace($old, array_filter($c, fn($v) => $v !== '' && $v !== [] && !(is_array($v) && !array_filter($v))), ['soon' => false, 'updated' => $now]);
            if (!empty($opt['publish'])) $c['status'] = 'published';
            if (!cms_item_save($ct, $c)) return [false, ['No se pudo guardar el curso en data/content/' . $ct . '/.']];
        } else $c = $old;
    } else {
        $c += ['status' => !empty($opt['publish']) ? 'published' : 'draft', 'created' => $now, 'updated' => $now];
        if (!cms_item_save($ct, $c)) return [false, ['No se pudo guardar el curso en data/content/' . $ct . '/.']];
    }
    $n = 0; $skipped = 0;
    foreach ($plan['lessons'] as $l) {
        if (cms_item($lt, $l['slug'], false)) { $skipped++; continue; }
        if (cms_item_save($lt, $l + ['status' => 'published', 'created' => $now, 'updated' => $now])) $n++;
    }
    $ttl = (string) cms_f($c, 'title', cms_default_lang());
    if (!$old) $msgs[] = 'Curso «' . $ttl . '» creado (' . ($c['status'] === 'published' ? 'publicado' : 'borrador') . ') con ' . $n . ' lecciones' . ($skipped ? '; ' . $skipped . ' ya existían y no se tocaron' : '') . '.';
    elseif ($empty) $msgs[] = 'El curso «' . $ttl . '» ya existía sin lecciones (su carátula): se llenó con los datos del curso' . (!empty($old['soon']) ? ', dejó de ser «Próximamente»' : '') . ' y ' . ($c['status'] === 'published' ? 'está publicado' : 'sigue en borrador') . '; ' . $n . ' lecciones creadas.';
    else $msgs[] = 'El curso «' . $ttl . '» ya existía: no se tocaron sus datos; ' . ($n ? $n . ' lección(es) nuevas creadas' : 'no faltaba ninguna lección') . ($skipped ? ' y ' . $skipped . ' ya existían' : '') . '.';
    if (lms_settings()['protect']) $msgs[] = 'Los videos de ' . $plan['rel'] . ' quedaron protegidos: solo se ven desde el aula.';
    if (!empty($opt['soon'])) {
        $k = 0;
        foreach ($plan['soon'] as $s) {
            $sl = cms_slugify((string) $s['clave']);
            if ($sl === '' || cms_item($ct, $sl, false)) continue;
            $es = cms_default_lang();
            if (cms_item_save($ct, ['slug' => $sl, 'title' => [$es => (string) ($s['titulo'] ?? $s['nombre'] ?? $sl)], 'excerpt' => [$es => (string) ($s['texto'] ?? '')],
                'topics' => [$es => array_values((array) ($s['temas'] ?? []))], 'audience' => [$es => (string) ($s['para'] ?? '')],
                'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($s['color'] ?? '')) ? strtolower((string) $s['color']) : '',
                'short' => [$es => (string) ($s['nombre'] ?? '')], 'series' => [$es => (string) ($plan['series'] ?? '')], 'icon' => lms_imp_icon((string) ($s['icono'] ?? '')),
                'order' => $s['_order'], 'soon' => true, 'access' => '', 'status' => 'published', 'created' => $now, 'updated' => $now])) $k++;
        }
        if ($k) $msgs[] = $k . ' curso(s) en preparación creados como «Próximamente».';
    }
    if (!empty($opt['redirect'])) {
        $pairs = [[$plan['rel'], cms_url('item:' . $ct, cms_default_lang(), $c['slug']), $c['title'][cms_default_lang()]]];
        if ($plan['catalog'] !== '') $pairs[] = [$plan['catalog'], cms_url('list:' . $ct, cms_default_lang()), 'Cursos'];
        foreach ($pairs as [$d, $to, $tt]) {
            $f = CMS_ROOT . '/' . $d . '/index.html';
            if (trim($d, '/') === lms_settings()['route'] || cms_segment(cms_type($ct) ?? [], cms_default_lang()) === explode('/', $d)[0]) { $msgs[] = 'No se redirigió /' . $d . '/: es la misma dirección del aula o de los cursos.'; continue; }
            $bk = CMS_DATA . '/backups/lms-import/' . date('Ymd-His') . '-' . str_replace('/', '_', $d) . '-index.html';
            if (!is_dir(dirname($bk))) @mkdir(dirname($bk), 0755, true);
            if (is_file($f) && @copy($f, $bk) && @file_put_contents($f, lms_import_redirect_html($to, $tt)) !== false) $msgs[] = '/' . $d . '/ ahora lleva a ' . $to . ' (el original está en data/backups/lms-import/).';
            else $msgs[] = 'No se pudo cambiar /' . $d . '/index.html.';
        }
    }
    $msgs = array_merge($msgs, lms_import_apply_extras($c['slug'], $plan['extras'], array_replace($opt, ['publish_quiz' => !empty($opt['publish']) || !empty($opt['publish_quiz'])])));
    foreach (array_merge($plan['warn'], $plan['extras']['warn']) as $w) $msgs[] = 'Aviso: ' . $w;
    return [true, $msgs];
}

/* ================================================================== lo que acompaña al curso: contenido, materiales, evaluaciones */

/**
 * Carpeta con lo que acompaña al curso: contenido/leccion-NN.html, materiales/*, evaluaciones/*.txt|.gift|.xml y un
 * curso.json opcional. Se busca en la carpeta del curso y en aula/<carpeta>/ (como lo entrega publicar_curso.py).
 * Devuelve la ruta relativa o ''.
 */
function lms_import_extras_dirs(string $rel, string $slug): array
{
    $out = [];
    // la carpeta del curso, su aula/, y aula/<curso>/ junto a cualquiera de sus carpetas de arriba (si el zip se subió
    // dentro de otra carpeta, aula/ queda al lado de capacitacion/, no en la raíz)
    $cands = [$rel, $rel . '/aula'];
    for ($up = dirname($rel); ; $up = dirname($up)) {
        $pre = $up === '.' || $up === '' ? '' : $up . '/';
        foreach ([basename($rel), $slug] as $n) $cands[] = $pre . 'aula/' . $n;
        if ($pre === '') break;
    }
    foreach (array_unique($cands) as $d) {
        $abs = CMS_ROOT . '/' . $d;
        if (is_dir($abs) && (is_dir($abs . '/contenido') || is_dir($abs . '/evaluaciones') || is_dir($abs . '/materiales') || is_file($abs . '/curso.json'))) $out[] = $d;
    }
    return $out;
}

/** Primera de esas carpetas que tiene $part (contenido, evaluaciones, materiales o curso.json), o ''. */
function lms_import_part(array $dirs, string $part): string
{
    foreach ($dirs as $d) if (file_exists(CMS_ROOT . '/' . $d . '/' . $part) && (is_file(CMS_ROOT . '/' . $d . '/' . $part) || glob(CMS_ROOT . '/' . $d . '/' . $part . '/*'))) return $d;
    return '';
}

/**
 * Cabecera opcional de un archivo de preguntas, entre dos líneas "---":
 *   titulo: Lección 1 · …    despues: 1 | final    aprobar: 80    intentos: 2    tiempo: 20 (min)
 *   preguntas: 10 (por intento)    mezclar: si    revelar: siempre|aciertos|nada    requisito: si    modulo: …
 * Devuelve [opciones, resto del texto].
 */
function lms_import_front(string $raw): array
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $raw)) ?? $raw;
    if (!preg_match('/^---\n(.*?)\n---\n/s', $raw, $m)) return [[], $raw];
    $o = [];
    foreach (explode("\n", $m[1]) as $l) if (preg_match('/^\s*([a-záéíóúñ_]+)\s*:\s*(.*?)\s*$/iu', $l, $kv)) $o[lms_quiz_norm($kv[1])] = $kv[2];
    return [$o, substr($raw, strlen($m[0]))];
}

/** "si", "sí", "true", "1" → true. */
function lms_import_bool($v): bool { return in_array(lms_quiz_norm((string) $v), ['si', 'yes', 'true', '1'], true); }

/**
 * Plan de lo que acompaña al curso (para lms_import_plan). $lessons: las lecciones del plan (o las del curso que ya
 * existe), en orden. $opt: valores por omisión del formulario (pass, attempts, final_attempts, shuffle, reveal, module).
 */
function lms_import_extras(string $rel, string $slug, array $lessons, array $opt = []): array
{
    $out = ['dir' => '', 'manifest' => [], 'bodies' => [], 'materials' => [], 'quizzes' => [], 'course' => [], 'module' => '', 'warn' => []];
    $dirs = lms_import_extras_dirs($rel, $slug);
    if (!$dirs) return $out;
    $out['dir'] = implode(' y /', $dirs);
    $es = cms_default_lang();
    foreach ($dirs as $d) if (explode('/', $d)[0] === lms_settings()['route'])
        $out['warn'][] = 'La carpeta /' . explode('/', $d)[0] . '/ de la raíz tiene el mismo nombre que la dirección del aula: mientras exista, /' . lms_settings()['route'] . ' puede dejar de abrir. Al importar, los materiales se copian a /' . $rel . '/materiales/; después borra esa carpeta en Archivos y carpetas.';
    $md = lms_import_part($dirs, 'curso.json');
    $man = $md !== '' ? json_decode((string) file_get_contents(CMS_ROOT . '/' . $md . '/curso.json'), true) : [];
    if ($md !== '' && !is_array($man)) { $out['warn'][] = 'curso.json no es JSON válido; se ignoró.'; $man = []; }
    $out['manifest'] = $man = (array) $man;

    // datos del curso que el catálogo no trae
    $access = lms_quiz_norm((string) ($man['acceso'] ?? ''));
    if (in_array($access, ['abierto', 'cuenta', 'inscritos'], true)) $out['course']['access'] = $access;
    if (!empty($man['nivel'])) $out['course']['level'] = [$es => (string) $man['nivel']];
    if (!empty($man['icono']) && lms_imp_icon((string) $man['icono']) !== '') $out['course']['icon'] = (string) $man['icono'];
    if (preg_match('/^#[0-9a-f]{6}$/i', (string) ($man['color'] ?? ''))) $out['course']['color'] = strtolower((string) $man['color']);
    if (isset($man['constancia'])) $out['course']['certificate'] = lms_import_bool($man['constancia']) ? 'si' : 'no';
    $out['module'] = trim((string) ($man['modulo'] ?? ($opt['module'] ?? '')));

    // lecciones por número (1, 2, …): la NN de contenido/leccion-NN.html y de *-LNN.txt
    $byNum = [];
    foreach (array_values($lessons) as $i => $l) {
        $num = preg_match('/-(\d+)$/', (string) $l['slug'], $m) ? (int) $m[1] : $i + 1;
        $byNum[$num] = $l;
    }
    $abs = CMS_ROOT . '/' . (lms_import_part($dirs, 'contenido') ?: $dirs[0]);
    foreach (glob($abs . '/contenido/*.html') ?: [] as $f) {
        if (!preg_match('/(\d+)\.html$/', $f, $m) || !isset($byNum[(int) $m[1]])) { $out['warn'][] = 'contenido/' . basename($f) . ': no hay lección con ese número.'; continue; }
        $html = (string) file_get_contents($f);
        if (preg_match('#<body[^>]*>(.*)</body>#is', $html, $b)) $html = $b[1];
        $out['bodies'][(string) $byNum[(int) $m[1]]['slug']] = trim($html);
    }

    // materiales: los de curso.json o todos los de materiales/
    $dir = $md !== '' && !empty($man['materiales']) ? $md : (lms_import_part($dirs, 'materiales') ?: $dirs[0]);
    $abs = CMS_ROOT . '/' . $dir;
    $mats = [];
    foreach ((array) ($man['materiales'] ?? []) as $mm) if (is_array($mm) && !empty($mm['archivo'])) $mats[] = [(string) ($mm['texto'] ?? basename((string) $mm['archivo'])), ltrim((string) $mm['archivo'], '/')];
    if (!$mats) foreach (glob($abs . '/materiales/*') ?: [] as $f) if (is_file($f)) {
        $base = pathinfo($f, PATHINFO_FILENAME);
        $label = ucfirst(str_replace(['-', '_'], ' ', preg_replace('/-' . preg_quote($slug, '/') . '$/i', '', $base) ?? $base));
        if (preg_match('/\.html?$/i', $f) && preg_match('#<title[^>]*>(.*?)</title>#is', (string) file_get_contents($f, false, null, 0, 20000), $tm)) $label = trim(preg_replace('/^.*?·\s*/u', '', lms_imp_text($tm[1])) ?? $label) ?: $label;   // "IU-102 · Cuaderno del alumno" → "Cuaderno del alumno"
        $mats[] = [$label, 'materiales/' . basename($f)];
    }
    foreach ($mats as [$label, $src]) {
        $from = $dir . '/' . $src;
        if (!is_file(CMS_ROOT . '/' . $from)) { $out['warn'][] = 'Material no encontrado: ' . $from; continue; }
        $to = strpos($from . '/', $rel . '/') === 0 ? $from : $rel . '/materiales/' . basename($src);   // fuera de la carpeta del curso: se copia adentro
        $out['materials'][] = ['label' => $label, 'from' => $from, 'to' => $to];
    }

    // evaluaciones: las de curso.json o todos los archivos de evaluaciones/
    $dir = $md !== '' && !empty($man['evaluaciones']) ? $md : (lms_import_part($dirs, 'evaluaciones') ?: $dirs[0]);
    $abs = CMS_ROOT . '/' . $dir;
    $defs = [];
    foreach ((array) ($man['evaluaciones'] ?? []) as $q) if (is_array($q) && !empty($q['archivo'])) $defs[ltrim((string) $q['archivo'], '/')] = $q;
    if (!$defs) foreach (glob($abs . '/evaluaciones/*.{txt,gift,xml}', GLOB_BRACE) ?: [] as $f) $defs['evaluaciones/' . basename($f)] = [];
    ksort($defs, SORT_NATURAL);
    $code = strtoupper($slug);
    foreach ($defs as $file => $m) {
        if (!is_file($abs . '/' . $file)) { $out['warn'][] = 'Evaluación no encontrada: ' . $dir . '/' . $file; continue; }
        [$front, $body] = lms_import_front((string) file_get_contents($abs . '/' . $file));
        $m = array_replace(array_change_key_case(array_map(fn($v) => is_bool($v) ? ($v ? 'si' : 'no') : $v, $front)), array_change_key_case(array_map(fn($v) => is_bool($v) ? ($v ? 'si' : 'no') : $v, $m)));
        [$text, $qwarn, $fmt] = lms_quiz_import_text($body, $file);
        $base = pathinfo($file, PATHINFO_FILENAME);
        $after = (string) ($m['despues'] ?? '');
        if ($after === '') $after = preg_match('/final/i', $base) ? 'final' : (preg_match('/-?L(\d+)$/i', $base, $lm) ? (string) (int) $lm[1] : '');
        $final = lms_quiz_norm($after) === 'final';
        $lesson = !$final && ctype_digit($after) ? ($byNum[(int) $after] ?? null) : null;
        if (!$final && $after !== '' && !$lesson) $out['warn'][] = $file . ': «después de la lección ' . $after . '» no existe; se pone al final.';
        $title = trim((string) ($m['titulo'] ?? ''));
        if ($title === '') $title = $final || !$lesson ? 'Cuestionario final · ' . $code : 'Lección ' . (int) $after . ' · ' . (string) cms_f($lesson, 'title', $es);
        $qslug = cms_slugify(stripos($base, $slug) === 0 ? $base : $slug . '-' . $base);
        $num = fn(string $k, $d) => isset($m[$k]) && is_numeric($m[$k]) ? (int) $m[$k] : $d;
        $parsed = lms_quiz_parse($text);
        $out['quizzes'][] = [
            'file' => $file, 'format' => $fmt, 'count' => count($parsed['questions']), 'warn' => array_merge($qwarn, $fmt === 'texto del aula' ? [] : $parsed['warnings']),
            'final' => $final || !$lesson,
            'item' => [
                'slug' => $qslug, 'title' => [$es => $title], 'questions' => [$es => $text], 'course' => $slug,
                'after' => $lesson ? (string) $lesson['slug'] : '', 'order' => $lesson ? '' : 999, 'module' => [$es => (string) ($m['modulo'] ?? '')],
                'pass' => $num('aprobar', (int) ($opt['pass'] ?? 80)),
                'attempts' => $num('intentos', $final || !$lesson ? (int) ($opt['final_attempts'] ?? 2) : (int) ($opt['attempts'] ?? 0)) ?: '',
                'time' => $num('tiempo', 0) ?: '', 'pick' => $num('preguntas', 0) ?: '',
                'shuffle' => isset($m['mezclar']) ? lms_import_bool($m['mezclar']) : !empty($opt['shuffle']),
                'reveal' => in_array($m['revelar'] ?? '', ['siempre', 'aciertos', 'nada', ''], true) ? (string) ($m['revelar'] ?? ($opt['reveal'] ?? '')) : '',
                'gate' => isset($m['requisito']) ? lms_import_bool($m['requisito']) : ($final || !$lesson) && !empty($opt['gate_final']),
                'intro' => [$es => ''],
            ],
        ];
    }
    return $out;
}

/**
 * Aplica lo que acompaña al curso: contenido de las lecciones, materiales (copiándolos dentro de la carpeta del curso
 * si vienen de fuera), módulo, datos del curso y evaluaciones. $opt: replace_body, replace_quiz, publish_quiz.
 */
function lms_import_apply_extras(string $courseSlug, array $ex, array $opt): array
{
    $msgs = [];
    if ($ex['dir'] === '') return $msgs;
    $ct = lms_course_type(); $lt = lms_lesson_type(); $qt = lms_quiz_type(); $es = cms_default_lang(); $now = date('Y-m-d');
    $course = cms_item($ct, $courseSlug, false);
    if (!$course) return ['No se encontró el curso «' . $courseSlug . '».'];

    // materiales
    $files = lms_file_list($course);
    $have = array_column($files, 1);
    $nm = 0;
    foreach ($ex['materials'] as $mt) {
        if ($mt['to'] !== $mt['from']) {
            $dst = CMS_ROOT . '/' . $mt['to'];
            if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
            if (!@copy(CMS_ROOT . '/' . $mt['from'], $dst)) { $msgs[] = 'No se pudo copiar ' . $mt['from'] . ' a ' . $mt['to'] . '.'; continue; }
        }
        if (!in_array($mt['to'], $have, true)) { $files[] = [$mt['label'], $mt['to']]; $have[] = $mt['to']; $nm++; }
    }
    $course = array_replace($course, $ex['course'], ['files' => array_map(fn($f) => $f[0] . ' | ' . $f[1], $files), 'updated' => $now]);
    if ($ex['course'] || $nm) { cms_item_save($ct, $course); }
    if ($nm) $msgs[] = $nm . ' material(es) del curso agregados' . (lms_settings()['protect'] ? ' (protegidos: solo los abre quien puede tomar el curso)' : '') . '.';
    if ($ex['course']) $msgs[] = 'Datos del curso de curso.json: ' . implode(', ', array_keys($ex['course'])) . '.';

    // contenido y módulo de las lecciones
    $nb = 0; $kept = 0; $nmod = 0;
    // directo de la colección: lms_lessons() guarda en caché la lista de antes de crear las lecciones
    $f = lms_settings()['lesson_field'];
    foreach (cms_items($lt, false) as $l) {
        if ((string) ($l[$f] ?? '') !== $courseSlug) continue;
        $full = cms_item($lt, (string) $l['slug'], false);
        if (!$full) continue;
        $ch = false;
        if (isset($ex['bodies'][$l['slug']])) {
            if (trim((string) cms_f($full, 'body', $es)) === '' || !empty($opt['replace_body'])) { $full['body'] = array_replace((array) ($full['body'] ?? []), [$es => $ex['bodies'][$l['slug']]]); $ch = true; $nb++; }
            else $kept++;
        }
        if ($ex['module'] !== '' && (trim((string) cms_f($full, 'module', $es)) === '' || !empty($opt['replace_body']))) { $full['module'] = [$es => $ex['module']]; $ch = true; $nmod++; }
        if ($ch) cms_item_save($lt, $full + ['updated' => $now]);
    }
    if ($nb) $msgs[] = 'Contenido puesto en ' . $nb . ' lección(es).';
    if ($kept) $msgs[] = $kept . ' lección(es) ya tenían contenido y no se tocaron (marca «Reemplazar» para sobrescribirlo).';
    if ($nmod) $msgs[] = 'Módulo «' . $ex['module'] . '» en ' . $nmod . ' lección(es).';

    // evaluaciones
    if (!cms_type($qt)) $msgs[] = 'No existe la colección de evaluaciones (Ajustes → Aula); no se importaron.';
    else {
        $nq = 0; $nu = 0; $ns = 0;
        foreach ($ex['quizzes'] as $q) {
            $it = $q['item'];
            $old = cms_item($qt, $it['slug'], false);
            if ($old && empty($opt['replace_quiz'])) { $ns++; continue; }
            $it = ($old ? array_replace($old, $it) : $it) + ['status' => 'draft', 'created' => $now];
            if (!empty($opt['publish_quiz'])) $it['status'] = 'published';
            $it['updated'] = $now;
            if (cms_item_save($qt, $it)) { if ($old) $nu++; else $nq++; }
        }
        if ($nq) $msgs[] = $nq . ' evaluación(es) creadas' . (!empty($opt['publish_quiz']) ? ' y publicadas' : ' en borrador') . '.';
        if ($nu) $msgs[] = $nu . ' evaluación(es) actualizadas.';
        if ($ns) $msgs[] = $ns . ' evaluación(es) ya existían y no se tocaron (marca «Reemplazar» para actualizarlas).';
    }
    return $msgs;
}

/** Completa un curso que ya existe con lo que acompaña a su carpeta. Devuelve [ok, mensajes]. */
function lms_import_complete(string $rel, array $opt): array
{
    $plan = lms_import_plan($rel, $opt);
    if (isset($plan['error'])) return [false, [$plan['error']]];
    $slug = $plan['course']['slug'];
    if (!cms_item(lms_course_type(), $slug, false)) return [false, ['El curso «' . $slug . '» no existe: impórtalo primero.']];
    if ($plan['extras']['dir'] === '') return [false, ['No hay contenido/, materiales/, evaluaciones/ ni curso.json junto a ' . $rel . '.']];
    $msgs = lms_import_apply_extras($slug, $plan['extras'], $opt);
    foreach ($plan['extras']['warn'] as $w) $msgs[] = 'Aviso: ' . $w;
    return [true, $msgs ?: ['No había nada nuevo que agregar.']];
}
