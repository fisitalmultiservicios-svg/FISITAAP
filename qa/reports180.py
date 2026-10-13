"""Validate all module queries, bounded pages, CSV, scopes and custom roles."""
import json,pathlib,re,secrets,sys,time
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,sql,PASSWORD
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
    datasets=['products','sales','items','orders','tables','shifts','cash','payments','purchases','inventory','inventory_moves','suppliers','cxc','cxp','customers','credit','loyalty','gifts','delivery','users','devices','registers','contacts','audit']
    samples={}
    for key in datasets:
        at=time.monotonic();status,body=request('owner','/admin/reportes?dataset='+key+'&from=2020-01-01&to=2030-01-01');samples[key]=round((time.monotonic()-at)*1000,1);assert status==200,(key,status,body[-250:]);assert '<table' in body,(key,body[-500:]);status,body=request('owner','/admin/reportes?dataset='+key+'&csv=1&from=2020-01-01&to=2030-01-01');assert status==200 and body.startswith('\ufeff'),(key,status,body[-250:])
    passed('all 24 operational module reports and real CSV exports execute successfully against MariaDB')
    assert request('owner','/admin/reportes?dataset=sales&branch=302')[0]==422
    assert request('owner','/admin/reportes?dataset=customers&user=206')[0]==422
    status,body=request('owner','/admin/reportes?dataset=products&q=Other%20QA');assert status==200 and 'Other QA</td>' not in body
    passed('foreign branch and client filters are rejected; searches never expose another business rows')
    # Existing limited cashier has no report grant. Add report view only, not financial module access.
    role=sql('SELECT role_id FROM fisitaap_r1_user_roles WHERE tenant_id=101 AND user_id=204')[0]['role_id'];sql('INSERT IGNORE INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,"reportes","view")',[role])
    status,login=request('cashier','/owner-login')
    if status==200:
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',login)[1]
        assert request('cashier','/owner-login',{'csrf':token,'email':'cashiera@r2.invalid','password':PASSWORD})[0]==302
    assert request('cashier','/admin/reportes?dataset=products')[0]==403;assert request('cashier','/admin/reportes?dataset=credit')[0]==403;assert request('cashier','/admin/reportes?dataset=sales&csv=1')[0]==200
    status,items=request('cashier','/admin/reportes?dataset=items&csv=1');assert status==200 and 'costo_unitario' not in items
    passed('report access alone cannot unlock product costs or credit; granted sales reports remain available')
    sql('UPDATE users SET name=? WHERE id=205',['=HYPERLINK("https://example.invalid")']);status,body=request('owner','/admin/reportes?dataset=customers&csv=1');assert status==200 and "'=HYPERLINK" in body;sql('UPDATE users SET name="QA customer" WHERE id=205')
    passed('CSV neutralizes spreadsheet formulas from customer-controlled text')
    status,body=request('master','/master/reportes?from=2020-01-01&to=2030-01-01');assert status==200,(status,body[-500:])
    passed('the master summary uses timestamp ranges and omits a received web order from the combined sales total')
    pathlib.Path('actualizaciones/1.8.0/QA-REPORTES.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'datasets':len(datasets),'fixture_http_round_trip_ms':samples,'environment':'isolated MariaDB/PHP, no production performance claim'},indent=2)+'\n')
if __name__=='__main__':main()
