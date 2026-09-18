<?php
/**
 * Archivos y carpetas (1.34): gestor de las carpetas estáticas del sitio, fuera del CMS. Para subir una presentación,
 * una landing hecha a mano o cualquier conjunto de HTML, CSS, JS e imágenes (con subcarpetas) a /nombre/, y editar
 * sus archivos de texto con el editor de código.
 *
 * - Solo carpetas propias en la raíz del sitio: nunca cms/, site/, themes/, packs/, data/, uploads/, admin/ ni los
 *   archivos de la raíz.
 * - Extensiones permitidas (lista cerrada): nada de PHP ni ejecutables. Cada carpeta raíz lleva un .htaccess que
 *   apaga PHP por si acaso.
 * - Un zip se descomprime respetando sus subcarpetas (con el mismo filtro).
 * - Los archivos de texto se editan con respaldo (data/backups/) como el Código del tema.
 * Se desactiva con 'file_manager' => false en site/config.php.
 */
declare(strict_types=1);

if (cms_config('file_manager', true) === false) { admin_flash('El gestor de archivos está desactivado en site/config.php.', 'err'); admin_redirect(admin_url()); }

const FM_RESERVED = ['cms', 'site', 'themes', 'packs', 'data', 'uploads', 'admin', 'tools', 'vendor', 'node_modules', 'cache', '_build'];
const FM_EXT = ['html', 'htm', 'css', 'js', 'mjs', 'json', 'txt', 'md', 'xml', 'svg', 'csv', 'pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'avif', 'mp4', 'webm', 'mp3', 'ogg', 'wav', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'webmanifest', 'map', 'vtt', 'srt', 'zip'];
const FM_TEXT = ['html', 'htm', 'css', 'js', 'mjs', 'json', 'txt', 'md', 'xml', 'svg', 'csv', 'webmanifest', 'vtt', 'srt'];
const FM_MAX_TEXT = 2 * 1024 * 1024;

/** Nombre de archivo o carpeta válido (sin rutas). */
function fm_name_ok(string $n): bool { return $n !== '' && $n[0] !== '.' && preg_match('/^[A-Za-z0-9_][A-Za-z0-9_. -]{0,120}$/', $n) === 1 && strpos($n, '..') === false; }

/** Ruta relativa segura de una carpeta gestionable ("presentacion", "presentacion/img") o null. "" = raíz (lista de carpetas). */
function fm_dir(string $rel): ?string
{
    $rel = trim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') return '';
    $parts = explode('/', $rel);
    foreach ($parts as $p) if (!fm_name_ok($p)) return null;
    if (in_array(strtolower($parts[0]), FM_RESERVED, true)) return null;
    return implode('/', $parts);
}

function fm_ext_ok(string $name): bool { return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), FM_EXT, true); }
function fm_is_text(string $name): bool { return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), FM_TEXT, true); }

/** .htaccess que apaga PHP en una carpeta raíz gestionada (por si alguien cuela un .php por otra vía). */
function fm_protect(string $abs): void
{
    $f = $abs . '/.htaccess';
    if (is_file($f)) return;
    @file_put_contents($f, "# Generado por cms_simple (Archivos y carpetas): aquí no se ejecuta PHP y la portada es index.html.\nDirectoryIndex index.html index.htm index.php\n<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n  <IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n  </IfModule>\n</FilesMatch>\n<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n");
}

/** Carpetas gestionables de la raíz (las que no son del CMS). */
function fm_roots(): array
{
    $out = [];
    foreach (glob(CMS_ROOT . '/*', GLOB_ONLYDIR) ?: [] as $d) {
        $n = basename($d);
        if (!fm_name_ok($n) || in_array(strtolower($n), FM_RESERVED, true)) continue;
        $out[] = $n;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function fm_rmdir(string $abs): bool
{
    foreach (scandir($abs) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $abs . '/' . $f;
        if (is_dir($p) && !is_link($p)) { if (!fm_rmdir($p)) return false; } elseif (!@unlink($p)) return false;
    }
    return @rmdir($abs);
}

/** Tamaño total y número de archivos de una carpeta. */
function fm_size(string $abs): array
{
    $n = 0; $b = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) { $n++; $b += $f->getSize(); }
    return [$n, $b];
}

/** Descomprime un zip dentro de $dst respetando subcarpetas, con el filtro de nombres y extensiones. Devuelve [copiados, omitidos]. */
function fm_unzip(string $zipFile, string $dst): array
{
    if (!class_exists('ZipArchive')) return [0, 0];
    $z = new ZipArchive();
    if ($z->open($zipFile) !== true) return [0, 0];
    $n = 0; $skipped = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $entry = str_replace('\\', '/', (string) $z->getNameIndex($i));
        if ($entry === '' || strpos($entry, '..') !== false || $entry[0] === '/' || strpos($entry, "\0") !== false || strpos($entry, '__MACOSX/') === 0) { $skipped++; continue; }
        $parts = array_values(array_filter(explode('/', $entry), 'strlen'));
        $bad = false; foreach ($parts as $p) if (!fm_name_ok($p)) { $bad = true; break; }
        if ($bad) { $skipped++; continue; }
        $rel = implode('/', $parts);
        if (substr($entry, -1) === '/') { @mkdir($dst . '/' . $rel, 0755, true); continue; }
        if (!fm_ext_ok($rel) || strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'zip') { $skipped++; continue; }
        @mkdir(dirname($dst . '/' . $rel), 0755, true);
        $src = $z->getStream($entry);
        if (!$src) { $skipped++; continue; }
        $out = @fopen($dst . '/' . $rel, 'wb');
        if ($out) { stream_copy_to_stream($src, $out); fclose($out); @chmod($dst . '/' . $rel, 0644); $n++; } else $skipped++;
        fclose($src);
    }
    $z->close();
    return [$n, $skipped];
}

$dir = fm_dir((string) ($_GET['d'] ?? ''));
if ($dir === null) { admin_flash('Carpeta no permitida.', 'err'); admin_redirect(admin_url('archivos')); }
$abs = $dir === '' ? CMS_ROOT : CMS_ROOT . '/' . $dir;
$rootName = $dir === '' ? '' : explode('/', $dir)[0];
$bakdir = CMS_DATA . '/backups';
$back = fn(string $d = '') => admin_url('archivos', $d !== '' ? ['d' => $d] : []);

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    $target = fm_dir(admin_post('dir'));
    if ($target === null) { admin_flash('Carpeta no permitida.', 'err'); admin_redirect($back()); }
    $tabs = $target === '' ? CMS_ROOT : CMS_ROOT . '/' . $target;

    if ($action === 'mkdir') {   // carpeta nueva (en la raíz o dentro de la actual)
        $name = trim(admin_post('name'));
        if (!fm_name_ok($name) || ($target === '' && in_array(strtolower($name), FM_RESERVED, true))) admin_flash('Nombre no válido: letras, números, guiones y puntos; sin espacios al inicio ni nombres del sistema.', 'err');
        elseif (file_exists($tabs . '/' . $name)) admin_flash('Ya existe algo con ese nombre.', 'err');
        elseif (!@mkdir($tabs . '/' . $name, 0755, true)) admin_flash('No se pudo crear la carpeta (permisos en la raíz del sitio).', 'err');
        else { if ($target === '') fm_protect($tabs . '/' . $name); admin_flash('Carpeta creada: /' . ($target !== '' ? $target . '/' : '') . $name . '/'); admin_redirect($back(($target !== '' ? $target . '/' : '') . $name)); }
        admin_redirect($back($target));
    }
    if ($target === '') { admin_flash('Elige una carpeta.', 'err'); admin_redirect($back()); }

    if ($action === 'upload') {
        $files = $_FILES['files'] ?? null; $unzip = admin_post('unzip') === '1';
        $n = 0; $errs = []; $ex = 0;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                if (($files['error'][$i] ?? 1) === UPLOAD_ERR_NO_FILE) continue;
                $name = basename(str_replace('\\', '/', (string) $name));
                if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) { $errs[] = $name . ': error al subir (¿supera el límite de ' . media_human(media_limit_bytes()) . '?).'; continue; }
                if (!fm_name_ok($name) || !fm_ext_ok($name)) { $errs[] = $name . ': nombre o tipo no permitido.'; continue; }
                $tmp = (string) $files['tmp_name'][$i];
                if ($unzip && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'zip') { [$c, $s] = fm_unzip($tmp, $tabs); $ex += $c; if ($s) $errs[] = $name . ': ' . $s . ' entradas omitidas (tipo o nombre no permitido).'; continue; }
                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'zip') { $errs[] = $name . ': los zip solo se suben para descomprimirlos (marca la casilla).'; continue; }
                if (move_uploaded_file($tmp, $tabs . '/' . $name)) { @chmod($tabs . '/' . $name, 0644); $n++; } else $errs[] = $name . ': no se pudo guardar.';
            }
        } else $errs[] = 'No se recibieron archivos (¿superan el límite de ' . media_human(media_limit_bytes()) . '?).';
        fm_protect(CMS_ROOT . '/' . explode('/', $target)[0]);
        if ($n) admin_flash($n . ' archivo(s) subido(s).');
        if ($ex) admin_flash($ex . ' archivo(s) extraídos del zip.');
        foreach ($errs as $e) admin_flash($e, 'err');
    } elseif ($action === 'delete') {
        $name = basename(admin_post('name'));
        $p = $tabs . '/' . $name;
        if (!fm_name_ok($name) || !file_exists($p)) admin_flash('No existe.', 'err');
        elseif (is_dir($p)) { if (fm_rmdir($p)) admin_flash('Carpeta eliminada: ' . $name . '/'); else admin_flash('No se pudo eliminar la carpeta completa (permisos).', 'err'); }
        elseif (@unlink($p)) admin_flash('Archivo eliminado: ' . $name);
        else admin_flash('No se pudo eliminar (permisos).', 'err');
    } elseif ($action === 'delete_root') {
        $p = CMS_ROOT . '/' . explode('/', $target)[0];
        if (fm_rmdir($p)) admin_flash('Carpeta eliminada: /' . explode('/', $target)[0] . '/'); else admin_flash('No se pudo eliminar la carpeta completa (permisos).', 'err');
        admin_redirect($back());
    } elseif ($action === 'rename') {
        $name = basename(admin_post('name')); $new = trim(admin_post('new'));
        $p = $tabs . '/' . $name;
        if (!fm_name_ok($name) || !file_exists($p)) admin_flash('No existe.', 'err');
        elseif (!fm_name_ok($new) || (is_file($p) && !fm_ext_ok($new))) admin_flash('Nombre nuevo no válido.', 'err');
        elseif (file_exists($tabs . '/' . $new)) admin_flash('Ya existe algo con ese nombre.', 'err');
        elseif (@rename($p, $tabs . '/' . $new)) admin_flash('Renombrado: ' . $new);
        else admin_flash('No se pudo renombrar.', 'err');
    } elseif ($action === 'save' || $action === 'create') {
        $name = basename(admin_post('file'));
        $p = $tabs . '/' . $name;
        $code = str_replace("\r\n", "\n", (string) ($_POST['code'] ?? ''));
        if (!fm_name_ok($name) || !fm_is_text($name)) { admin_flash('Solo se editan archivos de texto (html, css, js, json, txt, md, xml, svg, csv).', 'err'); admin_redirect($back($target)); }
        if ($action === 'create' && file_exists($p)) { admin_flash('Ese archivo ya existe.', 'err'); admin_redirect($back($target)); }
        if (strlen($code) > FM_MAX_TEXT) { admin_flash('El archivo supera 2 MB.', 'err'); admin_redirect(admin_url('archivos', ['d' => $target, 'f' => $name])); }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'json' && json_decode($code) === null && json_last_error() !== JSON_ERROR_NONE) { $_SESSION['fm_draft'][$target . '/' . $name] = $code; admin_flash('No se guardó: JSON inválido (' . json_last_error_msg() . ').', 'err'); admin_redirect(admin_url('archivos', ['d' => $target, 'f' => $name])); }
        if (is_file($p)) {
            if (!is_dir($bakdir)) @mkdir($bakdir, 0755, true);
            $key = 'archivos__' . str_replace('/', '__', $target . '/' . $name);
            @copy($p, $bakdir . '/' . date('Ymd-His') . '__' . $key . '.bak');
            $old = glob($bakdir . '/*__' . $key . '.bak') ?: []; rsort($old); foreach (array_slice($old, 20) as $o) @unlink($o);
        }
        if (file_put_contents($p, $code, LOCK_EX) === false) admin_flash('No se pudo escribir el archivo (permisos).', 'err');
        else { unset($_SESSION['fm_draft'][$target . '/' . $name]); admin_flash(($action === 'create' ? 'Archivo creado: ' : 'Guardado: ') . $name); }
        admin_redirect(admin_url('archivos', ['d' => $target, 'f' => $name]));
    }
    admin_redirect($back($target));
}

// ---------- vista
$file = trim((string) ($_GET['f'] ?? ''));
if ($file !== '' && (!fm_name_ok($file) || !fm_is_text($file) || $dir === '')) $file = '';
$editing = $file !== '';
admin_header('Archivos y carpetas', 'archivos');
$crumbs = []; if ($dir !== '') { $acc = ''; foreach (explode('/', $dir) as $p) { $acc .= ($acc === '' ? '' : '/') . $p; $crumbs[] = [$p, $acc]; } }
?>
<p class="ad-help">Carpetas propias en la raíz del sitio, fuera del CMS: una presentación, una landing hecha a mano, documentos… Cada carpeta responde en <code><?= cms_e(cms_site_url()) ?>/nombre/</code> (con su <code>index.html</code>). Sube archivos sueltos o un zip con subcarpetas, y edita los de texto aquí mismo. No se admiten archivos PHP.</p>
<p class="ad-actions ad-fm-crumbs">
  <a class="ad-pill<?= $dir === '' ? ' on' : '' ?>" href="<?= $back() ?>">Carpetas</a>
<?php foreach ($crumbs as [$label, $path]): ?>  <span class="ad-help">/</span> <a class="ad-pill<?= $path === $dir ? ' on' : '' ?>" href="<?= $back($path) ?>"><?= cms_e($label) ?></a>
<?php endforeach; if ($dir !== ''): ?>  <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e(CMS_BASE . '/' . $dir . '/') ?>" target="_blank" rel="noopener">Abrir /<?= cms_e($dir) ?>/ ↗</a>
<?php endif; ?>
</p>

<?php if ($dir === ''): $roots = fm_roots(); ?>
<section class="ad-box">
  <form method="post" class="ad-fm-new"><?= admin_csrf_field() ?><input type="hidden" name="action" value="mkdir"><input type="hidden" name="dir" value="">
    <input type="text" name="name" placeholder="nueva carpeta (p. ej. presentacion)" pattern="[A-Za-z0-9_][A-Za-z0-9_.\-]*" required><button class="ad-btn ad-btn-sm" type="submit">Crear carpeta</button>
    <span class="ad-help">Se crea en la raíz, con un <code>.htaccess</code> que apaga PHP dentro de ella.</span>
  </form>
</section>
<?php if (!$roots): ?><p class="ad-help">Aún no hay carpetas propias. Crea una y sube en ella tu HTML, CSS, JS e imágenes.</p><?php else: ?>
<table class="ad-table">
  <thead><tr><th>Carpeta</th><th>Contenido</th><th>Responde en</th><th></th></tr></thead>
  <tbody>
<?php foreach ($roots as $r): [$n, $b] = fm_size(CMS_ROOT . '/' . $r); $hasIndex = is_file(CMS_ROOT . '/' . $r . '/index.html') || is_file(CMS_ROOT . '/' . $r . '/index.htm') || is_file(CMS_ROOT . '/' . $r . '/index.php'); ?>
    <tr>
      <td><a href="<?= $back($r) ?>"><strong><?= cms_e($r) ?>/</strong></a></td>
      <td><span class="ad-help"><?= $n ?> archivo<?= $n === 1 ? '' : 's' ?> · <?= media_human($b) ?></span></td>
      <td><?php if ($hasIndex): ?><a href="<?= cms_e(CMS_BASE . '/' . $r . '/') ?>" target="_blank" rel="noopener">/<?= cms_e($r) ?>/ ↗</a><?php else: ?><span class="ad-help">sin index.html</span><?php endif; ?></td>
      <td class="ad-row-actions">
        <a class="ad-btn ad-btn-sm" href="<?= $back($r) ?>">Abrir</a>
        <form method="post" class="ad-inline" data-confirm="¿Eliminar la carpeta /<?= cms_e($r) ?>/ con todo lo que contiene (<?= $n ?> archivos)? No se puede deshacer."><?= admin_csrf_field() ?><input type="hidden" name="action" value="delete_root"><input type="hidden" name="dir" value="<?= cms_e($r) ?>"><button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button></form>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php elseif ($editing): // ---------- editor de un archivo de texto
    $p = $abs . '/' . $file; $is_new = !is_file($p);
    $content = $_SESSION['fm_draft'][$dir . '/' . $file] ?? ($is_new ? '' : (string) file_get_contents($p));
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mode = ['css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript', 'json' => 'application/json', 'webmanifest' => 'application/json', 'html' => 'text/html', 'htm' => 'text/html', 'xml' => 'application/xml', 'svg' => 'application/xml', 'md' => 'text/x-markdown'][$ext] ?? 'text/plain';
    $key = 'archivos__' . str_replace('/', '__', $dir . '/' . $file); $baks = glob($bakdir . '/*__' . $key . '.bak') ?: []; rsort($baks);
?>
<div class="ad-code-main">
  <form method="post" class="ad-code-form" id="code-form">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="<?= $is_new ? 'create' : 'save' ?>"><input type="hidden" name="dir" value="<?= cms_e($dir) ?>"><input type="hidden" name="file" value="<?= cms_e($file) ?>">
    <div class="ad-code-bar">
      <strong>/<?= cms_e($dir . '/' . $file) ?></strong><?= $is_new ? ' <span class="ad-pill">nuevo</span>' : '' ?><?= isset($_SESSION['fm_draft'][$dir . '/' . $file]) ? ' <span class="ad-pill warn">borrador sin guardar (con error)</span>' : '' ?>
      <span class="ad-help">Ctrl+S guarda</span>
      <a class="ad-btn ad-btn-light ad-btn-sm" href="<?= $back($dir) ?>">Volver a la carpeta</a>
      <?php if (!$is_new): ?><a class="ad-btn ad-btn-light ad-btn-sm" href="<?= cms_e(CMS_BASE . '/' . $dir . '/' . $file) ?>" target="_blank" rel="noopener">Ver ↗</a><?php endif; ?>
      <button class="ad-btn" type="submit"><?= $is_new ? 'Crear archivo' : 'Guardar' ?></button>
    </div>
    <textarea name="code" data-code data-mode="<?= cms_e($mode) ?>" spellcheck="false"><?= cms_e($content) ?></textarea>
  </form>
<?php if ($baks): ?>
  <details class="ad-box ad-code-baks"><summary><h2>Respaldos de este archivo (<?= count($baks) ?>)</h2></summary>
    <ul class="ad-list">
<?php foreach ($baks as $b): $n = basename($b); $ts = substr($n, 0, 15); ?>
      <li><?= cms_e(substr($ts, 6, 2) . '/' . substr($ts, 4, 2) . '/' . substr($ts, 0, 4) . ' ' . substr($ts, 9, 2) . ':' . substr($ts, 11, 2)) ?> · <?= media_human((int) filesize($b)) ?>
        <form method="post" class="ad-inline" data-confirm="¿Restaurar esta versión? La actual se guarda como respaldo."><?= admin_csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="dir" value="<?= cms_e($dir) ?>"><input type="hidden" name="file" value="<?= cms_e($file) ?>"><textarea name="code" hidden><?= cms_e((string) file_get_contents($b)) ?></textarea><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Restaurar</button></form></li>
<?php endforeach; ?>
    </ul>
  </details>
<?php endif; ?>
</div>

<?php else: // ---------- contenido de una carpeta
    $entries = []; foreach (scandir($abs) ?: [] as $f) { if ($f[0] === '.') continue; $entries[] = $f; }
    usort($entries, fn($a, $b) => (is_dir("$abs/$b") <=> is_dir("$abs/$a")) ?: strnatcasecmp($a, $b));
?>
<section class="ad-box ad-dropzone" data-dropzone>
  <form method="post" enctype="multipart/form-data" class="ad-upload-form">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="dir" value="<?= cms_e($dir) ?>"><input type="hidden" name="MAX_FILE_SIZE" value="<?= media_limit_bytes() ?>">
    <label class="ad-btn">Subir archivos <input type="file" name="files[]" multiple hidden data-auto-submit></label>
    <label class="ad-check"><input type="checkbox" name="unzip" value="1" checked> Descomprimir los .zip (conserva sus subcarpetas)</label>
    <span class="ad-help">o arrastra aquí. HTML, CSS, JS, JSON, imágenes, PDF, fuentes, audio y video. Máximo por archivo: <strong><?= media_human(media_limit_bytes()) ?></strong>.</span>
  </form>
</section>
<p class="ad-actions ad-fm-tools">
  <form method="post" class="ad-inline ad-fm-new"><?= admin_csrf_field() ?><input type="hidden" name="action" value="mkdir"><input type="hidden" name="dir" value="<?= cms_e($dir) ?>"><input type="text" name="name" placeholder="subcarpeta nueva" pattern="[A-Za-z0-9_][A-Za-z0-9_.\-]*" required><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Crear subcarpeta</button></form>
  <form method="get" class="ad-inline ad-fm-new"><input type="hidden" name="p" value="archivos"><input type="hidden" name="d" value="<?= cms_e($dir) ?>"><input type="text" name="f" placeholder="archivo nuevo (index.html, estilos.css…)" pattern="[A-Za-z0-9_][A-Za-z0-9_.\-]*\.(html|htm|css|js|mjs|json|txt|md|xml|svg|csv|webmanifest)" required><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Crear y editar</button></form>
</p>
<?php if (!$entries): ?><p class="ad-help">Carpeta vacía. Sube un <code>index.html</code> (o un zip con todo) para que responda en /<?= cms_e($dir) ?>/.</p><?php else: ?>
<table class="ad-table">
  <thead><tr><th>Nombre</th><th>Tamaño</th><th>Modificado</th><th></th></tr></thead>
  <tbody>
<?php foreach ($entries as $f): $p = $abs . '/' . $f; $isDir = is_dir($p); ?>
    <tr>
      <td><?php if ($isDir): ?><a href="<?= $back($dir . '/' . $f) ?>"><strong>📁 <?= cms_e($f) ?>/</strong></a><?php elseif (fm_is_text($f) && filesize($p) <= FM_MAX_TEXT): ?><a href="<?= admin_url('archivos', ['d' => $dir, 'f' => $f]) ?>"><?= cms_e($f) ?></a><?php else: ?><?= cms_e($f) ?><?php endif; ?></td>
      <td><span class="ad-help"><?= $isDir ? (function () use ($p) { [$n, $b] = fm_size($p); return $n . ' archivos · ' . media_human($b); })() : media_human((int) filesize($p)) ?></span></td>
      <td><span class="ad-help"><?= date('Y-m-d H:i', (int) filemtime($p)) ?></span></td>
      <td class="ad-row-actions">
<?php if (!$isDir): ?>        <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e(CMS_BASE . '/' . $dir . '/' . $f) ?>" target="_blank" rel="noopener">Ver</a>
<?php if (fm_is_text($f) && filesize($p) <= FM_MAX_TEXT): ?>        <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('archivos', ['d' => $dir, 'f' => $f]) ?>">Editar</a>
<?php endif; endif; ?>
        <button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-fm-rename>Renombrar</button>
        <form method="post" class="ad-inline" data-confirm="¿Eliminar <?= cms_e($f) ?><?= $isDir ? '/ con todo su contenido' : '' ?>? No se puede deshacer."><?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="dir" value="<?= cms_e($dir) ?>"><input type="hidden" name="name" value="<?= cms_e($f) ?>"><button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button></form>
        <form method="post" class="ad-fm-rename" data-fm-rename-form><?= admin_csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="dir" value="<?= cms_e($dir) ?>"><input type="hidden" name="name" value="<?= cms_e($f) ?>"><input type="text" name="new" value="<?= cms_e($f) ?>" aria-label="Nuevo nombre" required><button class="ad-btn ad-btn-sm" type="submit">OK</button></form>
      </td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<script>
document.querySelectorAll("[data-fm-rename]").forEach(function (b) {
  b.addEventListener("click", function () { var f = b.closest("td").querySelector("[data-fm-rename-form]"); f.classList.toggle("on"); if (f.classList.contains("on")) { var i = f.querySelector("input[name=new]"); i.focus(); i.select(); } });
});
</script>
<?php endif; ?>
<?php admin_footer();
