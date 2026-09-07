<?php /** Equipo. $b: title, text, items */ declare(strict_types=1); ?>
<div class="lz-container">
  <?= lz_head((string) $b['title'], (string) $b['text']) ?>
  <div class="lz-team">
<?php foreach ((array) $b['items'] as $line): [$n, $r, $img, $tx] = lz_split($line, 4); if ($n === '') continue; ?>
    <div class="lz-person" data-aos="fade-up"><?php if ($img !== ''): ?><img src="<?= cms_e(cms_img($img)) ?>" alt="<?= cms_e($n) ?>" loading="lazy"><?php else: ?><span class="lz-person-initial"><?= cms_e(mb_substr($n, 0, 1)) ?></span><?php endif; ?><h3><?= cms_e($n) ?></h3><?php if ($r !== ''): ?><p class="lz-person-role"><?= cms_e($r) ?></p><?php endif; ?><?php if ($tx !== ''): ?><p><?= cms_e($tx) ?></p><?php endif; ?></div>
<?php endforeach; ?>
  </div>
</div>
