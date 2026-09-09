<?php /** Acordeón. $b: title, subtitle, items, first, single */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$items = [];
foreach ((array) $b['items'] as $l) { [$t, $x] = ct_split((string) $l, 2); if ($t !== '') $items[] = [$t, $x]; }
if (!$items) return;
$name = !empty($b['single']) ? ' name="ct-acc-' . preg_replace('/[^a-z0-9]/i', '', (string) $sec['id']) . '"' : '';
?>
        <div class="<?= cms_e(cms_block_class('container')) ?>">
            <?= cms_block_header((string) $b['title'], (string) $b['subtitle']) ?>
            <div class="ct-acc">
<?php foreach ($items as $i => [$t, $x]): ?>
                <details class="ct-acc-item"<?= $name ?><?= $i === 0 && !empty($b['first']) ? ' open' : '' ?>>
                    <summary><span><?= cms_e($t) ?></span><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M5 8l5 5 5-5"/></svg></summary>
                    <div class="ct-acc-body"><p><?= ct_rich($x) ?></p></div>
                </details>
<?php endforeach; ?>
            </div>
        </div>
