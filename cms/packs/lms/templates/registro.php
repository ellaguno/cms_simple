<?php
/** Paquete lms — /aula/registro: alta del propio alumno (si Ajustes → Aula lo permite). Reemplazable con templates/lms/registro.php. */
$code = lms_settings()['signup_code'] !== '';
$v = fn(string $k) => cms_e((string) ($_POST[$k] ?? ''));
?>
<section class="phead lms-head">
  <div class="wrap phead-in">
    <p class="kicker lms-kicker"><?= cms_e(lms_tx('my_classroom')) ?></p>
    <h1><?= cms_e(lms_tx('signup_title')) ?></h1>
    <p class="lead"><?= cms_e(lms_tx('signup_lead')) ?></p>
  </div>
</section>
<section class="sec">
  <div class="wrap lms-narrow">
    <div class="card lms-form-card">
      <?= lms_notice() ?>
      <form method="post" class="form" action="<?= cms_e(lms_url('registro')) ?>">
        <?= lms_csrf_field() ?>
        <div class="lms-hp" aria-hidden="true"><label>Web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="field"><label for="lms-name"><?= cms_e(lms_tx('name')) ?></label><input id="lms-name" type="text" name="name" required autocomplete="name" value="<?= $v('name') ?>"></div>
        <div class="field"><label for="lms-email"><?= cms_e(lms_tx('email')) ?></label><input id="lms-email" type="email" name="email" required autocomplete="email" value="<?= $v('email') ?>"></div>
        <div class="form-row">
          <div class="field"><label for="lms-pass"><?= cms_e(lms_tx('password_new')) ?></label><input id="lms-pass" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
          <div class="field"><label for="lms-pass2"><?= cms_e(lms_tx('password_rep')) ?></label><input id="lms-pass2" type="password" name="password2" required minlength="8" autocomplete="new-password"></div>
        </div>
<?php if ($code): ?>
        <div class="field"><label for="lms-code"><?= cms_e(lms_tx('code')) ?></label><input id="lms-code" type="text" name="code" required autocomplete="off" value="<?= $v('code') ?>"></div>
<?php endif; ?>
        <div><button class="btn" type="submit"><?= cms_e(lms_tx('signup')) ?> <?= lms_icon('arrow') ?></button></div>
      </form>
      <p class="form-note lms-alt"><?= cms_e(lms_tx('have_account')) ?> <a href="<?= cms_e(lms_url('entrar')) ?>"><?= cms_e(lms_tx('login')) ?></a></p>
    </div>
  </div>
</section>
