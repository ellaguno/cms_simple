<?php /** Contacto. $b: form, address, phone, email, tiles */ declare(strict_types=1);
$S = cms_settings();
$address = trim((string) $b['address']) !== '' ? (string) $b['address'] : (string) ($S['address'] ?? '');
$phone = trim((string) $b['phone']) !== '' ? (string) $b['phone'] : (string) ($S['phone'] ?? '');
$email = trim((string) $b['email']) !== '' ? (string) $b['email'] : (string) ($S['email'] ?? '');
$tel = 'tel:' . preg_replace('/[^\d+]/', '', $phone);
$social = $S['social'] ?? [];
$fb = (string) ($social['facebook'] ?? ''); $x = (string) ($social['x'] ?? '');
?>
<div class="ad-container ad-contact">
  <div class="ad-contact-grid">
<?php if (!empty($b['form'])): ?>
    <form class="ad-form" method="post" action="<?= CMS_BASE ?>/_cms/form" novalidate data-ad-form
          data-msg-sending="<?= cms_e($t('f_sending', 'Enviando…')) ?>" data-msg-ok="<?= cms_e($t('f_ok', 'Gracias, recibimos tu mensaje.')) ?>" data-msg-err="<?= cms_e($t('f_err', 'No se pudo enviar. Inténtalo de nuevo.')) ?>" data-msg-invalid="<?= cms_e($t('f_invalid', 'Escribe tu nombre y un correo válido.')) ?>">
      <div class="ad-hp" aria-hidden="true"><input type="text" name="empresa_web2" tabindex="-1" autocomplete="off"></div>
      <label class="ad-field"><span class="ad-sr"><?= cms_e($t('f_email', 'nombre@email.com')) ?></span><input type="email" name="correo" required placeholder="<?= cms_e($t('f_email', 'nombre@email.com')) ?>" autocomplete="email"></label>
      <label class="ad-field"><span class="ad-sr"><?= cms_e($t('f_name', 'Su nombre')) ?></span><input type="text" name="nombre" required placeholder="<?= cms_e($t('f_name', 'Su nombre')) ?>" autocomplete="name"></label>
      <label class="ad-field ad-field-area"><span class="ad-sr"><?= cms_e($t('f_message', 'Agregue su mensaje…')) ?></span><textarea name="mensaje" rows="6" placeholder="<?= cms_e($t('f_message', 'Agregue su mensaje…')) ?>"></textarea></label>
      <div class="ad-form-foot"><button type="submit" class="ad-form-btn"><?= cms_e($t('f_send', 'Enviar')) ?></button></div>
      <p class="ad-form-msg" data-ad-form-msg role="status" aria-live="polite" hidden></p>
    </form>
<?php endif; ?>
    <div class="ad-contact-info">
<?php if ($address !== ''): ?>      <h3><?= cms_e($t('c_address', 'DIRECCIÓN:')) ?></h3><p><?= nl2br(cms_e($address)) ?></p>
<?php endif; ?>
<?php if ($phone !== ''): ?>      <h3><?= cms_e($t('c_phone', 'TELÉFONO:')) ?></h3><p><a href="<?= cms_e($tel) ?>"><?= cms_e($phone) ?></a></p>
<?php endif; ?>
<?php if ($email !== ''): ?>      <h3><?= cms_e($t('c_email', 'EMAIL:')) ?></h3><p><a href="mailto:<?= cms_e($email) ?>"><?= cms_e($email) ?></a></p>
<?php endif; ?>
    </div>
  </div>
<?php if (!empty($b['tiles']) && ($fb !== '' || $x !== '')): ?>
  <div class="ad-social-tiles">
<?php if ($fb !== ''): ?>    <a class="ad-social-tile ad-tile-fb" href="<?= cms_e($fb) ?>" target="_blank" rel="noopener"><span class="ad-social-tile-name"><?= cms_e($t('c_facebook', 'FACEBOOK')) ?></span><?= ad_svg('facebook') ?><span class="ad-social-tile-text"><?= cms_e($t('c_facebook_text', 'HAZTE FAN')) ?></span></a>
<?php endif; ?>
<?php if ($x !== ''): ?>    <a class="ad-social-tile ad-tile-x" href="<?= cms_e($x) ?>" target="_blank" rel="noopener"><span class="ad-social-tile-name"><?= cms_e($t('c_twitter', 'X')) ?></span><?= ad_svg('x') ?><span class="ad-social-tile-text"><?= cms_e($t('c_twitter_text', 'SÍGUENOS')) ?></span></a>
<?php endif; ?>
  </div>
<?php endif; ?>
</div>
