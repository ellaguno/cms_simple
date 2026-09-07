<?php /** Planes. $b: title, subtitle, items */ declare(strict_types=1); ?>
<div class="lz-container">
  <?= lz_head((string) $b['title'], (string) $b['subtitle']) ?>
  <div class="lz-plans">
<?php foreach ((array) $b['items'] as $line): [$n, $p, $per, $feats, $bt, $bu, $hi] = lz_split($line, 7); if ($n === '') continue; ?>
    <div class="lz-plan<?= in_array(strtolower($hi), ['si', 'sí', 'yes', '1'], true) ? ' is-featured' : '' ?>" data-aos="fade-up"><h3><?= cms_e($n) ?></h3><div class="lz-price"><?= cms_e($p) ?><?php if ($per !== ''): ?> <small><?= cms_e($per) ?></small><?php endif; ?></div>
      <ul><?php foreach (array_filter(array_map('trim', explode(';', $feats)), 'strlen') as $f): ?><li><?= cms_e($f) ?></li><?php endforeach; ?></ul>
      <?= lz_btn($bt, $bu, in_array(strtolower($hi), ['si', 'sí', 'yes', '1'], true) ? 'primary' : 'outline') ?></div>
<?php endforeach; ?>
  </div>
</div>
