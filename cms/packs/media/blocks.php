<?php
/** Bloques del paquete media. */
declare(strict_types=1);
return [
    'video' => [
        'label' => 'Video', 'group' => 'Media', 'desc' => 'Video de YouTube o Vimeo con título; se carga al hacer clic sobre la portada.',
        'wrap_class' => 'cms-sec',
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título (opcional)'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo (opcional)'],
            'url'      => ['type' => 'text', 'label' => 'URL del video (YouTube o Vimeo)', 'required' => true, 'placeholder' => 'https://www.youtube.com/watch?v=…'],
            'poster'   => ['type' => 'image', 'label' => 'Portada (opcional; si no, la miniatura de YouTube)'],
        ],
    ],
    'mapa' => [
        'label' => 'Mapa', 'group' => 'Media', 'desc' => 'Mapa de OpenStreetMap con un marcador, sin clave de API.',
        'wrap_class' => 'cms-sec', 'libs' => ['leaflet'],
        'fields' => [
            'title'   => ['type' => 'text', 'label' => 'Título (opcional)'],
            'lat'     => ['type' => 'text', 'label' => 'Latitud', 'required' => true, 'placeholder' => '19.4326', 'half' => true],
            'lng'     => ['type' => 'text', 'label' => 'Longitud', 'required' => true, 'placeholder' => '-99.1332', 'half' => true],
            'label'   => ['type' => 'text', 'label' => 'Texto del marcador (nombre, dirección)'],
            'zoom'    => ['type' => 'number', 'label' => 'Zoom (10 ciudad … 17 calle)', 'default' => 15, 'min' => 3, 'max' => 19],
            'height'  => ['type' => 'select', 'label' => 'Altura', 'options' => ['s' => 'Baja', 'm' => 'Media', 'l' => 'Alta'], 'default' => 'm'],
        ],
    ],
    'lottie' => [
        'label' => 'Animación Lottie', 'group' => 'Media', 'desc' => 'Animación vectorial (archivo .json de LottieFiles) con texto opcional al lado.',
        'wrap_class' => 'cms-sec', 'libs' => ['lottie'],
        'fields' => [
            'file'  => ['type' => 'text', 'label' => 'Archivo .json (ruta en uploads/ o URL)', 'required' => true],
            'title' => ['type' => 'text', 'label' => 'Título (opcional)'],
            'text'  => ['type' => 'textarea', 'label' => 'Texto (opcional)', 'rows' => 3],
            'side'  => ['type' => 'select', 'label' => 'Animación a la', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda', 'center' => 'Sola, centrada'], 'default' => 'right'],
            'loop'  => ['type' => 'checkbox', 'label' => 'Repetir', 'text' => 'En bucle', 'default' => true],
        ],
    ],
];
