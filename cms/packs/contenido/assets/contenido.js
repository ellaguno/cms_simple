/* cms_simple · paquete contenido — banda de aviso que recuerda si la cerraron, y ticker de novedades (cinta o rotación).
   Todo en el archivo del paquete: los scripts de bloque se ejecutan según el orden del <head>, y así no dependen entre sí. */
(function () {
  "use strict";
  /** Banda que se puede cerrar y recuerda que la cerraron (localStorage). Devuelve false si no hay que dibujarla. */
  CMS.ctBand = function (sec, band) {
    var close = band.querySelector(".ct-band-close");
    if (!close) { band.hidden = false; return true; }
    var key = "cms_band_" + band.getAttribute("data-ct-band"), hidden = false;
    try { hidden = localStorage.getItem(key) === "1"; } catch (e) {}
    if (hidden) { sec.setAttribute("hidden", "hidden"); return false; }
    band.hidden = false;
    close.addEventListener("click", function () {
      band.style.height = band.offsetHeight + "px";
      requestAnimationFrame(function () { band.classList.add("is-closing"); });
      try { localStorage.setItem(key, "1"); } catch (e) {}
      setTimeout(function () { sec.setAttribute("hidden", "hidden"); }, 260);
    });
    return true;
  };
  CMS.block("contenido/aviso", function (sec) {
    var band = sec.querySelector("[data-ct-band]"); if (!band) return;
    CMS.ctBand(sec, band);
  });
  CMS.block("contenido/ticker", function (sec) {
    var t = sec.querySelector("[data-ct-ticker]"); if (!t) return;
    if (!CMS.ctBand(sec, t)) return;
    var track = t.querySelector(".ct-ticker-track"); if (!track) return;
    var items = [].slice.call(track.children); if (!items.length) return;
    var mode = t.getAttribute("data-ct-ticker");
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (mode === "rotate") {
      if (items.length < 2) return;
      var i = 0, paused = false, every = reduce ? 9000 : 6000;
      t.addEventListener("mouseenter", function () { paused = true; });
      t.addEventListener("mouseleave", function () { paused = false; });
      t.addEventListener("focusin", function () { paused = true; });
      t.addEventListener("focusout", function () { paused = false; });
      setInterval(function () {
        if (paused || document.hidden) return;
        items[i].classList.remove("is-active"); i = (i + 1) % items.length; items[i].classList.add("is-active");
      }, every);
      return;
    }
    if (mode !== "scroll") return;
    if (reduce) { t.classList.add("ct-ticker-still"); return; }
    var view = track.parentElement;
    function build() {
      // una copia de la pista tras otra hasta cubrir dos anchos de la vista; las copias no se leen en voz alta
      [].slice.call(view.querySelectorAll(".ct-ticker-copy")).forEach(function (c) { c.remove(); });
      var w = track.scrollWidth + (parseFloat(getComputedStyle(track).marginRight) || 0), n = 0;
      if (w <= 0) return;
      while ((n + 1) * w < view.clientWidth * 2 + w && n < 20) { var c = track.cloneNode(true); c.className += " ct-ticker-copy"; c.setAttribute("aria-hidden", "true"); view.appendChild(c); n++; }
      var speed = { slow: 45, normal: 75, fast: 120 }[(t.className.match(/ct-ticker-(slow|normal|fast)/) || [])[1]] || 75;   // px por segundo
      view.style.setProperty("--ct-shift", w + "px");
      view.style.setProperty("--ct-dur", (w / speed).toFixed(2) + "s");
      view.classList.add("is-moving");
    }
    build();
    var tm; window.addEventListener("resize", function () { clearTimeout(tm); tm = setTimeout(build, 200); });
  });
})();
