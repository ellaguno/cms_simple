<?php
/** Bloques del paquete "motion". */
return [
    'marquesina' => [
        'label' => 'Marquesina', 'desc' => 'Textos o logotipos que se desplazan en una cinta continua.',
        'wrap_class' => 'cms-sec', 'styles' => ['bg', 'text', 'pad', 'anchor', 'class', 'hide_mobile'],
        'sample' => ['items' => ['Branding', 'Diseño web', 'Estrategia', 'Contenido'], 'outline' => true, 'size' => 'l'],
        'assets' => ['js' => ['assets/marquesina.js']],
        'fields' => [
            'items'     => ['type' => 'lines', 'label' => 'Elementos, uno por línea: texto, o ruta de imagen (logotipo)', 'rows' => 6, 'required' => true, 'default' => ['Diseño', 'Estrategia', 'Desarrollo']],
            'separator' => ['type' => 'text', 'label' => 'Separador entre elementos', 'default' => '✦'],
            'size'      => ['type' => 'select', 'label' => 'Tamaño', 'options' => ['m' => 'Normal', 'l' => 'Grande', 'xl' => 'Enorme'], 'default' => 'l'],
            'speed'     => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['slow' => 'Lenta', 'normal' => 'Normal', 'fast' => 'Rápida'], 'default' => 'normal'],
            'direction' => ['type' => 'select', 'label' => 'Dirección', 'options' => ['left' => 'Hacia la izquierda', 'right' => 'Hacia la derecha'], 'default' => 'left'],
            'outline'   => ['type' => 'checkbox', 'label' => 'Estilo', 'text' => 'Texto solo con contorno (alternando)'],
        ],
    ],
    'cifras' => [
        'label' => 'Cifras animadas', 'desc' => 'Números que cuentan desde cero al entrar en pantalla.',
        'wrap_class' => 'cms-sec', 'libs' => ['gsap', 'scrolltrigger'], 'assets' => ['js' => ['assets/cifras.js']],
        'sample' => ['title' => 'En números', 'subtitle' => 'Lo que hemos hecho hasta hoy'],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título (opcional)'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo (opcional)'],
            'items'    => ['type' => 'lines', 'label' => 'Cifras, una por línea: número | etiqueta | sufijo (opcional, ej. + o %)', 'rows' => 5, 'required' => true, 'default' => ['120 | Proyectos entregados | +', '15 | Años de experiencia', '98 | Clientes satisfechos | %']],
            'columns'  => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
        ],
    ],
    'titular' => [
        'label' => 'Titular grande', 'desc' => 'Una frase enorme, con texto de apoyo y botón opcionales; ideal con el efecto de texto revelado.',
        'wrap_class' => 'cms-sec', 'effects' => ['motion/reveal'],
        'sample' => ['text' => 'Diseñamos marcas que <em>se mueven</em>', 'sub' => 'Estrategia, identidad y web para empresas que quieren crecer.', 'button_text' => 'Hablemos', 'button_url' => '#'],
        'fields' => [
            'text'        => ['type' => 'textarea', 'label' => 'Frase (admite <em> para resaltar)', 'rows' => 2, 'required' => true],
            'sub'         => ['type' => 'textarea', 'label' => 'Texto de apoyo (opcional)', 'rows' => 2],
            'button_text' => ['type' => 'text', 'label' => 'Texto del botón (opcional)'],
            'button_url'  => ['type' => 'text', 'label' => 'URL del botón'],
            'align'       => ['type' => 'select', 'label' => 'Alineación', 'options' => ['left' => 'Izquierda', 'center' => 'Centro'], 'default' => 'left'],
            'reveal'      => ['type' => 'checkbox', 'label' => 'Efecto', 'text' => 'Revelar el texto letra por letra', 'default' => true],
        ],
    ],
    'parallax' => [
        'label' => 'Galería con parallax', 'desc' => 'Columnas de imágenes que se mueven a distinta velocidad con el scroll.',
        'wrap_class' => 'cms-sec', 'libs' => ['gsap', 'scrolltrigger'], 'assets' => ['js' => ['assets/parallax.js']],
        'sample' => ['title' => 'Trabajo reciente', 'images' => ['cms/assets/img/demo/foto-1.jpg | Panel', 'cms/assets/img/demo/foto-2.jpg | Casos', 'cms/assets/img/demo/foto-3.jpg | Documentos', 'cms/assets/img/demo/foto-4.jpg', 'cms/assets/img/demo/foto-5.jpg', 'cms/assets/img/demo/foto-6.jpg']],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título (opcional)'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo (opcional)'],
            'images'   => ['type' => 'images', 'label' => 'Imágenes, una por línea (opcional "ruta | texto | enlace")', 'rows' => 8, 'required' => true],
            'columns'  => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3'], 'default' => '3'],
        ],
    ],
];
