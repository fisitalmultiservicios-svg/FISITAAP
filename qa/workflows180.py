"""Operational browser/modal, delivery and concurrency checks on fictional data."""
import concurrent.futures,json,pathlib,re,secrets,subprocess,sys,time,urllib.parse
from playwright.sync_api import sync_playwright
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from orders180 import checkout,receive,state
from floor180 import post
checks=[];out=pathlib.Path('/workspace/fisitaap-env/browser180')
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
    sql('DELETE FROM rate_limits')
    sql('UPDATE pos_shifts s JOIN fisitaap_r2_devices d ON d.id=s.device_id SET s.status="closed",s.closed_at=NOW(),s.expected_cash=s.opening_cash+(SELECT COALESCE(SUM(p.amount),0) FROM pos_payments p JOIN sales x ON x.id=p.sale_id WHERE x.shift_id=s.id AND p.payment_method="Efectivo"),s.closing_cash=s.opening_cash+(SELECT COALESCE(SUM(p.amount),0) FROM pos_payments p JOIN sales x ON x.id=p.sale_id WHERE x.shift_id=s.id AND p.payment_method="Efectivo"),s.difference_amount=0 WHERE d.name="WORKFLOW BOX" AND s.status="open"')
    status,body=request('owner','/admin/kpis?from=2020-01-01&to=2030-01-01');assert status==200 and 'Ventas registradas por día' in body and 'Pedidos web por día' in body;assert request('owner','/admin/kpis?branch=302')[0]==422
    passed('KPIs presents dated sales and order charts separately and rejects another business branch')
    role=sql('SELECT role_id FROM fisitaap_r1_user_roles WHERE user_id=204 AND tenant_id=101')[0]['role_id']
    code="require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/admin.php';require '/var/www/html/app/v1.php';require '/var/www/html/app/admin_v1.php';require '/var/www/html/app/branches_v1.php';require '/var/www/html/app/modules_v12.php';require '/var/www/html/app/physical_v14.php';require '/var/www/html/app/pos_v141.php';require '/var/www/html/app/manuals_v142.php';require '/var/www/html/app/fisichat_v143.php';require '/var/www/html/app/restructure_r1.php';require '/var/www/html/app/restructure_r2.php';require '/var/www/html/app/qa180.php';$u=$app->one('SELECT * FROM users WHERE id=204');$t=$app->one('SELECT * FROM tenants WHERE id=101');try{$a=r2_chat_context($app,'dueno',$t,$u);echo json_encode($a);}catch(DomainException $e){echo json_encode(['denied'=>true]);}"
    def chat():return json.loads(subprocess.run(['docker','exec','fisitaap-r2-web','php','-r',code],capture_output=True,check=True).stdout)
    sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=? AND section_key="fisichat"',[role]);assert chat()=={'denied':True}
    sql('INSERT IGNORE INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,"fisichat","view")',[role]);had_report=bool(sql('SELECT role_id FROM fisitaap_r1_permissions WHERE role_id=? AND section_key="reportes" AND action_key="view"',[role]));sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=? AND section_key="reportes" AND action_key="view"',[role])
    try:
        context=chat();assert all(k not in context for k in ['ventas_mes','productos','stock_bajo']),list(context)
    finally:
        sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=? AND section_key="fisichat"',[role])
        if had_report:sql('INSERT IGNORE INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,"reportes","view")',[role])
    passed('FISIChat honors the custom role and never includes denied inventory, product or financial context')
    # Same checkout operation sent by independent HTTP requests; no shared cookie file writes.
    key=secrets.token_hex(16);payload={'tenant':'laventanita','branch_id':301,'delivery_type':'pickup','payment':'Efectivo','checkout_key':key,'items':[{'id':501,'qty':1,'option_ids':[]}],'csrf':csrf('buyer','/account')}
    before=float(sql('SELECT quantity FROM product_inventory WHERE product_id=501 AND branch_id=301')[0]['quantity'])
    def concurrent_checkout(_):
        p=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-buyer.cookies','-H','Content-Type: application/json','--data-binary','@-','http://localhost/api/checkout'],input=json.dumps(payload).encode(),capture_output=True,check=True);return json.loads(p.stdout)
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:replies=list(pool.map(concurrent_checkout,range(4)))
    assert all(r['ok'] for r in replies),replies;ids={r['order_id'] for r in replies};assert len(ids)==1 and float(sql('SELECT quantity FROM product_inventory WHERE product_id=501 AND branch_id=301')[0]['quantity'])==before-1;state(next(iter(ids)),'cancelled');passed('four simultaneous checkout retries create one order and reserve one product')
    # Real driver portal transitions, no external messages or delivery services.
    status,body=request('driver','/owner-login')
    if status==200:
        login=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1];assert request('driver','/owner-login',{'csrf':login,'email':'driver@r2.invalid','password':PASSWORD})[0]==302
    request('driver','/driver');sql('INSERT INTO driver_affiliations(user_id,tenant_id,status,requested_by,all_branches) VALUES(210,101,"active","tenant",1) ON DUPLICATE KEY UPDATE status="active",all_branches=1');request('driver','/driver',{'csrf':csrf('driver','/driver'),'action':'toggle','online':'on'})
    previous_deliveries=sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=210')[0]['completed_deliveries']
    oid=checkout({'delivery_type':'coordinated','address':'Dirección ficticia QA'});post('/admin/express',{'action':'publish','order_id':oid,'payout':500});assert not sql('SELECT id FROM delivery_jobs WHERE order_id=?',[oid]);receive(oid);state(oid,'preparing');state(oid,'ready');post('/admin/express',{'action':'publish','order_id':oid,'payout':500});job=sql('SELECT id,delivery_pin_hash FROM delivery_jobs WHERE order_id=?',[oid])[0];_,body=request('owner','/admin/express');pin=re.search(r'PIN de entrega: (\d{4})',body)[1]
    post('/admin/express',{'action':'publish','order_id':oid,'payout':999});assert len(sql('SELECT id FROM delivery_jobs WHERE order_id=?',[oid]))==1;assert sql('SELECT payout FROM delivery_jobs WHERE order_id=?',[oid])[0]['payout']=='500.00';passed('Express can publish only a ready order and duplicate publication preserves its assignment and payout')
    for action in ['accept','arrived','picked_up','in_transit']:
        request('driver','/driver',{'csrf':csrf('driver','/driver'),'action':action,'job_id':job['id']})
    request('driver','/driver',{'csrf':csrf('driver','/driver'),'action':'delivered','job_id':job['id'],'delivery_pin':'0000'});assert sql('SELECT status FROM delivery_jobs WHERE id=?',[job['id']])[0]['status']=='in_transit'
    for _ in range(2):request('driver','/driver',{'csrf':csrf('driver','/driver'),'action':'delivered','job_id':job['id'],'delivery_pin':pin})
    assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='delivered';assert sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=210')[0]['completed_deliveries']==previous_deliveries+1
    linked=sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid])[0]['ticket_id'];assert sql('SELECT fulfillment180,status FROM pos_tickets WHERE id=?',[linked])[0]=={'fulfillment180':'delivered','status':'open'};passed('the driver needs the correct PIN; delivery retries count once and leave the original unpaid account available for collection')
    # Release the fictional received account after delivery by recording its cash payment.
    from pos180 import charge
    from financial180 import api
    import hashlib
    token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"WORKFLOW BOX",?)',[digest]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"WORKFLOW REGISTER",?,202)',[did]);_,html=request('owner','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',html)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token);assert request('owner','/api/native/bind',{'csrf':csrf('owner','/admin/turnos'),'proof':proof['proof']})[0]==200;opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':0});workflow_cash=0
    for pending in sql('SELECT t.id,t.total FROM pos_tickets t JOIN orders o ON o.id=t.source_order_id WHERE t.tenant_id=101 AND t.status="open" AND o.status="delivered"'):
        _,_,sales=charge(pending['id'],float(pending['total']),{'customer_id':205});assert sales;workflow_cash+=float(pending['total'])
    tag=secrets.token_hex(4);post('/admin/salones',{'action':'create_room','name':'SALÓN VISUAL '+tag,'service_rate':10});room=sql('SELECT id FROM fisitaap_r2_rooms WHERE tenant_id=101 AND name=?',['SALÓN VISUAL '+tag])[0]['id'];tables=[]
    for i,shape in enumerate(['square','round','rectangle']):
        post('/admin/salones?room='+str(room),{'action':'save_table','room_id':room,'name':'Mesa '+str(i+1)+'-'+tag,'capacity':4,'shape':shape,'x':150+i*220,'y':200,'service_enabled':'on'});tables.append(sql('SELECT r.id FROM restaurant_tables r JOIN fisitaap_r2_floor f ON f.table_id=r.id WHERE r.tenant_id=101 AND f.room_id=? AND r.name=?',[room,'Mesa '+str(i+1)+'-'+tag])[0]['id'])
    key=secrets.token_hex(16);post('/admin/ventas',{'action':'table','table_id':tables[0],'open_key':key});tid=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];path='/admin/ventas?screen=order&ticket='+str(tid);post(path,{'action':'add_item','revision':0,'product_id':501,'quantity':1});post(path,{'action':'send_kitchen','revision':1})
    errors=[];layouts=[]
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);page=browser.new_page();page.on('pageerror',lambda e:errors.append(str(e)));base='http://localhost:18080';page.goto(base+'/owner-login');page.locator('[name=email]').fill('owner@r2.invalid');page.locator('[name=password]').fill(PASSWORD);page.locator('form button.btn').first.click();page.wait_for_url('**/admin')
        for width,height in [(1366,900),(768,1024),(390,844),(360,800)]:
            page.set_viewport_size({'width':width,'height':height})
            for suffix,label,modal in [(path,'loaded-pos',None),('/admin/cobro?ticket='+str(tid),'checkout','[data-payment-modal]'),('/admin/mesas?room='+str(room),'floor-modal','#r3TableAccount'),('/admin/salones?room='+str(room),'floor-designer',None),('/admin/kpis','kpis',None)]:
                response=page.goto(base+suffix,wait_until='networkidle');assert response.status==200,(label,response.status)
                if label=='floor-modal':page.locator('[data-r3-table="'+str(tables[0])+'"]').first.click()
                if modal:page.locator(modal+'[open]').wait_for()
                data=page.evaluate("() => ({width:innerWidth,scroll:Math.max(document.documentElement.scrollWidth,document.body.scrollWidth),x:scrollX})");assert data['scroll']<=width+1 and abs(data['x'])<=1,(label,width,data);layouts.append({'screen':label,'width':width,'overflow':False});page.screenshot(path=str(out/(str(width)+'-'+label+'.png')),full_page=True)
        assert not errors,errors;browser.close()
    passed('loaded POS, checkout, occupied-table modal, floor designer and KPI charts work at 1366/768/390/360 without overflow or JavaScript errors')
    revision=sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision'];post(path,{'action':'void','revision':revision});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening])[0]['id'];post('/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':workflow_cash});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed'
    pathlib.Path('actualizaciones/1.8.0/QA-FLUJOS-USO.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'layout':layouts,'page_errors':errors,'environment':'real fictional PHP/MariaDB HTTP and Chromium; no physical drivers, printers or live AI'},indent=2)+'\n')
if __name__=='__main__':main()
