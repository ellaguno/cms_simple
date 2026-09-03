<?php
/** Subida de archivos desde el admin (imágenes, PDF, video). Responde JSON {ok, url, path, type}. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (!admin_is_post()) { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'method']); exit; }
admin_csrf_check();
$f = $_FILES['file'] ?? ($_FILES['image'] ?? null);
if (!$f) { http_response_code(413); echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo. Puede que supere el límite del servidor (' . media_human(media_limit_bytes()) . ').']); exit; }
[$ok, $res] = media_store($f);
if (!$ok) { http_response_code(400); echo json_encode(['ok' => false, 'error' => $res]); exit; }
echo json_encode(['ok' => true, 'path' => $res, 'url' => CMS_BASE . '/' . $res, 'type' => media_type_of($res)]);
exit;
