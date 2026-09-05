#!/usr/bin/env python3
"""Recorta las capturas a la zona con contenido, las reduce a 640 px de ancho y guarda PNG (una) o GIF (varias).
   uso: make-previews-gif.py salida.(png|gif) captura1.png [captura2.png …]"""
import sys
from PIL import Image, ImageChops

out, files = sys.argv[1], sys.argv[2:]
frames = [Image.open(f).convert("RGB") for f in files if f]
if not frames:
    sys.exit(1)

# altura útil: hasta la última fila que difiere del fondo (tomando la unión de todos los cuadros), mínimo 220 px, máximo 720
def content_height(im):
    bg = Image.new("RGB", im.size, im.getpixel((im.width - 1, im.height - 1)))
    bbox = ImageChops.difference(im, bg).getbbox()
    return bbox[3] if bbox else im.height
h = max(220, min(720, max(content_height(f) for f in frames) + 24))
frames = [f.crop((0, 0, f.width, h)) for f in frames]
w = 640
frames = [f.resize((w, int(f.height * w / f.width)), Image.LANCZOS) for f in frames]

if out.endswith(".gif"):
    pal = [f.quantize(colors=128, method=Image.MEDIANCUT, dither=Image.FLOYDSTEINBERG) for f in frames]
    pal[0].save(out, save_all=True, append_images=pal[1:], duration=[700] * (len(pal) - 1) + [1400], loop=0, optimize=True)
else:
    frames[0].save(out, optimize=True)
