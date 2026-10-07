<?php
/**
 * Paquete "lms": aula en línea. Código común: alumnos, sesión, avance, acceso, rutas públicas y plantillas.
 *
 * Datos (todo JSON, fuera del alcance web por data/.htaccess):
 *   data/lms/users.json             alumnos: id => ['id', 'name', 'email', 'hash', 'active', 'created', 'notes']
 *   data/lms/progress/<id>.json     avance de un alumno: ['seen' => fecha, 'courses' => [curso => ['enrolled', 'by',
 *                                   'lessons' => [lección => fecha], 'last', 'completed']]]. Un archivo por alumno:
 *                                   marcar una lección reescribe solo el suyo. Reservado para la versión con exámenes:
 *                                   courses.<curso>.exams.<examen> = ['attempts' => [...], 'best' => n].
 *   data/lms/attempts.json          intentos fallidos de entrar, por IP (5 en 15 minutos bloquean)
 *
 * Sesión sin $_SESSION: una cookie firmada (HMAC con data/.secret) con el id del alumno, su caducidad y un trozo del
 * hash de su contraseña; cambiar la contraseña o desactivar al alumno la invalida. Así no choca con la sesión del panel
 * ni depende de la limpieza de sesiones del hosting (que en muchos cierra a los 24 minutos sin actividad).
 *
 * Rutas (gancho 'route'): /aula, /aula/entrar, /aula/registro, /aula/cuenta, /aula/salir, /aula/avance (POST).
 * Plantillas (gancho 'template'): cursos.php, curso.php y leccion.php del paquete cuando el tema no trae las suyas.
 */
declare(strict_types=1);
if (!function_exists('lms_settings')) {

    /* ================================================================== ajustes y textos */

    function lms_settings(): array
    {
        static $o = null;
        if ($o !== null) return $o;
        $S = cms_settings();
        $g = fn(string $k, string $d) => trim((string) (($S[$k] ?? '') === '' ? $d : $S[$k]));
        $access = $g('lms_access', 'cuenta');
        return $o = [
            'access'       => in_array($access, ['abierto', 'cuenta', 'inscritos'], true) ? $access : 'cuenta',
            'signup'       => !empty($S['lms_signup']),
            'signup_code'  => $g('lms_signup_code', ''),
            'route'        => cms_slugify($g('lms_route', 'aula')) ?: 'aula',
            'protect'      => !isset($S['lms_protect']) || !empty($S['lms_protect']),
            'notify_to'    => filter_var($g('lms_notify_to', ''), FILTER_VALIDATE_EMAIL) ? $g('lms_notify_to', '') : '',
            'course_type'  => preg_replace('/[^a-z0-9_-]/i', '', $g('lms_course_type', 'cursos')) ?: 'cursos',
            'lesson_type'  => preg_replace('/[^a-z0-9_-]/i', '', $g('lms_lesson_type', 'lecciones')) ?: 'lecciones',
            'lesson_field' => preg_replace('/[^a-z0-9_-]/i', '', $g('lms_lesson_field', 'course')) ?: 'course',
        ];
    }

    function lms_course_type(): string { return lms_settings()['course_type']; }
    function lms_lesson_type(): string { return lms_settings()['lesson_type']; }

    /** Texto de la interfaz del aula. Se puede cambiar en Textos del sitio con la clave lms_<clave> (si el tema la agrupa). */
    function lms_tx(string $k, ...$args): string
    {
        static $T = [
            'my_classroom'   => ['es' => 'Mi aula', 'en' => 'My classroom'],
            'login'          => ['es' => 'Entrar', 'en' => 'Sign in'],
            'login_title'    => ['es' => 'Entrar al aula', 'en' => 'Sign in to the classroom'],
            'login_lead'     => ['es' => 'Con tu correo y contraseña ves tus cursos y tu avance.', 'en' => 'Use your email and password to see your courses and progress.'],
            'logout'         => ['es' => 'Salir', 'en' => 'Sign out'],
            'signup'         => ['es' => 'Crear cuenta', 'en' => 'Create account'],
            'signup_title'   => ['es' => 'Crear tu cuenta', 'en' => 'Create your account'],
            'signup_lead'    => ['es' => 'Con una cuenta tu avance queda guardado y puedes seguir donde te quedaste.', 'en' => 'With an account your progress is saved and you can pick up where you left off.'],
            'no_account'     => ['es' => '¿No tienes cuenta?', 'en' => 'No account yet?'],
            'have_account'   => ['es' => '¿Ya tienes cuenta?', 'en' => 'Already have an account?'],
            'ask_account'    => ['es' => 'Las cuentas las da de alta el administrador. Escríbenos si necesitas una.', 'en' => 'Accounts are created by the administrator. Write to us if you need one.'],
            'name'           => ['es' => 'Nombre completo', 'en' => 'Full name'],
            'email'          => ['es' => 'Correo', 'en' => 'Email'],
            'password'       => ['es' => 'Contraseña', 'en' => 'Password'],
            'password_new'   => ['es' => 'Nueva contraseña (mínimo 8 caracteres)', 'en' => 'New password (at least 8 characters)'],
            'password_cur'   => ['es' => 'Contraseña actual', 'en' => 'Current password'],
            'password_rep'   => ['es' => 'Repite la contraseña', 'en' => 'Repeat the password'],
            'code'           => ['es' => 'Código de invitación', 'en' => 'Invitation code'],
            'remember'       => ['es' => 'Mantener la sesión abierta en este equipo', 'en' => 'Keep me signed in on this device'],
            'account'        => ['es' => 'Mi cuenta', 'en' => 'My account'],
            'save'           => ['es' => 'Guardar', 'en' => 'Save'],
            'hello'          => ['es' => 'Hola, %s', 'en' => 'Hello, %s'],
            'dash_lead'      => ['es' => 'Tus cursos y tu avance. Sigue donde te quedaste.', 'en' => 'Your courses and progress. Pick up where you left off.'],
            'my_courses'     => ['es' => 'Mis cursos', 'en' => 'My courses'],
            'no_courses'     => ['es' => 'Todavía no tienes cursos. Elige uno de la lista para empezar.', 'en' => 'You have no courses yet. Pick one from the list to start.'],
            'other_courses'  => ['es' => 'Otros cursos', 'en' => 'Other courses'],
            'all_courses'    => ['es' => 'Ver todos los cursos', 'en' => 'See all courses'],
            'courses'        => ['es' => 'Cursos', 'en' => 'Courses'],
            'courses_lead'   => ['es' => 'Capacitación en línea, a tu ritmo.', 'en' => 'Online training, at your own pace.'],
            'courses_empty'  => ['es' => 'Pronto publicaremos cursos aquí.', 'en' => 'Courses will be published here soon.'],
            'lessons_n'      => ['es' => '%d lecciones', 'en' => '%d lessons'],
            'lesson_1'       => ['es' => '1 lección', 'en' => '1 lesson'],
            'progress'       => ['es' => '%d de %d lecciones · %d %%', 'en' => '%d of %d lessons · %d%%'],
            'completed'      => ['es' => 'Terminado', 'en' => 'Completed'],
            'completed_on'   => ['es' => 'Terminaste este curso el %s.', 'en' => 'You completed this course on %s.'],
            'start'          => ['es' => 'Empezar el curso', 'en' => 'Start the course'],
            'continue'       => ['es' => 'Continuar', 'en' => 'Continue'],
            'review'         => ['es' => 'Repasar', 'en' => 'Review'],
            'login_to_start' => ['es' => 'Entra para empezar', 'en' => 'Sign in to start'],
            'login_to_save'  => ['es' => 'Entra para guardar tu avance', 'en' => 'Sign in to save your progress'],
            'only_enrolled'  => ['es' => 'Este curso es solo para alumnos inscritos.', 'en' => 'This course is for enrolled students only.'],
            'ask_enroll'     => ['es' => 'Pide acceso', 'en' => 'Request access'],
            'contents'       => ['es' => 'Contenido del curso', 'en' => 'Course content'],
            'you_learn'      => ['es' => 'Lo que vas a aprender', 'en' => 'What you will learn'],
            'for'            => ['es' => 'Para:', 'en' => 'For:'],
            'soon'           => ['es' => 'Próximamente', 'en' => 'Coming soon'],
            'soon_text'      => ['es' => 'Este curso está en preparación.', 'en' => 'This course is in preparation.'],
            'available'      => ['es' => 'Disponible', 'en' => 'Available'],
            'in_progress'    => ['es' => 'En curso', 'en' => 'In progress'],
            'in_prep'        => ['es' => 'En preparación', 'en' => 'In preparation'],
            'soon_short'     => ['es' => 'Pronto', 'en' => 'Soon'],
            'see_course'     => ['es' => 'Ver curso', 'en' => 'See course'],
            'n_course_1'     => ['es' => 'curso disponible', 'en' => 'course available'],
            'n_courses'      => ['es' => 'cursos disponibles', 'en' => 'courses available'],
            'n_soon'         => ['es' => 'en preparación', 'en' => 'in preparation'],
            'n_lessons'      => ['es' => 'lecciones en video', 'en' => 'video lessons'],
            'n_minutes'      => ['es' => 'de contenido', 'en' => 'of content'],
            'hero_kicker'    => ['es' => 'Capacitación', 'en' => 'Training'],
            'hero_title'     => ['es' => 'Cursos en línea, *a tu ritmo*', 'en' => 'Online courses, *at your own pace*'],
            'hero_lead'      => ['es' => 'Cursos en video, cortos y en orden. Entra con tu cuenta y sigue donde te quedaste.', 'en' => 'Short video courses, in order. Sign in and pick up where you left off.'],
            'sample'         => ['es' => 'Muestra', 'en' => 'Preview'],
            'level'          => ['es' => 'Nivel', 'en' => 'Level'],
            'duration'       => ['es' => 'Duración', 'en' => 'Duration'],
            'lesson'         => ['es' => 'Lección %d de %d', 'en' => 'Lesson %d of %d'],
            'mark_done'      => ['es' => 'Marcar como terminada', 'en' => 'Mark as complete'],
            'mark_next'      => ['es' => 'Terminada: siguiente lección', 'en' => 'Complete: next lesson'],
            'mark_undo'      => ['es' => 'Marcar como pendiente', 'en' => 'Mark as not complete'],
            'done'           => ['es' => 'Lección terminada', 'en' => 'Lesson complete'],
            'prev'           => ['es' => 'Anterior', 'en' => 'Previous'],
            'next'           => ['es' => 'Siguiente', 'en' => 'Next'],
            'back_course'    => ['es' => 'Volver al curso', 'en' => 'Back to the course'],
            'materials'      => ['es' => 'Materiales', 'en' => 'Materials'],
            'locked'         => ['es' => 'Esta lección es parte del curso «%s».', 'en' => 'This lesson is part of the course “%s”.'],
            'locked_login'   => ['es' => 'Entra con tu cuenta para verla.', 'en' => 'Sign in with your account to see it.'],
            'staff_view'     => ['es' => 'Vista de administración: ves todas las lecciones; el avance no se guarda.', 'en' => 'Admin view: you see every lesson; progress is not saved.'],
            'err_login'      => ['es' => 'Correo o contraseña incorrectos.', 'en' => 'Wrong email or password.'],
            'err_blocked'    => ['es' => 'Demasiados intentos. Espera %d minutos.', 'en' => 'Too many attempts. Wait %d minutes.'],
            'err_csrf'       => ['es' => 'La página caducó. Vuelve a intentarlo.', 'en' => 'The page expired. Please try again.'],
            'err_email'      => ['es' => 'Escribe un correo válido.', 'en' => 'Enter a valid email.'],
            'err_exists'     => ['es' => 'Ya hay una cuenta con ese correo.', 'en' => 'There is already an account with that email.'],
            'err_pass'       => ['es' => 'La contraseña debe tener al menos 8 caracteres.', 'en' => 'The password must have at least 8 characters.'],
            'err_repeat'     => ['es' => 'Las contraseñas no coinciden.', 'en' => 'Passwords do not match.'],
            'err_name'       => ['es' => 'Escribe tu nombre.', 'en' => 'Enter your name.'],
            'err_code'       => ['es' => 'El código de invitación no es correcto.', 'en' => 'Wrong invitation code.'],
            'err_current'    => ['es' => 'La contraseña actual no es correcta.', 'en' => 'The current password is wrong.'],
            'err_save'       => ['es' => 'No se pudo guardar. Avísanos si sigue pasando.', 'en' => 'Could not save. Let us know if it keeps happening.'],
            'ok_saved'       => ['es' => 'Cambios guardados.', 'en' => 'Changes saved.'],
            'ok_welcome'     => ['es' => 'Bienvenido. Tu cuenta está lista.', 'en' => 'Welcome. Your account is ready.'],
            'ok_completed'   => ['es' => '¡Terminaste el curso!', 'en' => 'You completed the course!'],
        ];
        $lang = cms_current()['lang'] ?: cms_default_lang();
        $s = (string) cms_t('lms_' . $k, $lang, '');
        if ($s === '') $s = $T[$k][$lang] ?? ($T[$k]['es'] ?? $k);
        return $args ? vsprintf($s, $args) : $s;
    }

    /** Texto de Ajustes → Aula en el idioma de la página (campos i18n), con respaldo al de lms_tx(). */
    function lms_setting_text(string $key, string $fallbackTx = ''): string
    {
        $v = cms_settings()[$key] ?? '';
        $lang = cms_current()['lang'] ?: cms_default_lang();
        if (is_array($v)) $v = (string) ($v[$lang] ?? '') !== '' ? $v[$lang] : ($v[cms_default_lang()] ?? '');
        $v = trim((string) $v);
        return $v !== '' ? $v : ($fallbackTx !== '' ? lms_tx($fallbackTx) : '');
    }

    /** Título con *énfasis*: escapa y convierte lo que va entre asteriscos en <em>. */
    function lms_emph(string $s): string
    {
        return preg_replace('/\*(.+?)\*/u', '<em>$1</em>', cms_e($s)) ?? cms_e($s);
    }

    /* ================================================================== alumnos */

    function lms_dir(): string { return CMS_DATA . '/lms'; }

    function lms_users(bool $reload = false): array
    {
        static $u = null;
        if ($u === null || $reload) $u = (array) cms_json_read(lms_dir() . '/users.json', []);
        return $u;
    }

    function lms_users_save(array $users): bool
    {
        $ok = cms_json_write(lms_dir() . '/users.json', $users);
        lms_users(true);
        return $ok;
    }

    function lms_user_get(string $id): ?array
    {
        $u = lms_users()[$id] ?? null;
        return is_array($u) ? $u : null;
    }

    function lms_email_norm(string $email): string { return mb_strtolower(trim($email)); }

    function lms_user_by_email(string $email): ?array
    {
        $email = lms_email_norm($email);
        if ($email === '') return null;
        foreach (lms_users() as $u) if (($u['email'] ?? '') === $email) return $u;
        return null;
    }

    /** Alta de un alumno. Devuelve [true, id] o [false, clave de error de lms_tx()]. */
    function lms_user_create(string $name, string $email, string $pass, string $notes = ''): array
    {
        $email = lms_email_norm($email);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') return [false, 'err_name'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'err_email'];
        if (lms_user_by_email($email)) return [false, 'err_exists'];
        if (strlen($pass) < 8) return [false, 'err_pass'];
        $users = lms_users(true);
        do { $id = bin2hex(random_bytes(6)); } while (isset($users[$id]));
        $users[$id] = ['id' => $id, 'name' => mb_substr($name, 0, 120), 'email' => $email, 'hash' => password_hash($pass, PASSWORD_DEFAULT),
                       'active' => true, 'created' => date('Y-m-d H:i'), 'notes' => $notes];
        return lms_users_save($users) ? [true, $id] : [false, 'err_save'];
    }

    /** Cambia campos de un alumno ('pass' se convierte en hash). */
    function lms_user_update(string $id, array $changes): bool
    {
        $users = lms_users(true);
        if (!isset($users[$id])) return false;
        if (isset($changes['pass'])) { $changes['hash'] = password_hash((string) $changes['pass'], PASSWORD_DEFAULT); unset($changes['pass']); }
        if (isset($changes['email'])) $changes['email'] = lms_email_norm((string) $changes['email']);
        $users[$id] = array_replace($users[$id], $changes);
        return lms_users_save($users);
    }

    function lms_user_delete(string $id): bool
    {
        $users = lms_users(true);
        if (!isset($users[$id])) return false;
        unset($users[$id]);
        @unlink(lms_progress_file($id));
        return lms_users_save($users);
    }

    /** Contraseña legible para dar de alta (sin caracteres que se confunden: 0/O, 1/l/I). */
    function lms_password_gen(int $len = 12): string
    {
        $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i = 0; $i < $len; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
        return $s;
    }

    /* ================================================================== avance e inscripciones */

    function lms_progress_file(string $uid): string { return lms_dir() . '/progress/' . preg_replace('/[^a-f0-9]/', '', $uid) . '.json'; }

    function lms_progress(string $uid): array
    {
        $p = cms_json_read(lms_progress_file($uid), []);
        $p['courses'] = (array) ($p['courses'] ?? []);
        return $p;
    }

    function lms_progress_save(string $uid, array $p): bool
    {
        $p['seen'] = date('Y-m-d H:i');
        return cms_json_write(lms_progress_file($uid), $p);
    }

    function lms_is_enrolled(string $uid, string $course): bool
    {
        return !empty(lms_progress($uid)['courses'][$course]['enrolled']);
    }

    /** Inscribe (o no hace nada si ya lo estaba). $by: 'admin' o 'alumno'. */
    function lms_enroll(string $uid, string $course, string $by = 'alumno'): bool
    {
        $p = lms_progress($uid);
        if (!empty($p['courses'][$course]['enrolled'])) return true;
        $p['courses'][$course] = array_replace(['lessons' => []], (array) ($p['courses'][$course] ?? []), ['enrolled' => date('Y-m-d H:i'), 'by' => $by]);
        return lms_progress_save($uid, $p);
    }

    /** Quita la inscripción; el avance se conserva salvo que se pida borrarlo. */
    function lms_unenroll(string $uid, string $course, bool $wipe = false): bool
    {
        $p = lms_progress($uid);
        if (!isset($p['courses'][$course])) return true;
        if ($wipe) unset($p['courses'][$course]);
        else unset($p['courses'][$course]['enrolled'], $p['courses'][$course]['by']);
        return lms_progress_save($uid, $p);
    }

    /** Marca (o desmarca) una lección. Inscribe de paso y fecha el curso como terminado al llegar al 100 %. */
    function lms_mark(string $uid, string $course, string $lesson, bool $done): bool
    {
        $p = lms_progress($uid);
        $c = (array) ($p['courses'][$course] ?? []);
        $c += ['lessons' => []];
        if (empty($c['enrolled'])) { $c['enrolled'] = date('Y-m-d H:i'); $c['by'] = 'alumno'; }
        if ($done) { if (empty($c['lessons'][$lesson])) $c['lessons'][$lesson] = date('Y-m-d H:i'); }
        else unset($c['lessons'][$lesson]);
        $c['last'] = $lesson;
        $p['courses'][$course] = $c;
        $st = lms_stats_from($c, lms_lessons($course));
        if ($st['total'] > 0 && $st['done'] >= $st['total']) { if (empty($p['courses'][$course]['completed'])) $p['courses'][$course]['completed'] = date('Y-m-d H:i'); }
        else unset($p['courses'][$course]['completed']);
        return lms_progress_save($uid, $p);
    }

    /* ================================================================== cursos y lecciones */

    function lms_courses(bool $published = true): array
    {
        return cms_type(lms_course_type()) ? cms_items(lms_course_type(), $published) : [];
    }

    function lms_course(string $slug, bool $published = true): ?array
    {
        return $slug !== '' && cms_type(lms_course_type()) ? cms_item(lms_course_type(), $slug, $published) : null;
    }

    /** Lecciones de un curso, en orden (campo order, luego título). Del índice ligero: sin el cuerpo. */
    function lms_lessons(string $course, bool $published = true): array
    {
        static $cache = [];
        $key = $course . '|' . (int) $published;
        if (isset($cache[$key])) return $cache[$key];
        if (!cms_type(lms_lesson_type())) return [];
        $f = lms_settings()['lesson_field'];
        $out = array_values(array_filter(cms_items(lms_lesson_type(), $published), fn($l) => (string) ($l[$f] ?? '') === $course));
        usort($out, function ($a, $b) {
            $oa = is_numeric($a['order'] ?? null) ? (float) $a['order'] : 1e9; $ob = is_numeric($b['order'] ?? null) ? (float) $b['order'] : 1e9;
            return $oa <=> $ob ?: strnatcasecmp((string) cms_f($a, 'title', cms_default_lang()), (string) cms_f($b, 'title', cms_default_lang()));
        });
        return $cache[$key] = $out;
    }

    /** Lecciones agrupadas por módulo, en el orden en que aparece cada módulo: [[nombre, [lecciones]], …]. */
    function lms_modules(array $lessons, string $lang): array
    {
        $mods = [];
        foreach ($lessons as $l) {
            $m = trim((string) cms_f($l, 'module', $lang));
            $mods[$m][] = $l;
        }
        $out = [];
        foreach ($mods as $m => $ls) $out[] = [(string) $m, $ls];
        return $out;
    }

    /** Curso de una lección. */
    function lms_lesson_course(array $lesson): string { return (string) ($lesson[lms_settings()['lesson_field']] ?? ''); }

    /** 'abierto', 'cuenta' o 'inscritos'. */
    function lms_course_access(array $course): string
    {
        $a = (string) ($course['access'] ?? '');
        return in_array($a, ['abierto', 'cuenta', 'inscritos'], true) ? $a : lms_settings()['access'];
    }

    /** ¿Quien está viendo puede abrir esta lección? */
    function lms_can_view(array $course, array $lesson): bool
    {
        if (lms_staff()) return true;
        if (!empty($course['soon'])) return false;   // "Próximamente": se anuncia, no se abre
        if (!empty($lesson['preview'])) return true;
        $a = lms_course_access($course);
        if ($a === 'abierto') return true;
        $u = lms_user();
        if (!$u) return false;
        return $a === 'cuenta' || lms_is_enrolled($u['id'], (string) $course['slug']);
    }

    /** ¿Puede abrir el curso completo (no solo las muestras)? */
    function lms_can_take(array $course): bool
    {
        return lms_can_view($course, ['preview' => false]);
    }

    /** Avance de un registro de curso contra sus lecciones actuales (las borradas no cuentan). */
    function lms_stats_from(array $c, array $lessons): array
    {
        $done = 0; $next = null;
        foreach ($lessons as $l) {
            if (!empty($c['lessons'][$l['slug']])) $done++;
            elseif ($next === null) $next = $l;
        }
        $total = count($lessons);
        return ['done' => $done, 'total' => $total, 'pct' => $total ? (int) floor($done * 100 / $total) : 0, 'next' => $next,
                'enrolled' => !empty($c['enrolled']), 'started' => $done > 0 || !empty($c['enrolled']), 'completed' => (string) ($c['completed'] ?? ''),
                'lessons' => (array) ($c['lessons'] ?? [])];
    }

    /** Avance del alumno que está viendo en un curso (o vacío si no hay alumno). */
    function lms_stats(string $course, ?array $user = null): array
    {
        $user = $user ?? lms_user();
        $c = $user ? (array) (lms_progress($user['id'])['courses'][$course] ?? []) : [];
        return lms_stats_from($c, lms_lessons($course));
    }

    /* ================================================================== sesión (cookie firmada) */

    function lms_https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    function lms_cookie(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, ['expires' => $expires, 'path' => (CMS_BASE ?: '/'), 'secure' => lms_https(), 'httponly' => true, 'samesite' => 'Lax']);
        if ($expires !== 0 && $expires < time()) unset($_COOKIE[$name]); else $_COOKIE[$name] = $value;
    }

    function lms_sign(string $uid, int $exp, string $hash): string
    {
        return hash_hmac('sha256', 'lms|' . $uid . '|' . $exp . '|' . substr($hash, -16), cms_secret());
    }

    /** Alumno con sesión abierta (cookie válida, alumno activo) o null. */
    function lms_user(): ?array
    {
        if (array_key_exists('lms_user', $GLOBALS)) return $GLOBALS['lms_user'];
        $GLOBALS['lms_user'] = null;
        $parts = explode('.', (string) ($_COOKIE['cmsaula'] ?? ''));
        if (count($parts) !== 3 || !preg_match('/^[a-f0-9]{12}$/', $parts[0]) || !ctype_digit($parts[1]) || (int) $parts[1] < time()) return null;
        $u = lms_user_get($parts[0]);
        if (!$u || empty($u['active']) || !hash_equals(lms_sign($u['id'], (int) $parts[1], (string) $u['hash']), $parts[2])) return null;
        return $GLOBALS['lms_user'] = $u;
    }

    function lms_login(array $u, bool $remember): void
    {
        $exp = time() + ($remember ? 86400 * 30 : 3600 * 12);
        lms_cookie('cmsaula', $u['id'] . '.' . $exp . '.' . lms_sign($u['id'], $exp, (string) $u['hash']), $remember ? $exp : 0);
        $GLOBALS['lms_user'] = $u;
        $p = lms_progress($u['id']);
        lms_progress_save($u['id'], $p);   // fecha de última visita
    }

    /** ¿La sesión actual se abrió con "mantener la sesión abierta"? (caduca en más de 12 horas) */
    function lms_remembered(): bool
    {
        $parts = explode('.', (string) ($_COOKIE['cmsaula'] ?? ''));
        return count($parts) === 3 && ctype_digit($parts[1]) && (int) $parts[1] - time() > 13 * 3600;
    }

    function lms_logout(): void
    {
        lms_cookie('cmsaula', '', time() - 3600);
        $GLOBALS['lms_user'] = null;
    }

    /**
     * ¿Hay alguien del panel con sesión abierta? Lee la sesión del panel sin bloquearla ni modificarla
     * (read_and_close). Quien administra ve todas las lecciones para revisarlas. Se resuelve antes de dibujar (en
     * los ganchos route y template): una vez enviada la cabecera de la página ya no se puede abrir una sesión.
     */
    function lms_staff(): bool
    {
        static $r = null;
        if ($r !== null) return $r;
        $r = false;
        $sid = (string) ($_COOKIE['cmsadmin'] ?? '');
        if ($sid === '' || !preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $sid) || session_status() !== PHP_SESSION_NONE || headers_sent()) return $r;
        $name = session_name('cmsadmin');
        session_id($sid);
        if (@session_start(['read_and_close' => true])) {
            $u = (string) ($_SESSION['user'] ?? '');
            if ($u !== '' && time() - (int) ($_SESSION['last'] ?? 0) <= 8 * 3600) foreach (cms_users() as $x) if (($x['user'] ?? '') === $u) { $r = true; break; }
        }
        $_SESSION = [];
        session_name($name);
        session_id('');
        return $r;
    }

    /** Ficha anti-CSRF: atada a la cookie de sesión del alumno o, antes de entrar, a una cookie aleatoria propia. */
    function lms_csrf(): string
    {
        $base = (string) ($_COOKIE['cmsaula'] ?? '');
        if ($base === '') {
            $base = (string) ($_COOKIE['cmsaula_f'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/', $base)) { $base = bin2hex(random_bytes(16)); if (!headers_sent()) lms_cookie('cmsaula_f', $base, 0); }
        }
        return substr(hash_hmac('sha256', 'csrf|' . $base, cms_secret()), 0, 32);
    }
    function lms_csrf_field(): string { return '<input type="hidden" name="_lms" value="' . cms_e(lms_csrf()) . '">'; }
    function lms_csrf_ok(): bool { return is_string($_POST['_lms'] ?? null) && hash_equals(lms_csrf(), (string) $_POST['_lms']); }

    /* intentos de acceso, por IP */
    function lms_throttle_wait(): int
    {
        $e = cms_json_read(lms_dir() . '/attempts.json', [])[md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''))] ?? null;
        return $e && ($e['n'] ?? 0) >= 5 && time() < ($e['until'] ?? 0) ? (int) $e['until'] - time() : 0;
    }
    function lms_throttle_record(bool $ok): void
    {
        $f = lms_dir() . '/attempts.json';
        $a = cms_json_read($f, []);
        foreach ($a as $k => $e) if (($e['until'] ?? 0) < time() - 3600) unset($a[$k]);
        $ip = md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ok) unset($a[$ip]);
        else $a[$ip] = ['n' => (int) ($a[$ip]['n'] ?? 0) + 1, 'until' => time() + 15 * 60];
        cms_json_write($f, $a);
    }

    /* ================================================================== URL y utilidades de vista */

    /** URL de una página del aula: lms_url('entrar'), lms_url('', 'en'). */
    function lms_url(string $sub = '', ?string $lang = null, array $q = []): string
    {
        $lang = $lang ?? (cms_current()['lang'] ?: cms_default_lang());
        $u = CMS_BASE . cms_lang_prefix($lang) . '/' . lms_settings()['route'] . ($sub !== '' ? '/' . $sub : '');
        return $u . ($q ? '?' . http_build_query($q) : '');
    }

    /** Destino de regreso seguro: solo rutas locales del sitio. */
    function lms_back(string $to, string $fallback): string
    {
        $to = trim($to);
        if ($to === '' || $to[0] !== '/' || strpos($to, '//') === 0 || strpos($to, '/\\') === 0 || preg_match('/[\r\n]/', $to)) return $fallback;
        if (CMS_BASE !== '' && strpos($to, CMS_BASE . '/') !== 0 && $to !== CMS_BASE) return $fallback;
        return $to;
    }

    function lms_redirect(string $to): void
    {
        header('Location: ' . $to, true, 303);
        exit;
    }

    /** URL actual (ruta + consulta), para volver después de entrar. */
    function lms_here(): string { return (string) ($_SERVER['REQUEST_URI'] ?? '/'); }

    /** Barra de avance. */
    function lms_bar(int $pct, string $label = ''): string
    {
        $pct = max(0, min(100, $pct));
        return '<div class="lms-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $pct . '"' . ($label !== '' ? ' aria-label="' . cms_e($label) . '"' : '') . '><span style="width:' . $pct . '%"></span></div>';
    }

    /** Íconos de estado de una lección. */
    function lms_icon(string $k): string
    {
        $p = [
            'done'   => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 10"/>',
            'todo'   => '<circle cx="12" cy="12" r="9"/>',
            'lock'   => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'play'   => '<circle cx="12" cy="12" r="9"/><path d="M10 8.8v6.4l5-3.2z"/>',
            'file'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
            'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'back'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
            'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        ][$k] ?? '';
        return '<svg class="lms-ico lms-ico-' . $k . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }

    /* ---- archivos de las lecciones: videos y materiales en uploads/ o en una carpeta propia (Archivos y carpetas) */

    /** Carpetas raíz que no son "propias": sus archivos no se protegen ni se sirven por el aula. */
    function lms_core_dirs(): array { return ['cms', 'site', 'themes', 'packs', 'data', 'admin', 'uploads', 'tools']; }

    /** Ruta relativa limpia de un archivo local ("capacitacion/x/v.mp4", "/uploads/…" → sin barra inicial) o '' si es externo. */
    function lms_rel(string $path): string
    {
        $path = trim($path);
        if ($path === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $path)) return '';
        $path = ltrim(preg_replace('#[?\#].*$#', '', $path) ?? '', '/');
        if (CMS_BASE !== '' && strpos('/' . $path, CMS_BASE . '/') === 0) $path = substr($path, strlen(CMS_BASE));
        $path = ltrim($path, '/');
        return strpos($path, '..') === false && strpos($path, '\\') === false ? $path : '';
    }

    /** Archivo absoluto si la ruta es local, existe y está dentro del sitio (nunca en data/ ni cms/); si no, null. */
    function lms_local_file(string $path): ?string
    {
        $rel = lms_rel($path);
        if ($rel === '' || in_array(strtolower(explode('/', $rel)[0]), ['data', 'cms', 'admin'], true)) return null;
        $f = realpath(CMS_ROOT . '/' . $rel);
        $root = realpath(CMS_ROOT);
        return $f && $root && strpos($f, $root . DIRECTORY_SEPARATOR) === 0 && is_file($f) ? $f : null;
    }

    /** ¿Este archivo va protegido? (local, en una carpeta propia, con la protección encendida). */
    function lms_protected(string $path): bool
    {
        if (!lms_settings()['protect'] || !lms_local_file($path)) return false;
        return !in_array(strtolower(explode('/', lms_rel($path))[0]), lms_core_dirs(), true);
    }

    /** URL pública de un archivo: externo tal cual, local desde la raíz del sitio, o como imagen del tema. */
    function lms_media_url(string $path): string
    {
        $rel = lms_rel($path);
        if ($rel === '') return trim($path);
        if (is_file(CMS_ROOT . '/' . $rel)) return CMS_BASE . '/' . str_replace('%2F', '/', rawurlencode($rel));
        return cms_img($path);
    }

    /** Corta el acceso directo a la carpeta de un archivo protegido con un .htaccess (como data/.htaccess). */
    function lms_protect_dir(string $path): bool
    {
        $f = lms_local_file($path);
        if (!$f || !lms_protected($path)) return false;
        $ht = dirname($f) . '/.htaccess';
        if (is_file($ht) && strpos((string) file_get_contents($ht), 'Require all denied') !== false) return true;
        $rule = "# Aula (paquete lms): sin acceso directo; los archivos se sirven por /" . lms_settings()['route'] . "/video y /archivo a quien puede ver la lección\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n";
        return @file_put_contents($ht, (is_file($ht) ? rtrim((string) file_get_contents($ht)) . "\n\n" : '') . $rule) !== false;
    }

    /** Envía un archivo con soporte de Range (para adelantar el video) y termina. */
    function lms_send_file(string $file): void
    {
        $size = (int) filesize($file);
        $mime = ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mp3' => 'audio/mpeg', 'pdf' => 'application/pdf',
                 'vtt' => 'text/vtt', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'zip' => 'application/zip'][strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
        $start = 0; $end = $size - 1;
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        if (!preg_match('#^(video|audio|image)/|pdf$#', $mime)) header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($file)) . '"');
        if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') { $start = max(0, $size - (int) $m[2]); }
            else { $start = (int) $m[1]; if ($m[2] !== '') $end = min($end, (int) $m[2]); }
            if ($start > $end || $start >= $size) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
        @set_time_limit(0);
        $fh = fopen($file, 'rb');
        fseek($fh, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($fh) && !connection_aborted()) { $chunk = fread($fh, (int) min(1 << 20, $left)); echo $chunk; flush(); $left -= strlen((string) $chunk); }
        fclose($fh);
        exit;
    }

    /** URL del video de una lección: protegido por el aula o directo. */
    function lms_video_url(array $lesson): string
    {
        $src = trim((string) ($lesson['video'] ?? ''));
        return lms_protected($src) ? lms_url('video', null, ['l' => $lesson['slug']]) : lms_media_url($src);
    }

    /** Reproductor de una lección: YouTube, Vimeo o un archivo de video (con su portada). */
    function lms_video(array $lesson, string $title = ''): string
    {
        $src = trim((string) ($lesson['video'] ?? ''));
        if ($src === '') return '';
        $t = cms_e($title);
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $src, $m)) {
            return '<div class="lms-video"><iframe src="https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0" title="' . $t . '" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe></div>';
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $src, $m)) {
            return '<div class="lms-video"><iframe src="https://player.vimeo.com/video/' . $m[1] . '?dnt=1" title="' . $t . '" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
        }
        if (preg_match('~\.(mp4|webm|m4v|mov)(\?.*)?$~i', $src)) {
            $poster = trim((string) ($lesson['poster'] ?? ''));
            return '<div class="lms-video"><video controls preload="metadata" playsinline controlslist="nodownload"' . ($poster !== '' ? ' poster="' . cms_e(lms_media_url($poster)) . '"' : '') . ' src="' . cms_e(lms_video_url($lesson)) . '"></video></div>';
        }
        return '<p><a class="btn btn-ghost" href="' . cms_e(lms_video_url($lesson)) . '" target="_blank" rel="noopener">' . lms_icon('play') . ' Video</a></p>';
    }

    /** Materiales de una lección: [[texto, ruta], …] a partir de líneas "Texto | ruta" (o solo la ruta). */
    function lms_file_list(array $lesson): array
    {
        $out = [];
        foreach ((array) ($lesson['files'] ?? []) as $line) {
            $line = trim((string) $line);
            if ($line === '') continue;
            [$label, $path] = strpos($line, '|') !== false ? array_map('trim', explode('|', $line, 2)) : [basename(parse_url($line, PHP_URL_PATH) ?: $line), $line];
            if ($path === '' || preg_match('~^\s*javascript:~i', $path)) continue;
            $out[] = [$label !== '' ? $label : $path, $path];
        }
        return $out;
    }

    /** Materiales con su URL (protegida por el aula cuando toca): [[texto, url], …]. */
    function lms_files(array $lesson): array
    {
        $out = [];
        foreach (lms_file_list($lesson) as $i => [$label, $path]) $out[] = [$label, lms_protected($path) ? lms_url('archivo', null, ['l' => $lesson['slug'], 'n' => $i]) : lms_media_url($path)];
        return $out;
    }

    /** Íconos para la tapa de un curso (campo icon) y para "Cómo están hechos". */
    function lms_course_icons(): array
    {
        return [
            'flechas'  => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
            'nucleo'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/>',
            'billetes' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 10v4M18 10v4"/>',
            'grafica'  => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
            'libro'    => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5M8 7h7"/>',
            'escudo'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'codigo'   => '<path d="m9 6-6 6 6 6M15 6l6 6-6 6"/>',
            'personas' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
            'engrane'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
            'video'    => '<rect x="2" y="5" width="15" height="14" rx="2"/><path d="m17 10 5-3v10l-5-3"/>',
            'subtitulos' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M7 13h4M13 13h4M7 16h7"/>',
            'capas'    => '<path d="M12 3 3 7.5 12 12l9-4.5L12 3Z"/><path d="m3 12 9 4.5 9-4.5M3 16.5 12 21l9-4.5"/>',
            'reloj'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        ];
    }

    function lms_svg(string $name, string $class = ''): string
    {
        $p = lms_course_icons()[$name] ?? '';
        return $p === '' ? '' : '<svg' . ($class !== '' ? ' class="' . $class . '"' : '') . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
    }

    /** Minutos de una duración escrita a mano: "9:43", "12 min", "1 h 10 min", "70min". 0 si no se entiende. */
    function lms_minutes(string $d): float
    {
        $d = mb_strtolower(trim($d));
        if ($d === '') return 0;
        if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?/', $d, $m)) return isset($m[3]) ? $m[1] * 60 + $m[2] + $m[3] / 60 : $m[1] + $m[2] / 60;
        $min = 0;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*h/', $d, $m)) $min += (float) str_replace(',', '.', $m[1]) * 60;
        if (preg_match('/(\d+)\s*min/', $d, $m)) $min += (int) $m[1];
        elseif (!$min && preg_match('/^(\d+)$/', $d, $m)) $min = (int) $m[1];
        return $min;
    }

    /** "1 h 10 min", "45 min". */
    function lms_minutes_text(float $min): string
    {
        $min = (int) round($min);
        return $min >= 60 ? intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . ($min % 60) . ' min' : '') : $min . ' min';
    }

    /** Duración de un curso: la del campo o la suma de sus lecciones. */
    function lms_course_minutes(array $c): float
    {
        $sum = 0;
        foreach (lms_lessons((string) $c['slug']) as $l) $sum += lms_minutes((string) ($l['duration'] ?? ''));
        return $sum ?: lms_minutes((string) cms_f($c, 'duration', cms_current()['lang'] ?: cms_default_lang()));
    }

    /**
     * Tarjeta de un curso, al estilo del catálogo de capacitación: tapa de color con estado, ícono, serie y nombre
     * (o la imagen del curso); debajo título, resumen, temas, para quién, avance de quien la ve y un pie con
     * lecciones, duración y el botón.
     */
    function lms_course_card(array $c, string $lang, ?array $user): string
    {
        $slug = (string) $c['slug'];
        $n = count(lms_lessons($slug));
        $st = $user ? lms_stats($slug, $user) : null;
        $title = (string) cms_f($c, 'title', $lang);
        $short = trim((string) cms_f($c, 'short', $lang)) ?: $title;
        $soon = !empty($c['soon']);
        $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($c['color'] ?? '')) ? (string) $c['color'] : '';
        $series = trim((string) cms_f($c, 'series', $lang)) ?: lms_setting_text('lms_series');
        $min = lms_course_minutes($c);
        $tag = $soon ? 'article' : 'a';
        $h = '<' . $tag . ' class="lms-c' . ($soon ? ' is-soon' : '') . '"' . ($soon ? '' : ' href="' . cms_e(cms_url('item:' . lms_course_type(), $lang, $slug)) . '"') . ($color !== '' ? ' style="--c:' . $color . '"' : '') . '>';
        // tapa
        $state = $soon ? '<span class="lms-c-state">' . cms_e(lms_tx('soon')) . '</span>'
            : ($st && $st['completed'] !== '' ? '<span class="lms-c-state is-ok">' . cms_e(lms_tx('completed')) . '</span>'
            : ($st && $st['started'] ? '<span class="lms-c-state is-on">' . cms_e(lms_tx('in_progress')) . '</span>'
            : '<span class="lms-c-state is-on">' . cms_e(lms_tx('available')) . '</span>'));
        $h .= '<span class="lms-c-tapa' . (!empty($c['image']) ? ' has-img' : '') . '">' . $state;
        if (!empty($c['image'])) $h .= '<span class="lms-c-img">' . cms_picture((string) $c['image'], $title) . '</span>';
        if (($ic = lms_svg((string) ($c['icon'] ?? ''))) !== '') $h .= '<span class="lms-c-icon">' . $ic . '</span>';
        if ($series !== '') $h .= '<span class="lms-c-series">' . cms_e($series) . '</span>';
        $h .= '<span class="lms-c-name">' . cms_e($short) . '</span></span>';
        // detalle
        $h .= '<span class="lms-c-body"><h3>' . cms_e($title) . '</h3>';
        if (($ex = (string) cms_f($c, 'excerpt', $lang)) !== '') $h .= '<p>' . cms_e($ex) . '</p>';
        if ($topics = array_filter((array) cms_f($c, 'topics', $lang, []))) $h .= '<span class="lms-c-topics">' . implode('', array_map(fn($x) => '<span>' . cms_e((string) $x) . '</span>', $topics)) . '</span>';
        if (($au = (string) cms_f($c, 'audience', $lang)) !== '') $h .= '<span class="lms-for"><strong>' . cms_e(lms_tx('for')) . '</strong> ' . cms_e($au) . '</span>';
        if (!$soon && $st && $st['started'] && $st['total'] > 0) $h .= '<span class="lms-progress">' . lms_bar($st['pct']) . '<small>' . cms_e(lms_tx('progress', $st['done'], $st['total'], $st['pct'])) . '</small></span>';
        // pie
        $meta = $soon ? '<span>' . lms_svg('reloj') . cms_e(lms_tx('in_prep')) . '</span>'
            : '<span>' . lms_svg('video') . cms_e($n === 1 ? lms_tx('lesson_1') : lms_tx('lessons_n', $n)) . '</span>' . ($min ? '<span>' . lms_svg('reloj') . cms_e(lms_minutes_text($min)) . '</span>' : '');
        $cta = $soon ? lms_tx('soon_short') : (!$st || !$st['started'] ? lms_tx('see_course') : ($st['next'] ? lms_tx('continue') : lms_tx('review')));
        $h .= '<span class="lms-c-foot"><span class="lms-c-meta">' . $meta . '</span><span class="lms-c-go">' . cms_e($cta) . ($soon ? '' : ' ' . lms_icon('arrow')) . '</span></span>';
        return $h . '</span></' . $tag . '>' . "\n";
    }

    /** Cifras del héroe: cursos disponibles, en preparación, lecciones y minutos de los disponibles. */
    function lms_catalog_numbers(array $courses): array
    {
        $ready = array_filter($courses, fn($c) => empty($c['soon']));
        $lessons = 0; $min = 0;
        foreach ($ready as $c) { $lessons += count(lms_lessons((string) $c['slug'])); $min += lms_course_minutes($c); }
        $out = [[count($ready), count($ready) === 1 ? lms_tx('n_course_1') : lms_tx('n_courses')]];
        if ($soon = count($courses) - count($ready)) $out[] = [$soon, lms_tx('n_soon')];
        $out[] = [$lessons, lms_tx('n_lessons')];
        if ($min) $out[] = [lms_minutes_text($min), lms_tx('n_minutes')];
        return $out;
    }

    /** Temario de un curso por módulos, con el estado de cada lección para quien lo ve; $current resalta una. */
    function lms_syllabus(array $course, array $lessons, array $st, string $lang, string $current = ''): string
    {
        if (!$lessons) return '<p class="form-note">—</p>';
        $canTake = lms_can_take($course);
        $h = '';
        foreach (lms_modules($lessons, $lang) as [$mod, $ls]) {
            if ($mod !== '') $h .= '<h3 class="lms-module">' . cms_e($mod) . '</h3>';
            $h .= '<ol class="lms-lessons">';
            foreach ($ls as $l) {
                $done = !empty($st['lessons'][$l['slug']]);
                $can = $canTake || !empty($l['preview']);
                $icon = $done ? 'done' : ($can ? ($l['slug'] === $current ? 'play' : 'todo') : 'lock');
                $label = '<span class="lms-l-title">' . cms_e((string) cms_f($l, 'title', $lang)) . '</span>';
                $meta = '';
                if (!$canTake && !empty($l['preview'])) $meta .= '<span class="tag tag-warn">' . cms_e(lms_tx('sample')) . '</span>';
                if (($du = (string) ($l['duration'] ?? '')) !== '') $meta .= '<small>' . cms_e($du) . '</small>';
                $cls = 'lms-l lms-l-' . $icon . ($l['slug'] === $current ? ' is-current' : '');
                $inner = lms_icon($icon) . $label . ($meta !== '' ? '<span class="lms-l-meta">' . $meta . '</span>' : '');
                $h .= '<li class="' . $cls . '">' . ($can && $l['slug'] !== $current
                    ? '<a href="' . cms_e(cms_url('item:' . lms_lesson_type(), $lang, $l['slug'])) . '">' . $inner . '</a>'
                    : '<span' . ($l['slug'] === $current ? ' aria-current="page"' : '') . '>' . $inner . '</span>') . '</li>';
            }
            $h .= '</ol>';
        }
        return $h;
    }

    /** Fecha corta legible (de "AAAA-MM-DD HH:MM"). */
    function lms_date(string $dt): string
    {
        $lang = cms_current()['lang'] ?: cms_default_lang();
        return $dt === '' ? '' : cms_date(substr($dt, 0, 10), $lang);
    }

    /** Correo de texto simple, como el formulario de contacto del núcleo. */
    function lms_mail(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $dom = preg_replace('/^www\./', '', (string) parse_url(cms_site_url(), PHP_URL_HOST)) ?: 'localhost';
        $site = (string) (cms_settings()['site_name'] ?? cms_config('name'));
        $from = '=?UTF-8?B?' . base64_encode($site) . '?= <no-reply@' . $dom . '>';
        $headers = "From: $from\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $reply = (string) (cms_settings()['email'] ?? '');
        if (filter_var($reply, FILTER_VALIDATE_EMAIL)) $headers .= "Reply-To: $reply\r\n";
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    /** El paquete (para sus rutas de archivos), por el nombre de su carpeta. */
    function lms_pack(): ?array { return cms_packs()[basename(__DIR__)] ?? null; }

    /** Plantilla del paquete (el tema puede tener una con el mismo nombre en templates/lms/). */
    function lms_view(string $name): string
    {
        $theme = cms_theme_file('templates/lms/' . $name . '.php');
        return is_file($theme) ? $theme : __DIR__ . '/templates/' . $name . '.php';
    }

    /* ================================================================== rutas públicas: /aula/… */

    function lms_route(array $seg, string $lang): ?array
    {
        if (($seg[0] ?? '') !== lms_settings()['route'] || count($seg) > 2) return null;
        $sub = (string) ($seg[1] ?? '');
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        header('Cache-Control: no-store, private');
        header('X-Robots-Tag: noindex');
        $user = lms_user();
        $err = '';
        $titles = ['' => lms_tx('my_classroom'), 'entrar' => lms_tx('login_title'), 'registro' => lms_tx('signup_title'), 'cuenta' => lms_tx('account')];

        switch ($sub) {
            case '':
                if (!$user) lms_redirect(lms_url('entrar', $lang, ['r' => lms_here()]));
                break;

            case 'entrar':
                $back = lms_back((string) ($_REQUEST['r'] ?? ''), lms_url('', $lang));
                if ($user) lms_redirect($back);
                if ($post) {
                    if (!lms_csrf_ok()) { $err = lms_tx('err_csrf'); break; }
                    if (($w = lms_throttle_wait()) > 0) { $err = lms_tx('err_blocked', (int) ceil($w / 60)); break; }
                    $u = lms_user_by_email((string) ($_POST['email'] ?? ''));
                    $pass = (string) ($_POST['password'] ?? '');
                    if ($u && !empty($u['active']) && password_verify($pass, (string) $u['hash'])) {
                        lms_throttle_record(true);
                        if (password_needs_rehash((string) $u['hash'], PASSWORD_DEFAULT)) { lms_user_update($u['id'], ['pass' => $pass]); $u = lms_user_get($u['id']) ?? $u; }
                        lms_login($u, !empty($_POST['remember']));
                        lms_redirect($back);
                    }
                    lms_throttle_record(false);
                    $err = lms_tx('err_login');
                }
                break;

            case 'registro':
                if (!lms_settings()['signup']) return null;
                if ($user) lms_redirect(lms_url('', $lang));
                if ($post) {
                    $code = lms_settings()['signup_code'];
                    $pass = (string) ($_POST['password'] ?? '');
                    if (!lms_csrf_ok()) { $err = lms_tx('err_csrf'); break; }
                    if (!empty($_POST['website'])) lms_redirect(lms_url('', $lang));   // trampa para robots
                    if ($code !== '' && !hash_equals(mb_strtolower($code), mb_strtolower(trim((string) ($_POST['code'] ?? ''))))) { $err = lms_tx('err_code'); break; }
                    if ($pass !== (string) ($_POST['password2'] ?? '')) { $err = lms_tx('err_repeat'); break; }
                    if (($w = lms_throttle_wait()) > 0) { $err = lms_tx('err_blocked', (int) ceil($w / 60)); break; }
                    [$ok, $r] = lms_user_create((string) ($_POST['name'] ?? ''), (string) ($_POST['email'] ?? ''), $pass, 'registro propio');
                    if (!$ok) { if ($r === 'err_exists') lms_throttle_record(false); $err = lms_tx($r); break; }
                    $u = lms_user_get($r);
                    if (lms_settings()['notify_to'] !== '') lms_mail(lms_settings()['notify_to'], 'Alumno nuevo en el aula: ' . $u['name'], "Se registró un alumno nuevo:\n\n" . $u['name'] . "\n" . $u['email'] . "\n\nPanel: " . cms_origin() . CMS_BASE . '/admin/?p=pack:' . basename(__DIR__) . '&id=' . $u['id'] . "\n");
                    lms_login($u, false);
                    lms_redirect(lms_url('', $lang, ['ok' => 'welcome']));
                }
                break;

            case 'cuenta':
                if (!$user) lms_redirect(lms_url('entrar', $lang, ['r' => lms_here()]));
                if ($post) {
                    if (!lms_csrf_ok()) { $err = lms_tx('err_csrf'); break; }
                    $name = trim((string) ($_POST['name'] ?? ''));
                    $new = (string) ($_POST['new'] ?? '');
                    if ($name === '') { $err = lms_tx('err_name'); break; }
                    $ch = ['name' => mb_substr($name, 0, 120)];
                    if ($new !== '') {
                        if (!password_verify((string) ($_POST['current'] ?? ''), (string) $user['hash'])) { $err = lms_tx('err_current'); break; }
                        if (strlen($new) < 8) { $err = lms_tx('err_pass'); break; }
                        if ($new !== (string) ($_POST['repeat'] ?? '')) { $err = lms_tx('err_repeat'); break; }
                        $ch['pass'] = $new;
                    }
                    if (!lms_user_update($user['id'], $ch)) { $err = lms_tx('err_save'); break; }
                    if (isset($ch['pass'])) lms_login(lms_user_get($user['id']), lms_remembered());   // la firma cambia con la contraseña
                    lms_redirect(lms_url('cuenta', $lang, ['ok' => 'saved']));
                }
                break;

            case 'salir':
                if ($post && lms_csrf_ok()) lms_logout();
                lms_redirect(CMS_BASE . cms_lang_prefix($lang) . '/');
                break;

            case 'video':     // ?l=<lección>: el video protegido, a quien puede ver la lección
            case 'archivo':   // ?l=<lección>&n=<n>: un material protegido
                lms_staff();
                $lesson = cms_type(lms_lesson_type()) ? cms_item(lms_lesson_type(), cms_slugify((string) ($_GET['l'] ?? ''))) : null;
                $course = $lesson ? lms_course(lms_lesson_course($lesson)) : null;
                if (!$lesson && lms_staff() && cms_type(lms_lesson_type())) { $lesson = cms_item(lms_lesson_type(), cms_slugify((string) ($_GET['l'] ?? '')), false); $course = $lesson ? lms_course(lms_lesson_course($lesson), false) : null; }
                $path = '';
                if ($lesson) $path = $sub === 'video' ? (string) ($lesson['video'] ?? '') : (string) (lms_file_list($lesson)[(int) ($_GET['n'] ?? -1)][1] ?? '');
                $file = $path !== '' ? lms_local_file($path) : null;
                if (!$file || !$course || !lms_can_view($course, $lesson)) { http_response_code($file ? 403 : 404); header('Content-Type: text/plain; charset=utf-8'); echo $file ? 'Sin acceso.' : 'No encontrado.'; exit; }
                lms_send_file($file);
                break;

            case 'avance':   // POST: marcar o desmarcar una lección; vuelve a la lección o pasa a la siguiente
                if (!$post) lms_redirect(lms_url('', $lang));
                $slug = cms_slugify((string) ($_POST['lesson'] ?? ''));
                $lesson = $slug !== '' && cms_type(lms_lesson_type()) ? cms_item(lms_lesson_type(), $slug) : null;
                $course = $lesson ? lms_course(lms_lesson_course($lesson)) : null;
                $back = $lesson ? cms_url('item:' . lms_lesson_type(), $lang, $lesson['slug']) : lms_url('', $lang);
                if (!$user) lms_redirect(lms_url('entrar', $lang, ['r' => $back]));
                if (!lms_csrf_ok() || !$lesson || !$course || !lms_can_view($course, $lesson)) lms_redirect($back);
                $done = ($_POST['done'] ?? '1') === '1';
                lms_mark($user['id'], (string) $course['slug'], (string) $lesson['slug'], $done);
                $st = lms_stats((string) $course['slug'], $user);
                if ($done && $st['total'] > 0 && $st['done'] >= $st['total']) lms_redirect(cms_url('item:' . lms_course_type(), $lang, $course['slug']) . '?ok=completed');
                $to = lms_back((string) ($_POST['next'] ?? ''), '');
                lms_redirect($done && $to !== '' ? $to : $back);
                break;

            default:
                return null;
        }

        lms_staff(); lms_csrf();   // antes de que el tema empiece a imprimir: leen o ponen cookies
        $view = ['' => 'aula', 'entrar' => 'entrar', 'registro' => 'registro', 'cuenta' => 'cuenta'][$sub] ?? 'aula';
        $GLOBALS['lms_error'] = $err;
        $site = (string) (cms_settings()['site_name'] ?? cms_config('name'));
        return ['file' => lms_view($view), 'page' => ['title' => ($titles[$sub] ?? lms_tx('my_classroom')) . ' · ' . $site, 'desc' => '', 'noindex' => true, 'lms' => true, 'route' => 'lms:' . ($sub ?: 'aula')]];
    }

    /** Aviso de la página: ?ok=… tras una redirección, o el error del formulario. */
    function lms_notice(): string
    {
        $err = (string) ($GLOBALS['lms_error'] ?? '');
        if ($err !== '') return '<p class="form-msg err lms-msg" role="alert">' . cms_e($err) . '</p>';
        $ok = ['saved' => 'ok_saved', 'welcome' => 'ok_welcome', 'completed' => 'ok_completed'][(string) ($_GET['ok'] ?? '')] ?? '';
        return $ok !== '' ? '<p class="form-msg ok lms-msg" role="status">' . cms_e(lms_tx($ok)) . '</p>' : '';
    }

    /* ================================================================== ganchos */

    cms_on('route', fn($r, array $seg, string $lang) => $r ?? lms_route($seg, $lang));

    // al guardar una lección, su video y sus materiales de carpeta propia quedan sin acceso directo
    cms_on('item.save', function (string $type, array $item) {
        if ($type !== lms_lesson_type() || !lms_settings()['protect']) return;
        lms_protect_dir((string) ($item['video'] ?? ''));
        foreach (lms_file_list($item) as [, $path]) lms_protect_dir($path);
    });

    // plantillas de cursos y lecciones cuando el tema no trae las suyas
    cms_on('template', function ($file, string $template, string $type, string $route) {
        $ours = $type !== '' && ($type === lms_course_type() || $type === lms_lesson_type());
        if ($ours) {   // la página muestra el avance de quien la ve: que no la guarde ninguna caché compartida
            header('Cache-Control: private, no-cache');
            lms_staff(); lms_csrf();
        }
        if (is_file((string) $file) || !$ours) return $file;
        $single = strpos($route, 'item:') === 0;
        if ($type === lms_course_type()) return lms_view($single ? 'curso' : 'cursos');
        if ($type === lms_lesson_type() && $single) return lms_view('leccion');
        return $file;
    });

    // estilos del aula solo en sus páginas; también evita que una página con avance personal se guarde en caché
    cms_on('head', function (array $page) {
        $type = (string) (cms_current()['type'] ?? '');
        if (empty($page['lms']) && $type !== lms_course_type() && $type !== lms_lesson_type()) return;
        $p = lms_pack();
        if ($p) echo '<link rel="stylesheet" href="' . cms_e(cms_pack_asset($p, 'assets/lms.css')) . '">' . "\n";
    });
}
