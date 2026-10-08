/* Paquete lms — API de SCORM 1.2 (window.API) para reproducir paquetes SCORM dentro de una lección.
   El paquete la busca en la ventana de arriba (esta página) y la usa para leer y guardar su estado. Lo que se guarda
   (estado, calificación, ubicación, suspend_data, comentarios, tiempo) se manda a /aula/scorm al hacer LMSCommit o
   LMSFinish y al salir de la página. Sin alumno (vista del panel o curso abierto sin cuenta), todo funciona pero
   no se guarda. */
(function () {
  'use strict';
  var box = document.querySelector('[data-lms-scorm]');
  if (!box) return;
  var cfg = JSON.parse(box.querySelector('script[type="application/json"]').textContent);
  var frame = box.querySelector('iframe'), statusEl = box.querySelector('[data-scorm-status]');
  var KEEP = ['cmi.core.lesson_status', 'cmi.core.lesson_location', 'cmi.core.score.raw', 'cmi.core.score.min', 'cmi.core.score.max', 'cmi.core.exit', 'cmi.suspend_data', 'cmi.comments'];
  var ERR = { 0: 'No error', 101: 'General exception', 201: 'Invalid argument error', 202: 'Element cannot have children', 203: 'Element not an array - cannot have count',
    301: 'Not initialized', 401: 'Not implemented error', 402: 'Invalid set value, element is a keyword', 403: 'Element is read only', 404: 'Element is write only', 405: 'Incorrect data type' };
  var RO = { 'cmi.core.student_id': 1, 'cmi.core.student_name': 1, 'cmi.core.credit': 1, 'cmi.core.entry': 1, 'cmi.core.total_time': 1, 'cmi.core.lesson_mode': 1,
    'cmi.launch_data': 1, 'cmi.comments_from_lms': 1, 'cmi.student_data.mastery_score': 1, 'cmi.student_data.max_time_allowed': 1, 'cmi.student_data.time_limit_action': 1 };
  var WO = { 'cmi.core.exit': 1, 'cmi.core.session_time': 1 };
  var CHILDREN = {
    'cmi.core._children': 'student_id,student_name,lesson_location,credit,lesson_status,entry,score,total_time,lesson_mode,exit,session_time',
    'cmi.core.score._children': 'raw,min,max',
    'cmi.student_data._children': 'mastery_score,max_time_allowed,time_limit_action',
    'cmi.student_preference._children': 'audio,language,speed,text',
    'cmi.objectives._children': 'id,score,status',
    'cmi.interactions._children': 'id,objectives,time,type,correct_responses,weighting,student_response,result,latency'
  };
  var cur = null, state = 'none', err = 0, dirty = {}, sending = false;

  function fmtTime(s) { s = Math.max(0, s); var h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), x = (s % 60).toFixed(2); return ('000' + h).slice(-4) + ':' + ('0' + m).slice(-2) + ':' + (x < 10 ? '0' : '') + x; }

  /* datos de un SCO al abrirlo: los guardados más los que pone el LMS */
  function load(sco) {
    var saved = (cfg.data && cfg.data[sco.id]) || {}, d = {};
    KEEP.forEach(function (k) { if (saved[k] !== undefined) d[k] = String(saved[k]); });
    d['cmi.core.student_id'] = cfg.student.id;
    d['cmi.core.student_name'] = cfg.student.name;
    d['cmi.core.credit'] = 'credit';
    d['cmi.core.lesson_mode'] = 'normal';
    d['cmi.core.lesson_status'] = d['cmi.core.lesson_status'] || 'not attempted';
    d['cmi.core.entry'] = saved['cmi.core.exit'] === 'suspend' ? 'resume' : (d['cmi.core.lesson_status'] === 'not attempted' ? 'ab-initio' : '');
    d['cmi.core.total_time'] = fmtTime(+saved.total_time || 0);
    d['cmi.core.lesson_location'] = d['cmi.core.lesson_location'] || '';
    d['cmi.suspend_data'] = d['cmi.suspend_data'] || '';
    d['cmi.launch_data'] = sco.launch || '';
    d['cmi.comments'] = d['cmi.comments'] || '';
    d['cmi.comments_from_lms'] = '';
    d['cmi.student_data.mastery_score'] = sco.mastery === null || sco.mastery === undefined ? '' : String(sco.mastery);
    d['cmi.student_data.max_time_allowed'] = '';
    d['cmi.student_data.time_limit_action'] = '';
    ['cmi.core.score.raw', 'cmi.core.score.min', 'cmi.core.score.max'].forEach(function (k) { if (d[k] === undefined) d[k] = ''; });
    ['audio', 'language', 'speed', 'text'].forEach(function (k) { d['cmi.student_preference.' + k] = k === 'language' ? '' : '0'; });
    delete d['cmi.core.exit'];
    return d;
  }

  function count(prefix) { var n = 0, re = new RegExp('^' + prefix.replace(/\./g, '\\.') + '\\.(\\d+)\\.'); Object.keys(cur.d).forEach(function (k) { var m = k.match(re); if (m) n = Math.max(n, +m[1] + 1); }); return n; }

  function valid(el, v) {
    if (el === 'cmi.core.lesson_status') return ['passed', 'completed', 'failed', 'incomplete', 'browsed'].indexOf(v) >= 0;
    if (el === 'cmi.core.exit') return ['time-out', 'suspend', 'logout', ''].indexOf(v) >= 0;
    if (/^cmi\.core\.score\.(raw|min|max)$/.test(el)) return v === '' || (/^-?\d+(\.\d+)?$/.test(v) && +v >= 0 && +v <= 100);
    if (el === 'cmi.core.session_time') return /^\d{2,4}:\d{2}:\d{2}(\.\d{1,2})?$/.test(v);
    if (el === 'cmi.suspend_data') return v.length <= 4096;
    if (el === 'cmi.core.lesson_location') return v.length <= 255;
    return true;
  }

  function known(el) {
    return /^cmi\.(core\.(student_id|student_name|lesson_location|credit|lesson_status|entry|score\.(raw|min|max)|total_time|lesson_mode|exit|session_time)|suspend_data|launch_data|comments|comments_from_lms|student_data\.(mastery_score|max_time_allowed|time_limit_action)|student_preference\.(audio|language|speed|text)|objectives\.\d+\.(id|score\.(raw|min|max)|status)|interactions\.\d+\.(id|objectives\.\d+\.id|time|type|correct_responses\.\d+\.pattern|weighting|student_response|result|latency))$/.test(el);
  }

  var API = {
    LMSInitialize: function (a) {
      if (a !== '' && a !== undefined) { err = 201; return 'false'; }
      if (state === 'running') { err = 101; return 'false'; }
      if (!cur) { err = 101; return 'false'; }
      state = 'running'; err = 0;
      if (cur.d['cmi.core.lesson_status'] === 'not attempted') { cur.d['cmi.core.lesson_status'] = 'incomplete'; dirty['cmi.core.lesson_status'] = 1; }
      show();
      return 'true';
    },
    LMSFinish: function (a) {
      if (a !== '' && a !== undefined) { err = 201; return 'false'; }
      if (state !== 'running') { err = 301; return 'false'; }
      commit(true); state = 'finished'; err = 0;
      return 'true';
    },
    LMSGetValue: function (el) {
      if (state !== 'running') { err = 301; return ''; }
      el = String(el || ''); err = 0;
      if (CHILDREN[el] !== undefined) return CHILDREN[el];
      if (/\._children$/.test(el)) { err = 202; return ''; }
      if (el === 'cmi.objectives._count') return String(count('cmi.objectives'));
      if (el === 'cmi.interactions._count') return String(count('cmi.interactions'));
      if (/\._count$/.test(el)) { err = 203; return ''; }
      if (!known(el)) { err = 401; return ''; }
      if (WO[el] || /^cmi\.interactions\./.test(el)) { err = 404; return ''; }
      return cur.d[el] !== undefined ? String(cur.d[el]) : '';
    },
    LMSSetValue: function (el, v) {
      if (state !== 'running') { err = 301; return 'false'; }
      el = String(el || ''); v = v === undefined || v === null ? '' : String(v); err = 0;
      if (/\._(children|count)$/.test(el)) { err = 402; return 'false'; }
      if (!known(el)) { err = 401; return 'false'; }
      if (RO[el]) { err = 403; return 'false'; }
      if (!valid(el, v)) { err = 405; return 'false'; }
      if (el === 'cmi.comments') v = (cur.d[el] || '') + v;
      cur.d[el] = v; dirty[el] = 1;
      if (el === 'cmi.core.lesson_status' || el === 'cmi.core.score.raw') show();
      return 'true';
    },
    LMSCommit: function (a) {
      if (a !== '' && a !== undefined) { err = 201; return 'false'; }
      if (state !== 'running') { err = 301; return 'false'; }
      commit(false); err = 0; return 'true';
    },
    LMSGetLastError: function () { return String(err); },
    LMSGetErrorString: function (c) { return ERR[+c] || ''; },
    LMSGetDiagnostic: function (c) { return ERR[c === '' || c === undefined ? err : +c] || ''; }
  };
  window.API = API;

  /* con calificación mínima (masteryscore) el LMS decide aprobado o no, como pide SCORM 1.2 */
  function mastery() {
    var m = cur.d['cmi.student_data.mastery_score'], raw = cur.d['cmi.core.score.raw'];
    if (m !== '' && raw !== '' && ['completed', 'passed', 'failed', 'incomplete'].indexOf(cur.d['cmi.core.lesson_status']) >= 0) {
      var st = +raw >= +m ? 'passed' : 'failed';
      if (st !== cur.d['cmi.core.lesson_status'] && (cur.d['cmi.core.lesson_status'] !== 'incomplete' || dirty['cmi.core.score.raw'])) { cur.d['cmi.core.lesson_status'] = st; dirty['cmi.core.lesson_status'] = 1; }
    }
  }

  function payload() {
    mastery();
    var o = {};
    KEEP.forEach(function (k) { if (dirty[k]) o[k] = cur.d[k]; });
    if (cur.d['cmi.core.session_time'] && dirty['cmi.core.session_time']) o['cmi.core.session_time'] = cur.d['cmi.core.session_time'];
    else if (state === 'running' && cur.t0 && !cur.timed) o['cmi.core.session_time'] = fmtTime((Date.now() - cur.t0) / 1000);
    return o;
  }
  function body(o) {
    var f = new FormData();
    f.append('lesson', cfg.lesson); f.append('sco', cur.sco.id); f.append('_lms', cfg.csrf); f.append('data', JSON.stringify(o));
    return f;
  }
  function commit(final) {
    if (!cur) return;
    var o = payload();
    if (o['cmi.core.session_time']) cur.timed = true;
    dirty = {};
    if (!cfg.save || !Object.keys(o).length) { show(); return; }
    if (final && navigator.sendBeacon && document.visibilityState === 'hidden') { navigator.sendBeacon(cfg.url, body(o)); show(); return; }
    sending = true;
    fetch(cfg.url, { method: 'POST', body: body(o), credentials: 'same-origin', keepalive: true })
      .then(function (r) { return r.json(); })
      .then(function (j) { sending = false; if (j && j.ok) { cfg.data[cur.sco.id] = Object.assign(cfg.data[cur.sco.id] || {}, o); if (j.done) box.classList.add('is-done'); show(j.done); } })
      .catch(function () { sending = false; });
  }

  function show(done) {
    if (!statusEl || !cur) return;
    var st = cur.d['cmi.core.lesson_status'] || 'not attempted', raw = cur.d['cmi.core.score.raw'];
    statusEl.textContent = (cfg.t[st] || st) + (raw !== '' && raw !== undefined ? ' · ' + cfg.t.score.replace('%s', Math.round(+raw)) : '') + (done || box.classList.contains('is-done') ? ' · ' + cfg.t.lesson_done : '') + (cfg.save ? '' : ' · ' + cfg.t.not_saved);
    box.querySelectorAll('[data-sco]').forEach(function (b) { b.setAttribute('aria-current', b.dataset.sco === cur.sco.id ? 'true' : 'false'); });
  }

  function open(i) {
    if (cur && state === 'running') { commit(true); }
    var sco = cfg.scos[i];
    cur = { sco: sco, d: load(sco), t0: Date.now(), timed: false }; state = 'none'; dirty = {};
    frame.src = sco.url;
    show();
  }

  box.querySelectorAll('[data-sco]').forEach(function (b, i) { b.addEventListener('click', function () { open(i); }); });
  var full = box.querySelector('[data-scorm-full]');
  if (full) full.addEventListener('click', function () { var el = box.querySelector('.lms-scorm-stage'); (el.requestFullscreen || el.webkitRequestFullscreen || function () {}).call(el); });
  var leave = function () { if (cur && state === 'running') { var o = payload(); dirty = {}; if (cfg.save && Object.keys(o).length && navigator.sendBeacon) navigator.sendBeacon(cfg.url, body(o)); } };
  window.addEventListener('pagehide', leave);
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && cur && state === 'running') { var o = payload(); if (cfg.save && Object.keys(o).length && navigator.sendBeacon) { navigator.sendBeacon(cfg.url, body(o)); cur.t0 = Date.now(); } } });
  open(Math.max(0, cfg.start || 0));
})();
