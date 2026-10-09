"""Reproduce stale CSS/service-worker state; no production browser or data used."""
import json,re,pathlib,sys,zipfile,hashlib,io,subprocess
from playwright.sync_api import sync_playwright
from migration179 import STAGE,SERVER,DELIVERY,run,php,digests,request
from migration_demos179 import sql

assert STAGE.name=='fisitaap-migration-test' and SERVER=='fisitaap-migration-web'
config=STAGE/'config.php';original=config.read_text()
baseline=digests(SERVER);source=digests('fisitaap-r2-web')
ip=run(['docker','inspect','-f','{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}',SERVER]).decode().strip()
base='http://'+ip
checks=[];tokens=[]
legacy=STAGE/'sw.js';foreign=STAGE/'foreign-worker-test.js'
assert not legacy.exists() and not foreign.exists()
def passed(label):
    checks.append(label);print('PASS',label,flush=True)
try:
    status,body=request('server-check','/comprobar-servidor.php?revision=179m2')
    assert status==200 and 'Estás en la copia nueva' in body and '179m2' in body
    assert str(STAGE) not in body and digests(SERVER)==baseline
    passed('public server marker needs no credentials or database writes and reveals no private paths')

    # Multiple collaborators share a generated, unknown password only within
    # their disposable copy; neither old passwords nor cross-copy hashes survive.
    result=json.loads(php(SERVER,"require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/demo_sandbox.php';$a=demo_open($app,'restaurante','panel');$b=demo_open($app,'restaurante','panel');echo json_encode([$a,$b]);"))
    tokens.extend(result)
    tenants=[sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?',[t])[0]['tenant_id'] for t in tokens]
    hashes=[sql('SELECT DISTINCT password_hash FROM users WHERE tenant_id=?',[t]) for t in tenants]
    original_hashes=sql('SELECT password_hash FROM users WHERE tenant_id=901')
    assert all(len(h)==1 for h in hashes) and hashes[0]!=hashes[1]
    assert all(h[0] not in original_hashes for h in hashes)
    passed('each private copy performs one password hash and keeps it independent of template and other copies')
    for token in tokens:
        php(SERVER,"require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/demo_sandbox.php';demo_discard($app,$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',['"+token+"']));")
    tokens.clear()
    config.write_text(original.replace('http://localhost',base))
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server','--unsafely-treat-insecure-origin-as-secure='+base])
        ctx=browser.new_context();page=ctx.new_page()
        page.goto(base+'/comprobar-servidor.php?revision=179m2')
        code=page.locator('code').inner_text()
        other=browser.new_context();p2=other.new_page();p2.goto(base+'/comprobar-servidor.php?revision=179m2')
        assert p2.locator('code').inner_text()==code
        assert not ctx.cookies() and not other.cookies()
        other.close()
        passed('separate browser sessions see the same server/revision marker without setting session cookies')

        page.goto(base+'/')
        stylesheet=page.locator('link[rel="stylesheet"][href*="assets/pdf-r3.css"]').get_attribute('href')
        page.evaluate('async()=>{await window.fisitaapRefreshBrowser();sessionStorage.setItem("fisitaap-browser-179m2","1")}')

        legacy.write_text("self.addEventListener('install',e=>e.waitUntil(self.skipWaiting()));self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));self.addEventListener('fetch',e=>{if(e.request.method==='GET')e.respondWith(caches.match(e.request).then(r=>r||fetch(e.request)))});")
        foreign.write_text("self.addEventListener('install',e=>e.waitUntil(self.skipWaiting()));self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));")
        legacy.chmod(0o644);foreign.chmod(0o644)
        page.evaluate("""async ({base,stylesheet})=>{
          await navigator.serviceWorker.register(base+'/sw.js?fixture=legacy');await navigator.serviceWorker.ready;
          await navigator.serviceWorker.register(base+'/foreign-worker-test.js',{scope:'/other-app/'});
          const c=await caches.open('fisitapp-stale-fixture');
          await c.put(stylesheet,new Response('<html>WRONG CACHED CSS</html>',{headers:{'Content-Type':'text/html'}}));
          await caches.open('other-app-cache');
          localStorage.setItem('fisitaap_cart_lavent','KEEP MY CART');sessionStorage.setItem('fisitaap-browser-179m2','1');
        }""",{'base':base,'stylesheet':stylesheet})
        page.goto(base+'/')
        assert page.locator('.r3-official-nav').evaluate('e=>getComputedStyle(e).display')=='block'
        passed('a stale worker serving cached HTML as CSS reproduces the unstyled landing page')
        parent=[c for c in ctx.cookies() if c['name']=='fisitaap_session'][0]['value']
        legacy.unlink()
        page.goto(base+'/comprobar-servidor.php?revision=179m2')
        page.get_by_role('button',name='Renovar archivos y abrir FISITAAP').click()
        page.wait_for_url(base+'/?revision=179m2')
        assert page.locator('.r3-official-nav').evaluate('e=>getComputedStyle(e).display')=='flex'
        state=page.evaluate("""async()=>({cache:await caches.keys(),workers:(await navigator.serviceWorker.getRegistrations()).map(r=>(r.active||r.waiting||r.installing)?.scriptURL),cart:localStorage.getItem('fisitaap_cart_lavent')})""")
        assert 'fisitapp-stale-fixture' not in state['cache'] and 'other-app-cache' in state['cache']
        assert not any('/sw.js' in s for s in state['workers'])
        assert any('/foreign-worker-test.js' in s for s in state['workers'])
        assert state['cart']=='KEEP MY CART'
        assert [c for c in ctx.cookies() if c['name']=='fisitaap_session'][0]['value']==parent
        passed('recovery restores styled landing and removes only old FISITAAP worker/cache, preserving other apps, cookies and cart')

        versions=page.locator('link[rel="stylesheet"]').evaluate_all('(links)=>links.map(l=>new URL(l.href).searchParams.get("v"))')
        assert versions and all(v.endswith('-179m2') for v in versions)
        passed('all landing stylesheet URLs carry the same new revision to bypass old HTTP cache entries')
        response=ctx.request.get(base+'/sw.js?old-version=1')
        assert response.status==200 and 'no-store' in response.headers['cache-control']
        assert 'registration.unregister' in response.text() and 'respondWith' not in response.text()
        passed('old worker update URLs serve a retiring worker that cannot substitute a cached homepage')

        # Record actual frame navigation: entry uses JSON, so no HTML
        # transition is visited while the independent copy is being created.
        page.goto(base+'/demo')
        visited=[];page.on('framenavigated',lambda frame:visited.append(frame.url) if frame==page.main_frame else None)
        page.get_by_role('button',name='Entrar a la tienda demo →').click()
        page.wait_for_url(re.compile(r'/demo/s/[a-f0-9]{32}/demo-tienda-[a-f0-9]{32}/catalogo'))
        token=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")');tokens.append(token)
        assert not any('/demo/iniciar' in u for u in visited)
        passed('demo entry uses JSON then opens the private catalog, without an unstyled opening page')
        browser.close()
finally:
    for token in tokens:
        assert re.fullmatch('[a-f0-9]{32}',token)
        php(SERVER,"require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/demo_sandbox.php';$r=$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',['"+token+"']);if($r)demo_discard($app,$r);")
    legacy.unlink(missing_ok=True);foreign.unlink(missing_ok=True);config.write_text(original)
assert {k:v for k,v in digests(SERVER).items() if k!='rate_limits'}=={k:v for k,v in baseline.items() if k!='rate_limits'}
assert digests('fisitaap-r2-web')==source
passed('all browser recovery operations preserve original business data and the source fixture')
(DELIVERY/'QA-RECUPERACION-NAVEGADOR.json').write_text(json.dumps({'environment':'isolated PHP 8.3 / MariaDB / Chromium, including a synthetic cached HTML stylesheet and stale worker; live Chrome/Brave DNS and certificates require user verification','passed':len(checks),'checks':checks},indent=2)+'\n')
