<?php /** Lista de puntos. $b: title, text, items, columns */ declare(strict_types=1); ?>
<div class="lz-container">
  <?= lz_head((string) $b['title'], (string) $b['text']) ?>
  <ul class="lz-list lz-list-<?= (int) $b['columns'] ?>">
<?php foreach ((array) $b['items'] as $line): [$t2, $d] = lz_split($line, 2); if ($t2 === '') continue; ?>
    <li><strong><?= cms_e($t2) ?></strong><?php if ($d !== ''): ?><span><?= cms_e($d) ?></span><?php endif; ?></li>
<?php endforeach; ?>
  </ul>
</div>
