<?php
/**
 * Prueba rápida (solo lectura) del video y la portada por idioma de las lecciones del aula (paquete lms 1.12):
 *   php tools/lms-i18n-smoke.php
 * No escribe nada: arma lecciones en memoria y comprueba lms_lesson_media(), lms_lesson_media_all(),
 * lms_has_video() y lms_imp_i18n_put() (el importador). Sale con 0 si todo pasa.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REQUEST_URI'] = '/';
require_once __DIR__ . '/../cms/bootstrap.php';
if (!function_exists('lms_lesson_media')) require_once CMS_DIR . '/packs/lms/inc.php';
if (!function_exists('lms_imp_i18n_put')) require_once CMS_DIR . '/packs/lms/import.php';

$def = cms_default_lang();
$other = array_values(array_diff(cms_langs(), [$def]))[0] ?? 'en';
$fail = 0; $n = 0;
$ok = function (string $what, $got, $want) use (&$fail, &$n): void {
    $n++;
    if ($got === $want) { echo "ok   $what\n"; return; }
    $fail++;
    echo "FAIL $what: " . var_export($got, true) . ' (esperaba ' . var_export($want, true) . ")\n";
};

// 1) valor de antes: una sola ruta para todos los idiomas
$scalar = ['slug' => 'l1', 'video' => 'cap/videos/01.mp4', 'poster' => 'cap/portadas/01.jpg'];
$ok('escalar, idioma principal', lms_lesson_media($scalar, 'video', $def), 'cap/videos/01.mp4');
$ok('escalar, otro idioma (el mismo)', lms_lesson_media($scalar, 'video', $other), 'cap/videos/01.mp4');
$ok('escalar, portada', lms_lesson_media($scalar, 'poster', $other), 'cap/portadas/01.jpg');
$ok('escalar, todos', lms_lesson_media_all($scalar, 'video'), ['cap/videos/01.mp4']);

// 2) bilingüe con los dos idiomas
$i18n = ['slug' => 'l2', 'video' => [$def => 'cap/videos/01.mp4', $other => 'cap/' . $other . '/videos/01.mp4'], 'poster' => [$def => 'cap/portadas/01.jpg', $other => '']];
$ok('bilingüe, principal', lms_lesson_media($i18n, 'video', $def), 'cap/videos/01.mp4');
$ok('bilingüe, otro idioma', lms_lesson_media($i18n, 'video', $other), 'cap/' . $other . '/videos/01.mp4');
$ok('bilingüe, portada vacía → la principal', lms_lesson_media($i18n, 'poster', $other), 'cap/portadas/01.jpg');
$ok('bilingüe, todos', lms_lesson_media_all($i18n, 'video'), ['cap/videos/01.mp4', 'cap/' . $other . '/videos/01.mp4']);

// 3) bilingüe sin el otro idioma (vacío o ausente) → respaldo al principal
$missing = ['slug' => 'l3', 'video' => [$def => 'https://youtu.be/abcdefghijk']];
$ok('sin el otro idioma → principal', lms_lesson_media($missing, 'video', $other), 'https://youtu.be/abcdefghijk');
$ok('lms_has_video con respaldo', lms_has_video($missing, $other), true);
$emptyOther = ['slug' => 'l4', 'video' => [$def => 'cap/videos/02.mp4', $other => '']];
$ok('otro idioma vacío → principal', lms_lesson_media($emptyOther, 'video', $other), 'cap/videos/02.mp4');

// 4) ya resuelto al idioma de la página (cms_localize en el sitio público) y casos vacíos
$ok('ya resuelto', lms_lesson_media(cms_localize($i18n, $other), 'video', $other), 'cap/' . $other . '/videos/01.mp4');
$ok('sin video', lms_lesson_media(['slug' => 'l5'], 'video', $other), '');
$ok('sin video: has_video', lms_has_video(['slug' => 'l5'], $other), false);
$ok('valor raro (lista) no truena', lms_lesson_media(['video' => ['a', 'b']], 'video', $other), '');
$ok('sin idioma = el que se dibuja (el principal en CLI)', lms_lesson_media($i18n, 'video'), 'cap/videos/01.mp4');

// 5) el importador pone el otro idioma sin tocar el principal
$ok('put sobre escalar', lms_imp_i18n_put('cap/videos/01.mp4', $other, 'x.mp4', false), [$def => 'cap/videos/01.mp4', $other => 'x.mp4']);
$ok('put sobre vacío', lms_imp_i18n_put('', $other, 'x.mp4', false), [$def => '', $other => 'x.mp4']);
$ok('put sin reemplazar lo que ya hay', lms_imp_i18n_put([$def => 'a', $other => 'b'], $other, 'c', false), [$def => 'a', $other => 'b']);
$ok('put reemplazando', lms_imp_i18n_put([$def => 'a', $other => 'b'], $other, 'c', true), [$def => 'a', $other => 'c']);
$ok('put con valor vacío no cambia', lms_imp_i18n_put([$def => 'a'], $other, '', true), [$def => 'a']);
$ok('put de una lista (temas)', lms_imp_i18n_put([$def => ['uno']], $other, ['one'], false), [$def => ['uno'], $other => ['one']]);

echo "\n" . ($n - $fail) . "/$n" . ($fail ? " — $fail fallaron\n" : " bien\n");
exit($fail ? 1 : 0);
