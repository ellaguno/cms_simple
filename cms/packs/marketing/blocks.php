<?php
/**
 * Bloques del paquete "marketing". Formatos de líneas: celdas separadas con " | ". Un icono es un emoji o un nombre de
 * Tabler Icons (ti-rocket); una imagen es una ruta (uploads/…, cms/…, nombre.jpg en site/assets/img) o una URL.
 */
declare(strict_types=1);
$F = 'cms/assets/img/demo/';
return [
    'hero-palabras' => [
        'label' => 'Hero con palabras rotativas', 'group' => 'Marketing',
        'desc' => 'Titular en el que una palabra cambia sola (diseñamos → construimos → lanzamos), con degradado, texto de apoyo, botones e imagen con halo.',
        'wrap_class' => 'cms-sec mk-hero', 'assets' => ['js' => ['assets/marketing.js']],
        'fields' => [
            'badge'    => ['type' => 'text', 'label' => 'Etiqueta pequeña sobre el título (opcional)'],
            'before'   => ['type' => 'text', 'label' => 'Texto antes de la palabra que rota', 'required' => true],
            'words'    => ['type' => 'lines', 'label' => 'Palabras que rotan, una por línea', 'rows' => 4, 'required' => true, 'default' => ['diseñan', 'construyen', 'lanzan']],
            'after'    => ['type' => 'text', 'label' => 'Texto después (opcional)'],
            'subtitle' => ['type' => 'textarea', 'label' => 'Texto de apoyo', 'rows' => 2],
            'buttons'  => ['type' => 'lines', 'label' => 'Botones, uno por línea: Texto | URL | estilo (primary u outline)', 'rows' => 2],
            'image'    => ['type' => 'image', 'label' => 'Imagen debajo (opcional; lleva un halo de color)'],
            'align'    => ['type' => 'select', 'label' => 'Alineación', 'options' => ['center' => 'Centro', 'left' => 'Izquierda'], 'default' => 'center'],
            'gradient' => ['type' => 'checkbox', 'label' => 'Color', 'text' => 'La palabra que rota lleva degradado', 'default' => true],
            'gradient2' => ['type' => 'color', 'label' => 'Segundo color del degradado de la palabra', 'placeholder' => 'vacío = rosa', 'half' => true],
            'interval' => ['type' => 'select', 'label' => 'Cada cuánto cambia la palabra', 'options' => ['1600' => 'Rápido', '2600' => 'Normal', '4000' => 'Lento'], 'default' => '2600', 'half' => true],
            'glow' => ['type' => 'color', 'label' => 'Color del halo detrás de la imagen', 'placeholder' => 'vacío = el de acento', 'half' => true],
        ],
        'sample' => ['badge' => 'Nuevo en 2026', 'before' => 'Equipos que', 'words' => ['diseñan', 'construyen', 'lanzan', 'crecen'], 'after' => 'con calma.',
            'subtitle' => 'Una herramienta para planear, publicar y medir sin cambiar de pestaña.', 'buttons' => ['Empezar gratis | # | primary', 'Ver una demo | # | outline'], 'image' => $F . 'foto-4.jpg'],
    ],
    'bento' => [
        'label' => 'Cuadrícula bento', 'group' => 'Marketing',
        'desc' => 'Tarjetas de distintos tamaños encajadas en una cuadrícula, con icono o imagen, título y texto. Para características o servicios.',
        'wrap_class' => 'cms-sec', 'libs' => ['tabler'],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo'],
            'items'    => ['type' => 'lines', 'label' => 'Tarjetas, una por línea: Título | Texto | icono o imagen | tamaño (normal, ancho, alto, grande)', 'rows' => 7, 'required' => true],
            'columns'  => ['type' => 'select', 'label' => 'Columnas', 'options' => ['3' => '3', '4' => '4'], 'default' => '4'],
        ],
        'sample' => ['title' => 'Todo en un solo lugar', 'subtitle' => 'Lo que necesitas, sin lo que sobra', 'items' => [
            'Editor visual | Escribe como en un documento y publica con un clic. | ' . $F . 'foto-2.jpg | grande',
            'Rápido | Páginas que cargan en menos de un segundo. | ti-bolt | normal',
            'Seguro | Copias diarias y actualizaciones incluidas. | ti-shield-check | normal',
            'Multiidioma | Español e inglés desde el mismo panel. | ti-language | ancho',
            'Métricas | Sabe qué funciona sin instalar nada, con panel propio. | ti-chart-bar | ancho',
            'Soporte | Personas reales que responden el mismo día. | ti-headset | ancho']],
    ],
    'pestanas' => [
        'label' => 'Pestañas con imagen', 'group' => 'Marketing',
        'desc' => 'Lista de funciones a un lado; al elegir una cambia la imagen del otro lado. Puede avanzar sola con una barra de progreso.',
        'wrap_class' => 'cms-sec', 'assets' => ['js' => ['assets/marketing.js']], 'libs' => ['tabler'],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo'],
            'items'    => ['type' => 'lines', 'label' => 'Pestañas, una por línea: Título | Texto | imagen | icono (opcional)', 'rows' => 5, 'required' => true],
            'side'     => ['type' => 'select', 'label' => 'Imagen a la', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda'], 'default' => 'right'],
            'autoplay' => ['type' => 'checkbox', 'label' => 'Avance', 'text' => 'Pasar a la siguiente cada 6 segundos', 'default' => true],
        ],
        'sample' => ['title' => 'Cómo funciona', 'subtitle' => 'Tres pasos, sin manual', 'items' => [
            'Escribe | El editor se parece a un documento: títulos, listas, imágenes y video. | ' . $F . 'foto-1.jpg | ti-pencil',
            'Ordena | Arrastra las secciones y cambia el fondo o el espacio desde la pestaña Estilo. | ' . $F . 'foto-2.jpg | ti-layout-grid',
            'Publica | Programa la fecha o publica al momento; la vista previa es la página real. | ' . $F . 'foto-3.jpg | ti-rocket']],
    ],
    'comparador' => [
        'label' => 'Comparador antes y después', 'group' => 'Marketing',
        'desc' => 'Dos imágenes superpuestas con un tirador que se arrastra para descubrir una u otra.',
        'wrap_class' => 'cms-sec', 'assets' => ['js' => ['assets/marketing.js']],
        'fields' => [
            'title'        => ['type' => 'text', 'label' => 'Título'],
            'subtitle'     => ['type' => 'text', 'label' => 'Subtítulo'],
            'before'       => ['type' => 'image', 'label' => 'Imagen "antes"', 'required' => true],
            'after'        => ['type' => 'image', 'label' => 'Imagen "después"', 'required' => true],
            'label_before' => ['type' => 'text', 'label' => 'Etiqueta "antes"', 'default' => 'Antes', 'half' => true],
            'label_after'  => ['type' => 'text', 'label' => 'Etiqueta "después"', 'default' => 'Después', 'half' => true],
            'start'        => ['type' => 'number', 'label' => 'Posición inicial del tirador (%)', 'default' => 50, 'min' => 5, 'max' => 95],
        ],
        'sample' => ['title' => 'Antes y después', 'subtitle' => 'Arrastra el tirador', 'before' => $F . 'foto-6.jpg', 'after' => $F . 'foto-2.jpg'],
    ],
    'testimonios-cinta' => [
        'label' => 'Cinta de testimonios', 'group' => 'Marketing',
        'desc' => 'Tarjetas de opiniones que se desplazan sin fin en una o dos filas; se detienen al pasar el ratón.',
        'wrap_class' => 'cms-sec', 'styles' => ['bg', 'text', 'pad', 'anchor', 'class', 'hide_mobile'], 'assets' => ['js' => ['assets/marketing.js']],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo'],
            'items'    => ['type' => 'lines', 'label' => 'Testimonios, uno por línea: Cita | Nombre | Cargo o empresa | foto (opcional)', 'rows' => 6, 'required' => true],
            'rows'     => ['type' => 'select', 'label' => 'Filas', 'options' => ['1' => 'Una', '2' => 'Dos, en sentidos opuestos'], 'default' => '2'],
            'speed'    => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['slow' => 'Lenta', 'normal' => 'Normal', 'fast' => 'Rápida'], 'default' => 'normal'],
            'stars'    => ['type' => 'checkbox', 'label' => 'Estrellas', 'text' => 'Mostrar cinco estrellas en cada tarjeta', 'default' => true],
        ],
        'sample' => ['title' => 'Lo que dicen quienes ya lo usan', 'items' => [
            'En tres semanas teníamos el sitio nuevo listo. Antes eran meses. | Ana Torres | Directora de marketing, Nordic | ' . $F . 'persona-1.jpg',
            'El equipo entendió la marca a la primera y el resultado se nota. | Luis Pérez | Fundador, Vertex | ' . $F . 'persona-2.jpg',
            'Publico yo misma sin pedir ayuda. Eso no tiene precio. | María Ruiz | Comunicación, Solaria | ' . $F . 'persona-3.jpg',
            'Soporte real, con nombre y apellido. | Jorge Lima | Operaciones, Orbit | ' . $F . 'persona-4.jpg',
            'Migramos 1,200 artículos en un fin de semana. | Sofía Mena | Editora, Kappa',
            'Cargaba lento; ahora abre al instante en el móvil. | Diego Ríos | Ecommerce, ACME']],
    ],
    'precios' => [
        'label' => 'Precios con interruptor mensual/anual', 'group' => 'Marketing',
        'desc' => 'Tarjetas de precio con un interruptor que alterna mensual y anual, insignia de ahorro y plan destacado.',
        'wrap_class' => 'cms-sec', 'assets' => ['js' => ['assets/marketing.js']],
        'fields' => [
            'title'     => ['type' => 'text', 'label' => 'Título'],
            'subtitle'  => ['type' => 'text', 'label' => 'Subtítulo'],
            'items'     => ['type' => 'lines', 'label' => 'Planes, uno por línea: Nombre | Precio mensual | Precio anual | Descripción | característica; característica; … | Botón | URL | destacado (si)', 'rows' => 4, 'required' => true],
            'monthly'   => ['type' => 'text', 'label' => 'Texto del interruptor: mensual', 'default' => 'Mensual', 'half' => true],
            'yearly'    => ['type' => 'text', 'label' => 'Texto del interruptor: anual', 'default' => 'Anual', 'half' => true],
            'per_month' => ['type' => 'text', 'label' => 'Periodo junto al precio mensual', 'default' => '/mes', 'half' => true],
            'per_year'  => ['type' => 'text', 'label' => 'Periodo junto al precio anual', 'default' => '/año', 'half' => true],
            'save'      => ['type' => 'text', 'label' => 'Insignia junto a "anual" (opcional)', 'default' => 'Ahorra 20 %'],
            'note'      => ['type' => 'text', 'label' => 'Nota al pie (opcional)'],
        ],
        'sample' => ['title' => 'Un precio claro', 'subtitle' => 'Cambia de plan cuando quieras', 'items' => [
            'Básico | $499 | $4,790 | Para empezar con un sitio. | 1 sitio; Soporte por correo; Actualizaciones | Elegir | # |',
            'Pro | $999 | $9,590 | Para equipos que publican a diario. | 5 sitios; Soporte prioritario; Dominio incluido; Métricas | Elegir Pro | # | si',
            'Empresa | A medida | A medida | Varios sitios y acuerdo de servicio. | Sitios ilimitados; Gestor dedicado; Acuerdo de servicio | Hablar | # |'],
            'note' => 'Precios en pesos, sin IVA. Sin permanencia.'],
    ],
    'linea-tiempo' => [
        'label' => 'Línea de tiempo', 'group' => 'Marketing',
        'desc' => 'Pasos o hitos en una línea vertical que se va iluminando con el scroll. Para procesos, historia de la empresa o hoja de ruta.',
        'wrap_class' => 'cms-sec', 'assets' => ['js' => ['assets/marketing.js']], 'libs' => ['tabler'],
        'fields' => [
            'title'    => ['type' => 'text', 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'label' => 'Subtítulo'],
            'items'    => ['type' => 'lines', 'label' => 'Hitos, uno por línea: Título | Texto | etiqueta (fecha o paso) | icono (opcional)', 'rows' => 6, 'required' => true],
            'layout'   => ['type' => 'select', 'label' => 'Disposición', 'options' => ['alternate' => 'Alternada (izquierda y derecha)', 'left' => 'Todo a la derecha de la línea'], 'default' => 'alternate'],
        ],
        'sample' => ['title' => 'Cómo trabajamos', 'subtitle' => 'De la primera llamada al lanzamiento', 'items' => [
            'Descubrimiento | Una llamada de 30 minutos para entender el negocio y lo que tiene que lograr el sitio. | Semana 1 | ti-search',
            'Propuesta | Mapa del sitio, referencias y presupuesto cerrado. Sin sorpresas después. | Semana 2 | ti-file-text',
            'Diseño | Portada e interiores en el constructor, revisadas contigo en vivo. | Semanas 3 y 4 | ti-palette',
            'Contenido | Migramos lo que existe y escribimos lo que falta. | Semana 5 | ti-pencil',
            'Lanzamiento | Dominio, correo, métricas y una sesión de formación. | Semana 6 | ti-rocket']],
    ],
    'logos-cinta' => [
        'label' => 'Cinta de logotipos', 'group' => 'Marketing',
        'desc' => 'Logotipos de clientes que se desplazan sin fin, en gris y a color al pasar el ratón.',
        'wrap_class' => 'cms-sec', 'styles' => ['bg', 'text', 'pad', 'anchor', 'class', 'hide_mobile'], 'assets' => ['js' => ['assets/marketing.js']],
        'fields' => [
            'title'  => ['type' => 'text', 'label' => 'Título pequeño (opcional)', 'default' => 'Confían en nosotros'],
            'images' => ['type' => 'images', 'label' => 'Logotipos, uno por línea (opcional "ruta | nombre")', 'rows' => 6, 'required' => true],
            'speed'  => ['type' => 'select', 'label' => 'Velocidad', 'options' => ['slow' => 'Lenta', 'normal' => 'Normal', 'fast' => 'Rápida'], 'default' => 'normal'],
            'gray'   => ['type' => 'checkbox', 'label' => 'Color', 'text' => 'En gris, a color al pasar el ratón', 'default' => true],
        ],
        'sample' => ['images' => [$F . 'logo-1.png | ACME', $F . 'logo-2.png | Nordic', $F . 'logo-3.png | Vertex', $F . 'logo-4.png | Solaria', $F . 'logo-5.png | Kappa', $F . 'logo-6.png | Orbit']],
    ],
];
