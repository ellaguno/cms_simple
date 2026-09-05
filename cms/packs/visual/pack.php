<?php
/** Paquete "visual": galería 3D, carrusel, galería con lightbox; efectos: fondo shader, tarjetas con luz, degradado que sigue al cursor. */
return [
    'label' => 'Visual',
    'version' => '1.0.0',
    'desc' => 'Galería 3D con three.js, carrusel con Swiper, galería con lightbox y efectos: fondo animado (shader WebGL), luz que sigue al cursor en tarjetas y degradado animado.',
    'assets' => ['css' => ['assets/visual.css']],
    'effects' => [
        'shader'    => ['label' => 'Fondo animado (ondas de luz, WebGL)', 'desc' => 'Un lienzo con ondas de luz detrás del contenido de la sección.', 'assets' => ['js' => ['assets/effects.js', 'assets/shader.js']]],
        'spotlight' => ['label' => 'Tarjetas con luz que sigue al cursor', 'desc' => 'Las tarjetas de la sección iluminan su borde y fondo según la posición del ratón.', 'assets' => ['js' => ['assets/effects.js']]],
        'gradient'  => ['label' => 'Degradado animado que sigue al cursor', 'desc' => 'Manchas de color en movimiento detrás del contenido; una sigue al ratón.', 'assets' => ['js' => ['assets/effects.js']]],
    ],
];
