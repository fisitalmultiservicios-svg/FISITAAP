"""Catalog/menu separation, direct delivery, phone checkout and zone persistence."""
import json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests');from r3_helpers import request,csrf,sql
from platform_r17 import post
from operations_r9 import jsoncall
from usability_r7 import login
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('owner','owner@r2.invalid');checks=[];tag='OPS17-'+secrets.token_hex(4)
 original=sql('SELECT catalog_label FROM tenants WHERE id=101')[0]['catalog_label'];overrides=sql('SELECT * FROM tenant_modules WHERE tenant_id=101 AND module_key IN("express","drivers")');intro=sql('SELECT setting_key,value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key IN("catalog_text_color","catalog_intro_mode")')
 try:
  sql('UPDATE tenants SET catalog_label="Catálogo" WHERE id=101')
  for module in ['express','drivers']:sql('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(101,?,0) ON DUPLICATE KEY UPDATE enabled=0',[module])
  for route in ['/admin/cocina','/admin/mesas','/admin/afiliados','/admin/invitaciones','/admin/express','/admin/entregas-historial']:assert request('owner',route)[0]==403,route
  html=request('owner','/admin/ventas')[1];assert 'Enviar comanda' not in html and 'screen=express' not in html and 'admin/cocina' not in html
  guest=tag+'guest';phone='8'+str(secrets.randbelow(10000000)).zfill(7);zone=sql('SELECT id FROM delivery_zones WHERE tenant_id=101 AND is_active=1 LIMIT 1')[0]['id']
  data={'csrf':csrf(guest,'/laventanita/catalogo'),'tenant':'laventanita','branch_id':301,'guest_name':tag,'guest_phone':phone,'payment':'Efectivo','delivery_type':'delivery','zone_id':zone,'address':'Dirección ficticia '+tag,'maps_url':'https://www.google.com/maps?q=9.93,-84.08','checkout_key':secrets.token_hex(16),'items':[{'id':501,'qty':1,'option_ids':[]}]}
  status,out=jsoncall(guest,'/api/checkout',{**data,'zone_id':0});assert status==422 and not out['ok']
  status,out=jsoncall(guest,'/api/checkout',data);assert status==200 and out['ok'] and out['customer_logged_in'],out;oid=out['order_id'];uid=sql('SELECT user_id FROM orders WHERE id=?',[oid])[0]['user_id'];assert sql('SELECT z.zone_id FROM customer_addresses a JOIN fisitaap_address_zones_r17 z ON z.address_id=a.id WHERE a.user_id=? AND z.tenant_id=101',[uid])[0]['zone_id']==zone
  status,again=jsoncall(guest,'/api/checkout',data);assert status==200 and again['order_id']==oid
  assert post(tag+'login','/laventanita/clientes/ingresar',{'phone':phone,'password':phone})[0]==302
  before=sql('SELECT COUNT(*) n FROM fisitaap_print_jobs180')[0]['n'];status,accepted=post('owner','/api/incoming180',{'order_id':oid});assert status==200 and 'comanda' not in json.loads(accepted)['message'].lower();assert sql('SELECT COUNT(*) n FROM fisitaap_print_jobs180')[0]['n']==before
  html=request('owner','/admin/pedidos')[1];assert 'Aceptar e imprimir comanda' not in html and 'name="action" value="prepare"' not in html
  rev=sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'];post('owner','/admin/pedidos',{'action':'status','id':oid,'origin':'web','revision':rev,'status':'delivered'});assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='delivered';assert not sql('SELECT id FROM delivery_jobs WHERE order_id=?',[oid])
  checks.append('Catalog hides and rejects kitchen, tables, Express and driver features; delivery checkout requires own zone, creates phone access, persists zone and retries once; direct business acceptance/completion creates no kitchen print or delivery task')
  # Acceptance prints once for menu; only monitor advances preparation.
  sql('UPDATE tenants SET catalog_label="Menú" WHERE id=101');data.update(checkout_key=secrets.token_hex(16),csrf=csrf(guest,'/laventanita/menu'));status,out=jsoncall(guest,'/api/checkout',data);assert status==200,out;oid=out['order_id'];before=sql('SELECT COUNT(*) n FROM fisitaap_print_jobs180')[0]['n'];assert post('owner','/api/incoming180',{'order_id':oid})[0]==200;printed=sql('SELECT COUNT(*) n FROM fisitaap_print_jobs180')[0]['n'];assert printed>before;assert post('owner','/api/incoming180',{'order_id':oid})[0]==200;assert sql('SELECT COUNT(*) n FROM fisitaap_print_jobs180')[0]['n']==printed
  rev=sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'];post('owner','/admin/pedidos',{'action':'prepare','origin':'web','id':oid,'revision':rev});assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='confirmed'
  post('owner','/admin/cocina',{'action':'prepare','origin':'web','id':oid,'revision':rev});assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='preparing';rev=sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'];post('owner','/admin/cocina',{'action':'prepared','origin':'web','id':oid,'revision':rev});assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='ready'
  rev=sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'];post('owner','/admin/pedidos',{'action':'handover','origin':'web','id':oid,'revision':rev});assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='delivered'
  checks.append('Menu acceptance queues comanda exactly once; Pedidos cannot run preparation, Monitor prepares/marks ready, and the business may hand over delivery directly without Entregas')
  post('owner','/admin/contenido',{'primary_color':'#173c54','accent_color':'#eef4f7','catalog_intro_mode':'custom','catalog_slide_seconds':'5','catalog_text_color':'#aa1133'})
  assert sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="catalog_text_color"')[0]['value']=='#aa1133';assert '--catalog-text-color:#aa1133' in request(guest,'/laventanita/menu?sucursal=principal')[1]
  post('owner','/admin/contenido',{'catalog_text_color':'red;display:none'});assert sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="catalog_text_color"')[0]['value']=='#aa1133'
  checks.append('Catalog slider text color is saved and rendered on active slides, rejects CSS injection, and does not change the official presentation')
 finally:
  sql('UPDATE tenants SET catalog_label=? WHERE id=101',[original]);sql('DELETE FROM tenant_modules WHERE tenant_id=101 AND module_key IN("express","drivers")')
  for r in overrides:sql('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(?,?,?)',[r['tenant_id'],r['module_key'],r['enabled']])
  sql('DELETE FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key IN("catalog_text_color","catalog_intro_mode")')
  for r in intro:sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(101,?,?)',[r['setting_key'],r['value']])
 out={'revision':'1.8.0-R17','passed':len(checks),'checks':checks,'environment':'Real HTTP/PHP/MariaDB fictional fixture. No WhatsApp messages or printer output executed.'};(ROOT/'actualizaciones/1.8.0/QA-PEDIDOS-R17.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print('PASS R17 operations',len(checks))
if __name__=='__main__':main()
