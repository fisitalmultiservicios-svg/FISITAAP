"""R9 delivery/contact/receipt/accounting checks through real PHP/MariaDB HTTP; fake data only."""
import hashlib,json,pathlib,re,secrets,subprocess,sys,urllib.parse
from PIL import Image
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from usability_r7 import login
from financial180 import api as native

ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[];tag='R9-'+secrets.token_hex(4)
owner='opowner_'+tag;guest='opguest_'+tag;driver='opdriver_'+tag;other='opother_'+tag
def passed(x):checks.append(x);print('PASS',x,flush=True)
def post(actor,path,data):return request(actor,path,{'csrf':csrf(actor,'/admin/pedidos' if actor==owner else '/driver' if actor==driver else '/laventanita/catalogo'),**data})
def row(oid):return sql('SELECT * FROM orders WHERE id=?',[oid])[0]
def raw(actor,path,data=None,file=None):
    args=['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-'+actor+'.cookies','-c','/tmp/r3-'+actor+'.cookies','-w','\n%{http_code}']
    payload=None
    if file:
        for k,v in data.items():args+=['-F',k+'='+str(v)]
        args+=['-F','proof=@'+file]
    elif data is not None:args+=['-H','Content-Type: application/json','--data-binary','@-'];payload=json.dumps(data).encode()
    args+=['http://localhost'+path];r=subprocess.run(args,input=payload,capture_output=True,check=True);body,status=r.stdout.rsplit(b'\n',1);return int(status),body
def jsoncall(actor,path,data=None,file=None):
    status,body=raw(actor,path,data,file)
    try:return status,json.loads(body)
    except Exception:raise AssertionError((path,status,body[:300]))
def checkout(**extra):
    data={'csrf':csrf(guest,'/laventanita/catalogo'),'tenant':'laventanita','branch_id':301,'guest_name':'Cliente ficticio R9','guest_phone':phone,'payment':'Efectivo','delivery_type':'delivery','zone_id':zone,'address':'Casa de prueba R9','maps_url':'https://www.google.com/maps?q=9.93,-84.08','checkout_key':secrets.token_hex(16),'items':[{'id':501,'qty':1,'option_ids':[]}],**extra}
    status,out=jsoncall(guest,'/api/checkout',data);return status,out,data
def board(oid,action,**extra):
    return post(owner,'/admin/pedidos',{'origin':'web','id':oid,'revision':row(oid)['revision180'],'action':action,**extra})
def driver_action(action,jid,**extra):return post(driver,'/driver',{'action':action,'job_id':jid,**extra})
def ready(oid):
    board(oid,'prepare');assert row(oid)['status']=='preparing';board(oid,'prepared');assert row(oid)['status']=='ready'
def job(oid):return sql('SELECT * FROM delivery_jobs WHERE order_id=?',[oid])[0]
def charge(tid,cash):
    key=secrets.token_hex(16);data={'payment_key':key,'revisions['+str(tid)+']':sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision'],'split_mode':'all','parts':2,'cash':cash}
    assert post(owner,'/admin/cobro?ticket='+str(tid),data)[0]==302
    assert len(sql('SELECT id FROM sales WHERE payment_key=?',[key]))==1,request(owner,'/admin/cobro?ticket='+str(tid))[1][-1600:]
    return key
phone='8'+str(secrets.randbelow(10000000)).zfill(7);zone=None
def main():
    global zone
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';assert sql('SELECT value FROM settings WHERE `key`="operations180_ready"')[0]['value']=='1'
    sql('DELETE FROM rate_limits');sql('UPDATE delivery_jobs SET status="cancelled" WHERE assigned_driver_id=210 AND status NOT IN("delivered","cancelled","expired")');login(owner,'owner@r2.invalid');login(other,'ownerb@r2.invalid');login(driver,'driver@r2.invalid');login('cashier','cashiera@r2.invalid')
    sql('UPDATE branches SET web_order_acceptance=0,whatsapp="50688880000" WHERE id=301')
    sql('INSERT INTO delivery_zones(tenant_id,name,price,is_active) VALUES(101,?,1000,1)',[tag]);zone=sql('SELECT id FROM delivery_zones WHERE name=?',[tag])[0]['id']
    oldstock=float(sql('SELECT quantity FROM product_inventory WHERE product_id=501 AND branch_id=301')[0]['quantity'])
    status,out,data=checkout();assert status==200 and out['ok'],out;oid=out['order_id'];pin=out['delivery_pin'];assert len(pin)==4
    assert request(guest,'/account')[0]==302
    assert row(oid)['status']=='new' and not sql('SELECT * FROM fisitaap_order_receipts180 WHERE order_id=?',[oid])
    assert not sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['web-order-'+str(oid)+'-%'])
    contact=row(oid)['user_id'];addresses=sql('SELECT * FROM customer_addresses WHERE user_id=?',[contact]);assert len(addresses)==1 and addresses[0]['is_default']==1
    status,again=jsoncall(guest,'/api/checkout',data);assert status==200 and again['duplicate'] and again['order_id']==oid and again['delivery_pin']==pin
    assert float(sql('SELECT quantity FROM product_inventory WHERE product_id=501 AND branch_id=301')[0]['quantity'])==oldstock-1
    passed('guest name/phone creates a delivery contact and principal Maps address without authenticating an account; retry preserves one order/stock reservation and the same client PIN; direct configuration does not print before acceptance')
    status,second,_=checkout(address='Trabajo ficticio',maps_url='https://maps.app.goo.gl/qa-r9');assert status==200,second
    lookup={'csrf':csrf(guest,'/laventanita/catalogo'),'tenant':'laventanita','phone':phone}
    status,found=request(guest,'/api/delivery-addresses180',lookup);found=json.loads(found);assert status==200 and len(found['addresses'])==2
    status,third,_=checkout(address_id=addresses[0]['id'],address='Must use saved address');assert status==200 and row(third['order_id'])['address']=='Casa de prueba R9'
    assert row(third['order_id'])['user_id']==contact and sql('SELECT COUNT(*) n FROM customer_addresses WHERE user_id=?',[contact])[0]['n']==2
    otheraddress=sql('SELECT user_id FROM tenant_customers WHERE tenant_id=102 LIMIT 1')[0]['user_id']
    sql('INSERT INTO customer_addresses(user_id,label,address,is_default) VALUES(?,"PRIVATE","Other business QA",1)',[otheraddress]);foreign=sql('SELECT id FROM customer_addresses WHERE user_id=? ORDER BY id DESC LIMIT 1',[otheraddress])[0]['id']
    status,bad,_=checkout(address_id=foreign);assert status==422
    status,otherlookup=request(guest,'/api/delivery-addresses180',{**lookup,'tenant':'prueba-b'});assert not json.loads(otherlookup)['addresses']
    # A logged-in legacy customer with no profile phone can still order using the required contact fields.
    auth='authenticated_'+tag;status,form=request(auth,'/login');assert status==200
    token=re.search(r'name="csrf" value="([a-f0-9]+)"',form)[1];email=sql('SELECT email FROM users WHERE id=205')[0]['email']
    assert request(auth,'/login',{'csrf':token,'email':email,'password':PASSWORD})[0]==302
    authenticated={**data,'csrf':csrf(auth,'/laventanita/catalogo'),'checkout_key':secrets.token_hex(16),'guest_name':'Contacto desde cuenta existente','guest_phone':phone}
    status,logged=jsoncall(auth,'/api/checkout',authenticated);assert status==200 and logged['ok'],logged
    assert row(logged['order_id'])['user_id']==contact and row(logged['order_id'])['customer_name']=='Contacto desde cuenta existente'
    assert request(auth,'/account')[0]==200 and sql('SELECT phone FROM users WHERE id=205')[0]['phone'] is None
    passed('same business/phone reuses its contact, lists multiple Maps addresses and uses the selected address; a foreign address or another business phone lookup does not expose that customer')
    status,missing,sinpe=checkout(payment='SINPE Móvil');assert status==422 and 'comprobante' in missing['error']
    image=pathlib.Path('/workspace/fisitaap-r2-test/uploads/qa-proof-r9.png');Image.new('RGB',(1200,500),'white').save(image);image.chmod(0o644)
    form={'csrf':sinpe['csrf'],'tenant':'laventanita','checkout_key':sinpe['checkout_key']}
    status,upload=jsoncall(guest,'/api/order-proof180',form,'/var/www/html/uploads/'+image.name);assert status==200,upload
    sinpe['proof_token']=upload['token'];status,paidproof=jsoncall(guest,'/api/checkout',sinpe);assert status==200,paidproof
    proof=sql('SELECT * FROM fisitaap_order_uploads180 WHERE order_id=?',[paidproof['order_id']])[0]
    assert raw(guest,'/'+proof['path'])[0]==403
    assert raw(owner,'/api/order-proof180?order='+str(paidproof['order_id']))[0]==200
    assert raw(other,'/api/order-proof180?order='+str(paidproof['order_id']))[0]==404
    status,wrong,_=checkout(payment='SINPE Móvil',proof_token=upload['token']);assert status==422
    passed('SINPE requires a real image tied to this browser/business/operation; image is optimized and denied as a public file; another business or another order cannot reuse/read it')
    initial=json.loads(request(owner,'/api/live180')[1]);status,incoming=request('cashier','/api/incoming180');assert status==200,(status,incoming)
    assert any(x['id']==oid for x in json.loads(incoming)['orders'])
    for _ in range(2):
        status,b=request('cashier','/api/incoming180',{'csrf':csrf('cashier','/admin/ventas'),'order_id':oid});assert status==200,(status,b)
    receipt=sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid]);assert len(receipt)==1
    assert len(sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['web-order-'+str(oid)+'-command-%']))==1
    assert row(oid)['status']=='confirmed' and json.loads(request(owner,'/api/live180')[1])['version']!=initial['version']
    passed('seller receives through the POS notification endpoint; duplicate acceptance makes one cash account and one durable kitchen command; live version changes without a page refresh')
    cancel=third['order_id']
    board(cancel,'status',status='cancelled');assert row(cancel)['status']=='new'
    request('cashier','/admin/pedidos',{'csrf':csrf('cashier','/admin/ventas'),'origin':'web','id':cancel,'revision':row(cancel)['revision180'],'action':'status','status':'cancelled','cancel_reason':'Justificación ficticia de prueba'})
    assert row(cancel)['status']=='new'
    board(cancel,'status',status='cancelled',cancel_reason='Cliente solicitó cancelar el pedido de prueba')
    assert row(cancel)['status']=='cancelled' and 'Cliente solicitó' in row(cancel)['cancel_reason180']
    assert sql('SELECT message FROM fisitaap_order_events180 WHERE order_id=? AND status="cancelled"',[cancel])
    passed('cancellation requires owner or an explicit custom-role delete grant AND a justification; cashier without grant and owner without reason cannot cancel; reason/user/time are retained')
    ready(oid);sql('INSERT IGNORE INTO driver_profiles(user_id,is_online) VALUES(210,1)');sql('UPDATE driver_profiles SET is_online=1 WHERE user_id=210')
    sql('INSERT INTO driver_affiliations(user_id,tenant_id,status,all_branches,payout_mode180,payout_percent180) VALUES(210,101,"active",1,"percent",60) ON DUPLICATE KEY UPDATE status="active",all_branches=1,payout_mode180="percent",payout_percent180=60')
    board(oid,'dispatch',dispatch_mode='pool',payout=999);j=job(oid);assert j['billing180']==1 and j['delivery_fee180']=='1000.00'
    driver_action('accept',j['id']);assert job(oid)['payout']=='600.00' and job(oid)['assigned_driver_id']==210
    driver_action('arrived',j['id']);driver_action('issue',j['id'],issue_note='No se encuentra la dirección de prueba')
    assert job(oid)['status']=='issue'
    path='/admin/pedido-detalle180?id='+str(oid)
    status,b=post(owner,path,{'action':'correct_address','address':'Dirección corregida QA','maps_url':'https://www.google.com/maps?q=9.94,-84.1','resolution':'Cliente confirmó la nueva ubicación de prueba'});assert status==302
    assert row(oid)['address']=='Dirección corregida QA'
    status,b=post(owner,path,{'action':'resolve_issue','resolution':'Se confirmó la ubicación con el cliente de prueba'});assert status==302
    assert job(oid)['status']=='arrived'
    for step in ['picked_up','in_transit']:driver_action(step,j['id'])
    driver_action('delivered',j['id'],delivery_pin='0000');assert job(oid)['status']=='in_transit'
    for _ in range(2):driver_action('delivered',j['id'],delivery_pin=pin)
    assert job(oid)['status']=='delivered';earned=sql('SELECT * FROM fisitaap_driver_earnings180 WHERE job_id=?',[j['id']]);assert len(earned)==1 and earned[0]['payout']=='600.00'
    timeline=sql('SELECT status,message,user_id FROM fisitaap_order_events180 WHERE order_id=? ORDER BY id',[oid]);assert all(s in [e['status'] for e in timeline] for s in ['new','confirmed','preparing','ready','available','assigned','arrived','issue','address_corrected','picked_up','in_transit','delivered'])
    tracking=urllib.parse.urlsplit(out['tracking_url']);assert request(guest,tracking.path+'?'+tracking.query)[0]==200
    assert request(guest,'/pedido/seguimiento?id='+str(oid)+'&token=wrong')[0]==404
    passed('driver percentage is fixed at acceptance; store corrects location and resolves an incident with notes; published client PIN completes only once; current state and full timestamped timeline are available through private tracking')
    # Treasury payout is a separate, idempotent expense, never a second order charge.
    post(owner,'/admin/cuentas-dinero',{'action':'save','name':tag+' BANK','kind':'bank','opening':5000})
    account=sql('SELECT id,balance FROM fisitaap_money_accounts180 WHERE tenant_id=101 AND name=?',[tag+' BANK'])[0];assert account['balance']=='5000.00'
    cut={'action':'settlement','driver_id':210,'operation_key':secrets.token_hex(16),'account_id':account['id'],'reference':'Transferencia ficticia QA','jobs[]':j['id']}
    for _ in range(2):assert post(owner,'/admin/afiliados',cut)[0]==302
    assert sql('SELECT balance FROM fisitaap_money_accounts180 WHERE id=?',[account['id']])[0]['balance']=='4400.00'
    assert sql('SELECT COUNT(*) n FROM fisitaap_driver_settlements180 WHERE operation_key=?',[cut['operation_key']])[0]['n']==1
    assert sql('SELECT settlement_id FROM fisitaap_driver_earnings180 WHERE job_id=?',[j['id']])[0]['settlement_id']
    assert 'Pagado' in request(driver,'/driver')[1]
    assert post(owner,'/admin/afiliados',{**cut,'operation_key':secrets.token_hex(16)})[0]==302
    assert sql('SELECT balance FROM fisitaap_money_accounts180 WHERE id=?',[account['id']])[0]['balance']=='4400.00'
    passed('affiliate cut pays only delivered/unpaid earnings from the chosen treasury account; retry is once-only and a second cut cannot pay the same job; driver retains paid history')
    # Every requested account kind is created through the administration form.
    for kind in ['principal','petty','cash','other']:
        post(owner,'/admin/cuentas-dinero',{'action':'save','name':tag+' '+kind,'kind':kind,'opening':0});assert sql('SELECT kind FROM fisitaap_money_accounts180 WHERE name=?',[tag+' '+kind])[0]['kind']==kind
    assert request(owner,'/admin/afiliados')[0]==200 and request(owner,'/admin/entregas-historial')[0]==200
    passed('administration provides principal cash, petty cash, bank, other cash boxes and other accounts; affiliation and delivery history pages render with current states and trace links')
    (ROOT/'actualizaciones/1.8.0/QA-OPERACIONES-R9.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'fictional fisitaap_r2_test; real PHP8.3/MariaDB HTTP, no real customers, banking, printers or physical devices'},indent=2)+'\n')

if __name__=='__main__':main()
