"""Demo entry, return navigation and tab isolation on the disposable fixture."""
import json
import pathlib
import re
import subprocess
import sys
from playwright.sync_api import sync_playwright
from migration179 import STAGE, SERVER, DELIVERY, run, php, digests
from migration_demos179 import sql
config = STAGE / 'config.php'
original = config.read_text()
ip = run(['docker','inspect','-f','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',SERVER]).decode().strip()
base = 'http://' + ip
checks=[]
created=[]
baseline=digests(SERVER)
source=digests('fisitaap-r2-web')
assert STAGE.name=='fisitaap-migration-test' and SERVER=='fisitaap-migration-web'
def passed(label):
    checks.append(label)
    print('PASS',label,flush=True)
try:
    config.write_text(original.replace('http://localhost', base))
    run(['docker','exec',SERVER,'curl','--noproxy','*','-sS','http://localhost/preparar-mudanza.php'])
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
        ctx=browser.new_context()
        page=ctx.new_page()
        navigations=[]
        page.on('framenavigated',lambda frame:navigations.append(frame.url) if frame==page.main_frame else None)
        page.goto(base+'/demo')
        page.get_by_role('button',name='Probar panel',exact=True).first.click()
        page.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/admin'))
        first=page.url
        token=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")')
        created.append(token)
        assert token in first
        page.goto(first+'/productos')
        assert page.locator('aside').filter(has_text='Demo privada').count()>=1
        passed('start sets tab storage and navigation remains in the private demo')
        other=ctx.new_page()
        other.goto(first)
        other.wait_for_url(base+'/demo')
        passed('a new tab without demo storage returns to the selector')
        page.close()
        other.get_by_role('button',name='Probar panel',exact=True).first.click()
        other.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/admin'))
        fresh=other.evaluate('sessionStorage.getItem("fisitaap-demo-tab")')
        created.append(fresh)
        assert fresh!=token
        passed('entry after closing the demo starts a new disposable copy')
        other.get_by_role('button',name='Terminar y descartar',exact=True).click()
        other.wait_for_url(base+'/demo')
        passed('explicit finish returns the browser to the demo selector')
        assert not any('/demo/iniciar' in url for url in navigations)
        passed('JavaScript demo entry stays on the selector until ready, without a visible intermediate page')

        # Reproduce the reported customer-mode return, rather than only panel mode.
        page=other
        page.get_by_role('button',name='Entrar a la tienda demo →',exact=True).click()
        page.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/demo-tienda-[a-f0-9]{32}/catalogo'))
        shop=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")');created.append(shop)
        shop_url=page.url
        back=page.get_by_role('link',name='← Elegir otra demo',exact=True)
        assert back.get_attribute('href')==base+'/demo'
        back.click();page.wait_for_url(base+'/demo')
        assert page.get_by_role('button',name='Entrar al restaurante demo →',exact=True).count()==1
        passed('customer catalog return opens the public selector without a 403')

        page.go_back();page.wait_for_url(shop_url)
        assert page.locator('aside').filter(has_text='Demo privada').count()==1
        page.get_by_role('button',name='Probar panel',exact=True).click()
        page.wait_for_url(base+'/demo/s/'+shop+'/admin')
        page.get_by_role('button',name='Probar compra',exact=True).click()
        page.wait_for_url(shop_url)
        passed('browser Back and customer/panel switching keep the same private copy')

        scoped=base+'/demo/s/'+shop
        response=ctx.request.get(scoped+'/demo',max_redirects=0)
        assert response.status==302 and response.headers['location']==base+'/demo'
        assert 'no-store' in response.headers['cache-control']
        assert ctx.request.post(scoped+'/demo',data={}).status==403
        assert ctx.request.get(scoped+'/laventanita/catalogo').status==403
        assert ctx.request.get(scoped+'/api/desktop/status').status==403
        page.goto(scoped+'/demo');page.wait_for_url(base+'/demo')
        passed('older scoped selector links return safely; writes, other stores and device APIs stay blocked')

        page.get_by_role('button',name='Entrar al restaurante demo →',exact=True).click()
        page.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/demo-restaurante-[a-f0-9]{32}/catalogo'))
        restaurant=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")');created.append(restaurant)
        assert page.locator('.demo-credentials').inner_text().startswith('Cupón de prueba: SABOR10')
        assert restaurant!=shop
        passed('restaurant entry from the selector opens a new copy and shows its correct coupon')

        tenant=sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?',[restaurant])[0]['tenant_id']
        sql('UPDATE products SET name="CHANGED PRIVATE FIXTURE" WHERE tenant_id=? AND sku="DEMO1"',[tenant])
        page.get_by_role('link',name='← Elegir otra demo',exact=True).click();page.wait_for_url(base+'/demo')
        page.get_by_role('button',name='Entrar al restaurante demo →',exact=True).click()
        page.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/demo-restaurante-[a-f0-9]{32}/catalogo'))
        restarted=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")');created.append(restarted)
        assert restarted!=restaurant
        new_tenant=sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?',[restarted])[0]['tenant_id']
        assert sql('SELECT name FROM products WHERE tenant_id=? AND sku="DEMO1"',[new_tenant])==[{'name':'Producto demo'}]
        passed('returning and re-entering starts from the original template, without prior private edits')

        stranger=browser.new_context()
        stranger_page=stranger.new_page()
        response=stranger_page.goto(page.url)
        assert response.status==403 and 'otro navegador' in stranger_page.inner_text('body')
        stranger.close()
        passed('a separate browser session cannot reuse another visitor\'s demo URL')

        for path in ['/tienda-demo/catalogo','/restaurante-demo/menu','/demo-restaurante']:
            page.goto(base+path);page.wait_for_url(base+'/demo')
            assert page.get_by_role('button',name='Entrar a la tienda demo →',exact=True).count()==1
        passed('both old public catalog addresses and the restaurant shortcut reach the selector')

        # Both official landing buttons must actually launch, without a detour
        # through the old template URLs reported by the Brave user.
        for i,kind in enumerate(['tienda','restaurante']):
            page.goto(base+'/')
            page.locator('.r3-market-demos').get_by_role('button',name='Abrir demo →',exact=True).nth(i).click()
            page.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/demo-'+kind+r'-[a-f0-9]{32}/catalogo'))
            launched=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")');created.append(launched)
            assert launched not in created[:-1]
            page.get_by_role('link',name='← Elegir otra demo',exact=True).click();page.wait_for_url(base+'/demo')
        passed('both official landing buttons directly launch isolated demos and return correctly')

        # A lingering expired URL must still offer a way back to the selector.
        expired='0'*32
        response=ctx.request.get(base+'/demo/s/'+expired+'/demo',max_redirects=0)
        assert response.status==302 and response.headers['location']==base+'/demo'
        passed('return links work even when the disposable session has already expired')
        browser.close()
finally:
    for token in created:
        assert re.fullmatch('[a-f0-9]{32}',token)
        php(SERVER,"require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/demo_sandbox.php';$r=$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',['"+token+"']);if($r)demo_discard($app,$r);")
    config.write_text(original)
    run(['docker','exec',SERVER,'curl','--noproxy','*','-sS','http://localhost/preparar-mudanza.php'])
after=digests(SERVER)
assert {k:v for k,v in after.items() if k!='rate_limits'}=={k:v for k,v in baseline.items() if k!='rate_limits'}
assert digests('fisitaap-r2-web')==source
passed('browser tests discard only their own copies and preserve all original business data')
(DELIVERY/'QA-NAVEGADOR.json').write_text(json.dumps({'environment':'Chromium with real JavaScript and nonpersistent browser contexts on isolated PHP 8.3 fixture; actual Brave deployment still requires user verification','passed':len(checks),'checks':checks},indent=2)+'\n')
