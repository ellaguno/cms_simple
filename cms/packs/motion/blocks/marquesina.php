<?php /** Marquesina. $b: items, separator, size, speed, direction, outline */ declare(strict_types=1);
$items = array_values(array_filter(array_map('trim', (array) $b['items'])));
if (!$items) return;
$isImg = fn(string $x) => (bool) preg_match('/\.(png|jpe?g|webp|svg|gif)$/i', $x);
?>
        <div class="mo-marquee mo-marquee-<?= cms_e($b['size']) ?> mo-marquee-<?= cms_e($b['speed']) ?> mo-marquee-<?= cms_e($b['direction']) ?><?= !empty($b['outline']) ? ' mo-marquee-outline' : '' ?>" aria-label="<?= cms_e(implode(', ', array_filter($items, fn($x) => !$isImg($x)))) ?>">
            <div class="mo-marquee-track">
<?php foreach ($items as $i => $it): ?>
                <span class="mo-marquee-item"><?php if ($isImg($it)): ?><img src="<?= cms_e(cms_img($it)) ?>" alt="" loading="lazy"><?php else: ?><?= cms_e($it) ?><?php endif; ?></span>
<?php if (trim((string) $b['separator']) !== ''): ?>                <span class="mo-marquee-sep" aria-hidden="true"><?= cms_e($b['separator']) ?></span>
<?php endif; ?>
<?php endforeach; ?>
            </div>
        </div>
