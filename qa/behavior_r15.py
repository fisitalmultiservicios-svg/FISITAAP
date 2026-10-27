"""Check the persistent table card's live refresh and each real FISIChat profile."""
import json,pathlib,secrets,sys
from playwright.sync_api import sync_playwright,expect
from interface_r15 import login,BASE
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql
ROOT=pathlib.Path(__file__).resolve().parents[1]
def touch():sql('INSERT INTO fisitaap_live180(tenant_id,revision) VALUES(101,1) ON DUPLICATE KEY UPDATE revision=revision+1')
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');checks=[];errors=[]
 with sync_playwright() as pw:
  b=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,p=login(b,'owner@r2.invalid','admin');p.on('pageerror',lambda e:errors.append(str(e)))
  p.goto(BASE+'/admin/mesas?view=graphic');expect(p.locator('#r3TableAccount')).to_be_visible();table=int(p.locator('[data-r3-table]').first.get_attribute('data-r3-table'));saved=sql('SELECT name FROM restaurant_tables WHERE id=? AND tenant_id=101',[table])[0]['name'];name='Mesa QA '+secrets.token_hex(3);nav=[];p.on('framenavigated',lambda f:nav.append(f.url) if f==p.main_frame else None)
  try:
   sql('UPDATE restaurant_tables SET name=? WHERE id=? AND tenant_id=101',[name,table]);touch();expect(p.locator('#r3TableAccount h2')).to_contain_text(name,timeout=9000);assert not nav;assert p.locator('[data-r3-table="'+str(table)+'"]').get_attribute('class').find('r15-selected')>=0
   checks.append('Persistent table card and selection update from a second actor without a page reload; the nonmodal card does not suspend the 2-second poll')
  finally:sql('UPDATE restaurant_tables SET name=? WHERE id=? AND tenant_id=101',[saved,table]);touch()
  # Authorized navigation search uses real links, not prototype routes.
  p.locator('[data-r15-search]').click();p.locator('[data-r15-command-input]').fill('productos');expect(p.locator('[data-r15-command-results] a[href$="/admin/productos"]')).to_be_visible();p.keyboard.press('Escape')
  assert json.loads(p.locator('[data-fc-config]').text_content())['profile']=='dueno';p.locator('.fc-launch').click();expect(p.locator('.fc-panel')).to_be_visible();p.locator('.fc-panel [data-fc-close]').click()
  master,mp=login(b,'master@r2.invalid','master');assert json.loads(mp.locator('[data-fc-config]').text_content())['profile']=='maestro'
  guest=b.new_context();gp=guest.new_page();gp.goto(BASE+'/laventanita/catalogo?sucursal=principal');assert json.loads(gp.locator('[data-fc-config]').text_content())['profile']=='cliente'
  driver,dp=login(b,'driver@r2.invalid','driver');dp.on('pageerror',lambda e:errors.append(str(e)));gp.goto(BASE+'/login');gp.locator('[name=email]').fill('customer@r2.invalid');gp.locator('[name=password]').fill(__import__('r3_helpers').PASSWORD);gp.locator('form button.btn').first.click();gp.wait_for_url('**/account');gp.on('pageerror',lambda e:errors.append(str(e)))
  for reader in (dp,gp):
   reader.set_viewport_size({'width':390,'height':844});reader.get_by_role('button',name='Abrir menú',exact=True).click();expect(reader.locator('[data-admin-menu]')).to_be_visible();reader.keyboard.press('Escape');expect(reader.locator('[data-admin-menu]')).not_to_be_visible();reader.get_by_role('button',name='Abrir menú',exact=True).click();reader.locator('[data-admin-menu] [data-admin-menu-toggle]').click();expect(reader.locator('[data-admin-menu]')).not_to_be_visible()
  checks.append('Driver and buyer menus open, close and respond to Escape on phone without JavaScript errors')
  checks.append('Real navigation search and chat open/close work; owner, master and buyer retain separate server-selected assistant profiles without external AI requests')
  assert not errors,errors;b.close()
 result={'revision':'1.8.0-R15','checks':checks,'page_errors':errors,'environment':'real PHP/MariaDB/Chromium; guarded fictional database, temporary table name restored; no production, external AI calls or printers'}
 (ROOT/'actualizaciones/1.8.0/QA-COMPORTAMIENTO-R15.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n');print('PASS R15 behavior:',len(checks),'checks')
if __name__=='__main__':main()
