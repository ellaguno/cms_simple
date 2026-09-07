# Tema "lienzo": un sitio en blanco para el constructor

Un tema mínimo pensado para arrancar un sitio desde cero, sobre todo importando un diseño (Admin → Importar diseño):
no trae cabecera ni pie propios. Son dos bloques del catálogo, **Cabecera del sitio** y **Pie de página**, que se
colocan como primera y última sección de cada página, así que un PDF de un diseñador se convierte en la página
completa, incluidos el menú y el pie.

## Instalar

1. Copia `cms/` del núcleo, `index.php` y `_router-dev.php` a la carpeta del sitio.
2. Copia esta carpeta como `site/`.
3. Crea `data/` y `uploads/` con permiso de escritura. Copia `site/defaults/settings.json` a `data/settings.json`.
4. Entra a `/admin/`: la primera vez pide crear el usuario.
5. En Ajustes → Diseño pon el color principal, el de acento y la tipografía de Google Fonts. Importar diseño propone
   la paleta del PDF.

## Bloques

Estructura: cabecera (con barra superior opcional de teléfono, correo y horario), pie. Cabeceras: hero (imagen a un
lado, debajo o de fondo; fondo animado opcional), encabezado. Contenido: texto, columnas (texto e imagen), imagen,
tarjetas (tarjeta, sin fondo o pasos numerados; icono emoji o de Tabler Icons, `ti-rocket`), lista de puntos,
logotipos, testimonios, equipo, planes de precios, preguntas frecuentes, llamado a la acción, contacto (con formulario
al endpoint del núcleo), boletín, separador, HTML. Además, los paquetes visual (galería 3D, carrusel, lightbox,
efectos), motion (marquesina, cifras, titular, parallax) y media (video de YouTube o Vimeo al clic, mapa de
OpenStreetMap, animación Lottie).

Los campos de líneas separan celdas con ` | `; un enlace dentro de una celda se escribe `Texto > URL`.

## Estilo

`assets/css/lienzo.css` usa variables `--lz-*`; los colores y las tipografías (una para títulos y otra para texto,
elegidas de una lista de Google Fonts o escritas a mano) salen de Ajustes → Diseño. Los estilos de sección del
constructor (fondo, color de texto, espacio, ancho, alineación) están implementados con las clases `sec-*` del núcleo.
Para un diseño propio, sustituye la hoja o añade reglas al final.

## Cabecera y pie compartidos

Una página importada trae su cabecera y su pie como secciones. Para que todas las páginas los compartan, en
Ajustes → Cabecera y pie compartidos se marca la casilla y se escribe la URL de la página de origen (por ejemplo
`inicio`). Desde entonces el tema dibuja en todas las páginas la primera sección "Cabecera del sitio" y la última
"Pie de página" de esa página, y las demás páginas dejan de mostrar los suyos propios. Se editan en el constructor de
la página de origen.

## Migas de pan y aviso de cookies

Las páginas con padre muestran migas de pan (Inicio › Padre › Página) bajo la cabecera, con su JSON-LD. El aviso de
cookies es del núcleo: Ajustes → Aviso de cookies (texto por idioma, botón, enlace a la política, abajo o en la
esquina); el tema puede restilizarlo con `.cms-cookie` y las variables `--cms-cookie-*`.
