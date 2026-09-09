# 9. Mantenimiento y seguridad

> Respaldos, actualizaciones, usuarios y lo que nunca hay que hacer en producción.

## Respaldos

Todo el contenido vive en dos carpetas: `data/` (páginas, artículos, ajustes, textos, menú, usuarios, versiones) y `uploads/` (archivos subidos). El diseño está en `site/` y el motor en `cms/`.

En **Respaldos** creas un zip con esas carpetas en un clic, con una nota para recordar el motivo, y opcionalmente con el tema. Los respaldos quedan en la carpeta `/backups` del sitio, que no es accesible desde fuera; desde el panel los descargas a tu equipo, los restauras o los eliminas. Al restaurar, el contenido actual se sustituye por el del respaldo, conservando los usuarios actuales si así lo marcas, y antes se guarda un respaldo automático del estado previo por si te arrepientes.

Hazlo antes de cualquier cambio grande y, en general, una vez al mes. Descarga los importantes: el hosting no es un archivo. Sin el panel, restaurar es descomprimir el zip en la raíz del sitio por FTP.

## Aviso de cookies

En **Ajustes → Aviso de cookies** se activa una barra que pide aceptar el uso de cookies, con texto por idioma, botón, enlace a la política de privacidad y posición (abajo o en la esquina). Aparece hasta que la persona la acepta y no vuelve a salir en ese navegador. Si el sitio solo usa cookies técnicas, como la sesión del panel, no es obligatoria; si añades analítica o video incrustado, conviene activarla y enlazar la política.

## Versiones de contenido

Cada vez que guardas una página o un artículo, la versión anterior se conserva. En el editor, "Versiones anteriores" permite restaurar cualquiera de las últimas diez. No hace falta hacer copias manuales antes de editar.

## Actualizar el motor

El motor es la carpeta `cms/`. Actualizar es sustituirla por la versión nueva; `site/`, `data/` y `uploads/` no se tocan. Quien mantiene el código prepara un archivo comprimido con lo necesario y las instrucciones. Después de actualizar, entra al panel: la versión aparece al pie del menú.

## Usuarios

- Un usuario por persona. No compartas contraseñas.
- Cada quien cambia la suya en **Contraseña**.
- Cuando alguien deja de colaborar, borra su usuario en **Usuarios** ese mismo día.
- Tras cinco intentos fallidos de acceso, la dirección queda bloqueada quince minutos.

## Seguridad

El panel usa sesión segura, protección contra envíos falsificados y validación de los archivos subidos: no se aceptan ejecutables ni SVG. Las carpetas de datos y de código no se pueden leer desde fuera. Lo que depende de ti:

- Contraseñas largas y únicas, guardadas en un gestor.
- Mantener el sitio detrás de HTTPS; el proveedor lo activa en un clic.
- No dar acceso al panel a quien no lo necesita.

## Código del tema: úsalo con cuidado

La sección **Código del tema** permite editar plantillas, CSS y JS desde el panel. Cada guardado deja un respaldo y los archivos PHP se verifican antes de escribirse, pero un error lógico puede dejar el sitio o el panel fuera de servicio. Si ocurre, se restaura el respaldo desde `data/backups/` por FTP. Recomendación: que solo lo use quien programa, y que las pruebas se hagan en una copia local, no en producción.

## Cuando algo falla

- **"Las carpetas data y uploads deben tener permiso de escritura"**: ajusta permisos a 755 o 775 en el administrador de archivos del hosting.
- **Una página da 404 después de moverla o renombrarla**: crea la redirección 301 desde la ruta vieja.
- **El sitio se ve sin estilos después de actualizar**: recarga forzando (Ctrl+Shift+R); el navegador tenía el CSS anterior en caché.
- **Un cambio en Ajustes no se ve**: comprueba que guardaste; el panel muestra "Ajustes guardados" arriba.

## Cambiar el aspecto del sitio sin tocar el contenido

En **Diseño** están las variaciones de estilo que trae el tema: cada una cambia colores, tipografías, esquinas y
espacios de todo el sitio de una vez. La tarjeta muestra el sitio dibujado de verdad con esa variación, así que puedes
compararlas antes de decidir. Al pulsar **Usar esta** se aplica y sus colores y tipografías pasan a Ajustes → Diseño,
donde puedes seguir afinándolos. Si ya tienes tus colores y solo quieres el resto, marca «conservar mis colores».

Las páginas, los artículos y las imágenes no se tocan: es solo la piel.

## Cambiar de tema

En **Diseño**, arriba, están los temas instalados. Cada ficha dice de qué va el tema y, si no es el que usas, avisa de
lo que ese tema no trae y tu contenido sí usa. Al activarlo, el sitio cambia de plantillas, de bloques y de estilo;
el contenido no se borra. Las secciones hechas con bloques que el tema nuevo no conozca dejan de verse, pero siguen
guardadas y reaparecen si vuelves al tema anterior.

Desde ahí también puedes **instalar un tema** desde un archivo zip y **descargar el que estás usando**, que es la forma
de llevarlo a otro sitio. Un tema contiene programación que se ejecuta en tu servidor: instala solo los que te dé
alguien de confianza.

## El catálogo: instalar temas y paquetes

En **Catálogo** aparecen los temas y los paquetes de bloques que se pueden instalar desde internet, y más abajo los
paquetes que ya tienes, con su interruptor. Instalar descarga programación que se ejecutará en tu servidor: hazlo solo
desde catálogos de confianza. En Ajustes puedes añadir los tuyos.

Desactivar un paquete no borra nada: los bloques que aporta dejan de estar disponibles y las secciones que los usen
dejan de dibujarse, pero vuelven en cuanto lo actives otra vez.

## Actualizar el CMS con un botón

En **Actualizar** ves la versión que tienes y la publicada. Pulsa **Buscar actualizaciones** (esa consulta sale a
internet, por eso no se hace sola) y, si hay una nueva, aparecen las notas del cambio y el botón para instalarla.

La actualización sustituye solo el motor del CMS. Tu contenido, tus imágenes y tu diseño no se tocan. La versión
anterior queda guardada y puedes volver a ella desde esa misma página si algo no te cuadra. Aun así, antes de una
actualización grande vale la pena crear un respaldo.

Si tu servidor no deja escribir en la carpeta del sitio, la página te lo dirá; en ese caso hay que actualizar por FTP,
copiando la carpeta `cms/` nueva encima de la vieja.
