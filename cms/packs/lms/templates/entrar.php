<?php
/** Paquete lms — /aula/entrar: formulario de acceso del alumno. El tema puede reemplazarlo con templates/lms/entrar.php. */
$back = lms_back((string) ($_REQUEST['r'] ?? ''), '');
?>
<section class="phead lms-head">
  <div class="wrap phead-in">
    <p class="kicker lms-kicker"><?= cms_e(lms_tx('my_classroom')) ?></p>
    <h1><?= cms_e(lms_tx('login_title')) ?></h1>
    <p class="lead"><?= cms_e(lms_tx('login_lead')) ?></p>
  </div>
</section>
<section class="sec">
  <div class="wrap lms-narrow">
    <div class="card lms-form-card">
      <?= lms_notice() ?>
      <form method="post" class="form" action="<?= cms_e(lms_url('entrar')) ?>">
        <?= lms_csrf_field() ?><?php if ($back !== ''): ?><input type="hidden" name="r" value="<?= cms_e($back) ?>"><?php endif; ?>
        <div class="field"><label for="lms-email"><?= cms_e(lms_tx('email')) ?></label><input id="lms-email" type="email" name="email" required autocomplete="username" value="<?= cms_e((string) ($_POST['email'] ?? '')) ?>"></div>
        <div class="field"><label for="lms-pass"><?= cms_e(lms_tx('password')) ?></label><input id="lms-pass" type="password" name="password" required autocomplete="current-password"></div>
        <label class="lms-check"><input type="checkbox" name="remember" value="1"> <span><?= cms_e(lms_tx('remember')) ?></span></label>
        <div><button class="btn" type="submit"><?= cms_e(lms_tx('login')) ?> <?= lms_icon('arrow') ?></button></div>
      </form>
      <p class="form-note lms-alt"><?= cms_e(lms_tx('no_account')) ?>
<?php if (lms_settings()['signup']): ?> <a href="<?= cms_e(lms_url('registro')) ?>"><?= cms_e(lms_tx('signup')) ?></a>
<?php else: ?> <?= cms_e(lms_tx('ask_account')) ?><?php endif; ?></p>
    </div>
    <?php cms_do('lms.login.extra', $back); /* otras formas de entrar que añaden los paquetes (p. ej. con la cuenta de una organización) */ ?>
  </div>
</section>
