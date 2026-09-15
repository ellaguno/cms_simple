<?php
/** Paquete agencia — ayudas compartidas por sus bloques (y por los temas que quieran reutilizarlas). */
declare(strict_types=1);

if (!function_exists('ag_split')) {
    /** Divide una línea "a | b | c" en $n celdas (rellena con ''). */
    function ag_split(string $line, int $n): array { return array_pad(array_map('trim', explode('|', $line, $n)), $n, ''); }
}

if (!function_exists('ag_inline')) {
    /** HTML en línea permitido en títulos y textos cortos. */
    function ag_inline(string $s): string
    {
        $s = strip_tags($s, '<span><strong><em><b><i><br><small><a>');
        return preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s) ?? '';
    }
}

if (!function_exists('ag_svg')) {
    /** Iconos SVG del paquete (heredan currentColor). */
    function ag_svg(string $name): string
    {
        $icons = [
            'share'     => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>',
            'chevron-l' => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>',
            'chevron-r' => '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>',
            'facebook'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.6 1.6-1.6h1.7V4.3c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.2v2.4H7.4V14h2.8v8h3.3z"/></svg>',
            'x'         => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M17.5 3h3.1l-6.8 7.8L21.8 21h-6.3l-4.9-6.4L5 21H1.9l7.3-8.3L1.5 3h6.4l4.4 5.9L17.5 3zm-1.1 16.2h1.7L7 4.7H5.1l11.3 14.5z"/></svg>',
            'linkedin'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M6.9 21H3.2V8.7h3.7V21zM5 7.1A2.1 2.1 0 1 1 5 2.9a2.1 2.1 0 0 1 0 4.2zM21 21h-3.7v-6c0-1.4 0-3.3-2-3.3s-2.3 1.6-2.3 3.2V21H9.3V8.7h3.5v1.7h.1c.5-.9 1.7-1.9 3.5-1.9 3.7 0 4.4 2.5 4.4 5.7V21z"/></svg>',
            'whatsapp'  => '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.1.6a2.7 2.7 0 0 0 1.8-1.2c.2-.6.2-1.1.2-1.2l-.5-.3z"/></svg>',
            'link'      => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a3.5 3.5 0 0 0 5 0l4-4a3.5 3.5 0 0 0-5-5l-.5.5"/><path d="M14 10a3.5 3.5 0 0 0-5 0l-4 4a3.5 3.5 0 0 0 5 5l.5-.5"/></svg>',
        ];
        return $icons[$name] ?? '';
    }
}

if (!function_exists('ag_share_tab')) {
    /** Pestaña curva con el botón de compartir (Facebook, X, LinkedIn, WhatsApp, copiar enlace). Va al pie de una portada. */
    function ag_share_tab(string $lang, string $label = ''): string
    {
        $label = $label !== '' ? $label : ($lang === 'en' ? 'Share this page' : 'Compartir esta página');
        $copy = $lang === 'en' ? 'Copy link' : 'Copiar enlace';
        return '<div class="ag-tab"><button type="button" class="ag-share" data-ag-share aria-label="' . cms_e($label) . '" title="' . cms_e($label) . '">' . ag_svg('share') . '</button>'
            . '<div class="ag-share-menu" data-ag-share-menu hidden>'
            . '<a data-share="facebook" href="#" target="_blank" rel="noopener">' . ag_svg('facebook') . ' Facebook</a>'
            . '<a data-share="x" href="#" target="_blank" rel="noopener">' . ag_svg('x') . ' X</a>'
            . '<a data-share="linkedin" href="#" target="_blank" rel="noopener">' . ag_svg('linkedin') . ' LinkedIn</a>'
            . '<a data-share="whatsapp" href="#" target="_blank" rel="noopener">' . ag_svg('whatsapp') . ' WhatsApp</a>'
            . '<a data-share="copy" href="#">' . ag_svg('link') . ' ' . cms_e($copy) . '</a>'
            . '</div></div>';
    }
}

if (!function_exists('ag_team_members')) {
    /**
     * Personas del bloque Equipo: de una colección (nombre = title_field, puesto = excerpt_field, foto = image_field,
     * texto = body/bio/text, redes = campos url cuyo nombre sea una red) o de líneas "Nombre | Puesto | foto | Texto | URL | URL".
     * Cada una: ['name', 'role', 'photo', 'text' (HTML), 'links' => [[icono, url], …]].
     */
    function ag_team_members(array $b, string $lang): array
    {
        $out = [];
        $src = (string) ($b['source'] ?? 'manual');
        $nets = ['x', 'twitter', 'linkedin', 'facebook', 'instagram', 'behance', 'youtube', 'whatsapp', 'web', 'website', 'url'];
        $netIcon = function (string $k, string $url): string {
            $k = strtolower($k);
            if ($k === 'twitter') $k = 'x';
            if (in_array($k, ['web', 'website', 'url'], true)) $k = 'link';
            if ($k === 'link' || $k === '') {   // adivinar por la URL
                foreach (['x.com' => 'x', 'twitter.com' => 'x', 'linkedin.com' => 'linkedin', 'facebook.com' => 'facebook', 'instagram.com' => 'instagram', 'behance.net' => 'behance', 'youtube.com' => 'youtube', 'wa.me' => 'whatsapp'] as $dom => $ic) if (stripos($url, $dom) !== false) return $ic;
                return 'link';
            }
            return $k;
        };
        if ($src === 'manual' || (cms_is_demo() && ($src === '' || !cms_items($src)))) {
            $lines = (array) ($b['items'] ?? []);
            if (!$lines && cms_is_demo()) $lines = ['Ana Torres | Directora creativa | ' . cms_demo_image('persona', 1) . ' | Veinte años construyendo marcas para empresas que quieren decir algo. | https://linkedin.com/in/', 'Luis Peña | Estratega | ' . cms_demo_image('persona', 2) . ' | Convierte objetivos de negocio en mensajes claros. | https://x.com/'];
            foreach ($lines as $l) {
                $c = array_map('trim', explode('|', (string) $l));
                if (($c[0] ?? '') === '') continue;
                $links = [];
                foreach (array_slice($c, 4) as $u) if ($u !== '') $links[] = [$netIcon('', $u), $u];
                $out[] = ['name' => $c[0], 'role' => $c[1] ?? '', 'photo' => $c[2] ?? '', 'text' => ($c[3] ?? '') !== '' ? '<p>' . cms_e($c[3]) . '</p>' : '', 'links' => $links];
            }
            return $out;
        }
        $def = cms_type($src);
        if (!$def) return $out;
        $nameF = (string) ($def['title_field'] ?? 'title');
        $roleF = (string) ($def['excerpt_field'] ?? 'role');
        $photoF = (string) ($def['image_field'] ?? 'image');
        $textF = 'body';
        foreach (['body', 'bio', 'text', 'semblanza', 'intro'] as $f) if (isset($def['fields'][$f])) { $textF = $f; break; }
        $urlFields = [];
        foreach ((array) ($def['fields'] ?? []) as $k => $fd) if (($fd['type'] ?? '') === 'url' && in_array(strtolower((string) $k), $nets, true)) $urlFields[] = (string) $k;
        $n = (int) ($b['count'] ?? 0);
        $items = cms_items($src);
        if ($n > 0) $items = array_slice($items, 0, $n, true);
        foreach ($items as $it) {
            $full = cms_item($src, (string) $it['slug']) ?: $it;   // el índice ligero no trae el cuerpo
            $links = [];
            foreach ($urlFields as $f) if (!empty($it[$f])) $links[] = [$netIcon($f, (string) $it[$f]), (string) $it[$f]];
            $out[] = ['name' => (string) cms_f($it, $nameF, $lang), 'role' => (string) cms_f($it, $roleF, $lang), 'photo' => (string) ($it[$photoF] ?? ''),
                'text' => cms_content((string) cms_f($full, $textF, $lang)), 'links' => $links];
        }
        return $out;
    }
}
