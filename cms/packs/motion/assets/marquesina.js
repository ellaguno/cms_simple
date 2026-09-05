/* cms_simple · motion/marquesina — duplica la pista para el bucle continuo (CSS hace el movimiento) */
(function () {
  "use strict";
  CMS.block("motion/marquesina", function (sec) {
    var track = sec.querySelector(".mo-marquee-track"); if (!track) return;
    var box = track.parentElement, html = track.innerHTML, n = 1;
    while (track.scrollWidth < box.clientWidth * 2 && n < 12) { track.insertAdjacentHTML("beforeend", html); n++; }
  });
})();
