/* cms_simple · paquete marketing — efectos de fondo: aurora, rejilla con haz, partículas, meteoros (sin librerías) */
(function () {
  "use strict";
  var reduce = CMS.reducedMotion();
  function layer(sec, cls, html) {
    var el = document.createElement("div"); el.className = "mk-fx " + cls; el.setAttribute("aria-hidden", "true"); if (html) el.innerHTML = html;
    CMS.bgLayer(sec, el); return el;
  }

  CMS.effect("marketing/aurora", function (sec) { layer(sec, "mk-aurora", "<span></span><span></span><span></span>"); });

  CMS.effect("marketing/rejilla", function (sec) { layer(sec, "mk-grid", reduce ? "" : '<span class="mk-grid-beam"></span><span class="mk-grid-beam mk-grid-beam-h"></span>'); });

  CMS.effect("marketing/meteoros", function (sec) {
    var el = layer(sec, "mk-sky"), w = sec.clientWidth || 1200, h = sec.clientHeight || 600, shadows = [];
    for (var i = 0; i < 90; i++) { var a = (Math.random() * .6 + .3).toFixed(2), s = Math.random() < .2 ? 2 : 1; shadows.push(Math.round(Math.random() * w) + "px " + Math.round(Math.random() * h) + "px 0 " + (s - 1) + "px rgba(255,255,255," + a + ")"); }
    var stars = document.createElement("span"); stars.className = "mk-sky-stars"; stars.style.boxShadow = shadows.join(","); el.appendChild(stars);
    if (reduce) return;
    for (var m = 0; m < 9; m++) {
      var me = document.createElement("span"); me.className = "mk-meteor";
      me.style.left = Math.round(Math.random() * 100) + "%"; me.style.top = Math.round(Math.random() * 60 - 10) + "%";
      me.style.setProperty("--d", (3 + Math.random() * 5).toFixed(1) + "s"); me.style.setProperty("--delay", (Math.random() * 9).toFixed(1) + "s");
      el.appendChild(me);
    }
  });

  CMS.effect("marketing/particulas", function (sec) {
    var el = layer(sec, "mk-particles-wrap"), c = document.createElement("canvas"); c.className = "mk-particles"; el.appendChild(c);
    var ctx = c.getContext("2d"), dots = [], W = 0, H = 0, dpr = Math.min(2, window.devicePixelRatio || 1), mouse = { x: -1e4, y: -1e4 }, running = false, raf = 0, color = "";
    function rgb() { var m = (getComputedStyle(sec).color || "").match(/\d+/g); return m ? m.slice(0, 3).join(",") : "120,120,120"; }
    function size() {
      W = sec.clientWidth; H = sec.clientHeight; c.width = W * dpr; c.height = H * dpr; c.style.width = W + "px"; c.style.height = H + "px"; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = Math.min(110, Math.max(25, Math.round(W * H / 14000))); dots = [];
      for (var i = 0; i < n; i++) dots.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35, r: 1 + Math.random() * 1.5 });
      color = rgb();
    }
    function draw() {
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < dots.length; i++) {
        var d = dots[i];
        if (!reduce) {
          d.x += d.vx; d.y += d.vy;
          if (d.x < 0 || d.x > W) d.vx *= -1; if (d.y < 0 || d.y > H) d.vy *= -1;
          var dx = mouse.x - d.x, dy = mouse.y - d.y, dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 140) { d.x -= dx / dist * .6; d.y -= dy / dist * .6; }
        }
        ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2); ctx.fillStyle = "rgba(" + color + ",.55)"; ctx.fill();
        for (var j = i + 1; j < dots.length; j++) {
          var o = dots[j], ex = d.x - o.x, ey = d.y - o.y, l = ex * ex + ey * ey;
          if (l < 120 * 120) { ctx.beginPath(); ctx.moveTo(d.x, d.y); ctx.lineTo(o.x, o.y); ctx.strokeStyle = "rgba(" + color + "," + (.22 * (1 - Math.sqrt(l) / 120)).toFixed(3) + ")"; ctx.lineWidth = 1; ctx.stroke(); }
        }
      }
      if (running && !reduce) raf = requestAnimationFrame(draw);
    }
    size(); draw();
    if (reduce) return;
    if ("IntersectionObserver" in window) new IntersectionObserver(function (es) { es.forEach(function (e) { running = e.isIntersecting; if (running) { cancelAnimationFrame(raf); raf = requestAnimationFrame(draw); } }); }).observe(sec);
    else { running = true; raf = requestAnimationFrame(draw); }
    if (!CMS.touch()) { sec.addEventListener("mousemove", function (e) { var r = sec.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; }); sec.addEventListener("mouseleave", function () { mouse.x = mouse.y = -1e4; }); }
    var t; window.addEventListener("resize", function () { clearTimeout(t); t = setTimeout(function () { size(); if (!running) draw(); }, 150); });
  });
})();
