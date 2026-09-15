# 13. Audio y redacción con IA

> Dos paquetes que se activan en Catálogo: uno convierte tus artículos en audio y otro escribe artículos y resúmenes de noticias. Los dos funcionan sin clave con un proveedor de prueba, para que veas el flujo antes de pagar nada.

## Antes de empezar

Ve a **Catálogo → Paquetes instalados** y activa **Audio: texto a voz** o **Redacción con IA** (o los dos). Cada uno añade un grupo en **Ajustes** y una página en el menú lateral. Con el proveedor **Prueba** que traen por defecto no se conecta a ningún servicio: el audio es un MP3 en silencio y el artículo es un texto de muestra. Cuando el flujo te convenza, elige un proveedor real y pega su clave.

Las claves se guardan en `data/settings.json`, que no se sirve por internet. Aun así, usa claves con límite de gasto: cada proveedor cobra por carácter o por token.

## Audio: texto a voz

En **Ajustes → Audio** eliges proveedor y voz: OpenAI, ElevenLabs, Azure o Google Cloud. Ahí decides también dónde va el reproductor (al principio o al final del texto, o solo donde tú lo pongas), en qué colecciones se ofrece, si se lee el título y el máximo de caracteres a convertir.

En cada artículo, la barra lateral del editor tiene **Generar audio**, uno por idioma. Convierte el texto tal como está guardado (guarda antes tus cambios), tarda unos segundos por cada 4,000 caracteres y deja el MP3 en `uploads/audio/`. Si editas el texto después, vuelve a generar: el audio no se actualiza solo. **Quitar** borra el archivo.

La página **Audio** del menú tiene un botón para probar la voz elegida y una tabla por colección con qué elementos tienen audio y cuáles no, con los mismos botones.

Opciones útiles:

- **Automático al publicar**: genera el audio al guardar un elemento publicado que no lo tenga. Cómodo, pero el guardado tarda más.
- En una página del constructor, el bloque **Reproductor de audio** pone el reproductor donde quieras, con un texto al lado; sin archivo indicado usa el audio de esa página.
- Puedes escribir a mano la ruta de un MP3 propio (subido por FTP a `uploads/`) en el campo Audio del elemento.

## Redacción con IA

En **Ajustes → Redacción con IA** eliges el proveedor (OpenRouter da acceso a casi cualquier modelo con una sola clave; también OpenAI, Anthropic y DeepSeek), la colección donde se guardan los textos y si entran como **borrador** (recomendado: los revisas y publicas tú) o publicados.

**Artículos.** Escribe una lista de temas, uno por línea, y la longitud y el número de secciones. En la página **Redacción IA**, elige un tema de la lista o escribe uno nuevo y pulsa *Escribir artículo*: en uno o dos minutos se abre el borrador con título, resumen, secciones con subtítulo y, si lo activaste, una imagen generada. Las instrucciones de estilo se aplican a todo lo que se escriba.

**Resumen de noticias.** Escribe los temas del resumen (o deja la lista vacía para la portada general). Cada tema busca en Google News las noticias de las últimas horas, descarta las que ya se usaron, pide un resumen que se ciña a los titulares y deja las fuentes enlazadas al final de cada sección. *Resumir las noticias de hoy* lo hace en el momento.

**Programación.** Un artículo al día o por semana (toma el siguiente tema de la lista cada vez) y un resumen de noticias diario, a partir de la hora que indiques. Para que ocurra solo, el hosting tiene que llamar cada 15 o 30 minutos a la dirección que muestra la página Redacción IA (en cPanel, *Trabajos de cron*). Sin ese cron, el botón *Ejecutar lo pendiente ahora* hace lo mismo a mano.

**Historial.** La página lista cada generación con su resultado, los tokens que gastó y el costo cuando el proveedor lo informa (OpenRouter lo hace). Desde ahí se abre cada texto.

## Qué revisar antes de publicar

Un modelo escribe con soltura, no con verdad. Antes de publicar un artículo generado, comprueba los datos y las cifras, quita lo que suene a relleno y añade lo que solo tú sabes. En los resúmenes de noticias, las fuentes enlazadas sirven justo para eso: abre dos o tres y confirma que el resumen dice lo que dicen ellas.
