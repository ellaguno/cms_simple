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

function cms_strings_all(bool $reload = false): array
{
    static $t = null;
    if ($t === null || $reload) {
        $t = cms_json_read(CMS_DATA . '/strings.json');
        if (!$t && is_file(CMS_SITE . '/defaults/strings.json')) $t = cms_json_read(CMS_SITE . '/defaults/strings.json');
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
    static $cache = [];
    $k = $type . ($published_only ? ':pub' : ':all');
    if (isset($cache[$k])) return $cache[$k];
    $def = cms_type($type);
    $items = [];
    foreach (glob(cms_content_dir($type) . '/*.json') ?: [] as $f) {
        $it = cms_json_read($f, null);
        if (is_array($it) && !empty($it['slug'])) $items[$it['slug']] = $it;
    }
    if ($published_only) $items = array_filter($items, fn($i) => ($i['status'] ?? 'draft') === 'published');
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

function cms_item(string $type, string $slug, bool $published_only = true): ?array
{
    return cms_items($type, $published_only)[$slug] ?? null;
}

function cms_item_save(string $type, array $item): bool
{
    return cms_json_write(cms_content_dir($type) . '/' . $item['slug'] . '.json', $item);
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
