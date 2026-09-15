<?php
/**
 * cms_simple — categorías por colección (1.30): registro en data/categories/<tipo>.json y subsecciones /coleccion/categoria/.
 *
 * Un tipo lo activa en site/config.php con 'categories' => true (usa el campo 'category') o ['field' => 'categoria'].
 * El elemento sigue guardando la etiqueta en su campo (por idioma si el campo es i18n), así que plantillas, índices y
 * bloques que leen cms_f($item, 'category') no cambian. El registro aporta lo demás: la URL (slug), el orden, la
 * descripción y los textos SEO de cada categoría, y evita duplicados por mayúsculas o erratas porque el editor elige de
 * una lista (Admin → Diseño → Categorías) en vez de escribir texto libre.
 *
 * Entrada del registro: slug => ['label' => [idioma => texto], 'desc' => [...], 'seo_title' => [...], 'seo_desc' => [...], 'order' => n]
 */
declare(strict_types=1);

/** Campo de categoría del tipo, o null si el tipo no usa categorías. */
function cms_categories_field(string $type): ?string
{
    $def = cms_config('types')[$type] ?? null;   // sin cms_type(), que a su vez pregunta aquí
    if (!$def || empty($def['categories'])) return null;
    $c = $def['categories'];
    $f = is_array($c) ? (string) ($c['field'] ?? 'category') : 'category';
    return isset($def['fields'][$f]) ? $f : null;
}

/** Tipos que usan categorías. */
function cms_categories_types(): array
{
    return array_values(array_filter(array_keys((array) cms_config('types')), fn($k) => cms_categories_field($k) !== null));
}

function cms_categories_file(string $type): string
{
    return CMS_DATA . '/categories/' . preg_replace('/[^a-z0-9_-]/i', '', $type) . '.json';
}

/** Registro del tipo, ordenado (order, etiqueta). Si el archivo no existe se construye con lo que ya hay en los elementos. */
function cms_categories(string $type, bool $reload = false): array
{
    if (!$reload && isset($GLOBALS['cms_cat_cache'][$type])) return $GLOBALS['cms_cat_cache'][$type];
    $f = cms_categories_file($type);
    $cats = is_file($f) ? (array) cms_json_read($f, []) : null;
    if ($cats === null) { $cats = []; if (cms_categories_field($type) !== null) { cms_categories_rebuild($type, $cats); cms_categories_save($type, $cats); } }
    return $GLOBALS['cms_cat_cache'][$type] = cms_categories_sort($cats);
}

function cms_categories_sort(array $cats): array
{
    $dl = cms_default_lang();
    uasort($cats, fn($a, $b) => (((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0))) ?: strcasecmp(cms_category_label($a, $dl), cms_category_label($b, $dl)));
    return $cats;
}

function cms_categories_save(string $type, array $cats): bool
{
    $f = cms_categories_file($type);
    if (!is_dir(dirname($f)) && !@mkdir(dirname($f), 0755, true)) return false;
    $ok = cms_json_write($f, $cats);
    unset($GLOBALS['cms_cat_cache'][$type]);
    return $ok;
}

/** Etiqueta de una categoría en un idioma, con respaldo al predeterminado. */
function cms_category_label(array $cat, string $lang): string
{
    return cms_category_text($cat, 'label', $lang);
}

/** Texto bilingüe de una entrada del registro ('label', 'desc', 'seo_title', 'seo_desc') en un idioma, con respaldo. */
function cms_category_text(array $cat, string $field, string $lang): string
{
    $v = $cat[$field] ?? '';
    if (!is_array($v)) return trim((string) $v);
    $x = trim((string) ($v[$lang] ?? ''));
    if ($x !== '') return $x;
    $y = trim((string) ($v[cms_default_lang()] ?? ''));
    if ($y !== '') return $y;
    foreach ($v as $z) if (trim((string) $z) !== '') return trim((string) $z);
    return '';
}

/** Una categoría por su slug (con 'slug' incluido), o null. */
function cms_category(string $type, string $slug): ?array
{
    $slug = cms_slugify($slug);
    $c = cms_categories($type)[$slug] ?? null;
    return $c ? $c + ['slug' => $slug] : null;
}

/**
 * Slug de registro que corresponde a un valor de categoría (etiqueta, o arreglo por idioma) de un elemento.
 * Compara con las etiquetas del registro en cualquier idioma sin distinguir mayúsculas; si no está, slugifica.
 */
function cms_category_slug_of(string $type, $value): string
{
    $vals = is_array($value) ? array_values(array_filter(array_map(fn($v) => trim((string) $v), $value), fn($v) => $v !== '')) : (trim((string) $value) !== '' ? [trim((string) $value)] : []);
    if (!$vals) return '';
    $low = array_map('mb_strtolower', $vals);
    foreach (cms_categories($type) as $slug => $c) foreach ((array) ($c['label'] ?? []) as $lab) if (in_array(mb_strtolower(trim((string) $lab)), $low, true)) return (string) $slug;
    return cms_slugify($vals[0]);
}

/** Slug de la categoría de un elemento ('' si no tiene). El elemento puede venir bilingüe o ya localizado. */
function cms_item_category(string $type, array $item): string
{
    $f = cms_categories_field($type);
    return $f === null ? '' : cms_category_slug_of($type, $item[$f] ?? '');
}

/** Valor que se guarda en el campo del elemento para una categoría: etiquetas por idioma (campo i18n) o texto. */
function cms_category_value(string $type, array $cat)
{
    $f = cms_categories_field($type);
    $i18n = $f !== null && !empty(cms_config('types')[$type]['fields'][$f]['i18n']);
    if (!$i18n) return cms_category_label($cat, cms_default_lang());
    $out = [];
    foreach (cms_langs() as $l) $out[$l] = trim((string) (($cat['label'] ?? [])[$l] ?? ''));
    if ($out[cms_default_lang()] === '') $out[cms_default_lang()] = cms_category_label($cat, cms_default_lang());
    return $out;
}

/** Elementos de una categoría (publicados, salvo que se pida todo), en el orden de la colección. */
function cms_items_in_category(string $type, string $slug, bool $published_only = true): array
{
    $slug = cms_slugify($slug);
    return array_filter(cms_items($type, $published_only), fn($it) => cms_item_category($type, $it) === $slug);
}

/** Conteos por categoría: slug => ['total' => n, 'live' => n] (del índice ligero, sin localizar). */
function cms_categories_counts(string $type): array
{
    $f = cms_categories_field($type);
    $out = [];
    if ($f === null) return $out;
    foreach (cms_index($type) as $it) {
        $s = cms_category_slug_of($type, $it[$f] ?? '');
        if ($s === '') continue;
        $out[$s] = $out[$s] ?? ['total' => 0, 'live' => 0];
        $out[$s]['total']++;
        if (cms_item_is_live($it)) $out[$s]['live']++;
    }
    return $out;
}

/** Añade al registro las categorías que aparecen en los elementos y no están. Devuelve cuántas añadió. */
function cms_categories_rebuild(string $type, array &$cats): int
{
    $f = cms_categories_field($type);
    if ($f === null) return 0;
    $dl = cms_default_lang();
    $added = 0;
    foreach (cms_index($type) as $it) {
        $v = $it[$f] ?? '';
        $labels = is_array($v) ? array_filter(array_map(fn($x) => trim((string) $x), $v), fn($x) => $x !== '') : (trim((string) $v) !== '' ? [$dl => trim((string) $v)] : []);
        if (!$labels) continue;
        $main = $labels[$dl] ?? (string) reset($labels);
        $slug = '';
        foreach ($cats as $s => $c) foreach ((array) ($c['label'] ?? []) as $lab) if (mb_strtolower(trim((string) $lab)) === mb_strtolower($main)) { $slug = (string) $s; break 2; }
        if ($slug === '') {
            $slug = cms_slugify($main);
            if ($slug === '') continue;
            if (!isset($cats[$slug])) { $cats[$slug] = ['label' => $labels + [$dl => $main], 'desc' => [], 'seo_title' => [], 'seo_desc' => [], 'order' => count($cats) + 1]; $added++; continue; }
        }
        foreach ($labels as $l => $lab) if (empty($cats[$slug]['label'][$l])) $cats[$slug]['label'][$l] = $lab;   // completa traducciones vistas en los elementos
    }
    return $added;
}

/** Crea una categoría a partir de una etiqueta (o devuelve la existente con esa etiqueta). Devuelve el slug ('' si no se pudo). */
function cms_category_create(string $type, string $label, ?string $lang = null): string
{
    $label = trim($label);
    if ($label === '' || cms_categories_field($type) === null) return '';
    $existing = cms_category_slug_of($type, $label);
    if ($existing !== '' && cms_category($type, $existing)) return $existing;
    $cats = cms_categories($type);
    $slug = cms_slugify($label);
    if ($slug === '') return '';
    $base = $slug; $n = 2;
    while (isset($cats[$slug])) $slug = $base . '-' . $n++;
    $cats[$slug] = ['label' => [$lang ?? cms_default_lang() => $label], 'desc' => [], 'seo_title' => [], 'seo_desc' => [], 'order' => count($cats) + 1];
    cms_categories_save($type, $cats);
    return $slug;
}

/**
 * Escribe en todos los elementos del tipo cuya categoría es $fromSlug el valor de $toCat (o la vacía si es null).
 * Sin ganchos ni versiones: es una corrección masiva. Devuelve cuántos elementos tocó.
 */
function cms_categories_apply(string $type, string $fromSlug, ?array $toCat): int
{
    $f = cms_categories_field($type);
    if ($f === null) return 0;
    $i18n = !empty(cms_config('types')[$type]['fields'][$f]['i18n']);
    $n = 0;
    foreach (cms_content_files($type) as $file) {
        $it = cms_json_read($file, null);
        if (!is_array($it) || empty($it['slug'])) continue;
        if (cms_category_slug_of($type, $it[$f] ?? '') !== $fromSlug) continue;
        $it[$f] = $toCat ? cms_category_value($type, $toCat) : ($i18n ? array_fill_keys(cms_langs(), '') : '');
        if (cms_json_write($file, $it)) { cms_index_touch($type, (string) $it['slug'], $it); $n++; }
    }
    cms_items_flush();
    return $n;
}

/** Cambia etiquetas, descripción, SEO u orden de una categoría; si cambia la etiqueta, corrige los elementos. */
function cms_category_update(string $type, string $slug, array $data): bool
{
    $cats = cms_categories($type);
    if (!isset($cats[$slug])) return false;
    $new = $cats[$slug];
    foreach (['label', 'desc', 'seo_title', 'seo_desc'] as $k) if (isset($data[$k]) && is_array($data[$k])) $new[$k] = array_map(fn($v) => trim((string) $v), $data[$k]);
    if (isset($data['order'])) $new['order'] = (int) $data['order'];
    if (cms_category_label($new, cms_default_lang()) === '') return false;
    if (($new['label'] ?? []) != ($cats[$slug]['label'] ?? [])) cms_categories_apply($type, $slug, $new + ['slug' => $slug]);   // con el registro viejo aún cargado
    $cats[$slug] = $new;
    return cms_categories_save($type, $cats);
}

/** Fusiona $from dentro de $into: mueve sus elementos y borra $from. */
function cms_category_merge(string $type, string $from, string $into): int
{
    $cats = cms_categories($type);
    if ($from === $into || !isset($cats[$from], $cats[$into])) return 0;
    $n = cms_categories_apply($type, $from, $cats[$into] + ['slug' => $into]);
    unset($cats[$from]);
    cms_categories_save($type, $cats);
    return $n;
}

/** Borra una categoría; sus elementos quedan sin categoría. */
function cms_category_delete(string $type, string $slug): int
{
    $cats = cms_categories($type);
    if (!isset($cats[$slug])) return 0;
    $n = cms_categories_apply($type, $slug, null);
    unset($cats[$slug]);
    cms_categories_save($type, $cats);
    return $n;
}
