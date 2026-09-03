<?php
/** cms_simple admin — sesión, autenticación, CSRF, avisos, utilidades de POST. */
declare(strict_types=1);

define('ADMIN_URL', CMS_BASE . '/admin');
define('ADMIN_SESSION_TTL', 60 * 60 * 8);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name('cmsadmin');
session_set_cookie_params(['lifetime' => 0, 'path' => (CMS_BASE ?: '/'), 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (!empty($_SESSION['user']) && isset($_SESSION['last']) && time() - (int) $_SESSION['last'] > ADMIN_SESSION_TTL) {
    session_unset(); session_destroy(); session_start();
}
$_SESSION['last'] = time();

function admin_url(string $page = 'dashboard', array $params = []): string
{
    $q = array_merge(['p' => $page], $params);
    return ADMIN_URL . '/?' . http_build_query($q);
}

function admin_user(): ?array
{
    if (empty($_SESSION['user'])) return null;
    foreach (cms_users() as $u) if (($u['user'] ?? '') === $_SESSION['user']) return $u;
    return null;
}

function admin_require_login(): void
{
    if (!admin_user()) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? admin_url();
        admin_redirect(admin_url('login'));
    }
}

/* intentos de acceso */
function admin_throttle_file(): string { return CMS_DATA . '/login-attempts.json'; }
function admin_throttle_blocked(string $ip): int
{
    $e = cms_json_read(admin_throttle_file(), [])[$ip] ?? null;
    if ($e && ($e['count'] ?? 0) >= 5 && time() < ($e['until'] ?? 0)) return (int) ($e['until'] - time());
    return 0;
}
function admin_throttle_record(string $ip, bool $ok): void
{
    $a = cms_json_read(admin_throttle_file(), []);
    foreach ($a as $k => $e) if (($e['until'] ?? 0) < time() - 3600) unset($a[$k]);
    if ($ok) unset($a[$ip]);
    else { $e = $a[$ip] ?? ['count' => 0]; $e['count'] = ($e['count'] ?? 0) + 1; $e['until'] = time() + 15 * 60; $a[$ip] = $e; }
    cms_json_write(admin_throttle_file(), $a);
}

/* CSRF */
function admin_csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function admin_csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . cms_e(admin_csrf()) . '">'; }
function admin_csrf_check(): void
{
    $tok = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($tok) || !hash_equals(admin_csrf(), $tok)) { http_response_code(403); exit('Sesión inválida (CSRF). Vuelve a intentarlo.'); }
}
function admin_is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

/* avisos y redirección */
function admin_flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type]; }
function admin_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function admin_redirect(string $to): void { header('Location: ' . $to, true, 303); exit; }
function admin_post(string $k, string $default = ''): string
{
    $v = $_POST[$k] ?? $default;
    return is_string($v) ? trim(str_replace("\r\n", "\n", $v)) : $default;
}

/** Limpieza mínima del HTML del editor visual. */
function admin_clean_html(string $html): string
{
    $html = preg_replace('#<\s*(script|style|object|embed)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? $html;
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1=$2#$2', $html) ?? $html;
    $html = trim($html);
    return in_array($html, ['<p><br></p>', '<p></p>'], true) ? '' : $html;
}
