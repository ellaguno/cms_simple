<?php
/**
 * Genera páginas de demostración (una por bloque y por efecto) para capturar sus vistas previas.
 *
 *   php tools/make-previews.php list      → crea data/content/paginas/_demo-*.json e imprime: clave \t ruta \t animado \t archivo destino
 *   php tools/make-previews.php clean     → borra las páginas de demostración
 *
 * Después, tools/make-previews.sh captura cada página con Chrome sin interfaz (?cmsbare=1) y arma PNG o GIF.
 * Las vistas previas quedan en site/assets/previews/<clave>.* (bloques del tema) y en
 * cms/packs/<paquete>/assets/previews/<bloque>.* y efecto-<efecto>.* (paquetes).
 */
declare(strict_types=1);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$root = getenv('CMS_ROOT') ?: dirname(__DIR__);   // CMS_ROOT=/ruta/a/otro/sitio para generar las de otro sitio (katapolt)
require $root . '/cms/bootstrap.php';

$cmd = $argv[1] ?? 'list';
$dir = cms_content_dir('paginas');
if ($cmd === 'clean') { foreach (glob($dir . '/_demo-*.json') ?: [] as $f) unlink($f); cms_items_flush(); echo "limpio\n"; exit; }

$shots = ['screenshot-1.png', 'screenshot-2.png', 'screenshot-3.png', 'screenshot-4.png', 'screenshot-5.png', 'screenshot-6.png', 'screenshot-7.png'];
/** Datos de ejemplo por bloque (se completan con los valores por defecto de la definición). */
$samples = [
    'hero' => ['title' => 'Un título <span class="gradient-text">que promete</span>', 'subtitle' => 'Un subtítulo breve que explica para quién es y qué gana.', 'badges' => ['🔒 | Seguro', '⚡ | Rápido', '🤖 | Con IA'], 'shader' => true],
    'insignias' => ['title' => 'Nuestros estándares'],
    'texto' => ['body' => '<h2>Un subtítulo</h2><p>Texto libre con el editor visual: <strong>negritas</strong>, <em>cursivas</em>, enlaces, listas e imágenes.</p><ul><li>Primer punto</li><li>Segundo punto</li></ul>'],
    'columnas' => ['title' => 'Texto a un lado, imagen al otro', 'body' => '<p>Explica una idea con apoyo visual. La imagen puede ir a la derecha o a la izquierda.</p>', 'image' => 'screenshot-2.png', 'button_text' => 'Saber más', 'button_url' => '#'],
    'imagen' => ['image' => 'screenshot-1.png', 'caption' => 'Un pie de imagen opcional'],
    'video' => ['url' => 'https://www.youtube.com/watch?v=SAdlFVYjVI0', 'subtitle' => 'Un video de YouTube o Vimeo'],
    'html' => ['code' => '<div style="padding:2rem;border:2px dashed #999;border-radius:12px;text-align:center">Tu HTML tal cual</div>'],
    'separador' => ['size' => 'm', 'line' => true],
    'tarjetas' => ['title' => 'Todo lo que <span class="gradient-text">necesitas</span>', 'subtitle' => 'Cuatro ventajas en tarjetas', 'items' => ['Documentos | Organiza y analiza con IA. | doc', 'Casos | Todo en un solo lugar. | users', 'Asistente | Pregunta en lenguaje natural. | bot', 'Calendario | Nunca pierdas un plazo. | calendar']],
    'comparacion' => ['title' => 'Antes y después', 'before_items' => ['Documentos dispersos', 'Plazos que se olvidan', 'Horas buscando'], 'after_items' => ['Todo centralizado', 'Alertas automáticas', 'Respuestas en segundos']],
    'tabla' => ['title' => 'Comparativa', 'rows' => ['Creación del plan | Manual | Conversacional', 'IA integrada | no | si', 'Soporte | Correo | Chat y correo']],
    'testimonio' => ['quote' => 'En tres semanas teníamos el plan de la obra nueva listo. Antes eran meses.', 'cite' => 'Directora de proyectos, constructora'],
    'planes' => ['product' => 'derecho'],
    'faq' => ['section' => 'precios'],
    'equipo' => [],
    'articulos' => ['count' => 3],
    'hijas' => ['title' => 'Páginas hijas'],
    'cta' => ['title' => '¿Listo para empezar?', 'text' => 'Agenda una demostración o empieza tu prueba gratuita.', 'form' => true, 'gradient' => true],
    'visual/galeria3d' => ['title' => 'Conoce la <span class="gradient-text">plataforma</span>', 'images' => $shots],
    'visual/carrusel' => ['title' => 'Carrusel', 'images' => ['screenshot-1.png | Uno', 'screenshot-2.png | Dos', 'screenshot-3.png | Tres'], 'per_view' => '2', 'autoplay' => true],
    'visual/lightbox' => ['title' => 'Galería', 'images' => ['screenshot-4.png', 'screenshot-5.png', 'screenshot-6.png', 'screenshot-7.png', 'screenshot-1.png', 'screenshot-2.png'], 'columns' => '3'],
    'motion/marquesina' => ['items' => ['Branding', 'Diseño web', 'Estrategia', 'Contenido'], 'outline' => true, 'size' => 'l'],
    'motion/cifras' => ['title' => 'En números', 'items' => ['120 | Proyectos entregados | +', '15 | Años de experiencia', '98 | Clientes satisfechos | %']],
    'motion/titular' => ['text' => 'Diseñamos marcas que <em>se mueven</em>', 'sub' => 'Estrategia, identidad y web para empresas que quieren crecer.', 'button_text' => 'Hablemos', 'button_url' => '#', 'reveal' => true],
    'motion/parallax' => ['title' => 'Trabajo reciente', 'images' => ['screenshot-1.png | Panel', 'screenshot-2.png | Casos', 'screenshot-3.png | Documentos', 'screenshot-4.png', 'screenshot-5.png', 'screenshot-6.png'], 'columns' => '3'],
    // katapolt (bloques kt-*, bilingües)
    'kt-hero' => ['title_grey' => 'Agencia de branding', 'title_rest' => 'y diseño editorial en México. Impulsa marcas que llegan lejos.', 'categories' => ['Branding', 'Reportes Anuales', 'Identidad corporativa', 'Diseño Editorial']],
    'kt-nosotros' => ['circle' => 'CREATIVIDAD AVANZADA EN DISEÑO DESDE 2012 •', 'statement' => 'Para ser competitivo en los negocios, se debe contar con una marca poderosa.'],
    'kt-servicios' => ['items' => ['Reportes Anuales ESG', 'Branding Corporativo', 'Branding de Productos', 'Diseño de Revistas']],
    'kt-clientes' => ['image' => 'clientes-katapolt-branding.png', 'alt' => 'Clientes'],
    'kt-marquesina' => ['circle' => 'GESTIONANDO Y DESARROLLANDO MARCAS PODEROSAS •', 'text' => 'Creatividad avanzada'],
    'kt-galeria' => ['col1' => ['branding-de-mezcal.jpg | / | Mezcal', 'diseno-de-informe-anual.jpg | / | Informe'], 'col2' => ['branding-inmobiliario.jpg | / | Inmobiliario', 'branding-corporativo.jpg | / | Corporativo'], 'col3' => ['branding-de-producto.jpg | / | Producto', 'diseno-de-reporte-anual-de-sostenibilidad.jpg | / | Sostenibilidad']],
    'kt-texto' => ['title' => 'Un título', 'body' => '<p>Texto libre con el editor visual.</p>'],
];
/** Los que se mueven: se capturan en GIF. */
$animated = ['hero', 'cta', 'visual/galeria3d', 'visual/carrusel', 'motion/marquesina', 'motion/cifras', 'motion/titular', 'motion/parallax', 'kt-hero', 'kt-marquesina', 'kt-nosotros', 'kt-galeria'];
/** Demos de efectos: [bloque base, datos extra, estilo]. */
$effectDemos = [
    'visual/shader' => ['hero', ['shader' => true], []],
    'visual/spotlight' => ['tarjetas', [], []],
    'visual/gradient' => ['cta', ['gradient' => true], []],
    'motion/reveal' => ['motion/titular', ['reveal' => true], []],
    'motion/stagger' => ['tarjetas', [], ['effect' => 'motion/stagger']],
    'motion/parallax' => ['texto', ['body' => '<h2 style="color:#fff">Parallax de fondo</h2><p style="color:#fff">La imagen de fondo se mueve más despacio que el scroll.</p>'], ['effect' => 'motion/parallax', 'bg_image' => 'screenshot-3.png', 'overlay' => 50, 'pad' => 'xl']],
];

function demo_data(array $def, array $extra): array
{
    $data = [];
    foreach ((array) ($def['fields'] ?? []) as $k => $fd) {
        $t = $fd['type'] ?? 'text';
        $i18n = fn($v) => !empty($fd['i18n']) && !is_array($v) ? array_fill_keys(cms_langs(), $v) : (!empty($fd['i18n']) && is_array($v) && !isset($v['es']) ? array_fill_keys(cms_langs(), $v) : $v);
        if (array_key_exists($k, $extra)) { $data[$k] = $i18n($extra[$k]); continue; }
        if (array_key_exists('default', $fd)) { $data[$k] = $i18n($fd['default']); continue; }
        $data[$k] = $i18n(match (true) {
            $t === 'image' => 'screenshot-1.png',
            $t === 'images' => ['screenshot-1.png', 'screenshot-2.png', 'screenshot-3.png'],
            $t === 'lines' || $t === 'tags' => [],
            $t === 'html' => '<p>Texto de ejemplo.</p>',
            $t === 'checkbox' => false,
            $t === 'number' => 3,
            $t === 'select' => (string) array_key_first((array) ($fd['options'] ?? [''])),
            $k === 'title' => 'Título de ejemplo',
            $k === 'subtitle' => 'Un subtítulo de apoyo',
            default => '',
        });
    }
    return $data;
}
function demo_page(string $slug, string $type, array $data, array $style): void
{
    $item = ['slug' => $slug, 'status' => 'published', 'title' => 'Demo ' . $type, 'brand' => 'derecho', 'parent' => '', 'path' => $slug, 'order' => 999, 'created' => date('Y-m-d'), 'updated' => date('Y-m-d'),
        'sections' => [['id' => 'demo01', 'type' => $type, 'data' => $data, 'style' => $style, 'hidden' => false]]];
    cms_json_write(cms_content_dir('paginas') . '/' . $slug . '.json', $item);
}
function preview_target(string $key, bool $effect = false): string
{
    if (strpos($key, '/') !== false) { [$pack, $name] = explode('/', $key, 2); return CMS_DIR . '/packs/' . $pack . '/assets/previews/' . ($effect ? 'efecto-' : '') . $name; }
    return CMS_SITE . '/assets/previews/' . $key;
}
// con --solo-tema se generan únicamente los bloques del tema (los de paquetes ya existen en el núcleo compartido)
$onlyTheme = in_array('--solo-tema', $argv, true);
$only = null; foreach ($argv as $a) if (strpos($a, '--solo=') === 0) $only = substr($a, 7);   // --solo=clave: una sola vista previa

foreach (cms_blocks() as $key => $def) {
    if ($onlyTheme && isset($def['pack'])) continue;
    if ($only !== null && $only !== $key) continue;
    $slug = '_demo-' . str_replace('/', '-', $key);
    demo_page($slug, $key, demo_data($def, $samples[$key] ?? []), []);
    if ($key === 'hijas') foreach (['Servicio uno' => 'screenshot-2.png', 'Servicio dos' => 'screenshot-4.png', 'Servicio tres' => 'screenshot-6.png'] as $ttl => $img) {
        $cs = $slug . '-' . cms_slugify($ttl);
        cms_json_write(cms_content_dir('paginas') . '/' . $cs . '.json', ['slug' => $cs, 'status' => 'published', 'title' => $ttl, 'summary' => 'Una página hija de ejemplo.', 'image' => $img, 'brand' => 'derecho', 'parent' => $slug, 'path' => $slug . '/' . $cs, 'order' => 1, 'created' => date('Y-m-d'), 'updated' => date('Y-m-d'), 'sections' => []]);
    }
    echo $key, "\t/", $slug, "\t", in_array($key, $animated, true) ? 1 : 0, "\t", preview_target($key), "\n";
}
foreach (cms_effects() as $key => $def) {
    if ($onlyTheme || !isset($effectDemos[$key]) || ($only !== null && $only !== $key)) continue;
    [$base, $extra, $style] = $effectDemos[$key];
    $bd = cms_block($base); if (!$bd) continue;
    $slug = '_demo-efecto-' . str_replace('/', '-', $key);
    demo_page($slug, $base, demo_data($bd, ($samples[$base] ?? []) + $extra), $style);
    echo $key, "\t/", $slug, "\t1\t", preview_target($key, true), "\n";
}
cms_items_flush();
