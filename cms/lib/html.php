<?php
/** cms_simple — texto, fechas, Markdown/HTML, imágenes (WebP, <picture>). */
declare(strict_types=1);

function cms_md(string $text): string
{
    static $pd = null;
    if ($pd === null) { $pd = new Parsedown(); $pd->setSafeMode(false); $pd->setBreaksEnabled(true); }
    return $pd->text(str_replace('{{base}}', CMS_BASE, $text));
}

/** Contenido del editor visual (HTML) o Markdown heredado. */
function cms_content(string $text): string
{
    $text = str_replace('{{base}}', CMS_BASE, $text);
    if ($text === '') return '';
    return preg_match('/^\s*</', $text) ? $text : cms_md($text);
}

function cms_date(string $ymd, string $lang): string
{
    $ts = strtotime($ymd);
    if (!$ts) return cms_e($ymd);
    if ($lang === 'en') return date('F j, Y', $ts);
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function cms_date_short(string $ymd, string $lang): array
{
    $ts = strtotime($ymd) ?: time();
    $es = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return [$lang === 'en' ? date('M', $ts) : $es[(int) date('n', $ts) - 1], date('j', $ts)];
}

/** Genera la versión .webp de una imagen local (JPG/PNG) si falta o está desactualizada. */
function cms_webp_make(string $file): ?string
{
    if (!is_file($file) || !function_exists('imagewebp')) return null;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) return null;
    $webp = preg_replace('/\.[^.]+$/', '.webp', $file);
    if (is_file($webp) && filemtime($webp) >= filemtime($file)) return $webp;
    if (!is_writable(dirname($file))) return null;
    $src = @imagecreatefromstring((string) file_get_contents($file));
    if (!$src) return null;
    if ($ext === 'png') { imagepalettetotruecolor($src); imagealphablending($src, true); imagesavealpha($src, true); }
    $ok = @imagewebp($src, $webp, 82);
    imagedestroy($src);
    return $ok ? $webp : null;
}

function cms_img_size(string $path): array
{
    static $cache = [];
    if (isset($cache[$path])) return $cache[$path];
    $f = cms_local_path($path);
    $sz = $f && is_file($f) ? @getimagesize($f) : false;
    return $cache[$path] = $sz ? [(int) $sz[0], (int) $sz[1]] : [0, 0];
}

/** <picture> con WebP si existe, width/height, lazy y decoding async. */
function cms_picture(string $path, string $alt = '', string $attrs = '', bool $eager = false): string
{
    if ($path === '') return '';
    $src = cms_img($path);
    [$w, $h] = cms_img_size($path);
    $dim = $w && $h ? ' width="' . $w . '" height="' . $h . '"' : '';
    $load = $eager ? ' fetchpriority="high"' : ' loading="lazy"';
    $img = '<img src="' . cms_e($src) . '" alt="' . cms_e($alt) . '"' . $dim . $load . ' decoding="async" ' . $attrs . '>';
    $local = cms_local_path($path);
    if ($local && preg_match('/\.(jpe?g|png)$/i', $local) && is_file(preg_replace('/\.[^.]+$/', '.webp', $local))) {
        return '<picture><source type="image/webp" srcset="' . cms_e(preg_replace('/\.[^.]+$/', '.webp', $src)) . '">' . $img . '</picture>';
    }
    return $img;
}

/** Botón genérico (el tema puede definir el suyo). */
function cms_btn(string $text, string $href, string $class = 'btn', string $attrs = ''): string
{
    return '<a href="' . cms_e($href) . '" class="' . cms_e($class) . '" ' . $attrs . '>' . cms_e($text) . '</a>';
}

/**
 * Aclara ($amount > 0, hacia blanco) u oscurece ($amount < 0, hacia negro) un color hex; $amount entre -1 y 1.
 * Sirve a los temas para derivar tonos de un color elegido en Ajustes (cms_color_shade('#4f46e5', -0.15)).
 */
function cms_color_shade(string $hex, float $amount): string
{
    if (!preg_match('/^#?([0-9a-f]{6})$/i', $hex, $m)) return $hex;
    $rgb = array_map('hexdec', str_split($m[1], 2));
    $amount = max(-1, min(1, $amount));
    foreach ($rgb as &$c) $c = (int) round($amount >= 0 ? $c + (255 - $c) * $amount : $c * (1 + $amount));
    return sprintf('#%02x%02x%02x', ...$rgb);
}

/** Enlace a Google Fonts para una o varias familias (nombres exactos), con los pesos habituales. */
function cms_google_fonts_url(array $families, string $weights = '400;500;600;700;800'): string
{
    $families = array_values(array_unique(array_filter(array_map('trim', $families))));
    if (!$families) return '';
    return 'https://fonts.googleapis.com/css2?' . implode('&', array_map(fn($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@' . $weights, $families)) . '&display=swap';
}
