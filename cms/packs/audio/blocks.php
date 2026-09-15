<?php
/** Bloque del paquete "audio": reproductor del MP3 del elemento actual o de un archivo que se indique. */
declare(strict_types=1);
require_once __DIR__ . '/inc.php';
return [
    'reproductor' => [
        'label' => 'Reproductor de audio', 'group' => 'Medios',
        'desc' => 'El audio (MP3) de esta página, generado con "Generar audio", o un archivo que indiques. Con texto al lado y descarga.',
        'wrap_class' => 'cms-sec',
        'fields' => [
            'label' => ['type' => 'text', 'label' => 'Texto junto al reproductor (vacío = el de Ajustes)', 'i18n' => true, 'placeholder' => 'Escucha esta página'],
            'file'  => ['type' => 'text', 'label' => 'Archivo MP3 (vacío = el audio de esta página en el idioma que se ve)', 'placeholder' => 'uploads/audio/… o https://…'],
        ],
        'styles' => ['bg', 'pad', 'width', 'anchor', 'class'],
        'sample' => ['label' => 'Escucha esta página', 'file' => 'cms/packs/audio/assets/silencio.mp3'],
        'assets' => ['css' => ['assets/audio.css']],
    ],
];
