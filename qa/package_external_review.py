"""Bundle the reviewed source tree, never the deployed host or local private files."""
import hashlib,json,pathlib,re,stat,subprocess,zipfile

ROOT=pathlib.Path(__file__).resolve().parents[1]
SOURCE='29d5a9e11ee67239b37617096f23fd94b7f98eca'
AUDIT=ROOT/'auditoria/2026-10-27-r13'
DEST=ROOT/'descargas/auditoria/FISITAAP-CODIGO-COMPLETO-QA-R13-2026-10-27.zip'
PREFIXES=('actualizaciones/1.8.0/','desktop/','android/','qa/')
EXTRA={'README.md','.gitignore','.gitattributes','actualizacion-fisitaap-r5/migration-r1.sql','descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R13.zip'}
PATTERNS=[rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',rb'\bAKIA[0-9A-Z]{16}\b',rb'\bgh[pousr]_[A-Za-z0-9]{30,}\b',rb'\bsk-(?:proj-)?[A-Za-z0-9_-]{35,}\b']

def digest(data):return hashlib.sha256(data).hexdigest()
def safe(name,data):
    p=pathlib.PurePosixPath(name)
    assert not p.is_absolute() and '..' not in p.parts
    assert not any(part in {'.git','node_modules','.gradle','dist','build','__pycache__'} for part in p.parts),name
    assert p.name not in {'config.php','.env','local.properties','credentials.json','fixture.json'},name
    assert p.suffix.lower() not in {'.jks','.keystore','.key','.pem','.log','.bak','.exe','.apk'},name
    if 'uploads' in p.parts:assert p.name=='.htaccess',name
    assert not data.startswith(b'version https://git-lfs.github.com/spec/v1\n'),name
    if p.suffix in {'.php','.py','.js','.json','.md','.txt','.sql','.xml','.properties','.gradle'}:
        for pattern in PATTERNS:assert not re.search(pattern,data),f'Secret-shaped content in {name}; contents were not printed'
    if p.suffix=='.sql':
        assert not re.search(rb'^\s*(?:INSERT|REPLACE|UPDATE|DELETE|LOAD DATA)\b',data,re.M|re.I),name

def main():
    tree=subprocess.check_output(['git','ls-tree','-r','-z',SOURCE],cwd=ROOT).split(b'\0')
    files={};provenance={}
    for row in tree:
        if not row:continue
        meta,path=row.split(b'\t',1);mode,kind,oid=meta.decode().split();name=path.decode()
        if name not in EXTRA and not name.startswith(PREFIXES):continue
        assert kind=='blob' and mode in {'100644','100755'},name
        data=subprocess.check_output(['git','cat-file','blob',oid],cwd=ROOT);safe(name,data)
        files[name]=data;provenance[name]={'kind':'git-source','commit':SOURCE,'path':name,'git_blob':oid,'bytes':len(data),'sha256':digest(data)}
    for path in sorted(AUDIT.iterdir()):
        if not path.is_file():continue
        name=path.relative_to(ROOT).as_posix();data=path.read_bytes();safe(name,data)
        files[name]=data;provenance[name]={'kind':'audit-document','path':name,'bytes':len(data),'sha256':digest(data)}
    files['LEEME-QA.md']=(AUDIT/'LEEME-QA.md').read_bytes();provenance['LEEME-QA.md']={'kind':'audit-document','path':'auditoria/2026-10-27-r13/LEEME-QA.md','bytes':len(files['LEEME-QA.md']),'sha256':digest(files['LEEME-QA.md'])}
    web_prefix='actualizaciones/1.8.0/web/'
    with zipfile.ZipFile(ROOT/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R13.zip') as release:
        manifest=json.loads(release.read('actualizador-180/manifest.json'))
        for name,info in manifest['files'].items():
            original=name.removeprefix('releases/1.8.0-r13/')
            if name in {'index.php','activar-180.php'}:continue # Generated versioned entry points; the canonical source is included.
            assert digest(files[web_prefix+original])==info['new'],original
    counts={key:sum(name.startswith(prefix) for name in files) for key,prefix in [('web',web_prefix),('windows','desktop/'),('android','android/'),('qa','qa/')]}
    assert counts['web']==137 and counts['windows']==32 and counts['android']==35
    files['MANIFEST-QA.json']=(json.dumps({'version':'web1.8.0-R13/windows1.8.1/android1.8.0','date':'2026-10-27','source_commit':SOURCE,'source_url':'https://github.com/fisitalmultiservicios-svg/FISITAAP/tree/'+SOURCE,'schema':'reference DDL from fictional QA database, 102 tables, no records','counts':counts,'file_count':len(files),'files':provenance},ensure_ascii=False,indent=2)+'\n').encode()
    files['SHA256SUMS-QA.txt']=''.join(digest(data)+'  '+name+'\n' for name,data in sorted(files.items())).encode()
    DEST.parent.mkdir(exist_ok=True)
    directories=sorted({parent.as_posix()+'/' for name in files for parent in pathlib.PurePosixPath(name).parents if parent.as_posix()!='.'})
    with zipfile.ZipFile(DEST,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
        for name in directories+sorted(files):
            directory=name.endswith('/');entry=zipfile.ZipInfo(name,(2026,10,27,0,0,0));entry.create_system=3;entry.compress_type=zipfile.ZIP_DEFLATED
            mode=0o755 if directory or name=='android/gradlew' else 0o644
            entry.external_attr=((stat.S_IFDIR if directory else stat.S_IFREG)|mode)<<16
            archive.writestr(entry,b'' if directory else files[name],compresslevel=9)
    with zipfile.ZipFile(DEST) as archive:
        assert archive.testzip() is None
        assert {i.filename for i in archive.infolist() if not i.is_dir()}==set(files)
        for name,data in files.items():assert archive.read(name)==data,name
        for name,info in provenance.items():assert digest(archive.read(name))==info['sha256'],name
    checksum=digest(DEST.read_bytes());(DEST.parent/'SHA256SUMS-QA-R13-2026-10-27.txt').write_text(checksum+'  '+DEST.name+'\n')
    schema_check=json.loads((AUDIT/'VALIDACION-ESQUEMA.json').read_text());assert schema_check['sha256']==digest((AUDIT/'esquema-r13-sin-datos.sql').read_bytes()) and schema_check['rows']==0 and schema_check['tables']==102
    report={'artifact':DEST.name,'source_commit':SOURCE,'zip_sha256':checksum,'zip_bytes':DEST.stat().st_size,'files':len(files),'source_files':sum(p['kind']=='git-source' for p in provenance.values()),'counts':counts,'zip_integrity':True,'source_blob_hashes_verified':True,'r13_payload_matches':True,'secret_shape_and_exclusion_checks':True,'sql_is_ddl_only':True,'schema_import_verified':schema_check}
    (DEST.parent/'VERIFICACION-QA-R13-2026-10-27.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
if __name__=='__main__':main()
