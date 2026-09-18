<?php /** Pie de página. $b: logo, name, text, columns, contact, contact_title, social, copy, bottom */ declare(strict_types=1);
$name = trim((string) $b['name']) ?: (string) ($S['site_name'] ?? cms_config('name'));
$logo = trim((string) $b['logo']) ?: (string) ($S['logo'] ?? '');
$cols = array_values(array_filter(array_map('trim', (array) $b['columns']), 'strlen'));
$contact = es_contact((array) $b['contact']);
$social = es_social((array) $b['social']);
$bottom = array_values(array_filter(array_map('trim', (array) $b['bottom']), 'strlen'));
?>
<div class="es-container">
  <div class="es-footer-grid">
    <div class="es-footer-brand">
      <?php if ($logo !== ''): ?><img src="<?= cms_e(cms_img($logo)) ?>" alt="<?= cms_e($name) ?>"><?php else: ?><strong><?= cms_e($name) ?></strong><?php endif; ?>
<?php if (trim((string) $b['text']) !== ''): ?>      <p><?= nl2br(cms_e((string) $b['text'])) ?></p>
<?php endif; if ($social !== ''): ?>      <div class="es-social"><?= $social ?></div>
<?php endif; ?>
    </div>
<?php foreach ($cols as $col): if ($col === '@menu'): ?>
    <div class="es-footer-col"><h4><?= cms_e($name) ?></h4><ul><?php foreach (cms_menu($lang) as $m): ?><li><a href="<?= cms_e(cms_menu_url((string) ($m['url'] ?? '/'), $lang)) ?>"><?= cms_e((string) ($m['label'] ?? '')) ?></a></li><?php endforeach; ?></ul></div>
<?php continue; endif; $cells = array_map('trim', explode('|', $col)); $title = array_shift($cells); ?>
    <div class="es-footer-col"><h4><?= cms_e($title) ?></h4><ul><?php foreach ($cells as $c): [$txt, $url] = es_link($c); if ($txt === '') continue; ?><li><?= $url !== '' ? '<a href="' . cms_e(es_href($url, $lang)) . '">' . cms_e($txt) . '</a>' : cms_e($txt) ?></li><?php endforeach; ?></ul></div>
<?php endforeach; if ($contact): ?>
    <div class="es-footer-col"><h4><?= cms_e((string) $b['contact_title']) ?></h4><ul><?php foreach ($contact as [$txt, $href]): ?><li><?= $href !== '' ? '<a href="' . cms_e($href) . '">' . cms_e($txt) . '</a>' : cms_e($txt) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
  </div>
  <div class="es-footer-bottom">
    <span class="es-copy"><?= cms_e(str_replace('{year}', date('Y'), (string) $b['copy'])) ?></span>
<?php if ($bottom): ?>    <span class="es-footer-links"><?php foreach ($bottom as $l): [$txt, $url] = es_split($l, 2); if ($txt === '') continue; ?><a href="<?= cms_e(es_href($url, $lang)) ?>"><?= cms_e($txt) ?></a><?php endforeach; ?></span>
<?php endif; ?>
  </div>
</div>
