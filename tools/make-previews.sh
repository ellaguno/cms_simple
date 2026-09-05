#!/bin/bash
# Captura las vistas previas de bloques y efectos con Chrome sin interfaz.
#   tools/make-previews.sh [http://127.0.0.1:8099] [--solo-tema | --solo=clave]   (CMS_ROOT=/otro/sitio para generar las de otro sitio)
# Requiere: google-chrome, python3 con Pillow, y el servidor de desarrollo corriendo.
set -u
BASE=${1:-http://127.0.0.1:8099}
EXTRA=${2:-}
TOOLS=$(cd "$(dirname "$0")" && pwd)
ROOT=${CMS_ROOT:-$(cd "$TOOLS/.." && pwd)}
export CMS_ROOT="$ROOT"
CHROME=${CHROME:-/usr/bin/google-chrome}
TMP=$(mktemp -d)
W=1200; H=720
cd "$ROOT"
php "$TOOLS/make-previews.php" list $EXTRA > "$TMP/list.tsv"
echo "$(wc -l < "$TMP/list.tsv") vistas previas por generar"

capture() { # $1 url  $2 salida.png  $3 presupuesto de tiempo virtual (ms)
  timeout 90 "$CHROME" --headless=new --disable-gpu --no-sandbox --hide-scrollbars --window-size=$W,$H --virtual-time-budget="$3" --screenshot="$2" "$1" >/dev/null 2>&1
}
export -f capture; export CHROME W H

one() { # línea del tsv
  IFS=$'\t' read -r key path animated target <<< "$1"
  url="$BASE$path?cmsbare=1"
  mkdir -p "$(dirname "$target")"
  d=$(mktemp -d)
  if [ "$animated" = "1" ]; then
    for t in 1200 1900 2600 3300 4000 4700; do capture "$url" "$d/f$t.png" "$t"; done
    python3 "$TOOLS/make-previews-gif.py" "$target.gif" "$d"/f*.png && rm -f "$target.png"
  else
    capture "$url" "$d/f.png" 2500
    python3 "$TOOLS/make-previews-gif.py" "$target.png" "$d/f.png" && rm -f "$target.gif"
  fi
  rm -rf "$d"
  echo "✓ $key → $(basename "$target")"
}
export -f one; export BASE ROOT TOOLS

# 3 capturas en paralelo
cat "$TMP/list.tsv" | xargs -P 3 -I{} bash -c 'one "$@"' _ {}
php "$TOOLS/make-previews.php" clean
rm -rf "$TMP"
echo "listo"
