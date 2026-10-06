<?php
/**
 * cms_simple — feed RSS 2.0 (1.39).
 *
 * Rutas (por idioma, con prefijo /xx para los no predeterminados):
 *   /feed.xml                       todas las colecciones con feed, mezcladas por fecha
 *   /{segmento-tipo}/feed.xml       una colección
 *   /{segmento-tipo}/{cat}/feed.xml una categoría de la colección
 *   (también /feed, /blog/feed… al estilo de WordPress)
 *
 * Entran las colecciones con 'schema' Article, BlogPosting o NewsArticle, o con 'feed' => true en config.php
 * ('feed' => false lo apaga para un tipo). Cada <item> lleva título, enlace, guid, pubDate, autor, categorías,
 * <description> con el resumen y <content:encoded> con el cuerpo completo (URLs absolutas, imagen destacada al
 * principio). Los paquetes intervienen con el gancho 'feed.item' (filtro): reciben el arreglo del elemento y pueden
 * poner un <enclosure> (el paquete audio pone su MP3, así el feed sirve también como podcast) o etiquetas extra.
 */
declare(strict_types=1);

/** ¿Está encendido el feed? (Ajustes → Marca y SEO → Feed RSS; encendido mientras no se apague) */
function cms_feed_enabled(): bool
{
    $S = cms_settings();
    return (!isset($S['feed_on']) || !empty($S['feed_on'])) && cms_config('feed', true) !== false;
}

/** ¿La colección entra al feed? Por defecto las de artículos (schema Article/BlogPosting/NewsArticle); 'feed' => true|false lo fija. */
function cms_feed_type_ok(string $type): bool
{
    $d = cms_type($type);
    if (!$d || !empty($d['internal']) || !empty($d['noindex']) || !empty($d['no_list'])) return false;
    if (isset($d['feed'])) return (bool) $d['feed'];
    return in_array((string) ($d['schema'] ?? ''), ['Article', 'BlogPosting', 'NewsArticle'], true);
}

/** Colecciones que entran al feed. */
function cms_feed_types(): array
{
    return array_values(array_filter(array_keys(cms_config('types')), 'cms_feed_type_ok'));
}

/** URL del feed: del sitio ('home'), de una colección ('list:tipo') o de una categoría ('cat:tipo' + slug). */
function cms_feed_url(string $route, string $lang, ?string $slug = null): string
{
    if ($route === 'home') return CMS_BASE . cms_lang_prefix($lang) . '/feed.xml';
    $u = cms_url_plain($route, $lang, $slug);
    return rtrim($u, '/') . '/feed.xml';
}

/** Feeds que anuncia una página en su <head> (el del sitio y, en una colección o uno de sus elementos, el de la colección): [[título, url], …]. */
function cms_feed_links(array $page): array
{
    if (!cms_feed_enabled() || !cms_feed_types()) return [];
    $lang = (string) ($page['lang'] ?? cms_default_lang());
    $site = (string) (cms_settings()['site_name'] ?? cms_config('name'));
    $out = [[$site, cms_feed_url('home', $lang)]];
    [$kind, $type] = array_pad(explode(':', (string) ($page['route'] ?? ''), 2), 2, '');
    if (in_array($kind, ['list', 'item', 'cat'], true) && cms_feed_type_ok($type)) {
        $d = cms_type($type);
        $label = cms_t($type . '_title', $lang, $d['label'] ?? $type);
        if ($kind === 'cat' && !empty($page['category']['slug'])) {
            $out[] = [$site . ' · ' . $label . ' · ' . (string) ($page['category']['label_text'] ?? $page['category']['slug']), cms_feed_url('cat:' . $type, $lang, (string) $page['category']['slug'])];
        } else {
            $out[] = [$site . ' · ' . $label, cms_feed_url('list:' . $type, $lang)];
        }
    }
    return $out;
}

/** Fecha RFC 2822 a partir de AAAA-MM-DD (o AAAA-MM-DD HH:MM); '' si no hay fecha. */
function cms_feed_date(string $ymd): string
{
    $ts = $ymd !== '' ? strtotime($ymd) : false;
    return $ts ? date('r', $ts) : '';
}

/** URLs relativas del HTML (src, href, poster, srcset que empiezan con "/") a absolutas con el dominio canónico. */
function cms_feed_absolutize(string $html): string
{
    $html = (string) preg_replace_callback('~\s(src|href|poster)=(["\'])(/(?!/)[^"\']*)\2~i', fn($m) => ' ' . $m[1] . '=' . $m[2] . cms_abs_url($m[3]) . $m[2], $html);
    return (string) preg_replace_callback('~\ssrcset=(["\'])([^"\']+)\1~i', function ($m) {
        $parts = array_map(function ($p) { $p = trim($p); return preg_match('#^/(?!/)#', $p) ? cms_abs_url($p) : $p; }, explode(',', $m[2]));
        return ' srcset=' . $m[1] . implode(', ', $parts) . $m[1];
    }, $html);
}

/** HTML sencillo de las secciones del constructor (títulos, textos y párrafos de cada bloque), para el cuerpo de una página sin campo html. */
function cms_feed_sections_html(array $sections, string $lang): string
{
    $out = '';
    foreach ($sections as $sec) {
        if (!is_array($sec) || !empty($sec['hidden'])) continue;
        $def = cms_block((string) ($sec['type'] ?? ''));
        if (!$def) continue;
        foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
            $t = $fd['type'] ?? 'text';
            if (!in_array($t, ['text', 'textarea', 'html'], true)) continue;
            $v = cms_f((array) ($sec['data'] ?? []), $k, $lang, '');
            if (is_array($v)) $v = implode("\n", array_map('strval', $v));
            $v = trim((string) $v);
            if ($v === '' || preg_match('~^(https?:)?//|^[a-z0-9_/.-]+\.(png|jpe?g|webp|svg|gif|mp4|pdf)$~i', $v)) continue;
            if ($t === 'html') $out .= cms_content($v);
            elseif ($t === 'textarea') $out .= '<p>' . nl2br(cms_e($v)) . '</p>';
            elseif (in_array($k, ['title', 'heading', 'titulo'], true)) $out .= '<h2>' . cms_e($v) . '</h2>';
            else $out .= '<p>' . cms_e($v) . '</p>';
        }
    }
    return $out;
}

/**
 * Datos de un <item> del feed: title, link, guid, date, author, categories[], desc (texto), html (content:encoded),
 * enclosure (null o ['url', 'length', 'type']) y extra[] (XML ya escapado). Pasa por el gancho 'feed.item'.
 */
function cms_feed_item(string $type, array $raw, string $lang): ?array
{
    if (!empty($raw['feed_hidden'])) return null;   // casilla opcional por elemento: 'feed_hidden' en los campos del tipo
    $def = cms_type($type) ?? [];
    $tf = $def['title_field'] ?? 'title';
    $tv = $raw[$tf] ?? '';
    if ($lang !== cms_default_lang() && is_array($tv) && trim((string) ($tv[$lang] ?? '')) === '') return null;   // sin traducción: fuera del feed de ese idioma
    $item = cms_localize($raw, $lang);
    $slug = (string) ($item['slug'] ?? '');
    $url = cms_url_plain('item:' . $type, $lang, $slug);
    $S = cms_settings();
    $html = '';
    foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
        if (($fd['type'] ?? '') !== 'html') continue;
        $v = trim((string) cms_f($item, $k, $lang));
        if ($v !== '') { $html = $v; break; }
    }
    $GLOBALS['cms_current'] = ['type' => $type, 'item' => $item, 'page' => ['route' => 'feed', 'lang' => $lang], 'lang' => $lang];   // los ganchos 'content' saben que es el feed
    if ($html !== '') $html = cms_content($html);
    else foreach ((array) ($def['fields'] ?? []) as $k => $fd) if (($fd['type'] ?? '') === 'sections') { $html = cms_feed_sections_html((array) cms_f($item, $k, $lang, []), $lang); break; }
    $imgField = $def['image_field'] ?? 'image';
    $title = (string) cms_f($item, $tf, $lang);
    if (!empty($item[$imgField]) && is_string($item[$imgField])) {
        $html = '<p><img src="' . cms_e(cms_abs_url(cms_img((string) $item[$imgField]))) . '" alt="' . cms_e($title) . '"></p>' . $html;
    }
    $cats = [];
    if (($c = cms_f($item, 'category', $lang)) && is_string($c)) $cats[] = $c;
    foreach ((array) cms_f($item, 'tags', $lang, []) as $tg) if (is_string($tg) && trim($tg) !== '') $cats[] = trim($tg);
    $fi = [
        'type' => $type, 'slug' => $slug,
        'title' => $title,
        'link' => cms_abs_url($url),
        'guid' => cms_abs_url($url),
        'date' => (string) ($item['date'] ?? ($item['publish_at'] ?? ($item['created'] ?? ''))),
        'updated' => (string) ($item['updated'] ?? ''),
        'author' => (string) ($S['author_name'] ?? ($S['site_name'] ?? cms_config('name'))),
        'categories' => array_values(array_unique($cats)),
        'desc' => cms_meta_desc((string) cms_f($item, $def['excerpt_field'] ?? 'excerpt', $lang), 500),
        'html' => cms_feed_absolutize($html),
        'enclosure' => null,
        'extra' => [],
    ];
    $fi = cms_has_hook('feed.item') ? (array) cms_apply('feed.item', $fi, $type, $item, $lang) : $fi;
    unset($GLOBALS['cms_current']);
    return $fi;
}

/** Elementos del feed: los últimos N publicados de las colecciones pedidas (todas por defecto), por fecha descendente; con $cat solo los de esa categoría. */
function cms_feed_entries(array $types, string $lang, ?string $cat = null, int $limit = 20): array
{
    $pool = [];
    foreach ($types as $t) {
        foreach (cms_items($t) as $it) {
            if (cms_is_home_item($t, (string) $it['slug'])) continue;
            if ($cat !== null && cms_category_slug_of($t, (string) cms_f($it, (string) cms_categories_field($t), $lang)) !== $cat) continue;
            $pool[] = [$t, (string) $it['slug'], (string) ($it['date'] ?? ($it['publish_at'] ?? ($it['created'] ?? ''))), (string) ($it['updated'] ?? '')];
        }
    }
    usort($pool, fn($a, $b) => strcmp($b[2], $a[2]) ?: strcmp($b[3], $a[3]));
    $out = [];
    foreach ($pool as [$t, $slug]) {
        if (count($out) >= $limit) break;
        $raw = $GLOBALS['cms_item_override'][$t][$slug] ?? cms_json_read(cms_content_dir($t) . '/' . cms_slugify($slug) . '.json', null);
        if (!is_array($raw)) continue;
        $fi = cms_feed_item($t, $raw, $lang);
        if ($fi) $out[] = $fi;
    }
    return $out;
}

/** Texto para CDATA (cierra y reabre la sección si el contenido trae "]]>"). */
function cms_feed_cdata(string $s): string
{
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';
}

/** Escapado XML (texto y atributos). */
function cms_feed_x($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Responde el feed. $scope: [] (todo el sitio), [segmento] (una colección) o [segmento, categoría].
 * Devuelve false si la ruta no corresponde a ningún feed (el enrutador sigue con un 404).
 */
function cms_feed(string $lang, array $scope = []): bool
{
    if (!cms_feed_enabled()) return false;
    $S = cms_settings();
    $site = (string) ($S['site_name'] ?? cms_config('name'));
    $types = cms_feed_types();
    $title = $site;
    $desc = (string) cms_t('home_meta_desc', $lang, '');
    $self = cms_feed_url('home', $lang);
    $home = cms_url_plain('home', $lang);
    $cat = null;
    if ($scope !== []) {
        $type = null;
        foreach (cms_config('types') as $k => $d) if (empty($d['internal']) && $scope[0] === cms_segment($d + ['key' => $k], $lang)) { $type = (string) $k; break; }
        if ($type === null || !cms_feed_type_ok($type)) return false;
        $d = cms_type($type);
        $label = (string) cms_t($type . '_title', $lang, $d['label'] ?? $type);
        $types = [$type];
        $title = $site . ' · ' . $label;
        $desc = (string) cms_t($type . '_meta_desc', $lang, '');
        $self = cms_feed_url('list:' . $type, $lang);
        $home = cms_url_plain('list:' . $type, $lang);
        if (count($scope) === 2) {
            if (cms_categories_field($type) === null || !($c = cms_category($type, $scope[1]))) return false;
            $cat = (string) $c['slug'];
            $title .= ' · ' . cms_category_label($c, $lang);
            $desc = cms_category_text($c, 'desc', $lang) ?: $desc;
            $self = cms_feed_url('cat:' . $type, $lang, $cat);
            $home = cms_url_plain('cat:' . $type, $lang, $cat);
        } elseif (count($scope) > 2) return false;
    }
    if (!$types) return false;
    $limit = max(1, min(100, (int) ($S['feed_count'] ?? 20)));
    $full = !isset($S['feed_full']) || !empty($S['feed_full']);
    $entries = cms_feed_entries($types, $lang, $cat, $limit);

    $latest = '';
    foreach ($entries as $e) $latest = max($latest, $e['updated'] ?: $e['date']);
    header('Content-Type: application/rss+xml; charset=utf-8');
    header('X-Robots-Tag: noindex');
    if ($latest !== '' && ($ts = strtotime($latest))) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $ts) . ' GMT');
    $o = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
        . "<channel>\n"
        . '  <title>' . cms_feed_x($title) . "</title>\n"
        . '  <link>' . cms_feed_x(cms_abs_url($home)) . "</link>\n"
        . '  <description>' . cms_feed_x($desc) . "</description>\n"
        . '  <language>' . cms_feed_x($lang) . "</language>\n"
        . '  <atom:link href="' . cms_feed_x(cms_abs_url($self)) . '" rel="self" type="application/rss+xml"/>' . "\n"
        . '  <generator>cms_simple ' . CMS_VERSION . "</generator>\n"
        . '  <lastBuildDate>' . cms_feed_x(cms_feed_date($latest) ?: date('r')) . "</lastBuildDate>\n";
    if (!empty($S['logo'])) $o .= '  <image><url>' . cms_feed_x(cms_abs_url(cms_img((string) $S['logo']))) . '</url><title>' . cms_feed_x($title) . '</title><link>' . cms_feed_x(cms_abs_url($home)) . "</link></image>\n";
    $o = (string) cms_apply('feed.channel', $o, $types, $lang);
    foreach ($entries as $e) {
        $o .= "  <item>\n"
            . '    <title>' . cms_feed_x($e['title']) . "</title>\n"
            . '    <link>' . cms_feed_x($e['link']) . "</link>\n"
            . '    <guid isPermaLink="true">' . cms_feed_x($e['guid']) . "</guid>\n";
        if (($pd = cms_feed_date((string) $e['date'])) !== '') $o .= '    <pubDate>' . cms_feed_x($pd) . "</pubDate>\n";
        if ($e['author'] !== '') $o .= '    <dc:creator>' . cms_feed_x($e['author']) . "</dc:creator>\n";
        foreach ((array) $e['categories'] as $c) $o .= '    <category>' . cms_feed_x($c) . "</category>\n";
        $o .= '    <description>' . cms_feed_x($e['desc'] !== '' ? $e['desc'] : cms_meta_desc((string) $e['html'], 500)) . "</description>\n";
        if ($full && trim((string) $e['html']) !== '') $o .= '    <content:encoded>' . cms_feed_cdata((string) $e['html']) . "</content:encoded>\n";
        if (!empty($e['enclosure']['url'])) {
            $en = $e['enclosure'];
            $o .= '    <enclosure url="' . cms_feed_x($en['url']) . '" length="' . (int) ($en['length'] ?? 0) . '" type="' . cms_feed_x($en['type'] ?? 'application/octet-stream') . '"/>' . "\n";
        }
        foreach ((array) $e['extra'] as $x) $o .= '    ' . $x . "\n";
        $o .= "  </item>\n";
    }
    $o .= "</channel>\n</rss>\n";
    echo $o;
    return true;
}
