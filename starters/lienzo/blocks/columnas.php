<?php /** Texto e imagen. $b: title, body, image, side, button_text, button_url */ declare(strict_types=1); ?>
<div class="lz-container lz-cols lz-cols-<?= cms_e((string) $b['side']) ?>">
  <div class="lz-cols-text lz-prose"><?php if (trim((string) $b['title']) !== ''): ?><h2><?= lz_inline((string) $b['title']) ?></h2><?php endif; ?><?= cms_content((string) $b['body']) ?><?= trim((string) $b['button_text']) !== '' ? '<p>' . lz_btn((string) $b['button_text'], (string) $b['button_url']) . '</p>' : '' ?></div>
<?php if (!empty($b['image'])): ?>  <div class="lz-cols-media"><?= cms_picture((string) $b['image'], strip_tags((string) $b['title'])) ?></div><?php endif; ?>
</div>
