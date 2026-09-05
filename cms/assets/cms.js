/* cms_simple — cargador de librerías y utilidades para los paquetes de bloques y efectos (sin dependencias) */
(function () {
  "use strict";
  var C = window.CMS = window.CMS || {};
  var loading = {};

  function loadOne(url, isCss) {
    if (loading[url]) return loading[url];
    loading[url] = new Promise(function (resolve, reject) {
      var el;
      if (isCss) { el = document.createElement("link"); el.rel = "stylesheet"; el.href = url; }
      else { el = document.createElement("script"); el.src = url; el.async = false; }
      el.onload = function () { resolve(); };
      el.onerror = function () { reject(new Error("No se pudo cargar " + url)); };
      document.head.appendChild(el);
    });
    return loading[url];
  }

  /** CMS.load("gsap") → Promise; carga dependencias, CSS y JS de la librería una sola vez. */
  C.load = function (name) {
    var lib = (C.libs || {})[name];
    if (!lib) return Promise.reject(new Error("Librería desconocida: " + name));
    if (lib.global && window[lib.global]) return Promise.resolve(window[lib.global]);
    var p = Promise.resolve();
    (lib.requires || []).forEach(function (r) { p = p.then(function () { return C.load(r); }); });
    (lib.css || []).forEach(function (u) { p = p.then(function () { return loadOne(u, true); }); });
    (lib.js || []).forEach(function (u) { p = p.then(function () { return loadOne(u, false); }); });
    return p.then(function () { return lib.global ? window[lib.global] : true; });
  };
  /** CMS.loadAll(["gsap","scrolltrigger"]) → Promise */
  C.loadAll = function (names) { var p = Promise.resolve(); (names || []).forEach(function (n) { p = p.then(function () { return C.load(n); }); }); return p; };

  /** CMS.ready(fn): tras DOMContentLoaded (o de inmediato si ya pasó). */
  C.ready = function (fn) { if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn); else fn(); };

  /** CMS.inView(el, fn, margen): ejecuta fn una vez cuando el elemento se acerca al viewport (para cargar lo pesado bajo demanda). */
  C.inView = function (el, fn, margin) {
    if (!("IntersectionObserver" in window)) { fn(); return; }
    var io = new IntersectionObserver(function (entries) { if (entries.some(function (e) { return e.isIntersecting; })) { io.disconnect(); fn(); } }, { rootMargin: (margin || "600px") + " 0px" });
    io.observe(el);
  };

  /** CMS.effect("visual/shader", fn): fn(section) por cada sección con ese efecto; CMS.block("visual/galeria3d", fn) por cada bloque. */
  var handlers = { effect: {}, block: {}, site: {} };
  function run(kind, key, fn) {
    if (kind === "site") { if (C.siteEffects && C.siteEffects.indexOf(key) !== -1) fn(document.body); return; }
    var sel = kind === "block" ? '[data-block="' + key + '"]' : '[data-effect~="' + key + '"]';
    document.querySelectorAll(sel).forEach(function (el) { if (el.getAttribute("data-" + kind + "-done-" + key.replace(/\W/g, "_"))) return; el.setAttribute("data-" + kind + "-done-" + key.replace(/\W/g, "_"), "1"); fn(el); });
  }
  C.effect = function (key, fn) { handlers.effect[key] = fn; C.ready(function () { run("effect", key, fn); }); };
  C.block = function (key, fn) { handlers.block[key] = fn; C.ready(function () { run("block", key, fn); }); };
  C.site = function (key, fn) { handlers.site[key] = fn; C.ready(function () { run("site", key, fn); }); };
  /** Vuelve a inicializar (contenido añadido dinámicamente). */
  C.refresh = function () { Object.keys(handlers.block).forEach(function (k) { run("block", k, handlers.block[k]); }); Object.keys(handlers.effect).forEach(function (k) { run("effect", k, handlers.effect[k]); }); };
  C.reducedMotion = function () { return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches; };
  C.touch = function () { return window.matchMedia && window.matchMedia("(hover: none)").matches; };
})();
