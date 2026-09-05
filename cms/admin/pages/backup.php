<?php
/**
 * Respaldos: crea un zip con data/ y uploads/ (y opcionalmente site/) en /backups, lista los existentes y permite
 * descargarlos, restaurarlos (con respaldo automático previo) o borrarlos. Manual por ahora.
 */
declare(strict_types=1);

define('BACKUP_DIR', CMS_ROOT . '/backups');

function backup_ensure_dir(): bool
{
    if (!is_dir(BACKUP_DIR) && !@mkdir(BACKUP_DIR, 0755, true)) return false;
    $ht = BACKUP_DIR . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "# Los respaldos solo se descargan desde el panel\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
    if (!is_file(BACKUP_DIR . '/index.html')) @file_put_contents(BACKUP_DIR . '/index.html', '');
    return is_writable(BACKUP_DIR);
}

/** Añade una carpeta al zip de forma recursiva; $skip: rutas relativas a omitir. */
function backup_add_dir(ZipArchive $z, string $dir, string $prefix, array $skip = []): int
{
    $n = 0;
    if (!is_dir($dir)) return 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $rel = $prefix . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
        foreach ($skip as $s) if ($rel === $s || strpos($rel, $s . '/') === 0) continue 2;
        if ($f->isDir()) $z->addEmptyDir($rel);
        else { $z->addFile($f->getPathname(), $rel); $n++; }
    }
    return $n;
}

/** Crea un respaldo. Devuelve [ok, mensaje, archivo]. */
function backup_create(bool $withSite, string $note = ''): array
{
    if (!class_exists('ZipArchive')) return [false, 'Este servidor no tiene la extensión zip de PHP; pide al proveedor activarla.', ''];
    if (!backup_ensure_dir()) return [false, 'No se puede escribir en la carpeta backups/. Revisa permisos (755 o 775).', ''];
    $name = 'respaldo-' . date('Ymd-His') . ($withSite ? '-con-tema' : '') . '.zip';
    $file = BACKUP_DIR . '/' . $name;
    $z = new ZipArchive();
    if ($z->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return [false, 'No se pudo crear el archivo zip.', ''];
    $n = backup_add_dir($z, CMS_DATA, 'data', ['data/login-attempts.json']);
    $n += backup_add_dir($z, CMS_UPLOADS, 'uploads');
    if ($withSite) $n += backup_add_dir($z, CMS_SITE, 'site');
    $z->addFromString('respaldo.json', json_encode(['fecha' => date('c'), 'sitio' => cms_config('name'), 'version' => CMS_VERSION, 'incluye' => $withSite ? ['data', 'uploads', 'site'] : ['data', 'uploads'], 'archivos' => $n, 'nota' => $note, 'usuario' => admin_user()['user'] ?? ''], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $z->close();
    return [true, 'Respaldo creado: ' . $name . ' (' . $n . ' archivos, ' . round(filesize($file) / 1048576, 1) . ' MB).', $name];
}

function backup_list(): array
{
    $out = [];
    foreach (glob(BACKUP_DIR . '/*.zip') ?: [] as $f) {
        $info = [];
        if (class_exists('ZipArchive')) { $z = new ZipArchive(); if ($z->open($f) === true) { $j = $z->getFromName('respaldo.json'); if ($j) $info = (array) json_decode($j, true); $z->close(); } }
        $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f), 'info' => $info];
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}

function backup_rmdir_contents(string $dir, array $keep = []): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dir) + 1));
        foreach ($keep as $k) if ($rel === $k || strpos($rel, $k . '/') === 0 || strpos($k, $rel . '/') === 0) continue 2;
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
}

/** Restaura un respaldo: respaldo automático previo, y luego data/ y uploads/ (y site/ si el zip lo trae y se pide) quedan como en el zip. */
function backup_restore(string $name, bool $keepUsers, bool $withSite): array
{
    $file = BACKUP_DIR . '/' . basename($name);
    if (!is_file($file) || !class_exists('ZipArchive')) return [false, 'Respaldo no encontrado.'];
    [$ok, $msg] = backup_create(true, 'Automático antes de restaurar ' . basename($name));
    if (!$ok) return [false, 'No se pudo hacer el respaldo previo: ' . $msg];
    $z = new ZipArchive();
    if ($z->open($file) !== true) return [false, 'No se pudo abrir el zip.'];
    $tmp = BACKUP_DIR . '/.restaurando-' . bin2hex(random_bytes(3));
    @mkdir($tmp, 0755, true);
    if (!$z->extractTo($tmp)) { $z->close(); backup_rmdir_contents($tmp); @rmdir($tmp); return [false, 'No se pudo extraer el zip.']; }
    $z->close();
    $hasSite = is_dir($tmp . '/site');
    $targets = [['data', CMS_DATA, $keepUsers ? ['users.json', '.secret'] : ['.secret']], ['uploads', CMS_UPLOADS, ['.htaccess', 'index.html']]];
    if ($withSite && $hasSite) $targets[] = ['site', CMS_SITE, []];
    $copied = 0;
    foreach ($targets as [$sub, $dest, $keep]) {
        $src = $tmp . '/' . $sub;
        if (!is_dir($src)) continue;
        backup_rmdir_contents($dest, $keep);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $f) {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($src) + 1));
            foreach ($keep as $k) if ($rel === $k) continue 2;
            $to = $dest . '/' . $rel;
            if ($f->isDir()) { if (!is_dir($to)) @mkdir($to, 0755, true); }
            else { if (!is_dir(dirname($to))) @mkdir(dirname($to), 0755, true); if (@copy($f->getPathname(), $to)) $copied++; }
        }
    }
    backup_rmdir_contents($tmp); @rmdir($tmp);
    cms_items_flush();
    return [true, 'Restaurado ' . basename($name) . ' (' . $copied . ' archivos). Antes se guardó un respaldo automático del estado anterior.'];
}

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    if ($action === 'create') { [$ok, $msg] = backup_create(admin_post('with_site') === '1', admin_post('note')); admin_flash($msg, $ok ? 'ok' : 'err'); }
    elseif ($action === 'restore') { [$ok, $msg] = backup_restore(admin_post('name'), admin_post('keep_users') !== '0', admin_post('with_site') === '1'); admin_flash($msg, $ok ? 'ok' : 'err'); }
    elseif ($action === 'delete') { $f = BACKUP_DIR . '/' . basename(admin_post('name')); if (is_file($f) && preg_match('/\.zip$/', $f) && unlink($f)) admin_flash('Respaldo eliminado.'); else admin_flash('No se pudo eliminar.', 'err'); }
    admin_redirect(admin_url('backup'));
}
if (($_GET['download'] ?? '') !== '') {
    $f = BACKUP_DIR . '/' . basename((string) $_GET['download']);
    if (is_file($f) && preg_match('/\.zip$/', $f)) {
        header('Content-Type: application/zip'); header('Content-Length: ' . filesize($f)); header('Content-Disposition: attachment; filename="' . basename($f) . '"');
        readfile($f); exit;
    }
}

backup_ensure_dir();
$list = backup_list();
$zipOk = class_exists('ZipArchive');
admin_header('Respaldos', 'backup');
?>
<p class="ad-help">Un respaldo guarda el contenido (<code>data/</code>: páginas, artículos, ajustes, textos, menú, usuarios, versiones) y los archivos subidos (<code>uploads/</code>) en un zip dentro de <code>/backups</code>, que no es accesible desde fuera. Restaurar deja esas carpetas como estaban en el respaldo; antes se crea otro respaldo automático por si te arrepientes.</p>
<?php if (!$zipOk): ?><div class="ad-flash err">Este servidor no tiene la extensión zip de PHP. Pide al proveedor activarla para poder crear y restaurar respaldos.</div><?php endif; ?>
<section class="ad-box">
  <h2>Crear respaldo</h2>
  <form method="post" class="ad-form">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="ad-field"><label>Nota (opcional, para recordar por qué)</label><input type="text" name="note" placeholder="Antes de cambiar los precios"></div>
    <div class="ad-field"><label class="ad-check"><input type="checkbox" name="with_site" value="1"> Incluir también el tema (<code>site/</code>: plantillas, CSS, JS, imágenes del diseño)</label></div>
    <p><button class="ad-btn" type="submit"<?= $zipOk ? '' : ' disabled' ?>>Crear respaldo ahora</button></p>
  </form>
</section>
<section class="ad-box">
  <h2>Respaldos existentes (<?= count($list) ?>)</h2>
<?php if (!$list): ?><p class="ad-help">Aún no hay respaldos.</p><?php else: ?>
  <table class="ad-table">
    <thead><tr><th>Archivo</th><th>Fecha</th><th>Contiene</th><th>Tamaño</th><th>Nota</th><th></th></tr></thead>
    <tbody>
<?php foreach ($list as $b): $i = $b['info']; $hasSite = in_array('site', (array) ($i['incluye'] ?? []), true); ?>
      <tr>
        <td><strong><?= cms_e($b['name']) ?></strong></td>
        <td><?= date('Y-m-d H:i', $b['time']) ?></td>
        <td><?= cms_e(implode(', ', (array) ($i['incluye'] ?? ['data', 'uploads']))) ?><?= isset($i['archivos']) ? ' · ' . (int) $i['archivos'] . ' archivos' : '' ?></td>
        <td><?= round($b['size'] / 1048576, 1) ?> MB</td>
        <td class="ad-help"><?= cms_e($i['nota'] ?? '') ?><?= !empty($i['usuario']) ? ' · ' . cms_e($i['usuario']) : '' ?></td>
        <td class="ad-row-actions">
          <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('backup', ['download' => $b['name']]) ?>">Descargar</a>
          <details class="ad-map-menu"><summary class="ad-btn ad-btn-sm">Restaurar…</summary><div class="ad-map-menu-box ad-restore-box">
            <form method="post" data-confirm="¿Restaurar <?= cms_e($b['name']) ?>? El contenido actual se sustituirá por el del respaldo. Antes se guardará un respaldo automático.">
              <?= admin_csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="name" value="<?= cms_e($b['name']) ?>">
              <label class="ad-check"><input type="hidden" name="keep_users" value="0"><input type="checkbox" name="keep_users" value="1" checked> Conservar los usuarios actuales</label>
<?php if ($hasSite): ?>              <label class="ad-check"><input type="checkbox" name="with_site" value="1"> Restaurar también el tema (site/)</label>
<?php endif; ?>
              <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Restaurar este respaldo</button>
            </form>
          </div></details>
          <form method="post" class="ad-inline" data-confirm="¿Eliminar el respaldo <?= cms_e($b['name']) ?>? No se puede deshacer."><?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= cms_e($b['name']) ?>"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Eliminar</button></form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
  <p class="ad-help">Los respaldos ocupan espacio en el hosting: descarga los importantes a tu equipo y elimina los viejos. Para restaurar sin el panel, descomprime el zip en la raíz del sitio por FTP.</p>
</section>
<?php admin_footer();
