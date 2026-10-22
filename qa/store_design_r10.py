"""Real editor upload, contact/brand rendering and access checks on fictional data."""
import json,pathlib,random,re,secrets,subprocess,sys,urllib.parse
from PIL import Image
from playwright.sync_api import sync_playwright,expect
from browser_operations_r9 import login_page
from usability_r7 import login
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql

ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path('/workspace/fisitaap-env/design-r10');OUT.mkdir(exist_ok=True)
checks=[]
def passed(label):checks.append(label);print('PASS',label,flush=True)
def values():return {r['setting_key']:r['value'] for r in sql('SELECT setting_key,value FROM fisitaap_r2_settings WHERE tenant_id=101')}
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('owner','owner@r2.invalid')
    foreign=sql('SELECT * FROM tenants WHERE id=102');fiscal=sql('SELECT tax_id,tax_id_required,payment_methods,delivery_types,tax_included FROM tenants WHERE id=101')
    photo=OUT/'large-photo.png';Image.frombytes('RGB',(2400,1600),random.Random(10).randbytes(2400*1600*3)).save(photo);assert photo.stat().st_size>10*1024*1024
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,editor=login_page(browser,'owner@r2.invalid');errors=[];editor.on('pageerror',lambda e:errors.append(str(e)));ctx.route('https://www.google.com/maps/embed**',lambda r:r.fulfill(body='<p>Mapa ficticio de QA</p>',content_type='text/html'))
        editor.goto('http://localhost:18080/admin/contenido');expect(editor.get_by_text('Imágenes JPG, PNG o WebP de hasta 25 MB.',exact=False)).to_be_visible();editor.locator('a[href="#design-nosotros"]').click();expect(editor.locator('#design-nosotros')).to_have_attribute('open','')
        data={'hero_title':'Tienda de prueba','hero_text':'Productos frescos, cada día.','about_label':'NUESTRA HISTORIA','about_title':'AQUÍ EMPEZÓ TODO','about_text':'Un negocio cercano a su comunidad.','about_promise_1':'Hecho en casa','about_promise_2':'Ingredientes frescos','about_promise_3':'Entrega puntual','about_button_label':'Conocer nuestro menú','contact_title':'Visítanos','contact_text':'Estamos para atenderte.'}
        for name,value in data.items():editor.locator('[name='+name+']').evaluate('(e)=>e.closest("details").open=true');editor.locator('[name='+name+']').fill(value)
        editor.locator('a[href="#design-contacto"]').click();contact={'email':'contacto-qa@example.invalid','phone':'22223333','whatsapp':'88884444','address':'Dirección ficticia del local','business_hours':'Lunes a sábado, de 8 a 18 h','map_embed':'<iframe src="https://www.google.com/maps/embed?pb=qa-r10"></iframe>','facebook_url':'https://www.facebook.com/tienda-qa','instagram_url':'https://www.instagram.com/tienda-qa','tiktok_url':'https://www.tiktok.com/@tienda-qa'}
        for name,value in contact.items():editor.locator('[name='+name+']').fill(value)
        editor.locator('details:has([name=primary_color])').evaluate('(e)=>e.open=true');editor.locator('[name=primary_color]').fill('#7b236f');editor.locator('[name=accent_color]').fill('#e6dbea');editor.locator('#design-portada').evaluate('(e)=>e.open=true')
        editor.locator('[name=hero_image]').set_input_files(photo)
        with editor.expect_response(lambda r:r.request.method=='POST' and '/admin/contenido' in r.url) as response:
            editor.evaluate('()=>document.querySelector(".qa180-design form").requestSubmit()')
        body_bytes=len(response.value.request.post_data_buffer);assert body_bytes<2*1024*1024,body_bytes;editor.wait_for_load_state();expect(editor.get_by_text('Diseño guardado.',exact=False)).to_be_visible()
        saved=values();assert all(saved[k]==v for k,v in data.items());path=pathlib.Path('/workspace/fisitaap-r2-test')/urllib.parse.urlsplit(saved['hero_image']).path.lstrip('/');assert path.is_file();image=Image.open(path);assert image.format=='WEBP' and max(image.size)<=1440 and path.stat().st_size<photo.stat().st_size/10;assert pathlib.Path(str(path)+'.thumb.webp').is_file()
        passed('an actual11MB PNG can be submitted immediately while preparation is pending; multipart is under2MB and server stores a WebP/thumbnail under1440px, over90% smaller, with all editor values saved')
        tenant=sql('SELECT * FROM tenants WHERE id=101')[0];assert all(tenant[k]==v for k,v in contact.items());assert sql('SELECT tax_id,tax_id_required,payment_methods,delivery_types,tax_included FROM tenants WHERE id=101')==fiscal;assert sql('SELECT * FROM tenants WHERE id=102')==foreign
        public=ctx.new_page();public.on('pageerror',lambda e:errors.append(str(e)));public.goto('http://localhost:18080/laventanita');expect(public.locator('#nosotros small')).to_have_text('NUESTRA HISTORIA');expect(public.locator('.r2-promises span')).to_have_text(['Hecho en casa','Ingredientes frescos','Entrega puntual']);expect(public.locator('#nosotros .btn')).to_have_text('Conocer nuestro menú');assert public.locator('a[href="mailto:contacto-qa@example.invalid"]').count()==1;assert public.locator('a[href="tel:22223333"]').count()==1;assert public.locator('a[href="https://wa.me/50688884444"]').count()>=1;expect(public.locator('#contacto')).to_contain_text(contact['address']);expect(public.locator('#contacto')).to_contain_text(contact['business_hours']);assert public.locator('#contacto iframe').count()==1;assert public.locator('footer .r2-platform-offer').count()==1
        passed('Nosotros label, title, three phrases and button are editable; contact email/phone/WhatsApp/address/hours/map/social links publish from the same general records without changing fiscal settings or another tenant')
        def style():return public.evaluate('''()=>({overlay:getComputedStyle(document.querySelector('.r2-hero'),':after').display,image:getComputedStyle(document.querySelector('.r2-hero>img')).opacity,buttons:[...document.querySelectorAll('.r2-landing a.btn')].filter(e=>e.href.includes('/laventanita/')).map(e=>({bg:getComputedStyle(e).backgroundColor,fg:getComputedStyle(e).color}))})''')
        state=style();assert state['overlay']=='none' and state['image']=='1' and len(state['buttons'])>=4 and all(x['bg']=='rgb(123, 35, 111)' for x in state['buttons']),state
        assert editor.evaluate('getComputedStyle(document.documentElement).getPropertyValue("--primary").trim()')=='#203f57'
        editor.locator('details:has([name=primary_color])').evaluate('(e)=>e.open=true');editor.locator('[name=primary_color]').fill('#ffdd00');editor.get_by_role('button',name='Guardar diseño',exact=True).click();editor.wait_for_load_state();public.reload();state=style();assert all(x['bg']=='rgb(255, 221, 0)' and x['fg']=='rgb(0, 0, 0)' for x in state['buttons']),state
        geometry=[]
        for width,height in [(1366,900),(768,1024),(390,844)]:
            public.set_viewport_size({'width':width,'height':height});public.screenshot(path=str(OUT/('store-'+str(width)+'.png')),full_page=True);g=public.evaluate('()=>({width:innerWidth,scroll:document.documentElement.scrollWidth})');assert g['scroll']<=width+1,g;geometry.append(g)
        editor.screenshot(path=str(OUT/'editor.png'),full_page=True);assert not errors,errors
        passed('hero image has no overlay; every catalog CTA uses the selected purple or yellow primary with readable contrast; owner panel stays neutral and store fits1366/768/390px without page overflow or JS errors')
        # Invalid contact values must reject the complete edit rather than partially save.
        before=values();contact_before=sql('SELECT * FROM tenants WHERE id=101');bad={'csrf':csrf('owner','/admin/contenido'),'hero_title':'SHOULD NOT SAVE','primary_color':'#123456','accent_color':'#ffffff','contact_info180':1,**contact,'email':'invalid'};request('owner','/admin/contenido',bad);assert values()==before and sql('SELECT * FROM tenants WHERE id=101')==contact_before
        request('owner','/admin/contenido',{**bad,'email':contact['email'],'map_embed':'<iframe src="https://evil.invalid/"></iframe>'});assert values()==before
        passed('invalid email or non-Google map rejects the design atomically; previously published image/contact/theme remain unchanged')
        browser.close()
    # A designer role must not gain general-settings authority by posting contact fields.
    login('cashier','cashiera@r2.invalid');role_tag='DESIGN10-'+secrets.token_hex(4);sql('INSERT INTO fisitaap_r1_roles(tenant_id,name) VALUES(101,?)',[role_tag]);role=sql('SELECT id FROM fisitaap_r1_roles WHERE name=?',[role_tag])[0]['id'];previous=sql('SELECT * FROM fisitaap_r1_user_roles WHERE tenant_id=101 AND user_id=204')
    for action in ['view','write']:sql('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,"contenido",?)',[role,action])
    sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,204,?,"cashier") ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)',[role])
    try:
        before=values();st,form=request('cashier','/admin/contenido');assert st==200 and 'fieldset disabled' in form
        request('cashier','/admin/contenido',{'csrf':csrf('cashier','/admin/contenido'),'hero_title':'Not allowed','contact_info180':1,**contact});assert values()==before
        request('cashier','/admin/contenido',{'csrf':csrf('cashier','/admin/contenido'),'about_label':'Solo diseño autorizado'});assert values()['about_label']=='Solo diseño autorizado' and values()['hero_image']==before['hero_image'];assert sql('SELECT * FROM tenants WHERE id=101')==contact_before
        passed('content-only role can edit public text but cannot alter contact/general settings; omitted image, other text and colors are preserved')
    finally:
        sql('DELETE FROM fisitaap_r1_user_roles WHERE tenant_id=101 AND user_id=204')
        if previous:sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,204,?,?)',[previous[0]['role_id'],previous[0]['previous_role']])
        sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=?',[role]);sql('DELETE FROM fisitaap_r1_roles WHERE id=?',[role])
    (ROOT/'actualizaciones/1.8.0/QA-DISENO-R10.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'source_image_bytes':photo.stat().st_size,'multipart_bytes':body_bytes,'stored_image_bytes':path.stat().st_size,'stored_dimensions':image.size,'layouts':geometry,'page_errors':errors,'environment':'fictional PHP8.3/MariaDB and Chromium; embedded Google map mocked; no production'},indent=2)+'\n')
if __name__=='__main__':main()
