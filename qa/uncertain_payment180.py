"""A lost web response must never permit a later duplicate cash settlement."""
import hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
from financial180 import api
checks=[]
def post(path,data):return request('owner',path,{'csrf':csrf('owner','/admin/turnos'),**data})
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
 token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"UNCERTAIN PAYMENT QA",?)',[digest]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"UNCERTAIN PAYMENT BOX",?,202)',[did])
 _,body=request('owner','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token);assert post('/api/native/bind',{'proof':proof['proof']})[0]==200
 opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':0});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening])[0]['id'];key=secrets.token_hex(16);post('/admin/ventas',{'action':'quick','start_product':1,'product_id':501,'quantity':1,'open_key':key});ticket=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];revision=sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]['revision']
 pending=secrets.token_hex(16);status,result=api('payment-status',{'payments':[{'key':pending,'user_id':202}]},token);assert status==200 and result['payments']==[{'key':pending,'status':'cancelled'}]
 data={'payment_key':pending,'revisions['+str(ticket)+']':revision,'split_mode':'all','parts':2,'cash':1130};post('/admin/cobro?ticket='+str(ticket),data);assert not sql('SELECT id FROM sales WHERE payment_key=?',[pending]);assert sql('SELECT status FROM pos_tickets WHERE id=?',[ticket])[0]['status']=='open'
 passed('a definitive cancellation prevents a delayed original POST from charging and preserves its unpaid account')
 actual=secrets.token_hex(16);data['payment_key']=actual;post('/admin/cobro?ticket='+str(ticket),data);sale=sql('SELECT id FROM sales WHERE payment_key=?',[actual])[0]['id'];status,result=api('payment-status',{'payments':[{'key':actual,'user_id':202}]},token);assert status==200 and result['payments']==[{'key':actual,'status':'received','sale_id':sale}];post('/admin/cobro?ticket='+str(ticket),data);assert len(sql('SELECT id FROM sales WHERE payment_key=?',[actual]))==1
 passed('checking a received payment after losing its response returns the original sale and cannot create another charge')
 _,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202,'web_payment_keys':[actual]},token);assert proof['cash_state']['web_payments']==[actual] and proof['cash_state']['shifts'][0]['server_cash_net']==113000
 passed('fresh native authorization immediately confirms the received key together with its authoritative cash balance')
 assert api('payment-status',{'payments':[{'key':actual,'user_id':203}]},token)[0]==422
 passed('another business user cannot inspect or cancel this register payment')
 post('/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':1130});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed'
 pathlib.Path('actualizaciones/1.8.0/QA-COBRO-SIN-RESPUESTA.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real PHP/MariaDB API; no production'},indent=2)+'\n')
if __name__=='__main__':main()
