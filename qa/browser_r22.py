"""Viewport-fit sales and table screens, long lists, payment and native window bridge."""
import json,pathlib,re,secrets,sys
from playwright.sync_api import sync_playwright,expect
from interface_r15 import login,BASE
from checkout_r20 import staff,setting
sys.path.insert(0,'/workspace/fisitaap-env/tests');from r3_helpers import sql
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path('/workspace/fisitaap-env/r22');OUT.mkdir(exist_ok=True)
SIZES=[(1920,1080),(1366,768),(1366,630),(1280,580),(1024,600),(900,520),(768,1024),(390,844),(360,740),(320,640)]
def visible(p,selector):
 e=p.locator(selector);expect(e).to_be_in_viewport();assert e.evaluate('e=>{const r=e.getBoundingClientRect();return r.top>=-1&&r.bottom<=innerHeight+1&&r.left>=-1&&r.right<=innerWidth+1}'),selector
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');tag='R22'+secrets.token_hex(4);uid=staff(tag,'tenant_admin');email=tag.lower()+'@example.invalid';saved=sql('SELECT catalog_label,catalog_mode FROM tenants WHERE id=101')[0];oldcash=sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="web_cash_r20"');setting('1');sql('UPDATE tenants SET catalog_label="Menú",catalog_mode="global" WHERE id=101');checks=[];layouts=[];errors=[]
 products=[]
 for i in range(32):
  name=tag+' producto '+str(i).zfill(2);sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,min_qty,qty_step,decimals) VALUES(101,401,?,?,?,1000,0,1,1,0)',[name,name.lower().replace(' ','-'),name]);pid=sql('SELECT id FROM products WHERE sku=?',[name])[0]['id'];products.append(pid);sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(301,?,1)',[pid])
 try:
  with sync_playwright() as pw:
   b=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);c,p=login(b,email,'admin');p.on('pageerror',lambda e:errors.append(str(e)));p.goto(BASE+'/admin/ventas');p.locator('[data-r18-open-shift]').click();p.locator('#r18OpenShift [name=opening_cash]').fill('0');p.locator('#r18OpenShift button:not([type=button])').click();p.wait_for_url('**/admin/ventas')
   # Use genuine account/item POSTs, not synthetic rows.
   def new_ticket(table=None):
    d={'csrf':p.evaluate('window.FISITAPP.csrf'),'action':'table' if table else 'quick','open_key':secrets.token_hex(16)}
    if table:d['table_id']=table
    response=p.request.post(BASE+'/admin/ventas',form=d);assert response.status==200,response.status;tid=int(re.search(r'ticket=(\d+)',response.url)[1]);path='/admin/ventas?screen=order&ticket='+str(tid)
    for pid in products[:22]:
     revision=sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision'];res=p.request.post(BASE+path,form={'csrf':d['csrf'],'revision':revision,'action':'add_item','product_id':pid,'quantity':1});assert res.status==200
    return tid,path
   tid,path=new_ticket()
   for w,h in SIZES:
    p.set_viewport_size({'width':w,'height':h});p.goto(BASE+path+'&q='+tag);p.wait_for_timeout(120)
    if w<=650:p.locator('[data-r15-panel=invoice]').click()
    assert p.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
    assert p.locator('#r15Viewport').evaluate('e=>e.scrollHeight<=e.clientHeight+1'),(w,h,'outer page scroll')
    for s in ['.r22-workspace-heading','.r19-sale-tools','.ticket-summary','.ticket-actions>.btn'] :visible(p,s)
    wrap=p.locator('.r3-sale-table-wrap');assert wrap.evaluate('e=>e.clientHeight>=60&&e.scrollHeight>e.clientHeight'),(w,h,'invoice list')
    top=p.locator('.ticket-actions').bounding_box()['y'];wrap.evaluate('e=>e.scrollTop=e.scrollHeight');assert wrap.evaluate('e=>e.scrollTop>0');assert p.locator('.ticket-actions').bounding_box()['y']==top
    if w<=650:p.locator('[data-r15-panel=products]').click()
    visible(p,'.r3-search');visible(p,'.quick-categories');list_=p.locator('.product-grid');assert list_.evaluate('e=>e.clientHeight>=70&&e.scrollHeight>e.clientHeight'),(w,h,'product list')
    list_.evaluate('e=>e.scrollTop=e.scrollHeight');expect(list_.locator('button').last).to_be_in_viewport();assert p.locator('#r15Viewport').evaluate('e=>e.scrollTop')==0
    if w>650:
     a=p.locator('.ticket').bounding_box();z=p.locator('.pos-products').bounding_box();assert a['width']>z['width'] and a['x']<z['x'];visible(p,'.ticket-actions>.btn')
    layouts.append({'width':w,'height':h,'outer_scroll':False,'fixed_total':True,'long_lists_reachable':True})
    if (w,h) in [(1366,630),(768,1024),(390,844)]:
     if w<=650:p.locator('[data-r15-panel=invoice]').click()
     p.screenshot(path=str(OUT/f'venta-{w}.png'))
   checks.append('Ten desktop/tablet/phone sizes keep the sale in the viewport, invoice larger left, fixed total/charge and independently scrollable 22-line invoice / 32-product catalog')
   p.set_viewport_size({'width':1366,'height':630});p.goto(BASE+path+'&q='+tag);catalog=p.locator('.product-grid');catalog.evaluate('e=>e.scrollTop=e.scrollHeight');before=catalog.evaluate('e=>e.scrollTop');p.locator('.r3-sale-table-wrap').evaluate('e=>e.scrollTop=e.scrollHeight');catalog.locator('button').last.click();p.wait_for_load_state();p.wait_for_timeout(150);assert p.locator('.product-grid').evaluate('e=>e.scrollTop')>=before-3;assert len(sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[tid]))==23;assert p.locator('#r15Viewport').evaluate('e=>e.scrollTop')==0;checks.append('Real repeated product addition retains product/invoice scroll and search while storing precisely one additional line')
   # Enlarged text/browser zoom represented by a smaller CSS viewport, same 16:9 screen.
   for w,h in [(1093,504),(911,420)]:
    p.set_viewport_size({'width':w,'height':h});p.goto(BASE+path+'&q='+tag);visible(p,'.ticket-actions>.btn');visible(p,'.r3-search');assert p.locator('#r15Viewport').evaluate('e=>e.scrollHeight<=e.clientHeight+1');layouts.append({'width':w,'height':h,'zoom_equivalent':True})
   checks.append('Reduced CSS viewport at 125/150 percent zoom retains charge, product search and the fixed frame')
   # Same frame applies inside a table, with current preparation and command controls.
   sql('INSERT INTO restaurant_tables(tenant_id,branch_id,name,capacity,space_type) VALUES(101,301,?,4,"table")',[tag]);table=sql('SELECT id FROM restaurant_tables WHERE name=?',[tag])[0]['id'];tt,tpath=new_ticket(table)
   for w,h in [(1366,630),(1024,600),(900,520)]:
    p.set_viewport_size({'width':w,'height':h});p.goto(BASE+tpath+'&q='+tag);visible(p,'.ticket-actions>.btn');visible(p,'.product-grid');visible(p,'[data-r19-table-status]');assert p.locator('#r15Viewport').evaluate('e=>e.scrollHeight<=e.clientHeight+1');expect(p.locator('form:has([name=action][value=send_kitchen]) button')).to_be_in_viewport();layouts.append({'width':w,'height':h,'table_frame':True})
   p.screenshot(path=str(OUT/'mesa-1024.png'));checks.append('Desktop table account shares fixed invoice/products frame, preparation status and command controls without page scrolling')
   p.set_viewport_size({'width':390,'height':844});p.goto(BASE+tpath+'&q='+tag);assert p.locator('.pos-mobile-tabs').count()==0;assert p.locator('.product-grid').evaluate('e=>getComputedStyle(e).overflowY')=='visible';p.locator('.r19-table-jump button').last.click();expect(p.locator('.ticket-actions>.btn')).to_be_visible();checks.append('Phone table retains continuous account/products navigation and touch-friendly section jumps')
   # Payment body can scroll, but both confirmations/output are always in the frame.
   checkout=BASE+'/admin/ventas?screen=checkout&ticket='+str(tid)
   for w,h in [(1366,630),(1024,600),(900,520),(768,1024),(390,844),(360,640),(844,390)]:
    p.set_viewport_size({'width':w,'height':h});p.goto(checkout);modal=p.locator('[data-payment-modal]');expect(modal).to_be_visible();assert modal.evaluate('e=>e.scrollWidth<=e.clientWidth+1')
    for s in ['[data-payment-modal]>form>header','[data-confirm-payment]','[name=receipt_output_r20]']:
     for element in p.locator(s).all():expect(element).to_be_in_viewport()
    assert modal.evaluate('e=>e.getBoundingClientRect().top>=0&&e.getBoundingClientRect().bottom<=innerHeight+1'),(w,h,'modal bounds')
    p.locator('.r22-payment-body').evaluate('e=>e.scrollTop=e.scrollHeight');expect(p.get_by_role('button',name='Solo cobrar',exact=True)).to_be_in_viewport();layouts.append({'width':w,'height':h,'payment_header_and_footer_fixed':True})
    if (w,h)==(1366,630):p.screenshot(path=str(OUT/'cobro-1366.png'))
   checks.append('Payment header, Solo cobrar, Cobrar e imprimir and output stay visible across seven sizes while the payment body scrolls independently')
   p.set_viewport_size({'width':1366,'height':630});p.get_by_role('button',name='Solo cobrar',exact=True).click();p.wait_for_url('**/admin/ventas?last_sale=*');assert sql('SELECT status FROM pos_tickets WHERE id=?',[tid])[0]['status']=='paid';assert len(sql('SELECT id FROM sales WHERE created_by=?',[uid]))==1;checks.append('Pinned Solo cobrar uses the original real settlement exactly once without creating a physical receipt')
   # New native bridge persists state independently of each document.
   c.add_init_script('window.fisitaapWindows={windowState:async()=>({fullscreen:sessionStorage.getItem("r22full")==="1"}),setFullscreen:async v=>{sessionStorage.setItem("r22full",v?"1":"0");return {fullscreen:v}},onWindowState:()=>{}};');p.goto(BASE+tpath);button=p.locator('[data-r15-full]');button.click();expect(button).to_have_attribute('aria-pressed','true');p.reload();expect(button).to_have_attribute('aria-pressed','true');p.goto(BASE+'/admin/mesas?view=graphic');expect(button).to_have_attribute('aria-pressed','true');button.click();expect(button).to_have_attribute('aria-pressed','false');checks.append('Installed full-screen button uses the restricted native window bridge and reflects state across reload and table navigation; explicit exit clears it')
   assert not errors,errors;c.close();b.close()
 finally:
  sql('UPDATE pos_shifts SET status="closed",closed_at=NOW() WHERE user_id=? AND status="open"',[uid]);sql('UPDATE tenants SET catalog_label=?,catalog_mode=? WHERE id=101',[saved['catalog_label'],saved['catalog_mode']]);setting(oldcash[0]['value'] if oldcash else '0')
 out={'revision':'1.8.0-R22/Windows1.8.3','passed':len(checks),'checks':checks,'layouts':layouts,'page_errors':errors,'environment':'Actual Chromium/HTTP/PHP/MariaDB guarded fictional fixture; native bridge simulated, no physical Windows display or printer.'};(ROOT/'actualizaciones/1.8.0/QA-PANTALLAS-R22.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print('PASS',len(checks),'flows',len(layouts),'layouts',flush=True)
if __name__=='__main__':main()
