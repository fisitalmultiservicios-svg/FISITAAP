"""R18 real HTTP/CSRF/database regression. All records are fictional, guarded locally."""
import datetime,hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
from usability_r7 import login
from financial180 import api
ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[]
def passed(s):checks.append(s);print('PASS',s,flush=True)
def post(a,p,d):return request(a,p,{'csrf':csrf(a,p if p=='/owner-login' else '/admin'),**d})
def bind(actor,uid,token):
 body=request(actor,'/admin')[1];ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);s,r=api('web-session',{'nonce':ctx['nonce'],'user_id':uid},token);assert s==200,r
 assert post(actor,'/api/native/bind',{'proof':r['proof']})[0]==200;return r
def device(tag,number):
 token=secrets.token_hex(32);sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,?,?)',[tag,hashlib.sha256(token.encode()).hexdigest()]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE name=?',[tag])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,number_r18) VALUES(101,301,?,?,?)',[tag,did,number]);rid=sql('SELECT id FROM fisitaap_registers180 WHERE device_id=?',[did])[0]['id'];return token,did,rid
def open_shift(actor,cash=100):
 key=secrets.token_hex(16);post(actor,'/admin/turnos',{'action':'open','opening_key':key,'opening_cash':cash});r=sql('SELECT * FROM pos_shifts WHERE opening_key=?',[key]);return r[0] if r else None
def ticket(actor,pid,table=None):
 key=secrets.token_hex(16);post(actor,'/admin/ventas',{'action':'table' if table else 'quick','table_id':table or 0,'open_key':key});r=sql('SELECT * FROM pos_tickets WHERE open_key=? AND tenant_id=101',[key])[0];path='/admin/ventas?screen=order&ticket='+str(r['id']);post(actor,path,{'action':'add_item','revision':r['revision'],'product_id':pid,'quantity':1});assert sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[r['id']]);return r['id'],path
def edit(actor,tid,path,**kw):
 rev=sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision'];return post(actor,path,{'revision':rev,**kw})
def settle(actor,tid,method='card',seller=None):
 r=sql('SELECT revision,total FROM pos_tickets WHERE id=?',[tid])[0];data={'payment_key':secrets.token_hex(16),f'revisions[{tid}]':r['revision'],'split_mode':'all',method:r['total'],'cash':r['total'] if method=='cash' else 0}
 if seller is not None:data['seller_id_r18']=seller
 path='/admin/ventas?screen=checkout&ticket='+str(tid);post(actor,path,data);return data,path

def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('owner','owner@r2.invalid');login('owner_b','ownerb@r2.invalid');tag='R18-'+secrets.token_hex(4)
 # Employee creation and alias/password policy via production UI.
 emp={'action':'save_employee','name':tag+' Cajero','email':tag.lower()+'@example.invalid','username':'c'+tag.lower(),'password':'2468','identification':tag,'job_title':'Caja QA','start_date':'2024-01-01','birth_date':datetime.date.today().replace(year=1996).isoformat(),'branch_id':301,'role':'cashier','salary':'600000','pay_frequency':'monthly','contract_type':'indefinite','workday':'day','weekly_days':6,'is_active':1,'leave_opening':30,'leave_asof':'2025-01-01'}
 post('owner','/admin/personal',emp);e=sql('SELECT e.id,e.user_id FROM fisitaap_employees_r17 e JOIN users u ON u.id=e.user_id WHERE u.email=?',[emp['email']])[0];uid=e['user_id'];actor=tag+'cashier';assert post(actor,'/owner-login',{'email':emp['username'],'password':'2468'})[0]==302;assert request(actor,'/admin')[0]==200
 duplicate={**emp,'name':tag+' Otro negocio','email':tag.lower()+'b@example.invalid','identification':tag+'b','branch_id':302};post('owner_b','/admin/personal',duplicate);assert sql('SELECT id FROM users WHERE email=?',[duplicate['email']]);post(tag+'ambiguous','/owner-login',{'email':emp['username'],'password':'2468'});assert request(tag+'ambiguous','/admin')[0]==302
 passed('Four-digit staff password works; quick name required and scoped per business; ambiguous global alias cannot choose another store')
 token,did,rid=device(tag,800000+secrets.randbelow(50000));proof=bind(actor,uid,token);assert proof['cash'];shift=open_shift(actor);assert shift,request(actor,'/admin/turnos')[1][-1000:]
 bind('owner',202,token);assert not open_shift('owner');assert 'otro usuario' in request('owner','/admin/turnos')[1]
 key=secrets.token_hex(16);post('owner','/admin/turnos',{'action':'close','id':shift['id'],'closing_key':key,'other_cash':100});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift['id']])[0]['status']=='open'
 passed('Only the actual opener can close a shift; owner on the same real native register cannot close it or open a duplicate')
 # own test product, no external printer.
 sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,track_inventory,min_qty,qty_step,decimals) VALUES(101,401,?,?,?,1000,0,1,1,1,0)',[tag,tag.lower(),tag]);pid=sql('SELECT id FROM products WHERE sku=?',[tag])[0]['id'];sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(101,301,?,100)',[pid]);sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(301,?,1)',[pid])
 # Owner handles deletion and discounted tickets because cashier requires explicit cancel permission.
 t,p=ticket('owner',pid);i=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[t])[0]['id'];edit('owner',t,p,action='quantity',item_id=i,quantity=0);assert sql('SELECT status,total FROM pos_tickets WHERE id=?',[t])[0]=={'status':'void','total':'0.00'};assert sql('SELECT quantity FROM product_inventory WHERE product_id=?',[pid])[0]['quantity']=='100.000';post('owner',p,{'action':'quantity','item_id':i,'quantity':0,'revision':1});assert sql('SELECT quantity FROM product_inventory WHERE product_id=?',[pid])[0]['quantity']=='100.000'
 t,p=ticket('owner',pid);edit('owner',t,p,action='discount',discount=1000);assert sql('SELECT status,total FROM pos_tickets WHERE id=?',[t])[0]=={'status':'open','total':'0.00'};i=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[t])[0]['id'];edit('owner',t,p,action='quantity',item_id=i,quantity=0);assert sql('SELECT status FROM pos_tickets WHERE id=?',[t])[0]['status']=='void'

 # A table and its linked secondary table both become available on final removal.
 tables=[]
 for n in range(2):
  sql('INSERT INTO restaurant_tables(tenant_id,branch_id,name,capacity,space_type,status) VALUES(101,301,?,4,"table","available")',[tag+' mesa '+str(n)]);tables.append(sql('SELECT id FROM restaurant_tables WHERE name=?',[tag+' mesa '+str(n)])[0]['id'])
 t,p=ticket('owner',pid,tables[0]);sql('INSERT INTO fisitaap_ticket_tables180(tenant_id,branch_id,ticket_id,table_id) VALUES(101,301,?,?)',[t,tables[1]]);sql('UPDATE restaurant_tables SET status="occupied" WHERE id=?',[tables[1]]);i=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[t])[0]['id'];edit('owner',t,p,action='quantity',item_id=i,quantity=0);assert all(x['status']=='available' for x in sql('SELECT status FROM restaurant_tables WHERE id IN(?,?)',tables));assert sql('SELECT status FROM pos_tickets WHERE id=?',[t])[0]['status']=='void'
 passed('Removing the final product auto-voids the empty bill and restores stock once and frees linked tables; a 100% discount with products leaves the account open')
 # Optional seller setting and validation under a real collecting cashier session.
 old=sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="ask_seller_r18"');sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(101,"ask_seller_r18","1") ON DUPLICATE KEY UPDATE value="1"')
 try:
  t,p=ticket(actor,pid);data,checkout=settle(actor,t);assert sql('SELECT status FROM pos_tickets WHERE id=?',[t])[0]['status']=='open';foreign=sql('SELECT id FROM users WHERE tenant_id=102 AND role="tenant_admin"')[0]['id'];post(actor,checkout,{**data,'seller_id_r18':foreign});assert not sql('SELECT id FROM sales WHERE payment_key=?',[data['payment_key']])
  post(actor,checkout,{**data,'seller_id_r18':202});sale=sql('SELECT id,seller_id_r18,created_by FROM sales WHERE payment_key=?',[data['payment_key']])[0];assert sale['seller_id_r18']==202 and sale['created_by']==uid
  entries=sql('SELECT id,amount,account_id FROM fisitaap_money_entries180 WHERE entity_type="sale" AND entity_id=?',[sale['id']]);assert len(entries)==1 and entries[0]['amount']=='1000.00';a=sql('SELECT balance,register_id_r18,channel_r18 FROM fisitaap_money_accounts180 WHERE id=?',[entries[0]['account_id']])[0];assert a=={'balance':'1000.00','register_id_r18':rid,'channel_r18':'card'},a
  post(actor,checkout,{**data,'seller_id_r18':202});assert len(sql('SELECT id FROM sales WHERE payment_key=?',[data['payment_key']]))==1;assert sql('SELECT balance FROM fisitaap_money_accounts180 WHERE id=?',[entries[0]['account_id']])[0]['balance']=='1000.00'
  t2,p2=ticket(actor,pid);d2,ch2=settle(actor,t2,'sinpe',202);sale2=sql('SELECT id FROM sales WHERE payment_key=?',[d2['payment_key']])[0]['id'];assert sql('SELECT a.channel_r18,e.amount FROM fisitaap_money_entries180 e JOIN fisitaap_money_accounts180 a ON a.id=e.account_id WHERE e.entity_type="sale" AND e.entity_id=?',[sale2])[0]=={'channel_r18':'sinpe','amount':'1000.00'}
  # Inspect shift expected via close preview UI: no card/SINPE counted as cash.
  html=request(actor,'/admin/turnos')[1];assert 'data-expected="10000"' in html
  passed('Seller prompt rejects missing/foreign staff, preserves cashier identity; card/SINPE credit own-register accounts once and do not inflate shift cash')
 finally:
  sql('DELETE FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="ask_seller_r18"')
  if old:sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(101,"ask_seller_r18",?)',[old[0]['value']])
 # Different real native register; money selector is scoped.
 tokenb,didb,ridb=device(tag+'b',900000+secrets.randbelow(50000));bind('owner',202,tokenb);sb=open_shift('owner');assert sb
 assert str(entries[0]['account_id']) not in re.findall(r'<option value="(\d+)"',request('owner','/admin/cxc')[1])
 t3,p3=ticket('owner',pid);d3,_=settle('owner',t3,'card');s3=sql('SELECT id FROM sales WHERE payment_key=?',[d3['payment_key']])[0]['id'];assert sql('SELECT a.register_id_r18 FROM fisitaap_money_entries180 e JOIN fisitaap_money_accounts180 a ON a.id=e.account_id WHERE e.entity_type="sale" AND e.entity_id=?',[s3])[0]['register_id_r18']==ridb
 assert sql('SELECT balance FROM fisitaap_money_accounts180 WHERE id=?',[entries[0]['account_id']])[0]['balance']=='1000.00'
 passed('A second register receives its own funds; account selectors do not offer another register’s funds')
 # Order-only box cannot open shift or settle, but attends real table/account.
 zero,didz,ridz=device(tag+'zero',0);zp=bind('owner',202,zero);assert not zp['cash'] and not zp['offline_policy']['allowed'];assert not open_shift('owner');t4,p4=ticket('owner',pid);d4,_=settle('owner',t4,'cash');assert not sql('SELECT id FROM sales WHERE payment_key=?',[d4['payment_key']]);assert sql('SELECT status FROM pos_tickets WHERE id=?',[t4])[0]['status']=='open';edit('owner',t4,p4,action='void',cancel_reason='Cierre ficticio de QA')
 passed('Caja 0 gets attention/print capability only: can open and add products, cannot collect, open cash shifts or gain offline sales')
 # Birthday and leave reminders expose only authenticated owner data, dismiss per owner.
 s,body=request('owner','/api/owner-alerts-r18');assert s==200;alerts=json.loads(body)['alerts'];birthday=next(a for a in alerts if tag+' Cajero' in a['title'] and a['type']=='birthday');assert any(a['type']=='vacation' and tag in a['title'] for a in alerts)
 assert request(actor,'/api/owner-alerts-r18')[0] in [302,403];other=json.loads(request('owner_b','/api/owner-alerts-r18')[1]);assert not any(tag+' Cajero' in a['title'] for a in other['alerts']);post('owner','/api/owner-alerts-r18',{'id':birthday['id']});assert not any(a['id']==birthday['id'] for a in json.loads(request('owner','/api/owner-alerts-r18')[1])['alerts'])
 assert not any('password' in u or 'password_hash' in u for u in json.loads(request(actor,'/api/staff-r18')[1])['users'])
 passed('Owner-only birthday/vacation alerts are own-store scoped, persist Visto per owner; staff selector exposes no password data')
 # Original opener may close its own register; then no other user inherits its authority.
 post(actor,'/admin/turnos',{'action':'close','id':shift['id'],'closing_key':secrets.token_hex(16),'other_cash':100});assert sql('SELECT status,expected_cash FROM pos_shifts WHERE id=?',[shift['id']])[0]=={'status':'closed','expected_cash':'100.00'}
 passed('Original cashier closes its own shift with independent cash reconciliation after noncash sales')
 out={'revision':'1.8.0-R18','passed':len(checks),'checks':checks,'environment':'Real HTTP/PHP/MariaDB, guarded fictional fixture; native transport simulated using production authorization, no bank/WhatsApp/physical printer'};(ROOT/'actualizaciones/1.8.0/QA-OPERACION-R18.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
