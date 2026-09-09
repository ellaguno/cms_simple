# packs/ — paquetes de bloques y efectos instalados en este sitio

Un paquete es una carpeta autocontenida con `pack.php` (manifiesto), `blocks.php`, `blocks/`, `assets/` y
`LICENSES.md`. El núcleo busca cada paquete en tres sitios, por orden: esta carpeta, `<tema>/packs/` y `cms/packs/`
(los que vienen con el núcleo: visual, motion, media, marketing y contenido).

Los paquetes que se instalan desde **Admin → Catálogo** llegan aquí, así que sobreviven a un cambio de tema y a una
actualización del núcleo. Se activan y desactivan desde esa misma página; queda guardado en `data/settings.json`
(`packs_on` y `packs_off`), sin tocar el `config.php` del tema.
