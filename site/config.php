<?php
/**
 * Configuración del sitio de ejemplo (tema "starter").
 * Copia esta carpeta site/ para crear un sitio nuevo y ajusta: nombre, idiomas, tipos de contenido,
 * páginas, ajustes propios y grupos de textos. El núcleo (cms/) no se toca.
 *
 * Tipos de campo: text, textarea, html (editor visual), date, number, url, email, select, checkbox,
 *                 image, images (una por línea), lines (una por línea), tags (separadas por coma).
 * Opciones de campo: label, help, i18n (por idioma), required, sidebar (columna derecha), rows, options,
 *                    placeholder, default, size (html: md|lg), suffix (images: texto que se agrega tras la ruta).
 */
return [
    'name' => 'Mi sitio',
    'langs' => ['es', 'en'],          // el primero es el predeterminado (sin prefijo en la URL)
    'default_lang' => 'es',
    'timezone' => 'America/Mexico_City',
    'admin_logo' => '',               // ruta de imagen para el panel; vacío = nombre del sitio

    'types' => [
        'posts' => [
            'label' => 'Entradas del blog',
            'label_singular' => 'Entrada',
            'routes' => ['es' => 'blog', 'en' => 'blog'],
            'template_list' => 'blog',        // site/templates/blog.php
            'template_single' => 'post',      // site/templates/post.php
            'schema' => 'Article',            // JSON-LD del detalle
            'sort' => ['field' => 'date', 'dir' => 'desc'],
            'list' => ['date', 'category'],   // columnas en el listado del admin
            'title_field' => 'title', 'excerpt_field' => 'excerpt', 'image_field' => 'image',
            'fields' => [
                'title'    => ['type' => 'text', 'label' => 'Título', 'i18n' => true, 'required' => true],
                'excerpt'  => ['type' => 'textarea', 'label' => 'Resumen (listado y buscadores)', 'i18n' => true, 'rows' => 3],
                'body'     => ['type' => 'html', 'label' => 'Contenido', 'i18n' => true, 'size' => 'lg',
                               'help' => 'Escribe como en Word. La barra permite subir imágenes, insertar de la Biblioteca (imagen, PDF o video) y videos de YouTube.'],
                'date'     => ['type' => 'date', 'label' => 'Fecha', 'sidebar' => true],
                'image'    => ['type' => 'image', 'label' => 'Imagen destacada', 'sidebar' => true],
                'category' => ['type' => 'text', 'label' => 'Categoría', 'i18n' => true, 'sidebar' => true],
                'tags'     => ['type' => 'tags', 'label' => 'Etiquetas', 'i18n' => true, 'sidebar' => true],
            ],
        ],
    ],

    'pages' => [
        'contact' => ['label' => 'Contacto', 'routes' => ['es' => 'contacto', 'en' => 'contact'], 'template' => 'contact', 'schema' => 'ContactPage'],
    ],

    // Secciones extra en Ajustes (además de contacto, redes, idiomas y SEO que ya trae el núcleo)
    'settings' => [
        'Portada' => [
            'hero_image' => ['type' => 'image', 'label' => 'Imagen de portada'],
            'home_posts' => ['type' => 'number', 'label' => 'Entradas que se muestran en la portada', 'default' => 3, 'min' => 0, 'max' => 12],
        ],
    ],

    // Agrupación de los textos fijos en Admin → Textos del sitio (opcional)
    'strings_groups' => [
        'Portada' => ['hero_title', 'hero_text', 'hero_button', 'latest_posts'],
        'Blog' => ['posts_title', 'read_more', 'categories', 'recent_posts', 'tags_label', 'search_label', 'search_placeholder', 'blog_empty', 'blog_filter_results', 'blog_filter_clear'],
        'Contacto y formulario' => ['contact_title', 'contact_intro', 'f_name', 'f_email', 'f_message', 'f_send', 'f_sending', 'f_ok', 'f_err', 'f_invalid'],
        'Navegación y pie' => ['crumb_home', 'footer_copy', 'lang_switch', 'not_found_title', 'not_found_text', 'go_home'],
        'SEO' => ['home_meta_title', 'home_meta_desc', 'posts_meta_title', 'posts_meta_desc', 'contact_meta_title', 'contact_meta_desc'],
    ],

    // Formulario de contacto (POST a /_cms/form)
    'form' => ['required' => ['nombre', 'correo'], 'email_field' => 'correo', 'name_field' => 'nombre', 'honeypot' => 'empresa_web2'],
    'max_image_width' => 1800,
];
