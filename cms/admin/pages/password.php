<?php
declare(strict_types=1);

if (admin_is_post()) {
    admin_csrf_check();
    $u = admin_user();
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $rep = (string) ($_POST['repeat'] ?? '');
    if (!password_verify($cur, (string) ($u['hash'] ?? ''))) admin_flash('La contraseña actual no es correcta.', 'err');
    elseif (strlen($new) < 10) admin_flash('La nueva contraseña debe tener al menos 10 caracteres.', 'err');
    elseif ($new !== $rep) admin_flash('Las contraseñas nuevas no coinciden.', 'err');
    else {
        $users = cms_users();
        foreach ($users as &$row) {
            if (($row['user'] ?? '') === $u['user']) {
                $row['hash'] = password_hash($new, PASSWORD_DEFAULT);
                if (admin_post('name') !== '') $row['name'] = admin_post('name');
            }
        }
        unset($row);
        if (cms_json_write(CMS_DATA . '/users.json', $users)) admin_flash('Contraseña actualizada.');
        else admin_flash('No se pudo guardar users.json.', 'err');
    }
    admin_redirect(admin_url('password'));
}

$u = admin_user();
admin_header('Contraseña', 'password');
?>
<form method="post" class="ad-form ad-form-narrow" autocomplete="off">
  <?= admin_csrf_field() ?>
  <div class="ad-field"><label>Usuario</label><input type="text" value="<?= cms_e($u['user']) ?>" disabled></div>
  <div class="ad-field"><label>Nombre para mostrar</label><input type="text" name="name" value="<?= cms_e($u['name'] ?? '') ?>"></div>
  <div class="ad-field"><label>Contraseña actual</label><input type="password" name="current" required autocomplete="current-password"></div>
  <div class="ad-field"><label>Nueva contraseña (mínimo 10 caracteres)</label><input type="password" name="new" required minlength="10" autocomplete="new-password"></div>
  <div class="ad-field"><label>Repetir nueva contraseña</label><input type="password" name="repeat" required minlength="10" autocomplete="new-password"></div>
  <button class="ad-btn" type="submit">Cambiar contraseña</button>
</form>
<p class="ad-help">Para agregar o eliminar usuarios ve a <a href="<?= admin_url('users') ?>">Usuarios</a>.</p>
<?php admin_footer();
