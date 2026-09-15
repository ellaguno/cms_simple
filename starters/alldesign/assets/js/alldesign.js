/* All Design — comportamientos del tema: menú, cabecera que esconde la barra social, acordeón, visor de galerías
   (GLightbox por CMS.load), formulario y volver arriba. El pase de imágenes, el equipo, las barras y el botón de
   compartir los pone el paquete agencia del núcleo. Sin dependencias. */
(function () {
  "use strict";
  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function on(el, ev, fn, opts) { el.addEventListener(ev, fn, opts || false); }
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  /* ---------- menú ---------- */
  function nav() {
    var toggle = qs("[data-ad-nav-toggle]"), nav = qs("#ad-nav");
    if (toggle && nav) {
      on(toggle, "click", function () {
        var open = nav.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
      });
    }
    qsa("[data-ad-sub-toggle]").forEach(function (btn) {
      on(btn, "click", function () { btn.parentNode.classList.toggle("is-open"); });
    });
    // en pantallas táctiles, el primer toque en "Portafolio" abre el desplegable
    qsa(".ad-nav > ul > li.has-sub > a").forEach(function (a) {
      on(a, "click", function (e) {
        var li = a.parentNode;
        if (window.matchMedia("(hover: none)").matches && window.innerWidth > 900 && !li.classList.contains("is-open")) {
          e.preventDefault(); li.classList.add("is-open");
        }
      });
    });
    on(document, "click", function (e) {
      if (!e.target.closest(".ad-nav") && !e.target.closest("[data-ad-nav-toggle]")) qsa(".ad-nav li.is-open").forEach(function (li) { li.classList.remove("is-open"); });
    });
  }

  /* ---------- acordeón ---------- */
  function accordion(root) {
    var items = qsa(".ad-acc-item", root);
    items.forEach(function (item) {
      var btn = qs("[data-ad-acc-btn]", item), panel = qs(".ad-acc-panel", item);
      on(btn, "click", function () {
        var open = item.classList.contains("is-open");
        items.forEach(function (o) { o.classList.remove("is-open"); qs(".ad-acc-panel", o).hidden = true; qs("[data-ad-acc-btn]", o).setAttribute("aria-expanded", "false"); });
        if (!open) { item.classList.add("is-open"); panel.hidden = false; btn.setAttribute("aria-expanded", "true"); }
      });
    });
  }

  /* ---------- visor de galerías ---------- */
  function lightbox() {
    if (!qs(".ad-galeria .glightbox")) return;
    function init() {
      if (!window.GLightbox) return;
      window.GLightbox({ selector: ".ad-galeria .glightbox", touchNavigation: true, loop: true, openEffect: "fade", closeEffect: "fade", moreText: "Ver más", moreLength: 0 });
    }
    if (window.GLightbox) init();
    else if (window.CMS && CMS.load) CMS.load("glightbox").then(init).catch(function () {});
  }

  /* ---------- formulario de contacto ---------- */
  function form(f) {
    var msg = qs("[data-ad-form-msg]", f), btn = qs("button[type=submit]", f);
    var texts = { sending: f.getAttribute("data-msg-sending"), ok: f.getAttribute("data-msg-ok"), err: f.getAttribute("data-msg-err"), invalid: f.getAttribute("data-msg-invalid") };
    function show(cls, text) { msg.hidden = false; msg.className = "ad-form-msg " + cls; msg.textContent = text; }
    on(f, "submit", function (e) {
      e.preventDefault();
      var data = new FormData(f);
      var nombre = (data.get("nombre") || "").trim(), correo = (data.get("correo") || "").trim();
      if (!nombre || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(correo)) { show("is-err", texts.invalid); return; }
      if (data.get("empresa_web2")) return;
      btn.disabled = true; show("", texts.sending);
      fetch(f.action, { method: "POST", body: data, headers: { "Accept": "application/json" } })
        .then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); })
        .then(function (j) { if (j && j.ok) { show("is-ok", texts.ok); f.reset(); } else { show("is-err", texts.err); } })
        .catch(function () { show("is-err", texts.err); })
        .then(function () { btn.disabled = false; });
    });
  }

  /* ---------- cabecera: la barra social se esconde al hacer scroll y el menú se queda pegado arriba ---------- */
  function header() {
    var hdr = qs(".ad-header"), top = qs(".ad-topbar", hdr);
    if (!hdr || !top) return;
    var h = top.offsetHeight, last = -1, ticking = false;
    function upd() {
      ticking = false;
      var y = Math.min(Math.max(window.scrollY || 0, 0), h);
      if (y === last) return;
      last = y; hdr.style.transform = y ? "translateY(-" + y + "px)" : "";
      hdr.classList.toggle("is-scrolled", y >= h);
    }
    on(window, "scroll", function () { if (!ticking) { ticking = true; requestAnimationFrame(upd); } }, { passive: true });
    on(window, "resize", function () { h = top.offsetHeight; last = -1; upd(); });
    upd();
  }

  /* ---------- volver arriba ---------- */
  function totop() {
    qsa("[data-ad-totop]").forEach(function (a) { on(a, "click", function (e) { e.preventDefault(); window.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" }); }); });
  }

  function init() {
    nav();
    header();
    qsa("[data-ad-accordion]").forEach(accordion);
    qsa("[data-ad-form]").forEach(form);
    lightbox();
    totop();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
