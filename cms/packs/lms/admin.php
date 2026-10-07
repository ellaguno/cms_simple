<?php
/**
 * Página "Alumnos y avance" del panel (?p=pack:lms). $pack trae el manifiesto.
 *   (sin parámetros)   alumnos: buscador, avance general, alta (con contraseña generada y aviso por correo opcional)
 *   &id=<alumno>        ficha: datos, contraseña, activo, inscripciones y avance lección por lección
 *   &tab=cursos         cursos: lecciones, inscritos, terminados y avance promedio
 *   &course=<curso>     un curso: cada alumno con su avance, inscribir por lista de correos
 *   &csv=alumnos|<curso>  exportación CSV (UTF-8 con BOM, para Excel)
 *   &tab=importar[&dir=<carpeta>]  importar un curso hecho a mano en Archivos y carpetas (import.php)
 */
declare(strict_types=1);
require_once __DIR__ . '/import.php';
$self = 'pack:' . $pack['name'];
$url = fn(array $q = []) => admin_url($self, $q);
$lang = cms_default_lang();
$ct = lms_course_type();
$courses = lms_courses(false);
$courseTitle = fn(string $s) => isset($courses[$s]) ? (string) (cms_f($courses[$s], 'title', $lang) ?: $s) : $s;
$users = lms_users();
uasort($users, fn($a, $b) => strcasecmp((string) $a['name'], (string) $b['name']));

/** Avance de todos los alumnos: id => [curso => stats]. Lee un archivo por alumno. */
$allStats = function () use ($users, $courses): array {
    $out = [];
    foreach ($users as $id => $u) {
        $p = lms_progress((string) $id)['courses'];
        foreach ($p as $cs => $c) if (isset($courses[$cs])) $out[$id][$cs] = lms_stats_from((array) $c, lms_lessons((string) $cs)) + ['since' => (string) ($c['enrolled'] ?? ''), 'by' => (string) ($c['by'] ?? '')];
        $out[$id]['_seen'] = (string) (lms_progress((string) $id)['seen'] ?? '');
    }
    return $out;
};

// ------------------------------------------------------------------ CSV
if (isset($_GET['csv'])) {
    $which = (string) $_GET['csv'];
    $stats = $allStats();
    $rows = [];
    if ($which === 'alumnos') {
        $rows[] = ['Nombre', 'Correo', 'Activo', 'Alta', 'Última visita', 'Curso', 'Inscrito', 'Lecciones terminadas', 'Lecciones del curso', 'Avance %', 'Terminado'];
        foreach ($users as $id => $u) {
            $any = false;
            foreach ($stats[$id] ?? [] as $cs => $s) {
                if ($cs === '_seen') continue;
                $any = true;
                $rows[] = [$u['name'], $u['email'], !empty($u['active']) ? 'sí' : 'no', $u['created'] ?? '', $stats[$id]['_seen'] ?? '', $courseTitle($cs), $s['since'], $s['done'], $s['total'], $s['pct'], $s['completed']];
            }
            if (!$any) $rows[] = [$u['name'], $u['email'], !empty($u['active']) ? 'sí' : 'no', $u['created'] ?? '', $stats[$id]['_seen'] ?? '', '', '', '', '', '', ''];
        }
        $name = 'alumnos';
    } elseif (isset($courses[$which])) {
        $ls = lms_lessons($which);
        $rows[] = array_merge(['Nombre', 'Correo', 'Inscrito', 'Avance %', 'Terminado'], array_map(fn($l) => (string) cms_f($l, 'title', $lang), $ls));
        foreach ($users as $id => $u) {
            if (!isset($stats[$id][$which])) continue;
            $s = $stats[$id][$which];
            $rows[] = array_merge([$u['name'], $u['email'], $s['since'], $s['pct'], $s['completed']], array_map(fn($l) => (string) ($s['lessons'][$l['slug']] ?? ''), $ls));
        }
        $name = 'curso-' . $which;
    } else admin_redirect($url());
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aula-' . $name . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $r) fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, $r));   // sin fórmulas al abrir en Excel
    exit;
}

// ------------------------------------------------------------------ acciones
if (admin_is_post()) {
    admin_csrf_check();
    $action = admin_post('action');
    $id = preg_replace('/[^a-f0-9]/', '', admin_post('id'));
    $course = cms_slugify(admin_post('course'));
    $back = $url($id !== '' && $action !== 'delete' ? ['id' => $id] : []);
    $sendAccess = function (array $u, string $pass, array $cs) use ($lang): bool {
        $site = (string) (cms_settings()['site_name'] ?? cms_config('name'));
        $body = "Hola, " . $u['name'] . ".\n\nTe dimos de alta en el aula de $site.\n\n"
            . "Entra en: " . cms_origin() . lms_url('entrar', $lang) . "\nCorreo: " . $u['email'] . "\nContraseña: $pass\n\n"
            . ($cs ? "Cursos: " . implode(', ', $cs) . "\n\n" : '')
            . "Puedes cambiar tu contraseña en «Mi cuenta» después de entrar.\n";
        return lms_mail((string) $u['email'], "Tu acceso al aula de $site", $body);
    };

    if ($action === 'import') {
        [$ok, $msgs] = lms_import_run(admin_post('dir'), ['publish' => !empty($_POST['publish']), 'soon' => !empty($_POST['soon']), 'redirect' => !empty($_POST['redirect'])]);
        admin_flash(implode(' ', $msgs), $ok ? 'ok' : 'err');
        admin_redirect($ok ? $url(['tab' => 'cursos']) : $url(['tab' => 'importar', 'dir' => admin_post('dir')]));
    }
    if ($action === 'add') {
        $pass = (string) ($_POST['pass'] ?? '');
        if ($pass === '') $pass = lms_password_gen();
        [$ok, $r] = lms_user_create(admin_post('name'), admin_post('email'), $pass, admin_post('notes'));
        if (!$ok) { admin_flash(['err_name' => 'Falta el nombre.', 'err_email' => 'El correo no es válido.', 'err_exists' => 'Ya hay un alumno con ese correo.', 'err_pass' => 'La contraseña debe tener al menos 8 caracteres.'][$r] ?? 'No se pudo guardar data/lms/users.json.', 'err'); admin_redirect($url()); }
        $names = [];
        foreach ((array) ($_POST['courses'] ?? []) as $cs) { $cs = cms_slugify((string) $cs); if (isset($courses[$cs])) { lms_enroll($r, $cs, 'admin'); $names[] = $courseTitle($cs); } }
        $u = lms_user_get($r);
        $mailed = !empty($_POST['send']) && $sendAccess($u, $pass, $names);
        admin_flash('Alumno dado de alta. Contraseña: ' . $pass . ($mailed ? ' (enviada por correo).' : (!empty($_POST['send']) ? ' — el correo no salió: compártela tú.' : ' — cópiala ahora: no se vuelve a mostrar.')));
        admin_redirect($url(['id' => $r]));
    }
    $u = $id !== '' ? lms_user_get($id) : null;
    if ($action === 'bulk') {   // inscribir por lista de correos en un curso
        if (!isset($courses[$course])) admin_redirect($url(['tab' => 'cursos']));
        $n = 0; $miss = [];
        foreach (preg_split('/[\s,;]+/', admin_post('emails')) ?: [] as $em) {
            if (trim($em) === '') continue;
            $x = lms_user_by_email($em);
            if ($x) { lms_enroll($x['id'], $course, 'admin'); $n++; } else $miss[] = $em;
        }
        admin_flash($n . ' inscrito(s) en «' . $courseTitle($course) . '».' . ($miss ? ' Sin cuenta (dalos de alta primero): ' . implode(', ', array_slice($miss, 0, 20)) . (count($miss) > 20 ? '…' : '') : ''), $miss ? 'err' : 'ok');
        admin_redirect($url(['course' => $course]));
    }
    if (!$u) { admin_flash('No se encontró al alumno.', 'err'); admin_redirect($url()); }
    if ($action === 'update') {
        $email = lms_email_norm(admin_post('email'));
        $other = lms_user_by_email($email);
        if (admin_post('name') === '') admin_flash('Falta el nombre.', 'err');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) admin_flash('El correo no es válido.', 'err');
        elseif ($other && $other['id'] !== $u['id']) admin_flash('Ese correo ya es de otro alumno.', 'err');
        else admin_flash(lms_user_update($u['id'], ['name' => admin_post('name'), 'email' => $email, 'notes' => admin_post('notes'), 'active' => !empty($_POST['active'])]) ? 'Datos guardados.' : 'No se pudo guardar.', 'ok');
    } elseif ($action === 'password') {
        $pass = (string) ($_POST['pass'] ?? '');
        if ($pass === '') $pass = lms_password_gen();
        if (strlen($pass) < 8) admin_flash('La contraseña debe tener al menos 8 caracteres.', 'err');
        elseif (lms_user_update($u['id'], ['pass' => $pass])) {
            $mailed = !empty($_POST['send']) && $sendAccess(lms_user_get($u['id']), $pass, []);
            admin_flash('Contraseña nueva: ' . $pass . ($mailed ? ' (enviada por correo).' : ' — compártela con el alumno; sus sesiones abiertas se cerraron.'));
        } else admin_flash('No se pudo guardar.', 'err');
    } elseif ($action === 'enroll' && isset($courses[$course])) {
        lms_enroll($u['id'], $course, 'admin');
        admin_flash('Inscrito en «' . $courseTitle($course) . '».');
    } elseif ($action === 'unenroll') {
        $wipe = !empty($_POST['wipe']);
        lms_unenroll($u['id'], $course, $wipe);
        admin_flash($wipe ? 'Inscripción y avance borrados.' : 'Inscripción quitada (el avance se conserva).');
    } elseif ($action === 'delete') {
        admin_flash(lms_user_delete($u['id']) ? 'Alumno «' . $u['name'] . '» eliminado con su avance.' : 'No se pudo eliminar.', 'ok');
    }
    admin_redirect(admin_post('back') === 'course' && $course !== '' ? $url(['course' => $course]) : $back);
}

// ------------------------------------------------------------------ vistas
$id = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['id'] ?? ''));
$course = cms_slugify((string) ($_GET['course'] ?? ''));
$tab = $course !== '' ? 'cursos' : (in_array((string) ($_GET['tab'] ?? ''), ['cursos', 'importar'], true) ? (string) $_GET['tab'] : 'alumnos');
$stats = $allStats();
$accessLabel = ['abierto' => 'Abierto', 'cuenta' => 'Con cuenta', 'inscritos' => 'Solo inscritos'];
$pill = fn(array $s) => $s['completed'] !== '' ? '<span class="ad-pill on">Terminado</span>' : ($s['done'] > 0 ? '<span class="ad-pill warn">' . $s['pct'] . ' %</span>' : '<span class="ad-pill">Sin empezar</span>');
$bar = fn(int $pct) => '<span style="display:inline-block;vertical-align:middle;width:90px;height:6px;border-radius:9px;background:#e6e6e6;overflow:hidden;margin-right:6px"><span style="display:block;height:100%;width:' . max(0, min(100, $pct)) . '%;background:#18b6ef"></span></span>' . $pct . ' %';
$ago = fn(string $d) => $d === '' ? '—' : cms_e(substr($d, 0, 16));

admin_header('Aula: alumnos y avance', $self);
?>
<div class="ad-stat-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
  <nav class="ad-tabs" style="border:0;margin:0;padding:0">
    <a href="<?= cms_e($url()) ?>"<?= $tab === 'alumnos' && $id === '' ? ' style="background:#000;color:#fff"' : '' ?>>Alumnos (<?= count($users) ?>)</a>
    <a href="<?= cms_e($url(['tab' => 'cursos'])) ?>"<?= $tab === 'cursos' ? ' style="background:#000;color:#fff"' : '' ?>>Cursos (<?= count($courses) ?>)</a>
    <a href="<?= cms_e($url(['tab' => 'importar'])) ?>"<?= $tab === 'importar' ? ' style="background:#000;color:#fff"' : '' ?>>Importar</a>
  </nav>
  <p class="ad-help" style="margin:0">Público: <a href="<?= cms_e(lms_url('', $lang)) ?>" target="_blank" rel="noopener"><?= cms_e(lms_url('', $lang)) ?></a> · <a href="<?= cms_e(cms_url('list:' . $ct, $lang)) ?>" target="_blank" rel="noopener"><?= cms_e(cms_url('list:' . $ct, $lang)) ?></a> · <a href="<?= admin_url('settings') ?>">Ajustes del aula</a></p>
</div>
<?php if (!cms_type($ct) || !cms_type(lms_lesson_type())): ?>
<p class="ad-flash err">No existe la colección «<?= cms_e(!cms_type($ct) ? $ct : lms_lesson_type()) ?>». Revisa Ajustes → Aula (colección de cursos y de lecciones).</p>
<?php endif; ?>

<?php if ($id !== '' && ($u = lms_user_get($id))): /* ============================== ficha del alumno */
    $mine = $stats[$id] ?? []; unset($mine['_seen']); ?>
<p><a href="<?= cms_e($url()) ?>">← Alumnos</a></p>
<div class="ad-grid2">
  <section class="ad-box">
    <h2><?= cms_e($u['name']) ?> <?= empty($u['active']) ? '<span class="ad-pill warn">Inactivo</span>' : '' ?></h2>
    <p class="ad-help">Alta: <?= $ago((string) ($u['created'] ?? '')) ?> · Última visita: <?= $ago((string) ($stats[$id]['_seen'] ?? '')) ?></p>
    <form method="post" class="ad-form">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= cms_e($id) ?>">
      <div class="ad-two">
        <div class="ad-field"><label>Nombre</label><input type="text" name="name" required value="<?= cms_e($u['name']) ?>"></div>
        <div class="ad-field"><label>Correo (para entrar)</label><input type="email" name="email" required value="<?= cms_e($u['email']) ?>"></div>
      </div>
      <div class="ad-field"><label>Notas internas</label><textarea name="notes" rows="2" placeholder="Empresa, grupo, factura…"><?= cms_e((string) ($u['notes'] ?? '')) ?></textarea></div>
      <label class="ad-check"><input type="checkbox" name="active" value="1"<?= !empty($u['active']) ? ' checked' : '' ?>> Activo (si lo desmarcas no puede entrar; su avance se conserva)</label>
      <p><button class="ad-btn" type="submit">Guardar</button></p>
    </form>
  </section>
  <section class="ad-box">
    <h2>Contraseña</h2>
    <form method="post" class="ad-form" autocomplete="off">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= cms_e($id) ?>">
      <div class="ad-field"><label>Nueva contraseña</label><input type="text" name="pass" minlength="8" placeholder="vacío = generar una" autocomplete="off"></div>
      <label class="ad-check"><input type="checkbox" name="send" value="1"> Enviársela por correo</label>
      <p><button class="ad-btn" type="submit">Cambiar contraseña</button></p>
    </form>
    <h2 style="margin-top:22px">Inscribir en un curso</h2>
    <form method="post" class="ad-inline-form">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="enroll"><input type="hidden" name="id" value="<?= cms_e($id) ?>">
      <select name="course"><?php foreach ($courses as $cs => $c): if (!empty($mine[$cs]['enrolled'])) continue; ?><option value="<?= cms_e($cs) ?>"><?= cms_e($courseTitle($cs)) ?></option><?php endforeach; ?></select>
      <button class="ad-btn ad-btn-sm" type="submit">Inscribir</button>
    </form>
  </section>
</div>
<section class="ad-box">
  <h2>Avance</h2>
<?php if (!$mine): ?>
  <p class="ad-help">Todavía no está inscrito ni ha empezado ningún curso.</p>
<?php endif; ?>
<?php foreach ($mine as $cs => $s): $ls = lms_lessons($cs); ?>
  <h3 style="margin:18px 0 6px"><a href="<?= cms_e($url(['course' => $cs])) ?>"><?= cms_e($courseTitle($cs)) ?></a> <?= $pill($s) ?></h3>
  <p class="ad-help"><?= $bar($s['pct']) ?> · <?= $s['done'] ?> de <?= $s['total'] ?> lecciones · <?= $s['enrolled'] ? 'Inscrito ' . $ago($s['since']) . ($s['by'] === 'admin' ? ' (por el administrador)' : '') : 'No inscrito (conserva su avance)' ?><?= $s['completed'] !== '' ? ' · Terminó ' . $ago($s['completed']) : '' ?></p>
  <table class="ad-table">
    <thead><tr><th>#</th><th>Lección</th><th>Módulo</th><th>Terminada</th></tr></thead>
    <tbody>
<?php foreach ($ls as $i => $l): $d = (string) ($s['lessons'][$l['slug']] ?? ''); ?>
      <tr><td><?= $i + 1 ?></td><td><a href="<?= admin_url('edit', ['type' => lms_lesson_type(), 'slug' => $l['slug']]) ?>"><?= cms_e((string) cms_f($l, 'title', $lang)) ?></a></td><td><?= cms_e((string) cms_f($l, 'module', $lang)) ?></td><td><?= $d !== '' ? '✓ ' . $ago($d) : '—' ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
  <div class="ad-btnrow" style="margin-top:8px">
<?php if ($s['enrolled']): ?>
    <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="unenroll"><input type="hidden" name="id" value="<?= cms_e($id) ?>"><input type="hidden" name="course" value="<?= cms_e($cs) ?>"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Quitar inscripción</button></form>
<?php endif; ?>
    <form method="post" class="ad-inline" data-confirm="¿Borrar la inscripción y todo el avance de <?= cms_e($u['name']) ?> en este curso?"><?= admin_csrf_field() ?><input type="hidden" name="action" value="unenroll"><input type="hidden" name="wipe" value="1"><input type="hidden" name="id" value="<?= cms_e($id) ?>"><input type="hidden" name="course" value="<?= cms_e($cs) ?>"><button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Borrar avance</button></form>
  </div>
<?php endforeach; ?>
</section>
<section class="ad-box">
  <h2>Eliminar alumno</h2>
  <form method="post" class="ad-inline" data-confirm="¿Eliminar a <?= cms_e($u['name']) ?> y todo su avance? No se puede deshacer.">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= cms_e($id) ?>">
    <button class="ad-btn ad-btn-danger" type="submit">Eliminar alumno y su avance</button>
  </form>
  <p class="ad-help">Para que solo deje de entrar, desmárcalo como activo.</p>
</section>

<?php elseif ($tab === 'cursos' && $course !== '' && isset($courses[$course])): /* ============================== un curso */
    $c = $courses[$course]; $ls = lms_lessons($course, false);
    $rows = array_filter($users, fn($u) => isset($stats[$u['id']][$course])); ?>
<p><a href="<?= cms_e($url(['tab' => 'cursos'])) ?>">← Cursos</a></p>
<div class="ad-cards">
  <div class="ad-card"><strong><?= count($ls) ?></strong><span>Lecciones<?= count($ls) !== count(lms_lessons($course)) ? ' (' . count(lms_lessons($course)) . ' publicadas)' : '' ?></span></div>
  <div class="ad-card"><strong><?= count($rows) ?></strong><span>Alumnos con avance o inscritos</span></div>
  <div class="ad-card"><strong><?= count(array_filter($rows, fn($u) => $stats[$u['id']][$course]['completed'] !== '')) ?></strong><span>Lo terminaron</span></div>
  <div class="ad-card"><strong><?= $rows ? (int) round(array_sum(array_map(fn($u) => $stats[$u['id']][$course]['pct'], $rows)) / count($rows)) : 0 ?> %</strong><span>Avance promedio</span></div>
</div>
<section class="ad-box">
  <h2><?= cms_e($courseTitle($course)) ?> <span class="ad-pill"><?= cms_e($accessLabel[lms_course_access($c)]) ?></span><?= cms_item_is_live($c) ? '' : ' <span class="ad-pill warn">No publicado</span>' ?></h2>
  <p class="ad-actions"><a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('edit', ['type' => $ct, 'slug' => $course]) ?>">Editar curso</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('content', ['type' => lms_lesson_type()]) ?>">Lecciones</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e(cms_url('item:' . $ct, $lang, $course)) ?>" target="_blank" rel="noopener">Ver en el sitio</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e($url(['csv' => $course])) ?>">Exportar CSV</a></p>
<?php if (!$rows): ?>
  <p class="ad-help">Nadie ha empezado este curso todavía.</p>
<?php else: ?>
  <table class="ad-table">
    <thead><tr><th>Alumno</th><th>Avance</th><th>Lecciones</th><th>Inscrito</th><th>Terminó</th><th></th></tr></thead>
    <tbody>
<?php foreach ($rows as $u): $s = $stats[$u['id']][$course]; ?>
      <tr>
        <td><a href="<?= cms_e($url(['id' => $u['id']])) ?>"><strong><?= cms_e($u['name']) ?></strong></a><small class="ad-help"><?= cms_e($u['email']) ?></small></td>
        <td><?= $bar($s['pct']) ?></td>
        <td><?= $s['done'] ?> / <?= $s['total'] ?></td>
        <td><?= $s['enrolled'] ? $ago($s['since']) : '—' ?></td>
        <td><?= $ago($s['completed']) ?></td>
        <td class="ad-row-actions"><?php if ($s['enrolled']): ?><form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="unenroll"><input type="hidden" name="back" value="course"><input type="hidden" name="id" value="<?= cms_e($u['id']) ?>"><input type="hidden" name="course" value="<?= cms_e($course) ?>"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Quitar</button></form><?php endif; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</section>
<section class="ad-box">
  <h2>Inscribir alumnos</h2>
  <form method="post" class="ad-form">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="course" value="<?= cms_e($course) ?>">
    <div class="ad-field"><label>Correos de alumnos ya dados de alta (uno por línea o separados por coma)</label><textarea name="emails" rows="4" placeholder="ana@empresa.com&#10;luis@empresa.com"></textarea></div>
    <p><button class="ad-btn" type="submit">Inscribir</button></p>
  </form>
</section>

<?php elseif ($tab === 'importar'): /* ============================== importar de Archivos y carpetas */
    $cands = lms_import_candidates();
    $dir = trim((string) ($_GET['dir'] ?? ''), '/');
    $plan = $dir !== '' ? lms_import_plan($dir) : null; ?>
<section class="ad-box">
  <h2>Importar un curso hecho a mano</h2>
  <p class="ad-help">Para los cursos subidos como carpetas en <a href="<?= admin_url('archivos') ?>">Archivos y carpetas</a> (un <code>index.html</code> con la lista <code>MODULOS</code> de videos, como <code>/capacitacion/arbitraje/</code>). Se crea el curso con una lección por video; los videos se quedan donde están<?= lms_settings()['protect'] ? ' y su carpeta queda protegida: solo se ven desde el aula, a quien tenga acceso' : '' ?>. Los datos de la tarjeta (temas, para quién, color, duración) se toman del catálogo de la carpeta de arriba si lo hay.</p>
<?php if (!$cands): ?>
  <p class="ad-help">No encontré carpetas con cursos de ese formato.</p>
<?php else: ?>
  <table class="ad-table">
    <thead><tr><th>Carpeta</th><th>Título</th><th></th></tr></thead>
    <tbody>
<?php foreach ($cands as $rel => $h1): ?>
      <tr><td><code>/<?= cms_e($rel) ?>/</code></td><td><?= cms_e($h1) ?></td><td class="ad-row-actions"><a class="ad-btn ad-btn-sm<?= $rel === $dir ? '' : ' ad-btn-light' ?>" href="<?= cms_e($url(['tab' => 'importar', 'dir' => $rel])) ?>">Revisar</a></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</section>
<?php if ($plan && isset($plan['error'])): ?>
<p class="ad-flash err"><?= cms_e($plan['error']) ?></p>
<?php elseif ($plan): $pc = $plan['course']; $dl = cms_default_lang(); $exists = (bool) cms_item($ct, $pc['slug'], false); ?>
<section class="ad-box">
  <h2><?= cms_e($pc['title'][$dl]) ?> <span class="ad-pill"><?= count($plan['lessons']) ?> lecciones</span><?= $exists ? ' <span class="ad-pill warn">ya existe</span>' : '' ?></h2>
  <p class="ad-help">Quedará en <code><?= cms_e(cms_url('item:' . $ct, $dl, $pc['slug'])) ?></code> · <?= cms_e($pc['duration'][$dl] ?: 'sin duración') ?><?= $pc['audience'][$dl] !== '' ? ' · Para: ' . cms_e($pc['audience'][$dl]) : '' ?><?= $pc['topics'][$dl] ? ' · Temas: ' . cms_e(implode(', ', $pc['topics'][$dl])) : '' ?></p>
  <p><?= cms_e($pc['excerpt'][$dl]) ?></p>
  <table class="ad-table">
    <thead><tr><th>#</th><th>Lección</th><th>Duración</th><th>Para</th><th>Video</th></tr></thead>
    <tbody>
<?php foreach ($plan['lessons'] as $l): ?>
      <tr><td><?= (int) $l['order'] ?></td><td><strong><?= cms_e($l['title'][$dl]) ?></strong><small class="ad-help"><?= cms_e($l['summary'][$dl]) ?></small></td><td><?= cms_e($l['duration']) ?></td><td><?= cms_e($l['audience'][$dl]) ?></td><td><code><?= cms_e($l['video']) ?></code><?= $l['poster'] !== '' ? ' + portada' : '' ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php foreach ($plan['warn'] as $w): ?>  <p class="ad-flash err"><?= cms_e($w) ?></p>
<?php endforeach; ?>
<?php if (!$exists): ?>
  <form method="post" class="ad-form" style="margin-top:14px">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="dir" value="<?= cms_e($plan['rel']) ?>">
    <label class="ad-check"><input type="checkbox" name="publish" value="1"> Publicar el curso ya (si no, queda en borrador para revisarlo; en borrador solo lo ves tú desde el panel)</label>
<?php if ($plan['soon']): ?>
    <label class="ad-check"><input type="checkbox" name="soon" value="1" checked> Crear también como «Próximamente» los cursos en preparación del catálogo: <?= cms_e(implode(', ', array_map(fn($x) => (string) ($x['titulo'] ?? $x['clave']), $plan['soon']))) ?></label>
<?php endif; ?>
    <label class="ad-check"><input type="checkbox" name="redirect" value="1"> Cambiar las páginas viejas (<code>/<?= cms_e($plan['rel']) ?>/</code><?= $plan['catalog'] !== '' ? ' y <code>/' . cms_e($plan['catalog']) . '/</code>' : '' ?>) por una redirección al aula. Se guarda una copia en data/backups/lms-import/.</label>
    <p><button class="ad-btn" type="submit">Importar curso</button></p>
  </form>
<?php endif; ?>
</section>
<?php endif; ?>

<?php elseif ($tab === 'cursos'): /* ============================== cursos */ ?>
<section class="ad-box">
  <h2>Cursos</h2>
<?php if (!$courses): ?>
  <p class="ad-help">Aún no hay cursos. <a href="<?= admin_url('edit', ['type' => $ct]) ?>">Crea el primero</a> y después sus lecciones (Aula → Lecciones, campo «Curso»).</p>
<?php else: ?>
  <table class="ad-table">
    <thead><tr><th>Curso</th><th>Acceso</th><th>Lecciones</th><th>Alumnos</th><th>Terminaron</th><th>Avance promedio</th></tr></thead>
    <tbody>
<?php foreach ($courses as $cs => $c):
    $rows = array_filter($users, fn($u) => isset($stats[$u['id']][$cs]));
    $avg = $rows ? (int) round(array_sum(array_map(fn($u) => $stats[$u['id']][$cs]['pct'], $rows)) / count($rows)) : 0; ?>
      <tr>
        <td><a href="<?= cms_e($url(['course' => $cs])) ?>"><strong><?= cms_e($courseTitle($cs)) ?></strong></a><?= cms_item_is_live($c) ? '' : ' <span class="ad-pill warn">No publicado</span>' ?></td>
        <td><?= cms_e($accessLabel[lms_course_access($c)]) ?></td>
        <td><?= count(lms_lessons($cs)) ?></td>
        <td><?= count($rows) ?></td>
        <td><?= count(array_filter($rows, fn($u) => $stats[$u['id']][$cs]['completed'] !== '')) ?></td>
        <td><?= $bar($avg) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</section>

<?php else: /* ============================== alumnos */
    $q = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
    $list = $q === '' ? $users : array_filter($users, fn($u) => strpos(mb_strtolower($u['name'] . ' ' . $u['email'] . ' ' . ($u['notes'] ?? '')), $q) !== false); ?>
<div class="ad-grid2">
  <section class="ad-box">
    <h2>Alumnos</h2>
    <form method="get" class="ad-filter" role="search" style="margin-bottom:12px">
      <input type="hidden" name="p" value="<?= cms_e($self) ?>">
      <input type="search" name="q" value="<?= cms_e($q) ?>" placeholder="Buscar por nombre, correo o notas">
      <button class="ad-btn ad-btn-sm" type="submit">Buscar</button>
      <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e($url(['csv' => 'alumnos'])) ?>">Exportar CSV</a>
    </form>
<?php if (!$list): ?>
    <p class="ad-help"><?= $users ? 'Nadie coincide con la búsqueda.' : 'Todavía no hay alumnos. Da de alta el primero con el formulario de al lado' . (lms_settings()['signup'] ? ' o deja que se registren en ' . cms_e(lms_url('registro', $lang)) : '') . '.' ?></p>
<?php else: ?>
    <table class="ad-table">
      <thead><tr><th>Alumno</th><th>Cursos</th><th>Última visita</th></tr></thead>
      <tbody>
<?php foreach ($list as $id2 => $u): $mine = $stats[$id2] ?? []; unset($mine['_seen']); ?>
        <tr>
          <td><a href="<?= cms_e($url(['id' => $id2])) ?>"><strong><?= cms_e($u['name']) ?></strong></a><?= empty($u['active']) ? ' <span class="ad-pill warn">Inactivo</span>' : '' ?><small class="ad-help"><?= cms_e($u['email']) ?></small></td>
          <td><?php foreach ($mine as $cs => $s): ?><div><?= cms_e($courseTitle($cs)) ?> <?= $pill($s) ?></div><?php endforeach; ?><?= $mine ? '' : '—' ?></td>
          <td><?= $ago((string) ($stats[$id2]['_seen'] ?? '')) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
<?php endif; ?>
  </section>
  <section class="ad-box">
    <h2>Dar de alta un alumno</h2>
    <form method="post" class="ad-form" autocomplete="off">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="ad-field"><label>Nombre completo</label><input type="text" name="name" required></div>
      <div class="ad-field"><label>Correo (con él entra)</label><input type="email" name="email" required></div>
      <div class="ad-field"><label>Contraseña</label><input type="text" name="pass" minlength="8" placeholder="vacío = generar una" autocomplete="off"></div>
<?php if ($courses): ?>
      <div class="ad-field"><label>Inscribir en</label>
<?php foreach ($courses as $cs => $c): ?>        <label class="ad-check"><input type="checkbox" name="courses[]" value="<?= cms_e($cs) ?>"> <?= cms_e($courseTitle($cs)) ?> <small class="ad-help">(<?= cms_e($accessLabel[lms_course_access($c)]) ?>)</small></label>
<?php endforeach; ?>
      </div>
<?php endif; ?>
      <div class="ad-field"><label>Notas internas</label><input type="text" name="notes" placeholder="Empresa, grupo…"></div>
      <label class="ad-check"><input type="checkbox" name="send" value="1"> Enviarle sus datos de acceso por correo</label>
      <p><button class="ad-btn" type="submit">Dar de alta</button></p>
    </form>
    <p class="ad-help">El alumno entra en <a href="<?= cms_e(lms_url('entrar', $lang)) ?>" target="_blank" rel="noopener"><?= cms_e(lms_url('entrar', $lang)) ?></a> y puede cambiar su contraseña en «Mi cuenta». Los cursos «abiertos» y «con cuenta» no necesitan inscripción: el alumno queda inscrito al terminar su primera lección.</p>
  </section>
</div>
<?php endif; ?>
<?php admin_footer();
