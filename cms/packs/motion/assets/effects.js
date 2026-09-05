/* cms_simple · efectos motion/reveal, motion/stagger, motion/parallax — GSAP 3.13 (gratuito) */
(function () {
  "use strict";
  CMS.effect("motion/reveal", function (sec) {
    if (CMS.reducedMotion()) return;
    var targets = sec.querySelectorAll("[data-reveal], h1, h2, h3, .mo-statement-text");
    if (!targets.length) return;
    targets.forEach(function (t) { t.classList.add("mo-reveal-pending"); });
    CMS.inView(sec, function () {
      CMS.loadAll(["gsap", "scrolltrigger", "splittext"]).then(function () {
        gsap.registerPlugin(ScrollTrigger, SplitText);
        targets.forEach(function (t) {
          var split = new SplitText(t, { type: "chars,words" });
          t.classList.remove("mo-reveal-pending");
          gsap.from(split.chars, { opacity: 0, y: "60%", rotateX: -60, stagger: 0.02, duration: 0.7, ease: "back.out(1.4)", scrollTrigger: { trigger: t, start: "top 85%", once: true } });
        });
      });
    }, "300px");
  });

  CMS.effect("motion/stagger", function (sec) {
    if (CMS.reducedMotion()) return;
    var grid = sec.querySelector('[class*="grid"], .mo-counters, .mo-parallax'); if (!grid) return;
    var items = Array.from(grid.children); if (!items.length) return;
    items.forEach(function (i) { i.style.opacity = "0"; });
    CMS.inView(sec, function () {
      CMS.loadAll(["gsap", "scrolltrigger"]).then(function () {
        gsap.registerPlugin(ScrollTrigger);
        gsap.fromTo(items, { opacity: 0, y: 40 }, { opacity: 1, y: 0, duration: 0.7, stagger: 0.12, ease: "power3.out", scrollTrigger: { trigger: grid, start: "top 85%", once: true }, clearProps: "opacity,transform" });
      });
    }, "300px");
  });

  CMS.effect("motion/parallax", function (sec) {
    if (CMS.reducedMotion() || CMS.touch()) return;
    CMS.loadAll(["gsap", "scrolltrigger"]).then(function () {
      gsap.registerPlugin(ScrollTrigger);
      var target = sec.classList.contains("sec-has-bg") ? sec : sec.querySelector("img, .vis-shader, .sec-figure");
      if (!target) return;
      if (target === sec) {
        sec.style.backgroundAttachment = "scroll";
        gsap.fromTo(sec, { backgroundPositionY: "-15%" }, { backgroundPositionY: "15%", ease: "none", scrollTrigger: { trigger: sec, start: "top bottom", end: "bottom top", scrub: true } });
      } else {
        gsap.fromTo(target, { yPercent: -8 }, { yPercent: 8, ease: "none", scrollTrigger: { trigger: sec, start: "top bottom", end: "bottom top", scrub: true } });
      }
    });
  });
})();
