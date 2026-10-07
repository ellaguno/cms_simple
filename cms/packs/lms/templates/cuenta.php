<?php
/** Paquete lms — /aula/cuenta: nombre y contraseña del alumno. Reemplazable con templates/lms/cuenta.php. */
$user = lms_user();
?>
<section class="phead lms-head">
  <div class="wrap phead-in">
    <nav class="crumbs" aria-label="Ruta"><a href="<?= cms_e(lms_url()) ?>"><?= cms_e(lms_tx('my_classroom')) ?></a><span>/</span><em style="font-style:normal"><?= cms_e(lms_tx('account')) ?></em></nav>
    <h1><?= cms_e(lms_tx('account')) ?></h1>
    <p class="lead"><?= cms_e((string) $user['email']) ?></p>
  </div>
</section>
<section class="sec">
  <div class="wrap lms-narrow">
    <div class="card lms-form-card">
      <?= lms_notice() ?>
      <form method="post" class="form" action="<?= cms_e(lms_url('cuenta')) ?>" autocomplete="off">
        <?= lms_csrf_field() ?>
        <div class="field"><label for="lms-name"><?= cms_e(lms_tx('name')) ?></label><input id="lms-name" type="text" name="name" required value="<?= cms_e((string) $user['name']) ?>"></div>
        <div class="field"><label for="lms-cur"><?= cms_e(lms_tx('password_cur')) ?></label><input id="lms-cur" type="password" name="current" autocomplete="current-password"></div>
        <div class="form-row">
          <div class="field"><label for="lms-new"><?= cms_e(lms_tx('password_new')) ?></label><input id="lms-new" type="password" name="new" minlength="8" autocomplete="new-password"></div>
          <div class="field"><label for="lms-rep"><?= cms_e(lms_tx('password_rep')) ?></label><input id="lms-rep" type="password" name="repeat" minlength="8" autocomplete="new-password"></div>
        </div>
        <div class="btnrow" style="margin-top:0"><button class="btn" type="submit"><?= cms_e(lms_tx('save')) ?></button><a class="btn btn-ghost" href="<?= cms_e(lms_url()) ?>"><?= lms_icon('back') ?> <?= cms_e(lms_tx('my_classroom')) ?></a></div>
      </form>
    </div>
  </div>
</section>
