<?php
/** Utilidades compartidas por las vistas del paquete contenido. */
declare(strict_types=1);
if (!function_exists('ct_split')) {
    function ct_split(string $line, int $n): array { return array_pad(array_map('trim', explode('|', $line, $n)), $n, ''); }
    /** "Texto > URL" → [texto, url]; si no hay ">", todo es texto. */
    function ct_link(string $cell): array { $p = array_pad(array_map('trim', explode('>', $cell, 2)), 2, ''); return [$p[0], $p[1]]; }
    /** Texto seguro con marcado sencillo (para descripciones escritas por el editor). */
    function ct_rich(string $s): string { return preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', strip_tags($s, '<a><strong><em><b><i><br><small><span>')) ?? ''; }
    function ct_is_image(string $v): bool { return $v !== '' && (preg_match('#\.(png|jpe?g|webp|gif|svg|avif)(\?.*)?$#i', $v) || preg_match('#^(https?:)?//|^(uploads|cms|site|assets)/#', $v)); }
    function ct_icon(string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (ct_is_image($v)) return '<img class="ct-icon-img" src="' . cms_e(cms_img($v)) . '" alt="" loading="lazy">';
        if (preg_match('/^ti-[a-z0-9-]+$/', $v)) return '<i class="ti ' . cms_e($v) . '" aria-hidden="true"></i>';
        return '<span aria-hidden="true">' . cms_e($v) . '</span>';
    }
    /** Fecha legible en el idioma que se dibuja (sin depender de intl). */
    function ct_date(string $iso, string $lang): string
    {
        $t = strtotime($iso);
        if (!$t) return $iso;
        $es = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $m = (int) date('n', $t) - 1;
        return $lang === 'en' ? $en[$m] . ' ' . date('j, Y', $t) : date('j', $t) . ' de ' . $es[$m] . ' de ' . date('Y', $t);
    }
    /**
     * Tarjetas del bloque "coleccion": elementos publicados del tipo, filtrados y recortados.
     * Devuelve [ [url, título, resumen, imagen, fecha, categoría], … ]. En un ejemplo del panel con la colección vacía, inventa tres.
     */
    function ct_collection_cards(string $type, array $b, string $lang): array
    {
        $def = cms_type($type);
        $n = max(1, (int) $b['count']);
        $out = [];
        if ($def && !cms_is_demo()) {
            $items = cms_items($type);
            $filter = mb_strtolower(trim((string) $b['filter']));
            if ($filter !== '') {
                $items = array_filter($items, function ($it) use ($filter, $lang) {
                    foreach (['category', 'tags', 'categoria', 'etiquetas'] as $f) {
                        foreach ((array) cms_f($it, $f, $lang) as $v) if (mb_strtolower(trim((string) $v)) === $filter) return true;
                        $v = cms_f($it, $f, $lang);
                        if (is_string($v) && mb_strtolower(trim($v)) === $filter) return true;
                    }
                    return false;
                });
            }
            $items = array_values($items);
            if (($b['order'] ?? '') === 'reverse') $items = array_reverse($items);
            elseif (($b['order'] ?? '') === 'random') shuffle($items);
            foreach (array_slice($items, 0, $n) as $it) {
                $out[] = [
                    cms_item_url($type, $it, $lang),
                    (string) (cms_f($it, $def['title_field'] ?? 'title', $lang) ?: $it['slug']),
                    (string) cms_f($it, $def['excerpt_field'] ?? 'excerpt', $lang),
                    (string) cms_f($it, $def['image_field'] ?? 'image', $lang),
                    (string) cms_f($it, 'date', $lang),
                    (string) (is_array($c = cms_f($it, 'category', $lang)) ? reset($c) : $c),
                ];
            }
        }
        if (!$out && cms_is_demo()) {   // ejemplo del manual o del selector: tarjetas inventadas, así el bloque se entiende
            $t = ['Cómo elegir el color de una marca', 'Tres errores al escribir una página de inicio', 'Qué mide de verdad una web que funciona'];
            $x = ['Un método corto para decidir sin discutir dos semanas.', 'Y qué poner en su lugar, con ejemplos reales.', 'Las cuatro cifras que importan y dónde mirarlas.'];
            for ($i = 0; $i < $n; $i++) $out[] = ['#', $t[$i % 3], $x[$i % 3], cms_demo_image('foto', $i + 1), date('Y-m-d', strtotime('-' . ($i * 9 + 3) . ' days')), ['Marca', 'Contenido', 'Métricas'][$i % 3]];
        }
        return $out;
    }
}

/* ------------------------------------------------------------------ ticker: colección, feed externo (RSS/Atom, WordPress) o a mano */
if (!function_exists('ct_feed_items')) {
    /** Iconos SVG del ticker (Tabler): clave => etiqueta, en el orden del selector. */
    function ct_ticker_icons(): array
    {
        return ['news' => 'Periódico', 'rss' => 'RSS', 'speakerphone' => 'Megáfono', 'bell' => 'Campana', 'bolt' => 'Rayo', 'sparkles' => 'Destellos',
                'star' => 'Estrella', 'flame' => 'Fuego', 'bulb' => 'Bombilla', 'trending-up' => 'Tendencia', 'calendar' => 'Calendario', 'article' => 'Artículo',
                'bookmark' => 'Marcador', 'scale' => 'Balanza', 'gavel' => 'Mazo', 'briefcase' => 'Maletín', 'world' => 'Mundo', 'info-circle' => 'Información', 'alert-triangle' => 'Alerta'];
    }
    /** <svg> en línea de un icono del ticker, o el emoji / imagen que escriba el editor (ct_icon). */
    function ct_ticker_icon(string $key, string $custom = ''): string
    {
        if (trim($custom) !== '') return ct_icon($custom);
        static $paths = null;
        if ($paths === null) $paths = (array) require __DIR__ . '/icons.php';
        if (!isset($paths[$key])) return '';
        return '<svg class="ct-svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$key] . '</svg>';
    }
    /** Fecha corta ("14 sep" / "Sep 14"; con año si no es el actual). */
    function ct_date_short(string $iso, string $lang): string
    {
        $t = strtotime($iso);
        if (!$t) return '';
        $es = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $m = (int) date('n', $t) - 1;
        $y = date('Y', $t) !== date('Y') ? ' ' . date('Y', $t) : '';
        return $lang === 'en' ? $en[$m] . ' ' . date('j', $t) . $y : date('j', $t) . ' ' . $es[$m] . $y;
    }

    /** Descarga con límite (2 MB, 8 s). Admite http:// probando primero https://. Devuelve [cuerpo|null, error]. */
    function ct_http(string $url): array
    {
        if (!function_exists('cms_http_get')) return [null, 'Este núcleo no puede descargar direcciones.'];
        $u = preg_replace('#^http://#i', 'https://', trim($url)) ?? $url;
        if (!preg_match('#^https://#i', $u)) $u = 'https://' . ltrim($u, '/');
        return cms_http_get($u, 2097152, 8);
    }

    /** Un feed RSS 2.0, RSS 1.0 o Atom → ['items' => [[title, url, date, cats], …], 'title', 'link'] o null si no es un feed. */
    function ct_feed_parse(string $xml): ?array
    {
        $xml = ltrim($xml, "\xEF\xBB\xBF \t\r\n");
        if ($xml === '' || $xml[0] !== '<') return null;
        $prev = libxml_use_internal_errors(true);
        $doc = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$doc) return null;
        $out = ['items' => [], 'title' => '', 'link' => ''];
        $name = $doc->getName();
        $clean = fn($v) => trim(html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $date = function ($v): string { $t = strtotime(trim((string) $v)); return $t ? date('Y-m-d H:i:s', $t) : ''; };
        if ($name === 'feed') {   // Atom
            $out['title'] = $clean($doc->title);
            foreach ($doc->link as $l) if ((string) ($l['rel'] ?? 'alternate') === 'alternate') { $out['link'] = (string) $l['href']; break; }
            foreach ($doc->entry as $e) {
                $url = '';
                foreach ($e->link as $l) { $rel = (string) ($l['rel'] ?? 'alternate'); if ($rel === 'alternate' || $url === '') $url = (string) $l['href']; if ($rel === 'alternate') break; }
                $cats = [];
                foreach ($e->category as $c) { $cats[] = $clean($c['label'] ?? $c['term']); if ((string) ($c['term'] ?? '') !== '') $cats[] = $clean($c['term']); }
                $out['items'][] = [$clean($e->title), $url, $date((string) ($e->published ?: $e->updated)), array_values(array_unique(array_filter($cats)))];
            }
            return $out;
        }
        $channel = $name === 'rss' ? $doc->channel : $doc;   // RSS 2.0, o RDF (RSS 1.0) con los <item> en la raíz
        $out['title'] = $clean($channel->title ?? '');
        $out['link'] = trim((string) ($channel->link ?? ''));
        $items = $name === 'rss' ? $channel->item : $doc->item;
        foreach ($items as $it) {
            $cats = [];
            foreach ($it->category as $c) $cats[] = $clean($c);
            $dc = $it->children('http://purl.org/dc/elements/1.1/');
            $out['items'][] = [$clean($it->title), trim((string) $it->link), $date((string) ($it->pubDate ?: ($dc->date ?? ''))), array_values(array_unique(array_filter($cats)))];
        }
        return $out;
    }

    /**
     * Últimas entradas de un blog externo. $url puede ser la dirección del sitio (WordPress: se usa /feed/ y, con
     * categoría, /category/<slug>/feed/), o la de un feed RSS/Atom cualquiera (entonces la categoría filtra por nombre).
     * Con caché en data/cache (minutos de $ttl); si la red falla se sigue mostrando lo último descargado.
     * Devuelve ['items' => [[title, url, date, cats], …] (ya filtrados y recortados), 'error', 'cats' (vistas), 'site' (enlace del blog), 'from' (feed usado)].
     */
    function ct_feed_items(string $url, string $category, int $count, int $ttl = 30): array
    {
        $url = trim($url);
        $category = trim($category);
        $empty = ['items' => [], 'error' => '', 'cats' => [], 'site' => '', 'from' => ''];
        if ($url === '') return ['error' => 'Falta la dirección del blog o del feed.'] + $empty;
        $dir = CMS_DATA . '/cache';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . '/feed-' . substr(sha1(mb_strtolower($url) . '|' . mb_strtolower($category)), 0, 16) . '.json';
        $cached = is_file($file) ? cms_json_read($file, null) : null;
        if (is_array($cached) && !empty($cached['time']) && time() - (int) $cached['time'] < max(1, $ttl) * 60) {
            $r = $cached; unset($r['time']);
            $r['items'] = array_slice((array) $r['items'], 0, max(1, $count));
            return $r + $empty;
        }
        if (is_array($cached)) { $cached['time'] = time(); @file_put_contents($file, json_encode($cached)); }   // otras peticiones usan lo viejo mientras esta descarga

        // dirección base y feed principal
        $u = preg_replace('#^http://#i', 'https://', $url) ?? $url;
        if (!preg_match('#^https://#i', $u)) $u = 'https://' . ltrim($u, '/');
        $isFeed = (bool) preg_match('#(/feed/?$|/feed/(rss2?|atom|rdf)/?$|\.(xml|rss|atom)$|[?&]feed=)#i', $u);
        $base = rtrim($isFeed ? preg_replace('#/feed(/(rss2?|atom|rdf))?/?$#i', '', $u) : $u, '/');
        $slug = $category !== '' ? cms_slugify($category) : '';
        $cands = [];   // [url, filtrar por categoría después, buscar el feed en el HTML si no lo es]
        if ($slug !== '' && !preg_match('#\.(xml|rss|atom)$|[?&]feed=#i', $u)) $cands[] = [$base . '/category/' . $slug . '/feed/', false, false];   // WordPress
        $cands[] = [$isFeed ? $u : $base . '/feed/', true, true];
        if (!$isFeed) $cands[] = [$base, true, true];   // no es WordPress: la portada suele anunciar su feed
        $feed = null; $err = ''; $from = ''; $seen = [];
        foreach ($cands as [$cu, $filter, $discover]) {
            [$body, $e] = ct_http($cu);
            if ($body === null) { $err = $e; continue; }
            $p = ct_feed_parse($body);
            if ($p === null) {   // no era un feed: si es HTML, buscar el enlace de descubrimiento (<link rel="alternate" type="application/rss+xml">)
                if (!$discover) continue;
                if (preg_match('#<link[^>]+type=["\']application/(rss|atom)\+xml["\'][^>]*>#i', $body, $m) && preg_match('#href=["\']([^"\']+)["\']#i', $m[0], $h)) {
                    $alt = $h[1];
                    if (!preg_match('#^https?://#i', $alt)) $alt = $base . '/' . ltrim($alt, '/');
                    [$body2, $e2] = ct_http($alt);
                    $p = $body2 !== null ? ct_feed_parse($body2) : null;
                    if ($p !== null) { $cu = $alt; }
                }
                if ($p === null) { $err = 'La dirección no devuelve un feed RSS ni Atom.'; continue; }
            }
            foreach ($p['items'] as $it) foreach ((array) $it[3] as $c) $seen[$c] = true;   // categorías del feed, antes de filtrar (para el aviso del constructor)
            if ($filter && $slug !== '') {
                $want = mb_strtolower($category);
                $p['items'] = array_values(array_filter($p['items'], function ($it) use ($want, $slug) {
                    foreach ((array) $it[3] as $c) if (mb_strtolower($c) === $want || cms_slugify($c) === $slug) return true;
                    return false;
                }));
            }
            if (!$p['items'] && !$filter) continue;   // feed de categoría vacío (o inexistente pero con RSS vacío): probar el general filtrando
            $feed = $p; $from = $cu; $err = '';
            break;
        }
        if ($feed === null) {
            if (is_array($cached)) { $r = $cached; unset($r['time']); $r['error'] = $err; $r['items'] = array_slice((array) $r['items'], 0, max(1, $count)); return $r + $empty; }
            return ['error' => $err !== '' ? $err : 'No hay entradas que mostrar.'] + $empty;
        }
        $r = ['time' => time(), 'items' => array_slice($feed['items'], 0, 30), 'error' => '', 'cats' => array_keys($seen), 'site' => $feed['link'] !== '' ? $feed['link'] : $base, 'from' => $from];
        @file_put_contents($file, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        unset($r['time']);
        $r['items'] = array_slice($r['items'], 0, max(1, $count));
        return $r;
    }

    /**
     * Elementos del ticker según la fuente elegida en $b: [[title, url, date], …] más 'note' (aviso para el constructor) y 'more' (enlace por defecto).
     */
    function ct_ticker_items(array $b, string $lang): array
    {
        $n = max(1, (int) $b['count']);
        $items = []; $note = ''; $more = '';
        $src = (string) $b['source'];
        if ($src === 'feed') {
            if (cms_is_demo()) {
                $t = ['Nueva versión con firma electrónica avanzada', 'Guía: cómo organizar expedientes en equipo', 'Resumen de noticias jurídicas de la semana', 'Webinar: inteligencia artificial en el despacho'];
                for ($i = 0; $i < min($n, 4); $i++) $items[] = [$t[$i], '#', date('Y-m-d', strtotime('-' . ($i * 5 + 1) . ' days'))];
            } else {
                $f = ct_feed_items((string) $b['feed_url'], (string) $b['feed_category'], $n, (int) $b['refresh']);
                foreach ($f['items'] as $it) $items[] = [$it[0], $it[1], $it[2]];
                $more = (string) $f['site'];
                if (!$items) {
                    $note = $f['error'] !== '' ? $f['error'] : (trim((string) $b['feed_category']) !== '' ? 'No hay entradas de la categoría "' . trim((string) $b['feed_category']) . '".' : 'El feed no tiene entradas.');
                    if ($f['cats']) $note .= ' Categorías que trae el feed: ' . implode(', ', array_slice($f['cats'], 0, 20)) . '.';
                }
            }
        } elseif ($src === 'manual') {
            foreach ((array) $b['items'] as $line) {
                [$text, $url] = ct_split((string) $line, 2);
                if ($text !== '') $items[] = [$text, $url, ''];
            }
        } else {
            $type = (string) $b['collection'];
            foreach (ct_collection_cards($type, ['count' => $n, 'filter' => (string) $b['filter'], 'order' => 'default'], $lang) as $c) $items[] = [$c[1], $c[0], $c[4]];
            if ($type !== '' && cms_type($type) && empty(cms_type($type)['no_list'])) $more = cms_url('list:' . $type, $lang);
            if (!$items && !cms_is_demo()) $note = 'La colección no tiene elementos publicados' . (trim((string) $b['filter']) !== '' ? ' de la categoría "' . trim((string) $b['filter']) . '"' : '') . '.';
        }
        return ['items' => array_slice($items, 0, $n), 'note' => $note, 'more' => $more];
    }
}
