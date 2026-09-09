<?php
/**
 * cms_simple — actualizar el núcleo (cms/) desde el panel.
 *
 * El proyecto publica un archivo de versión (JSON) con la versión disponible, el zip de donde sacarla y las notas:
 *   { "version": "1.21.0", "date": "2026-09-09", "notes": "…",
 *     "url": "https://…/main.zip", "subdir": "cms",
 *     "min_version": "1.10.0",   ← desde una versión anterior a esta hay que actualizar a mano
 *     "min_php": "7.4",
 *     "sha256": "…" }            ← opcional; los zips que GitHub genera al vuelo no tienen una firma estable
 *
 * La actualización descarga el zip, extrae cms/ a una carpeta temporal, comprueba que es un núcleo válido y de la
 * versión anunciada, y solo entonces cambia las carpetas de sitio (rename), guardando la anterior para poder volver.
 * Nunca toca data/, uploads/, site/, themes/ ni packs/.
 */
declare(strict_types=1);

const CMS_UPDATE_DEFAULT = 'https://raw.githubusercontent.com/ellaguno/cms_simple/main/version.json';
const CMS_UPDATE_TTL = 3600;

function cms_update_url(): string
{
    $u = (string) cms_config('update_url', CMS_UPDATE_DEFAULT);
    return preg_match('#^https://[^\s"\'<>]+$#i', $u) ? $u : '';
}

function cms_update_cache_file(): string
{
    return CMS_DATA . '/cache/update.json';
}

/**
 * Información de la versión publicada. Con $force descarga; sin él usa la caché y no toca la red
 * (así el panel nunca se queda esperando). Devuelve [info|null, error].
 */
function cms_update_check(bool $force = false): array
{
    $file = cms_update_cache_file();
    if (!$force) {
        $j = is_file($file) ? cms_json_read($file, null) : null;
        return [is_array($j) ? cms_update_info($j) : null, ''];
    }
    $url = cms_update_url();
    if ($url === '') return [null, 'No hay una dirección de actualizaciones configurada.'];
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0775, true);
    [$body, $err] = cms_http_get($url, 262144, 15);
    if ($body === null) {
        $j = is_file($file) ? cms_json_read($file, null) : null;
        return [is_array($j) ? cms_update_info($j) : null, $err];
    }
    $j = json_decode($body, true);
    if (!is_array($j) || empty($j['version'])) return [null, 'El archivo de versión no tiene el formato esperado.'];
    @file_put_contents($file, $body);
    return [cms_update_info($j), ''];
}

/** Normaliza el JSON de versión y calcula el estado frente a esta instalación. */
function cms_update_info(array $j): array
{
    $v = (string) ($j['version'] ?? '');
    $url = (string) ($j['url'] ?? '');
    $minV = (string) ($j['min_version'] ?? '');
    $minPhp = (string) ($j['min_php'] ?? '');
    $info = [
        'version' => $v,
        'date' => (string) ($j['date'] ?? ''),
        'notes' => (string) ($j['notes'] ?? ''),
        'url' => preg_match('#^https://[^\s"\'<>]+\.zip(\?.*)?$#i', $url) ? $url : '',
        'subdir' => trim((string) ($j['subdir'] ?? 'cms'), '/'),
        'sha256' => preg_match('/^[a-f0-9]{64}$/i', (string) ($j['sha256'] ?? '')) ? strtolower((string) $j['sha256']) : '',
        'min_version' => $minV,
        'min_php' => $minPhp,
        'checked' => is_file(cms_update_cache_file()) ? (int) filemtime(cms_update_cache_file()) : 0,
    ];
    $info['newer'] = $v !== '' && version_compare($v, CMS_VERSION, '>');
    $info['too_old'] = $minV !== '' && version_compare(CMS_VERSION, $minV, '<');
    $info['php_low'] = $minPhp !== '' && version_compare(PHP_VERSION, $minPhp, '<');
    return $info;
}

/** ¿Se puede escribir donde hace falta? Devuelve [ok, motivo]. */
function cms_update_writable(): array
{
    foreach ([CMS_ROOT => 'la carpeta del sitio', CMS_DIR => 'la carpeta cms/'] as $dir => $name) {
        $probe = rtrim($dir, '/') . '/.cms-write-test';
        $ok = @file_put_contents($probe, 'x') !== false;
        @unlink($probe);
        if (!$ok) return [false, 'El servidor no puede escribir en ' . $name . ' (' . $dir . '). Actualiza por FTP o pide permisos de escritura.'];
    }
    return [true, ''];
}

/** Copias del núcleo anterior que quedan guardadas, de más reciente a más antigua. */
function cms_update_backups(): array
{
    $out = [];
    foreach (glob(CMS_ROOT . '/.cms-anterior-*', GLOB_ONLYDIR) ?: [] as $d) $out[basename($d)] = ['dir' => $d, 'time' => (int) filemtime($d), 'version' => cms_update_dir_version($d)];
    krsort($out);
    return $out;
}

/** Versión declarada en el bootstrap de una carpeta de núcleo. */
function cms_update_dir_version(string $dir): string
{
    $f = $dir . '/bootstrap.php';
    if (!is_file($f)) return '';
    $s = (string) @file_get_contents($f, false, null, 0, 4096);
    return preg_match("/CMS_VERSION\s*=\s*'([^']+)'/", $s, $m) ? $m[1] : '';
}

/** Borra una carpeta y su contenido (solo dentro del sitio). */
function cms_rmdir(string $dir): bool
{
    if (strpos(realpath($dir) ?: '/', realpath(CMS_ROOT) ?: '//') !== 0) return false;
    if (!is_dir($dir)) return false;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    return @rmdir($dir);
}

/**
 * Aplica la actualización. Devuelve [ok, mensaje]. Tras un cambio con éxito conviene redirigir en seguida:
 * el código que se está ejecutando venía de la carpeta que se acaba de sustituir.
 */
function cms_update_apply(array $info): array
{
    if ($info['url'] === '') return [false, 'El archivo de versión no trae un zip válido.'];
    if (!$info['newer']) return [false, 'Ya tienes la versión ' . CMS_VERSION . '.'];
    if ($info['too_old']) return [false, 'Desde la versión ' . CMS_VERSION . ' hay que actualizar a mano hasta la ' . $info['min_version'] . '.'];
    if ($info['php_low']) return [false, 'Necesita PHP ' . $info['min_php'] . ' o superior; este servidor tiene ' . PHP_VERSION . '.'];
    [$w, $why] = cms_update_writable();
    if (!$w) return [false, $why];

    $lock = CMS_DATA . '/update.lock';
    $fh = @fopen($lock, 'c');
    if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { if ($fh) fclose($fh); return [false, 'Ya hay una actualización en marcha.']; }

    $done = function (bool $ok, string $msg) use ($fh, $lock) { flock($fh, LOCK_UN); fclose($fh); @unlink($lock); return [$ok, $msg]; };

    [$zip, $err] = cms_http_get($info['url'], 67108864, 60);
    if ($zip === null) return $done(false, 'No se pudo descargar: ' . $err);
    if ($info['sha256'] !== '' && hash('sha256', $zip) !== $info['sha256']) return $done(false, 'La descarga no coincide con la firma publicada.');

    $tmpZip = tempnam(sys_get_temp_dir(), 'cmsup') ?: '';
    if ($tmpZip === '' || file_put_contents($tmpZip, $zip) === false) return $done(false, 'No se pudo guardar la descarga.');
    $new = CMS_ROOT . '/.cms-nuevo-' . bin2hex(random_bytes(3));
    [$n, $skipped, $err2] = cms_zip_extract($tmpZip, $info['subdir'], $new, ['bootstrap.php']);
    @unlink($tmpZip);
    if ($err2 !== '') { cms_rmdir($new); return $done(false, $err2); }

    // el paquete tiene que ser un núcleo completo y de la versión anunciada
    foreach (['bootstrap.php', 'router.php', 'lib/storage.php', 'admin/index.php'] as $must) {
        if (!is_file($new . '/' . $must)) { cms_rmdir($new); return $done(false, 'El paquete descargado está incompleto (falta ' . $must . ').'); }
    }
    $got = cms_update_dir_version($new);
    if ($got === '' || version_compare($got, $info['version'], '!=')) { cms_rmdir($new); return $done(false, 'El paquete dice ser la versión ' . ($got ?: '¿?') . ' y se esperaba la ' . $info['version'] . '.'); }
    if (!is_dir($new . '/packs') || !is_dir($new . '/lib')) { cms_rmdir($new); return $done(false, 'El paquete descargado no tiene la estructura esperada.'); }

    // cambio de carpetas: rápido y reversible
    $old = CMS_ROOT . '/.cms-anterior-' . date('Ymd-His');
    if (!@rename(CMS_DIR, $old)) { cms_rmdir($new); return $done(false, 'No se pudo apartar la carpeta cms/ (permisos).'); }
    if (!@rename($new, CMS_DIR)) {
        @rename($old, CMS_DIR);   // vuelta atrás inmediata
        cms_rmdir($new);
        return $done(false, 'No se pudo poner la versión nueva en su sitio; se dejó la anterior.');
    }
    if (function_exists('opcache_reset')) @opcache_reset();
    // se guardan las dos últimas copias
    $backups = array_keys(cms_update_backups());
    foreach (array_slice($backups, 2) as $b) cms_rmdir(CMS_ROOT . '/' . $b);
    return $done(true, 'Actualizado a la versión ' . $info['version'] . ' (' . $n . ' archivos' . ($skipped ? ', ' . $skipped . ' omitidos' : '') . '). La copia anterior queda en ' . basename($old) . '.');
}

/** Vuelve a una copia guardada del núcleo. */
function cms_update_rollback(string $name): array
{
    if (!preg_match('/^\.cms-anterior-[0-9-]+$/', $name)) return [false, 'Copia desconocida.'];
    $dir = CMS_ROOT . '/' . $name;
    if (!is_dir($dir) || !is_file($dir . '/bootstrap.php')) return [false, 'Esa copia ya no está.'];
    [$w, $why] = cms_update_writable();
    if (!$w) return [false, $why];
    $side = CMS_ROOT . '/.cms-cambio-' . bin2hex(random_bytes(3));
    if (!@rename(CMS_DIR, $side)) return [false, 'No se pudo apartar la carpeta cms/.'];
    if (!@rename($dir, CMS_DIR)) { @rename($side, CMS_DIR); return [false, 'No se pudo restaurar la copia; se dejó lo que había.']; }
    @rename($side, $dir);   // lo que había pasa a ocupar el sitio de la copia, por si hay que rehacer
    if (function_exists('opcache_reset')) @opcache_reset();
    return [true, 'Se volvió a la versión ' . (cms_update_dir_version(CMS_DIR) ?: '¿?') . '.'];
}
