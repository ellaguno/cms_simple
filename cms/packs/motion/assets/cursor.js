/* cms_simple · efecto de sitio motion/cursor — círculo que sigue al puntero y atrae los elementos [data-magnetic] (GSAP) */
(function () {
  "use strict";
  CMS.site("motion/cursor", function () {
    if (CMS.touch() || CMS.reducedMotion()) return;
    CMS.load("gsap").then(function () {
      var c = document.createElement("div"); c.className = "mo-cursor"; document.body.appendChild(c); document.body.classList.add("mo-has-cursor");
      var x = gsap.quickTo(c, "x", { duration: 0.25, ease: "power3" }), y = gsap.quickTo(c, "y", { duration: 0.25, ease: "power3" });
      window.addEventListener("mousemove", function (e) { c.classList.add("on"); x(e.clientX); y(e.clientY); });
      document.addEventListener("mouseleave", function () { c.classList.remove("on"); });
      document.querySelectorAll("a, button, [data-magnetic]").forEach(function (el) {
        el.addEventListener("mouseenter", function () { c.classList.add("hover"); });
        el.addEventListener("mouseleave", function () { c.classList.remove("hover"); if (el.hasAttribute("data-magnetic")) gsap.to(el, { x: 0, y: 0, duration: 0.5, ease: "elastic.out(1, 0.4)" }); });
        if (el.hasAttribute("data-magnetic")) el.addEventListener("mousemove", function (e) { var r = el.getBoundingClientRect(); gsap.to(el, { x: (e.clientX - r.left - r.width / 2) * 0.3, y: (e.clientY - r.top - r.height / 2) * 0.3, duration: 0.3 }); });
      });
    });
  });
})();
