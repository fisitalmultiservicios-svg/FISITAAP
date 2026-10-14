"""Assemble the reviewed updater from canonical sources; no private files included."""
import hashlib,json,pathlib,shutil,zipfile
from portable_zip180 import write_portable_zip
root=pathlib.Path(__file__).resolve().parents[1];release=root/'actualizaciones/1.8.0';web=release/'web';staging=pathlib.Path('/workspace/fisitaap-env/package180');folder=staging/'actualizador-180'
code_prefix='releases/1.8.0-r4'
prior_packages=[root/'descargas/1.8.0'/name for name in ['FISITAAP-1.8.0-CPANEL-R1.zip','FISITAAP-1.8.0-CPANEL-R2.zip','FISITAAP-1.8.0-CPANEL-R3.zip']]
def build():
    if folder.exists():shutil.rmtree(folder)
    folder.mkdir(parents=True);(folder/'payload').mkdir()
    selected=sorted([p for p in web.rglob('*') if p.is_file() and (p.relative_to(web).as_posix().startswith(('app/','assets/')) or p.relative_to(web).as_posix() in ['index.php','.htaccess','activar-180.php','uploads/.htaccess'])])
    files={};legacy={};baselines=list(root.glob('actualizaciones/*/web'))+list(root.glob('actualizacion-fisitaap-r*/payload'))
    for p in selected:
        original=p.relative_to(web).as_posix()
        name=original if original in ['.htaccess','uploads/.htaccess'] else code_prefix+'/'+original
        if p.is_symlink() or p.name=='config.php' or p.suffix.lower() in ['.sql','.log','.zip','.jks','.keystore']:raise RuntimeError('Private or unsupported payload: '+name)
        target=folder/'payload'/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(p,target)
        old=sorted({hashlib.sha256((base/name).read_bytes()).hexdigest() for base in baselines if base!=web and (base/name).is_file() and not(base/name).is_symlink()})
        files[name]={'new':hashlib.sha256(p.read_bytes()).hexdigest(),'old':old}
        if original.startswith(('app/','assets/')):
            legacy[original]=sorted({hashlib.sha256((base/original).read_bytes()).hexdigest() for base in baselines if base!=web and (base/original).is_file()})
    for name in ['index.php','activar-180.php']:
        entry="<?php\ndeclare(strict_types=1);\ndefine('FISITAAP_SITE_ROOT',__DIR__);\ndefine('FISITAAP_CODE_PREFIX','"+code_prefix+"');\nrequire __DIR__.'/"+code_prefix+'/'+name+"';\n"
        (folder/'payload'/name).write_text(entry)
        old={hashlib.sha256((base/name).read_bytes()).hexdigest() for base in baselines if base!=web and (base/name).is_file()}
        # Retained published archives identify the exact older entry files.
        for prior in prior_packages:
            if not prior.is_file():continue
            with zipfile.ZipFile(prior) as archive:
                entry_name='actualizador-180/payload/'+name
                if entry_name in archive.namelist():old.add(hashlib.sha256(archive.read(entry_name)).hexdigest())
        files[name]={'new':hashlib.sha256(entry.encode()).hexdigest(),'old':sorted(old)}
    # The canonical root rules also accept known previous rules, without changing
    # any shared app files while another request is still executing them.
    for prior in prior_packages:
        if not prior.is_file():continue
        with zipfile.ZipFile(prior) as archive:
            for name in legacy:
                entry_name='actualizador-180/payload/'+name
                if entry_name in archive.namelist():legacy[name]=sorted(set(legacy[name])|{hashlib.sha256(archive.read(entry_name)).hexdigest()})
            for name in ['.htaccess','uploads/.htaccess']:
                entry_name='actualizador-180/payload/'+name
                if entry_name in archive.namelist():files[name]['old']=sorted(set(files[name]['old'])|{hashlib.sha256(archive.read(entry_name)).hexdigest()})
            # Also check the versioned code currently in use before changing
            # its entry point; retain it and reject unknown custom changes.
            for entry_name in archive.namelist():
                prefix='actualizador-180/payload/'
                if not entry_name.startswith(prefix+'releases/') or entry_name.endswith('/'):continue
                name=entry_name.removeprefix(prefix)
                legacy[name]=sorted(set(legacy.get(name,[]))|{hashlib.sha256(archive.read(entry_name)).hexdigest()})
    (folder/'manifest.json').write_text(json.dumps({'version':'1.8.0','files':files},indent=2)+'\n')
    def php_value(value):
        if isinstance(value,dict):return '['+','.join(php_value(k)+'=>'+php_value(v) for k,v in value.items())+']'
        if isinstance(value,list):return '['+','.join(php_value(v) for v in value)+']'
        return "'"+str(value).replace('\\','\\\\').replace("'","\\'")+"'"
    (folder/'files.php').write_text('<?php\ndeclare(strict_types=1);\nconst UPDATE180_FILES = '+php_value(list(files))+';\nconst UPDATE180_LEGACY = '+php_value(legacy)+';\n')
    for name in ['index.php','.htaccess']:shutil.copyfile(release/'updater'/name,folder/name)
    guide=release/'GUIA-CPANEL.md'
    if guide.exists():shutil.copyfile(guide,staging/'LEEME-CPANEL.md')
    out=pathlib.Path('/workspace/entregas');out.mkdir(exist_ok=True);target=out/'FISITAAP-1.8.0-CPANEL.zip'
    write_portable_zip(staging,target)
    with zipfile.ZipFile(target) as archive:
        assert archive.testzip() is None
        for name,info in files.items():assert hashlib.sha256(archive.read('actualizador-180/payload/'+name)).hexdigest()==info['new']
    for p in staging.rglob('*'):p.chmod(0o755 if p.is_dir() else 0o644)
    shutil.copyfile(target,out/'FISITAAP-1.8.0-CPANEL-R4.zip')
    (release/'MANIFEST-ARCHIVOS.json').write_text(json.dumps({'version':'1.8.0','files':{n:i['new'] for n,i in files.items()}},indent=2)+'\n')
    print('Verified cPanel updater ZIP:',len(files),'functional files, no config, private keys or business uploads.')
    return folder
if __name__=='__main__':build()
