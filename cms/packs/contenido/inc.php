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
