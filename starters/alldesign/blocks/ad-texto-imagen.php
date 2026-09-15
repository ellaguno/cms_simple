<?php /** Texto con imagen circular. $b: title, body, image, shape, side, button_text, button_url */ declare(strict_types=1);
$side = (string) $b['side'] === 'left' ? 'left' : 'right';
$shape = (string) $b['shape'] === 'rect' ? 'rect' : 'circle';
$hasImg = trim((string) $b['image']) !== '';
?>
<div class="ad-container ad-ti-in ad-side-<?= $side ?><?= $hasImg ? '' : ' no-image' ?>">
  <div class="ad-ti-text">
<?php if (trim((string) $b['title']) !== ''): ?>    <h2 class="ad-h-light"><?= ad_inline((string) $b['title']) ?></h2>
<?php endif; ?>
    <div class="ad-prose"><?= cms_content((string) $b['body']) ?></div>
<?php if (trim((string) $b['button_text']) !== ''): ?>    <p><a class="ad-btn" href="<?= cms_e(ad_url((string) $b['button_url'], $lang) ?: '#') ?>"><?= cms_e((string) $b['button_text']) ?></a></p>
<?php endif; ?>
  </div>
<?php if ($hasImg): ?>
  <div class="ad-ti-img"><div class="ad-<?= $shape ?>"><?= cms_picture((string) $b['image'], strip_tags((string) $b['title'])) ?></div></div>
<?php endif; ?>
</div>
