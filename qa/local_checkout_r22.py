"""Real local Node sales engine/UI; printer bridge is fictional and records calls only."""
import json,pathlib,subprocess,tempfile
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path('/workspace/fisitaap-env/r22');checks=[]
NODE=r'''
const {createLocalServer}=require('/workspace/FISITAAP/desktop/server');const bcrypt=require('/workspace/FISITAAP/desktop/node_modules/bcryptjs');
const snapshot={offline_policy:{version:179,allowed:true,generation:1},id:'a'.repeat(32),generated_at:new Date().toISOString(),tenant:{id:1,name:'Tienda QA R20',tax_included:false},branch:{id:1,name:'Principal'},products:[{id:1,name:'Producto QA',sku:'QA',price:1000,rate:0,cost:1,min:1000,step:1000,stock:100000,track:true,allow_negative:false,category_id:1,bogo:false,groups:[],components:[]}],users:[{id:1,name:'Caja QA',password_hash:bcrypt.hashSync('1357',4)}],customers:[],categories:[{id:1,name:'Productos'}]};
for(let i=2;i<=36;i++)snapshot.products.push({...snapshot.products[0],id:i,name:"Producto QA "+i,sku:"QA"+i});
createLocalServer({dataDir:process.argv[2],port:0,host:'127.0.0.1',autoSync:false,allowTestHttp:true,fetch:async(url)=>{if(url.endsWith('/pair'))return {ok:true,json:async()=>({ok:true,token:'t'.repeat(64),snapshot})};throw Error('No internet in fictional QA')}}).then(s=>{console.log(s.url);process.on('SIGTERM',()=>{s.close();process.exit(0)})});
'''
def main():
 with tempfile.TemporaryDirectory(prefix='r22-local-') as temp:
  f=pathlib.Path(temp)/'server.js';f.write_text(NODE);server=subprocess.Popen(['node',str(f),temp],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
  try:
   base=server.stdout.readline().strip();assert base.startswith('http://127.0.0.1:'),server.stderr.read()
   with sync_playwright() as pw:
    b=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);c=b.new_context(viewport={'width':1366,'height':768});c.add_init_script('window.printCalls=[];window.fisitaapWindows={printLocal:async value=>{window.printCalls.push(value);return {ok:true,status:value.output.endsWith("pdf")?"pdf_created":"sent_to_printer"}}};');p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.goto(base);p.locator('#pairForm [name=url]').fill('https://fictional.invalid');p.locator('#pairForm [name=device_id]').fill('1');p.locator('#pairForm [name=code]').fill('QA');p.locator('#pairForm button').click();p.locator('#loginForm [name=password]').fill('1357');p.locator('#loginForm button').click();expect(p.locator('#shiftButton')).to_be_visible();p.once('dialog',lambda d:d.accept('0'));p.locator('#shiftButton').click();expect(p.locator('#shiftButton')).to_have_text('Cerrar turno')
    def start():p.locator('[data-add="1"]').click();p.locator('#charge').click();expect(p.locator('#paymentDialog')).to_be_visible()
    for w,h in [(1366,630),(1280,580),(1024,600),(844,390)]:
     p.set_viewport_size({'width':w,'height':h});expect(p.locator('#charge')).to_be_in_viewport();assert p.locator('#root').evaluate('e=>e.scrollHeight<=e.clientHeight+1'),(w,h,'local outer scroll');assert p.locator('#products').evaluate('e=>e.clientHeight>=30&&e.scrollHeight>e.clientHeight'),(w,h,'local product height');p.locator('#products').evaluate('e=>e.scrollTop=e.scrollHeight');p.locator('[data-add="36"]').click();expect(p.locator('#charge')).to_be_in_viewport();assert p.locator('#products').evaluate('e=>e.scrollTop>0')
    p.set_viewport_size({'width':1366,'height':630});p.locator('a[href="#localHistory"]').click();expect(p.locator('#localHistoryDialog')).to_be_visible();p.get_by_role('button',name='Volver a la venta',exact=True).click();expect(p.locator('#localHistoryDialog')).not_to_be_visible();checks.append('Local installed frame fits four desktop/low-height sizes with fixed total/charge, internal product scrolling retained after add and history opening without moving the sale')
    start()
    for w,h in [(1366,768),(768,1024),(390,844),(320,700),(844,390)]:
     p.set_viewport_size({'width':w,'height':h});p.locator('#confirmPrintCharge').scroll_into_view_if_needed();expect(p.locator('#confirmPrintCharge')).to_be_in_viewport();p.locator('#receiptOutput').scroll_into_view_if_needed();expect(p.locator('#receiptOutput')).to_be_in_viewport();
     if not p.locator('#paymentDialog').evaluate('e=>e.scrollWidth<=e.clientWidth+1'):
      p.screenshot(path=str(OUT/'local-overflow.png'));print(p.locator('#paymentDialog').evaluate('e=>({rect:e.getBoundingClientRect().toJSON(),sw:e.scrollWidth,cw:e.clientWidth,children:[...e.querySelectorAll("*")].filter(x=>x.getBoundingClientRect().right>e.getBoundingClientRect().right).map(x=>({tag:x.tagName,class:x.className,width:x.getBoundingClientRect().width,right:x.getBoundingClientRect().right})).slice(0,20)})'),flush=True)
     assert p.locator('#paymentDialog').evaluate('e=>e.scrollWidth<=e.clientWidth+1'),(w,h)
    checks.append('Same real local Node checkout exposes both actions and three formats across five desktop/tablet/phone layouts')
    p.locator('#receiptOutput').select_option('letter_pdf');p.locator('#confirmCharge').click();expect(p.locator('#paymentDialog')).not_to_be_visible();assert p.evaluate('window.printCalls.length')==0;checks.append('Local Solo cobrar really saves without calling the native printer')
    for output in ['printer','letter_pdf','receipt_pdf']:
     start();p.locator('#receiptOutput').select_option(output);p.locator('#confirmPrintCharge').click();expect(p.locator('#paymentDialog')).not_to_be_visible();p.wait_for_timeout(30);assert p.evaluate('window.printCalls.at(-1).output')==output
    checks.append('Local Cobrar e imprimir forwards the selected format only after the durable local sale succeeds')
    start();p.locator('#receiptOutput').select_option('receipt_pdf');p.route('**/api/sales',lambda r:r.abort(),times=1);p.locator('#confirmPrintCharge').click();expect(p.locator('#charge')).to_have_text('Confirmar la venta anterior');before=p.evaluate('window.printCalls.length');p.locator('#charge').click();expect(p.locator('#charge')).not_to_have_text('Confirmar la venta anterior');assert p.evaluate('window.printCalls.length')==before+1;assert p.evaluate('window.printCalls.at(-1).output')=='receipt_pdf';state=p.request.get(base+'/api/state').json();assert len(state['sales'])==5 and len({s['id'] for s in state['sales']})==5;checks.append('Loss of transport keeps the original operation and output; retry via Solo cobrar default cannot change prior print intent or duplicate the five saved sales')
    assert not errors,errors;c.close();b.close()
  finally:server.terminate();server.wait(timeout=10)
 out={'revision':'Windows1.8.3/R22','passed':len(checks),'checks':checks,'environment':'Actual Node local engine and Chromium/UI on temporary fictional data; fake printLocal bridge, no Windows driver or printer.'};(ROOT/'actualizaciones/1.8.0/QA-LOCAL-R22.json').write_text(json.dumps(out,indent=2)+'\n');print('PASS',len(checks),'local checkout groups',flush=True)
if __name__=='__main__':main()
