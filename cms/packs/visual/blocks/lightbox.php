<?php /** Galería con lightbox. $b: title, subtitle, images, columns */ declare(strict_types=1);
$items = [];
foreach ((array) $b['images'] as $l) { [$src, $cap] = array_pad(array_map('trim', explode('|', (string) $l, 2)), 2, ''); if ($src !== '') $items[] = [cms_img($src), $cap]; }
if (!$items) return;
$gid = 'g' . $sec['id'];
?>
        <div class="cms-container">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="vis-grid vis-grid-<?= (int) $b['columns'] ?>">
<?php foreach ($items as [$src, $cap]): ?>
                <a class="vis-grid-item glightbox" href="<?= cms_e($src) ?>" data-gallery="<?= $gid ?>"<?= $cap !== '' ? ' data-title="' . cms_e($cap) . '"' : '' ?>><img src="<?= cms_e($src) ?>" alt="<?= cms_e($cap ?: strip_tags((string) $b['title'])) ?>" loading="lazy"></a>
<?php endforeach; ?>
            </div>
        </div>
