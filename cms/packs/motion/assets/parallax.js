/* cms_simple · motion/parallax (galería) — columnas a distinta velocidad con ScrollTrigger */
(function () {
  "use strict";
  CMS.block("motion/parallax", function (sec) {
    if (CMS.reducedMotion() || CMS.touch()) return;
    CMS.inView(sec, function () {
      CMS.loadAll(["gsap", "scrolltrigger"]).then(function () {
        gsap.registerPlugin(ScrollTrigger);
        sec.querySelectorAll(".mo-parallax-col").forEach(function (col) {
          var speed = parseFloat(col.getAttribute("data-speed") || "1");
          gsap.fromTo(col, { y: (1 - speed) * -120 }, { y: (1 - speed) * 120, ease: "none", scrollTrigger: { trigger: sec, start: "top bottom", end: "bottom top", scrub: true } });
        });
      });
    }, "300px");
  });
})();
