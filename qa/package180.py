"""Assemble the reviewed updater from canonical sources; no private files included."""
import hashlib,json,pathlib,shutil,zipfile
from portable_zip180 import write_portable_zip
root=pathlib.Path(__file__).resolve().parents[1];release=root/'actualizaciones/1.8.0';web=release/'web';staging=pathlib.Path('/workspace/fisitaap-env/package180');folder=staging/'actualizador-180'
def build():
    if folder.exists():shutil.rmtree(folder)
    folder.mkdir(parents=True);(folder/'payload').mkdir()
    selected=sorted([p for p in web.rglob('*') if p.is_file() and (p.relative_to(web).as_posix().startswith(('app/','assets/')) or p.relative_to(web).as_posix() in ['index.php','.htaccess','activar-180.php','uploads/.htaccess'])])
    files={};baselines=list(root.glob('actualizaciones/*/web'))+list(root.glob('actualizacion-fisitaap-r*/payload'))
    for p in selected:
        name=p.relative_to(web).as_posix()
        if p.is_symlink() or p.name=='config.php' or p.suffix.lower() in ['.sql','.log','.zip','.jks','.keystore']:raise RuntimeError('Private or unsupported payload: '+name)
        target=folder/'payload'/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(p,target)
        old=sorted({hashlib.sha256((base/name).read_bytes()).hexdigest() for base in baselines if base!=web and (base/name).is_file() and not(base/name).is_symlink()})
        files[name]={'new':hashlib.sha256(p.read_bytes()).hexdigest(),'old':old}
    (folder/'manifest.json').write_text(json.dumps({'version':'1.8.0','files':files},indent=2)+'\n')
    (folder/'files.php').write_text('<?php\ndeclare(strict_types=1);\nconst UPDATE180_FILES = '+json.dumps(list(files),ensure_ascii=False).replace('[','[').replace(']',']')+';\n')
    for name in ['index.php','.htaccess']:shutil.copyfile(release/'updater'/name,folder/name)
    guide=release/'GUIA-CPANEL.md'
    if guide.exists():shutil.copyfile(guide,staging/'LEEME-CPANEL.md')
    out=pathlib.Path('/workspace/entregas');out.mkdir(exist_ok=True);target=out/'FISITAAP-1.8.0-CPANEL.zip'
    write_portable_zip(staging,target)
    with zipfile.ZipFile(target) as archive:
        assert archive.testzip() is None
        for name,info in files.items():assert hashlib.sha256(archive.read('actualizador-180/payload/'+name)).hexdigest()==info['new']
    for p in staging.rglob('*'):p.chmod(0o755 if p.is_dir() else 0o644)
    shutil.copyfile(target,out/'FISITAAP-1.8.0-CPANEL-R1.zip')
    (release/'MANIFEST-ARCHIVOS.json').write_text(json.dumps({'version':'1.8.0','files':{n:i['new'] for n,i in files.items()}},indent=2)+'\n')
    print('Verified cPanel updater ZIP:',len(files),'functional files, no config, private keys or business uploads.')
    return folder
if __name__=='__main__':build()
