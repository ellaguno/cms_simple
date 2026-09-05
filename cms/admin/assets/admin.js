/* cms_simple admin — editor visual (Quill), idioma ES/EN, subida de archivos, biblioteca, slug, menú, confirmaciones */
(function () {
  "use strict";
  var A = window.CMS_ADMIN || { base: "", upload: "/admin/?p=upload", media: "/admin/?p=media&json=1", csrf: "", langs: ["es"], defaultLang: "es" };

  /* ---------------- menú lateral: grupos plegables (recuerda abierto/cerrado) ---------------- */
  document.querySelectorAll(".ad-nav-group").forEach(function (d) {
    var key = "adnav:" + d.getAttribute("data-nav-group");
    try {
      var saved = localStorage.getItem(key);
      if (!d.hasAttribute("data-active")) d.open = saved === null ? true : saved === "1";
    } catch (e) {}
    d.addEventListener("toggle", function () { try { localStorage.setItem(key, d.open ? "1" : "0"); } catch (e) {} });
  });

  /* ---------------- utilidades ---------------- */
  function upload(file) {
    var fd = new FormData();
    fd.append("file", file);
    fd.append("_csrf", A.csrf);
    return fetch(A.upload, { method: "POST", body: fd, credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) throw new Error(j.error || "Error al subir"); return j; });
  }
  function pickFile(cb, accept) {
    var inp = document.createElement("input");
    inp.type = "file"; inp.accept = accept || "image/*";
    inp.onchange = function () { if (inp.files[0]) cb(inp.files[0]); };
    inp.click();
  }
  function toast(msg) {
    var t = document.createElement("div"); t.className = "ad-toast"; t.textContent = msg; document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 1800);
  }
  function imgUrl(v) {
    v = (v || "").trim();
    if (!v) return "";
    if (/^(https?:)?\/\//.test(v)) return v;
    return (v.indexOf("uploads/") === 0 || v.indexOf("assets/") === 0) ? A.base + "/" + v : A.base + "/assets/img/" + v;
  }
  function esc(s) { return String(s).replace(/[<>&"]/g, function (c) { return { "<": "&lt;", ">": "&gt;", "&": "&amp;", '"': "&quot;" }[c]; }); }

  /* ---------------- biblioteca de medios (selector) ---------------- */
  function openPicker(type, onPick) {
    fetch(A.media + "&type=" + encodeURIComponent(type || ""), { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var m = document.createElement("div"); m.className = "ad-modal";
        var items = (j.items || []).map(function (it) {
          var th = it.type === "image" ? '<img src="' + esc(it.url) + '" alt="">' : '<div style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-weight:600;color:#595959">' + esc(it.type.toUpperCase()) + '</div>';
          return '<button type="button" class="ad-pick" data-item="' + esc(JSON.stringify(it)) + '">' + th + '<span title="' + esc(it.path) + '">' + esc(it.name) + '</span></button>';
        }).join("");
        m.innerHTML = '<div class="ad-modal-box"><div class="ad-modal-head"><h3>Biblioteca de medios</h3><button type="button" class="ad-btn ad-btn-sm ad-btn-light" data-close>Cerrar</button></div><div class="ad-modal-body">' +
          (items ? '<div class="ad-pick-grid">' + items + '</div>' : '<p class="ad-help">No hay archivos subidos todavía. Súbelos en Medios o con el botón "Subir imagen".</p>') + '</div></div>';
        document.body.appendChild(m);
        m.addEventListener("click", function (e) {
          if (e.target === m || e.target.closest("[data-close]")) m.remove();
          var p = e.target.closest(".ad-pick");
          if (p) { onPick(JSON.parse(p.getAttribute("data-item"))); m.remove(); }
        });
      }).catch(function () { alert("No se pudo cargar la biblioteca."); });
  }
  window.CMS_openPicker = openPicker;

  /* ---------------- editor visual (Quill) ---------------- */
  if (window.Quill) {
    var BlockEmbed = Quill.import("blots/block/embed");
    var Delta = Quill.import("delta");

    /* Video de la Biblioteca (archivo mp4/webm) */
    class VideoFile extends BlockEmbed {
      static create(url) {
        var n = super.create();
        n.setAttribute("src", url); n.setAttribute("controls", ""); n.setAttribute("playsinline", ""); n.setAttribute("preload", "metadata");
        n.style.maxWidth = "100%";
        return n;
      }
      static value(n) { return n.getAttribute("src"); }
    }
    VideoFile.blotName = "videofile";
    VideoFile.tagName = "video";
    Quill.register(VideoFile);

    /* Video de YouTube / Vimeo: <div class="video-embed"><iframe …></div>, responsivo al 100 % del ancho.
       Sustituye al blot "video" de Quill (iframe suelto de 300 px). El tema debe estilizar .video-embed. */
    function embedUrl(url) {
      url = (url || "").trim();
      var m;
      if ((m = url.match(/(?:youtube(?:-nocookie)?\.com\/(?:embed\/|watch\?(?:.*&)?v=|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/))) return "https://www.youtube-nocookie.com/embed/" + m[1];
      if ((m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/))) return "https://player.vimeo.com/video/" + m[1];
      return url;
    }
    class VideoEmbed extends BlockEmbed {
      static create(url) {
        var n = super.create();
        var f = document.createElement("iframe");
        f.className = "ql-video";   // los temas antiguos estilizan iframe.ql-video
        f.setAttribute("src", embedUrl(url));
        f.setAttribute("loading", "lazy");
        f.setAttribute("allow", "accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share");
        f.setAttribute("allowfullscreen", "");
        f.setAttribute("title", "Video");
        n.appendChild(f);
        return n;
      }
      static value(n) { var f = n.querySelector("iframe"); return f ? f.getAttribute("src") : ""; }
    }
    VideoEmbed.blotName = "video";
    VideoEmbed.tagName = "div";
    VideoEmbed.className = "video-embed";
    Quill.register(VideoEmbed, true);

    /* Línea horizontal */
    class Divider extends BlockEmbed {}
    Divider.blotName = "divider";
    Divider.tagName = "hr";
    Quill.register(Divider);

    var titles = { bold: "Negritas", italic: "Cursivas", underline: "Subrayado", strike: "Tachado", blockquote: "Cita", "code-block": "Bloque de código", link: "Enlace",
      image: "Insertar imagen", video: "Video de YouTube o Vimeo (pega la URL)", clean: "Quitar formato", upload: "Subir una imagen desde tu computadora",
      library: "Insertar imagen, PDF o video de la Biblioteca", hr: "Línea horizontal", html: "Editar el HTML directamente", indent: "Sangría" };

    /* Carga CodeMirror bajo demanda para el modo HTML */
    var cmLoading = null;
    function loadCodeMirror() {
      if (window.CodeMirror) return Promise.resolve();
      if (cmLoading) return cmLoading;
      var base = "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/";
      var css = document.createElement("link"); css.rel = "stylesheet"; css.href = base + "codemirror.min.css"; document.head.appendChild(css);
      var files = ["codemirror.min.js", "mode/xml/xml.min.js", "mode/javascript/javascript.min.js", "mode/css/css.min.js", "mode/htmlmixed/htmlmixed.min.js", "addon/edit/closetag.min.js", "addon/selection/active-line.min.js"];
      cmLoading = files.reduce(function (p, f) {
        return p.then(function () { return new Promise(function (res, rej) { var sc = document.createElement("script"); sc.src = base + f; sc.onload = res; sc.onerror = rej; document.head.appendChild(sc); }); });
      }, Promise.resolve());
      return cmLoading;
    }
    /* HTML legible: un bloque por línea */
    function prettyHtml(h) {
      return h.replace(/></g, ">\n<")
        .replace(/\n<(\/?)(strong|em|u|s|a|br|span|code|mark|sub|sup|b|i)\b/g, "<$1$2")
        .replace(/(<\/(?:strong|em|u|s|a|span|code|mark|sub|sup|b|i)>)\n/g, "$1")
        .replace(/(<br\s*\/?>)\n/g, "$1")
        .replace(/\n{2,}/g, "\n").trim();
    }

    document.querySelectorAll("textarea[data-html]").forEach(function (ta) {
      var wrap = document.createElement("div");
      wrap.className = "ad-editor" + (ta.name.indexOf("body") === 0 || ta.getAttribute("data-size") === "lg" ? " ad-editor-lg" : "");
      var box = document.createElement("div");
      wrap.appendChild(box);
      ta.parentNode.insertBefore(wrap, ta);
      box.innerHTML = ta.value;
      // iframes antiguos de Quill (ql-video) o sueltos → bloque .video-embed
      box.querySelectorAll("iframe").forEach(function (f) {
        if (f.parentNode.classList && f.parentNode.classList.contains("video-embed")) return;
        var d = document.createElement("div"); d.className = "video-embed"; f.parentNode.insertBefore(d, f); d.appendChild(f);
        f.className = "ql-video"; f.removeAttribute("width"); f.removeAttribute("height");
      });

      var quill, cm = null, mode = "visual";
      var src = document.createElement("textarea");
      src.className = "ad-editor-src"; src.hidden = true; src.spellcheck = false;
      wrap.appendChild(src);

      var handlers = {
        upload: function () {
          pickFile(function (file) {
            toast("Subiendo…");
            upload(file).then(function (j) {
              var r = quill.getSelection(true);
              quill.insertEmbed(r.index, "image", j.url, "user");
              quill.setSelection(r.index + 1, 0, "silent");
            }).catch(function (e) { alert(e.message); });
          });
        },
        library: function () {
          openPicker("", function (it) {
            var r = quill.getSelection(true), i = r.index;
            if (it.type === "image") { quill.insertEmbed(i, "image", it.url, "user"); quill.setSelection(i + 1, 0, "silent"); }
            else if (it.type === "video") { quill.insertEmbed(i, "videofile", it.url, "user"); quill.setSelection(i + 1, 0, "silent"); }
            else { var txt = it.name + " (PDF)"; quill.insertText(i, txt, { link: it.url }, "user"); quill.setSelection(i + txt.length, 0, "silent"); }
          });
        },
        hr: function () {
          var r = quill.getSelection(true);
          quill.insertEmbed(r.index, "divider", true, "user");
          quill.setSelection(r.index + 1, 0, "silent");
        },
        html: function () { toggleMode(); }
      };
      handlers.image = handlers.upload;

      quill = new Quill(box, {
        theme: "snow",
        placeholder: "Escribe aquí…",
        modules: {
          toolbar: {
            container: [
              [{ header: [1, 2, 3, 4, false] }],
              ["bold", "italic", "underline", "strike"],
              [{ list: "ordered" }, { list: "bullet" }, { indent: "-1" }, { indent: "+1" }],
              ["blockquote", "code-block", "hr"],
              [{ align: [] }],
              ["link", "video"],
              ["upload", "library"],
              ["clean", "html"]
            ],
            handlers: handlers
          },
          uploader: {
            handler: function (range, files) {
              Array.prototype.forEach.call(files, function (f) {
                upload(f).then(function (j) { var r = quill.getSelection(true) || range; quill.insertEmbed(r.index, "image", j.url, "user"); })
                  .catch(function (e) { alert(e.message); });
              });
            }
          }
        }
      });
      // iframes pegados desde el portapapeles → video
      quill.clipboard.addMatcher("IFRAME", function (node) { return new Delta().insert({ video: node.getAttribute("src") }); });

      var tb = wrap.querySelector(".ql-toolbar");
      tb.querySelector(".ql-upload").textContent = "Subir imagen";
      tb.querySelector(".ql-library").textContent = "Biblioteca";
      tb.querySelector(".ql-hr").textContent = "—";
      tb.querySelector(".ql-html").textContent = "HTML";
      tb.querySelector(".ql-html").classList.add("ql-html-toggle");
      Object.keys(titles).forEach(function (k) { tb.querySelectorAll(".ql-" + k).forEach(function (b) { b.title = titles[k]; }); });

      // sincronizar con el textarea oculto (en modo HTML manda el código tal cual se escribió)
      var visualHtml = function () {
        var empty = quill.getLength() <= 1 && !quill.root.querySelector("img,video,iframe,hr");
        return empty ? "" : quill.root.innerHTML;
      };
      var sync = function () {
        if (mode === "html") { if (cm) cm.save(); ta.value = src.value.trim(); }
        else ta.value = visualHtml();
      };
      quill.on("text-change", sync);
      sync();
      var form = ta.closest("form");
      if (form) form.addEventListener("submit", sync);

      function toggleMode() {
        var btn = tb.querySelector(".ql-html");
        if (mode === "visual") {
          src.value = prettyHtml(visualHtml());
          mode = "html";
          wrap.classList.add("ad-editor-html");
          btn.classList.add("ql-active");
          src.hidden = false;
          loadCodeMirror().then(function () {
            if (mode !== "html" || cm) return;
            cm = CodeMirror.fromTextArea(src, { mode: "htmlmixed", lineNumbers: true, lineWrapping: true, autoCloseTags: true, styleActiveLine: true, viewportMargin: 50 });
            cm.setSize("100%", wrap.classList.contains("ad-editor-lg") ? 520 : 360);
            cm.on("change", function () { cm.save(); ta.value = src.value.trim(); });
          }).catch(function () { /* sin CodeMirror: queda el textarea */ });
          src.addEventListener("input", function () { ta.value = src.value.trim(); });
        } else {
          if (cm) cm.save();
          var html = src.value.trim();
          mode = "visual";
          wrap.classList.remove("ad-editor-html");
          btn.classList.remove("ql-active");
          if (cm) { cm.toTextArea(); cm = null; }
          src.hidden = true;
          quill.setContents([], "silent");
          quill.clipboard.dangerouslyPasteHTML(0, html, "silent");
          sync();
        }
      }
    });
  }

  /* ---------------- conmutador Español / English ---------------- */
  document.querySelectorAll("[data-lang-switch]").forEach(function (sw) {
    var form = sw.closest("form");
    var key = "kt-edit-lang";
    function refValue(block) {
      var el = block.querySelector("input, textarea");
      if (!el) return "";
      if (el.hasAttribute("data-html")) { var d = document.createElement("div"); d.innerHTML = el.value; return d.textContent || ""; }
      return el.value || "";
    }
    function apply(lang) {
      form.setAttribute("data-edit-lang", lang);
      sw.querySelectorAll("[data-set-lang]").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-set-lang") === lang); });
      try { localStorage.setItem(key, lang); } catch (e) {}
      form.querySelectorAll(".ad-langs").forEach(function (g) {
        var es = g.querySelector('.ad-lang[data-lang="es"]'), en = g.querySelector('.ad-lang[data-lang="en"]');
        if (!es || !en) return;
        var old = g.querySelector(".ad-ref"); if (old) old.remove();
        var badge = g.parentNode.querySelector(".ad-missing"); if (badge) badge.remove();
        if (lang === "en") {
          var ref = refValue(es).trim();
          var r = document.createElement("div"); r.className = "ad-ref";
          r.innerHTML = "<b>ES:</b> " + (ref ? esc(ref.slice(0, 600)) : "<i>(vacío)</i>");
          en.appendChild(r);
          if (!refValue(en).trim() && ref) {
            var lbl = g.parentNode.querySelector("label");
            if (lbl) { var m = document.createElement("span"); m.className = "ad-missing"; m.textContent = "sin traducir"; lbl.appendChild(m); }
          }
        }
      });
    }
    sw.addEventListener("click", function (e) {
      var b = e.target.closest("[data-set-lang]");
      if (b) apply(b.getAttribute("data-set-lang"));
    });
    var initial = "es";
    try { initial = localStorage.getItem(key) === "en" ? "en" : "es"; } catch (e) {}
    apply(initial);
  });

  /* ---------------- campos de imagen: subir / biblioteca / vista previa ---------------- */
  document.querySelectorAll("[data-upload]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var row = btn.closest(".ad-image-row"), input = row.querySelector("[data-image-input]"), img = row.querySelector("[data-preview]");
      pickFile(function (file) {
        btn.disabled = true; btn.textContent = "Subiendo…";
        upload(file).then(function (j) { input.value = j.path; img.src = j.url; img.hidden = false; })
          .catch(function (e) { alert(e.message); }).finally(function () { btn.disabled = false; btn.textContent = "Subir imagen"; });
      });
    });
  });
  document.querySelectorAll("[data-image-input]").forEach(function (input) {
    input.addEventListener("change", function () {
      var img = input.closest(".ad-image-row").querySelector("[data-preview]");
      var v = imgUrl(input.value);
      if (!v) { img.hidden = true; return; }
      img.src = v; img.hidden = false;
    });
  });
  document.querySelectorAll("[data-pick]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = btn.closest(".ad-image-row").querySelector("[data-image-input]");
      openPicker(btn.getAttribute("data-pick"), function (it) { input.value = it.path; input.dispatchEvent(new Event("change")); });
    });
  });
  document.querySelectorAll("[data-upload-append]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var ta = btn.closest(".ad-field").querySelector("textarea[data-image-list]"), suffix = ta.getAttribute("data-image-suffix") || "";
      pickFile(function (file) {
        btn.disabled = true; btn.textContent = "Subiendo…";
        upload(file).then(function (j) { ta.value = (ta.value.trim() ? ta.value.replace(/\s+$/, "") + "\n" : "") + j.path + suffix; })
          .catch(function (e) { alert(e.message); }).finally(function () { btn.disabled = false; btn.textContent = "Subir y agregar"; });
      });
    });
  });
  document.querySelectorAll("[data-pick-append]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var ta = btn.closest(".ad-field").querySelector("textarea[data-image-list]"), suffix = ta.getAttribute("data-image-suffix") || "";
      openPicker(btn.getAttribute("data-pick-append"), function (it) { ta.value = (ta.value.trim() ? ta.value.replace(/\s+$/, "") + "\n" : "") + it.path + suffix; });
    });
  });

  /* ---------------- slug automático ---------------- */
  var form = document.querySelector("form[data-slug-source]");
  if (form) {
    var src = form.querySelector('[name="' + form.getAttribute("data-slug-source") + '"]'), slug = form.querySelector("[data-slug]"), prev = form.querySelector("[data-slug-preview]");
    var touched = !!(slug && slug.value);
    var slugify = function (s) { return s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, ""); };
    if (src && slug) {
      src.addEventListener("input", function () { if (!touched) { slug.value = slugify(src.value); if (prev) prev.textContent = slug.value; } });
      slug.addEventListener("input", function () { touched = slug.value !== ""; slug.value = slugify(slug.value); if (prev) prev.textContent = slug.value; });
    }
  }

  /* ---------------- menú ---------------- */
  document.querySelectorAll("[data-menu-rows]").forEach(function (box) {
    var lang = box.getAttribute("data-lang");
    box.addEventListener("click", function (e) {
      var row = e.target.closest(".ad-menu-row");
      if (!row) return;
      if (e.target.matches("[data-remove]")) row.remove();
      if (e.target.matches("[data-up]") && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
      if (e.target.matches("[data-down]") && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
    });
    var add = box.parentNode.querySelector("[data-add-row]");
    if (add) add.addEventListener("click", function () {
      box.insertAdjacentHTML("beforeend", document.getElementById("menu-row-tpl").innerHTML.replace(/__L__/g, lang));
    });
  });

  /* ---------------- medios: copiar, arrastrar, auto-envío ---------------- */
  document.querySelectorAll("[data-copy]").forEach(function (b) {
    b.addEventListener("click", function () {
      var v = b.getAttribute("data-copy");
      (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { toast("Copiado: " + v); }, function () { window.prompt("Copia la ruta:", v); });
    });
  });
  var dz = document.querySelector("[data-dropzone]");
  if (dz) {
    var dform = dz.querySelector("form"), dinput = dform.querySelector("input[type=file]");
    dinput.addEventListener("change", function () { if (dinput.files.length) dform.submit(); });
    ["dragenter", "dragover"].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add("over"); }); });
    ["dragleave", "drop"].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove("over"); }); });
    dz.addEventListener("drop", function (e) {
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      try { dinput.files = e.dataTransfer.files; dform.submit(); } catch (err) { alert("Tu navegador no permite soltar archivos aquí; usa el botón Subir archivos."); }
    });
  }

  /* ---------------- contadores SEO ---------------- */
  document.querySelectorAll('[name^="seo_title"], [name^="seo_desc"]').forEach(function (el) {
    var max = el.name.indexOf("seo_title") === 0 ? 60 : 160;
    var c = document.createElement("div"); c.className = "ad-count"; el.parentNode.appendChild(c);
    var upd = function () { c.textContent = el.value.length + " / " + max; c.classList.toggle("over", el.value.length > max); };
    el.addEventListener("input", upd); upd();
  });

  /* ---------------- editor de código (CodeMirror) ---------------- */
  document.querySelectorAll("textarea[data-code]").forEach(function (ta) {
    if (!window.CodeMirror) return;
    var mode = ta.getAttribute("data-mode") || "text/plain";
    var cm = CodeMirror.fromTextArea(ta, {
      mode: mode, theme: "eclipse", lineNumbers: true, lineWrapping: true, indentUnit: 4, tabSize: 4, indentWithTabs: false,
      matchBrackets: true, autoCloseBrackets: true, autoCloseTags: mode !== "text/css" && mode !== "text/javascript", styleActiveLine: true, viewportMargin: 50,
      extraKeys: { "Ctrl-S": function () { cm.save(); ta.form.requestSubmit(); }, "Cmd-S": function () { cm.save(); ta.form.requestSubmit(); }, Tab: function (c) { c.replaceSelection("    ", "end"); } }
    });
    cm.setSize("100%", "70vh");
    var dirty = false;
    cm.on("change", function () { dirty = true; });
    ta.form.addEventListener("submit", function () { cm.save(); dirty = false; });
    window.addEventListener("beforeunload", function (e) { if (dirty) { e.preventDefault(); e.returnValue = ""; } });
  });

  /* ---------------- confirmaciones ---------------- */
  document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) { if (!window.confirm(f.getAttribute("data-confirm"))) e.preventDefault(); });
  });
})();
