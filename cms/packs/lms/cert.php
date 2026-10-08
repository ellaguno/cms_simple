<?php
/**
 * Paquete lms — constancias de terminación. Lo carga inc.php.
 *
 * Al llegar un alumno al 100 % de un curso (lecciones y evaluaciones), lms_course_recheck() le emite una constancia:
 * un código único (ABCD-2345) guardado en su avance (courses.<curso>.cert = ['code', 'date']) y en el registro
 * data/lms/certs.json (código => alumno, curso, fecha y los nombres de ese momento), que sirve para verificarla en
 * /aula/verificar sin entrar. La constancia se ve e imprime (o se guarda como PDF desde el navegador) en
 * /aula/constancia?c=<curso>: una página propia, tamaño carta u A4 apaisado, con el diseño de Ajustes → Aula.
 * Si después se borra el avance del alumno o al alumno, la verificación la da por no válida.
 */
declare(strict_types=1);

function lms_cert_file(): string { return lms_dir() . '/certs.json'; }

/** ¿El curso da constancia? Campo del curso "Constancia" (sí / no / según Ajustes) y Ajustes → Aula. */
function lms_cert_on(array $course): bool
{
    $v = (string) ($course['certificate'] ?? '');
    if ($v === 'si') return true;
    if ($v === 'no') return false;
    $S = cms_settings();
    return !isset($S['lms_cert']) || !empty($S['lms_cert']);
}

function lms_cert_code(): string
{
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $reg = (array) cms_json_read(lms_cert_file(), []);
    do {
        $s = '';
        for ($i = 0; $i < 8; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
        $s = substr($s, 0, 4) . '-' . substr($s, 4);
    } while (isset($reg[$s]));
    return $s;
}

/** Emite la constancia de un curso terminado (si da constancia y aún no la tiene). Modifica $p; devuelve el código o ''. */
function lms_cert_issue(string $uid, string $course, array &$p): string
{
    $c = (array) ($p['courses'][$course] ?? []);
    if (!empty($c['cert']['code']) || empty($c['completed'])) return (string) ($c['cert']['code'] ?? '');
    $co = lms_course($course, false);
    $u = lms_user_get($uid);
    if (!$co || !$u || !lms_cert_on($co)) return '';
    $code = lms_cert_code();
    $date = (string) $c['completed'];
    $reg = (array) cms_json_read(lms_cert_file(), []);
    $reg[$code] = ['uid' => $uid, 'course' => $course, 'date' => $date, 'name' => (string) $u['name'], 'title' => (string) cms_f($co, 'title', cms_default_lang())];
    if (!cms_json_write(lms_cert_file(), $reg)) return '';
    $p['courses'][$course]['cert'] = ['code' => $code, 'date' => $date];
    return $code;
}

/** Constancia de un alumno en un curso: ['code', 'date'] o null. */
function lms_cert_get(string $uid, string $course): ?array
{
    $c = (array) (lms_progress($uid)['courses'][$course]['cert'] ?? []);
    return !empty($c['code']) ? $c : null;
}

/** Busca un código: ['entry' => registro, 'valid' => bool] o null si no existe. */
function lms_cert_lookup(string $code): ?array
{
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if (strlen($code) !== 8) return null;
    $code = substr($code, 0, 4) . '-' . substr($code, 4);
    $e = ((array) cms_json_read(lms_cert_file(), []))[$code] ?? null;
    if (!is_array($e)) return null;
    $own = lms_user_get((string) $e['uid']) ? lms_cert_get((string) $e['uid'], (string) $e['course']) : null;
    return ['code' => $code, 'entry' => $e, 'valid' => $own !== null && $own['code'] === $code];
}

function lms_cert_url(string $course, ?string $lang = null, string $uid = ''): string
{
    return lms_url('constancia', $lang, ['c' => $course] + ($uid !== '' ? ['u' => $uid] : []));
}

function lms_cert_verify_url(string $code, ?string $lang = null): string
{
    return lms_url('verificar', $lang, ['c' => $code]);
}

/** Correo al alumno con el enlace a su constancia (al terminar el curso). */
function lms_cert_mail(string $uid, string $course): bool
{
    $u = lms_user_get($uid);
    $co = lms_course($course, false);
    $cert = lms_cert_get($uid, $course);
    if (!$u || !$co || !$cert) return false;
    $lang = cms_default_lang();
    $site = (string) (cms_settings()['site_name'] ?? cms_config('name'));
    $title = (string) cms_f($co, 'title', $lang);
    return lms_mail((string) $u['email'], "Tu constancia: $title", "Hola, " . $u['name'] . ".\n\n¡Felicidades! Terminaste el curso «" . $title . "» en el aula de $site.\n\n"
        . "Tu constancia (puedes imprimirla o guardarla como PDF):\n" . cms_origin() . lms_cert_url($course, $lang) . "\n\n"
        . "Código de verificación: " . $cert['code'] . "\nCualquiera puede comprobarla en: " . cms_origin() . lms_cert_verify_url($cert['code'], $lang) . "\n");
}

/** Texto de Ajustes → Aula (por idioma) para la constancia. */
function lms_cert_setting(string $key, string $lang): string
{
    $v = cms_settings()[$key] ?? '';
    if (is_array($v)) $v = (string) ($v[$lang] ?? '') !== '' ? $v[$lang] : ($v[cms_default_lang()] ?? '');
    return trim((string) $v);
}

/** Dibuja la constancia como página completa (sin la cabecera del tema) y termina. */
function lms_cert_render(array $user, array $course, array $cert, string $lang): void
{
    $S = cms_settings();
    $site = (string) ($S['site_name'] ?? cms_config('name'));
    $ctitle = (string) cms_f($course, 'title', $lang);
    $heading = lms_cert_setting('lms_cert_title', $lang) ?: lms_tx('cert_title');
    $text = lms_cert_setting('lms_cert_text', $lang) ?: lms_tx('cert_text');
    $signer = trim((string) ($S['lms_cert_signer'] ?? ''));
    $role = lms_cert_setting('lms_cert_signer_role', $lang);
    $sig = trim((string) ($S['lms_cert_signature'] ?? ''));
    $logo = trim((string) ($S['lms_cert_logo'] ?? '')) ?: trim((string) ($S['logo'] ?? ''));
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string) ($S['lms_cert_color'] ?? '')) ? (string) $S['lms_cert_color'] : '#1f3a5f';
    $min = lms_course_minutes($course);
    $dur = trim((string) cms_f($course, 'duration', $lang)) ?: ($min ? lms_minutes_text($min) : '');
    $grades = [];
    foreach (lms_quizzes((string) $course['slug']) as $q) { $e = (array) (lms_progress($user['id'])['courses'][$course['slug']]['exams'][$q['slug']] ?? []); if (isset($e['best']) && lms_quiz_cfg($q)['pass'] > 0) $grades[] = (int) $e['best']; }
    $avg = $grades ? (int) round(array_sum($grades) / count($grades)) : null;
    $verify = cms_origin() . lms_cert_verify_url($cert['code'], $lang);
    $img = fn(string $p) => cms_e(preg_match('#^(https?:)?//#i', $p) ? $p : lms_media_url($p));
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    ?><!doctype html>
<html lang="<?= cms_e($lang) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= cms_e($heading . ' · ' . $ctitle . ' · ' . $user['name']) ?></title>
<style>
@page{size:letter landscape;margin:0}
*{box-sizing:border-box}
html,body{margin:0;background:#e9ecef;font-family:Georgia,"Times New Roman",serif;color:#1d2433}
.bar{display:flex;gap:10px;justify-content:center;align-items:center;flex-wrap:wrap;padding:14px;font:14px/1.4 system-ui,sans-serif}
.bar button,.bar a{font:inherit;padding:9px 16px;border-radius:8px;border:1px solid #c3c9d1;background:#fff;color:#1d2433;cursor:pointer;text-decoration:none}
.bar button{background:<?= $color ?>;border-color:<?= $color ?>;color:#fff}
.sheet{width:11in;height:8.5in;margin:0 auto 30px;background:#fff;position:relative;box-shadow:0 10px 40px rgba(0,0,0,.15);overflow:hidden}
.frame{position:absolute;inset:.35in;border:3px solid <?= $color ?>;padding:.18in}
.frame>div{height:100%;border:1px solid <?= $color ?>;display:flex;flex-direction:column;align-items:center;text-align:center;padding:.4in .8in .35in}
.main{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%}
.logo{max-height:.85in;max-width:3in;margin-bottom:.25in}
.site{font:600 12px/1 system-ui,sans-serif;letter-spacing:.3em;text-transform:uppercase;color:<?= $color ?>;margin-bottom:.2in}
h1{margin:0;font-weight:400;font-size:42px;letter-spacing:.02em;color:<?= $color ?>}
.to{margin:.3in 0 .08in;font-size:16px;font-style:italic;color:#5b6576}
.name{font-size:40px;line-height:1.15;padding:0 .3in .08in;border-bottom:1px solid #c9ced6;min-width:60%}
.text{margin:.22in 0 .06in;font-size:16px;font-style:italic;color:#5b6576}
.course{font-size:26px;font-weight:700;max-width:8in}
.meta{margin-top:.16in;font:14px/1.5 system-ui,sans-serif;color:#5b6576}
.foot{display:flex;justify-content:space-between;align-items:flex-end;width:100%;padding-top:.2in;font:12px/1.45 system-ui,sans-serif;color:#5b6576}
.sign{text-align:center;min-width:2.6in}
.sign img{max-height:.7in;max-width:2.4in;display:block;margin:0 auto -6px}
.sign .line{border-top:1px solid #1d2433;padding-top:5px;color:#1d2433;font-size:13px}
.code{text-align:left}
.code b{font:600 15px/1.3 ui-monospace,Menlo,monospace;color:#1d2433;letter-spacing:.06em}
@media print{html,body{background:#fff}.bar{display:none}.sheet{margin:0;box-shadow:none;width:100vw;height:100vh}}
@media screen and (max-width:1100px){.sheet{transform-origin:top left;transform:scale(calc((100vw - 20px) / 11in));margin:0 10px calc(8.5in * ((100vw - 20px) / 11in) - 8.5in)}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()"><?= cms_e(lms_tx('cert_print')) ?></button><a href="<?= cms_e(cms_url('item:' . lms_course_type(), $lang, $course['slug'])) ?>"><?= cms_e(lms_tx('back_course')) ?></a></div>
<div class="sheet"><div class="frame"><div>
 <div class="main">
<?php if ($logo !== ''): ?>  <img class="logo" src="<?= $img($logo) ?>" alt="<?= cms_e($site) ?>">
<?php else: ?>  <div class="site"><?= cms_e($site) ?></div>
<?php endif; ?>
  <h1><?= cms_e($heading) ?></h1>
  <p class="to"><?= cms_e(lms_tx('cert_to')) ?></p>
  <div class="name"><?= cms_e((string) $user['name']) ?></div>
  <p class="text"><?= cms_e($text) ?></p>
  <div class="course"><?= cms_e($ctitle) ?></div>
  <p class="meta"><?= cms_e(lms_tx('cert_date', cms_date(substr((string) $cert['date'], 0, 10), $lang))) ?><?= $dur !== '' ? ' · ' . cms_e(lms_tx('cert_duration', $dur)) : '' ?><?= $avg !== null ? ' · ' . cms_e(lms_tx('cert_grade', $avg)) : '' ?></p>
 </div>
  <div class="foot">
    <div class="code"><?= cms_e(lms_tx('cert_code')) ?><br><b><?= cms_e((string) $cert['code']) ?></b><br><?= cms_e($verify) ?></div>
<?php if ($signer !== '' || $sig !== ''): ?>
    <div class="sign"><?php if ($sig !== ''): ?><img src="<?= $img($sig) ?>" alt=""><?php endif; ?><div class="line"><?= cms_e($signer) ?><?php if ($role !== ''): ?><br><span style="color:#5b6576;font-size:12px"><?= cms_e($role) ?></span><?php endif; ?></div></div>
<?php endif; ?>
  </div>
</div></div></div>
</body>
</html>
<?php
    exit;
}
