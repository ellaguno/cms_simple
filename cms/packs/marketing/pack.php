<?php
/** Paquete "marketing": secciones de página de venta al estilo de los catálogos de componentes actuales, en PHP, CSS y JS sin librerías. */
return [
    'label' => 'Marketing',
    'version' => '1.0.0',
    'desc' => 'Hero con palabras rotativas, cuadrícula bento, pestañas con imagen, comparador antes/después, cinta de testimonios, precios mensual/anual, línea de tiempo, cinta de logotipos; efectos de fondo: aurora, rejilla con haz, partículas y meteoros.',
    'assets' => ['css' => ['assets/marketing.css']],
    'effects' => [
        'aurora'    => ['label' => 'Fondo aurora (manchas de color que fluyen)', 'desc' => 'Tres velos de color que se mueven despacio detrás del contenido. Luce más sobre fondo oscuro.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/hero-palabras', 'style' => ['bg' => 'dark', 'text' => 'light']]]],
        'rejilla'   => ['label' => 'Fondo de rejilla con haz de luz', 'desc' => 'Cuadrícula fina que se desvanece hacia los bordes, con un haz que la recorre.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/linea-tiempo'], ['block' => 'marketing/hero-palabras']]],
        'particulas' => ['label' => 'Partículas conectadas (canvas)', 'desc' => 'Puntos que flotan y se unen con líneas cuando se acercan; reaccionan al ratón.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/hero-palabras', 'style' => ['bg' => 'dark', 'text' => 'light']]]],
        'meteoros'  => ['label' => 'Cielo con meteoros', 'desc' => 'Estrellas fijas y trazos de luz que cruzan la sección de vez en cuando. Para fondos oscuros.', 'assets' => ['js' => ['assets/effects.js']],
                        'sample' => [['block' => 'marketing/precios', 'style' => ['bg' => 'dark', 'text' => 'light']], ['block' => 'marketing/hero-palabras', 'style' => ['bg' => 'dark', 'text' => 'light']]]],
    ],
];
