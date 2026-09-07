/* paquete media: video al clic, mapa Leaflet y Lottie. Las librerías se cargan solo cuando la sección entra en pantalla. */
(function () {
  "use strict";
  CMS.block("media/video", function (sec) {
    sec.querySelectorAll("[data-med-video]").forEach(function (box) {
      box.querySelector(".med-play").addEventListener("click", function () {
        var f = document.createElement("iframe"); f.src = box.getAttribute("data-med-video"); f.allow = "autoplay; fullscreen; picture-in-picture"; f.setAttribute("allowfullscreen", "");
        box.innerHTML = ""; box.appendChild(f);
      });
    });
  });
  CMS.block("media/mapa", function (sec) {
    sec.querySelectorAll("[data-med-map]").forEach(function (el) {
      CMS.inView(el, function () {
        CMS.load("leaflet").then(function () {
          el.innerHTML = "";
          var lat = +el.getAttribute("data-lat"), lng = +el.getAttribute("data-lng"), z = +el.getAttribute("data-zoom") || 15;
          var m = L.map(el, { scrollWheelZoom: false }).setView([lat, lng], z);
          L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(m);
          var mk = L.marker([lat, lng]).addTo(m), lb = el.getAttribute("data-label");
          if (lb) mk.bindPopup(lb).openPopup();
        });
      });
    });
  });
  CMS.block("media/lottie", function (sec) {
    sec.querySelectorAll("[data-med-lottie]").forEach(function (el) {
      CMS.inView(el, function () {
        CMS.load("lottie").then(function () { lottie.loadAnimation({ container: el, renderer: "svg", loop: el.getAttribute("data-loop") === "1", autoplay: true, path: el.getAttribute("data-med-lottie") }); });
      });
    });
  });
})();
