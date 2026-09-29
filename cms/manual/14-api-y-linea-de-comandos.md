# 14. API y línea de comandos

> Para publicar desde otros programas (una rutina diaria, un script de migración, un asistente) sin imitar al navegador: una API con token en `/admin/api/` y, si el script corre en el mismo servidor, `php cms/cli.php`. Los dos pasan por la misma validación y el mismo saneado que el formulario del editor.

## Crear un token

En **Usuarios → Acceso por API** escribe un nombre para reconocerlo («Rutina de novedades») y elige a qué colecciones llega: todo el contenido o solo algunas. Al crearlo se muestra **una sola vez**; cópialo en ese momento. En la tabla ves cuándo se usó por última vez y lo puedes **revocar**. Un token actúa como el usuario que lo creó y deja de valer si ese usuario se elimina.

Desde el servidor también: `php cms/cli.php token create <usuario> "<nombre>" [colección …]`, `token list` y `token revoke <id>`.

## Llamadas

El token va en la cabecera `Authorization: Bearer <token>` o, si el hosting la quita (pasa en algunos Apache con PHP por FastCGI), en `X-CMS-Token: <token>`. Todas las respuestas son JSON con `"ok"`.

| Método y ruta | Qué hace |
|---|---|
| `GET /admin/api/` | Quién eres, versión del núcleo y colecciones a las que llega el token. |
| `GET /admin/api/types` | Esquema: colecciones, sus campos, de qué tipo son y cuáles son bilingües. |
| `GET /admin/api/items/{colección}` | Listado: slug, título, estado, fechas, categoría y URL. Filtros `?status=` (published, scheduled, expired o draft), `?q=` (en título y slug), `?page=` y `?per=` (hasta 200). |
| `GET /admin/api/items/{colección}/{slug}` | El elemento completo, tal como está guardado. |
| `POST /admin/api/items/{colección}` | Crea o actualiza a partir de un objeto JSON. |
| `DELETE /admin/api/items/{colección}/{slug}` | Elimina (la portada no). |
| `POST /admin/api/upload` | Sube un medio (multipart, campo `file`); devuelve su `path` para usarlo en los campos de imagen. |

## Crear o actualizar

```bash
curl -X POST https://tu-sitio/admin/api/items/articulos \
  -H "Authorization: Bearer cms_…" -H "Content-Type: application/json" \
  -d '{"title": {"es": "Novedades v4.84", "en": "What’s new v4.84"},
       "excerpt": "Qué cambia en esta versión.",
       "body": {"es": "<p>…</p>"},
       "image": "uploads/2026/09/portada.jpg",
       "tags": ["versión", "novedades"],
       "status": "published"}'
```

- Si mandas `slug` y ya existe, se **actualiza**; si no existe (o no lo mandas), se **crea** con ese slug o con el derivado del título. También vale ponerlo en la ruta: `POST /admin/api/items/articulos/novedades-v4-84`.
- Al actualizar, los campos que no mandas **conservan su valor**. En los bilingües solo cambian los idiomas que mandes; un texto suelto va al idioma principal.
- `status` es `published` o `draft`; `publish_at` (AAAA-MM-DD, futura) lo programa y `unpublish_at` lo retira. Además: `seo_title`, `seo_desc` y, en colecciones en árbol, `parent`.
- Etiquetas y listas pueden ir como arreglo o como texto (separadas por coma o por renglón). La categoría, por su nombre; si no existe, se crea.
- Las páginas del constructor aceptan `sections` con la misma forma que devuelve `GET` (tipo de bloque, `data`, `style`). Lo más cómodo: leer el elemento, cambiar lo necesario y volver a mandarlo.
- Si algo no pasa la validación, la respuesta es **422** con la lista `errors` (título obligatorio, slug repetido…), y no se guarda nada. Cada guardado deja la versión anterior en el historial, como en el editor.

## Firewalls del hosting

Si el alojamiento corta los POST con HTML (mod_security, «Not Acceptable»), manda el cuerpo entero —o cualquier texto— blindado como lo hace el panel: `=?rb64?=` seguido del base64 **invertido**. En bash: `printf '%s' "$json" | base64 -w0 | rev`, con `=?rb64?=` delante.

## Línea de comandos

Para scripts que corren en el mismo servidor, sin token ni HTTP (responde JSON; código de salida 0 si salió bien):

```bash
php cms/cli.php types
php cms/cli.php list articulos --status=draft --q=novedades
php cms/cli.php get articulos novedades-v4-84
php cms/cli.php put articulos articulo.json      # o "-" para leer el JSON de la entrada estándar
php cms/cli.php upload /ruta/portada.jpg
php cms/cli.php delete articulos borrador-viejo
```

`cms/cli.php` no se puede abrir desde el navegador (el `.htaccess` de `cms/` lo impide y además solo responde en la línea de comandos).
