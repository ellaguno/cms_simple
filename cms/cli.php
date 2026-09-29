<?php
/**
 * cms_simple — línea de comandos para scripts que corren en el mismo servidor (sin token ni sesión).
 *
 *   php cms/cli.php types                           esquema de los tipos de contenido
 *   php cms/cli.php list <tipo> [--status=draft] [--q=texto] [--page=N] [--per=N]
 *   php cms/cli.php get <tipo> <slug>
 *   php cms/cli.php put <tipo> <archivo.json | ->   crear o actualizar (mismo JSON que POST /admin/api/items/{tipo})
 *   php cms/cli.php delete <tipo> <slug>
 *   php cms/cli.php upload <archivo>                 copia un medio a uploads/AAAA/MM (mismas reglas que el panel)
 *   php cms/cli.php token create <usuario> <nombre> [tipo …]   token para la API (sin tipos = todos)
 *   php cms/cli.php token list | token revoke <id>
 * Todo responde JSON por la salida estándar; el código de salida es 0 si salió bien.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REQUEST_URI'] = '/';
define('CMS_API', true);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin/inc/auth.php';
require_once __DIR__ . '/admin/inc/fields.php';
require_once __DIR__ . '/admin/inc/media.php';
require_once __DIR__ . '/admin/inc/api.php';

$args = array_slice($argv, 1);
$opt = [];
$pos = [];
foreach ($args as $a) {
    if (preg_match('/^--([a-z_]+)=(.*)$/', $a, $m)) $opt[$m[1]] = $m[2];
    else $pos[] = $a;
}
$done = function (int $code, array $body): void {
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    exit($code < 400 ? 0 : 1);
};
$needType = function (string $t) use ($done): string {
    if ($t === '' || !cms_type($t)) $done(404, ['ok' => false, 'error' => 'Tipo de contenido desconocido: «' . $t . '». Mira «types».']);
    return $t;
};

switch ($pos[0] ?? '') {
    case 'types':
        $done(200, ['ok' => true] + api_types());
    case 'list':
        $done(200, ['ok' => true] + api_list($needType($pos[1] ?? ''), $opt));
    case 'get':
        $t = $needType($pos[1] ?? '');
        $it = cms_item($t, cms_slugify($pos[2] ?? ''), false);
        $it ? $done(200, ['ok' => true, 'status' => api_status_of($it), 'item' => $it]) : $done(404, ['ok' => false, 'error' => 'No existe.']);
    case 'put':
        $t = $needType($pos[1] ?? '');
        $src = $pos[2] ?? '-';
        $json = $src === '-' ? stream_get_contents(STDIN) : (is_file($src) ? file_get_contents($src) : false);
        $in = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($in)) $done(400, ['ok' => false, 'error' => 'Se esperaba un objeto JSON en ' . ($src === '-' ? 'la entrada estándar' : $src) . '.']);
        [$code, $res] = api_put($t, $in);
        $done($code, $res);
    case 'delete':
        [$ok, $msg] = cms_item_remove($needType($pos[1] ?? ''), (string) ($pos[2] ?? ''));
        $done($ok ? 200 : 400, ['ok' => $ok, ($ok ? 'message' : 'error') => $msg]);
    case 'upload':
        $f = (string) ($pos[1] ?? '');
        if (!is_file($f)) $done(404, ['ok' => false, 'error' => 'No existe el archivo «' . $f . '».']);
        [$code, $res] = api_upload(['name' => basename($f), 'tmp_name' => $f, 'size' => filesize($f), 'error' => UPLOAD_ERR_OK], true);
        $done($code, $res);
    case 'token':
        $sub = $pos[1] ?? '';
        if ($sub === 'list') $done(200, ['ok' => true, 'tokens' => array_map(fn($t) => array_diff_key($t, ['hash' => 1]), api_tokens())]);
        if ($sub === 'revoke') { $ok = api_token_revoke((string) ($pos[2] ?? '')); $done($ok ? 200 : 404, ['ok' => $ok]); }
        if ($sub === 'create') {
            $user = (string) ($pos[2] ?? '');
            if (!array_filter(cms_users(), fn($u) => ($u['user'] ?? '') === $user)) $done(404, ['ok' => false, 'error' => 'No existe el usuario «' . $user . '».']);
            $tok = api_token_create($user, (string) ($pos[3] ?? 'Línea de comandos'), array_slice($pos, 4));
            $tok !== '' ? $done(201, ['ok' => true, 'token' => $tok, 'note' => 'Guárdalo ahora: no se vuelve a mostrar.']) : $done(400, ['ok' => false, 'error' => 'No se creó: ningún tipo de contenido válido entre los pedidos, o no se pudo escribir data/api-tokens.json.']);
        }
        $done(400, ['ok' => false, 'error' => 'Uso: token create <usuario> <nombre> [tipo …] | token list | token revoke <id>']);
    default:
        fwrite(STDERR, "Uso: php cms/cli.php types | list <tipo> | get <tipo> <slug> | put <tipo> <archivo.json|-> | delete <tipo> <slug> | upload <archivo> | token …\n");
        exit(2);
}
