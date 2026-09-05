#!/usr/bin/env python3
"""
Importador de diseños (experimento): PDF o imagen de una página → borrador de página del constructor.

  python3 tools/import-design.py diseño.pdf --slug import-prueba --model sonnet
  python3 tools/import-design.py captura.png --slug import-prueba --model opus --compare data/content/paginas/inicio.json
  python3 tools/import-design.py diseño.pdf --slug x --model haiku --dry        # no escribe en data/, solo el reporte

Etapas:
  1. Extracción: rasteriza el PDF (pdftoppm), apila las páginas y corta "pantallas" de ~1100 px con solape;
     extrae la capa de texto (pdftotext) para que el copy salga exacto, con acentos y sin OCR.
  2. Mapeo: manda las pantallas + texto + catálogo de bloques (cms/lib/import.php vía tools/blocks-schema.php) al
     modelo con salida estructurada (JSON Schema), a través de la CLI de Claude Code (`claude -p --json-schema`),
     que usa la sesión de este equipo. El panel (Importar diseño) hace lo mismo con OpenRouter o con esta CLI.
  3. Materialización: escribe data/content/paginas/<slug>.json como borrador, con campos bilingües envueltos
     como {idioma: valor}, y un reporte (notas por sección, partes sin mapear, paleta, costo) en la carpeta de trabajo.

Requisitos: poppler-utils (pdftoppm, pdftotext), Pillow, la CLI `claude` con sesión iniciada.
"""
import argparse, json, os, re, shutil, subprocess, sys, time, random, difflib
from pathlib import Path
from PIL import Image

ROOT = Path(os.environ.get('CMS_ROOT') or Path(__file__).resolve().parent.parent)
SCREEN_W, SCREEN_H, OVERLAP = 1400, 1100, 120
MODELS = {'haiku': 'haiku', 'sonnet': 'sonnet', 'opus': 'opus', 'fable': 'fable'}


def run(cmd, **kw):
    r = subprocess.run(cmd, capture_output=True, text=True, **kw)
    if r.returncode != 0:
        sys.exit(f"error en {cmd[0]}: {r.stderr.strip()[:800]}")
    return r.stdout


# ---------- 1. Extracción -----------------------------------------------------------------------
def rasterize(src: Path, work: Path):
    """Devuelve (imagen apilada, texto). PDF → páginas a ~1400 px de ancho; imagen → tal cual."""
    pages, text = [], ''
    if src.suffix.lower() == '.pdf':
        info = run(['pdfinfo', str(src)])
        m = re.search(r'Page size:\s+([\d.]+) x ([\d.]+)', info)
        wpt = float(m.group(1)) if m else 612.0
        dpi = max(72, min(300, round(SCREEN_W / wpt * 72)))
        run(['pdftoppm', '-r', str(dpi), '-png', str(src), str(work / 'page')])
        pages = sorted(work.glob('page-*.png'))
        text = run(['pdftotext', '-layout', str(src), '-'])
    else:
        pages = [src]
    ims = [Image.open(p).convert('RGB') for p in pages]
    if not ims:
        sys.exit('no se pudo rasterizar')
    w = SCREEN_W
    ims = [im.resize((w, round(im.height * w / im.width))) if im.width != w else im for im in ims]
    stack = Image.new('RGB', (w, sum(im.height for im in ims)), 'white')
    y = 0
    for im in ims:
        stack.paste(im, (0, y)); y += im.height
    stack = trim_bottom(stack)
    for p in pages:
        if p.parent == work: p.unlink()
    return stack, text


def trim_bottom(im: Image.Image) -> Image.Image:
    """Recorta las filas finales de color uniforme (capturas más altas que la página)."""
    small = im.resize((max(1, im.width // 8), max(1, im.height // 8)))
    px = small.load(); w, h = small.size
    last = h - 1
    while last > 0:
        row = {px[x, last] for x in range(0, w, 4)}
        if len(row) > 2: break
        last -= 1
    cut = min(im.height, (last + 2) * 8 + 40)
    return im.crop((0, 0, im.width, cut)) if cut < im.height - 100 else im


def tile(stack: Image.Image, work: Path):
    """Corta la imagen apilada en pantallas de SCREEN_H con solape; devuelve las rutas."""
    shots, y, n = [], 0, 1
    while y < stack.height:
        box = (0, y, stack.width, min(stack.height, y + SCREEN_H))
        p = work / f'pantalla-{n:02d}.png'
        stack.crop(box).save(p, optimize=True)
        shots.append(p)
        if box[3] >= stack.height: break
        y += SCREEN_H - OVERLAP; n += 1
    return shots


def clean_text(t: str) -> str:
    lines = [re.sub(r'[ \t]+', ' ', l).strip() for l in t.splitlines()]
    out, prev = [], None
    for l in lines:
        if l == '' and prev == '': continue
        out.append(l); prev = l
    return '\n'.join(out).strip()


# ---------- 2. Mapeo -----------------------------------------------------------------------------
def build_prompt(cat: dict, text: str) -> str:
    """El prompt lo arma cms/lib/import.php (mismo texto que usa el panel); aquí solo se inserta la capa de texto."""
    return cat['prompt'].replace('__TEXTO__', text if text else '(sin capa de texto: lee el texto de las imágenes)')


def call_claude(prompt: str, schema: dict, model: str, work: Path, effort: str | None):
    cmd = ['claude', '-p', '--model', MODELS.get(model, model), '--output-format', 'json',
           '--allowedTools', 'Read', '--add-dir', str(work), '--json-schema', json.dumps(schema, ensure_ascii=False)]
    if effort: cmd += ['--effort', effort]
    t0 = time.time()
    r = subprocess.run(cmd, input=prompt, capture_output=True, text=True, cwd=str(work))
    dt = time.time() - t0
    if r.returncode != 0:
        sys.exit(f"claude -p falló ({r.returncode}): {r.stderr.strip()[:1500]}\n{r.stdout[:800]}")
    try:
        out = json.loads(r.stdout)
    except json.JSONDecodeError:
        sys.exit('respuesta no JSON:\n' + r.stdout[:1500])
    if out.get('is_error'):
        sys.exit('error del modelo: ' + str(out.get('result'))[:1500])
    so = out.get('structured_output')
    if so is None:
        # algunas versiones devuelven el JSON como texto en result
        try: so = json.loads(out.get('result') or '')
        except Exception: sys.exit('sin structured_output:\n' + json.dumps(out, ensure_ascii=False)[:1500])
    usage = out.get('usage') or {}
    return so, {'model': model, 'cost_usd': out.get('total_cost_usd'), 'seconds': round(dt, 1), 'turns': out.get('num_turns'),
                'input_tokens': usage.get('input_tokens'), 'output_tokens': usage.get('output_tokens'),
                'cache_read': usage.get('cache_read_input_tokens'), 'cache_creation': usage.get('cache_creation_input_tokens'),
                'model_usage': out.get('modelUsage')}


# ---------- 3. Materialización -------------------------------------------------------------------
def sid(): return '%06x' % random.randrange(16 ** 6)


def materialize(result: dict, meta: dict, slug: str, lang: str, brand: str, src: Path) -> tuple[dict, list]:
    lang = (result.get('lang') or lang or 'es')[:2]
    notes, sections = [], []
    for s in result.get('sections', []):
        t = s.get('type', '')
        m = meta.get(t)
        if not m:
            notes.append(f"- bloque desconocido «{t}» descartado"); continue
        data = {}
        for k, v in (s.get('data') or {}).items():
            if v in ('', None, []): continue
            if k in m['lines'] and isinstance(v, str): v = [x for x in v.split('\n') if x.strip()]
            data[k] = {lang: v} if k in m['i18n'] else v
        style = {k: v for k, v in (s.get('style') or {}).items() if v not in ('', None, False)}
        sections.append({'id': sid(), 'type': t, 'data': data, 'style': style, 'hidden': False})
        if s.get('note'): notes.append(f"- [{t}] pantalla {s.get('screen')}: {s['note']}")
    today = time.strftime('%Y-%m-%d')
    page = {
        'slug': slug, 'status': 'draft',
        'title': {lang: result.get('title') or slug},
        'sections': sections,
        'summary': {lang: result.get('summary') or ''},
        'brand': brand, 'image': '', 'order': 99, 'parent': '', 'path': slug,
        'seo_title': {lang: ''}, 'seo_desc': {lang: ''},
        'created': today, 'updated': today,
        'import': {'source': src.name, 'date': today, 'palette': result.get('palette'), 'fonts': result.get('fonts'), 'unmapped': result.get('unmapped')},
    }
    return page, notes


def compare(page: dict, ref_path: Path, lang: str) -> str:
    ref = json.loads(ref_path.read_text())
    a = [s['type'] for s in ref.get('sections', []) if not s.get('hidden')]
    b = [s['type'] for s in page['sections']]
    sm = difflib.SequenceMatcher(a=a, b=b)
    lines = [f"Referencia: {' > '.join(a)}", f"Importado:  {' > '.join(b)}",
             f"Coincidencia de secuencia de tipos: {sm.ratio():.0%} ({len(a)} vs {len(b)} secciones)"]
    def loc(v):
        if isinstance(v, dict): v = v.get(lang) or v.get('es') or ''
        return re.sub(r'<[^>]+>', '', str(v)).strip().lower()
    hits = tot = 0
    for op, i1, i2, j1, j2 in sm.get_opcodes():
        if op != 'equal': continue
        for i, j in zip(range(i1, i2), range(j1, j2)):
            ra, rb = ref['sections'][i]['data'], page['sections'][j]['data']
            for k in ('title', 'subtitle', 'text', 'quote'):
                if k in ra and loc(ra[k]):
                    tot += 1
                    if loc(ra[k]) == loc(rb.get(k, '')): hits += 1
    if tot: lines.append(f"Títulos/subtítulos exactos en secciones emparejadas: {hits}/{tot}")
    return '\n'.join(lines)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('source', help='PDF o imagen (png/jpg) del diseño')
    ap.add_argument('--slug', required=True, help='slug de la página a crear (borrador)')
    ap.add_argument('--model', default='sonnet', help='haiku | sonnet | opus | fable | nombre completo')
    ap.add_argument('--effort', default=None, help='low | medium | high | xhigh | max')
    ap.add_argument('--lang', default='es'); ap.add_argument('--brand', default='teams')
    ap.add_argument('--compare', help='JSON de referencia para medir (data/content/paginas/inicio.json)')
    ap.add_argument('--work', help='carpeta de trabajo (por defecto: junto al archivo, <nombre>-import/)')
    ap.add_argument('--dry', action='store_true', help='no escribir en data/content')
    a = ap.parse_args()

    src = Path(a.source).resolve()
    work = Path(a.work) if a.work else src.parent / f'{src.stem}-import-{a.model}'
    if work.exists(): shutil.rmtree(work)
    work.mkdir(parents=True)

    print(f"[1/3] extracción de {src.name}…", flush=True)
    stack, text = rasterize(src, work)
    shots = tile(stack, work)
    text = clean_text(text)
    (work / 'texto.txt').write_text(text)
    print(f"      {len(shots)} pantallas de {SCREEN_W}x{SCREEN_H}, {len(text)} caracteres de texto", flush=True)

    print(f"[2/3] mapeo con {a.model}…", flush=True)
    cat = json.loads(subprocess.run(['php', str(ROOT / 'tools/blocks-schema.php'), str(len(shots))] + [str(p) for p in shots], capture_output=True, text=True).stdout)
    prompt = build_prompt(cat, text)
    (work / 'prompt.txt').write_text(prompt)
    result, stats = call_claude(prompt, cat['schema'], a.model, work, a.effort)
    (work / 'respuesta.json').write_text(json.dumps(result, ensure_ascii=False, indent=2))
    print(f"      {len(result.get('sections', []))} secciones, {stats['seconds']} s, costo {stats['cost_usd']}", flush=True)

    print("[3/3] materialización…", flush=True)
    page, notes = materialize(result, cat['meta'], a.slug, a.lang, a.brand, src)
    dest = ROOT / 'data/content/paginas' / f'{a.slug}.json'
    if not a.dry:
        dest.write_text(json.dumps(page, ensure_ascii=False, indent=2))
        print(f"      borrador escrito: {dest.relative_to(ROOT)}  (vista previa en el panel → Páginas → {a.slug})")
    else:
        (work / 'pagina.json').write_text(json.dumps(page, ensure_ascii=False, indent=2))

    rep = [f"# Importación de {src.name} con {a.model}", '',
           f"Secciones: {' > '.join(s['type'] for s in page['sections'])}", '',
           f"Tiempo {stats['seconds']} s · costo {stats['cost_usd']} USD · turnos {stats['turns']} · tokens entrada {stats['input_tokens']} (caché {stats['cache_read']}) · salida {stats['output_tokens']}", '',
           f"Idioma: {result.get('lang')} · paleta: {result.get('palette')} · fuentes: {result.get('fonts')}", '',
           '## Notas por sección', *(notes or ['(ninguna)']), '',
           '## Sin mapear', *([f'- {u}' for u in result.get('unmapped', [])] or ['(nada)']), '']
    if a.compare:
        rep += ['## Comparación con la referencia', compare(page, Path(a.compare), a.lang), '']
    (work / 'reporte.md').write_text('\n'.join(rep))
    print('\n'.join(rep))
    print(f"carpeta de trabajo: {work}")


if __name__ == '__main__':
    main()
