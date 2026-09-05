<?php /** Carrusel. $b: title, subtitle, images, per_view, autoplay */ declare(strict_types=1);
$items = [];
foreach ((array) $b['images'] as $l) { [$src, $cap] = array_pad(array_map('trim', explode('|', (string) $l, 2)), 2, ''); if ($src !== '') $items[] = [cms_img($src), $cap]; }
if (!$items) return;
?>
        <div class="cms-container">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="swiper vis-carousel" data-per-view="<?= (int) $b['per_view'] ?>" data-autoplay="<?= !empty($b['autoplay']) ? '1' : '0' ?>">
                <div class="swiper-wrapper">
<?php foreach ($items as [$src, $cap]): ?>
                    <figure class="swiper-slide vis-slide"><img src="<?= cms_e($src) ?>" alt="<?= cms_e($cap ?: strip_tags((string) $b['title'])) ?>" loading="lazy"><?php if ($cap !== ''): ?><figcaption><?= cms_e($cap) ?></figcaption><?php endif; ?></figure>
<?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div><div class="swiper-button-next"></div>
            </div>
        </div>
