<?php
/**
 * Paquete "audio": texto a voz. Convierte artículos y páginas en MP3 con OpenAI, ElevenLabs, Azure o Google
 * (o un proveedor de prueba sin clave) y pone un reproductor al principio o al final del texto.
 * Reescritura para cms_simple del plugin de WordPress "TTS SesoLibre": sin SDK, solo HTTP; los MP3 van a uploads/audio/.
 */
$voicesOpenAI = ['alloy' => 'Alloy', 'ash' => 'Ash', 'ballad' => 'Ballad', 'coral' => 'Coral', 'echo' => 'Echo', 'fable' => 'Fable', 'nova' => 'Nova', 'onyx' => 'Onyx', 'sage' => 'Sage', 'shimmer' => 'Shimmer'];
return [
    'label' => 'Audio: texto a voz',
    'version' => '1.0.0',
    'desc' => 'Convierte artículos y páginas en audio (MP3) con OpenAI, ElevenLabs, Azure o Google Cloud, con un reproductor al principio o al final del texto. Botón "Generar audio" en el editor (por idioma), generación automática al publicar (opcional), página Audio con el estado de cada colección y bloque "Reproductor" para el constructor. Sin clave, el proveedor de prueba genera un MP3 en silencio para probar el flujo.',
    'assets' => ['css' => ['assets/audio.css']],
    'effects' => [],
    'admin' => ['label' => 'Audio', 'file' => 'admin.php'],
    'settings' => ['Audio: texto a voz' => [
        'audio_provider'  => ['type' => 'select', 'label' => 'Proveedor', 'default' => 'prueba',
                              'options' => ['prueba' => 'Prueba (sin clave: MP3 en silencio, para probar el flujo)', 'openai' => 'OpenAI', 'elevenlabs' => 'ElevenLabs', 'azure' => 'Azure (Microsoft)', 'google' => 'Google Cloud Text-to-Speech']],
        'audio_openai_key'   => ['type' => 'text', 'label' => 'OpenAI: clave (sk-…)', 'half' => true],
        'audio_openai_voice' => ['type' => 'select', 'label' => 'OpenAI: voz', 'options' => $voicesOpenAI, 'default' => 'nova', 'half' => true],
        'audio_openai_model' => ['type' => 'text', 'label' => 'OpenAI: modelo', 'default' => 'gpt-4o-mini-tts', 'placeholder' => 'gpt-4o-mini-tts, tts-1, tts-1-hd', 'half' => true],
        'audio_openai_instructions' => ['type' => 'text', 'label' => 'OpenAI: instrucciones de tono (solo gpt-4o-mini-tts, opcional)', 'placeholder' => 'Lee con calma, tono cercano, en español de México', 'half' => true],
        'audio_elevenlabs_key'   => ['type' => 'text', 'label' => 'ElevenLabs: clave', 'half' => true],
        'audio_elevenlabs_voice' => ['type' => 'text', 'label' => 'ElevenLabs: ID de la voz', 'default' => 'pNInz6obpgDQGcFmaJgB', 'help' => 'Los ID están en la biblioteca de voces de ElevenLabs.', 'half' => true],
        'audio_elevenlabs_model' => ['type' => 'text', 'label' => 'ElevenLabs: modelo', 'default' => 'eleven_multilingual_v2', 'half' => true],
        'audio_azure_key'    => ['type' => 'text', 'label' => 'Azure: clave', 'half' => true],
        'audio_azure_region' => ['type' => 'text', 'label' => 'Azure: región', 'default' => 'eastus', 'placeholder' => 'eastus, westeurope…', 'half' => true],
        'audio_azure_voice'  => ['type' => 'text', 'label' => 'Azure: voz', 'default' => 'es-MX-DaliaNeural', 'placeholder' => 'es-MX-DaliaNeural, es-MX-JorgeNeural, en-US-JennyNeural', 'half' => true],
        'audio_google_key'   => ['type' => 'text', 'label' => 'Google Cloud: clave de API', 'half' => true],
        'audio_google_voice' => ['type' => 'text', 'label' => 'Google Cloud: voz', 'default' => 'es-US-Neural2-A', 'placeholder' => 'es-US-Neural2-A, es-ES-Neural2-B, en-US-Neural2-C', 'half' => true],
        'audio_types'   => ['type' => 'text', 'label' => 'Colecciones con audio (claves separadas por coma; vacío = todas)', 'placeholder' => 'posts', 'half' => true],
        'audio_player'  => ['type' => 'select', 'label' => 'Reproductor en el texto', 'default' => 'top',
                            'options' => ['top' => 'Al principio del texto', 'bottom' => 'Al final del texto', 'none' => 'No poner (solo con el bloque Reproductor o desde el tema)'], 'half' => true],
        'audio_label'   => ['type' => 'text', 'i18n' => true, 'label' => 'Texto junto al reproductor', 'placeholder' => 'Escucha este artículo', 'half' => true],
        'audio_auto'    => ['type' => 'checkbox', 'label' => 'Automático', 'text' => 'Generar el audio al publicar un elemento que no lo tenga (tarda unos segundos más al guardar)'],
        'audio_title'   => ['type' => 'checkbox', 'label' => 'Título', 'text' => 'Leer el título antes del texto', 'default' => true],
        'audio_max_chars' => ['type' => 'number', 'label' => 'Máximo de caracteres a convertir (controla el costo)', 'default' => 15000, 'min' => 500, 'max' => 100000, 'half' => true],
    ]],
    'item_fields' => ['*' => [
        'audio' => ['type' => 'text', 'i18n' => true, 'label' => 'Audio (MP3 por idioma)', 'placeholder' => 'uploads/audio/…', 'sidebar' => true,
                    'help' => 'Se llena con "Generar audio" (más abajo). También puedes escribir la ruta de un MP3 propio subido por FTP a uploads/.'],
    ]],
];
