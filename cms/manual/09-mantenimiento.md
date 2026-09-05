# 9. Mantenimiento y seguridad

> Respaldos, actualizaciones, usuarios y lo que nunca hay que hacer en producción.

## Respaldos

Todo el contenido vive en dos carpetas: `data/` (páginas, artículos, ajustes, textos, menú, usuarios, versiones) y `uploads/` (archivos subidos). El diseño está en `site/` y el motor en `cms/`.

En **Respaldos** creas un zip con esas carpetas en un clic, con una nota para recordar el motivo, y opcionalmente con el tema. Los respaldos quedan en la carpeta `/backups` del sitio, que no es accesible desde fuera; desde el panel los descargas a tu equipo, los restauras o los eliminas. Al restaurar, el contenido actual se sustituye por el del respaldo, conservando los usuarios actuales si así lo marcas, y antes se guarda un respaldo automático del estado previo por si te arrepientes.

Hazlo antes de cualquier cambio grande y, en general, una vez al mes. Descarga los importantes: el hosting no es un archivo. Sin el panel, restaurar es descomprimir el zip en la raíz del sitio por FTP.

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
