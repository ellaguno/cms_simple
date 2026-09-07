<?php /** Pie de página. $b: logo, name, text, columns, contact, social, copy */ declare(strict_types=1);
$S = cms_settings(); $name = (string) ($b['name'] ?: ($S['site_name'] ?? cms_config('name')));
$cols = array_values(array_filter(array_map('trim', (array) $b['columns']), 'strlen')); ?>
<div class="lz-container">
  <div class="lz-footer-grid">
    <div class="lz-footer-brand">
      <?php if (!empty($b['logo'])): ?><img src="<?= cms_e(cms_img((string) $b['logo'])) ?>" alt="<?= cms_e($name) ?>"><?php else: ?><strong><?= cms_e($name) ?></strong><?php endif; ?>
<?php if (trim((string) $b['text']) !== ''): ?>      <p><?= lz_inline((string) $b['text']) ?></p><?php endif; ?>
<?php if ((array) $b['social']): ?>      <div class="lz-social"><?php foreach ((array) $b['social'] as $l): [$n, $u] = lz_split($l, 2); if ($n === '') continue; ?><a href="<?= cms_e(lz_href($u)) ?>" target="_blank" rel="noopener"><?= cms_e($n) ?></a><?php endforeach; ?></div>
<?php endif; ?>
    </div>
<?php foreach ($cols as $col): $cells = array_map('trim', explode('|', $col)); $title = array_shift($cells); ?>
    <div class="lz-footer-col"><h4><?= cms_e($title) ?></h4><ul><?php foreach ($cells as $c): [$txt, $url] = lz_link($c); if ($txt === '') continue; ?><li><?= $url !== '' ? '<a href="' . cms_e($url) . '">' . cms_e($txt) . '</a>' : cms_e($txt) ?></li><?php endforeach; ?></ul></div>
<?php endforeach; ?>
<?php if ((array) $b['contact']): ?>
    <div class="lz-footer-col"><h4>Contacto</h4><ul><?php foreach ((array) $b['contact'] as $c): $c = trim($c); if ($c === '') continue; $h = filter_var($c, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $c : (preg_match('/^\+?[\d\s()-]{7,}$/', $c) ? 'tel:' . preg_replace('/[^\d+]/', '', $c) : ''); ?><li><?= $h ? '<a href="' . cms_e($h) . '">' . cms_e($c) . '</a>' : cms_e($c) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
  </div>
  <p class="lz-copy"><?= cms_e(str_replace('{year}', date('Y'), (string) $b['copy'])) ?></p>
</div>
