<?php
/** cms_simple — URLs, escape, rutas por idioma, redirecciones. */
declare(strict_types=1);

function cms_e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cms_lang_prefix(string $lang): string
{
    return $lang === cms_default_lang() ? '' : '/' . $lang;
}

/** Segmento de URL de un tipo o página en un idioma (config: routes => [lang => segmento]). */
function cms_segment(array $def, string $lang): string
{
    $r = $def['routes'] ?? [];
    return (string) ($r[$lang] ?? ($r[cms_default_lang()] ?? ($def['key'] ?? '')));
}

/**
 * URL de una ruta lógica:
 *   home | list:<tipo> | item:<tipo> (con $slug) | page:<clave>
 */
function cms_url(string $route, string $lang, ?string $slug = null): string
{
    $u = cms_url_plain($route, $lang, $slug);
    return cms_theme_preview() !== '' && strpos($u, '?') === false ? $u . '?' . cms_theme_preview_qs() : $u;
}
function cms_url_plain(string $route, string $lang, ?string $slug = null): string
{
    $base = CMS_BASE . cms_lang_prefix($lang);
    if ($route === 'home') return $base . '/';
    [$kind, $key] = array_pad(explode(':', $route, 2), 2, '');
    if ($kind === 'list' || $kind === 'item') {
        $def = cms_type($key);
        if (!$def) return $base . '/';
        $seg = cms_segment($def, $lang);
        if ($kind === 'item' && cms_is_home_item($key, (string) $slug)) return $base . '/';
        if ($kind === 'item' && !empty($def['tree'])) {
            $it = cms_items($key, false)[(string) $slug] ?? null;
            $path = $it ? ($it['path'] ?? $it['slug']) : (string) $slug;
            return $base . ($seg !== '' ? '/' . $seg : '') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
        }
        return $kind === 'list' ? $base . '/' . $seg . '/' : $base . '/' . $seg . '/' . rawurlencode((string) $slug);
    }
    if ($kind === 'cat') {   // subsección por categoría: /coleccion/categoria/
        $def = cms_type($key);
        if (!$def || $slug === null || $slug === '') return $base . '/';
        return $base . '/' . cms_segment($def, $lang) . '/' . rawurlencode(cms_slugify((string) $slug)) . '/';
    }
    if ($kind === 'page') {
        $pages = cms_config('pages');
        if (!isset($pages[$key])) return $base . '/';
        return $base . '/' . cms_segment($pages[$key] + ['key' => $key], $lang);
    }
    return $base . '/';
}

/** URL de un enlace del menú: absoluto (http/mailto/tel/#) o relativo a la raíz del idioma ("/blog"). */
/** ¿Este elemento es la portada? (config 'home_item' => ['paginas', 'inicio']) */
function cms_is_home_item(string $type, string $slug): bool
{
    $h = cms_config('home_item');
    return is_array($h) && count($h) >= 2 && $h[0] === $type && $h[1] === $slug;
}

function cms_menu_url(string $url, string $lang): string
{
    if ($url === '' || preg_match('#^(https?:)?//|^mailto:|^tel:|^\##i', $url)) return $url;
    $u = CMS_BASE . cms_lang_prefix($lang) . '/' . ltrim($url, '/');
    return cms_theme_preview() !== '' && strpos($u, '?') === false ? $u . '?' . cms_theme_preview_qs() : $u;
}

function cms_asset(string $path): string
{
    $path = ltrim($path, '/');
    if (CMS_SITE_PARENT !== '' && !is_file(CMS_SITE . '/assets/' . $path) && is_file(CMS_SITE_PARENT . '/assets/' . $path)) return CMS_SITE_PARENT_BASE . '/assets/' . $path;
    return CMS_SITE_BASE . '/assets/' . $path;
}

/** Imagen guardada como nombre (site/assets/img), ruta uploads/… o site/assets/…, o URL absoluta. */
function cms_img(string $path): string
{
    if ($path === '') return '';
    if (preg_match('#^(https?:)?//#i', $path)) return $path;
    if (strpos($path, 'uploads/') === 0 || strpos($path, 'site/') === 0 || strpos($path, 'themes/') === 0 || strpos($path, 'cms/') === 0) return CMS_BASE . '/' . $path;
    if (strpos($path, 'assets/') === 0) return CMS_SITE_BASE . '/' . $path;
    return CMS_SITE_BASE . '/assets/img/' . ltrim($path, '/');
}

/** Archivo local de una imagen o null si es externa. */
function cms_local_path(string $path): ?string
{
    if ($path === '' || preg_match('#^(https?:)?//#i', $path)) return null;
    if (strpos($path, 'uploads/') === 0 || strpos($path, 'site/') === 0 || strpos($path, 'themes/') === 0 || strpos($path, 'cms/') === 0) return CMS_ROOT . '/' . $path;
    if (strpos($path, 'assets/') === 0) return cms_theme_file($path);
    return cms_theme_file('assets/img/' . ltrim($path, '/'));
}

/** Origen canónico (esquema + host): Ajustes → "URL canónica", si no config 'site_url', si no el de la petición. */
function cms_origin(): string
{
    static $o = null;
    if ($o !== null) return $o;
    $fixed = trim((string) (cms_settings()['site_url'] ?? '')) ?: trim((string) cms_config('site_url', ''));
    if ($fixed !== '' && preg_match('#^https?://[^/]+#i', $fixed, $m)) return $o = $m[0];
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return $o = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function cms_site_url(): string
{
    return cms_origin() . CMS_BASE;
}

/** URL absoluta de una ruta que ya incluye CMS_BASE. */
function cms_abs_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) return $path;
    return cms_origin() . $path;
}

/** Redirecciones 301 administradas (data/redirects.json). */
function cms_redirect_for(string $path): ?string
{
    $rules = cms_json_read(CMS_DATA . '/redirects.json', []);
    if (!$rules) return null;
    $norm = function (string $p): string {
        $p = preg_replace('#^https?://[^/]+#i', '', $p);
        $p = (string) parse_url('/' . ltrim((string) $p, '/'), PHP_URL_PATH);
        if (CMS_BASE !== '' && stripos($p, CMS_BASE . '/') === 0) $p = substr($p, strlen(CMS_BASE));
        return strtolower(trim($p, '/'));
    };
    $want = $norm($path);
    foreach ($rules as $r) {
        if (!isset($r['from'], $r['to']) || $r['to'] === '') continue;
        if ($norm((string) $r['from']) === $want) {
            $to = (string) $r['to'];
            return preg_match('#^https?://#i', $to) ? $to : CMS_BASE . '/' . ltrim($to, '/');
        }
    }
    return null;
}

function cms_whatsapp_url(): string
{
    $n = preg_replace('/\D+/', '', (string) (cms_settings()['whatsapp'] ?? ''));
    return $n ? 'https://wa.me/' . $n : '';
}

function cms_tel_href(): string
{
    $n = preg_replace('/[^\d+]+/', '', (string) (cms_settings()['phone_href'] ?? cms_settings()['phone'] ?? ''));
    return $n ? 'tel:' . $n : '';
}

/* ------------------------------------------------------------------ idioma del visitante (Ajustes → General → lang_auto) */

/** Países → idioma, para elegir por el origen del visitante (cabecera CF-IPCountry de Cloudflare o GEOIP del servidor). */
const CMS_COUNTRY_LANGS = [
    'es' => ['MX', 'ES', 'AR', 'CO', 'CL', 'PE', 'VE', 'EC', 'GT', 'CU', 'BO', 'DO', 'HN', 'PY', 'SV', 'NI', 'CR', 'PA', 'UY', 'PR', 'GQ'],
    'en' => ['US', 'GB', 'IE', 'CA', 'AU', 'NZ', 'ZA', 'IN', 'SG', 'PH', 'NG', 'KE', 'JM', 'TT', 'BZ', 'BS', 'BB'],
    'pt' => ['BR', 'PT', 'AO', 'MZ', 'CV'],
    'fr' => ['FR', 'MC', 'LU', 'SN', 'CI', 'ML', 'CM', 'MG', 'HT'],
    'de' => ['DE', 'AT', 'LI'],
    'it' => ['IT', 'SM', 'VA'],
];

/** Idioma activo que prefiere el visitante según su navegador (Accept-Language, en orden de preferencia), o ''. */
function cms_browser_lang(): string
{
    $prefs = [];
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $i => $part) {
        if (!preg_match('/^\s*([a-z]{2,3})(?:-[a-z0-9]+)*\s*(?:;\s*q\s*=\s*([0-9.]+))?/i', $part, $m)) continue;
        $q = isset($m[2]) ? (float) $m[2] : 1.0;
        if ($q > 0) $prefs[] = [strtolower($m[1]), $q, $i];
    }
    usort($prefs, fn($a, $b) => $b[1] <=> $a[1] ?: $a[2] <=> $b[2]);
    $active = cms_active_langs();
    foreach ($prefs as [$l]) if (in_array($l, $active, true)) return $l;
    return '';
}

/** Idioma activo que corresponde al país de origen del visitante, o ''. Solo funciona si el hosting informa el país (Cloudflare). */
function cms_country_lang(): string
{
    $cc = strtoupper((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? ''));
    if (!preg_match('/^[A-Z]{2}$/', $cc)) return '';
    foreach (CMS_COUNTRY_LANGS as $l => $list) if (in_array($cc, $list, true) && in_array($l, cms_active_langs(), true)) return $l;
    return '';
}

/**
 * Idioma según el visitante. Se llama desde el enrutador antes de dibujar una página pública.
 * - Al entrar desde fuera (sin referer del propio sitio) a una URL del idioma predeterminado, redirige (302) a la misma
 *   página en el idioma que el visitante eligió antes (cookie cms_lang) o, si nunca eligió, en el de su navegador o su país.
 * - Al navegar dentro del sitio se guarda en la cookie el idioma de la página que ve: usar el selector de idioma es elegir.
 * Las URL con prefijo (/en/…) se respetan siempre: un enlace compartido abre en su idioma. Nunca redirige a buscadores,
 * vistas previas, POST ni contenidos sin traducción en el idioma de destino.
 */
function cms_lang_negotiate(string $lang, array $page, string $type = '', string $slug = ''): void
{
    $mode = (string) (cms_settings()['lang_auto'] ?? '');
    $active = cms_active_langs();
    if ($mode === '' || count($active) < 2 || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || PHP_SAPI === 'cli') return;
    if (!empty($page['preview']) || cms_theme_preview() !== '' || isset($_GET['preview'])) return;
    $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
    $internal = $ref !== '' && strcasecmp($ref, (string) parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) === 0;
    $cookie = (string) ($_COOKIE['cms_lang'] ?? '');
    if (!in_array($cookie, $active, true)) $cookie = '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    if ($internal) {
        if ($cookie !== $lang) setcookie('cms_lang', $lang, ['expires' => time() + 365 * 86400, 'path' => CMS_BASE ?: '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        return;
    }
    if ($lang !== cms_default_lang()) return;
    header('Vary: Accept-Language, Cookie', false);
    if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|headless|lighthouse|validator/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) return;
    $want = $cookie;
    if ($want === '' && ($mode === 'browser' || $mode === 'both')) $want = cms_browser_lang();
    if ($want === '' && ($mode === 'country' || $mode === 'both')) $want = cms_country_lang();
    if ($want === '' || $want === $lang || empty($page['alt'][$want])) return;
    if ($type !== '' && $slug !== '' && ($def = cms_type($type))) {   // sin traducción del título, la página saldría en el predeterminado: no vale la pena
        $raw = cms_json_read(cms_content_dir($type) . '/' . cms_slugify($slug) . '.json', []);   // el elemento del enrutador ya viene localizado
        $tv = $raw[$def['title_field'] ?? 'title'] ?? null;
        if (is_array($tv) && trim((string) ($tv[$want] ?? '')) === '') return;
    }
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $qs = trim((string) preg_replace('/(^|&)p=[^&]*/', '', $qs), '&');   // el .htaccess añade ?p=<ruta>
    header('Cache-Control: private, no-store');
    header('Location: ' . $page['alt'][$want] . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
}
