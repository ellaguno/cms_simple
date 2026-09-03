<?php
declare(strict_types=1);

$me = admin_user();
$users = cms_users();

if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    $user = strtolower(admin_post('user'));

    if ($action === 'add') {
        $pass = (string) ($_POST['pass'] ?? '');
        $exists = (bool) array_filter($users, fn($u) => ($u['user'] ?? '') === $user);
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $user)) admin_flash('El usuario debe tener de 3 a 30 caracteres: letras minúsculas, números, punto, guion o guion bajo.', 'err');
        elseif ($exists) admin_flash('Ese usuario ya existe.', 'err');
        elseif (strlen($pass) < 10) admin_flash('La contraseña debe tener al menos 10 caracteres.', 'err');
        else {
            $users[] = ['user' => $user, 'name' => admin_post('name') ?: $user, 'hash' => password_hash($pass, PASSWORD_DEFAULT), 'created' => date('Y-m-d')];
            if (cms_json_write(CMS_DATA . '/users.json', $users)) admin_flash('Usuario "' . $user . '" creado.');
            else admin_flash('No se pudo guardar users.json.', 'err');
        }
    } elseif ($action === 'reset') {
        $pass = (string) ($_POST['pass'] ?? '');
        if (strlen($pass) < 10) admin_flash('La contraseña debe tener al menos 10 caracteres.', 'err');
        else {
            $found = false;
            foreach ($users as &$u) if (($u['user'] ?? '') === $user) { $u['hash'] = password_hash($pass, PASSWORD_DEFAULT); $found = true; }
            unset($u);
            if ($found && cms_json_write(CMS_DATA . '/users.json', $users)) admin_flash('Contraseña de "' . $user . '" actualizada.');
            else admin_flash('No se pudo actualizar.', 'err');
        }
    } elseif ($action === 'delete') {
        if ($user === ($me['user'] ?? '')) admin_flash('No puedes eliminar tu propio usuario.', 'err');
        elseif (count($users) <= 1) admin_flash('Debe quedar al menos un usuario.', 'err');
        else {
            $users = array_values(array_filter($users, fn($u) => ($u['user'] ?? '') !== $user));
            if (cms_json_write(CMS_DATA . '/users.json', $users)) admin_flash('Usuario "' . $user . '" eliminado.');
            else admin_flash('No se pudo guardar users.json.', 'err');
        }
    }
    admin_redirect(admin_url('users'));
}

admin_header('Usuarios', 'users');
?>
<p class="ad-help">Todos los usuarios tienen los mismos permisos de administración. Cada quien puede cambiar su propia contraseña en "Contraseña"; desde aquí puedes restablecer la de otros.</p>
<div class="ad-grid2">
  <section class="ad-box">
    <h2>Usuarios actuales</h2>
    <table class="ad-table">
      <thead><tr><th>Usuario</th><th>Nombre</th><th>Creado</th><th></th></tr></thead>
      <tbody>
<?php foreach ($users as $u): $self = ($u['user'] ?? '') === ($me['user'] ?? ''); ?>
        <tr>
          <td><strong><?= cms_e($u['user'] ?? '') ?></strong><?= $self ? ' <span class="ad-pill on">tú</span>' : '' ?></td>
          <td><?= cms_e($u['name'] ?? '') ?></td>
          <td><?= cms_e($u['created'] ?? '—') ?></td>
          <td class="ad-row-actions">
            <details class="ad-details">
              <summary class="ad-btn ad-btn-sm ad-btn-light">Nueva contraseña</summary>
              <form method="post" class="ad-inline-form">
                <?= admin_csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="user" value="<?= cms_e($u['user'] ?? '') ?>">
                <input type="password" name="pass" minlength="10" required placeholder="mínimo 10 caracteres" autocomplete="new-password">
                <button class="ad-btn ad-btn-sm" type="submit">Guardar</button>
              </form>
            </details>
<?php if (!$self && count($users) > 1): ?>
            <form method="post" class="ad-inline" data-confirm="¿Eliminar al usuario <?= cms_e($u['user'] ?? '') ?>?">
              <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="user" value="<?= cms_e($u['user'] ?? '') ?>">
              <button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Eliminar</button>
            </form>
<?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <section class="ad-box">
    <h2>Agregar usuario</h2>
    <form method="post" class="ad-form" autocomplete="off">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="ad-field"><label>Usuario (para entrar)</label><input type="text" name="user" required pattern="[a-z0-9._\-]{3,30}" placeholder="ej. hermano"></div>
      <div class="ad-field"><label>Nombre para mostrar</label><input type="text" name="name" placeholder="Nombre"></div>
      <div class="ad-field"><label>Contraseña (mínimo 10 caracteres)</label><input type="password" name="pass" required minlength="10" autocomplete="new-password"></div>
      <button class="ad-btn" type="submit">Crear usuario</button>
    </form>
  </section>
</div>
<?php admin_footer();
