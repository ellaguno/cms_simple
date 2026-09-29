<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/api.php';

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
    } elseif ($action === 'me') {   // contraseña y nombre propios (antes, página "Contraseña")
        $cur = (string) ($_POST['current'] ?? ''); $new = (string) ($_POST['new'] ?? ''); $rep = (string) ($_POST['repeat'] ?? '');
        if (!password_verify($cur, (string) ($me['hash'] ?? ''))) admin_flash('La contraseña actual no es correcta.', 'err');
        elseif (strlen($new) < 10) admin_flash('La nueva contraseña debe tener al menos 10 caracteres.', 'err');
        elseif ($new !== $rep) admin_flash('Las contraseñas nuevas no coinciden.', 'err');
        else {
            foreach ($users as &$row) if (($row['user'] ?? '') === ($me['user'] ?? '')) { $row['hash'] = password_hash($new, PASSWORD_DEFAULT); if (admin_post('name') !== '') $row['name'] = admin_post('name'); }
            unset($row);
            if (cms_json_write(CMS_DATA . '/users.json', $users)) admin_flash('Contraseña actualizada.');
            else admin_flash('No se pudo guardar users.json.', 'err');
        }
    } elseif ($action === 'token_create') {   // token para la API (/admin/api/…): se enseña una sola vez
        $types = array_map('strval', (array) ($_POST['types'] ?? []));
        $tok = api_token_create((string) ($me['user'] ?? ''), admin_post('label'), in_array('*', $types, true) || !$types ? ['*'] : $types);
        if ($tok !== '') { $_SESSION['api_token_new'] = $tok; admin_flash('Token creado. Cópialo ahora: no se vuelve a mostrar.'); }
        else admin_flash('No se pudo guardar data/api-tokens.json.', 'err');
    } elseif ($action === 'token_revoke') {
        admin_flash(api_token_revoke(admin_post('id')) ? 'Token revocado.' : 'No se encontró ese token.', 'ok');
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
<?php $newTok = (string) ($_SESSION['api_token_new'] ?? ''); unset($_SESSION['api_token_new']); ?>
<p class="ad-help">Todos los usuarios tienen los mismos permisos de administración. Tu contraseña la cambias abajo; la de otros se restablece desde su fila.</p>
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
  <div>
  <section class="ad-box">
    <h2>Tu contraseña</h2>
    <form method="post" class="ad-form" autocomplete="off">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="me">
      <div class="ad-field"><label>Nombre para mostrar</label><input type="text" name="name" value="<?= cms_e($me['name'] ?? '') ?>"></div>
      <div class="ad-field"><label>Contraseña actual</label><input type="password" name="current" required autocomplete="current-password"></div>
      <div class="ad-two">
        <div class="ad-field"><label>Nueva contraseña (mínimo 10)</label><input type="password" name="new" required minlength="10" autocomplete="new-password"></div>
        <div class="ad-field"><label>Repetir nueva contraseña</label><input type="password" name="repeat" required minlength="10" autocomplete="new-password"></div>
      </div>
      <button class="ad-btn" type="submit">Cambiar mi contraseña</button>
    </form>
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
</div>
<section class="ad-box" id="api">
  <h2>Acceso por API</h2>
  <p class="ad-help">Para publicar desde otros programas sin pasar por los formularios: <code><?= cms_e(cms_site_url()) ?>/admin/api/</code> con la cabecera <code>Authorization: Bearer &lt;token&gt;</code> (o <code>X-CMS-Token</code>). Ver el manual, «API y línea de comandos». Cada token actúa como el usuario que lo creó; revócalo si deja de usarse.</p>
<?php if ($newTok !== ''): ?>
  <div class="ad-flash ok"><strong>Token nuevo (se muestra una sola vez):</strong><br><input type="text" readonly value="<?= cms_e($newTok) ?>" onclick="this.select()" style="width:100%;font-family:monospace"></div>
<?php endif; $toks = api_tokens(); if ($toks): ?>
  <table class="ad-table">
    <thead><tr><th>Nombre</th><th>Usuario</th><th>Alcance</th><th>Creado</th><th>Último uso</th><th></th></tr></thead>
    <tbody>
<?php foreach ($toks as $t): ?>
      <tr>
        <td><strong><?= cms_e((string) ($t['label'] ?? '')) ?></strong> <small class="ad-help">cms_<?= cms_e((string) ($t['id'] ?? '')) ?>_…</small></td>
        <td><?= cms_e((string) ($t['user'] ?? '')) ?></td>
        <td><?= in_array('*', (array) ($t['types'] ?? []), true) ? 'Todo el contenido' : cms_e(implode(', ', array_map(fn($k) => (string) (cms_type((string) $k)['label'] ?? $k), (array) ($t['types'] ?? [])))) ?></td>
        <td><?= cms_e((string) ($t['created'] ?? '')) ?></td>
        <td><?= cms_e((string) ($t['last_used'] ?? '') ?: '—') ?></td>
        <td class="ad-row-actions"><form method="post" class="ad-inline" data-confirm="¿Revocar el token «<?= cms_e((string) ($t['label'] ?? '')) ?>»? Los programas que lo usen dejarán de funcionar."><?= admin_csrf_field() ?><input type="hidden" name="action" value="token_revoke"><input type="hidden" name="id" value="<?= cms_e((string) ($t['id'] ?? '')) ?>"><button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Revocar</button></form></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
  <form method="post" class="ad-form" autocomplete="off">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="token_create">
    <div class="ad-field"><label>Nombre del token (para reconocerlo)</label><input type="text" name="label" required maxlength="60" placeholder="ej. Rutina de novedades"></div>
    <div class="ad-field"><label>Puede leer y escribir</label>
      <label class="ad-check"><input type="checkbox" name="types[]" value="*" checked> Todo el contenido</label>
<?php foreach ((array) cms_config('types', []) as $k => $d): if (!cms_type((string) $k)) continue; ?>
      <label class="ad-check"><input type="checkbox" name="types[]" value="<?= cms_e((string) $k) ?>"> <?= cms_e((string) ($d['label'] ?? $k)) ?></label>
<?php endforeach; ?>
      <p class="ad-help">Desmarca «Todo el contenido» para limitarlo a las colecciones elegidas. Subir medios está permitido a cualquier token.</p>
    </div>
    <button class="ad-btn" type="submit">Crear token</button>
  </form>
</section>
<?php admin_footer();
