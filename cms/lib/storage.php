<?php
/** cms_simple — almacenamiento JSON: ajustes, textos, menú, contenido por tipo, usuarios. */
declare(strict_types=1);

function cms_json_read(string $file, $default = [])
{
    if (!is_file($file)) return $default;
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : $default;
}

function cms_json_write(string $file, $data, bool $pretty = true): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return false;
    $json = json_encode($data, ($pretty ? JSON_PRETTY_PRINT : 0) | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    if (!isset($types[$type])) return null;
    $def = $types[$type] + ['key' => $type];
    // categorías (1.30): el campo declarado en 'categories' se edita con un selector del registro (cms/lib/categories.php)
    if (($cf = cms_categories_field($type)) !== null) {
        $def['categories'] = ['field' => $cf];
        $def['fields'][$cf] = ['type' => 'category', '_type' => $type] + array_diff_key((array) $def['fields'][$cf], ['type' => 1]);
    }
    // campos que añaden los paquetes activos: 'item_fields' => ['*' => [campo => def]] (todos los tipos) o [tipo => [...]]
    foreach (cms_packs() as $p) foreach ((array) ($p['item_fields'] ?? []) as $for => $fields) {
        if ($for !== '*' && $for !== $type) continue;
        foreach ((array) $fields as $k => $fd) if (!isset($def['fields'][$k])) $def['fields'][$k] = (array) $fd + ['sidebar' => true];
    }
    return $def;
}

/** Lo que se está dibujando en esta petición: 'type', 'item', 'page', 'lang' (lo fija el enrutador; vacío en el panel). */
function cms_current(): array
{
    return (array) ($GLOBALS['cms_current'] ?? []) + ['type' => null, 'item' => null, 'page' => [], 'lang' => (string) ($GLOBALS['cms_render_lang'] ?? cms_default_lang())];
}

function cms_content_dir(string $type): string
{
    return CMS_DATA . '/content/' . preg_replace('/[^a-z0-9_-]/i', '', $type);
}

/* ------------------------------------------------------------------ índice ligero por tipo (data/index/<tipo>.json)
 * Leer todos los JSON de un tipo (cuerpo y secciones incluidos) en cada petición no escala: con 5,000 artículos son
 * ~180 ms y 37 MB por página. El índice guarda de cada elemento solo los campos ligeros (todo menos los de tipo
 * html, sections y code, salvo que el campo diga 'index' => true) y se reescribe al guardar o borrar desde el panel.
 * Si alguien toca data/content a mano (importadores, rsync), la firma (cantidad de archivos + fecha del más nuevo)
 * deja de coincidir y el índice se reconstruye solo en la siguiente lectura.
 */

function cms_index_file(string $type): string
{
    return CMS_DATA . '/index/' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '.json';
}

/** Archivos de contenido de un tipo (sin el índice ni temporales). */
function cms_content_files(string $type): array
{
    return glob(cms_content_dir($type) . '/*.json') ?: [];
}

/** Firma de la carpeta del tipo: cambia si se añade, borra o reescribe cualquier archivo. */
function cms_index_signature(string $type): string
{
    $files = cms_content_files($type);
    $max = 0;
    foreach ($files as $f) { $m = (int) @filemtime($f); if ($m > $max) $max = $m; }
    // si el esquema cambia qué campos son pesados ('index' => true en site/config.php), el índice se rehace solo
    return count($files) . ':' . $max . ':' . (int) @filemtime(cms_content_dir($type)) . ':' . substr(md5(implode(',', cms_index_heavy_fields($type))), 0, 8);
}

/** Campos que NO entran al índice: los de tipo html, sections y code (salvo 'index' => true) y los pesados sin esquema. */
function cms_index_heavy_fields(string $type): array
{
    $def = cms_type($type);
    $heavy = ['sections' => true, 'body' => true, 'content' => true, 'html' => true];
    foreach ((array) ($def['fields'] ?? []) as $name => $fd) {
        $fd = (array) $fd;
        if (isset($fd['index'])) { if ($fd['index']) unset($heavy[$name]); else $heavy[$name] = true; continue; }
        if (in_array($fd['type'] ?? 'text', ['html', 'sections', 'code'], true)) $heavy[$name] = true;
        else unset($heavy[$name]);
    }
    return array_keys($heavy);
}

/** Versión ligera de un elemento para el índice. */
function cms_index_entry(string $type, array $it, ?array $heavy = null): array
{
    $heavy = $heavy ?? cms_index_heavy_fields($type);
    foreach ($heavy as $f) unset($it[$f]);
    return $it;
}

/** Vuelve a leer todos los archivos del tipo y escribe el índice. Devuelve los elementos ligeros (slug => elemento). */
function cms_index_rebuild(string $type): array
{
    $heavy = cms_index_heavy_fields($type);
    $items = [];
    foreach (cms_content_files($type) as $f) {
        $it = cms_json_read($f, null);
        if (is_array($it) && !empty($it['slug'])) $items[$it['slug']] = cms_index_entry($type, $it, $heavy);
    }
    cms_json_write(cms_index_file($type), ['signature' => cms_index_signature($type), 'built' => date('Y-m-d H:i:s'), 'items' => $items], false);   // compacto: se lee en cada petición
    unset($GLOBALS['cms_index_cache'][$type]);
    return $items;
}

/** Actualiza (o quita, con $item = null) una entrada del índice sin releer los demás archivos. */
function cms_index_touch(string $type, string $slug, ?array $item): void
{
    $file = cms_index_file($type);
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
    $lock = @fopen(dirname($file) . '/.lock', 'c');   // dos guardados a la vez no se pisan la entrada del otro
    if ($lock) flock($lock, LOCK_EX);
    $idx = cms_json_read($file, null);
    if (!is_array($idx) || !isset($idx['items']) || !is_array($idx['items'])) {
        cms_index_rebuild($type);
    } else {
        if ($item === null) unset($idx['items'][$slug]);
        else $idx['items'][$slug] = cms_index_entry($type, $item);
        $idx['signature'] = cms_index_signature($type);
        $idx['built'] = date('Y-m-d H:i:s');
        cms_json_write($file, $idx, false);
    }
    if ($lock) { flock($lock, LOCK_UN); fclose($lock); }
    unset($GLOBALS['cms_index_cache'][$type]);
}

/**
 * Índice ligero de un tipo: slug => elemento sin campos pesados, TODOS los estados y sin localizar ni ordenar
 * (lo hace cms_items). Se reconstruye si falta o si la carpeta cambió por fuera del panel.
 */
function cms_index(string $type): array
{
    $cache = &$GLOBALS['cms_index_cache'];
    if (!is_array($cache)) $cache = [];
    if (isset($cache[$type])) return $cache[$type];
    $idx = cms_json_read(cms_index_file($type), null);
    $items = is_array($idx) && isset($idx['items']) && is_array($idx['items']) && ($idx['signature'] ?? '') === cms_index_signature($type)
        ? $idx['items'] : cms_index_rebuild($type);
    return $cache[$type] = $items;
}

/** Borra los índices (se regeneran al vuelo). Útil tras importar contenido a mano. */
function cms_index_flush(): void
{
    foreach (glob(CMS_DATA . '/index/*.json') ?: [] as $f) @unlink($f);
    $GLOBALS['cms_index_cache'] = [];
    cms_items_flush();
}

/**
 * Elementos de un tipo (publicados por defecto), ordenados según el esquema, con los campos bilingües resueltos al
 * idioma que se dibuja. Por defecto salen del índice ligero, SIN los campos pesados (html, sections, code): es lo que
 * necesita un listado, un menú o el sitemap. Con $full = true se leen los archivos completos (buscar en el cuerpo,
 * exportar); para un solo elemento usa cms_item(), que lee únicamente su archivo.
 */
function cms_items(string $type, bool $published_only = true, bool $full = false): array
{
    $cache = &$GLOBALS['cms_items_cache'];
    if (!is_array($cache)) $cache = [];
    $k = $type . ($published_only ? ':pub' : ':all') . ($full ? ':full' : '');
    if (isset($cache[$k])) return $cache[$k];
    $def = cms_type($type);
    if ($full || !empty($def['no_index'])) {
        $items = [];
        foreach (cms_content_files($type) as $f) {
            $it = cms_json_read($f, null);
            if (is_array($it) && !empty($it['slug'])) $items[$it['slug']] = $it;
        }
    } else {
        $items = cms_index($type);
    }
    // elementos en memoria (vista previa del constructor, sin guardar)
    foreach ((array) ($GLOBALS['cms_item_override'][$type] ?? []) as $sl => $it) $items[$sl] = $it;
    if ($published_only) $items = array_filter($items, 'cms_item_is_live');
    // al dibujar el sitio, los campos bilingües llegan resueltos al idioma de la petición (las plantillas usan $item['title'] sin más)
    if (!empty($GLOBALS['cms_render_lang'])) foreach ($items as $sl => $it) $items[$sl] = cms_localize($it, (string) $GLOBALS['cms_render_lang']);
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

/** Vacía la caché en memoria de elementos e índices (tras guardar o al inyectar un elemento en memoria). */
function cms_items_flush(): void
{
    $GLOBALS['cms_items_cache'] = [];
    $GLOBALS['cms_index_cache'] = [];
}

/** Un elemento completo (con cuerpo y secciones) leyendo solo su archivo; null si no existe o no está publicado. */
function cms_item(string $type, string $slug, bool $published_only = true): ?array
{
    $slug = (string) preg_replace('/[^a-z0-9_.-]/i', '', $slug);
    if ($slug === '' || $slug === '.' || $slug === '..') return null;
    $it = $GLOBALS['cms_item_override'][$type][$slug] ?? null;
    if ($it === null) {
        $it = cms_json_read(cms_content_dir($type) . '/' . $slug . '.json', null);
        if (!is_array($it) || empty($it['slug'])) return null;
    }
    if (!empty($GLOBALS['cms_render_lang'])) $it = cms_localize($it, (string) $GLOBALS['cms_render_lang']);
    if ($published_only && !cms_item_is_live($it)) return null;
    return $it;
}

/** Publicado, con la fecha de publicación programada (si la hay) ya alcanzada y la de retiro (si la hay) aún no. */
function cms_item_is_live(array $it): bool
{
    if (($it['status'] ?? 'draft') !== 'published') return false;
    $today = date('Y-m-d');
    $at = (string) ($it['publish_at'] ?? '');
    if ($at !== '' && $at > $today) return false;
    $to = (string) ($it['unpublish_at'] ?? '');
    return $to === '' || $to > $today;
}

/** Publicado pero ya retirado por su fecha de caducidad. */
function cms_item_is_expired(array $it): bool
{
    $to = (string) ($it['unpublish_at'] ?? '');
    return ($it['status'] ?? 'draft') === 'published' && $to !== '' && $to <= date('Y-m-d');
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
    if ($ok) cms_index_touch($type, (string) $item['slug'], $item);
    cms_items_flush();
    if ($ok) cms_do('item.save', $type, $item);
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

/** Token del cron del hosting (derivado del secreto de la instalación) y la URL que se programa: /_cms/cron?token=… */
function cms_cron_token(): string
{
    return substr(hash_hmac('sha256', 'cron', cms_secret()), 0, 32);
}

function cms_cron_url(): string
{
    return cms_site_url() . '/_cms/cron?token=' . cms_cron_token();
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
    $ok = is_file($f) && unlink($f);
    if ($ok) cms_index_touch($type, cms_slugify($slug), null);
    cms_items_flush();
    return $ok;
}

/** ¿Es un valor por idioma? (arreglo cuyas claves son códigos de idioma del sitio) */
function cms_is_i18n_value($v): bool
{
    if (!is_array($v) || !$v || isset($v[0])) return false;
    foreach (array_keys($v) as $k) if (!in_array($k, cms_langs(), true)) return false;
    return true;
}

/** Resuelve recursivamente los valores por idioma de un elemento (o de cualquier arreglo) a un idioma, con respaldo al predeterminado. */
function cms_localize($data, string $lang)
{
    if (!is_array($data)) return $data;
    if (cms_is_i18n_value($data)) {
        $x = $data[$lang] ?? null;
        if ($x !== null && $x !== '' && $x !== []) return $x;
        $y = $data[cms_default_lang()] ?? null;
        return $y ?? '';
    }
    foreach ($data as $k => $v) if (is_array($v)) $data[$k] = cms_localize($v, $lang);
    return $data;
}

/** Idioma que se está dibujando (vacío en el panel). */
function cms_render_lang(): string
{
    return (string) ($GLOBALS['cms_render_lang'] ?? cms_default_lang());
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
    foreach (cms_content_files($type) as $f) { $it = cms_json_read($f, null); if (is_array($it) && !empty($it['slug'])) $items[$it['slug']] = $it; }
    $changed = false;
    foreach ($items as $slug => $it) {
        $path = cms_tree_path($type, $items, $slug);
        if (($it['path'] ?? '') !== $path) { $it['path'] = $path; cms_json_write(cms_content_dir($type) . '/' . $slug . '.json', $it); $changed = true; }
    }
    if ($changed) cms_index_rebuild($type);
    cms_items_flush();
}

/** Elemento de un tipo en árbol por su ruta completa. */
function cms_tree_item(string $type, string $path, bool $published_only = true): ?array
{
    foreach (cms_items($type, $published_only) as $it) if (($it['path'] ?? $it['slug']) === $path) return cms_item($type, (string) $it['slug'], $published_only);
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
