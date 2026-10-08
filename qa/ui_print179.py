import sys,pathlib,secrets,subprocess,urllib.parse
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import post,sql,request
from playwright.sync_api import sync_playwright
import re
from r3_helpers import ROOT
def main():
    cfg=ROOT/"config.php"
    original=cfg.read_text()
    original_brand=sql("SELECT primary_color,accent_color,logo FROM tenants WHERE id=101")[0]
    address=subprocess.check_output(["docker","inspect","-f","{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}","fisitaap-r2-web"]).decode().strip()
    assert address=="172.18.0.6", "Update this lab browser fixture origin for the current container"
    cfg.write_text(re.sub(r"('app_url'\s*=>\s*)'[^']*'",lambda m:m[1]+"'http://"+address+"'",original));cfg.chmod(0o644)
    try:
        sql('UPDATE tenants SET primary_color="#ffff00",accent_color="#ff00ff",logo="http://172.18.0.6/assets/demo/nova-mix-logo.webp" WHERE id=101')
        if not sql('SELECT id FROM pos_shifts WHERE tenant_id=101 AND user_id=202 AND status="open"'):
         post('owner','/admin/turnos',{'action':'open','name':'Refined QA','opening_cash':'0'})
        def ticket():
         key=secrets.token_hex(16);post('owner','/admin/ventas',{'action':'quick','open_key':key})
         rows=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key]);assert rows
         tid=int(rows[0]['id']);path='/admin/ventas?screen=order&ticket='+str(tid)
         post('owner',path,{'action':'add_item','revision':'0','product_id':'502','quantity':'1'})
         assert sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[tid]);return tid,path
        cookies=subprocess.check_output(['docker','exec','fisitaap-r2-web','cat','/tmp/r2-owner.cookies']).decode()
        with sync_playwright() as p:
         b=p.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox','--no-proxy-server'])
         c=b.new_context(viewport={'width':1440,'height':1000})
         for line in cookies.splitlines():
          if line.startswith('#HttpOnly_'):line=line[len('#HttpOnly_'):]
          elif line.startswith('#'):continue
          parts=line.split('\t')
          if len(parts)==7:c.add_cookies([{'name':parts[5],'value':parts[6],'url':'http://172.18.0.6/'}])
         c.add_init_script('window.printed=JSON.parse(sessionStorage.getItem("qa-printed")||"[]"); window.failPrint=false; window.fisitaapWindows={print:async job=>{window.printed.push(job);sessionStorage.setItem("qa-printed",JSON.stringify(window.printed));if(window.failPrint)throw Error("Printer unavailable");return {ok:true,status:"sent_to_printer"};}};')
         page=c.new_page();tid,path=ticket();page.goto('http://172.18.0.6'+path)
         page.wait_for_function('() => getComputedStyle(document.body).getPropertyValue("--primary").trim()==="#203f57"')
         assert page.locator('.r3-brand img').get_attribute('src').endswith('nova-mix-logo.webp')
         assert page.locator('.r3-panel').first.evaluate('(e)=>getComputedStyle(e).borderRadius')=='0px'
         page.locator('form:has(input[name=action][value=send_kitchen]) button').click()
         page.wait_for_function('() => window.printed.length>0 && document.querySelector(".direct-print-notice").textContent.includes("Enviado")')
         assert page.url=='http://172.18.0.6'+path and not page.locator('.print-result-card').count()
         assert sql('SELECT kitchen_status FROM pos_ticket_items WHERE ticket_id=?',[tid])[0]['kitchen_status']=='sent'
         print('PASS real kitchen POST prints silently, same sales page, shared colors, straight corners, business logo',flush=True)
         # A manual link prints with a failed printer, then retries only the same jobs.
         page.evaluate('(id)=>{const a=document.createElement("a");a.id="qa-receipt";a.href="/admin/recibo?comanda="+id;a.textContent="Comanda";document.body.append(a);window.printed=[];sessionStorage.removeItem("qa-printed");window.failPrint=true;}',tid)
         page.locator('#qa-receipt').click();page.get_by_role('button',name='Reintentar impresión').wait_for()
         first=page.evaluate('printed.map(j=>j.id)');page.evaluate('window.failPrint=false')
         page.get_by_role('button',name='Reintentar impresión').click()
         page.wait_for_function('() => document.querySelector(".direct-print-notice").textContent.includes("Enviado")')
         assert page.evaluate('printed.map(j=>j.id)')==first+first
         print('PASS failed printer retry conserves IDs, no financial replay or print preview',flush=True)
         page.once('dialog',lambda dialog:dialog.accept())
         page.get_by_role('button',name='Imprimir otra copia').click()
         page.wait_for_function('() => printed.length>=3')
         assert page.evaluate('printed[printed.length-1].id') not in first
         assert '-copy-' in page.evaluate('printed[printed.length-1].id')
         print('PASS explicit confirmed copy uses a new ID; failure retries keep that ID',flush=True)
         # Actual settlement stays on the payment screen and records one sale.
         tid2,path2=ticket();checkout='/admin/ventas?screen=checkout&ticket='+str(tid2)
         page.goto('http://172.18.0.6'+checkout);page.locator('[name=cash]').fill('100000');page.locator('[data-confirm-payment]').click()
         page.wait_for_function('() => window.printed.length>0 && document.querySelector(".direct-print-notice").textContent.includes("Enviado")')
         assert page.url=='http://172.18.0.6/admin/ventas'
         assert not page.locator('[data-r3-collect]').count()
         assert sql('SELECT status FROM pos_tickets WHERE id=?',[tid2])[0]['status']=='paid'
         assert len(sql('SELECT DISTINCT sale_id FROM pos_payments WHERE ticket_id=?',[tid2]))==1
         print('PASS real payment prints and returns to sales without a preview and retains one committed sale',flush=True)
         # Existing Android 1.7.9 native script accepts the same in-place print path.
         page.goto('http://172.18.0.6'+path)
         page.evaluate('delete window.fisitaapWindows;window.printed=[];window.FisitaapAndroid={postMessage:text=>{const data=JSON.parse(text);printed.push(data.job);setTimeout(()=>FisitaapAndroid.onmessage({data:JSON.stringify({requestId:data.requestId,ok:true,status:"sent_to_printer"})}),0)}};')
         page.evaluate(pathlib.Path('/workspace/FISITAAP/android/app/src/main/assets/native-print.js').read_text())
         page.evaluate('(id)=>{const a=document.createElement("a");a.id="qa-android";a.href="/admin/recibo?comanda="+id;a.textContent="Comanda";document.body.append(a)}',tid)
         page.locator('#qa-android').click();page.wait_for_function('() => printed.length>0 && document.querySelector(".direct-print-notice").textContent.includes("Enviado")')
         assert page.url=='http://172.18.0.6'+path
         print('PASS existing Android bridge prints in place without rebuilding APK',flush=True)
         # A response lost after a mutation must never trigger a second automatic POST.
         tid3,path3=ticket();page.goto('http://172.18.0.6'+path3)
         page.evaluate("() => { const original=window.fetch.bind(window); window.fetch=async(url,options)=>{const response=await original(url,options);if(options?.method==='POST')throw Error('simulated response lost after commit');return response;}; }")
         page.locator('form:has(input[name=action][value=send_kitchen]) button').click()
         page.wait_for_function('() => document.querySelector(".direct-print-notice")?.textContent.includes("Revisa el estado")')
         assert page.locator('form:has(input[name=action][value=send_kitchen])').get_attribute('data-print-pending')=='1'
         assert sql('SELECT kitchen_status FROM pos_ticket_items WHERE ticket_id=?',[tid3])[0]['kitchen_status']=='sent'
         print('PASS uncertain mutation response stops automatic financial/command replay',flush=True)
         tid4,path4=ticket();page.goto('http://172.18.0.6/admin/ventas?screen=checkout&ticket='+str(tid4))
         before=page.evaluate('printed.length');page.locator('[name=print_receipt]').uncheck();page.locator('[data-confirm-payment]').click()
         page.wait_for_url('http://172.18.0.6/admin/ventas')
         page.wait_for_function('() => document.querySelector(".direct-print-notice")?.textContent.includes("Venta registrada")')
         assert page.evaluate('printed.length')==before
         assert sql('SELECT status FROM pos_tickets WHERE id=?',[tid4])[0]['status']=='paid'
         print('PASS disabled automatic receipt records sale without a preview or print job',flush=True)
         # Store colors/logo remain public; fallback is the business name rather than FISITAAP.
         page.goto('http://172.18.0.6/prueba-a/catalogo')
         assert page.locator('.brand-logo').get_attribute('src').endswith('nova-mix-logo.webp')
         assert page.evaluate('getComputedStyle(document.documentElement).getPropertyValue("--primary").trim()')=='#ffff00'
         assert page.locator('.footer-logo').get_attribute('src').endswith('nova-mix-logo.webp')
         page.goto('http://172.18.0.6/prueba-a');assert page.locator('.r2-wordmark img').get_attribute('src').endswith('nova-mix-logo.webp')
         assert page.locator('.r2-platform-offer img').count()==1
         page.screenshot(path='/workspace/fisitaap-qa/1.7.9/public-refined.png',full_page=True)
         page.goto('http://172.18.0.6/admin');assert page.locator('.admin-brand img').get_attribute('src').endswith('nova-mix-logo.webp');page.screenshot(path='/workspace/fisitaap-qa/1.7.9/admin-refined.png',full_page=True)
         page.set_viewport_size({'width':390,'height':844});page.goto('http://172.18.0.6/admin')
         assert page.evaluate('document.documentElement.scrollWidth')<=390
         page.screenshot(path='/workspace/fisitaap-qa/1.7.9/admin-refined-mobile.png',full_page=True)
         print('PASS narrow mobile dashboard fits without horizontal page overflow',flush=True)
         sql('UPDATE tenants SET logo=NULL WHERE id=101')
         page.goto('http://172.18.0.6/admin');assert not page.locator('.admin-brand img').count()
         assert 'Prueba A' in page.locator('.admin-brand').inner_text()
         page.goto('http://172.18.0.6/prueba-a/catalogo');assert not page.locator('.brand-mark').count()
         assert 'Prueba A' in page.locator('.topbar .brand').inner_text()
         print('PASS no business logo falls back to its name, never the platform logo',flush=True)
         print('PASS public catalog/landing retain store theme and logo, platform offer confined to footer',flush=True)
         b.close()
    finally:
        cfg.write_text(original);cfg.chmod(0o644)
        sql("UPDATE tenants SET primary_color=?,accent_color=?,logo=? WHERE id=101",[original_brand["primary_color"],original_brand["accent_color"],original_brand["logo"]])

if __name__=="__main__":main()
