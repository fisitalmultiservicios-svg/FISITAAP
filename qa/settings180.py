"""Business identification, reception preference and public-only design."""
import json,pathlib,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
    sql("UPDATE tenants SET tax_id_required=0 WHERE id=101");sql("UPDATE branches SET web_order_acceptance=1 WHERE id=301")
    tenant=sql('SELECT * FROM tenants WHERE id=101')[0];data={k:tenant.get(k) or '' for k in ['name','email','phone','whatsapp','address','business_hours','map_embed','catalog_label','offline_message']};data.update({'csrf':csrf('owner','/admin/configuracion'),'tax_id_required':1,'tax_id':'','web_order_acceptance':'0'})
    request('owner','/admin/configuracion',data);assert sql('SELECT tax_id_required FROM tenants WHERE id=101')[0]['tax_id_required']==0;assert sql('SELECT web_order_acceptance FROM branches WHERE id=301')[0]['web_order_acceptance']==1
    passed('required identification cannot be empty and invalid settings do not partially change branch reception')
    request('owner','/admin/configuracion',{**data,'tax_id':'3-101-123456'});row=sql('SELECT tax_id,tax_id_required FROM tenants WHERE id=101')[0];assert row=={'tax_id':'3-101-123456','tax_id_required':1};assert sql('SELECT web_order_acceptance FROM branches WHERE id=301')[0]['web_order_acceptance']==0
    passed('company identification and automatic reception can be stored together in the selected branch')
    if request('cashier','/owner-login')[0]==200:request('cashier','/owner-login',{'csrf':csrf('cashier','/owner-login'),'email':'cashiera@r2.invalid','password':PASSWORD})
    assert request('cashier','/admin/configuracion',{**data,'tax_id':'1-1111-1111'})[0]==403;assert sql('SELECT tax_id FROM tenants WHERE id=101')[0]['tax_id']=='3-101-123456'
    passed('a limited cashier cannot alter general business or fiscal settings')
    design={'csrf':csrf('owner','/admin/contenido'),'hero_title':'MI NEGOCIO QA','hero_text':'Textos simples','about_title':'Nosotros QA','about_text':'Atención','services_title':'Productos QA','contact_title':'Contacto QA','contact_text':'Visítanos','hero_button_label':'Abrir mi menú','catalog_button_label':'Todo mi menú','primary_color':'#9b1122','accent_color':'#2255cc'};request('owner','/admin/contenido',design)
    status,public=request('owner','/laventanita');assert status==200 and 'MI NEGOCIO QA' in public and 'Abrir mi menú' in public and 'Todo mi menú' in public,(status,public[-500:]);status,editor=request('owner','/admin/contenido');assert status==200 and 'qa180-design-preview' in editor and all(x in editor for x in ['Portada','Nosotros','Colores y botones']);status,panel=request('owner','/admin/clientes');assert status==200 and 'qa180.css' in panel
    passed('section editor publishes the requested texts and buttons and provides a same-origin saved-page preview')
    request('owner','/admin/contenido',{**design,'primary_color':'invalid','hero_title':'SHOULD NOT SAVE'});assert sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="hero_title"')[0]['value']=='MI NEGOCIO QA'
    passed('invalid public colors roll back the design changes without affecting the owner-panel style')
    sql('UPDATE branches SET web_order_acceptance=1 WHERE id=301')
    pathlib.Path('actualizaciones/1.8.0/QA-CONFIGURACION.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real HTTP/MariaDB; visual browser checks separate'},indent=2)+'\n')
if __name__=='__main__':main()
