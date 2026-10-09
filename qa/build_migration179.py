"""Rebuild clean cPanel ZIPs from reviewed source and the committed schema manifest.

Run from any directory with Python 3. No database, credentials or services needed.
Deployment code lives in actualizaciones/mudanza-1.7.9-r3/web, not the legacy root.
"""
import hashlib,json,pathlib,re,zipfile

ROOT=pathlib.Path(__file__).resolve().parents[1]
DELIVERY=ROOT/'actualizaciones/mudanza-1.7.9-r3'
WEB=DELIVERY/'web'
MANIFEST=WEB/'mudanza-manifest.php'

def build():
    text=MANIFEST.read_text()
    data=json.loads(text.split("<<<'MIGRATION_MANIFEST'\n",1)[1].split('\nMIGRATION_MANIFEST',1)[0])
    files=sorted(p for p in WEB.rglob('*') if p.is_file())
    for p in files:
        relative=p.relative_to(WEB).as_posix()
        if p.is_symlink() or p.name.lower()=='config.php' or p.name.startswith('.env') or re.search(r'\.(?:sql|zip|log|bak)(?:\.(?:gz|bz2|xz))?$',p.name,re.I):
            raise RuntimeError('Private or unsupported file in deployment source: '+relative)
        if relative.startswith('uploads/') and relative!='uploads/.htaccess':
            raise RuntimeError('Uploaded business files must never enter the public deployment ZIP.')
    data['version']='1.7.9-R3 · mudanza revisada e imágenes optimizadas'
    data['files']={p.relative_to(WEB).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in files if p!=MANIFEST}
    encoded=json.dumps(data,ensure_ascii=False,separators=(',',':'))
    MANIFEST.write_text("<?php\ndeclare(strict_types=1);\nreturn json_decode(<<<'MIGRATION_MANIFEST'\n"+encoded+"\nMIGRATION_MANIFEST\n, true, 512, JSON_THROW_ON_ERROR);\n")
    for p in WEB.rglob('*'):p.chmod(0o755 if p.is_dir() else 0o644)
    names=['FISITAAP-1.7.9-R3-NUEVO-CPANEL.zip','FISITAAP-COMPROBAR-MUDANZA.zip']
    for name in names:
        target=DELIVERY/name;temp=target.with_suffix('.zip.tmp')
        try:
            with zipfile.ZipFile(temp,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
                selected=files if 'NUEVO-CPANEL' in name else [WEB/'comprobar-mudanza.php',MANIFEST]
                for p in selected:archive.write(p,p.relative_to(WEB).as_posix())
                if 'NUEVO-CPANEL' in name:archive.write(DELIVERY/'GUIA-NUEVO-CPANEL.md','LEEME-MUDANZA.md')
            temp.replace(target)
        finally:
            if temp.exists():temp.unlink()
    (DELIVERY/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256((DELIVERY/name).read_bytes()).hexdigest()+'  '+name+'\n' for name in names))
    print('Reviewed deployment ZIPs and SHA256 checksums rebuilt without private data.')

if __name__=='__main__':build()
