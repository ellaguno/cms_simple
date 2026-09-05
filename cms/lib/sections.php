<?php
/**
 * cms_simple — secciones (bloques de página definidos por el tema).
 *
 * El tema declara sus bloques en site/blocks.php:
 *   return ['hero' => ['label' => 'Hero', 'group' => 'Cabeceras', 'desc' => '…', 'fields' => [ …campos como en config… ],
 *                      'styles' => ['bg', 'pad', 'width', …] (controles de estilo permitidos; ausente = todos; [] = ninguno),
 *                      'wrap_class' => 'hero' (clases extra del <section>), 'wrap_class_by' => ['field' => 'variant', 'map' => [valor => clase]],
 *                      'animate' => 'fade-up' (animación por defecto)], …];
 * y dibuja cada uno en site/blocks/<clave>.php con $b (datos con valores por defecto), $st (estilo), $sec (id, índice),
 * $ctx (lang, S, t, page, item…). El núcleo envuelve cada bloque en <section class="sec sec-<clave> …"> con las clases
 * de estilo (sec-bg-*, sec-text-*, sec-pad-*, sec-w-*, sec-align-*) que el tema implementa en su CSS.
 *
 * Un campo de tipo 'sections' guarda: [['id' => 'a1b2', 'type' => 'hero', 'data' => […], 'style' => […], 'hidden' => bool], …]
 */
declare(strict_types=1);

/** Definiciones de bloques del tema (site/blocks.php), con la clave dentro. */
function cms_blocks(): array
{
    static $b = null;
    if ($b === null) {
        $b = [];
        $file = CMS_SITE . '/blocks.php';
        if (is_file($file)) foreach ((array) require $file as $k => $d) $b[$k] = (array) $d + ['key' => $k, 'label' => ucfirst($k), 'fields' => [], 'file' => CMS_SITE . '/blocks/' . preg_replace('/[^a-z0-9_-]/i', '', (string) $k) . '.php'];
        $b += cms_pack_blocks();
        foreach ((array) cms_config('block_exclude', []) as $k) unset($b[$k]);
    }
    return $b;
}

/** Definición de un bloque; admite alias (config 'block_aliases' => ['viejo' => 'paquete/nuevo']). */
function cms_block(string $type): ?array
{
    $b = cms_blocks();
    if (isset($b[$type])) return $b[$type];
    $alias = (array) cms_config('block_aliases', []);
    return isset($alias[$type], $b[$alias[$type]]) ? $b[$alias[$type]] : null;
}

/** Paleta de fondos del tema (config 'sections' => ['palette' => [clave => etiqueta]]). */
function cms_sections_palette(): array
{
    $p = (array) (cms_config('sections')['palette'] ?? []);
    return $p ?: ['white' => 'Blanco', 'light' => 'Gris claro', 'dark' => 'Oscuro', 'primary' => 'Color principal', 'gradient' => 'Degradado'];
}

/** Vocabulario de estilo de una sección (el mismo para todos los temas; el tema implementa las clases). */
function cms_section_styles(): array
{
    return [
        'bg'          => ['type' => 'select', 'label' => 'Fondo', 'options' => ['' => 'Por defecto del bloque'] + cms_sections_palette()],
        'text'        => ['type' => 'select', 'label' => 'Color del texto', 'options' => ['' => 'Automático', 'dark' => 'Oscuro', 'light' => 'Claro']],
        'pad'         => ['type' => 'select', 'label' => 'Espacio vertical', 'options' => ['' => 'Normal', 'none' => 'Sin espacio', 's' => 'Pequeño', 'm' => 'Medio', 'l' => 'Grande', 'xl' => 'Muy grande']],
        'width'       => ['type' => 'select', 'label' => 'Ancho del contenido', 'options' => ['' => 'Normal', 'narrow' => 'Estrecho (texto)', 'wide' => 'Ancho', 'full' => 'Todo el ancho']],
        'align'       => ['type' => 'select', 'label' => 'Alineación', 'options' => ['' => 'Por defecto', 'left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha']],
        'animate'     => ['type' => 'select', 'label' => 'Animación al aparecer', 'options' => ['' => 'Por defecto', 'none' => 'Ninguna', 'fade-up' => 'Subir y aparecer', 'fade-in' => 'Aparecer', 'zoom-in' => 'Acercar']],
        'bg_image'    => ['type' => 'image', 'label' => 'Imagen de fondo'],
        'overlay'     => ['type' => 'number', 'label' => 'Oscurecer la imagen de fondo (0 a 90 %)', 'min' => 0, 'max' => 90, 'step' => 10],
        'effect'      => ['type' => 'select', 'label' => 'Efecto', 'options' => ['' => 'Ninguno'] + array_map(fn($e) => (string) $e['label'], cms_effects())],
        'anchor'      => ['type' => 'text', 'label' => 'Ancla (id para enlaces #ancla)', 'placeholder' => 'contacto'],
        'class'       => ['type' => 'text', 'label' => 'Clases CSS adicionales (avanzado)'],
        'hide_mobile' => ['type' => 'checkbox', 'label' => 'Móvil', 'text' => 'Ocultar en pantallas pequeñas'],
    ];
}

/** Controles de estilo que admite un bloque. */
function cms_block_styles(array $def): array
{
    $all = cms_section_styles();
    if (!cms_effects()) unset($all['effect']);
    if (!array_key_exists('styles', $def)) return $all;
    return array_intersect_key($all, array_flip((array) $def['styles']));
}

function cms_section_id(): string
{
    return substr(bin2hex(random_bytes(4)), 0, 6);
}

/** Datos del bloque con los valores por defecto de su definición. */
function cms_block_data(array $def, array $data): array
{
    foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
        if (!array_key_exists($k, $data) || $data[$k] === '' || $data[$k] === null) {
            if (array_key_exists('default', $fd)) $data[$k] = $fd['default'];
            elseif (in_array($fd['type'] ?? 'text', ['lines', 'images', 'tags'], true)) $data[$k] = [];
            elseif (!array_key_exists($k, $data)) $data[$k] = '';
        }
    }
    return $data;
}

/** HTML de una lista de secciones. $ctx: lang, S, t, page, item, builder (bool). */
function cms_sections_render(array $sections, array $ctx = []): string
{
    $blocks = cms_blocks();
    $lang = (string) ($ctx['lang'] ?? cms_default_lang());
    $S = $ctx['S'] ?? cms_settings();
    $t = $ctx['t'] ?? fn(string $k, $d = '') => cms_t($k, $lang, $d);
    $page = $ctx['page'] ?? [];
    $item = $ctx['item'] ?? null;
    $builder = array_key_exists('builder', $ctx) ? !empty($ctx['builder']) : !empty($GLOBALS['cms_builder']);
    $out = '';
    foreach (array_values($sections) as $i => $sec) {
        if (!is_array($sec) || !empty($sec['hidden'])) continue;
        $type = (string) ($sec['type'] ?? '');
        $def = cms_block($type);
        $file = (string) ($def['file'] ?? '');
        if (!$def || !is_file($file)) continue;
        $type = (string) $def['key'];
        $b = cms_block_data($def, (array) ($sec['data'] ?? []));
        $st = (array) ($sec['style'] ?? []);
        $id = (string) ($sec['id'] ?? ('s' . $i));
        $cls = ['sec', 'sec-' . $type];
        if (!empty($def['wrap_class'])) foreach (preg_split('/\s+/', (string) $def['wrap_class']) as $c) if ($c !== '') $cls[] = $c;
        if (!empty($def['wrap_class_by']['field'])) { $wv = (string) ($b[$def['wrap_class_by']['field']] ?? ''); if (!empty($def['wrap_class_by']['map'][$wv])) $cls[] = (string) $def['wrap_class_by']['map'][$wv]; }
        foreach (['bg', 'text', 'pad', 'width', 'align'] as $k) if (!empty($st[$k])) $cls[] = 'sec-' . ($k === 'width' ? 'w' : $k) . '-' . preg_replace('/[^a-z0-9-]/i', '', (string) $st[$k]);
        if (!empty($st['hide_mobile'])) $cls[] = 'sec-hide-mobile';
        if (!empty($st['bg_image'])) $cls[] = 'sec-has-bg';
        if (!empty($st['class'])) foreach (preg_split('/\s+/', (string) $st['class']) as $c) if ($c !== '' && preg_match('/^[a-z0-9_-]+$/i', $c)) $cls[] = $c;
        $attrs = ' class="' . cms_e(implode(' ', $cls)) . '"';
        $anchor = !empty($st['anchor']) ? cms_slugify((string) $st['anchor']) : '';
        $attrs .= ' id="' . cms_e($anchor !== '' ? $anchor : 'sec-' . $id) . '"';
        $style = '';
        if (!empty($st['bg_image'])) $style .= '--sec-bg:url(' . cms_e(cms_img((string) $st['bg_image'])) . ');';
        if (isset($st['overlay']) && $st['overlay'] !== '') $style .= '--sec-overlay:' . (int) $st['overlay'] / 100 . ';';
        if ($style !== '') $attrs .= ' style="' . $style . '"';
        $anim = (string) ($st['animate'] ?? '');
        if ($anim === '') $anim = (string) ($def['animate'] ?? '');
        if ($anim !== '' && $anim !== 'none') $attrs .= ' data-aos="' . cms_e($anim) . '"';
        if ($builder) $attrs .= ' data-sec-id="' . cms_e($id) . '"';
        $attrs .= ' data-block="' . cms_e($type) . '"';
        $secMeta = ['id' => $id, 'index' => $i, 'type' => $type, 'anchor' => $anchor];
        // efectos: los del bloque, los elegidos en Estilo y los que la vista añada con cms_section_effect()
        $GLOBALS['cms_current_effects'] = array_values(array_unique(array_merge((array) ($def['effects'] ?? []), cms_section_effect_list($st))));
        ob_start();
        try {
            (static function () use ($file, $b, $st, $secMeta, $ctx, $lang, $S, $t, $page, $item) {
                $sec = $secMeta;
                require $file;
            })();
        } catch (\Throwable $e) {
            ob_end_clean();
            $out .= '<!-- bloque ' . cms_e($type) . ': ' . cms_e($e->getMessage()) . ' -->';
            continue;
        }
        $inner = ob_get_clean();
        $fx = array_values(array_filter((array) ($GLOBALS['cms_current_effects'] ?? []), fn($e) => preg_match('#^[a-z0-9_-]+/[a-z0-9_-]+$#i', (string) $e)));
        if ($fx) $attrs .= ' data-effect="' . cms_e(implode(' ', $fx)) . '"';
        $out .= '<section' . $attrs . '>' . $inner . '</section>' . "\n";
    }
    if ($builder) $out .= cms_sections_builder_script();
    return $out;
}

/** Script de la vista previa del constructor: clic en una sección → el panel la abre; el panel → resaltar/desplazar. */
function cms_sections_builder_script(): string
{
    return '<style>[data-sec-id]{cursor:pointer;position:relative}[data-sec-id]:hover{outline:2px dashed rgba(79,70,229,.5);outline-offset:-2px}[data-sec-id].sec-selected{outline:3px solid #4f46e5;outline-offset:-3px}</style>'
        . '<script>(function(){var P=window.parent;if(!P||P===window)return;'
        . 'document.querySelectorAll("[data-sec-id]").forEach(function(s){s.addEventListener("click",function(e){if(e.target.closest("a,button,input,select,textarea,iframe"))e.preventDefault();P.postMessage({cmsSec:s.getAttribute("data-sec-id")},"*");});});'
        . 'var tm;window.addEventListener("scroll",function(){clearTimeout(tm);tm=setTimeout(function(){P.postMessage({cmsScroll:window.scrollY},"*");},150);},{passive:true});'
        . 'window.addEventListener("message",function(e){var d=e.data||{};if(typeof d.cmsScrollTo==="number")window.scrollTo(0,d.cmsScrollTo);'
        . 'if(d.cmsHighlight!==undefined){document.querySelectorAll(".sec-selected").forEach(function(x){x.classList.remove("sec-selected");});var s=document.querySelector("[data-sec-id=\\""+d.cmsHighlight+"\\"]");if(s){s.classList.add("sec-selected");if(d.cmsScrollIntoView)s.scrollIntoView({behavior:"smooth",block:"start"});}}});'
        . 'P.postMessage({cmsReady:1},"*");})();</script>';
}

/** Texto plano corto de una sección (para el título de la tarjeta en el panel). */
function cms_section_summary(array $sec): string
{
    $def = cms_block((string) ($sec['type'] ?? ''));
    if (!$def) return '';
    foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
        if (in_array($fd['type'] ?? 'text', ['text', 'textarea', 'html'], true)) {
            $v = $sec['data'][$k] ?? '';
            if (is_array($v)) $v = reset($v);
            $v = trim(preg_replace('/\s+/', ' ', strip_tags((string) $v)));
            if ($v !== '') return mb_substr($v, 0, 70) . (mb_strlen($v) > 70 ? '…' : '');
        }
    }
    return '';
}

/** Desde la vista de un bloque: añade (o quita, con $on = false) un efecto a la sección que se está dibujando.
 *  Los recursos (CSS/JS) de un efecto solo se cargan si está declarado en 'effects' de la definición del bloque o elegido en Estilo,
 *  porque el <head> se calcula antes de dibujar; la vista decide si al final la sección lo lleva o no. */
function cms_section_effect(string $effect, bool $on = true): void
{
    $cur = (array) ($GLOBALS['cms_current_effects'] ?? []);
    if ($on) { if (!in_array($effect, $cur, true)) $cur[] = $effect; }
    else $cur = array_values(array_filter($cur, fn($e) => $e !== $effect));
    $GLOBALS['cms_current_effects'] = $cur;
}

/** Clases del tema para los bloques de paquetes (config 'sections' => ['classes' => ['container' => …, 'header' => …, 'title' => …, 'subtitle' => …, 'btn' => …]]). */
function cms_block_class(string $what): string
{
    $map = (array) (cms_config('sections')['classes'] ?? []);
    $defaults = ['container' => 'cms-container', 'header' => 'cms-header', 'title' => '', 'subtitle' => '', 'btn' => 'cms-btn'];
    return trim(($defaults[$what] ?? '') . ' ' . (string) ($map[$what] ?? ''));
}

/** Cabecera estándar de un bloque de paquete: título (HTML sencillo permitido) y subtítulo. */
function cms_block_header(string $title, string $subtitle = '', string $extraClass = ''): string
{
    if (trim($title) === '' && trim($subtitle) === '') return '';
    $clean = fn(string $x) => preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', strip_tags($x, '<span><strong><em><b><i><br><small>')) ?? '';
    $h = '<div class="' . cms_e(trim(cms_block_class('header') . ' ' . $extraClass)) . '">';
    if (trim($title) !== '') $h .= '<h2 class="' . cms_e(cms_block_class('title')) . '">' . $clean($title) . '</h2>';
    if (trim($subtitle) !== '') $h .= '<p class="' . cms_e(cms_block_class('subtitle')) . '">' . $clean($subtitle) . '</p>';
    return $h . '</div>';
}
