<?php
/**
 * Paquete "enlaces": enlazado interno automático. Sin bloques: es solo código (inc.php) con ganchos del núcleo.
 * Al dibujar el contenido convierte en enlace las palabras de una lista "palabra | URL" (hacia tus mejores páginas)
 * y, si se pide, las etiquetas de las colecciones. No modifica los textos guardados.
 */
return [
    'label' => 'Enlaces automáticos',
    'version' => '1.0.0',
    'desc' => 'Enlazado interno automático: al dibujar el contenido convierte en enlace las palabras que elijas ("palabra | URL", hacia tus mejores páginas) y, opcionalmente, las etiquetas de las colecciones. Un enlace por palabra y página, nunca dentro de títulos ni de otros enlaces, y nunca hacia la propia página. Los textos guardados no cambian.',
    'assets' => [],
    'effects' => [],
    'settings' => ['Enlaces automáticos' => [
        'autolink_on'       => ['type' => 'checkbox', 'label' => 'Activar', 'text' => 'Enlazar palabras al dibujar el contenido (artículos, páginas y bloques de texto)'],
        'autolink_words'    => ['type' => 'textarea', 'i18n' => true, 'rows' => 8, 'label' => 'Palabras y destino, una por línea: "palabra | URL"',
                                'placeholder' => "diseño web | /servicios/diseno-web\nCiudad de México | https://es.wikipedia.org/wiki/Ciudad_de_México",
                                'help' => 'Apunta cada palabra a la mejor página del sitio para ese tema (una URL relativa como /servicios/x se ajusta al idioma). Las líneas sin URL no se enlazan. Las frases largas ganan a las cortas cuando se solapan.'],
        'autolink_tags'     => ['type' => 'select', 'label' => 'Etiquetas de las colecciones', 'default' => '',
                                'options' => ['' => 'No enlazar etiquetas (recomendado: los filtros por etiqueta llevan noindex)', 'list' => 'Enlazar cada etiqueta al filtro de su colección (?tag=…)']],
        'autolink_types'    => ['type' => 'text', 'label' => 'Colecciones donde se enlaza (claves separadas por coma; vacío = en todo el sitio)', 'placeholder' => 'posts, proyectos', 'half' => true],
        'autolink_per_term' => ['type' => 'number', 'label' => 'Enlaces por palabra en cada página', 'default' => 1, 'min' => 1, 'max' => 5, 'half' => true],
        'autolink_per_page' => ['type' => 'number', 'label' => 'Máximo de enlaces automáticos por página', 'default' => 10, 'min' => 1, 'max' => 100, 'half' => true],
        'autolink_min_len'  => ['type' => 'number', 'label' => 'Largo mínimo de la palabra (letras)', 'default' => 3, 'min' => 1, 'max' => 20, 'half' => true],
        'autolink_case'     => ['type' => 'checkbox', 'label' => 'Mayúsculas', 'text' => 'Distinguir mayúsculas y minúsculas (por defecto "México" y "méxico" son la misma palabra)'],
        'autolink_blacklist' => ['type' => 'textarea', 'i18n' => true, 'rows' => 3, 'label' => 'Palabras que nunca se enlazan (una por línea)', 'help' => 'Útil para etiquetas muy genéricas cuando se enlazan etiquetas.'],
        'autolink_class'    => ['type' => 'text', 'label' => 'Clase CSS de los enlaces', 'default' => 'cms-autolink', 'placeholder' => 'cms-autolink', 'half' => true],
        'autolink_css'      => ['type' => 'textarea', 'rows' => 2, 'label' => 'CSS propio para esos enlaces (opcional; se emite en el <head>)', 'placeholder' => '.cms-autolink { border-bottom: 1px dotted currentColor; }'],
    ]],
    'item_fields' => ['*' => [
        'autolink_off' => ['type' => 'checkbox', 'label' => 'Enlaces automáticos', 'text' => 'No enlazar palabras en este elemento', 'sidebar' => true],
    ]],
];
