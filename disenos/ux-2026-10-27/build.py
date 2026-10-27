"""Build three self-contained HTML prototypes; application/official-site files are not changed."""
import base64,hashlib,json,pathlib,shutil,stat,zipfile
ROOT=pathlib.Path(__file__).resolve().parents[2]
SOURCE=pathlib.Path(__file__).parent
OUT=ROOT/'descargas/disenos-ux'
DESIGNS=[
 {'design':'linea','name':'Línea','file':'01-linea.html','description':'Clara, sobria y precisa. Superficies blancas, líneas finas y acentos en petróleo.','color':'#246b6b','canvas':'#f4f7f8'},
 {'design':'estudio','name':'Estudio','file':'02-estudio.html','description':'Cálida y editorial. Tonos marfil, tipografía con carácter y una composición más abierta.','color':'#865c42','canvas':'#f4f1eb'},
 {'design':'noche','name':'Noche','file':'03-noche.html','description':'Compacta y enfocada. Grafito, contraste cuidado y acentos suaves para la operación diaria.','color':'#c4dbad','canvas':'#141b21'}]
def asset(name):
 p=ROOT/'actualizaciones/1.8.0/web/assets'/name
 return 'data:'+('image/svg+xml' if p.suffix=='.svg' else 'image/webp')+';base64,'+base64.b64encode(p.read_bytes()).decode()
def build():
 OUT.mkdir(exist_ok=True)
 images={key:asset(value) for key,value in {'logo':'fisitapp-logo.webp','coffee':'demo/coffee.webp','burger':'demo/burger.webp','tacos':'demo/tacos.webp','pizza':'demo/pizza.webp','bottle':'demo/bottle.webp','shirt':'demo/shirt.webp','slide-food':'demo/slide-food.webp','slide-lifestyle':'demo/slide-lifestyle.webp'}.items()}
 css=(SOURCE/'styles.css').read_text();js=(SOURCE/'app.js').read_text();assert '</script' not in js.lower()
 for design in DESIGNS:
  cfg={**design,'assets':images}
  html='<!doctype html>\n<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="'+('dark' if design['design']=='noche' else 'light')+'"><meta name="description" content="Propuesta interactiva de interfaz FISITAAP · '+design['name']+'"><title>FISITAAP · '+design['name']+'</title><style>'+css+'</style></head><body data-design="'+design['design']+'"><div id="app"></div><div id="navShade" hidden></div><aside id="navigation" aria-label="Menú del espacio" aria-hidden="true" inert></aside><dialog id="modal"></dialog><div id="chatHost"></div><div id="toasts" aria-live="polite"></div><script type="application/json" id="uxConfig">'+json.dumps(cfg,ensure_ascii=False).replace('</','<\\/')+'</script><script>'+js+'</script></body></html>\n'
  (OUT/design['file']).write_text(html)
 cards=[]
 for i,d in enumerate(DESIGNS,1):
  preview=OUT/('vista-'+d['design']+'.png')
  src='data:image/png;base64,'+base64.b64encode(preview.read_bytes()).decode() if preview.exists() else ''
  visual='<img src="'+src+'" alt="Vista del panel '+d['name']+'">' if src else '<div class="sample" style="background:'+d['canvas']+';color:'+d['color']+'"><span>LA VENTANITA</span><h3>Tu negocio,<br>en perspectiva.</h3><div class="tiles"><i></i><i></i><i></i></div></div>'
  cards.append('<article><a class="visual" href="'+d['file']+'">'+visual+'</a><div class="body"><span class="number">0'+str(i)+'</span><h2>'+d['name']+'</h2><p>'+d['description']+'</p><a class="button" href="'+d['file']+'">Explorar '+d['name']+' <span>→</span></a></div></article>')
 index='''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FISITAAP · Tres maneras de sentir tu negocio</title><style>*{box-sizing:border-box}body{font:14px/1.6 Inter,"Segoe UI",Arial,sans-serif;background:#f5f6f7;color:#293a43;margin:0;padding:34px 40px}header{max-width:1400px;margin:auto;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e0e6e9;padding-bottom:24px}header img{width:140px;height:auto}header small{font-size:10px;letter-spacing:1px;color:#7c8a93}.intro{max-width:1400px;margin:48px auto 32px}.intro small{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#7c8a93}.intro h1{font-size:36px;font-weight:500;line-height:1.2;letter-spacing:-1px;margin:12px 0 15px}.intro p{font-size:13px;max-width:690px;color:#74818a}.options{max-width:1400px;margin:auto;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}article{border:1px solid #e0e6e9;background:white;min-width:0;border-radius:5px;overflow:hidden}.visual{display:block;aspect-ratio:1.49;background:#edf1f3;overflow:hidden}.visual img{width:100%;height:100%;object-fit:cover;object-position:top}.sample{height:100%;padding:25px}.sample>span{font-size:9px;letter-spacing:1.4px}.sample h3{font-size:25px;font-weight:500;line-height:1.3}.tiles{display:flex;gap:10px}.tiles i{height:40px;width:30%;border:1px solid currentColor;opacity:.3}.body{padding:24px}.number{font-size:10px;color:#8b969d}.body h2{font-size:26px;font-weight:500;letter-spacing:-.5px;margin:5px 0 10px}.body p{font-size:12px;color:#7b868e;min-height:60px}.button{display:flex;align-items:center;justify-content:space-between;border-top:1px solid #e2e8ec;padding:16px 0 0;text-decoration:none;color:#2b5557;font-size:12px;font-weight:600}.notes{max-width:1400px;margin:35px auto 0;display:grid;grid-template-columns:1fr 1fr;gap:30px;border-top:1px solid #e0e6e9;padding-top:22px}.notes p{color:#7b878f;font-size:11px}.notes b{color:#3d4f5b;font-weight:550}.notes code{background:#e8edf0;padding:2px 5px;border-radius:2px;font-size:10px}@media(max-width:900px){body{padding:25px}.options{gap:15px}.body{padding:18px}.intro h1{font-size:30px}}@media(max-width:650px){body{padding:22px 16px}.intro{margin-top:35px}.intro h1{font-size:30px}.options{grid-template-columns:1fr;gap:22px}.body p{min-height:0}.notes{grid-template-columns:1fr;gap:8px}header img{width:120px}header small{font-size:8px}}</style></head><body><header><img src="'''+images['logo']+'''" alt="FISITAAP"><small>EXPLORACIÓN DE INTERFAZ · OCTUBRE 2026</small></header><section class="intro"><small>Un mismo sistema. Tres personalidades.</small><h1>Elige cómo quieres<br>sentir tu negocio.</h1><p>Tres propuestas modernas, ligeras y adaptables. Cada una permite explorar el negocio, el panel maestro, el catálogo y la experiencia del motorizado con los mismos recorridos y datos de ejemplo.</p></section><main class="options">'''+''.join(cards)+'''</main><footer class="notes"><p><b>Empieza por una venta.</b> Abre Venta rápida, agrega un producto y confirma el cobro. Después crea un pedido en Catálogo y sigue su recorrido desde Negocio y Moto.</p><p><b>Tu exploración se conserva en este navegador.</b> Cada propuesta tiene datos independientes. Puedes restablecerla desde su menú. La web de presentación de FISITAAP permanece igual.</p></footer></body></html>'''
 (OUT/'index.html').write_text(index)
 for name in ['LEEME.md','QA.json']:
  if (SOURCE/name).exists():shutil.copyfile(SOURCE/name,OUT/name)
 files=[OUT/'index.html',*[OUT/d['file'] for d in DESIGNS]]
 files += [OUT/name for name in ['LEEME.md','README.md','QA.json'] if (OUT/name).exists()]
 files += [OUT/(prefix+d['design']+'.png') for prefix in ['vista-','movil-'] for d in DESIGNS if (OUT/(prefix+d['design']+'.png')).exists()]
 sums=''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+p.name+'\n' for p in files);(OUT/'SHA256SUMS.txt').write_text(sums);files.append(OUT/'SHA256SUMS.txt')
 archive=OUT/'FISITAAP-TRES-DISENOS-UX-2026-10-27.zip'
 with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for p in files:
   info=zipfile.ZipInfo(p.name,(2026,10,27,0,0,0));info.create_system=3;info.external_attr=(stat.S_IFREG|0o644)<<16;info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,p.read_bytes(),compresslevel=9)
 with zipfile.ZipFile(archive) as z:assert z.testzip() is None
 print(json.dumps({'html':[d['file'] for d in DESIGNS],'archive_bytes':archive.stat().st_size,'archive_sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'self_contained':True},ensure_ascii=False))
if __name__=='__main__':build()
