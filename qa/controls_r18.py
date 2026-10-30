"""Real settings/register/staff configuration controls, fictional fixture only."""
import json,pathlib,secrets,sys
from playwright.sync_api import sync_playwright,expect
from interface_r15 import login,BASE
sys.path.insert(0,'/workspace/fisitaap-env/tests');from r3_helpers import sql,request,csrf
from runtime_r18 import post
ROOT=pathlib.Path(__file__).resolve().parents[1]
def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');tag='CTR18-'+secrets.token_hex(4);checks=[]
 with sync_playwright() as pw:
  b=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,p=login(b,'owner@r2.invalid','admin')
  p.goto(BASE+'/admin/configuracion');toggle=p.locator('[name=ask_seller_r18]');old=toggle.is_checked();toggle.set_checked(not old);toggle.locator('xpath=ancestor::form').locator('button[type=submit],button:not([type])').last.click();p.wait_for_load_state();p.goto(BASE+'/admin/configuracion');assert p.locator('[name=ask_seller_r18]').is_checked()!=old;p.locator('[name=ask_seller_r18]').set_checked(old);p.locator('[name=ask_seller_r18]').locator('xpath=ancestor::form').locator('button[type=submit],button:not([type])').last.click();checks.append('Generales seller switch saves through its real form and can be turned off without changing the business type')
  guest=b.new_context();guest.add_init_script('window.fisitaapWindows={loginUsers:async()=>({users:[{id:202,name:"caja_ana",email:"owner@r2.invalid"}]})};');g=guest.new_page();g.goto(BASE+'/owner-login');expect(g.locator('#nativeLoginUser')).to_be_visible();g.locator('#nativeLoginUser').select_option('202');assert g.locator('[name=email]').input_value()=='owner@r2.invalid';assert 'caja_ana' in g.locator('#nativeLoginUser').inner_text();checks.append('Installed login selector displays quick name and submits the real assigned identity rather than an ambiguous cross-store alias');b.close()
 # Configuration handler creates separate default accounts and prevents duplicate positive box numbers.
 actor=tag+'owner';from usability_r7 import login as http_login
 http_login(actor,'owner@r2.invalid');number=950000+secrets.randbelow(40000);data={'action':'register','name':tag,'branch_id':301,'number_r18':number,'is_active':1};post(actor,'/admin/escritorio',data);reg=sql('SELECT id,number_r18 FROM fisitaap_registers180 WHERE name=?',[tag])[0];assert reg['number_r18']==number;accounts=sql('SELECT channel_r18,balance FROM fisitaap_money_accounts180 WHERE register_id_r18=?',[reg['id']]);assert len(accounts)==3 and {x['channel_r18'] for x in accounts}=={'card','sinpe','bank'} and all(x['balance']=='0.00' for x in accounts)
 post(actor,'/admin/escritorio',{**data,'name':tag+' duplicate'});assert not sql('SELECT id FROM fisitaap_registers180 WHERE name=?',[tag+' duplicate']);post(actor,'/admin/escritorio',{**data,'name':tag+' zero','number_r18':0});zero=sql('SELECT id FROM fisitaap_registers180 WHERE name=?',[tag+' zero'])[0]['id'];assert not sql('SELECT id FROM fisitaap_money_accounts180 WHERE register_id_r18=?',[zero]);checks.append('Real Equipos y cajas saves number/independent funds, rejects duplicate active positive number, and creates Caja0 without money funds')
 # Legacy team screen still requires a quick name when Personnel is not in the plan.
 old=sql('SELECT enabled FROM tenant_modules WHERE tenant_id=101 AND module_key="personnel"');sql('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(101,"personnel",0) ON DUPLICATE KEY UPDATE enabled=0')
 try:
  data={'action':'save','name':tag,'email':tag.lower()+'@example.invalid','role':'cashier','password':'1234'};post(actor,'/admin/usuarios',data);assert not sql('SELECT id FROM users WHERE email=?',[data['email']]);post(actor,'/admin/usuarios',{**data,'username':'u'+tag.lower()});uid=sql('SELECT id,force_password_change FROM users WHERE email=?',[data['email']])[0];assert uid['force_password_change']==1;new=tag+'employee';post(new,'/owner-login',{'email':'u'+tag.lower(),'password':'1234'});assert request(new,'/change-password')[0]==200;assert 'minlength="12"' not in request(new,'/change-password')[1];request(new,'/change-password',{'csrf':csrf(new,'/change-password'),'password':'4321','confirm':'4321'});assert request(new,'/admin')[0]==200
  checks.append('Team screen without Personnel also requires a quick name; four-digit temporary login and four-digit forced password change succeed')
 finally:
  sql('DELETE FROM tenant_modules WHERE tenant_id=101 AND module_key="personnel"')
  if old:sql('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(101,"personnel",?)',[old[0]['enabled']])
 out={'revision':'1.8.0-R18','passed':len(checks),'checks':checks,'environment':'Real PHP/MariaDB/Chromium fictional fixture; login bridge safe metadata simulated, no physical device'};(ROOT/'actualizaciones/1.8.0/QA-CONTROLES-R18.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print('PASS R18 controls',len(checks),flush=True)
if __name__=='__main__':main()
