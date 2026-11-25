/* Paquete lms — buscador de cursos con sugerencias (lms_search_box). Filtra en el navegador la lista que viene en el
   HTML: todas las palabras deben aparecer (sin importar mayúsculas ni acentos) en el nombre, temas, tracks, clave o
   títulos de lecciones del curso. Flechas para moverse, Enter abre el curso elegido (o el listado con ?q=), Esc
   cierra; la tecla / enfoca el buscador desde cualquier parte. */
(function () {
  'use strict';
  var fold = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var mark = function (text, words) {
    var f = fold(text), hits = [];
    words.forEach(function (w) { var i = f.indexOf(w); if (i >= 0) hits.push([i, i + w.length]); });
    if (!hits.length) return esc(text);
    hits.sort(function (a, b) { return a[0] - b[0]; });
    var out = '', at = 0;
    hits.forEach(function (h) { if (h[0] < at) return; out += esc(text.slice(at, h[0])) + '<mark>' + esc(text.slice(h[0], h[1])) + '</mark>'; at = h[1]; });
    return out + esc(text.slice(at));
  };
  function init(form) {
    var data; try { data = JSON.parse(document.getElementById(form.getAttribute('data-lms-find')).textContent); } catch (e) { return; }
    var input = form.querySelector('input[name="q"]'), list = form.querySelector('.lms-find-list');
    if (!input || !list) return;
    data.forEach(function (c) { c.ft = fold(c.t); c.fl = (c.l || []).map(fold); });
    var sel = -1, items = [];
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); sel = -1; }
    function paint() {
      [].forEach.call(list.children, function (li, i) { li.classList.toggle('is-sel', i === sel); li.setAttribute('aria-selected', i === sel ? 'true' : 'false'); });
      if (sel >= 0 && list.children[sel]) list.children[sel].scrollIntoView({ block: 'nearest' });
    }
    function render() {
      var words = fold(input.value).split(/\s+/).filter(Boolean);
      if (!words.length) { close(); return; }
      var res = [];
      data.forEach(function (c) {
        for (var i = 0; i < words.length; i++) if (c.h.indexOf(words[i]) < 0) return;
        var score = 0, lesson = '';
        words.forEach(function (w) { if (c.ft.indexOf(w) >= 0) score += 3; });
        if (c.ft.indexOf(words[0]) === 0) score += 2;
        if (score < 3 * words.length) { // no todo está en el nombre: muestra la lección que más coincide
          var best = -1, bi = -1;
          c.fl.forEach(function (l, j) { var n = 0; words.forEach(function (w) { if (l.indexOf(w) >= 0) n++; }); if (n > best) { best = n; bi = j; } });
          if (best > 0) lesson = c.l[bi];
        }
        if (c.s) score -= 5;
        res.push({ c: c, score: score, lesson: lesson });
      });
      res.sort(function (a, b) { return b.score - a.score || a.c.t.localeCompare(b.c.t); });
      items = res.slice(0, 8);
      var html = '';
      if (!items.length) html = '<li class="lms-find-none">' + esc(form.dataset.none) + '</li>';
      items.forEach(function (r, i) {
        var c = r.c;
        html += '<li role="option" data-i="' + i + '">' + (c.u ? '<a href="' + esc(c.u) + '">' : '<span>')
          + '<strong>' + mark(c.t, words) + '</strong>'
          + (c.s ? ' <em>' + esc(form.dataset.soon) + '</em>' : '')
          + (c.k ? '<small>' + esc(c.k) + '</small>' : '')
          + (r.lesson ? '<small class="lms-find-lesson">' + esc(form.dataset.lesson) + ' ' + mark(r.lesson, words) + '</small>' : '')
          + (c.u ? '</a>' : '</span>') + '</li>';
      });
      if (res.length > items.length || res.length > 1) html += '<li class="lms-find-all"><button type="submit">' + esc(form.dataset.all) + ' (' + res.length + ')</button></li>';
      list.innerHTML = html; list.hidden = false; input.setAttribute('aria-expanded', 'true'); sel = -1;
    }
    input.addEventListener('input', render);
    input.addEventListener('focus', function () { if (input.value.trim()) render(); });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(items.length - 1, sel + 1); paint(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(-1, sel - 1); paint(); }
      else if (e.key === 'Escape') { close(); }
      else if (e.key === 'Enter' && sel >= 0 && items[sel] && items[sel].c.u) { e.preventDefault(); location.href = items[sel].c.u; }
    });
    document.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
  }
  function boot() {
    var forms = document.querySelectorAll('form[data-lms-find]');
    [].forEach.call(forms, init);
    if (!forms.length) return;
    document.addEventListener('keydown', function (e) {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
      var t = e.target, tag = (t && t.tagName) || '';
      if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag) || (t && t.isContentEditable)) return;
      var inp = forms[0].querySelector('input[name="q"]');
      if (inp) { e.preventDefault(); inp.focus(); inp.select(); }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
