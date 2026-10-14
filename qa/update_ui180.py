"""Versioned release rendering and business update concurrency on the fake fixture."""
import json,pathlib,secrets,subprocess,sys,time,zipfile
from playwright.sync_api import sync_playwright
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import PASSWORD,request,csrf,sql

ROOT=pathlib.Path(__file__).resolve().parents[1]
STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
BASE='http://localhost:18080'

def main():
    # The actual updater runs separately; here serve its exact verified payload.
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    package=pathlib.Path('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R2.zip')
    with zipfile.ZipFile(package) as archive:
        manifest=json.loads(archive.read('actualizador-180/manifest.json'))
        for name in sorted(manifest['files'],key=lambda n:n=='index.php'):
            destination=STAGE/name;destination.parent.mkdir(parents=True,exist_ok=True)
            destination.write_bytes(archive.read('actualizador-180/payload/'+name));destination.chmod(0o644)
            directory=destination.parent
            while directory!=STAGE:directory.chmod(0o755);directory=directory.parent
    tid=sql('SELECT id FROM tenants WHERE name="NEW ROLLOUT BUSINESS" ORDER BY id DESC LIMIT 1')[0]['id']
    email='update-ui-'+secrets.token_hex(4)+'@r2.invalid'
    sql('INSERT INTO users(tenant_id,name,email,password_hash,role,is_active) SELECT ?,"UPDATE UI OWNER",?,password_hash,"tenant_admin",1 FROM users WHERE id=202',[tid,email])
    errors=[];assets=[];pages=[]
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
        for actor,login,home,expected in [('pending',email,'/admin','Tu negocio continúa en 1.7.9'),('updated','owner@r2.invalid','/admin','Tu negocio ya está actualizado'),('master','master@r2.invalid','/master','Estado de los negocios')]:
            ctx=browser.new_context(viewport={'width':390,'height':844});page=ctx.new_page()
            page.on('pageerror',lambda error:errors.append(str(error)))
            page.on('response',lambda response:assets.append({'url':response.url,'http':response.status}) if response.request.resource_type in ['stylesheet','script'] else None)
            page.goto(BASE+'/owner-login');page.locator('[name=email]').fill(login);page.locator('[name=password]').fill(PASSWORD)
            page.locator('form button[type=submit],form button.btn').first.click();page.wait_for_url('**'+home)
            menu=page.locator('a[href$="/actualizar-sistema"]');assert menu.count()>0,actor
            page.goto(menu.first.get_attribute('href'),wait_until='networkidle')
            assert expected in page.inner_text('body'),actor
            if actor=='pending':
                button=page.get_by_role('button',name='Actualizar sistema',exact=True);assert button.count()==1 and button.is_enabled()
                assert page.locator('[name=ready]').get_attribute('required') is not None
                assert page.get_by_role('link',name='Descargar Windows').count()==1
            assert page.evaluate('Math.max(document.documentElement.scrollWidth,document.body.scrollWidth)<=innerWidth+1'),actor
            pages.append({'actor':actor,'http':200,'width':390,'administration_menu_present':True,'expected_state':expected})
            ctx.close()
        browser.close()
    assert not errors,errors
    assert assets and all(a['http']==200 and '/releases/1.8.0-r2/assets/' in a['url'] for a in assets),assets
    # An activation locks only its business. Read-only screens keep responding.
    owner_b='update_concurrency_b_'+secrets.token_hex(4)
    assert request(owner_b,'/owner-login',{'csrf':csrf(owner_b,'/owner-login'),'email':'ownerb@r2.invalid','password':PASSWORD})[0]==302
    own_csrf=csrf('owner','/admin/actualizar-sistema');b_csrf=csrf(owner_b,'/admin/actualizar-sistema')
    hold="define('FISITAAP_SITE_ROOT','/var/www/html');require '/var/www/html/releases/1.8.0-r2/app/bootstrap.php';$app->one('SELECT GET_LOCK(\"fisitaap:update180:101\",0) locked');echo \"LOCKED\\n\";flush();sleep(9);$app->one('SELECT RELEASE_LOCK(\"fisitaap:update180:101\") released');"
    process=subprocess.Popen(['docker','exec','fisitaap-r2-web','php','-r',hold],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    assert process.stdout.readline()==b'LOCKED\n'
    timings=[]
    try:
        for actor,method in [('owner','read'),(owner_b,'read'),(owner_b,'update'),('owner','update')]:
            start=time.monotonic()
            data=None if method=='read' else {'csrf':own_csrf if actor=='owner' else b_csrf,'action':'activate','ready':'yes'}
            status,body=request(actor,'/admin/actualizar-sistema',data)
            elapsed=time.monotonic()-start
            if actor=='owner' and method=='update':
                assert status==409 and 'terminando otra operación' in body,(status,body[-200:])
            else:assert status==200 and elapsed<4,(actor,method,status,elapsed)
            timings.append({'business':'A' if actor=='owner' else 'B','action':method,'http':status,'seconds':round(elapsed,2)})
    finally:
        process.communicate(timeout=15)
    assert process.returncode==0
    report={'passed':5,'pages':pages,'javascript_errors':errors,'versioned_assets':len(assets),'concurrent_requests':timings,'scope':'real Chromium and PHP/MariaDB; exact R2 payload and fictional tenants only; held activation mutex on business A'}
    (ROOT/'actualizaciones/1.8.0/QA-ACTUALIZACION-UI.json').write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
    print('PASS owner/master update menus and states, 390px layout, versioned assets, and business-specific concurrency',flush=True)

if __name__=='__main__':main()
