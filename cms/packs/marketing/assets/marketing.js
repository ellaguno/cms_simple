/* cms_simple · paquete marketing — comportamiento de los bloques (sin librerías) */
(function () {
  "use strict";
  var reduce = CMS.reducedMotion();

  /* hero: la palabra rota; los "fantasmas" reservan el ancho de la más larga para que el titular no salte */
  CMS.block("marketing/hero-palabras", function (sec) {
    var box = sec.querySelector(".mk-rotate"), word = sec.querySelector(".mk-rotate-word"); if (!box || !word) return;
    var words = []; try { words = JSON.parse(box.getAttribute("data-words") || "[]"); } catch (e) {}
    if (words.length < 2 || reduce) return;
    // el ancho se anima a la palabra que entra: sin hueco reservado y sin salto del titular
    var ruler = document.createElement("span"); ruler.className = "mk-rotate-measure"; ruler.setAttribute("aria-hidden", "true"); box.appendChild(ruler);
    function widthOf(w) { ruler.textContent = w; return ruler.getBoundingClientRect().width; }
    box.style.width = widthOf(words[0]) + "px"; box.classList.add("is-live");
    var i = 0;
    // la tipografía web cambia el ancho: se vuelve a medir cuando termina de cargar
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { box.style.width = widthOf(words[i]) + "px"; });
    setInterval(function () {
      word.classList.add("is-out");
      setTimeout(function () {
        i = (i + 1) % words.length;
        word.textContent = words[i]; box.style.width = widthOf(words[i]) + "px";
        // reflow síncrono: el navegador registra el estado inicial (invisible, desplazada) y la transición arranca al quitar la clase
        word.classList.remove("is-out"); word.classList.add("is-in");
        void word.offsetWidth;
        word.classList.remove("is-in");
      }, 300);
    }, Math.max(800, parseInt(box.getAttribute("data-interval") || "2600", 10) || 2600));
    window.addEventListener("resize", function () { box.style.width = widthOf(words[i]) + "px"; });
  });

  /* pestañas con imagen: clic, teclado y avance automático con barra de progreso */
  CMS.block("marketing/pestanas", function (sec) {
    var tabs = Array.from(sec.querySelectorAll(".mk-tab")), panels = Array.from(sec.querySelectorAll(".mk-tab-panel")), box = sec.querySelector(".mk-tabs");
    if (!tabs.length || !box) return;
    var ms = parseInt(box.getAttribute("data-autoplay") || "0", 10), timer = null, cur = 0, paused = false;
    panels.forEach(function (p) { p.removeAttribute("hidden"); });
    function show(n) {
      cur = (n + tabs.length) % tabs.length;
      tabs.forEach(function (t, i) { var on = i === cur; t.classList.toggle("is-active", on); t.setAttribute("aria-selected", on ? "true" : "false"); t.tabIndex = on ? 0 : -1; });
      panels.forEach(function (p, i) { var on = i === cur; p.classList.toggle("is-active", on); p.setAttribute("aria-hidden", on ? "false" : "true"); });
      if (ms) restart();
    }
    function restart() { clearTimeout(timer); if (paused || reduce) return; timer = setTimeout(function () { show(cur + 1); }, ms); }
    tabs.forEach(function (t, i) {
      t.addEventListener("click", function () { show(i); });
      t.addEventListener("keydown", function (e) { if (e.key === "ArrowDown" || e.key === "ArrowRight") { e.preventDefault(); show(cur + 1); tabs[cur].focus(); } if (e.key === "ArrowUp" || e.key === "ArrowLeft") { e.preventDefault(); show(cur - 1); tabs[cur].focus(); } });
    });
    if (ms && !reduce) {
      box.classList.add("is-auto"); box.style.setProperty("--mk-tab-ms", ms + "ms");
      box.addEventListener("mouseenter", function () { paused = true; clearTimeout(timer); });
      box.addEventListener("mouseleave", function () { paused = false; show(cur); });
      CMS.inView(box, function () { restart(); }, "0px");
    }
    show(0);
  });

  /* comparador: el range invisible mueve el corte */
  CMS.block("marketing/comparador", function (sec) {
    var box = sec.querySelector(".mk-compare"), range = sec.querySelector(".mk-compare-range"); if (!box || !range) return;
    var set = function () { box.style.setProperty("--mk-pos", range.value + "%"); };
    range.addEventListener("input", set); set();
  });

  /* cintas: se duplica el contenido (siempre a pares) hasta cubrir el doble del ancho, para un bucle sin salto */
  function fillTrack(sec) {
    if (reduce) return;
    sec.querySelectorAll(".mk-ticker-track").forEach(function (track) {
      var box = track.parentElement, n = 0;
      while (track.scrollWidth < box.clientWidth * 2 && n < 6) { track.insertAdjacentHTML("beforeend", track.innerHTML); n++; }
      if (n === 0) track.insertAdjacentHTML("beforeend", track.innerHTML);
    });
  }
  CMS.block("marketing/testimonios-cinta", fillTrack);
  CMS.block("marketing/logos-cinta", fillTrack);

  /* precios: interruptor mensual/anual */
  CMS.block("marketing/precios", function (sec) {
    var box = sec.querySelector(".mk-pricing"); if (!box) return;
    box.querySelectorAll(".mk-switch-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var p = btn.getAttribute("data-period"); box.setAttribute("data-period", p);
        box.querySelectorAll(".mk-switch-btn").forEach(function (b) { var on = b === btn; b.classList.toggle("is-active", on); b.setAttribute("aria-pressed", on ? "true" : "false"); });
      });
    });
  });

  /* línea de tiempo: cada hito se enciende al entrar; la línea se rellena según el scroll */
  CMS.block("marketing/linea-tiempo", function (sec) {
    var list = sec.querySelector(".mk-timeline"), items = Array.from(sec.querySelectorAll(".mk-tl-item")); if (!list || !items.length) return;
    if (reduce || !("IntersectionObserver" in window)) { items.forEach(function (i) { i.classList.add("is-on"); }); list.style.setProperty("--mk-progress", "100%"); return; }
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add("is-on"); io.unobserve(e.target); } }); }, { rootMargin: "0px 0px -8% 0px" });
    items.forEach(function (i) { io.observe(i); });
    var ticking = false;
    function progress() {
      ticking = false;
      var r = list.getBoundingClientRect(), mid = window.innerHeight * .6;
      var p = Math.max(0, Math.min(1, (mid - r.top) / r.height));
      list.style.setProperty("--mk-progress", (p * 100).toFixed(1) + "%");
    }
    window.addEventListener("scroll", function () { if (!ticking) { ticking = true; requestAnimationFrame(progress); } }, { passive: true });
    progress();
  });
})();
