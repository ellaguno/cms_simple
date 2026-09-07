<?php /** Contacto. $b: title, text, email, phone, address, form, button_text */ declare(strict_types=1); ?>
<div class="lz-container lz-contact">
  <div class="lz-contact-info">
    <?= lz_head((string) $b['title'], (string) $b['text']) ?>
    <ul class="lz-contact-list">
<?php if (trim((string) $b['email']) !== ''): ?>      <li><a href="mailto:<?= cms_e($b['email']) ?>"><?= cms_e($b['email']) ?></a></li><?php endif; ?>
<?php if (trim((string) $b['phone']) !== ''): ?>      <li><a href="tel:<?= cms_e(preg_replace('/[^\d+]/', '', (string) $b['phone'])) ?>"><?= cms_e($b['phone']) ?></a></li><?php endif; ?>
<?php if (trim((string) $b['address']) !== ''): ?>      <li><?= cms_e($b['address']) ?></li><?php endif; ?>
    </ul>
  </div>
<?php if (!empty($b['form'])): ?>
  <form class="lz-form" method="post" action="<?= CMS_BASE ?>/_cms/form" data-lz-form>
    <input type="text" name="empresa_web2" tabindex="-1" autocomplete="off" class="lz-hp" aria-hidden="true">
    <label>Nombre<input type="text" name="nombre" required></label>
    <label>Correo<input type="email" name="correo" required></label>
    <label>Mensaje<textarea name="mensaje" rows="4"></textarea></label>
    <button class="lz-btn lz-btn-primary" type="submit"><?= cms_e($b['button_text'] ?: 'Enviar') ?></button>
    <p class="lz-form-msg" data-lz-form-msg hidden></p>
  </form>
<?php endif; ?>
</div>
