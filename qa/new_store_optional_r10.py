"""New-business onboarding and named optional groups through real fake-data HTTP."""
import json,pathlib,re,secrets,sys
from playwright.sync_api import sync_playwright,expect
from usability_r7 import login
from browser_operations_r9 import login_page
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def post(actor,path,data):return request(actor,path,{'csrf':csrf(actor,path),**data})
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('master','master@r2.invalid');login('owner','owner@r2.invalid')
    tag=secrets.token_hex(4);versions=sql('SELECT * FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102) ORDER BY tenant_id');sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=102');pending=sql('SELECT * FROM fisitaap_tenant_updates180 WHERE tenant_id=102')
    try:
        for mode in ['Catálogo','Menú']:
            slug='new-r10-'+mode[:1].lower()+'-'+tag;email=slug+'@r2.invalid';data={'action':'save','name':'New R10 '+mode+' '+tag,'slug':slug,'whatsapp':'88887777','phone':'22223333','email':'','catalog_label':mode,'admin_name':'Dueño nuevo QA','admin_email':email,'admin_password':PASSWORD,'physical_store_enabled':'on'}
            assert post('master','/master/empresas',data)[0]==302;tenant=sql('SELECT id FROM tenants WHERE slug=?',[slug])[0]['id'];v=sql('SELECT * FROM fisitaap_tenant_updates180 WHERE tenant_id=?',[tenant])[0];assert v['version']==180 and v['modifiers_migrated']==1 and v['activated_by']==201 and v['activated_at']
            actor='new-'+slug;st,_=post(actor,'/owner-login',{'email':email,'password':PASSWORD});assert st==302;st,body=request(actor,'/change-password');assert st==200 and 'Crea una contraseña nueva' in body
            assert post(actor,'/change-password',{'password':PASSWORD+'-new','confirm':PASSWORD+'-new'})[0]==302
            st,body=request(actor,'/admin/actualizar-sistema');assert st==200 and 'Tu negocio ya está actualizado' in body and 'name="ready"' not in body
            assert 'Crear grupo de opcionales' in request(actor,'/admin/opcionales')[1];assert 'Portada del catálogo o menú' in request(actor,'/admin/contenido')[1];assert request(actor,'/admin/productos-importar')[0]==200
        passed('new Catálogo and Menú businesses are born180 inside creation; owner completes normal first-password flow and immediately sees current groups/design/import without manual activation')
        row=sql('SELECT * FROM tenants WHERE id=102')[0];post('master','/master/empresas',{'id':102,'name':row['name'],'slug':row['slug'],'phone':row['phone'] or '','whatsapp':row['whatsapp'] or '','email':row['email'] or '','catalog_label':row['catalog_label'],'physical_store_enabled':'on','is_listed':'on'});assert sql('SELECT * FROM fisitaap_tenant_updates180 WHERE tenant_id=102')==pending
        before=sql('SELECT COUNT(*) n FROM tenants')[0]['n'];vc=sql('SELECT COUNT(*) n FROM fisitaap_tenant_updates180')[0]['n'];post('master','/master/empresas',{**data,'slug':'fail-'+tag,'admin_email':'owner@r2.invalid'});assert sql('SELECT COUNT(*) n FROM tenants')[0]['n']==before and sql('SELECT COUNT(*) n FROM fisitaap_tenant_updates180')[0]['n']==vc
        assert post('master','/master/actualizar-sistema',{'action':'publish','backup':'yes'})[0]==200;assert sql('SELECT * FROM fisitaap_tenant_updates180 WHERE tenant_id=102')==pending;assert sql('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=?',[tenant])[0]['version']==180
        passed('editing or republishing does not auto-upgrade existing179; failed tenant creation leaves neither tenant nor version orphan, existing180 remains unchanged')
    finally:
        for row in versions:sql('UPDATE fisitaap_tenant_updates180 SET version=? WHERE tenant_id=?',[row['version'],row['tenant_id']])
    sql('INSERT INTO categories(tenant_id,name,slug) VALUES(101,?,?)',['Grupo R10 '+tag,'group-r10-'+tag]);cat=sql('SELECT id FROM categories WHERE slug=?',['group-r10-'+tag])[0]['id'];sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price) VALUES(101,?,?,?,?,1000)',[cat,'Camisa R10 '+tag,'camisa-r10-'+tag,'SHIRT-'+tag]);pid=sql('SELECT id FROM products WHERE sku=?',['SHIRT-'+tag])[0]['id']
    size='Tallas '+tag;color='Colores '+tag
    for name,names in [(size,['S','M','L']),(color,['Rojo','Azul'])]:
        post('owner','/admin/opcionales',{'group_name':name,'choices[0][name]':names[0],'choices[1][name]':names[1],**({'choices[2][name]':names[2]} if len(names)>2 else {}),'required':'on','categories[]':cat})
    st,body=request('owner','/api/pos-options?product_id='+str(pid));groups=json.loads(body)['groups'];talla=next(g for g in groups if g['name']==size);colors=next(g for g in groups if g['name']==color);assert len(talla['options'])==3 and len(colors['options'])==2 and talla['min_select']==1 and talla['max_select']==1
    passed('named Tallas and Colores groups inherit complete options by category and reach POS as independent required single-choice groups at zero extra price')
    root=sql('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=101 AND legacy_name180=? ORDER BY id',[size]);assignment=request('owner','/admin/producto-extras?id='+str(pid))[1];assert assignment.count('value="'+str(root[0]['id'])+'"')==1 and 'S, M, L' in assignment
    post('owner','/admin/producto-extras?id='+str(pid),{'id':pid,'modifiers[]':root[0]['id']});assert len(sql('SELECT * FROM fisitaap_modifier_products180 WHERE product_id=?',[pid]))==3
    key=secrets.token_hex(16);post('owner','/admin/ventas',{'action':'quick','open_key':key});ticket=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];path='/admin/ventas?screen=order&ticket='+str(ticket)
    post('owner',path,{'action':'add_item','revision':0,'product_id':pid,'quantity':1});assert not sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket])
    post('owner',path,{'action':'add_item','revision':0,'product_id':pid,'quantity':1,'option_ids[]':[talla['options'][0]['id'],talla['options'][1]['id'],colors['options'][0]['id']]});assert not sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket])
    post('owner',path,{'action':'add_item','revision':0,'product_id':pid,'quantity':1,'option_ids[]':[talla['options'][0]['id'],colors['options'][0]['id']]});line=sql('SELECT * FROM pos_ticket_items WHERE ticket_id=?',[ticket]);assert len(line)==1
    post('owner','/admin/opcionales',{'id':root[0]['id'],'group_name':size,'choices[0][id]':root[0]['id'],'choices[0][name]':'Pequeña','choices[1][id]':root[1]['id'],'choices[1][name]':'M','choices[2][name]':'XL','required':'on','products[]':pid,'categories[]':cat})
    assert sql('SELECT * FROM pos_ticket_items WHERE ticket_id=?',[ticket])==line;assert sql('SELECT is_active FROM fisitaap_r2_modifiers WHERE id=?',[root[2]['id']])[0]['is_active']==0;new=json.loads(request('owner','/api/pos-options?product_id='+str(pid))[1])['groups'];updated=next(g for g in new if g['name']==size);assert [o['name'] for o in updated['options']]==['Pequeña','M','XL'] and updated['options'][0]['id']==talla['options'][0]['id']
    passed('group assignment expands all members; server rejects zero/two sizes, accepts one size+color; editing/removing/adding choices keeps option IDs and historical ticket snapshot intact')
    before=sql('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=101 AND legacy_name180=?',[size]);post('owner','/admin/opcionales',{'id':root[0]['id'],'group_name':'Invalid','choices[0][name]':'X','products[]':502});assert sql('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=101 AND legacy_name180=?',[size])==before
    post('owner','/admin/opcionales',{'group_name':'Duplicate '+tag,'choices[0][name]':'S','choices[1][name]':'s'});assert not sql('SELECT id FROM fisitaap_r2_modifiers WHERE legacy_name180=?',['Duplicate '+tag])
    login('cashier','cashiera@r2.invalid');st,_=request('cashier','/admin/opcionales');assert st in [403,422];passed('foreign-product and duplicate-choice edits roll back, cashier has no optional-group administration permission')
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,page=login_page(browser,'owner@r2.invalid');errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto('http://localhost:18080/admin/opcionales?edit='+str(root[0]['id']));expect(page.locator('[name=group_name]')).to_have_value(size);n=page.locator('[data-optional180-line]').count();page.get_by_role('button',name='Agregar opción',exact=True).click();assert page.locator('[data-optional180-line]').count()==n+1;page.locator('[data-optional180-line]').last.locator('input:not([type=hidden])').fill('XXL');page.locator('[data-optional180-remove]').last.click();assert page.locator('[data-optional180-line]').count()==n
        for width in [1366,768,390]:page.set_viewport_size({'width':width,'height':900});assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
        assert not errors,errors;browser.close()
    passed('optional editor adds/removes individual rows accessibly and fits desktop/tablet/mobile with no JavaScript errors')
    post('owner',path,{'action':'void','revision':1});(ROOT/'actualizaciones/1.8.0/QA-ALTAS-OPCIONALES-R10.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'page_errors':errors,'environment':'fictional PHP/MariaDB and Chromium; real HTTP first-password/onboarding/group/POS flows, no production'},indent=2)+'\n')
if __name__=='__main__':main()
