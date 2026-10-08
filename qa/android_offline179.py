"""Bundled Android JS/real PHP integration; native storage bridge simulated here.
SQLite persistence/CAS/encryption failure is tested separately with Robolectric.
"""
import json,pathlib,re,sys,secrets
from playwright.sync_api import sync_playwright
sys.path.insert(0,str(pathlib.Path(__file__).parent))
from primary179_web import prepare,api,choose,sql,request,csrf
root=pathlib.Path('/workspace/FISITAAP/android/app/src/main/assets')
d=prepare();password=secrets.token_urlsafe(18)
slug='android179-'+secrets.token_hex(8)
sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,track_inventory,min_qty,qty_step) VALUES(101,401,?,?,?,1000,13,1,1,1)',['Prueba Android '+slug,slug,slug])
product_id=int(sql('SELECT id FROM products WHERE tenant_id=101 AND slug=?',[slug])[0]['id'])
sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(101,301,?,5)',[product_id])
assert request('owner','/admin/escritorio',{'csrf':csrf('owner','/admin/escritorio'),'action':'user','branch_id':301,'user_id':204,'password':password})[0]==302
offline=False
def network(raw):
    value=json.loads(raw)
    if offline:return json.dumps({'requestId':value['requestId'],'transport_error':'offline fixture'})
    action=value['url'].rsplit('/',1)[1]
    assert value['url'].startswith('https://fisitaap.test/api/desktop/')
    status,out=api(action,json.loads(value.get('body','{}')),value.get('token','').removeprefix('Bearer '))
    return json.dumps({'requestId':value['requestId'],'status':status,'body':json.dumps(out)})
init=r'''
window.OfflineStorage={
 read:()=>JSON.stringify({ok:true,revision:Number(localStorage.getItem('native-revision')||0),state:localStorage.getItem('native-state')}),
 commit:(text,revision)=>{if(window.failCommit)return JSON.stringify({ok:false,error:'simulated storage failure'});const old=Number(localStorage.getItem('native-revision')||0);if(old!==revision)return JSON.stringify({ok:false,error:'stale write'});localStorage.setItem('native-state',text);localStorage.setItem('native-revision',old+1);return JSON.stringify({ok:true,revision:old+1});},
 backup:()=>JSON.stringify({ok:true}),digest:text=>'simulated-native-digest:'+text
};
window.FisitaapOfflineNetwork={postMessage:text=>offlineNetworkSend(text).then(out=>FisitaapOfflineNetwork.onmessage({data:out}))};
window.printedJobs=[];
window.FisitaapAndroid={postMessage:text=>{const request=JSON.parse(text);printedJobs.push(request.job);setTimeout(()=>FisitaapAndroid.onmessage({data:JSON.stringify({requestId:request.requestId,ok:true,status:'sent_to_printer'})}),0)}};
'''
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':800,'height':1000});errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
    page.expose_function('offlineNetworkSend',network);page.add_init_script(init);page.add_init_script((root/'native-print.js').read_text())
    def asset(route):
        name=route.request.url.rsplit('/',1)[1];file=root/'offline'/name
        assert file.is_file(),name
        route.fulfill(body=file.read_bytes(),content_type='text/html' if name.endswith('.html') else 'text/css' if name.endswith('.css') else 'application/javascript')
    page.route('https://appassets.androidplatform.net/offline/*',asset)
    page.goto('https://appassets.androidplatform.net/offline/index.html')
    page.locator('#pairForm').wait_for();page.locator('[name=url]').fill('https://fisitaap.test');page.locator('[name=device_id]').fill(str(d['id']));page.locator('[name=code]').fill(d['code']);page.locator('#pairForm button').click()
    page.locator('#loginForm').wait_for();page.locator('[name=user_id]').select_option('204');page.locator('[name=password]').fill(password);page.locator('[name=register]').fill('Android principal');page.locator('#loginForm button').click();page.locator('#shiftButton').wait_for()
    blocked=page.evaluate("async()=>{const state=await (await fetch('/api/state')).json();return (await fetch('/api/shift/open',{method:'POST',headers:{'X-CSRF-Token':state.csrf},body:JSON.stringify({opening_cash:0})})).status}")
    assert blocked==403
    choose('owner',d);page.evaluate('async()=>{await offlineService.sync()}');page.reload();page.locator('#loginForm').wait_for();page.locator('[name=user_id]').select_option('204');page.locator('[name=password]').fill(password);page.locator('[name=register]').fill('Android principal');page.locator('#loginForm button').click();page.locator('#shiftButton').wait_for()
    page.on('dialog',lambda dialog:dialog.accept('0'));page.locator('#shiftButton').click();page.wait_for_function('offlineService.store.data.shifts.length===1')
    product=page.evaluate('(id)=>offlineService.store.data.snapshot.products.find(p=>p.id===id)',product_id)
    assert product
    offline=True
    page.locator('#search').fill(product['name']);page.locator('#searchButton').click();page.locator('[data-add="'+str(product['id'])+'"]').click();page.locator('#charge').click();page.locator('#confirmCharge').click();page.locator('#receiptDialog[open]').wait_for()
    page.locator('#printReceipt').click();page.wait_for_function('printedJobs.length===1');assert page.evaluate('printedJobs[0].id.startsWith("local-")')
    sale_id=page.evaluate('offlineService.store.data.sales[0].id');assert page.evaluate('offlineService.store.data.sales.length')==1
    page.reload();page.locator('#loginForm').wait_for();page.locator('[name=user_id]').select_option('204');page.locator('[name=password]').fill(password);page.locator('[name=register]').fill('Android principal');page.locator('#loginForm button').click();page.locator('#shiftButton').wait_for()
    assert page.evaluate('offlineService.store.data.sales.length')==1
    assert page.evaluate('offlineService.store.data.sales[0].synced') is False
    # A storage error is uncertain: keep the request for reconciliation, never invite a second charge.
    result=page.evaluate("async(id)=>{const state=await (await fetch('/api/state')).json();const p=state.snapshot.products.find(p=>p.id===id);window.failCommit=true;const out=await (await fetch('/api/sales',{method:'POST',headers:{'X-CSRF-Token':state.csrf},body:JSON.stringify({id:crypto.randomUUID(),items:[{id:p.id,quantity:p.min,options:[]}],payments:{cash:99999999},customer:{}})})).json();window.failCommit=false;return out}",product_id)
    assert result['ok'] is False and result['not_saved'] is False
    assert page.evaluate('offlineService.store.data.sales.length')==1
    offline=False;page.evaluate('async()=>{await offlineService.sync();await offlineService.sync()}')
    assert page.evaluate('offlineService.store.data.sales[0].synced') is True
    assert len(sql('SELECT operation_id FROM fisitaap_r2_synced_sales WHERE device_id=? AND operation_id=?',[d['id'],sale_id]))==1
    assert not errors,errors
    browser.close()
print('PASS Android bundled UI/engine + PHP: unassigned blocked; authorized offline search/sale/print; restart preserves outbox; storage uncertainty; repeated sync creates exactly one web sale')
