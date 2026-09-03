<?php
/**
 * cms_simple — receptor genérico del formulario de contacto (POST a /_cms/form).
 * Envía por mail() al correo de Ajustes (form_to o email) y registra en data/mensajes.log.
 * Campos obligatorios y nombres se configuran en site/config.php → 'form'.
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$cfg = cms_config('form');
$S = cms_settings();
$destino = $S['form_to'] ?: ($S['email'] ?? '');
$dominio = preg_replace('/^www\./', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'method']); exit; }
if (!empty($_POST[$cfg['honeypot'] ?? 'empresa_web2'])) { echo json_encode(['ok' => true]); exit; }

$clean = fn($v) => is_array($v) ? implode(', ', array_map(fn($x) => trim(strip_tags((string) $x)), $v)) : trim(strip_tags((string) $v));
$fields = [];
foreach ($_POST as $k => $v) {
    if (in_array($k, ['_csrf', $cfg['honeypot'] ?? 'empresa_web2'], true)) continue;
    $fields[$k] = mb_substr($clean($v), 0, 5000);
}
$emailField = $cfg['email_field'] ?? 'correo';
$nameField = $cfg['name_field'] ?? 'nombre';
foreach ((array) ($cfg['required'] ?? []) as $r) {
    if (($fields[$r] ?? '') === '') { http_response_code(422); echo json_encode(['ok' => false, 'error' => 'validacion', 'field' => $r]); exit; }
}
if (isset($fields[$emailField]) && !filter_var($fields[$emailField], FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['ok' => false, 'error' => 'validacion', 'field' => $emailField]); exit; }
if ($destino === '') { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'sin-destino']); exit; }

$nombre = $fields[$nameField] ?? 'Visitante';
$correo = $fields[$emailField] ?? '';
$cuerpo = "Nuevo mensaje desde $dominio\n\n";
foreach ($fields as $k => $v) if ($v !== '') $cuerpo .= ucfirst(str_replace(['_', '[]'], [' ', ''], $k)) . ": $v\n";
$cuerpo .= "\n—\nIP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\nFecha: " . date('Y-m-d H:i');

$asunto = '=?UTF-8?B?' . base64_encode("Mensaje de $nombre — $dominio") . '?=';
$headers = "From: $dominio <no-reply@$dominio>\r\n";
if ($correo) $headers .= 'Reply-To: ' . str_replace(["\r", "\n"], '', $nombre) . " <$correo>\r\n";
$headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";

@file_put_contents(CMS_DATA . '/mensajes.log', date('c') . "\t" . str_replace(["\r", "\n", "\t"], ' ', json_encode($fields, JSON_UNESCAPED_UNICODE)) . "\n", FILE_APPEND | LOCK_EX);

if (@mail($destino, $asunto, $cuerpo, $headers)) echo json_encode(['ok' => true]);
else { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'mail']); }
