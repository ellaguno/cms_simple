<?php /** Línea de tiempo. $b: title, subtitle, items, layout */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$items = [];
foreach ((array) $b['items'] as $l) { [$t, $x, $tag, $ic] = mk_split((string) $l, 4); if ($t !== '') $items[] = [$t, $x, $tag, $ic]; }
if (!$items) return;
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <ol class="mk-timeline mk-timeline-<?= cms_e((string) $b['layout']) ?>">
<?php foreach ($items as $i => [$t, $x, $tag, $ic]): ?>
                <li class="mk-tl-item">
                    <div class="mk-tl-dot" aria-hidden="true"><?= $ic !== '' ? mk_visual($ic, '', 'mk-tl-icon') : '<span class="mk-tl-num">' . ($i + 1) . '</span>' ?></div>
                    <div class="mk-tl-card">
<?php if ($tag !== ''): ?>                        <span class="mk-tl-tag"><?= cms_e($tag) ?></span>
<?php endif; ?>
                        <h3><?= cms_e($t) ?></h3>
<?php if ($x !== ''): ?>                        <p><?= cms_e($x) ?></p>
<?php endif; ?>
                    </div>
                </li>
<?php endforeach; ?>
            </ol>
        </div>
