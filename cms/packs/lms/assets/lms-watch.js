/* Paquete lms — avance de los videos de una lección.
   Divide el video en 100 tramos y marca los que se reproducen de corrido (adelantar o saltar no cuenta). El
   porcentaje visto se guarda en el navegador (para sumar entre visitas) y se manda a /aula/visto, que lo registra y
   marca la lección al llegar al mínimo de Ajustes → Aula. Funciona con <video> (MP4/WebM), YouTube y Vimeo (por sus
   mensajes postMessage, sin cargar sus bibliotecas). */
(function () {
  'use strict';
  var N = 100;

  function init(box) {
    var d = box.dataset, need = +d.need || 90, sent = +d.pct || 0, done = d.done === '1';
    var key = 'lmsw:' + d.key, marks = new Uint8Array(N), dur = 0, lastT = -1, lastSend = 0, timer = null;
    try { (JSON.parse(localStorage.getItem(key) || '[]') || []).forEach(function (i) { if (i >= 0 && i < N) marks[i] = 1; }); } catch (e) {}
    var note = document.querySelector('[data-lms-watch-note]'), btn = document.querySelector('[data-lms-need-video]');

    function pct() { var c = 0; for (var i = 0; i < N; i++) c += marks[i]; return c; }
    function bucket(t) { return Math.max(0, Math.min(N - 1, Math.floor(t / dur * N))); }
    function store() {
      var a = []; for (var i = 0; i < N; i++) if (marks[i]) a.push(i);
      try { localStorage.setItem(key, JSON.stringify(a)); } catch (e) {}
    }
    function show(p) {
      p = Math.max(p, sent);
      if (note && !done) note.textContent = note.dataset.tpl.replace('%d', p);
      if (btn && p >= need) { btn.disabled = false; btn.removeAttribute('aria-disabled'); var h = document.querySelector('[data-lms-need-note]'); if (h) h.hidden = true; }
    }
    function finished() {
      done = true;
      if (note) { note.textContent = note.dataset.done; note.classList.add('is-done'); }
      if (btn) { btn.disabled = false; btn.removeAttribute('aria-disabled'); }
    }
    function body(p) {
      var f = new FormData();
      f.append('lesson', d.lesson); f.append('pct', String(p)); f.append('_lms', d.csrf);
      return f;
    }
    function send(force) {
      var p = pct();
      if (p <= sent && !force) return;
      if (!force && p < need && p - sent < 5 && Date.now() - lastSend < 20000) return;
      lastSend = Date.now();
      fetch(d.url, { method: 'POST', body: body(p), credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (!j || !j.ok) return; sent = Math.max(sent, j.pct || 0); if (j.done && !done) finished(); })
        .catch(function () {});
    }
    function beacon() {
      var p = pct();
      if (p > sent && navigator.sendBeacon) { navigator.sendBeacon(d.url, body(p)); sent = p; }
    }
    /* posición actual: t segundos de un video de duración du; playing = se está reproduciendo */
    function at(t, du, playing) {
      if (du > 0) dur = du;
      if (!dur || !playing || !(t >= 0)) { lastT = -1; return; }
      if (lastT >= 0 && t - lastT > 0 && t - lastT <= 3) {
        for (var i = bucket(lastT); i <= bucket(t); i++) marks[i] = 1;
      }
      if (dur - t < 1.5) marks[N - 1] = 1;   // el final
      lastT = t;
      clearTimeout(timer); timer = setTimeout(store, 1000);
      show(pct());
      send(false);
    }
    function ended() { if (dur) marks[N - 1] = 1; lastT = -1; store(); show(pct()); send(true); }

    var v = box.querySelector('video'), f = box.querySelector('iframe');
    if (v) {
      v.addEventListener('timeupdate', function () { at(v.currentTime, v.duration, !v.paused && !v.seeking); });
      v.addEventListener('seeking', function () { lastT = -1; });
      v.addEventListener('ended', ended);
    } else if (f && box.dataset.kind === 'youtube') {
      var heard = false, tries = 0;
      var hello = function () {
        if (heard || tries++ > 30) return;
        try { f.contentWindow.postMessage(JSON.stringify({ event: 'listening', id: d.lesson, channel: 'widget' }), '*'); } catch (e) {}
        setTimeout(hello, 1000);
      };
      f.addEventListener('load', hello); hello();
      var state = -1;
      window.addEventListener('message', function (e) {
        if (e.source !== f.contentWindow || !/^https:\/\/(www\.)?youtube(-nocookie)?\.com$/.test(e.origin)) return;
        var m; try { m = typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch (x) { return; }
        if (!m || m.event !== 'infoDelivery' || !m.info) return;
        heard = true;
        if (m.info.playerState !== undefined) { state = m.info.playerState; if (state === 0) ended(); }
        if (m.info.currentTime !== undefined) at(m.info.currentTime, m.info.duration || dur, state === 1);
      });
    } else if (f && box.dataset.kind === 'vimeo') {
      var post = function (o) { try { f.contentWindow.postMessage(JSON.stringify(o), 'https://player.vimeo.com'); } catch (e) {} };
      var subscribe = function () { ['timeupdate', 'seeked', 'ended'].forEach(function (ev) { post({ method: 'addEventListener', value: ev }); }); };
      f.addEventListener('load', subscribe);
      window.addEventListener('message', function (e) {
        if (e.source !== f.contentWindow || e.origin !== 'https://player.vimeo.com') return;
        var m; try { m = typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch (x) { return; }
        if (!m) return;
        if (m.event === 'ready') subscribe();
        else if (m.event === 'timeupdate' && m.data) at(m.data.seconds, m.data.duration, true);
        else if (m.event === 'seeked') lastT = -1;
        else if (m.event === 'ended') ended();
      });
    }
    show(pct());
    if (pct() > sent) send(true);   // lo visto en otra visita que no alcanzó a llegar
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') beacon(); });
    window.addEventListener('pagehide', beacon);
  }

  document.querySelectorAll('[data-lms-watch]').forEach(init);
})();
