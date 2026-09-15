<?php
/** All Design — ayudas del tema para el layout, los bloques y las plantillas. */
declare(strict_types=1);

/** Divide una línea "a | b | c" en $n celdas (rellena con ''). */
function ad_split(string $line, int $n): array { return array_pad(array_map('trim', explode('|', $line, $n)), $n, ''); }

/** HTML en línea permitido en títulos y textos cortos. */
function ad_inline(string $s): string
{
    $s = strip_tags($s, '<span><strong><em><b><i><br><small><a>');
    return preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s) ?? '';
}

/** Texto de un campo de líneas: *palabra* → <strong>, "//" → salto de párrafo. */
function ad_rich(string $s): string
{
    $parts = array_filter(array_map('trim', explode('//', $s)), 'strlen');
    $out = '';
    foreach ($parts as $p) $out .= '<p>' . preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', cms_e($p)) . '</p>';
    return $out;
}

function ad_href(string $url): string { $url = trim($url); return $url === '' ? '#' : $url; }

/** URL interna del tema (rutas relativas del menú → con base del sitio). */
function ad_url(string $url, string $lang): string
{
    if ($url === '' || preg_match('#^(https?:)?//|^mailto:|^tel:|^\##i', $url)) return $url;
    return cms_menu_url($url, $lang);
}

/** Miniatura cuadrada/recortada: usa la miniatura si existe, si no la imagen grande (el recorte lo hace el CSS). */
function ad_thumb(array $item): string
{
    $t = (string) ($item['thumb'] ?? '');
    return $t !== '' ? $t : (string) ($item['image'] ?? '');
}

/** Iconos SVG propios del tema (trazo blanco, heredan currentColor). */
function ad_svg(string $name): string
{
    $icons = [
        'share'    => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>',
        'arrow'    => '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M13 5l8 6-8 6v-4c-5 0-8.5 1.5-11 5 .8-5.5 4-9 11-9.5V5z"/></svg>',
        'plus'     => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
        'minus'    => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>',
        'chevron-l'=> '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>',
        'chevron-r'=> '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>',
        'menu'     => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        'lines'    => '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 8h16M4 12h16M4 16h16"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.6 1.6-1.6h1.7V4.3c-.3 0-1.3-.1-2.5-.1-2.5 0-4.1 1.5-4.1 4.2v2.4H7.4V14h2.8v8h3.3z"/></svg>',
        'x'        => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M17.5 3h3.1l-6.8 7.8L21.8 21h-6.3l-4.9-6.4L5 21H1.9l7.3-8.3L1.5 3h6.4l4.4 5.9L17.5 3zm-1.1 16.2h1.7L7 4.7H5.1l11.3 14.5z"/></svg>',
        'linkedin' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M6.9 21H3.2V8.7h3.7V21zM5 7.1A2.1 2.1 0 1 1 5 2.9a2.1 2.1 0 0 1 0 4.2zM21 21h-3.7v-6c0-1.4 0-3.3-2-3.3s-2.3 1.6-2.3 3.2V21H9.3V8.7h3.5v1.7h.1c.5-.9 1.7-1.9 3.5-1.9 3.7 0 4.4 2.5 4.4 5.7V21z"/></svg>',
        'instagram'=> '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>',
        'behance'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8.6 11.3c1-.5 1.5-1.3 1.5-2.4 0-2.2-1.6-2.9-3.6-2.9H1v12h5.7c2.2 0 4.2-1 4.2-3.5 0-1.6-.7-2.7-2.3-3.2zM3.6 8h2.6c1 0 1.9.3 1.9 1.4 0 1-.7 1.5-1.7 1.5H3.6V8zm3 8H3.6v-3.4h3.1c1.2 0 2 .5 2 1.8 0 1.2-.9 1.6-2.1 1.6zM15.7 14.4c.1 1.4.9 2 2.1 2 .9 0 1.6-.5 1.9-1.1H22c-.6 2-2.1 3-4.3 3-2.9 0-4.5-2-4.5-4.7 0-2.6 1.8-4.7 4.5-4.7 2.9 0 4.4 2.3 4.3 5.5h-6.3zm0-1.6h3.9c-.1-1.2-.7-1.9-1.9-1.9-1.2 0-1.9.8-2 1.9zM15 6h5v1.2h-5V6z"/></svg>',
        'youtube'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M22.5 7.2a2.8 2.8 0 0 0-2-2C18.8 4.8 12 4.8 12 4.8s-6.8 0-8.5.4a2.8 2.8 0 0 0-2 2C1 8.9 1 12 1 12s0 3.1.5 4.8a2.8 2.8 0 0 0 2 2c1.7.4 8.5.4 8.5.4s6.8 0 8.5-.4a2.8 2.8 0 0 0 2-2c.5-1.7.5-4.8.5-4.8s0-3.1-.5-4.8zM9.8 15.1V8.9l5.7 3.1-5.7 3.1z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.1.6a2.7 2.7 0 0 0 1.8-1.2c.2-.6.2-1.1.2-1.2l-.5-.3z"/></svg>',
    ];
    return $icons[$name] ?? '';
}

/** Iconos de redes de Ajustes → Contacto y redes, con los SVG del tema. */
function ad_social(string $cls = ''): string
{
    $out = '';
    foreach (cms_social_links() as $l) $out .= '<a href="' . cms_e($l['url']) . '" target="_blank" rel="noopener" aria-label="' . cms_e($l['label']) . '" title="' . cms_e($l['label']) . '">' . (ad_svg($l['key']) ?: cms_icon($l['key'])) . '</a>';
    return $out === '' ? '' : '<div class="ad-social ' . cms_e($cls) . '">' . $out . '</div>';
}

/** Páginas hijas de la página Portafolio (Ajustes → portfolio_page): el menú desplegable. */
function ad_portfolio_children(): array
{
    static $r = null;
    if ($r !== null) return $r;
    $slug = cms_slugify((string) (cms_settings()['portfolio_page'] ?? 'portafolio')) ?: 'portafolio';
    $r = cms_tree_children('paginas', $slug);
    usort($r, fn($a, $b) => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));
    return $r;
}

/** ¿El enlace del menú es la página Portafolio? */
function ad_is_portfolio_link(string $url): bool
{
    $slug = cms_slugify((string) (cms_settings()['portfolio_page'] ?? 'portafolio')) ?: 'portafolio';
    return trim($url, '/') === $slug;
}

/** Elementos de la galería a partir de la colección Proyectos (o de líneas escritas a mano). Cada uno: [thumb, full, title, desc, url, slug]. */
function ad_gallery_items(array $b, string $lang): array
{
    $items = [];
    $src = (string) ($b['source'] ?? 'category');
    if ($src === 'manual') {
        foreach ((array) $b['images'] as $l) {
            [$img, $title, $desc] = ad_split((string) $l, 3);
            if ($img !== '') $items[] = ['thumb' => $img, 'full' => $img, 'title' => $title, 'desc' => $desc !== '' ? '<p>' . cms_e($desc) . '</p>' : '', 'url' => '', 'slug' => ''];
        }
        return $items;
    }
    if (cms_is_demo()) {
        for ($i = 1; $i <= 6; $i++) $items[] = ['thumb' => cms_demo_image('foto', $i), 'full' => cms_demo_image('foto', $i), 'title' => 'Proyecto ' . $i, 'desc' => '<p>Descripción de ejemplo del proyecto.</p>', 'url' => '', 'slug' => ''];
        return $items;
    }
    $all = cms_items('proyectos');
    if ($src === 'category') {
        $cat = trim((string) ($b['category'] ?? ''));
        if ($cat !== '') {
            $slug = cms_category_slug_of('proyectos', $cat) ?: cms_slugify($cat);
            $all = array_filter($all, fn($p) => cms_item_category('proyectos', $p) === $slug || cms_slugify((string) cms_f($p, 'category', $lang)) === cms_slugify($cat));
        }
    } elseif ($src === 'featured') {
        $all = array_filter($all, fn($p) => !empty($p['featured']));
    }
    $n = (int) ($b['count'] ?? 0);
    if ($n > 0) $all = array_slice($all, 0, $n, true);
    foreach ($all as $p) {
        $full = cms_item('proyectos', (string) $p['slug']);   // el índice ligero no trae el cuerpo
        $items[] = ['thumb' => ad_thumb($p), 'full' => (string) ($p['image'] ?? ''), 'title' => (string) cms_f($p, 'title', $lang),
            'desc' => cms_content((string) cms_f($full ?: $p, 'body', $lang)), 'url' => (string) ($p['url'] ?? ''), 'slug' => (string) $p['slug']];
    }
    return $items;
}

/** Contenido HTML del visor de un proyecto (título, descripción y enlace). */
function ad_gallery_caption(array $it, string $lang): string
{
    $h = '<h3 class="ad-visor-title">' . cms_e($it['title']) . '</h3>';
    if ($it['desc'] !== '') $h .= '<div class="ad-visor-desc">' . $it['desc'] . '</div>';
    if ($it['url'] !== '') $h .= '<p class="ad-visor-link"><a href="' . cms_e($it['url']) . '" target="_blank" rel="noopener">' . cms_e(cms_t('gallery_visit', $lang, 'Visitar sitio')) . ' →</a></p>';
    return $h;
}
