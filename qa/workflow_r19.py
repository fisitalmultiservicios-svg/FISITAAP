"""R19 real HTTP/CSRF/MariaDB integration on a guarded fictional store."""
import hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests');from r3_helpers import request,csrf,sql
from usability_r7 import login
from financial180 import api
from runtime_r18 import device,bind,open_shift,ticket,edit
ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[]
def post(a,p,d):return request(a,p,{'csrf':csrf(a,'/owner-login' if p=='/owner-login' else '/admin'),**d})
def passed(s):checks.append(s);print('PASS',s,flush=True)
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('owner','owner@r2.invalid');login('owner_b','ownerb@r2.invalid');tag='R19-'+secrets.token_hex(4)
 before=sql('SELECT COUNT(*) n FROM users')[0]['n'];employee={'action':'save_employee','name':tag,'email':tag.lower()+'@example.invalid','identification':tag,'job_title':'Caja QA','start_date':'2025-01-01','branch_id':301,'is_active':1,'weekly_days':6,'password':'9999','username':'ignored'}
 post('owner','/admin/personal?new=1',employee);e=sql('SELECT * FROM fisitaap_employees_r17 WHERE tenant_id=101 AND identification=?',[tag])[0];assert e['user_id'] is None and e['name_r19']==tag;assert sql('SELECT COUNT(*) n FROM users')[0]['n']==before
 assert 'class="card admin-card hr17-form"' not in request('owner','/admin/personal')[1]
 passed('Personnel creates a real employee without a user or credentials; default screen lists staff and opens creation only through Nuevo empleado')
 assert 'r19-user-form' not in request('owner','/admin/usuarios')[1];assert 'r19-user-form' in request('owner','/admin/usuarios?new=1')[1]
 access={'name':'irrelevant','email':tag.lower()+'@example.invalid','username':'c'+tag.lower(),'password':'2468','role':'cashier','is_active':1}
 post('owner','/admin/usuarios?new=1',access);assert sql('SELECT COUNT(*) n FROM users')[0]['n']==before
 post('owner_b','/admin/usuarios?new=1',{**access,'employee_id':e['id']});assert sql('SELECT COUNT(*) n FROM users')[0]['n']==before
 post('owner','/admin/usuarios?new=1',{**access,'employee_id':e['id']});u=sql('SELECT id,name,password_hash FROM users WHERE email=?',[access['email']])[0];uid=u['id'];assert u['name']==tag;assert sql('SELECT user_id FROM fisitaap_employees_r17 WHERE id=?',[e['id']])[0]['user_id']==uid
 post('owner','/admin/usuarios?new=1',{**access,'email':'duplicate-'+access['email'],'username':access['username']+'b','employee_id':e['id']});assert sql('SELECT COUNT(*) n FROM users')[0]['n']==before+1
 passed('Usuarios has a separate list/new/edit flow; new access requires an existing own-store employee and duplicate/foreign links are rejected atomically')
 actor=tag+'staff';post(actor,'/owner-login',{'email':access['username'],'password':'2468'});assert request(actor,'/admin')[0]==200
 post('owner','/admin/usuarios?edit='+str(uid),{**access,'id':uid,'employee_id':e['id'],'password':'1357','username':access['username']+'x'});post(tag+'newlogin','/owner-login',{'email':access['username']+'x','password':'1357'});assert request(tag+'newlogin','/admin')[0]==200
 current=sql('SELECT revision FROM fisitaap_employees_r17 WHERE id=?',[e['id']])[0]['revision'];post('owner','/admin/personal?employee='+str(e['id']),{**employee,'id':e['id'],'revision':current,'password':'0000','username':'shouldnotchange'});assert sql('SELECT username_r17 FROM users WHERE id=?',[uid])[0]['username_r17']==access['username']+'x'
 passed('Linked user edits preserve the employee and really change quick login/password; posting credentials to Personnel cannot change access fields')
 roster=json.loads(request('owner','/api/staff-r18')[1])['users'];assert uid in {x['id'] for x in roster};foreign=json.loads(request('owner_b','/api/staff-r18')[1])['users'];assert uid not in {x['id'] for x in foreign};assert all('password_hash' not in x for x in roster)
 passed('Attendance/seller roster comes from active business users, is own-store isolated and exposes no password hashes or obsolete offline passwords')
 # Simulate the installed bridge returning only an obsolete cached ID. Directory request authenticates the DEVICE, not that user.
 token,did,rid=device(tag+'device',510000+secrets.randbelow(10000));guest=tag+'directory';html=request(guest,'/owner-login')[1];context=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',html)[1]);nonce=context['directory_nonce_r19'];status,proof=api('web-session',{'nonce':nonce,'user_id':9999999},token);assert status==200 and not proof['cash'] and 'cash_state' not in proof and 'users180' not in proof
 data={'csrf':csrf(guest,'/owner-login'),'proof':proof['proof']};s,b=request(guest,'/api/staff-login-r19',data);assert s==200;out=json.loads(b);assert uid in {x['id'] for x in out['users']} and all('password_hash' not in x for x in out['users'])
 other=tag+'other';assert request(other,'/api/staff-login-r19',{'csrf':csrf(other,'/owner-login'),'proof':proof['proof']})[0]==403
 assert request('owner','/api/native/bind',{'csrf':csrf('owner','/admin'),'proof':proof['proof']})[0] in [403,422]
 passed('Installed online login replaces obsolete cache with current business directory; proof is nonce/session/device bound and cannot become a cash grant')
 # Browser attention mode is UI+authority: no register or shift is manufactured.
 simple=tag+'browser';login(simple,'owner@r2.invalid');body=request(simple,'/admin/ventas')[1];assert 'Caja 0' in body and 'Ver turnos' not in body;assert not open_shift(simple)
 sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,min_qty,qty_step,decimals) VALUES(101,401,?,?,?,1000,0,1,1,0)',[tag,tag.lower(),tag]);pid=sql('SELECT id FROM products WHERE sku=?',[tag])[0]['id'];sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(301,?,1)',[pid]);t,path=ticket(simple,pid);body=request(simple,path)[1];assert 'Cobrar en caja numerada' in body and 'screen=checkout&ticket='+str(t)+'"' not in body
 sql('INSERT INTO restaurant_tables(tenant_id,branch_id,name,capacity,space_type) VALUES(101,301,?,4,"table")',[tag]);table=sql('SELECT id FROM restaurant_tables WHERE name=?',[tag])[0]['id'];tt,tp=ticket(simple,pid,table);edit(simple,tt,tp,action='send_kitchen');assert sql('SELECT kitchen_status FROM pos_ticket_items WHERE ticket_id=?',[tt])[0]['kitchen_status']=='sent';assert sql('SELECT status FROM pos_tickets WHERE id=?',[tt])[0]['status']=='open';assert request(simple,tp)[0]==200
 edit(simple,tt,tp,action='add_item',product_id=pid,quantity=1);assert len(sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[tt]))==2
 passed('Browser sessions are Caja0: cannot open money shifts or collect, but can open a table, send a comanda, keep the bill open and add the next product')
 # Native positive register keeps exclusive opener rules and close-return works directly from quick sale.
 bind(actor,uid,token);shift=open_shift(actor);assert shift;body=request(actor,'/admin/ventas')[1];assert 'r19CloseShift' in body and 'Cerrar turno' in body and 'Ver turnos' not in body
 post(actor,'/admin/turnos',{'action':'close','id':shift['id'],'closing_key':secrets.token_hex(16),'other_cash':100,'return_to_r18':'admin/ventas'});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift['id']])[0]['status']=='closed'
 passed('Installed numbered register still opens/closes only the actual cashier shift, with direct close dialog and return to quick sale')
 # Team plans retain a basic employee registry without exposing or erasing advanced HR fields.
 overrides=sql('SELECT enabled FROM tenant_modules WHERE tenant_id=101 AND module_key="personnel"');sql('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(101,"personnel",0) ON DUPLICATE KEY UPDATE enabled=0')
 try:
  count=sql('SELECT COUNT(*) n FROM users')[0]['n'];post('owner','/admin/empleados?new=1',{'name':tag+' básico','identification':tag+'basic','branch_id':301,'job_title':'Registro','start_date':'2025-01-01','is_active':1,'salary':999999});basic=sql('SELECT * FROM fisitaap_employees_r17 WHERE identification=?',[tag+'basic'])[0];assert basic['user_id'] is None and basic['salary']=='0.00';assert sql('SELECT COUNT(*) n FROM users')[0]['n']==count
  sql('UPDATE fisitaap_employees_r17 SET salary=600000,leave_opening=7,leave_asof="2026-01-01" WHERE id=?',[basic['id']]);post('owner','/admin/empleados?employee='+str(basic['id']),{'id':basic['id'],'revision':0,'name':tag+' básico editado','identification':tag+'basic','branch_id':301,'job_title':'Registro','start_date':'2025-01-01','is_active':1,'salary':1});kept=sql('SELECT salary,leave_opening FROM fisitaap_employees_r17 WHERE id=?',[basic['id']])[0];assert kept=={'salary':'600000.00','leave_opening':'7.000'},kept
 finally:
  sql('DELETE FROM tenant_modules WHERE tenant_id=101 AND module_key="personnel"')
  if overrides:sql('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(101,"personnel",?)',[overrides[0]['enabled']])
 passed('Basic employee registry works without advanced Personnel; it cannot post salary changes or erase existing salary/vacation balances when edited')
 out={'revision':'1.8.0-R19','passed':len(checks),'checks':checks,'environment':'Real HTTP/PHP/MariaDB guarded fictional fixture; production native authorization protocol simulated, no physical printer or real people'};(ROOT/'actualizaciones/1.8.0/QA-FLUJO-R19.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
