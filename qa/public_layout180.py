"""Public layout regressions against real Chromium, fictional PHP/MariaDB only."""
import json,pathlib,subprocess,sys,zipfile
from playwright.sync_api import sync_playwright
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import PASSWORD,sql

ROOT=pathlib.Path(__file__).resolve().parents[1]
STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
OUTPUT=pathlib.Path('/workspace/fisitaap-env/public-layout-r3')
BASE='http://localhost:18080'
WIDTHS=[1366,1317,1024,900,768,621,620,390,360]

def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    packaged='--packaged' in sys.argv
    phase='packaged' if packaged else 'canonical'
    if packaged:
        with zipfile.ZipFile('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R3.zip') as archive:
            manifest=json.loads(archive.read('actualizador-180/manifest.json'))
            for name in sorted(manifest['files'],key=lambda n:n=='index.php'):
                destination=STAGE/name;destination.parent.mkdir(parents=True,exist_ok=True)
                destination.write_bytes(archive.read('actualizador-180/payload/'+name));destination.chmod(0o644)
                directory=destination.parent
                while directory!=STAGE:directory.chmod(0o755);directory=directory.parent
    prior=sql('SELECT logo FROM tenants WHERE id=101')[0]['logo']
    sql('UPDATE tenants SET logo="assets/demo/sabor-urbano-logo.svg" WHERE id=101')
    results=[];errors=[];missing=[];assets=[];OUTPUT.mkdir(exist_ok=True)
    try:
        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
            page=browser.new_page()
            page.on('pageerror',lambda error:errors.append(str(error)))
            page.on('response',lambda response:assets.append({'url':response.url,'http':response.status}) if response.request.resource_type in ['stylesheet','script'] else None)
            page.on('response',lambda response:missing.append(response.url) if response.request.resource_type in ['stylesheet','script','image'] and response.status>=400 else None)
            for width in WIDTHS:
                page.set_viewport_size({'width':width,'height':900})
                response=page.goto(BASE+'/',wait_until='networkidle');assert response.status==200
                metrics=page.evaluate('''()=>{const rect=s=>{const x=document.querySelector(s).getBoundingClientRect();return {top:x.top,bottom:x.bottom,width:x.width,height:x.height}};const button=document.querySelector('.r3-official-hero>div:first-of-type>a');const b=button.getBoundingClientRect();return {title:rect('.r3-official-hero h1'),controls:rect('.r3-official-hero>div:first-of-type'),devices:rect('.r3-devices'),laptop:rect('.r3-laptop'),phone:rect('.r3-market-phone'),buttonVisible:document.elementFromPoint(b.x+b.width/2,b.y+b.height/2)?.closest('a')===button,overflow:Math.max(document.documentElement.scrollWidth,document.body.scrollWidth)>innerWidth+1}}''')
                assert min(metrics['laptop']['top'],metrics['phone']['top'])>=metrics['controls']['bottom']+16,(width,metrics)
                assert metrics['devices']['width']<=940 and metrics['buttonVisible'] and not metrics['overflow'],(width,metrics)
                reply=page.locator('.r3-chat-laptop>article').inner_text()
                assert 'crema facial (3 unidades)' in reply and 'botella térmica (8 unidades)' in reply
                assert 'Este ejemplo' not in reply and 'si compras' not in reply
                results.append({'page':'official','width':width,'controls_visible':True,'devices_clear_of_title':True,'device_height':metrics['devices']['height'],'overflow':False})
                if width in [1317,390]:page.screenshot(path=str(OUTPUT/(phase+'-portada-'+str(width)+'.png')),full_page=True)
                response=page.goto(BASE+'/laventanita',wait_until='networkidle');assert response.status==200
                metrics=page.evaluate('''()=>{const style=s=>getComputedStyle(document.querySelector(s));return {navigation:style('.r2-public-nav').display,linkGap:parseFloat(style('.r2-public-nav nav').gap),heroHeight:document.querySelector('.r2-hero').getBoundingClientRect().height,heroBackground:style('.r2-hero').backgroundColor,about:style('.r2-about').display,cards:style('.r2-service-grid').display,columns:style('.r2-service-grid').gridTemplateColumns.split(' ').length,overflow:Math.max(document.documentElement.scrollWidth,document.body.scrollWidth)>innerWidth+1}}''')
                assert metrics['navigation']=='flex' and metrics['linkGap']>=12 and metrics['heroHeight']>=430,(width,metrics)
                assert metrics['heroBackground']!='rgba(0, 0, 0, 0)' and metrics['about']=='grid' and metrics['cards']=='grid'
                assert metrics['columns']==(1 if width<=700 else 3) and not metrics['overflow'],(width,metrics)
                assert page.locator('script[src*="restructure-r2.js"]').count()==0
                logo=page.locator('.r2-wordmark img');assert logo.get_attribute('alt')=='LA VENTANITA'
                assert 'sabor-urbano-logo.svg' in logo.get_attribute('src')
                assert page.locator('.r2-platform-offer img').get_attribute('alt')=='FISITAAP'
                catalog=page.locator('.r2-hero .btn').get_attribute('href')
                results.append({'page':'laventanita','width':width,'styled_navigation':True,'hero_height':metrics['heroHeight'],'service_columns':metrics['columns'],'store_logo':True,'extra_pos_script':False,'overflow':False})
                if width in [1317,390]:page.screenshot(path=str(OUTPUT/(phase+'-ventanita-'+str(width)+'.png')),full_page=True)
                if width in [1317,390]:
                    page.locator('.r2-hero .btn').click();page.wait_for_url(catalog);assert page.locator('body').inner_text()
                    assert page.locator('link[href*="restructure-r2.css"]').count()==0
            # Pending businesses receive the styling correction without activating.
            version=sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=101')[0]['version']
            sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=101')
            try:
                page.goto(BASE+'/laventanita',wait_until='networkidle')
                assert page.locator('.r2-hero').evaluate('(e)=>e.getBoundingClientRect().height>=430')
            finally:sql('UPDATE fisitaap_tenant_updates180 SET version=? WHERE tenant_id=101',[version])
            page.goto(BASE+'/owner-login');page.locator('[name=email]').fill('owner@r2.invalid');page.locator('[name=password]').fill(PASSWORD)
            page.locator('form button[type=submit],form button.btn').first.click();page.wait_for_url('**/admin')
            page.goto(BASE+'/admin/ventas',wait_until='networkidle')
            assert page.evaluate("getComputedStyle(document.documentElement).getPropertyValue('--primary').trim()")=='#203f57'
            assert page.locator('script[src*="restructure-r2.js"]').count()==1
            browser.close()
    finally:sql('UPDATE tenants SET logo=? WHERE id=101',[prior])
    assert not errors,errors;assert not missing,missing
    if packaged:assert assets and all('/releases/1.8.0-r3/assets/' in a['url'] and a['http']==200 for a in assets)
    path=ROOT/'actualizaciones/1.8.0/QA-PUBLICO-R3.json'
    report={'phases':[],'scope':'real Chromium, PHP/MariaDB fictional fixture; public screenshots and live geometry, not production or physical Brave'}
    if packaged:report=json.loads(path.read_text());assert report['phases'][0]['name']=='canonical'
    report['phases'].append({'name':phase,'public_pages':len(results),'cases':results,'javascript_errors':errors,'missing_assets':missing,'script_or_style_responses':len(assets),'catalog_cta_checked_at':[1317,390],'legacy_business_styled':True,'admin_theme_preserved':True,'store_logo_restored_after_test':True})
    path.write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
    print('PASS',phase,len(results),'public layouts, 9 widths, visible CTA/title, store branding/catalog, no extra POS JS, legacy state and admin theme',flush=True)

if __name__=='__main__':main()
