<?php
/**
 * Paquete "enlaces": enlazado interno automático, con el gancho 'content' del núcleo.
 *
 * Cómo trabaja: reúne los términos (la lista "palabra | URL" de Ajustes y, si se pide, las etiquetas de las
 * colecciones), los ordena del más largo al más corto y los compila en UNA expresión regular. Luego recorre el HTML
 * por nodos de texto, saltando lo que está dentro de <a>, títulos, botones, código y scripts, y sustituye cada término
 * la primera vez que aparece (hasta el tope por palabra y por página). El límite de palabra se hace con \p{L} y \p{N},
 * no con \b, para que las vocales acentuadas y la eñe cuenten como letras ("México" no coincide dentro de "Méxicos").
 * Nunca enlaza hacia la página que se está dibujando. Se aplica al dibujar: lo guardado no cambia.
 */
declare(strict_types=1);
if (!function_exists('enlaces_apply')) {

    /** Ajustes del paquete con sus valores por defecto. */
    function enlaces_settings(): array
    {
        static $o = null;
        if ($o !== null) return $o;
        $S = cms_settings();
        $num = fn(string $k, int $d, int $min, int $max) => max($min, min($max, (int) (($S[$k] ?? '') === '' ? $d : $S[$k])));
        return $o = [
            'on'       => !empty($S['autolink_on']),
            'tags'     => (string) ($S['autolink_tags'] ?? ''),
            'types'    => array_values(array_filter(array_map('trim', explode(',', (string) ($S['autolink_types'] ?? ''))))),
            'per_term' => $num('autolink_per_term', 1, 1, 5),
            'per_page' => $num('autolink_per_page', 10, 1, 100),
            'min_len'  => $num('autolink_min_len', 3, 1, 20),
            'case'     => !empty($S['autolink_case']),
            'class'    => preg_replace('/[^a-z0-9_ -]/i', '', (string) ($S['autolink_class'] ?? '')) ?: 'cms-autolink',
            'css'      => trim((string) ($S['autolink_css'] ?? '')),
        ];
    }

    /** ¿Dos URL (relativas con CMS_BASE o absolutas del propio sitio) son la misma página? */
    function enlaces_same_page(string $a, string $b): bool
    {
        $norm = function (string $u): string {
            $p = parse_url($u);
            if (!is_array($p)) return $u;
            if (!empty($p['host']) && strcasecmp($p['host'], (string) parse_url(cms_site_url(), PHP_URL_HOST)) !== 0) return 'ext:' . $u;
            return rtrim((string) ($p['path'] ?? '/'), '/') . (isset($p['query']) ? '?' . $p['query'] : '');
        };
        return $norm($a) === $norm($b);
    }

    /**
     * Términos a enlazar en un idioma: [['t' => texto, 'url' => destino], …] del más largo al más corto, sin repetidos,
     * sin los de la lista negra ni los más cortos que el mínimo.
     */
    function enlaces_terms(string $lang): array
    {
        static $cache = [];
        if (isset($cache[$lang])) return $cache[$lang];
        $o = enlaces_settings();
        $S = cms_settings();
        $key = fn(string $t) => $o['case'] ? $t : mb_strtolower($t);
        $black = [];
        foreach (cms_lines((string) cms_localize($S['autolink_blacklist'] ?? '', $lang)) as $w) if (trim($w) !== '') $black[$key(trim($w))] = true;
        $terms = [];
        // 1) lista "palabra | URL" (la primera aparición de una palabra gana)
        foreach (cms_lines((string) cms_localize($S['autolink_words'] ?? '', $lang)) as $line) {
            [$w, $u] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($w === '' || $u === '' || isset($terms[$key($w)])) continue;
            $terms[$key($w)] = ['t' => $w, 'url' => cms_menu_url($u, $lang)];
        }
        // 2) etiquetas de las colecciones, hacia el filtro del índice de su colección
        if ($o['tags'] === 'list') {
            foreach (cms_config('types') as $k => $d) {
                if (!empty($d['no_list']) || ($o['types'] && !in_array($k, $o['types'], true))) continue;
                $tagFields = array_keys(array_filter((array) ($d['fields'] ?? []), fn($f) => (($f['type'] ?? '') === 'tags')));
                if (!$tagFields) continue;
                $base = cms_url('list:' . $k, $lang);
                foreach (cms_items($k) as $it) foreach ($tagFields as $f) foreach ((array) cms_f($it, $f, $lang, []) as $tag) {
                    $tag = trim((string) $tag);
                    if ($tag === '' || isset($terms[$key($tag)])) continue;
                    $terms[$key($tag)] = ['t' => $tag, 'url' => $base . '?tag=' . rawurlencode($tag)];
                }
            }
        }
        $terms = array_filter($terms, fn($t, $k) => !isset($black[$k]) && mb_strlen($t['t']) >= $o['min_len'], ARRAY_FILTER_USE_BOTH);
        uasort($terms, fn($a, $b) => mb_strlen($b['t']) <=> mb_strlen($a['t']));
        return $cache[$lang] = $terms;
    }

    /** Gancho 'content': enlaza los términos en los nodos de texto del HTML. */
    function enlaces_apply(string $html, array $ctx): string
    {
        static $onPage = 0;   // enlaces puestos en esta petición (= esta página), sumando todos los campos y bloques
        $o = enlaces_settings();
        if (!$o['on'] || trim($html) === '') return $html;
        if (function_exists('cms_is_demo') && cms_is_demo()) return $html;
        $item = is_array($ctx['item'] ?? null) ? $ctx['item'] : null;
        if ($item && !empty($item['autolink_off'])) return $html;
        if ($o['types'] && !in_array((string) ($ctx['type'] ?? ''), $o['types'], true)) return $html;
        if ($onPage >= $o['per_page']) return $html;
        $lang = (string) ($ctx['lang'] ?? cms_default_lang());
        $terms = enlaces_terms($lang);
        if (!$terms) return $html;
        // la propia página no se enlaza a sí misma
        $self = (string) (($ctx['page']['alt'] ?? [])[$lang] ?? '');
        $key = fn(string $t) => $o['case'] ? $t : mb_strtolower($t);
        static $re = [];
        if (!isset($re[$lang])) {
            $alts = array_map(fn($t) => preg_quote($t['t'], '~'), array_values($terms));
            $re[$lang] = '~(?<![\p{L}\p{N}_])(' . implode('|', $alts) . ')(?![\p{L}\p{N}_])~u' . ($o['case'] ? '' : 'i');
        }
        $skip = array_fill_keys(['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button', 'script', 'style', 'code', 'pre', 'kbd', 'samp', 'textarea', 'select', 'svg', 'iframe'], true);
        $parts = preg_split('~(<[^>]*>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) return $html;
        $stack = [];
        $used = [];
        foreach ($parts as $i => $p) {
            if ($p === '') continue;
            if ($p[0] === '<') {
                if (preg_match('~^<(/?)([a-z][a-z0-9]*)~i', $p, $m) && isset($skip[strtolower($m[2])])) {
                    $tag = strtolower($m[2]);
                    if ($m[1] === '') { if (substr($p, -2) !== '/>') $stack[] = $tag; }
                    else for ($j = count($stack) - 1; $j >= 0; $j--) if ($stack[$j] === $tag) { array_splice($stack, $j); break; }
                }
                continue;
            }
            if ($stack || trim($p) === '' || $onPage >= $o['per_page']) continue;
            $parts[$i] = preg_replace_callback($re[$lang], function (array $m) use (&$onPage, &$used, $o, $terms, $key, $self, $lang) {
                $k = $key($m[1]);
                $t = $terms[$k] ?? null;
                if (!$t || $onPage >= $o['per_page'] || ($used[$k] ?? 0) >= $o['per_term']) return $m[0];
                if ($self !== '' && enlaces_same_page($t['url'], $self)) return $m[0];
                $used[$k] = ($used[$k] ?? 0) + 1;
                $onPage++;
                return '<a href="' . cms_e($t['url']) . '" class="' . cms_e($o['class']) . '">' . $m[1] . '</a>';
            }, $p) ?? $p;
        }
        return implode('', $parts);
    }

    cms_on('content', 'enlaces_apply');
    cms_on('head', function (array $page): void {
        $o = enlaces_settings();
        if ($o['on'] && $o['css'] !== '') echo '<style>' . str_ireplace('</style', '', $o['css']) . '</style>' . "\n";
    });
}
