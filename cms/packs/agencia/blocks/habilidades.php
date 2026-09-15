<?php /** Barras de habilidades. $b: title, body, label, show_pct, items, side, thickness */ declare(strict_types=1);
require_once dirname(__DIR__) . '/inc.php';
$bars = [];
foreach ((array) $b['items'] as $l) { [$name, $pct, $color] = ag_split((string) $l, 3); if ($name !== '') $bars[] = [$name, max(0, min(100, (int) $pct ?: 80)), preg_match('/^#[0-9a-f]{3,8}$/i', $color) ? $color : '']; }
if (!$bars) return;
$hasText = trim((string) $b['title']) !== '' || trim(strip_tags((string) $b['body'])) !== '';
$side = (string) $b['side'] === 'left' ? 'left' : 'right';
$th = in_array((string) $b['thickness'], ['thin', 'normal', 'thick'], true) ? (string) $b['thickness'] : 'normal';
?>
<div class="<?= cms_e(cms_block_class('container')) ?> ag-skills-in ag-side-<?= $side ?><?= $hasText ? '' : ' no-text' ?>">
<?php if ($hasText): ?>
  <div class="ag-skills-text">
<?php if (trim((string) $b['title']) !== ''): ?>    <h2 class="<?= cms_e(cms_block_class('title')) ?>"><?= ag_inline((string) $b['title']) ?></h2>
<?php endif; ?>
    <?= cms_content((string) $b['body']) ?>
  </div>
<?php endif; ?>
  <div class="ag-skills ag-th-<?= $th ?>" data-ag-skills>
<?php if (trim((string) $b['label']) !== ''): ?>    <h3 class="ag-skills-label"><?= cms_e((string) $b['label']) ?></h3>
<?php endif; ?>
<?php foreach ($bars as [$name, $pct, $color]): ?>
    <div class="ag-skill">
      <span class="ag-skill-name"><?= cms_e($name) ?><?php if (!empty($b['show_pct'])): ?><span class="ag-skill-num"><?= $pct ?>%</span><?php endif; ?></span>
      <div class="ag-skill-bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= cms_e($name) ?>"><div class="ag-skill-fill" style="--w:<?= $pct ?>%<?= $color !== '' ? ';--c:' . $color : '' ?>"></div></div>
    </div>
<?php endforeach; ?>
  </div>
</div>
