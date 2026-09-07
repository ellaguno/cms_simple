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

/** Secciones compartidas [cabecera, pie] de la página de origen (Ajustes → Cabecera y pie compartidos), o [null, null]. */
function lz_shared(): array
{
    static $r = null;
    if ($r !== null) return $r;
    $S = cms_settings();
    $r = [null, null];
    if (empty($S['shared_on']) || trim((string) ($S['shared_source'] ?? '')) === '') return $r;
    $src = cms_item('paginas', cms_slugify((string) $S['shared_source']), false);
    if (!$src) return $r;
    foreach ((array) ($src['sections'] ?? []) as $sec) {
        if (!empty($sec['hidden'])) continue;
        if (($sec['type'] ?? '') === 'cabecera' && $r[0] === null) $r[0] = $sec;
        if (($sec['type'] ?? '') === 'pie') $r[1] = $sec;
    }
    return $r;
}

/** Migas de pan para páginas interiores (padre > hija); vacío en la portada y en páginas de primer nivel. */
function lz_crumbs(array $page): string
{
    $crumbs = (array) ($page['crumbs'] ?? []);
    if (count($crumbs) < 3) return '';
    $h = '<nav class="lz-crumbs" aria-label="Ruta"><div class="lz-container">';
    foreach ($crumbs as $i => [$label, $url]) {
        $last = $i === count($crumbs) - 1;
        $h .= $last ? '<span aria-current="page">' . cms_e($label) . '</span>' : '<a href="' . cms_e($url) . '">' . cms_e($label) . '</a><span class="lz-crumbs-sep">›</span>';
    }
    return $h . '</div></nav>';
}
