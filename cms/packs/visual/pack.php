<?php
/** Paquete "visual": galería 3D, carrusel, galería con lightbox; efectos: fondo shader, tarjetas con luz, degradado que sigue al cursor. */
return [
    'label' => 'Visual',
    'version' => '1.0.0',
    'desc' => 'Galería 3D con three.js, carrusel con Swiper, galería con lightbox y efectos: fondo animado (shader WebGL), luz que sigue al cursor en tarjetas y degradado animado.',
    'assets' => ['css' => ['assets/visual.css']],
    'effects' => [
        'shader' => ['label' => 'Fondo animado (ondas de luz, WebGL)', 'desc' => 'Un lienzo con ondas de luz detrás del contenido de la sección.', 'assets' => ['js' => ['assets/effects.js', 'assets/shader.js']],
            'sample' => [['block' => 'hero', 'data' => ['shader' => true, 'layout' => 'none'], 'style' => ['pad' => 'xl', 'text' => 'light']], ['block' => 'motion/titular', 'style' => ['pad' => 'xl', 'text' => 'light']]],
            'fields' => [
                'line' => ['type' => 'color', 'label' => 'Color de las ondas', 'default' => '#4f46e5', 'half' => true],
                'bg1' => ['type' => 'color', 'label' => 'Fondo, arriba a la izquierda', 'default' => '#0f0a2e', 'half' => true],
                'bg2' => ['type' => 'color', 'label' => 'Fondo, abajo a la derecha', 'default' => '#2e0f4d', 'half' => true],
                'speed' => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['0.5' => 'Muy lenta', '1' => 'Lenta', '2' => 'Normal', '3' => 'Rápida'], 'default' => '2', 'half' => true],
                'density' => ['type' => 'select', 'label' => 'Cantidad de ondas', 'options' => ['8' => 'Pocas', '16' => 'Normal', '26' => 'Muchas'], 'default' => '16', 'half' => true],
                'scale' => ['type' => 'select', 'label' => 'Tamaño del dibujo', 'options' => ['3' => 'Grande', '5' => 'Normal', '8' => 'Pequeño'], 'default' => '5', 'half' => true],
                'grid' => ['type' => 'checkbox', 'label' => 'Rejilla', 'text' => 'Dibujar también la rejilla de fondo'],
            ]],
        'spotlight' => ['label' => 'Tarjetas con luz que sigue al cursor', 'desc' => 'Las tarjetas de la sección iluminan su borde y fondo según la posición del ratón.', 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'tarjetas'], ['block' => 'motion/cifras']],
            'fields' => [
                'hue' => ['type' => 'select', 'label' => 'Color de la luz', 'options' => ['auto' => 'Cambia según dónde esté el ratón', 'accent' => 'El color de acento de la sección'], 'default' => 'auto', 'half' => true],
                'size' => ['type' => 'select', 'label' => 'Tamaño de la luz', 'options' => ['120px' => 'Pequeña', '200px' => 'Normal', '320px' => 'Grande'], 'default' => '200px', 'half' => true],
            ]],
        'gradient' => ['label' => 'Degradado animado que sigue al cursor', 'desc' => 'Manchas de color en movimiento detrás del contenido; una sigue al ratón.', 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'cta'], ['block' => 'motion/titular']],
            'fields' => [
                'color1' => ['type' => 'color', 'label' => 'Color de la mancha grande', 'default' => '#1f6feb', 'half' => true],
                'color2' => ['type' => 'color', 'label' => 'Color de la mancha que gira', 'default' => '#dd4aff', 'half' => true],
                'color3' => ['type' => 'color', 'label' => 'Color de la mancha del ratón', 'default' => '#8c64ff', 'half' => true],
                'blur' => ['type' => 'select', 'label' => 'Difuminado', 'options' => ['20px' => 'Poco', '40px' => 'Normal', '70px' => 'Mucho'], 'default' => '40px', 'half' => true],
                'speed' => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['0.5' => 'Lenta', '1' => 'Normal', '2' => 'Rápida'], 'default' => '1', 'half' => true],
                'opacity' => ['type' => 'select', 'label' => 'Intensidad', 'options' => ['.4' => 'Suave', '.7' => 'Normal', '1' => 'Fuerte'], 'default' => '.7', 'half' => true],
            ]],
    ],
];
