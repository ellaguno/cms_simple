<?php
/** Lienzo — ayudas del tema para las vistas de bloques. */
declare(strict_types=1);

/** Divide una línea "a | b | c" en $n celdas (rellena con ''). */
function lz_split(string $line, int $n): array { return array_pad(array_map('trim', explode('|', $line, $n)), $n, ''); }

/** "Texto > URL" → [texto, url]; sin ">" devuelve [texto, '']. */
function lz_link(string $cell): array { $p = array_map('trim', explode('>', $cell, 2)); return [$p[0], $p[1] ?? '']; }

/** HTML en línea permitido en títulos y textos cortos. */
function lz_inline(string $s): string
{
    $s = strip_tags($s, '<span><strong><em><b><i><br><small><a>');
    return preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s) ?? '';
}

function lz_href(string $url): string { $url = trim($url); return $url === '' ? '#' : $url; }

/** Botón con estilo primary | secondary | outline. */
function lz_btn(string $text, string $url, string $style = 'primary'): string
{
    if (trim($text) === '') return '';
    $style = in_array($style, ['primary', 'secondary', 'outline'], true) ? $style : 'primary';
    return '<a class="lz-btn lz-btn-' . $style . '" href="' . cms_e(lz_href($url)) . '">' . cms_e($text) . '</a>';
}

/** Cabecera de sección (título + subtítulo) con las clases del tema. */
function lz_head(string $title, string $subtitle = ''): string { return cms_block_header($title, $subtitle); }
