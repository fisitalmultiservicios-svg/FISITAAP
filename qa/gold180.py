"""Sequential regression on a reset, explicitly fictional database only."""
import json,pathlib,subprocess,sys,time
root=pathlib.Path(__file__).resolve().parents[1]
resume='--resume' in sys.argv
subprocess.run([sys.executable,'qa/setup180.py']+([] if resume else ['--reset']),cwd=root,check=True)
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf
status,body=request('master','/master/actualizar-179',{'csrf':csrf('master','/master/actualizar-179'),'backup':'yes'})
assert status==200 and '1.7.9 activada' in body
scripts=['financial180','accounts_purchases180','catalog180','floor180','offline180','pos180','printing180','uncertain_payment180','permissions180','two_boxes180','coupons180','orders180','settings180','purchases_xml180','loyalty180','reports180','demos180','activation_demo180','browser180','android_offline180','workflows180','performance180','selected_branch180','panel180']
results=[]
if resume:
    previous=json.loads(pathlib.Path('/workspace/fisitaap-env/gold180.json').read_text());failed=next((r['suite'] for r in previous if r['exit_code']),None)
    if not failed:raise RuntimeError('No failed suite to resume; use the published evidence instead of repeating completed tests.')
    results=[r for r in previous if r['exit_code']==0];scripts=scripts[scripts.index(failed):]
for script in scripts:
    print('RUN',script,flush=True);begin=time.monotonic();result=subprocess.run([sys.executable,'qa/'+script+'.py'],cwd=root)
    results.append({'suite':script,'exit_code':result.returncode,'elapsed_seconds':round(time.monotonic()-begin,2)})
    pathlib.Path('/workspace/fisitaap-env/gold180.json').write_text(json.dumps(results,indent=2))
    if result.returncode:sys.exit(result.returncode)
print('PASS all',len(results),'sequential suites in the fictional fixture; resume retains previously approved suites',flush=True)
