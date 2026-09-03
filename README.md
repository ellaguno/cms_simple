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
301 redirects, users, and SEO out of the box (meta, canonical, hreflang, Open Graph, JSON-LD, sitemap with lastmod, robots, `<picture>`).
Requires PHP 7.4+ and Apache with mod_rewrite. Copy to your web root, make `data/` and `uploads/` writable, open `/admin/` and create the first user.
The admin UI is in Spanish. MIT license.
