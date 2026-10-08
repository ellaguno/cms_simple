<?php
/**
 * Paquete lms — /aula/verificar: comprobar el código de una constancia (pública, sin entrar). El tema puede
 * reemplazarla con templates/lms/verificar.php.
 */
$check = $GLOBALS['lms_cert_check'] ?? null;   // null = sin código; false = no existe; array = encontrado
$code = (string) ($_GET['c'] ?? '');
?>
<section class="phead lms-head">
  <div class="wrap phead-in">
    <p class="kicker lms-kicker"><?= cms_e(lms_tx('cert')) ?></p>
    <h1><?= cms_e(lms_tx('cert_verify')) ?></h1>
    <p class="lead"><?= cms_e(lms_tx('cert_verify_lead')) ?></p>
  </div>
</section>
<section class="sec">
  <div class="wrap lms-narrow">
<?php if (is_array($check)): $e = $check['entry']; $co = lms_course((string) $e['course'], false); $who = lms_user_get((string) $e['uid']); ?>
    <div class="card lms-form-card lms-verify <?= $check['valid'] ? 'is-ok' : 'is-bad' ?>">
      <p class="lms-verify-head"><?= lms_icon($check['valid'] ? 'done' : 'x') ?> <strong><?= cms_e($check['valid'] ? lms_tx('cert_valid') : lms_tx('cert_invalid')) ?></strong></p>
      <p><?= cms_e(lms_tx('cert_valid_text', $who ? (string) $who['name'] : (string) $e['name'], $co ? (string) cms_f($co, 'title', $lang) : (string) $e['title'], lms_date((string) $e['date']))) ?></p>
      <p class="form-note"><?= cms_e(lms_tx('cert_code')) ?>: <code><?= cms_e($check['code']) ?></code></p>
    </div>
<?php elseif ($check === false): ?>
    <p class="form-msg err lms-msg" role="alert"><?= cms_e(lms_tx('cert_unknown')) ?></p>
<?php endif; ?>
    <div class="card lms-form-card" style="margin-top:1.4rem">
      <form method="get" class="form" action="<?= cms_e(lms_url('verificar')) ?>">
        <div class="field"><label for="lms-code"><?= cms_e(lms_tx('cert_code')) ?></label><input id="lms-code" type="text" name="c" required placeholder="ABCD-2345" value="<?= cms_e($code) ?>" autocomplete="off" style="text-transform:uppercase;letter-spacing:.08em"></div>
        <div><button class="btn" type="submit"><?= cms_e(lms_tx('cert_check')) ?> <?= lms_icon('arrow') ?></button></div>
      </form>
    </div>
  </div>
</section>
