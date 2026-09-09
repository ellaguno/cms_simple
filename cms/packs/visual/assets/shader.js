/* cms_simple · efecto visual/shader — ondas de luz en WebGL detrás del contenido (sin librerías).
   Los ajustes de la sección (color de las ondas, fondos, velocidad, densidad, tamaño y rejilla) llegan como uniforms. */
(function () {
  "use strict";

  var VS = "attribute vec4 aVertexPosition; void main() { gl_Position = aVertexPosition; }";

  // en GLSL ES 1.0 las variables globales solo admiten inicializadores constantes: todo lo que dependa de un uniform va dentro de una función
  var FS = [
    "precision highp float;",
    "uniform vec2 iResolution;",
    "uniform float iTime;",
    "uniform float uSpeed;",     // velocidad general
    "uniform float uScale;",     // tamaño del dibujo
    "uniform float uDensity;",   // cuántas ondas se dibujan
    "uniform float uGrid;",      // 1 = dibujar la rejilla de fondo
    "uniform vec3 uLine;",       // color de las ondas
    "uniform vec3 uBg1;",        // fondo izquierda
    "uniform vec3 uBg2;",        // fondo derecha",
    "",
    "const float gridSmoothWidth = 0.015;",
    "const float axisWidth = 0.05;",
    "const float majorLineWidth = 0.025;",
    "const float minorLineWidth = 0.0125;",
    "const float majorLineFrequency = 5.0;",
    "const float minorLineFrequency = 1.0;",
    "const vec4 gridColor = vec4(0.5);",
    "const float minLineWidth = 0.01;",
    "const float maxLineWidth = 0.2;",
    "const float lineAmplitude = 1.0;",
    "const float lineFrequency = 0.2;",
    "const float warpFrequency = 0.5;",
    "const float warpAmplitude = 1.0;",
    "const float offsetFrequency = 0.5;",
    "const float minOffsetSpread = 0.6;",
    "const float maxOffsetSpread = 2.0;",
    "const int maxLines = 26;",
    "",
    "float baseSpeed() { return 0.1 * uSpeed; }",
    "",
    "#define drawCircle(pos, radius, coord) smoothstep(radius + gridSmoothWidth, radius, length(coord - (pos)))",
    "#define drawSmoothLine(pos, halfWidth, t) smoothstep(halfWidth, 0.0, abs(pos - (t)))",
    "#define drawCrispLine(pos, halfWidth, t) smoothstep(halfWidth + gridSmoothWidth, halfWidth, abs(pos - (t)))",
    "#define drawPeriodicLine(freq, width, t) drawCrispLine(freq / 2.0, width, abs(mod(t, freq) - (freq) / 2.0))",
    "",
    "float drawGridLines(float axis) {",
    "    return drawCrispLine(0.0, axisWidth, axis)",
    "        + drawPeriodicLine(majorLineFrequency, majorLineWidth, axis)",
    "        + drawPeriodicLine(minorLineFrequency, minorLineWidth, axis);",
    "}",
    "float drawGrid(vec2 space) { return min(1.0, drawGridLines(space.x) + drawGridLines(space.y)); }",
    "float random(float t) { return (cos(t) + cos(t * 1.3 + 1.3) + cos(t * 1.4 + 1.4)) / 3.0; }",
    "float getPlasmaY(float x, float horizontalFade, float offset) {",
    "    return random(x * lineFrequency + iTime * baseSpeed()) * horizontalFade * lineAmplitude + offset;",
    "}",
    "",
    "void main() {",
    "    float lineSpeed = baseSpeed();",
    "    float warpSpeed = 0.2 * baseSpeed();",
    "    float offsetSpeed = 1.33 * baseSpeed();",
    "    float density = max(2.0, uDensity);",
    "    vec4 lineColor = vec4(uLine, 1.0);",
    "",
    "    vec2 fragCoord = gl_FragCoord.xy;",
    "    vec2 uv = fragCoord.xy / iResolution.xy;",
    "    vec2 space = (fragCoord - iResolution.xy / 2.0) / iResolution.x * 2.0 * uScale;",
    "",
    "    float horizontalFade = 1.0 - (cos(uv.x * 6.28) * 0.5 + 0.5);",
    "    float verticalFade = 1.0 - (cos(uv.y * 6.28) * 0.5 + 0.5);",
    "",
    "    space.y += random(space.x * warpFrequency + iTime * warpSpeed) * warpAmplitude * (0.5 + horizontalFade);",
    "    space.x += random(space.y * warpFrequency + iTime * warpSpeed + 2.0) * warpAmplitude * horizontalFade;",
    "",
    "    vec4 lines = vec4(0.0);",
    "    for (int l = 0; l < maxLines; l++) {",
    "        if (float(l) >= density) break;",
    "        float normalizedLineIndex = float(l) / density;",
    "        float offsetTime = iTime * offsetSpeed;",
    "        float offsetPosition = float(l) + space.x * offsetFrequency;",
    "        float rand = random(offsetPosition + offsetTime) * 0.5 + 0.5;",
    "        float halfWidth = mix(minLineWidth, maxLineWidth, rand * horizontalFade) / 2.0;",
    "        float offset = random(offsetPosition + offsetTime * (1.0 + normalizedLineIndex)) * mix(minOffsetSpread, maxOffsetSpread, horizontalFade);",
    "        float linePosition = getPlasmaY(space.x, horizontalFade, offset);",
    "        float line = drawSmoothLine(linePosition, halfWidth, space.y) / 2.0 + drawCrispLine(linePosition, halfWidth * 0.15, space.y);",
    "        float circleX = mod(float(l) + iTime * lineSpeed, 25.0) - 12.0;",
    "        vec2 circlePosition = vec2(circleX, getPlasmaY(circleX, horizontalFade, offset));",
    "        line = line + drawCircle(circlePosition, 0.01, space) * 4.0;",
    "        lines += line * lineColor * rand;",
    "    }",
    "",
    "    vec4 fragColor = mix(vec4(uBg1, 1.0), vec4(uBg2, 1.0), uv.x);",
    "    if (uGrid > 0.5) fragColor += gridColor * drawGrid(space) * 0.1;",
    "    fragColor *= verticalFade;",
    "    fragColor.a = 1.0;",
    "    fragColor += lines;",
    "    gl_FragColor = fragColor;",
    "}"
  ].join("\n");

  function compile(gl, type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src); gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) { console.error("shader:", gl.getShaderInfoLog(sh)); gl.deleteShader(sh); return null; }
    return sh;
  }

  /** host es la sección: el lienzo toma su tamaño y solo se anima mientras está a la vista. */
  function initShader(canvas, host, opts) {
    if (!canvas || !host) return;
    var gl = canvas.getContext("webgl") || canvas.getContext("experimental-webgl");
    if (!gl) return;   // sin WebGL la sección se queda con su fondo normal
    var vs = compile(gl, gl.VERTEX_SHADER, VS), fs = compile(gl, gl.FRAGMENT_SHADER, FS);
    if (!vs || !fs) return;
    var prog = gl.createProgram();
    gl.attachShader(prog, vs); gl.attachShader(prog, fs); gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { console.error("shader link:", gl.getProgramInfoLog(prog)); return; }

    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    var aPos = gl.getAttribLocation(prog, "aVertexPosition"), u = {};
    ["iResolution", "iTime", "uSpeed", "uScale", "uDensity", "uGrid", "uLine", "uBg1", "uBg2"].forEach(function (n) { u[n] = gl.getUniformLocation(prog, n); });

    function resize() {
      var r = host.getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.max(1, Math.round(r.width * dpr));
      canvas.height = Math.max(1, Math.round(r.height * dpr));
      gl.viewport(0, 0, canvas.width, canvas.height);
    }
    var t; window.addEventListener("resize", function () { clearTimeout(t); t = setTimeout(resize, 150); });
    resize();

    var start = performance.now(), id = 0, running = false;
    function render() {
      gl.clearColor(0, 0, 0, 1); gl.clear(gl.COLOR_BUFFER_BIT);
      gl.useProgram(prog);
      gl.uniform2f(u.iResolution, canvas.width, canvas.height);
      gl.uniform1f(u.iTime, (performance.now() - start) / 1000);
      gl.uniform1f(u.uSpeed, opts.speed);
      gl.uniform1f(u.uScale, opts.scale);
      gl.uniform1f(u.uDensity, opts.density);
      gl.uniform1f(u.uGrid, opts.grid ? 1 : 0);
      gl.uniform3fv(u.uLine, opts.line);
      gl.uniform3fv(u.uBg1, opts.bg1);
      gl.uniform3fv(u.uBg2, opts.bg2);
      gl.bindBuffer(gl.ARRAY_BUFFER, buf);
      gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);
      gl.enableVertexAttribArray(aPos);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      if (running) id = requestAnimationFrame(render);
    }
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (es) {
        var vis = es[0].isIntersecting;
        if (vis && !running) { running = true; id = requestAnimationFrame(render); }
        else if (!vis && running) { running = false; cancelAnimationFrame(id); }
      }, { threshold: 0 }).observe(host);
    } else { running = true; id = requestAnimationFrame(render); }
    render();   // primer fotograma aunque aún no haya llegado el observador
  }

  /** "#4f46e5" → [0.31, 0.27, 0.9] */
  function rgb(hex, fallback) {
    var m = /^#?([0-9a-f]{6})$/i.exec(String(hex || ""));
    if (!m) return fallback;
    var n = parseInt(m[1], 16);
    return [((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255];
  }

  CMS.effect("visual/shader", function (sec) {
    if (CMS.reducedMotion()) return;
    var o = CMS.fx(sec, "visual/shader");
    var opts = {
      speed: CMS.fxNum(o, "speed", 2, 0.1, 8),
      scale: CMS.fxNum(o, "scale", 5, 1, 20),
      density: CMS.fxNum(o, "density", 16, 2, 26),
      grid: !!o.grid && o.grid !== "0",
      line: rgb(o.line, [0.31, 0.27, 0.9]),
      bg1: rgb(o.bg1, [0.06, 0.04, 0.18]),
      bg2: rgb(o.bg2, [0.18, 0.06, 0.30])
    };
    var canvas = document.createElement("canvas");
    canvas.className = "vis-shader"; canvas.setAttribute("aria-hidden", "true");
    if (CMS.bgLayer) CMS.bgLayer(sec, canvas); else sec.insertBefore(canvas, sec.firstChild);
    initShader(canvas, sec, opts);
  });
})();
