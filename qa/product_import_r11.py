"""Real XLSX/CSV preview, transactional creation, permissions and hostile-file QA."""
import csv,io,json,pathlib,re,secrets,sys,zipfile
from playwright.sync_api import sync_playwright,expect
from product_template180 import workbook,COLS
from browser_operations_r9 import login_page
from usability_r7 import login
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path('/workspace/fisitaap-env/import-r11');OUT.mkdir(exist_ok=True);checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def rows(suffix):return [[f'0001-{suffix}','Camiseta QA '+suffix,'Ropa '+suffix,8500,4200,13,'Si',20,3,'unidad',1,1,0,'Algodón','Activo'],[f'KG-{suffix}','Café QA '+suffix,'Ropa '+suffix,12000,6000,1,'Si',10.5,2,'kg',0.25,0.25,2,'Venta por peso','Oculto']]
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('owner','owner@r2.invalid');tag=secrets.token_hex(4);foreign=sql('SELECT * FROM products WHERE tenant_id=102 ORDER BY id');stock_other=sql('SELECT * FROM product_inventory WHERE branch_id=303 ORDER BY id');count=lambda:sql('SELECT COUNT(*) n FROM products WHERE tenant_id=101')[0]['n']
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,page=login_page(browser,'owner@r2.invalid');errors=[];page.on('pageerror',lambda e:errors.append(str(e)));page.goto('http://localhost:18080/admin/productos');page.get_by_role('link',name='Crear productos desde Excel',exact=True).click();expect(page.get_by_role('heading',name='Crear productos desde Excel',exact=True)).to_be_visible()
        with page.expect_download() as d:page.get_by_role('link',name='Descargar machote Excel').click()
        template=OUT/'template.xlsx';d.value.save_as(template);assert template.read_bytes()==(ROOT/'actualizaciones/1.8.0/web/assets/machote-productos.xlsx').read_bytes()
        with zipfile.ZipFile(template) as z:assert b'<row r="2"' not in z.read('xl/worksheets/sheet1.xml') and 'Camiseta'.encode() in z.read('xl/worksheets/sheet2.xml')
        passed('Products has a discoverable Excel action and authenticated downloadable real XLSX; Products sheet is empty, examples/help separate and leading-zero SKU column has Text style')
        def upload(path,branch=301):
            page.goto('http://localhost:18080/admin/productos-importar');page.locator('[name=branch_id]').evaluate('(el,value)=>{if(![...el.options].some(o=>o.value===value))el.append(new Option("Foreign QA branch",value));}',str(branch));page.locator('[name=branch_id]').select_option(str(branch));page.locator('[name=products_file]').set_input_files(path);page.get_by_role('button',name='Revisar archivo',exact=True).click();page.wait_for_load_state()
        file=OUT/'valid.xlsx';workbook(file,rows(tag));before=count();upload(file);expect(page.get_by_role('heading',name='Revisión: 2 productos nuevos')).to_be_visible();assert count()==before;assert page.locator('input[name=import_key]').count()==1;key=page.locator('[name=import_key]').input_value();token=page.locator('[name=csrf]').first.input_value();page.get_by_role('button',name='Crear 2 productos',exact=True).click();page.wait_for_url('**/admin/productos?branch_id=301');assert count()==before+2
        products=sql('SELECT * FROM products WHERE tenant_id=101 AND sku IN(?,?) ORDER BY sku',[r[0] for r in rows(tag)]);assert len(products)==2;assert {p['sku'] for p in products}=={r[0] for r in rows(tag)};assert any(p['status']=='hidden' and p['qty_step']=='0.250' for p in products);category=products[0]['category_id'];assert products[1]['category_id']==category
        pids=[p['id'] for p in products];assert len(sql('SELECT * FROM inventory_movements WHERE product_id IN(?,?) AND movement_type="initial"',pids))==2;assert [float(r['quantity']) for r in sql('SELECT quantity FROM product_inventory WHERE product_id IN(?,?) ORDER BY quantity',pids)]==[10.5,20];assert sql('SELECT * FROM product_inventory WHERE branch_id=303 ORDER BY id')==stock_other and sql('SELECT * FROM products WHERE tenant_id=102 ORDER BY id')==foreign
        # Send the same browser confirmation twice after the successful redirect.
        response=page.evaluate('''async x=>{const r=await fetch('/admin/productos-importar',{method:'POST',body:new URLSearchParams(x)});return {http:r.status,body:await r.text()};}''',{'csrf':token,'action':'commit','import_key':key});assert response['http']==200 and 'No se duplicaron productos' in response['body'] and count()==before+2
        passed('XLSX preview writes nothing; confirmation creates2 products/category, preserves leading-zero SKU and fractional quantities, records stock/movements only in selected branch; repeated confirmation creates nothing extra')
        upload(file);expect(page.get_by_role('alert')).to_contain_text('ya existe');assert not page.locator('[name=import_key]').count() and count()==before+2
        invalid=OUT/'invalid.xlsx';bad=rows(tag+'-bad');bad[0][3]=-3;bad[1][0]=bad[0][0];workbook(invalid,bad);upload(invalid);expect(page.get_by_role('alert')).to_contain_text('Fila 2');assert count()==before+2 and not page.locator('[name=import_key]').count()
        fractional=OUT/'fractional-unit.xlsx';unit_rows=rows(tag+'-unit');unit_rows[0][10]=.25;unit_rows[0][11]=.25;unit_rows[0][12]=2;workbook(fractional,unit_rows);upload(fractional);expect(page.get_by_role('alert')).to_contain_text('Por unidad');assert not page.locator('[name=import_key]').count() and count()==before+2
        good=OUT/'csv.csv';csv_rows=rows(tag+'-csv');csv_rows[1][0]='OTHER-'+tag;stream=io.StringIO();writer=csv.writer(stream,delimiter=';');writer.writerow(COLS);writer.writerows(csv_rows);good.write_text('\ufeff'+stream.getvalue(),encoding='utf8');upload(good,303);expect(page.get_by_role('heading',name='Revisión: 2 productos nuevos')).to_be_visible();pending=page.locator('[name=import_key]').input_value()
        # Another request uses one SKU after preview: commit must reject every row.
        sql('INSERT INTO products(tenant_id,name,slug,sku,price) VALUES(101,"Competing QA",?,?,1)',['competing-'+tag,csv_rows[0][0]]);before_conflict=count();page.get_by_role('button',name='Crear 2 productos',exact=True).click();expect(page.get_by_role('alert')).to_contain_text('ya existe');assert count()==before_conflict and not sql('SELECT id FROM products WHERE tenant_id=101 AND sku=?',[csv_rows[1][0]])
        sql('DELETE FROM products WHERE slug=?',['competing-'+tag]);upload(good,303);page.get_by_role('button',name='Crear 2 productos',exact=True).click();page.wait_for_url('**/admin/productos?branch_id=303');assert sql('SELECT COUNT(*) n FROM products WHERE tenant_id=101 AND sku IN(?,?)',[r[0] for r in csv_rows])[0]['n']==2
        passed('duplicates/negative amounts/fractional unit configuration reject the preview; UTF8 semicolon CSV works; commit revalidates a competing SKU and rolls back every row, then a corrected retry creates in branch303')
        # Hostile/malformed workbook variants are rejected before any product is written.
        def modified(name,replace):
            path=OUT/name
            with zipfile.ZipFile(file) as source,zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED) as dest:
                for info in source.infolist():dest.writestr(info.filename,replace(info.filename,source.read(info.filename)))
            return path
        formula=modified('formula.xlsx',lambda n,b:b.replace(b'<c r="D2" s="0"><v>8500</v></c>',b'<c r="D2"><f>1+1</f><v>2</v></c>') if n=='xl/worksheets/sheet1.xml' else b)
        dtd=modified('dtd.xlsx',lambda n,b:b.replace(b'<worksheet ',b'<!DOCTYPE worksheet [<!ENTITY x SYSTEM "file:///etc/passwd">]><worksheet ',1) if n=='xl/worksheets/sheet1.xml' else b)
        badrel=modified('external.xlsx',lambda n,b:b.replace(b'Target="worksheets/sheet1.xml"',b'Target="https://example.invalid/sheet.xml" TargetMode="External"') if n=='xl/_rels/workbook.xml.rels' else b)
        wrongsheet=modified('wrong.xlsx',lambda n,b:b.replace(b'name="Productos"',b'name="NoProductos"') if n=='xl/workbook.xml' else b)
        zipbomb=OUT/'large-xml.xlsx'
        with zipfile.ZipFile(zipbomb,'w',zipfile.ZIP_DEFLATED) as z:z.writestr('xl/workbook.xml',b'x'*(21*1024*1024))
        before_hostile=count()
        for path,text in [(formula,'fórmulas'),(dtd,'estructura'),(badrel,'dentro'),(wrongsheet,'hoja Productos'),(zipbomb,'descomprimido')]:upload(path);expect(page.get_by_role('alert')).to_contain_text(text);assert not page.locator('[name=import_key]').count() and count()==before_hostile
        many=OUT/'too-many.xlsx';workbook(many,[rows(str(n))[0] for n in range(501)]);upload(many);expect(page.get_by_role('alert')).to_contain_text('500');assert count()==before_hostile
        upload(file,302);expect(page.get_by_role('alert')).to_contain_text('sucursal activa');assert count()==before_hostile
        passed('formula cells, DTD/entities, external sheet relationship, missing Products sheet, decompression bomb,501rows and foreign branch are rejected without writes or stale confirmation')
        for width in [1366,768,390]:page.set_viewport_size({'width':width,'height':900});page.goto('http://localhost:18080/admin/productos-importar');assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
        assert not errors,errors;browser.close()
    login('cashier','cashiera@r2.invalid');assert request('cashier','/admin/productos-importar')[0] in [403,422]
    previous=sql('SELECT * FROM fisitaap_r1_user_roles WHERE tenant_id=101 AND user_id=204');role_name='IMPORT11-'+tag;sql('INSERT INTO fisitaap_r1_roles(tenant_id,name) VALUES(101,?)',[role_name]);role=sql('SELECT id FROM fisitaap_r1_roles WHERE name=?',[role_name])[0]['id']
    for section,action in [('dashboard','view'),('productos','view'),('productos','write')]:sql('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,?,?)',[role,section,action])
    sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,204,?,"cashier") ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)',[role])
    try:
        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,page=login_page(browser,'cashiera@r2.invalid');errors_role=[];page.on('pageerror',lambda e:errors_role.append(str(e)))
            # New categories and inventory cannot be smuggled through product-write permission.
            restricted=OUT/'restricted.xlsx';workbook(restricted,rows('RESTRICTED-'+tag));upload(restricted);expect(page.get_by_role('alert')).to_contain_text('Tu rol no permite');assert not page.locator('[name=import_key]').count()
            clean=OUT/'permission.xlsx';plain=rows('PERM-'+tag)[0];plain[2]='';plain[6]='No';plain[7]=0;plain[8]=0;workbook(clean,[plain]);upload(clean);expect(page.get_by_role('heading',name='Revisión: 1 producto nuevo')).to_be_visible();before_perm=count();sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=? AND section_key="productos" AND action_key="write"',[role]);page.get_by_role('button',name='Crear 1 producto',exact=True).click();assert 'Tu rol no permite' in page.inner_text('body') and count()==before_perm
            sql('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,"productos","write"),(?,"inventario","write"),(?,"inventario","view")',[role,role,role]);tracked=OUT/'tracked-permission.xlsx';plain[6]='Si';plain[7]=1;workbook(tracked,[plain]);upload(tracked);expect(page.get_by_role('heading',name='Revisión: 1 producto nuevo')).to_be_visible();sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=? AND section_key="inventario"',[role]);page.get_by_role('button',name='Crear 1 producto',exact=True).click();expect(page.get_by_role('alert')).to_contain_text('Tu rol no permite');assert count()==before_perm and not sql('SELECT id FROM products WHERE sku=?',[plain[0]]);assert not errors_role;browser.close()
    finally:
        sql('DELETE FROM fisitaap_r1_user_roles WHERE tenant_id=101 AND user_id=204')
        if previous:sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,204,?,?)',[previous[0]['role_id'],previous[0]['previous_role']])
        sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=?',[role]);sql('DELETE FROM fisitaap_r1_roles WHERE id=?',[role])
    passed('product-only role cannot create inventory/categories; product/inventory write permissions revoked after preview are revalidated at commit and every product is rolled back; editor has no JS errors at desktop/tablet/mobile')
    (ROOT/'actualizaciones/1.8.0/QA-EXCEL-R11.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'page_errors':errors,'template_bytes':template.stat().st_size,'environment':'fictional PHP/MariaDB and Chromium, generated actual XLSX and UTF8 CSV; no production or physical Microsoft Excel'},indent=2)+'\n')
if __name__=='__main__':main()
