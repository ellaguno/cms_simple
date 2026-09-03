<?php
declare(strict_types=1);
if (admin_user()) admin_redirect(admin_url());

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$error = '';
$wait = admin_throttle_blocked($ip);
$users = cms_users();

// Primer arranque: si no hay usuarios, se crea el primero desde aquí.
if (!$users && admin_is_post() && admin_post('action') === 'setup') {
    admin_csrf_check();
    $user = strtolower(admin_post('user'));
    $pass = (string) ($_POST['pass'] ?? '');
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $user)) $error = 'Usuario inválido (3 a 30 caracteres: minúsculas, números, punto, guion).';
    elseif (strlen($pass) < 10) $error = 'La contraseña debe tener al menos 10 caracteres.';
    elseif (!cms_json_write(CMS_DATA . '/users.json', [['user' => $user, 'name' => admin_post('name') ?: $user, 'hash' => password_hash($pass, PASSWORD_DEFAULT), 'created' => date('Y-m-d')]])) $error = 'No se pudo escribir data/users.json. Revisa permisos de la carpeta data/.';
    else { admin_flash('Usuario creado. Ya puedes entrar.'); admin_redirect(admin_url('login')); }
} elseif ($users && admin_is_post() && !$wait) {
    admin_csrf_check();
    $user = admin_post('user');
    $pass = (string) ($_POST['pass'] ?? '');
    $found = null;
    foreach ($users as $u) if (hash_equals((string) ($u['user'] ?? ''), $user)) $found = $u;
    if ($found && password_verify($pass, (string) ($found['hash'] ?? ''))) {
        admin_throttle_record($ip, true);
        session_regenerate_id(true);
        $_SESSION['user'] = $found['user'];
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        $to = $_SESSION['after_login'] ?? admin_url();
        unset($_SESSION['after_login']);
        if (!is_string($to) || strpos($to, ADMIN_URL) !== 0 || strpos($to, 'p=logout') !== false) $to = admin_url();
        admin_redirect($to);
    }
    admin_throttle_record($ip, false);
    usleep(400000);
    $wait = admin_throttle_blocked($ip);
    $error = $wait ? '' : 'Usuario o contraseña incorrectos.';
}
if ($wait) $error = 'Demasiados intentos. Espera ' . (int) ceil($wait / 60) . ' minutos.';

$S = cms_settings();
$logo = cms_config('admin_logo') ?: ($S['logo'] ?? '');
admin_header($users ? 'Iniciar sesión' : 'Crear el primer usuario');
?>
<div class="ad-login">
<?php if ($logo): ?>  <img src="<?= cms_e(cms_img($logo)) ?>" alt="" class="ad-login-logo">
<?php else: ?>  <h2 class="text-center"><?= cms_e($S['site_name'] ?? cms_config('name')) ?></h2>
<?php endif; ?>
<?php if ($error): ?><div class="ad-flash err"><?= cms_e($error) ?></div><?php endif; ?>
<?php if (!$users): ?>
  <p class="ad-help">Aún no hay usuarios. Crea el primero (administrador).</p>
  <form method="post" class="ad-form" autocomplete="off">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="setup">
    <div class="ad-field"><label for="user">Usuario</label><input id="user" type="text" name="user" required pattern="[a-z0-9._\-]{3,30}" placeholder="admin"></div>
    <div class="ad-field"><label for="name">Nombre</label><input id="name" type="text" name="name"></div>
    <div class="ad-field"><label for="pass">Contraseña (mínimo 10 caracteres)</label><input id="pass" type="password" name="pass" required minlength="10" autocomplete="new-password"></div>
    <button class="ad-btn" type="submit">Crear usuario</button>
  </form>
<?php else: ?>
  <form method="post" class="ad-form">
    <?= admin_csrf_field() ?>
    <div class="ad-field"><label for="user">Usuario</label><input id="user" type="text" name="user" required autocomplete="username" autofocus></div>
    <div class="ad-field"><label for="pass">Contraseña</label><input id="pass" type="password" name="pass" required autocomplete="current-password"></div>
    <button class="ad-btn" type="submit"<?= $wait ? ' disabled' : '' ?>>Entrar</button>
  </form>
<?php endif; ?>
  <p class="ad-help"><a href="<?= cms_url('home', cms_default_lang()) ?>">← Volver al sitio</a></p>
</div>
<?php admin_footer();
