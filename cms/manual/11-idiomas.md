# 11. Idiomas: un sitio en español e inglés

> Cómo funciona el sitio bilingüe, qué se traduce desde el panel y qué decide quien programa el tema.

## Cómo está construido

Un sitio puede tener varios idiomas. El primero es el predeterminado y vive en la raíz, `tudominio.com/`; los demás llevan prefijo, `tudominio.com/en/`. Cada página tiene su versión en cada idioma con la misma estructura: mismas secciones, mismos bloques, textos distintos. Los buscadores reciben la relación entre versiones de forma automática, y el selector ES / EN de la cabecera lleva a la misma página en el otro idioma.

La regla que evita trabajo doble: **cuando una traducción falta, se muestra el texto del idioma predeterminado.** Un artículo escrito solo en español aparece en español dentro del sitio en inglés, en lugar de desaparecer o quedar vacío. Así puedes activar un idioma antes de tener todo traducido, y traducir poco a poco.

## Qué se traduce desde el panel

Todo campo de texto marcado como bilingüe muestra un conmutador **Español / English** en el editor. Se escribe un idioma a la vez; al cambiar, los campos muestran el otro idioma con el texto del predeterminado como referencia. Al guardar se guardan ambos.

- **Páginas del constructor**: título, descripción para buscadores y los textos de cada sección.
- **Artículos, legales, planes, preguntas frecuentes, equipo**: sus campos de texto.
- **Textos del sitio**: cada texto fijo tiene una casilla por idioma.
- **Menú**: se edita por idioma en la misma pantalla.
- **Ajustes → Menú de la landing para abogados**: hay un campo para las líneas en inglés.

Lo que no cambia por idioma: imágenes, URL de páginas y colecciones, videos, precios y ajustes generales. La excepción son las **lecciones del aula**: su video y la portada del video sí tienen una casilla por idioma (ver abajo).

## Cursos del aula en dos idiomas

Con el paquete **Aula** un curso es **el mismo** en los dos idiomas: mismas lecciones, mismas evaluaciones y la misma dirección (solo cambia el prefijo `/en/`). Lo que cambia es el texto y, si quieres, el video:

- En el editor de cada lección, **Video** y **Portada del video** tienen su casilla ES / EN. Si la de inglés queda vacía, en `/en/` se ve el video en español.
- Los videos protegidos (los de una carpeta propia) siguen protegidos en los dos idiomas: la página en inglés pide el suyo por `/en/aula/video`, y solo lo recibe quien puede ver la lección.
- **El avance es uno solo.** Un alumno que empieza en español y sigue en inglés conserva sus lecciones terminadas, sus intentos de evaluación y su constancia: la lección es la misma, solo cambia el idioma en que la ve.
- Al exportar un curso como paquete SCORM, se eligen el idioma y sus videos.

### Importar la versión en inglés de un curso

El importador del aula (Aula → Alumnos y avance → Importar) busca una subcarpeta **`en/`** junto al curso, con la misma forma que la versión en español, siempre que el inglés esté activo en Ajustes → Idiomas:

```
capacitacion/iu-102/            (la versión en español, como siempre)
  index.html                    MODULOS, videos/01.mp4…
  contenido/leccion-01.html
  evaluaciones/IU-102-L01.txt
  curso.json
  en/                           la versión en inglés (todo opcional)
    curso.json                  nombre, resumen, descripción, objetivos, temas, nivel, módulo… en inglés
    index.html                  MODULOS en inglés (titulo, texto, para, archivo), en el mismo orden
    contenido/leccion-01.html   contenido de la lección 1 en inglés
    evaluaciones/IU-102-L01.txt la misma evaluación en inglés (mismo nombre de archivo)
    videos/01.mp4               el video de la lección 1 en inglés (mismo nombre que el de español)
    portadas/01.jpg             su portada
    materiales/…                materiales en inglés (se agregan al curso con «(EN)»)
```

También sirve `aula/<curso>/en/`, al lado de `aula/<curso>/` (como lo entrega `publicar_curso.py`). Todo va al campo EN del mismo curso, de las mismas lecciones y de las mismas evaluaciones: **el español no se toca**. Lo que ya estaba escrito en inglés se respeta, salvo que marques «Reemplazar».

**El video en inglés de la lección NN** se toma, en este orden:

1. `en/curso.json` → `"lecciones": {"1": {"video": "…", "portada": "…"}}`
2. `en/curso.json` → `"videos": {"1": "videos/clase-1-en.mp4", "2": "https://youtu.be/…"}` (o una lista en orden)
3. el `archivo` de la lección NN en la lista `MODULOS` de `en/index.html`
4. `en/videos/<mismo nombre que el video en español>` (si existe)

En `curso.json`, una ruta relativa es desde la carpeta `en/`; con `/` al principio, desde la raíz del sitio; una URL va tal cual. La portada sale de `en/portadas/<nombre del video>.jpg` si no se indica. Guarda los videos en `en/videos/` y no sueltos en `en/`: la carpeta de un video protegido queda sin acceso directo, y con el video suelto en `en/` también quedarían bloqueadas las portadas.

Las evaluaciones en inglés se emparejan por **nombre de archivo** con las de español; una que no tenga pareja se omite con un aviso. Su título sale de la cabecera `titulo:` del archivo (o de `curso.json`); si no lo trae, «Lesson N · …» o «Final quiz · …».

## Activar o desactivar un idioma

En **Ajustes → Idiomas** se marca cuáles están activos para el público. Un idioma desactivado sigue editándose en el panel, pero sus páginas no responden en el sitio ni entran al sitemap. Es la forma de trabajar una traducción con calma y publicarla de golpe.

## Que cada visitante entre en su idioma

En **Ajustes → General → Idioma según el visitante** eliges cómo se decide el idioma de quien llega:

- **No** (predeterminado): todos entran al idioma principal y cambian con el selector.
- **Según su navegador**: se usa el idioma configurado en el navegador del visitante, si ese idioma está activo en el sitio.
- **Según su país**: se usa el país desde el que visita. Requiere que el sitio pase por **Cloudflare**, que es quien informa el país; sin Cloudflare esta opción no hace nada.
- **Navegador y, si no coincide ninguno, país**: lo más completo.

Cómo se comporta:

- Solo actúa cuando alguien **llega desde fuera** (un buscador, un enlace, la barra de direcciones) a una página del idioma principal. Si corresponde, pasa a la **misma página** en su idioma, siempre que esa página tenga traducción.
- **Lo que el visitante elige, se queda.** Al cambiar de idioma con el selector, el sitio lo recuerda en una cookie durante un año, y en sus siguientes visitas entra en ese idioma aunque su navegador diga otro.
- Un enlace con prefijo (`/en/…`) siempre abre en su idioma: si compartes una página en inglés, se ve en inglés.
- Los buscadores nunca se redirigen: cada idioma sigue indexándose en su propia URL.

## Por qué no aparece la opción de "crear un idioma" en el panel

Añadir un idioma cambia la estructura del sitio, no solo el contenido: hay que decidir el código y la URL, marcar qué campos del tema se traducen, y escribir los textos fijos del diseño en el idioma nuevo. Eso lo declara quien programa el tema en `site/config.php`, en la lista `langs`, y a partir de ahí el panel muestra el conmutador en todos los campos bilingües y la casilla en Ajustes → Idiomas. Es un trabajo de una vez por idioma; después, todo es contenido.

## Consejos para traducir bien

1. **Traduce primero lo estructural**: menú, textos del sitio, portada, páginas principales, legales. Después, colecciones y artículos por orden de tráfico.
2. **No traduzcas las URL.** Una página se llama igual en todos los idiomas; solo cambia el prefijo. Así los enlaces no se rompen.
3. **Revisa la longitud.** Un título que cabe en español puede saltar de línea en inglés, o al revés. La vista previa del constructor permite comprobarlo en escritorio y móvil.
4. **Para buscadores, cada idioma es un sitio.** Título SEO y descripción propios por idioma, con las palabras que busca ese público.
5. **Cuida el "tú" y el "usted".** El tono es parte de la marca; acuerda uno por idioma y úsalo en todo el sitio.
