<?php
/** Funciones del administrador de medios (carpeta uploads/). */
declare(strict_types=1);

const CMS_MEDIA_TYPES = [
    // mime => [extensión, tipo]
    'image/jpeg'      => ['jpg', 'image'],
    'image/png'       => ['png', 'image'],
    'image/webp'      => ['webp', 'image'],
    'image/gif'       => ['gif', 'image'],
    'application/pdf' => ['pdf', 'pdf'],
    'video/mp4'       => ['mp4', 'video'],
    'video/webm'      => ['webm', 'video'],
    'video/quicktime' => ['mov', 'video'],
];
const CMS_MEDIA_MAX = 200 * 1024 * 1024; // tope propio; el hosting puede tener uno menor

/** Límite real de subida según php.ini (bytes). */
function media_limit_bytes(): int
{
    $toBytes = function (string $v): int {
        $v = trim($v);
        if ($v === '' || $v === '-1' || $v === '0') return PHP_INT_MAX;
        $n = (float) $v;
        switch (strtolower(substr($v, -1))) {
            case 'g': $n *= 1024;
            case 'm': $n *= 1024;
            case 'k': $n *= 1024;
        }
        return (int) $n;
    };
    return (int) min(CMS_MEDIA_MAX, $toBytes((string) ini_get('upload_max_filesize')), $toBytes((string) ini_get('post_max_size')));
}

function media_human(int $b): string
{
    if ($b >= 1048576) return number_format($b / 1048576, 1) . ' MB';
    if ($b >= 1024) return number_format($b / 1024, 0) . ' KB';
    return $b . ' B';
}

function media_type_of(string $file): string
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) return 'image';
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, ['mp4', 'webm', 'mov'], true)) return 'video';
    return 'other';
}

/** Lista todos los archivos de uploads/ (más recientes primero). */
function media_list(): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CMS_UPLOADS, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if (!$f->isFile() || $f->getFilename()[0] === '.' || $f->getFilename() === 'index.html') continue;
        if (strtolower($f->getExtension()) === 'webp' && is_file(preg_replace('/\.webp$/i', '.jpg', $f->getPathname())) || strtolower($f->getExtension()) === 'webp' && is_file(preg_replace('/\.webp$/i', '.png', $f->getPathname()))) continue;
        $rel = 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen(CMS_UPLOADS) + 1));
        $out[] = ['path' => $rel, 'url' => CMS_BASE . '/' . $rel, 'name' => $f->getFilename(), 'size' => $f->getSize(), 'mtime' => $f->getMTime(), 'type' => media_type_of($rel)];
    }
    usort($out, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $out;
}

/** Texto de todo el contenido (para saber si un archivo está en uso). */
function media_content_blob(): string
{
    static $blob = null;
    if ($blob !== null) return $blob;
    $blob = '';
    foreach (glob(CMS_DATA . '/*.json') ?: [] as $f) $blob .= file_get_contents($f);
    foreach (glob(CMS_DATA . '/*/*.json') ?: [] as $f) $blob .= file_get_contents($f);
    return $blob;
}

function media_in_use(string $path): bool
{
    return strpos(media_content_blob(), $path) !== false;
}

/** Ruta absoluta segura dentro de uploads/ o null. */
function media_safe_path(string $rel): ?string
{
    if (strpos($rel, 'uploads/') !== 0 || strpos($rel, '..') !== false) return null;
    $abs = realpath(CMS_ROOT . '/' . $rel);
    $root = realpath(CMS_UPLOADS);
    if (!$abs || !$root || strpos($abs, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($abs)) return null;
    return $abs;
}

/**
 * Guarda un archivo subido ($_FILES[...]) en uploads/AAAA/MM. Devuelve [ok, path|error].
 */
function media_store(array $f): array
{
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
        $msgs = [UPLOAD_ERR_INI_SIZE => 'El archivo supera el límite del servidor (' . media_human(media_limit_bytes()) . ').', UPLOAD_ERR_FORM_SIZE => 'Archivo demasiado grande.', UPLOAD_ERR_PARTIAL => 'Subida incompleta.', UPLOAD_ERR_NO_FILE => 'No se recibió el archivo.'];
        return [false, $msgs[$f['error'] ?? 0] ?? 'Error al subir (código ' . ($f['error'] ?? '?') . ').'];
    }
    if (($f['size'] ?? 0) > media_limit_bytes()) return [false, 'Máximo ' . media_human(media_limit_bytes()) . '.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(CMS_MEDIA_TYPES[$mime])) return [false, 'Tipo no permitido (' . $mime . '). Se aceptan JPG, PNG, WEBP, GIF, PDF, MP4, WEBM y MOV.'];
    [$ext, $type] = CMS_MEDIA_TYPES[$mime];
    if ($type === 'image' && !@getimagesize($f['tmp_name'])) return [false, 'La imagen no es válida.'];

    $sub = date('Y/m');
    $dir = CMS_UPLOADS . '/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) return [false, 'No se pudo crear uploads/' . $sub . '. Revisa permisos.'];
    $base = cms_slugify(pathinfo((string) $f['name'], PATHINFO_FILENAME)) ?: $type;
    $name = $base . '.' . $ext;
    if (file_exists($dir . '/' . $name)) $name = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 5) . '.' . $ext;
    $dest = $dir . '/' . $name;

    $done = false;
    if ($type === 'image' && $ext !== 'gif' && function_exists('imagecreatefromstring')) {
        [$w, $h] = getimagesize($f['tmp_name']);
        $maxw = (int) cms_config('max_image_width', 1800); if ($maxw > 0 && $w > $maxw) {
            $src = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
            if ($src) {
                $nw = $maxw; $nh = (int) round($h * $maxw / $w);
                $dst = imagecreatetruecolor($nw, $nh);
                if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                $done = $ext === 'jpg' ? imagejpeg($dst, $dest, 84) : ($ext === 'png' ? imagepng($dst, $dest, 7) : imagewebp($dst, $dest, 84));
                imagedestroy($dst); imagedestroy($src);
            }
        }
    }
    if (!$done && !move_uploaded_file($f['tmp_name'], $dest)) return [false, 'No se pudo guardar. Revisa permisos de uploads/.'];
    @chmod($dest, 0644);
    if ($type === 'image') cms_webp_make($dest); // versión WebP para el sitio
    return [true, 'uploads/' . $sub . '/' . $name];
}
