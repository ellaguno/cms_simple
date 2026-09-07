<?php /** Hero. $b: badge, title, subtitle, buttons, image, layout, shader */ declare(strict_types=1);
cms_section_effect('visual/shader', !empty($b['shader']));
$layout = (string) $b['layout']; $img = (string) $b['image'];
if ($img === '' && $layout !== 'bg') $layout = 'none';
if ($layout === 'bg' && $img !== '') echo '<div class="lz-hero-bg" style="background-image:url(', cms_e(cms_img($img)), ')"></div>'; ?>
<div class="lz-container lz-hero-in lz-hero-<?= cms_e($layout) ?>">
  <div class="lz-hero-text">
<?php if (trim((string) $b['badge']) !== ''): ?>    <span class="lz-badge"><?= cms_e($b['badge']) ?></span><?php endif; ?>
    <h1><?= lz_inline((string) $b['title']) ?></h1>
<?php if (trim((string) $b['subtitle']) !== ''): ?>    <p class="lz-lead"><?= lz_inline((string) $b['subtitle']) ?></p><?php endif; ?>
<?php if ((array) $b['buttons']): ?>    <div class="lz-btns"><?php foreach ((array) $b['buttons'] as $l): [$t2, $u, $s] = lz_split($l, 3); echo lz_btn($t2, $u, $s ?: 'primary'); ?><?php endforeach; ?></div><?php endif; ?>
  </div>
<?php if ($img !== '' && in_array($layout, ['right', 'left', 'below'], true)): ?>
  <div class="lz-hero-media"><?= cms_picture($img, strip_tags((string) $b['title']), '', true) ?></div>
<?php endif; ?>
</div>
