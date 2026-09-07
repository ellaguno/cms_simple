<?php
/**
 * Lienzo — catálogo de bloques genéricos. Cada clave tiene su vista en blocks/<clave>.php.
 * Formatos de líneas: los campos "lines" separan celdas con " | ". Los enlaces dentro de una celda van como "Texto > URL".
 */
declare(strict_types=1);
return [
    // ---- Estructura ------------------------------------------------------------------------------
    'cabecera' => [
        'label' => 'Cabecera del sitio', 'group' => 'Estructura', 'desc' => 'Logotipo, menú de navegación y botón. Va al principio de la página.',
        'wrap_class' => 'lz-header', 'styles' => ['bg', 'text', 'class', 'hide_mobile'], 'animate' => 'none',
        'fields' => [
            'topbar'      => ['type' => 'lines', 'i18n' => true, 'label' => 'Barra superior (opcional), un dato por línea: Texto | URL | derecha (para alinear a la derecha)', 'rows' => 3],
            'logo'        => ['type' => 'image', 'label' => 'Logotipo (imagen)'],
            'name'        => ['type' => 'text', 'i18n' => true, 'label' => 'Nombre o texto del logotipo (si no hay imagen)'],
            'menu'        => ['type' => 'lines', 'i18n' => true, 'label' => 'Menú, una entrada por línea: Texto | URL o #ancla', 'rows' => 5, 'default' => ['Inicio | /']],
            'button_text' => ['type' => 'text', 'i18n' => true, 'label' => 'Botón (opcional)', 'half' => true],
            'button_url'  => ['type' => 'text', 'label' => 'URL del botón', 'half' => true],
            'sticky'      => ['type' => 'checkbox', 'label' => 'Posición', 'text' => 'Fija al hacer scroll', 'default' => true],
        ],
    ],
    'pie' => [
        'label' => 'Pie de página', 'group' => 'Estructura', 'desc' => 'Logotipo o nombre, texto, columnas de enlaces, contacto, redes y derechos. Va al final.',
        'wrap_class' => 'lz-footer', 'styles' => ['bg', 'text', 'pad', 'class'], 'animate' => 'none',
        'fields' => [
            'logo'    => ['type' => 'image', 'label' => 'Logotipo (imagen)'],
            'name'    => ['type' => 'text', 'i18n' => true, 'label' => 'Nombre'],
            'text'    => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto breve bajo el nombre', 'rows' => 2],
            'columns' => ['type' => 'lines', 'i18n' => true, 'label' => 'Columnas de enlaces, una por línea: Título | Enlace > URL | Enlace > URL', 'rows' => 4],
            'contact' => ['type' => 'lines', 'i18n' => true, 'label' => 'Datos de contacto, uno por línea (correo, teléfono, dirección)', 'rows' => 3],
            'social'  => ['type' => 'lines', 'label' => 'Redes, una por línea: Nombre | URL', 'rows' => 3],
            'copy'    => ['type' => 'text', 'i18n' => true, 'label' => 'Línea de derechos', 'default' => '© {year} Todos los derechos reservados'],
        ],
    ],

    // ---- Cabeceras -------------------------------------------------------------------------------
    'hero' => [
        'label' => 'Hero', 'group' => 'Cabeceras', 'desc' => 'Titular grande con texto de apoyo, botones e imagen. La primera banda de una portada.',
        'wrap_class' => 'lz-hero', 'effects' => ['visual/shader', 'visual/gradient'],
        'fields' => [
            'badge'    => ['type' => 'text', 'i18n' => true, 'label' => 'Etiqueta pequeña sobre el título (opcional)'],
            'title'    => ['type' => 'text', 'i18n' => true, 'label' => 'Título (admite <span class="lz-accent">…</span> para resaltar)', 'required' => true],
            'subtitle' => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto de apoyo', 'rows' => 2],
            'buttons'  => ['type' => 'lines', 'i18n' => true, 'label' => 'Botones, uno por línea: Texto | URL | estilo (primary, secondary, outline)', 'rows' => 2],
            'image'    => ['type' => 'image', 'label' => 'Imagen'],
            'layout'   => ['type' => 'select', 'label' => 'Imagen', 'options' => ['right' => 'A la derecha', 'left' => 'A la izquierda', 'below' => 'Debajo, centrada', 'bg' => 'De fondo, a todo el ancho', 'none' => 'Sin imagen'], 'default' => 'right'],
            'shader'   => ['type' => 'checkbox', 'label' => 'Fondo', 'text' => 'Fondo animado (efecto visual/shader)'],
        ],
    ],
    'encabezado' => [
        'label' => 'Encabezado de página', 'group' => 'Cabeceras', 'desc' => 'Título centrado con texto de apoyo, para páginas interiores.',
        'wrap_class' => 'lz-pagehead',
        'fields' => [
            'title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título', 'required' => true],
            'text'  => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto de apoyo', 'rows' => 2],
        ],
    ],

    // ---- Contenido --------------------------------------------------------------------------------
    'texto' => [
        'label' => 'Texto', 'group' => 'Contenido', 'desc' => 'Texto libre con el editor visual (títulos, listas, imágenes, videos).',
        'wrap_class' => 'lz-sec',
        'fields' => ['body' => ['type' => 'html', 'i18n' => true, 'label' => 'Contenido', 'size' => 'lg']],
    ],
    'columnas' => [
        'label' => 'Texto e imagen', 'group' => 'Contenido', 'desc' => 'Dos columnas: texto a un lado e imagen al otro.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'body'  => ['type' => 'html', 'i18n' => true, 'label' => 'Texto'],
            'image' => ['type' => 'image', 'label' => 'Imagen'],
            'side'  => ['type' => 'select', 'label' => 'Imagen a la', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda'], 'default' => 'right'],
            'button_text' => ['type' => 'text', 'i18n' => true, 'label' => 'Botón (opcional)', 'half' => true],
            'button_url'  => ['type' => 'text', 'label' => 'URL del botón', 'half' => true],
        ],
    ],
    'imagen' => [
        'label' => 'Imagen', 'group' => 'Contenido', 'desc' => 'Una imagen grande con pie opcional.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'image'   => ['type' => 'image', 'label' => 'Imagen', 'required' => true],
            'alt'     => ['type' => 'text', 'i18n' => true, 'label' => 'Texto alternativo'],
            'caption' => ['type' => 'text', 'i18n' => true, 'label' => 'Pie de imagen (opcional)'],
        ],
    ],
    'tarjetas' => [
        'label' => 'Tarjetas', 'group' => 'Contenido', 'desc' => 'Rejilla de tarjetas con icono, título y texto: servicios, características, beneficios, pasos.',
        'wrap_class' => 'lz-sec', 'effects' => ['visual/spotlight'], 'libs' => ['tabler'],
        'fields' => [
            'title'    => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'i18n' => true, 'label' => 'Subtítulo'],
            'items'    => ['type' => 'lines', 'i18n' => true, 'label' => 'Tarjetas, una por línea: Título | Texto | icono (emoji, o nombre de Tabler Icons como ti-rocket, ti-shield-check, ti-users; catálogo en tabler.io/icons)', 'rows' => 6, 'required' => true],
            'columns'  => ['type' => 'select', 'label' => 'Columnas', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
            'variant'  => ['type' => 'select', 'label' => 'Estilo', 'options' => ['card' => 'Tarjeta con fondo', 'plain' => 'Sin fondo', 'steps' => 'Pasos numerados'], 'default' => 'card'],
        ],
    ],
    'lista' => [
        'label' => 'Lista de puntos', 'group' => 'Contenido', 'desc' => 'Título y lista de ventajas o puntos con marca, en una o dos columnas.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title'   => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'text'    => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto introductorio', 'rows' => 2],
            'items'   => ['type' => 'lines', 'i18n' => true, 'label' => 'Puntos, uno por línea: Texto | detalle (opcional)', 'rows' => 6, 'required' => true],
            'columns' => ['type' => 'select', 'label' => 'Columnas', 'options' => ['1' => '1', '2' => '2'], 'default' => '2'],
        ],
    ],
    'logos' => [
        'label' => 'Logotipos', 'group' => 'Contenido', 'desc' => 'Fila de logotipos de clientes o aliados.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title'  => ['type' => 'text', 'i18n' => true, 'label' => 'Título pequeño (opcional)'],
            'images' => ['type' => 'images', 'label' => 'Logotipos (una imagen por línea; opcional "ruta | nombre")', 'rows' => 6, 'required' => true],
        ],
    ],
    'testimonios' => [
        'label' => 'Testimonios', 'group' => 'Contenido', 'desc' => 'Una o varias citas de clientes con nombre y cargo.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'items' => ['type' => 'lines', 'i18n' => true, 'label' => 'Testimonios, uno por línea: Cita | Nombre | Cargo o empresa', 'rows' => 4, 'required' => true],
        ],
    ],
    'equipo' => [
        'label' => 'Equipo', 'group' => 'Contenido', 'desc' => 'Personas con foto, nombre, cargo y texto breve.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'text'  => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto introductorio', 'rows' => 2],
            'items' => ['type' => 'lines', 'i18n' => true, 'label' => 'Personas, una por línea: Nombre | Cargo | ruta de la foto | texto breve', 'rows' => 6, 'required' => true],
        ],
    ],
    'boletin' => [
        'label' => 'Boletín', 'group' => 'Contenido', 'desc' => 'Título, texto y un campo de correo para suscribirse.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title'       => ['type' => 'text', 'i18n' => true, 'label' => 'Título', 'required' => true],
            'text'        => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto', 'rows' => 2],
            'button_text' => ['type' => 'text', 'i18n' => true, 'label' => 'Texto del botón', 'default' => 'Suscribirme'],
            'note'        => ['type' => 'text', 'i18n' => true, 'label' => 'Nota pequeña (privacidad)'],
        ],
    ],
    'planes' => [
        'label' => 'Planes de precios', 'group' => 'Contenido', 'desc' => 'Tarjetas de precio con características y botón.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title'    => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'subtitle' => ['type' => 'text', 'i18n' => true, 'label' => 'Subtítulo'],
            'items'    => ['type' => 'lines', 'i18n' => true, 'label' => 'Planes, uno por línea: Nombre | Precio | Periodo | característica; característica; … | Botón | URL | destacado (si)', 'rows' => 4, 'required' => true],
        ],
    ],
    'faq' => [
        'label' => 'Preguntas frecuentes', 'group' => 'Contenido', 'desc' => 'Preguntas desplegables con su respuesta.',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'items' => ['type' => 'lines', 'i18n' => true, 'label' => 'Preguntas, una por línea: Pregunta | Respuesta', 'rows' => 6, 'required' => true],
        ],
    ],
    'cta' => [
        'label' => 'Llamado a la acción', 'group' => 'Contenido', 'desc' => 'Banda destacada con título, texto y uno o dos botones.',
        'wrap_class' => 'lz-cta', 'effects' => ['visual/gradient'],
        'fields' => [
            'title'        => ['type' => 'text', 'i18n' => true, 'label' => 'Título', 'required' => true],
            'text'         => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto', 'rows' => 2],
            'button_text'  => ['type' => 'text', 'i18n' => true, 'label' => 'Botón principal', 'half' => true],
            'button_url'   => ['type' => 'text', 'label' => 'URL', 'half' => true],
            'button2_text' => ['type' => 'text', 'i18n' => true, 'label' => 'Segundo botón (opcional)', 'half' => true],
            'button2_url'  => ['type' => 'text', 'label' => 'URL', 'half' => true],
        ],
    ],
    'contacto' => [
        'label' => 'Contacto', 'group' => 'Contenido', 'desc' => 'Datos de contacto y formulario (nombre, correo, mensaje).',
        'wrap_class' => 'lz-sec',
        'fields' => [
            'title'   => ['type' => 'text', 'i18n' => true, 'label' => 'Título'],
            'text'    => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto', 'rows' => 2],
            'email'   => ['type' => 'text', 'label' => 'Correo', 'half' => true],
            'phone'   => ['type' => 'text', 'label' => 'Teléfono', 'half' => true],
            'address' => ['type' => 'text', 'i18n' => true, 'label' => 'Dirección'],
            'form'    => ['type' => 'checkbox', 'label' => 'Formulario', 'text' => 'Mostrar el formulario de contacto', 'default' => true],
            'button_text' => ['type' => 'text', 'i18n' => true, 'label' => 'Texto del botón de envío', 'default' => 'Enviar'],
        ],
    ],
    'separador' => [
        'label' => 'Separador', 'group' => 'Contenido', 'desc' => 'Espacio en blanco, con o sin línea.',
        'wrap_class' => 'lz-spacer', 'styles' => ['bg', 'class'], 'animate' => 'none',
        'fields' => [
            'size' => ['type' => 'select', 'label' => 'Altura', 'options' => ['s' => 'Pequeño', 'm' => 'Medio', 'l' => 'Grande'], 'default' => 'm'],
            'line' => ['type' => 'checkbox', 'label' => 'Línea', 'text' => 'Mostrar una línea'],
        ],
    ],
    'html' => [
        'label' => 'HTML', 'group' => 'Contenido', 'desc' => 'Código HTML tal cual (incrustados, mapas).',
        'wrap_class' => 'lz-sec',
        'fields' => ['code' => ['type' => 'code', 'label' => 'HTML', 'rows' => 10, 'i18n' => true]],
    ],
];
