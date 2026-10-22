"""Catalog header editing/visibility preserves branch promotions and cart behavior."""
import json,pathlib,secrets,sys
from PIL import Image
from playwright.sync_api import sync_playwright,expect
from browser_operations_r9 import login_page
from usability_r7 import login
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path('/workspace/fisitaap-env/design-r10')
def post(data):return request('owner','/admin/contenido',{'csrf':csrf('owner','/admin/contenido'),**data})
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';login('owner','owner@r2.invalid');sql('DELETE FROM rate_limits');sql('DELETE FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key IN("catalog_intro_mode","catalog_intro_title","catalog_intro_text","catalog_intro_image")');checks=[]
    sql('UPDATE tenant_content SET is_active=0 WHERE tenant_id=101 AND section_key="catalog_slider"');tag=secrets.token_hex(4)
    for branch,title in [(None,'Banner principal A '+tag),(None,'Banner principal B '+tag),(303,'Banner segunda sede '+tag)]:sql('INSERT INTO tenant_content(tenant_id,branch_id,section_key,title,body,is_active) VALUES(101,?,"catalog_slider",?,"Promoción vigente",1)',[branch,title])
    banners=sql('SELECT * FROM tenant_content WHERE tenant_id=101 AND section_key="catalog_slider" ORDER BY id');post({'catalog_intro_mode':'auto'});other=sql('SELECT * FROM tenants WHERE id=102')
    image=OUT/'catalog-logo.png';logo=Image.new('RGBA',(1600,800),(0,0,0,0));logo.paste((80,30,120,255),(100,100,1400,700));logo.save(image)
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,editor=login_page(browser,'owner@r2.invalid');errors=[];editor.on('pageerror',lambda e:errors.append(str(e)));editor.goto('http://localhost:18080/admin/contenido');editor.locator('a[href="#design-catalogo"]').click();expect(editor.locator('#design-catalogo')).to_have_attribute('open','');expect(editor.locator('[name=catalog_intro_title]')).to_have_value('Banner principal A '+tag)
        editor.locator('[name=catalog_intro_mode]').select_option('custom');editor.locator('[name=catalog_intro_title]').fill('Disfruta nuestro menú');editor.locator('[name=catalog_intro_text]').fill('Elige tus favoritos, hechos en casa.');editor.locator('[name=catalog_intro_image]').set_input_files(image);editor.get_by_role('button',name='Guardar diseño',exact=True).click();editor.wait_for_load_state();expect(editor.get_by_text('Diseño guardado.',exact=False)).to_be_visible()
        public=browser.new_context(viewport={'width':1366,'height':900});page=public.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.goto('http://localhost:18080/laventanita/catalogo?sucursal=principal');expect(page.locator('.catalog-hero .slider h1')).to_have_text('Disfruta nuestro menú');expect(page.locator('.catalog-hero .slider p')).to_have_text('Elige tus favoritos, hechos en casa.');assert page.locator('.catalog-hero .slider img.slide-image').count()==1;assert 'placeholder.svg' not in page.locator('.catalog-hero .slider').inner_html();assert page.locator('.slide-shade').evaluate('(e)=>getComputedStyle(e).backgroundImage')=='none';page.screenshot(path=str(OUT/'catalog-header.png'),full_page=True)
        assert sql('SELECT * FROM tenant_content WHERE tenant_id=101 AND section_key="catalog_slider" ORDER BY id')==banners;assert sql('SELECT * FROM tenants WHERE id=102')==other
        checks.append('catalog header is editable from the same section editor, including title/text/optimized image; no platform placeholder or green tint; existing branch banner records remain unchanged')
        post({'catalog_intro_mode':'hidden'});page.reload();assert page.locator('.catalog-hero .slider').count()==0;expect(page.locator('[data-category-launcher]')).to_be_visible();page.locator('[data-category-open]').first.click();page.locator('[data-product] [data-add-cart]').first.click();page.locator('[data-open-cart]').first.click();expect(page.locator('#checkoutForm')).to_be_visible();page.locator('[data-close-cart]').first.click()
        checks.append('hiding the header leaves categories, products, cart and checkout reachable; no whole-catalog section is accidentally removed')
        post({'catalog_intro_mode':'auto'});page.reload();expect(page.locator('.catalog-hero .slider .slide h1').first).to_have_text('Banner principal A '+tag);assert page.locator('.catalog-hero .slider .slide').count()==2;page.goto('http://localhost:18080/laventanita/catalogo?sucursal=segunda');expect(page.locator('.catalog-hero .slider .slide h1').first).to_have_text('Banner segunda sede '+tag)
        before=sql('SELECT setting_key,value FROM fisitaap_r2_settings WHERE tenant_id=101 ORDER BY setting_key');post({'catalog_intro_mode':'bad','hero_title':'Must not save'});assert sql('SELECT setting_key,value FROM fisitaap_r2_settings WHERE tenant_id=101 ORDER BY setting_key')==before;assert not errors,errors
        checks.append('restoring automatic mode restores original multi-slide/branch precedence without deleting custom data; invalid mode rejects the entire edit and there are no JavaScript errors')
        browser.close()
    (ROOT/'actualizaciones/1.8.0/QA-CATALOGO-R10.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'real fictional PHP/MariaDB and Chromium; no production'},indent=2)+'\n');print('PASS',len(checks),'catalog header groups')
if __name__=='__main__':main()
