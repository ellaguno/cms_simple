<?php
/**
 * Paquete "lms": aula en línea sencilla sobre cms_simple. Cursos y lecciones son colecciones normales (se editan en el
 * panel como cualquier otra); el paquete añade alumnos con su cuenta, inscripciones, avance por lección y una página
 * "Aula" en el panel para darlos de alta y seguir su progreso. Todo en JSON bajo data/lms/, sin base de datos.
 * Las vistas públicas usan site_header()/site_footer() del tema y sus clases (.phead, .sec, .card, .btn…), así que
 * toman el aspecto del sitio; el tema puede dar sus propias plantillas cursos.php, curso.php y leccion.php.
 */
return [
    'label' => 'Aula: cursos en línea (LMS)',
    'version' => '1.0.0',
    'desc' => 'Cursos con lecciones, alumnos con cuenta propia, inscripciones y avance por lección. Los alumnos entran en /aula, ven sus cursos con su porcentaje, marcan cada lección como terminada y siguen con la siguiente. El panel gana la página Aula: alta de alumnos (con contraseña generada y aviso por correo opcional), inscripciones por curso, avance de cada alumno y exportación CSV. Acceso por curso: abierto, con cuenta o solo inscritos; lecciones de muestra visibles para todos. Preparado para exámenes y calificaciones en una versión siguiente.',
    'assets' => [],
    'effects' => [],
    'admin' => ['label' => 'Alumnos y avance', 'file' => 'admin.php', 'group' => 'Aula'],

    // colecciones: si el tema ya declara 'cursos' o 'lecciones', se usan las suyas (y en Ajustes se dice cuáles son)
    'types' => [
        'cursos' => [
            'label' => 'Cursos', 'label_singular' => 'Curso', 'group' => 'Aula',
            'routes' => ['es' => 'cursos', 'en' => 'courses'],
            'template_list' => 'cursos', 'template_single' => 'curso',
            'schema' => 'Course', 'feed' => false,
            'sort' => ['field' => 'order', 'dir' => 'asc'], 'list' => ['access', 'order'],
            'title_field' => 'title', 'excerpt_field' => 'excerpt', 'image_field' => 'image',
            'help' => 'Cada curso agrupa lecciones (Aula → Lecciones, campo "Curso"). Quién puede ver las lecciones se decide en "Acceso".',
            'fields' => [
                'title'    => ['type' => 'text', 'label' => 'Nombre del curso', 'i18n' => true, 'required' => true],
                'excerpt'  => ['type' => 'textarea', 'label' => 'Resumen (listado y buscadores)', 'i18n' => true, 'rows' => 3],
                'body'     => ['type' => 'html', 'label' => 'Descripción', 'i18n' => true,
                               'help' => 'A quién va dirigido, requisitos, temario general. Se ve en la página del curso, antes de inscribirse.'],
                'goals'    => ['type' => 'lines', 'label' => 'Lo que se aprende (uno por línea)', 'i18n' => true, 'rows' => 5],
                'image'    => ['type' => 'image', 'label' => 'Imagen', 'sidebar' => true],
                'access'   => ['type' => 'select', 'label' => 'Acceso a las lecciones', 'sidebar' => true,
                               'options' => ['' => 'El de Ajustes → Aula', 'abierto' => 'Abierto: cualquiera las ve', 'cuenta' => 'Con cuenta: cualquier alumno que entre', 'inscritos' => 'Solo inscritos por el administrador'],
                               'help' => 'Las lecciones marcadas "de muestra" se ven siempre.'],
                'level'    => ['type' => 'text', 'label' => 'Nivel', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Básico, Intermedio…'],
                'duration' => ['type' => 'text', 'label' => 'Duración', 'i18n' => true, 'sidebar' => true, 'placeholder' => '6 horas'],
                'order'    => ['type' => 'number', 'label' => 'Orden en el listado', 'sidebar' => true],
            ],
        ],
        'lecciones' => [
            'label' => 'Lecciones', 'label_singular' => 'Lección', 'group' => 'Aula',
            'routes' => ['es' => 'lecciones', 'en' => 'lessons'],
            'template_single' => 'leccion', 'no_list' => true, 'noindex' => true, 'feed' => false,
            'sort' => ['field' => 'order', 'dir' => 'asc'], 'list' => ['course', 'module', 'order'],
            'title_field' => 'title',
            'help' => 'Cada lección pertenece a un curso y se ordena con "Orden". "Módulo" agrupa lecciones en el temario (Módulo 1, Módulo 2…).',
            'fields' => [
                'title'    => ['type' => 'text', 'label' => 'Título de la lección', 'i18n' => true, 'required' => true],
                'video'    => ['type' => 'text', 'label' => 'Video (opcional)', 'placeholder' => 'https://youtu.be/…, https://vimeo.com/…, o uploads/2026/10/clase.mp4',
                               'help' => 'YouTube, Vimeo o un MP4/WebM de Medios. Se pone arriba del texto.'],
                'body'     => ['type' => 'html', 'label' => 'Contenido', 'i18n' => true, 'size' => 'lg'],
                'files'    => ['type' => 'lines', 'label' => 'Materiales para descargar (uno por línea: "Texto | ruta o URL")', 'rows' => 4,
                               'placeholder' => "Presentación | uploads/2026/10/presentacion.pdf\nEjercicio | uploads/2026/10/ejercicio.xlsx"],
                'course'   => ['type' => 'select', 'label' => 'Curso', 'sidebar' => true, 'required' => true, 'options' => ['' => '— elige el curso —'], 'options_from' => 'cursos'],
                'module'   => ['type' => 'text', 'label' => 'Módulo', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Módulo 1: Fundamentos'],
                'order'    => ['type' => 'number', 'label' => 'Orden dentro del curso', 'sidebar' => true],
                'duration' => ['type' => 'text', 'label' => 'Duración', 'sidebar' => true, 'placeholder' => '12 min'],
                'preview'  => ['type' => 'checkbox', 'label' => 'Lección de muestra', 'text' => 'Visible para todos, sin cuenta ni inscripción', 'sidebar' => true],
            ],
        ],
    ],

    'settings' => ['Aula (cursos en línea)' => [
        'lms_access'       => ['type' => 'select', 'label' => 'Acceso a las lecciones (por omisión; cada curso puede cambiarlo)', 'default' => 'cuenta',
                               'options' => ['abierto' => 'Abierto: cualquiera las ve (el avance se guarda solo con cuenta)', 'cuenta' => 'Con cuenta: cualquier alumno que entre al aula', 'inscritos' => 'Solo alumnos inscritos en ese curso']],
        'lms_signup'       => ['type' => 'checkbox', 'label' => 'Registro', 'text' => 'Los visitantes pueden crear su propia cuenta en /aula/registro (si no, solo el administrador da de alta alumnos)'],
        'lms_signup_code'  => ['type' => 'text', 'label' => 'Código de invitación para registrarse (opcional)', 'half' => true, 'show_if' => ['lms_signup' => '1'],
                               'help' => 'Si lo pones, solo se registra quien lo conozca (útil para un grupo o un cliente).'],
        'lms_route'        => ['type' => 'text', 'label' => 'Dirección del aula', 'default' => 'aula', 'placeholder' => 'aula', 'half' => true,
                               'help' => 'Las páginas del alumno quedan en /aula, /aula/entrar, /aula/cuenta… Añade "/aula" al Menú para que se vea.'],
        'lms_notify_to'    => ['type' => 'email', 'label' => 'Avisar de cada registro nuevo a (opcional)', 'half' => true],
        'lms_course_type'  => ['type' => 'text', 'label' => 'Colección de cursos', 'default' => 'cursos', 'placeholder' => 'cursos', 'half' => true,
                               'help' => 'Solo si el tema trae su propia colección de cursos con otra clave.'],
        'lms_lesson_type'  => ['type' => 'text', 'label' => 'Colección de lecciones', 'default' => 'lecciones', 'placeholder' => 'lecciones', 'half' => true],
        'lms_lesson_field' => ['type' => 'text', 'label' => 'Campo de la lección que dice su curso', 'default' => 'course', 'placeholder' => 'course', 'half' => true],
    ]],
];
