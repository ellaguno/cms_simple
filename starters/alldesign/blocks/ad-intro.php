<?php /** Texto con sello. $b: title, body, image, image_hover, side */ declare(strict_types=1);
$S = cms_settings();
$img = (string) ($b['image'] ?: ($S['seal'] ?? 'sello.png'));
$hover = (string) ($b['image_hover'] ?: ($S['seal_hover'] ?? ''));
$side = in_array((string) $b['side'], ['right', 'left', 'none'], true) ? (string) $b['side'] : 'right';
?>
<div class="ad-container ad-intro-in ad-side-<?= $side ?>">
  <div class="ad-intro-text">
<?php if (trim((string) $b['title']) !== ''): ?>    <h2 class="ad-h-light"><?= ad_inline((string) $b['title']) ?></h2>
<?php endif; ?>
    <div class="ad-prose"><?= cms_content((string) $b['body']) ?></div>
  </div>
<?php if ($side !== 'none' && $img !== ''): ?>
  <div class="ad-intro-seal"><div class="ad-circle ad-circle-dark<?= $hover !== '' ? ' has-hover' : '' ?>"><img src="<?= cms_e(cms_img($img)) ?>" alt="" loading="lazy"><?php if ($hover !== ''): ?><img class="ad-circle-hover" src="<?= cms_e(cms_img($hover)) ?>" alt="" loading="lazy"><?php endif; ?></div></div>
<?php endif; ?>
</div>
