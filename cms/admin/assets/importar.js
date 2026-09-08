/* cms_simple admin — importador de diseños: rasteriza el PDF o imagen en el navegador (pdf.js), extrae el texto y las
 * imágenes incrustadas con su posición, corta pantallas y las manda al servidor de una en una; el servidor llama al
 * modelo y crea el borrador. Un PDF puede ser una sola página web (apilar) o varias (una por página o cortes a mano). */
(function () {
  "use strict";
  var C = window.CMS_IMPORT;
  /* el token de sesión lo define el pie del panel (admin_footer), que se dibuja después de este script: leerlo al enviar */
  function csrf() { var A = window.CMS_ADMIN || {}, inp = document.querySelector('input[name="_csrf"]'); return A.csrf || (inp ? inp.value : ""); }
  var form = document.querySelector("[data-import-form]");
  if (!form || !C) return;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var fileInput = $("[data-import-file]"), drop = $("[data-import-drop]"), nameEl = $("[data-import-filename]");
  var progress = $("[data-import-progress]"), status = $("[data-import-status]"), thumbs = $("[data-import-thumbs]"), result = $("[data-import-result]");
  var go = $("[data-import-go]"), title = $("[data-import-title]"), slug = $("[data-import-slug]");
  var splitSel = $("[data-import-split]"), splitBox = $("[data-import-splitbox]");
  var W = C.screenW || 1400, H = C.screenH || 1100, OVERLAP = 120, MAX_IMG = 2400, MIN_IMG = 60;
  var picked = null, pdfjsLib = null;

  /* ---- ajustes: mostrar solo los campos del proveedor elegido ---- */
  var prov = $("[data-import-provider]");
  function syncProvider() {
    if (!prov) return;
    document.querySelectorAll("[data-import-if]").forEach(function (el) {
      var on = el.getAttribute("data-import-if") === prov.value;
      el.hidden = !on;
      el.querySelectorAll("select[name=model]").forEach(function (s) { s.disabled = !on; });
    });
  }
  if (prov) { prov.addEventListener("change", syncProvider); syncProvider(); }
  var refresh = $("[data-import-refresh]");
  if (refresh) refresh.addEventListener("click", function () {
    refresh.disabled = true; refresh.textContent = "Actualizando…";
    var fd = new FormData(); fd.append("_csrf", csrf()); fd.append("action", "modelos");
    postJson(fd).then(function (j) {
      var sel = $("[data-import-models]"); sel.innerHTML = "";
      Object.keys(j.models || {}).forEach(function (id) {
        var m = j.models[id], o = document.createElement("option");
        o.value = id; o.textContent = id + " — " + m.in + " / " + m.out + (m.structured ? "" : " (sin salida estructurada)");
        if (id === j.default) o.selected = true;
        sel.appendChild(o);
      });
      $("[data-import-models-info]").textContent = j.count ? j.count + " modelos con visión" : "No se pudo obtener la lista";
    }).catch(function () { $("[data-import-models-info]").textContent = "No se pudo obtener la lista"; })
      .finally(function () { refresh.disabled = false; refresh.textContent = "Actualizar lista"; });
  });

  /* ---- campos según el tipo ---- */
  var typeSel = $("[data-import-type]");
  function syncType() {
    var t = typeSel ? typeSel.value : "";
    document.querySelectorAll("[data-import-type-fields]").forEach(function (el) {
      var on = el.getAttribute("data-import-type-fields") === t;
      el.hidden = !on;
      el.querySelectorAll("select,input").forEach(function (x) { x.disabled = !on; });
    });
  }
  if (typeSel && typeSel.tagName === "SELECT") typeSel.addEventListener("change", syncType);
  syncType();

  /* ---- slug automático ---- */
  var slugify = function (s) { return s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, ""); };
  var slugTouched = false;
  title.addEventListener("input", function () { if (!slugTouched) slug.value = slugify(title.value); });
  slug.addEventListener("input", function () { slugTouched = slug.value !== ""; slug.value = slugify(slug.value); });

  /* ---- elegir archivo ---- */
  function setFile(f) {
    if (!f) return;
    if (!/\.(pdf|png|jpe?g)$/i.test(f.name)) { alert("Solo PDF, PNG o JPG."); return; }
    picked = f;
    nameEl.textContent = "Archivo: " + f.name + " (" + Math.round(f.size / 1024) + " KB)";
    if (!title.value) { title.value = f.name.replace(/\.[^.]+$/, "").replace(/[-_]+/g, " "); title.dispatchEvent(new Event("input")); }
    if (splitSel) splitSel.closest(".ad-field").hidden = !/\.pdf$/i.test(f.name);
  }
  $("[data-import-pick]").addEventListener("click", function () { fileInput.click(); });
  fileInput.addEventListener("change", function () { setFile(fileInput.files[0]); });
  ["dragenter", "dragover"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add("over"); }); });
  ["dragleave", "drop"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove("over"); }); });
  drop.addEventListener("drop", function (ev) { setFile(ev.dataTransfer.files[0]); });

  function say(msg) { status.textContent = msg; }
  function thumb(canvas, w, h, label) {
    var t = document.createElement("img"); t.src = canvas.toDataURL("image/jpeg", 0.6);
    t.style.cssText = "width:" + w + "px;height:" + h + "px;object-fit:cover;object-position:top;border:1px solid #ddd;border-radius:4px";
    if (label) t.title = label;
    thumbs.appendChild(t);
  }

  /* ---- rasterizado ---- */
  function loadPdfjs() {
    if (pdfjsLib) return Promise.resolve(pdfjsLib);
    return import(C.pdfjs).then(function (lib) { lib.GlobalWorkerOptions.workerSrc = C.pdfjsWorker; pdfjsLib = lib; return lib; });
  }

  /** Devuelve [{canvas, text, images: [{canvas, alpha, x, y, w, h}]}, …]: una entrada por página del PDF, canvas de W px de ancho. */
  function renderPdf(file) {
    return loadPdfjs().then(function () { return file.arrayBuffer(); }).then(function (buf) {
      return pdfjsLib.getDocument({ data: buf }).promise;
    }).then(function (pdf) {
      var pages = [], chain = Promise.resolve();
      var n = Math.min(pdf.numPages, 40);
      for (var i = 1; i <= n; i++) (function (num) {
        chain = chain.then(function () {
          say("Rasterizando página " + num + " de " + n + "…");
          return pdf.getPage(num).then(function (page) {
            var vp0 = page.getViewport({ scale: 1 }), scale = W / vp0.width, vp = page.getViewport({ scale: scale });
            var c = document.createElement("canvas"); c.width = W; c.height = Math.round(vp.height);
            var ctx = c.getContext("2d"); ctx.fillStyle = "#fff"; ctx.fillRect(0, 0, c.width, c.height);
            var entry = { canvas: c, text: "", images: [] };
            return page.render({ canvasContext: ctx, viewport: vp }).promise
              .then(function () { return page.getTextContent(); })
              .then(function (tc) { entry.text = textLines(tc, vp0.height); return extractImages(page, vp, c).catch(function () { return []; }); })
              .then(function (imgs) { entry.images = imgs; pages.push(entry); });
          });
        });
      })(i);
      return chain.then(function () { return pages; });
    });
  }

  /** Agrupa los fragmentos de texto de pdf.js en líneas (por su coordenada vertical). */
  function textLines(tc, pageH) {
    var rows = {};
    tc.items.forEach(function (it) {
      if (!it.str || !it.str.trim()) return;
      var y = Math.round((pageH - it.transform[5]) / 4), x = it.transform[4];
      (rows[y] = rows[y] || []).push({ x: x, s: it.str });
    });
    return Object.keys(rows).map(Number).sort(function (a, b) { return a - b; }).map(function (y) {
      return rows[y].sort(function (a, b) { return a.x - b.x; }).map(function (p) { return p.s; }).join(" ").replace(/\s+/g, " ").replace(/ ([,.;:!?)\]»])/g, "$1").replace(/([(\[«¿¡]) /g, "$1").trim();
    }).filter(Boolean).join("\n");
  }

  /**
   * Imágenes incrustadas de la página, a su resolución original, con su posición en la página rasterizada (píxeles).
   * Recorre la lista de operadores llevando la matriz de transformación para saber dónde se pintó cada una. Si pdf.js
   * ya no conserva el objeto (imágenes de un solo uso), se recorta la zona de la página rasterizada.
   */
  function extractImages(page, vp, pageCanvas) {
    var OPS = pdfjsLib.OPS, Util = pdfjsLib.Util;
    return page.getOperatorList().then(function (ol) {
      var ctm = [1, 0, 0, 1, 0, 0], stack = [], boxes = [], seen = {};
      for (var i = 0; i < ol.fnArray.length; i++) {
        var fn = ol.fnArray[i], a = ol.argsArray[i];
        if (fn === OPS.save) stack.push(ctm.slice());
        else if (fn === OPS.restore) ctm = stack.pop() || [1, 0, 0, 1, 0, 0];
        else if (fn === OPS.transform) ctm = Util.transform(ctm, a);
        else if (fn === OPS.paintFormXObjectBegin) { stack.push(ctm.slice()); if (a && a[0]) ctm = Util.transform(ctm, a[0]); }
        else if (fn === OPS.paintFormXObjectEnd) ctm = stack.pop() || ctm;
        else if (fn === OPS.paintImageXObject || fn === OPS.paintImageXObjectRepeat || fn === OPS.paintJpegXObject) {
          var name = a[0];
          if (seen[name]) continue;
          seen[name] = true;
          // la imagen se pinta en el cuadrado unidad bajo la matriz actual
          var pts = [[0, 0], [1, 0], [0, 1], [1, 1]].map(function (p) { var u = Util.applyTransform(p, ctm); return vp.convertToViewportPoint(u[0], u[1]); });
          var xs = pts.map(function (p) { return p[0]; }), ys = pts.map(function (p) { return p[1]; });
          var box = { x: Math.min.apply(null, xs), y: Math.min.apply(null, ys), w: Math.max.apply(null, xs) - Math.min.apply(null, xs), h: Math.max.apply(null, ys) - Math.min.apply(null, ys) };
          if (box.w < MIN_IMG || box.h < MIN_IMG) continue;   // iconos y adornos
          boxes.push({ name: name, box: box });
        }
      }
      var found = [], chain = Promise.resolve();
      boxes.forEach(function (it) {
        chain = chain.then(function () { return getImageObj(page, it.name); }).then(function (obj) {
          var cv = obj ? imageToCanvas(obj) : null;
          if (!cv) cv = cropFromPage(pageCanvas, it.box);
          if (!cv) return;
          var b = it.box;
          found.push({ canvas: cv.canvas, alpha: cv.alpha, x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.w), h: Math.round(b.h), name: it.name, cropped: !obj });
        });
      });
      return chain.then(function () { found.sort(function (p, q) { return p.y - q.y || p.x - q.x; }); return found; });
    });
  }

  /** Objeto imagen de pdf.js por id: ya resuelto, o esperando como mucho 2 s a que lo resuelva; null si no existe. */
  function getImageObj(page, name) {
    return new Promise(function (res) {
      var done = false, finish = function (o) { if (!done) { done = true; res(o || null); } };
      try {
        if (page.objs.has(name)) return finish(page.objs.get(name));
        if (page.commonObjs.has(name)) return finish(page.commonObjs.get(name));
        page.objs.get(name, finish);
      } catch (e) { return finish(null); }
      setTimeout(function () { finish(null); }, 2000);
    });
  }

  /** Recorte de la zona de la página rasterizada (respaldo cuando el objeto no está disponible). */
  function cropFromPage(pageCanvas, box) {
    if (!pageCanvas) return null;
    var x = Math.max(0, Math.round(box.x)), y = Math.max(0, Math.round(box.y));
    var w = Math.min(pageCanvas.width - x, Math.round(box.w)), h = Math.min(pageCanvas.height - y, Math.round(box.h));
    if (w < MIN_IMG || h < MIN_IMG) return null;
    var c = document.createElement("canvas"); c.width = w; c.height = h;
    c.getContext("2d").drawImage(pageCanvas, x, y, w, h, 0, 0, w, h);
    return { canvas: c, alpha: false };
  }

  /** Objeto imagen de pdf.js → canvas (máximo MAX_IMG de ancho). Devuelve {canvas, alpha} o null. */
  function imageToCanvas(obj) {
    var w = obj.width, h = obj.height;
    if (!w || !h) return null;
    var c = document.createElement("canvas"), alpha = false;
    if (obj.bitmap) {
      c.width = w; c.height = h; c.getContext("2d").drawImage(obj.bitmap, 0, 0);
      var d = c.getContext("2d").getImageData(0, 0, w, h).data;
      for (var k = 3; k < d.length; k += 4 * 97) if (d[k] < 250) { alpha = true; break; }
    } else if (obj.data) {
      var kind = obj.kind, src = obj.data, out = new Uint8ClampedArray(w * h * 4), n = w * h;
      if (kind === 3) { out.set(src.subarray(0, n * 4)); for (var j = 3; j < out.length; j += 4 * 97) if (out[j] < 250) { alpha = true; break; } }
      else if (kind === 2) { for (var p = 0, q = 0; p < n; p++, q += 3) { out[p * 4] = src[q]; out[p * 4 + 1] = src[q + 1]; out[p * 4 + 2] = src[q + 2]; out[p * 4 + 3] = 255; } }
      else if (kind === 1) { for (var g = 0; g < n; g++) { var v = (src[g >> 3] >> (7 - (g & 7))) & 1 ? 255 : 0; out[g * 4] = out[g * 4 + 1] = out[g * 4 + 2] = v; out[g * 4 + 3] = 255; } }
      else return null;
      c.width = w; c.height = h; c.getContext("2d").putImageData(new ImageData(out, w, h), 0, 0);
    } else return null;
    if (w > MAX_IMG) {
      var s = document.createElement("canvas"); s.width = MAX_IMG; s.height = Math.round(h * MAX_IMG / w);
      s.getContext("2d").drawImage(c, 0, 0, s.width, s.height); c = s;
    }
    return { canvas: c, alpha: alpha };
  }

  function renderImage(file) {
    return new Promise(function (res, rej) {
      var img = new Image();
      img.onload = function () {
        var c = document.createElement("canvas"); c.width = W; c.height = Math.round(img.height * W / img.width);
        c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(img.src);
        res([{ canvas: c, text: "", images: [] }]);
      };
      img.onerror = function () { rej(new Error("No se pudo leer la imagen.")); };
      img.src = URL.createObjectURL(file);
    });
  }

  /** Recorta las filas finales de color uniforme de la última página del grupo. */
  function trimBottom(canvas) {
    var ctx = canvas.getContext("2d"), h = canvas.height, last = h - 1;
    for (; last > 0; last -= 8) {
      var d = ctx.getImageData(0, last, canvas.width, 1).data, first = [d[0], d[1], d[2]], diff = false;
      for (var x = 16; x < d.length; x += 16) if (Math.abs(d[x] - first[0]) + Math.abs(d[x + 1] - first[1]) + Math.abs(d[x + 2] - first[2]) > 30) { diff = true; break; }
      if (diff) break;
    }
    var cut = Math.min(h, last + 48);
    if (cut < h - 100) { var n = document.createElement("canvas"); n.width = canvas.width; n.height = cut; n.getContext("2d").drawImage(canvas, 0, 0); return n; }
    return canvas;
  }

  /** Apila las páginas de un grupo y las corta en pantallas de H px con solape. Devuelve {blobs, images, text}. */
  function tileGroup(pages) {
    var canvases = pages.map(function (p) { return p.canvas; });
    canvases[canvases.length - 1] = trimBottom(canvases[canvases.length - 1]);
    var total = canvases.reduce(function (s, c) { return s + c.height; }, 0), tops = [], y = 0;
    canvases.forEach(function (c) { tops.push(y); y += c.height; });
    var starts = [], start = 0;
    while (true) { starts.push(start); if (start + H >= total) break; start += H - OVERLAP; }
    // imágenes con su pantalla y posición dentro de ella
    var images = [];
    pages.forEach(function (p, i) {
      p.images.forEach(function (im) {
        var ay = tops[i] + im.y, k = 0;
        for (var s = 0; s < starts.length; s++) if (starts[s] <= ay) k = s;
        images.push({ canvas: im.canvas, alpha: im.alpha, page: i + 1, screen: k + 1, x: im.x, y: ay - starts[k], w: im.w, h: im.h });
      });
    });
    var blobs = [], chain = Promise.resolve();
    starts.forEach(function (y0) {
      var y1 = Math.min(total, y0 + H);
      chain = chain.then(function () {
        var c = document.createElement("canvas"); c.width = W; c.height = y1 - y0;
        var ctx = c.getContext("2d"); ctx.fillStyle = "#fff"; ctx.fillRect(0, 0, c.width, c.height);
        canvases.forEach(function (p, i) {
          var pt = tops[i], pb = pt + p.height;
          if (pb <= y0 || pt >= y1) return;
          var sy = Math.max(0, y0 - pt), sh = Math.min(pb, y1) - Math.max(pt, y0);
          ctx.drawImage(p, 0, sy, W, sh, 0, Math.max(pt, y0) - y0, W, sh);
        });
        thumb(c, 70, 55, "Pantalla " + (blobs.length + 1));
        return new Promise(function (res) { c.toBlob(function (b) { blobs.push(b); res(); }, "image/png"); });
      });
    });
    return chain.then(function () { return { blobs: blobs, images: images, text: pages.map(function (p) { return p.text; }).join("\n\n") }; });
  }

  /* ---- grupos: una página web o varias ---- */
  function askSplits(pages) {
    var mode = splitSel ? splitSel.value : "una";
    if (pages.length < 2 || mode === "una") return Promise.resolve([pages]);
    if (mode === "cada") return Promise.resolve(pages.map(function (p) { return [p]; }));
    // marcar cortes a mano
    return new Promise(function (res) {
      splitBox.innerHTML = '<p class="ad-help" style="margin:0 0 8px">Marca dónde empieza cada página web. Las páginas del PDF sin marca se apilan con la anterior.</p>';
      var row = document.createElement("div"); row.style.cssText = "display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start";
      pages.forEach(function (p, i) {
        var lab = document.createElement("label"); lab.style.cssText = "display:flex;flex-direction:column;align-items:center;gap:4px;font-size:12px;cursor:pointer";
        var im = document.createElement("img"); im.src = p.canvas.toDataURL("image/jpeg", 0.5); im.style.cssText = "width:110px;border:1px solid #ddd;border-radius:4px";
        var cb = document.createElement("input"); cb.type = "checkbox"; cb.checked = i === 0; cb.disabled = i === 0; cb.setAttribute("data-split-page", i);
        lab.appendChild(im); lab.appendChild(document.createTextNode("Pág. " + (i + 1))); lab.appendChild(cb); lab.appendChild(document.createTextNode(i === 0 ? "empieza" : "nueva página web"));
        row.appendChild(lab);
      });
      var btn = document.createElement("button"); btn.type = "button"; btn.className = "ad-btn"; btn.textContent = "Continuar"; btn.style.marginTop = "10px";
      splitBox.appendChild(row); splitBox.appendChild(btn); splitBox.hidden = false;
      btn.addEventListener("click", function () {
        var groups = [], cur = [];
        pages.forEach(function (p, i) { if (i > 0 && splitBox.querySelector('[data-split-page="' + i + '"]').checked && cur.length) { groups.push(cur); cur = []; } cur.push(p); });
        if (cur.length) groups.push(cur);
        splitBox.hidden = true; splitBox.innerHTML = "";
        res(groups);
      });
    });
  }

  /* ---- envío de un grupo ---- */
  function uploadAll(g, gi, gn) {
    var token = "", chain = Promise.resolve(), pre = gn > 1 ? "Página web " + gi + " de " + gn + ": " : "";
    g.blobs.forEach(function (b, i) {
      chain = chain.then(function () {
        say(pre + "subiendo pantalla " + (i + 1) + " de " + g.blobs.length + "…");
        var fd = new FormData();
        fd.append("_csrf", csrf()); fd.append("action", "subir"); fd.append("token", token); fd.append("index", i + 1);
        fd.append("screen", b, "pantalla-" + (i + 1) + ".png");
        return postJson(fd).then(function (j) { if (!j.ok) throw new Error(j.error || "No se pudo subir una pantalla"); token = j.token; });
      });
    });
    g.images.forEach(function (im, i) {
      chain = chain.then(function () {
        say(pre + "subiendo imagen " + (i + 1) + " de " + g.images.length + "…");
        thumb(im.canvas, 55, 55, "Imagen " + (i + 1));
        var type = im.alpha ? "image/png" : "image/jpeg";
        return new Promise(function (res) { im.canvas.toBlob(res, type, 0.9); }).then(function (blob) {
          var fd = new FormData();
          fd.append("_csrf", csrf()); fd.append("action", "subir"); fd.append("token", token); fd.append("index", i + 1); fd.append("kind", "imagen");
          fd.append("meta", JSON.stringify({ page: im.page, screen: im.screen, x: im.x, y: im.y, w: im.w, h: im.h, ow: im.canvas.width, oh: im.canvas.height }));
          fd.append("screen", blob, "imagen-" + (i + 1) + (im.alpha ? ".png" : ".jpg"));
          return postJson(fd).then(function (j) { if (!j.ok) throw new Error(j.error || "No se pudo subir una imagen"); token = j.token; });
        });
      });
    });
    return chain.then(function () { return token; });
  }

  function analyze(g, token, gi, gn) {
    say((gn > 1 ? "Página web " + gi + " de " + gn + ": " : "") + "analizando " + g.blobs.length + " pantallas" + (g.images.length ? " y " + g.images.length + " imágenes" : "") + " con el modelo. Suele tardar de 1 a 3 minutos; no cierres esta página.");
    var fd = new FormData(form);
    fd.append("_csrf", csrf()); fd.append("action", "analizar"); fd.append("source", picked.name); fd.append("text", g.text); fd.append("token", token);
    fd.delete("file");
    if (gi > 1) { fd.set("title", ""); fd.set("slug", ""); fd.append("auto_slug", "1"); }   // el título y la URL salen del propio diseño
    var timer = setInterval(function () { status.textContent = status.textContent.endsWith("…") ? status.textContent.slice(0, -1) : status.textContent + "…"; }, 1500);
    return postJson(fd).catch(function (e) {
      // el navegador o un proxy cortaron la conexión larga; el servidor sigue trabajando: esperar y recoger el resultado
      say((gn > 1 ? "Página web " + gi + " de " + gn + ": " : "") + "la conexión se cortó, pero el análisis sigue en el servidor. Esperando el resultado…");
      return waitResult(token, 600).catch(function () { throw e; });
    }).finally(function () { clearInterval(timer); });
  }

  /** Consulta el resultado de un análisis por su token cada 5 s hasta que exista (o hasta agotar los segundos). */
  function waitResult(token, seconds) {
    return new Promise(function (res, rej) {
      var t0 = Date.now();
      (function tick() {
        var fd = new FormData(); fd.append("_csrf", csrf()); fd.append("action", "resultado"); fd.append("token", token);
        postJson(fd).then(function (j) {
          if (j.ok || (j.error && !j.pending)) return res(j);
          if (Date.now() - t0 > seconds * 1000) return rej(new Error("tiempo de espera agotado"));
          setTimeout(tick, 5000);
        }).catch(function () { if (Date.now() - t0 > seconds * 1000) rej(new Error("tiempo de espera agotado")); else setTimeout(tick, 5000); });
      })();
    });
  }

  function renderResult(j, gi, gn) {
    var st = j.stats || {}, cost = st.cost_usd != null ? " · " + Number(st.cost_usd).toFixed(3) + " USD" : "";
    var h = '<div class="ad-flash ok">' + (gn > 1 ? "Página web " + gi + " de " + gn + ": b" : "B") + "orrador «" + esc(j.title) + "» creado con " + esc(j.sections.length) + " secciones en " + esc(st.seconds) + " s" + cost + " (" + esc(st.model) + ")" + (j.images_total ? " · " + esc(j.images_used) + " de " + esc(j.images_total) + " imágenes del diseño colocadas" : "") + ".</div>";
    h += '<p><a class="ad-btn" href="' + esc(j.edit) + '">Abrir en el constructor</a> <a class="ad-btn ad-btn-light" href="' + esc(j.preview) + '" target="_blank" rel="noopener">Vista previa</a></p>';
    h += "<p><strong>Secciones:</strong> " + j.sections.map(esc).join(" › ") + "</p>";
    if (j.notes && j.notes.length) h += "<h3>Notas del análisis</h3><ul class=\"ad-list\">" + j.notes.map(function (n) { return "<li>" + esc(n) + "</li>"; }).join("") + "</ul>";
    if (j.unmapped && j.unmapped.length) h += "<h3>Partes del diseño sin bloque equivalente</h3><ul class=\"ad-list\">" + j.unmapped.map(function (n) { return "<li>" + esc(n) + "</li>"; }).join("") + "</ul>";
    if (j.palette) h += "<p><strong>Paleta del diseño:</strong> " + Object.keys(j.palette).map(function (k) { return '<span class="ad-pill" style="border-left:14px solid ' + esc(j.palette[k]) + '">' + esc(k) + " " + esc(j.palette[k]) + "</span>"; }).join(" ") + (j.fonts && j.fonts.length ? " · <strong>Tipografías:</strong> " + j.fonts.map(esc).join(", ") : "") + "</p>";
    return h;
  }

  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    if (!picked) { alert("Elige primero el archivo del diseño."); return; }
    go.disabled = true; progress.hidden = false; result.hidden = true; result.innerHTML = ""; thumbs.innerHTML = ""; status.className = "ad-flash ok";
    say("Leyendo el archivo…");
    var isPdf = /\.pdf$/i.test(picked.name), html = "";
    (isPdf ? renderPdf(picked) : renderImage(picked)).then(function (pages) {
      var nImg = pages.reduce(function (s, p) { return s + p.images.length; }, 0);
      say("Leídas " + pages.length + " páginas" + (nImg ? " y " + nImg + " imágenes incrustadas" : "") + ".");
      return askSplits(pages);
    }).then(function (groups) {
      var chain = Promise.resolve();
      groups.forEach(function (pages, gi) {
        chain = chain.then(function () {
          thumbs.innerHTML = "";
          say((groups.length > 1 ? "Página web " + (gi + 1) + " de " + groups.length + ": " : "") + "cortando pantallas…");
          return tileGroup(pages).then(function (g) {
            return uploadAll(g, gi + 1, groups.length).then(function (token) { return analyze(g, token, gi + 1, groups.length); });
          }).then(function (j) {
            if (!j.ok) throw new Error(j.error || "Error desconocido");
            html += renderResult(j, gi + 1, groups.length);
            result.innerHTML = html; result.hidden = false;
          });
        });
      });
      return chain;
    }).then(function () {
      progress.hidden = true;
      html += '<p class="ad-help">Las notas y las pantallas de referencia quedan en el panel "Diseño importado" del constructor. Las imágenes del diseño que no se usaron están en uploads/import/ y en Medios.</p>';
      result.innerHTML = html; result.hidden = false;
      go.disabled = false; picked = null; nameEl.textContent = ""; fileInput.value = "";
    }).catch(function (e) {
      status.className = "ad-flash err"; say("No se pudo importar: " + (e && e.message ? e.message : e));
      go.disabled = false;
    });
  });

  /** POST al panel; si la respuesta no es JSON (aviso de PHP, sesión caducada, límite del servidor), lo dice con el texto recibido. */
  function postJson(fd) {
    return fetch(C.endpoint, { method: "POST", body: fd, credentials: "same-origin" }).then(function (res) {
      return res.text().then(function (t) {
        try { return JSON.parse(t); } catch (e) {
          var plain = t.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim().slice(0, 300);
          throw new Error("el servidor respondió " + res.status + (plain ? ": " + plain : " sin contenido"));
        }
      });
    });
  }

  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
})();
