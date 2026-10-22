"""Lint the exact R10 archive and verify its final payload, portable modes and guide."""
import hashlib,json,pathlib,subprocess,zipfile
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
    archive=pathlib.Path('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R10.zip');php=[];js=[]
    with zipfile.ZipFile(archive) as z:
        assert z.testzip() is None;manifest=json.loads(z.read('actualizador-180/manifest.json'));assert len(manifest['files'])==126
        assert z.read('LEEME-CPANEL.md')==(ROOT/'actualizaciones/1.8.0/GUIA-CPANEL.md').read_bytes()
        for e in z.infolist():
            assert (e.external_attr>>16)&0o777==(0o755 if e.is_dir() else 0o644),e.filename
            if e.is_dir():continue
            name=e.filename;data=z.read(name)
            assert not name.endswith(('.sql','.log','.jks','.keystore','config.php')),name
            if name.endswith('.php'):
                r=subprocess.run(['docker','exec','-i','fisitaap-r2-web','php','-l'],input=data,capture_output=True);assert r.returncode==0,(name,r.stdout.decode());php.append(name)
            elif name.endswith('.js'):
                r=subprocess.run(['node','--check'],input=data,capture_output=True);assert r.returncode==0,(name,r.stderr.decode());js.append(name)
        for name,info in manifest['files'].items():assert hashlib.sha256(z.read('actualizador-180/payload/'+name)).hexdigest()==info['new']
    payloadphp=[p for p in php if '/payload/' in p]
    out={'payload_files':126,'payload_php_passed':len(payloadphp),'all_archive_php_passed':len(php),'javascript_passed':len(js),'guide_matches':True,'hashes_match':True,'permissions':'explicit644/755','php_files':php,'javascript_files':js,'environment':'PHP8.3 and Node; exact final R10 ZIP'}
    (ROOT/'actualizaciones/1.8.0/QA-SINTAXIS-R10.json').write_text(json.dumps(out,indent=2)+'\n');print('PASS',len(payloadphp),'payload PHP,',len(php),'all archive PHP,',len(js),'JavaScript,126 hashes/permissions/guide')
if __name__=='__main__':main()
