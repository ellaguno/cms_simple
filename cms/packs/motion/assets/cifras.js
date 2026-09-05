/* cms_simple · motion/cifras — cuenta con GSAP al entrar en pantalla */
(function () {
  "use strict";
  CMS.block("motion/cifras", function (sec) {
    var nums = sec.querySelectorAll(".mo-counter-value"); if (!nums.length) return;
    CMS.inView(sec, function () {
      CMS.loadAll(["gsap", "scrolltrigger"]).then(function () {
        gsap.registerPlugin(ScrollTrigger);
        nums.forEach(function (el) {
          var target = parseFloat(el.getAttribute("data-count") || "0"), dec = parseInt(el.getAttribute("data-decimals") || "0", 10), o = { v: 0 };
          gsap.to(o, { v: target, duration: 1.8, ease: "power2.out", scrollTrigger: { trigger: el, start: "top 90%", once: true },
            onUpdate: function () { el.textContent = o.v.toLocaleString("es-MX", { minimumFractionDigits: dec, maximumFractionDigits: dec }); } });
        });
      });
    }, "200px");
  });
})();
