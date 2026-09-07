<?php /** Animación Lottie. $b: file, title, text, side, loop */ declare(strict_types=1);
$file = trim((string) $b['file']); if ($file === '') return;
$src = preg_match('#^https?://#', $file) ? $file : cms_img($file); ?>
<div class="<?= cms_e(cms_block_class('container')) ?> med-lottie-wrap med-lottie-<?= cms_e((string) $b['side']) ?>">
<?php if (trim((string) $b['title']) !== '' || trim((string) $b['text']) !== ''): ?>
  <div class="med-lottie-text"><?php if (trim((string) $b['title']) !== ''): ?><h2><?= cms_e($b['title']) ?></h2><?php endif; ?><?php if (trim((string) $b['text']) !== ''): ?><p><?= nl2br(cms_e($b['text'])) ?></p><?php endif; ?></div>
<?php endif; ?>
  <div class="med-lottie" data-med-lottie="<?= cms_e($src) ?>" data-loop="<?= !empty($b['loop']) ? '1' : '0' ?>"></div>
</div>
