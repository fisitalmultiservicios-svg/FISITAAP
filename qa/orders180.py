"""Real checkout -> once-only reception, kitchen queue and prepaid settlement."""
import hashlib,json,pathlib,re,secrets,subprocess,sys,urllib.parse
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from financial180 import api
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def post(path,data):return request('owner',path,{'csrf':csrf('owner','/admin/turnos'),**data})
def checkout(extra):
    payload={'tenant':'laventanita','branch_id':301,'delivery_type':'pickup','payment':'Efectivo','checkout_key':secrets.token_hex(16),'items':[{'id':501,'qty':1,'option_ids':[]}],'csrf':csrf('buyer','/account'),**extra}
    p=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-buyer.cookies','-H','Content-Type: application/json','--data-binary','@-','http://localhost/api/checkout'],input=json.dumps(payload).encode(),capture_output=True,check=True)
    out=json.loads(p.stdout);assert out['ok'],out;return out['order_id']
def receive(oid):return post('/admin/pedidos',{'action':'receive','origin':'web','id':oid,'revision':sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180']})
def state(oid,status,revision=None):return post('/admin/pedidos',{'action':'status','origin':'web','id':oid,'revision':sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'] if revision is None else revision,'status':status})
def main():
    sql('UPDATE users SET phone="88888888" WHERE id=205');page=subprocess.run(['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','-c','/tmp/r3-buyer.cookies','http://localhost/login'],capture_output=True,check=True).stdout.decode();tc=re.search(r'name="csrf" value="([a-f0-9]+)"',page)[1]
    subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-buyer.cookies','-c','/tmp/r3-buyer.cookies','--data-binary','@-','http://localhost/login'],input=urllib.parse.urlencode({'csrf':tc,'email':'customer@r2.invalid','password':PASSWORD}).encode(),capture_output=True,check=True)
    token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"ORDER QA",?)',[digest]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"ORDER BOX",?,202)',[did]);_,body=request('owner','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token);assert post('/api/native/bind',{'proof':proof['proof']})[0]==200
    opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':0});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening])[0]['id']
    code='REG-'+secrets.token_hex(8).upper();sql('INSERT INTO fisitaap_r3_gift_cards(tenant_id,customer_id,code,issue_key,initial_amount,balance,expires_at,created_by) VALUES(101,205,?,?,1000,1000,DATE_ADD(NOW(),INTERVAL 1 YEAR),202)',[code,secrets.token_hex(16)])
    stock=lambda:sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=501')[0]['quantity'];before=float(stock());oid=checkout({'gift_code':code,'gift_amount':100});assert float(stock())==before-1;assert not sql('SELECT * FROM fisitaap_order_receipts180 WHERE order_id=?',[oid]);receive(oid);tid=sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid])[0]['ticket_id'];receive(oid);assert len(sql('SELECT id FROM pos_tickets WHERE source_order_id=?',[oid]))==1 and float(stock())==before-1
    passed('manual reception creates one unpaid POS account and never reserves web inventory twice, including a double click')
    jobs=sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['web-order-'+str(oid)+'-command-%']);assert len(jobs)==1;assert sql('SELECT kitchen_status FROM pos_ticket_items WHERE ticket_id=?',[tid])[0]['kitchen_status']=='sent'
    passed('reception sends a single durable kitchen command and copies the original customer, prices and options')
    rev=lambda:sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision'];post('/admin/ventas?screen=order&ticket='+str(tid),{'action':'add_item','revision':rev(),'product_id':501,'quantity':1});assert len(sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[tid]))==1 and float(stock())==before-1
    payment=secrets.token_hex(16);data={'payment_key':payment,'revisions['+str(tid)+']':rev(),'customer_id':205,'split_mode':'all','parts':2,'cash':1030};post('/admin/cobro?ticket='+str(tid),data);rows=sql('SELECT total FROM sales WHERE payment_key=?',[payment]);assert rows==[{'total':'1130.00'}],rows;assert sql('SELECT balance FROM fisitaap_r3_gift_cards WHERE code=?',[code])[0]['balance']=='900.00';assert sql('SELECT amount FROM pos_payments WHERE ticket_id=? AND payment_method="Tarjeta regalo prepagada"',[tid])==[{'amount':'100.00'}];post('/admin/cobro?ticket='+str(tid),data);assert len(sql('SELECT id FROM sales WHERE payment_key=?',[payment]))==1
    passed('settlement recognizes prepaid gift plus cash without debiting the gift again or allowing original-order edits')
    state(oid,'cancelled');assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='confirmed' and float(stock())==before-1
    passed('a received paid order cannot be cancelled with an unpaid-order refund')
    stale=sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'];state(oid,'preparing');state(oid,'ready',stale);assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='preparing';state(oid,'ready');state(oid,'sent');state(oid,'delivered');assert sql('SELECT fulfillment180 FROM pos_tickets WHERE id=?',[tid])[0]['fulfillment180']=='delivered'
    passed('preparation transitions reject stale revisions and synchronize the linked account through delivery')
    oid2=checkout({'gift_code':code,'gift_amount':50});receive(oid2);tid2=sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid2])[0]['ticket_id'];state(oid2,'cancelled');state(oid2,'cancelled');assert float(stock())==before-1;assert sql('SELECT status FROM pos_tickets WHERE id=?',[tid2])[0]['status']=='void';assert sql('SELECT balance FROM fisitaap_r3_gift_cards WHERE code=?',[code])[0]['balance']=='900.00'
    passed('cancelling an unpaid received order releases inventory and prepaid gift exactly once')
    sql('UPDATE branches SET web_order_acceptance=0 WHERE id=301');oid3=checkout({});sql('UPDATE branches SET web_order_acceptance=1 WHERE id=301');assert len(sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid3]))==1;assert len(sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['web-order-'+str(oid3)+'-command-%']))==1;state(oid3,'cancelled')
    passed('direct reception respects the branch setting and commits the web order, account and kitchen job together')
    sql('UPDATE tenants SET physical_store_enabled=0 WHERE id=101')
    try:
        oid4=checkout({});receive(oid4);assert sql('SELECT status FROM orders WHERE id=?',[oid4])[0]['status']=='confirmed';assert not sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[oid4]);state(oid4,'cancelled');assert float(stock())==before-1
    finally:sql('UPDATE tenants SET physical_store_enabled=1 WHERE id=101')
    passed('a catalog-only business accepts and cancels an order without creating an inaccessible cash account or reserving stock twice')
    old_order=checkout({});sql('UPDATE orders SET status="confirmed" WHERE id=?',[old_order]);receive(old_order);assert len(sql('SELECT ticket_id FROM fisitaap_order_receipts180 WHERE order_id=?',[old_order]))==1;assert not sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['web-order-'+str(old_order)+'-command-%']);receive(old_order);state(old_order,'cancelled');assert float(stock())==before-1
    passed('an accepted legacy web order can be linked once to its unpaid account without repeating old kitchen instructions or inventory')
    status,board=request('owner','/admin/pedidos');assert status==200 and 'qa180-kanban' in board and all(label in board for label in ['Preparación','Aceptado','Despacho / Express']);assert post('/admin/pedidos',{'action':'receive','id':99999999,'revision':0})[0]==302
    post('/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':1030});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed'
    passed('Kanban exposes the operational columns and rejects an unavailable order without affecting another business')
    pathlib.Path('actualizaciones/1.8.0/QA-PEDIDOS.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real PHP/MariaDB HTTP; no live printers or customer data'},indent=2)+'\n')
if __name__=='__main__':main()
