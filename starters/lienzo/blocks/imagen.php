<?php /** Imagen. $b: image, alt, caption */ declare(strict_types=1); if (empty($b['image'])) return; ?>
<div class="lz-container"><figure class="lz-figure"><?= cms_picture((string) $b['image'], (string) ($b['alt'] ?: $b['caption']), '', true) ?><?php if (trim((string) $b['caption']) !== ''): ?><figcaption><?= cms_e($b['caption']) ?></figcaption><?php endif; ?></figure></div>
