"""Read-only welcome diagnostic: real fake DB/HTTP, auth and injected failures."""
import hashlib,json,pathlib,re,secrets,shutil,subprocess,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,sql,PASSWORD

ROOT=pathlib.Path(__file__).resolve().parents[1];STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
SOURCE=ROOT/'actualizaciones/1.8.0/diagnostico-bienvenida/diagnostico-bienvenida-180.php'
PATH='/diagnostico-bienvenida-180.php'
def digest():
    data=subprocess.run(['docker','exec','fisitaap-db','mariadb-dump','-uroot','--no-create-info','--skip-comments','--skip-dump-date','--compact','--order-by-primary','--skip-extended-insert','fisitaap_r2_test'],capture_output=True,check=True).stdout
    return hashlib.sha256(data).hexdigest()
def reload_fixture():
    # Deterministically load each synthetic fixture, independent of OPcache's timer.
    # This temporary local-only helper is never shipped in the diagnostic ZIP.
    helper=STAGE/'qa-refresh-welcome-local.php'
    helper.write_text("<?php foreach(['config.php','releases/1.8.0-r5/app/panel180.php','diagnostico-bienvenida-180.php'] as $f)if(function_exists('opcache_invalidate'))opcache_invalidate(__DIR__.'/'.$f,true);echo 'ok';")
    helper.chmod(0o644)
    try:assert request('fixture','/'+helper.name)==(200,'ok')
    finally:helper.unlink()
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    assert 'releases/1.8.0-r5' in (STAGE/'index.php').read_text()
    shutil.copyfile(SOURCE,STAGE/SOURCE.name);(STAGE/SOURCE.name).chmod(0o644)
    reload_fixture()
    for actor,email in [('master','master@r2.invalid'),('owner','owner@r2.invalid')]:
        status,body=request(actor,'/owner-login')
        if status==200:
            csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
            assert request(actor,'/owner-login',{'csrf':csrf,'email':email,'password':PASSWORD})[0]==302
    checks=[];before=digest()
    status,body=request('welcome_anon_'+secrets.token_hex(3),PATH)
    assert status==403 and 'releases/1.8.0-r5' not in body and 'Archivo:' not in body
    for actor in ['owner','master']:
        status,body=request(actor,PATH)
        assert status==200 and 'FISITAAP-BIENVENIDA-180-D2' in body and 'class="error"' not in body,(actor,status,body)
        assert 'La bienvenida completa de esta cuenta se pudo generar.' in body
    assert request('owner',PATH,{'clear':'yes'})[0]==405
    assert digest()==before
    checks.append('fresh master/owner authorization, anonymous403, POST405, published-R5 detection and successful welcome/render checks; entire fixture data digest unchanged')
    sql('UPDATE users SET force_password_change=1 WHERE id=202')
    try:assert request('owner',PATH)[0]==403
    finally:sql('UPDATE users SET force_password_change=0 WHERE id=202')
    sql('UPDATE users SET role="cashier" WHERE id=202')
    try:assert request('owner',PATH)[0]==403
    finally:sql('UPDATE users SET role="tenant_admin" WHERE id=202')
    checks.append('forced password and revoked owner role immediately deny diagnostic details')
    panel=STAGE/'releases/1.8.0-r5/app/panel180.php';saved_panel=panel.read_bytes();config=STAGE/'config.php';saved_config=config.read_bytes()
    secret='not-real-diagnostic-qa-'+secrets.token_hex(8)
    marker=b'function qa180_welcome(App $app,array $user,?array $tenant):void {'
    try:
        config.write_bytes(saved_config.rstrip()[:-2]+b",'openai_api_key'=>'"+secret.encode()+b"'];")
        assert saved_panel.count(marker)==1
        panel.write_bytes(saved_panel.replace(marker,marker+b"\nthrow new Error('D1 synthetic failure '.$app->config['openai_api_key']);",1));reload_fixture()
        assert request('owner','/admin')[0]==500
        before=digest();status,body=request('owner',PATH)
        assert status==200 and 'D1 synthetic failure [privado]' in body and secret not in body
        assert 'app/panel180.php' in body and 'línea' in body
        assert 'La estructura del panel y el chat se pudieron generar' in body
        assert '/workspace/' not in body and '/var/www/html' not in body
        assert digest()==before
        checks.append('a reproducible synthetic admin500 is caught with file/line, secrets redacted, absolute root hidden, default panel tested independently; all DB data unchanged')
        panel.write_bytes(saved_panel.replace(marker,marker+b"\ntrigger_error('D1 synthetic fatal',E_USER_ERROR);",1));reload_fixture()
        before=digest();status,body=request('owner',PATH)
        assert status==200 and 'D1 synthetic fatal' in body and secret not in body
        assert digest()==before
        checks.append('fatal shutdown produces an authenticated readable report and rolls back the read-only transaction')
        panel.write_bytes(saved_panel.replace(marker,marker+b"\n$app->exec('UPDATE tenants SET name=\"DIAGNOSTIC MUST NOT WRITE\" WHERE id=101');",1));reload_fixture()
        before=digest();status,body=request('owner',PATH)
        assert status==200 and 'READ ONLY' in body.upper() and digest()==before
        checks.append('MariaDB READ ONLY rejects even an injected write; no business data changes')
    finally:panel.write_bytes(saved_panel);config.write_bytes(saved_config);reload_fixture()
    report={'passed':len(checks),'checks':checks,'scope':'published R5 in isolated PHP8.3/MariaDB; fictitious accounts/data and explicitly synthetic500/fatal/write attempts; not the live hosting cause'}
    (ROOT/'actualizaciones/1.8.0/QA-DIAGNOSTICO-BIENVENIDA-D2.json').write_text(json.dumps(report,indent=2)+'\n')
    print('PASS',len(checks),'welcome diagnostic checks: auth, redaction, fatal/error capture and database read-only enforcement',flush=True)

if __name__=='__main__':main()
