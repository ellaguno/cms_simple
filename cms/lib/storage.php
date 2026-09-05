<?php
/** cms_simple — almacenamiento JSON: ajustes, textos, menú, contenido por tipo, usuarios. */
declare(strict_types=1);

function cms_json_read(string $file, $default = [])
{
    if (!is_file($file)) return $default;
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function cms_json_write(string $file, $data): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

/* ------------------------------------------------------------------ ajustes y textos */

function cms_settings(bool $reload = false): array
{
    static $s = null;
    if ($s === null || $reload) {
        $s = cms_json_read(CMS_DATA . '/settings.json');
        if (!$s && is_file(CMS_SITE . '/defaults/settings.json')) $s = cms_json_read(CMS_SITE . '/defaults/settings.json');
    }
    return $s;
}

/** Textos fijos: data/strings.json completado con las claves que falten de site/defaults/strings.json (textos nuevos del tema). */
function cms_strings_all(bool $reload = false): array
{
    static $t = null;
    if ($t === null || $reload) {
        $t = cms_json_read(CMS_DATA . '/strings.json');
        if (is_file(CMS_SITE . '/defaults/strings.json')) $t += cms_json_read(CMS_SITE . '/defaults/strings.json');
    }
    return $t;
}

/** Texto de la interfaz en el idioma pedido, con respaldo al idioma predeterminado. */
function cms_t(string $key, string $lang, $default = '')
{
    $all = cms_strings_all();
    if (isset($all[$key][$lang]) && $all[$key][$lang] !== '' && $all[$key][$lang] !== []) return $all[$key][$lang];
    $d = cms_default_lang();
    if (isset($all[$key][$d]) && $all[$key][$d] !== '' && $all[$key][$d] !== []) return $all[$key][$d];
    return $default;
}

function cms_menu(string $lang): array
{
    $m = cms_json_read(CMS_DATA . '/menu.json');
    if (!$m && is_file(CMS_SITE . '/defaults/menu.json')) $m = cms_json_read(CMS_SITE . '/defaults/menu.json');
    return $m[$lang] ?? ($m[cms_default_lang()] ?? []);
}

/* ------------------------------------------------------------------ contenido por tipo */

function cms_type(string $type): ?array
{
    $types = cms_config('types');
    return isset($types[$type]) ? $types[$type] + ['key' => $type] : null;
}

function cms_content_dir(string $type): string
{
    return CMS_DATA . '/content/' . preg_replace('/[^a-z0-9_-]/i', '', $type);
}

/** Todos los elementos de un tipo (publicados por defecto), ordenados según el esquema. */
function cms_items(string $type, bool $published_only = true): array
{
    $cache = &$GLOBALS['cms_items_cache'];
    if (!is_array($cache)) $cache = [];
    $k = $type . ($published_only ? ':pub' : ':all');
    if (isset($cache[$k])) return $cache[$k];
    $def = cms_type($type);
    $items = [];
    foreach (glob(cms_content_dir($type) . '/*.json') ?: [] as $f) {
        $it = cms_json_read($f, null);
        if (is_array($it) && !empty($it['slug'])) $items[$it['slug']] = $it;
    }
    // elementos en memoria (vista previa del constructor, sin guardar)
    foreach ((array) ($GLOBALS['cms_item_override'][$type] ?? []) as $sl => $it) $items[$sl] = $it;
    if ($published_only) $items = array_filter($items, 'cms_item_is_live');
    $sort = $def['sort'] ?? ['field' => 'date', 'dir' => 'desc'];
    $field = $sort['field'] ?? 'date';
    $dir = ($sort['dir'] ?? 'desc') === 'asc' ? 1 : -1;
    uasort($items, function ($a, $b) use ($field, $dir) {
        $va = $a[$field] ?? ''; $vb = $b[$field] ?? '';
        if (is_numeric($va) && is_numeric($vb)) return ($va <=> $vb) * $dir;
        return strcmp((string) $va, (string) $vb) * $dir;
    });
    return $cache[$k] = $items;
}

/** Vacía la caché de elementos (tras guardar o al inyectar un elemento en memoria). */
function cms_items_flush(): void
{
    $GLOBALS['cms_items_cache'] = [];
}

function cms_item(string $type, string $slug, bool $published_only = true): ?array
{
    return cms_items($type, $published_only)[$slug] ?? null;
}

/** Publicado y, si tiene fecha de publicación programada, ya alcanzada. */
function cms_item_is_live(array $it): bool
{
    if (($it['status'] ?? 'draft') !== 'published') return false;
    $at = (string) ($it['publish_at'] ?? '');
    return $at === '' || $at <= date('Y-m-d');
}

/** Guarda el elemento; la versión anterior queda en data/versions/<tipo>/<slug>/ (se conservan las últimas 10). */
function cms_item_save(string $type, array $item): bool
{
    $file = cms_content_dir($type) . '/' . $item['slug'] . '.json';
    if (is_file($file)) {
        $old = (string) file_get_contents($file);
        $new = json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($old !== $new && $old !== '') {
            $dir = cms_versions_dir($type, $item['slug']);
            if (is_dir($dir) || mkdir($dir, 0755, true)) {
                $vf = $dir . '/' . date('Ymd-His'); $n = 1;
                while (is_file($vf . ($n > 1 ? '-' . $n : '') . '.json')) $n++;
                file_put_contents($vf . ($n > 1 ? '-' . $n : '') . '.json', $old);
                $vs = glob($dir . '/*.json') ?: [];
                sort($vs);
                foreach (array_slice($vs, 0, max(0, count($vs) - 10)) as $v) @unlink($v);
            }
        }
    }
    $ok = cms_json_write($file, $item);
    cms_items_flush();
    return $ok;
}

function cms_versions_dir(string $type, string $slug): string
{
    return CMS_DATA . '/versions/' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '/' . cms_slugify($slug);
}

/** Versiones guardadas de un elemento: [['file' => ruta, 'when' => 'AAAA-MM-DD HH:MM:SS', 'title' => …], …] de la más reciente a la más antigua. */
function cms_item_versions(string $type, string $slug): array
{
    $out = [];
    foreach (glob(cms_versions_dir($type, $slug) . '/*.json') ?: [] as $f) {
        $b = basename($f, '.json');
        $d = json_decode((string) file_get_contents($f), true);
        $out[] = ['file' => $f, 'name' => $b, 'when' => preg_replace('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/', '$1-$2-$3 $4:$5:$6', $b),
            'title' => is_array($d) ? (string) (is_array($d['title'] ?? null) ? reset($d['title']) : ($d['title'] ?? '')) : '', 'status' => is_array($d) ? ($d['status'] ?? '') : ''];
    }
    return array_reverse($out);
}

/** Secreto de la instalación (data/.secret), para tokens de vista previa. */
function cms_secret(): string
{
    $f = CMS_DATA . '/.secret';
    if (is_file($f)) return trim((string) file_get_contents($f));
    $s = bin2hex(random_bytes(24));
    @file_put_contents($f, $s);
    return $s;
}

function cms_preview_token(string $type, string $slug): string
{
    return substr(hash_hmac('sha256', $type . '/' . $slug, cms_secret()), 0, 24);
}

/** URL pública de un elemento; si no está visible, con el token de vista previa (borradores y programados). */
function cms_item_url(string $type, array $item, string $lang): string
{
    $u = cms_url('item:' . $type, $lang, $item['slug']);
    return cms_item_is_live($item) ? $u : $u . '?preview=' . cms_preview_token($type, $item['slug']);
}

function cms_item_delete(string $type, string $slug): bool
{
    $f = cms_content_dir($type) . '/' . cms_slugify($slug) . '.json';
    return is_file($f) && unlink($f);
}

/** Campo bilingüe: $item[$field][$lang] con respaldo al idioma predeterminado. */
function cms_f(array $item, string $field, string $lang, $default = '')
{
    $v = $item[$field] ?? null;
    $d = cms_default_lang();
    if (is_array($v) && (array_key_exists($lang, $v) || array_key_exists($d, $v)) && !isset($v[0])) {
        $x = $v[$lang] ?? null;
        if ($x !== null && $x !== '' && $x !== []) return $x;
        $y = $v[$d] ?? null;
        return ($y !== null && $y !== '' && $y !== []) ? $y : $default;
    }
    return $v ?? $default;
}

/* ------------------------------------------------------------------ tipos en árbol ('tree' => true: elementos con 'parent' y ruta completa 'path') */

/** Ruta completa de un elemento de un tipo en árbol (padre/…/slug), calculada a partir de 'parent'. */
function cms_tree_path(string $type, array $items, string $slug, int $depth = 0): string
{
    $it = $items[$slug] ?? null;
    if (!$it) return $slug;
    $parent = (string) ($it['parent'] ?? '');
    if ($parent === '' || $parent === $slug || $depth > 20 || !isset($items[$parent])) return $slug;
    return cms_tree_path($type, $items, $parent, $depth + 1) . '/' . $slug;
}

/** Recalcula y guarda 'path' en todos los elementos del tipo cuyo valor haya cambiado (tras renombrar o mover). */
function cms_tree_rebuild(string $type): void
{
    $items = [];
    foreach (glob(cms_content_dir($type) . '/*.json') ?: [] as $f) { $it = cms_json_read($f, null); if (is_array($it) && !empty($it['slug'])) $items[$it['slug']] = $it; }
    foreach ($items as $slug => $it) {
        $path = cms_tree_path($type, $items, $slug);
        if (($it['path'] ?? '') !== $path) { $it['path'] = $path; cms_json_write(cms_content_dir($type) . '/' . $slug . '.json', $it); }
    }
}

/** Elemento de un tipo en árbol por su ruta completa. */
function cms_tree_item(string $type, string $path, bool $published_only = true): ?array
{
    foreach (cms_items($type, $published_only) as $it) if (($it['path'] ?? $it['slug']) === $path) return $it;
    return null;
}

/** Ancestros de un elemento (del más lejano al padre directo). */
function cms_tree_ancestors(string $type, array $item, bool $published_only = true): array
{
    $items = cms_items($type, $published_only);
    $out = []; $p = (string) ($item['parent'] ?? ''); $n = 0;
    while ($p !== '' && isset($items[$p]) && $n++ < 20) { array_unshift($out, $items[$p]); $p = (string) ($items[$p]['parent'] ?? ''); }
    return $out;
}

/** Hijos directos publicados de un elemento (o de la raíz si $slug = ''), en el orden del tipo. */
function cms_tree_children(string $type, string $slug = ''): array
{
    return array_values(array_filter(cms_items($type), fn($i) => (string) ($i['parent'] ?? '') === $slug));
}

/** Primeros segmentos de URL que no puede usar una página de árbol en la raíz. */
function cms_reserved_segments(): array
{
    $r = ['admin', 'cms', 'site', 'data', 'uploads', 'api', 'index.php', 'sitemap.xml', 'robots.txt', 'llms.txt', '_cms'];
    foreach (cms_config('types') as $k => $d) foreach ((array) ($d['routes'] ?? [$k]) as $sg) if ($sg !== '') $r[] = $sg;
    foreach (cms_config('pages') as $k => $d) foreach ((array) ($d['routes'] ?? [$k]) as $sg) if ($sg !== '') $r[] = $sg;
    foreach (cms_langs() as $l) $r[] = $l;
    if (function_exists('cms_static_dirs')) foreach (cms_static_dirs() as $d) $r[] = $d;
    return array_values(array_unique($r));
}

/* ------------------------------------------------------------------ usuarios */

function cms_users(): array
{
    return cms_json_read(CMS_DATA . '/users.json', []);
}

/* ------------------------------------------------------------------ utilidades */

function cms_slugify(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string) $s, '-');
}

/** "una por línea" → array. */
function cms_lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)), fn($l) => $l !== ''));
}
