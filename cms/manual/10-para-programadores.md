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

En `site/blocks.php` se declara, y en `site/blocks/<clave>.php` se dibuja. La vista recibe `$b` (datos con valores por defecto), `$st` (estilo), `$sec`, `$lang`, `$S`, `$t`, `$page`, `$item`. El motor envuelve la salida en `<section class="sec sec-<clave> …">` con las clases de estilo, que el tema implementa en `sections.css`.

Opciones útiles: `wrap_class` y `wrap_class_by` para añadir clases al envoltorio, `styles` para limitar los controles de estilo, `effects` para declarar efectos de paquete que el bloque puede usar, y `cms_section_effect('paquete/efecto', $on)` desde la vista para activarlos o no.

## Un paquete

Carpeta en `cms/packs/<nombre>` o `site/packs/<nombre>` con `pack.php` (manifiesto con efectos y recursos), `blocks.php`, `blocks/*.php`, `assets/` y `LICENSES.md`. Se activa con `'packs' => ['nombre']`. Sus bloques usan clases neutras `cms-*` y variables `--cms-*` que el tema define, y la cabecera estándar `cms_block_header()` toma las clases del tema de `sections.classes`. Las librerías abiertas se cargan bajo demanda con `CMS.load('gsap')`; el registro de librerías, con versión y licencia, está en `cms/lib/packs.php`.

## Funciones que se usan a diario

| Función | Para qué |
|---|---|
| `cms_url('home' / 'list:tipo' / 'item:tipo' / 'page:clave', $lang, $slug)` | URL de cualquier ruta |
| `cms_items('tipo')`, `cms_item('tipo', $slug)` | contenido publicado |
| `cms_f($item, 'campo', $lang)` | campo bilingüe con respaldo |
| `cms_t('clave', $lang, 'por defecto')` | texto fijo |
| `cms_content($html)` | HTML del editor |
| `cms_picture($ruta, $alt)` | imagen con WebP y tamaño |
| `cms_sections_render($secciones, $ctx)` | dibujar las secciones de un elemento |
| `cms_tree_children('tipo', $slug)` | páginas hijas |

El detalle completo está en el `README.md` del repositorio.
