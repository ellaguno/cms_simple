<?php
/**
 * Layout de All Design: site_header() = <head> (metadatos del núcleo con cms_head) + barra social, logotipo y menú;
 * site_footer() = pie oscuro con logotipo blanco, menús, contacto y redes, más los scripts del tema.
 */
declare(strict_types=1);

function site_header(array $page): void
{
    $lang = $page['lang'];
    $S = cms_settings();
    $t = fn(string $k, $d = '') => cms_t($k, $lang, $d);
    $site = $S['site_name'] ?? 'All Design';
    $path = trim((string) ($page['path'] ?? ''), '/');
    $font = trim((string) ($S['font'] ?? '')) ?: 'Open Sans';
    $fontMenu = trim((string) ($S['font_menu'] ?? '')) ?: 'Open Sans';
    $fam = [];
    foreach (array_unique([$font, $fontMenu]) as $f) $fam[] = $f === 'Open Sans' ? 'family=Open+Sans:ital,wdth,wght@0,75,300;0,100,300;0,100,400;0,100,600;1,100,300' : 'family=' . str_replace(' ', '+', $f) . ':wght@300;400;600';
    $fam[] = 'family=M+PLUS+1p:wght@300;400';
    $fam[] = 'family=Josefin+Sans:wght@600';
    $gf = 'https://fonts.googleapis.com/css2?' . implode('&', $fam) . '&display=swap';
    $vars = '';
    if (preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($S['color_accent'] ?? ''))) $vars .= '--ad-accent:' . $S['color_accent'] . ';--cms-accent:' . $S['color_accent'] . ';';
    if (preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($S['color_bg'] ?? ''))) $vars .= '--ad-bg:' . $S['color_bg'] . ';';
    if ($font !== 'Open Sans') $vars .= '--ad-font:"' . cms_e($font) . '",sans-serif;';
    if ($fontMenu !== 'Open Sans') $vars .= '--ad-font-menu:"' . cms_e($fontMenu) . '",sans-serif;';
    $logo = cms_img($S['logo'] ?? 'logo.png');
    $home = cms_url('home', $lang);
    $bare = !empty($_GET['cmsbare']);
    ?><!DOCTYPE html>
<html lang="es-MX">
<head>
<?php cms_head($page); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= cms_e($gf) ?>">
<link rel="stylesheet" href="<?= cms_asset('css/alldesign.css') ?>?v=<?= CMS_VERSION ?>">
<?php if ($vars !== ''): ?><style>:root:root:root{<?= $vars ?>}</style>
<?php endif; ?>
</head>
<body class="ad<?= $bare ? ' cms-bare' : '' ?>">
<?php if ($bare): echo '<main class="ad-main">'; return; endif; ?>
<a class="ad-skip" href="#contenido">Ir al contenido</a>
<?php $coreHdr = function_exists('cms_layout_header') ? cms_layout_header($page) : ''; if ($coreHdr !== ''): echo $coreHdr; else: // cabecera con nombre (Diseño → Cabeceras y pies) o la del tema ?>
<header class="ad-header" id="top">
  <div class="ad-topbar">
    <div class="ad-container ad-topbar-in">
      <?= ad_social('ad-social-top') ?>
    </div>
  </div>
  <div class="ad-navbar">
    <div class="ad-container ad-navbar-in">
      <a class="ad-logo" href="<?= $home ?>" aria-label="<?= cms_e($site) ?>"><img src="<?= cms_e($logo) ?>" alt="<?= cms_e($site) ?>" width="106" height="48"></a>
      <button class="ad-nav-toggle" type="button" aria-expanded="false" aria-controls="ad-nav" data-ad-nav-toggle><?= ad_svg('menu') ?><span><?= cms_e($t('menu_label', 'MENU')) ?></span></button>
      <nav class="ad-nav" id="ad-nav" aria-label="Principal">
        <ul>
<?php foreach (cms_menu($lang) as $it):
    $url = (string) ($it['url'] ?? '/');
    $rel = trim($url, '/');
    $ext = (bool) preg_match('#^(https?:)?//#', $url);
    $active = (!$ext && (($rel === '' && $path === '') || ($rel !== '' && ($path === $rel || strpos($path, $rel . '/') === 0))));
    $target = !empty($it['new_tab']) ? ' target="_blank" rel="noopener"' : '';
    $children = (!$ext && ad_is_portfolio_link($url)) ? ad_portfolio_children() : [];
    ?>
          <li class="<?= $active ? 'is-active' : '' ?><?= $children ? ' has-sub' : '' ?>">
            <a href="<?= cms_e(ad_url($url, $lang)) ?>"<?= $target ?>><?= cms_e($it['label'] ?? '') ?><?= $children ? ' ' . ad_svg('lines') : '' ?></a>
<?php if ($children): ?>
            <button type="button" class="ad-sub-toggle" aria-label="Abrir submenú" data-ad-sub-toggle><?= ad_svg('plus') ?></button>
            <ul class="ad-sub">
<?php foreach ($children as $c): $curl = cms_url('item:paginas', $lang, (string) $c['slug']); ?>
              <li><a href="<?= cms_e($curl) ?>"<?= $path === trim((string) ($c['path'] ?? $c['slug']), '/') ? ' aria-current="page"' : '' ?>><?= cms_e(cms_f($c, 'menu_label', $lang) ?: cms_f($c, 'title', $lang)) ?></a></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </div>
</header>
<?php endif; ?>
<main class="ad-main" id="contenido">
<?php
}

function site_footer(array $page): void
{
    $lang = $page['lang'];
    $S = cms_settings();
    $t = fn(string $k, $d = '') => cms_t($k, $lang, $d);
    $site = $S['site_name'] ?? 'All Design';
    echo "\n</main>\n";
    if (empty($_GET['cmsbare'])):
    $logoLight = cms_img($S['logo_light'] ?? 'logo-blanco.png');
    $children = ad_portfolio_children();
    $tel = cms_tel_href();
    $coreFtr = function_exists('cms_layout_footer') ? cms_layout_footer($page) : '';
    if ($coreFtr !== ''): echo $coreFtr; else: // pie con nombre (Diseño → Cabeceras y pies) o el del tema ?>
<footer class="ad-footer">
  <div class="ad-container">
    <div class="ad-footer-top">
      <a class="ad-footer-logo" href="<?= cms_url('home', $lang) ?>"><img src="<?= cms_e($logoLight) ?>" alt="<?= cms_e($site) ?>" width="97" height="44"></a>
<?php if (!empty($S['footer_text'])): ?>      <p class="ad-footer-text"><?= nl2br(cms_e((string) $S['footer_text'])) ?></p>
<?php endif; ?>
    </div>
    <div class="ad-footer-cols">
      <nav class="ad-footer-col" aria-label="Secciones">
        <ul>
<?php foreach (cms_menu($lang) as $it): ?>
          <li><a href="<?= cms_e(ad_url((string) ($it['url'] ?? '/'), $lang)) ?>"><?= cms_e($it['label'] ?? '') ?></a></li>
<?php endforeach; ?>
        </ul>
      </nav>
<?php if ($children): ?>
      <nav class="ad-footer-col" aria-label="<?= cms_e($t('menu_portfolio', 'Portafolio')) ?>">
        <ul>
<?php foreach ($children as $c): ?>
          <li><a href="<?= cms_e(cms_url('item:paginas', $lang, (string) $c['slug'])) ?>"><?= cms_e(cms_f($c, 'menu_label', $lang) ?: cms_f($c, 'title', $lang)) ?></a></li>
<?php endforeach; ?>
        </ul>
      </nav>
<?php endif; ?>
      <div class="ad-footer-col ad-footer-contact">
        <h4><?= cms_e($t('footer_contact', 'Contacto')) ?></h4>
<?php if (!empty($S['address'])): ?>        <p><?= nl2br(cms_e((string) $S['address'])) ?></p>
<?php endif; ?>
<?php if (!empty($S['phone'])): ?>        <p><a href="<?= cms_e($tel) ?>"><?= cms_e($S['phone']) ?></a></p>
<?php endif; ?>
<?php if (!empty($S['email'])): ?>        <p><a href="mailto:<?= cms_e($S['email']) ?>"><?= cms_e($S['email']) ?></a></p>
<?php endif; ?>
        <h4><?= cms_e($t('footer_follow', 'Síguenos')) ?></h4>
        <?= ad_social('ad-social-footer') ?>
      </div>
    </div>
    <div class="ad-footer-bottom">
      <span><?= cms_e(str_replace('{year}', date('Y'), $t('footer_copy', '© {year} All Design'))) ?></span>
      <a href="#top" class="ad-totop" data-ad-totop><?= cms_e($t('back_top', 'Subir')) ?> ↑</a>
    </div>
  </div>
</footer>
<?php endif; endif; ?>
<script defer src="<?= cms_asset('js/alldesign.js') ?>?v=<?= CMS_VERSION ?>"></script>
</body>
</html>
<?php
}
