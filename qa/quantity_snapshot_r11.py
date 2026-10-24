"""Verify installed Windows1.8.1 uses updated integer/bulk snapshot rules without rebuilding."""
import hashlib,json,pathlib,secrets,subprocess,sys
from financial180 import api
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"R11 SNAPSHOT QA",?)',[digest]);device=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id']
    try:
        status,out=api('snapshot',{},token);assert status==200 and out['ok'],out;snapshot=out['snapshot'];unitid=sql('SELECT id FROM products WHERE sku LIKE "R11-unit-%" ORDER BY id DESC LIMIT 1')[0]['id'];bulkid=sql('SELECT id FROM products WHERE sku LIKE "R11-bulk-%" ORDER BY id DESC LIMIT 1')[0]['id'];unit=next(p for p in snapshot['products'] if p['id']==unitid);bulk=next(p for p in snapshot['products'] if p['id']==bulkid);assert unit['min']==1000 and unit['step']==1000 and bulk['min']==1 and bulk['step']==1
        code="const fs=require('node:fs'),{quote}=require('./desktop/server.js');const x=JSON.parse(fs.readFileSync(0,'utf8'));let rejected=false;try{quote(x.snapshot,[{id:x.unit,quantity:1500,options:[],note:''}]);}catch(_){rejected=true;}if(!rejected)throw Error('fractional unit accepted');const a=quote(x.snapshot,[{id:x.unit,quantity:2000,options:[],note:''}]);const b=quote(x.snapshot,[{id:x.bulk,quantity:250,options:[],note:''}]);process.stdout.write(JSON.stringify({unit:a.lines[0].quantity,bulk:b.lines[0].quantity,rejected}));"
        r=subprocess.run(['node','-e',code],input=json.dumps({'snapshot':snapshot,'unit':unitid,'bulk':bulkid}).encode(),capture_output=True,check=True,cwd=ROOT);actual=json.loads(r.stdout);assert actual=={'unit':2000,'bulk':250,'rejected':True}
        result={'passed':1,'checks':['real HTTP desktop snapshot publishes unit min/step1000 and bulk min/step1; unchanged Windows1.8.1 quote rejects1.5units, accepts2units and0.25kg'],'native_quote':actual,'environment':'fictional PHP/MariaDB snapshot plus actual Windows1.8.1 server.js quote under Node; no physical Windows/disconnection/printers; synchronize installed clients to adopt new snapshot'};(ROOT/'actualizaciones/1.8.0/QA-SNAPSHOT-R11.json').write_text(json.dumps(result,indent=2)+'\n');print('PASS current desktop snapshot + unchanged Windows1.8.1 quote enforce whole units and allow0.25kg',flush=True)
    finally:sql('DELETE FROM fisitaap_r2_snapshots WHERE device_id=?',[device]);sql('DELETE FROM fisitaap_r2_devices WHERE id=?',[device])
if __name__=='__main__':main()
