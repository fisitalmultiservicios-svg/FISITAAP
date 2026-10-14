"""Real HTTP financial regression cases on the isolated fixture only."""
import hashlib,json,pathlib,re,secrets,subprocess,sys,urllib.parse
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
OUT=pathlib.Path('/workspace/FISITAAP/actualizaciones/1.8.0')
checks=[]
def passed(name):checks.append(name);print('PASS',name,flush=True)
def post(actor,path,data):return request(actor,path,{'csrf':csrf(actor,'/admin/turnos' if path=='/admin/caja-movimiento' else path),**data})
def api(action,data,token):
    args=['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-H','Content-Type: application/json','-H','Accept: application/json','-H','Authorization: Bearer '+token,'--data-binary','@-','-w','\n%{http_code}','http://localhost/api/desktop/'+action]
    p=subprocess.run(args,input=json.dumps(data).encode(),capture_output=True,check=True)
    body,status=p.stdout.rsplit(b'\n',1);return int(status),json.loads(body)
def main():
    assert request('owner','/activar-180.php',{'csrf':csrf('owner','/admin/turnos'),'backup':'yes'})[0]==403
    status,body=post('master','/activar-180.php',{'backup':'yes','action':'publish'})
    assert status==200 and 'publicada' in body
    for tenant in sql('SELECT id FROM tenants WHERE is_active=1 ORDER BY id'):
        status,body=post('master','/master/actualizar-sistema',{'action':'activate','tenant_id':tenant['id'],'ready':'yes'})
        assert status==200 and 'activada para este negocio' in body
    passed('publication is master-only and additive; each fictitious business is explicitly activated')
    key=secrets.token_hex(16)
    assert post('owner','/admin/turnos',{'action':'open','opening_key':key,'opening_cash':100})[0]==302
    assert not sql('SELECT id FROM pos_shifts WHERE opening_key=?',[key])
    passed('a normal web browser cannot open a cash shift even with valid CSRF')

    token=secrets.token_hex(32)
    sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"AUTHORIZED TEST DEVICE",?)',[hashlib.sha256(token.encode()).hexdigest()])
    device=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[hashlib.sha256(token.encode()).hexdigest()])[0]['id']
    sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"TEST CASH REGISTER",?,202)',[device])
    status,body=request('owner','/admin/turnos');assert status==200
    ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1])
    status,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token)
    assert status==200 and proof['ok']
    assert request('owner','/api/native/bind',{'csrf':csrf('owner','/admin/turnos'),'proof':proof['proof']})[0]==200
    assert api('web-session',{'nonce':ctx['nonce'],'user_id':203},token)[0]==422
    passed('native authorization is bound to a real device, assigned register, user, tenant and session nonce')
    opening={'action':'open','opening_key':key,'opening_cash':100}
    assert post('owner','/admin/turnos',opening)[0]==302
    assert post('owner','/admin/turnos',opening)[0]==302
    shifts=sql('SELECT * FROM pos_shifts WHERE opening_key=?',[key]);assert len(shifts)==1
    shift=shifts[0]['id']
    changed=dict(opening,opening_cash=999)
    assert post('owner','/admin/turnos',changed)[0]==302
    assert sql('SELECT opening_cash FROM pos_shifts WHERE id=?',[shift])[0]['opening_cash']=='100.00'
    passed('shift opening is idempotent and rejects reuse for changed amounts')

    for direction,amount in [('in',50),('out',10)]:
        op=secrets.token_hex(16);move={'direction':direction,'amount':amount,'reason':'TRANSACTION TEST','reference':'TEST ONLY','confirmed':'yes','operation_key':op}
        assert post('owner','/admin/caja-movimiento',move)[0]==302
        assert post('owner','/admin/caja-movimiento',move)[0]==302
        assert len(sql('SELECT id FROM fisitaap_money_entries180 WHERE operation_key=?',[op]))==1
    assert sql('SELECT SUM(amount) amount FROM fisitaap_money_entries180 WHERE shift_id=?',[shift])[0]['amount']=='40.00'
    passed('cash in/out affects only the assigned shift and each retry is received once')

    openkey=secrets.token_hex(16)
    assert post('owner','/admin/ventas',{'action':'quick','open_key':openkey})[0]==302
    ticket=sql('SELECT id FROM pos_tickets WHERE tenant_id=101 AND open_key=?',[openkey])[0]['id']
    order='/admin/ventas?screen=order&ticket='+str(ticket)
    assert post('owner',order,{'action':'add_item','revision':0,'product_id':501,'quantity':1})[0]==302
    closing={'action':'close','id':shift,'closing_key':secrets.token_hex(16),'denominations[100]':1,'denominations[10]':4,'other_cash':0}
    assert post('owner','/admin/turnos',closing)[0]==302
    assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='open'
    revision=sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]['revision']
    assert post('owner',order,{'action':'void','revision':revision})[0]==302
    passed('closing is blocked by an open account until it is safely settled or voided')
    assert post('owner','/admin/turnos',closing)[0]==302
    closed=sql('SELECT * FROM pos_shifts WHERE id=?',[shift])[0]
    assert closed['status']=='closed' and closed['expected_cash']=='140.00' and closed['closing_cash']=='140.00' and closed['difference_amount']=='0.00'
    assert post('owner','/admin/turnos',closing)[0]==302
    assert sql('SELECT closing_cash FROM pos_shifts WHERE id=?',[shift])[0]['closing_cash']=='140.00'
    passed('denomination count closes once and reconciles sales plus cash movements')
    assert not sql('SELECT id FROM pos_shifts WHERE tenant_id=102')
    assert not sql('SELECT id FROM fisitaap_money_entries180 WHERE tenant_id=102')
    passed('financial operations remain isolated from another business')
    (OUT/'QA-DINERO.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated PHP 8.3 / MariaDB, real HTTP/CSRF and simulated trusted native transport; no live business or printer'},indent=2)+'\n')
if __name__=='__main__':main()
