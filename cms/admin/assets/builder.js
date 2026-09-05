/* cms_simple admin — constructor de páginas por secciones: tarjetas, reordenar, añadir, duplicar y vista previa en vivo */
(function () {
  "use strict";
  var form = document.querySelector("form[data-builder]");
  var boxes = document.querySelectorAll("[data-sections]");
  if (!boxes.length) return;

  function uid() { return Math.random().toString(36).slice(2, 8); }
  function ping() { if (form) form.dispatchEvent(new CustomEvent("cms:changed", { bubbles: true })); }

  boxes.forEach(function (box) {
    var name = box.getAttribute("data-sections");
    var list = box.querySelector("[data-sections-list]");
    var empty = box.querySelector(".ad-sections-empty");
    var picker = box.querySelector("[data-section-picker]");
    var counter = 1000;

    function renumber() {
      list.querySelectorAll("[data-sec]").forEach(function (card, i) {
        card.querySelectorAll("[name]").forEach(function (el) {
          el.name = el.name.replace(new RegExp("^" + name.replace(/[[\]]/g, "\\$&") + "\\[[^\\]]*\\]"), name + "[" + i + "]");
        });
      });
      if (empty) empty.hidden = list.querySelector("[data-sec]") !== null;
    }
    function updateTitle(card) {
      var t = card.querySelector("[data-sec-title]");
      var f = card.querySelector('[data-sec-pane="content"] input[type="text"], [data-sec-pane="content"] textarea:not([data-html])');
      var html = card.querySelector('[data-sec-pane="content"] textarea[data-html]');
      var v = f && f.value ? f.value : (html ? (function () { var d = document.createElement("div"); d.innerHTML = html.value; return d.textContent || ""; })() : "");
      if (t) t.textContent = v.replace(/<[^>]+>/g, "").replace(/\s+/g, " ").trim().slice(0, 70);
    }
    function bindCard(card) {
      card.querySelector("[data-sec-toggle]").addEventListener("click", function () { card.classList.toggle("ad-sec-collapsed"); });
      card.querySelector(".ad-sec-head").addEventListener("dblclick", function (e) { if (!e.target.closest("button,label,input")) card.classList.toggle("ad-sec-collapsed"); });
      card.querySelectorAll("[data-sec-tab]").forEach(function (b) {
        b.addEventListener("click", function () {
          card.querySelectorAll("[data-sec-tab]").forEach(function (x) { x.classList.toggle("on", x === b); });
          card.querySelectorAll("[data-sec-pane]").forEach(function (p) { p.hidden = p.getAttribute("data-sec-pane") !== b.getAttribute("data-sec-tab"); });
        });
      });
      card.querySelector("[data-sec-up]").addEventListener("click", function () { var prev = card.previousElementSibling; if (prev) { list.insertBefore(card, prev); renumber(); ping(); } });
      card.querySelector("[data-sec-down]").addEventListener("click", function () { var next = card.nextElementSibling; if (next) { list.insertBefore(next, card); renumber(); ping(); } });
      card.querySelector("[data-sec-del]").addEventListener("click", function () {
        if (!window.confirm("¿Quitar esta sección? Puedes volver a añadirla, pero se pierde su contenido.")) return;
        card.remove(); renumber(); ping();
      });
      card.querySelector("[data-sec-dup]").addEventListener("click", function () { duplicate(card); });
      card.querySelector('.ad-sec-hide input[type="checkbox"]').addEventListener("change", function (e) { card.classList.toggle("ad-sec-hidden", e.target.checked); ping(); });
      card.addEventListener("input", function () { updateTitle(card); });
      card.addEventListener("focusin", function () { select(card, true); });
      // arrastrar para reordenar
      card.addEventListener("dragstart", function (e) {
        if (!e.target.closest(".ad-sec-grip")) { e.preventDefault(); return; }
        card.classList.add("ad-sec-dragging"); e.dataTransfer.effectAllowed = "move"; e.dataTransfer.setData("text/plain", "");
      });
      card.addEventListener("dragend", function () { card.classList.remove("ad-sec-dragging"); renumber(); ping(); });
      card.querySelector(".ad-sec-grip").addEventListener("mousedown", function () { card.setAttribute("draggable", "true"); });
      updateTitle(card);
    }
    list.addEventListener("dragover", function (e) {
      e.preventDefault();
      var dragging = list.querySelector(".ad-sec-dragging"); if (!dragging) return;
      var after = null;
      list.querySelectorAll("[data-sec]:not(.ad-sec-dragging)").forEach(function (c) { var r = c.getBoundingClientRect(); if (e.clientY < r.top + r.height / 2 && after === null) after = c; });
      if (after) list.insertBefore(dragging, after); else list.appendChild(dragging);
    });

    function fromTemplate(type, values) {
      var tpl = box.querySelector('template[data-section-tpl="' + type + '"]');
      if (!tpl) return null;
      var idx = "n" + (counter++), id = uid();
      var html = tpl.innerHTML.replace(/__IDX__/g, idx).replace(/__ID__/g, id);
      var holder = document.createElement("div"); holder.innerHTML = html;
      var card = holder.firstElementChild;
      card.setAttribute("data-sec-id", id);
      if (values) {
        card.querySelectorAll("[name]").forEach(function (el) {
          var key = el.name.replace(/^.*?\]\[/, "").replace(/\[.*$/, "") + "|" + el.name.replace(/^[^\]]*\]\[[^\]]*\]/, "");
          if (!(key in values)) return;
          if (el.type === "checkbox") el.checked = values[key] === "1" || values[key] === true;
          else if (el.type === "hidden" && /\[(id|type)\]$/.test(el.name)) return;
          else el.value = values[key];
        });
      }
      return card;
    }
    function add(type, values, after) {
      var card = fromTemplate(type, values);
      if (!card) return;
      if (after && after.parentNode === list) list.insertBefore(card, after.nextElementSibling); else list.appendChild(card);
      bindCard(card);
      if (window.cmsBindWidgets) window.cmsBindWidgets(card);
      if (window.cmsInitEditor) card.querySelectorAll("textarea[data-html]").forEach(window.cmsInitEditor);
      card.querySelectorAll("[data-image-input]").forEach(function (i) { i.dispatchEvent(new Event("change")); });
      renumber(); updateTitle(card); ping();
      card.scrollIntoView({ behavior: "smooth", block: "center" });
      return card;
    }
    function duplicate(card) {
      var values = {};
      card.querySelectorAll("[name]").forEach(function (el) {
        var key = el.name.replace(/^.*?\]\[/, "").replace(/\[.*$/, "") + "|" + el.name.replace(/^[^\]]*\]\[[^\]]*\]/, "");
        if (el.type === "checkbox") { if (el.checked) values[key] = "1"; else if (!(key in values)) values[key] = "0"; }
        else values[key] = el.value;
      });
      add(card.getAttribute("data-sec-type"), values, card);
    }

    // selector de bloques
    box.querySelector("[data-add-section]").addEventListener("click", function () { picker.hidden = false; });
    picker.querySelector("[data-close]").addEventListener("click", function () { picker.hidden = true; });
    picker.addEventListener("click", function (e) { if (e.target === picker) picker.hidden = true; });
    picker.querySelectorAll("[data-block]").forEach(function (b) {
      b.addEventListener("click", function () { picker.hidden = true; var c = add(b.getAttribute("data-block")); if (c) c.classList.remove("ad-sec-collapsed"); });
    });

    list.querySelectorAll("[data-sec]").forEach(function (card) { bindCard(card); card.classList.add("ad-sec-collapsed"); });
    if (form) form.addEventListener("submit", renumber);
    renumber();

    // selección (sincronizada con la vista previa)
    function select(card, fromForm) {
      list.querySelectorAll(".ad-sec-selected").forEach(function (c) { c.classList.remove("ad-sec-selected"); });
      card.classList.add("ad-sec-selected");
      var fr = document.querySelector("[data-preview-frame]");
      if (fr && fr.contentWindow) fr.contentWindow.postMessage({ cmsHighlight: card.getAttribute("data-sec-id"), cmsScrollIntoView: !!fromForm }, "*");
    }
    window.addEventListener("message", function (e) {
      var d = e.data || {};
      if (d.cmsSec) {
        var card = list.querySelector('[data-sec][data-sec-id="' + d.cmsSec + '"]');
        if (!card) return;
        card.classList.remove("ad-sec-collapsed");
        select(card, false);
        card.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });

  /* ---------------- aprovechar el ancho: plegar menú del panel y columna de ajustes ---------------- */
  if (form) {
    document.body.classList.add("ad-has-builder");
    var panels = { nav: ["ad-nav-collapsed", document.body], side: ["ad-side-collapsed", form] };
    Object.keys(panels).forEach(function (k) {
      var cls = panels[k][0], target = panels[k][1], btn = form.querySelector('[data-toggle-panel="' + k + '"]');
      var on = false; try { on = localStorage.getItem("ad-builder-" + k) === "1"; } catch (e) {}
      if (on) { target.classList.add(cls); if (btn) btn.classList.add("on"); }
      if (btn) btn.addEventListener("click", function () {
        var now = target.classList.toggle(cls); btn.classList.toggle("on", now);
        try { localStorage.setItem("ad-builder-" + k, now ? "1" : "0"); } catch (e) {}
      });
    });
  }

  /* ---------------- vista previa en vivo ---------------- */
  if (!form) return;
  var frame = document.querySelector("[data-preview-frame]"), btn = form.querySelector("[data-preview-submit]");
  if (!frame || !btn) return;
  var lastScroll = 0, timer = null, pending = false, loading = false;
  window.addEventListener("message", function (e) {
    var d = e.data || {};
    if (typeof d.cmsScroll === "number") lastScroll = d.cmsScroll;
    if (d.cmsReady) { loading = false; frame.contentWindow.postMessage({ cmsScrollTo: lastScroll }, "*"); var sel = document.querySelector(".ad-sec-selected"); if (sel) frame.contentWindow.postMessage({ cmsHighlight: sel.getAttribute("data-sec-id") }, "*"); if (pending) { pending = false; refresh(); } }
  });
  function refresh() {
    if (loading) { pending = true; return; }
    loading = true;
    frame.classList.add("ad-preview-loading");
    form.requestSubmit(btn);
    setTimeout(function () { loading = false; frame.classList.remove("ad-preview-loading"); }, 4000);
  }
  frame.addEventListener("load", function () { frame.classList.remove("ad-preview-loading"); });
  function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 700); }
  form.addEventListener("input", schedule);
  form.addEventListener("change", schedule);
  form.addEventListener("cms:changed", schedule);
  var rb = form.querySelector("[data-preview-refresh]"); if (rb) rb.addEventListener("click", refresh);
  form.querySelectorAll("[data-device]").forEach(function (b) {
    b.addEventListener("click", function () {
      form.querySelectorAll("[data-device]").forEach(function (x) { x.classList.toggle("on", x === b); });
      frame.className = "ad-preview-frame ad-preview-" + b.getAttribute("data-device");
    });
  });
  refresh();
})();
