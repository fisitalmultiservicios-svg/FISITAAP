"""Native authorization must not silently overwrite an explicit admin branch choice."""
import hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
from financial180 import api
def main():
    token=secrets.token_hex(32);h=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"BRANCH CHOICE QA",?)',[h]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[h])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"BRANCH CHOICE BOX",?,202)',[did]);status,html=request('owner','/admin/configuracion?branch_id=303');assert status==200 and 'Pedidos web · Segunda' in html;ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',html)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token)
    assert request('owner','/api/native/bind',{'csrf':csrf('owner','/admin/configuracion'),'proof':proof['proof']})[0]==200
    status,html=request('owner','/admin/configuracion');assert status==200 and 'Pedidos web · Segunda' in html
    # Keeping the administrative choice does not grant cash access to that branch.
    key=secrets.token_hex(16);request('owner','/admin/turnos',{'csrf':csrf('owner','/admin/turnos'),'action':'open','opening_key':key,'opening_cash':0});assert not sql('SELECT id FROM pos_shifts WHERE opening_key=?',[key]);request('owner','/admin/turnos?branch_id=301')
    checks=['native binding preserves an explicitly selected administration branch while keeping cash restricted to the assigned physical register branch']
    pathlib.Path('actualizaciones/1.8.0/QA-SUCURSAL-NATIVA.json').write_text(json.dumps({'passed':1,'checks':checks,'environment':'real fictional PHP/MariaDB HTTP native proof; no production'},indent=2)+'\n');print('PASS',checks[0],flush=True)
if __name__=='__main__':main()
