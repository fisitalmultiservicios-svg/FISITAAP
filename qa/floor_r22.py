"""Real desktop graphic floor fits and preserves SVG geometry without page scrolling."""
import sys,json,pathlib
from playwright.sync_api import sync_playwright,expect
from interface_r15 import login,BASE
sys.path.insert(0,'/workspace/fisitaap-env/tests');from r3_helpers import sql
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');saved=sql('SELECT catalog_label FROM tenants WHERE id=101')[0]['catalog_label'];sql('UPDATE tenants SET catalog_label="Menú" WHERE id=101');checks=[]
 try:
  with sync_playwright() as pw:
   b=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);c,p=login(b,'owner@r2.invalid','admin');errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
   for w,h in [(1366,768),(1366,630),(1024,600),(900,520),(768,1024)]:
    p.set_viewport_size({'width':w,'height':h});p.goto(BASE+'/admin/mesas?view=graphic');expect(p.locator('.r22-floor')).to_be_visible();assert p.locator('#r15Viewport').evaluate('e=>e.scrollHeight<=e.clientHeight+1'),(w,h,'floor outer scroll');canvas=p.locator('.r3-floor-canvas');expect(canvas).to_be_in_viewport();assert canvas.evaluate('e=>{const r=e.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight+1&&r.height>=150}'),(w,h,'canvas');svg=canvas.locator('svg');assert svg.get_attribute('viewBox');assert svg.evaluate('e=>e.getAttribute("preserveAspectRatio")!=="none"');p.locator('[data-r15-floor-zoom=in]').click();p.locator('[data-r15-floor-fit]').click();checks.append({'width':w,'height':h,'whole_floor_visible':True,'svg_aspect_preserved':True})
   p.screenshot(path='/workspace/fisitaap-env/r22/salon-768.png');assert not errors,errors;b.close()
 finally:sql('UPDATE tenants SET catalog_label=? WHERE id=101',[saved])
 (ROOT/'actualizaciones/1.8.0/QA-MESAS-R22.json').write_text(json.dumps({'revision':'R22','passed':len(checks),'layouts':checks,'environment':'Real Chromium/PHP/MariaDB, fictional store, no production hosting.'},indent=2)+'\n');print('PASS',len(checks),'graphic floor layouts',flush=True)
if __name__=='__main__':main()
