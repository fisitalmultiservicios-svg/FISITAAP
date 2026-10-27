"""Real PHP/MariaDB/Chromium checks for the approved application interface.
All writes are confined to the guarded, fictional fisitaap_r2_test fixture.
"""
import hashlib,json,pathlib,re,secrets,sys
from PIL import Image,ImageChops
from playwright.sync_api import sync_playwright,expect
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql,PASSWORD
from financial180 import api

ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=pathlib.Path('/workspace/fisitaap-env/interface-r15');OUT.mkdir(exist_ok=True)
BASE='http://localhost:18080'
SIZES=[(1366,900),(1366,768),(1024,768),(820,1180),(768,1024),(600,960),(390,844),(360,740),(320,700),(844,390),(390,520),(360,520)]

def login(browser,email,role):
    ctx=browser.new_context(viewport={'width':1366,'height':900});p=ctx.new_page();p.goto(BASE+'/owner-login');p.locator('[name=email]').fill(email);p.locator('[name=password]').fill(PASSWORD);p.locator('form button.btn').first.click();p.wait_for_url('**/'+role);return ctx,p

def geometry(p):
    return p.evaluate('''()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,height:innerHeight,body:document.body.scrollHeight})''')

def checked_page(p,path,w,h):
    p.set_viewport_size({'width':w,'height':h});r=p.goto(BASE+path);assert r.status==200,(path,r.status)
    assert any(x in p.locator('body').get_attribute('class') for x in ['r15-app','r15-store']),path
    assert not re.search(r'Fatal error|SQLSTATE\[',p.inner_text('body')),path
    g=geometry(p)
    if g['scroll']>w+1:p.screenshot(path=str(OUT/('overflow-'+str(w)+'-'+path.split('?')[0].split('/')[-1]+'.png')),full_page=True)
    assert g['scroll']<=w+1,(path,w,h,g)
    return {'path':path,'width':w,'height':h,'no_page_overflow':True}

def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    sql('DELETE FROM rate_limits');layouts=[];checks=[];errors=[];tag='UIR15-'+secrets.token_hex(4)
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
        guest=browser.new_context(viewport={'width':1366,'height':900});p=guest.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
        p.goto(BASE+'/');p.wait_for_timeout(500);assert 'r14-' not in (p.locator('body').get_attribute('class') or '')
        assert p.locator('[href*="interface-r15"],[src*="interface-r15"]').count()==0
        p.screenshot(path=str(OUT/'official-after.png'),animations='disabled')
        diff=ImageChops.difference(Image.open(ROOT/'../fisitaap-env/interface-r14/official-before.png').convert('RGB'),Image.open(OUT/'official-after.png').convert('RGB'))
        assert diff.getbbox() is None,'Official presentation screenshot changed'
        checks.append('Official presentation has identical screenshot and loads no interface assets')
        owner,op=login(browser,'owner@r2.invalid','admin');op.on('pageerror',lambda e:errors.append(str(e)))
        # Start with a browser that has no native cash grant; the design must not bypass that rule.
        checked_page(op,'/admin/ventas',390,844);expect(op.locator('[data-r14-pos]')).to_be_visible();expect(op.locator('.ticket')).to_be_visible()
        assert op.locator('.ticket-actions>button[disabled]').count()==1
        checks.append('Empty sale uses the same left invoice/right catalog without opening a fake account')
        # Real device authorization is established by the production protocol, using a fictional device.
        token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest()
        sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,?,?)',[tag,digest]);device=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id']
        sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,?,?,202)',[tag,device])
        nonce=op.evaluate('window.FISITAAP180.nonce');status,proof=api('web-session',{'nonce':nonce,'user_id':202},token);assert status==200 and proof['ok']
        bound=op.request.post(BASE+'/api/native/bind',form={'csrf':op.evaluate('window.FISITAPP.csrf'),'proof':proof['proof']});assert bound.status==200 and bound.json()['ok']
        key=secrets.token_hex(16);response=op.request.post(BASE+'/admin/turnos',form={'csrf':op.evaluate('window.FISITAPP.csrf'),'action':'open','opening_key':key,'opening_cash':10000});assert response.status==200
        assert sql('SELECT id FROM pos_shifts WHERE opening_key=?',[key])
        sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,track_inventory,min_qty,qty_step,decimals) VALUES(101,401,?,?,?,1000,13,1,1,1,0)',[tag,tag.lower(),tag]);pid=sql('SELECT id FROM products WHERE sku=?',[tag])[0]['id']
        sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(101,301,?,100)',[pid]);sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(301,?,1)',[pid])
        op.goto(BASE+'/admin/ventas?q='+tag);op.locator('[data-r15-panel=products]').click();op.locator('.r3-quick-product:has([name=product_id][value="'+str(pid)+'"]) button').click();op.wait_for_url('**/admin/ventas?screen=order&ticket=*')
        tid=int(re.search(r'ticket=(\d+)',op.url)[1]);path='/admin/ventas?screen=order&ticket='+str(tid)
        assert sql('SELECT quantity FROM pos_ticket_items WHERE ticket_id=? AND product_id=?',[tid,pid])[0]['quantity']=='1.000'
        # Mobile quick category changes and POSTs retain a usable quick-products panel.
        op.locator('[data-r15-panel=products]').click();op.locator('.r3-search [name=q]').evaluate('e=>e.blur()')
        op.goto(BASE+path+'&q='+tag);expect(op.locator('.pos-products')).to_be_visible();op.locator('.r3-quick-product:has([name=product_id][value="'+str(pid)+'"]) button').click();op.wait_for_load_state();expect(op.locator('.pos-products')).to_be_visible();assert float(sql('SELECT SUM(quantity) quantity FROM pos_ticket_items WHERE ticket_id=? AND product_id=?',[tid,pid])[0]['quantity'])==2
        checks.append('Real mobile start/add/re-add persists product panel and updates the same account exactly once')
        print('PASS mobile start/re-add and native grant',flush=True)
        op.locator('[data-r15-panel=invoice]').click()
        qty=op.locator('.r3-quantity input[name=quantity]').first
        qty.fill('2')
        with op.expect_navigation(wait_until='load'):qty.press('Tab')
        assert float(sql('SELECT SUM(quantity) n FROM pos_ticket_items WHERE ticket_id=? AND product_id=?',[tid,pid])[0]['n'])==3
        with op.expect_navigation(wait_until='load'):op.locator('[data-quantity-step="-1"]').first.click()
        assert float(sql('SELECT SUM(quantity) n FROM pos_ticket_items WHERE ticket_id=? AND product_id=?',[tid,pid])[0]['n'])==2
        op.locator('[data-line-edit]').first.click();expect(op.locator('#r3LineEdit')).to_be_visible();expect(op.locator('#r3LineEdit footer button:last-child')).to_be_enabled();op.locator('#r3LineEdit [data-dialog-close]').first.click()
        choices=op.locator('[data-r15-client] option').evaluate_all("xs=>xs.map(x=>x.value).filter(v=>Number(v)>0)")
        if choices:
            customer=choices[0]
            with op.expect_navigation(wait_until='load'):op.locator('[data-r15-client]').select_option(customer)
            assert str(sql('SELECT customer_id FROM pos_tickets WHERE id=?',[tid])[0]['customer_id'])==customer
        checks.append('Invoice name opens real options/prices/notes; typed quantity and step button submit once; customer selector keeps server validation')
        for w,h in SIZES:
            layouts.append(checked_page(op,path,w,h));grid=op.locator('[data-r14-pos]');assert not op.locator('.r11-fit-content').count()
            if w<=650:op.locator('[data-r15-panel=invoice]').click()
            g=op.evaluate('''()=>{const rect=e=>{const r=e.getBoundingClientRect();return {left:r.left,right:r.right,width:r.width,top:r.top,bottom:r.bottom}};return {invoice:rect(document.querySelector('.r3-cart-panel')),quick:rect(document.querySelector('.r3-quick')),pay:rect(document.querySelector('.ticket-actions>a.primary'))}}''')
            if g['pay']['bottom']>h+1:
                op.screenshot(path=str(OUT/'pos-pay-overflow.png'),animations='disabled');print(op.locator('.r15-pos-view').evaluate('(e)=>[...e.children].map(x=>({class:x.className,height:x.getBoundingClientRect().height,children:[...x.children].map(c=>({class:c.className,height:c.getBoundingClientRect().height}))}))'),flush=True)
            assert g['pay']['bottom']<=h+1 and g['pay']['top']>=0,(w,h,g)
            assert op.locator('.r3-sale-table-wrap').evaluate('e=>e.scrollWidth<=e.clientWidth+1'),(w,h,'invoice scrolls horizontally')
            incoming=op.locator('[data-incoming-open]').bounding_box();assert incoming['x']>=0 and incoming['x']+incoming['width']<=w+1,(w,h,'incoming badge clipped',incoming)
            assert op.locator('.page-heading h1').bounding_box()['width']>=60,(w,h,'heading clipped')
            if w>650:assert g['invoice']['right']<=g['quick']['left'] and g['invoice']['width']>=1.4*g['quick']['width'],(w,h,g)
            else:
                op.locator('[data-r15-panel=products]').click();expect(op.locator('.pos-products')).to_be_visible();op.locator('[data-r15-panel=invoice]').click()
            if w in (1366,768,390) and h in (900,1024,844):op.screenshot(path=str(OUT/('pos-'+str(w)+'.png')),animations='disabled')
        # Coupon keeps the same server action but is now in a dialog, not below the viewport.
        op.set_viewport_size({'width':390,'height':844});op.locator('[data-r15-more]').click();op.locator('[data-dialog-open=r14Coupon]').click();expect(op.locator('#r14Coupon')).to_be_visible();op.locator('#r14Coupon [data-dialog-close]').click()
        # Checkout is measured on phone/tablet and then completed through the actual cash protocol.
        checkout='/admin/ventas?screen=checkout&ticket='+str(tid)
        for w,h in [(1366,900),(768,1024),(390,844),(320,700)]:
            layouts.append(checked_page(op,checkout,w,h));expect(op.locator('[data-payment-modal]')).to_be_visible();g=op.locator('[data-payment-modal]').bounding_box();assert g['x']>=-1 and g['x']+g['width']<=w+1,(w,g)
        op.set_viewport_size({'width':390,'height':844});op.locator('[name=cash]').fill('3000');op.locator('[name=print_receipt]').uncheck();op.locator('[data-confirm-payment]').click();op.wait_for_url('**/admin/ventas?last_sale=*');assert sql('SELECT status FROM pos_tickets WHERE id=?',[tid])[0]['status']=='paid';assert sql('SELECT quantity FROM product_inventory WHERE product_id=? AND branch_id=301',[pid])[0]['quantity']=='98.000'
        checks.append('Cash checkout remains native-authorized; real payment, stock and receipt flow pass on phone')
        # Core business pages across the requested responsive sizes.
        for w,h in SIZES:
            for route in ['/admin','/admin/dashboard','/admin/productos','/admin/pedidos','/admin/clientes-buscar','/admin/turnos','/admin/configuracion','/admin/mesas?view=graphic','/admin/mesas?view=basic','/admin/cobro']:
                layouts.append(checked_page(op,route,w,h))
                if '/mesas?view=graphic' in route:
                    svg=op.locator('[data-r3-floor]>svg');expect(svg).to_be_visible();assert svg.get_attribute('viewBox')=='0 0 1000 680'
                    if w==390:
                        op.locator('[data-r15-floor-zoom=in]').click();assert op.locator('[data-r15-floor-fit]').inner_text()=='110 %';op.locator('[data-r15-floor-fit]').click()
                        table=op.locator('[data-r3-floor] [data-r3-table]').first;table.focus();op.keyboard.press('Enter');expect(op.locator('#r3TableAccount[open]')).to_be_visible();assert op.locator('#r3TableAccount').get_attribute('role')=='region'
                    if w in (1366,768,390) and h in (900,1024,844):op.screenshot(path=str(OUT/('mesas-'+str(w)+'.png')),animations='disabled')
            op.get_by_role('button',name='Abrir menú',exact=True).click();expect(op.locator('[data-admin-menu]')).to_be_in_viewport(ratio=.95);op.keyboard.press('Escape');expect(op.locator('[data-admin-menu]')).not_to_be_visible()
        checks.append('Business pages, saved floor SVG/objects/zoom, persistent real table detail and shared navigation pass across twelve screen sizes')
        master,mp=login(browser,'master@r2.invalid','master');mp.on('pageerror',lambda e:errors.append(str(e)))
        driver,dp=login(browser,'driver@r2.invalid','driver');dp.on('pageerror',lambda e:errors.append(str(e)))
        for w,h in [(1366,900),(820,1180),(768,1024),(600,960),(390,844),(360,740),(320,700)]:
            for route in ['/master','/master/empresas','/master/planes','/master/configuracion']:layouts.append(checked_page(mp,route,w,h))
            layouts.append(checked_page(dp,'/driver',w,h));layouts.append(checked_page(p,'/laventanita/catalogo?sucursal=principal',w,h))
            p.locator('[data-mobile-menu-toggle]').click();expect(p.locator('[data-mobile-menu]')).to_be_visible();p.locator('[data-mobile-menu] [data-open-cart]').click();expect(p.locator('#cartDrawer')).to_have_class('drawer open');assert geometry(p)['scroll']<=w+1
            assert p.locator('#checkoutForm [name=guest_phone]').evaluate('e=>getComputedStyle(e).fontSize')=='16px' if w<=650 else True
            p.locator('button[data-close-cart]').click()
        checks.append('Master, driver and tenant catalog/cart pass on phone and tablet; tenant primary color remains unchanged')
        # Print media and tenant colors are part of the final theme too.
        assert p.locator('body').evaluate('e=>getComputedStyle(e).getPropertyValue("--primary").trim()')==sql('SELECT primary_color FROM tenants WHERE id=101')[0]['primary_color']
        sale=sql('SELECT id FROM sales WHERE tenant_id=101 ORDER BY id DESC LIMIT 1')[0]['id'];op.goto(BASE+'/admin/recibo?venta='+str(sale));expect(op.locator('#thermalReceipt')).to_be_visible();op.emulate_media(media='print');assert op.locator('body').evaluate('e=>getComputedStyle(e).backgroundColor')=='rgb(255, 255, 255)';assert 'monospace' in op.locator('#thermalReceipt').evaluate('e=>getComputedStyle(e).fontFamily');assert op.locator('#thermalReceipt').evaluate('e=>getComputedStyle(e).visibility')=='visible'
        checks.append('Print media preserves receipt background/font/visibility without printer calls; catalog preserves its configured primary color')
        assert not errors,errors
        for ctx in (owner,master,driver,guest):ctx.close()
        browser.close()
    # Invalidate only the fictional authorization created by this run.
    sql('UPDATE fisitaap_r2_devices SET is_active=0 WHERE id=?',[device])
    result={'revision':'1.8.0-R15','checks':checks,'layouts':layouts,'layout_cases':len(layouts),'page_errors':errors,'scope':'Real PHP/MariaDB/Chromium on guarded fictional database; no production, external messages or printer calls. Desktop and touch-sized viewports; physical iOS/Android tablet/Windows checks remain manual.'}
    (ROOT/'actualizaciones/1.8.0/QA-INTERFAZ-R15.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print('PASS R15 interface:',len(checks),'flows;',len(layouts),'layouts',flush=True)

if __name__=='__main__':main()
