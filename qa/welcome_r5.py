"""Real uploads and all-business welcome display across mixed tenant versions."""
import json,pathlib,re,secrets,subprocess,sys
from PIL import Image
from playwright.sync_api import sync_playwright
from access180 import install_payload
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
ROOT=pathlib.Path(__file__).resolve().parents[1];STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
def ensure(actor,email):
    status,body=request(actor,'/owner-login')
    if status==200:assert request(actor,'/owner-login',{'csrf':re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1],'email':email,'password':PASSWORD})[0]==302
def upload(color):
    p=STAGE/'uploads'/('welcome-r5-'+color+'.png');Image.new('RGB',(1600,900),color).save(p);p.chmod(0o644)
    token=csrf('master','/master/bienvenida')
    result=subprocess.run(['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-master.cookies','-c','/tmp/r3-master.cookies','-F','csrf='+token,'-F','image=@/var/www/html/uploads/'+p.name,'-w','\n%{http_code}','http://localhost/master/bienvenida'],capture_output=True,check=True).stdout
    assert result.rsplit(b'\n',1)[1]==b'302';p.unlink()
    return sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key="welcome_image180"')[0]['value']
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    versions=sql('SELECT tenant_id,version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)');previous=sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key="welcome_image180"')
    counts=sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM pos_shifts) shifts')
    try:
        sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=101');sql('UPDATE fisitaap_tenant_updates180 SET version=180 WHERE tenant_id=102')
        install_payload(ROOT/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R4.zip')
        for actor,email in [('master','master@r2.invalid'),('owner','owner@r2.invalid'),('welcome_b','ownerb@r2.invalid')]:ensure(actor,email)
        image=upload('navy');assert image in request('master','/master')[1] and image in request('welcome_b','/admin')[1]
        assert image not in request('owner','/admin')[1]
        checks=['R4 real upload is visible for master/active180 but ignored on pending179, reproducing the shared welcome image defect']
        install_payload('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R5.zip')
        for actor,path in [('master','/master'),('owner','/admin'),('welcome_b','/admin')]:
            status,body=request(actor,path);assert status==200 and image in body and 'qa180-welcome' in body and 'qa180.css' in body,(actor,status)
        assert 'Imagen de bienvenida actual' in request('master','/master/bienvenida')[1]
        checks.append('R5 displays the saved shared image and its styles for master, pending179 and active180, with preview in the master editor')
        next_image=upload('green');assert next_image!=image
        for actor,path in [('master','/master'),('owner','/admin'),('welcome_b','/admin')]:
            body=request(actor,path)[1];assert next_image in body and image not in body
        checks.append('a second real upload replaces the image in all three profiles on reload, with a distinct media URL')
        sql('DELETE FROM rate_limits')
        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
            page=browser.new_page(viewport={'width':390,'height':844});page.goto('http://localhost:18080/owner-login');page.locator('[name=email]').fill('owner@r2.invalid');page.locator('[name=password]').fill(PASSWORD);page.locator('form button[type=submit],form button.btn').first.click();page.wait_for_url('**/admin')
            img=page.locator('.qa180-welcome>img');assert img.get_attribute('src')==next_image
            assert img.evaluate('(e)=>e.complete&&e.naturalWidth>0')
            assert img.evaluate('(e)=>e.getBoundingClientRect().width<=innerWidth')
            assert page.locator('script[src*="qa180.js"]').count()==0
            browser.close()
        checks.append('Chromium390px actually loads/displays the uploaded image for pending179, fits the viewport and does not load the new POS JavaScript')
        assert request('owner','/master/bienvenida',{'csrf':csrf('owner','/admin'),'clear':'yes'})[0]==403
        assert sql('SELECT tenant_id,version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)')==[{'tenant_id':101,'version':179},{'tenant_id':102,'version':180}]
        assert sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM pos_shifts) shifts')==counts
        checks.append('owner cannot alter global welcome; tenant activation states, sales and shifts remain unchanged by shared display')
        (ROOT/'actualizaciones/1.8.0/QA-BIENVENIDA-R5.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'scope':'actual multipart upload/PHP/MariaDB/Chromium, published R4 baseline and R5 payload, fictional tenants only'},indent=2)+'\n')
        print('PASS',len(checks),'shared welcome groups; real uploads, mixed versions, master preview, mobile and state preservation')
    finally:
        for row in versions:sql('UPDATE fisitaap_tenant_updates180 SET version=? WHERE tenant_id=?',[row['version'],row['tenant_id']])
        if previous:sql('UPDATE fisitaap_r2_settings SET value=? WHERE tenant_id=0 AND setting_key="welcome_image180"',[previous[0]['value']])
        else:sql('DELETE FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key="welcome_image180"')
        sql('DELETE FROM rate_limits')
if __name__=='__main__':main()
