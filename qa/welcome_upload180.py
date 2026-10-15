"""R5 false-save reproduction and real PHP multipart upload rejection/replacement on R6."""
import json,pathlib,re,secrets,subprocess,sys
from PIL import Image
from playwright.sync_api import sync_playwright
from access180 import install_payload
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD

ROOT=pathlib.Path(__file__).resolve().parents[1]
STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
FIELD='welcome_image180'

def ensure(actor,email):
    status,body=request(actor,'/owner-login')
    if status==200:
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
        assert request(actor,'/owner-login',{'csrf':token,'email':email,'password':PASSWORD})[0]==302

def current():
    return sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key=?',[FIELD])

def audit_count():
    return sql('SELECT COUNT(*) n FROM audit_logs WHERE action="welcome_image"')[0]['n']

def multipart(actor,path,file,fields=None):
    fields={'csrf':csrf(actor,path),**(fields or {})}
    args=['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','--max-time','45',
          '-b','/tmp/r3-'+actor+'.cookies','-c','/tmp/r3-'+actor+'.cookies']
    for key,value in fields.items():args+=['-F',key+'='+str(value)]
    args+=['-F','image=@/var/www/html/uploads/'+file.name,'-w','\n%{http_code}','http://localhost'+path]
    result=subprocess.run(args,capture_output=True,check=True).stdout
    body,status=result.rsplit(b'\n',1)
    return int(status),body.decode()

def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test'
    previous=current()
    versions=sql('SELECT tenant_id,version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)')
    state=sql('SELECT id,is_active FROM tenants ORDER BY id')
    counts=sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM pos_shifts) shifts')
    public_image=sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key="hero_image"')
    marker=sql('SELECT COALESCE(MAX(id),0) id FROM audit_logs')[0]['id']
    actor='upload_r6_'+secrets.token_hex(4);files=[];checks=[]
    def make_file(label,color,size=None):
        file=STAGE/'uploads'/('qa-upload-r6-'+label+'.png')
        Image.new('RGB',(1600,900),color).save(file)
        if size:
            with file.open('ab') as stream:stream.write(b'\0'*(size-file.stat().st_size))
        file.chmod(0o644);files.append(file);return file
    def rejected(file=None,fields=None,expected='No se cambió tu bienvenida.'):
        before=current();audits=audit_count()
        if file is None:
            status,_=request(actor,'/master/bienvenida',{'csrf':csrf(actor,'/master/bienvenida'),**(fields or {})})
        else:status,_=multipart(actor,'/master/bienvenida',file,fields)
        assert status==302,status
        status,body=request(actor,'/master/bienvenida')
        assert status==200 and expected in body and 'alert-success' not in body,(status,expected)
        assert current()==before and audit_count()==audits
        return body
    try:
        sql('DELETE FROM rate_limits')
        install_payload(ROOT/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R5.zip')
        ensure(actor,'master@r2.invalid')
        good=make_file('navy','navy')
        request(actor,'/master/bienvenida',{'csrf':csrf(actor,'/master/bienvenida')})
        assert 'Bienvenida guardada.' in request(actor,'/master/bienvenida')[1]
        before=current();assert multipart(actor,'/master/bienvenida',good,{'MAX_FILE_SIZE':'1'})[0]==302
        assert 'Bienvenida guardada.' in request(actor,'/master/bienvenida')[1] and current()==before
        checks.append('published R5 falsely confirms an empty submission and an actual PHP-rejected multipart upload without changing the saved image')

        install_payload('/workspace/entregas/FISITAAP-1.8.0-CPANEL-R6.zip')
        sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=101')
        sql('UPDATE fisitaap_tenant_updates180 SET version=180 WHERE tenant_id=102')
        for who,email in [('owner','owner@r2.invalid'),('upload_b','ownerb@r2.invalid')]:ensure(who,email)
        assert 'Cambiar imagen de bienvenida' in request(actor,'/master')[1]
        editor=request(actor,'/master/bienvenida')[1]
        assert 'MAX_FILE_SIZE' in editor and 'portada pública' in editor and 'Ver bienvenida publicada' in editor
        rejected();rejected(good,{'MAX_FILE_SIZE':'1'})
        checks.append('R6 empty selection and real PHP form-size rejection give an error, preserve the previous image and create no success audit')

        assert multipart(actor,'/master/bienvenida',good)[0]==302
        assert 'Bienvenida guardada.' in request(actor,'/master/bienvenida')[1]
        first=current()[0]['value']
        for who,path in [(actor,'/master'),('owner','/admin'),('upload_b','/admin')]:assert first in request(who,path)[1]
        assert 'Imagen de bienvenida actual' in request(actor,'/master/bienvenida')[1]
        checks.append('a valid upload is persisted and displayed in master preview/master welcome and both pending179/active180 businesses')

        large=make_file('app-limit','navy',6*1048576)
        rejected(large)
        ini_large=make_file('php-limit','navy',17*1048576)
        rejected(ini_large,expected='El servidor rechazó la imagen por su tamaño.')
        post_large=make_file('post-limit','navy',25*1048576)
        rejected(post_large,expected='La carga supera el límite del servidor.')
        checks.append('actual 6/17/25MiB multipart requests exercise application5MiB, PHP upload16MiB and POST24MiB limits while preserving the saved image and audit')

        invalid=STAGE/'uploads/qa-upload-r6-invalid.png';invalid.write_text('not an image');invalid.chmod(0o644);files.append(invalid)
        rejected(invalid)
        rejected(good,{'clear':'yes'})
        rejected(good,{'csrf':'bad'},expected='El formulario venció.')
        assert request('owner','/master/bienvenida',{'csrf':csrf('owner','/admin'),'clear':'yes'})[0]==403
        assert first==current()[0]['value']
        checks.append('invalid content, conflicting reset/upload, expired CSRF and owner privilege rejection leave the saved image intact')

        next_file=make_file('green','green')
        assert multipart(actor,'/master/bienvenida',next_file)[0]==302
        second=current()[0]['value'];assert second!=first
        for who,path in [(actor,'/master'),('owner','/admin'),('upload_b','/admin')]:
            body=request(who,path)[1];assert second in body and first not in body
        for suffix in [first,second]:
            relative=suffix.split('/uploads/',1)[1];local=STAGE/'uploads'/relative
            assert local.is_file() and local.stat().st_size<=160*1024
        checks.append('a second upload generates a new optimized media URL shown on all welcomes; old media is retained and public landing configuration is untouched')

        with sync_playwright() as pw:
            browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server'])
            for width in [1366,390]:
                context=browser.new_context(viewport={'width':width,'height':900})
                page=context.new_page();errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
                page.goto('http://localhost:18080/owner-login')
                page.locator('[name=email]').fill('master@r2.invalid');page.locator('[name=password]').fill(PASSWORD)
                page.locator('form button.btn').first.click();page.wait_for_url('**/master')
                image=page.locator('.qa180-welcome>img');assert image.get_attribute('src')==second
                image.evaluate('(e)=>e.decode()')
                assert image.evaluate('(e)=>e.complete&&e.naturalWidth>0&&e.getBoundingClientRect().width<=innerWidth')
                page.get_by_role('link',name='Cambiar imagen de bienvenida').click();page.wait_for_url('**/master/bienvenida')
                assert page.get_by_alt_text('Imagen de bienvenida actual').get_attribute('src')==second
                assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1') and not errors
                context.close()
            browser.close()
        checks.append('Chromium1366/390px loads the replaced image and direct editor link/preview without overflow or JavaScript errors')

        assert request(actor,'/master/bienvenida',{'csrf':csrf(actor,'/master/bienvenida'),'clear':'yes'})[0]==302
        assert 'Se restauró la bienvenida predeterminada.' in request(actor,'/master/bienvenida')[1]
        for who,path in [(actor,'/master'),('owner','/admin'),('upload_b','/admin')]:assert 'Tu negocio, al día.' in request(who,path)[1]
        assert 'Aún no hay una imagen personalizada guardada' in request(actor,'/master/bienvenida')[1]
        assert sql('SELECT id,is_active FROM tenants ORDER BY id')==state
        assert sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM pos_shifts) shifts')==counts
        assert sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key="hero_image"')==public_image
        assert sql('SELECT tenant_id,version FROM fisitaap_tenant_updates180 WHERE tenant_id IN(101,102)')==[{'tenant_id':101,'version':179},{'tenant_id':102,'version':180}]
        checks.append('intentional reset restores the default for all; versions, business activation, sales, shifts and separate public hero image remain unchanged')
        (ROOT/'actualizaciones/1.8.0/QA-CARGA-BIENVENIDA-R6.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'scope':'published R5 and exact R6 payload, real PHP/MariaDB multipart limits, desktop/mobile Chromium; fictitious data only; production cause not independently observed'},indent=2)+'\n')
        print('PASS',len(checks),'welcome upload groups; real PHP upload/post rejections, correct save/replace, preview and mobile')
    finally:
        if previous:sql('UPDATE fisitaap_r2_settings SET value=? WHERE tenant_id=0 AND setting_key=?',[previous[0]['value'],FIELD])
        else:sql('DELETE FROM fisitaap_r2_settings WHERE tenant_id=0 AND setting_key=?',[FIELD])
        for row in versions:sql('UPDATE fisitaap_tenant_updates180 SET version=? WHERE tenant_id=?',[row['version'],row['tenant_id']])
        sql('DELETE FROM audit_logs WHERE id>? AND action="welcome_image"',[marker]);sql('DELETE FROM rate_limits')
        for file in files:
            if file.exists():file.unlink()

if __name__=='__main__':main()
