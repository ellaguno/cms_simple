<?php /** Cabecera del sitio. $b: logo, name, menu, button_text, button_url, sticky */ declare(strict_types=1);
$S = cms_settings(); $name = (string) ($b['name'] ?: ($S['site_name'] ?? cms_config('name'))); ?>
<?php $top = array_values(array_filter(array_map('trim', (array) $b['topbar']), 'strlen')); if ($top): ?>
<div class="lz-topbar"><div class="lz-container lz-topbar-in">
<?php foreach (['left', 'right'] as $sideK): ?><div class="lz-topbar-<?= $sideK ?>"><?php foreach ($top as $l): [$txt, $url, $al] = lz_split($l, 3); if ($txt === '' || (strtolower($al) === 'derecha') !== ($sideK === 'right')) continue; ?><?= $url !== '' ? '<a href="' . cms_e(lz_href($url)) . '">' . cms_e($txt) . '</a>' : '<span>' . cms_e($txt) . '</span>' ?><?php endforeach; ?></div><?php endforeach; ?>
</div></div>
<?php endif; ?>
<div class="lz-container lz-header-in<?= !empty($b['sticky']) ? ' is-sticky' : '' ?>">
  <a class="lz-brand" href="<?= cms_url('home', $lang) ?>"><?php if (!empty($b['logo'])): ?><img src="<?= cms_e(cms_img((string) $b['logo'])) ?>" alt="<?= cms_e($name) ?>"><?php else: ?><?= cms_e($name) ?><?php endif; ?></a>
  <button class="lz-nav-toggle" type="button" aria-label="Menú" data-lz-toggle><span></span><span></span><span></span></button>
  <nav class="lz-nav" data-lz-nav>
<?php foreach ((array) $b['menu'] as $line): [$txt, $url] = lz_split($line, 2); if ($txt === '') continue; ?>
    <a href="<?= cms_e(lz_href($url)) ?>"><?= cms_e($txt) ?></a>
<?php endforeach; ?>
    <?= lz_btn((string) $b['button_text'], (string) $b['button_url']) ?>
  </nav>
</div>
