"""Tenant rollout through real HTTP, exclusively on the fictitious fixture."""
import datetime,hashlib,json,os,pathlib,secrets,subprocess,sys,urllib.parse,uuid
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql

checks=[]
def passed(name):checks.append(name);print('PASS',name,flush=True)
def post(actor,path,data):return request(actor,path,{'csrf':csrf(actor,path),**data})
def api(token,action,data):
    result=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-H','Content-Type: application/json','-H','Authorization: Bearer '+token,'--data-binary','@-','-w','\n%{http_code}','http://localhost/api/desktop/'+action],input=json.dumps(data).encode(),capture_output=True,check=True)
    body,status=result.stdout.rsplit(b'\n',1);return int(status),json.loads(body)

def main():
    info=json.loads(pathlib.Path('/workspace/fisitaap-env/fixture.json').read_text())
    status,body=post('master','/master/actualizar-179',{'backup':'yes'});assert status==200 and '1.7.9 activada' in body
    owner_b='rollout_b_'+secrets.token_hex(4);cashier='rollout_cashier_'+secrets.token_hex(4)
    for actor,email in [(owner_b,'ownerb@r2.invalid'),(cashier,'cashiera@r2.invalid')]:
        assert request(actor,'/owner-login',{'csrf':csrf(actor,'/owner-login'),'email':email,'password':info['password']})[0]==302
    for actor in ['owner',owner_b]:
        assert post(actor,'/admin/turnos',{'action':'open','name':'ROLLOUT TEST','opening_cash':100})[0]==302
    shifts=sql('SELECT id,tenant_id,status,opening_cash FROM pos_shifts WHERE name="ROLLOUT TEST" ORDER BY tenant_id');assert len(shifts)==2
    for tid in [101,102]:
        sql('INSERT INTO fisitaap_r2_modifiers(tenant_id,kind,name,choices_json,required,multiple,is_active) VALUES(?,"extra","ROLLOUT OPTIONS",?,0,1,1)',[tid,json.dumps([{'name':'Opción A','price':50},{'name':'Opción B','price':75}])])
    legacy_columns='id,tenant_id,kind,name,choices_json,required,multiple,is_active'
    before_b=sql('SELECT '+legacy_columns+' FROM fisitaap_r2_modifiers WHERE tenant_id=102 ORDER BY id')
    status,body=post('master','/master/actualizar-sistema',{'action':'publish','backup':'yes'})
    assert status==200 and 'publicada' in body,body[-200:]
    assert sql('SELECT id,tenant_id,status,opening_cash FROM pos_shifts WHERE name="ROLLOUT TEST" ORDER BY tenant_id')==shifts
    assert all(r['version']==179 for r in sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)'))
    assert sql('SELECT '+legacy_columns+' FROM fisitaap_r2_modifiers WHERE tenant_id=102 ORDER BY id')==before_b
    passed('publication succeeds with two open businesses and preserves shifts/catalogs at 1.7.9')
    status,body=post('owner','/admin/actualizar-sistema',{'action':'activate','ready':'yes','tenant_id':102})
    assert status==200 and 'Turno abierto' in body
    assert all(r['version']==179 for r in sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)'))
    passed('only the current business blockers matter; POST cannot choose another tenant')
    a=next(r for r in shifts if r['tenant_id']==101)
    assert post('owner','/admin/turnos',{'action':'close','id':a['id'],'closing_cash':100})[0]==302
    assert sql('SELECT status FROM pos_shifts WHERE id=?',[a['id']])[0]['status']=='closed'
    tokens={}
    for tid,bid in [(101,301),(102,302)]:
        token=secrets.token_hex(32);tokens[tid]=token
        sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(?,?,"ROLLOUT DEVICE",?)',[tid,bid,hashlib.sha256(token.encode()).hexdigest()])
    status,body=post('owner','/admin/actualizar-sistema',{'action':'activate','ready':'yes','tenant_id':102})
    assert status==200 and 'activada para este negocio' in body,body[-500:]
    versions={r['tenant_id']:r['version'] for r in sql('SELECT tenant_id,version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)')}
    assert versions=={101:180,102:179}
    assert sql('SELECT '+legacy_columns+' FROM fisitaap_r2_modifiers WHERE tenant_id=102 ORDER BY id')==before_b
    assert sql('SELECT status FROM pos_shifts WHERE tenant_id=102 AND name="ROLLOUT TEST"')[0]['status']=='open'
    assert not sql('SELECT id FROM fisitaap_registers180 WHERE tenant_id=102')
    assert len(sql('SELECT id FROM fisitaap_r2_modifiers WHERE tenant_id=101 AND legacy_name180="ROLLOUT OPTIONS"'))==2
    passed('A activates while B keeps its open shift, old modifier choices and device configuration')
    prior=sql('SELECT * FROM fisitaap_tenant_updates180 WHERE tenant_id=101')
    assert post('owner','/admin/actualizar-sistema',{'action':'activate','ready':'yes'})[0]==200
    assert sql('SELECT * FROM fisitaap_tenant_updates180 WHERE tenant_id=101')==prior
    passed('activation retry preserves its audit/version timestamp and does not migrate twice')
    assert request(cashier,'/admin/actualizar-sistema')[0]==403
    assert request('owner','/admin/actualizar-sistema',{'csrf':'wrong','action':'activate','ready':'yes'})[0] in [302,403]
    passed('owner-only authorization and CSRF protect the update action')
    snapshots={}
    for tid,token in tokens.items():
        status,data=api(token,'snapshot',{});assert status==200 and data['ok'],data.get('error')
        snapshots[tid]=data['snapshot']
    assert snapshots[101]['tenant']['id']==101 and snapshots[102]['tenant']['id']==102
    assert snapshots[101]['offline_policy']['version']==180 and snapshots[102]['offline_policy']['version']==179
    passed('verified device tokens receive their own tenant version: modern A and compatible legacy B')
    bdevice=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[hashlib.sha256(tokens[102].encode()).hexdigest()])[0]['id']
    assert post(owner_b,'/admin/escritorio',{'action':'user','branch_id':302,'user_id':203,'password':info['password']})[0]==302
    assert post(owner_b,'/admin/escritorio',{'action':'primary','branch_id':302,'id':bdevice})[0]==302
    status,data=api(tokens[102],'snapshot',{});assert status==200 and data['ok'];legacy=data['snapshot']
    assert legacy['offline_policy']['allowed'] and any(u['id']==203 for u in legacy['users'])
    product=next(p for p in legacy['products'] if not any(g['min'] for g in p['groups']))
    items=[{'id':product['id'],'quantity':product['min'],'options':[],'note':''}]
    old=pathlib.Path('/workspace/fisitaap-env/legacy-client179.js')
    old.write_bytes(subprocess.check_output(['git','show','84dd82410aa5b056513ca96bb20e8c4f3ca6afcb:desktop/server.js']))
    env=os.environ.copy();env['NODE_PATH']=str(pathlib.Path.cwd()/'desktop/node_modules')
    code="const fs=require('node:fs'),{quote}=require('/workspace/fisitaap-env/legacy-client179.js');const x=JSON.parse(fs.readFileSync(0,'utf8'));process.stdout.write(JSON.stringify(quote(x.snapshot,x.items)));"
    quote=json.loads(subprocess.check_output(['node','-e',code],input=json.dumps({'snapshot':legacy,'items':items}).encode(),env=env))
    now=datetime.datetime.now(datetime.timezone.utc).isoformat();sid=str(uuid.uuid4());sale_id=str(uuid.uuid4())
    meta={'id':sid,'user_id':203,'register':'LEGACY B BOX','opening_cash':1000,'opened_at':now,'snapshot_id':legacy['id']}
    sale={'id':sale_id,'snapshot_id':legacy['id'],'user_id':203,'register':'LEGACY B BOX','shift_id':sid,'created_at':now,'items':items,'discount':0,'payments':{'cash':quote['total']},'customer':{}}
    stock_before=sql('SELECT quantity FROM product_inventory WHERE product_id=? AND branch_id=302',[product['id']])[0]['quantity']
    status,result=api(tokens[102],'sync',{'shifts':[meta],'sales':[sale]});assert status==200 and not result['errors'],result.get('errors')
    assert any(a['id']==sale_id for a in result['acknowledged'])
    received=sql('SELECT s.id,s.total FROM sales s JOIN fisitaap_r2_synced_sales l ON l.sale_id=s.id WHERE l.device_id=? AND l.operation_id=?',[bdevice,sale_id])
    assert len(received)==1 and round(float(received[0]['total'])*100)==quote['total']
    status,retry=api(tokens[102],'sync',{'shifts':[meta],'sales':[sale]});assert status==200 and not retry['errors']
    if product['track']:assert abs(float(sql('SELECT quantity FROM product_inventory WHERE product_id=? AND branch_id=302',[product['id']])[0]['quantity'])-(float(stock_before)-product['min']/1000))<.0001
    meta.update(closed_at=datetime.datetime.now(datetime.timezone.utc).isoformat(),closing_cash=1000+quote['total'])
    status,closing=api(tokens[102],'sync',{'shifts':[meta],'sales':[]});assert status==200 and not closing['errors'] and sid in closing['closed_shifts']
    passed('real 1.7.9 pricing produces a legacy B offline sale received once with correct stock/cash and shift closure while A is updated')
    status,body=request(owner_b,'/admin/ventas');assert status==200 and 'Turno abierto' in body
    status,body=request('owner','/admin/actualizar-sistema');assert status==200 and 'ya está actualizado' in body
    passed('legacy B keeps its sales screen and A shows the completed update')
    slug='rollout-new-'+secrets.token_hex(4)
    sql('INSERT INTO tenants(name,slug,is_active) VALUES("NEW ROLLOUT BUSINESS",?,1)',[slug])
    new_tenant=sql('SELECT id FROM tenants WHERE slug=?',[slug])[0]['id']
    status,body=post('master','/master/actualizar-sistema',{'action':'publish','backup':'yes'})
    assert status==200 and 'publicada' in body
    assert sql('SELECT version,modifiers_migrated FROM fisitaap_tenant_updates180 WHERE tenant_id=?',[new_tenant])==[{'version':179,'modifiers_migrated':0}]
    assert sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=102')[0]['version']==179
    passed('repeat publication does not activate pending or newly created businesses')
    b=next(r for r in shifts if r['tenant_id']==102)
    assert post(owner_b,'/admin/turnos',{'action':'close','id':b['id'],'closing_cash':100})[0]==302
    status,body=post(owner_b,'/admin/actualizar-sistema',{'action':'activate','ready':'yes'})
    assert status==200 and 'activada para este negocio' in body
    assert sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=102')[0]['version']==180
    passed('B activates later through its own Administration button')
    pathlib.Path('actualizaciones/1.8.0/QA-ACTUALIZACION-POR-NEGOCIO.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'real PHP/MariaDB/HTTP, fictional tenants 101/102; no production'},indent=2)+'\n')

if __name__=='__main__':main()
