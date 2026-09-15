<?php
/**
 * alldesign.mx — configuración del sitio para cms_simple.
 * Reproduce el diseño del sitio hecho con Adobe Muse (2012-2018): cabecera clara con menú en mayúsculas, portadas a
 * todo el ancho con la pestaña curva, cuerpo gris oscuro, etiquetas espaciadas, círculo verde, tarjetas y galerías.
 * Todo el contenido se edita en el panel: las páginas se arman con los bloques del tema (site/blocks.php) más los del
 * paquete "agencia" del núcleo (portada con imágenes, banda con círculo, equipo, habilidades), y el portafolio sale de
 * la colección "Proyectos" agrupada por categoría.
 */
return [
    'name' => 'All Design',
    'langs' => ['es'],
    'default_lang' => 'es',
    'timezone' => 'America/Mexico_City',
    'admin_logo' => 'logo.png',
    'site_url' => 'https://alldesign.mx',
    // La portada es la página "inicio" del constructor (data/content/paginas/inicio.json)
    'home_item' => ['paginas', 'inicio'],
    // Paquetes compartidos del núcleo (cms/packs): galerías, lightbox, marquesinas, video, listados de colecciones…
    'packs' => ['agencia', 'visual', 'motion', 'media', 'contenido'],
    'sections' => [
        'palette' => ['dark' => 'Gris oscuro (el del sitio)', 'darker' => 'Gris más oscuro', 'black' => 'Casi negro', 'light' => 'Gris claro', 'white' => 'Blanco', 'accent' => 'Verde de la marca'],
        'classes' => ['container' => 'ad-container', 'header' => 'ad-sec-head', 'title' => 'ad-sec-title', 'subtitle' => 'ad-sec-sub', 'btn' => 'ad-btn'],
        'accent_vars' => ['--ad-accent'],
    ],

    'types' => [
        'paginas' => [
            'label' => 'Páginas', 'label_singular' => 'Página', 'group' => 'Contenido',
            'help' => 'Páginas armadas con secciones. La portada es "inicio"; las del portafolio cuelgan de "portafolio" (portafolio/informes-anuales…) y así aparecen en el menú desplegable.',
            'routes' => ['es' => ''], 'tree' => true,
            'template_single' => 'pagina', 'schema' => 'WebPage',
            'sort' => ['field' => 'order', 'dir' => 'asc'], 'list' => ['updated', 'order'],
            'title_field' => 'title', 'excerpt_field' => 'summary', 'image_field' => 'image',
            'fields' => [
                'title'    => ['type' => 'text', 'label' => 'Título', 'i18n' => true, 'required' => true],
                'sections' => ['type' => 'sections', 'label' => 'Secciones de la página',
                               'help' => 'Portada con imágenes (paquete agencia), "Qué hacemos", acordeón de servicios, banda con círculo, etiqueta, tarjetas, galería, equipo, habilidades, contacto…'],
                'summary'  => ['type' => 'textarea', 'label' => 'Descripción para buscadores', 'i18n' => true, 'rows' => 2, 'sidebar' => true],
                'image'    => ['type' => 'image', 'label' => 'Imagen para redes (opcional)', 'sidebar' => true],
                'menu_label' => ['type' => 'text', 'label' => 'Texto corto para el menú desplegable (opcional)', 'i18n' => true, 'sidebar' => true],
                'order'    => ['type' => 'number', 'label' => 'Orden', 'sidebar' => true],
            ],
        ],
        'proyectos' => [
            'label' => 'Proyectos', 'label_singular' => 'Proyecto', 'group' => 'Contenido',
            'help' => 'Cada pieza del portafolio, con su categoría (Informes anuales, Identidad de marca, Web, Empaques, Fotografía, Ilustración). El bloque "Galería de proyectos" los muestra filtrados por categoría; "Proyecto especial" los lleva a la tira de la portada.',
            'routes' => ['es' => 'proyectos'],
            'template_list' => 'proyectos', 'template_single' => 'proyecto',
            'schema' => 'CreativeWork', 'sort' => ['field' => 'order', 'dir' => 'asc'], 'list' => ['category', 'order'],
            'title_field' => 'title', 'excerpt_field' => 'summary', 'image_field' => 'image',
            'categories' => true,
            'fields' => [
                'title'    => ['type' => 'text', 'label' => 'Título', 'i18n' => true, 'required' => true],
                'body'     => ['type' => 'html', 'label' => 'Descripción (aparece en el visor y en la página del proyecto)', 'i18n' => true],
                'summary'  => ['type' => 'textarea', 'label' => 'Resumen corto para buscadores (opcional)', 'i18n' => true, 'rows' => 2],
                'category' => ['type' => 'text', 'label' => 'Categoría', 'i18n' => true, 'sidebar' => true],
                'image'    => ['type' => 'image', 'label' => 'Imagen grande (visor)', 'sidebar' => true, 'required' => true],
                'thumb'    => ['type' => 'image', 'label' => 'Miniatura (vacío = recorte de la grande)', 'sidebar' => true],
                'url'      => ['type' => 'url', 'label' => 'Enlace externo (sitio web del proyecto, opcional)', 'sidebar' => true, 'placeholder' => 'https://'],
                'client'   => ['type' => 'text', 'label' => 'Cliente (opcional)', 'sidebar' => true],
                'featured' => ['type' => 'checkbox', 'label' => 'Portada', 'text' => 'Proyecto especial (tira de la portada)', 'sidebar' => true],
                'order'    => ['type' => 'number', 'label' => 'Orden', 'sidebar' => true],
            ],
        ],
        'equipo' => [
            'label' => 'Equipo', 'label_singular' => 'Integrante', 'group' => 'Contenido',
            'help' => 'Las personas del carrusel de "Nosotros": foto, nombre, puesto, semblanza y redes.',
            'routes' => ['es' => 'equipo'], 'noindex' => true, 'no_list' => true,
            'template_single' => 'integrante', 'schema' => 'Person',
            'sort' => ['field' => 'order', 'dir' => 'asc'], 'list' => ['role', 'order'],
            'title_field' => 'title', 'excerpt_field' => 'role', 'image_field' => 'photo',
            'fields' => [
                'title'    => ['type' => 'text', 'label' => 'Nombre', 'i18n' => true, 'required' => true],
                'body'     => ['type' => 'html', 'label' => 'Semblanza', 'i18n' => true],
                'role'     => ['type' => 'text', 'label' => 'Puesto (DIRECTOR CREATIVO, EDITORA…)', 'i18n' => true, 'sidebar' => true],
                'photo'    => ['type' => 'image', 'label' => 'Foto (cuadrada, blanco y negro)', 'sidebar' => true],
                'twitter'  => ['type' => 'url', 'label' => 'X / Twitter', 'sidebar' => true, 'placeholder' => 'https://x.com/…'],
                'linkedin' => ['type' => 'url', 'label' => 'LinkedIn', 'sidebar' => true, 'placeholder' => 'https://www.linkedin.com/in/…'],
                'order'    => ['type' => 'number', 'label' => 'Orden', 'sidebar' => true],
            ],
        ],
        'noticias' => [
            'label' => 'Noticias', 'label_singular' => 'Noticia', 'group' => 'Contenido',
            'help' => 'Las tarjetas de "Valor agregado" (PyMEs, Feng Shui, premios) y cualquier novedad. Cada una tiene su página.',
            'routes' => ['es' => 'noticias'],
            'template_list' => 'noticias', 'template_single' => 'noticia',
            'schema' => 'Article', 'sort' => ['field' => 'date', 'dir' => 'desc'], 'list' => ['date', 'kicker'],
            'title_field' => 'title', 'excerpt_field' => 'summary', 'image_field' => 'image',
            'fields' => [
                'title'   => ['type' => 'text', 'label' => 'Título', 'i18n' => true, 'required' => true],
                'kicker'  => ['type' => 'text', 'label' => 'Antetítulo de la tarjeta (EN ALL DESIGN CONSULTORES…, DISEÑANDO CON:)', 'i18n' => true],
                'body'    => ['type' => 'html', 'label' => 'Contenido', 'i18n' => true, 'size' => 'lg'],
                'summary' => ['type' => 'textarea', 'label' => 'Resumen para buscadores', 'i18n' => true, 'rows' => 2],
                'date'    => ['type' => 'date', 'label' => 'Fecha', 'sidebar' => true],
                'image'   => ['type' => 'image', 'label' => 'Imagen de la tarjeta', 'sidebar' => true],
                'hero'    => ['type' => 'image', 'label' => 'Imagen de portada de la página (opcional)', 'sidebar' => true],
                'layout'  => ['type' => 'select', 'label' => 'Tarjeta', 'options' => ['bottom' => 'Imagen debajo del título', 'left' => 'Imagen a la izquierda'], 'default' => 'bottom', 'sidebar' => true],
            ],
        ],
    ],

    'pages' => [],

    'settings' => [
        'Diseño' => [
            'color_accent' => ['type' => 'color', 'label' => 'Color de acento (verde del círculo)', 'placeholder' => '#97d700 (el del tema)', 'half' => true],
            'color_bg'     => ['type' => 'color', 'label' => 'Gris del cuerpo', 'placeholder' => '#404040 (el del tema)', 'half' => true],
            'font'         => ['type' => 'select', 'label' => 'Tipografía del texto (Google Fonts)', 'options' => ['' => 'Open Sans (la del tema)', 'Lato' => 'Lato', 'Source Sans 3' => 'Source Sans 3', 'Roboto' => 'Roboto', 'Nunito Sans' => 'Nunito Sans', 'Work Sans' => 'Work Sans', 'Inter' => 'Inter'], 'half' => true],
            'font_menu'    => ['type' => 'select', 'label' => 'Tipografía del menú', 'options' => ['' => 'Open Sans (la del tema)', 'Dosis' => 'Dosis', 'Josefin Sans' => 'Josefin Sans', 'Montserrat' => 'Montserrat', 'Raleway' => 'Raleway'], 'half' => true],
        ],
        'Marca' => [
            'logo_light' => ['type' => 'image', 'label' => 'Logotipo en blanco (pie de página)'],
            'seal'       => ['type' => 'image', 'label' => 'Sello (círculo de "Qué hacemos")'],
            'seal_hover' => ['type' => 'image', 'label' => 'Sello al pasar el ratón (opcional)'],
        ],
        'Cabecera y pie' => [
            'portfolio_page' => ['type' => 'text', 'label' => 'URL de la página Portafolio (sus páginas hijas forman el menú desplegable)', 'placeholder' => 'portafolio', 'default' => 'portafolio'],
            'address'        => ['type' => 'textarea', 'label' => 'Dirección (pie de página y contacto)', 'rows' => 2],
            'footer_text'    => ['type' => 'textarea', 'label' => 'Texto breve del pie (opcional)', 'rows' => 2],
        ],
    ],

    'strings_groups' => [
        'Cabecera y pie' => ['menu_label', 'menu_portfolio', 'footer_contact', 'footer_follow', 'footer_copy', 'back_top', 'share_label', 'share_title'],
        'Galerías y proyectos' => ['gallery_open', 'gallery_visit', 'all_projects', 'category_projects', 'projects_empty', 'team_prev', 'team_next'],
        'Contacto y formulario' => ['f_name', 'f_email', 'f_message', 'f_send', 'f_sending', 'f_ok', 'f_err', 'f_invalid', 'c_address', 'c_phone', 'c_email', 'c_facebook', 'c_facebook_text', 'c_twitter', 'c_twitter_text'],
        'Buscadores (SEO) y errores' => ['home_meta_title', 'home_meta_desc', 'not_found_title', 'not_found_text', 'go_home', 'read_more'],
    ],

    'form' => ['required' => ['nombre', 'correo'], 'email_field' => 'correo', 'name_field' => 'nombre', 'honeypot' => 'empresa_web2'],
    'max_image_width' => 1920,
    'code_editor' => true,
];
