"""Build the requested Línea + current FISITAAP exploration, independently of production."""
import hashlib,json,pathlib,shutil,stat,zipfile

ROOT=pathlib.Path(__file__).resolve().parents[2]
SOURCE=pathlib.Path(__file__).parent
BASE=ROOT/'disenos/ux-2026-10-27'
OUT=ROOT/'descargas/linea-fisitaap'

def build():
    OUT.mkdir(exist_ok=True)
    original=(ROOT/'descargas/disenos-ux/01-linea.html').read_text()
    config_start=original.index('<script type="application/json" id="uxConfig">')
    config_start=original.index('>',config_start)+1
    config_end=original.index('</script>',config_start)
    config=json.loads(original[config_start:config_end])
    config.update(design='fusion',name='Línea · FISITAAP',file='index.html',color='#1d7550',canvas='#f3f6f9',description='La claridad de Línea con el azul profundo, el verde y los detalles actuales de FISITAAP. Facturación amplia a la izquierda y mesas en plano gráfico.')
    css=(BASE/'styles.css').read_text()+'\n'+(SOURCE/'hybrid.css').read_text()
    js=(BASE/'app.js').read_text()
    marker="shell();$('#navigation').inert=true;"
    assert js.count(marker)==1
    js=js.replace(marker,(SOURCE/'hybrid.js').read_text()+'\n'+marker+"\nif(['#pos','#tables','#catalog'].includes(location.hash)){if(location.hash==='#catalog')mode('catalog');else go(location.hash.slice(1));}")
    assert '</script' not in js.lower()
    html='<!doctype html>\n<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="description" content="Exploración UX: Línea y la identidad de FISITAAP, con facturación a la izquierda y plano gráfico de mesas."><title>FISITAAP · Línea, con nuestra identidad</title><style>'+css+'</style></head><body data-design="fusion"><div id="app"></div><div id="navShade" hidden></div><aside id="navigation" aria-label="Menú del espacio" aria-hidden="true" inert></aside><dialog id="modal"></dialog><div id="chatHost"></div><div id="toasts" aria-live="polite"></div><script type="application/json" id="uxConfig">'+json.dumps(config,ensure_ascii=False).replace('</','<\\/')+'</script><script>'+js+'</script></body></html>\n'
    (OUT/'index.html').write_text(html)
    for name in ['LEEME.md','QA.json']:
        if (SOURCE/name).exists():shutil.copyfile(SOURCE/name,OUT/name)
    files=[OUT/'index.html']+[OUT/name for name in ['LEEME.md','README.md','QA.json'] if (OUT/name).exists()]+sorted(OUT.glob('*.png'))
    (OUT/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+p.name+'\n' for p in files))
    files.append(OUT/'SHA256SUMS.txt')
    archive=OUT/'FISITAAP-LINEA-MEZCLA-UX-2026-10-27.zip'
    with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for p in files:
            info=zipfile.ZipInfo(p.name,(2026,10,27,0,0,0));info.create_system=3;info.external_attr=(stat.S_IFREG|0o644)<<16;info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,p.read_bytes(),compresslevel=9)
    with zipfile.ZipFile(archive) as z:assert z.testzip() is None
    print(json.dumps({'archive_bytes':archive.stat().st_size,'sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'html':'descargas/linea-fisitaap/index.html','originals_unchanged':True}))

if __name__=='__main__':build()
