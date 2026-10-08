/* Reproductor del curso exportado como SCORM 1.2 (paquete lms de cms_simple).
   Habla con el LMS por la API de SCORM 1.2: avance y respuestas en cmi.suspend_data, el paso actual en
   cmi.core.lesson_location, estado (incomplete / completed / passed / failed) y calificación (promedio de las
   evaluaciones) en cmi.core.score.raw. Sin LMS funciona igual y guarda en el navegador. */
(function () {
  'use strict';
  var C = window.COURSE, T = C.t, $ = function (s, el) { return (el || document).querySelector(s); };
  document.documentElement.style.setProperty('--acc', C.color || '#0369a1');

  /* ---- API de SCORM 1.2: se busca en las ventanas de arriba y en la que abrió esta */
  function findAPI(w) { for (var i = 0; w && i < 12; i++) { if (w.API) return w.API; if (w.parent === w) break; w = w.parent; } return null; }
  var api = findAPI(window) || (window.opener ? findAPI(window.opener) : null), t0 = Date.now();
  var lms = {
    init: function () { return api ? api.LMSInitialize('') === 'true' : false; },
    get: function (k) { return api ? api.LMSGetValue(k) : ''; },
    set: function (k, v) { if (api) api.LMSSetValue(k, String(v)); },
    commit: function () { if (api) api.LMSCommit(''); },
    finish: function () { if (!api) return; var s = Math.round((Date.now() - t0) / 1000); lms.set('cmi.core.session_time', ('000' + Math.floor(s / 3600)).slice(-4) + ':' + ('0' + Math.floor(s % 3600 / 60)).slice(-2) + ':' + ('0' + s % 60).slice(-2)); lms.set('cmi.core.exit', 'suspend'); lms.commit(); api.LMSFinish(''); api = null; }
  };
  var on = lms.init();
  var S = { d: {}, q: {}, loc: 0 };   // d: pasos hechos, q: mejor calificación por evaluación
  try { var raw = on ? lms.get('cmi.suspend_data') : localStorage.getItem('scorm:' + C.title); if (raw) S = Object.assign(S, JSON.parse(raw)); } catch (e) {}
  if (on) { var l = parseInt(lms.get('cmi.core.lesson_location'), 10); if (!isNaN(l)) S.loc = l; }

  var steps = C.steps, quizzes = steps.filter(function (s) { return s.type === 'quiz'; });
  function save() {
    var done = steps.filter(function (s) { return S.d[s.id]; }).length, all = done === steps.length;
    // la calificación (promedio de las evaluaciones) se manda cuando ya se presentaron todas: antes, con una calificación
    // mínima en el manifiesto, el LMS daría el curso por reprobado
    var tried = quizzes.filter(function (q) { return S.q[q.id] !== undefined; });
    var avg = quizzes.length && tried.length === quizzes.length ? Math.round(tried.reduce(function (a, q) { return a + S.q[q.id]; }, 0) / quizzes.length) : null;
    var json = JSON.stringify(S);
    if (on) {
      lms.set('cmi.suspend_data', json.length > 4096 ? JSON.stringify({ d: S.d, q: S.q, loc: S.loc }) : json);
      lms.set('cmi.core.lesson_location', S.loc);
      if (avg !== null) { lms.set('cmi.core.score.min', 0); lms.set('cmi.core.score.max', 100); lms.set('cmi.core.score.raw', avg); }
      lms.set('cmi.core.lesson_status', all ? (quizzes.length ? 'passed' : 'completed') : 'incomplete');
      lms.commit();
    } else { try { localStorage.setItem('scorm:' + C.title, json); } catch (e) {} }
    var pct = Math.round(done * 100 / steps.length);
    $('[data-bar]').style.width = pct + '%'; $('[data-pct]').textContent = pct + ' %';
    side();
    return all;
  }

  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function norm(s) { return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim().replace(/^[ .,;:¡!¿?"'«»]+|[ .,;:¡!¿?"'«»]+$/g, ''); }
  function num(s) { s = String(s).replace(/[\s ]/g, ''); if (/^-?\d+,\d+$/.test(s)) s = s.replace(',', '.'); else if (/^-?\d{1,3}(,\d{3})+(\.\d+)?$/.test(s)) s = s.replace(/,/g, ''); return s !== '' && !isNaN(s) ? parseFloat(s) : null; }

  function side() {
    var h = '', mod = null;
    steps.forEach(function (s, i) {
      if (s.module !== mod) { mod = s.module; if (mod) h += '<h3>' + esc(mod) + '</h3>'; }
      h += '<button type="button" data-go="' + i + '" class="' + (S.d[s.id] ? 'done ' : '') + (s.type === 'quiz' ? 'quiz' : '') + '"' + (i === S.loc ? ' aria-current="true"' : '') + '><i>' + (S.d[s.id] ? '✓' : '') + '</i><span>' + esc(s.title) + (s.type === 'quiz' && S.q[s.id] !== undefined ? ' · ' + S.q[s.id] + ' %' : '') + '</span></button>';
    });
    if (C.files.length) h += '<div class="files"><strong>' + esc(T.materials) + '</strong>' + C.files.map(function (f) { return '<a href="' + esc(f[1]) + '" target="_blank" rel="noopener">' + esc(f[0]) + '</a>'; }).join('') + '</div>';
    var el = $('[data-side]'); el.innerHTML = h;
    el.querySelectorAll('[data-go]').forEach(function (b) { b.addEventListener('click', function () { go(+b.dataset.go); el.classList.remove('open'); }); });
  }

  function nav(i) {
    return '<div class="actions">' + (i > 0 ? '<button class="btn ghost" data-prev>← ' + esc(T.prev) + '</button>' : '') + (i < steps.length - 1 ? '<button class="btn ghost" data-next>' + esc(T.next) + ' →</button>' : '') + '</div>';
  }
  function bindNav(el, i) { var p = $('[data-prev]', el), n = $('[data-next]', el); if (p) p.onclick = function () { go(i - 1); }; if (n) n.onclick = function () { go(i + 1); }; }

  function markDone(s) { if (!S.d[s.id]) { S.d[s.id] = 1; if (save() && !S.fin) { S.fin = 1; save(); alert(T.complete); } } }

  function lesson(s, i) {
    var v = s.video, vh = '';
    if (v.kind === 'youtube') vh = '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' + esc(v.src) + '?rel=0" allowfullscreen allow="encrypted-media; fullscreen"></iframe></div>';
    else if (v.kind === 'vimeo') vh = '<div class="video"><iframe src="https://player.vimeo.com/video/' + esc(v.src) + '?dnt=1" allowfullscreen allow="fullscreen"></iframe></div>';
    else if (v.kind === 'file') vh = '<div class="video"><video controls playsinline preload="metadata" src="' + esc(v.src) + '"' + (v.poster ? ' poster="' + esc(v.poster) + '"' : '') + '></video></div>';
    var h = '<div><p class="kicker">' + esc(s.module || '') + (s.duration ? ' · ' + esc(s.duration) : '') + '</p><h2>' + esc(s.title) + '</h2>' + (s.summary ? '<p class="lead">' + esc(s.summary) + '</p>' : '') + vh
      + '<div class="body">' + s.body + '</div>'
      + (s.files.length ? '<div class="files-l"><strong>' + esc(T.materials) + '</strong>' + s.files.map(function (f) { return '<a href="' + esc(f[1]) + '" target="_blank" rel="noopener">' + esc(f[0]) + '</a>'; }).join('') + '</div>' : '')
      + '<div class="actions">' + (S.d[s.id] ? '<span class="ok">✓ ' + esc(T.done) + '</span>' : '<button class="btn" data-mark>' + esc(T.mark) + '</button>') + '</div>' + nav(i) + '</div>';
    var el = $('[data-main]'); el.innerHTML = h;
    var mk = $('[data-mark]', el); if (mk) mk.onclick = function () { markDone(s); if (i < steps.length - 1) go(i + 1); else lesson(s, i); };
    var vid = $('video', el);
    if (vid) { var seen = {}; vid.addEventListener('timeupdate', function () { if (vid.duration) seen[Math.floor(vid.currentTime / vid.duration * 20)] = 1; if (Object.keys(seen).length >= Math.ceil(20 * (C.videoPct || 90) / 100)) markDone(s); }); vid.addEventListener('ended', function () { markDone(s); }); }
    bindNav(el, i);
  }

  function quiz(s, i, result) {
    var h = '<div><p class="kicker">' + esc(T.quiz) + (s.pass ? ' · ' + s.pass + ' %' : '') + '</p><h2>' + esc(s.title) + (S.d[s.id] ? ' <span class="ok">✓</span>' : '') + '</h2>' + (s.intro ? '<div class="body">' + s.intro + '</div>' : '');
    if (result) h += '<p class="score">' + result.pct + ' %</p><p class="' + (result.pct >= s.pass ? 'ok' : '') + '">' + esc(result.pct >= s.pass ? T.passed : T.failed) + '</p>';
    h += '<form data-quiz>';
    s.questions.forEach(function (q, k) {
      var r = result ? result.marks[k] : null, cls = r === null || r === undefined ? '' : (r >= q.points ? 'right' : (r > 0 ? 'partial' : 'wrong'));
      h += '<fieldset class="q ' + cls + '"><legend>' + (k + 1) + '. ' + q.text + '</legend>';
      var nm = 'q' + k, a = result ? result.ans[k] : null;
      if (q.kind === 'single' || q.kind === 'multi') {
        if (q.kind === 'multi') h += '<p class="hint">' + esc(T.multi) + '</p>';
        q.options.forEach(function (o, j) { var chk = a !== null && (q.kind === 'multi' ? a.indexOf(j) >= 0 : a === j); h += '<label><input type="' + (q.kind === 'multi' ? 'checkbox' : 'radio') + '" name="' + nm + '" value="' + j + '"' + (chk ? ' checked' : '') + (result ? ' disabled' : '') + '><span>' + o[0] + '</span></label>'; });
      } else if (q.kind === 'tf') {
        [['v', T['true']], ['f', T['false']]].forEach(function (o) { h += '<label><input type="radio" name="' + nm + '" value="' + o[0] + '"' + (a === o[0] ? ' checked' : '') + (result ? ' disabled' : '') + '><span>' + esc(o[1]) + '</span></label>'; });
      } else if (q.kind === 'open') {
        h += '<p class="hint">' + esc(T.open_note) + '</p><textarea name="' + nm + '" rows="4"' + (result ? ' disabled' : '') + '>' + (a ? esc(a) : '') + '</textarea>';
      } else h += '<input type="text" name="' + nm + '" value="' + (a ? esc(a) : '') + '"' + (result ? ' disabled' : '') + '>';
      if (result && q.kind !== 'open') {
        var right = q.kind === 'single' || q.kind === 'multi' ? q.options.filter(function (o) { return o[1]; }).map(function (o) { return o[0]; }).join(' · ')
          : q.kind === 'tf' ? (q.answers[0] === 'v' ? T['true'] : T['false']) : q.kind === 'num' ? q.answers.join(' | ') + (q.tol ? ' ± ' + q.tol : '') : '';
        h += '<p class="fb"><strong>' + esc(r >= q.points ? T.correct : T.wrong) + '</strong>' + (r < q.points && right ? ' · ' + esc(T.right_answer) + ': ' + (q.kind === 'single' || q.kind === 'multi' ? right : esc(right)) : '') + (q.explain ? '<br>' + q.explain : '') + '</p>';
      }
      h += '</fieldset>';
    });
    h += '<div class="actions">' + (result ? '<button type="button" class="btn ghost" data-retry>' + esc(T.retry) + '</button>' : '<button class="btn">' + esc(T.submit) + '</button>') + '</div></form>' + nav(i) + '</div>';
    var el = $('[data-main]'); el.innerHTML = h;
    var f = $('[data-quiz]', el);
    f.onsubmit = function (e) {
      e.preventDefault();
      var ans = [], marks = [], pts = 0, max = 0, blank = 0;
      s.questions.forEach(function (q, k) {
        var nm = 'q' + k, a, m = 0;
        if (q.kind === 'multi') { a = [].map.call(f.querySelectorAll('[name="' + nm + '"]:checked'), function (x) { return +x.value; }); if (!a.length) blank++; }
        else if (q.kind === 'single') { var x = f.querySelector('[name="' + nm + '"]:checked'); a = x ? +x.value : null; if (a === null) blank++; }
        else if (q.kind === 'tf') { var y = f.querySelector('[name="' + nm + '"]:checked'); a = y ? y.value : null; if (a === null) blank++; }
        else { a = f.querySelector('[name="' + nm + '"]').value; if (!a.trim()) blank++; }
        if (q.kind === 'single') m = a !== null && q.options[a] && q.options[a][1] ? q.points : 0;
        else if (q.kind === 'multi') { var c = 0, w = 0, tot = q.options.filter(function (o) { return o[1]; }).length; a.forEach(function (j) { if (q.options[j][1]) c++; else w++; }); m = tot && a.length ? Math.max(0, (c - w) / tot) * q.points : 0; }
        else if (q.kind === 'tf') m = a === q.answers[0] ? q.points : 0;
        else if (q.kind === 'short') m = norm(a) !== '' && q.answers.indexOf(norm(a)) >= 0 ? q.points : 0;
        else if (q.kind === 'num') { var n = num(a); m = n !== null && q.answers.some(function (t) { return Math.abs(n - t) <= q.tol + 1e-9; }) ? q.points : 0; }
        if (q.kind !== 'open') { pts += m; max += q.points; }
        ans.push(a); marks.push(q.kind === 'open' ? null : m);
      });
      if (blank && !confirm(T.unanswered)) return;
      var pct = max ? Math.floor(pts * 100 / max + 1e-9) : 100;
      S.q[s.id] = Math.max(S.q[s.id] || 0, pct);
      if (pct >= s.pass) markDone(s); else save();
      quiz(s, i, { pct: pct, ans: ans, marks: marks });
      $('[data-main]').scrollTop = 0;
    };
    var rt = $('[data-retry]', el); if (rt) rt.onclick = function () { quiz(s, i, null); };
    bindNav(el, i);
  }

  function go(i) {
    i = Math.max(0, Math.min(steps.length - 1, i)); S.loc = i;
    var s = steps[i];
    if (s.type === 'quiz') quiz(s, i, null); else lesson(s, i);
    save(); $('[data-main]').scrollTop = 0; $('[data-main]').focus();
  }

  $('[data-title]').textContent = C.title;
  $('[data-menu]').onclick = function () { $('[data-side]').classList.toggle('open'); };
  go(S.loc || 0);
  window.addEventListener('pagehide', function () { save(); lms.finish(); });
  window.addEventListener('beforeunload', function () { save(); lms.finish(); });
})();
