<?php
/**
 * Página "Alumnos y avance" del panel (?p=pack:lms). $pack trae el manifiesto.
 *   (sin parámetros)   alumnos: buscador, avance general, alta (con contraseña generada y aviso por correo opcional)
 *   &id=<alumno>        ficha: datos, contraseña, activo, inscripciones y avance lección por lección
 *   &tab=cursos         cursos: lecciones, inscritos, terminados y avance promedio
 *   &course=<curso>     un curso: cada alumno con su avance, inscribir por lista de correos
 *   &tab=evaluaciones   evaluaciones: por calificar, cada evaluación con intentos, aprobados y promedio
 *   &quiz=<evaluación>  resultados de una evaluación por alumno; &id=<alumno>[&n=<intento>] revisa y califica un intento
 *   &csv=alumnos|<curso>|quiz:<evaluación>  exportación CSV (UTF-8 con BOM, para Excel)
 *   &tab=importar[&dir=<carpeta>]  importar un curso hecho a mano en Archivos y carpetas (import.php)
 *   &tab=scorm          paquetes SCORM 1.2: subir, crear la lección que lo usa, borrar (scorm.php)
 *   &scorm_export=<curso>[&videos=1&files=1]  descargar el curso como paquete SCORM 1.2
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
$qt = lms_quiz_type();
$quizzes = cms_type($qt) ? cms_items($qt, false) : [];   // del índice: sin las preguntas
$quizFull = function (string $slug) use ($qt): ?array { return cms_type($qt) ? cms_item($qt, $slug, false) : null; };
$quizTitle = fn(string $s) => isset($quizzes[$s]) ? (string) (cms_f($quizzes[$s], 'title', $lang) ?: $s) : $s;

/** Avance de todos los alumnos: id => [curso => stats]. Lee un archivo por alumno. */
$allStats = function () use ($users, $courses): array {
    $out = [];
    foreach ($users as $id => $u) {
        $p = lms_progress((string) $id)['courses'];
        foreach ($p as $cs => $c) if (isset($courses[$cs])) $out[$id][$cs] = lms_stats_from((array) $c, lms_steps((string) $cs)) + ['since' => (string) ($c['enrolled'] ?? ''), 'by' => (string) ($c['by'] ?? '')];
        $out[$id]['_seen'] = (string) (lms_progress((string) $id)['seen'] ?? '');
    }
    return $out;
};

/** Intentos por calificar (preguntas abiertas): [['uid', 'course', 'quiz', 'n', 'end'], …], los más viejos primero. */
$pendingList = function () use ($users, $quizzes): array {
    $out = [];
    foreach ($users as $id => $u) foreach (lms_progress((string) $id)['courses'] as $cs => $c)
        foreach ((array) ($c['exams'] ?? []) as $qs => $e) if (isset($quizzes[$qs])) foreach ((array) ($e['attempts'] ?? []) as $a)
            if (($a['status'] ?? '') === 'pending') $out[] = ['uid' => (string) $id, 'course' => (string) $cs, 'quiz' => (string) $qs, 'n' => (int) $a['n'], 'end' => (string) $a['end']];
    usort($out, fn($a, $b) => strcmp($a['end'], $b['end']));
    return $out;
};

// ------------------------------------------------------------------ exportar un curso como SCORM 1.2
if (isset($_GET['scorm_export']) && isset($courses[cms_slugify((string) $_GET['scorm_export'])])) {
    $cs = cms_slugify((string) $_GET['scorm_export']);
    @set_time_limit(300);
    [$zip, $warn] = lms_scorm_export($cs, $lang, ['videos' => !empty($_GET['videos']), 'files' => !empty($_GET['files'])]);
    if (!$zip) { admin_flash(implode(' ', $warn), 'err'); admin_redirect($url(['course' => $cs])); }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $cs . '-scorm12-' . date('Ymd') . '.zip"');
    header('Content-Length: ' . filesize($zip));
    admin_flash('Paquete SCORM 1.2 de «' . $courseTitle($cs) . '» descargado.' . ($warn ? ' Avisos: ' . implode(' ', $warn) : ''), $warn ? 'err' : 'ok');   // se ve al volver al panel
    readfile($zip);
    @unlink($zip);
    exit;
}

// ------------------------------------------------------------------ preguntas en formatos de Moodle
if (isset($_GET['export'], $_GET['quiz']) && ($qz = $quizFull(cms_slugify((string) $_GET['quiz'])))) {
    $fmt = (string) $_GET['export'] === 'xml' ? 'xml' : 'gift';
    $qs = lms_quiz_questions($qz, $lang)['questions'];
    $tt = (string) cms_f($qz, 'title', $lang);
    header('Content-Type: ' . ($fmt === 'xml' ? 'application/xml' : 'text/plain') . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $qz['slug'] . ($fmt === 'xml' ? '-moodle.xml' : '.gift.txt') . '"');
    echo $fmt === 'xml' ? lms_quiz_to_moodle_xml($qs, $tt) : lms_quiz_to_gift($qs, $tt);
    exit;
}

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
        $ls = lms_steps($which);
        $rows[] = array_merge(['Nombre', 'Correo', 'Inscrito', 'Avance %', 'Terminado'], array_map(fn($l) => (lms_is_quiz($l) ? 'Evaluación: ' : '') . cms_f($l, 'title', $lang), $ls));
        foreach ($users as $id => $u) {
            if (!isset($stats[$id][$which])) continue;
            $s = $stats[$id][$which];
            $rows[] = array_merge([$u['name'], $u['email'], $s['since'], $s['pct'], $s['completed']], array_map(function ($l) use ($s) {
                if (!lms_is_quiz($l)) return (string) ($s['lessons'][$l['slug']] ?? '');
                $e = (array) ($s['exams'][$l['slug']] ?? []);
                return isset($e['best']) ? $e['best'] . ' %' . (!empty($e['passed']) ? ' (aprobada)' : '') : (!empty($e['attempts']) ? 'por calificar' : '');
            }, $ls));
        }
        $name = 'curso-' . $which;
    } elseif (strpos($which, 'quiz:') === 0 && isset($quizzes[substr($which, 5)])) {
        $qs = substr($which, 5);
        $qz = $quizFull($qs);
        $cs = lms_quiz_course($qz);
        $rows[] = ['Nombre', 'Correo', 'Intento', 'Empezó', 'Terminó', 'Puntos', 'Máximo', 'Calificación %', 'Estado', 'Fuera de tiempo', 'Calificó'];
        foreach ($users as $id => $u) foreach ((array) (lms_quiz_record((string) $id, $cs, $qs)['attempts'] ?? []) as $a)
            $rows[] = [$u['name'], $u['email'], $a['n'], $a['start'], $a['end'], $a['points'], $a['max'], $a['status'] === 'pending' ? '' : $a['pct'],
                       ['passed' => 'aprobada', 'failed' => 'no aprobada', 'pending' => 'por calificar'][$a['status']] ?? '', !empty($a['late']) ? 'sí' : '', (string) ($a['by'] ?? '')];
        $name = 'evaluacion-' . $qs;
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
    $sendAccess = fn(array $u, string $pass, array $cs): bool => lms_welcome_mail($u, $pass, $cs);
    $mailFail = ' — el correo NO salió (el servidor no pudo enviarlo): compártela tú y revisa «Correos enviados» al pie de la lista de alumnos.';

    if ($action === 'import' || $action === 'import_complete') {
        $io = ['publish' => !empty($_POST['publish']), 'soon' => !empty($_POST['soon']), 'redirect' => !empty($_POST['redirect']),
               'module' => admin_post('module'), 'pass' => max(0, min(100, (int) admin_post('pass'))), 'attempts' => max(0, (int) admin_post('attempts')),
               'final_attempts' => max(0, (int) admin_post('final_attempts')), 'shuffle' => !empty($_POST['shuffle']), 'gate_final' => !empty($_POST['gate_final']),
               'reveal' => in_array(admin_post('reveal'), ['', 'siempre', 'aciertos', 'nada'], true) ? admin_post('reveal') : '',
               'replace_body' => !empty($_POST['replace_body']), 'replace_quiz' => !empty($_POST['replace_quiz']), 'publish_quiz' => !empty($_POST['publish_quiz'])];
        [$ok, $msgs] = $action === 'import' ? lms_import_run(admin_post('dir'), $io) : lms_import_complete(admin_post('dir'), $io);
        admin_flash(implode(' ', $msgs), $ok ? 'ok' : 'err');
        admin_redirect($ok ? $url(['tab' => 'cursos']) : $url(['tab' => 'importar', 'dir' => admin_post('dir')]));
    }
    if ($action === 'scorm_upload') {   // subir un paquete SCORM (.zip)
        $f = $_FILES['file'] ?? null;
        if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) admin_flash('No llegó el archivo' . (is_array($f) && in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ': pasa del tamaño máximo de subida del servidor (' . ini_get('upload_max_filesize') . '). Súbelo por FTP o en Archivos y carpetas y escribe su ruta abajo.' : '.'), 'err');
        else {
            $name = admin_post('name') !== '' ? admin_post('name') : pathinfo((string) $f['name'], PATHINFO_FILENAME);
            [$ok, $r, $w] = lms_scorm_install((string) $f['tmp_name'], $name, !empty($_POST['replace']));
            admin_flash($ok ? 'Paquete instalado en /' . $r . '/.' . ($w ? ' ' . implode(' ', $w) : '') : $r, $ok && !$w ? 'ok' : 'err');
        }
        admin_redirect($url(['tab' => 'scorm']));
    }
    if ($action === 'scorm_from_path') {   // un .zip que ya está en el sitio (subido por FTP o en Archivos y carpetas)
        $src = lms_local_file(admin_post('path'));
        if (!$src || strtolower(pathinfo($src, PATHINFO_EXTENSION)) !== 'zip') admin_flash('No encontré ese .zip en el sitio.', 'err');
        else {
            [$ok, $r, $w] = lms_scorm_install($src, admin_post('name') !== '' ? admin_post('name') : pathinfo($src, PATHINFO_FILENAME), !empty($_POST['replace']));
            admin_flash($ok ? 'Paquete instalado en /' . $r . '/.' . ($w ? ' ' . implode(' ', $w) : '') : $r, $ok && !$w ? 'ok' : 'err');
        }
        admin_redirect($url(['tab' => 'scorm']));
    }
    if ($action === 'scorm_lesson') {   // crear la lección que usa un paquete
        $pkg = lms_scorm_rel(admin_post('pkg'));
        $man = $pkg !== '' ? lms_scorm_manifest($pkg) : ['error' => 'Paquete no válido.'];
        $cs = cms_slugify(admin_post('course'));
        if (isset($man['error']) || !isset($courses[$cs])) { admin_flash($man['error'] ?? 'Elige el curso.', 'err'); admin_redirect($url(['tab' => 'scorm'])); }
        $title = admin_post('title') !== '' ? admin_post('title') : (string) $man['title'];
        $slug = cms_slugify($cs . '-' . $title) ?: $cs . '-scorm';
        $base = $slug; $k = 2;
        while (cms_item(lms_lesson_type(), $slug, false)) $slug = $base . '-' . $k++;
        $es = cms_default_lang(); $now = date('Y-m-d');
        $order = admin_post('order');
        if (!is_numeric($order)) { $order = 0; foreach (lms_lessons($cs, false) as $l) $order = max($order, (float) ($l['order'] ?? 0)); $order++; }
        $ok = cms_item_save(lms_lesson_type(), ['slug' => $slug, 'status' => 'published', 'title' => [$es => $title], 'summary' => [$es => ''], 'course' => $cs, 'order' => $order + 0,
            'module' => [$es => admin_post('module')], 'scorm' => $pkg, 'video' => '', 'body' => [$es => ''], 'files' => [], 'preview' => false, 'created' => $now, 'updated' => $now]);
        admin_flash($ok ? 'Lección «' . $title . '» creada en «' . $courseTitle($cs) . '» con el paquete.' : 'No se pudo guardar la lección.', $ok ? 'ok' : 'err');
        admin_redirect($ok ? admin_url('edit', ['type' => lms_lesson_type(), 'slug' => $slug]) : $url(['tab' => 'scorm']));
    }
    if ($action === 'scorm_delete') {
        $pkg = lms_scorm_rel(admin_post('pkg'));
        $used = array_filter(cms_items(lms_lesson_type(), false), fn($l) => ($l['scorm'] ?? '') === $pkg);
        if ($pkg === '' || $used) admin_flash($used ? 'Lo usan ' . count($used) . ' lección(es); quítalo de ellas primero.' : 'Paquete no válido.', 'err');
        else { lms_scorm_rmdir(CMS_ROOT . '/' . $pkg); admin_flash('Paquete /' . $pkg . '/ borrado.'); }
        admin_redirect($url(['tab' => 'scorm']));
    }
    if ($action === 'quiz_import') {   // preguntas desde un archivo Moodle XML, GIFT o de texto
        $qs = cms_slugify(admin_post('quiz'));
        $qz = $qs !== '' ? $quizFull($qs) : null;
        $f = $_FILES['file'] ?? null;
        if (!$qz || !is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) > 5 * 1024 * 1024) { admin_flash('Elige un archivo de preguntas (máximo 5 MB).', 'err'); admin_redirect($url(['quiz' => $qs])); }
        [$text, $warn, $fmt] = lms_quiz_import_text((string) file_get_contents((string) $f['tmp_name']), (string) $f['name']);
        $n = count(lms_quiz_parse($text)['questions']);
        if (!$n) { admin_flash('No se encontró ninguna pregunta que el aula pueda usar en el archivo (' . $fmt . '). ' . implode(' ', array_slice($warn, 0, 5)), 'err'); admin_redirect($url(['quiz' => $qs])); }
        $cur = trim((string) cms_f($qz, 'questions', $lang));
        $new = admin_post('mode') === 'append' && $cur !== '' ? $cur . "\n\n" . $text : $text;
        $qz['questions'] = array_replace((array) ($qz['questions'] ?? []), [$lang => $new]);
        $qz['updated'] = date('Y-m-d');
        $ok = cms_item_save($qt, $qz);
        admin_flash($ok ? $n . ' pregunta(s) importadas de ' . $fmt . (admin_post('mode') === 'append' ? ' (agregadas a las que había)' : ' (reemplazan a las que había)') . '.' . ($warn ? ' Avisos: ' . implode(' ', array_slice($warn, 0, 8)) . (count($warn) > 8 ? ' …' : '') : '') : 'No se pudo guardar la evaluación.', $ok ? ($warn ? 'err' : 'ok') : 'err');
        admin_redirect($url(['quiz' => $qs]));
    }
    if ($action === 'unblock') {   // quitar bloqueos por intentos fallidos: de un correo o todos
        $em = admin_post('email');
        admin_flash(lms_unblock($em) ? ($em !== '' ? 'Desbloqueado: ' . $em . '. Ya puede volver a intentar.' : 'Bloqueos quitados: todos pueden volver a intentar.') : 'No se pudo escribir data/lms/attempts.json.', 'ok');
        admin_redirect(admin_post('back') === 'id' && ($x = lms_user_by_email($em)) ? $url(['id' => $x['id']]) : $url());
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
        admin_flash('Alumno dado de alta. Contraseña: ' . $pass . ($mailed ? ' — sus datos de acceso se enviaron a ' . $u['email'] . '.' : (!empty($_POST['send']) ? $mailFail : ' — cópiala ahora: no se vuelve a mostrar.')), !empty($_POST['send']) && !$mailed ? 'err' : 'ok');
        admin_redirect($url(['id' => $r]));
    }
    $u = $id !== '' ? lms_user_get($id) : null;
    if (in_array($action, ['grade', 'quiz_extra', 'quiz_reset', 'quiz_close'], true)) {   // evaluaciones de un alumno
        $qs = cms_slugify(admin_post('quiz'));
        $qz = $qs !== '' ? $quizFull($qs) : null;
        if (!$u || !$qz) { admin_flash('No se encontró al alumno o la evaluación.', 'err'); admin_redirect($url(['tab' => 'evaluaciones'])); }
        $cs = lms_quiz_course($qz);
        $n = (int) admin_post('n');
        if ($action === 'grade') {
            $a = lms_quiz_grade($u['id'], $cs, $qz, $n, (array) ($_POST['mark'] ?? []), (array) ($_POST['note'] ?? []), (string) (admin_user()['user'] ?? 'admin'));
            if (!$a) admin_flash('No se pudo guardar la calificación.', 'err');
            else {
                $mailed = false;
                if (!empty($_POST['send'])) {
                    $site = (string) (cms_settings()['site_name'] ?? cms_config('name'));
                    $qtl = (string) cms_f($qz, 'title', $lang);
                    $mailed = lms_mail((string) $u['email'], "Calificación: $qtl", "Hola, " . $u['name'] . ".\n\nYa está calificado tu intento " . $a['n'] . " de «" . $qtl . "» en el aula de $site: "
                        . ($a['status'] === 'pending' ? 'aún quedan preguntas por calificar' : $a['pct'] . ' % (' . ($a['status'] === 'passed' ? 'aprobada' : 'no aprobada') . ')') . ".\n\nRevísalo en: "
                        . cms_origin() . cms_url('item:' . $qt, $lang, $qs) . '?intento=' . $a['n'] . "\n");
                }
                admin_flash('Calificación guardada: ' . ($a['status'] === 'pending' ? 'aún hay preguntas sin calificar' : $a['pct'] . ' % · ' . ($a['status'] === 'passed' ? 'aprobada' : 'no aprobada')) . ($mailed ? ' (aviso enviado al alumno).' : '.'));
            }
            admin_redirect($url(['tab' => 'evaluaciones', 'quiz' => $qs, 'id' => $u['id'], 'n' => $n]));
        }
        lms_quiz_reset($u['id'], $cs, $qs, ['quiz_extra' => 'extra', 'quiz_close' => 'close', 'quiz_reset' => 'all'][$action]);
        admin_flash(['quiz_extra' => 'Le diste un intento más a ' . $u['name'] . '.', 'quiz_close' => 'Intento con tiempo cerrado sin calificar: puede volver a empezar.', 'quiz_reset' => 'Intentos de ' . $u['name'] . ' borrados: empieza de cero.'][$action]);
        admin_redirect($url(admin_post('back') === 'id' ? ['id' => $u['id']] : ['tab' => 'evaluaciones', 'quiz' => $qs]));
    }
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
            lms_unblock((string) $u['email']);   // con contraseña nueva, que pueda entrar ya
            $mailed = !empty($_POST['send']) && $sendAccess(lms_user_get($u['id']), $pass, []);
            admin_flash('Contraseña nueva: ' . $pass . ($mailed ? ' — enviada a ' . $u['email'] . '; sus sesiones abiertas se cerraron.' : (!empty($_POST['send']) ? $mailFail : ' — compártela con el alumno; sus sesiones abiertas se cerraron.')), !empty($_POST['send']) && !$mailed ? 'err' : 'ok');
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
$quiz = cms_slugify((string) ($_GET['quiz'] ?? ''));
$tab = $course !== '' ? 'cursos' : ($quiz !== '' ? 'evaluaciones' : (in_array((string) ($_GET['tab'] ?? ''), ['cursos', 'importar', 'evaluaciones', 'scorm'], true) ? (string) $_GET['tab'] : 'alumnos'));
$pending = $quizzes ? $pendingList() : [];
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
    <a href="<?= cms_e($url(['tab' => 'evaluaciones'])) ?>"<?= $tab === 'evaluaciones' ? ' style="background:#000;color:#fff"' : '' ?>>Evaluaciones (<?= count($quizzes) ?>)<?= $pending ? ' <span class="ad-pill warn">' . count($pending) . ' por calificar</span>' : '' ?></a>
    <a href="<?= cms_e($url(['tab' => 'scorm'])) ?>"<?= $tab === 'scorm' ? ' style="background:#000;color:#fff"' : '' ?>>SCORM</a>
    <a href="<?= cms_e($url(['tab' => 'importar'])) ?>"<?= $tab === 'importar' ? ' style="background:#000;color:#fff"' : '' ?>>Importar</a>
  </nav>
  <p class="ad-help" style="margin:0">Público: <a href="<?= cms_e(lms_url('', $lang)) ?>" target="_blank" rel="noopener"><?= cms_e(lms_url('', $lang)) ?></a> · <a href="<?= cms_e(cms_url('list:' . $ct, $lang)) ?>" target="_blank" rel="noopener"><?= cms_e(cms_url('list:' . $ct, $lang)) ?></a> · <a href="<?= admin_url('settings') ?>">Ajustes del aula</a></p>
</div>
<?php if (!cms_type($ct) || !cms_type(lms_lesson_type())): ?>
<p class="ad-flash err">No existe la colección «<?= cms_e(!cms_type($ct) ? $ct : lms_lesson_type()) ?>». Revisa Ajustes → Aula (colección de cursos y de lecciones).</p>
<?php endif; ?>

<?php if ($tab === 'evaluaciones' && $quiz !== '' && $id !== '' && ($u = lms_user_get($id)) && ($qz = $quizFull($quiz))): /* ============================== un intento: revisar y calificar */
    $cs = lms_quiz_course($qz);
    $rec = lms_quiz_record($id, $cs, $quiz);
    $atts = (array) ($rec['attempts'] ?? []);
    $n = (int) ($_GET['n'] ?? 0);
    $a = null;
    foreach ($atts as $x) if ((int) $x['n'] === $n) $a = $x;
    if (!$a && $atts) $a = end($atts);
    $cfg = lms_quiz_cfg($qz);
    $allowed = $cfg['attempts'] ? $cfg['attempts'] + (int) ($rec['extra'] ?? 0) : 0;
    $statusPill = fn(string $st) => ['passed' => '<span class="ad-pill on">Aprobada</span>', 'failed' => '<span class="ad-pill">No aprobada</span>', 'pending' => '<span class="ad-pill warn">Por calificar</span>'][$st] ?? ''; ?>
<p><a href="<?= cms_e($url(['quiz' => $quiz])) ?>">← <?= cms_e($quizTitle($quiz)) ?></a> · <a href="<?= cms_e($url(['id' => $id])) ?>">Ficha de <?= cms_e($u['name']) ?></a></p>
<section class="ad-box">
  <h2><?= cms_e($u['name']) ?> · <?= cms_e($quizTitle($quiz)) ?></h2>
  <p class="ad-help"><?= cms_e($u['email']) ?> · Curso: <?= cms_e($courseTitle($cs)) ?> · Intentos: <?= count($atts) ?><?= $allowed ? ' de ' . $allowed . (!empty($rec['extra']) ? ' (' . (int) $rec['extra'] . ' dado' . ((int) $rec['extra'] === 1 ? '' : 's') . ' desde el panel)' : '') : ' (sin límite)' ?><?= isset($rec['best']) ? ' · Mejor: ' . (int) $rec['best'] . ' %' : '' ?><?= !empty($rec['passed']) ? ' · Aprobó ' . $ago((string) $rec['passed']) : '' ?></p>
<?php if (!empty($rec['open'])): ?>
  <div class="ad-flash err" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">Tiene un intento con tiempo abierto desde <?= $ago((string) $rec['open']['start']) ?> (vence <?= cms_e(date('Y-m-d H:i', (int) $rec['open']['deadline'])) ?>).
    <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="quiz_close"><input type="hidden" name="id" value="<?= cms_e($id) ?>"><input type="hidden" name="quiz" value="<?= cms_e($quiz) ?>"><button class="ad-btn ad-btn-sm" type="submit">Cerrarlo sin contar</button></form></div>
<?php endif; ?>
<?php if ($atts): ?>
  <nav class="ad-tabs" style="border:0;margin:10px 0">
<?php foreach ($atts as $x): ?>    <a href="<?= cms_e($url(['quiz' => $quiz, 'id' => $id, 'n' => $x['n']])) ?>"<?= (int) $x['n'] === (int) $a['n'] ? ' style="background:#000;color:#fff"' : '' ?>>Intento <?= (int) $x['n'] ?> · <?= cms_e(lms_quiz_pct_text($x)) ?></a>
<?php endforeach; ?>
  </nav>
<?php endif; ?>
  <div class="ad-btnrow">
    <?php if ($allowed): ?><form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="quiz_extra"><input type="hidden" name="id" value="<?= cms_e($id) ?>"><input type="hidden" name="quiz" value="<?= cms_e($quiz) ?>"><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Darle un intento más</button></form><?php endif; ?>
    <?php if ($atts): ?><form method="post" class="ad-inline" data-confirm="¿Borrar todos los intentos de <?= cms_e($u['name']) ?> en esta evaluación? Vuelve a empezar de cero."><?= admin_csrf_field() ?><input type="hidden" name="action" value="quiz_reset"><input type="hidden" name="id" value="<?= cms_e($id) ?>"><input type="hidden" name="quiz" value="<?= cms_e($quiz) ?>"><button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Borrar sus intentos</button></form><?php endif; ?>
  </div>
</section>
<?php if (!$a): ?>
<p class="ad-help">Todavía no ha presentado esta evaluación.</p>
<?php else: $qs = lms_quiz_questions($qz, (string) ($a['lang'] ?? cms_default_lang())); ?>
<section class="ad-box">
  <h2>Intento <?= (int) $a['n'] ?> <?= $statusPill((string) $a['status']) ?><?= !empty($a['late']) ? ' <span class="ad-pill warn">Fuera de tiempo</span>' : '' ?></h2>
  <p class="ad-help">Empezó <?= $ago((string) $a['start']) ?> · terminó <?= $ago((string) $a['end']) ?> · <?= lms_quiz_pts((float) $a['points']) ?> de <?= lms_quiz_pts((float) $a['max']) ?> puntos<?= $a['status'] !== 'pending' ? ' · ' . (int) $a['pct'] . ' % (aprueba con ' . $cfg['pass'] . ' %)' : '' ?><?= !empty($a['graded']) ? ' · Calificado a mano ' . $ago((string) $a['graded']) . ' por ' . cms_e((string) $a['by']) : '' ?> · Idioma: <?= cms_e(strtoupper((string) $a['lang'])) ?></p>
<?php if (($a['hash'] ?? '') !== '' && $a['hash'] !== $qs['hash']): ?>
  <p class="ad-flash err">Las preguntas cambiaron después de este intento: lo que se ve abajo es la versión actual y puede no coincidir con lo que contestó.</p>
<?php endif; ?>
  <form method="post" class="ad-form">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="grade"><input type="hidden" name="id" value="<?= cms_e($id) ?>"><input type="hidden" name="quiz" value="<?= cms_e($quiz) ?>"><input type="hidden" name="n" value="<?= (int) $a['n'] ?>">
    <table class="ad-table">
      <thead><tr><th>#</th><th>Pregunta y respuesta</th><th style="width:150px">Puntos</th></tr></thead>
      <tbody>
<?php foreach ((array) $a['set'] as $pos => $i): $q = $qs['questions'][$i] ?? null; if (!$q) continue; $m = $a['marks'][$i] ?? null; $ans = lms_quiz_answer_text($q, $a['answers'][$i] ?? ''); ?>
        <tr<?= $m === null ? ' style="background:#fff8e6"' : '' ?>>
          <td><?= $pos + 1 ?></td>
          <td><strong><?= lms_quiz_html($q['text']) ?></strong>
            <div style="margin-top:6px"><small class="ad-help" style="display:inline">Contestó:</small> <?= $ans !== '' ? nl2br(cms_e($ans), false) : '<em>(sin contestar)</em>' ?></div>
            <?php if ($q['kind'] !== 'open'): ?><div><small class="ad-help" style="display:inline">Correcta:</small> <?= cms_e(lms_quiz_correct_text($q)) ?></div><?php endif; ?>
            <div class="ad-field" style="margin:8px 0 0"><textarea name="note[<?= (int) $i ?>]" rows="1" placeholder="Comentario para el alumno (opcional)"><?= cms_e((string) ($a['notes'][$i] ?? '')) ?></textarea></div></td>
          <td><input type="number" name="mark[<?= (int) $i ?>]" min="0" max="<?= cms_e(lms_quiz_pts((float) $q['points'])) ?>" step="0.25" value="<?= $m === null ? '' : cms_e(lms_quiz_pts((float) $m)) ?>" style="width:80px"<?= $m === null ? ' placeholder="?"' : '' ?>> / <?= lms_quiz_pts((float) $q['points']) ?><?= $q['kind'] === 'open' ? '<br><small class="ad-help">abierta</small>' : '' ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
    <label class="ad-check"><input type="checkbox" name="send" value="1"<?= $a['status'] === 'pending' ? ' checked' : '' ?>> Avisar al alumno por correo</label>
    <p><button class="ad-btn" type="submit">Guardar calificación</button> <span class="ad-help">Puedes corregir los puntos de cualquier pregunta, no solo las abiertas.</span></p>
  </form>
</section>
<?php endif; ?>

<?php elseif ($tab === 'evaluaciones' && $quiz !== '' && isset($quizzes[$quiz]) && ($qz = $quizFull($quiz))): /* ============================== una evaluación */
    $cs = lms_quiz_course($qz);
    $cfg = lms_quiz_cfg($qz);
    $parsed = lms_quiz_questions($qz, $lang);
    $rows = [];
    foreach ($users as $uid => $u) { $r = lms_quiz_record((string) $uid, $cs, $quiz); if (!empty($r['attempts']) || !empty($r['open'])) $rows[$uid] = $r; }
    $passedN = count(array_filter($rows, fn($r) => !empty($r['passed'])));
    $bests = array_filter(array_map(fn($r) => $r['best'] ?? null, $rows), fn($x) => $x !== null);
    $all = []; foreach ($rows as $r) foreach ((array) ($r['attempts'] ?? []) as $x) $all[] = $x;
    // preguntas más falladas (intentos calificados, en el idioma principal)
    $miss = [];
    foreach ($all as $x) if (($x['lang'] ?? '') === $lang && ($x['hash'] ?? '') === $parsed['hash']) foreach ((array) $x['marks'] as $i => $m) {
        if ($m === null || !isset($parsed['questions'][$i])) continue;
        $miss[$i] = ($miss[$i] ?? [0, 0]); $miss[$i][1]++; if ((float) $m < (float) $parsed['questions'][$i]['points']) $miss[$i][0]++;
    }
    $miss = array_filter($miss, fn($x) => $x[0] > 0);
    uasort($miss, fn($a, $b) => $b[0] / max(1, $b[1]) <=> $a[0] / max(1, $a[1])); ?>
<p><a href="<?= cms_e($url(['tab' => 'evaluaciones'])) ?>">← Evaluaciones</a></p>
<div class="ad-cards">
  <div class="ad-card"><strong><?= count($parsed['questions']) ?></strong><span>Preguntas<?= $cfg['pick'] && $cfg['pick'] < count($parsed['questions']) ? ' (' . $cfg['pick'] . ' por intento)' : '' ?></span></div>
  <div class="ad-card"><strong><?= count($rows) ?></strong><span>Alumnos que la presentaron</span></div>
  <div class="ad-card"><strong><?= $passedN ?></strong><span>Aprobaron</span></div>
  <div class="ad-card"><strong><?= $bests ? (int) round(array_sum($bests) / count($bests)) . ' %' : '—' ?></strong><span>Promedio de la mejor calificación</span></div>
</div>
<section class="ad-box">
  <h2><?= cms_e($quizTitle($quiz)) ?> <span class="ad-pill"><?= cms_e($courseTitle($cs)) ?></span><?= cms_item_is_live($qz) ? '' : ' <span class="ad-pill warn">No publicada</span>' ?></h2>
  <p class="ad-help"><?= $cfg['pass'] ? 'Aprueba con ' . $cfg['pass'] . ' %' : 'De práctica' ?> · <?= $cfg['attempts'] ? $cfg['attempts'] . ' intento' . ($cfg['attempts'] === 1 ? '' : 's') : 'intentos sin límite' ?><?= $cfg['time'] ? ' · ' . $cfg['time'] . ' min' : '' ?><?= $cfg['shuffle'] ? ' · mezcla preguntas y opciones' : '' ?><?= $cfg['gate'] ? ' · se abre al terminar lo anterior' : '' ?></p>
  <p class="ad-actions"><a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('edit', ['type' => $qt, 'slug' => $quiz]) ?>">Editar evaluación</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e(cms_url('item:' . $qt, $lang, $quiz)) ?>" target="_blank" rel="noopener">Ver con las respuestas</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e($url(['csv' => 'quiz:' . $quiz])) ?>">Resultados en CSV</a></p>
  <p class="ad-actions"><span class="ad-help" style="display:inline">Preguntas para Moodle:</span> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e($url(['export' => 'xml', 'quiz' => $quiz])) ?>">Moodle XML</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e($url(['export' => 'gift', 'quiz' => $quiz])) ?>">GIFT</a></p>
  <form method="post" enctype="multipart/form-data" class="ad-inline-form" style="margin:6px 0 12px">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="quiz_import"><input type="hidden" name="quiz" value="<?= cms_e($quiz) ?>">
    <span class="ad-help" style="display:inline">Importar preguntas (Moodle XML, GIFT o texto del aula):</span>
    <input type="file" name="file" accept=".xml,.gift,.txt" required>
    <select name="mode"><option value="replace">Reemplazar las actuales</option><option value="append">Agregarlas al final</option></select>
    <button class="ad-btn ad-btn-sm" type="submit">Importar</button>
  </form>
<?php foreach ($parsed['warnings'] as $w): ?>  <p class="ad-flash err"><?= cms_e($w) ?></p>
<?php endforeach; ?>
<?php if (!$rows): ?>
  <p class="ad-help">Nadie la ha presentado todavía.</p>
<?php else: ?>
  <table class="ad-table">
    <thead><tr><th>Alumno</th><th>Intentos</th><th>Mejor</th><th>Último</th><th>Estado</th><th></th></tr></thead>
    <tbody>
<?php foreach ($rows as $uid => $r): $u = $users[$uid]; $ea = (array) ($r['attempts'] ?? []); $last = $ea ? end($ea) : null; ?>
      <tr>
        <td><a href="<?= cms_e($url(['id' => $uid])) ?>"><strong><?= cms_e($u['name']) ?></strong></a><small class="ad-help"><?= cms_e($u['email']) ?></small></td>
        <td><?= count($ea) ?><?= $cfg['attempts'] ? ' / ' . ($cfg['attempts'] + (int) ($r['extra'] ?? 0)) : '' ?></td>
        <td><?= isset($r['best']) ? (int) $r['best'] . ' %' : '—' ?></td>
        <td><?= $last ? $ago((string) $last['end']) . ' · ' . cms_e(lms_quiz_pct_text($last)) : '—' ?></td>
        <td><?= !empty($r['passed']) ? '<span class="ad-pill on">Aprobada</span>' : '' ?><?= array_filter($ea, fn($x) => $x['status'] === 'pending') ? ' <span class="ad-pill warn">Por calificar</span>' : '' ?><?= !empty($r['open']) ? ' <span class="ad-pill warn">Presentándola</span>' : '' ?><?= empty($r['passed']) && $last && $last['status'] === 'failed' ? '<span class="ad-pill">No aprobada</span>' : '' ?></td>
        <td class="ad-row-actions"><a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e($url(['quiz' => $quiz, 'id' => $uid])) ?>">Revisar</a></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</section>
<?php if ($miss): ?>
<section class="ad-box">
  <h2>Preguntas más falladas</h2>
  <table class="ad-table">
    <thead><tr><th>Pregunta</th><th>Fallos</th></tr></thead>
    <tbody>
<?php foreach (array_slice($miss, 0, 10, true) as $i => [$bad, $tot]): ?>
      <tr><td><?= lms_quiz_html(mb_strimwidth($parsed['questions'][$i]['text'], 0, 160, '…')) ?></td><td><?= $bad ?> de <?= $tot ?> (<?= (int) round($bad * 100 / max(1, $tot)) ?> %)</td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
  <p class="ad-help">Cuenta los intentos con las preguntas actuales en <?= cms_e(strtoupper($lang)) ?>; incluye respuestas parciales.</p>
</section>
<?php endif; ?>

<?php elseif ($tab === 'evaluaciones'): /* ============================== evaluaciones */ ?>
<?php if ($pending): ?>
<section class="ad-box">
  <h2>Por calificar (<?= count($pending) ?>)</h2>
  <p class="ad-help">Intentos con preguntas abiertas. Hasta que los califiques, el alumno ve «Por calificar» y la evaluación no cuenta para su avance.<?= lms_settings()['notify_to'] === '' ? ' Para recibir un correo con cada uno, pon un correo en Ajustes → Aula → «Avisar de registros nuevos y evaluaciones por calificar a».' : '' ?></p>
  <table class="ad-table">
    <thead><tr><th>Alumno</th><th>Evaluación</th><th>Enviado</th><th></th></tr></thead>
    <tbody>
<?php foreach ($pending as $pd): $u = $users[$pd['uid']]; ?>
      <tr><td><strong><?= cms_e($u['name']) ?></strong><small class="ad-help"><?= cms_e($u['email']) ?></small></td><td><?= cms_e($quizTitle($pd['quiz'])) ?> · intento <?= $pd['n'] ?><small class="ad-help"><?= cms_e($courseTitle($pd['course'])) ?></small></td><td><?= $ago($pd['end']) ?></td>
        <td class="ad-row-actions"><a class="ad-btn ad-btn-sm" href="<?= cms_e($url(['quiz' => $pd['quiz'], 'id' => $pd['uid'], 'n' => $pd['n']])) ?>">Calificar</a></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
<section class="ad-box">
  <h2>Evaluaciones</h2>
<?php if (!cms_type($qt)): ?>
  <p class="ad-flash err">No existe la colección «<?= cms_e($qt) ?>». Revisa Ajustes → Aula → Colección de evaluaciones.</p>
<?php elseif (!$quizzes): ?>
  <p class="ad-help">Aún no hay evaluaciones. <a href="<?= admin_url('edit', ['type' => $qt]) ?>">Crea la primera</a>: elige su curso, dale un orden entre las lecciones y escribe las preguntas en texto (la ayuda del campo explica el formato).</p>
<?php else:
    $byQuiz = [];
    foreach ($users as $uid => $u) foreach (lms_progress((string) $uid)['courses'] as $cs => $c) foreach ((array) ($c['exams'] ?? []) as $qs => $e) {
        if (!isset($quizzes[$qs]) || empty($e['attempts'])) continue;
        $byQuiz[$qs][] = $e;
    } ?>
  <table class="ad-table">
    <thead><tr><th>Evaluación</th><th>Curso</th><th>Preguntas</th><th>Para aprobar</th><th>Alumnos</th><th>Aprobaron</th><th>Promedio</th></tr></thead>
    <tbody>
<?php foreach ($quizzes as $qs => $q): $full = $quizFull((string) $qs); $pq = $full ? lms_quiz_questions($full, $lang) : ['questions' => [], 'warnings' => []]; $cfg = lms_quiz_cfg($q); $es = $byQuiz[$qs] ?? [];
    $bests = array_filter(array_map(fn($e) => $e['best'] ?? null, $es), fn($x) => $x !== null); ?>
      <tr>
        <td><a href="<?= cms_e($url(['quiz' => $qs])) ?>"><strong><?= cms_e($quizTitle((string) $qs)) ?></strong></a><?= cms_item_is_live($q) ? '' : ' <span class="ad-pill warn">No publicada</span>' ?><?= $pq['warnings'] ? ' <span class="ad-pill warn" title="' . cms_e(implode("\n", $pq['warnings'])) . '">' . count($pq['warnings']) . ' aviso' . (count($pq['warnings']) === 1 ? '' : 's') . '</span>' : '' ?></td>
        <td><?= cms_e($courseTitle(lms_quiz_course($q))) ?><?= !isset($courses[lms_quiz_course($q)]) ? ' <span class="ad-pill warn">sin curso</span>' : '' ?></td>
        <td><?= count($pq['questions']) ?></td>
        <td><?= $cfg['pass'] ? $cfg['pass'] . ' %' : 'práctica' ?></td>
        <td><?= count($es) ?></td>
        <td><?= count(array_filter($es, fn($e) => !empty($e['passed']))) ?></td>
        <td><?= $bests ? (int) round(array_sum($bests) / count($bests)) . ' %' : '—' ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</section>

<?php elseif ($id !== '' && ($u = lms_user_get($id))): /* ============================== ficha del alumno */
    $mine = $stats[$id] ?? []; unset($mine['_seen']);
    $watch = array_map(fn($c) => (array) ($c['watch'] ?? []), lms_progress($id)['courses']); ?>
<p><a href="<?= cms_e($url()) ?>">← Alumnos</a></p>
<?php foreach (lms_blocks() as $b) if ($b['email'] === $u['email']): ?>
<div class="ad-flash err" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">Bloqueado por intentos fallidos: puede volver a intentar en <?= (int) $b['mins'] ?> min.
  <form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="unblock"><input type="hidden" name="back" value="id"><input type="hidden" name="email" value="<?= cms_e($u['email']) ?>"><button class="ad-btn ad-btn-sm" type="submit">Desbloquear</button></form></div>
<?php break; endif; ?>
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
    <h2>Contraseña y acceso</h2>
    <p class="ad-help">Para reenviarle su acceso: pon una contraseña nueva (o deja que se genere) con el correo marcado.</p>
    <form method="post" class="ad-form" autocomplete="off">
      <?= admin_csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= cms_e($id) ?>">
      <div class="ad-field"><label>Nueva contraseña</label><input type="text" name="pass" minlength="8" placeholder="vacío = generar una" autocomplete="off"></div>
      <label class="ad-check"><input type="checkbox" name="send" value="1" checked> Enviarle por correo sus datos de acceso con la contraseña nueva</label>
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
<?php foreach ($mine as $cs => $s): $ls = lms_steps($cs); ?>
  <h3 style="margin:18px 0 6px"><a href="<?= cms_e($url(['course' => $cs])) ?>"><?= cms_e($courseTitle($cs)) ?></a> <?= $pill($s) ?><?php if (($ce = lms_cert_get($id, $cs)) || ($s['completed'] !== '' && lms_cert_on($courses[$cs]))): ?> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e(lms_cert_url($cs, $lang, $id)) ?>" target="_blank" rel="noopener">Constancia<?= $ce ? ' ' . cms_e($ce['code']) : '' ?></a><?php endif; ?></h3>
  <p class="ad-help"><?= $bar($s['pct']) ?> · <?= $s['done'] ?> de <?= $s['total'] ?> <?= $s['quizzes'] ? 'lecciones y evaluaciones' : 'lecciones' ?> · <?= $s['enrolled'] ? 'Inscrito ' . $ago($s['since']) . ($s['by'] === 'admin' ? ' (por el administrador)' : '') : 'No inscrito (conserva su avance)' ?><?= $s['completed'] !== '' ? ' · Terminó ' . $ago($s['completed']) : '' ?></p>
  <table class="ad-table">
    <thead><tr><th>#</th><th>Lección o evaluación</th><th>Módulo</th><th>Terminada / calificación</th></tr></thead>
    <tbody>
<?php foreach ($ls as $i => $l): if (lms_is_quiz($l)): $e = (array) ($s['exams'][$l['slug']] ?? []); $ea = (array) ($e['attempts'] ?? []); $last = $ea ? end($ea) : null; ?>
      <tr><td><?= $i + 1 ?></td><td><span class="ad-pill">Evaluación</span> <a href="<?= cms_e($url(['quiz' => $l['slug']])) ?>"><?= cms_e((string) cms_f($l, 'title', $lang)) ?></a></td><td><?= cms_e((string) cms_f($l, 'module', $lang)) ?></td>
        <td><?php if (!$ea): ?>—<?php else: ?><a href="<?= cms_e($url(['quiz' => $l['slug'], 'id' => $id, 'n' => $last['n']])) ?>"><?= isset($e['best']) ? 'Mejor: ' . (int) $e['best'] . ' %' : 'Por calificar' ?></a> · <?= count($ea) ?> intento<?= count($ea) === 1 ? '' : 's' ?><?= !empty($e['passed']) ? ' · <span class="ad-pill on">Aprobada</span>' : '' ?><?= array_filter($ea, fn($a) => $a['status'] === 'pending') ? ' <span class="ad-pill warn">Por calificar</span>' : '' ?><?php endif; ?><?= !empty($e['open']) ? ' <span class="ad-pill warn">Presentándola ahora</span>' : '' ?></td></tr>
<?php else: $d = (string) ($s['lessons'][$l['slug']] ?? ''); $wv = (int) ($watch[$cs][$l['slug']] ?? 0); ?>
      <tr><td><?= $i + 1 ?></td><td><a href="<?= admin_url('edit', ['type' => lms_lesson_type(), 'slug' => $l['slug']]) ?>"><?= cms_e((string) cms_f($l, 'title', $lang)) ?></a></td><td><?= cms_e((string) cms_f($l, 'module', $lang)) ?></td><td><?= $d !== '' ? '✓ ' . $ago($d) : '—' ?><?= $wv > 0 ? ' <small class="ad-help" style="display:inline">· video visto ' . $wv . ' %</small>' : '' ?><?php if (($l['scorm'] ?? '') !== '' && ($sd = lms_scorm_data($id, $cs, (string) $l['slug']))): $sm = lms_scorm_summary($sd); ?> <small class="ad-help" style="display:inline">· SCORM: <?= cms_e(lms_scorm_status_text($sm['status'])) ?><?= $sm['score'] !== null ? ' · ' . $sm['score'] . ' %' : '' ?><?= $sm['time'] ? ' · ' . cms_e(lms_minutes_text($sm['time'] / 60)) : '' ?></small><?php endif; ?></td></tr>
<?php endif; endforeach; ?>
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
  <div class="ad-card"><strong><?= count(array_filter($rows, fn($u) => lms_cert_get($u['id'], $course) !== null)) ?></strong><span>Constancias emitidas<?= lms_cert_on($c) ? '' : ' (este curso no da)' ?></span></div>
</div>
<section class="ad-box">
  <h2><?= cms_e($courseTitle($course)) ?> <span class="ad-pill"><?= cms_e($accessLabel[lms_course_access($c)]) ?></span><?= cms_item_is_live($c) ? '' : ' <span class="ad-pill warn">No publicado</span>' ?></h2>
  <p class="ad-actions"><a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('edit', ['type' => $ct, 'slug' => $course]) ?>">Editar curso</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= admin_url('content', ['type' => lms_lesson_type()]) ?>">Lecciones</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e(cms_url('item:' . $ct, $lang, $course)) ?>" target="_blank" rel="noopener">Ver en el sitio</a> <a class="ad-btn ad-btn-sm ad-btn-light" href="<?= cms_e($url(['csv' => $course])) ?>">Exportar CSV</a></p>
  <form method="get" class="ad-inline-form" style="margin:4px 0 12px">
    <input type="hidden" name="p" value="<?= cms_e($self) ?>"><input type="hidden" name="scorm_export" value="<?= cms_e($course) ?>">
    <span class="ad-help" style="display:inline">Paquete SCORM 1.2 para el LMS de un cliente (Moodle, Canvas…):</span>
    <label class="ad-check" style="display:inline-flex"><input type="checkbox" name="videos" value="1" checked> incluir los videos</label>
    <label class="ad-check" style="display:inline-flex"><input type="checkbox" name="files" value="1" checked> y los materiales</label>
    <button class="ad-btn ad-btn-sm" type="submit">Exportar SCORM 1.2</button>
  </form>
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
        <td><?= $ago($s['completed']) ?><?php if ($s['completed'] !== '' && lms_cert_on($c)): ?> · <a href="<?= cms_e(lms_cert_url($course, $lang, $u['id'])) ?>" target="_blank" rel="noopener">constancia</a><?php endif; ?></td>
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

<?php elseif ($tab === 'scorm'): /* ============================== paquetes SCORM */
    $pkgs = lms_scorm_packages();
    $usedBy = [];
    foreach (cms_type(lms_lesson_type()) ? cms_items(lms_lesson_type(), false) : [] as $l) if (($l['scorm'] ?? '') !== '') $usedBy[(string) $l['scorm']][] = $l; ?>
<section class="ad-box">
  <h2>Paquetes SCORM 1.2</h2>
  <p class="ad-help">Sube un paquete SCORM (el .zip que exportan Articulate Storyline/Rise, iSpring, Adobe Captivate, H5P, Moodle…). Se descomprime en <code>/scorm/&lt;nombre&gt;/</code> sin archivos que el servidor pudiera ejecutar, y la carpeta queda sin acceso directo: el aula lo sirve solo a quien puede ver la lección. Después crea la lección que lo usa (o escribe <code>scorm/&lt;nombre&gt;</code> en el campo «Paquete SCORM» de una lección). El paquete guarda su avance, su calificación y dónde se quedó el alumno; la lección se marca terminada cuando lo completa.</p>
  <form method="post" enctype="multipart/form-data" class="ad-form">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="scorm_upload">
    <div class="ad-two">
      <div class="ad-field"><label>Archivo .zip (máximo del servidor: <?= cms_e((string) ini_get('upload_max_filesize')) ?>)</label><input type="file" name="file" accept=".zip" required></div>
      <div class="ad-field"><label>Nombre de la carpeta (opcional)</label><input type="text" name="name" placeholder="vacío = el nombre del archivo"></div>
    </div>
    <label class="ad-check"><input type="checkbox" name="replace" value="1"> Reemplazar si ya existe (el avance de los alumnos se conserva)</label>
    <p><button class="ad-btn" type="submit">Subir paquete</button></p>
  </form>
  <form method="post" class="ad-inline-form" style="margin-top:6px">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="scorm_from_path">
    <span class="ad-help" style="display:inline">¿Muy grande para subirlo aquí? Súbelo por FTP o en Archivos y carpetas y escribe su ruta:</span>
    <input type="text" name="path" placeholder="capacitacion/paquete.zip" required> <input type="text" name="name" placeholder="nombre (opcional)" style="width:150px">
    <label class="ad-check" style="display:inline-flex"><input type="checkbox" name="replace" value="1"> reemplazar</label>
    <button class="ad-btn ad-btn-sm" type="submit">Instalar</button>
  </form>
</section>
<?php if ($pkgs): ?>
<section class="ad-box">
  <h2>Instalados</h2>
  <table class="ad-table">
    <thead><tr><th>Paquete</th><th>Partes (SCO)</th><th>Lecciones que lo usan</th><th></th></tr></thead>
    <tbody>
<?php foreach ($pkgs as $rel => $m): ?>
      <tr>
        <td><strong><?= cms_e((string) ($m['title'] ?? $rel)) ?></strong><small class="ad-help"><code><?= cms_e($rel) ?></code><?= !empty($m['version']) ? ' · SCORM ' . cms_e((string) $m['version']) : '' ?></small><?php if (isset($m['error'])): ?><small class="ad-help" style="color:#b45309"><?= cms_e($m['error']) ?></small><?php endif; ?><?php foreach ((array) ($m['warn'] ?? []) as $w): ?><small class="ad-help" style="color:#b45309"><?= cms_e($w) ?></small><?php endforeach; ?></td>
        <td><?= isset($m['scos']) ? cms_e(implode(', ', array_map(fn($x) => $x['title'], $m['scos']))) : '—' ?></td>
        <td><?php foreach ($usedBy[$rel] ?? [] as $l): ?><div><a href="<?= admin_url('edit', ['type' => lms_lesson_type(), 'slug' => $l['slug']]) ?>"><?= cms_e((string) cms_f($l, 'title', $lang)) ?></a> <small class="ad-help" style="display:inline">· <?= cms_e($courseTitle((string) ($l['course'] ?? ''))) ?></small></div><?php endforeach; ?>
<?php if (!isset($m['error']) && $courses): ?>
          <form method="post" class="ad-inline-form" style="margin-top:6px">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="scorm_lesson"><input type="hidden" name="pkg" value="<?= cms_e($rel) ?>">
            <select name="course" required><option value="">— curso —</option><?php foreach ($courses as $cs => $c): ?><option value="<?= cms_e($cs) ?>"><?= cms_e($courseTitle($cs)) ?></option><?php endforeach; ?></select>
            <input type="text" name="title" placeholder="<?= cms_e((string) $m['title']) ?>" style="width:180px"> <input type="number" name="order" step="any" placeholder="orden" style="width:80px"> <input type="text" name="module" placeholder="módulo" style="width:120px">
            <button class="ad-btn ad-btn-sm" type="submit">Crear lección</button>
          </form>
<?php endif; ?></td>
        <td class="ad-row-actions"><?php if (empty($usedBy[$rel])): ?><form method="post" class="ad-inline" data-confirm="¿Borrar el paquete /<?= cms_e($rel) ?>/?"><?= admin_csrf_field() ?><input type="hidden" name="action" value="scorm_delete"><input type="hidden" name="pkg" value="<?= cms_e($rel) ?>"><button class="ad-btn ad-btn-sm ad-btn-danger" type="submit">Borrar</button></form><?php endif; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<?php elseif ($tab === 'importar'): /* ============================== importar de Archivos y carpetas */
    $cands = lms_import_candidates();
    $dir = trim((string) ($_GET['dir'] ?? ''), '/');
    $impDef = ['pass' => 80, 'attempts' => 0, 'final_attempts' => 2, 'shuffle' => true, 'reveal' => '', 'gate_final' => true, 'module' => ''];
    $plan = $dir !== '' ? lms_import_plan($dir, $impDef) : null; ?>
<section class="ad-box">
  <h2>Importar un curso hecho a mano</h2>
  <p class="ad-help">Para los cursos subidos como carpetas en <a href="<?= admin_url('archivos') ?>">Archivos y carpetas</a> (un <code>index.html</code> con la lista <code>MODULOS</code> de videos, como <code>/capacitacion/arbitraje/</code>). Se crea el curso con una lección por video; los videos se quedan donde están. También trae el contenido de cada lección, los materiales y las evaluaciones si vienen junto (carpetas <code>contenido/</code>, <code>materiales/</code>, <code>evaluaciones/</code> y un <code>curso.json</code> opcional, en la carpeta del curso o en <code>aula/&lt;curso&gt;/</code>); un curso ya importado se puede completar después<?= lms_settings()['protect'] ? ' y su carpeta queda protegida: solo se ven desde el aula, a quien tenga acceso' : '' ?>. Los datos de la tarjeta (temas, para quién, color, duración) se toman del catálogo de la carpeta de arriba si lo hay.</p>
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
<?php $ex = $plan['extras']; $qt = lms_quiz_type(); if ($ex['dir'] !== ''): ?>
  <h3 style="margin:22px 0 6px">Lo que acompaña al curso <small class="ad-help" style="display:inline">en <code>/<?= cms_e($ex['dir']) ?>/</code><?= $ex['manifest'] ? ' · con curso.json' : '' ?></small></h3>
  <ul class="ad-help" style="margin:0 0 10px 18px">
    <li>Contenido de <?= count($ex['bodies']) ?> lección(es) (objetivos, ejercicios…)<?= $ex['module'] !== '' ? ' · módulo «' . cms_e($ex['module']) . '»' : '' ?></li>
    <li>Materiales del curso: <?= $ex['materials'] ? cms_e(implode(', ', array_map(fn($m) => $m['label'] . ($m['to'] !== $m['from'] ? ' (se copia a /' . $m['to'] . ')' : ''), $ex['materials']))) : 'ninguno' ?></li>
    <?php if ($ex['course']): ?><li>Del curso: <?= cms_e(implode(', ', array_map(fn($k, $v) => $k . ' = ' . (is_array($v) ? reset($v) : $v), array_keys($ex['course']), $ex['course']))) ?></li><?php endif; ?>
  </ul>
<?php if ($ex['quizzes']): ?>
  <table class="ad-table">
    <thead><tr><th>Archivo</th><th>Evaluación</th><th>Va</th><th>Preguntas</th><th>Aprobar · intentos</th></tr></thead>
    <tbody>
<?php foreach ($ex['quizzes'] as $q): $qi = $q['item']; ?>
      <tr><td><code><?= cms_e(basename($q['file'])) ?></code><small class="ad-help"><?= cms_e($q['format']) ?></small></td><td><strong><?= cms_e($qi['title'][$dl]) ?></strong><?= cms_item($qt, $qi['slug'], false) ? ' <span class="ad-pill warn">ya existe</span>' : '' ?><?php foreach ($q['warn'] as $w): ?><small class="ad-help" style="color:#b45309"><?= cms_e($w) ?></small><?php endforeach; ?></td>
        <td><?= $qi['after'] !== '' ? 'después de ' . cms_e($qi['after']) : 'al final' . ($qi['gate'] ? ' (requisito)' : '') ?></td><td><?= (int) $q['count'] ?></td><td><?= (int) $qi['pass'] ?> % · <?= $qi['attempts'] ? (int) $qi['attempts'] : 'sin límite' ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php foreach ($ex['warn'] as $w): ?>  <p class="ad-flash err"><?= cms_e($w) ?></p>
<?php endforeach; ?>
<?php endif; ?>
<?php $impOpts = function (bool $complete) use ($ex): void { if ($ex['dir'] === '') return; ?>
    <fieldset style="border:1px solid #e3e3e3;border-radius:10px;padding:10px 14px;margin:10px 0">
      <legend class="ad-help" style="padding:0 6px">Evaluaciones y lecciones (curso.json o la cabecera de cada archivo mandan sobre esto)</legend>
      <div class="ad-two">
        <div class="ad-field"><label>Calificación para aprobar (%)</label><input type="number" name="pass" min="0" max="100" value="80"></div>
        <div class="ad-field"><label>Intentos por lección (0 = sin límite) / del final</label><span style="display:flex;gap:8px"><input type="number" name="attempts" min="0" value="0"><input type="number" name="final_attempts" min="0" value="2"></span></div>
      </div>
      <div class="ad-two">
        <div class="ad-field"><label>Al terminar, el alumno ve</label><select name="reveal"><option value="">Aciertos; correctas y explicaciones al aprobar o agotar intentos</option><option value="siempre">Correctas y explicaciones siempre</option><option value="aciertos">Solo aciertos</option><option value="nada">Solo la calificación</option></select></div>
        <div class="ad-field"><label>Módulo de todas las lecciones (opcional)</label><input type="text" name="module" value="<?= cms_e($ex['module']) ?>" placeholder="Fundamentos · IU-102"></div>
      </div>
      <label class="ad-check"><input type="checkbox" name="shuffle" value="1" checked> Mezclar preguntas y opciones</label>
      <label class="ad-check"><input type="checkbox" name="gate_final" value="1" checked> La evaluación final se abre solo al terminar todo lo anterior</label>
<?php if ($complete): ?>
      <label class="ad-check"><input type="checkbox" name="publish_quiz" value="1" checked> Publicar las evaluaciones nuevas</label>
      <label class="ad-check"><input type="checkbox" name="replace_body" value="1"> Reemplazar el contenido y el módulo de las lecciones que ya tienen (si no, solo se llenan las vacías)</label>
      <label class="ad-check"><input type="checkbox" name="replace_quiz" value="1"> Reemplazar las evaluaciones que ya existen (preguntas y opciones; los intentos de los alumnos se conservan)</label>
<?php endif; ?>
    </fieldset>
<?php }; ?>
<?php if ($exists): $oldC = cms_item($ct, $pc['slug'], false); $oldEmpty = !lms_lessons($pc['slug'], false); ?>
  <form method="post" class="ad-form" style="margin-top:14px">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="dir" value="<?= cms_e($plan['rel']) ?>">
    <p class="ad-help"><?= $oldEmpty
        ? 'El curso «' . cms_e($pc['slug']) . '» ya existe pero no tiene lecciones' . (!empty($oldC['soon']) ? ' (es la carátula «Próximamente»)' : '') . ': al importar se llena con los datos de esta carpeta, deja de ser «Próximamente» y se crean sus lecciones, contenido, materiales y evaluaciones.'
        : 'El curso ya existe y tiene lecciones: se respetan sus datos y su avance; se crean las lecciones que falten y se agregan contenido, materiales y evaluaciones.' ?></p>
    <?php $impOpts(true); ?>
    <label class="ad-check"><input type="checkbox" name="publish" value="1"<?= $oldEmpty ? ' checked' : '' ?>> Publicar el curso<?= $oldEmpty ? '' : ' (si estaba en borrador)' ?></label>
    <p><button class="ad-btn" type="submit"><?= $oldEmpty ? 'Importar sobre la carátula' : 'Completar el curso' ?></button></p>
  </form>
<?php endif; ?>
<?php if (!$exists): ?>
  <form method="post" class="ad-form" style="margin-top:14px">
    <?= admin_csrf_field() ?><input type="hidden" name="action" value="import"><input type="hidden" name="dir" value="<?= cms_e($plan['rel']) ?>">
    <?php $impOpts(false); ?>
    <label class="ad-check"><input type="checkbox" name="publish" value="1"> Publicar el curso y sus evaluaciones ya (si no, quedan en borrador para revisarlos; en borrador solo los ves tú desde el panel)</label>
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
    $blocks = lms_blocks();
    if ($blocks): ?>
<section class="ad-box">
  <h2>Bloqueados por intentos fallidos</h2>
  <p class="ad-help">Tras 5 contraseñas equivocadas, ese correo no puede entrar desde esa conexión durante 15 minutos (y tras 30 intentos, la conexión entera). Si es alguien de confianza, desbloquéalo aquí; si olvidó su contraseña, ponle una nueva en su ficha (eso también lo desbloquea).</p>
  <table class="ad-table">
    <thead><tr><th>Correo</th><th>Espera</th><th></th></tr></thead>
    <tbody>
<?php foreach ($blocks as $b): $bu = $b['email'] !== '' ? lms_user_by_email($b['email']) : null; ?>
      <tr><td><?= $b['email'] !== '' ? ($bu ? '<a href="' . cms_e($url(['id' => $bu['id']])) . '">' . cms_e($b['email']) . '</a>' : cms_e($b['email']) . ' <small class="ad-help">(no es alumno)</small>') : '<em>Toda una conexión (muchos correos distintos)</em>' ?></td><td><?= (int) $b['mins'] ?> min</td>
        <td class="ad-row-actions"><?php if ($b['email'] !== ''): ?><form method="post" class="ad-inline"><?= admin_csrf_field() ?><input type="hidden" name="action" value="unblock"><input type="hidden" name="email" value="<?= cms_e($b['email']) ?>"><button class="ad-btn ad-btn-sm" type="submit">Desbloquear</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" class="ad-inline" style="margin-top:8px"><?= admin_csrf_field() ?><input type="hidden" name="action" value="unblock"><input type="hidden" name="email" value=""><button class="ad-btn ad-btn-sm ad-btn-light" type="submit">Quitar todos los bloqueos</button></form>
</section>
<?php endif; ?>
<?php /* lista de alumnos */
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
      <label class="ad-check"><input type="checkbox" name="send" value="1" checked> Enviarle sus datos de acceso por correo (dirección del aula, su correo y su contraseña)</label>
      <p><button class="ad-btn" type="submit">Dar de alta</button></p>
    </form>
    <p class="ad-help">El correo sale del servidor del sitio (función mail de PHP) a nombre de no-reply@<?= cms_e(preg_replace('/^www\./', '', (string) parse_url(cms_site_url(), PHP_URL_HOST))) ?>. Si no llega, que revisen la carpeta de spam.</p>
    <p class="ad-help">El alumno entra en <a href="<?= cms_e(lms_url('entrar', $lang)) ?>" target="_blank" rel="noopener"><?= cms_e(lms_url('entrar', $lang)) ?></a> y puede cambiar su contraseña en «Mi cuenta». Los cursos «abiertos» y «con cuenta» no necesitan inscripción: el alumno queda inscrito al terminar su primera lección.</p>
  </section>
</div>
<?php if ($mails = lms_mail_recent(15)): ?>
<section class="ad-box">
  <h2>Correos enviados</h2>
  <p class="ad-help">Los últimos correos del aula (accesos, constancias, avisos). «Falló» quiere decir que el servidor no aceptó enviarlo: revisa con tu alojamiento que la función mail de PHP esté activa. «Ok» quiere decir que salió del servidor; si no llega, puede estar en spam.</p>
  <table class="ad-table">
    <thead><tr><th>Fecha</th><th>Resultado</th><th>Para</th><th>Asunto</th></tr></thead>
    <tbody>
<?php foreach ($mails as [$md, $mok, $mto, $msub]): ?>      <tr><td><?= cms_e($md) ?></td><td><?= $mok === 'ok' ? '<span class="ad-pill on">ok</span>' : '<span class="ad-pill warn">falló</span>' ?></td><td><?= cms_e($mto) ?></td><td><?= cms_e($msub) ?></td></tr>
<?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
<?php endif; ?>
<?php admin_footer();
