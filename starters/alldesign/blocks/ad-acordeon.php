<?php /** Acordeón. $b: items, open */ declare(strict_types=1);
$items = []; foreach ((array) $b['items'] as $l) { [$t2, $tx] = ad_split((string) $l, 2); if ($t2 !== '') $items[] = [$t2, $tx]; }
if (!$items) return;
$first = (string) $b['open'] !== 'none';
?>
<div class="ad-container">
  <div class="ad-acc" data-ad-accordion>
<?php foreach ($items as $i => [$t2, $tx]): $open = $first && $i === 0; $id = 'acc-' . $sec['id'] . '-' . $i; ?>
    <div class="ad-acc-item<?= $open ? ' is-open' : '' ?>">
      <h3 class="ad-acc-title"><button type="button" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $id ?>" data-ad-acc-btn><span class="ad-acc-icon"><?= ad_svg('plus') ?></span><span class="ad-acc-label"><?= ad_inline($t2) ?></span></button></h3>
      <div class="ad-acc-panel" id="<?= $id ?>"<?= $open ? '' : ' hidden' ?>><div class="ad-acc-body"><?= ad_rich($tx) ?></div></div>
    </div>
<?php endforeach; ?>
  </div>
</div>
