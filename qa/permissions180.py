"""Fresh custom-role boundaries on real cashier HTTP and private device proof."""
import hashlib,json,pathlib,re,secrets,subprocess,sys,urllib.parse
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from financial180 import api
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def post(path,data):return request('cashier',path,{'csrf':csrf('cashier','/admin/turnos'),**data})
def main():
 sql("UPDATE users SET force_password_change=0 WHERE id=204")
 sql('INSERT INTO fisitaap_r1_roles(tenant_id,name) VALUES(101,?)',['QA LIMITED '+secrets.token_hex(4)]);role=sql('SELECT MAX(id) id FROM fisitaap_r1_roles WHERE tenant_id=101')[0]['id']
 for section,actions in {'ventas':['view','write','charge'],'cobro':['view'],'turnos':['view','write'],'clientes':['view']}.items():
  for action in actions:sql('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,?,?)',[role,section,action])
 sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,204,?,"cashier") ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)',[role])
 html=subprocess.run(['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','-c','/tmp/r3-cashier.cookies','http://localhost/owner-login'],capture_output=True,check=True).stdout.decode();token_csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',html)[1]
 subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-cashier.cookies','-c','/tmp/r3-cashier.cookies','--data-binary','@-','http://localhost/owner-login'],input=urllib.parse.urlencode({'csrf':token_csrf,'email':'cashiera@r2.invalid','password':PASSWORD}).encode(),capture_output=True,check=True)
 token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"LIMITED CASHIER QA",?)',[digest]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"LIMITED CASHIER BOX",?,204)',[did])
 _,body=request('cashier','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);status,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':204},token);assert status==200 and proof['cash'],proof;assert post('/api/native/bind',{'proof':proof['proof']})[0]==200
 passed('a custom cashier with sales view/charge can bind its device without acquiring administration or kitchen permissions')
 _,snapshot=api('snapshot',{},token);cashier=next(u for u in snapshot['snapshot']['users'] if u['id']==204);assert cashier['permissions']=={'shift':True,'discount':False,'create_customer':False,'release':False},cashier['permissions']
 passed('offline action flags mirror the exact custom-role permissions')
 opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':0});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening])[0]['id'];key=secrets.token_hex(16);post('/admin/ventas',{'action':'quick','start_product':1,'product_id':501,'quantity':1,'open_key':key});ticket=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];item=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket])[0]['id'];rev=lambda:sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]['revision']
 for data in [{'action':'edit_item','item_id':item,'quantity':1,'base_price180':1},{'action':'discount','discount':999}]:
  post('/admin/ventas?screen=order&ticket='+str(ticket),{'revision':rev(),**data});assert sql('SELECT unit_price FROM pos_ticket_items WHERE id=?',[item])[0]['unit_price']=='1000.00';assert sql('SELECT discount_total FROM pos_tickets WHERE id=?',[ticket])[0]['discount_total']=='0.00'
 passed('denied manual price and discount edits leave the ticket and its stock reservation unchanged')
 for extra in [{'customer_id':205,'credit':1130,'cash':0},{'create_customer':1,'new_name':'MUST NOT CREATE','new_phone':'81112223','cash':1130}]:
  payment=secrets.token_hex(16);post('/admin/cobro?ticket='+str(ticket),{'payment_key':payment,'revisions['+str(ticket)+']':rev(),'split_mode':'all','parts':2,**extra});assert not sql('SELECT id FROM sales WHERE payment_key=?',[payment]);assert not sql('SELECT id FROM users WHERE name="MUST NOT CREATE"')
 passed('denied credit and inline client creation roll back the entire payment')
 code='REG-'+secrets.token_hex(8).upper();sql('INSERT INTO fisitaap_r3_gift_cards(tenant_id,customer_id,code,issue_key,initial_amount,balance,expires_at,created_by) VALUES(101,205,?,?,1000,1000,DATE_ADD(NOW(),INTERVAL 1 YEAR),202)',[code,secrets.token_hex(16)])
 status,body=request('cashier','/admin/cobro?ticket='+str(ticket));assert status==200,(status,body[-500:]);assert code not in body;data=json.loads(re.search(r'data-r3-payment-data>(.*?)</script>',body,re.S)[1]);assert data['gifts']==[] and data['credit']==[],data
 passed('cashier HTML contains neither other gift-card secrets nor unauthorized credit balances')
 status,body=post('/api/gift-preview',{'customer_id':205,'code':code});assert status==200 and json.loads(body)['card']['balance']=='1000.00',(status,body);assert post('/api/gift-preview',{'customer_id':206,'code':code})[0]==403
 passed('a supplied gift code returns only its own customer balance and rejects another business customer')
 payment=secrets.token_hex(16);post('/admin/cobro?ticket='+str(ticket),{'payment_key':payment,'revisions['+str(ticket)+']':rev(),'split_mode':'all','parts':2,'customer_id':205,'cash':1030,'gift_code':code,'gift_amount':100});assert sql('SELECT total FROM sales WHERE payment_key=?',[payment])[0]['total']=='1130.00';assert sql('SELECT balance FROM fisitaap_r3_gift_cards WHERE code=?',[code])[0]['balance']=='900.00'
 passed('the authorized cashier can settle a supplied gift plus cash without unrelated credit permissions')
 last_csrf=csrf('cashier','/admin/turnos');sql('UPDATE users SET force_password_change=1 WHERE id=204');assert api('web-session',{'nonce':ctx['nonce'],'user_id':204},token)[0]==422;blocked_key=secrets.token_hex(16);assert request('cashier','/admin/caja-movimiento',{'csrf':last_csrf,'direction':'in','amount':1,'reason':'SHOULD NOT POST','confirmed':'yes','operation_key':blocked_key})[0] in [302,422];assert not sql('SELECT id FROM fisitaap_money_entries180 WHERE operation_key=?',[blocked_key]);sql('UPDATE users SET force_password_change=0 WHERE id=204')
 post('/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':1030});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed'
 passed('a forced password change immediately invalidates monetary authority even while the old browser session is open')
 pathlib.Path('actualizaciones/1.8.0/QA-PERMISOS.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real PHP/MariaDB HTTP, custom cashier, trusted device transport'},indent=2)+'\n')
if __name__=='__main__':main()
