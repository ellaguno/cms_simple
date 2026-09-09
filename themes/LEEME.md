# themes/ — temas instalados

Cada subcarpeta es un tema completo: lo mismo que hay en `site/` (config.php, inc/, templates/, blocks/, assets/,
styles/ y, opcionalmente, theme.json con nombre, descripción, versión y autor, más screenshot.png).

El tema activo se elige en **Admin → Diseño**; queda guardado en `data/settings.json` (`"theme"`). Si no eliges
ninguno, el sitio usa `site/` como siempre. Desde esa misma página puedes instalar un tema desde un zip y descargar
el que está en uso, que es la forma de llevarlo a otro sitio.

El contenido (`data/`) y los archivos subidos (`uploads/`) no dependen del tema. Aun así, cada tema trae sus propios
bloques: las secciones construidas con bloques que el tema nuevo no conozca dejan de dibujarse, aunque siguen
guardadas y vuelven a aparecer si regresas al tema anterior. Los bloques de paquetes (`visual/…`, `motion/…`,
`marketing/…`, `contenido/…`) los pone el núcleo, así que funcionan en todos los temas.
