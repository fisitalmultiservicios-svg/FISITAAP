"""Primary selection/release regression, only against the disposable R2/R3 fixture."""
import sys,pathlib,json,secrets,hashlib,subprocess,uuid,concurrent.futures
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,ROOT,SOURCE,PASSWORD

def api(action,data=None,token=''):
    cmd=['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','--max-time','65','-X','POST','-H','Content-Type: application/json','-H','Authorization: Bearer '+token,'--data-binary','@-','-w','\n%{http_code}','http://localhost/api/desktop/'+action]
    result=subprocess.run(cmd,input=json.dumps(data or {}).encode(),capture_output=True,check=True)
    body,status=result.stdout.rsplit(b'\n',1)
    return int(status),json.loads(body)

def device(name,branch=301):
    code=secrets.token_hex(16)
    sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,pair_hash,pair_expires) VALUES(101,?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[branch,name,hashlib.sha256(code.encode()).hexdigest()])
    return {'id':int(sql('SELECT MAX(id) id FROM fisitaap_r2_devices')[0]['id']),'code':code,'branch':branch}

def choose(actor,d):
    return request(actor,'/admin/escritorio',{'csrf':csrf(actor,'/admin/escritorio'),'action':'primary','id':d['id'],'branch_id':d['branch']})

def prepare():
    status,_=request('owner','/master/actualizar-179');assert status==403
    token=csrf('master','/master/actualizar-179')
    status,body=request('master','/master/actualizar-179',{'csrf':token,'backup':'yes'});assert status==200 and '1.7.9 activada' in body
    assert request('master','/master/actualizar-179',{'csrf':token,'backup':'yes'})[0]==200
    sql('DELETE FROM fisitaap_primary179 WHERE tenant_id=101') # Explicit isolated fixture only.
    first,second=device('Windows QA 179'),device('Android QA 179',303)
    for d in [first,second]:
        status,out=api('pair',{'device_id':d['id'],'code':d['code']});assert status==200
        assert out['snapshot']['offline_policy']['allowed'] is False
        d['token']=out['token']
        if d['branch']==301:
            sid=str(uuid.uuid4());snap=out['snapshot'];sale=str(uuid.uuid4())
            status,blocked=api('sync',{'shifts':[{'id':sid,'snapshot_id':snap['id'],'user_id':204,'register':'forged secondary','opening_cash':0,'opened_at':snap['generated_at']}],'sales':[{'id':sale,'snapshot_id':snap['id'],'user_id':204}]},d['token'])
            assert status==200 and not blocked['acknowledged'] and len(blocked['errors'])==2
            assert all('principal' in error['error'] for error in blocked['errors'])
            assert not sql('SELECT shift_id FROM fisitaap_r2_shift_links WHERE device_id=? AND shift_uuid=?',[d['id'],sid])
    choose('owner',first)
    assert api('snapshot',token=first['token'])[1]['snapshot']['offline_policy']['allowed'] is True
    assert api('snapshot',token=second['token'])[1]['snapshot']['offline_policy']['allowed'] is False
    generation=sql('SELECT generation FROM fisitaap_primary179 WHERE tenant_id=101')[0]['generation']
    choose('owner',first);assert sql('SELECT generation FROM fisitaap_primary179 WHERE tenant_id=101')[0]['generation']==generation
    choose('owner',second);assert int(sql('SELECT device_id FROM fisitaap_primary179 WHERE tenant_id=101')[0]['device_id'])==first['id']
    request('owner','/admin/escritorio',{'csrf':csrf('owner','/admin/escritorio'),'action':'revoke','id':first['id'],'branch_id':301})
    assert int(sql('SELECT is_active FROM fisitaap_r2_devices WHERE id=?',[first['id']])[0]['is_active'])==1
    shift=str(uuid.uuid4())
    sql('INSERT INTO pos_shifts(tenant_id,branch_id,user_id,name,opening_cash,status,opened_at) VALUES(101,301,204,"Primary179 fixture",0,"open",NOW())')
    sid=int(sql('SELECT MAX(id) id FROM pos_shifts')[0]['id'])
    sql('INSERT INTO fisitaap_r2_shift_links(device_id,shift_uuid,shift_id) VALUES(?,?,?)',[first['id'],shift,sid])
    assert api('release',token=first['token'])[0]==422
    sql('UPDATE pos_shifts SET status="closed",closed_at=NOW() WHERE id=?',[sid])
    assert api('release',token=first['token'])[0]==200
    assert api('release',token=first['token'])[0]==200
    assert api('snapshot',token=first['token'])[1]['snapshot']['offline_policy']['allowed'] is False
    if request('owner179b','/owner-login')[0]==200:
        token=csrf('owner179b','/owner-login');assert request('owner179b','/owner-login',{'csrf':token,'email':'owner@r2.invalid','password':PASSWORD})[0]==302
    tokens=[csrf('owner','/admin/escritorio'),csrf('owner179b','/admin/escritorio')]
    def select(i):return request(['owner','owner179b'][i],'/admin/escritorio',{'csrf':tokens[i],'action':'primary','id':[first,second][i]['id'],'branch_id':[first,second][i]['branch']})[0]
    with concurrent.futures.ThreadPoolExecutor(2) as workers:assert list(workers.map(select,[0,1]))==[302,302]
    policies=[api('snapshot',token=d['token'])[1]['snapshot']['offline_policy']['allowed'] for d in [first,second]]
    assert sorted(policies)==[False,True]
    winner=[first,second][policies.index(True)];assert api('release',token=winner['token'])[0]==200
    print('PASS web179: owner-only selection, one primary across concurrent requests, inactive-by-default, no forced replacement/revocation, closed-shift release, repeatable migration/release')
    return device('Android browser179')

if __name__=='__main__':prepare()
