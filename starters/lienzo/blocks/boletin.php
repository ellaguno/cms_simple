<?php /** Boletín. $b: title, text, button_text, note */ declare(strict_types=1); ?>
<div class="lz-container lz-narrow lz-newsletter">
  <?= lz_head((string) $b['title'], (string) $b['text']) ?>
  <form class="lz-form lz-form-inline" method="post" action="<?= CMS_BASE ?>/_cms/form" data-lz-form>
    <input type="text" name="empresa_web2" tabindex="-1" autocomplete="off" class="lz-hp" aria-hidden="true">
    <input type="hidden" name="asunto" value="Suscripción al boletín">
    <input type="email" name="correo" placeholder="tu@correo.com" required aria-label="Correo">
    <button class="lz-btn lz-btn-primary" type="submit"><?= cms_e($b['button_text'] ?: 'Suscribirme') ?></button>
    <p class="lz-form-msg" data-lz-form-msg hidden></p>
  </form>
<?php if (trim((string) $b['note']) !== ''): ?>  <p class="lz-note"><?= cms_e($b['note']) ?></p><?php endif; ?>
</div>
