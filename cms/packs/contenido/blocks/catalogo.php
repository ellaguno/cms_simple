<?php /** Catálogo de tarjetas. $b: title, subtitle, items, columns, ratio */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$items = [];
foreach ((array) $b['items'] as $l) { [$t, $x, $img, $tag, $link] = ct_split((string) $l, 5); if ($t !== '' || $img !== '') $items[] = [$t, $x, $img, $tag, ct_link($link)]; }
if (!$items) return;
$ratio = (string) $b['ratio'];
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="ct-cards ct-grid<?= (int) $b['columns'] ?>">
<?php foreach ($items as [$t, $x, $img, $tag, [$lt, $lu]]): $url = $lu !== '' ? $lu : ''; $tagEl = $url !== '' ? 'a' : 'div'; ?>
                <article class="ct-card ct-card-cat">
                    <<?= $tagEl ?> class="ct-card-link"<?= $url !== '' ? ' href="' . cms_e($url) . '"' : '' ?>>
<?php if ($ratio !== 'none' && $img !== ''): ?>                        <div class="ct-card-media ct-r-<?= cms_e($ratio) ?>"><img src="<?= cms_e(cms_img($img)) ?>" alt="<?= cms_e($t) ?>" loading="lazy"><?php if ($tag !== ''): ?><span class="ct-price"><?= cms_e($tag) ?></span><?php endif; ?></div>
<?php endif; ?>
                        <div class="ct-card-body">
<?php if ($ratio === 'none' && $tag !== ''): ?>                            <span class="ct-tag"><?= cms_e($tag) ?></span>
<?php endif; ?>
                            <h3><?= cms_e($t) ?></h3>
<?php if ($x !== ''): ?>                            <p><?= cms_e($x) ?></p>
<?php endif; ?>
<?php if ($lt !== ''): ?>                            <span class="ct-cta"><?= cms_e($lt) ?> <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 10h11M11 5l5 5-5 5"/></svg></span>
<?php endif; ?>
                        </div>
                    </<?= $tagEl ?>>
                </article>
<?php endforeach; ?>
            </div>
        </div>
