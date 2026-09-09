<?php /** Comparador antes/después. $b: title, subtitle, before, after, label_before, label_after, start */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$before = trim((string) $b['before']); $after = trim((string) $b['after']);
if ($before === '' || $after === '') return;
$pos = max(5, min(95, (int) $b['start']));
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="mk-compare" style="--mk-pos:<?= $pos ?>%">
                <img class="mk-compare-after" src="<?= cms_e(cms_img($after)) ?>" alt="<?= cms_e($b['label_after']) ?>" loading="lazy">
                <div class="mk-compare-before"><img src="<?= cms_e(cms_img($before)) ?>" alt="<?= cms_e($b['label_before']) ?>" loading="lazy"></div>
<?php if (trim((string) $b['label_before']) !== ''): ?>                <span class="mk-compare-label mk-compare-label-before"><?= cms_e($b['label_before']) ?></span>
<?php endif; ?>
<?php if (trim((string) $b['label_after']) !== ''): ?>                <span class="mk-compare-label mk-compare-label-after"><?= cms_e($b['label_after']) ?></span>
<?php endif; ?>
                <div class="mk-compare-handle" aria-hidden="true"><span></span></div>
                <input type="range" class="mk-compare-range" min="0" max="100" value="<?= $pos ?>" aria-label="<?= cms_e(($b['label_before'] ?: 'Antes') . ' / ' . ($b['label_after'] ?: 'Después')) ?>">
            </div>
        </div>
