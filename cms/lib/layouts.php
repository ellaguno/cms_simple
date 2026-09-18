<?php
/**
 * cms_simple — cabeceras y pies con nombre, hechos con el constructor (1.33).
 *
 * Son elementos de la colección interna "layouts" (Diseño → Cabeceras y pies): nombre, tipo (cabecera o pie) y una
 * lista de secciones como cualquier página. Cada página del constructor elige los suyos en la barra lateral
 * (campos layout_header / layout_footer, que el núcleo añade a todo tipo con un campo 'sections'); lo que no elige
 * nada usa el predeterminado de Ajustes → Cabecera y pie. El tema los dibuja llamando a cms_layout_header($page)
 * dentro de site_header() y a cms_layout_footer($page) dentro de site_footer(); si devuelven '' (no hay ninguno
 * elegido, o la función está desactivada con 'layouts' => false en config), el tema pone los suyos de siempre.
 */
declare(strict_types=1);

const CMS_LAYOUTS = 'layouts';

function cms_layouts_enabled(): bool
{
    return !empty(cms_config('types')[CMS_LAYOUTS]['internal']);
}

/** Cabeceras o pies guardados (slug => elemento ligero). $kind: header | footer | '' (todos). */
function cms_layouts(string $kind = '', bool $published_only = true): array
{
    if (!cms_layouts_enabled()) return [];
    $out = [];
    foreach (cms_items(CMS_LAYOUTS, $published_only) as $it) if ($kind === '' || (string) ($it['kind'] ?? 'header') === $kind) $out[$it['slug']] = $it;
    return $out;
}

/** Opciones del selector de una página: predeterminada, ninguna y las guardadas (los borradores, marcados). */
function cms_layout_options(string $kind): array
{
    $o = ['' => 'La predeterminada (Ajustes → Cabecera y pie)', 'none' => 'Ninguna'];
    foreach (cms_layouts($kind, false) as $slug => $it) $o[$slug] = (string) ($it['title'] ?? $slug) . (($it['status'] ?? '') !== 'published' ? ' (borrador)' : '');
    return $o;
}

/** Sección de Ajustes con los predeterminados (la dibuja settings.php como una sección más del tema). */
function cms_layouts_settings(): array
{
    if (!cms_layouts_enabled()) return [];
    $opts = function (string $kind): array {
        $o = ['' => 'Ninguna (la del tema)'];
        foreach (cms_layouts($kind, false) as $slug => $it) $o[$slug] = (string) ($it['title'] ?? $slug) . (($it['status'] ?? '') !== 'published' ? ' (borrador)' : '');
        return $o;
    };
    return ['Cabeceras y pies del constructor' => [
        'layout_header_default' => ['type' => 'select', 'label' => 'Cabecera predeterminada', 'options' => $opts('header'), 'half' => true,
            'help' => 'La usan las páginas que no eligen otra en su editor. Se crean y editan en Diseño → Cabeceras y pies.'],
        'layout_footer_default' => ['type' => 'select', 'label' => 'Pie predeterminado', 'options' => $opts('footer'), 'half' => true],
    ]];
}

/**
 * La cabecera o el pie que corresponde a la página actual (o a $page e $item dados): elemento completo o null.
 * Orden: la pieza en vista previa (su propio editor) → la elegida en la página → la predeterminada de Ajustes.
 * En vistas previas y en el constructor cuentan también los borradores; en el sitio público, solo lo publicado.
 */
function cms_layout(string $kind, ?array $page = null, ?array $item = null): ?array
{
    if (!cms_layouts_enabled()) return null;
    $kind = $kind === 'footer' ? 'footer' : 'header';
    $cur = cms_current();
    $page = $page ?? (array) $cur['page'];
    if (!empty($page['layout_preview']) && (string) ($page['layout_preview']['kind'] ?? 'header') === $kind) return $page['layout_preview'];
    $item = $item ?? $cur['item'];
    $choice = trim((string) ($item['layout_' . $kind] ?? ''));
    if ($choice === '') $choice = trim((string) (cms_settings()['layout_' . $kind . '_default'] ?? ''));
    if ($choice === '' || $choice === 'none') return null;
    $publishedOnly = empty($GLOBALS['cms_builder']) && empty($page['preview']);
    $it = cms_item(CMS_LAYOUTS, $choice, $publishedOnly);
    return $it && (string) ($it['kind'] ?? 'header') === $kind ? $it : null;
}

/** HTML de la cabecera o el pie de la página actual ('' si no hay). El tema lo imprime donde van los suyos. */
function cms_layout_render(string $kind, ?array $page = null): string
{
    $kind = $kind === 'footer' ? 'footer' : 'header';
    $GLOBALS['cms_layout_done'][$kind] = true;   // el tema sí los dibuja (la vista previa lo comprueba)
    $it = cms_layout($kind, $page);
    if (!$it) return '';
    $page = $page ?? (array) cms_current()['page'];
    $lang = (string) ($page['lang'] ?? cms_default_lang());
    $secs = array_values(array_filter((array) ($it['sections'] ?? []), fn($x) => is_array($x) && empty($x['hidden'])));
    if (!$secs) return '';
    // en el editor de la propia pieza sus secciones son clicables; en el de una página, no (son de otro elemento)
    $own = !empty($page['layout_preview']) && (string) ($page['layout_preview']['slug'] ?? '') === (string) $it['slug'];
    $ctx = ['lang' => $lang, 'S' => cms_settings(), 'page' => $page, 'item' => $it, 'builder' => $own];
    return '<!-- ' . $kind . ': ' . cms_e((string) $it['slug']) . " -->\n" . cms_sections_render($secs, $ctx);
}

function cms_layout_header(?array $page = null): string { return cms_layout_render('header', $page); }
function cms_layout_footer(?array $page = null): string { return cms_layout_render('footer', $page); }

/**
 * Vista previa de una cabecera o pie desde su editor: la página de muestra del tema con la pieza puesta. Si el tema
 * no llama a cms_layout_header()/cms_layout_footer(), la pieza se dibuja igualmente, con un aviso.
 */
function cms_layout_preview_page(array $page): void
{
    $kind = (string) ($page['layout_preview']['kind'] ?? 'header');
    $GLOBALS['cms_layout_done'] = [];
    ob_start(); site_header($page); $head = ob_get_clean();
    ob_start(); site_footer($page); $foot = ob_get_clean();
    $warn = '<div class="cms-layout-warn">El tema no dibuja las cabeceras y pies del constructor: añade <code>echo cms_layout_header($page);</code> en <code>site_header()</code> y <code>echo cms_layout_footer($page);</code> en <code>site_footer()</code> de <code>' . cms_e(CMS_SITE_REL) . '/inc/layout.php</code>. Mientras tanto se muestra aquí.</div>';
    echo $head;
    if ($kind === 'header' && empty($GLOBALS['cms_layout_done']['header'])) echo $warn, cms_layout_render('header', $page);
    echo '<main class="cms-layout-placeholder"><div><p><strong>', cms_e((string) ($page['layout_preview']['title'] ?? '')), '</strong></p><p>Aquí va el contenido de cada página. ',
        $kind === 'header' ? 'Esta cabecera se ve arriba tal como quedará en el sitio.' : 'Este pie se ve abajo tal como quedará en el sitio.', '</p></div></main>';
    if ($kind === 'footer' && empty($GLOBALS['cms_layout_done']['footer'])) echo $warn, cms_layout_render('footer', $page);
    echo $foot;
}
