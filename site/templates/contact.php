<?php /** Contacto. Variables: $lang, $S, $t, $page */ declare(strict_types=1); ?>
<section class="wrap narrow">
  <h1><?= cms_e($t('contact_title', 'Contacto')) ?></h1>
  <p class="lead"><?= cms_e($t('contact_intro')) ?></p>
<?php if (!empty($S['email'])): ?>  <p><a href="mailto:<?= cms_e($S['email']) ?>"><?= cms_e($S['email']) ?></a><?php if ($wa = cms_whatsapp_url()): ?> · <a href="<?= cms_e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></p><?php endif; ?>
  <form id="cms-form" class="form" action="<?= CMS_BASE ?>/_cms/form" method="post" novalidate
        data-msg-sending="<?= cms_e($t('f_sending', 'Enviando…')) ?>" data-msg-ok="<?= cms_e($t('f_ok', '¡Gracias! Recibimos tu mensaje.')) ?>" data-msg-err="<?= cms_e($t('f_err', 'No se pudo enviar. Escríbenos por correo.')) ?>" data-msg-invalid="<?= cms_e($t('f_invalid', 'Escribe tu nombre y un correo válido.')) ?>" data-mailto="<?= cms_e($S['email'] ?? '') ?>">
    <input type="hidden" name="lang" value="<?= $lang ?>">
    <label><?= cms_e($t('f_name', 'Nombre')) ?><input type="text" name="nombre" required autocomplete="name"></label>
    <label><?= cms_e($t('f_email', 'Correo electrónico')) ?><input type="email" name="correo" required autocomplete="email"></label>
    <label><?= cms_e($t('f_message', 'Mensaje')) ?><textarea name="mensaje" rows="6"></textarea></label>
    <div class="hp" aria-hidden="true"><input type="text" name="empresa_web2" tabindex="-1" autocomplete="off"></div>
    <button class="btn" type="submit"><?= cms_e($t('f_send', 'Enviar')) ?></button>
    <p id="cms-form-msg" class="form-msg" role="status" aria-live="polite"></p>
  </form>
</section>
