"""R9 demo SINPE uploads, consecutive orders, isolation and private file cleanup."""
import json,pathlib,secrets,sys
from playwright.sync_api import sync_playwright,expect
from PIL import Image
from usability_r7 import ensure_demo_templates
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql

ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');ensure_demo_templates();checks=[]
    proof=pathlib.Path('/workspace/fisitaap-env/demo-proof-r9.png');Image.new('RGB',(500,300),'white').save(proof)
    baseline=sql('SELECT tenant_id,product_id,quantity FROM product_inventory WHERE tenant_id IN(101,901,902) ORDER BY tenant_id,product_id')
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
        for kind in ['tienda','restaurante']:
            ctx=browser.new_context();page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto('http://localhost:18080/demo');page.get_by_role('button',name='Entrar a la tienda demo →' if kind=='tienda' else 'Entrar al restaurante demo →',exact=True).click();page.wait_for_url('**/catalogo');token=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")');tenant=sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?',[token])[0]['tenant_id'];page.locator('[data-category-open]').first.click();outputs=[]
            for attempt in range(2):
                page.locator('[data-product] [data-add-cart]').first.click();page.locator('[data-open-cart]').first.click();form=page.locator('#checkoutForm');form.locator('[name=delivery_type]').select_option('coordinated');form.locator('[name=address]').fill('Dirección de ejemplo aislada');form.locator('[name=maps_url]').fill('https://www.google.com/maps?q=9.93,-84.08');form.locator('[name=payment]').select_option('SINPE Móvil');form.locator('[name=sinpe_proof]').set_input_files(proof)
                with page.expect_response(lambda r:r.url.endswith('/api/checkout')) as res:form.locator('button[type=submit]').click()
                out=res.value.json();assert out.get('ok') and not out.get('duplicate'),out;outputs.append(out);expect(page.locator('#demoWhatsapp')).to_be_visible();expect(page.locator('#demoWhatsapp')).to_contain_text('PIN de entrega: '+out['delivery_pin']);page.get_by_role('button',name='Cerrar ejemplo',exact=True).click();expect(form.locator('button[type=submit]')).to_be_enabled()
            assert outputs[0]['order_id']!=outputs[1]['order_id'];assert len(sql('SELECT id FROM orders WHERE tenant_id=?',[tenant]))==2
            files=sql('SELECT path FROM fisitaap_order_uploads180 WHERE tenant_id=?',[tenant]);assert len(files)==2
            # The demo can never use its newly allowed address/proof endpoints against a real business.
            foreign=page.evaluate(r"async()=>{const r=await fetch(window.FISITAPP.base.replace(/\/$/,'')+'/api/delivery-addresses180',{method:'POST',body:new URLSearchParams({csrf:window.FISITAPP.csrf,tenant:'laventanita',phone:'88888888'})});return r.status}");assert foreign==404,foreign
            page.goto(page.url.split('/demo/s/')[0]+'/demo/s/'+token+'/account');page.get_by_role('button',name='Terminar y descartar',exact=True).click();page.wait_for_url('**/demo');assert not sql('SELECT id FROM tenants WHERE id=?',[tenant]);assert not sql('SELECT path FROM fisitaap_order_uploads180 WHERE tenant_id=?',[tenant]);assert all(not (pathlib.Path('/workspace/fisitaap-r2-test')/f['path']).exists() for f in files);assert not errors,errors
            checks.append(kind+': two SINPE demo orders without reload have distinct IDs/PIN and private proof, cross-business lookup is denied and logout removes the clone plus proofs')
            ctx.close()
        browser.close()
    assert sql('SELECT tenant_id,product_id,quantity FROM product_inventory WHERE tenant_id IN(101,901,902) ORDER BY tenant_id,product_id')==baseline
    checks.append('real fictional business and both demo template inventories remain unchanged')
    (ROOT/'actualizaciones/1.8.0/QA-DEMOS-R9.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'fictional PHP/MariaDB/Chromium; no external services'},indent=2)+'\n');print('PASS',len(checks),'demo operation groups')
if __name__=='__main__':main()
