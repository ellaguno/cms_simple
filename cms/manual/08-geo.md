# 8. GEO: que los asistentes de IA te citen

> Cómo eligen sus fuentes ChatGPT, Gemini, Claude o Perplexity, y cómo escribir para que te tomen en cuenta.

## Qué cambia respecto al SEO

Un buscador clásico devuelve una lista de enlaces; el usuario elige. Un asistente de IA redacta una respuesta y cita dos o tres fuentes; el usuario rara vez visita más. Para aparecer ahí no basta con posicionar: hay que ser **la fuente más clara y verificable** sobre el tema.

La buena noticia: todo lo que hace bien el SEO sirve para el GEO. Lo que sigue son las diferencias.

## 1. Que puedan leerte

Los asistentes rastrean con sus propios robots, distintos de Google. Algunos servicios de protección, como Cloudflare, los bloquean por defecto en el `robots.txt` administrado. Comprueba `tudominio.com/robots.txt`: si ves bloqueados GPTBot, ClaudeBot, PerplexityBot o Google-Extended, ningún asistente puede citarte. Se corrige en el panel del proveedor, no aquí.

El sitio publica además `/llms.txt`, un resumen en texto plano de qué es el producto y cuáles son las páginas clave. Varios asistentes lo leen primero. Quien programa el tema lo edita en el archivo `site/llms.txt`.

## 2. Frases citables

Los asistentes extraen definiciones directas y las reproducen casi literalmente. Cada página importante debería abrir con una o dos oraciones que un tercero pueda citar tal cual:

> Iurefficient es un software de gestión de casos con inteligencia artificial para despachos de abogados en México. Organiza expedientes, analiza documentos y redacta con IA, con los datos alojados en infraestructura propia.

Compara con un eslogan: "El derecho a un clic de distancia". Bonito, pero no dice qué es, y un asistente no puede citarlo como hecho.

## 3. Preguntas con respuesta corta

El bloque de preguntas frecuentes se publica en un formato que los asistentes entienden directamente. Escribe preguntas como las haría un cliente, "¿cuánto cuesta?", "¿dónde se guardan mis datos?", "¿sirve para un despacho de una persona?", y responde en la primera oración, sin rodeos. El resto de la respuesta puede matizar.

## 4. Una sola entidad, igual en todas partes

Los modelos cruzan fuentes. Si el nombre, la descripción y los datos de la empresa coinciden en el sitio, en LinkedIn, en los directorios y en los medios, ganan confianza. Si cada lugar dice algo distinto, la ignoran. Usa el mismo nombre con la misma ortografía, la misma frase de descripción y el mismo logotipo en todos lados.

## 5. Presencia fuera del sitio

Un asistente pondera lo que ve repetido en fuentes independientes. Para un producto o servicio:

- Fichas completas en directorios de la categoría: para software, G2, Capterra, Software Advice, Product Hunt; para agencias, Clutch, Behance, directorios locales.
- Perfil de empresa en LinkedIn con la misma descripción del sitio.
- Menciones en medios del sector, entrevistas, podcasts. Un artículo de terceros vale más que diez propios.
- Reseñas con nombre y contexto.

## 6. Hechos, cifras y fechas

Los asistentes prefieren afirmaciones concretas y fechadas: "reduce 40 % el tiempo de redacción, según 12 despachos en 2026" pesa más que "ahorra mucho tiempo". Las notas de versión, los casos con resultados y las cifras de uso son el mejor material. Publícalos como artículos con fecha visible.

## 7. Frescura

Un contenido con fecha reciente y actualizada se cita más. Revisa las páginas clave cada pocos meses, corrige lo que cambió y guarda: la fecha de actualización se publica sola.

## Lista de comprobación

- [ ] `robots.txt` no bloquea a los robots de IA.
- [ ] Cada página clave abre con una definición citable.
- [ ] Preguntas frecuentes con respuesta en la primera oración.
- [ ] Mismo nombre y descripción en sitio, redes y directorios.
- [ ] Al menos tres fuentes externas que hablen de ti.
- [ ] Artículos con datos, fechas y resultados.
- [ ] `llms.txt` al día.
