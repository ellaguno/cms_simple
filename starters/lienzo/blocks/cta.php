<?php /** Llamado a la acción. $b: title, text, button_text, button_url, button2_text, button2_url */ declare(strict_types=1); ?>
<div class="lz-container lz-cta-in"><h2><?= lz_inline((string) $b['title']) ?></h2><?php if (trim((string) $b['text']) !== ''): ?><p class="lz-lead"><?= lz_inline((string) $b['text']) ?></p><?php endif; ?>
  <div class="lz-btns"><?= lz_btn((string) $b['button_text'], (string) $b['button_url'], 'primary') ?><?= lz_btn((string) $b['button2_text'], (string) $b['button2_url'], 'outline') ?></div></div>
