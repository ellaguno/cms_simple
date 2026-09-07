<?php /** Logotipos. $b: title, images */ declare(strict_types=1);
$items = []; foreach ((array) $b['images'] as $l) { [$src, $n] = lz_split((string) $l, 2); if ($src !== '') $items[] = [$src, $n]; } if (!$items) return; ?>
<div class="lz-container"><?php if (trim((string) $b['title']) !== ''): ?><p class="lz-logos-title"><?= cms_e($b['title']) ?></p><?php endif; ?>
  <div class="lz-logos"><?php foreach ($items as [$src, $n]): ?><img src="<?= cms_e(cms_img($src)) ?>" alt="<?= cms_e($n) ?>" loading="lazy"><?php endforeach; ?></div>
</div>
