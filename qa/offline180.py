"""Real device API regression for immutable catalogs and a shared web/offline shift."""
import hashlib,json,pathlib,re,secrets,sys,uuid
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
from financial180 import api,post
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
    token=secrets.token_hex(32);token_hash=hashlib.sha256(token.encode()).hexdigest()
    sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"SHARED SHIFT QA",?)',[token_hash]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[token_hash])[0]['id']
    sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"SHARED REGISTER",?,202)',[did])
    sql('INSERT INTO fisitaap_primary179(tenant_id,device_id,generation) VALUES(101,?,1) ON DUPLICATE KEY UPDATE device_id=VALUES(device_id),generation=generation+1',[did])
    status,s=api('snapshot',{},token);assert status==200,s;s=s['snapshot'];assert s['offline_policy']['version']==180 and s['offline_policy']['allowed'];assert {u['id'] for u in s['users']}=={202}
    assert s['cash_state']['register']['device_id']==did
    passed('snapshot uses the existing assigned active users and the authorized principal register')
    sid=s['id'];before=len(sql('SELECT id FROM fisitaap_r2_snapshots WHERE device_id=?',[did]));sql('UPDATE product_inventory SET quantity=quantity+1 WHERE product_id=501 AND branch_id=301')
    status,fresh=api('snapshot',{},token);assert status==200 and fresh['snapshot']['id']==sid;assert len(sql('SELECT id FROM fisitaap_r2_snapshots WHERE device_id=?',[did]))==before
    p=lambda snap:next(x for x in snap['products'] if x['id']==501)
    assert p(fresh['snapshot'])['stock']==p(s)['stock']+1000
    passed('stock changes update the response without storing another full catalog')
    oldprice=sql('SELECT price FROM products WHERE id=501')[0]['price'];sql('UPDATE products SET price=price+1 WHERE id=501')
    status,changed=api('snapshot',{},token);assert status==200 and changed['snapshot']['id']!=sid;sql('UPDATE products SET price=? WHERE id=501',[oldprice])
    passed('price changes create a new immutable catalog while the old pending-sale catalog remains available')
    _,html=request('owner','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',html)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token)
    assert proof['cash'] and proof['cash_state']['device_id']==did
    assert request('owner','/api/native/bind',{'csrf':csrf('owner','/admin/turnos'),'proof':proof['proof']})[0]==200
    key=secrets.token_hex(16);assert post('owner','/admin/turnos',{'action':'open','opening_key':key,'opening_cash':100})[0]==302
    shift=sql('SELECT * FROM pos_shifts WHERE opening_key=?',[key])[0];assert shift['device_id']==did
    status,fresh=api('snapshot',{},token);assert status==200,fresh;current=fresh['snapshot'];live=current['cash_state']['shifts'];assert len(live)==1 and live[0]['server_id']==shift['id'];assert current['id']==sid
    meta={k:live[0][k] for k in ['id','user_id','register','opening_cash','opened_at']};meta['snapshot_id']=sid
    status,sync=api('sync',{'shifts':[meta],'sales':[]},token);assert status==200 and not sync['errors'],sync
    assert len(sql('SELECT id FROM pos_shifts WHERE register_id=?',[shift['register_id']]))==1
    passed('offline adoption of a web-opened UUID does not create a second shift')
    broken=dict(meta,opening_cash=999);_,sync=api('sync',{'shifts':[broken],'sales':[]},token);assert sync['errors'];assert len(sql('SELECT id FROM pos_shifts WHERE register_id=?',[shift['register_id']]))==1
    passed('altered opening cash is rejected without changing the adopted web shift')
    sale={'id':str(uuid.uuid4()),'snapshot_id':sid,'user_id':202,'register':meta['register'],'shift_id':meta['id'],'created_at':current['generated_at'],'items':[{'id':501,'quantity':1000,'options':[]}],'payments':{'cash':200000,'card':0,'sinpe':0},'customer':{}}
    status,sync=api('sync',{'shifts':[meta],'sales':[sale]},token);assert status==200 and not sync['errors'],sync
    websale=sync['acknowledged'][0]['sale_id'];assert sql('SELECT shift_id FROM sales WHERE id=?',[websale])[0]['shift_id']==shift['id']
    _,again=api('sync',{'shifts':[meta],'sales':[sale]},token);assert again['acknowledged'][0]['sale_id']==websale
    _,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202,'pending':[sale['id']]},token)
    assert proof['cash_state']['acknowledged']==[{'id':sale['id'],'sale_id':websale}]
    assert proof['cash_state']['shifts'][0]['server_cash_net']==113000
    passed('offline sales are received once in the same shift, with authoritative cash and lost-response acknowledgment')
    for direction,amount in [('in',50),('out',10)]:post('owner','/admin/caja-movimiento',{'direction':direction,'amount':amount,'reason':'SHARED CASH QA','confirmed':'yes','operation_key':secrets.token_hex(16)})
    _,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token);assert proof['cash_state']['shifts'][0]['server_cash_net']==117000
    closed={**meta,'closed_at':current['generated_at'],'closing_cash':127000,'denominations':{'1000':1,'100':2,'50':1,'10':2},'other_cash':0}
    _,sync=api('sync',{'shifts':[closed],'sales':[]},token);assert not sync['errors'],sync
    row=sql('SELECT status,expected_cash,closing_cash,difference_amount FROM pos_shifts WHERE id=?',[shift['id']])[0];assert row=={'status':'closed','expected_cash':'1270.00','closing_cash':'1270.00','difference_amount':'0.00'},row
    passed('offline denomination closing includes web cash movements and reconciles the shared shift once')
    pathlib.Path('actualizaciones/1.8.0/QA-CONTINUIDAD.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real PHP/MariaDB HTTP device API; no production'},indent=2)+'\n')
if __name__=='__main__':main()
