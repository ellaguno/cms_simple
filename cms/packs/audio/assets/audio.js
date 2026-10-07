/* Paquete audio: convierte cada <figure class="cms-audio" data-au> en el reproductor compacto (píldora con botón, onda,
   tiempo, velocidad y descarga). El <audio> nativo queda dentro como motor; sin JS se ve el reproductor nativo. */
(function () {
  "use strict";
  var NS = "http://www.w3.org/2000/svg";
  var ICONS = {
    play: "M8 5.14v13.72a1 1 0 0 0 1.52.85l10.6-6.86a1 1 0 0 0 0-1.7L9.52 4.29A1 1 0 0 0 8 5.14z",
    pause: "M7 5h3.2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm6.8 0H17a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-3.2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z",
    dl: "M12 3a1 1 0 0 1 1 1v9.59l3.3-3.3a1 1 0 1 1 1.4 1.42l-5 5a1 1 0 0 1-1.4 0l-5-5a1 1 0 1 1 1.4-1.42l3.3 3.3V4a1 1 0 0 1 1-1zM5 19h14a1 1 0 1 1 0 2H5a1 1 0 1 1 0-2z"
  };
  var RATES = [1, 1.25, 1.5, 1.75, 2, 0.75];
  var BARS = 48;

  function icon(name, cls) {
    var s = document.createElementNS(NS, "svg"), p = document.createElementNS(NS, "path");
    s.setAttribute("viewBox", "0 0 24 24"); s.setAttribute("aria-hidden", "true"); if (cls) s.setAttribute("class", cls);
    p.setAttribute("d", ICONS[name]); s.appendChild(p); return s;
  }
  function el(tag, cls, attrs) {
    var e = document.createElement(tag); if (cls) e.className = cls;
    for (var k in attrs || {}) e.setAttribute(k, attrs[k]);
    return e;
  }
  function fmt(t) {
    if (!isFinite(t) || t < 0) t = 0;
    t = Math.floor(t); var h = Math.floor(t / 3600), m = Math.floor(t / 60) % 60, s = t % 60;
    return (h ? h + ":" + (m < 10 ? "0" : "") : "") + m + ":" + (s < 10 ? "0" : "") + s;
  }
  /* alturas de la onda: pseudoaleatorias pero fijas para cada archivo (semilla = su URL), con forma de voz */
  function heights(seed) {
    var h = 2166136261, out = [];
    for (var i = 0; i < seed.length; i++) { h ^= seed.charCodeAt(i); h = Math.imul(h, 16777619); }
    for (var j = 0; j < BARS; j++) {
      h ^= h << 13; h ^= h >>> 17; h ^= h << 5;
      var r = ((h >>> 0) % 1000) / 1000, env = 0.6 + 0.4 * Math.sin(j / BARS * Math.PI * 3 + seed.length);
      out.push(Math.round(14 + 86 * Math.pow(r, 0.7) * env));
    }
    return out;
  }

  function build(fig) {
    var audio = fig.querySelector("audio"); if (!audio || fig.classList.contains("au-ready")) return;
    var src = audio.currentSrc || audio.getAttribute("src") || "";
    var labelEl = fig.querySelector(".cms-audio-label"), dlEl = fig.querySelector(".cms-audio-dl");
    var label = labelEl ? labelEl.textContent : "";
    var L = fig.getAttribute("data-au-lang") === "en"
      ? { play: "Play", pause: "Pause", seek: "Position", rate: "Playback speed", dl: "Download MP3" }
      : { play: "Reproducir", pause: "Pausar", seek: "Posición", rate: "Velocidad de reproducción", dl: "Descargar MP3" };

    var play = el("button", "au-play", { type: "button", "aria-label": L.play + (label ? ": " + label : "") });
    play.appendChild(icon("play", "au-i-play")); play.appendChild(icon("pause", "au-i-pause"));
    var mid = el("div", "au-mid"), top = el("div", "au-top"), title = el("span", "au-title"), time = el("span", "au-time");
    title.textContent = label; time.textContent = "0:00";
    var wave = el("div", "au-wave", { role: "slider", tabindex: "0", "aria-label": L.seek, "aria-valuemin": "0", "aria-valuemax": "100", "aria-valuenow": "0" });
    var bars = heights(src).map(function (v) { var b = document.createElement("i"); b.style.height = v + "%"; wave.appendChild(b); return b; });
    top.appendChild(title); top.appendChild(time); mid.appendChild(top); mid.appendChild(wave);
    var rate = el("button", "au-btn au-rate", { type: "button", "aria-label": L.rate, title: L.rate }); rate.textContent = "1×";
    fig.appendChild(play); fig.appendChild(mid); fig.appendChild(rate);
    if (dlEl) { var dl = el("a", "au-btn au-dl", { href: dlEl.getAttribute("href"), download: "", "aria-label": L.dl, title: L.dl }); dl.appendChild(icon("dl")); fig.appendChild(dl); }
    audio.removeAttribute("controls");
    fig.classList.add("au-ready");

    var lastOn = -1;
    function paint() {
      var d = audio.duration, c = audio.currentTime, p = isFinite(d) && d > 0 ? c / d : 0, on = Math.round(p * BARS);
      if (on !== lastOn) { bars.forEach(function (b, i) { b.classList.toggle("on", i < on); b.classList.toggle("now", i === on - 1); }); lastOn = on; }
      time.textContent = isFinite(d) && d > 0 ? fmt(c) + " / " + fmt(d) : fmt(c);
      wave.setAttribute("aria-valuenow", String(Math.round(p * 100)));
      wave.setAttribute("aria-valuetext", fmt(c) + (isFinite(d) ? " / " + fmt(d) : ""));
    }
    function state() {
      var on = !audio.paused && !audio.ended;
      fig.classList.toggle("is-playing", on);
      play.setAttribute("aria-label", (on ? L.pause : L.play) + (label ? ": " + label : ""));
    }
    function seekTo(p) {
      var d = audio.duration; p = Math.max(0, Math.min(1, p));
      if (isFinite(d) && d > 0) { audio.currentTime = p * d; paint(); }
      else audio.addEventListener("loadedmetadata", function once() { audio.removeEventListener("loadedmetadata", once); audio.currentTime = p * audio.duration; paint(); });
    }
    function pos(e) { var r = wave.getBoundingClientRect(); return (e.clientX - r.left) / r.width; }

    play.addEventListener("click", function () {
      if (audio.paused) {
        document.querySelectorAll(".cms-audio audio").forEach(function (a) { if (a !== audio) a.pause(); });   // uno a la vez
        var pr = audio.play(); if (pr && pr.catch) pr.catch(function () { fig.classList.remove("is-loading"); });
      } else audio.pause();
    });
    var dragging = false;
    wave.addEventListener("pointerdown", function (e) { dragging = true; try { wave.setPointerCapture(e.pointerId); } catch (x) {} seekTo(pos(e)); });
    wave.addEventListener("pointermove", function (e) { if (dragging) seekTo(pos(e)); });
    wave.addEventListener("pointerup", function () { dragging = false; });
    wave.addEventListener("pointercancel", function () { dragging = false; });
    wave.addEventListener("keydown", function (e) {
      var d = audio.duration || 0, step = { ArrowRight: 5, ArrowUp: 5, ArrowLeft: -5, ArrowDown: -5, PageUp: 30, PageDown: -30 }[e.key];
      if (step !== undefined && d) { audio.currentTime = Math.max(0, Math.min(d, audio.currentTime + step)); paint(); e.preventDefault(); }
      else if (e.key === "Home") { audio.currentTime = 0; paint(); e.preventDefault(); }
      else if (e.key === "End" && d) { audio.currentTime = d; paint(); e.preventDefault(); }
      else if (e.key === " " || e.key === "Enter") { play.click(); e.preventDefault(); }
    });
    rate.addEventListener("click", function () {
      var i = (RATES.indexOf(audio.playbackRate) + 1) % RATES.length;
      audio.playbackRate = RATES[i]; rate.textContent = String(RATES[i]).replace(".", ",") + "×";
      if (fig.getAttribute("data-au-lang") === "en") rate.textContent = RATES[i] + "×";
    });
    audio.addEventListener("play", state); audio.addEventListener("pause", state);
    audio.addEventListener("ended", function () { state(); audio.currentTime = 0; paint(); });
    audio.addEventListener("waiting", function () { fig.classList.add("is-loading"); });
    ["playing", "canplay", "error"].forEach(function (ev) { audio.addEventListener(ev, function () { fig.classList.remove("is-loading"); }); });
    ["timeupdate", "loadedmetadata", "durationchange", "seeked"].forEach(function (ev) { audio.addEventListener(ev, paint); });
    paint(); state();
  }

  function init() { document.querySelectorAll(".cms-audio[data-au]").forEach(build); }
  init();
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
})();
