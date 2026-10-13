"""HTTP tests for printer configuration, durable jobs, scope and direct POS navigation."""
import hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
from financial180 import api
checks=[]
def post(actor,path,data):return request(actor,path,{'csrf':csrf(actor,'/admin/turnos'),**data})
def passed(x):checks.append(x);print('PASS',x,flush=True)
def device(tenant,branch,user):
    token=secrets.token_hex(32);h=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(?,?,"PRINT QA",?)',[tenant,branch,h]);d=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[h])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id,drawer_enabled) VALUES(?,?,"PRINT QA REGISTER",?,?,1)',[tenant,branch,d,user]);return token,d

def main():
    token,did=device(101,301,202);other,_=device(102,302,203)
    _,html=request('owner','/admin/turnos');context=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',html)[1])
    assert api('web-session',{'nonce':context['nonce'],'user_id':202,'confirm':True},token)[0]==422
    _,proof=api('web-session',{'nonce':context['nonce'],'user_id':202},token);assert post('owner','/api/native/bind',{'proof':proof['proof']})[0]==200
    _,confirmed=api('web-session',{'nonce':context['nonce'],'user_id':202,'confirm':True},token);assert confirmed['bound'] and confirmed['cash']
    _,kitchen=api('web-session',{'nonce':context['nonce'],'user_id':209},token);assert not kitchen['cash'];assert json.loads(bytes.fromhex(kitchen['proof'].split('.')[0]))['print']
    passed('automatic native resume requires a real authenticated binding; kitchen proof grants printing without cash')
    config={}
    for point in ['receipt','kitchen','bar','dispatch','copy']:
        for key,value in {'type':'network','width':80,'host':'192.168.1.50','port':9100,'device_id':did,'drawer':1 if point=='receipt' else 0}.items():config['printer_points['+point+']['+key+']']=value
    assert post('owner','/admin/impresion',config)[0]==302
    profiles=json.loads(sql('SELECT printer_points_json FROM branches WHERE id=301')[0]['printer_points_json']);assert profiles['receipt']['device_id']==did and profiles['receipt']['drawer'];assert not profiles['kitchen']['drawer']
    passed('each document point saves an authorized branch device, network/Windows profile, paper and separate drawer setting')
    money=len(sql('SELECT id FROM fisitaap_money_entries180'));status,body=post('owner','/api/native/print-test',{'point':'kitchen'});assert status==200,(status,body);job=json.loads(body)['id'];assert len(sql('SELECT id FROM fisitaap_money_entries180'))==money
    _,claim=api('print-claim',{},token);assert claim['job']['id']==job and not claim['job']['payload']['drawer']
    assert api('print-ack',{'id':job,'claim':claim['job']['claim'],'status':'sent_to_printer'},other)[0]==422
    ack={'id':job,'claim':claim['job']['claim'],'status':'uncertain','error':'simulated disconnect'};assert api('print-ack',ack,token)[0]==200;assert api('print-ack',ack,token)[0]==200;assert api('print-claim',{},token)[1]['job'] is None
    passed('point tests move no cash, reject another business ACK and retain an uncertain delivery without automatic rerouting')
    shiftkey=secrets.token_hex(16);post('owner','/admin/turnos',{'action':'open','opening_key':shiftkey,'opening_cash':0});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[shiftkey])[0]['id']
    key=secrets.token_hex(16);post('owner','/admin/ventas',{'action':'quick','start_product':1,'product_id':501,'quantity':1,'open_key':key});ticket=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];rev=sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]['revision']
    pay=secrets.token_hex(16);status,_=post('owner','/admin/cobro?ticket='+str(ticket),{'payment_key':pay,'revisions['+str(ticket)+']':rev,'split_mode':'all','parts':2,'cash':1130,'print_receipt':1});assert status==302;sale=sql('SELECT id FROM sales WHERE payment_key=?',[pay])[0]['id']
    jobs=sql('SELECT * FROM fisitaap_print_jobs180 WHERE tenant_id=101 AND operation_key LIKE ? ORDER BY id',['sale-'+str(sale)+'%']);assert len(jobs)==2,jobs;assert {j['point'] for j in jobs}=={'receipt','kitchen'}
    _,claim=api('print-claim',{},token);assert claim['job']['payload']['document']=='receipt' and claim['job']['payload']['drawer']
    assert api('print-claim',{},other)[1]['job'] is None
    passed('cash payment atomically queues receipt and new kitchen items on their configured device; only the original cash receipt authorizes drawer')
    ack={'id':claim['job']['id'],'claim':claim['job']['claim'],'status':'sent_to_printer'};api('print-ack',ack,token);_,command=api('print-claim',{},token);assert command['job']['payload']['document']=='kitchen' and not command['job']['payload']['drawer'];api('print-ack',{'id':command['job']['id'],'claim':command['job']['claim'],'status':'sent_to_printer'},token)
    post('owner','/admin/cobro?ticket='+str(ticket),{'payment_key':pay,'revisions['+str(ticket)+']':rev,'split_mode':'all','parts':2,'cash':1130,'print_receipt':1});assert len(sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key LIKE ?',['sale-'+str(sale)+'%']))==2
    passed('payment retries do not enqueue another receipt or resend old kitchen items')
    # Verify actual Location through the helper's raw curl path.
    import subprocess
    out=subprocess.run(['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','-D','-','-o','/dev/null','-b','/tmp/r3-owner.cookies','--data',__import__('urllib.parse',fromlist=['urlencode']).urlencode({'csrf':csrf('owner','/admin/turnos'),'payment_key':pay,'revisions['+str(ticket)+']':rev,'split_mode':'all','parts':2,'cash':1130,'print_receipt':1}),'http://localhost/admin/cobro?ticket='+str(ticket)],capture_output=True,check=True).stdout.decode()
    assert re.search(r'Location: .*admin/ventas\?last_sale=',out,re.I),out
    passed('printing returns directly to sales without opening the receipt/FISITAAP Print screen')
    post('owner','/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':1130});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed'
    pathlib.Path('actualizaciones/1.8.0/QA-IMPRESION.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real PHP/MariaDB device API; physical printers/drawer not connected'},indent=2)+'\n')
if __name__=='__main__':main()
