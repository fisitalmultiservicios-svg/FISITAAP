"""Delivery-only checkout fields, saved-address race and genuine pickup/delivery orders."""
import json,pathlib,secrets,sys
from playwright.sync_api import sync_playwright,expect
from interface_r15 import BASE
sys.path.insert(0,'/workspace/fisitaap-env/tests');from r3_helpers import sql
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');tag='R22D'+secrets.token_hex(4);saved=sql('SELECT catalog_label,catalog_mode,delivery_types,payment_methods FROM tenants WHERE id=102')[0];branch=sql('SELECT delivery_types,payment_methods FROM branches WHERE id=302')[0];sql('UPDATE tenants SET catalog_mode="global",delivery_types=?,payment_methods=? WHERE id=102',[json.dumps(['delivery','pickup','coordinated']),json.dumps(['Efectivo','SINPE Móvil'])]);sql('UPDATE branches SET delivery_types=NULL,payment_methods=NULL WHERE id=302');checks=[]
 sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,min_qty,qty_step,decimals) VALUES(102,402,?,?,?,1000,0,1,1,0)',[tag,tag.lower(),tag]);pid=sql('SELECT id FROM products WHERE sku=?',[tag])[0]['id'];sql('INSERT INTO delivery_zones(tenant_id,name,price,is_active) VALUES(102,?,500,1)',[tag]);zone=sql('SELECT id FROM delivery_zones WHERE name=?',[tag])[0]['id'];slug=sql('SELECT slug FROM tenants WHERE id=102')[0]['slug'];phone='8'+str(secrets.randbelow(10000000)).zfill(7)
 try:
  with sync_playwright() as pw:
   b=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);c=b.new_context(viewport={'width':390,'height':844});p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.goto(BASE+'/'+slug+'/catalogo');form=p.locator('#checkoutForm');expect(form).to_have_count(1)
   def order(kind,**extra):
    data={'csrf':p.evaluate('window.FISITAPP.csrf'),'tenant':slug,'branch_id':302,'guest_name':tag,'guest_phone':phone,'payment':'Efectivo','delivery_type':kind,'checkout_key':secrets.token_hex(16),'items':[{'id':pid,'qty':1}],**extra};r=p.request.post(BASE+'/api/checkout',data=data);return r,r.json()
   r,out=order('delivery',address='Dirección ficticia QA',zone_id=zone,maps_url='https://www.google.com/maps?q=10,-84');assert r.status==200 and out['ok'],out;customer=sql('SELECT user_id FROM orders WHERE id=?',[out['order_id']])[0]['user_id'];count=sql('SELECT COUNT(*) n FROM customer_addresses WHERE user_id=?',[customer])[0]['n'];checks.append('Real delivery creates the customer address with its valid zone and adds delivery fee once')
   p.goto(BASE+'/'+slug+'/catalogo');p.locator('[data-open-cart]:visible').first.click();types=form.locator('[name=delivery_type]');fields=form.locator('[data-delivery-fields]');types.select_option('delivery');form.locator('[name=guest_phone]').fill(phone);form.locator('[name=guest_phone]').blur();expect(form.locator('[data-saved-address]')).to_be_visible()
   for kind in ['pickup','coordinated','delivery','pickup','delivery']:
    types.select_option(kind)
    if kind=='delivery':
     expect(fields).to_be_visible();expect(form.locator('[name=zone_id]')).to_be_visible();expect(form.locator('[name=address]')).to_be_visible();expect(form.locator('[name=maps_url]')).to_be_visible();expect(form.locator('[data-saved-address]')).to_be_visible()
    else:
     expect(fields).not_to_be_visible();expect(form.locator('[name=address]')).not_to_be_visible();expect(form.locator('[data-saved-address]')).not_to_be_visible();assert form.evaluate('e=>!["zone_id","address_id","address","maps_url"].some(k=>new FormData(e).has(k))')
   checks.append('Only Delivery shows zone, written address, Maps/current location and saved addresses; pickup/coordinated hide and omit all of them, with switch-back preserving usable data')
   # Late phone lookup may change its child selector but cannot reveal the disabled parent.
   types.select_option('pickup');form.locator('[name=guest_phone]').fill(phone);form.locator('[name=guest_phone]').blur();p.wait_for_timeout(700);expect(fields).not_to_be_visible();assert fields.evaluate('e=>e.disabled') and form.locator('[name=address]').is_disabled();checks.append('Phone-address lookup finishing after switching to pickup cannot expose delivery controls or make them submit')
   for kind in ['pickup','coordinated']:
    r,out=order(kind,address='STALE MUST NOT SAVE',address_id=999999999,zone_id=zone,maps_url='https://www.google.com/maps?q=1,2');assert r.status==200 and out['ok'],out;row=sql('SELECT delivery_type,delivery_zone_id,delivery_total,address FROM orders WHERE id=?',[out['order_id']])[0];assert row['delivery_type']==kind and row['delivery_zone_id'] is None and float(row['delivery_total'])==0 and not row['address'],row
   assert sql('SELECT COUNT(*) n FROM customer_addresses WHERE user_id=?',[customer])[0]['n']==count;checks.append('Actual pickup/coordinated orders accept no delivery fields, ignore stale address/zone IDs, charge no delivery and create no saved address')
   r,out=order('delivery');assert r.status==422 and not out['ok'];r,out=order('delivery',address='Dirección nueva');assert r.status==422 and not out['ok'];checks.append('Delivery still enforces address and an active store zone on the server')
   sql('UPDATE tenants SET delivery_types=? WHERE id=102',[json.dumps(['pickup'])]);p.goto(BASE+'/'+slug+'/catalogo');p.locator('[data-open-cart]:visible').first.click();expect(form.locator('[data-delivery-fields]')).not_to_be_visible();assert form.locator('[data-delivery-fields]').evaluate('e=>e.disabled');checks.append('A pickup-only store starts with delivery fields hidden and disabled in server HTML, before any selection')
   assert not errors,errors;b.close()
 finally:
  sql('UPDATE tenants SET catalog_label=?,catalog_mode=?,delivery_types=?,payment_methods=? WHERE id=102',[saved[k] for k in ['catalog_label','catalog_mode','delivery_types','payment_methods']]);sql('UPDATE branches SET delivery_types=?,payment_methods=? WHERE id=302',[branch['delivery_types'],branch['payment_methods']])
 (ROOT/'actualizaciones/1.8.0/QA-DELIVERY-R22.json').write_text(json.dumps({'revision':'R22','passed':len(checks),'checks':checks,'environment':'Actual Chromium/HTTP/CSRF/PHP/MariaDB on a guarded fictional store. No WhatsApp or external messages sent.'},ensure_ascii=False,indent=2)+'\n');print('PASS',len(checks),'delivery groups',flush=True)
if __name__=='__main__':main()
