/* cms_simple admin — importador de diseños: rasteriza el PDF o imagen en el navegador (pdf.js), extrae el texto,
 * corta pantallas y las manda al servidor, que llama al modelo y crea el borrador. */
(function () {
  "use strict";
  var C = window.CMS_IMPORT, A = window.CMS_ADMIN || {};
  var form = document.querySelector("[data-import-form]");
  if (!form || !C) return;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var fileInput = $("[data-import-file]"), drop = $("[data-import-drop]"), nameEl = $("[data-import-filename]");
  var progress = $("[data-import-progress]"), status = $("[data-import-status]"), thumbs = $("[data-import-thumbs]"), result = $("[data-import-result]");
  var go = $("[data-import-go]"), title = $("[data-import-title]"), slug = $("[data-import-slug]");
  var W = C.screenW || 1400, H = C.screenH || 1100, OVERLAP = 120;
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
    var fd = new FormData(); fd.append("_csrf", A.csrf); fd.append("action", "modelos");
    fetch(C.endpoint, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
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
  }
  $("[data-import-pick]").addEventListener("click", function () { fileInput.click(); });
  fileInput.addEventListener("change", function () { setFile(fileInput.files[0]); });
  ["dragenter", "dragover"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.add("over"); }); });
  ["dragleave", "drop"].forEach(function (e) { drop.addEventListener(e, function (ev) { ev.preventDefault(); drop.classList.remove("over"); }); });
  drop.addEventListener("drop", function (ev) { setFile(ev.dataTransfer.files[0]); });

  function say(msg) { status.textContent = msg; }

  /* ---- rasterizado ---- */
  function loadPdfjs() {
    if (pdfjsLib) return Promise.resolve(pdfjsLib);
    return import(C.pdfjs).then(function (lib) { lib.GlobalWorkerOptions.workerSrc = C.pdfjsWorker; pdfjsLib = lib; return lib; });
  }

  /** Devuelve {pages: [canvas…], text: string}. Cada canvas mide W de ancho. */
  function renderPdf(file) {
    return loadPdfjs().then(function (lib) { return file.arrayBuffer(); }).then(function (buf) {
      return pdfjsLib.getDocument({ data: buf }).promise;
    }).then(function (pdf) {
      var pages = [], texts = [], chain = Promise.resolve();
      var n = Math.min(pdf.numPages, 40);
      for (var i = 1; i <= n; i++) (function (num) {
        chain = chain.then(function () {
          say("Rasterizando página " + num + " de " + n + "…");
          return pdf.getPage(num).then(function (page) {
            var vp0 = page.getViewport({ scale: 1 }), scale = W / vp0.width, vp = page.getViewport({ scale: scale });
            var c = document.createElement("canvas"); c.width = W; c.height = Math.round(vp.height);
            var ctx = c.getContext("2d"); ctx.fillStyle = "#fff"; ctx.fillRect(0, 0, c.width, c.height);
            return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
              pages.push(c);
              return page.getTextContent();
            }).then(function (tc) { texts.push(textLines(tc, vp0.height)); });
          });
        });
      })(i);
      return chain.then(function () { return { pages: pages, text: texts.join("\n\n") }; });
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

  function renderImage(file) {
    return new Promise(function (res, rej) {
      var img = new Image();
      img.onload = function () {
        var c = document.createElement("canvas"); c.width = W; c.height = Math.round(img.height * W / img.width);
        c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(img.src);
        res({ pages: [c], text: "" });
      };
      img.onerror = function () { rej(new Error("No se pudo leer la imagen.")); };
      img.src = URL.createObjectURL(file);
    });
  }

  /** Recorta las filas finales de color uniforme de la última página. */
  function trimBottom(pages) {
    var c = pages[pages.length - 1], ctx = c.getContext("2d"), h = c.height, last = h - 1;
    for (; last > 0; last -= 8) {
      var d = ctx.getImageData(0, last, c.width, 1).data, first = [d[0], d[1], d[2]], diff = false;
      for (var x = 16; x < d.length; x += 16) if (Math.abs(d[x] - first[0]) + Math.abs(d[x + 1] - first[1]) + Math.abs(d[x + 2] - first[2]) > 30) { diff = true; break; }
      if (diff) break;
    }
    var cut = Math.min(h, last + 48);
    if (cut < h - 100) { var n = document.createElement("canvas"); n.width = c.width; n.height = cut; n.getContext("2d").drawImage(c, 0, 0); pages[pages.length - 1] = n; }
    return pages;
  }

  /** Corta la pila de páginas en pantallas de H px con solape; devuelve promesa de [Blob PNG…]. */
  function tile(pages) {
    var total = pages.reduce(function (s, c) { return s + c.height; }, 0), tops = [], y = 0;
    pages.forEach(function (c) { tops.push(y); y += c.height; });
    var blobs = [], chain = Promise.resolve(), start = 0;
    while (start < total) {
      (function (y0) {
        var y1 = Math.min(total, y0 + H);
        chain = chain.then(function () {
          var c = document.createElement("canvas"); c.width = W; c.height = y1 - y0;
          var ctx = c.getContext("2d"); ctx.fillStyle = "#fff"; ctx.fillRect(0, 0, c.width, c.height);
          pages.forEach(function (p, i) {
            var pt = tops[i], pb = pt + p.height;
            if (pb <= y0 || pt >= y1) return;
            var sy = Math.max(0, y0 - pt), sh = Math.min(pb, y1) - Math.max(pt, y0);
            ctx.drawImage(p, 0, sy, W, sh, 0, Math.max(pt, y0) - y0, W, sh);
          });
          var t = document.createElement("img"); t.src = c.toDataURL("image/jpeg", 0.6); t.style.cssText = "width:70px;height:55px;object-fit:cover;object-position:top;border:1px solid #ddd;border-radius:4px";
          thumbs.appendChild(t);
          return new Promise(function (res) { c.toBlob(function (b) { blobs.push(b); res(); }, "image/png"); });
        });
      })(start);
      if (start + H >= total) break;
      start += H - OVERLAP;
    }
    return chain.then(function () { return blobs; });
  }

  /* ---- envío ---- */
  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    if (!picked) { alert("Elige primero el archivo del diseño."); return; }
    go.disabled = true; progress.hidden = false; result.hidden = true; thumbs.innerHTML = ""; status.className = "ad-flash ok";
    say("Leyendo el archivo…");
    var isPdf = /\.pdf$/i.test(picked.name);
    (isPdf ? renderPdf(picked) : renderImage(picked)).then(function (r) {
      var pages = trimBottom(r.pages);
      say("Cortando pantallas…");
      return tile(pages).then(function (blobs) { return { blobs: blobs, text: r.text }; });
    }).then(function (r) {
      say("Analizando " + r.blobs.length + " pantallas con el modelo. Suele tardar de 1 a 3 minutos; no cierres esta página.");
      var fd = new FormData(form);
      fd.append("_csrf", A.csrf); fd.append("action", "analizar"); fd.append("source", picked.name); fd.append("text", r.text);
      fd.delete("file");
      r.blobs.forEach(function (b, i) { fd.append("screens[]", b, "pantalla-" + (i + 1) + ".png"); });
      var timer = setInterval(function () { status.textContent = status.textContent.endsWith("…") ? status.textContent.slice(0, -1) : status.textContent + "…"; }, 1500);
      return fetch(C.endpoint, { method: "POST", body: fd, credentials: "same-origin" }).then(function (res) { clearInterval(timer); return res.json(); });
    }).then(function (j) {
      if (!j.ok) throw new Error(j.error || "Error desconocido");
      progress.hidden = true;
      var st = j.stats || {}, cost = st.cost_usd != null ? " · " + Number(st.cost_usd).toFixed(3) + " USD" : "";
      var h = '<div class="ad-flash ok">Borrador creado con ' + esc(j.sections.length) + ' secciones en ' + esc(st.seconds) + ' s' + cost + ' (' + esc(st.model) + ').</div>';
      h += '<p><a class="ad-btn" href="' + esc(j.edit) + '">Abrir en el constructor</a> <a class="ad-btn ad-btn-light" href="' + esc(j.preview) + '" target="_blank" rel="noopener">Vista previa</a></p>';
      h += '<p><strong>Secciones:</strong> ' + j.sections.map(esc).join(" › ") + "</p>";
      if (j.notes && j.notes.length) h += "<h3>Notas del análisis (imágenes que faltan, ajustes)</h3><ul class=\"ad-list\">" + j.notes.map(function (n) { return "<li>" + esc(n) + "</li>"; }).join("") + "</ul>";
      if (j.unmapped && j.unmapped.length) h += "<h3>Partes del diseño sin bloque equivalente</h3><ul class=\"ad-list\">" + j.unmapped.map(function (n) { return "<li>" + esc(n) + "</li>"; }).join("") + "</ul>";
      if (j.palette) h += "<p><strong>Paleta del diseño:</strong> " + Object.keys(j.palette).map(function (k) { return '<span class="ad-pill" style="border-left:14px solid ' + esc(j.palette[k]) + '">' + esc(k) + " " + esc(j.palette[k]) + "</span>"; }).join(" ") + (j.fonts && j.fonts.length ? " · <strong>Tipografías:</strong> " + j.fonts.map(esc).join(", ") : "") + "</p>";
      h += '<p class="ad-help">Estas notas quedan guardadas en la página, en el panel "Diseño importado" del constructor, junto con las pantallas de referencia.</p>';
      result.innerHTML = h; result.hidden = false;
      go.disabled = false; picked = null; nameEl.textContent = ""; fileInput.value = "";
    }).catch(function (e) {
      status.className = "ad-flash err"; say("No se pudo importar: " + (e && e.message ? e.message : e));
      go.disabled = false;
    });
  });

  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
})();
