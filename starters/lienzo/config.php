<?php
/**
 * Tema "lienzo": un sitio en blanco para el constructor de páginas.
 * No trae cabecera ni pie propios: son bloques (cabecera, pie) que se ponen en cada página, de modo que un diseño
 * importado desde un PDF genera el sitio completo. Cuando el sitio crece, la cabecera y el pie se comparten con
 * 'site_sections' (ver LEEME.md). Copia esta carpeta como site/ de un sitio nuevo.
 */
return [
    'name' => 'Lienzo',
    'langs' => ['es'],
    'default_lang' => 'es',
    'timezone' => 'America/Mexico_City',
    'admin_logo' => '',
    'site_url' => '',

    'types' => [
        'paginas' => [
            'label' => 'Páginas', 'label_singular' => 'Página', 'group' => 'Páginas',
            'routes' => ['es' => ''],           // en la raíz: /ruta-de-la-pagina
            'tree' => true,                     // páginas anidadas (padre/hijo)
            'template_single' => 'pagina',
            'schema' => 'WebPage',
            'sort' => ['field' => 'order', 'dir' => 'asc'],
            'list' => ['status', 'updated'],
            'title_field' => 'title', 'excerpt_field' => 'summary', 'image_field' => 'image',
            'fields' => [
                'title'    => ['type' => 'text', 'i18n' => true, 'label' => 'Título', 'required' => true],
                'sections' => ['type' => 'sections', 'label' => 'Secciones de la página',
                               'help' => 'La página se arma con secciones: cabecera, hero, texto, tarjetas, galería, preguntas, llamado a la acción, pie…'],
                'summary'  => ['type' => 'textarea', 'i18n' => true, 'label' => 'Descripción para buscadores (opcional)', 'rows' => 2, 'sidebar' => true],
                'image'    => ['type' => 'image', 'label' => 'Imagen para redes (opcional)', 'sidebar' => true],
                'order'    => ['type' => 'number', 'label' => 'Orden', 'sidebar' => true],
            ],
        ],
    ],
    'home_item' => ['paginas', 'inicio'],   // la página con URL "inicio" es la portada (/)
    'pages' => [],

    // Paquetes de bloques y efectos compartidos (cms/packs): galería 3D, carrusel, lightbox, marquesina, cifras…
    'packs' => ['visual', 'motion', 'media', 'marketing', 'contenido'],
    'sections' => [
        'palette' => ['white' => 'Blanco', 'light' => 'Gris claro', 'dark' => 'Oscuro', 'primary' => 'Color principal', 'accent' => 'Color de acento'],
        'classes' => ['container' => 'lz-container', 'header' => 'lz-head', 'title' => '', 'subtitle' => '', 'btn' => 'lz-btn'],
        'accent_vars' => ['--lz-accent'],   // el color de acento por sección también cambia el del tema
    ],

    'settings' => [
        'Cabecera y pie compartidos' => [
            'shared_on'     => ['type' => 'checkbox', 'label' => 'Compartir', 'text' => 'Usar en todas las páginas la cabecera y el pie de la página de origen'],
            'shared_source' => ['type' => 'text', 'label' => 'URL (slug) de la página de origen', 'placeholder' => 'inicio',
                                'help' => 'Se toman su primera sección "Cabecera del sitio" y su última "Pie de página"; se editan ahí, en el constructor. Las demás páginas dejan de mostrar sus propias cabecera y pie.'],
        ],
        'Diseño' => [
            'color_primary' => ['type' => 'color', 'label' => 'Color principal (títulos, cabecera oscura, pie)', 'placeholder' => '#111827 (el del tema)', 'half' => true],
            'color_accent'  => ['type' => 'color', 'label' => 'Color de acento (botones, enlaces, resaltados)', 'placeholder' => '#2563eb (el del tema)', 'half' => true],
            'font_heading'  => ['type' => 'select', 'label' => 'Tipografía de títulos (Google Fonts)', 'options' => ['' => 'La misma que el texto', 'Inter' => 'Inter', 'Poppins' => 'Poppins', 'Montserrat' => 'Montserrat', 'Manrope' => 'Manrope', 'DM Sans' => 'DM Sans', 'Plus Jakarta Sans' => 'Plus Jakarta Sans', 'Space Grotesk' => 'Space Grotesk', 'Sora' => 'Sora', 'Outfit' => 'Outfit', 'Raleway' => 'Raleway', 'Playfair Display' => 'Playfair Display', 'Merriweather' => 'Merriweather', 'Lora' => 'Lora', 'Fraunces' => 'Fraunces'], 'half' => true],
            'font'          => ['type' => 'select', 'label' => 'Tipografía del texto (Google Fonts)', 'options' => ['' => 'Inter', 'Inter' => 'Inter', 'Poppins' => 'Poppins', 'Montserrat' => 'Montserrat', 'Manrope' => 'Manrope', 'DM Sans' => 'DM Sans', 'Plus Jakarta Sans' => 'Plus Jakarta Sans', 'Nunito' => 'Nunito', 'Open Sans' => 'Open Sans', 'Lato' => 'Lato', 'Roboto' => 'Roboto', 'Source Sans 3' => 'Source Sans 3', 'Work Sans' => 'Work Sans', 'Lora' => 'Lora', 'Merriweather' => 'Merriweather'], 'half' => true],
            'font_custom'   => ['type' => 'text', 'label' => 'Otra tipografía de Google Fonts (nombre exacto; tiene prioridad para el texto)', 'placeholder' => 'Figtree'],
            'font_heading_custom' => ['type' => 'text', 'label' => 'Otra tipografía para títulos', 'placeholder' => 'Cormorant Garamond'],
        ],
    ],
    'strings_groups' => ['Navegación' => ['not_found_title', 'not_found_text', 'go_home', 'f_ok', 'f_err']],
    'form' => ['required' => ['correo'], 'email_field' => 'correo', 'name_field' => 'nombre', 'honeypot' => 'empresa_web2'],
    'max_image_width' => 1800,
    'code_editor' => true,
];
