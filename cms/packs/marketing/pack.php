<?php
/** Paquete "marketing": secciones de página de venta al estilo de los catálogos de componentes actuales, en PHP, CSS y JS sin librerías. */
return [
    'label' => 'Marketing',
    'version' => '1.0.0',
    'desc' => 'Hero con palabras rotativas, cuadrícula bento, pestañas con imagen, comparador antes/después, cinta de testimonios, precios mensual/anual, línea de tiempo, cinta de logotipos; efectos de fondo: aurora, rejilla con haz, partículas y meteoros.',
    'assets' => ['css' => ['assets/marketing.css']],
    'effects' => [
        'aurora'    => ['label' => 'Fondo aurora (manchas de color que fluyen)', 'desc' => 'Tres velos de color que se mueven despacio detrás del contenido. Luce más sobre fondo oscuro.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/hero-palabras', 'style' => ['bg' => 'dark', 'text' => 'light']]],
            'fields' => [
                'color1' => ['type' => 'color', 'label' => 'Primer color', 'placeholder' => 'vacío = el de acento', 'half' => true],
                'color2' => ['type' => 'color', 'label' => 'Segundo color', 'default' => '#ec4899', 'half' => true],
                'color3' => ['type' => 'color', 'label' => 'Tercer color', 'default' => '#22d3ee', 'half' => true],
                'opacity' => ['type' => 'select', 'label' => 'Intensidad', 'options' => ['.3' => 'Suave', '.55' => 'Normal', '.85' => 'Fuerte'], 'default' => '.55', 'half' => true],
                'blur' => ['type' => 'select', 'label' => 'Difuminado', 'options' => ['40px' => 'Poco', '70px' => 'Normal', '110px' => 'Mucho'], 'default' => '70px', 'half' => true],
                'speed' => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['0.5' => 'Muy lenta', '1' => 'Lenta', '2' => 'Normal'], 'default' => '1', 'half' => true],
            ]],
        'rejilla'   => ['label' => 'Fondo de rejilla con haz de luz', 'desc' => 'Cuadrícula fina que se desvanece hacia los bordes, con un haz que la recorre.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/linea-tiempo'], ['block' => 'marketing/hero-palabras']],
            'fields' => [
                'size' => ['type' => 'select', 'label' => 'Tamaño de la cuadrícula', 'options' => ['28px' => 'Pequeña', '48px' => 'Normal', '80px' => 'Grande'], 'default' => '48px', 'half' => true],
                'color' => ['type' => 'color', 'label' => 'Color de las líneas', 'placeholder' => 'vacío = el del texto', 'half' => true],
                'beam' => ['type' => 'select', 'label' => 'Haz de luz', 'options' => ['both' => 'Vertical y horizontal', 'x' => 'Solo vertical', 'none' => 'Sin haz'], 'default' => 'both', 'half' => true],
                'fade' => ['type' => 'select', 'label' => 'Desvanecido hacia los bordes', 'options' => ['25%' => 'Fuerte', '35%' => 'Normal', '60%' => 'Suave'], 'default' => '35%', 'half' => true],
            ]],
        'particulas' => ['label' => 'Partículas conectadas (canvas)', 'desc' => 'Puntos que flotan y se unen con líneas cuando se acercan; reaccionan al ratón.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/hero-palabras', 'style' => ['bg' => 'dark', 'text' => 'light']]],
            'fields' => [
                'color' => ['type' => 'color', 'label' => 'Color de los puntos', 'placeholder' => 'vacío = el del texto', 'half' => true],
                'density' => ['type' => 'select', 'label' => 'Cantidad de puntos', 'options' => ['22000' => 'Pocos', '14000' => 'Normal', '8000' => 'Muchos'], 'default' => '14000', 'half' => true],
                'link' => ['type' => 'select', 'label' => 'Distancia a la que se unen', 'options' => ['80' => 'Corta', '120' => 'Normal', '180' => 'Larga'], 'default' => '120', 'half' => true],
                'speed' => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['0.15' => 'Lenta', '0.35' => 'Normal', '0.8' => 'Rápida'], 'default' => '0.35', 'half' => true],
                'interact' => ['type' => 'checkbox', 'label' => 'Ratón', 'text' => 'Los puntos se apartan del puntero', 'default' => true],
            ]],
        'meteoros'  => ['label' => 'Cielo con meteoros', 'desc' => 'Estrellas fijas y trazos de luz que cruzan la sección de vez en cuando. Para fondos oscuros.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/precios', 'style' => ['bg' => 'dark', 'text' => 'light']], ['block' => 'marketing/hero-palabras', 'style' => ['bg' => 'dark', 'text' => 'light']]],
            'fields' => [
                'stars' => ['type' => 'select', 'label' => 'Cantidad de estrellas', 'options' => ['40' => 'Pocas', '90' => 'Normal', '180' => 'Muchas'], 'default' => '90', 'half' => true],
                'meteors' => ['type' => 'select', 'label' => 'Cantidad de meteoros', 'options' => ['4' => 'Pocos', '9' => 'Normal', '18' => 'Muchos'], 'default' => '9', 'half' => true],
                'speed' => ['type' => 'select', 'label' => 'Velocidad de los meteoros', 'options' => ['8' => 'Lenta', '5' => 'Normal', '2.5' => 'Rápida'], 'default' => '5', 'half' => true],
                'color' => ['type' => 'color', 'label' => 'Color de las estrellas', 'default' => '#ffffff', 'half' => true],
            ]],
    ],
];
