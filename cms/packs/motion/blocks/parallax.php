<?php /** Galería con parallax. $b: title, subtitle, images, columns */ declare(strict_types=1);
$items = [];
foreach ((array) $b['images'] as $l) { [$src, $txt, $url] = array_pad(array_map('trim', explode('|', (string) $l, 3)), 3, ''); if ($src !== '') $items[] = [cms_img($src), $txt, $url]; }
if (!$items) return;
$cols = max(2, min(3, (int) $b['columns']));
$columns = array_fill(0, $cols, []);
foreach ($items as $i => $it) $columns[$i % $cols][] = $it;
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="mo-parallax mo-parallax-<?= $cols ?>">
<?php foreach ($columns as $ci => $col): ?>
                <div class="mo-parallax-col" data-speed="<?= [0.9, 1.15, 0.8][$ci % 3] ?>">
<?php foreach ($col as [$src, $txt, $url]): $tag = $url !== '' ? 'a' : 'div'; ?>
                    <<?= $tag ?> class="mo-parallax-item"<?= $url !== '' ? ' href="' . cms_e($url) . '"' : '' ?>><img src="<?= cms_e($src) ?>" alt="<?= cms_e($txt) ?>" loading="lazy"><?php if ($txt !== ''): ?><span class="mo-parallax-caption"><?= cms_e($txt) ?></span><?php endif; ?></<?= $tag ?>>
<?php endforeach; ?>
                </div>
<?php endforeach; ?>
            </div>
        </div>
