"""R3 extraction under restrictive umask; Apache UID 33, no repairs afterwards."""
import hashlib,json,os,pathlib,shutil,subprocess,zipfile

ROOT=pathlib.Path(__file__).resolve().parents[1]
STAGE=pathlib.Path('/workspace/fisitaap-permissions180-test')
CONTAINER='fisitaap-permissions180-web'

def run(args):return subprocess.run(args,capture_output=True,check=True).stdout
def put(path,text,mode):
    path.parent.mkdir(parents=True,exist_ok=True);path.write_text(text);path.chmod(mode)
def get(path):
    result=run(['docker','exec',CONTAINER,'curl','--noproxy','*','-sS','-w','\n%{http_code}','http://localhost'+path])
    body,status=result.rsplit(b'\n',1);return int(status),body

def main():
    state=json.loads(run(['docker','inspect',CONTAINER]))[0]
    assert any(m.get('Source')==str(STAGE) and m.get('Destination')=='/var/www/html' for m in state['Mounts'])
    run(['docker','start',CONTAINER]);report=[]
    package=pathlib.Path('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R3.zip')
    with zipfile.ZipFile(package) as archive:
        manifest=json.loads(archive.read('actualizador-180/manifest.json'))
        assert len(manifest['files'])==112
        for entry in archive.infolist():
            assert (entry.external_attr>>16)&0o777==(0o755 if entry.is_dir() else 0o644)
        for name,info in manifest['files'].items():
            assert hashlib.sha256(archive.read('actualizador-180/payload/'+name)).hexdigest()==info['new']
    for variant in ['over-R2','fresh']:
        for item in STAGE.iterdir():
            if item.is_dir():shutil.rmtree(item)
            else:item.unlink()
        put(STAGE/'.htaccess',(ROOT/'actualizaciones/mudanza-1.7.9-r3/web/.htaccess').read_text(),0o644)
        put(STAGE/'index.php','<?php http_response_code(404); echo "FIXTURE-APP-404";',0o644)
        put(STAGE/'who.php','<?php echo posix_geteuid();',0o644)
        put(STAGE/'app/bootstrap.php','<?php function current_user(){return null;} function url($p){return "/".$p;}',0o644)
        (STAGE/'app').chmod(0o755)
        if variant=='over-R2':
            previous=os.umask(0o077)
            try:run(['unzip','-oq',str(ROOT/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R2.zip'),'-d',str(STAGE)])
            finally:os.umask(previous)
            assert (STAGE/'actualizador-180/index.php').stat().st_mode&0o777==0o644
        previous=os.umask(0o077)
        try:run(['unzip','-oq',str(package),'-d',str(STAGE)])
        finally:os.umask(previous)
        # No chmod/chown after extracting the R3 archive.
        assert get('/who.php')==(200,b'33')
        assert get('/actualizador-180/index.php')[0]==302
        for path in ['/actualizador-180/manifest.json','/actualizador-180/payload/index.php',
                     '/actualizador-180/payload/releases/1.8.0-r3/app/updates180.php']:
            assert get(path)[0]==403
        for name in manifest['files']:
            path=STAGE/'actualizador-180/payload'/name
            assert path.stat().st_mode&0o777==0o644,name
            assert path.parent.stat().st_mode&0o777==0o755,name
        report.append({'variant':variant,'apache_uid':33,'updater_http':302,'protected_payload_http':403,'payload_files':112})
        print('PASS',variant,'unprivileged Apache, updater accessible, private payload denied',flush=True)
    (ROOT/'actualizaciones/1.8.0/QA-PERMISOS-ZIP-R3.json').write_text(json.dumps({
        'checks':report,'payload_hashes_verified':112,'extraction':'unzip under umask 077, explicit ZIP 644/755; no chmod/chown after R3 extraction',
        'scope':'isolated Apache UID33; old bootstrap authentication stub for entry redirect only; full updater/database exercised separately; no production'},indent=2)+'\n')

if __name__=='__main__':main()
