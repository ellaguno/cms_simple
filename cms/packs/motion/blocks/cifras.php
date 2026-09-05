<?php /** Cifras animadas. $b: title, subtitle, items, columns */ declare(strict_types=1);
$rows = [];
foreach ((array) $b['items'] as $l) { [$n, $label, $suffix] = array_pad(array_map('trim', explode('|', (string) $l, 3)), 3, ''); if ($n !== '') $rows[] = [$n, $label, $suffix]; }
if (!$rows) return;
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="mo-counters mo-counters-<?= (int) $b['columns'] ?>">
<?php foreach ($rows as [$n, $label, $suffix]): $num = (float) str_replace(',', '', $n); ?>
                <div class="mo-counter">
                    <div class="mo-counter-num"><span class="mo-counter-value" data-count="<?= cms_e((string) $num) ?>" data-decimals="<?= strpos($n, '.') !== false ? strlen(explode('.', $n)[1]) : 0 ?>">0</span><?php if ($suffix !== ''): ?><span class="mo-counter-suffix"><?= cms_e($suffix) ?></span><?php endif; ?></div>
<?php if ($label !== ''): ?>                    <div class="mo-counter-label"><?= cms_e($label) ?></div>
<?php endif; ?>
                </div>
<?php endforeach; ?>
            </div>
        </div>
