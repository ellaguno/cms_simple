# packs/ — paquetes de bloques y efectos instalados en este sitio

Un paquete es una carpeta autocontenida con `pack.php` (manifiesto), `blocks.php`, `blocks/`, `assets/` y
`LICENSES.md`. El núcleo busca cada paquete en tres sitios, por orden: esta carpeta, `<tema>/packs/` y `cms/packs/`
(los que vienen con el núcleo: visual, motion, media, marketing y contenido).

Los paquetes que se instalan desde **Admin → Catálogo** llegan aquí, así que sobreviven a un cambio de tema y a una
actualización del núcleo. Se activan y desactivan desde esa misma página; queda guardado en `data/settings.json`
(`packs_on` y `packs_off`), sin tocar el `config.php` del tema.

Un paquete puede traer además `inc.php`: código que el núcleo carga en cada petición si el paquete está activo (ganchos
`cms_on()`, helpers). En `pack.php` puede declarar `'settings'` (un grupo en Ajustes), `'item_fields'` (campos en el
editor) y `'admin'` (una página propia en el panel). Un paquete puede ser solo código, sin bloques, como `cms/packs/enlaces`. Ver `cms/lib/hooks.php`.
