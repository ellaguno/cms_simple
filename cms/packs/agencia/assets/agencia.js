/* cms_simple · paquete agencia — pase de imágenes de la portada, botón de compartir, carrusel del equipo y barras de habilidades.
   Sin librerías. Todo en el archivo del paquete; cada bloque se inicializa con CMS.block(). */
(function () {
  "use strict";
  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  function qsa(sel, root) { return Array.prototype.slice.call(root.querySelectorAll(sel)); }

  /** Pasa entre elementos: go(n), play() y stop(); flechas, puntos, gesto de arrastre y pausa al pasar el ratón. */
  function carousel(root, items, dots, interval, onChange) {
    var i = 0, timer = null;
    function go(n) {
      onChange(items[i], false); if (dots[i]) dots[i].classList.remove("is-active");
      i = (n + items.length) % items.length;
      onChange(items[i], true); if (dots[i]) dots[i].classList.add("is-active");
    }
    function play() { if (!interval || reduce) return; stop(); timer = setInterval(function () { go(i + 1); }, interval); }
    function stop() { if (timer) clearInterval(timer); timer = null; }
    var prev = root.querySelector("[data-ag-prev]"), next = root.querySelector("[data-ag-next]");
    if (prev) prev.addEventListener("click", function () { go(i - 1); play(); });
    if (next) next.addEventListener("click", function () { go(i + 1); play(); });
    dots.forEach(function (d, k) { d.addEventListener("click", function () { go(k); play(); }); });
    var x0 = null;
    root.addEventListener("touchstart", function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    root.addEventListener("touchend", function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(dx) > 40) { go(dx < 0 ? i + 1 : i - 1); play(); } }, { passive: true });
    root.addEventListener("mouseenter", stop); root.addEventListener("mouseleave", play);
    document.addEventListener("visibilitychange", function () { document.hidden ? stop() : play(); });
    play();
  }

  CMS.block("agencia/portada", function (sec) {
    var root = sec.querySelector("[data-ag-slideshow]");
    if (root) {
      var slides = qsa(".ag-slide", root);
      if (slides.length > 1) carousel(root, slides, qsa("[data-ag-dot]", root), parseInt(root.getAttribute("data-interval"), 10) || 5000, function (el, on) { el.classList.toggle("is-active", on); });
    }
    var btn = sec.querySelector("[data-ag-share]"), menu = sec.querySelector("[data-ag-share-menu]");
    if (!btn || !menu) return;
    var url = location.href, title = document.title;
    var links = {
      facebook: "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url),
      x: "https://twitter.com/intent/tweet?url=" + encodeURIComponent(url) + "&text=" + encodeURIComponent(title),
      linkedin: "https://www.linkedin.com/sharing/share-offsite/?url=" + encodeURIComponent(url),
      whatsapp: "https://wa.me/?text=" + encodeURIComponent(title + " " + url)
    };
    qsa("[data-share]", menu).forEach(function (a) {
      var k = a.getAttribute("data-share");
      if (k === "copy") { a.addEventListener("click", function (e) { e.preventDefault(); if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () { a.classList.add("is-done"); setTimeout(function () { a.classList.remove("is-done"); }, 1200); }); }); }
      else a.href = links[k] || "#";
    });
    function close() { menu.hidden = true; btn.classList.remove("is-open"); }
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      if (navigator.share && window.matchMedia("(hover: none)").matches) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      var open = menu.hidden; menu.hidden = !open; btn.classList.toggle("is-open", open);
    });
    document.addEventListener("click", function (e) { if (!e.target.closest(".ag-tab")) close(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });
  });

  CMS.block("agencia/equipo", function (sec) {
    var root = sec.querySelector("[data-ag-team]"); if (!root) return;
    var members = qsa(".ag-member", root);
    if (members.length < 2) { qsa(".ag-team-btn", root).forEach(function (b) { b.style.visibility = "hidden"; }); return; }
    carousel(root, members, [], parseInt(root.getAttribute("data-interval"), 10) || 0, function (el, on) { el.hidden = !on; el.classList.toggle("is-active", on); });
  });

  CMS.block("agencia/habilidades", function (sec) {
    var root = sec.querySelector("[data-ag-skills]"); if (!root) return;
    if (!("IntersectionObserver" in window) || reduce) { root.classList.add("is-visible"); return; }
    var io = new IntersectionObserver(function (entries) { entries.forEach(function (en) { if (en.isIntersecting) { root.classList.add("is-visible"); io.disconnect(); } }); }, { threshold: 0.3 });
    io.observe(root);
  });
})();
