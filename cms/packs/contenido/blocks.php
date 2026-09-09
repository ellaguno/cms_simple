<?php
/**
 * Bloques del paquete "contenido". El listado de colección lee los elementos publicados del tipo elegido, así que la
 * página se actualiza sola al publicar. Las opciones del selector de colección se generan desde site/config.php.
 */
declare(strict_types=1);
$F = 'cms/assets/img/demo/';
$types = [];
foreach ((array) cms_config('types', []) as $k => $d) $types[$k] = (string) ($d['label'] ?? $k);
if (!$types) $types = ['' => 'No hay colecciones en este sitio'];

return [
    'coleccion' => [
        'label' => 'Listado de una colección', 'group' => 'Contenido dinámico',
        'desc' => 'Los últimos elementos de una colección (artículos, proyectos, productos) en rejilla, lista o con uno destacado. Se actualiza solo al publicar.',
        'wrap_class' => 'cms-sec',
        'fields' => [
            'title'     => ['type' => 'text', 'label' => 'Título (opcional)'],
            'subtitle'  => ['type' => 'text', 'label' => 'Subtítulo (opcional)'],
            'source'    => ['type' => 'select', 'label' => 'Colección', 'options' => $types, 'default' => (string) array_key_first($types)],
            'count'     => ['type' => 'number', 'label' => 'Cuántos mostrar', 'default' => 3, 'min' => 1, 'max' => 24, 'half' => true],
            'layout'    => ['type' => 'select', 'label' => 'Disposición', 'options' => ['grid3' => 'Rejilla de 3', 'grid2' => 'Rejilla de 2', 'grid4' => 'Rejilla de 4', 'list' => 'Lista con imagen a un lado', 'featured' => 'Uno destacado y el resto en lista'], 'default' => 'grid3', 'half' => true],
            'filter'    => ['type' => 'text', 'label' => 'Mostrar solo los de esta categoría o etiqueta (opcional)', 'placeholder' => 'Diseño'],
            'order'     => ['type' => 'select', 'label' => 'Orden', 'options' => ['default' => 'El de la colección', 'reverse' => 'Al revés', 'random' => 'Al azar'], 'default' => 'default', 'half' => true],
            'ratio'     => ['type' => 'select', 'label' => 'Forma de la imagen', 'options' => ['16-9' => 'Apaisada (16:9)', '4-3' => 'Clásica (4:3)', '1-1' => 'Cuadrada', 'none' => 'Sin imagen'], 'default' => '16-9', 'half' => true],
            'show_date' => ['type' => 'checkbox', 'label' => 'Mostrar', 'text' => 'La fecha', 'default' => true],
            'show_cat'  => ['type' => 'checkbox', 'label' => ' ', 'text' => 'La categoría', 'default' => true],
            'show_text' => ['type' => 'checkbox', 'label' => ' ', 'text' => 'El resumen', 'default' => true],
            'more_text' => ['type' => 'text', 'label' => 'Botón al índice de la colección (opcional)', 'placeholder' => 'Ver todo', 'half' => true],
            'empty'     => ['type' => 'text', 'label' => 'Texto si no hay elementos', 'default' => '', 'half' => true],
        ],
        'sample' => ['title' => 'Últimas publicaciones', 'subtitle' => 'Lo que hemos escrito', 'more_text' => 'Ver todo'],
    ],
    'catalogo' => [
        'label' => 'Catálogo de tarjetas', 'group' => 'Contenido dinámico',
        'desc' => 'Rejilla de tarjetas con imagen, etiqueta, título, texto y enlace. Para productos, servicios o proyectos escritos a mano.',
        'wrap_class' => 'cms-sec',
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título (opcional)'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo (opcional)'],
            'items'    => ['type' => 'lines', 'label' => 'Tarjetas, una por línea: Título | Texto | imagen | etiqueta o precio | Texto del enlace > URL', 'rows' => 6, 'required' => true],
            'columns'  => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3', 'half' => true],
            'ratio'    => ['type' => 'select', 'label' => 'Forma de la imagen', 'options' => ['4-3' => 'Clásica (4:3)', '16-9' => 'Apaisada (16:9)', '1-1' => 'Cuadrada', 'none' => 'Sin imagen'], 'default' => '4-3', 'half' => true],
        ],
        'sample' => ['title' => 'Lo que hacemos', 'subtitle' => 'Tres servicios, sin letra pequeña', 'items' => [
            'Identidad de marca | Naming, logotipo y manual de uso para que todo hable igual. | ' . $F . 'foto-1.jpg | Desde $35,000 | Ver el proceso > #',
            'Sitio web | Diseño, contenido y puesta en marcha con panel para editarlo tú. | ' . $F . 'foto-2.jpg | Desde $48,000 | Ver un ejemplo > #',
            'Reporte anual | Diseño editorial y maquetación, impreso y digital. | ' . $F . 'foto-3.jpg | A cotizar | Pedir presupuesto > #']],
    ],
    'aviso' => [
        'label' => 'Banda de aviso', 'group' => 'Contenido dinámico',
        'desc' => 'Una franja estrecha arriba de la página con un anuncio, un enlace y una equis para cerrarla. Quien la cierra no la vuelve a ver.',
        'wrap_class' => 'cms-band', 'styles' => ['bg', 'text', 'anchor', 'class', 'hide_mobile'], 'animate' => 'none',
        'fields' => [
            'text'        => ['type' => 'text', 'label' => 'Texto del aviso', 'required' => true],
            'icon'        => ['type' => 'text', 'label' => 'Emoji o icono al principio (opcional)', 'default' => '🎉', 'half' => true],
            'link_text'   => ['type' => 'text', 'label' => 'Texto del enlace (opcional)', 'half' => true],
            'link_url'    => ['type' => 'text', 'label' => 'URL del enlace', 'half' => true],
            'closable'    => ['type' => 'checkbox', 'label' => 'Cerrar', 'text' => 'Se puede cerrar y no vuelve a aparecer', 'default' => true],
            'key'         => ['type' => 'text', 'label' => 'Nombre del aviso (cámbialo al publicar uno nuevo para que se vuelva a ver)', 'default' => 'aviso-1', 'half' => true],
        ],
        'sample' => ['text' => 'Taller gratuito de diseño editorial el 24 de octubre.', 'link_text' => 'Apuntarme', 'link_url' => '#'],
    ],
    'acordeon' => [
        'label' => 'Acordeón', 'group' => 'Contenido dinámico',
        'desc' => 'Apartados que se abren al hacer clic, con texto largo dentro. Para condiciones, temarios o secciones densas.',
        'wrap_class' => 'cms-sec',
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título (opcional)'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo (opcional)'],
            'items'    => ['type' => 'lines', 'label' => 'Apartados, uno por línea: Título | Texto (admite <strong>, <em>, <br> y enlaces)', 'rows' => 6, 'required' => true],
            'first'    => ['type' => 'checkbox', 'label' => 'Al cargar', 'text' => 'Abrir el primero', 'default' => true],
            'single'   => ['type' => 'checkbox', 'label' => 'Comportamiento', 'text' => 'Al abrir uno, cerrar los demás', 'default' => true],
        ],
        'sample' => ['title' => 'Condiciones del servicio', 'items' => [
            'Qué incluye | Diseño, desarrollo, contenido inicial y una sesión de formación de dos horas. El dominio y el alojamiento del primer año también entran.',
            'Plazos | Entre cuatro y seis semanas desde la aprobación de la propuesta, siempre que el contenido llegue a tiempo.',
            'Revisiones | Dos rondas de cambios en diseño y una en contenido. A partir de ahí se cotiza por hora.',
            'Después de la entrega | Un mes de soporte incluido. Luego puedes contratar mantenimiento o llevarlo tú desde el panel.']],
    ],
    'pasos' => [
        'label' => 'Pasos numerados', 'group' => 'Contenido dinámico',
        'desc' => 'Una fila de pasos unidos por una línea, con número, título y texto. Para explicar cómo funciona algo en tres o cuatro movimientos.',
        'wrap_class' => 'cms-sec',
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título (opcional)'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo (opcional)'],
            'items'    => ['type' => 'lines', 'label' => 'Pasos, uno por línea: Título | Texto | icono (opcional; emoji o ti-…)', 'rows' => 5, 'required' => true],
            'layout'   => ['type' => 'select', 'label' => 'Disposición', 'options' => ['row' => 'En fila', 'column' => 'En columna'], 'default' => 'row'],
        ],
        'sample' => ['title' => 'Cómo empezamos', 'subtitle' => 'Tres pasos y ya estás dentro', 'items' => [
            'Cuéntanos | Una llamada de 30 minutos para entender qué necesitas.',
            'Te proponemos | Alcance, plazo y precio cerrado en 48 horas.',
            'Empezamos | Firmamos y arrancamos el lunes siguiente.']],
    ],
];
