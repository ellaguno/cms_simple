/* cms_simple · visual/lightbox — GLightbox (MIT) */
(function () {
  "use strict";
  CMS.block("visual/lightbox", function (sec) {
    CMS.inView(sec, function () { CMS.load("glightbox").then(function (GLightbox) { GLightbox({ selector: '[data-block="visual/lightbox"] .glightbox', touchNavigation: true, loop: true }); }); });
  });
})();
