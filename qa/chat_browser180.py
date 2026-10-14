"""Real Chromium conversation UX, store branding and master confirmation workflow."""
import datetime,json,pathlib,re,sys
from playwright.sync_api import sync_playwright,expect
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import PASSWORD,sql

BASE='http://localhost:18080';ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=pathlib.Path('/workspace/fisitaap-env/chat-browser-r4')
def send(page,text):
    page.locator('#fc-message').fill(text);page.locator('#fc-message').press('Enter')
    expect(page.locator('[data-fc-form]')).to_have_attribute('aria-busy','false',timeout=12000)
    return page.locator('.fc-bubble').last.inner_text()
def login(page,email):
    page.goto(BASE+'/owner-login');page.locator('[name=email]').fill(email);page.locator('[name=password]').fill(PASSWORD);page.locator('form button[type=submit],form button.btn').first.click();page.wait_for_url(lambda u:'/owner-login' not in u)

def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');OUT.mkdir(exist_ok=True)
    packaged='--packaged' in sys.argv
    if packaged:assert 'releases/1.8.0-r4' in pathlib.Path('/workspace/fisitaap-r2-test/index.php').read_text()
    logo=sql('SELECT logo FROM tenants WHERE id=101')[0]['logo'];sql('UPDATE tenants SET logo="assets/demo/sabor-urbano-logo.svg" WHERE id=101')
    original=sql('SELECT plan,expires_at FROM tenants WHERE id=101')[0]
    cases=[];errors=[];missing=[]
    try:
        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
            ctx=browser.new_context();page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.on('response',lambda r:missing.append(r.url) if r.request.resource_type in ['stylesheet','script'] and r.status>=400 else None)
            for width in [1366,768,390,360]:
                page.set_viewport_size({'width':width,'height':844});page.goto(BASE+'/',wait_until='networkidle')
                assert page.locator('[data-fc]').count()==1
                if packaged:assert '/releases/1.8.0-r4/assets/fisichat.js' in page.locator('script[src*="fisichat.js"]').get_attribute('src')
                page.locator('.fc-launch').click();expect(page.locator('[data-fc]')).to_be_visible()
                assert page.locator('#fc-message').is_enabled()
                if width==1366:
                    page.locator('[data-fc-suggest]').filter(has_text='¿Qué es FISITAAP?').click();expect(page.locator('.fc-user')).to_have_count(1);expect(page.locator('[data-fc-form]')).to_have_attribute('aria-busy','false')
                    assert 'FISITAAP reúne' in page.locator('.fc-bubble').last.inner_text()
                    assert 'restaurante' in send(page,'Tengo un restaurante')
                    assert 'cotizar lo que me comentaste' in send(page,'¿Y cuánto cuesta?')
                metrics=page.locator('[data-fc]').evaluate('(e)=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,overflow:e.scrollWidth>e.clientWidth+1}}')
                assert metrics['x']>=0 and metrics['y']>=0 and metrics['right']<=width and metrics['bottom']<=844 and not metrics['overflow'],(width,metrics)
                assert page.locator('#fc-message').evaluate('(e)=>parseFloat(getComputedStyle(e).fontSize)')>=16
                page.screenshot(path=str(OUT/('public-'+str(width)+'.png')))
                page.locator('#fc-message').press('Escape');expect(page.locator('[data-fc]')).not_to_be_visible();expect(page.locator('.fc-launch')).to_have_attribute('aria-expanded','false')
                cases.append({'context':'platform','width':width,'viewport_fit':True,'enabled_without_key':True,'escape_focus_aria':True})
            page.set_viewport_size({'width':390,'height':844});page.goto(BASE+'/laventanita/catalogo?sucursal=principal',wait_until='networkidle');page.locator('.fc-launch').click()
            cfg=page.locator('[data-fc-config]').text_content();assert json.loads(cfg)['profile']=='cliente'
            assert page.locator('.fc-avatar img').get_attribute('alt')=='LA VENTANITA'
            assert 'LA VENTANITA' in send(page,'¿Qué productos venden?')
            page.reload(wait_until='networkidle');page.locator('.fc-launch').click();assert page.locator('.fc-user').last.inner_text()=='¿Qué productos venden?'
            page.locator('[data-fc-reset]').click();expect(page.locator('.fc-user')).to_have_count(0)
            page.screenshot(path=str(OUT/'store-390.png'))
            cases.append({'context':'store','width':390,'own_logo':True,'real_product_reply':True,'history_reload_reset':True})
            malicious='<img src=x onerror=window.__fc_xss=1>'
            send(page,malicious);assert page.locator('.fc-user').last.inner_text()==malicious and page.evaluate('window.__fc_xss') is None
            page.route('**/api/fisichat-r2',lambda route:route.fulfill(status=403,content_type='text/html',body='<html>error</html>'))
            page.locator('#fc-message').fill('Mi consulta pendiente');page.locator('#fc-message').press('Enter');expect(page.locator('[data-fc-form]')).to_have_attribute('aria-busy','false')
            assert page.locator('#fc-message').input_value()=='Mi consulta pendiente' and 'incompleta' in page.locator('[data-fc-status]').inner_text()
            page.unroute('**/api/fisichat-r2')
            cases.append({'context':'errors','html_not_executed':True,'failed_transport_keeps_draft':True,'controls_reenabled':True})
            page.goto(BASE+'/asistente?perfil=vendedor',wait_until='networkidle');assert page.locator('.fc-full').is_visible()
            assert json.loads(page.locator('[data-fc-config]').text_content())['profile']=='tecnico';assert 'Windows y Android' in send(page,'Quiero Android sin internet')
            cases.append({'context':'fullpage','legacy_alias':True,'working_reply':True})
            master=browser.new_context(viewport={'width':1366,'height':900});tab=master.new_page();tab.on('pageerror',lambda e:errors.append(str(e)));login(tab,'master@r2.invalid')
            tab.goto(BASE+'/master',wait_until='networkidle');tab.locator('.fc-launch').click()
            assert 'administración' in tab.locator('.fc-header p').inner_text()
            assert 'Resumen de FISITAAP' in send(tab,'Resumen de FISITAAP')
            assert 'Vamos a revisar' in send(tab,'Quiero cambiar un plan')
            expect(tab.locator('[data-fc-plan]')).to_be_visible();assert tab.locator('[data-fc-business]').input_value()==''
            tab.locator('[data-fc-business]').select_option('laventanita');tab.locator('[data-fc-newplan]').fill('CHAT BROWSER QA')
            tab.locator('[data-fc-prepare]').click();expect(tab.locator('[data-fc-proposal]')).to_be_visible()
            assert sql('SELECT plan,expires_at FROM tenants WHERE id=101')[0]==original
            tab.screenshot(path=str(OUT/'master-review-1366.png'));tab.locator('[data-fc-confirm]').click();expect(tab.locator('[data-fc-status]')).to_contain_text('confirmado')
            assert sql('SELECT plan FROM tenants WHERE id=101')[0]['plan']=='CHAT BROWSER QA'
            tab.set_viewport_size({'width':390,'height':844});tab.screenshot(path=str(OUT/'master-390.png'))
            assert tab.locator('[data-fc]').evaluate('(e)=>e.getBoundingClientRect().right<=innerWidth')
            cases.append({'context':'master','private_context':True,'selected_business_required':True,'proposal_readonly':True,'explicit_confirmation_written':True,'mobile_fit':True})
            browser.close()
    finally:
        sql('UPDATE tenants SET logo=?,plan=?,expires_at=? WHERE id=101',[logo,original['plan'],original['expires_at']]);sql('DELETE FROM rate_limits')
    assert not errors,errors;assert not missing,missing
    (ROOT/'actualizaciones/1.8.0/QA-FISICHAT-NAVEGADOR-R4.json').write_text(json.dumps({'passed':len(cases),'delivery':'installed R4 payload' if packaged else 'canonical source','cases':cases,'javascript_errors':errors,'missing_assets':missing,'scope':'Chromium/PHP/MariaDB fictitious data; actual local guide HTTP, scoped admin confirmation; not physical Brave or production'},indent=2)+'\n')
    print('PASS',len(cases),'chat browser flows, four sizes, real messages/history, XSS/error recovery, master review and confirmation',flush=True)

if __name__=='__main__':main()
