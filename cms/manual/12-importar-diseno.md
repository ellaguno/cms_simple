# 12. Importar un diseño

> De un PDF o una imagen de una página, hecha por quien diseña, a un borrador del constructor con las secciones y los textos ya puestos.

## Qué hace

**Importar diseño** toma el archivo de una página (un PDF de una o varias páginas, o una captura PNG o JPG) y pide a un modelo de inteligencia artificial con visión que la reconstruya con los bloques del sitio: la misma sucesión de bandas, los mismos textos, el fondo claro u oscuro de cada una. El resultado es un **borrador** que se abre en el constructor como cualquier otra página.

No es una copia exacta. Es un arranque: la estructura y el copy quedan hechos, y tú ajustas detalles, subes las imágenes y publicas. En las pruebas con una portada real, un buen modelo acierta todas las secciones y todos los títulos; con uno pequeño hay que reordenar bastante.

## Antes de empezar: el motor

En la propia pantalla, en **Motor de análisis**, se elige:

- **Proveedor.** OpenRouter da acceso a cualquier modelo con visión de cualquier fabricante con una sola clave. Se crea en openrouter.ai, se carga saldo y se pega aquí; queda en `data/settings.json`, fuera del repositorio. En un equipo de desarrollo con Claude Code instalado aparece además la opción de usarlo directamente, sin clave.
- **Modelo.** La lista muestra solo modelos con visión, con su precio por millón de tokens de entrada y salida. Recomendación: Claude Sonnet como predeterminado y Opus para diseños complejos. Una página de 6 a 8 pantallas cuesta unos centavos con Sonnet y menos de un dólar con Opus. Los modelos sin "salida estructurada" también funcionan, pero con más riesgo de que la respuesta no sea válida.

## Preparar el archivo

- **Figma, Illustrator, Photoshop, Inkscape, LibreOffice Draw, Word.** Exporta a PDF. Todos lo hacen en un clic, y el PDF conserva el texto, que es lo que garantiza que el copy salga exacto con sus acentos.
- **Una captura de pantalla** también sirve. El modelo lee el texto de la imagen; revisa los acentos después.
- Una landing larga puede venir como un PDF de una sola página muy alta o partida en varias páginas. Da igual: el panel la apila y la corta en pantallas de 1400 por 1100 píxeles antes de enviarla.
- Lo que hay que evitar: PDF escaneados en baja resolución, diseños a doble columna de varias páginas (eso no es una página web) y archivos con más de unas 40 pantallas.

## Importar

1. Arrastra el archivo o pulsa **Elegir archivo**.
2. Escribe el título de la página, o déjalo y se toma del diseño. La URL se genera sola.
3. Elige la página padre y las opciones del tipo, como la cabecera y pie de una marca.
4. **Analizar y crear borrador.** El navegador rasteriza el archivo y muestra las pantallas que va cortando; después el modelo tarda entre uno y tres minutos. No cierres la pestaña.

Al terminar se muestra la lista de secciones creadas, el costo, y tres cosas que conviene leer:

- **Notas del análisis.** Por cada sección, qué imagen va ahí y cómo es, o qué detalle del diseño no cupo en el bloque.
- **Partes sin bloque equivalente.** Lo que el catálogo del sitio no tiene: un sello circular, un botón suelto, una doble cinta. Se resuelve con otro bloque, con HTML, o pidiendo un bloque nuevo a quien programa.
- **Paleta y tipografías del diseño**, por si quieres llevarlas a los colores del sitio en Ajustes.

Todo esto queda guardado en la página: en el constructor, en la columna derecha, el panel **Diseño importado** muestra las pantallas de referencia en miniatura y las notas, para comparar mientras editas.

## Qué revisar en el borrador

- **Imágenes.** Vienen vacías. Súbelas desde Medios con los originales de quien diseñó; la nota de cada sección dice cuál va.
- **Enlaces de los botones.** Si el diseño no muestra la URL, quedan como `#`.
- **Planes, preguntas frecuentes, equipo, artículos.** Esos bloques toman su contenido de las colecciones del sitio. El importador coloca el bloque y anota lo que el diseño mostraba, por ejemplo los precios; cárgalo en la colección correspondiente.
- **Traducción.** El borrador se crea en el idioma del diseño. Los demás idiomas se completan en el mismo editor.

## Desactivar el importador

En `site/config.php`, `'importer' => false` quita la entrada del menú. Es útil en sitios donde nadie va a usarlo o donde no se quiere guardar una clave de API.
