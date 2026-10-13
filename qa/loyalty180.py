"""Program scopes, partial-sale accumulation, once-only reward and readable history."""
import hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
from financial180 import api
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def post(path,data):return request('owner',path,{'csrf':csrf('owner','/admin/turnos'),**data})
def main():
    config={'action':'save_program','name':'QA puntos','mode':'points','earn_value':1000,'goal_value':1,'reward_type':'percent','reward_value':10,'is_active':1}
    for extra in [{'branch_id':302},{'reward_type':'product','reward_product_id':502},{'reward_value':101}]:
        name='REJECT-'+secrets.token_hex(4);post('/admin/fidelizacion',{**config,**extra,'name':name});assert not sql('SELECT id FROM loyalty_programs WHERE name=?',[name])
    passed('foreign reward products, foreign branches and percentages over 100 cannot become a program')
    post('/admin/fidelizacion',config);program=sql('SELECT id FROM loyalty_programs WHERE tenant_id=101 AND branch_id IS NULL ORDER BY id LIMIT 1')[0]['id'];assert request('owner','/admin/fidelizacion')[0]==200
    token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"LOYALTY QA",?)',[digest]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"LOYALTY BOX",?,202)',[did]);_,body=request('owner','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token);assert post('/api/native/bind',{'proof':proof['proof']})[0]==200
    opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':0});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening])[0]['id']
    def create():
        key=secrets.token_hex(16);post('/admin/ventas',{'action':'quick','open_key':key,'start_product':1,'product_id':501,'quantity':1});return sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id']
    def half(tid):
        rev=sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision'];key=secrets.token_hex(16);post('/admin/cobro?ticket='+str(tid),{'payment_key':key,'revisions['+str(tid)+']':rev,'customer_id':205,'split_mode':'equal','parts':2,'cash':565});row=sql('SELECT id FROM sales WHERE payment_key=?',[key]);assert len(row)==1;return row[0]['id']
    tid=create();one=half(tid);assert sql('SELECT points_delta FROM loyalty_ledger WHERE sale_id=?',[one])[0]['points_delta']=='0.00';two=half(tid);assert sql('SELECT points_delta FROM loyalty_ledger WHERE sale_id=?',[two])[0]['points_delta']=='1.00';assert sql('SELECT status FROM pos_tickets WHERE id=?',[tid])[0]['status']=='paid'
    passed('two partial payments below the point threshold earn the correct cumulative point on the same account')
    reward=sql('SELECT r.id,r.code,r.account_id FROM loyalty_rewards r JOIN loyalty_accounts a ON a.id=r.account_id WHERE a.tenant_id=101 AND a.user_id=205 AND r.program_id=? AND r.status="available" ORDER BY r.id DESC LIMIT 1',[program])[0];post('/admin/fidelizacion',{'action':'redeem','reward_code':reward['code']});post('/admin/fidelizacion',{'action':'redeem','reward_code':reward['code']});assert sql('SELECT status FROM loyalty_rewards WHERE id=?',[reward['id']])[0]['status']=='used';assert len(sql('SELECT id FROM loyalty_ledger WHERE account_id=? AND event="redeem" AND description LIKE ?',[reward['account_id'],'%'+reward['code']+'%']))==1
    passed('the generated reward can be redeemed once and its repeat produces no second ledger event')
    post('/admin/fidelizacion',{**config,'mode':'purchases','goal_value':100});tid=create();one=half(tid);two=half(tid);assert sql('SELECT purchases_delta FROM loyalty_ledger WHERE sale_id=?',[one])[0]['purchases_delta']==1;assert sql('SELECT purchases_delta FROM loyalty_ledger WHERE sale_id=?',[two])[0]['purchases_delta']==0
    passed('splitting one customer account never multiplies the number of purchases for a reward')
    status,history=request('owner','/admin/clientes?customer=205&tab=fidelizacion');assert status==200 and reward['code'] in history and 'Movimientos recientes' in history;status,guide=request('owner','/admin/fidelizacion');assert status==200 and 'Cómo usar Fidelización' in guide
    post('/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':2260});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed';sql('UPDATE loyalty_programs SET is_active=0 WHERE id=?',[program])
    passed('the owner can follow points, canje and reward history from the client card and the built-in manual')
    pathlib.Path('actualizaciones/1.8.0/QA-FIDELIZACION.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real PHP/MariaDB HTTP; virtual fixture customers'},indent=2)+'\n')
if __name__=='__main__':main()
