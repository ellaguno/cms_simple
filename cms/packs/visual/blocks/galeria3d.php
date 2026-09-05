<?php /** Galería 3D. $b: title, subtitle, hint, images, height */ declare(strict_types=1);
$imgs = array_values(array_filter(array_map(fn($l) => cms_img(trim(explode('|', (string) $l, 2)[0])), (array) $b['images'])));
if (!$imgs) return;
?>
        <div class="vis-g3d vis-g3d-<?= cms_e($b['height']) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle'], 'vis-g3d-header') ?>
            <canvas class="vis-g3d-canvas" data-images="<?= cms_e(implode(',', $imgs)) ?>"></canvas>
<?php if (trim((string) $b['hint']) !== ''): ?>
            <div class="vis-g3d-overlay"><p class="vis-g3d-hint"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg> <?= cms_e($b['hint']) ?></p></div>
<?php endif; ?>
            <noscript><div class="vis-g3d-fallback"><?php foreach ($imgs as $i => $src): ?><img src="<?= cms_e($src) ?>" alt="<?= cms_e(strip_tags((string) $b['title']) ?: 'Imagen') ?> <?= $i + 1 ?>"><?php endforeach; ?></div></noscript>
        </div>
