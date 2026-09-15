# Paquete audio — licencias

Código propio de cms_simple, licencia MIT (la del repositorio). Es una reescritura para cms_simple del plugin de
WordPress **TTS SesoLibre** (Eduardo Llaguno, GPL v2): sin SDK de AWS ni de Google, solo HTTP; los MP3 se guardan en
`uploads/audio/` y no hay tabla ni base de datos. No incluye código de WordPress.

`assets/silencio.mp3`: 1.5 s de silencio generado con ffmpeg para el proveedor de prueba; sin derechos de terceros.

Los proveedores (OpenAI, ElevenLabs, Microsoft Azure, Google Cloud) son servicios de pago con sus propios términos;
las claves se guardan en `data/settings.json`, que el `.htaccess` no sirve por HTTP.
