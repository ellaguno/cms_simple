<?php /** Tarjetas. $b: title, subtitle, items, columns, variant */ declare(strict_types=1);
cms_section_effect('visual/spotlight', (string) $b['variant'] === 'card'); ?>
<div class="lz-container">
  <?= lz_head((string) $b['title'], (string) $b['subtitle']) ?>
  <div class="lz-cards lz-cards-<?= (int) $b['columns'] ?> lz-cards-<?= cms_e((string) $b['variant']) ?>">
<?php foreach ((array) $b['items'] as $i => $line): [$t2, $tx, $ic] = lz_split($line, 3); if ($t2 === '') continue; ?>
    <div class="lz-card" data-aos="fade-up" data-aos-delay="<?= min($i * 60, 400) ?>"><?php if ((string) $b['variant'] === 'steps'): ?><span class="lz-card-icon lz-card-num"><?= $i + 1 ?></span><?php elseif ($ic !== ''): ?><span class="lz-card-icon"><?= cms_e($ic) ?></span><?php endif; ?><h3><?= cms_e($t2) ?></h3><?php if ($tx !== ''): ?><p><?= cms_e($tx) ?></p><?php endif; ?></div>
<?php endforeach; ?>
  </div>
</div>
