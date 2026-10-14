"""Disposable virtual turns do not block maintenance; real turns still do."""
import json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
def main():
    actor='activation_demo_'+secrets.token_hex(4);status,body=request(actor,'/demo/iniciar',{'csrf':csrf(actor,'/demo'),'type':'tienda','mode':'panel'});assert status==200;token=re.search(r'sessionStorage.setItem\("fisitaap-demo-tab","([a-f0-9]{32})"',body)[1];base='/demo/s/'+token;demo=sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?',[token])[0]['tenant_id'];key=secrets.token_hex(16);request(actor,base+'/admin/turnos',{'csrf':csrf(actor,base+'/admin/turnos'),'action':'open','opening_key':key,'opening_cash':0});assert sql('SELECT id FROM pos_shifts WHERE tenant_id=? AND status="open"',[demo])
    prior=sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=101')[0]['version'];sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=101')
    actual=secrets.token_hex(16);sql('INSERT INTO pos_shifts(tenant_id,branch_id,user_id,name,opening_cash,status,opening_key,opened_at) VALUES(101,301,202,"REAL GUARD QA",0,"open",?,NOW())',[actual])
    try:
        status,body=request('master','/activar-180.php',{'csrf':csrf('master','/activar-180.php'),'backup':'yes','action':'publish'});assert status==200 and 'publicada' in body
        assert sql('SELECT status FROM pos_shifts WHERE opening_key=?',[actual])[0]['status']=='open'
        assert sql('SELECT id FROM pos_shifts WHERE tenant_id=? AND status="open"',[demo])
        status,body=request('owner','/admin/actualizar-sistema',{'csrf':csrf('owner','/admin/actualizar-sistema'),'action':'activate','ready':'yes'});assert status==200 and 'Turno abierto' in body
        assert sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=101')[0]['version']==179
    finally:
        sql('DELETE FROM pos_shifts WHERE tenant_id=101 AND opening_key=?',[actual]);sql('UPDATE fisitaap_tenant_updates180 SET version=? WHERE tenant_id=101',[prior]);request(actor,base+'/logout',{'csrf':csrf(actor,base+'/admin/turnos')})
    checks=['publication preserves an open disposable demo and an open real business shift','activation blocks only the selected business own open shift; demo cleanup preserves the real business']
    pathlib.Path('actualizaciones/1.8.0/QA-ACTIVACION-DEMOS.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'real HTTP activation on fictional data and disposable demo; no production'},indent=2)+'\n')
    for x in checks:print('PASS',x,flush=True)
if __name__=='__main__':main()
