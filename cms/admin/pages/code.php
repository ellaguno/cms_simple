<?php
/**
 * Código (avanzado): editor de los archivos del tema (site/): plantillas PHP, layout, CSS, JS, config.
 * - Sólo rutas dentro de site/ con extensiones permitidas.
 * - Cada guardado deja una copia previa en data/backups/ y se puede restaurar.
 * - Los archivos PHP se verifican con el analizador antes de escribirse (no se guarda un PHP roto).
 * Se desactiva con 'code_editor' => false en site/config.php.
 */
declare(strict_types=1);

if (cms_config('code_editor', true) === false) { admin_flash('El editor de código está desactivado en site/config.php.', 'err'); admin_redirect(admin_url()); }

const CODE_EXT = ['php', 'css', 'js', 'html', 'htm', 'json', 'txt', 'md', 'xml'];
const CODE_MAX = 2 * 1024 * 1024;
$root = realpath(CMS_SITE);
$bakdir = CMS_DATA . '/backups';

/** Ruta relativa segura dentro de site/ o null. */
function code_safe(string $rel): ?string
{
    $rel = str_replace('\\', '/', trim($rel));
    if ($rel === '' || strpos($rel, '..') !== false || $rel[0] === '/' || strpos($rel, "\0") !== false) return null;
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, CODE_EXT, true)) return null;
    if (!preg_match('#^[A-Za-z0-9_./-]+$#', $rel)) return null;
    return $rel;
}

function code_list(string $dir, string $prefix = ''): array
{
    $out = [];
    foreach (scandir($dir) ?: [] as $f) {
        if ($f[0] === '.') continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) { if (in_array($f, ['img', 'video', 'fonts', 'vendor', 'node_modules'], true)) continue; $out = array_merge($out, code_list($p, $prefix . $f . '/')); }
        elseif (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), CODE_EXT, true) && filesize($p) <= CODE_MAX) $out[] = $prefix . $f;
    }
    return $out;
}

function code_backups(string $rel): array
{
    global $bakdir;
    $key = str_replace('/', '__', $rel);
    $list = glob($bakdir . '/*__' . $key . '.bak') ?: [];
    rsort($list);
    return $list;
}

/** Verificación de sintaxis PHP sin ejecutar el archivo. */
function code_php_check(string $code): ?string
{
    try { token_get_all($code, TOKEN_PARSE); } catch (ParseError $e) { return 'Línea ' . $e->getLine() . ': ' . $e->getMessage(); }
    // php -l cuando está disponible (detecta más casos)
    if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true) && PHP_BINARY && strpos(PHP_BINARY, 'fpm') === false) {
        $tmp = tempnam(sys_get_temp_dir(), 'cmslint');
        file_put_contents($tmp, $code);
        @exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
        @unlink($tmp);
        if ($rc !== 0) return trim(preg_replace('/ in .*? on line/', ' en la línea', implode("\n", $out)));
    }
    return null;
}

$file = code_safe((string) ($_GET['f'] ?? ''));
$files = code_list($root);

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    $rel = code_safe(admin_post('file'));
    if (!$rel) { admin_flash('Ruta de archivo no permitida.', 'err'); admin_redirect(admin_url('code')); }
    $abs = $root . '/' . $rel;

    if ($action === 'save' || $action === 'create') {
        $code = (string) ($_POST['code'] ?? '');
        $code = str_replace("\r\n", "\n", $code);
        if ($action === 'create' && file_exists($abs)) { admin_flash('Ese archivo ya existe.', 'err'); admin_redirect(admin_url('code', ['f' => $rel])); }
        if (strlen($code) > CODE_MAX) { admin_flash('El archivo supera 2 MB.', 'err'); admin_redirect(admin_url('code', ['f' => $rel])); }
        if (strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'php' && ($err = code_php_check($code)) !== null) {
            $_SESSION['code_draft'][$rel] = $code;
            admin_flash('No se guardó: error de sintaxis PHP. ' . $err, 'err');
            admin_redirect(admin_url('code', ['f' => $rel]));
        }
        if (strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'json' && json_decode($code) === null && json_last_error() !== JSON_ERROR_NONE) {
            $_SESSION['code_draft'][$rel] = $code;
            admin_flash('No se guardó: JSON inválido (' . json_last_error_msg() . ').', 'err');
            admin_redirect(admin_url('code', ['f' => $rel]));
        }
        if (!is_dir(dirname($abs)) && !mkdir(dirname($abs), 0755, true)) { admin_flash('No se pudo crear la carpeta.', 'err'); admin_redirect(admin_url('code')); }
        if (is_file($abs)) {
            if (!is_dir($bakdir)) mkdir($bakdir, 0755, true);
            copy($abs, $bakdir . '/' . date('Ymd-His') . '__' . str_replace('/', '__', $rel) . '.bak');
            foreach (array_slice(code_backups($rel), 20) as $old) @unlink($old); // conserva las últimas 20
        }
        if (file_put_contents($abs, $code, LOCK_EX) === false) admin_flash('No se pudo escribir el archivo. Revisa permisos de site/.', 'err');
        else { unset($_SESSION['code_draft'][$rel]); admin_flash(($action === 'create' ? 'Archivo creado' : 'Guardado') . ': ' . $rel); }
        admin_redirect(admin_url('code', ['f' => $rel]));
    }
    if ($action === 'restore') {
        $bak = basename(admin_post('backup'));
        $src = $bakdir . '/' . $bak;
        if (!preg_match('/^\d{8}-\d{6}__.+\.bak$/', $bak) || !is_file($src)) { admin_flash('Respaldo no encontrado.', 'err'); admin_redirect(admin_url('code', ['f' => $rel])); }
        copy($abs, $bakdir . '/' . date('Ymd-His') . '__' . str_replace('/', '__', $rel) . '.bak');
        if (copy($src, $abs)) admin_flash('Restaurado desde ' . substr($bak, 0, 15) . '.');
        else admin_flash('No se pudo restaurar.', 'err');
        admin_redirect(admin_url('code', ['f' => $rel]));
    }
    admin_redirect(admin_url('code'));
}

$content = null;
$is_new = false;
if ($file) {
    $abs = $root . '/' . $file;
    if (isset($_SESSION['code_draft'][$file])) $content = $_SESSION['code_draft'][$file];
    elseif (is_file($abs)) $content = (string) file_get_contents($abs);
    else { $is_new = true; $content = ''; }
}
$ext = $file ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';
$mode = ['php' => 'application/x-httpd-php', 'css' => 'text/css', 'js' => 'text/javascript', 'json' => 'application/json', 'html' => 'text/html', 'htm' => 'text/html', 'xml' => 'application/xml', 'md' => 'text/x-markdown', 'txt' => 'text/plain'][$ext] ?? 'text/plain';
$writable = is_writable($root);

admin_header('Código del tema', 'code');
?>
<?php if (!$writable): ?><div class="ad-flash err">La carpeta <code>site/</code> no tiene permiso de escritura; se puede ver pero no guardar.</div><?php endif; ?>
<p class="ad-help">Edita las plantillas, el layout, el CSS, el JS y la configuración del tema (<code>site/</code>). Cada guardado deja un respaldo (últimos 20 por archivo) y los archivos PHP se verifican antes de escribirse. <strong>Un error en <code>site/config.php</code> o en el layout puede dejar el sitio o este panel sin funcionar</strong>; si pasa, restaura el respaldo desde aquí o por FTP desde <code>data/backups/</code>.</p>
<div class="ad-code">
  <aside class="ad-code-files">
    <form method="get" class="ad-code-new"><input type="hidden" name="p" value="code"><input type="text" name="f" placeholder="nuevo: templates/servicios.php" title="Ruta dentro de site/ (php, css, js, html, json, md, txt)"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Nuevo</button></form>
<?php $group = ''; foreach ($files as $f): $g = strpos($f, '/') !== false ? dirname($f) : '(raíz)'; if ($g !== $group): $group = $g; ?>
    <div class="ad-code-group"><?= cms_e($g) ?></div>
<?php endif; ?>
    <a href="<?= admin_url('code', ['f' => $f]) ?>"<?= $f === $file ? ' class="on"' : '' ?>><?= cms_e(basename($f)) ?></a>
<?php endforeach; ?>
  </aside>
  <section class="ad-code-main">
<?php if ($file === null && isset($_GET['f'])): ?>
    <div class="ad-flash err">Ruta no permitida. Sólo archivos dentro de <code>site/</code> con extensión php, css, js, html, json, md, txt o xml, sin espacios ni caracteres especiales.</div>
<?php elseif ($file === null): ?>
    <p class="ad-help">Elige un archivo de la lista o escribe la ruta de uno nuevo.</p>
<?php else: ?>
    <form method="post" class="ad-code-form" id="code-form">
      <?= admin_csrf_field() ?>
      <input type="hidden" name="action" value="<?= $is_new ? 'create' : 'save' ?>"><input type="hidden" name="file" value="<?= cms_e($file) ?>">
      <div class="ad-code-bar">
        <strong>site/<?= cms_e($file) ?></strong><?= $is_new ? ' <span class="ad-pill">nuevo</span>' : '' ?><?= isset($_SESSION['code_draft'][$file]) ? ' <span class="ad-pill" style="background:#fdecec;color:#a11212">borrador sin guardar (con error)</span>' : '' ?>
        <span class="ad-help">Ctrl+S guarda</span>
        <button class="ad-btn" type="submit"<?= $writable ? '' : ' disabled' ?>><?= $is_new ? 'Crear archivo' : 'Guardar' ?></button>
      </div>
      <textarea name="code" data-code data-mode="<?= cms_e($mode) ?>" spellcheck="false"><?= cms_e($content) ?></textarea>
    </form>
<?php $baks = $is_new ? [] : code_backups($file); if ($baks): ?>
    <details class="ad-box ad-code-baks"><summary><h2>Respaldos de este archivo (<?= count($baks) ?>)</h2></summary>
      <ul class="ad-list">
<?php foreach ($baks as $b): $n = basename($b); $ts = substr($n, 0, 15); ?>
        <li><?= cms_e(substr($ts, 6, 2) . '/' . substr($ts, 4, 2) . '/' . substr($ts, 0, 4) . ' ' . substr($ts, 9, 2) . ':' . substr($ts, 11, 2) . ':' . substr($ts, 13, 2)) ?> · <?= media_human((int) filesize($b)) ?>
          <form method="post" class="ad-inline" data-confirm="¿Restaurar esta versión? La actual se guarda como respaldo."><?= admin_csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="file" value="<?= cms_e($file) ?>"><input type="hidden" name="backup" value="<?= cms_e($n) ?>"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Restaurar</button></form></li>
<?php endforeach; ?>
      </ul>
    </details>
<?php endif; endif; ?>
  </section>
</div>
<?php admin_footer();
