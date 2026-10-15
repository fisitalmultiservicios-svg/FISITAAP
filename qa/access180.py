"""Reproduce the suspended/orphan account login loop on R4; verify R5 recovery."""
import json,pathlib,re,secrets,subprocess,sys,urllib.parse,zipfile
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
ROOT=pathlib.Path(__file__).resolve().parents[1];STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
def reload_root():
    p=STAGE/'qa-access-refresh-local.php';p.write_text("<?php if(function_exists('opcache_invalidate'))foreach(['index.php','volver-acceso-180.php','diagnostico-bienvenida-180.php'] as $f)opcache_invalidate(__DIR__.'/'.$f,true);echo 'ok';");p.chmod(0o644)
    try:assert request('fixture','/'+p.name)==(200,'ok')
    finally:p.unlink()
def login(actor,email):
    return request(actor,'/owner-login',{'csrf':csrf(actor,'/owner-login'),'email':email,'password':PASSWORD})
def install_payload(name):
    with zipfile.ZipFile(name) as z:
        manifest=json.loads(z.read('actualizador-180/manifest.json'))
        for n in sorted(manifest['files'],key=lambda n:n=='index.php'):
            p=STAGE/n;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(z.read('actualizador-180/payload/'+n));p.chmod(0o644)
            parent=p.parent
            while parent!=STAGE:parent.chmod(0o755);parent=parent.parent
        for n in ['volver-acceso-180.php','diagnostico-bienvenida-180.php']:
            if n in z.namelist():p=STAGE/n;p.write_bytes(z.read(n));p.chmod(0o644)
    reload_root()
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits')
    active=sql('SELECT is_active FROM tenants WHERE id=101')[0]['is_active'];tenant=sql('SELECT tenant_id FROM users WHERE id=202')[0]['tenant_id']
    counts=sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM orders) orders,(SELECT COUNT(*) FROM pos_shifts) shifts')
    checks=[];actor='access_'+secrets.token_hex(4)
    try:
        sql('UPDATE tenants SET is_active=1 WHERE id=101');sql('UPDATE users SET tenant_id=101 WHERE id=202')
        install_payload(ROOT/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R4.zip')
        assert login(actor,'owner@r2.invalid')[0]==302
        sql('UPDATE tenants SET is_active=0 WHERE id=101')
        assert request(actor,'/admin')[0]==500
        assert request(actor,'/owner-login')[0]==302
        checks.append('published R4 reproduces admin500 with a suspended store and owner-login redirects the retained session back to it')
        with zipfile.ZipFile('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R5.zip') as z:
            p=STAGE/'volver-acceso-180.php';p.write_bytes(z.read(p.name));p.chmod(0o644)
        reload_root();status,body=request(actor,'/volver-acceso-180.php');assert status==200
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
        assert request(actor,'/volver-acceso-180.php',{'csrf':token})[0]==302
        assert request(actor,'/owner-login')[0]==200
        assert sql('SELECT is_active FROM tenants WHERE id=101')[0]['is_active']==0
        assert login(actor,'owner@r2.invalid')[0]==302 and request(actor,'/admin')[0]==500
        checks.append('the bundled recovery file works before installation on old R4, explicitly closes the stuck session and restores the login form without reactivating the store')
        install_payload('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R5.zip')
        assert request(actor,'/admin')[0]==302
        status,body=request(actor,'/owner-login');assert status==200 and 'Tienda fuera de servicio' in body and 'suspendido' in body and 'name="password"' in body
        assert sql('SELECT is_active FROM tenants WHERE id=101')[0]['is_active']==0
        assert login(actor,'owner@r2.invalid')[0]==302
        assert 'suspendido' in request(actor,'/owner-login')[1] and request(actor,'/admin')[0]==302
        checks.append('R5 returns an existing suspended-store session to the usable login; valid credentials are rejected humanly without reactivating the store or leaving an authenticated session')
        assert login(actor,'master@r2.invalid')[0]==302
        assert request(actor,'/master')[0]==200 and request(actor,'/admin')[0]==302
        status,body=request(actor,'/volver-acceso-180.php');assert status==200
        assert request(actor,'/volver-acceso-180.php',{'csrf':'bad'})[0]==200
        assert request(actor,'/master')[0]==200
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
        before=sql('SELECT id,is_active,plan FROM tenants ORDER BY id')
        assert request(actor,'/volver-acceso-180.php',{'csrf':token})[0]==302
        assert request(actor,'/owner-login')[0]==200 and request(actor,'/master')[0]==302
        assert sql('SELECT id,is_active,plan FROM tenants ORDER BY id')==before
        checks.append('master login remains available, master at /admin is redirected to /master, recovery GET/badCSRF preserves session and explicit valid POST closes only that browser session')
        sql('UPDATE tenants SET is_active=1 WHERE id=101')
        assert login(actor,'owner@r2.invalid')[0]==302 and request(actor,'/admin')[0]==200
        sql('UPDATE users SET tenant_id=NULL WHERE id=202')
        assert request(actor,'/owner-login')[0]==302
        status,body=request(actor,'/owner-login');assert status==200 and 'asignado' in body
        assert login(actor,'owner@r2.invalid')[0]==302
        assert 'asignado' in request(actor,'/owner-login')[1]
        assert sql('SELECT tenant_id FROM users WHERE id=202')[0]['tenant_id'] is None
        checks.append('a retained session and a new login with missing store association both return a clear usable form; no automatic reassignment or privilege escalation')
        sql('UPDATE users SET tenant_id=101 WHERE id=202')
        assert login(actor,'owner@r2.invalid')[0]==302 and request(actor,'/admin')[0]==200
        assert login('manager_access_'+secrets.token_hex(3),'manager@r2.invalid')[0]==302
        cashier='cashier_access_'+secrets.token_hex(3);assert login(cashier,'cashiera@r2.invalid')[0]==302 and request(cashier,'/admin/ventas')[0]==200
        buyer='buyer_access_'+secrets.token_hex(3)
        assert request(buyer,'/login',{'csrf':csrf(buyer,'/login'),'email':'customer@r2.invalid','password':PASSWORD})[0]==302
        assert request(buyer,'/account')[0]==200
        assert sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM orders) orders,(SELECT COUNT(*) FROM pos_shifts) shifts')==counts
        checks.append('valid owner/manager/cashier/customer flows still work and sales, orders and shifts remain unchanged')
        (ROOT/'actualizaciones/1.8.0/QA-ACCESO-R5.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'scope':'exact published R4 baseline and R5 payload, real PHP/MariaDB HTTP; isolated fictitious data, no live hosting or automatic business activation'},indent=2)+'\n')
        print('PASS',len(checks),'login/recovery groups; R4 failure reproduced and R5 fixes loop without activating/reassigning accounts')
    finally:
        sql('UPDATE tenants SET is_active=? WHERE id=101',[active]);sql('UPDATE users SET tenant_id=? WHERE id=202',[tenant]);sql('DELETE FROM rate_limits')
if __name__=='__main__':main()
