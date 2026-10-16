"""R6 reproductions and R7 browser/order/Express checks on fictional data only."""
import json,pathlib,re,secrets,subprocess,sys
from PIL import Image
from playwright.sync_api import sync_playwright,expect
from access180 import install_payload
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD

ROOT=pathlib.Path(__file__).resolve().parents[1];STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
REPORT=ROOT/'actualizaciones/1.8.0/QA-USABILIDAD-R7.json'

def login(actor,email):
    status,body=request(actor,'/owner-login')
    if status==200:
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
        assert request(actor,'/owner-login',{'csrf':token,'email':email,'password':PASSWORD})[0]==302

def ensure_demo_templates():
    # Only the isolated setup180 fixture is accepted by main before this runs.
    schema='require "/var/www/html/app/bootstrap.php";require "/var/www/html/app/demo_sandbox.php";demo_schema($app);'
    subprocess.run(['docker','exec','fisitaap-r2-web','php','-r',schema],capture_output=True,check=True)
    sql('INSERT INTO settings(`key`,`value`) VALUES("demo_sandbox_active","1") ON DUPLICATE KEY UPDATE value="1"')
    if sql('SELECT id FROM tenants WHERE slug="tienda-demo"'):return
    assert not sql('SELECT id FROM tenants WHERE id IN(901,902) OR slug="restaurante-demo"')
    sql('INSERT INTO tenants(id,name,slug,physical_store_enabled,catalog_label,is_listed) VALUES(901,"Restaurante demo QA","restaurante-demo",1,"Menú",0),(902,"Tienda demo QA","tienda-demo",1,"Catálogo",0)')
    sql('INSERT INTO branches(id,tenant_id,name,slug,is_default) VALUES(1301,901,"Demo principal","principal",1),(1302,902,"Tienda demo","principal",1)')
    for uid,tid,name,email in [(1201,901,'Dueño demo','restaurante.demo@fisitaap.com'),(1202,902,'Dueño tienda','negocio.demo@fisitaap.com')]:
        sql('INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) SELECT ?,?,?,?,password_hash,"tenant_admin",1 FROM users WHERE id=202',[uid,tid,name,email])
    sql('INSERT INTO categories(id,tenant_id,name,slug) VALUES(1401,901,"Comida demo","comida"),(1402,902,"Artículos demo","articulos")')
    sql('INSERT INTO products(id,tenant_id,category_id,name,slug,sku,price,tax_rate,track_inventory) VALUES(1501,901,1401,"Producto demo","producto","D1",1000,13,1),(1502,902,1402,"Artículo demo","articulo","D2",2000,0,1)')
    sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(901,1301,1501,30),(902,1302,1502,20)')
    sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(1301,1501,1),(1302,1502,1)')

def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    ensure_demo_templates()
    assert sql('SELECT COUNT(*) n FROM tenants WHERE slug IN("tienda-demo","restaurante-demo")')[0]['n']==2
    old_image=sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key="welcome_image180"')
    versions=sql('SELECT tenant_id,version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)')
    inventory=sql('SELECT * FROM product_inventory WHERE tenant_id IN(101,901,902) ORDER BY id')
    totals=sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM pos_shifts) shifts')
    tag='R7-'+secrets.token_hex(5);orders=[];driver=None;checks=[];clones=[];driver_actor='r7driver_'+tag
    image=STAGE/'uploads/qa-welcome-r7.png';Image.new('RGB',(1600,900),'navy').save(image);image.chmod(0o644)
    def make_job(tenant,branch,suffix):
        number=tag+suffix
        sql('INSERT INTO orders(tenant_id,branch_id,user_id,order_number,customer_name,customer_phone,address,delivery_type,payment_method,subtotal,total,status) VALUES(?,?,205,?,"Cliente ficticio R7","88888888","Dirección de prueba","coordinated","SINPE Móvil",100,100,"ready")',[tenant,branch,number])
        oid=sql('SELECT id FROM orders WHERE tenant_id=? AND order_number=?',[tenant,number])[0]['id'];orders.append(oid)
        hashed=subprocess.run(['docker','exec','fisitaap-r2-web','php','-r',"echo password_hash('4321',PASSWORD_DEFAULT);"],capture_output=True,check=True).stdout.decode()
        sql('INSERT INTO delivery_jobs(tenant_id,branch_id,order_id,status,payout,delivery_pin_hash) VALUES(?,?,?,"available",40,?)',[tenant,branch,oid,hashed])
        return sql('SELECT id FROM delivery_jobs WHERE order_id=?',[oid])[0]['id'],number
    def driver_post(action,job,**extra):
        assert request(driver_actor,'/driver',{'csrf':csrf(driver_actor,'/driver'),'action':action,'job_id':job,**extra})[0]==302
    def row_visible(path,job,actor='owner'):
        status,body=request(actor,path);assert status==200,(path,status)
        return ('data-express-job="'+str(job)+'"') in body,body
    def demo_run(browser,kind,fixed,extra=False):
        context=browser.new_context();page=context.new_page();errors=[];alerts=[];nav=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.on('dialog',lambda dialog:(alerts.append(dialog.message),dialog.dismiss()))
        page.goto('http://localhost:18080/demo')
        page.get_by_role('button',name=('Entrar a la tienda demo →' if kind=='tienda' else 'Entrar al restaurante demo →'),exact=True).click()
        page.wait_for_url('**/catalogo');token=page.evaluate('sessionStorage.getItem("fisitaap-demo-tab")');clones.append(token)
        tenant=sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?',[token])[0]['tenant_id']
        page.get_by_role('button',name=('Artículos demo' if kind=='tienda' else 'Comida demo'),exact=True).click()
        page.on('framenavigated',lambda frame:nav.append(frame.url) if frame==page.main_frame else None)
        stock=float(sql('SELECT quantity FROM product_inventory WHERE tenant_id=?',[tenant])[0]['quantity'])
        def add(qty):
            page.locator('[data-product] [data-qty]').first.fill(str(qty));page.locator('[data-add-cart]').first.click()
            page.locator('[data-open-cart]').first.click()
            page.locator('#checkoutForm [name=delivery_type]').select_option('pickup')
            page.locator('#checkoutForm [name=payment]').select_option('Efectivo')
        def send():
            key=page.locator('#checkoutForm [name=checkout_key]').input_value()
            with page.expect_response(lambda response:response.url.endswith('/api/checkout')) as result:
                page.locator('#checkoutForm button[type=submit]').click()
            out=result.value.json()
            expect(page.locator("#checkoutForm button[type=submit]")).to_be_enabled()
            after=page.locator('#checkoutForm [name=checkout_key]').input_value()
            if out.get('ok'):page.get_by_role('button',name='Cerrar ejemplo',exact=True).click()
            return out,key,after
        seen=[]
        for qty in [1,1,2]:
            add(qty);out,key,after=send();seen.append(out)
            if fixed:assert out['ok'] and not out.get('duplicate') and key!=after,out
        if not fixed:
            assert seen[0]['ok'] and seen[1].get('duplicate') and seen[1]['order_id']==seen[0]['order_id']
            assert not seen[2]['ok'] and 'otros datos' in seen[2]['error']
        else:
            assert len({row['order_id'] for row in seen})==3
            assert len(sql('SELECT id FROM orders WHERE tenant_id=?',[tenant]))==3
            assert float(sql('SELECT quantity FROM product_inventory WHERE tenant_id=?',[tenant])[0]['quantity'])==stock-4
        if fixed and extra:
            add(1);key=page.locator('#checkoutForm [name=checkout_key]').input_value()
            def lose_reply(route):route.fetch();route.abort('failed')
            page.route('**/api/checkout',lose_reply)
            page.locator('#checkoutForm button[type=submit]').click()
            expect(page.locator("#checkoutForm button[type=submit]")).to_be_enabled()
            page.unroute('**/api/checkout',lose_reply)
            assert page.locator('#checkoutForm [name=checkout_key]').input_value()==key
            assert len(sql('SELECT id FROM orders WHERE tenant_id=?',[tenant]))==4
            out,before,after=send();assert out['ok'] and out['duplicate'] and before==key and after!=key
            assert len(sql('SELECT id FROM orders WHERE tenant_id=?',[tenant]))==4
            add(1);page.locator('#checkoutForm [name=delivery_type]').select_option('delivery');page.locator('#checkoutForm [name=address]').fill('')
            key=page.locator('#checkoutForm [name=checkout_key]').input_value();out,before,after=send()
            assert not out['ok'] and before==after==key
            page.locator('#checkoutForm [name=delivery_type]').select_option('pickup')
            out,before,after=send();assert out['ok'] and before==key and before!=after
            add(1);requests=[];page.on('request',lambda r:requests.append(r.url) if r.url.endswith('/api/checkout') else None)
            with page.expect_response(lambda response:response.url.endswith('/api/checkout')):
                page.evaluate('() => {const f=document.querySelector("#checkoutForm");f.requestSubmit();f.requestSubmit();}')
            expect(page.locator("#checkoutForm button[type=submit]")).to_be_enabled()
            assert len(requests)==1
            page.get_by_role('button',name='Cerrar ejemplo',exact=True).click()
            assert len(sql('SELECT id FROM orders WHERE tenant_id=?',[tenant]))==6
        assert not errors and not nav,(errors,nav)
        if 'open' in (page.locator('#cartDrawer').get_attribute('class') or ''):
            page.locator('#cartDrawer button[data-close-cart]').click()
        page.get_by_role('button',name='Terminar y descartar',exact=True).click();page.wait_for_url('**/demo')
        assert not sql('SELECT id FROM tenants WHERE id=?',[tenant])
        context.close()
    try:
        sql('DELETE FROM rate_limits')
        install_payload(ROOT/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R6.zip')
        login('owner','owner@r2.invalid');login('r7master','master@r2.invalid')
        email='driver-'+tag.lower()+'@r7.invalid'
        sql('INSERT INTO users(name,email,phone,password_hash,role,is_active) SELECT "Motorizado ficticio R7",?,"88888888",password_hash,"driver",1 FROM users WHERE id=202',[email])
        driver=sql('SELECT id FROM users WHERE email=?',[email])[0]['id']
        login(driver_actor,email);request(driver_actor,'/driver')
        sql('INSERT INTO driver_affiliations(user_id,tenant_id,status,requested_by,all_branches) VALUES(?,101,"active","tenant",1)',[driver])
        request(driver_actor,'/driver',{'csrf':csrf(driver_actor,'/driver'),'action':'toggle','online':'on'})
        job,number=make_job(101,301,'-main')
        assert number not in request('owner','/admin/express-pendientes')[1]
        assert number in request('owner','/admin/entregas-historial')[1]
        assert ('name="job_id" value="'+str(job)+'"') in request(driver_actor,'/driver')[1]
        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
            demo_run(browser,'tienda',False)
            checks.append('published R6 browser reproduces a duplicate second demo order and a third changed-cart error without reloading; its pending Express screen omits the web job shown in history and driver portal')
            install_payload('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R7.zip')
            demo_run(browser,'tienda',True,True);demo_run(browser,'restaurante',True)
            checks.append('both demo catalogs create three distinct intentional orders on the same page; lost-response retry reuses the original operation once, validation retains cart/key and simultaneous submit sends one request')

            assert row_visible('/admin/express-pendientes',job)[0]
            visible,body=row_visible('/admin/entregas-historial',job);assert visible and 'Disponible para motorizados' in body and '>available<' not in body
            assert 'No hay Express pendientes' not in row_visible('/admin/express-pendientes',job)[1]
            checks.append('one web Express appears in pending/history/driver views with a consistent Spanish status and separate order total versus driver payout')
            other_branch,other_number=make_job(101,303,'-branch');foreign,foreign_number=make_job(102,302,'-foreign')
            pending=request('owner','/admin/express-pendientes')[1]
            assert other_number not in pending and foreign_number not in pending
            history=request('owner','/admin/entregas-historial')[1];assert other_number in history and foreign_number not in history and 'Sucursal' in history
            assert number not in request('owner','/admin/entregas-historial?status=delivered')[1]
            checks.append('pending scope is the current branch, history explicitly covers all own branches, filters work and a foreign business remains excluded')
            driver_post('accept',job)
            assert row_visible('/admin/express-pendientes',job)[0] and 'Motorizado asignado' in row_visible('/admin/entregas-historial',job)[1]
            for action in ['arrived','picked_up','in_transit']:driver_post(action,job)
            driver_post('delivered',job,delivery_pin='0000')
            assert sql('SELECT status FROM delivery_jobs WHERE id=?',[job])[0]['status']=='in_transit'
            assert row_visible('/admin/express-pendientes',job)[0]
            for _ in range(2):driver_post('delivered',job,delivery_pin='4321')
            assert sql('SELECT status FROM delivery_jobs WHERE id=?',[job])[0]['status']=='delivered'
            assert not row_visible('/admin/express-pendientes',job)[0]
            assert row_visible('/admin/entregas-historial',job)[0] and 'Entregado' in row_visible('/admin/entregas-historial',job)[1]
            assert sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=?',[driver])[0]['completed_deliveries']==1
            checks.append('real driver HTTP assignment/collection/route/PIN delivery moves the same job from pending to history once; wrong PIN cannot hide or complete it')
            sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(0,"welcome_image180",?) ON DUPLICATE KEY UPDATE value=VALUES(value)',['http://localhost:18080/uploads/'+image.name])
            sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=101')
            layouts=[]
            for email,path in [('master@r2.invalid','/master'),('owner@r2.invalid','/admin')]:
                context=browser.new_context();page=context.new_page();page.goto('http://localhost:18080/owner-login')
                page.locator('[name=email]').fill(email);page.locator('[name=password]').fill(PASSWORD);page.locator('form button.btn').first.click();page.wait_for_url('**'+path)
                for width,height in [(1366,900),(900,900),(768,900),(390,844)]:
                    page.set_viewport_size({'width':width,'height':height});page.reload();page.locator('.qa180-welcome>img').evaluate('(e)=>e.decode()')
                    geometry=page.evaluate('''()=>{const a=document.querySelector('.qa180-welcome'),i=a.querySelector('img'),r=a.getBoundingClientRect(),m=document.querySelector('.admin-main'),p=getComputedStyle(m);return {width:r.width,height:r.height,fit:getComputedStyle(i).objectFit,imageWidth:i.getBoundingClientRect().width,imageHeight:i.getBoundingClientRect().height,bottom:r.bottom,availableBottom:innerHeight-parseFloat(p.paddingBottom),overflow:document.documentElement.scrollWidth>innerWidth+1};}''')
                    assert geometry['width']>0 and geometry['height']>150 and geometry['fit']=='cover' and not geometry['overflow'],geometry
                    assert abs(geometry['width']-geometry['imageWidth'])<2 and abs(geometry['height']-geometry['imageHeight'])<2,geometry
                    assert abs(geometry['bottom']-geometry['availableBottom'])<3,geometry
                    layouts.append({'role':'master' if path=='/master' else 'pending179','viewport_width':width,'viewport_height':height,**geometry})
                context.close()
            browser.close()
        checks.append('master and pending179 welcome images cover the exact remaining area at1366/900/768/390px with preserved aspect ratio and no horizontal overflow')
        assert sql('SELECT * FROM product_inventory WHERE tenant_id IN(101,901,902) ORDER BY id')==inventory
        assert sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM pos_shifts) shifts')==totals
        checks.append('demo disposal leaves source templates and business stock unchanged; read-only Express additions do not create sales, alter cash or close shifts')
        REPORT.write_text(json.dumps({'passed':len(checks),'checks':checks,'welcome_layouts':layouts,'scope':'published R6 baseline and exact R7 package; fictional PHP/MariaDB/Chromium, no production or external delivery services'},indent=2)+'\n')
        print('PASS',len(checks),'R7 usability groups; demo repeated orders/retry, Express scope/PIN/history, full welcome layouts')
    finally:
        cleanup="require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/demo_sandbox.php';foreach(json_decode(stream_get_contents(STDIN),true) as $token){$row=$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',[$token]);if($row)demo_discard($app,$row);}"
        subprocess.run(['docker','exec','-i','fisitaap-r2-web','php','-r',cleanup],input=json.dumps(clones).encode(),capture_output=True,check=True)
        if old_image:sql('UPDATE fisitaap_r2_settings SET value=? WHERE tenant_id=0 AND setting_key="welcome_image180"',[old_image[0]['value']])
        else:sql('DELETE FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key="welcome_image180"')
        for row in versions:sql('UPDATE fisitaap_tenant_updates180 SET version=? WHERE tenant_id=?',[row['version'],row['tenant_id']])
        for oid in orders:sql('DELETE FROM delivery_jobs WHERE order_id=?',[oid]);sql('DELETE FROM orders WHERE id=?',[oid])
        if driver:
            sql('DELETE FROM audit_logs WHERE user_id=?',[driver]);sql('DELETE FROM driver_affiliations WHERE user_id=?',[driver]);sql('DELETE FROM driver_profiles WHERE user_id=?',[driver]);sql('DELETE FROM users WHERE id=?',[driver])
        sql('DELETE FROM rate_limits')
        if image.exists():image.unlink()

if __name__=='__main__':main()
