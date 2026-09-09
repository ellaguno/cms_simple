/* cms_simple · paquete contenido — banda de aviso que recuerda si la cerraron */
(function () {
  "use strict";
  CMS.block("contenido/aviso", function (sec) {
    var band = sec.querySelector("[data-ct-band]"); if (!band) return;
    var close = band.querySelector(".ct-band-close");
    if (!close) { band.hidden = false; return; }
    var key = "cms_band_" + band.getAttribute("data-ct-band"), hidden = false;
    try { hidden = localStorage.getItem(key) === "1"; } catch (e) {}
    if (hidden) { sec.setAttribute("hidden", "hidden"); return; }
    band.hidden = false;
    close.addEventListener("click", function () {
      band.style.height = band.offsetHeight + "px";
      requestAnimationFrame(function () { band.classList.add("is-closing"); });
      try { localStorage.setItem(key, "1"); } catch (e) {}
      setTimeout(function () { sec.setAttribute("hidden", "hidden"); }, 260);
    });
  });
})();
