<?php /** Pestañas con imagen. $b: title, subtitle, items, side, autoplay */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$items = [];
foreach ((array) $b['items'] as $l) { [$t, $x, $img, $ic] = mk_split((string) $l, 4); if ($t !== '') $items[] = [$t, $x, $img, $ic]; }
if (!$items) return;
$uid = 'mk-tabs-' . preg_replace('/[^a-z0-9]/i', '', (string) $sec['id']);
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="mk-tabs mk-tabs-<?= cms_e((string) $b['side']) ?>" data-autoplay="<?= !empty($b['autoplay']) ? '6000' : '0' ?>">
                <div class="mk-tabs-list" role="tablist">
<?php foreach ($items as $i => [$t, $x, $img, $ic]): ?>
                    <button type="button" class="mk-tab<?= $i === 0 ? ' is-active' : '' ?>" role="tab" id="<?= $uid ?>-t<?= $i ?>" aria-controls="<?= $uid ?>-p<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                        <?php if ($ic !== ''): ?><?= mk_visual($ic, '', 'mk-tab-icon') ?><?php endif; ?>
                        <span class="mk-tab-text"><strong><?= cms_e($t) ?></strong><?php if ($x !== ''): ?><span><?= cms_e($x) ?></span><?php endif; ?></span>
                        <span class="mk-tab-bar" aria-hidden="true"><span></span></span>
                    </button>
<?php endforeach; ?>
                </div>
                <div class="mk-tabs-media">
<?php foreach ($items as $i => [$t, $x, $img, $ic]): ?>
                    <div class="mk-tab-panel<?= $i === 0 ? ' is-active' : '' ?>" role="tabpanel" id="<?= $uid ?>-p<?= $i ?>" aria-labelledby="<?= $uid ?>-t<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>><?php if ($img !== ''): ?><img src="<?= cms_e(cms_img($img)) ?>" alt="<?= cms_e($t) ?>" loading="lazy"><?php else: ?><div class="mk-tab-empty"><?= cms_e($t) ?></div><?php endif; ?></div>
<?php endforeach; ?>
                </div>
            </div>
        </div>
