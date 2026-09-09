<?php
/** Utilidades compartidas por las vistas del paquete marketing. */
declare(strict_types=1);
if (!function_exists('mk_split')) {
    /** Celdas de una línea "a | b | c", rellenadas hasta $n. */
    function mk_split(string $line, int $n): array { return array_pad(array_map('trim', explode('|', $line, $n)), $n, ''); }
    /** ¿La celda es una imagen (ruta o URL) y no un icono? */
    function mk_is_image(string $v): bool { return $v !== '' && (preg_match('#\.(png|jpe?g|webp|gif|svg|avif)(\?.*)?$#i', $v) || preg_match('#^(https?:)?//|^(uploads|cms|site|assets)/#', $v)); }
    /** HTML del icono (emoji o Tabler ti-…) o de la imagen de una celda. */
    function mk_visual(string $v, string $alt = '', string $cls = 'mk-visual'): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (mk_is_image($v)) return '<img class="' . $cls . '-img" src="' . cms_e(cms_img($v)) . '" alt="' . cms_e($alt) . '" loading="lazy">';
        if (preg_match('/^ti-[a-z0-9-]+$/', $v)) return '<span class="' . $cls . ' ' . $cls . '-icon" aria-hidden="true"><i class="ti ' . cms_e($v) . '"></i></span>';
        return '<span class="' . $cls . ' ' . $cls . '-emoji" aria-hidden="true">' . cms_e($v) . '</span>';
    }
    /** Botón con las clases del tema y la variante del paquete. */
    function mk_btn(string $text, string $url, string $style = 'primary'): string
    {
        if (trim($text) === '') return '';
        $style = in_array($style, ['primary', 'outline', 'secondary'], true) ? $style : 'primary';
        return '<a class="' . cms_e(cms_block_class('btn')) . ' mk-btn mk-btn-' . $style . '" href="' . cms_e($url !== '' ? $url : '#') . '">' . cms_e($text) . '</a>';
    }
    /** Título con HTML sencillo permitido (span, strong, em, br). */
    function mk_inline(string $s): string { return preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', strip_tags($s, '<span><strong><em><b><i><br><small>')) ?? ''; }
}
