# 10. Para quien programa el tema

> El mapa de la parte técnica en una página: dónde vive cada cosa y cómo extender el sistema sin tocar el motor.

## Estructura

```
index.php, admin/, cms/     motor (no se edita por sitio; se actualiza sustituyendo cms/)
cms/packs/                  paquetes compartidos de bloques y efectos
site/config.php             tipos de contenido, páginas fijas, ajustes, textos, paleta y paquetes
site/blocks.php, blocks/    catálogo y vistas de las secciones del constructor
site/templates/             plantillas: home, listados, detalles, páginas fijas, 404
site/inc/layout.php         cabecera y pie; debe llamar a cms_head($page) dentro de <head>
site/inc/functions.php      helpers del tema
site/assets/                CSS, JS, imágenes, video
site/llms.txt               resumen del sitio para asistentes de IA
data/, uploads/             contenido y archivos de cada instalación
```

## Un tipo de contenido

Se declara en `site/config.php`, en `types`. Un tipo tiene ruta, plantillas de listado y detalle, campos, orden y opciones:

- `tree => true`: los elementos tienen página padre y ruta completa; con `routes` vacío cuelgan de la raíz.
- `noindex => true`: detalles con `noindex` y fuera del sitemap.
- `no_list => true`: sin índice público.
- `group => 'Páginas'`: agrupa en el menú del panel.
- Un campo `type => 'sections'` convierte el tipo en páginas del constructor.
- `home_item => ['paginas', 'inicio']` en la raíz de la configuración hace que la portada sea ese elemento.

Tipos de campo: `text`, `textarea`, `html`, `code`, `date`, `number`, `url`, `email`, `select`, `checkbox`, `image`, `images`, `lines`, `tags`, `sections`. Opciones: `label`, `help`, `i18n`, `required`, `sidebar`, `rows`, `options`, `default`, `placeholder`.

## Un bloque del constructor

En `site/blocks.php` se declara, y en `site/blocks/<clave>.php` se dibuja. Cada bloque puede llevar `'sample' => [campo => valor, …]`: los datos con los que se muestra su ejemplo en vivo en el manual y en el selector (`admin/?p=demo&block=<clave>`); lo que no declares se deduce de los valores por defecto y de las imágenes de muestra del núcleo (`cms_demo_image('foto', n)`, también `persona` y `logo`). Los efectos declaran en `'sample'` con qué bloque se demuestran: `[['block' => 'tarjetas'], ['block' => 'motion/cifras']]`, y se usa el primero que exista en el sitio. Un bloque que dibuje contenido real del sitio puede preguntar `cms_is_demo()` para inventar elementos de muestra cuando lo que se está dibujando es un ejemplo del panel.

Un efecto puede declarar `'fields' => […]` en `pack.php`: el panel los pinta bajo el selector de efecto y los guarda en `style['fx']['<paquete/efecto>']`. El núcleo los emite en el `<section>` como variables CSS `--fx-<paquete>-<efecto>-<campo>` (para el CSS del efecto) y como `data-fx` en JSON; el JS los lee con `CMS.fx(sec, 'paquete/efecto')` y `CMS.fxNum(opts, 'campo', defecto, min, max)`. El control de estilo `accent` define `--cms-accent` en la sección; el tema añade las suyas con `'sections' => ['accent_vars' => ['--lz-accent']]`. La vista recibe `$b` (datos con valores por defecto), `$st` (estilo), `$sec`, `$lang`, `$S`, `$t`, `$page`, `$item`. El motor envuelve la salida en `<section class="sec sec-<clave> …">` con las clases de estilo, que el tema implementa en `sections.css`.

Opciones útiles: `wrap_class` y `wrap_class_by` para añadir clases al envoltorio, `styles` para limitar los controles de estilo, `effects` para declarar efectos de paquete que el bloque puede usar, y `cms_section_effect('paquete/efecto', $on)` desde la vista para activarlos o no.

## Un paquete

Carpeta en `cms/packs/<nombre>` o `site/packs/<nombre>` con `pack.php` (manifiesto con efectos y recursos), `blocks.php`, `blocks/*.php`, `assets/` y `LICENSES.md`. Se activa con `'packs' => ['nombre']`. Sus bloques usan clases neutras `cms-*` y variables `--cms-*` que el tema define, y la cabecera estándar `cms_block_header()` toma las clases del tema de `sections.classes`. Las librerías abiertas se cargan bajo demanda con `CMS.load('gsap')`; el registro de librerías, con versión y licencia, está en `cms/lib/packs.php`.

## Funciones que se usan a diario

| Función | Para qué |
|---|---|
| `cms_url('home' / 'list:tipo' / 'item:tipo' / 'page:clave', $lang, $slug)` | URL de cualquier ruta |
| `cms_items('tipo')` | contenido publicado, del índice ligero (sin cuerpo ni secciones); `cms_items('tipo', true, true)` lee los archivos completos |
| `cms_item('tipo', $slug)` | un elemento completo, leyendo solo su archivo |
| `cms_paginate($items, 12)`, `cms_pager($pg, $base)` | paginación de un listado (`?pg=`) |
| `cms_f($item, 'campo', $lang)` | campo bilingüe con respaldo |
| `cms_t('clave', $lang, 'por defecto')` | texto fijo |
| `cms_content($html)` | HTML del editor |
| `cms_picture($ruta, $alt)` | imagen con WebP y tamaño |
| `cms_sections_render($secciones, $ctx)` | dibujar las secciones de un elemento |
| `cms_tree_children('tipo', $slug)` | páginas hijas |

El detalle completo está en el `README.md` del repositorio.

## El índice ligero

`data/index/<tipo>.json` guarda cada elemento sin sus campos pesados (`html`, `sections`, `code`). Se actualiza al guardar desde el panel y se reconstruye solo si la carpeta `data/content/<tipo>/` cambió por fuera. Si un campo `html` corto hace falta en listados, márcalo con `'index' => true` en `site/config.php`; `'no_index' => true` en el tipo lo deja sin índice. No lo subas al repositorio ni lo copies entre instalaciones: se regenera.

## Paquetes con código: ganchos

Un paquete puede traer `inc.php`; el motor lo carga una vez por petición si el paquete está activo (Catálogo o `'packs'` en `site/config.php`). Ahí se registran ganchos y helpers:

```php
cms_on('content', fn(string $html, array $ctx) => str_replace('…', '...', $html));   // filtro
cms_on('head', function (array $page) { echo '<meta name="x" content="y">'; });        // acción
cms_on('item.save', function (string $type, array $item) { /* p. ej. generar algo */ });
```

Puntos de gancho del motor: `content` (el HTML que devuelve `cms_content()`: campos html y bloques de texto; `$ctx` es `cms_current()`, con `type`, `item`, `page` y `lang`), `head` (al final de `cms_head()`), `item.save` (tras guardar un elemento), `admin.item.sidebar` (la barra lateral del editor de un elemento guardado, para botones propios) y `cron` (cuando el hosting llama a `/_cms/cron?token=…` o se ejecuta `php cms/cron.php`; recibe una función `$log`). Se añade un punto nuevo solo cuando un paquete real lo necesita.

Ojo con `cms_current()['item']` al dibujar el sitio: llega resuelto al idioma de la petición, con respaldo al predeterminado; si necesitas saber si un campo existe en ese idioma exacto, lee el archivo del elemento (el paquete audio lo hace con `au_raw_item()`).

Para interfaz no hay API: el manifiesto `pack.php` declara `'settings' => ['Grupo' => [campo => def]]` (un grupo en Ajustes), `'item_fields' => ['*' => [campo => def]]` o `[tipo => …]` (campos en la barra lateral del editor), con la misma sintaxis de campos de `config.php`, y `'admin' => ['label' => 'Audio', 'file' => 'admin.php']` para una página propia en el panel (en el grupo Contenido, o en el que indique `'group' => 'diseno'|'ajustes'|'sistema'`): responde en `admin/?p=pack:<nombre>`, con `$pack` (el manifiesto) y los helpers del panel (`admin_header()`, `admin_is_post()`, `admin_csrf_check()`, `admin_flash()`, `admin_redirect()`), y aparece en el menú lateral. Para hablar con una API, `cms_http_post($url, $body, $headers)` (JSON o cuerpo crudo, respuesta de texto o binaria, excepción legible).

Ejemplos completos: `cms/packs/enlaces` (solo código, un grupo de ajustes, una casilla por elemento y dos ganchos), `cms/packs/audio` (página propia, botones en el editor, bloque, `item.save`) y `cms/packs/redaccion` (página propia, historial en `data/`, tarea de `cron`).

## Cabeceras y pies del constructor en el tema (1.33)

Para que el tema use las piezas de Diseño → Cabeceras y pies, en `site/inc/layout.php` envuelve la cabecera y el pie propios:

```php
// en site_header(), donde va la cabecera
$h = cms_layout_header($page);
if ($h !== '') echo $h; else { ?> …tu <header> de siempre… <?php }

// en site_footer(), donde va el pie
$f = cms_layout_footer($page);
if ($f !== '') echo $f; else { ?> …tu <footer> de siempre… <?php }
```

Devuelven `''` cuando la página no tiene cabecera o pie elegidos ni hay predeterminados, así que el tema conserva los suyos. `cms_layout('header'|'footer', $page)` devuelve el elemento elegido (o `null`) por si la plantilla necesita saberlo, por ejemplo para no pintar sus propios bloques de cabecera. Los bloques de la pieza se dibujan con `cms_sections_render()` como los de cualquier página, con `$ctx['item']` = la pieza; sus recursos de paquete ya van en `cms_head()`. La colección interna es `layouts` (`'internal' => true`): no responde en el sitio, no sale en el sitemap ni en el mapa, y sus vistas previas van por `/_layout/<slug>?preview=token`. `'layouts' => false` en `config.php` la quita; `'block_exclude' => ['estructura/cabecera', 'estructura/pie']` esconde los bloques genéricos si el tema trae los suyos.
