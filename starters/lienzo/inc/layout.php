<?php
/**
 * Lienzo — layout mínimo: solo <head> y <body>. La cabecera y el pie del sitio son bloques (cabecera, pie) que se
 * ponen en cada página desde el constructor. Para compartirlos entre páginas: 'site_sections' en Ajustes (pendiente).
 */
declare(strict_types=1);

function site_header(array $page): void
{
    $lang = $page['lang'];
    $S = cms_settings();
    $font = trim((string) ($S['font_custom'] ?? '')) ?: (trim((string) ($S['font'] ?? '')) ?: 'Inter');
    $fontH = trim((string) ($S['font_heading_custom'] ?? '')) ?: (trim((string) ($S['font_heading'] ?? '')) ?: $font);
    $families = array_unique([$font, $fontH]);
    $gf = implode('&', array_map(fn($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700;800', $families));
    $primary = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($S['color_primary'] ?? '')) ? $S['color_primary'] : '#111827';
    $accent = preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($S['color_accent'] ?? '')) ? $S['color_accent'] : '#2563eb';
    $aos = cms_libs()['aos'];
    ?><!DOCTYPE html>
<html lang="<?= cms_e($lang) ?>">
<head>
<?php cms_head($page); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?<?= cms_e($gf) ?>&display=swap">
<link rel="stylesheet" href="<?= cms_e($aos['css'][0]) ?>">
<link rel="stylesheet" href="<?= cms_asset('css/lienzo.css') ?>?v=<?= CMS_VERSION ?>">
<style>:root{--lz-font:"<?= cms_e($font) ?>",system-ui,sans-serif;--lz-font-heading:"<?= cms_e($fontH) ?>",system-ui,sans-serif;--lz-primary:<?= cms_e($primary) ?>;--lz-accent:<?= cms_e($accent) ?>;--cms-accent:<?= cms_e($accent) ?>;--cms-text:<?= cms_e($primary) ?>}</style>
</head>
<body class="lz<?= !empty($_GET['cmsbare']) ? ' cms-bare' : '' ?>">
<?php
}

function site_footer(array $page): void
{
    $aos = cms_libs()['aos'];
    ?>
<script defer src="<?= cms_e($aos['js'][0]) ?>"></script>
<script defer src="<?= cms_asset('js/lienzo.js') ?>?v=<?= CMS_VERSION ?>"></script>
</body>
</html>
<?php
}
