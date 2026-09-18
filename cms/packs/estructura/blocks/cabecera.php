<?php /** Cabecera del sitio. $b: topbar, logo, logo_height, name, menu, button_text/url, button2_text/url, langs, sticky, transparent */ declare(strict_types=1);
$name = trim((string) $b['name']) ?: (string) ($S['site_name'] ?? cms_config('name'));
$logo = trim((string) $b['logo']) ?: (string) ($S['logo'] ?? '');
$menu = es_menu((array) $b['menu'], $lang);
$top = array_values(array_filter(array_map('trim', (array) $b['topbar']), 'strlen'));
$langs = !empty($b['langs']) && count(cms_active_langs()) > 1 ? cms_active_langs() : [];
$alt = (array) ($page['alt'] ?? []);
?>
<?php if ($top): ?>
<div class="es-topbar"><div class="es-container es-topbar-in">
<?php foreach (['left', 'right'] as $sideK): ?><div class="es-topbar-<?= $sideK ?>"><?php foreach ($top as $l): [$txt, $url, $al] = es_split($l, 3); if ($txt === '' || (mb_strtolower($al) === 'derecha') !== ($sideK === 'right')) continue; ?><?= $url !== '' ? '<a href="' . cms_e(es_href($url, $lang)) . '">' . cms_e($txt) . '</a>' : '<span>' . cms_e($txt) . '</span>' ?><?php endforeach; ?></div><?php endforeach; ?>
</div></div>
<?php endif; ?>
<div class="es-container es-header-in" data-es-header<?= !empty($b['sticky']) ? ' data-es-sticky' : '' ?><?= !empty($b['transparent']) ? ' data-es-transparent' : '' ?>>
  <a class="es-brand" href="<?= cms_url('home', $lang) ?>"><?php if ($logo !== ''): ?><img src="<?= cms_e(cms_img($logo)) ?>" alt="<?= cms_e($name) ?>" style="height:<?= (int) ($b['logo_height'] ?: 40) ?>px"><?php else: ?><?= cms_e($name) ?><?php endif; ?></a>
  <button class="es-nav-toggle" type="button" aria-label="Menú" aria-expanded="false" data-es-toggle><span></span><span></span><span></span></button>
  <nav class="es-nav" data-es-nav>
<?php foreach ($menu as $m): if ($m['children']): ?>
    <div class="es-nav-item has-sub"><a href="<?= cms_e($m['url']) ?>"><?= cms_e($m['label']) ?></a><button type="button" class="es-sub-toggle" aria-label="Abrir" data-es-sub>▾</button>
      <div class="es-sub"><?php foreach ($m['children'] as $c): ?><a href="<?= cms_e($c['url']) ?>"><?= cms_e($c['label']) ?></a><?php endforeach; ?></div></div>
<?php else: ?>
    <a class="es-nav-item" href="<?= cms_e($m['url']) ?>"<?= $m['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><?= cms_e($m['label']) ?></a>
<?php endif; endforeach; ?>
<?php if ($langs): ?>    <span class="es-langs"><?php foreach ($langs as $l): ?><a href="<?= cms_e($alt[$l] ?? cms_url('home', $l)) ?>"<?= $l === $lang ? ' class="on"' : '' ?> hreflang="<?= $l ?>"><?= strtoupper($l) ?></a><?php endforeach; ?></span>
<?php endif; ?>
<?php if (trim((string) $b['button2_text']) !== ''): ?>    <a class="es-btn es-btn-outline" href="<?= cms_e(es_href((string) $b['button2_url'], $lang)) ?>"><?= cms_e($b['button2_text']) ?></a>
<?php endif; if (trim((string) $b['button_text']) !== ''): ?>    <a class="es-btn" href="<?= cms_e(es_href((string) $b['button_url'], $lang)) ?>"><?= cms_e($b['button_text']) ?></a>
<?php endif; ?>
  </nav>
</div>
