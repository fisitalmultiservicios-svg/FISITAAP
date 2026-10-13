"""Disposable demo integration using fictional templates, never migration/delete live data."""
import json,pathlib,re,secrets,subprocess,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
    subprocess.run(['docker','exec','fisitaap-r2-web','php','-r','require "/var/www/html/app/bootstrap.php";require "/var/www/html/app/demo_sandbox.php";demo_schema($app);'],capture_output=True,check=True)
    sql('INSERT INTO settings(`key`,`value`) VALUES("demo_sandbox_active","1") ON DUPLICATE KEY UPDATE value="1"')
    if not sql('SELECT id FROM tenants WHERE slug="restaurante-demo"'):
        sql('INSERT INTO tenants(id,name,slug,physical_store_enabled,catalog_label,is_listed) VALUES(901,"Restaurante demo QA","restaurante-demo",1,"Menú",0),(902,"Tienda demo QA","tienda-demo",1,"Catálogo",0)')
        sql('INSERT INTO branches(id,tenant_id,name,slug,is_default) VALUES(1301,901,"Demo principal","principal",1),(1302,902,"Tienda demo","principal",1)')
        sql('INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) SELECT 1201,901,"Dueño demo","restaurante.demo@fisitaap.com",password_hash,"tenant_admin",1 FROM users WHERE id=202');sql('INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) SELECT 1202,902,"Dueño tienda","negocio.demo@fisitaap.com",password_hash,"tenant_admin",1 FROM users WHERE id=202')
        sql('INSERT INTO categories(id,tenant_id,name,slug) VALUES(1401,901,"Comida demo","comida"),(1402,902,"Artículos demo","articulos")');sql('INSERT INTO products(id,tenant_id,category_id,name,slug,sku,price,tax_rate,track_inventory) VALUES(1501,901,1401,"Producto demo","producto","D1",1000,13,1),(1502,902,1402,"Artículo demo","articulo","D2",2000,0,1)')
        sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(901,1301,1501,30),(902,1302,1502,20)');sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(1301,1501,1),(1302,1502,1)')
        sql('INSERT INTO coupons(tenant_id,code,type,value,is_active,applicable_products,applicable_categories) VALUES(901,"DEMOQ","percent",10,1,"[1501]","[1401]")')
        sql('INSERT INTO fisitaap_r2_modifiers(id,tenant_id,kind,name,choices_json,is_active,legacy_family180,legacy_name180,legacy_min180,legacy_max180) VALUES(1801,901,"optional","Preparación","[]",0,NULL,NULL,0,1),(1802,901,"optional","Sin cebolla",?,1,1801,"Preparación",1,1)',[json.dumps([{'key':'one','name':'Sin cebolla','price':0}])])
        sql('INSERT INTO product_option_groups(id,tenant_id,product_id,name,type,min_select,max_select,modifier_id180) VALUES(1601,901,1501,"Sin cebolla","multiple",0,1,1802)');sql('INSERT INTO product_options(id,group_id,name,price_delta,modifier_option180) VALUES(1701,1601,"Sin cebolla",0,"one")');sql('INSERT INTO fisitaap_modifier_products180(tenant_id,modifier_id,product_id) VALUES(901,1802,1501)');sql('INSERT INTO fisitaap_r2_modifier_groups(modifier_id,product_id,group_id) VALUES(1802,1501,1601)')
    original=sql('SELECT quantity FROM product_inventory WHERE tenant_id=101 ORDER BY id');templates=sql('SELECT quantity FROM product_inventory WHERE tenant_id IN(901,902) ORDER BY id')
    def start(actor,kind='restaurante'):
        status,body=request(actor,'/demo/iniciar',{'csrf':csrf(actor,'/demo'),'type':kind,'mode':'panel'});assert status==200,(status,body[-500:]);token=re.search(r'sessionStorage.setItem\("fisitaap-demo-tab","([a-f0-9]{32})"',body)[1];row=sql('SELECT * FROM fisitaap_demo_sessions WHERE token=?',[token])[0];return '/demo/s/'+token,row
    base,a=start('demo180a');baseb,b=start('demo180b');assert a['tenant_id']!=b['tenant_id'];assert request('demo180b',base+'/admin')[0]==403
    passed('two visitors receive independent tenants and cannot reuse each other demo sessions')
    coupon=sql('SELECT applicable_products,applicable_categories FROM coupons WHERE tenant_id=?',[a['tenant_id']])[0];pid=sql('SELECT id FROM products WHERE tenant_id=?',[a['tenant_id']])[0]['id'];cat=sql('SELECT category_id FROM products WHERE id=?',[pid])[0]['category_id'];assert json.loads(coupon['applicable_products'])==[pid] and json.loads(coupon['applicable_categories'])==[cat]
    mods=sql('SELECT id,legacy_family180 FROM fisitaap_r2_modifiers WHERE tenant_id=? ORDER BY id',[a['tenant_id']]);assert mods[1]['legacy_family180']==mods[0]['id'] and mods[0]['id']!=1801
    passed('coupon product/category scopes and legacy mandatory option families are remapped to the private copy')
    token=csrf('demo180a',base+'/admin/turnos');opening=secrets.token_hex(16);request('demo180a',base+'/admin/turnos',{'csrf':token,'action':'open','opening_key':opening,'opening_cash':0});assert sql('SELECT id FROM pos_shifts WHERE tenant_id=? AND opening_key=?',[a['tenant_id'],opening])
    option=sql('SELECT o.id FROM product_options o JOIN product_option_groups g ON g.id=o.group_id WHERE g.tenant_id=?',[a['tenant_id']])[0]['id'];key=secrets.token_hex(16);request('demo180a',base+'/admin/ventas',{'csrf':token,'action':'quick','start_product':1,'product_id':pid,'quantity':1,'option_ids[]':option,'open_key':key});ticket=sql('SELECT id,revision FROM pos_tickets WHERE tenant_id=? AND open_key=?',[a['tenant_id'],key])[0];request('demo180a',base+'/admin/cobro?ticket='+str(ticket['id']),{'csrf':token,'payment_key':secrets.token_hex(16),'revisions['+str(ticket['id'])+']':ticket['revision'],'split_mode':'all','parts':2,'cash':1130,'print_receipt':1});assert sql('SELECT status FROM pos_tickets WHERE id=?',[ticket['id']])[0]['status']=='paid';assert sql('SELECT quantity FROM product_inventory WHERE tenant_id=?',[a['tenant_id']])[0]['quantity']=='29.000';assert all(r['status']=='simulated' for r in sql('SELECT status FROM fisitaap_print_jobs180 WHERE tenant_id=?',[a['tenant_id']]))
    passed('virtual demo cash can sell with inherited choices and all receipt/command jobs remain simulated')
    assert request('demo180a',base+'/api/desktop/snapshot',{'csrf':token})[0]==403;assert request('demo180a',base+'/demo')[0]==302
    passed('demo blocks real equipment services and Other demos exits to the public selector')
    status,_=request('demo180a',base+'/logout',{'csrf':token});assert status==302;assert not sql('SELECT id FROM tenants WHERE id=?',[a['tenant_id']]);assert not sql('SELECT id FROM pos_shifts WHERE tenant_id=?',[a['tenant_id']]);assert not sql('SELECT id FROM fisitaap_print_jobs180 WHERE tenant_id=?',[a['tenant_id']]);fresh,c=start('demo180a');assert c['tenant_id']!=a['tenant_id'];assert sql('SELECT quantity FROM product_inventory WHERE tenant_id=?',[c['tenant_id']])[0]['quantity']=='30.000'
    passed('finishing removes all private cash/history/print records and a new session starts from the frozen template')
    assert sql('SELECT quantity FROM product_inventory WHERE tenant_id=101 ORDER BY id')==original;assert sql('SELECT quantity FROM product_inventory WHERE tenant_id IN(901,902) ORDER BY id')==templates
    for actor,url in [('demo180a',fresh),('demo180b',baseb)]:request(actor,url+'/logout',{'csrf':csrf(actor,url+'/admin/turnos')})
    passed('demo disposal and new entry leave La Ventanita and both source templates unchanged')
    pathlib.Path('actualizaciones/1.8.0/QA-DEMOS.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated fictional templates, real HTTP/MariaDB'},indent=2)+'\n')
if __name__=='__main__':main()
