# 7. SEO: que te encuentren en buscadores

> Lo que el sistema hace solo, lo que depende de ti, y en qué orden atacarlo.

## Lo que ya está resuelto

No tienes que hacer nada de esto; conviene saber que existe:

- **Un solo dominio**: la URL canónica de Ajustes fija el dominio en cada página, y `www` o `http` redirigen.
- **Títulos y descripciones** por página, con lo que escribes en SEO o en el título y resumen.
- **Datos estructurados**: organización, sitio, migas de pan, artículos, preguntas frecuentes y producto, en el formato que Google lee.
- **Sitemap** en `/sitemap.xml`, con solo las páginas con contenido real, y `robots.txt`.
- **Noindex** en páginas de relleno, para no diluir el sitio.
- **Imágenes en WebP** con tamaño declarado, y librerías pesadas cargadas solo cuando hacen falta.
- **Enlaces para compartir** con imagen y texto correctos en redes y mensajería.

## Lo que depende de ti

En orden de impacto:

### 1. Una página por intención de búsqueda

Google posiciona páginas, no sitios. Cada cosa que alguien pueda buscar merece su propia página con su propio título: un servicio, un problema que resuelves, una comparación con una alternativa, una guía. Una portada que intenta cubrir todo no posiciona por nada.

### 2. Títulos que contienen lo que la gente escribe

El título SEO es el texto azul del resultado. Que diga qué es y para quién, con las palabras del que busca: "Software de gestión de casos con IA para abogados" gana a "El derecho a un clic". Entre 50 y 60 caracteres.

La descripción no posiciona, pero decide si hacen clic. Una promesa concreta y una razón, en 150 caracteres.

### 3. Texto real

Un buscador no puede leer un video ni una imagen. Cada página necesita texto que explique el tema, con el título en un solo Título 1, subtítulos en Título 2 y párrafos que respondan preguntas. Trescientas palabras es un mínimo razonable para una página que quiere posicionar; un artículo serio suele pasar de ochocientas.

### 4. Enlaces internos

Enlaza desde el texto a tus otras páginas con palabras que describan el destino, como "nuestros planes para despachos", no "haz clic aquí". Cada enlace le dice al buscador de qué trata la página de destino y reparte la autoridad.

### 5. Imágenes con nombre y texto alternativo

Explicado en [Artículos y el editor visual](cap:06-articulos-y-editor). Es barato y suma en las búsquedas de imágenes.

### 6. Velocidad

El sistema ya carga lo pesado bajo demanda. Lo que puedes estropear: imágenes enormes sin comprimir y videos incrustados por docenas en una misma página. Comprime antes de subir, y una página con un video es mejor que una con seis.

### 7. Herramientas de los buscadores

- **Google Search Console**: registra el dominio con el método de DNS, envía el sitemap y pide la indexación de las páginas nuevas con "Inspección de URL". Revisa "Páginas" y "Mejoras" cada semana.
- **Bing Webmaster Tools**: casi nadie lo registra, y Bing alimenta a ChatGPT y Copilot. Importa el sitio desde Search Console en un clic.

### 8. Cuando cambies una URL

Crea una redirección 301 de la vieja a la nueva. Sin ella, pierdes lo que la página había ganado y los enlaces externos quedan rotos.

## Errores comunes

- Publicar páginas casi vacías "para completar". Diluyen. Mejor borrador hasta que tengan contenido.
- Repetir el mismo título en varias páginas.
- Usar acentos a medias. Escribe con ortografía completa; el buscador diferencia.
- Textos escritos para el buscador y no para la persona. Google lo detecta y, peor, la persona también.
- Cambiar URL por gusto. Una URL estable vale más que una bonita.
