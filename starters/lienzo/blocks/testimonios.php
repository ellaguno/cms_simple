<?php /** Testimonios. $b: title, items */ declare(strict_types=1); ?>
<div class="lz-container">
  <?= lz_head((string) $b['title']) ?>
  <div class="lz-quotes">
<?php foreach ((array) $b['items'] as $line): [$q, $n, $r] = lz_split($line, 3); if ($q === '') continue; ?>
    <blockquote class="lz-quote" data-aos="fade-up"><p>“<?= cms_e($q) ?>”</p><footer><strong><?= cms_e($n) ?></strong><?php if ($r !== ''): ?> <span><?= cms_e($r) ?></span><?php endif; ?></footer></blockquote>
<?php endforeach; ?>
  </div>
</div>
