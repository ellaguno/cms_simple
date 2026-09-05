/* cms_simple · efectos visual/spotlight y visual/gradient (sin librerías) */
(function () {
  "use strict";
  /* Inserta una capa de fondo en la sección: después de los fondos absolutos que ya tenga y debajo del contenido. */
  CMS.bgLayer = function (sec, el) {
    var ref = null;
    Array.prototype.some.call(sec.children, function (c) { if (getComputedStyle(c).position === "absolute") return false; ref = c; return true; });
    sec.insertBefore(el, ref);
    Array.prototype.forEach.call(sec.children, function (c) { if (c === el || getComputedStyle(c).position === "absolute") return; if (getComputedStyle(c).position === "static") c.style.position = "relative"; if (!c.style.zIndex) c.style.zIndex = "1"; });
  };
  CMS.effect("visual/spotlight", function (sec) {
    if (CMS.touch()) return;
    var cards = sec.querySelectorAll(".feature-card, .card, .vis-card, [data-spot]");
    if (!cards.length) sec.querySelectorAll('[class*="grid"] > *').forEach(function (c) { cards = Array.prototype.concat.call(Array.from(cards), c); });
    cards = Array.from(cards);
    if (!cards.length) return;
    cards.forEach(function (c) { c.classList.add("vis-spot"); });
    sec.addEventListener("mousemove", function (e) {
      sec.classList.add("vis-spot-active");
      var xp = (e.clientX / window.innerWidth).toFixed(2);
      cards.forEach(function (card) {
        var r = card.getBoundingClientRect();
        card.style.setProperty("--card-x", (e.clientX - r.left).toFixed(2));
        card.style.setProperty("--card-y", (e.clientY - r.top).toFixed(2));
        card.style.setProperty("--xp", xp);
      });
    });
    sec.addEventListener("mouseleave", function () { sec.classList.remove("vis-spot-active"); });
  });

  CMS.effect("visual/gradient", function (sec) {
    if (CMS.reducedMotion()) return;
    var box = document.createElement("div"); box.className = "vis-blobs"; box.setAttribute("aria-hidden", "true");
    box.innerHTML = '<div class="vis-blob vis-blob-1"></div><div class="vis-blob vis-blob-2"></div><div class="vis-blob vis-blob-pointer"></div>';
    CMS.bgLayer(sec, box);
    if (CMS.touch()) return;
    var pointer = box.querySelector(".vis-blob-pointer"), curX = 0, curY = 0, tgX = 0, tgY = 0, running = false;
    sec.addEventListener("mousemove", function (e) { var r = sec.getBoundingClientRect(); tgX = e.clientX - r.left; tgY = e.clientY - r.top; if (!running) { running = true; requestAnimationFrame(tick); } });
    function tick() { curX += (tgX - curX) / 20; curY += (tgY - curY) / 20; pointer.style.transform = "translate(" + Math.round(curX) + "px, " + Math.round(curY) + "px)"; if (Math.abs(tgX - curX) > .5 || Math.abs(tgY - curY) > .5) requestAnimationFrame(tick); else running = false; }
  });
})();
