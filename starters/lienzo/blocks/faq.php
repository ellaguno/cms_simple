<?php /** Preguntas frecuentes. $b: title, items */ declare(strict_types=1); ?>
<div class="lz-container lz-narrow">
  <?= lz_head((string) $b['title']) ?>
  <div class="lz-faq">
<?php foreach ((array) $b['items'] as $line): [$q, $a] = lz_split($line, 2); if ($q === '') continue; ?>
    <details class="lz-faq-item"><summary><?= cms_e($q) ?></summary><p><?= cms_e($a) ?></p></details>
<?php endforeach; ?>
  </div>
</div>
