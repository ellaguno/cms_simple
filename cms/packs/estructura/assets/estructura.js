/* Paquete estructura: menú móvil, desplegables y cabecera fija o transparente. */
(function () {
  "use strict";
  function init() {
    document.querySelectorAll("[data-es-header]").forEach(function (h) {
      var sec = h.closest(".es-header") || h;
      if (h.hasAttribute("data-es-sticky")) sec.classList.add("is-sticky");
      if (h.hasAttribute("data-es-transparent")) {
        sec.classList.add("is-transparent");
        var onScroll = function () { sec.classList.toggle("is-scrolled", window.scrollY > 40); };
        window.addEventListener("scroll", onScroll, { passive: true }); onScroll();
      }
      var t = h.querySelector("[data-es-toggle]"), n = h.querySelector("[data-es-nav]");
      if (t && n) t.addEventListener("click", function () { var open = n.classList.toggle("is-open"); t.classList.toggle("is-open", open); t.setAttribute("aria-expanded", open ? "true" : "false"); });
      h.querySelectorAll("[data-es-sub]").forEach(function (b) {
        b.addEventListener("click", function (e) { e.preventDefault(); var it = b.closest(".es-nav-item"); var open = it.classList.toggle("is-open"); h.querySelectorAll(".es-nav-item.is-open").forEach(function (o) { if (o !== it) o.classList.remove("is-open"); }); b.setAttribute("aria-expanded", open ? "true" : "false"); });
      });
    });
    document.addEventListener("click", function (e) { if (!e.target.closest(".es-nav-item")) document.querySelectorAll(".es-nav-item.is-open").forEach(function (o) { o.classList.remove("is-open"); }); });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
