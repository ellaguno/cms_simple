/* All Design — comportamientos del tema: menú, pase de diapositivas, acordeón, equipo, barras, compartir,
   visor de galerías (GLightbox por CMS.load), formulario y volver arriba. Sin dependencias. */
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

  /* ---------- pase de diapositivas de la portada ---------- */
  function slideshow(root) {
    var slides = qsa(".ad-slide", root), dots = qsa("[data-ad-dot]", root), i = 0, timer = null;
    var interval = parseInt(root.getAttribute("data-interval"), 10) || 5000;
    if (slides.length < 2) return;
    function go(n) {
      slides[i].classList.remove("is-active"); if (dots[i]) dots[i].classList.remove("is-active");
      i = (n + slides.length) % slides.length;
      slides[i].classList.add("is-active"); if (dots[i]) dots[i].classList.add("is-active");
    }
    function play() { if (reduce) return; stop(); timer = setInterval(function () { go(i + 1); }, interval); }
    function stop() { if (timer) clearInterval(timer); timer = null; }
    var prev = qs("[data-ad-prev]", root), next = qs("[data-ad-next]", root);
    if (prev) on(prev, "click", function () { go(i - 1); play(); });
    if (next) on(next, "click", function () { go(i + 1); play(); });
    dots.forEach(function (d) { on(d, "click", function () { go(parseInt(d.getAttribute("data-ad-dot"), 10)); play(); }); });
    on(root, "mouseenter", stop); on(root, "mouseleave", play);
    var x0 = null;
    on(root, "touchstart", function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    on(root, "touchend", function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0; x0 = null;
      if (Math.abs(dx) > 40) { go(dx < 0 ? i + 1 : i - 1); play(); }
    }, { passive: true });
    on(document, "visibilitychange", function () { document.hidden ? stop() : play(); });
    play();
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

  /* ---------- equipo ---------- */
  function team(root) {
    var members = qsa(".ad-member", root), i = 0, timer = null;
    var interval = parseInt(root.getAttribute("data-interval"), 10) || 0;
    if (members.length < 2) { qsa(".ad-team-btn", root).forEach(function (b) { b.style.visibility = "hidden"; }); return; }
    function go(n) {
      members[i].classList.remove("is-active"); members[i].hidden = true;
      i = (n + members.length) % members.length;
      members[i].hidden = false; members[i].classList.add("is-active");
    }
    function play() { if (!interval || reduce) return; stop(); timer = setInterval(function () { go(i + 1); }, interval); }
    function stop() { if (timer) clearInterval(timer); timer = null; }
    on(qs("[data-ad-prev]", root), "click", function () { go(i - 1); play(); });
    on(qs("[data-ad-next]", root), "click", function () { go(i + 1); play(); });
    var x0 = null;
    on(root, "touchstart", function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    on(root, "touchend", function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(dx) > 40) { go(dx < 0 ? i + 1 : i - 1); play(); } }, { passive: true });
    on(root, "mouseenter", stop); on(root, "mouseleave", play);
    play();
  }

  /* ---------- barras de habilidades ---------- */
  function skills(root) {
    if (!("IntersectionObserver" in window) || reduce) { root.classList.add("is-visible"); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { root.classList.add("is-visible"); io.disconnect(); } });
    }, { threshold: 0.3 });
    io.observe(root);
  }

  /* ---------- compartir ---------- */
  function share(btn) {
    var menu = btn.parentNode.querySelector("[data-ad-share-menu]");
    var url = location.href, title = document.title;
    var links = {
      facebook: "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url),
      x: "https://twitter.com/intent/tweet?url=" + encodeURIComponent(url) + "&text=" + encodeURIComponent(title),
      linkedin: "https://www.linkedin.com/sharing/share-offsite/?url=" + encodeURIComponent(url),
      whatsapp: "https://wa.me/?text=" + encodeURIComponent(title + " " + url)
    };
    qsa("[data-share]", menu).forEach(function (a) { a.href = links[a.getAttribute("data-share")] || "#"; });
    on(btn, "click", function (e) {
      e.stopPropagation();
      if (navigator.share && window.matchMedia("(hover: none)").matches) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      var open = menu.hidden; menu.hidden = !open; btn.classList.toggle("is-open", open);
    });
    on(document, "click", function (e) { if (!e.target.closest(".ad-tab")) { menu.hidden = true; btn.classList.remove("is-open"); } });
    on(document, "keydown", function (e) { if (e.key === "Escape") { menu.hidden = true; btn.classList.remove("is-open"); } });
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

  /* ---------- volver arriba ---------- */
  function totop() {
    qsa("[data-ad-totop]").forEach(function (a) { on(a, "click", function (e) { e.preventDefault(); window.scrollTo({ top: 0, behavior: reduce ? "auto" : "smooth" }); }); });
  }

  function init() {
    nav();
    qsa("[data-ad-slideshow]").forEach(slideshow);
    qsa("[data-ad-accordion]").forEach(accordion);
    qsa("[data-ad-team]").forEach(team);
    qsa("[data-ad-skills]").forEach(skills);
    qsa("[data-ad-share]").forEach(share);
    qsa("[data-ad-form]").forEach(form);
    lightbox();
    totop();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
