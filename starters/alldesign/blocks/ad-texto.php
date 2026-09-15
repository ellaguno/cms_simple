<?php /** Texto. $b: kicker, title, body */ declare(strict_types=1); ?>
<div class="ad-container ad-texto-in">
<?php if (trim((string) $b['kicker']) !== ''): ?>  <span class="ad-kicker"><?= cms_e((string) $b['kicker']) ?></span>
<?php endif; ?>
<?php if (trim((string) $b['title']) !== ''): ?>  <h2 class="ad-h-light"><?= ad_inline((string) $b['title']) ?></h2>
<?php endif; ?>
  <div class="ad-prose"><?= cms_content((string) $b['body']) ?></div>
</div>
