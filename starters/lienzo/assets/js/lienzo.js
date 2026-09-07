/* Lienzo — menú móvil, cabecera fija y formulario de contacto */
(function () {
  "use strict";
  if (window.AOS) AOS.init({ duration: 600, once: true, offset: 60 });
  var t = document.querySelector("[data-lz-toggle]"), n = document.querySelector("[data-lz-nav]");
  if (t && n) t.addEventListener("click", function () { n.classList.toggle("is-open"); t.classList.toggle("is-open"); });
  var f = document.querySelector("[data-lz-form]");
  if (f) f.addEventListener("submit", function (ev) {
    ev.preventDefault();
    var msg = f.querySelector("[data-lz-form-msg]"), btn = f.querySelector("button[type=submit]");
    btn.disabled = true;
    fetch(f.action, { method: "POST", body: new FormData(f), headers: { "X-Requested-With": "fetch" } }).then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); }).then(function (j) {
      msg.hidden = false; msg.textContent = j.ok ? (j.message || "Gracias, recibimos tu mensaje.") : (j.error || j.message || "No se pudo enviar. Inténtalo de nuevo.");
      if (j.ok) f.reset();
    }).catch(function () { msg.hidden = false; msg.textContent = "No se pudo enviar. Inténtalo de nuevo."; }).finally(function () { btn.disabled = false; });
  });
})();
