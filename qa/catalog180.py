"""Catalog assignment and legacy option-rule regressions, fixture only."""
import json,pathlib,secrets,subprocess,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
checks=[]
def passed(message):checks.append(message);print('PASS',message,flush=True)
def post(path,data):return request('owner',path,{'csrf':csrf('owner',path),**data})
def main():
    assert request('owner','/admin/posts')[0]==404 and request('master','/master/posts')[0]==404
    suffix=secrets.token_hex(4);name='QA Cheese '+suffix
    post('/admin/extras',{'action':'save','name':name,'price':75,'categories[]':401})
    mod=sql('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=101 AND name=?',[name]);assert len(mod)==1 and json.loads(mod[0]['choices_json'])==[{'name':name,'price':75}]
    mid=mod[0]['id'];groups=sql('SELECT product_id FROM product_option_groups WHERE tenant_id=101 AND modifier_id180=? AND is_active=1',[mid]);assert {int(g['product_id']) for g in groups}=={501,503}
    assert 'nombre | precio' not in request('owner','/admin/extras')[1]
    passed('an extra is created with individual name/price fields and inherited by every product of its category')
    code,body=request('owner','/api/pos-options?product_id=501');assert code==200
    info=json.loads(body);assert any(g['name']==name and len(g['options'])==1 for g in info['groups'])
    assert not sql('SELECT id FROM product_option_groups WHERE tenant_id=102 AND modifier_id180=?',[mid])
    passed('POS receives the materialized options in a scoped batched lookup')
    post('/admin/producto-extras?id=501',{'id':501,'modifiers[]':mid})
    sql('INSERT INTO categories(tenant_id,name,slug) VALUES(101,?,?)',['QA New '+suffix,'new-'+suffix]);category=sql('SELECT id FROM categories WHERE tenant_id=101 AND slug=?',['new-'+suffix])[0]['id']
    post('/admin/productos',{'id':501,'name':'Producto QA','slug':'product-qa','sku':'QA1','category_id':category,'price':1000,'tax_rate':13,'cost':100,'min_qty':1,'qty_step':1,'decimals':0,'track_inventory':'on','status':'active'})
    assert sql('SELECT category_id FROM products WHERE id=501')[0]['category_id']==category
    assert sql('SELECT id FROM product_option_groups WHERE product_id=501 AND modifier_id180=? AND is_active=1',[mid])
    post('/admin/producto-extras?id=501',{'id':501})
    assert not sql('SELECT id FROM product_option_groups WHERE product_id=501 AND modifier_id180=? AND is_active=1',[mid])
    assert sql('SELECT id FROM product_option_groups WHERE product_id=503 AND modifier_id180=? AND is_active=1',[mid])
    passed('category changes replace inherited extras while explicit product assignments remain until removed')
    post('/admin/extras',{'action':'save','name':'QA Foreign '+suffix,'price':1,'products[]':502})
    assert not sql('SELECT id FROM fisitaap_r2_modifiers WHERE name=?',['QA Foreign '+suffix])
    passed('a cross-business assignment rolls back the modifier and option writes')
    combo='QA Combo '+suffix
    post('/admin/combos',{'name':combo,'price':2500,'tax_rate':13,'component_lines[0][product_id]':501,'component_lines[0][quantity]':1,'component_lines[1][product_id]':501,'component_lines[1][quantity]':2,'component_lines[2][product_id]':503,'component_lines[2][quantity]':1})
    product=sql('SELECT id,cost FROM products WHERE tenant_id=101 AND name=?',[combo]);assert len(product)==1 and product[0]['cost']=='400.0000'
    items=sql('SELECT product_id,quantity FROM fisitaap_r2_combo_items WHERE combo_id=? ORDER BY product_id',[product[0]['id']]);assert items==[{'product_id':501,'quantity':'3.000'},{'product_id':503,'quantity':'1.000'}]
    passed('combo product rows merge duplicate components and calculate their actual estimated cost')
    assert 'Agregar producto al combo' in request('owner','/admin/combos?edit='+str(product[0]['id']))[1]
    assert 'General' in request('owner','/admin/productos?edit=501')[1] and 'Extras y opcionales' in request('owner','/admin/productos?edit=501')[1]
    assert request('owner','/admin')[0]==200 and 'Tu negocio, al día.' in request('owner','/admin')[1]
    assert request('owner','/admin/blog')[0]==404
    assert request('owner','/laventanita/blog')[0]==404
    passed('product tabs, welcome and hidden blog routes match the new navigation')
    # The individual editor must not silently turn a legacy mandatory choice
    # into an optional choice or force customers to select the first item.
    info=json.loads(request('owner','/api/pos-options?product_id=503')[1]);family=next(g for g in info['groups'] if g['name']=='Preparación anterior')
    assert family['min_select']==1 and family['max_select']==1 and {o['name'] for o in family['options']}=={'Sin cebolla','Sin chile'}
    openkey=secrets.token_hex(16);post('/admin/ventas',{'action':'quick','open_key':openkey});ticket=sql('SELECT id FROM pos_tickets WHERE open_key=? AND tenant_id=101',[openkey])[0]['id'];path='/admin/ventas?screen=order&ticket='+str(ticket)
    before=sql('SELECT quantity FROM product_inventory WHERE product_id=503 AND branch_id=301')[0]['quantity']
    post(path,{'action':'add_item','revision':0,'product_id':503,'quantity':1});assert not sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket])
    ids=[o['id'] for o in family['options']];post(path,{'action':'add_item','revision':0,'product_id':503,'quantity':1,'option_ids[]':ids[1]})
    assert len(sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket]))==1
    revision=sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]['revision']
    post(path,{'action':'add_item','revision':revision,'product_id':503,'quantity':1,'option_ids[]':ids});assert len(sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket]))==1
    post(path,{'action':'void','revision':revision});assert sql('SELECT quantity FROM product_inventory WHERE product_id=503 AND branch_id=301')[0]['quantity']==before
    passed('legacy mandatory single-choice rules still allow either choice and reject zero or two choices without changing stock')
    pathlib.Path('actualizaciones/1.8.0/QA-CATALOGO.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated HTTP/CSRF and MariaDB'},indent=2)+'\n')
if __name__=='__main__':main()
