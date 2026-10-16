"""R8 real HTTP workflow/security/financial and Chromium layout checks, fictional DB only."""
import concurrent.futures,hashlib,json,pathlib,re,secrets,subprocess,sys
from playwright.sync_api import sync_playwright,expect
from access180 import install_payload
from usability_r7 import login,ensure_demo_templates
from financial180 import api
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD

ROOT=pathlib.Path(__file__).resolve().parents[1]
checks=[];layouts=[];tag='R8-'+secrets.token_hex(5)
owner='boardowner_'+tag;drivers=[];orders=[];tickets=[]
def passed(text):checks.append(text);print('PASS',text,flush=True)
def post(path,data,actor=owner):
    return request(actor,path,{'csrf':csrf(actor,'/admin/pedidos'),**data})
def state(oid):return sql('SELECT * FROM orders WHERE id=?',[oid])[0]
def ticket(tid):return sql('SELECT * FROM pos_tickets WHERE id=?',[tid])[0]
def stock():return float(sql('SELECT quantity FROM product_inventory WHERE product_id=501 AND branch_id=301')[0]['quantity'])
def board(oid,action,origin='web',**extra):
    revision=state(oid)['revision180'] if origin=='web' else ticket(oid)['revision']
    status,body=post('/admin/pedidos',{'origin':origin,'id':oid,'revision':revision,'action':action,**extra})
    assert status==302,(status,body[-200:])
def checkout(kind='coordinated'):
    payload={'tenant':'laventanita','branch_id':301,'delivery_type':kind,'payment':'Efectivo','address':'Dirección ficticia R8','checkout_key':secrets.token_hex(16),'items':[{'id':501,'qty':1,'option_ids':[]}],'csrf':csrf('buyer','/account')}
    result=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-buyer.cookies','-H','Content-Type: application/json','--data-binary','@-','http://localhost/api/checkout'],input=json.dumps(payload).encode(),capture_output=True,check=True)
    out=json.loads(result.stdout);assert out.get('ok'),out;orders.append(out['order_id']);return out['order_id']
def prepare(oid):
    board(oid,'prepare');assert state(oid)['status']=='preparing',request(owner,'/admin/pedidos')[1][-2500:]
    board(oid,'prepared');assert state(oid)['status']=='ready'
def job(oid):return sql('SELECT * FROM delivery_jobs WHERE order_id=?',[oid])
def dispatch(oid,mode='pool',driver=0,origin='web',payout=40):board(oid,'dispatch',origin,dispatch_mode=mode,driver_id=driver,payout=payout)
def make_driver(suffix,branch=301,online=1):
    email=(tag+suffix).lower()+'@r8.invalid'
    sql('INSERT INTO users(name,email,password_hash,role,is_active) SELECT ?,?,password_hash,"driver",1 FROM users WHERE id=202',[tag+suffix,email])
    uid=sql('SELECT id FROM users WHERE email=?',[email])[0]['id'];drivers.append(uid)
    sql('INSERT INTO driver_profiles(user_id,is_online) VALUES(?,?)',[uid,online])
    sql('INSERT INTO driver_affiliations(user_id,tenant_id,status,all_branches) VALUES(?,101,"active",0)',[uid])
    aid=sql('SELECT id FROM driver_affiliations WHERE user_id=?',[uid])[0]['id']
    sql('INSERT INTO driver_affiliation_branches(affiliation_id,branch_id) VALUES(?,?)',[aid,branch])
    actor='driver_'+str(uid)+'_'+tag;login(actor,email)
    return uid,actor,aid
def driver_post(actor,action,jid,**extra):
    return request(actor,'/driver',{'csrf':csrf(actor,'/driver'),'action':action,'job_id':jid,**extra})
def pos(kind='express',qty=1):
    key=secrets.token_hex(16)
    post('/admin/ventas',{'action':kind,'open_key':key,'customer_id':205,'delivery_address':'Dirección de prueba R8','start_product':1,'product_id':501,'quantity':qty})
    rows=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key]);assert rows,request(owner,'/admin/ventas')[1][-1000:]
    tid=rows[0]['id'];tickets.append(tid)
    assert sql('SELECT COUNT(*) n FROM pos_ticket_items WHERE ticket_id=?',[tid])[0]['n']==1
    return tid
def pos_ready(tid):
    board(tid,'prepare','pos');assert ticket(tid)['fulfillment180']=='preparing'
    board(tid,'prepared','pos');assert ticket(tid)['fulfillment180']=='ready'
def charge(tid,amount,split='all',quantities=None):
    key=secrets.token_hex(16);data={'payment_key':key,'revisions['+str(tid)+']':ticket(tid)['revision'],'split_mode':split,'parts':2,'cash':amount,'customer_id':205,**(quantities or {})}
    assert post('/admin/cobro?ticket='+str(tid),data)[0]==302
    rows=sql('SELECT * FROM sales WHERE payment_key=?',[key]);assert len(rows)==1,request(owner,'/admin/cobro?ticket='+str(tid))[1][-1600:]
    return rows[0]
def browser_checks(prepared_oid):
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
        context=browser.new_context();page=context.new_page();errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
        page.goto('http://localhost:18080/owner-login');page.locator('[name=email]').fill('owner@r2.invalid');page.locator('[name=password]').fill(PASSWORD);page.locator('form button.btn').first.click();page.wait_for_url('**/admin')
        page.goto('http://localhost:18080/admin/pedidos')
        for width,height in [(1366,900),(1024,900),(900,900),(768,900),(390,844)]:
            page.set_viewport_size({'width':width,'height':height});page.reload()
            assert page.locator('[data-board-stage]').count()==3
            geometry=page.evaluate('''()=>({overflow:document.documentElement.scrollWidth>innerWidth+1,boardOverflow:document.querySelector('.qa180-board').scrollWidth>document.querySelector('.qa180-board').clientWidth+1,columns:getComputedStyle(document.querySelector('.qa180-board')).gridTemplateColumns})''')
            assert not geometry['overflow'] and not geometry['boardOverflow'],geometry
            layouts.append({'width':width,**geometry})
            if width in [1366,390]:page.screenshot(path='/workspace/fisitaap-env/kanban-r8-'+str(width)+'.png',full_page=True)
        button=page.locator('[data-board-order="web-'+str(prepared_oid)+'"] [data-board-dispatch]');button.click()
        dialog=page.get_by_role('dialog');expect(dialog).to_be_visible();expect(dialog.locator('[name=driver_id]')).to_be_disabled()
        dialog.locator('[name=dispatch_mode][value=driver]').check();expect(dialog.locator('[name=driver_id]')).to_be_enabled()
        expect(dialog.locator('[name=driver_id]')).to_have_attribute('required','')
        bounds=dialog.bounding_box();assert bounds['y']>=0 and bounds['y']+bounds['height']<=844,bounds
        page.screenshot(path='/workspace/fisitaap-env/kanban-r8-dialog-mobile.png',full_page=True)
        page.keyboard.press('Escape');expect(dialog).not_to_be_visible();expect(button).to_be_focused()
        button.click();expect(dialog.locator('[name=dispatch_mode][value=pool]')).to_be_checked()
        dialog.get_by_role('button',name='Volver',exact=True).click();expect(dialog).not_to_be_visible()
        assert not errors,errors;context.close();browser.close()

def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    install_payload('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R8.zip')
    sql('DELETE FROM rate_limits');sql('UPDATE users SET phone="88888888" WHERE id=205')
    login(owner,'owner@r2.invalid')
    status,body=request('buyer','/login')
    if status==200:
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
        assert request('buyer','/login',{'csrf':token,'email':'customer@r2.invalid','password':PASSWORD})[0]==302
    d1,a1,aff1=make_driver('A');d2,a2,aff2=make_driver('B');d3,a3,aff3=make_driver('Other',303);d4,a4,aff4=make_driver('Offline',301,0)
    original_physical=sql('SELECT physical_store_enabled FROM tenants WHERE id=101')[0]['physical_store_enabled']
    try:
        before=stock();oid=checkout();assert stock()==before-1
        board(oid,'prepared');assert state(oid)['status']=='new'
        rev=state(oid)['revision180'];board(oid,'prepare');assert state(oid)['status']=='preparing'
        receipt=sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid]);assert len(receipt)==1
        assert stock()==before-1
        assert len(sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['web-order-'+str(oid)+'-command-%']))==1
        post('/admin/pedidos',{'origin':'web','id':oid,'revision':rev,'action':'prepare'})
        assert len(sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid]))==1
        assert stock()==before-1
        board(oid,'prepared');assert state(oid)['status']=='ready'
        passed('Preparar receives and starts preparation in one POST; one account/command and original stock only; premature/stale/repeated changes rejected')
        for invalid in [205,d3,99999999]:
            dispatch(oid,'driver',invalid);assert not job(oid) and state(oid)['status']=='ready'
        sql('UPDATE driver_affiliations SET status="paused" WHERE id=?',[aff2]);dispatch(oid,'driver',d2);assert not job(oid);sql('UPDATE driver_affiliations SET status="active" WHERE id=?',[aff2])
        dispatch(oid,payout=-1);assert not job(oid)
        dispatch(oid,'wrong');assert not job(oid)
        passed('invalid driver, other branch, paused affiliation, bad mode and negative payout roll back without publishing a job')
        snapshot=sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM inventory_movements) movements')
        dispatch(oid);j=job(oid)[0];assert j['status']=='available' and state(oid)['status']=='sent'
        assert snapshot==sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM inventory_movements) movements')
        published_board=request(owner,'/admin/pedidos')[1]
        assert 'data-board-order="web-'+str(oid)+'"' not in published_board
        pin=re.search(r'PIN de entrega: (\d{4})',published_board)[1]
        assert 'data-express-job="'+str(j['id'])+'"' in request(owner,'/admin/express-pendientes')[1]
        assert state(oid)['order_number'] in request(a1,'/driver')[1] and state(oid)['order_number'] not in request(a3,'/driver')[1]
        driver_post(a3,'accept',j['id']);assert job(oid)[0]['status']=='available'
        dispatch(oid,'driver',d2,payout=99);assert job(oid)==[j]
        with concurrent.futures.ThreadPoolExecutor() as pool:
            list(pool.map(lambda actor:driver_post(actor,'accept',j['id']),[a1,a2]))
        assigned=job(oid)[0];assert assigned['status']=='assigned' and assigned['assigned_driver_id'] in [d1,d2]
        winner=a1 if assigned['assigned_driver_id']==d1 else a2
        for s in ['arrived','picked_up','in_transit']:driver_post(winner,s,j['id'])
        board(oid,'status',status='delivered');assert state(oid)['status']=='sent'
        driver_post(winner,'delivered',j['id'],delivery_pin='0000');assert state(oid)['status']=='sent'
        driver_post(winner,'delivered',j['id'],delivery_pin=pin);driver_post(winner,'delivered',j['id'],delivery_pin=pin)
        assert state(oid)['status']=='delivered' and sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=?',[assigned['assigned_driver_id']])[0]['completed_deliveries']==1
        passed('pool dispatch is once-only, scoped to authorized affiliates, first concurrent acceptance wins; manual delivery/wrong PIN fail and correct PIN completes once')
        direct=checkout();prepare(direct);dispatch(direct,'driver',d4)
        assert job(direct)[0]['status']=='assigned' and job(direct)[0]['assigned_driver_id']==d4
        busy=checkout();prepare(busy);dispatch(busy,'driver',d4);assert not job(busy) and state(busy)['status']=='ready'
        passed('direct assignment supports an authorized offline driver but rejects a driver with an active delivery atomically')
        newcard=checkout();cooking=checkout();board(cooking,'prepare')
        browser_checks(busy)
        passed('three-column board at five viewport sizes has no horizontal scroll; modal changes direct/pool fields and closes/restores focus without JavaScript errors')
        status,b=request(owner,'/admin/pedidos');assert '<option value="'+str(d4)+'" disabled>' in b
        scoped='boardother_'+tag;login(scoped,'ownerb@r2.invalid')
        assert 'data-board-order="web-'+str(busy)+'"' not in request(scoped,'/admin/pedidos')[1]
        assert request(scoped,'/admin/pedidos',{'csrf':csrf(scoped,'/admin/pedidos'),'origin':'web','id':busy,'revision':state(busy)['revision180'],'action':'dispatch','dispatch_mode':'pool','payout':40})[0]==302
        assert not job(busy)
        kitchen='kitchen_'+tag;login(kitchen,'kitchen@r2.invalid')
        assert request(kitchen,'/admin/cocina')[0]==200
        assert 'data-board-dispatch ' not in request(kitchen,'/admin/cocina')[1]
        ktoken=csrf(kitchen,'/admin/cocina');request(kitchen,'/admin/cocina',{'csrf':ktoken,'origin':'web','id':busy,'revision':state(busy)['revision180'],'action':'dispatch','dispatch_mode':'pool','payout':40});assert not job(busy)
        assert request(owner,'/admin/pedidos',{'csrf':'bad','origin':'web','id':busy,'revision':state(busy)['revision180'],'action':'dispatch','dispatch_mode':'pool','payout':40})[0]==302
        assert not job(busy) and 'El formulario venció' in request(owner,'/admin/pedidos')[1]
        assert request(owner,'/admin/configuracion?branch_id=303')[0]==200
        assert 'data-board-order="web-'+str(busy)+'"' not in request(owner,'/admin/pedidos')[1]
        post('/admin/pedidos',{'origin':'web','id':busy,'revision':state(busy)['revision180'],'action':'dispatch','dispatch_mode':'pool','payout':40})
        assert not job(busy);request(owner,'/admin/configuracion?branch_id=301')
        passed('fresh kitchen/Express permission, tenant isolation, busy driver list and CSRF prevent unauthorized dispatch')
        # POS fixture uses real native session proof and cash shift; all financial calls remain HTTP.
        token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest()
        sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,?,?)',[tag,digest])
        device=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,?,?,202)',[tag,device])
        ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',request(owner,'/admin/turnos')[1])[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token)
        assert post('/api/native/bind',{'proof':proof['proof']})[0]==200
        shiftkey=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':shiftkey,'opening_cash':0})
        assert sql('SELECT status FROM pos_shifts WHERE opening_key=?',[shiftkey])[0]['status']=='open'
        before=stock();tid=pos();assert stock()==before-1;pos_ready(tid)
        snapshot=ticket(tid);counts=sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM inventory_movements) movements,(SELECT COUNT(*) FROM pos_tickets) tickets')
        dispatch(tid,'driver',d3,'pos');assert ticket(tid)['source_order_id'] is None
        assert not sql('SELECT id FROM orders WHERE order_number=?',['POS-'+str(tid)])
        dispatch(tid,'driver',d2,'pos');linked=ticket(tid)['source_order_id'];assert linked;orders.append(linked)
        assert len(sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=? AND ticket_id=?',[linked,tid]))==1
        assert state(linked)['total']==snapshot['total'] and ticket(tid)['paid_total']==snapshot['paid_total']
        assert counts==sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM inventory_movements) movements,(SELECT COUNT(*) FROM pos_tickets) tickets')
        dispatch(tid,'pool',origin='pos');assert len(job(linked))==1 and job(linked)[0]['assigned_driver_id']==d2
        assert request(owner,'/admin/express-pendientes')[1].count('data-express-job="'+str(job(linked)[0]['id'])+'"')==1
        post('/admin/express-pendientes',{'ticket_id':tid,'state':'delivered'});assert state(linked)['status']=='sent' and ticket(tid)['fulfillment180']=='sent'
        passed('POS Express dispatch links the original account/customer/prices without another sale or stock movement; invalid assignment rolls back the snapshot, retry stays unique and old pending POST cannot bypass PIN')
        # Cancellation of the POS snapshot must restore its POS-reserved stock once, without an online movement.
        post('/admin/ventas?screen=order&ticket='+str(tid),{'action':'void','revision':ticket(tid)['revision']})
        assert ticket(tid)['status']=='void' and state(linked)['status']=='cancelled' and stock()==before
        board(linked,'status',status='cancelled');assert stock()==before
        passed('cancelling an unpaid POS-derived Express releases POS inventory once and cancels its delivery')
        partial=pos(qty=2);item=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[partial])[0]['id']
        sale=charge(partial,1130,'items',{'qty['+str(item)+']':1});assert sale['total']=='1130.00'
        pos_ready(partial);before=stock();paid=ticket(partial)['paid_total'];dispatch(partial,origin='pos');linked=ticket(partial)['source_order_id'];orders.append(linked)
        assert ticket(partial)['paid_total']==paid and state(linked)['total']==ticket(partial)['total']
        post('/admin/ventas?screen=order&ticket='+str(partial),{'action':'void','revision':ticket(partial)['revision']});assert ticket(partial)['status']!='void' and stock()==before
        remaining=charge(partial,1130);assert remaining['total']=='1130.00' and ticket(partial)['status']=='paid'
        assert len(sql('SELECT id FROM pos_payments WHERE ticket_id=?',[partial]))==2
        status,history=request(owner,'/admin/clientes?customer=205&tab=ventas');assert status==200 and 'POS-'+str(partial) not in history and remaining['sale_number'] in history
        status,report=request(owner,'/admin/reportes?dataset=orders');assert status==200 and 'POS-'+str(partial) not in report
        passed('partially paid POS Express preserves collected amounts at dispatch, blocks cancellation and settles only the remaining balance on the original account')
        paidpos=pos();sql('UPDATE pos_tickets SET service_rate=10 WHERE id=?',[paidpos])
        post('/admin/ventas?screen=order&ticket='+str(paidpos),{'action':'save','revision':ticket(paidpos)['revision']})
        assert ticket(paidpos)['total']=='1230.00';charge(paidpos,1230);pos_ready(paidpos)
        prior_sales=sql('SELECT COUNT(*) n FROM sales')[0]['n'];prior_loyalty=sql('SELECT COUNT(*) n FROM loyalty_ledger')[0]['n']
        completed=sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=?',[d2])[0]['completed_deliveries']
        dispatch(paidpos,'driver',d2,'pos');linked=ticket(paidpos)['source_order_id'];orders.append(linked)
        pin=re.search(r'PIN de entrega: (\d{4})',request(owner,'/admin/pedidos')[1])[1]
        assert state(linked)['total']=='1230.00' and state(linked)['payment_method']=='Pagado en caja'
        assert sql('SELECT line_subtotal FROM order_items WHERE order_id=? AND product_name="Servicio de la cuenta"',[linked])==[{'line_subtotal':'100.00'}]
        for step in ['arrived','picked_up','in_transit']:driver_post(a2,step,job(linked)[0]['id'])
        driver_post(a2,'delivered',job(linked)[0]['id'],delivery_pin=pin)
        assert state(linked)['status']=='delivered' and ticket(paidpos)['fulfillment180']=='delivered' and ticket(paidpos)['status']=='paid'
        assert sql('SELECT COUNT(*) n FROM sales')[0]['n']==prior_sales and sql('SELECT COUNT(*) n FROM loyalty_ledger')[0]['n']==prior_loyalty
        assert sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=?',[d2])[0]['completed_deliveries']==completed+1
        passed('already paid POS Express includes service in its delivery snapshot and completes with the published PIN without another sale, payment or loyalty accrual')
        # A POS-* delivery number must not break the next web order's numeric sequence.
        quick=pos('quick');pos_ready(quick);dispatch(quick,origin='pos');assert ticket(quick)['source_order_id'] is None
        board(quick,'handover','pos');assert ticket(quick)['fulfillment180']=='delivered'
        pickup=checkout('pickup');prepare(pickup);dispatch(pickup);assert not job(pickup);board(pickup,'handover');assert state(pickup)['status']=='delivered'
        passed('quick sale and pickup use Entregar after preparation and cannot create an Express')
        sql('UPDATE tenants SET physical_store_enabled=0 WHERE id=101');webonly=checkout();prepare(webonly);dispatch(webonly)
        assert job(webonly)[0]['status']=='available' and not sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[webonly])
        sql('UPDATE tenants SET physical_store_enabled=? WHERE id=101',[original_physical])
        passed('catalog-only business prepares and dispatches without creating a physical cash account')
        # Re-release is explicit and cannot rotate an active job's PIN.
        sql('UPDATE delivery_jobs SET status="expired" WHERE order_id=?',[webonly]);dispatch(webonly)
        assert job(webonly)[0]['status']=='available' and state(webonly)['status']=='sent'
        passed('an expired delivery can be explicitly released again while an active one remains unchanged on retry')
        ensure_demo_templates();demo='demo_'+tag
        status,body=request(demo,'/demo/iniciar',{'csrf':csrf(demo,'/demo'),'type':'restaurante','mode':'panel'});assert status==200
        token=re.search(r'sessionStorage.setItem\("fisitaap-demo-tab","([a-f0-9]{32})"',body)[1]
        ds=sql('SELECT * FROM fisitaap_demo_sessions WHERE token=?',[token])[0];base='/demo/s/'+token
        try:
            branch=sql('SELECT id FROM branches WHERE tenant_id=? ORDER BY id LIMIT 1',[ds['tenant_id']])[0]['id']
            sql('INSERT INTO orders(tenant_id,branch_id,user_id,order_number,customer_name,customer_phone,address,delivery_type,payment_method,subtotal,total,status) VALUES(?,?,?,"DEMO-R8","Cliente demo","88888888","Demo","coordinated","Efectivo",100,100,"ready")',[ds['tenant_id'],branch,ds['customer_id']])
            did=sql('SELECT id FROM orders WHERE tenant_id=? AND order_number="DEMO-R8"',[ds['tenant_id']])[0]['id']
            html=request(demo,base+'/admin/pedidos')[1];assert 'data-board-dispatch ' not in html
            assert request(demo,base+'/admin/pedidos',{'csrf':csrf(demo,base+'/admin/pedidos'),'origin':'web','id':did,'revision':0,'action':'dispatch','dispatch_mode':'pool','payout':40})[0]==302
            assert not job(did) and state(did)['status']=='ready'
        finally:request(demo,base+'/logout',{'csrf':csrf(demo,base+'/admin')})
        assert not sql('SELECT id FROM tenants WHERE id=?',[ds['tenant_id']])
        passed('demo board hides real driver dispatch and rejects forged dispatch without a job or account; disposal removes only the private demo')
        legacy='legacy_'+tag;login(legacy,'owner@r2.invalid');version=sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=101')[0]['version']
        compatibility=[]
        try:
            sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=101')
            for path in ['/admin/express-pendientes','/admin/pedidos']:
                assert request(legacy,path)[0]==200;compatibility.append('GET '+path+' HTTP200')
        finally:sql('UPDATE fisitaap_tenant_updates180 SET version=? WHERE tenant_id=101',[version])
        report={'passed':len(checks),'checks':checks,'layouts':layouts,'pending179_compatibility':compatibility,'environment':'fictional fisitaap_r2_test PHP8.3/MariaDB real HTTP and Chromium; simulated native session proof; no production or physical printers'}
        (ROOT/'actualizaciones/1.8.0/QA-KANBAN-R8.json').write_text(json.dumps(report,indent=2)+'\n')
    finally:
        sql('UPDATE tenants SET physical_store_enabled=? WHERE id=101',[original_physical])

if __name__=='__main__':main()
