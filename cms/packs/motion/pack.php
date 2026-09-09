<?php
/** Paquete "motion": movimiento con GSAP 3.13 (gratuito, uso comercial incluido). Reescrito en limpio a partir de los efectos de katapolt.mx. */
return [
    'label' => 'Movimiento',
    'version' => '1.0.0',
    'desc' => 'Marquesina, cifras animadas, titular grande, galería con parallax; efectos: texto revelado, aparición escalonada, parallax de fondo y cursor magnético.',
    'assets' => ['css' => ['assets/motion.css']],
    'effects' => [
        'reveal' => ['label' => 'Texto revelado letra por letra (títulos)', 'desc' => 'Los títulos de la sección aparecen letra por letra al llegar al viewport.', 'libs' => ['gsap', 'scrolltrigger', 'splittext'], 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'motion/titular', 'data' => ['reveal' => true]], ['block' => 'encabezado']],
            'fields' => [
                'speed' => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['0.4' => 'Rápida', '0.7' => 'Normal', '1.1' => 'Lenta'], 'default' => '0.7', 'half' => true],
                'gap' => ['type' => 'select', 'label' => 'Separación entre letras', 'options' => ['0.01' => 'Poca', '0.02' => 'Normal', '0.05' => 'Mucha'], 'default' => '0.02', 'half' => true],
                'from' => ['type' => 'select', 'label' => 'Aparecen desde', 'options' => ['bottom' => 'Abajo', 'top' => 'Arriba', 'scale' => 'El centro, creciendo'], 'default' => 'bottom', 'half' => true],
                'unit' => ['type' => 'select', 'label' => 'Se revela', 'options' => ['chars' => 'Letra por letra', 'words' => 'Palabra por palabra'], 'default' => 'chars', 'half' => true],
            ]],
        'stagger' => ['label' => 'Aparición escalonada de tarjetas', 'desc' => 'Los elementos de la rejilla de la sección entran uno tras otro.', 'libs' => ['gsap', 'scrolltrigger'], 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'tarjetas'], ['block' => 'motion/cifras']],
            'fields' => [
                'speed' => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['0.4' => 'Rápida', '0.7' => 'Normal', '1.1' => 'Lenta'], 'default' => '0.7', 'half' => true],
                'gap' => ['type' => 'select', 'label' => 'Separación entre elementos', 'options' => ['0.06' => 'Poca', '0.12' => 'Normal', '0.25' => 'Mucha'], 'default' => '0.12', 'half' => true],
                'distance' => ['type' => 'select', 'label' => 'Distancia del desplazamiento', 'options' => ['20' => 'Corta', '40' => 'Normal', '80' => 'Larga'], 'default' => '40', 'half' => true],
            ]],
        'parallax' => ['label' => 'Parallax de la imagen de fondo', 'desc' => 'La imagen de fondo de la sección se mueve más despacio que el scroll.', 'libs' => ['gsap', 'scrolltrigger'], 'assets' => ['js' => ['assets/effects.js']],
            'sample' => [['block' => 'texto', 'data' => ['body' => '<h2>Parallax de fondo</h2><p>La imagen de fondo se mueve más despacio que el scroll.</p>'], 'style' => ['bg_image' => 'cms/assets/img/demo/foto-3.jpg', 'overlay' => 50, 'pad' => 'xl', 'text' => 'light']], ['block' => 'motion/titular', 'style' => ['bg_image' => 'cms/assets/img/demo/foto-3.jpg', 'overlay' => 50, 'pad' => 'xl', 'text' => 'light']]],
            'fields' => [
                'amount' => ['type' => 'select', 'label' => 'Intensidad', 'options' => ['8' => 'Suave', '15' => 'Normal', '30' => 'Fuerte'], 'default' => '15', 'half' => true],
                'direction' => ['type' => 'select', 'label' => 'Dirección', 'options' => ['normal' => 'Se mueve más despacio', 'reverse' => 'Se mueve al revés'], 'default' => 'normal', 'half' => true],
            ]],
        'cursor' => ['label' => 'Cursor magnético (todo el sitio)', 'desc' => 'Un círculo sigue al puntero y se agranda sobre enlaces y botones; se activa por sitio en config packs → motion → site.', 'libs' => ['gsap'], 'assets' => ['js' => ['assets/cursor.js']],
            'sample' => [['block' => 'cta'], ['block' => 'motion/titular']],
            'fields' => [
                'size' => ['type' => 'select', 'label' => 'Tamaño del círculo', 'options' => ['20px' => 'Pequeño', '30px' => 'Normal', '48px' => 'Grande'], 'default' => '30px', 'half' => true],
                'color' => ['type' => 'color', 'label' => 'Color del círculo', 'placeholder' => 'vacío = el de acento', 'half' => true],
                'pull' => ['type' => 'select', 'label' => 'Atracción de botones y enlaces', 'options' => ['0' => 'Ninguna', '0.3' => 'Normal', '0.6' => 'Fuerte'], 'default' => '0.3', 'half' => true],
            ]],
    ],
];
