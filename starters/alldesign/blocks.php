<?php
/**
 * All Design — bloques del constructor. Cada clave tiene su vista en blocks/<clave>.php.
 * Son las bandas del sitio original de Muse: "Qué hacemos" con sello, acordeón de servicios, etiqueta espaciada,
 * tarjetas de noticias, galería de proyectos, servicios con icono, mosaico del portafolio, texto con imagen circular y
 * contacto. La portada con imágenes, la banda con círculo, el equipo y las barras de habilidades son del paquete
 * "agencia" del núcleo (cms/packs/agencia), que este tema activa en config.php.
 * Formato de los campos de líneas: las celdas se separan con " | ".
 */
declare(strict_types=1);
return [
    // ---- Contenido ------------------------------------------------------------------------------------
    'ad-intro' => [
        'label' => 'Texto con sello', 'group' => 'Contenido',
        'desc' => 'Título ligero, párrafos y a la derecha el sello de la marca dentro de un círculo (la banda "Qué hacemos").',
        'wrap_class' => 'ad-intro',
        'sample' => ['title' => 'Qué Hacemos', 'body' => '<p>All Design es una consultora de branding y diseño enfocada en ayudar a las empresas a construir marcas.</p><p>Generamos el nombre de la marca, la posicionamos, le damos identidad y personalidad.</p>'],
        'fields' => [
            'title' => ['type' => 'text', 'label' => 'Título', 'i18n' => true],
            'body'  => ['type' => 'html', 'label' => 'Texto', 'i18n' => true, 'size' => 'lg'],
            'image' => ['type' => 'image', 'label' => 'Imagen del círculo (vacío = sello de Ajustes → Marca)'],
            'image_hover' => ['type' => 'image', 'label' => 'Imagen al pasar el ratón (opcional)'],
            'side'  => ['type' => 'select', 'label' => 'Círculo a la', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda', 'none' => 'Sin círculo'], 'default' => 'right'],
        ],
    ],
    'ad-acordeon' => [
        'label' => 'Acordeón de servicios', 'group' => 'Contenido',
        'desc' => 'Lista con "+" y título en mayúsculas; al abrir uno se muestra su texto y se cierra el anterior.',
        'wrap_class' => 'ad-acordeon',
        'sample' => ['items' => ['INFORMES ANUALES | Diseñamos y conceptualizamos informes anuales impresos, en PDF o interactivos.', 'IDENTIDAD DE MARCA | La expresión visual de una marca ligada a una estrategia.', 'PÁGINAS WEB | Diseño y desarrollo de sitios que generan oportunidades de negocio.']],
        'fields' => [
            'items' => ['type' => 'lines', 'label' => 'Apartados, uno por línea: TÍTULO | Texto', 'i18n' => true, 'rows' => 8, 'required' => true],
            'open'  => ['type' => 'select', 'label' => 'Al cargar', 'options' => ['first' => 'El primero abierto', 'none' => 'Todos cerrados'], 'default' => 'first'],
        ],
    ],
    'ad-etiqueta' => [
        'label' => 'Etiqueta espaciada', 'group' => 'Contenido',
        'desc' => 'Un rótulo centrado en mayúsculas con las letras muy separadas ("V A L O R   A G R E G A D O"), en su propia franja.',
        'wrap_class' => 'ad-etiqueta', 'styles' => ['bg', 'pad', 'anchor', 'class', 'hide_mobile'],
        'sample' => ['text' => 'VALOR AGREGADO'],
        'fields' => [
            'text' => ['type' => 'text', 'label' => 'Texto', 'i18n' => true, 'required' => true],
            'tag'  => ['type' => 'select', 'label' => 'Etiqueta HTML', 'options' => ['h2' => 'Título de sección (h2)', 'h1' => 'Título de página (h1)', 'p' => 'Texto'], 'default' => 'h2'],
        ],
    ],
    'ad-tarjetas' => [
        'label' => 'Tarjetas de noticias', 'group' => 'Contenido',
        'desc' => 'Tarjetas oscuras con antetítulo, título, imagen y flecha ("Valor agregado"): las últimas noticias o tarjetas escritas a mano.',
        'wrap_class' => 'ad-tarjetas',
        'sample' => ['source' => 'manual', 'items' => ['EN ALL DESIGN CONSULTORES… | Tenemos soluciones para PyMEs | cms/assets/img/demo/foto-4.jpg | # | bottom', 'PREMIO a! DISEÑO | Abril, 2012 | cms/assets/img/demo/foto-5.jpg | # | left', 'DISEÑANDO CON: | Feng-Shui para tu imagen corporativa | cms/assets/img/demo/foto-6.jpg | # | left']],
        'fields' => [
            'source'  => ['type' => 'select', 'label' => 'Origen', 'options' => ['noticias' => 'Las últimas noticias', 'manual' => 'Tarjetas escritas aquí'], 'default' => 'noticias'],
            'count'   => ['type' => 'number', 'label' => 'Cuántas noticias', 'default' => 3, 'min' => 1, 'max' => 12, 'show_if' => ['source' => 'noticias']],
            'items'   => ['type' => 'lines', 'label' => 'Tarjetas, una por línea: Antetítulo | Título | imagen | URL | left o bottom', 'i18n' => true, 'rows' => 5, 'show_if' => ['source' => 'manual']],
            'columns' => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3'], 'default' => '2'],
        ],
    ],
    'ad-galeria' => [
        'label' => 'Galería de proyectos', 'group' => 'Contenido',
        'desc' => 'Mosaico de proyectos de una categoría (o los "especiales") que se abren en un visor con su título y descripción. También admite imágenes sueltas.',
        'wrap_class' => 'ad-galeria', 'libs' => ['glightbox'],
        'sample' => ['source' => 'manual', 'images' => ['cms/assets/img/demo/foto-1.jpg | Proyecto uno', 'cms/assets/img/demo/foto-2.jpg | Proyecto dos', 'cms/assets/img/demo/foto-3.jpg | Proyecto tres', 'cms/assets/img/demo/foto-4.jpg | Proyecto cuatro'], 'layout' => 'strip', 'columns' => '4'],
        'fields' => [
            'source'   => ['type' => 'select', 'label' => 'Origen', 'options' => ['category' => 'Proyectos de una categoría', 'featured' => 'Proyectos especiales (marcados en Proyectos)', 'all' => 'Todos los proyectos', 'manual' => 'Imágenes escritas aquí'], 'default' => 'category'],
            'category' => ['type' => 'text', 'label' => 'Categoría (como está escrita en los proyectos, p. ej. Informes anuales)', 'show_if' => ['source' => 'category']],
            'count'    => ['type' => 'number', 'label' => 'Máximo de proyectos (0 = todos)', 'default' => 0, 'min' => 0, 'max' => 60, 'show_if' => ['source' => ['category', 'featured', 'all']]],
            'images'   => ['type' => 'images', 'label' => 'Imágenes, una por línea: ruta | título | descripción', 'rows' => 6, 'show_if' => ['source' => 'manual']],
            'layout'   => ['type' => 'select', 'label' => 'Disposición', 'options' => ['grid' => 'Rejilla centrada', 'strip' => 'Tira a todo el ancho', 'masonry' => 'Mosaico de alturas libres'], 'default' => 'grid', 'half' => true],
            'columns'  => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5'], 'default' => '3', 'half' => true],
            'ratio'    => ['type' => 'select', 'label' => 'Proporción de las miniaturas', 'options' => ['4x3' => '4:3', '1x1' => 'Cuadrada', '3x2' => '3:2', '16x9' => '16:9'], 'default' => '4x3', 'half' => true],
            'captions' => ['type' => 'checkbox', 'label' => 'Miniaturas', 'text' => 'Mostrar el título al pasar el ratón', 'default' => true, 'half' => true],
            'lightbox' => ['type' => 'checkbox', 'label' => 'Visor', 'text' => 'Abrir en el visor con título y descripción (si no, enlaza a la página del proyecto)', 'default' => true],
        ],
    ],
    'ad-texto-imagen' => [
        'label' => 'Texto con imagen circular', 'group' => 'Contenido',
        'desc' => 'Título ligero y párrafos con una imagen recortada en círculo al lado (el cierre de cada página del portafolio).',
        'wrap_class' => 'ad-texto-imagen',
        'sample' => ['title' => 'Informes Anuales', 'body' => '<p>Comenzamos a diseñar y conceptualizar informes anuales a partir de 1991 y desde entonces no hemos parado.</p>', 'image' => 'cms/assets/img/demo/foto-2.jpg'],
        'fields' => [
            'title' => ['type' => 'text', 'label' => 'Título', 'i18n' => true],
            'body'  => ['type' => 'html', 'label' => 'Texto', 'i18n' => true, 'size' => 'lg'],
            'image' => ['type' => 'image', 'label' => 'Imagen'],
            'shape' => ['type' => 'select', 'label' => 'Forma de la imagen', 'options' => ['circle' => 'Círculo', 'rect' => 'Rectángulo'], 'default' => 'circle', 'half' => true],
            'side'  => ['type' => 'select', 'label' => 'Imagen a la', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda'], 'default' => 'right', 'half' => true],
            'button_text' => ['type' => 'text', 'label' => 'Botón (opcional)', 'i18n' => true, 'half' => true],
            'button_url'  => ['type' => 'text', 'label' => 'URL del botón', 'half' => true],
        ],
    ],
    'ad-servicios' => [
        'label' => 'Servicios con icono', 'group' => 'Contenido',
        'desc' => 'Lista vertical: icono a la izquierda, título y texto a la derecha (la página Servicios). Las palabras entre *asteriscos* salen resaltadas.',
        'wrap_class' => 'ad-servicios',
        'sample' => ['items' => ['🎯 | Estrategia de Negocio | All Design está conformado por un grupo de expertos en *marcas* desde distintas perspectivas.', '💡 | Solución de Diseño | Creemos en el poder del *diseño* como catalizador de innovación.']],
        'fields' => [
            'items' => ['type' => 'lines', 'label' => 'Servicios, uno por línea: icono (imagen o emoji) | Título | Texto (usa *asteriscos* para resaltar y "//" para saltar de párrafo)', 'i18n' => true, 'rows' => 8, 'required' => true],
            'icon_size' => ['type' => 'select', 'label' => 'Tamaño del icono', 'options' => ['s' => 'Pequeño', 'm' => 'Mediano', 'l' => 'Grande'], 'default' => 'm'],
        ],
    ],
    'ad-portafolio' => [
        'label' => 'Mosaico del portafolio', 'group' => 'Contenido',
        'desc' => 'Cuadros con imagen y un rótulo que aparece al pasar el ratón, uno por categoría del portafolio.',
        'wrap_class' => 'ad-portafolio',
        'sample' => ['items' => ['IDENTIDAD | cms/assets/img/demo/foto-1.jpg | #', 'INFORMES Y + | cms/assets/img/demo/foto-2.jpg | #', 'WEB | cms/assets/img/demo/foto-3.jpg | #', 'FOTOGRAFÍA | cms/assets/img/demo/foto-4.jpg | #', 'EMPAQUES | cms/assets/img/demo/foto-5.jpg | #', 'ILUSTRACIÓN | cms/assets/img/demo/foto-6.jpg | #']],
        'fields' => [
            'items'   => ['type' => 'lines', 'label' => 'Cuadros, uno por línea: RÓTULO | imagen | URL', 'i18n' => true, 'rows' => 7, 'required' => true],
            'columns' => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3', 'half' => true],
            'hover'   => ['type' => 'select', 'label' => 'Rótulo', 'options' => ['hover' => 'Solo al pasar el ratón', 'always' => 'Siempre visible'], 'default' => 'hover', 'half' => true],
        ],
    ],
    'ad-texto' => [
        'label' => 'Texto', 'group' => 'Contenido',
        'desc' => 'Texto libre con el editor visual.',
        'wrap_class' => 'ad-texto',
        'sample' => ['body' => '<h2>Un subtítulo</h2><p>Texto libre con el editor visual: <strong>negritas</strong>, <em>cursivas</em>, enlaces, listas e imágenes.</p>'],
        'fields' => [
            'kicker' => ['type' => 'text', 'label' => 'Antetítulo pequeño (opcional)', 'i18n' => true],
            'title'  => ['type' => 'text', 'label' => 'Título (opcional)', 'i18n' => true],
            'body'   => ['type' => 'html', 'label' => 'Contenido', 'i18n' => true, 'size' => 'lg'],
        ],
    ],
    'ad-contacto' => [
        'label' => 'Contacto', 'group' => 'Contenido',
        'desc' => 'Formulario a la izquierda, datos de contacto a la derecha y debajo los dos cuadros de Facebook y X.',
        'wrap_class' => 'ad-contacto',
        'sample' => ['form' => true, 'tiles' => true],
        'fields' => [
            'form'    => ['type' => 'checkbox', 'label' => 'Formulario', 'text' => 'Mostrar el formulario de contacto', 'default' => true],
            'address' => ['type' => 'textarea', 'label' => 'Dirección (vacío = la de Ajustes)', 'rows' => 2],
            'phone'   => ['type' => 'text', 'label' => 'Teléfono (vacío = el de Ajustes)', 'half' => true],
            'email'   => ['type' => 'email', 'label' => 'Correo (vacío = el de Ajustes)', 'half' => true],
            'tiles'   => ['type' => 'checkbox', 'label' => 'Redes', 'text' => 'Mostrar los cuadros de Facebook y X (enlaces de Ajustes → Contacto y redes)', 'default' => true],
        ],
    ],
];
