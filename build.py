"""Construit la page autonome d'essayage de lentilles (un seul fichier HTML, tout embarqué).

    python3 build.py            ->  dist/essayage-lentilles.html

Aucune dépendance hors bibliothèque standard de Python 3.
"""
import base64, gzip, pathlib, sys

root = pathlib.Path(__file__).parent
vendor = root / "vendor" / "mediapipe-tasks-vision-0.10.21"

# Position attendue des iris sur l'image de référence (autotest du moteur), calculée avec le moteur CPU.
REF_IRIS = "[[0.2821,0.3510],[0.4478,0.3589]]"

tpl = (root / "src" / "template.html").read_text(encoding="utf-8")
loader = (vendor / "wasm" / "vision_wasm_internal.js").read_text(encoding="utf-8")
bundle = (vendor / "vision_bundle.cjs").read_text(encoding="utf-8")
wasm = (vendor / "wasm" / "vision_wasm_internal.wasm").read_bytes()
model = (root / "vendor" / "face_landmarker.task").read_bytes()
ref = (root / "src" / "reference-autotest.jpg").read_bytes()


def safe(js):
    return js.replace("</script", "<\\/script")


def b64gz(data):
    # mtime=0 : sortie identique d'une construction à l'autre
    return base64.b64encode(gzip.compress(data, 9, mtime=0)).decode("ascii")


page = (tpl.replace("__LOADER_JS__", safe(loader))
           .replace("__REF_B64__", base64.b64encode(ref).decode("ascii"))
           .replace("__REF_IRIS__", REF_IRIS)
           .replace("__BUNDLE_JS__", safe(bundle))
           .replace("__WASM_B64__", b64gz(wasm))
           .replace("__MODEL_B64__", b64gz(model)))

standalone = ('<!doctype html><html lang="fr"><head><meta charset="utf-8">'
              '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
              '</head><body>' + page + '</body></html>')
out = root / "dist" / "essayage-lentilles.html"
out.parent.mkdir(exist_ok=True)
out.write_text(standalone, encoding="utf-8")
print("ok", out, len(standalone), "octets", file=sys.stderr)
