<?php
/**
 * cms_simple — catálogo remoto de temas y paquetes.
 *
 * Un catálogo es un JSON accesible por HTTPS:
 *   {
 *     "name": "Catálogo cms_simple",
 *     "items": [
 *       { "kind": "theme"|"pack", "key": "lienzo", "label": "Lienzo", "desc": "…", "version": "1.0.0",
 *         "author": "…", "license": "MIT", "screenshot": "https://…/x.jpg", "tags": ["blog"],
 *         "url": "https://…/algo.zip",   ← el zip a descargar
 *         "subdir": "starters/lienzo",   ← (opcional) carpeta dentro del zip que contiene el tema o el paquete
 *         "sha256": "…",                 ← (opcional) para comprobar la descarga
 *         "requires": { "cms": "1.20.0" } }
 *     ]
 *   }
 * Las URLs de catálogo salen de config 'registries' o de Ajustes ('registries', una por línea). El resultado se
 * guarda en data/cache/ unas horas. Instalar descarga el zip, comprueba lo que pueda y extrae en themes/<clave>
 * o en packs/<clave>, con el mismo filtro de rutas y extensiones que la instalación manual.
 */
declare(strict_types=1);

const CMS_REGISTRY_DEFAULT = 'https://raw.githubusercontent.com/ellaguno/cms_simple/main/catalog.json';
const CMS_REGISTRY_TTL = 21600;   // 6 horas

/**
 * Deja en una carpeta el .htaccess que impide servir por HTTP el PHP y el JSON de temas y paquetes.
 * Se llama al instalar, porque un sitio que solo copió cms/ no tiene todavía themes/ ni packs/.
 */
function cms_protect_dir(string $dir): void
{
    $f = $dir . '/.htaccess';
    if (is_file($f) || !is_dir($dir)) return;
    @file_put_contents($f, "# Generado por cms_simple: el PHP y el JSON de temas y paquetes no se sirven por HTTP; sus assets sí.\n"
        . "<FilesMatch \"\\.(php|json)$\">\n"
        . "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
        . "  <IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n  </IfModule>\n"
        . "</FilesMatch>\n");
}

/** Extensiones que se admiten dentro de un tema, un paquete o el núcleo. */
const CMS_ZIP_EXT = '/\\.(php|json|css|js|md|html|txt|svg|png|jpe?g|webp|gif|ico|woff2?|mp4|webm)$/i';

/**
 * Extrae de un zip la carpeta $subdir (o su raíz) dentro de $dst, saltando rutas peligrosas y archivos ajenos.
 * $marker es un archivo que debe existir para dar el zip por bueno. Devuelve [copiados, omitidos, error].
 */
function cms_zip_extract(string $zipFile, string $subdir, string $dst, array $markers): array
{
    if (!class_exists('ZipArchive')) return [0, 0, 'Este servidor no tiene la extensión Zip de PHP.'];
    $z = new ZipArchive();
    if ($z->open($zipFile) !== true) return [0, 0, 'El archivo no es un zip válido.'];
    // raíz: la carpeta única que envuelve el zip (la que crea GitHub), más el subdirectorio pedido
    $root = '';
    $first = (string) ($z->getNameIndex(0) ?: '');
    if (strpos($first, '/') !== false) {
        $cand = explode('/', $first)[0] . '/';
        $all = true;
        for ($i = 0; $i < $z->numFiles; $i++) if (strpos((string) $z->getNameIndex($i), $cand) !== 0) { $all = false; break; }
        if ($all) $root = $cand;
    }
    if ($subdir !== '') {
        $want = $root . trim($subdir, '/') . '/';
        $found = false;
        for ($i = 0; $i < $z->numFiles; $i++) if (strpos((string) $z->getNameIndex($i), $want) === 0) { $found = true; break; }
        if (!$found) { $z->close(); return [0, 0, 'El zip no contiene la carpeta ' . $subdir . '.']; }
        $root = $want;
    }
    if ($markers) {
        $ok = false;
        for ($i = 0; $i < $z->numFiles && !$ok; $i++) {
            $n = (string) $z->getNameIndex($i);
            if (strpos($n, $root) !== 0) continue;
            if (in_array(substr($n, strlen($root)), $markers, true)) $ok = true;
        }
        if (!$ok) { $z->close(); return [0, 0, 'El contenido del zip no es el esperado (falta ' . implode(' o ', $markers) . ').']; }
    }
    if (!is_dir($dst) && !@mkdir($dst, 0775, true)) { $z->close(); return [0, 0, 'No se pudo crear ' . basename($dst) . ' (permisos).']; }
    $n = 0; $skipped = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $entry = (string) $z->getNameIndex($i);
        if (strpos($entry, $root) !== 0) continue;
        $rel = substr($entry, strlen($root));
        if ($rel === '' || strpos($rel, '..') !== false || $rel[0] === '/' || strpos($rel, "\0") !== false) { $skipped++; continue; }
        if (substr($rel, -1) === '/') { @mkdir($dst . '/' . $rel, 0775, true); continue; }
        if (!preg_match(CMS_ZIP_EXT, $rel) && basename($rel) !== '.htaccess') { $skipped++; continue; }
        @mkdir(dirname($dst . '/' . $rel), 0775, true);
        $src = $z->getStream($entry);
        if (!$src) { $skipped++; continue; }
        $out = @fopen($dst . '/' . $rel, 'wb');
        if ($out) { stream_copy_to_stream($src, $out); fclose($out); $n++; } else $skipped++;
        fclose($src);
    }
    $z->close();
    return [$n, $skipped, ''];
}

/** URLs de catálogo activas. */
function cms_registries(): array
{
    $urls = (array) cms_config('registries', [CMS_REGISTRY_DEFAULT]);
    foreach (preg_split('/\s+/', (string) (cms_settings()['registries'] ?? '')) ?: [] as $u) if (trim($u) !== '') $urls[] = trim($u);
    if (cms_config('registry_default', true) === false) $urls = array_diff($urls, [CMS_REGISTRY_DEFAULT]);
    $out = [];
    foreach ($urls as $u) { $u = trim((string) $u); if (preg_match('#^https://[^\s"\'<>]+$#i', $u)) $out[$u] = true; }
    return array_keys($out);
}

/** Descarga una URL con límite de tamaño y tiempo. Devuelve [contenido|null, error]. */
function cms_http_get(string $url, int $maxBytes = 33554432, int $timeout = 25): array
{
    if (!preg_match('#^https://#i', $url)) return [null, 'Solo se admiten direcciones https.'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'cms_simple/' . CMS_VERSION,
            CURLOPT_PROGRESSFUNCTION => function ($r, $dn, $db) use ($maxBytes) { return $dn > $maxBytes ? 1 : 0; },
            CURLOPT_NOPROGRESS => false,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) return [null, $err !== '' ? $err : 'No se pudo descargar.'];
        if ($code >= 400) return [null, 'El servidor respondió ' . $code . '.'];
        return [(string) $body, ''];
    }
    if (!ini_get('allow_url_fopen')) return [null, 'Este servidor no puede descargar (sin cURL ni allow_url_fopen).'];
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'user_agent' => 'cms_simple/' . CMS_VERSION]]);
    $body = @file_get_contents($url, false, $ctx, 0, $maxBytes);
    return $body === false ? [null, 'No se pudo descargar.'] : [(string) $body, ''];
}

/** Lee un catálogo, con caché en data/cache. Devuelve [items, error]. */
function cms_registry_fetch(string $url, bool $force = false): array
{
    $dir = CMS_DATA . '/cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/registry-' . substr(sha1($url), 0, 16) . '.json';
    if (!$force && is_file($file) && time() - (int) filemtime($file) < CMS_REGISTRY_TTL) {
        $j = cms_json_read($file, null);
        if (is_array($j)) return [(array) ($j['items'] ?? []), ''];
    }
    [$body, $err] = cms_http_get($url, 2097152, 15);
    if ($body === null) {
        $j = is_file($file) ? cms_json_read($file, null) : null;   // si falla la red, se usa lo último que se descargó
        return [is_array($j) ? (array) ($j['items'] ?? []) : [], $err];
    }
    $j = json_decode($body, true);
    if (!is_array($j) || !isset($j['items']) || !is_array($j['items'])) return [[], 'El catálogo no tiene el formato esperado.'];
    @file_put_contents($file, $body);
    return [$j['items'], ''];
}

/** Un ítem del catálogo, saneado. */
function cms_registry_clean(array $it, string $from): ?array
{
    $kind = ($it['kind'] ?? '') === 'pack' ? 'pack' : 'theme';
    $key = (string) ($it['key'] ?? '');
    $url = (string) ($it['url'] ?? '');
    if (!preg_match('/^[a-z0-9_-]+$/i', $key) || !preg_match('#^https://[^\s"\'<>]+\.zip(\?.*)?$#i', $url)) return null;
    $shot = (string) ($it['screenshot'] ?? '');
    return [
        'kind' => $kind, 'key' => $key, 'url' => $url, 'from' => $from,
        'label' => (string) ($it['label'] ?? ucfirst($key)),
        'desc' => (string) ($it['desc'] ?? ''),
        'version' => (string) ($it['version'] ?? ''),
        'author' => (string) ($it['author'] ?? ''),
        'license' => (string) ($it['license'] ?? ''),
        'screenshot' => preg_match('#^https://#i', $shot) ? $shot : '',
        'subdir' => trim((string) ($it['subdir'] ?? ''), '/'),
        'sha256' => preg_match('/^[a-f0-9]{64}$/i', (string) ($it['sha256'] ?? '')) ? strtolower((string) $it['sha256']) : '',
        'requires' => (array) ($it['requires'] ?? []),
        'tags' => array_values(array_filter(array_map('strval', (array) ($it['tags'] ?? [])))),
    ];
}

/** Todo el catálogo (de todas las URLs), con el estado de cada ítem. Devuelve [items, errores]. */
function cms_registry_items(bool $force = false): array
{
    $items = []; $errors = [];
    foreach (cms_registries() as $url) {
        [$raw, $err] = cms_registry_fetch($url, $force);
        if ($err !== '') $errors[$url] = $err;
        foreach ($raw as $it) {
            if (!is_array($it)) continue;
            $c = cms_registry_clean($it, $url);
            if (!$c) continue;
            $id = $c['kind'] . ':' . $c['key'];
            if (!isset($items[$id])) $items[$id] = $c;
        }
    }
    $themes = cms_themes();
    $packs = cms_packs_available();
    foreach ($items as $id => &$it) {
        $local = $it['kind'] === 'theme' ? ($themes[$it['key']] ?? null) : ($packs[$it['key']] ?? null);
        $it['installed'] = $local !== null;
        $it['local_version'] = (string) ($local['version'] ?? '');
        $it['core'] = (bool) ($local['core'] ?? false);
        $it['update'] = $it['installed'] && $it['version'] !== '' && $it['local_version'] !== '' && version_compare($it['version'], $it['local_version'], '>');
        $need = (string) ($it['requires']['cms'] ?? '');
        $it['too_old'] = $need !== '' && version_compare(CMS_VERSION, $need, '<');
    }
    unset($it);
    return [$items, $errors];
}

/**
 * Instala (o actualiza) un ítem del catálogo. Devuelve [ok, mensaje].
 * Los archivos van a themes/<clave> o packs/<clave>; se admite 'subdir' para zips que traen todo un repositorio.
 */
function cms_registry_install(array $it): array
{
    if (!class_exists('ZipArchive')) return [false, 'Este servidor no tiene la extensión Zip de PHP.'];
    if (!empty($it['too_old'])) return [false, 'Necesita cms_simple ' . (string) ($it['requires']['cms'] ?? '') . ' o superior.'];
    if (!empty($it['core'])) return [false, 'Ese paquete viene con el núcleo: se actualiza al actualizar cms/.'];
    [$zipBody, $err] = cms_http_get($it['url'], 33554432, 40);
    if ($zipBody === null) return [false, 'No se pudo descargar: ' . $err];
    if ($it['sha256'] !== '' && hash('sha256', $zipBody) !== $it['sha256']) return [false, 'La descarga no coincide con la firma del catálogo.'];

    $tmp = tempnam(sys_get_temp_dir(), 'cmsreg') ?: '';
    if ($tmp === '' || file_put_contents($tmp, $zipBody) === false) return [false, 'No se pudo guardar la descarga.'];

    $base = $it['kind'] === 'pack' ? CMS_ROOT . '/packs' : CMS_THEMES;
    $dst = $base . '/' . $it['key'];
    if (!is_dir($base) && !@mkdir($base, 0775, true)) { @unlink($tmp); return [false, 'No se pudo crear la carpeta ' . basename($base) . '/ (permisos).']; }
    cms_protect_dir($base);
    $markers = $it['kind'] === 'pack' ? ['pack.php'] : ['config.php', 'theme.json'];
    [$n, $skipped, $err2] = cms_zip_extract($tmp, $it['subdir'], $dst, $markers);
    @unlink($tmp);
    if ($err2 !== '') return [false, $err2];
    cms_protect_dir($dst);
    if ($n === 0) return [false, 'No se copió ningún archivo (revisa los permisos de la carpeta).'];
    return [true, $n . ' archivos instalados en ' . basename($base) . '/' . $it['key'] . ($skipped ? ' (' . $skipped . ' omitidos)' : '') . '.'];
}
