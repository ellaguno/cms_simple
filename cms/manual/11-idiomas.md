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

Lo que no cambia por idioma: imágenes, URL de páginas y colecciones, videos, precios y ajustes generales.

## Activar o desactivar un idioma

En **Ajustes → Idiomas** se marca cuáles están activos para el público. Un idioma desactivado sigue editándose en el panel, pero sus páginas no responden en el sitio ni entran al sitemap. Es la forma de trabajar una traducción con calma y publicarla de golpe.

## Por qué no aparece la opción de "crear un idioma" en el panel

Añadir un idioma cambia la estructura del sitio, no solo el contenido: hay que decidir el código y la URL, marcar qué campos del tema se traducen, y escribir los textos fijos del diseño en el idioma nuevo. Eso lo declara quien programa el tema en `site/config.php`, en la lista `langs`, y a partir de ahí el panel muestra el conmutador en todos los campos bilingües y la casilla en Ajustes → Idiomas. Es un trabajo de una vez por idioma; después, todo es contenido.

## Consejos para traducir bien

1. **Traduce primero lo estructural**: menú, textos del sitio, portada, páginas principales, legales. Después, colecciones y artículos por orden de tráfico.
2. **No traduzcas las URL.** Una página se llama igual en todos los idiomas; solo cambia el prefijo. Así los enlaces no se rompen.
3. **Revisa la longitud.** Un título que cabe en español puede saltar de línea en inglés, o al revés. La vista previa del constructor permite comprobarlo en escritorio y móvil.
4. **Para buscadores, cada idioma es un sitio.** Título SEO y descripción propios por idioma, con las palabras que busca ese público.
5. **Cuida el "tú" y el "usted".** El tono es parte de la marca; acuerda uno por idioma y úsalo en todo el sitio.
