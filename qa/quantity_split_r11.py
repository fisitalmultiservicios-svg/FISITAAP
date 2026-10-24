"""Real payment quote rejects fractional unit transfers without breaking monetary splits."""
import json,pathlib,secrets,subprocess,sys
from usability_r7 import login
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';login('owner','owner@r2.invalid');key=secrets.token_hex(16)
    request('owner','/admin/ventas',{'csrf':csrf('owner','/admin/ventas'),'action':'quick','open_key':key});tid=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];unit=sql('SELECT id FROM products WHERE sku LIKE "R11-unit-%" ORDER BY id DESC LIMIT 1')[0]['id'];bulk=sql('SELECT id FROM products WHERE sku LIKE "R11-bulk-%" ORDER BY id DESC LIMIT 1')[0]['id'];path='/admin/ventas?screen=order&ticket='+str(tid);branch=sql('SELECT branch_id FROM pos_tickets WHERE id=?',[tid])[0]['branch_id']
    for rev,pid,qty in [(0,unit,2),(1,bulk,.25)]:request('owner',path,{'csrf':csrf('owner',path),'action':'add_item','revision':rev,'product_id':pid,'quantity':qty})
    items=sql('SELECT * FROM pos_ticket_items WHERE ticket_id=? ORDER BY id',[tid]);assert len(items)==2;sales=sql('SELECT COUNT(*) n FROM sales')[0]['n']
    def quote(mode,qs,parts=2):
        body={'csrf':csrf('owner',path),'tickets':[tid],'mode':mode,'quantities':qs,'parts':parts};r=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-owner.cookies','-H','Content-Type: application/json','-H','X-CSRF-Token: '+body['csrf'],'--data-binary','@-','-w','\n%{http_code}','http://localhost/api/pos-quote-r3?branch_id='+str(branch)],input=json.dumps(body).encode(),capture_output=True,check=True);raw,code=r.stdout.rsplit(b'\n',1);return int(code),json.loads(raw)
    code,out=quote('items',{str(items[0]['id']):.5});assert code==422 and 'enteras' in out['error'],(code,out)
    code,out=quote('items',{str(items[0]['id']):1});assert code==200 and out['ok'] and out['total']==100000
    code,out=quote('items',{str(items[1]['id']):.125});assert code==200 and out['ok'] and out['total']==25000
    code,out=quote('equal',{},3);assert code==200 and out['ok'] and out['total']==83333
    php="define('FISITAAP_SITE_ROOT','/var/www/html');require '/var/www/html/releases/1.8.0-r11/app/bootstrap.php';require '/var/www/html/releases/1.8.0-r11/app/pos_v141.php';require '/var/www/html/releases/1.8.0-r11/app/qa180.php';qa180_scope($app,101);echo json_encode(['tickets'=>[$app->one('SELECT * FROM pos_tickets WHERE id=?',[%d])],'items'=>pos_items_v141($app,%d)]);"%(tid,tid)
    data=json.loads(subprocess.run(['docker','exec','fisitaap-r2-web','php','-r',php],capture_output=True,check=True).stdout)
    node="const fs=require('node:fs');globalThis.FISITAAP180={enabled:true};require('./actualizaciones/1.8.0/web/assets/pos-v148.js');const x=JSON.parse(fs.readFileSync(0,'utf8')),q=globalThis.FisitaapPos.quote;let rejected=false;try{q(x.data,'items',{[x.unit]:.5},2);}catch(_){rejected=true;}if(!rejected||q(x.data,'items',{[x.unit]:1},2).total!==100000||q(x.data,'items',{[x.bulk]:.125},2).total!==25000||q(x.data,'equal',{},3).total!==83333)throw Error('quote parity failed');process.stdout.write('preview parity passed');"
    r=subprocess.run(['node','-e',node],cwd=ROOT,input=json.dumps({'data':data,'unit':items[0]['id'],'bulk':items[1]['id']}).encode(),capture_output=True,check=True);assert r.stdout==b'preview parity passed'
    assert sql('SELECT * FROM pos_ticket_items WHERE ticket_id=? ORDER BY id',[tid])==items and sql('SELECT COUNT(*) n FROM sales')[0]['n']==sales
    request('owner',path,{'csrf':csrf('owner',path),'action':'void','revision':2,'cancel_reason':'Cierre prueba división R11'})
    (ROOT/'actualizaciones/1.8.0/QA-DIVISION-R11.json').write_text(json.dumps({'passed':1,'checks':['actual API and frontend preview using actual SQL item metadata reject0.5unit transfer, accepts1unit and0.125kg transfers, preserves a3-way monetary division at83333cents; quotes do not mutate items/sales'],'environment':'fictional PHP/MariaDB real HTTP payment quote; no funds, printers or devices used'},indent=2)+'\n');print('PASS actual payment quote: whole-unit transfer, bulk fractions and equal money division, without data mutations',flush=True)
if __name__=='__main__':main()
