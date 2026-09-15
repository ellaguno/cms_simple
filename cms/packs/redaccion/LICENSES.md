# Paquete redaccion — licencias

Código propio de cms_simple, licencia MIT (la del repositorio). Es una reescritura para cms_simple del plugin de
WordPress **AI Content Generator** (Eduardo Llaguno, GPL v2): mismos prompts de fondo (título, artículo por secciones,
resumen fiel a los titulares), pero sin tablas ni cron de WordPress; el historial y las URL usadas van en
`data/redaccion/`, y lo programado lo dispara el cron del hosting a través de `/_cms/cron`. No incluye código de WordPress.

Los proveedores (OpenRouter, OpenAI, Anthropic, DeepSeek) y Google News tienen sus propios términos de uso; las claves
se guardan en `data/settings.json`, que el `.htaccess` no sirve por HTTP.
