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
function lms_import_plan(string $rel): array
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
    return ['course' => $course, 'lessons' => $lessons, 'soon' => $soon, 'series' => $series, 'warn' => $warn, 'rel' => $rel, 'catalog' => $catalog ? $parent : ''];
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
    $plan = lms_import_plan($rel);
    if (isset($plan['error'])) return [false, [$plan['error']]];
    $ct = lms_course_type(); $lt = lms_lesson_type();
    if (!cms_type($ct) || !cms_type($lt)) return [false, ['Faltan las colecciones de cursos y lecciones (Ajustes → Aula).']];
    $c = $plan['course'];
    if (cms_item($ct, $c['slug'], false)) return [false, ['Ya existe un curso «' . $c['slug'] . '». Bórralo o cámbiale el slug si quieres importarlo otra vez.']];
    $now = date('Y-m-d');
    $msgs = [];
    $c += ['status' => !empty($opt['publish']) ? 'published' : 'draft', 'created' => $now, 'updated' => $now];
    if (!cms_item_save($ct, $c)) return [false, ['No se pudo guardar el curso en data/content/' . $ct . '/.']];
    $n = 0; $skipped = 0;
    foreach ($plan['lessons'] as $l) {
        if (cms_item($lt, $l['slug'], false)) { $skipped++; continue; }
        if (cms_item_save($lt, $l + ['status' => 'published', 'created' => $now, 'updated' => $now])) $n++;
    }
    $msgs[] = 'Curso «' . $c['title'][cms_default_lang()] . '» creado (' . ($c['status'] === 'published' ? 'publicado' : 'borrador') . ') con ' . $n . ' lecciones' . ($skipped ? '; ' . $skipped . ' ya existían y no se tocaron' : '') . '.';
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
    foreach ($plan['warn'] as $w) $msgs[] = 'Aviso: ' . $w;
    return [true, $msgs];
}
