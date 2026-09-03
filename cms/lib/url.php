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
    $base = CMS_BASE . cms_lang_prefix($lang);
    if ($route === 'home') return $base . '/';
    [$kind, $key] = array_pad(explode(':', $route, 2), 2, '');
    if ($kind === 'list' || $kind === 'item') {
        $def = cms_type($key);
        if (!$def) return $base . '/';
        $seg = cms_segment($def, $lang);
        return $kind === 'list' ? $base . '/' . $seg . '/' : $base . '/' . $seg . '/' . rawurlencode((string) $slug);
    }
    if ($kind === 'page') {
        $pages = cms_config('pages');
        if (!isset($pages[$key])) return $base . '/';
        return $base . '/' . cms_segment($pages[$key] + ['key' => $key], $lang);
    }
    return $base . '/';
}

/** URL de un enlace del menú: absoluto (http/mailto/tel/#) o relativo a la raíz del idioma ("/blog"). */
function cms_menu_url(string $url, string $lang): string
{
    if ($url === '' || preg_match('#^(https?:)?//|^mailto:|^tel:|^\##i', $url)) return $url;
    return CMS_BASE . cms_lang_prefix($lang) . '/' . ltrim($url, '/');
}

function cms_asset(string $path): string
{
    return CMS_BASE . '/site/assets/' . ltrim($path, '/');
}

/** Imagen guardada como nombre (site/assets/img), ruta uploads/… o site/assets/…, o URL absoluta. */
function cms_img(string $path): string
{
    if ($path === '') return '';
    if (preg_match('#^(https?:)?//#i', $path)) return $path;
    if (strpos($path, 'uploads/') === 0 || strpos($path, 'site/') === 0) return CMS_BASE . '/' . $path;
    if (strpos($path, 'assets/') === 0) return CMS_BASE . '/site/' . $path;
    return CMS_BASE . '/site/assets/img/' . ltrim($path, '/');
}

/** Archivo local de una imagen o null si es externa. */
function cms_local_path(string $path): ?string
{
    if ($path === '' || preg_match('#^(https?:)?//#i', $path)) return null;
    if (strpos($path, 'uploads/') === 0 || strpos($path, 'site/') === 0) return CMS_ROOT . '/' . $path;
    if (strpos($path, 'assets/') === 0) return CMS_SITE . '/' . $path;
    return CMS_SITE . '/assets/img/' . ltrim($path, '/');
}

function cms_site_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . CMS_BASE;
}

/** URL absoluta de una ruta que ya incluye CMS_BASE. */
function cms_abs_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) return $path;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
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
