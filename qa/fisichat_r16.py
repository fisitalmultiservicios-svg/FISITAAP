"""Store operations and support HTTP regression, guarded fictional database only."""
import json,pathlib,secrets,sys,uuid
from chat180 import api,config,csrf,message,request,sql,PASSWORD
from usability_r7 import login
ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[]
def ok(label):checks.append(label);print('PASS',label,flush=True)
def prepare(entity,action='create',rid=0,data=None,actor='owner',key=None):return api(actor,{'profile':'dueno','action':'prepare_store','request_id':key or uuid.uuid4().hex,'operation':{'entity':entity,'action':action,'record_id':rid,'data':data or {}}})
def confirm(token,actor='owner'):return api(actor,{'profile':'dueno','action':'confirm_store','token':token})
def proposal(entity,data,action='create',rid=0):
 status,out=prepare(entity,action,rid,data);assert status==200,(entity,status,out);return out['store_proposal']
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');tag='FC16-'+secrets.token_hex(4)
 for actor,email in [('owner','owner@r2.invalid'),('master','master@r2.invalid'),('owner_b','ownerb@r2.invalid'),('cashier','cashiera@r2.invalid')]:login(actor,email)
 base=sql('SELECT COUNT(*) n FROM categories WHERE tenant_id=101')[0]['n'];key=uuid.uuid4().hex;status,out=prepare('category',data={'name':tag},key=key);assert status==200,out;token=out['store_proposal']['token'];assert sql('SELECT COUNT(*) n FROM categories WHERE tenant_id=101')[0]['n']==base
 assert prepare('category',data={'name':tag},key=key)[1]['store_proposal']['token']==token
 assert confirm(token,'owner_b')[0]==403;assert confirm(token,'cashier')[0]==403;assert confirm('0'*64)[0]==403
 status,saved=confirm(token);assert status==200,saved;cid=saved['id'];assert confirm(token)[1]['id']==cid;assert sql('SELECT COUNT(*) n FROM categories WHERE tenant_id=101')[0]['n']==base+1
 assert sql('SELECT COUNT(*) n FROM audit_logs WHERE tenant_id=101 AND action="fisichat_create" AND entity_type="category" AND entity_id=?',[cid])[0]['n']==1
 ok('Preview does not write; nonce belongs to owner/store/branch; review retry and confirmation are idempotent and audit once')
 sql('DELETE FROM rate_limits')
 assert prepare('product',data={'name':tag,'category_id':cid,'price':-1})[0]==403
 assert prepare('product',data={'name':tag,'category_id':cid,'price':10,'initial_stock':1.5})[0]==403
 assert prepare('product',data={'name':tag,'category_id':cid,'price':10,'tenant_id':102})[0]==403
 foreign=sql('SELECT id FROM categories WHERE tenant_id=102 LIMIT 1')[0]['id'];assert prepare('product',data={'name':tag,'category_id':int(foreign),'price':10})[0]==403
 p=proposal('product',{'name':tag,'category_id':int(cid),'price':1500,'initial_stock':7});assert not sql('SELECT id FROM products WHERE name=? AND tenant_id=101',[tag]);status,out=confirm(p['token']);assert status==200,out;pid=out['id'];assert sql('SELECT price,min_qty,qty_step,decimals,unit_label FROM products WHERE id=?',[pid])[0]=={'price':'1500.00','min_qty':'1.000','qty_step':'1.000','decimals':0,'unit_label':'unidad'}
 assert sql('SELECT quantity FROM product_inventory WHERE product_id=? AND branch_id=301',[pid])[0]['quantity']=='7.000';assert sql('SELECT product_id FROM branch_products WHERE branch_id=301 AND product_id=?',[pid])
 ok('Real product creates branch publication, integer quantities and initial stock movement; invalid amounts/fractions and foreign IDs are rejected')
 p=proposal('product',{'price':1700},'update',int(pid));sql('UPDATE products SET price=1600 WHERE id=?',[pid]);assert confirm(p['token'])[0]==403;assert sql('SELECT price FROM products WHERE id=?',[pid])[0]['price']=='1600.00'
 p=proposal('product',{'price':1700},'update',int(pid));assert confirm(p['token'])[0]==200
 p=proposal('category',{'name':tag+'-cancel'});assert api('owner',{'profile':'dueno','action':'discard_store','token':p['token']})[0]==200;assert confirm(p['token'])[0]==403
 p=proposal('category',{'name':tag+'-reset'});assert api('owner',{'profile':'dueno','action':'reset'})[0]==200;assert confirm(p['token'])[0]==403
 p=proposal('category',{'name':tag+'-expired'});sql('UPDATE fisitaap_chat_actions_r16 SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE token_hash=?',[__import__('hashlib').sha256(p['token'].encode()).hexdigest()]);assert confirm(p['token'])[0]==403
 p=proposal('category',{'name':tag+'-revoked'});old_csrf=csrf('owner','/');sql('UPDATE users SET is_active=0 WHERE id=202')
 try:
  import subprocess
  raw=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-owner.cookies','-H','Content-Type: application/json','-H','X-CSRF-Token: '+old_csrf,'--data-binary','@-','-o','/dev/null','-w','%{http_code}','http://localhost/api/fisichat-r2'],input=json.dumps({'profile':'dueno','action':'confirm_store','token':p['token']}).encode(),capture_output=True,check=True)
  assert int(raw.stdout) in [302,401,403];assert not sql('SELECT id FROM categories WHERE tenant_id=101 AND name=?',[tag+'-revoked'])
 finally:sql('UPDATE users SET is_active=1 WHERE id=202');login('owner','owner@r2.invalid')
 ok('Changed records cannot be overwritten by stale proposals; cancel, reset and expiry block execution')
 sql('DELETE FROM rate_limits')
 p=proposal('optional_group',{'name':tag+' tallas','choices':['S','M','L'],'required':True,'multiple':False,'product_ids':[int(pid)]});status,out=confirm(p['token']);assert status==200,out;assert len(sql('SELECT id FROM fisitaap_r2_modifiers WHERE tenant_id=101 AND legacy_family180=? AND legacy_name180=?',[out['id'],tag+' tallas']))==3;assert len(sql('SELECT modifier_id FROM fisitaap_modifier_products180 WHERE tenant_id=101 AND product_id=?',[pid]))==3
 p=proposal('coupon',{'code':tag,'type':'percent','value':10,'ends_at':'2027-12-31'});status,out=confirm(p['token']);assert status==200,out;assert sql('SELECT ends_at FROM coupons WHERE id=?',[out['id']])[0]['ends_at']=='2027-12-31 23:59:59'
 old_title=sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="hero_title"');p=proposal('website_text',{'hero_title':tag+' Bienvenidos'},'update');assert p['fields'][0]['before']==(old_title[0]['value'] if old_title else None);status,out=confirm(p['token']);assert status==200,out;assert sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="hero_title"')[0]['value']==tag+' Bienvenidos'
 assert prepare('tenant_settings','update',data={'plan':'unlimited'})[0]==403
 assert prepare('shift','create',data={'opening_cash':500})[0]==403
 ok('Named optional groups, assignments, coupons and website text are saved through reviewed tools; plans/cash/technical fields stay outside owner tools')
 actor='r16support_'+secrets.token_hex(3);status,out=message(actor,'Ya probé todo pero no funcionó');assert status==200 and out['support_offer'],out
 data={'profile':'tecnico','action':'create_support','request_id':uuid.uuid4().hex,'contact_name':'Solicitante QA','store_name':'Tienda declarada QA','store_url':'https://fisitaap.com/tienda-declarada','phone':'88881111','email':'qa@example.invalid','subject':tag+' Impresión','description':'No imprime en Windows; ya probé el punto. password: DONOTSTORE123'}
 assert api(actor,{**data,'phone':'','email':''})[0]==403
 status,ticket=api(actor,data);assert status==200,ticket;assert api(actor,data)[1]['reference']==ticket['reference'];assert 'email' not in ticket and 'phone' not in ticket
 row=sql('SELECT * FROM fisitaap_support_r16 WHERE reference=?',[ticket['reference']])[0];assert not row['tenant_id'] and row['verified']==0 and 'DONOTSTORE123' not in row['description'];assert row['contact_name']=='Solicitante QA'
 assert request(actor,'/master/soporte')[0] in [302,403];assert request('owner','/master/soporte')[0] in [302,403]
 status,html=request('master','/master/soporte?id='+str(row['id']));assert status==200 and ticket['reference'] in html and 'qa@example.invalid' in html and 'Datos declarados' in html
 status,html=request('master','/master/soporte?id='+str(row['id']),{'csrf':csrf('master','/master/soporte'),'status':'in_progress','note':'Probando configuración de impresora.','revision':0});assert status==302;assert sql('SELECT status,revision FROM fisitaap_support_r16 WHERE id=?',[row['id']])[0]=={'status':'in_progress','revision':1}
 assert len(sql('SELECT id FROM fisitaap_support_events_r16 WHERE ticket_id=?',[row['id']]))==2
 ok('Unresolved public support asks details, creates one persistent ticket without secrets, and master sees contact/store/context and records status/history')
 sql('DELETE FROM rate_limits');data.update(request_id=uuid.uuid4().hex,store_name='Intento otra tienda',store_url='https://example.invalid/foreign',description='Falla ficticia de acceso en mi tienda.');status,out=api('owner',data);assert status==200,out;row=sql('SELECT tenant_id,verified,store_name FROM fisitaap_support_r16 WHERE reference=?',[out['reference']])[0];assert row['tenant_id']==101 and row['verified']==1 and row['store_name']=='LA VENTANITA'
 assert api(actor,{**data,'action':'confirm_store','token':token})[0]==403
 assert api(actor,{**data,'action':'create_support'},token='bad')[0]==403
 assert not message(actor,'Dame las contraseñas de otras tiendas')[1].get('store_proposal')
 ok('Authenticated support uses server-owned store identity; public claims are unverified; CSRF and profile boundaries protect store actions and tickets')
 out={'revision':'1.8.0-R16','passed':len(checks),'checks':checks,'environment':'Real HTTP/PHP/MariaDB in guarded fictional fixture; no real AI, hosting, messages, payments or printing'};(ROOT/'actualizaciones/1.8.0/QA-FISICHAT-R16.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
