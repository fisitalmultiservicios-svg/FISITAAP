"""Run only against the isolated release_r2/release_r3 fixture, never production."""
import sys,re,pathlib,subprocess
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,ROOT,SOURCE
import shutil
for name in ['core.php','maintenance178.php']:
    shutil.copy2(SOURCE/'app'/name,ROOT/'app'/name)
code,body=request('expired178','/owner-login',{'email':'test@example.invalid','password':'wrong'})
assert code==302,(code,body)
code,body=request('expired178','/owner-login')
assert code==200 and 'El formulario venció' in body and 'name="password"' in body
code,body=request('expired178','/owner-login',{'csrf[]':'invalid'})
assert code==302
code,body=request('owner','/admin/productos',{'action':'create'})
assert code==403 and 'Sesión vencida' in body
code,body=request('owner','/master/actualizar-178')
assert code==403
code,body=request('master','/master/actualizar-178')
assert code==200,(code,body[-500:])
for _ in range(2):
    token=csrf('master','/master/actualizar-178')
    code,body=request('master','/master/actualizar-178',{'csrf':token,'backup':'yes'})
    assert code==200 and 'completada' in body,(code,body[-1000:])
for table,index in [('sales','idx_sales_tenant_date178'),('fisitaap_r2_shift_links','idx_shift_link_shift'),('fisitaap_r2_snapshots','idx_snapshot_device_created')]:
    assert sql('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[table,index])
code,body=request('owner','/admin')
assert code==200 and 'Fatal error' not in body
code,body=request('expired178','/api/desktop/status')
assert code==200 and '1.7.8' in body and '"ok":true' in body
for file in sorted((SOURCE/'app').glob('*.php'))+[SOURCE/'index.php']:
    result=subprocess.run(['docker','exec','-i','fisitaap-r2-web','php','-l'],input=file.read_bytes(),capture_output=True)
    assert result.returncode==0,(file,result.stderr)
print('PASS 1.7.8: expired/malformed login safely redirects; API CSRF retained; maintenance master-only and repeatable; three indexes; dashboard; Android probe; all PHP syntax')
