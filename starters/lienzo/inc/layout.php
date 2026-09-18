<?php
/**
 * Lienzo — layout mínimo: solo <head> y <body>. La cabecera y el pie del sitio son bloques (cabecera, pie) que se
 * ponen en cada página desde el constructor, o mejor (1.33) cabeceras y pies con nombre de Diseño → Cabeceras y pies,
 * que el núcleo dibuja con cms_layout_header() / cms_layout_footer(). Si no hay ninguno elegido, vale lo de antes:
 * Ajustes → "Cabecera y pie compartidos" (lz_shared) o los bloques de cada página.
 */
declare(strict_types=1);

function site_header(array $page): void
{
    $lang = $page['lang'];
    $S = cms_settings();
    // lo que el usuario eligió en Ajustes → Diseño; lo que deje vacío lo pone la variación de estilo (o el CSS del tema)
    $hasStyle = function_exists('cms_style') && cms_style() !== [];
    $font = trim((string) ($S['font_custom'] ?? '')) ?: trim((string) ($S['font'] ?? ''));
    $fontH = trim((string) ($S['font_heading_custom'] ?? '')) ?: trim((string) ($S['font_heading'] ?? ''));
    // tema hijo: tipografías propias por defecto (config 'theme_fonts' => [texto, títulos]) mientras la persona no elija otras
    $tf = (array) cms_config('theme_fonts', []);
    if (!$hasStyle && $font === '' && $fontH === '') { $font = (string) ($tf[0] ?? 'Inter'); $fontH = (string) ($tf[1] ?? ''); }
    elseif (!$hasStyle && $font === '') $font = (string) ($tf[0] ?? 'Inter');
    $families = array_values(array_unique(array_filter([$font, $fontH])));
    $gf = $families ? implode('&', array_map(fn($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700;800', $families)) : '';
    $child = defined('CMS_SITE_PARENT') && CMS_SITE_PARENT !== '';   // tema hijo: sus colores base los pone su CSS
    $primary = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($S['color_primary'] ?? '')) ? (string) $S['color_primary'] : ($hasStyle || $child ? '' : '#111827');
    $accent = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($S['color_accent'] ?? '')) ? (string) $S['color_accent'] : ($hasStyle || $child ? '' : '#2563eb');
    $vars = '';
    if ($font !== '') $vars .= '--lz-font:"' . cms_e($font) . '",system-ui,sans-serif;';
    if ($fontH !== '') $vars .= '--lz-font-heading:"' . cms_e($fontH) . '",system-ui,sans-serif;';
    elseif ($font !== '') $vars .= '--lz-font-heading:"' . cms_e($font) . '",system-ui,sans-serif;';
    if ($primary !== '') $vars .= '--lz-primary:' . cms_e($primary) . ';--cms-text:' . cms_e($primary) . ';';
    if ($accent !== '') $vars .= '--lz-accent:' . cms_e($accent) . ';--cms-accent:' . cms_e($accent) . ';';
    $aos = cms_libs()['aos'];
    ?><!DOCTYPE html>
<html lang="<?= cms_e($lang) ?>">
<head>
<?php cms_head($page); ?>
<?php if ($gf !== ''): ?><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?<?= cms_e($gf) ?>&display=swap">
<?php endif; ?><link rel="stylesheet" href="<?= cms_e($aos['css'][0]) ?>">
<link rel="stylesheet" href="<?= cms_asset('css/lienzo.css') ?>?v=<?= CMS_VERSION ?>">
<?php if (defined('CMS_SITE_PARENT') && CMS_SITE_PARENT !== '' && is_file(CMS_SITE . '/assets/css/tema.css')): // hoja del tema hijo ?><link rel="stylesheet" href="<?= cms_asset('css/tema.css') ?>?v=<?= cms_e((string) @filemtime(CMS_SITE . '/assets/css/tema.css')) ?>">
<?php endif; ?>
<?php if ($vars !== ''): ?><style>:root:root:root{<?= $vars ?>}</style>
<?php endif; ?>
</head>
<body class="lz<?= !empty($_GET['cmsbare']) ? ' cms-bare' : '' ?>">
<?php
    if (empty($_GET['cmsbare'])) {
        $core = function_exists('cms_layout_header') ? cms_layout_header($page) : '';
        if ($core !== '') echo $core;
        else { [$hdr] = lz_shared(); if ($hdr) echo cms_sections_render([$hdr], ['lang' => $lang, 'S' => $S, 'page' => $page, 'builder' => false]); }
    }
}

function site_footer(array $page): void
{
    $aos = cms_libs()['aos'];
    if (empty($_GET['cmsbare'])) {
        $core = function_exists('cms_layout_footer') ? cms_layout_footer($page) : '';
        if ($core !== '') echo $core;
        else { [, $ftr] = lz_shared(); if ($ftr) echo cms_sections_render([$ftr], ['lang' => $page['lang'], 'S' => cms_settings(), 'page' => $page, 'builder' => false]); }
    }
    ?>
<script defer src="<?= cms_e($aos['js'][0]) ?>"></script>
<script defer src="<?= cms_asset('js/lienzo.js') ?>?v=<?= CMS_VERSION ?>"></script>
</body>
</html>
<?php
}
