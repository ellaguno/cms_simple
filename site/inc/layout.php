<?php
/**
 * Layout del tema: site_header($page) y site_footer($page) envuelven cada plantilla.
 * $page trae: lang, route, title, desc, canonical, alt (URLs por idioma), jsonld, og_image, noindex.
 */
declare(strict_types=1);

function site_header(array $page): void
{
    $lang = $page['lang'];
    $S = cms_settings();
    $t = fn(string $k, $d = '') => cms_t($k, $lang, $d);
    $site = $S['site_name'] ?? cms_config('name');
    $alt = $page['alt'] ?? [];
    $path = $page['path'] ?? '';
    ?><!DOCTYPE html>
<html lang="<?= cms_e($lang) ?>">
<head>
<?php cms_head($page); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap">
<link rel="stylesheet" href="<?= cms_asset('css/site.css') ?>?v=<?= CMS_VERSION ?>">
</head>
<body>
<header class="hdr">
  <div class="wrap hdr-in">
    <a class="brand" href="<?= cms_url('home', $lang) ?>"><?php if (!empty($S['logo'])): ?><img src="<?= cms_e(cms_img($S['logo'])) ?>" alt="<?= cms_e($site) ?>"><?php else: ?><?= cms_e($site) ?><?php endif; ?></a>
    <nav class="nav" aria-label="principal">
<?php foreach (cms_menu($lang) as $it):
    $url = $it['url'] ?? '/'; $rel = trim($url, '/');
    $active = ($rel === '' && $path === '') || ($rel !== '' && !preg_match('#^(https?:)?//#', $url) && ($path === $rel || strpos($path, $rel . '/') === 0)); ?>
      <a href="<?= cms_e(cms_menu_url($url, $lang)) ?>"<?= $active ? ' class="on"' : '' ?><?= !empty($it['new_tab']) ? ' target="_blank" rel="noopener"' : '' ?>><?= cms_e($it['label'] ?? '') ?></a>
<?php endforeach; ?>
<?php foreach ($alt as $l => $u): if ($l === $lang) continue; ?>
      <a class="lang" href="<?= cms_e($u) ?>" hreflang="<?= $l ?>"><?= strtoupper($l) ?></a>
<?php endforeach; ?>
    </nav>
  </div>
</header>
<main>
<?php
}

function site_footer(array $page): void
{
    $lang = $page['lang'];
    $S = cms_settings();
    $t = fn(string $k, $d = '') => cms_t($k, $lang, $d);
    ?>
</main>
<footer class="ftr">
  <div class="wrap ftr-in">
    <div>
      <strong><?= cms_e($S['site_name'] ?? cms_config('name')) ?></strong>
<?php if (!empty($S['email'])): ?>      <div><a href="mailto:<?= cms_e($S['email']) ?>"><?= cms_e($S['email']) ?></a></div><?php endif; ?>
<?php if (!empty($S['phone'])): ?>      <div><a href="<?= cms_e(cms_tel_href()) ?>"><?= cms_e($S['phone']) ?></a></div><?php endif; ?>
<?php if ($wa = cms_whatsapp_url()): ?>      <div><a href="<?= cms_e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a></div><?php endif; ?>
    </div>
    <div>
      <?= cms_social_html() ?>
<?php foreach ((array) ($S['other_sites'] ?? []) as $os): ?>      <div><a href="<?= cms_e($os['url']) ?>" target="_blank" rel="noopener"><?= cms_e($os['label']) ?></a></div>
<?php endforeach; ?>
    </div>
  </div>
  <p class="wrap copy"><?= cms_e(str_replace('{year}', date('Y'), (string) $t('footer_copy', '© {year}'))) ?></p>
</footer>
<script defer src="<?= cms_asset('js/site.js') ?>?v=<?= CMS_VERSION ?>"></script>
</body>
</html>
<?php
}
