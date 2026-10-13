"""Bundled Android UI and engine with real PHP API; explicit native bridge substitute.
Native encrypted SQLite/CAS and receipt printing are tested by Android JUnit separately.
"""
import hashlib,json,pathlib,secrets,sys
from playwright.sync_api import sync_playwright
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql,PASSWORD
from financial180 import api
root=pathlib.Path('/workspace/FISITAAP/android/app/src/main/assets');checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
    code=secrets.token_hex(16);slug='android180-'+secrets.token_hex(6)
    sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,pair_hash,pair_expires) VALUES(101,301,"ANDROID UI QA",?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))',[hashlib.sha256(code.encode()).hexdigest()]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE pair_hash=?',[hashlib.sha256(code.encode()).hexdigest()])[0]['id']
    sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"Android principal QA",?,202)',[did])
    sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,track_inventory,min_qty,qty_step) VALUES(101,401,?,?,?,1000,13,1,1,1)',['Producto '+slug,slug,slug]);pid=sql('SELECT id FROM products WHERE slug=?',[slug])[0]['id'];sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(101,301,?,5)',[pid])
    offline=False
    def network(raw):
        value=json.loads(raw)
        if offline:return json.dumps({'requestId':value['requestId'],'transport_error':'offline fixture'})
        assert value['url'].startswith('https://fisitaap.test/api/desktop/')
        status,out=api(value['url'].rsplit('/',1)[1],json.loads(value.get('body','{}')),value.get('token','').removeprefix('Bearer '))
        return json.dumps({'requestId':value['requestId'],'status':status,'body':json.dumps(out)})
    init=r'''
window.OfflineStorage={read:()=>JSON.stringify({ok:true,revision:Number(localStorage.getItem('native-revision')||0),state:localStorage.getItem('native-state')}),commit:(text,revision)=>{if(window.failCommit)return JSON.stringify({ok:false,error:'storage failure'});const old=Number(localStorage.getItem('native-revision')||0);if(old!==revision)return JSON.stringify({ok:false,error:'stale write'});localStorage.setItem('native-state',text);localStorage.setItem('native-revision',old+1);return JSON.stringify({ok:true,revision:old+1});},backup:()=>JSON.stringify({ok:true}),digest:text=>{const request=new XMLHttpRequest();request.open('POST','https://appassets.androidplatform.net/qa-digest',false);request.send(text);return request.responseText;}};
window.FisitaapOfflineNetwork={postMessage:text=>offlineNetworkSend(text).then(out=>FisitaapOfflineNetwork.onmessage({data:out}))};
window.printedJobs=[];window.FisitaapAndroid={postMessage:text=>{const request=JSON.parse(text);if(request.action==='printLocal')printedJobs.push(request);setTimeout(()=>FisitaapAndroid.onmessage({data:JSON.stringify({requestId:request.requestId,ok:true,status:'sent_to_printer'})}),0)}};
'''
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox']);page=browser.new_page(viewport={'width':390,'height':844});errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.expose_function('offlineNetworkSend',network);page.add_init_script(init);page.add_init_script((root/'native-print.js').read_text())
        page.route('https://appassets.androidplatform.net/qa-digest',lambda route:route.fulfill(body=hashlib.sha256((route.request.post_data or '').encode()).hexdigest(),content_type='text/plain'))
        def asset(route):
            name=route.request.url.rsplit('/',1)[1];file=root/'offline'/name;assert file.is_file(),name;route.fulfill(body=file.read_bytes(),content_type='text/html' if name.endswith('.html') else 'text/css' if name.endswith('.css') else 'application/javascript')
        page.route('https://appassets.androidplatform.net/offline/*',asset);page.goto('https://appassets.androidplatform.net/offline/index.html');page.locator('#pairForm').wait_for();page.locator('[name=url]').fill('https://fisitaap.test');page.locator('[name=device_id]').fill(str(did));page.locator('[name=code]').fill(code);page.locator('#pairForm button').click();page.locator('#loginForm').wait_for()
        def login():
            page.locator('[name=user_id]').select_option('202');page.locator('[name=password]').fill(PASSWORD);assert page.locator('[name=register]').get_attribute('readonly') is not None;page.locator('#loginForm button').click();page.locator('#shiftButton').wait_for()
        login();blocked=page.evaluate("async()=>{const s=await(await fetch('/api/state')).json();return(await fetch('/api/shift/open',{method:'POST',headers:{'X-CSRF-Token':s.csrf},body:JSON.stringify({opening_cash:0})})).status}");assert blocked==403;passed('a secondary Android cannot open an offline shift; approved users and register are selected from its actual assignment')
        sql('INSERT INTO fisitaap_primary179(tenant_id,device_id,generation) VALUES(101,?,1) ON DUPLICATE KEY UPDATE device_id=VALUES(device_id),generation=generation+1',[did]);page.evaluate('async()=>{await offlineService.sync()}');page.reload();page.locator('#loginForm').wait_for();login()
        result=page.evaluate("async()=>{const s=await(await fetch('/api/state')).json();return(await fetch('/api/shift/open',{method:'POST',headers:{'X-CSRF-Token':s.csrf},body:JSON.stringify({opening_cash:0})})).json()}");assert result['ok'];page.evaluate('refresh()');offline=True
        page.locator('#search').fill('Producto '+slug);page.locator('#searchButton').click();page.locator('[data-add="'+str(pid)+'"]').click();page.locator('#charge').click();page.locator('#confirmCharge').click();page.wait_for_function('offlineService.store.data.sales.length===1');page.wait_for_function('printedJobs.length===1');assert not page.locator('#receiptDialog').get_attribute('open');sale=page.evaluate('offlineService.store.data.sales[0].id');assert page.evaluate('printedJobs[0].id')==sale;passed('the principal Android searches and sells without internet, saves once and sends the configured receipt without opening a preview')
        quantity=page.evaluate('(id)=>offlineService.store.data.stocks[id]',pid);assert quantity==4000,quantity
        page.reload();page.locator('#loginForm').wait_for();login();assert page.evaluate('offlineService.store.data.sales.length')==1;assert page.evaluate('offlineService.store.data.sales[0].synced') is False;quantity=page.evaluate('(id)=>offlineService.store.data.stocks[id]',pid);assert quantity==4000,quantity;passed('restart preserves the pending sale, its stock and shared shift')
        result=page.evaluate("async(id)=>{const s=await(await fetch('/api/state')).json();window.failCommit=true;const out=await(await fetch('/api/sales',{method:'POST',headers:{'X-CSRF-Token':s.csrf},body:JSON.stringify({id:crypto.randomUUID(),items:[{id,quantity:1000,options:[]}],payments:{cash:113000},customer:{}})})).json();window.failCommit=false;return out}",pid);assert not result['ok'] and result['not_saved'] is False;assert page.evaluate('offlineService.store.data.sales.length')==1;passed('uncertain storage failures keep the prior sale intact and do not invite a duplicate charge')
        offline=False;page.evaluate('async()=>{await offlineService.sync();await offlineService.sync()}');assert page.evaluate('offlineService.store.data.sales[0].synced') is True;assert len(sql('SELECT sale_id FROM fisitaap_r2_synced_sales WHERE device_id=? AND operation_id=?',[did,sale]))==1;assert sql('SELECT quantity FROM product_inventory WHERE product_id=? AND branch_id=301',[pid])[0]['quantity']=='4.000';passed('repeated reconnection receives the pending sale exactly once and reconciles stock')
        close=page.evaluate("async()=>{const s=await(await fetch('/api/state')).json();return(await fetch('/api/shift/close',{method:'POST',headers:{'X-CSRF-Token':s.csrf},body:JSON.stringify({closing_cash:113000})})).json()}");assert close['ok'];page.evaluate('async()=>{await offlineService.sync()}');assert sql('SELECT status,expected_cash,closing_cash FROM pos_shifts WHERE device_id=?',[did])==[{'status':'closed','expected_cash':'1130.00','closing_cash':'1130.00'}];passed('the shared Android shift closes and synchronizes with the same cash total and no JavaScript errors')
        page.locator('#logoutButton').click();page.locator('#loginForm').wait_for();assert 'Contraseña de tu cuenta' in page.inner_text('body');assert 'ok:false' not in page.inner_text('body');assert page.url=='https://appassets.androidplatform.net/offline/index.html';assert not errors,errors;browser.close();passed('Android offline logout returns to its approved-user login without a public landing page or raw JSON')
    pathlib.Path('actualizaciones/1.8.0/QA-ANDROID-UI.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'actual bundled JS/UI plus real PHP/MariaDB; native bridge substitute; encryption/CAS/printing tested separately with Android JUnit; no physical Android'},indent=2)+'\n')
if __name__=='__main__':main()
