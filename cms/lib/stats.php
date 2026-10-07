<?php
/**
 * cms_simple — estadísticas básicas de uso (1.40), sin cookies ni servicios externos.
 *
 * Qué se cuenta
 *   - Páginas: cada página pública que responde 200 (el enrutador llama a cms_stats_page() al terminar). No cuentan los
 *     buscadores y bots (por su User-Agent), las peticiones de precarga, las vistas previas ni quien tiene sesión en el
 *     panel (cookie cms_staff, que el panel pone al entrar, o la de sesión cmsadmin).
 *   - Visitantes: una huella diaria (HMAC de fecha + IP + navegador con el secreto del sitio). No se guardan IPs y la
 *     huella cambia cada día, así que no sirve para seguir a nadie; solo para no contar dos veces al mismo en un día.
 *     Con la primera página del día de cada visitante se anotan su procedencia (dominio del referer), país (cabecera de
 *     Cloudflare) y tipo de dispositivo.
 *   - Descargas: documentos, audio y comprimidos de uploads/. Apache los sirve directo, así que el .htaccess de la raíz
 *     lleva un bloque (cms_stats_htaccess_block) que los manda a /_cms/dl/<archivo>; ahí se cuentan (una vez por
 *     visitante, archivo y día: los reproductores y las apps de podcast piden el mismo MP3 en varios trozos) y se
 *     redirige al archivo con ?cmsdirect=1, que Apache entrega tal cual (rangos, caché, Cloudflare).
 *
 * Dónde: data/stats/AAAA-MM.json, un archivo por mes con
 *   d    [día => ['v' => vistas, 'u' => visitantes, 'dl' => descargas]]
 *   p    [ruta => vistas]          t   [ruta => [tipo, slug]] (para enlazar al editor)
 *   dl   [uploads/… => descargas]  r   [dominio|'' => visitantes]   c   [país => visitantes]
 *   dev  [m|t|d => visitantes]     l   [idioma => vistas]           seen [huellas de hoy] (se vacía al cambiar de día)
 * Ajustes → Marca y SEO → Estadísticas: encender o apagar (stats_on) y meses que se guardan (stats_months).
 */
declare(strict_types=1);

const CMS_STATS_DL_EXT = 'pdf|docx?|xlsx?|pptx?|ppsx?|od[tsp]|rtf|csv|epub|zip|rar|7z|gz|tgz|mp3|m4a|aac|wav|ogg|oga|opus|flac';
const CMS_STATS_MAX_KEYS = 600;   // tope de rutas, archivos o dominios distintos por mes (lo nuevo pasa a "(otros)")

function cms_stats_dir(): string { return CMS_DATA . '/stats'; }

function cms_stats_enabled(): bool
{
    $S = cms_settings();
    return cms_config('stats', true) !== false && (!isset($S['stats_on']) || !empty($S['stats_on']));
}

function cms_stats_months(): int
{
    return max(1, min(60, (int) (cms_settings()['stats_months'] ?? 12) ?: 12));
}

/** ¿La petición viene de un bot, una precarga o de alguien del panel? */
function cms_stats_skip(bool $download = false): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return true;
    if (isset($_COOKIE['cms_staff']) || isset($_COOKIE['cmsadmin'])) return true;
    $purpose = strtolower(($_SERVER['HTTP_SEC_PURPOSE'] ?? '') . ($_SERVER['HTTP_PURPOSE'] ?? '') . ($_SERVER['HTTP_X_MOZ'] ?? ''));
    if (strpos($purpose, 'prefetch') !== false || strpos($purpose, 'prerender') !== false) return true;
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (strlen($ua) < 12) return true;
    if (preg_match('/bot\b|bot[\/;-]|crawl|spider|slurp|scrap|archiver|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|libwww|httpclient|headless|phantom|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|facebookexternalhit|embedly|whatsapp|telegram|discord|skype|preview|validator|feed|semrush|ahrefs|mj12|yandex|baidu|bytespider|petal|gptbot|claude|perplexity|ccbot/i', $ua)) return true;
    // los navegadores no ponen URL en su User-Agent; casi todos los bots sí. Las apps de podcast también, y sus descargas valen.
    if (!$download && preg_match('#https?://|\+http|@#i', $ua)) return true;
    return false;
}

/** Huella diaria del visitante (no reversible, cambia cada día). */
function cms_stats_visitor(): string
{
    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    return substr(hash_hmac('sha256', date('Y-m-d') . '|' . $ip . '|' . $ua, cms_secret() . '/stats'), 0, 12);
}

function cms_stats_device(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (preg_match('/iPad|Tablet|Android(?!.*Mobile)/i', $ua)) return 't';
    if (preg_match('/Mobi|iPhone|iPod|Android|Windows Phone/i', $ua)) return 'm';
    return 'd';
}

/** Dominio de procedencia ('' = directo o desconocido); el propio sitio devuelve null (no es una entrada). */
function cms_stats_referrer(): ?string
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') return '';
    if (preg_match('#^android-app://([^/]+)#i', $ref, $m)) return strtolower($m[1]);
    $h = strtolower((string) parse_url($ref, PHP_URL_HOST));
    if ($h === '') return '';
    $h = preg_replace('/^(www|m|l|lm)\./', '', $h);
    $own = preg_replace('/^www\./', '', strtolower((string) parse_url(cms_origin(), PHP_URL_HOST)));
    $req = preg_replace('/^www\./', '', strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]));
    return ($h === $own || $h === $req) ? null : $h;
}

/** Suma 1 a $map[$key] sin pasar del tope de claves distintas. */
function cms_stats_inc(array &$map, string $key, int $n = 1): void
{
    if (!isset($map[$key]) && count($map) >= CMS_STATS_MAX_KEYS) $key = '(otros)';
    $map[$key] = ($map[$key] ?? 0) + $n;
}

/** Abre el mes en curso con bloqueo, aplica $fn y lo guarda. Nunca falla hacia fuera: las estadísticas no rompen el sitio. */
function cms_stats_update(callable $fn): void
{
    $dir = cms_stats_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return;
    $file = $dir . '/' . date('Y-m') . '.json';
    $new = !is_file($file);
    $fh = @fopen($file, 'c+');
    if (!$fh) return;
    if (flock($fh, LOCK_EX)) {
        $raw = stream_get_contents($fh);
        $m = $raw ? json_decode((string) $raw, true) : null;
        if (!is_array($m)) $m = [];
        $m += ['d' => [], 'p' => [], 't' => [], 'dl' => [], 'r' => [], 'c' => [], 'dev' => [], 'l' => [], 'seen' => [], 'day' => ''];
        $today = date('d');
        if ($m['day'] !== $today) { $m['seen'] = []; $m['day'] = $today; }   // las huellas solo valen el día en que se tomaron
        $m['d'][$today] = ($m['d'][$today] ?? []) + ['v' => 0, 'u' => 0, 'dl' => 0];
        $fn($m, $today);
        rewind($fh); ftruncate($fh, 0);
        fwrite($fh, (string) json_encode($m, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    if ($new) cms_stats_prune();
}

/** Borra los meses que pasan de Ajustes → "meses que se guardan". */
function cms_stats_prune(): void
{
    $keep = date('Y-m', strtotime(date('Y-m-01') . ' -' . (cms_stats_months() - 1) . ' months'));
    foreach (glob(cms_stats_dir() . '/????-??.json') ?: [] as $f) if (basename($f, '.json') < $keep) @unlink($f);
}

/** Registra la página que se acaba de servir (la llama el enrutador al terminar, si respondió 200). */
function cms_stats_page(array $page, string $type = '', string $slug = ''): void
{
    if (!cms_stats_enabled() || !empty($page['preview']) || !empty($_GET['cmsbare']) || cms_theme_preview() !== '' || cms_stats_skip()) return;
    if (http_response_code() !== 200) return;
    $lang = (string) ($page['lang'] ?? cms_default_lang());
    $path = '/' . ($lang !== cms_default_lang() ? $lang . ($page['path'] !== '' ? '/' : '') : '') . (string) ($page['path'] ?? '');
    $path = mb_substr($path, 0, 200);
    $vis = cms_stats_visitor();
    $ref = cms_stats_referrer();
    $country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    $dev = cms_stats_device();
    cms_stats_update(function (array &$m, string $day) use ($path, $type, $slug, $lang, $vis, $ref, $country, $dev) {
        $m['d'][$day]['v']++;
        cms_stats_inc($m['p'], $path);
        if ($type !== '' && $slug !== '' && isset($m['p'][$path])) $m['t'][$path] = [$type, $slug];
        cms_stats_inc($m['l'], $lang);
        if (empty($m['seen'][$vis])) {   // primera página del día de este visitante: es una visita (con su procedencia)
            $m['seen'][$vis] = 1;
            $m['d'][$day]['u']++;
            cms_stats_inc($m['r'], $ref ?? '');
            if (strlen($country) === 2 && $country !== 'XX' && $country !== 'T1') cms_stats_inc($m['c'], $country);
            cms_stats_inc($m['dev'], $dev);
        }
    });
}

/**
 * /_cms/dl/<ruta en uploads> (llega aquí por el bloque del .htaccess): cuenta la descarga y redirige al archivo
 * con ?cmsdirect=1, que el .htaccess deja pasar. Sin archivo válido, 404.
 */
function cms_stats_download(string $rel): void
{
    $rel = trim(str_replace('\\', '/', $rel), '/');
    $abs = realpath(CMS_UPLOADS . '/' . $rel);
    $base = realpath(CMS_UPLOADS);
    if ($rel === '' || strpos($rel, '..') !== false || $abs === false || $base === false || strpos($abs, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($abs)
        || !preg_match('/\.(' . CMS_STATS_DL_EXT . ')$/i', $rel)) {
        http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Not found'; return;
    }
    $rel = str_replace('\\', '/', substr($abs, strlen($base) + 1));
    if (cms_stats_enabled() && !cms_stats_skip(true)) {
        $key = 'uploads/' . $rel;
        $once = cms_stats_visitor() . ':' . substr(md5($key), 0, 8);
        cms_stats_update(function (array &$m, string $day) use ($key, $once) {
            if (!empty($m['seen'][$once])) return;   // mismo visitante y archivo hoy: los trozos de un mismo MP3 no suman
            $m['seen'][$once] = 1;
            $m['d'][$day]['dl']++;
            cms_stats_inc($m['dl'], $key);
        });
    }
    $qs = (string) preg_replace('/(^|&)p=[^&]*/', '', (string) ($_SERVER['QUERY_STRING'] ?? ''));
    $qs = trim($qs . '&cmsdirect=1', '&');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex');
    header('Location: ' . CMS_BASE . '/uploads/' . implode('/', array_map('rawurlencode', explode('/', $rel))) . '?' . $qs, true, 302);
}

/* ------------------------------------------------------------------ .htaccess de la raíz */

/** Bloque que manda las descargas de uploads/ a /_cms/dl/ (va al principio del .htaccess, antes de servir archivos). */
function cms_stats_htaccess_block(): string
{
    return "# BEGIN cms_simple descargas (Estadísticas; lo pone y lo quita el panel)\n"
        . "<IfModule mod_rewrite.c>\n"
        . "  RewriteEngine On\n"
        . "  RewriteCond %{QUERY_STRING} !(^|&)cmsdirect=1(&|$)\n"
        . "  RewriteRule ^uploads/(.+\\.(" . CMS_STATS_DL_EXT . "))$ index.php?p=_cms/dl/$1 [B,NC,QSA,L]\n"
        . "</IfModule>\n"
        . "# END cms_simple descargas\n";
}

/** Estado del bloque en el .htaccess: 'ok', 'missing', 'old' (otra versión del bloque) o 'none' (no hay .htaccess). */
function cms_stats_htaccess_state(): string
{
    $f = CMS_ROOT . '/.htaccess';
    if (!is_file($f)) return 'none';
    $s = (string) @file_get_contents($f);
    if (strpos($s, '# BEGIN cms_simple descargas') === false) return 'missing';
    return strpos($s, cms_stats_htaccess_block()) !== false ? 'ok' : 'old';
}

/**
 * Pone (o quita) el bloque de descargas en el .htaccess de la raíz. El actualizador solo instala cms/, así que el
 * panel lo mantiene al día por su cuenta. Devuelve true si el archivo quedó como debía.
 */
function cms_stats_htaccess_sync(?bool $on = null): bool
{
    $on = $on ?? cms_stats_enabled();
    $f = CMS_ROOT . '/.htaccess';
    if (!is_file($f)) return !$on;
    $s = (string) @file_get_contents($f);
    $clean = (string) preg_replace('/# BEGIN cms_simple descargas.*?# END cms_simple descargas\R?/s', '', $s);
    $want = $on ? cms_stats_htaccess_block() . $clean : $clean;
    if ($want === $s) return true;
    if (!is_writable($f)) return false;
    return @file_put_contents($f, $want, LOCK_EX) !== false;
}

/* ------------------------------------------------------------------ lectura (panel) */

/** Meses guardados ['AAAA-MM' => datos], del más viejo al más nuevo, dentro de [$from, $to]. */
function cms_stats_load(string $from, string $to): array
{
    $out = [];
    foreach (glob(cms_stats_dir() . '/????-??.json') ?: [] as $f) {
        $k = basename($f, '.json');
        if ($k < $from || $k > $to) continue;
        $j = cms_json_read($f, []);
        if (is_array($j)) $out[$k] = $j;
    }
    ksort($out);
    return $out;
}

/** Suma de los meses: totales, serie por día y listas ordenadas. */
function cms_stats_sum(array $months): array
{
    $sum = ['v' => 0, 'u' => 0, 'dl' => 0, 'days' => [], 'p' => [], 't' => [], 'dl_files' => [], 'r' => [], 'c' => [], 'dev' => [], 'l' => []];
    foreach ($months as $ym => $m) {
        foreach ((array) ($m['d'] ?? []) as $d => $row) {
            $sum['days'][$ym . '-' . $d] = ['v' => (int) ($row['v'] ?? 0), 'u' => (int) ($row['u'] ?? 0), 'dl' => (int) ($row['dl'] ?? 0)];
            $sum['v'] += (int) ($row['v'] ?? 0); $sum['u'] += (int) ($row['u'] ?? 0); $sum['dl'] += (int) ($row['dl'] ?? 0);
        }
        foreach (['p' => 'p', 'dl' => 'dl_files', 'r' => 'r', 'c' => 'c', 'dev' => 'dev', 'l' => 'l'] as $src => $dst)
            foreach ((array) ($m[$src] ?? []) as $k => $n) $sum[$dst][(string) $k] = ($sum[$dst][(string) $k] ?? 0) + (int) $n;
        $sum['t'] += (array) ($m['t'] ?? []);
    }
    foreach (['p', 'dl_files', 'r', 'c', 'dev', 'l'] as $k) arsort($sum[$k]);
    ksort($sum['days']);
    return $sum;
}

/** Visitas de los últimos $days días (para la tarjeta de Inicio). */
function cms_stats_recent(int $days = 30): array
{
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $s = cms_stats_sum(cms_stats_load(substr($from, 0, 7), date('Y-m')));
    $out = ['v' => 0, 'u' => 0, 'dl' => 0];
    foreach ($s['days'] as $d => $row) if ($d >= $from) foreach ($out as $k => $_) $out[$k] += $row[$k];
    return $out;
}
