<?php
/** Bloques del paquete estructura: cabecera y pie. Los mismos campos que el tema lienzo, para que un sitio pueda pasar de uno a otro. */
return [
    'cabecera' => [
        'label' => 'Cabecera del sitio', 'group' => 'Estructura', 'desc' => 'Logotipo, menú de navegación (con desplegables), botón y barra superior opcional. Para Diseño → Cabeceras y pies.',
        'wrap_class' => 'es-header', 'styles' => ['bg', 'text', 'class', 'hide_mobile'], 'animate' => 'none',
        'sample' => ['topbar' => ['📞 55 1234 5678 | tel:5512345678', 'hola@tumarca.com | mailto:hola@tumarca.com | derecha'], 'name' => 'Tu marca', 'menu' => ['Inicio | /', 'Servicios | #servicios', '- Branding | #branding', '- Sitios web | #web', 'Nosotros | #nosotros', 'Contacto | #contacto'], 'button_text' => 'Cotizar', 'button_url' => '#contacto'],
        'fields' => [
            'topbar'      => ['type' => 'lines', 'i18n' => true, 'label' => 'Barra superior (opcional), un dato por línea: Texto | URL | derecha (para alinear a la derecha)', 'rows' => 3],
            'logo'        => ['type' => 'image', 'label' => 'Logotipo (imagen)', 'half' => true],
            'logo_height' => ['type' => 'number', 'label' => 'Alto del logotipo (px)', 'default' => 40, 'min' => 16, 'max' => 160, 'half' => true],
            'name'        => ['type' => 'text', 'i18n' => true, 'label' => 'Nombre o texto del logotipo (si no hay imagen; vacío = el nombre del sitio)'],
            'menu'        => ['type' => 'lines', 'i18n' => true, 'label' => 'Menú, una entrada por línea: Texto | URL o #ancla. Una línea que empieza con "- " cuelga de la anterior (desplegable). "@menu" pone el menú de Diseño → Menú.', 'rows' => 6, 'default' => ['@menu']],
            'button_text' => ['type' => 'text', 'i18n' => true, 'label' => 'Botón (opcional)', 'half' => true],
            'button_url'  => ['type' => 'text', 'label' => 'URL del botón', 'half' => true],
            'button2_text' => ['type' => 'text', 'i18n' => true, 'label' => 'Segundo botón (opcional, con borde)', 'half' => true],
            'button2_url' => ['type' => 'text', 'label' => 'URL del segundo botón', 'half' => true],
            'langs'       => ['type' => 'checkbox', 'label' => 'Idiomas', 'text' => 'Mostrar el conmutador de idioma (si el sitio tiene más de uno)', 'default' => true],
            'sticky'      => ['type' => 'checkbox', 'label' => 'Posición', 'text' => 'Fija al hacer scroll', 'default' => true],
            'transparent' => ['type' => 'checkbox', 'label' => 'Fondo', 'text' => 'Transparente sobre la primera sección de la página (toma color al hacer scroll)'],
        ],
    ],
    'imagen' => [
        'label' => 'Imagen', 'group' => 'Contenido', 'desc' => 'Una imagen con tamaño, alineación, pie y enlace. Sirve en cualquier tema.',
        'wrap_class' => 'es-image', 'styles' => ['bg', 'pad', 'width', 'anchor', 'class', 'hide_mobile'],
        'sample' => ['image' => 'cms/assets/img/demo/foto-2.jpg', 'caption' => 'Un pie de imagen opcional', 'size' => 'm'],
        'fields' => [
            'image'   => ['type' => 'image', 'label' => 'Imagen', 'required' => true],
            'alt'     => ['type' => 'text', 'i18n' => true, 'label' => 'Texto alternativo (accesibilidad y SEO)'],
            'caption' => ['type' => 'text', 'i18n' => true, 'label' => 'Pie de imagen (opcional)', 'half' => true],
            'link'    => ['type' => 'text', 'label' => 'Enlace al hacer clic (opcional)', 'half' => true],
            'size'    => ['type' => 'select', 'label' => 'Tamaño', 'half' => true, 'options' => ['' => 'Ancho del contenido', 'orig' => 'Tamaño original (sin agrandar)', 'xs' => 'Pequeña (320 px)', 's' => 'Mediana (480 px)', 'm' => 'Grande (720 px)', 'l' => 'Muy grande (960 px)']],
            'align'   => ['type' => 'select', 'label' => 'Alineación', 'half' => true, 'options' => ['' => 'Centrada', 'left' => 'Izquierda', 'right' => 'Derecha']],
            'radius'  => ['type' => 'checkbox', 'label' => 'Esquinas', 'text' => 'Redondeadas', 'default' => true],
        ],
    ],
    'html' => [
        'label' => 'HTML libre', 'group' => 'Contenido', 'desc' => 'Código HTML tal cual, sin contenedor: incrustados, mapas, o una sección convertida a HTML para retocarla a mano.',
        'wrap_class' => 'es-html', 'styles' => ['anchor', 'class', 'hide_mobile'], 'animate' => 'none',
        'sample' => ['code' => '<div style="padding:2rem;border:2px dashed #999;border-radius:12px;text-align:center">Tu HTML tal cual</div>'],
        'fields' => ['code' => ['type' => 'code', 'label' => 'HTML', 'rows' => 14, 'i18n' => true, 'help' => 'Se imprime tal cual (con sus etiquetas <script> y <style> si las trae). Cualquier sección del constructor se puede convertir a este bloque con el botón </> de su tarjeta.']],
    ],
    'pie' => [
        'label' => 'Pie de página', 'group' => 'Estructura', 'desc' => 'Logotipo o nombre, texto, columnas de enlaces, contacto, redes y derechos. Para Diseño → Cabeceras y pies.',
        'wrap_class' => 'es-footer', 'styles' => ['bg', 'text', 'pad', 'class'], 'animate' => 'none',
        'sample' => ['name' => 'Tu marca', 'text' => 'Diseño y desarrollo con calma y buen gusto.', 'columns' => ['Empresa | Nosotros > # | Equipo > # | Blog > #', 'Servicios | Branding > # | Sitios web > # | Contenido > #'], 'contact' => ['hola@tumarca.com', '55 1234 5678', 'Ciudad de México'], 'social' => ['@ajustes']],
        'fields' => [
            'logo'    => ['type' => 'image', 'label' => 'Logotipo (imagen)', 'half' => true],
            'name'    => ['type' => 'text', 'i18n' => true, 'label' => 'Nombre (vacío = el del sitio)', 'half' => true],
            'text'    => ['type' => 'textarea', 'i18n' => true, 'label' => 'Texto breve bajo el nombre', 'rows' => 2],
            'columns' => ['type' => 'lines', 'i18n' => true, 'label' => 'Columnas de enlaces, una por línea: Título | Enlace > URL | Enlace > URL. "@menu" en una línea pone el menú del sitio como columna.', 'rows' => 4],
            'contact' => ['type' => 'lines', 'i18n' => true, 'label' => 'Datos de contacto, uno por línea (correo, teléfono, dirección). "@ajustes" pone el correo, teléfono y WhatsApp de Ajustes.', 'rows' => 3, 'default' => ['@ajustes']],
            'contact_title' => ['type' => 'text', 'i18n' => true, 'label' => 'Título de la columna de contacto', 'default' => 'Contacto', 'half' => true],
            'social'  => ['type' => 'lines', 'label' => 'Redes, una por línea: Nombre | URL. "@ajustes" pone las redes de Ajustes.', 'rows' => 3, 'default' => ['@ajustes'], 'half' => true],
            'copy'    => ['type' => 'text', 'i18n' => true, 'label' => 'Línea de derechos ({year} = año actual)', 'default' => '© {year} Todos los derechos reservados'],
            'bottom'  => ['type' => 'lines', 'i18n' => true, 'label' => 'Enlaces de la línea inferior (privacidad, términos…), uno por línea: Texto | URL', 'rows' => 2],
        ],
    ],
];
