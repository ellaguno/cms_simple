/* Tema starter — envío del formulario de contacto a /_cms/form con respaldo mailto. */
(function () {
  var form = document.getElementById("cms-form");
  if (!form) return;
  var msg = document.getElementById("cms-form-msg");
  var btn = form.querySelector('button[type="submit"]');
  var T = { sending: form.dataset.msgSending, ok: form.dataset.msgOk, err: form.dataset.msgErr, invalid: form.dataset.msgInvalid };
  function show(type, text) { msg.className = "form-msg " + type; msg.textContent = text; }
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var data = new FormData(form);
    if (!(data.get("nombre") || "").trim() || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test((data.get("correo") || "").trim())) { show("err", T.invalid); return; }
    if (data.get("empresa_web2")) return;
    btn.disabled = true; show("ok", T.sending);
    fetch(form.action, { method: "POST", body: data, headers: { "X-Requested-With": "fetch" } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) throw new Error(j.error || r.status); return j; }); })
      .then(function () { show("ok", T.ok); form.reset(); })
      .catch(function () {
        show("err", T.err);
        if (form.dataset.mailto) { var lines = []; data.forEach(function (v, k) { if (v && k !== "empresa_web2") lines.push(k + ": " + v); }); location.href = "mailto:" + form.dataset.mailto + "?body=" + encodeURIComponent(lines.join("\n")); }
      })
      .finally(function () { btn.disabled = false; });
  });
})();
