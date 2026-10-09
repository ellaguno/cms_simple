<?php
/**
 * Paquete "lms": aula en línea sencilla sobre cms_simple. Cursos y lecciones son colecciones normales (se editan en el
 * panel como cualquier otra); el paquete añade alumnos con su cuenta, inscripciones, avance por lección y una página
 * "Aula" en el panel para darlos de alta y seguir su progreso. Todo en JSON bajo data/lms/, sin base de datos.
 * Las vistas públicas usan site_header()/site_footer() del tema y sus clases (.phead, .sec, .card, .btn…), así que
 * toman el aspecto del sitio; el tema puede dar sus propias plantillas cursos.php, curso.php y leccion.php.
 */
// dirección del listado de cursos (Ajustes → Aula); el manifiesto se lee después de cargar los ajustes
$lmsS = function_exists('cms_settings') ? cms_settings() : [];
$lmsRoute = cms_slugify((string) ($lmsS['lms_courses_route'] ?? '')) ?: 'cursos';
// cabecera y pie de las páginas del aula: piezas de Diseño → Cabeceras y pies (si el sitio tiene constructor)
$lmsLayouts = function (string $kind): array {
    $o = $kind === 'footer' ? ['' => 'El del resto del sitio', 'none' => 'El del tema'] : ['' => 'La del resto del sitio', 'none' => 'La del tema'];
    if ($kind === 'header') $o['cabecera-aula'] = 'Cabecera del aula (se crea sola, con menú del aula)';
    if (function_exists('cms_layouts')) foreach (cms_layouts($kind, false) as $slug => $it) $o[$slug] = (string) ($it['title'] ?? $slug) . (($it['status'] ?? '') !== 'published' ? ' (borrador)' : '');
    return $o;
};
$lmsQuizHelp = "Una pregunta por bloque, separadas por una línea en blanco. Primera línea: la pregunta (el número del principio es opcional; {2} al final = vale 2 puntos). Debajo:\n"
    . "  * opción correcta   - opción incorrecta   (varias * = opción múltiple, con crédito parcial)\n"
    . "  = verdadero  o  = falso                    (verdadero/falso)\n"
    . "  = respuesta | otra forma válida           (respuesta corta: sin importar mayúsculas ni acentos)\n"
    . "  = 42  o  = 3.5 ± 0.1                        (numérica, con tolerancia opcional)\n"
    . "  = ?                                        (abierta: la califica el instructor en Aula → Evaluaciones)\n"
    . "  > explicación que ve el alumno al revisar su intento\n"
    . "En el texto de la pregunta: **negritas** y `código`. La vista de la evaluación con sesión en el panel muestra las respuestas correctas y los avisos del formato.";
return [
    'label' => 'Aula: cursos en línea (LMS)',
    'version' => '1.9.0',
    'desc' => 'Cursos con lecciones y evaluaciones, alumnos con cuenta propia, inscripciones y avance. Los alumnos entran en /aula, ven sus cursos con su porcentaje, marcan cada lección como terminada, presentan cuestionarios y exámenes (opción única o múltiple, verdadero/falso, respuesta corta, numérica y abiertas que califica el instructor) y siguen con lo siguiente. El avance de los videos se sigue solo (YouTube, Vimeo o MP4: cuenta lo que de verdad se vio) y puede exigirse antes de marcar la lección. Al terminar un curso, el alumno recibe por correo su constancia para imprimir o guardar en PDF, con código de verificación público. Reproduce paquetes SCORM 1.2 (Articulate, iSpring, Captivate, H5P…) con su avance y calificación, y exporta cada curso como paquete SCORM 1.2 para el LMS de un cliente. El panel gana la página Aula: alta de alumnos (con contraseña generada y aviso por correo opcional), inscripciones por curso, avance y calificaciones de cada alumno, revisión de intentos, preguntas por calificar y exportación CSV. Acceso por curso: abierto, con cuenta o solo inscritos; lecciones de muestra visibles para todos.',
    'assets' => [],
    'effects' => [],
    'admin' => ['label' => 'Alumnos y avance', 'file' => 'admin.php', 'group' => 'Aula'],

    // colecciones: si el tema ya declara 'cursos' o 'lecciones', se usan las suyas (y en Ajustes se dice cuáles son)
    'types' => [
        'cursos' => [
            'label' => 'Cursos', 'label_singular' => 'Curso', 'group' => 'Aula',
            'routes' => ['es' => $lmsRoute, 'en' => $lmsRoute === 'cursos' ? 'courses' : $lmsRoute],
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
                'files'    => ['type' => 'lines', 'label' => 'Materiales del curso (uno por línea: "Texto | ruta o URL")', 'rows' => 3,
                               'placeholder' => "Cuaderno del alumno | capacitacion/iu-102/materiales/cuaderno-alumno.html\nMateriales de práctica | capacitacion/iu-102/materiales/practica.zip",
                               'help' => 'Se ven en la página del curso y en cada lección, a quien puede tomar el curso. Los de una carpeta propia quedan protegidos como los videos.'],
                'topics'   => ['type' => 'tags', 'label' => 'Temas (etiquetas en la tarjeta del curso)', 'i18n' => true],
                'audience' => ['type' => 'text', 'label' => 'Para quién', 'i18n' => true, 'placeholder' => 'Técnico · Funcional · Ventas'],
                'image'    => ['type' => 'image', 'label' => 'Imagen', 'sidebar' => true],
                'access'   => ['type' => 'select', 'label' => 'Acceso a las lecciones', 'sidebar' => true,
                               'options' => ['' => 'El de Ajustes → Aula', 'abierto' => 'Abierto: cualquiera las ve', 'cuenta' => 'Con cuenta: cualquier alumno que entre', 'inscritos' => 'Solo inscritos por el administrador'],
                               'help' => 'Las lecciones marcadas "de muestra" se ven siempre.'],
                'groups'   => ['type' => 'tags', 'label' => 'Solo para los grupos', 'sidebar' => true, 'placeholder' => 'vacío = para todos',
                               'help' => 'Si pones grupos, el curso solo lo ven y lo toman los alumnos de esos grupos (y el panel); para los demás no aparece. Los grupos se ponen en la ficha de cada alumno; los alumnos que entran desde una organización (p. ej. su instancia de Iurefficient) quedan en el grupo de su organización.'],
                'level'    => ['type' => 'text', 'label' => 'Nivel', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Básico, Intermedio…'],
                'duration' => ['type' => 'text', 'label' => 'Duración', 'i18n' => true, 'sidebar' => true, 'placeholder' => '6 horas'],
                'order'    => ['type' => 'number', 'label' => 'Orden en el listado', 'sidebar' => true],
                'soon'     => ['type' => 'checkbox', 'label' => 'Próximamente', 'text' => 'Se anuncia en el listado, pero aún no se puede abrir', 'sidebar' => true],
                'color'    => ['type' => 'color', 'label' => 'Color de la tapa', 'sidebar' => true],
                'icon'     => ['type' => 'select', 'label' => 'Ícono de la tapa', 'sidebar' => true, 'options' => ['' => '— sin ícono —', 'flechas' => 'Flechas (intercambio)', 'nucleo' => 'Núcleo', 'billetes' => 'Billetes', 'grafica' => 'Gráfica', 'libro' => 'Libro', 'escudo' => 'Escudo', 'codigo' => 'Código', 'personas' => 'Personas', 'engrane' => 'Engrane', 'video' => 'Video', 'capas' => 'Capas']],
                'short'    => ['type' => 'text', 'label' => 'Nombre en la tapa', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Arbitraje', 'help' => 'Corto; si se deja vacío va el nombre del curso.'],
                'certificate' => ['type' => 'select', 'label' => 'Constancia al terminar', 'sidebar' => true, 'options' => ['' => 'La de Ajustes → Aula', 'si' => 'Sí', 'no' => 'No']],
                'series'   => ['type' => 'text', 'label' => 'Serie (sobre el nombre en la tapa)', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Curso · NextSabi', 'help' => 'Vacío = la de Ajustes → Aula.'],
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
                'summary'  => ['type' => 'textarea', 'label' => 'De qué trata (se ve bajo el título)', 'i18n' => true, 'rows' => 2],
                'video'    => ['type' => 'text', 'label' => 'Video (opcional)', 'placeholder' => 'https://youtu.be/…, capacitacion/arbitraje/videos/01.mp4, uploads/2026/10/clase.mp4',
                               'help' => 'YouTube, Vimeo o un MP4/WebM: de Medios o de una carpeta de Archivos y carpetas. Los de una carpeta propia quedan protegidos: solo los ve quien tiene acceso a la lección (Ajustes → Aula).'],
                'scorm'    => ['type' => 'text', 'label' => 'Paquete SCORM (opcional)', 'placeholder' => 'scorm/nombre-del-paquete',
                               'help' => 'Un paquete SCORM 1.2 subido en Aula → Alumnos y avance → SCORM. Se reproduce en la lección en lugar del video, guarda su avance y calificación, y la lección se marca sola al completarlo.'],
                'body'     => ['type' => 'html', 'label' => 'Contenido', 'i18n' => true, 'size' => 'lg'],
                'files'    => ['type' => 'lines', 'label' => 'Materiales para descargar (uno por línea: "Texto | ruta o URL")', 'rows' => 4,
                               'placeholder' => "Presentación | uploads/2026/10/presentacion.pdf\nEjercicio | uploads/2026/10/ejercicio.xlsx"],
                'course'   => ['type' => 'select', 'label' => 'Curso', 'sidebar' => true, 'required' => true, 'options' => ['' => '— elige el curso —'], 'options_from' => 'cursos'],
                'module'   => ['type' => 'text', 'label' => 'Módulo', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Módulo 1: Fundamentos'],
                'order'    => ['type' => 'number', 'label' => 'Orden dentro del curso', 'sidebar' => true],
                'duration' => ['type' => 'text', 'label' => 'Duración', 'sidebar' => true, 'placeholder' => '12 min'],
                'audience' => ['type' => 'text', 'label' => 'Para quién', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Todos'],
                'poster'   => ['type' => 'image', 'label' => 'Portada del video', 'sidebar' => true],
                'preview'  => ['type' => 'checkbox', 'label' => 'Lección de muestra', 'text' => 'Visible para todos, sin cuenta ni inscripción', 'sidebar' => true],
            ],
        ],
        'evaluaciones' => [
            'label' => 'Evaluaciones', 'label_singular' => 'Evaluación', 'group' => 'Aula',
            'routes' => ['es' => 'evaluaciones', 'en' => 'quizzes'],
            'template_single' => 'evaluacion', 'no_list' => true, 'noindex' => true, 'feed' => false,
            'sort' => ['field' => 'order', 'dir' => 'asc'], 'list' => ['course', 'module', 'order', 'pass'],
            'title_field' => 'title',
            'help' => 'Cuestionarios y exámenes. Cada evaluación pertenece a un curso y aparece en su temario junto a las lecciones, según "Módulo" y "Orden" (usa el mismo orden que las lecciones: 35 va entre la 3 y la 4). Cuenta para el avance: se da por hecha al aprobarla. Resultados en Aula → Alumnos y avance → Evaluaciones.',
            'fields' => [
                'title'     => ['type' => 'text', 'label' => 'Nombre de la evaluación', 'i18n' => true, 'required' => true, 'placeholder' => 'Repaso del módulo 1, Examen final…'],
                'intro'     => ['type' => 'html', 'label' => 'Instrucciones (se ven antes de empezar)', 'i18n' => true],
                'questions' => ['type' => 'textarea', 'label' => 'Preguntas', 'i18n' => true, 'rows' => 22, 'index' => false, 'help' => $lmsQuizHelp,
                                'placeholder' => "1. ¿Qué protocolo cifra el tráfico de la web?\n- FTP\n* HTTPS\n- SMTP\n> HTTPS es HTTP sobre TLS.\n\n2. Marca los lenguajes de programación\n* PHP\n* Python\n- HTML\n\n3. El agua hierve a 100 °C al nivel del mar.\n= verdadero\n\n4. Explica con tus palabras qué es una API. {3}\n= ?"],
                'course'    => ['type' => 'select', 'label' => 'Curso', 'sidebar' => true, 'required' => true, 'options' => ['' => '— elige el curso —'], 'options_from' => 'cursos'],
                'after'     => ['type' => 'select', 'label' => 'Va después de la lección', 'sidebar' => true, 'options' => ['' => '— por su orden y módulo —'], 'options_from' => 'lecciones', 'options_suffix' => 'course',
                                'help' => 'Si eliges una, la evaluación va justo después de ella y toma su módulo; si no, se ordena con "Módulo" y "Orden" (p. ej. 99 = al final).'],
                'module'    => ['type' => 'text', 'label' => 'Módulo', 'i18n' => true, 'sidebar' => true, 'placeholder' => 'Módulo 1: Fundamentos', 'help' => 'Vacío = el de la lección elegida arriba.'],
                'order'     => ['type' => 'number', 'label' => 'Orden dentro del curso', 'sidebar' => true, 'help' => 'En la misma escala que las lecciones. No hace falta si elegiste la lección.'],
                'pass'      => ['type' => 'number', 'label' => 'Calificación para aprobar (%)', 'sidebar' => true, 'min' => 0, 'max' => 100, 'placeholder' => '70',
                                'help' => 'Vacío = 70. 0 = práctica: cuenta como hecha al enviarla.'],
                'attempts'  => ['type' => 'number', 'label' => 'Intentos permitidos', 'sidebar' => true, 'min' => 0, 'placeholder' => 'sin límite', 'help' => 'Vacío o 0 = sin límite. Desde el panel se puede dar otro intento a un alumno.'],
                'time'      => ['type' => 'number', 'label' => 'Tiempo límite (minutos)', 'sidebar' => true, 'min' => 0, 'placeholder' => 'sin límite',
                                'help' => 'Con tiempo, el alumno pulsa "Empezar" y el reloj corre aunque cierre la página; al acabarse se envía lo que lleve.'],
                'pick'      => ['type' => 'number', 'label' => 'Preguntas por intento', 'sidebar' => true, 'min' => 0, 'placeholder' => 'todas',
                                'help' => 'Para un banco de preguntas: cada intento toma esta cantidad al azar.'],
                'shuffle'   => ['type' => 'checkbox', 'label' => 'Orden', 'text' => 'Mezclar las preguntas y sus opciones en cada intento', 'sidebar' => true],
                'reveal'    => ['type' => 'select', 'label' => 'Al terminar, el alumno ve', 'sidebar' => true,
                                'options' => ['' => 'Aciertos; respuestas correctas al aprobar o al agotar los intentos', 'siempre' => 'Aciertos, respuestas correctas y explicaciones siempre', 'aciertos' => 'Solo qué contestó bien y mal', 'nada' => 'Solo la calificación']],
                'gate'      => ['type' => 'checkbox', 'label' => 'Requisito', 'text' => 'Se abre solo al terminar todo lo anterior del curso', 'sidebar' => true],
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
        'lms_layout_header' => ['type' => 'select', 'label' => 'Cabecera del aula', 'options' => $lmsLayouts('header'), 'default' => 'cabecera-aula', 'half' => true,
                               'help' => 'Para /aula, el listado de cursos, los cursos, las lecciones y las evaluaciones. De serie, «Cabecera del aula»: la cabecera del tema con un menú propio (Inicio, Cursos, Mi cuenta y el botón Mi aula), que se edita en Diseño → Cabeceras y pies. «La del resto del sitio» = la predeterminada de Ajustes → Cabecera y pie.'],
        'lms_layout_footer' => ['type' => 'select', 'label' => 'Pie del aula', 'options' => $lmsLayouts('footer'), 'half' => true],
        'lms_hero_kicker'  => ['type' => 'text', 'i18n' => true, 'label' => 'Listado de cursos: etiqueta del encabezado', 'placeholder' => 'Capacitación interna', 'half' => true],
        'lms_series'       => ['type' => 'text', 'i18n' => true, 'label' => 'Serie en las tapas de los cursos', 'placeholder' => 'Curso · NextSabi', 'half' => true],
        'lms_hero_title'   => ['type' => 'text', 'i18n' => true, 'label' => 'Listado de cursos: título', 'placeholder' => 'Lo que construimos, *explicado por quienes lo construyen*',
                               'help' => 'Lo que va entre *asteriscos* sale resaltado con degradado.'],
        'lms_hero_lead'    => ['type' => 'textarea', 'i18n' => true, 'rows' => 2, 'label' => 'Listado de cursos: texto bajo el título'],
        'lms_hero_numbers' => ['type' => 'checkbox', 'label' => 'Cifras', 'text' => 'Mostrar cifras bajo el título (cursos disponibles, en preparación, lecciones y duración)', 'default' => true],
        'lms_features_title' => ['type' => 'text', 'i18n' => true, 'label' => 'Bloque bajo los cursos: título', 'placeholder' => 'Cómo están hechos', 'half' => true],
        'lms_features_sub' => ['type' => 'text', 'i18n' => true, 'label' => 'Bloque bajo los cursos: subtítulo', 'placeholder' => 'El mismo formato en todos los cursos.', 'half' => true],
        'lms_features'     => ['type' => 'textarea', 'i18n' => true, 'rows' => 4, 'label' => 'Bloque bajo los cursos: una tarjeta por línea, "ícono | título | texto" (vacío = sin bloque)',
                               'placeholder' => "video | Módulos de 10 a 15 minutos | Se ven de corrido o uno al día.\nsubtitulos | Con subtítulos | Se pueden ver en silencio.\ncapas | Pensados para el cliente | Cada curso cierra con lo que preguntará el cliente.",
                               'help' => 'Íconos: video, subtitulos, capas, reloj, libro, personas, escudo, codigo, grafica, engrane, flechas, nucleo, billetes.'],
        'lms_courses_route' => ['type' => 'text', 'label' => 'Dirección del listado de cursos', 'default' => 'cursos', 'placeholder' => 'cursos', 'half' => true,
                               'help' => 'Los cursos quedan en /cursos/<curso>. No uses el nombre de una carpeta de Archivos y carpetas (p. ej. capacitacion): esa carpeta se sirve antes que el aula.'],
        'lms_protect'      => ['type' => 'checkbox', 'label' => 'Videos protegidos', 'default' => true,
                               'text' => 'Los videos y materiales que estén en una carpeta propia (Archivos y carpetas) se sirven solo a quien puede ver la lección: el aula le pone a esa carpeta un .htaccess que corta el acceso directo'],
        'lms_video_mode'   => ['type' => 'select', 'label' => 'Avance de los videos', 'default' => 'auto',
                               'options' => ['auto' => 'La lección se marca sola al ver el video (y también a mano)', 'exigir' => 'Se marca sola, y el botón "Marcar como terminada" espera a que se vea el video', 'manual' => 'Solo a mano, con el botón'],
                               'help' => 'Cuenta lo que de verdad se reproduce (adelantar no cuenta), en videos de YouTube, Vimeo o archivo. Las lecciones sin video se marcan con el botón.'],
        'lms_video_pct'    => ['type' => 'number', 'label' => 'Porcentaje del video que hay que ver', 'default' => 90, 'min' => 10, 'max' => 100, 'placeholder' => '90', 'half' => true],
        'lms_cert'         => ['type' => 'checkbox', 'label' => 'Constancias', 'default' => true, 'text' => 'Al terminar un curso (lecciones y evaluaciones al 100 %) el alumno recibe una constancia con código de verificación; cada curso puede cambiarlo'],
        'lms_cert_title'   => ['type' => 'text', 'i18n' => true, 'label' => 'Constancia: título', 'placeholder' => 'Constancia de terminación', 'half' => true, 'show_if' => ['lms_cert' => '1']],
        'lms_cert_text'    => ['type' => 'text', 'i18n' => true, 'label' => 'Constancia: texto antes del curso', 'placeholder' => 'por haber concluido satisfactoriamente el curso', 'half' => true, 'show_if' => ['lms_cert' => '1']],
        'lms_cert_signer'  => ['type' => 'text', 'label' => 'Constancia: quien firma', 'placeholder' => 'Ing. Ana Pérez', 'half' => true, 'show_if' => ['lms_cert' => '1']],
        'lms_cert_signer_role' => ['type' => 'text', 'i18n' => true, 'label' => 'Constancia: cargo de quien firma', 'placeholder' => 'Directora de capacitación', 'half' => true, 'show_if' => ['lms_cert' => '1']],
        'lms_cert_signature' => ['type' => 'image', 'label' => 'Constancia: imagen de la firma (PNG con fondo transparente)', 'show_if' => ['lms_cert' => '1']],
        'lms_cert_logo'    => ['type' => 'image', 'label' => 'Constancia: logotipo (vacío = el nombre del sitio)', 'show_if' => ['lms_cert' => '1']],
        'lms_cert_color'   => ['type' => 'color', 'label' => 'Constancia: color del marco y el título', 'half' => true, 'show_if' => ['lms_cert' => '1']],
        'lms_notify_to'    => ['type' => 'email', 'label' => 'Avisar de registros nuevos y evaluaciones por calificar a (opcional)', 'half' => true],
        'lms_course_type'  => ['type' => 'text', 'label' => 'Colección de cursos', 'default' => 'cursos', 'placeholder' => 'cursos', 'half' => true,
                               'help' => 'Solo si el tema trae su propia colección de cursos con otra clave.'],
        'lms_lesson_type'  => ['type' => 'text', 'label' => 'Colección de lecciones', 'default' => 'lecciones', 'placeholder' => 'lecciones', 'half' => true],
        'lms_lesson_field' => ['type' => 'text', 'label' => 'Campo de la lección que dice su curso', 'default' => 'course', 'placeholder' => 'course', 'half' => true],
        'lms_quiz_type'    => ['type' => 'text', 'label' => 'Colección de evaluaciones', 'default' => 'evaluaciones', 'placeholder' => 'evaluaciones', 'half' => true,
                               'help' => 'Su campo "course" dice el curso, como en las lecciones.'],
    ]],
];
