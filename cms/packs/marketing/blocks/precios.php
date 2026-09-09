<?php /** Precios con interruptor. $b: title, subtitle, items, monthly, yearly, per_month, per_year, save, note */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$plans = [];
foreach ((array) $b['items'] as $l) { [$n, $pm, $py, $d, $f, $bt, $bu, $hi] = mk_split((string) $l, 8); if ($n !== '') $plans[] = [$n, $pm, $py !== '' ? $py : $pm, $d, array_values(array_filter(array_map('trim', explode(';', $f)))), $bt, $bu, in_array(strtolower($hi), ['si', 'sí', 'yes', '1', 'x'], true)]; }
if (!$plans) return;
$uid = 'mk-pr-' . preg_replace('/[^a-z0-9]/i', '', (string) $sec['id']);
$isNum = fn(string $p) => (bool) preg_match('/\d/', $p);
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="mk-pricing" data-period="month">
                <div class="mk-switch" role="group" aria-label="Periodo">
                    <button type="button" class="mk-switch-btn is-active" data-period="month" aria-pressed="true"><?= cms_e($b['monthly']) ?></button>
                    <button type="button" class="mk-switch-btn" data-period="year" aria-pressed="false"><?= cms_e($b['yearly']) ?><?php if (trim((string) $b['save']) !== ''): ?> <span class="mk-switch-save"><?= cms_e($b['save']) ?></span><?php endif; ?></button>
                </div>
                <div class="mk-plans mk-plans-<?= min(4, count($plans)) ?>">
<?php foreach ($plans as [$n, $pm, $py, $d, $feats, $bt, $bu, $hi]): ?>
                    <article class="mk-plan<?= $hi ? ' mk-plan-hi' : '' ?>">
<?php if ($hi): ?>                        <span class="mk-plan-tag">Recomendado</span>
<?php endif; ?>
                        <h3><?= cms_e($n) ?></h3>
<?php if ($d !== ''): ?>                        <p class="mk-plan-desc"><?= cms_e($d) ?></p>
<?php endif; ?>
                        <div class="mk-plan-price">
                            <span class="mk-price mk-price-month"><strong><?= cms_e($pm) ?></strong><?php if ($isNum($pm) && trim((string) $b['per_month']) !== ''): ?><small><?= cms_e($b['per_month']) ?></small><?php endif; ?></span>
                            <span class="mk-price mk-price-year"><strong><?= cms_e($py) ?></strong><?php if ($isNum($py) && trim((string) $b['per_year']) !== ''): ?><small><?= cms_e($b['per_year']) ?></small><?php endif; ?></span>
                        </div>
<?php if ($feats): ?>                        <ul class="mk-plan-feats"><?php foreach ($feats as $f): ?><li><svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M4 10.5l4 4 8-9"/></svg><?= cms_e($f) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
                        <?= mk_btn($bt, $bu, $hi ? 'primary' : 'outline') ?>
                    </article>
<?php endforeach; ?>
                </div>
<?php if (trim((string) $b['note']) !== ''): ?>                <p class="mk-pricing-note"><?= cms_e($b['note']) ?></p>
<?php endif; ?>
            </div>
        </div>
