"""Compare the approved HTML with real PHP views. Temporary sample DB is fully restored."""
import functools,hashlib,http.server,json,pathlib,secrets,subprocess,sys,threading
from PIL import Image,ImageDraw
from playwright.sync_api import sync_playwright,expect
from interface_r15 import login,BASE
from ux_designs_20261027 import route as refroute,act as refact
from build_interface_r15 import TARGET,content,SOURCES
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=ROOT/'descargas/1.8.0';PRIVATE=pathlib.Path('/workspace/fisitaap-env/interface-r15')
def run(args,data=None):return subprocess.run(args,input=data,capture_output=True,check=True).stdout
class Quiet(http.server.SimpleHTTPRequestHandler):
 def log_message(self,*args):pass
def rect(p,selector):
 return p.locator(selector).first.evaluate('''e=>{const r=e.getBoundingClientRect(),s=getComputedStyle(e);return {x:r.x,y:r.y,width:r.width,height:r.height,radius:s.borderRadius,font:s.fontSize,color:s.color,background:s.backgroundColor}}''')
def compare_shot(ref,real,name):
 a=Image.open(ref).convert('RGB');b=Image.open(real).convert('RGB');canvas=Image.new('RGB',(a.width+b.width+18,max(a.height,b.height)+36),'#e4eaf0');d=ImageDraw.Draw(canvas);d.text((14,11),'HTML APROBADO',fill='#193c56');d.text((a.width+32,11),'APLICACION PHP REAL - DATOS DE EJEMPLO',fill='#193c56');canvas.paste(a,(0,36));canvas.paste(b,(a.width+18,36));canvas.save(OUT/name,optimize=True)
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';assert TARGET.read_text()==content()
 db='fisitaap_r2_test';backup=run(['docker','exec','fisitaap-db','mariadb-dump','-uroot','--single-transaction','--skip-comments',db]);before=sql('SELECT COUNT(*) n FROM products')[0]['n']
 server=http.server.ThreadingHTTPServer(('127.0.0.1',18090),functools.partial(Quiet,directory=str(ROOT/'descargas/linea-fisitaap')));threading.Thread(target=server.serve_forever,daemon=True).start();records=[];errors=[]
 try:
  sql('DELETE FROM rate_limits');sql('UPDATE users SET name="Valeria Méndez" WHERE id=202');sql('UPDATE branches SET name="Aguas Zarcas · Sucursal principal" WHERE id=301 AND tenant_id=101');sql('UPDATE tenants SET primary_color="#276656" WHERE id=101')
  sql('UPDATE categories SET is_active=0 WHERE tenant_id=101');sql('UPDATE products SET status="hidden" WHERE tenant_id=101');cats={}
  for i,name in enumerate(['Bebidas','Cocina','Despensa','Tienda']):
   slug='r15-visual-'+str(i);sql('INSERT INTO categories(tenant_id,name,slug,sort_order) VALUES(101,?,?,?)',[name,slug,i]);cats[name]=sql('SELECT id FROM categories WHERE tenant_id=101 AND slug=?',[slug])[0]['id']
  products=[]
  for i,(name,category,price,stock,image) in enumerate([('Café latte','Bebidas',1800,28,'coffee'),('Hamburguesa artesanal','Cocina',4200,18,'burger'),('Tacos al pastor','Cocina',3500,23,'tacos'),('Pizza de la casa','Cocina',4800,14,'pizza'),('Café en grano','Despensa',9500,12,'coffee'),('Botella térmica','Tienda',8500,9,'bottle'),('Camiseta esencial','Tienda',6500,7,'shirt'),('Espresso doble','Bebidas',1500,34,'coffee')]):
   sku='R15-VISUAL-'+str(i);sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,image,track_inventory,min_qty,qty_step,decimals) VALUES(101,?,?,?,?,?,0,?,1,1,1,0)',[cats[category],name,sku.lower(),sku,price,'assets/demo/'+image+'.webp']);pid=sql('SELECT id FROM products WHERE sku=?',[sku])[0]['id'];products.append(pid);sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(101,301,?,?)',[pid,stock]);sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(301,?,1)',[pid])
  orders=sql('SELECT id FROM orders WHERE tenant_id=101 ORDER BY id DESC LIMIT 3');sql('UPDATE orders SET status="delivered" WHERE tenant_id=101')
  for i,(name,status,total) in enumerate([('Mariana Solís','new',8400),('Diego Vargas','preparing',7000),('Sofía Rojas','ready',11100)]):sql('UPDATE orders SET customer_name=?,status=?,total=? WHERE id=?',[name,status,total,orders[i]['id']])
  sql('UPDATE restaurant_tables SET is_active=0 WHERE tenant_id=101 AND branch_id=301');sql('UPDATE fisitaap_r2_rooms SET is_active=0 WHERE tenant_id=101 AND branch_id=301');sql('INSERT INTO fisitaap_r2_rooms(tenant_id,branch_id,name,is_active) VALUES(101,301,"Salón principal",1)');room=sql('SELECT MAX(id) id FROM fisitaap_r2_rooms WHERE tenant_id=101')[0]['id']
  for i in range(8):
   sql('INSERT INTO restaurant_tables(tenant_id,branch_id,name,capacity,area,sort_order) VALUES(101,301,?,?,"Salón principal",?)',['Mesa '+str(i+1).zfill(2),4 if i<4 else 2,i]);table=sql('SELECT MAX(id) id FROM restaurant_tables WHERE tenant_id=101')[0]['id'];x=80+(i%3)*220 if i<6 else 790;y=100+(i//3)*240 if i<6 else 105+(i-6)*240
   sql('INSERT INTO fisitaap_r2_floor(table_id,room_id,x,y,width,height,shape) VALUES(?,?,?,?,120,120,?)',[table,room,x,y,'round' if i in [1,4,7] else 'square'])
   sql('INSERT INTO fisitaap_r3_table_details(table_id,tenant_id,branch_id,basic_order) VALUES(?,101,301,?)',[table,i])
  for key,value in {'catalog_intro_mode':'custom','catalog_intro_image':'assets/demo/slide-food.webp','catalog_intro_title':'Hecho cerca. Hecho con cariño.','catalog_intro_text':'Tu selección favorita, lista para disfrutar.','catalog_intro_image_2':'','catalog_intro_image_3':'','catalog_slide_seconds':'5'}.items():sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(101,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[key,value])
  with sync_playwright() as pw:
   browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,p=login(browser,'owner@r2.invalid','admin');p.on('pageerror',lambda e:errors.append(str(e)));rc=browser.new_context(viewport={'width':1440,'height':1000});ref=rc.new_page();ref.goto('http://127.0.0.1:18090/index.html');ref.on('pageerror',lambda e:errors.append(str(e)))
   for route,name,selectors in [('/admin','RESUMEN',[('.topbar','.r15-topbar'),('.breadcrumb','.breadcrumb'),('.metrics','.metrics'),('.dash-grid','.dash-grid')]),('/admin/ventas?q=R15-VISUAL','VENTA',[('.pos-layout','.pos-layout'),('.ticket','.ticket'),('.pos-products','.pos-products')]),('/admin/mesas?view=graphic','MESAS',[('.tables-layout','.r15-floor-layout'),('.detail-panel','.r15-inline-table')])]:
    p.set_viewport_size({'width':1440,'height':1000});ref.set_viewport_size({'width':1440,'height':1000})
    if name=='VENTA':
     ref.set_viewport_size({'width':1440,'height':1000});refroute(ref,'pos');refact(ref,'product-add','p1');refact(ref,'product-add','p6');ref.locator('[data-action=page][data-direction="1"]').click();refact(ref,'product-add','p8');ref.locator('[data-action=page][data-direction="-1"]').click();expect(ref.locator('#toasts .toast')).to_have_count(0)
     p.goto(BASE+route);p.locator('form.r3-quick-product:has([name=product_id][value="'+str(products[0])+'"]) button').click();p.wait_for_url('**/admin/ventas?screen=order&ticket=*');ticket=p.url.split('ticket=')[1].split('&')[0];route='/admin/ventas?screen=order&ticket='+ticket+'&q=R15-VISUAL'
     for pid in [products[5],products[7]]:
      p.goto(BASE+route)
      with p.expect_navigation(wait_until='load'):p.locator('form.r3-quick-product:has([name=product_id][value="'+str(pid)+'"]) button').click()
    elif name=='MESAS':refroute(ref,'tables')
    for w,h in [(1440,1000),(768,1024),(390,844)]:
     ref.set_viewport_size({'width':w,'height':h});p.set_viewport_size({'width':w,'height':h});p.goto(BASE+route);p.wait_for_timeout(180)
     if name=='VENTA' and w<=650:p.locator('[data-r15-panel=invoice]').click();ref.locator('[data-tab=ticket]').click()
     assert p.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
     measurement={'screen':name,'width':w,'height':h,'geometry':[]}
     for a,b in selectors:
      r1=rect(ref,a);r2=rect(p,b);assert abs(r1['x']-r2['x'])<=5,(name,w,a,r1,r2);assert abs(r1['width']-r2['width'])<=20,(name,w,a,r1,r2)
      if name=='VENTA':assert abs(r1['y']-r2['y'])<=12,(name,w,a,r1,r2)
      measurement['geometry'].append({'reference':a,'production':b,'html':r1,'php':r2})
     records.append(measurement);rf=PRIVATE/f'ref-{name}-{w}.png';pf=OUT/f'R15-{name}-{w}.png';ref.screenshot(path=str(rf),animations='disabled');p.screenshot(path=str(pf),animations='disabled');compare_shot(rf,pf,f'R15-COMPARAR-{name}-{w}.png')
   assert not errors,errors;browser.close()
 finally:
  server.shutdown();server.server_close();run(['docker','exec','-i','fisitaap-db','mariadb','-uroot',db],backup)
 assert sql('SELECT COUNT(*) n FROM products')[0]['n']==before
 report={'revision':'1.8.0-R15','source_css':{str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in SOURCES},'shared_styles_sha256':hashlib.sha256(TARGET.read_bytes()).hexdigest(),'exact_reference_css_except_prototype_print_rule':True,'comparisons':records,'page_errors':errors,'scope':'Real PHP views compared with unchanged approved HTML at desktop/tablet/phone. Temporary fictional sample data; original fixture restored in finally. Production uses its real records, saved floor and role permissions. Different content/authorized header modes and native actions are intentional; this is not a claim of pixel identity across different data.'}
 (ROOT/'actualizaciones/1.8.0/QA-REFERENCIA-VISUAL-R15.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n');print('PASS R15 visual:',len(records),'HTML/PHP comparisons; fixture restored')
if __name__=='__main__':main()
