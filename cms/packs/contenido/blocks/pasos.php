<?php /** Pasos numerados. $b: title, subtitle, items, layout */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$items = [];
foreach ((array) $b['items'] as $l) { [$t, $x, $ic] = ct_split((string) $l, 3); if ($t !== '') $items[] = [$t, $x, $ic]; }
if (!$items) return;
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <ol class="ct-steps ct-steps-<?= cms_e((string) $b['layout']) ?>" style="--ct-steps:<?= count($items) ?>">
<?php foreach ($items as $i => [$t, $x, $ic]): ?>
                <li class="ct-step">
                    <div class="ct-step-mark"><?= $ic !== '' ? ct_icon($ic) : '<span>' . ($i + 1) . '</span>' ?></div>
                    <h3><?= cms_e($t) ?></h3>
<?php if ($x !== ''): ?>                    <p><?= cms_e($x) ?></p>
<?php endif; ?>
                </li>
<?php endforeach; ?>
            </ol>
        </div>
