<?php
/** Bloques del paquete "visual". Las vistas usan clases vis-* y la cabecera estándar del núcleo (cms_block_header). */
return [
    'galeria3d' => [
        'label' => 'Galería 3D', 'desc' => 'Imágenes flotando en un espacio 3D que se recorre con el scroll (three.js, se carga solo al llegar).',
        'wrap_class' => 'cms-sec', 'styles' => ['bg', 'text', 'pad', 'anchor', 'class', 'hide_mobile'],
        'assets' => ['js' => ['assets/galeria3d.js']],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo'],
            'hint'     => ['type' => 'text', 'label' => 'Texto de ayuda', 'default' => 'Usa el scroll para explorar'],
            'images'   => ['type' => 'images', 'label' => 'Imágenes (una por línea)', 'rows' => 6, 'required' => true],
            'height'   => ['type' => 'select', 'label' => 'Altura', 'options' => ['normal' => 'Normal (80 % de la pantalla)', 'short' => 'Baja (60 %)', 'tall' => 'Alta (100 %)'], 'default' => 'normal'],
        ],
    ],
    'carrusel' => [
        'label' => 'Carrusel', 'desc' => 'Imágenes en un carrusel deslizable con flechas y puntos (Swiper).',
        'wrap_class' => 'cms-sec', 'libs' => ['swiper'], 'assets' => ['js' => ['assets/carrusel.js']],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo'],
            'images'   => ['type' => 'images', 'label' => 'Imágenes (una por línea; opcional "ruta | pie de foto")', 'rows' => 6, 'required' => true],
            'per_view' => ['type' => 'select', 'label' => 'Imágenes visibles a la vez', 'options' => ['1' => '1', '2' => '2', '3' => '3'], 'default' => '1'],
            'autoplay' => ['type' => 'checkbox', 'label' => 'Avance', 'text' => 'Avanzar solo cada 5 segundos', 'default' => true],
        ],
    ],
    'lightbox' => [
        'label' => 'Galería con lightbox', 'desc' => 'Rejilla de imágenes que se abren a pantalla completa (GLightbox).',
        'wrap_class' => 'cms-sec', 'libs' => ['glightbox'], 'assets' => ['js' => ['assets/lightbox.js']],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo'],
            'images'   => ['type' => 'images', 'label' => 'Imágenes (una por línea; opcional "ruta | pie de foto")', 'rows' => 6, 'required' => true],
            'columns'  => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
        ],
    ],
];
