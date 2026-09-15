<?php
/**
 * Paquete "redaccion": artículos y resúmenes de noticias escritos con IA, guardados como borradores (o publicados) en
 * una colección del sitio. Reescritura para cms_simple del plugin de WordPress "AI Content Generator": proveedores por
 * HTTP (OpenRouter, OpenAI, Anthropic, DeepSeek, o "prueba" sin clave), historial en data/redaccion/ y programación con
 * el cron del hosting (/_cms/cron).
 */
$types = [];
foreach ((array) cms_config('types', []) as $k => $d) $types[$k] = (string) ($d['label'] ?? $k);
if (!$types) $types = ['' => 'No hay colecciones en este sitio'];
return [
    'label' => 'Redacción con IA',
    'version' => '1.0.0',
    'desc' => 'Escribe artículos completos sobre los temas que indiques y resúmenes diarios de noticias (Google News por tema, con fuentes), con OpenRouter, OpenAI, Anthropic o DeepSeek. Los guarda como borrador o publicados en la colección que elijas, con imagen generada opcional. Página "Redacción" con historial y costos, y programación diaria o semanal con el cron del hosting. Sin clave, el proveedor de prueba escribe un texto de muestra.',
    'assets' => [],
    'effects' => [],
    'admin' => ['label' => 'Redacción IA', 'file' => 'admin.php'],
    'settings' => ['Redacción con IA' => [
        'rd_provider' => ['type' => 'select', 'label' => 'Proveedor', 'default' => 'prueba',
                          'options' => ['prueba' => 'Prueba (sin clave: texto de muestra)', 'openrouter' => 'OpenRouter (cualquier modelo)', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic (Claude)', 'deepseek' => 'DeepSeek']],
        'rd_model' => ['type' => 'text', 'label' => 'Modelo (vacío = el recomendado del proveedor)', 'placeholder' => 'anthropic/claude-sonnet-5 · gpt-4o-mini · claude-sonnet-5 · deepseek-chat', 'half' => true],
        'rd_openrouter_key' => ['show_if' => ['rd_provider' => 'openrouter'], 'type' => 'text', 'label' => 'OpenRouter: clave (vacío = la del importador de diseño, si la hay)', 'half' => true],
        'rd_openai_key'     => ['type' => 'text', 'label' => 'OpenAI: clave (también para las imágenes)', 'half' => true],
        'rd_anthropic_key'  => ['type' => 'text', 'label' => 'Anthropic: clave', 'half' => true],
        'rd_deepseek_key'   => ['type' => 'text', 'label' => 'DeepSeek: clave', 'half' => true],
        'rd_type'   => ['type' => 'select', 'label' => 'Colección donde se guardan', 'options' => $types, 'default' => (string) array_key_first($types), 'half' => true],
        'rd_status' => ['type' => 'select', 'label' => 'Estado al guardar', 'options' => ['draft' => 'Borrador (para revisar antes de publicar)', 'published' => 'Publicado'], 'default' => 'draft', 'half' => true],
        'rd_lang'   => ['type' => 'text', 'label' => 'Idioma de redacción', 'default' => 'español de México', 'half' => true],
        'rd_category' => ['type' => 'text', 'label' => 'Categoría de los artículos (si la colección la tiene)', 'half' => true],
        'rd_topics' => ['type' => 'textarea', 'rows' => 5, 'label' => 'Temas de artículos, uno por línea', 'placeholder' => "Cómo elegir un CMS para una agencia\nTendencias de diseño web 2026", 'help' => 'Con la programación activa se toma el siguiente tema de la lista cada vez, en orden y sin repetir hasta dar la vuelta.'],
        'rd_min_words' => ['type' => 'number', 'label' => 'Palabras mínimas', 'default' => 800, 'min' => 100, 'max' => 5000, 'half' => true],
        'rd_max_words' => ['type' => 'number', 'label' => 'Palabras máximas', 'default' => 1200, 'min' => 150, 'max' => 8000, 'half' => true],
        'rd_sections'  => ['type' => 'number', 'label' => 'Secciones con subtítulo (mínimo)', 'default' => 4, 'min' => 1, 'max' => 12, 'half' => true],
        'rd_style' => ['type' => 'textarea', 'rows' => 3, 'label' => 'Instrucciones de estilo (opcional)', 'placeholder' => 'Tono cercano, ejemplos concretos, sin anglicismos, cierra con una recomendación.'],
        'rd_news_topics' => ['type' => 'textarea', 'rows' => 4, 'label' => 'Temas del resumen de noticias, uno por línea (vacío = portada general)', 'placeholder' => "inteligencia artificial\nderecho laboral México"],
        'rd_news_query' => ['type' => 'text', 'label' => 'Fuente de noticias por tema (RSS; {topic} se sustituye)', 'default' => 'https://news.google.com/rss/search?q={topic}&hl=es-419&gl=MX&ceid=MX:es-419'],
        'rd_news_hours' => ['type' => 'number', 'label' => 'Noticias de las últimas horas', 'default' => 48, 'min' => 6, 'max' => 240, 'half' => true],
        'rd_news_max'   => ['type' => 'number', 'label' => 'Noticias por tema (máximo)', 'default' => 8, 'min' => 2, 'max' => 20, 'half' => true],
        'rd_news_title' => ['type' => 'text', 'label' => 'Título del resumen ({fecha} se sustituye)', 'default' => 'Resumen de noticias: {fecha}', 'half' => true],
        'rd_news_category' => ['type' => 'text', 'label' => 'Categoría del resumen (si la colección la tiene)', 'default' => 'Noticias', 'half' => true],
        'rd_image' => ['type' => 'select', 'label' => 'Imagen destacada', 'default' => 'none', 'options' => ['none' => 'Sin imagen', 'openai' => 'Generar con OpenAI (usa la clave de OpenAI)'], 'half' => true],
        'rd_image_model' => ['show_if' => ['rd_image' => 'openai'], 'type' => 'text', 'label' => 'Modelo de imagen', 'default' => 'gpt-image-1', 'placeholder' => 'gpt-image-1, dall-e-3', 'half' => true],
        'rd_image_prompt' => ['show_if' => ['rd_image' => 'openai'], 'type' => 'textarea', 'rows' => 2, 'label' => 'Instrucción para la imagen ({topic} se sustituye)', 'default' => 'Ilustración editorial moderna y limpia sobre "{topic}", colores sobrios, sin texto ni logotipos.'],
        'rd_schedule_articles' => ['type' => 'select', 'label' => 'Artículos programados', 'default' => 'off', 'options' => ['off' => 'No', 'daily' => 'Uno al día', 'weekly' => 'Uno por semana'], 'half' => true],
        'rd_schedule_news' => ['type' => 'select', 'label' => 'Resumen de noticias programado', 'default' => 'off', 'options' => ['off' => 'No', 'daily' => 'Uno al día'], 'half' => true],
        'rd_schedule_hour' => ['type' => 'number', 'label' => 'A partir de qué hora (0-23, hora del sitio)', 'default' => 7, 'min' => 0, 'max' => 23, 'half' => true],
    ]],
];
