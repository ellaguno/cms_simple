/* cms_simple · visual/carrusel — Swiper (MIT) */
(function () {
  "use strict";
  CMS.block("visual/carrusel", function (sec) {
    var el = sec.querySelector(".vis-carousel"); if (!el) return;
    CMS.inView(el, function () {
      CMS.load("swiper").then(function (Swiper) {
        var n = parseInt(el.getAttribute("data-per-view") || "1", 10);
        new Swiper(el, { slidesPerView: 1, spaceBetween: 16, loop: true, breakpoints: { 768: { slidesPerView: n } },
          autoplay: el.getAttribute("data-autoplay") === "1" ? { delay: 5000, disableOnInteraction: true } : false,
          pagination: { el: el.querySelector(".swiper-pagination"), clickable: true },
          navigation: { nextEl: el.querySelector(".swiper-button-next"), prevEl: el.querySelector(".swiper-button-prev") } });
      });
    });
  });
})();
