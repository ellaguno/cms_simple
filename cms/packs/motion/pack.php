<?php
/** Paquete "motion": movimiento con GSAP 3.13 (gratuito, uso comercial incluido). Reescrito en limpio a partir de los efectos de katapolt.mx. */
return [
    'label' => 'Movimiento',
    'version' => '1.0.0',
    'desc' => 'Marquesina, cifras animadas, titular grande, galería con parallax; efectos: texto revelado, aparición escalonada, parallax de fondo y cursor magnético.',
    'assets' => ['css' => ['assets/motion.css']],
    'effects' => [
        'reveal' => ['label' => 'Texto revelado letra por letra (títulos)', 'desc' => 'Los títulos de la sección aparecen letra por letra al llegar al viewport.', 'libs' => ['gsap', 'scrolltrigger', 'splittext'], 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'motion/titular', 'data' => ['reveal' => true]], ['block' => 'encabezado']]],
        'stagger' => ['label' => 'Aparición escalonada de tarjetas', 'desc' => 'Los elementos de la rejilla de la sección entran uno tras otro.', 'libs' => ['gsap', 'scrolltrigger'], 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'tarjetas'], ['block' => 'motion/cifras']]],
        'parallax' => ['label' => 'Parallax de la imagen de fondo', 'desc' => 'La imagen de fondo de la sección se mueve más despacio que el scroll.', 'libs' => ['gsap', 'scrolltrigger'], 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'texto', 'data' => ['body' => '<h2>Parallax de fondo</h2><p>La imagen de fondo se mueve más despacio que el scroll.</p>'], 'style' => ['bg_image' => 'cms/assets/img/demo/foto-3.jpg', 'overlay' => 50, 'pad' => 'xl', 'text' => 'light']], ['block' => 'motion/titular', 'style' => ['bg_image' => 'cms/assets/img/demo/foto-3.jpg', 'overlay' => 50, 'pad' => 'xl', 'text' => 'light']]]],
        'cursor' => ['label' => 'Cursor magnético (todo el sitio)', 'desc' => 'Un círculo sigue al puntero y se agranda sobre enlaces y botones; se activa por sitio en config packs → motion → site.', 'libs' => ['gsap'], 'assets' => ['js' => ['assets/cursor.js']],
            'sample' => [['block' => 'cta'], ['block' => 'motion/titular']]],
    ],
];
