/* cms_simple · paquete marketing — efectos de fondo: aurora, rejilla con haz, partículas, meteoros (sin librerías) */
(function () {
  "use strict";
  var reduce = CMS.reducedMotion();
  function layer(sec, cls, html) {
    var el = document.createElement("div"); el.className = "mk-fx " + cls; el.setAttribute("aria-hidden", "true"); if (html) el.innerHTML = html;
    CMS.bgLayer(sec, el); return el;
  }

  CMS.effect("marketing/aurora", function (sec) { layer(sec, "mk-aurora", "<span></span><span></span><span></span>"); });

  CMS.effect("marketing/rejilla", function (sec) {
    var beam = CMS.fx(sec, "marketing/rejilla").beam || "both";
    var html = reduce || beam === "none" ? "" : '<span class="mk-grid-beam"></span>' + (beam === "both" ? '<span class="mk-grid-beam mk-grid-beam-h"></span>' : "");
    var el = layer(sec, "mk-grid", html);
    if (CMS.fx(sec, "marketing/rejilla").color) el.setAttribute("data-color", "1");
  });

  CMS.effect("marketing/meteoros", function (sec) {
    var o = CMS.fx(sec, "marketing/meteoros");
    var nStars = CMS.fxNum(o, "stars", 90, 0, 400), nMet = CMS.fxNum(o, "meteors", 9, 0, 40), dur = CMS.fxNum(o, "speed", 5, 1, 20);
    var color = /^#[0-9a-f]{6}$/i.test(o.color || "") ? o.color : "#ffffff";
    var el = layer(sec, "mk-sky"), w = sec.clientWidth || 1200, h = sec.clientHeight || 600, shadows = [];
    el.style.setProperty("--mk-star-color", color);
    for (var i = 0; i < nStars; i++) { var a = (Math.random() * .6 + .3).toFixed(2), s = Math.random() < .2 ? 2 : 1; shadows.push(Math.round(Math.random() * w) + "px " + Math.round(Math.random() * h) + "px 0 " + (s - 1) + "px color-mix(in srgb, " + color + " " + Math.round(a * 100) + "%, transparent)"); }
    var stars = document.createElement("span"); stars.className = "mk-sky-stars"; stars.style.boxShadow = shadows.join(","); el.appendChild(stars);
    if (reduce) return;
    for (var m = 0; m < nMet; m++) {
      var me = document.createElement("span"); me.className = "mk-meteor";
      me.style.left = Math.round(Math.random() * 100) + "%"; me.style.top = Math.round(Math.random() * 60 - 10) + "%";
      me.style.setProperty("--d", (dur * (0.7 + Math.random() * 0.8)).toFixed(1) + "s"); me.style.setProperty("--delay", (Math.random() * dur * 1.8).toFixed(1) + "s");
      el.appendChild(me);
    }
  });

  CMS.effect("marketing/particulas", function (sec) {
    var o = CMS.fx(sec, "marketing/particulas");
    var area = CMS.fxNum(o, "density", 14000, 4000, 40000), link = CMS.fxNum(o, "link", 120, 40, 300);
    var vel = CMS.fxNum(o, "speed", 0.35, 0.02, 2), interact = o.interact === undefined ? true : (!!o.interact && o.interact !== "0");
    var el = layer(sec, "mk-particles-wrap"), c = document.createElement("canvas"); c.className = "mk-particles"; el.appendChild(c);
    var ctx = c.getContext("2d"), dots = [], W = 0, H = 0, dpr = Math.min(2, window.devicePixelRatio || 1), mouse = { x: -1e4, y: -1e4 }, running = false, raf = 0, color = "";
    function rgb() {
      var hex = /^#([0-9a-f]{6})$/i.exec(o.color || "");
      if (hex) { var n = parseInt(hex[1], 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255].join(","); }
      var m = (getComputedStyle(sec).color || "").match(/\d+/g); return m ? m.slice(0, 3).join(",") : "120,120,120";
    }
    function size() {
      W = sec.clientWidth; H = sec.clientHeight; c.width = W * dpr; c.height = H * dpr; c.style.width = W + "px"; c.style.height = H + "px"; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.min(220, Math.max(12, Math.round(W * H / area))); dots = [];
      for (var i = 0; i < n; i++) dots.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * vel, vy: (Math.random() - .5) * vel, r: 1 + Math.random() * 1.5 });
      color = rgb();
    }
    function draw() {
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < dots.length; i++) {
        var d = dots[i];
        if (!reduce) {
          d.x += d.vx; d.y += d.vy;
          if (d.x < 0 || d.x > W) d.vx *= -1; if (d.y < 0 || d.y > H) d.vy *= -1;
          if (interact) { var dx = mouse.x - d.x, dy = mouse.y - d.y, dist = Math.sqrt(dx * dx + dy * dy); if (dist < 140) { d.x -= dx / dist * .6; d.y -= dy / dist * .6; } }
        }
        ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2); ctx.fillStyle = "rgba(" + color + ",.55)"; ctx.fill();
        for (var j = i + 1; j < dots.length; j++) {
          var p = dots[j], ex = d.x - p.x, ey = d.y - p.y, l = ex * ex + ey * ey;
          if (l < link * link) { ctx.beginPath(); ctx.moveTo(d.x, d.y); ctx.lineTo(p.x, p.y); ctx.strokeStyle = "rgba(" + color + "," + (.22 * (1 - Math.sqrt(l) / link)).toFixed(3) + ")"; ctx.lineWidth = 1; ctx.stroke(); }
        }
      }
      if (running && !reduce) raf = requestAnimationFrame(draw);
    }
    size(); draw();
    if (reduce) return;
    if ("IntersectionObserver" in window) new IntersectionObserver(function (es) { es.forEach(function (e) { running = e.isIntersecting; if (running) { cancelAnimationFrame(raf); raf = requestAnimationFrame(draw); } }); }).observe(sec);
    else { running = true; raf = requestAnimationFrame(draw); }
    if (!CMS.touch() && interact) { sec.addEventListener("mousemove", function (e) { var r = sec.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; }); sec.addEventListener("mouseleave", function () { mouse.x = mouse.y = -1e4; }); }
    var t; window.addEventListener("resize", function () { clearTimeout(t); t = setTimeout(function () { size(); if (!running) draw(); }, 150); });
  });
})();
