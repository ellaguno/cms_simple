<?php
/**
 * Bloques del paquete "agencia": portada con pase de imágenes, banda con círculo, equipo en carrusel y barras de
 * habilidades. Vienen del tema de alldesign.mx, reescritos con clases neutras ag-* y variables --cms-* para que se
 * vean bien en cualquier tema (claro u oscuro). El selector de colección del equipo se llena desde site/config.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc.php';
$F = 'cms/assets/img/demo/';
$types = ['manual' => 'Personas escritas aquí'];
foreach ((array) cms_config('types', []) as $k => $d) $types[$k] = 'Colección: ' . (string) ($d['label'] ?? $k);

return [
    'portada' => [
        'label' => 'Portada con pase de imágenes', 'group' => 'Cabeceras',
        'desc' => 'Una o varias imágenes a todo el ancho que pasan solas (flechas, puntos y gesto de arrastre), con título opcional encima y una pestaña curva con el botón de compartir. Opción parallax: la imagen se queda fija y la página pasa por encima. La primera banda de una página.',
        'wrap_class' => 'ag-hero', 'styles' => ['bg', 'anchor', 'class', 'hide_mobile'], 'animate' => 'none',
        'sample' => ['images' => [$F . 'foto-1.jpg | Primera imagen', $F . 'foto-2.jpg | Segunda imagen', $F . 'foto-3.jpg | Tercera imagen'], 'height' => 'medium', 'title' => 'Diseñamos marcas que se recuerdan', 'subtitle' => 'Identidad, editorial y web desde 1992'],
        'fields' => [
            'images'   => ['type' => 'images', 'label' => 'Imágenes, una por línea (con varias se hace pase de diapositivas): ruta | texto alternativo', 'rows' => 4, 'required' => true],
            'height'   => ['type' => 'select', 'label' => 'Altura', 'options' => ['full' => 'Toda la pantalla', 'tall' => 'Alta', 'medium' => 'Media', 'short' => 'Baja'], 'default' => 'medium', 'half' => true],
            'interval' => ['type' => 'number', 'label' => 'Segundos entre imágenes', 'default' => 5, 'min' => 2, 'max' => 30, 'half' => true],
            'title'    => ['type' => 'text', 'label' => 'Título sobre la imagen (opcional)', 'half' => true],
            'subtitle' => ['type' => 'text', 'label' => 'Texto bajo el título (opcional)', 'half' => true],
            'position' => ['type' => 'select', 'label' => 'Encuadre de la imagen', 'options' => ['center' => 'Centro', 'top' => 'Arriba', 'bottom' => 'Abajo'], 'default' => 'center', 'half' => true],
            'overlay'  => ['type' => 'select', 'label' => 'Oscurecer la imagen bajo el título', 'options' => ['0' => 'No', '25' => 'Un poco', '50' => 'Bastante'], 'default' => '25', 'half' => true],
            'share'    => ['type' => 'checkbox', 'label' => 'Pestaña', 'text' => 'Mostrar la pestaña curva con el botón de compartir', 'default' => true, 'half' => true],
            'fixed'    => ['type' => 'checkbox', 'label' => 'Parallax', 'text' => 'La imagen se queda fija y la página pasa por encima', 'default' => false, 'half' => true],
        ],
    ],
    'banda' => [
        'label' => 'Banda con círculo', 'group' => 'Cabeceras',
        'desc' => 'Imagen ancha con un círculo del color de acento y una frase corta en el centro. Sirve de separador o de llamado a la acción.',
        'wrap_class' => 'ag-band', 'styles' => ['anchor', 'class', 'hide_mobile', 'accent'], 'animate' => 'none',
        'sample' => ['image' => $F . 'foto-3.jpg', 'text' => 'LE DAMOS VALOR A TU MARCA', 'url' => '#'],
        'fields' => [
            'image'  => ['type' => 'image', 'label' => 'Imagen de fondo', 'required' => true],
            'text'   => ['type' => 'text', 'label' => 'Frase del círculo', 'required' => true],
            'url'    => ['type' => 'text', 'label' => 'Enlace del círculo (opcional)'],
            'height' => ['type' => 'select', 'label' => 'Altura', 'options' => ['s' => 'Baja', 'm' => 'Media', 'l' => 'Alta', 'xl' => 'Muy alta'], 'default' => 'm', 'half' => true],
            'size'   => ['type' => 'select', 'label' => 'Tamaño del círculo', 'options' => ['s' => 'Pequeño', 'm' => 'Mediano', 'l' => 'Grande'], 'default' => 'm', 'half' => true],
            'color'  => ['type' => 'color', 'label' => 'Color del círculo', 'placeholder' => 'vacío = el de acento', 'half' => true],
            'text_color' => ['type' => 'select', 'label' => 'Texto del círculo', 'options' => ['light' => 'Claro', 'dark' => 'Oscuro'], 'default' => 'light', 'half' => true],
            'opacity' => ['type' => 'select', 'label' => 'Transparencia del círculo', 'options' => ['1' => 'Sólido', '0.8' => 'Un poco transparente', '0.6' => 'Bastante transparente'], 'default' => '1', 'half' => true],
            'fixed'  => ['type' => 'checkbox', 'label' => 'Parallax', 'text' => 'La imagen se queda fija y la página pasa por encima', 'default' => false, 'half' => true],
        ],
    ],
    'equipo' => [
        'label' => 'Equipo (carrusel)', 'group' => 'Contenido dinámico',
        'desc' => 'Una persona a la vez: foto a la izquierda, nombre, semblanza, puesto y redes; flechas para pasar. Lee una colección (nombre, resumen como puesto, imagen y campos de URL como redes) o personas escritas a mano.',
        'wrap_class' => 'cms-sec ag-team-sec',
        'sample' => ['source' => 'manual', 'grayscale' => true],
        'fields' => [
            'title'     => ['type' => 'text', 'label' => 'Título (opcional)'],
            'source'    => ['type' => 'select', 'label' => 'Origen', 'options' => $types, 'default' => 'manual'],
            'items'     => ['type' => 'lines', 'label' => 'Personas, una por línea: Nombre | Puesto | foto | Texto | URL de una red | URL de otra red', 'rows' => 5, 'show_if' => ['source' => 'manual']],
            'count'     => ['type' => 'number', 'label' => 'Cuántas personas (0 = todas)', 'default' => 0, 'min' => 0, 'max' => 30, 'half' => true],
            'interval'  => ['type' => 'number', 'label' => 'Segundos para pasar solo (0 = no)', 'default' => 0, 'min' => 0, 'max' => 60, 'half' => true],
            'grayscale' => ['type' => 'checkbox', 'label' => 'Fotos', 'text' => 'En blanco y negro', 'default' => true, 'half' => true],
            'shape'     => ['type' => 'select', 'label' => 'Forma de la foto', 'options' => ['square' => 'Cuadrada', 'circle' => 'Círculo', 'portrait' => 'Vertical'], 'default' => 'square', 'half' => true],
        ],
    ],
    'habilidades' => [
        'label' => 'Barras de habilidades', 'group' => 'Contenido',
        'desc' => 'Texto a un lado y a la derecha barras de porcentaje que se llenan al aparecer en pantalla. Sin texto, las barras ocupan todo el ancho.',
        'wrap_class' => 'cms-sec ag-skills-sec',
        'sample' => ['title' => 'Nuestra experiencia', 'body' => '<p>Somos un grupo multidisciplinario con una trayectoria larga y con mucho entusiasmo para seguirnos actualizando.</p>', 'label' => 'NUESTRAS HABILIDADES', 'items' => ['Planeación | 95', 'Procesamiento de información | 90 | #f0c060', 'Trabajo en equipo | 98', 'Pensamiento metódico | 100 | #e04545']],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título del texto (opcional)'],
            'body'     => ['type' => 'html', 'label' => 'Texto (opcional)'],
            'label'    => ['type' => 'text', 'label' => 'Rótulo de las barras (opcional)', 'half' => true],
            'show_pct' => ['type' => 'checkbox', 'label' => 'Porcentaje', 'text' => 'Mostrar la cifra en cada barra', 'default' => false, 'half' => true],
            'items'    => ['type' => 'lines', 'label' => 'Barras, una por línea: Nombre | porcentaje | color (opcional; vacío = el de acento)', 'rows' => 7, 'required' => true],
            'side'     => ['type' => 'select', 'label' => 'Barras a la', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda'], 'default' => 'right', 'half' => true],
            'thickness' => ['type' => 'select', 'label' => 'Grosor', 'options' => ['thin' => 'Fino', 'normal' => 'Normal', 'thick' => 'Grueso'], 'default' => 'normal', 'half' => true],
        ],
    ],
];
