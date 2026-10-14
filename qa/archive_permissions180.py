"""Unzip as-is under umask 077; serve as www-data, without repairing extraction."""
import hashlib, json, os, pathlib, shutil, subprocess, zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
STAGE = pathlib.Path('/workspace/fisitaap-permissions180-test')
CONTAINER = 'fisitaap-permissions180-web'

def run(args):
    return subprocess.run(args, capture_output=True, check=True).stdout

def put(path, text, mode):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)
    path.chmod(mode)

def get(path):
    data = run(['docker','exec',CONTAINER,'curl','--noproxy','*','-sS',
                '-w','\n%{http_code}','http://localhost'+path])
    body, status = data.rsplit(b'\n',1)
    return int(status), body

def main():
    STAGE.mkdir(exist_ok=True)
    STAGE.chmod(0o755)
    if subprocess.run(['docker','inspect',CONTAINER],capture_output=True).returncode:
        run(['docker','run','-d','--name',CONTAINER,'--network','fisitaap-dev',
             '-v',str(STAGE)+':/var/www/html','fisitaap-dev:php83'])
    else:
        state=json.loads(run(['docker','inspect',CONTAINER]))[0]
        assert any(m.get('Source')==str(STAGE) and m.get('Destination')=='/var/www/html' for m in state['Mounts'])
        run(['docker','start',CONTAINER])
    report=[]
    for variant in ['before','fixed-over-existing','fresh-fixed']:
        if variant!='fixed-over-existing':
            for item in list(STAGE.iterdir()):
                if item.is_dir():shutil.rmtree(item)
                else:item.unlink()
            # The existing site is accessible. Only new ZIP extraction is under test.
            put(STAGE/'.htaccess',(ROOT/'actualizaciones/mudanza-1.7.9-r3/web/.htaccess').read_text(),0o644)
            put(STAGE/'index.php','<?php http_response_code(404); echo "FIXTURE-APP-404";',0o644)
            put(STAGE/'who.php','<?php echo posix_geteuid();',0o644)
            put(STAGE/'app/bootstrap.php','<?php function current_user(){return null;} function url($p){return "/".$p;}',0o644)
            (STAGE/'app').chmod(0o755)
        # Fixed ZIPs overwrite the original failed extraction, including existing directories.
        # Original cPanel report: directories 755, archive files 600.
        # Corrected archives must also work with a restrictive extraction umask.
        previous=os.umask(0o022 if variant=='before' else 0o077)
        try:
            for name in ['FISITAAP-1.8.0-CPANEL.zip','FISITAAP-180-COMPROBAR-RUTA.zip']:
                package=(pathlib.Path('/workspace/fisitaap-env')/(name+'.before-permissions-fix')
                         if variant=='before' else pathlib.Path('/workspace/entregas')/name)
                run(['unzip','-oq',str(package),'-d',str(STAGE)])
        finally:os.umask(previous)
        # No chmod/chown after extraction: preserve the exact archive result.
        assert get('/who.php')==(200,b'33'), 'Apache must serve as unprivileged www-data'
        results=[]
        for path,marker in [('/prueba-ruta-180.txt',b'FISITAAP-RUTA-180-20261014'),
                            ('/actualizador-180/prueba-ruta-180.txt',b'FISITAAP-ACTUALIZADOR-RUTA-180-20261014'),
                            ('/comprobar-ruta-180.php',b'FISITAAP-RUTA-180-20261014')]:
            status,body=get(path)
            if variant=='before':assert status!=200,(path,status)
            else:assert status==200 and marker in body,(path,status)
            results.append({'path':path,'http':status})
        updater_status,_=get('/actualizador-180/index.php')
        if variant!='before':
            assert updater_status==302
            for path in ['/actualizador-180/manifest.json','/actualizador-180/payload/index.php']:
                assert get(path)[0]==403
            assert (STAGE/'actualizador-180').stat().st_mode&0o777==0o755
            assert (STAGE/'actualizador-180/index.php').stat().st_mode&0o777==0o644
            for name in ['FISITAAP-1.8.0-CPANEL.zip','FISITAAP-180-COMPROBAR-RUTA.zip']:
                with zipfile.ZipFile(pathlib.Path('/workspace/entregas')/name) as archive:
                    assert all((e.external_attr>>16)&0o777==(0o755 if e.is_dir() else 0o644) for e in archive.infolist())
        report.append({'variant':variant,'apache_uid':33,'probe_responses':results,'updater_http':updater_status})
        print('PASS',variant,json.dumps(results),flush=True)
    with zipfile.ZipFile('/workspace/fisitaap-env/FISITAAP-1.8.0-CPANEL.zip.before-permissions-fix') as before, zipfile.ZipFile('/workspace/entregas/FISITAAP-1.8.0-CPANEL.zip') as after:
        payload=[n for n in before.namelist() if n.startswith('actualizador-180/payload/') and not n.endswith('/')]
        assert len(payload)==109
        assert all(hashlib.sha256(before.read(n)).digest()==hashlib.sha256(after.read(n)).digest() for n in payload)
    result={'checks':report,'functional_payload_files_unchanged':109,
            'extraction':'old ZIP under umask 022 matching reported 755 directories/600 files; corrected ZIP under umask 077 both over existing files and fresh; no chmod/chown after extraction',
            'scope':'isolated Apache www-data fixture; updater auth entry mocked; no production access or database'}
    (ROOT/'actualizaciones/1.8.0/QA-PERMISOS-ZIP-R1.json').write_text(json.dumps(result,indent=2)+'\n')
    print('PASS 109 functional files unchanged; protected payload/manifest HTTP 403.',flush=True)

if __name__=='__main__':main()
