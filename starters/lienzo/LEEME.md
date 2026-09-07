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

Estructura: cabecera, pie. Cabeceras: hero (imagen a un lado, debajo o de fondo; fondo animado opcional), encabezado.
Contenido: texto, columnas (texto e imagen), imagen, tarjetas (tarjeta, sin fondo o pasos numerados), lista de puntos,
logotipos, testimonios, planes de precios, preguntas frecuentes, llamado a la acción, contacto (con formulario al
endpoint del núcleo), separador, HTML. Además, los paquetes visual (galería 3D, carrusel, lightbox, efectos) y motion
(marquesina, cifras, titular, parallax).

Los campos de líneas separan celdas con ` | `; un enlace dentro de una celda se escribe `Texto > URL`.

## Estilo

`assets/css/lienzo.css` usa variables `--lz-*`; los colores y la fuente salen de Ajustes. Los estilos de sección del
constructor (fondo, color de texto, espacio, ancho, alineación) están implementados con las clases `sec-*` del núcleo.
Para un diseño propio, sustituye la hoja o añade reglas al final.

## Pendiente

Compartir la cabecera y el pie entre páginas sin repetirlos ('site_sections' en Ajustes, con la primera página
importada como origen).
