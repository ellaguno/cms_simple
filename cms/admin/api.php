<?php
/**
 * cms_simple — API HTTP del contenido. La llama el enrutador para /admin/api/… ($api_route = lo que sigue).
 * Autenticación: cabecera "Authorization: Bearer <token>" o "X-CMS-Token: <token>" (algunos hostings quitan Authorization).
 *
 *   GET    /admin/api/                       → quién soy, versión y tipos a los que llega el token
 *   GET    /admin/api/types                  → esquema de los tipos (campos, idiomas)
 *   GET    /admin/api/items/{tipo}           → listado (?status=published|scheduled|expired|draft &q= &page= &per=)
 *   GET    /admin/api/items/{tipo}/{slug}    → el elemento completo
 *   POST   /admin/api/items/{tipo}           → crear o actualizar desde JSON (mismo saneado que el formulario)
 *   DELETE /admin/api/items/{tipo}/{slug}    → eliminar
 *   POST   /admin/api/upload                 → subir un medio (multipart, campo "file")
 * También vale ?type= en lugar del segmento del tipo. Respuestas JSON con "ok".
 */
declare(strict_types=1);

define('CMS_API', true);
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/fields.php';
require_once __DIR__ . '/inc/media.php';
require_once __DIR__ . '/inc/api.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

$out = function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
};

// ---- token
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if (($wait = admin_throttle_blocked($ip)) > 0) $out(429, ['ok' => false, 'error' => 'Demasiados intentos con un token incorrecto. Espera ' . ceil($wait / 60) . ' min.']);
$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($auth === '' && function_exists('getallheaders')) foreach ((array) getallheaders() as $k => $v) if (strcasecmp((string) $k, 'Authorization') === 0) $auth = (string) $v;
$raw = (string) ($_SERVER['HTTP_X_CMS_TOKEN'] ?? '');
if ($raw === '' && preg_match('/^Bearer\s+(\S+)/i', $auth, $m)) $raw = $m[1];
$tok = $raw !== '' ? api_token_check($raw) : null;
if (!$tok) {
    if ($raw !== '') admin_throttle_record($ip, false);
    $out(401, ['ok' => false, 'error' => $raw === '' ? 'Falta el token (cabecera Authorization: Bearer … o X-CMS-Token).' : 'Token inválido o revocado.']);
}

// ---- ruta
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$seg = array_values(array_filter(explode('/', trim((string) ($api_route ?? ''), '/')), fn($s) => $s !== ''));
$what = $seg[0] ?? '';
$type = preg_replace('/[^a-z0-9_-]/i', '', (string) ($seg[1] ?? ($_GET['type'] ?? '')));
$slug = cms_slugify((string) ($seg[2] ?? ($_GET['slug'] ?? '')));

if ($what === '' || $what === 'me') {
    $out(200, ['ok' => true, 'user' => $tok['user'], 'token' => $tok['label'], 'types' => $tok['types'], 'version' => CMS_VERSION]);
}
if ($what === 'types' && $method === 'GET') $out(200, ['ok' => true] + api_types($tok));
if ($what === 'upload') {
    if ($method !== 'POST') $out(405, ['ok' => false, 'error' => 'Usa POST (multipart, campo "file").']);
    $f = $_FILES['file'] ?? null;
    if (!$f) $out(413, ['ok' => false, 'error' => 'No se recibió el archivo (campo "file"). Puede que supere el límite del servidor (' . media_human(media_limit_bytes()) . ').']);
    [$code, $body] = api_upload($f);
    $out($code, $body);
}
if ($what === 'items') {
    if ($type === '' || !cms_type($type)) $out(404, ['ok' => false, 'error' => 'Tipo de contenido desconocido: «' . $type . '».']);
    if (!api_can($tok, $type)) $out(403, ['ok' => false, 'error' => 'El token no tiene acceso a «' . $type . '».']);
    if ($method === 'GET' && $slug === '') $out(200, ['ok' => true] + api_list($type, $_GET));
    if ($method === 'GET') {
        $it = cms_item($type, $slug, false);
        $it ? $out(200, ['ok' => true, 'status' => api_status_of($it), 'url' => cms_abs_url(cms_item_url($type, $it, cms_default_lang())), 'item' => $it])
            : $out(404, ['ok' => false, 'error' => 'No existe «' . $slug . '» en ' . $type . '.']);
    }
    if ($method === 'DELETE') {
        [$ok, $msg] = cms_item_remove($type, $slug);
        $out($ok ? 200 : 400, ['ok' => $ok, ($ok ? 'message' : 'error') => $msg]);
    }
    if ($method === 'POST' || $method === 'PUT') {
        $body = trim((string) file_get_contents('php://input'));
        admin_unarmor($body);   // el cuerpo entero puede venir blindado (=?rb64?=…) para firewalls que inspeccionan el JSON
        $in = json_decode($body, true);
        if (!is_array($in)) $out(400, ['ok' => false, 'error' => 'El cuerpo debe ser un objeto JSON.']);
        if ($slug !== '') $in['slug'] = $slug;
        [$code, $res] = api_put($type, $in, $tok);
        $out($code, $res);
    }
    $out(405, ['ok' => false, 'error' => 'Método no admitido.']);
}
$out(404, ['ok' => false, 'error' => 'Ruta desconocida. Usa /admin/api/, /types, /items/{tipo}, /items/{tipo}/{slug} o /upload.']);
