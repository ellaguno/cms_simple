# cms_simple

CMS mínimo en PHP plano, sin base de datos, para sitios pequeños y medianos (portafolios, agencias, blogs).
El contenido vive en archivos JSON, el diseño en una carpeta `site/` independiente y el panel de
administración es genérico: se adapta a cualquier tipo de contenido declarado en un esquema.

Nació para [katapolt.mx](https://katapolt.mx) y está pensado para reutilizarse en otros sitios sin tocar el núcleo.

*A minimal flat-file PHP CMS: JSON content, schema-driven admin, multilingual, SEO-ready. Spanish UI. See "English summary" below.*

## Características

- **Sin base de datos**: todo es JSON en `data/`. Respaldar el sitio es copiar la carpeta.
- **Panel de administración** con login (contraseñas `password_hash`, CSRF, bloqueo tras 5 intentos, sesión con cookie HttpOnly), varios usuarios, contraseñas.
- **Tipos de contenido por esquema**: declaras en `site/config.php` los tipos (entradas, proyectos, servicios…) y sus campos; el panel genera listados y formularios. Tipos de campo: texto, área de texto, editor visual (Quill), fecha, número, URL, correo, selector, casilla, imagen, lista de imágenes, líneas, etiquetas.
- **Multilingüe**: cualquier campo marcado `i18n` se edita por idioma con un conmutador (un idioma a la vez, con el texto del idioma base como referencia). URLs con prefijo por idioma (`/en/...`), `hreflang`, respaldo al idioma predeterminado cuando falta traducción.
- **Medios**: subida por botón o arrastrando (imágenes, PDF, video), biblioteca para insertar en el editor o en campos de imagen, WebP automático, aviso de "en uso" antes de borrar.
- **Menú, textos fijos, ajustes, redirecciones 301** editables desde el panel.
- **Código del tema**: editor de código en el panel para plantillas, layout, CSS y JS, con respaldos, restauración y verificación de sintaxis PHP.
- **SEO de serie**: `title`/`description` por página y campos SEO por elemento, `canonical`, `hreflang`, Open Graph y Twitter Card, JSON-LD (`Organization`, `WebSite`, `BreadcrumbList` y `Article`/`CreativeWork`/… según el tipo), `sitemap.xml` con `lastmod`, `robots.txt`, `noindex` en filtros y 404, imágenes con `<picture>` WebP y dimensiones.
- **Formulario de contacto** genérico (`POST /_cms/form`) con `mail()`, honeypot y registro en `data/mensajes.log`.
- **URLs limpias** con `.htaccess` (Apache + mod_rewrite, el estándar de cPanel). Funciona en la raíz o en una subcarpeta.

## Requisitos

PHP 7.4+ (probado con 8.1) con `json`, `mbstring`, `fileinfo`, `session` y, opcionalmente, `gd` para WebP y redimensionado.
Apache con `mod_rewrite`. Permisos de escritura en `data/` y `uploads/`.

## Instalación rápida

1. Descarga o clona este repositorio en la carpeta pública del hosting (raíz o subcarpeta).
2. Da permisos de escritura a `data/` y `uploads/` (755 o 775).
3. Abre `https://tu-dominio/admin/`. La primera vez te pide crear el usuario administrador.
4. En **Ajustes** pon correo, teléfono, WhatsApp, redes y logotipo. En **Textos del sitio** revisa los textos fijos.
5. Sustituye el tema de ejemplo (`site/`) por tu diseño (ver abajo).

Para desarrollo local:

```bash
php -S 127.0.0.1:8080 _router-dev.php
# http://127.0.0.1:8080  y  http://127.0.0.1:8080/admin/
```

## Estructura

```
index.php          entrada pública (delegada a cms/router.php)
admin/index.php    entrada del panel (delegada a cms/admin/index.php)
cms/               NÚCLEO — no se edita por sitio
  bootstrap.php    constantes, configuración, carga de librerías
  router.php       enrutador público (idiomas, tipos, páginas, sitemap, robots, redirecciones, formulario)
  form.php         receptor del formulario de contacto
  lib/             storage (JSON), url, html (Markdown/HTML, <picture>, WebP), seo (head, JSON-LD), icons, Parsedown
  admin/           panel: inc/ (auth, layout, campos por esquema, medios), pages/, assets/
site/              TEMA — lo propio de cada sitio
  config.php       nombre, idiomas, tipos de contenido y campos, páginas, ajustes propios, grupos de textos
  inc/layout.php   site_header($page) y site_footer($page)
  inc/functions.php  helpers opcionales del tema
  templates/       home.php, <listado>.php, <detalle>.php, <página>.php, 404.php
  assets/          css, js, img, video del tema
  defaults/        settings.json, strings.json, menu.json iniciales (se copian a data/ al primer guardado)
data/              contenido: settings, strings, menu, users, redirects, content/<tipo>/<slug>.json
uploads/           archivos subidos (AAAA/MM/)
```

## Crear un sitio nuevo

1. Copia la carpeta `site/` del tema de ejemplo y edita `site/config.php`:

```php
'types' => [
    'services' => [
        'label' => 'Servicios', 'label_singular' => 'Servicio',
        'routes' => ['es' => 'servicios', 'en' => 'services'],
        'template_list' => 'services', 'template_single' => 'service',
        'schema' => 'Service', 'sort' => ['field' => 'order', 'dir' => 'asc'], 'list' => ['order'],
        'fields' => [
            'title' => ['type' => 'text', 'label' => 'Nombre', 'i18n' => true, 'required' => true],
            'body'  => ['type' => 'html', 'label' => 'Descripción', 'i18n' => true],
            'image' => ['type' => 'image', 'label' => 'Imagen', 'sidebar' => true],
            'order' => ['type' => 'number', 'label' => 'Orden', 'sidebar' => true],
        ],
    ],
],
```

2. Crea las plantillas en `site/templates/` (`services.php`, `service.php`). Dentro tienes disponibles
   `$lang`, `$S` (ajustes), `$t('clave')` (textos), `$page`, y en detalle `$item` y `$def`.
3. Escribe `site/inc/layout.php` con `site_header($page)` (que debe llamar a `cms_head($page)` dentro de `<head>`) y `site_footer($page)`.
4. Declara en `strings_groups` los textos fijos que usa tu tema y sus valores iniciales en `site/defaults/strings.json`.

### Funciones útiles en plantillas

| Función | Para qué |
|---|---|
| `cms_url('home'\|'list:tipo'\|'item:tipo'\|'page:clave', $lang, $slug)` | URLs por idioma |
| `cms_items('tipo')`, `cms_item('tipo', $slug)` | Contenido publicado |
| `cms_f($item, 'campo', $lang)` | Valor de un campo bilingüe con respaldo |
| `cms_t('clave', $lang, 'por defecto')` | Texto fijo |
| `cms_content($html)` | HTML del editor (o Markdown heredado) |
| `cms_picture($ruta, $alt, $attrs, $eager)` | `<picture>` con WebP, width/height y lazy |
| `cms_img($ruta)`, `cms_asset('css/x.css')` | Rutas de imágenes y assets |
| `cms_menu($lang)`, `cms_menu_url($url, $lang)` | Menú |
| `cms_social_html()`, `cms_whatsapp_url()`, `cms_tel_href()` | Contacto y redes de Ajustes |
| `cms_date($ymd, $lang)`, `cms_e($texto)` | Fechas y escape HTML |

## Dónde vive cada cosa: núcleo vs. tema (ejemplo de una portada compleja)

El núcleo nunca decide cómo se ve una página. Una portada con video, marquesinas, galerías animadas y
cursor personalizado (como la de katapolt.mx) se construye por completo en `site/` y consume del núcleo
solo datos y utilidades:

| Pieza | Archivo del tema |
|---|---|
| HTML de la portada (hero, galería, servicios, portafolio, blog) | `site/templates/home.php` |
| Header, menú, popup lateral, footer(s) | `site/inc/layout.php` (`site_header()` / `site_footer()`) |
| Fragmentos repetidos (tarjeta de proyecto, sidebar, botones especiales) | `site/inc/functions.php` |
| Demás páginas (listado, detalle, contacto, 404) | `site/templates/*.php` |
| CSS propio y CSS de un tema comprado (Bfolio, etc.) | `site/assets/css/` |
| JS propio y librerías del tema (GSAP, sliders, cursor) | `site/assets/js/` |
| Imágenes, video, fuentes | `site/assets/img`, `video`, `fonts` |
| Textos de esas secciones (editables en el panel) | `data/strings.json` (claves declaradas en `strings_groups`) |
| Imágenes de marca, galerías, opciones de la portada (editables en el panel) | `data/settings.json` (campos declarados en `settings`) |

Dentro de una plantilla tienes `$lang`, `$S` (ajustes), `$t('clave')` (textos), `$page`, y las funciones `cms_*`
para contenido, URLs e imágenes. La plantilla puede tener todo el HTML, CSS y JS que necesite; por ejemplo, la
portada de katapolt es un `home.php` largo que lee `$S['gallery_1']`, `$t('hero_title_grey')` y `cms_items('projects')`.

`site_header($page)` debe llamar a `cms_head($page)` dentro de `<head>` (metadatos, canonical, hreflang,
Open Graph y JSON-LD) y después cargar los CSS/JS del tema como prefiera.

Los temas comerciales (ThemeForest y similares) tienen licencia por sitio: se copian a `site/assets/` de cada
instalación, pero no se publican en repositorios abiertos. Por eso este repositorio incluye solo el tema de ejemplo.

## Editar el tema desde el panel: "Código del tema"

El panel incluye un editor de código (CodeMirror, con resaltado para PHP, HTML, CSS, JS, JSON y Markdown) para
los archivos de `site/`: plantillas, `inc/layout.php`, `inc/functions.php`, `config.php`, CSS y JS.

- Cada guardado deja una copia previa en `data/backups/` (se conservan las últimas 20 por archivo) y se puede
  **restaurar** cualquier versión desde la misma pantalla.
- Los archivos **PHP se verifican con el analizador antes de escribirse**; si hay un error de sintaxis no se
  guarda, el borrador se conserva en el editor y el sitio sigue funcionando. Los JSON se validan igual.
- Solo se permiten rutas dentro de `site/` con extensiones php, css, js, html, json, md, txt y xml (sin `..`,
  espacios ni caracteres especiales). Se pueden crear archivos nuevos escribiendo su ruta.
- `Ctrl+S` guarda. El navegador avisa si cierras con cambios sin guardar.
- Se desactiva con `'code_editor' => false` en `site/config.php`.

Advertencia: es edición de código en producción. Un error lógico en `config.php` o en el layout puede dejar el
sitio o el propio panel fuera de servicio (la verificación evita errores de sintaxis, no errores de lógica).
En ese caso, restaura el respaldo desde `data/backups/` por FTP. Úsalo con cuentas de administrador de confianza.

## Constructor de páginas por secciones, mapa del sitio y flujo de contenido (1.5)

- **Secciones**: un campo `'type' => 'sections'` convierte un tipo en páginas armadas con bloques que declara el
  tema en `site/blocks.php` (campos como en `config.php`, `wrap_class`, `styles` permitidos) y dibuja en
  `site/blocks/<clave>.php` (`$b` datos, `$st` estilo, `$sec`, `$lang`, `$S`, `$t`, `$page`, `$item`). El núcleo
  envuelve cada bloque en `<section class="sec sec-<clave> sec-bg-* sec-text-* sec-pad-* sec-w-* sec-align-*">`
  (más `sec-has-bg` con `--sec-bg`/`--sec-overlay`, `sec-hide-mobile`, ancla, clases); el tema implementa esas
  clases en su CSS. Paleta de fondos: `'sections' => ['palette' => [clave => etiqueta]]` en `config.php`.
  Panel: tarjetas plegables con pestañas Contenido/Estilo, selector por grupos, subir/bajar, arrastrar, duplicar,
  ocultar; **vista previa en vivo** (`admin/?p=preview`) que dibuja el formulario sin guardar con el tema en un
  iframe (escritorio/tableta/móvil); clic en una sección de la vista previa abre su tarjeta.
- **Tipos en árbol**: `'tree' => true` da página padre, ruta completa (`path`), migas de pan y resolución a
  cualquier profundidad; `'routes' => ['es' => '']` las cuelga de la raíz (con validación de segmentos reservados).
- **Mapa del sitio** (Admin → Mapa del sitio): árbol de todo lo que responde, con estado y origen; desde ahí se
  crean páginas hijas, se mueven arrastrando, se ordenan, se publican y se añaden al menú.
- **Flujo de contenido**: vista previa de borradores con token (`?preview=`), publicación programada
  (`publish_at`), versiones (últimas 10, restaurables) y duplicar.
- Campo `code` (textarea monoespaciado). `admin_read_item()` compartido. Elementos en memoria
  (`$GLOBALS['cms_item_override']`) y `cms_items_flush()`.

Un tema que no defina `site/blocks.php` no cambia en nada.

## Editor visual (1.4)

Barra completa (títulos 1-4, negritas, cursivas, subrayado, tachado, listas con sangría, cita, bloque de código,
línea horizontal, alineación, enlace, video, subir imagen, biblioteca, quitar formato) y botón **HTML** que cambia
a un editor de código (CodeMirror bajo demanda) para editar el HTML directamente; lo escrito ahí se guarda tal cual.
Los videos de YouTube/Vimeo se insertan como `<div class="video-embed"><iframe class="ql-video" …></div>`: el tema
debe estilizar `.video-embed` (por ejemplo `position:relative; aspect-ratio:16/9` con el iframe absoluto al 100 %);
los temas que ya estilizan `iframe.ql-video` siguen funcionando.

## Opciones del núcleo añadidas en 1.3 (todas opcionales)

- **Grupos en el menú del panel**: `'group' => 'Páginas'` en un tipo lo agrupa bajo ese encabezado plegable
  (recuerda si está abierto o cerrado). Los tipos sin grupo se listan sueltos como siempre.
- **`'noindex' => true` en un tipo**: sus detalles llevan `<meta name="robots" content="noindex">` y no entran al
  sitemap. Útil para elementos que solo se muestran embebidos en otras páginas (planes, preguntas, equipo).
- **URL canónica fija**: `'site_url' => 'https://midominio.com'` en `site/config.php` (o Ajustes → SEO → URL
  canónica, que tiene prioridad) fija el dominio en canonical, sitemap y JSON-LD aunque el sitio se abra por
  `www` u otro alias. Sin definir, se usa el host de la petición como antes.
- **`/llms.txt`**: si existe `site/llms.txt` se sirve como texto plano; `{{site}}` se sustituye por la URL del sitio.
- **Textos por defecto**: `cms_strings_all()` completa las claves que falten en `data/strings.json` con las de
  `site/defaults/strings.json`, así un tema puede añadir textos nuevos sin tocar `data/` en producción; al guardar
  desde el panel quedan en `data/`.
- `cms_jsonld_graph()` acepta `null` (páginas de `pages` sin `schema` ya no fallan).

## Actualizar el núcleo en un sitio existente

Sustituye la carpeta `cms/` por la nueva versión. `site/`, `data/` y `uploads/` no cambian.

## Seguridad

Contraseñas con `password_hash`; sesión HttpOnly/SameSite; token CSRF en todos los formularios; bloqueo de 15 min tras 5 intentos fallidos;
subida validada por tipo real de archivo (sin SVG ni ejecutables) y sin ejecución de PHP en `uploads/`; `.htaccess` impide leer `data/`, el PHP de `cms/` y `site/`.
Si el servidor no es Apache hay que replicar esas reglas.

## Licencia

MIT. Incluye [Parsedown](https://github.com/erusev/parsedown) (MIT). El panel carga [Quill](https://quilljs.com/) desde CDN (BSD-3).

---

## English summary

`cms_simple` is a flat-file PHP CMS: JSON content, a schema-driven admin panel (content types and fields declared in `site/config.php`),
multilingual fields with a language switcher, media library (images/PDF/video with automatic WebP), menus, site texts, settings,
301 redirects, users, a built-in code editor for theme files (with backups and PHP syntax check), and SEO out of the box (meta, canonical, hreflang, Open Graph, JSON-LD, sitemap with lastmod, robots, `<picture>`).
Requires PHP 7.4+ and Apache with mod_rewrite. Copy to your web root, make `data/` and `uploads/` writable, open `/admin/` and create the first user.
The admin UI is in Spanish. MIT license.
