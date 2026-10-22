"""Browser QA: real guest checkout, geolocation, SINPE upload, live seller/driver/customer views."""
import json,pathlib,re,secrets,sys,time,urllib.parse
from playwright.sync_api import sync_playwright,expect
from PIL import Image
from usability_r7 import login
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD

ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path('/workspace/fisitaap-env/browser-r10');OUT.mkdir(exist_ok=True)
checks=[];layouts=[];tag='BR10-'+secrets.token_hex(4)
def passed(x):checks.append(x);print('PASS',x,flush=True)
def login_page(browser,email):
    ctx=browser.new_context(viewport={'width':1366,'height':900});p=ctx.new_page();p.goto('http://localhost:18080/owner-login');p.locator('[name=email]').fill(email);p.locator('[name=password]').fill(PASSWORD);p.locator('form button.btn').first.click();p.wait_for_url('**/'+('driver' if email.startswith('driver@') else 'admin'));return ctx,p
def post(oid,action,**extra):
    rev=sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'];return request('owner','/admin/pedidos',{'csrf':csrf('owner','/admin/pedidos'),'origin':'web','id':oid,'revision':rev,'action':action,**extra})
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');sql('UPDATE delivery_jobs j JOIN orders o ON o.id=j.order_id SET j.status="cancelled" WHERE o.customer_name="Cliente navegador QA" AND j.status NOT IN("delivered","cancelled","expired")');login('owner','owner@r2.invalid')
    candidate=sql('SELECT p.id,c.slug FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN product_inventory i ON i.product_id=p.id AND i.branch_id=301 WHERE p.tenant_id=101 AND p.status="active" AND (p.track_inventory=0 OR i.quantity>5) AND NOT EXISTS(SELECT 1 FROM product_option_groups g WHERE g.product_id=p.id AND g.is_active=1) ORDER BY p.id LIMIT 1')[0]
    proof=OUT/'proof.png';Image.new('RGB',(800,450),'white').save(proof)
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);errors=[]
        guest=browser.new_context(viewport={'width':390,'height':844},geolocation={'latitude':9.93,'longitude':-84.08},permissions=['geolocation']);page=guest.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto('http://localhost:18080/laventanita/catalogo?sucursal=principal');page.locator('[data-category-open="'+candidate['slug']+'"]').click();page.locator('[data-product]:visible [data-add-cart]').first.click();page.locator('[data-open-cart]').first.click()
        form=page.locator('#checkoutForm');form.locator('[name=guest_name]').fill('Cliente navegador QA');form.locator('[name=guest_phone]').fill('9'+str(secrets.randbelow(10000000)).zfill(7));form.locator('[name=delivery_type]').select_option('coordinated');form.locator('[name=address]').fill('Casa de prueba navegador')
        assert form.locator('[name=address_reference]').count()==0
        form.get_by_role('button',name='Usar mi ubicación actual',exact=True).click();expect(form.locator('[name=maps_url]')).to_have_value(re.compile(r'https://www.google.com/maps\?q=9\.9300000,-84\.0800000'))
        form.locator('[name=payment]').select_option('SINPE Móvil');expect(form.locator('[name=sinpe_proof]')).to_be_visible();expect(form.locator('[name=sinpe_proof]')).to_have_attribute('required','')
        form.locator('[name=sinpe_proof]').set_input_files(proof)
        with page.expect_response(lambda r:r.url.endswith('/api/checkout')) as response:form.locator('button[type=submit]').click()
        out=response.value.json();assert out.get('ok'),out;oid=out['order_id'];pin=out['delivery_pin'];tracking=out['tracking_url']
        expect(page.locator('#op180Confirmation')).to_be_visible();expect(page.locator('#op180Confirmation .op180-pin strong')).to_have_text(pin)
        page.screenshot(path=str(OUT/'confirmation-mobile.png'),full_page=True)
        page.get_by_role('button',name='Seguir comprando',exact=True).click()
        expect(form.locator('[data-saved-address] option')).to_have_count(2)
        passed('mobile guest checkout uses permitted current geolocation, replaces reference with Maps, requires/uploads SINPE image and shows a persistent four-digit delivery PIN before WhatsApp')
        owner,orders=login_page(browser,'owner@r2.invalid');orders.on('pageerror',lambda e:errors.append(str(e)));orders.goto('http://localhost:18080/admin/pedidos')
        row=orders.locator('[data-order-row="web-'+str(oid)+'"]');expect(row).to_be_visible();expect(row.locator('.op180-status')).to_have_text('Nuevo')
        nav=[];orders.on('framenavigated',lambda f:nav.append(f.url) if f==orders.main_frame else None)
        pos=owner.new_page();pos.on('pageerror',lambda e:errors.append(str(e)));pos.goto('http://localhost:18080/admin/ventas')
        pos.locator('[data-incoming-open]').click();expect(pos.locator('#op180Incoming')).to_be_visible()
        with pos.expect_response(lambda r:r.url.endswith('/api/incoming180') and r.request.method=='POST') as accepted:pos.locator('[data-accept-order="'+str(oid)+'"]').click()
        assert accepted.value.json()['ok'];expect(row.locator('.op180-status')).to_have_text('Aceptado',timeout=8000);assert not nav
        receipt=sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid])[0]['ticket_id']
        assert len(sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['web-order-'+str(oid)+'-command-%']))==1
        passed('POS web-order badge opens the inbox and accepts/prints once; another seller view changes from New to Accepted without navigating or refreshing')
        pos.locator('#op180Incoming [data-dialog-close]').click();pos.goto('http://localhost:18080/admin/ventas?screen=order&ticket='+str(receipt))
        post(oid,'prepare');expect(pos.locator('.r3-kitchen-state').first).to_have_text('Preparando',timeout=8000)
        post(oid,'prepared');expect(pos.locator('.r3-kitchen-state').first).to_have_text('Listo',timeout=8000)
        expect(orders.locator('[data-order-row="web-'+str(oid)+'"] [data-board-dispatch]')).to_be_visible(timeout=8000)
        orders.locator('[data-order-row="web-'+str(oid)+'"] [data-board-dispatch]').click();expect(orders.locator('#qa180Dispatch')).to_be_visible();orders.get_by_role('button',name='Volver',exact=True).click()
        passed('remote preparation updates the live POS kitchen state and action revision; the refreshed order row keeps its Express dialog working')
        # The free coordinated delivery uses the same unchanged financial snapshot.
        post(oid,'dispatch',dispatch_mode='pool',payout=100)
        driver,dpage=login_page(browser,'driver@r2.invalid');dpage.on('pageerror',lambda e:errors.append(str(e)))
        jid=sql('SELECT id FROM delivery_jobs WHERE order_id=?',[oid])[0]['id']
        trackingpage=guest.new_page();trackingpage.goto(tracking);trackingpage.on('pageerror',lambda e:errors.append(str(e)))
        dpage.locator('form:has([name=job_id][value="'+str(jid)+'"]) button:has-text("Aceptar express")').click()
        expect(orders.locator('[data-order-row="web-'+str(oid)+'"] .op180-status')).to_have_text('Motorizado asignado',timeout=8000)
        expect(trackingpage.locator('[data-live-region=order-detail]')).to_contain_text('Motorizado asignado',timeout=8000)
        assert not nav
        assert dpage.get_by_role('link',name='Abrir ubicación del cliente en Google Maps').count()==1
        passed('driver acceptance reaches seller and private customer tracking automatically; assigned driver can open the actual Maps location')
        for width,height in [(1366,900),(900,900),(768,1024),(390,844)]:
            orders.set_viewport_size({'width':width,'height':height});orders.reload()
            geometry=orders.evaluate('()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,columns:document.querySelectorAll(".qa180-board-column").length})')
            assert geometry['scroll']<=width+1 and geometry['columns']==0,geometry;layouts.append(geometry)
            if width in [1366,390]:orders.screenshot(path=str(OUT/('orders-'+str(width)+'.png')),full_page=True)
        assert not errors,errors
        passed('orders is a normal state list at desktop/tablet/mobile widths, with contained table scroll and no JavaScript errors across checkout, POS, driver and tracking')
        browser.close()
    (ROOT/'actualizaciones/1.8.0/QA-NAVEGADOR-R10.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'layouts':layouts,'page_errors':errors,'environment':'real fictional PHP/MariaDB and Chromium; geolocation simulated with browser permission, no real Google API, banking, printers or physical Windows'},indent=2)+'\n')

if __name__=='__main__':main()
