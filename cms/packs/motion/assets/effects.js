/* cms_simple · efectos motion/reveal, motion/stagger, motion/parallax — GSAP 3.13 (gratuito) */
(function () {
  "use strict";
  CMS.effect("motion/reveal", function (sec) {
    if (CMS.reducedMotion()) return;
    var o = CMS.fx(sec, "motion/reveal");
    var speed = CMS.fxNum(o, "speed", 0.7, 0.1, 3), gap = CMS.fxNum(o, "gap", 0.02, 0, 0.3);
    var from = o.from || "bottom", unit = o.unit === "words" ? "words" : "chars";
    var targets = sec.querySelectorAll("[data-reveal], h1, h2, h3, .mo-statement-text");
    if (!targets.length) return;
    targets.forEach(function (t) { t.classList.add("mo-reveal-pending"); });
    CMS.inView(sec, function () {
      CMS.loadAll(["gsap", "scrolltrigger", "splittext"]).then(function () {
        gsap.registerPlugin(ScrollTrigger, SplitText);
        targets.forEach(function (t) {
          var split = new SplitText(t, { type: "chars,words" });
          t.classList.remove("mo-reveal-pending");
          var pieces = unit === "words" ? split.words : split.chars;
          var vars = { opacity: 0, stagger: gap, duration: speed, ease: "back.out(1.4)", scrollTrigger: { trigger: t, start: "top 85%", once: true } };
          if (from === "scale") { vars.scale = 0.5; vars.transformOrigin = "50% 50%"; }
          else { vars.y = from === "top" ? "-60%" : "60%"; vars.rotateX = from === "top" ? 60 : -60; }
          gsap.from(pieces, vars);
        });
      });
    }, "300px");
  });

  CMS.effect("motion/stagger", function (sec) {
    if (CMS.reducedMotion()) return;
    var o = CMS.fx(sec, "motion/stagger");
    var speed = CMS.fxNum(o, "speed", 0.7, 0.1, 3), gap = CMS.fxNum(o, "gap", 0.12, 0, 1), dist = CMS.fxNum(o, "distance", 40, 0, 200);
    var grid = sec.querySelector('[class*="grid"], .mo-counters, .mo-parallax'); if (!grid) return;
    var items = Array.from(grid.children); if (!items.length) return;
    items.forEach(function (i) { i.style.opacity = "0"; });
    CMS.inView(sec, function () {
      CMS.loadAll(["gsap", "scrolltrigger"]).then(function () {
        gsap.registerPlugin(ScrollTrigger);
        gsap.fromTo(items, { opacity: 0, y: dist }, { opacity: 1, y: 0, duration: speed, stagger: gap, ease: "power3.out", scrollTrigger: { trigger: grid, start: "top 85%", once: true }, clearProps: "opacity,transform" });
      });
    }, "300px");
  });

  CMS.effect("motion/parallax", function (sec) {
    if (CMS.reducedMotion() || CMS.touch()) return;
    var o = CMS.fx(sec, "motion/parallax");
    var amount = CMS.fxNum(o, "amount", 15, 0, 60) * (o.direction === "reverse" ? -1 : 1);
    CMS.loadAll(["gsap", "scrolltrigger"]).then(function () {
      gsap.registerPlugin(ScrollTrigger);
      var target = sec.classList.contains("sec-has-bg") ? sec : sec.querySelector("img, .vis-shader, .sec-figure");
      if (!target) return;
      if (target === sec) {
        sec.style.backgroundAttachment = "scroll";
        gsap.fromTo(sec, { backgroundPositionY: -amount + "%" }, { backgroundPositionY: amount + "%", ease: "none", scrollTrigger: { trigger: sec, start: "top bottom", end: "bottom top", scrub: true } });
      } else {
        var y = amount * 0.55;
        gsap.fromTo(target, { yPercent: -y }, { yPercent: y, ease: "none", scrollTrigger: { trigger: sec, start: "top bottom", end: "bottom top", scrub: true } });
      }
    });
  });
})();
