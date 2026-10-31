"""Real HTTP/MariaDB R20 cash and receipt QA. Uses only guarded fictional stores."""
import concurrent.futures,hashlib,json,pathlib,re,secrets,subprocess,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import sql,request,csrf,PASSWORD
from usability_r7 import login
from runtime_r18 import post,bind,device,open_shift,ticket
ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[]
def passed(s):checks.append(s);print('PASS',s,flush=True)
def setting(value):sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(101,"web_cash_r20",?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[value])
def staff(tag,role='cashier'):
 email=tag.lower()+'@example.invalid';sql('INSERT INTO users(tenant_id,name,email,password_hash,role,is_active,email_verified_at,username_r17) SELECT 101,?,?,password_hash,?,1,NOW(),? FROM users WHERE id=202',[tag,email,role,tag.lower()]);u=sql('SELECT id FROM users WHERE email=?',[email])[0]['id'];login(tag,email);return u

def pay(a,tid,output='none',method='cash'):
 t=sql('SELECT revision,total FROM pos_tickets WHERE id=?',[tid])[0];d={'payment_key':secrets.token_hex(16),f'revisions[{tid}]':t['revision'],'split_mode':'all','parts':2,'cash':t['total'] if method=='cash' else 0,method:t['total'],'seller_id_r18':sql('SELECT opened_by FROM pos_tickets WHERE id=?',[tid])[0]['opened_by'],'print_receipt':int(output!='none'),'receipt_output_r20':output if output!='none' else 'printer'};p='/admin/ventas?screen=checkout&ticket='+str(tid);status,body=post(a,p,d);return d,p,status,body

def close(a,sid,cash):
 d={'action':'close','id':sid,'closing_key':secrets.token_hex(16),'other_cash':cash};post(a,'/admin/turnos',d);assert sql('SELECT status FROM pos_shifts WHERE id=?',[sid])[0]['status']=='closed';return d

def pdf(a,sale):
 args=['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-'+a+'.cookies','http://localhost/admin/comprobante-r20?venta='+str(sale)+'&pdf=1'];r=subprocess.run(args,capture_output=True,check=True);assert r.stdout.startswith(b'%PDF-1.4');return r.stdout

def main():
 assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';sql('DELETE FROM rate_limits');login('r20owner','owner@r2.invalid');login('r20other','ownerb@r2.invalid');tag='R20'+secrets.token_hex(4);a=tag+'a';b=tag+'b';ua=staff(a);ub=staff(b);ed=tag+'editor';ue=staff(ed,'editor');oldtenant=sql('SELECT * FROM tenants WHERE id=101')[0];oldmode=oldtenant['catalog_label'];oldprofiles=sql('SELECT printer_points_json FROM branches WHERE id=301')[0]['printer_points_json'];oldseller=sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="ask_seller_r18"');sql('UPDATE tenants SET catalog_label="Catálogo" WHERE id=101');setting('0')
 sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,tax_rate,min_qty,qty_step,decimals) VALUES(101,401,?,?,?,1000,0,1,1,0)',[tag,tag.lower(),tag]);pid=sql('SELECT id FROM products WHERE sku=?',[tag])[0]['id'];sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(301,?,1)',[pid]);opened=[]
 try:
  assert not open_shift(a);t,_=ticket(a,pid);d,p,s,h=pay(a,t);assert not sql('SELECT id FROM sales WHERE payment_key=?',[d['payment_key']]);assert not sql('SELECT id FROM fisitaap_registers180 WHERE web_user_r20=?',[ua]);assert 'Caja 0' in request(a,'/admin/ventas')[1]
  passed('Installed-only default denies browser opening/collection without manufacturing a cash register; order entry remains available')
  setting('1');assert not open_shift(ed);assert not sql('SELECT id FROM fisitaap_registers180 WHERE web_user_r20=?',[ue]);assert not sql('SELECT id FROM pos_shifts WHERE user_id=? AND status="open"',[ub]);before=sql('SELECT COUNT(*) n FROM fisitaap_registers180')[0]['n'];assert 'Abrir turno' in request(a,'/admin/ventas')[1];assert sql('SELECT COUNT(*) n FROM fisitaap_registers180')[0]['n']==before
  passed('Web mode offers opening without side effects on page loads; staff lacking cashier permission still cannot acquire a box or collect')
  # An administrator may disable web while a first opening is waiting: reread policy after tenant lock.
  code=r'''require "/var/www/html/app/bootstrap.php";if($app->one("SELECT DATABASE() db")["db"]!=="fisitaap_r2_test")exit(2);$app->db->beginTransaction();$app->one("SELECT id FROM tenants WHERE id=101 FOR UPDATE");$app->exec('UPDATE fisitaap_r2_settings SET value="0" WHERE tenant_id=101 AND setting_key="web_cash_r20"');echo "locked\n";flush();sleep(3);$app->db->commit();'''
  holder=subprocess.Popen(['docker','exec','-i','fisitaap-r2-web','php','-r',code],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
  assert holder.stdout.readline().strip()=='locked';blocked=open_shift(a);assert holder.wait(timeout=10)==0;assert blocked is None;setting('1')
  passed('A web opening waiting behind a settings change rereads the policy after its lock and cannot open a new cash shift after web collection is disabled')
  # Same cashier, independent browser sessions: both opening attempts cannot create two live shifts.
  second=tag+'second';email=sql('SELECT email FROM users WHERE id=?',[ua])[0]['email'];login(second,email)
  with concurrent.futures.ThreadPoolExecutor() as pool:out=list(pool.map(open_shift,[a,second]))
  sa=sql('SELECT * FROM pos_shifts WHERE user_id=? AND status="open"',[ua]);assert len(sa)==1;sa=sa[0];opened.append((a,sa['id']));sb=open_shift(b);assert sb;opened.append((b,sb['id']));ra=sql('SELECT * FROM fisitaap_registers180 WHERE id=?',[sa['register_id']])[0];rb=sql('SELECT * FROM fisitaap_registers180 WHERE id=?',[sb['register_id']])[0];assert ra['device_id'] is None and rb['device_id'] is None and ra['number_r18']!=rb['number_r18'] and ra['web_user_r20']==ua and rb['web_user_r20']==ub
  assert len(sql('SELECT id FROM fisitaap_registers180 WHERE web_user_r20=?',[ua]))==1
  passed('Concurrent browser opening allocates only one own-user shift/register; a second cashier gets a different sequential number and separate funds')
  # A no-shift cashier cannot adopt the other user's session by passing a register or user id.
  third=tag+'third';ut=staff(third);tt,_=ticket(third,pid);bad,path,_,denied=pay(third,tt);assert 'Abre tu propio turno' in denied;post(third,path,{**bad,'user_id':ua,'register_id':ra['id']});assert not sql('SELECT id FROM sales WHERE payment_key=?',[bad['payment_key']]);post(b,'/admin/turnos',{'action':'close','id':sa['id'],'closing_key':secrets.token_hex(16),'other_cash':100});assert sql('SELECT status FROM pos_shifts WHERE id=?',[sa['id']])[0]['status']=='open'
  passed('Another user without a shift can sell but not collect; posted register/user IDs cannot borrow another cashier’s authority or close their turn')
  # Real general-settings form cannot disable while any web shift remains open.
  tenant=sql('SELECT * FROM tenants WHERE id=101')[0];config={k:tenant[k] or '' for k in ['name','email','phone','whatsapp','address','catalog_label','offline_message','tax_id']};config.update({'more_settings180':1,'cash_access_r20':'installed','payment_methods[]':['Efectivo'],'delivery_types[]':['pickup']});post('r20owner','/admin/configuracion',config);assert sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="web_cash_r20"')[0]['value']=='1'
  passed('General-settings switch is permission checked and refuses to strand open browser turns when disabling web collection')
  sql('UPDATE branches SET printer_points_json=? WHERE id=301',[json.dumps({'receipt':{'type':'browser','width':80}})])
  d,p,_,_=pay(a,t);sale=sql('SELECT * FROM sales WHERE payment_key=?',[d['payment_key']])[0];assert sale['receipt_output_r20']=='none';assert not sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key=?',['sale-'+str(sale['id'])]);post(a,p,{**d,'print_receipt':1,'receipt_output_r20':'letter_pdf'});assert len(sql('SELECT id FROM sales WHERE payment_key=?',[d['payment_key']]))==1;assert not sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key=?',['sale-'+str(sale['id'])]);assert sql('SELECT receipt_output_r20 FROM sales WHERE id=?',[sale['id']])[0]['receipt_output_r20']=='none'
  passed('Solo cobrar commits the sale without receipt jobs; replay with a changed print preference cannot charge twice, create a job or alter original receipt intent')
  output_sales={}
  for output in ['letter_pdf','receipt_pdf']:
   tt,_=ticket(a,pid);dd,pp,_,_=pay(a,tt,output);ss=sql('SELECT * FROM sales WHERE payment_key=?',[dd['payment_key']])[0];output_sales[output]=ss['id'];data=pdf(a,ss['id']);assert b'/MediaBox [0 0 612 792]' in data if output=='letter_pdf' else b'/MediaBox [0 0 226.7717' in data;assert b'CRC ' in data and tag.encode() in data;assert not sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key=?',['sale-'+str(ss['id'])]);post(a,pp,dd);assert len(sql('SELECT id FROM sales WHERE payment_key=?',[dd['payment_key']]))==1
   target=pathlib.Path('/workspace/fisitaap-env/r20');target.mkdir(exist_ok=True);(target/(output+'.pdf')).write_bytes(data)
  assert 'admin/comprobante-r20?venta='+str(output_sales['letter_pdf']) in request(a,'/admin/ventas?history=1')[1]
  assert request('r20other','/admin/comprobante-r20?venta='+str(output_sales['letter_pdf'])+'&pdf=1')[0]==404
  passed('Carta and thermal PDFs are real authenticated PDFs with correct page geometry, sale amounts and multi-use download without physical jobs; another store cannot access them')
  # Real printer config accepts letter only for Windows/PDF receipt; queue snapshots width and configured device.
  tok,did,rid=device(tag+'printer',700000+secrets.randbelow(10000));config={'printer_points[receipt][type]':'usb','printer_points[receipt][name]':'QA Carta','printer_points[receipt][width]':216,'printer_points[receipt][device_id]':did,'printer_points[copy][type]':'browser','printer_points[copy][width]':80};post('r20owner','/admin/impresion',config);profiles=json.loads(sql('SELECT printer_points_json FROM branches WHERE id=301')[0]['printer_points_json']);assert profiles['receipt']['width']==216
  tt,_=ticket(a,pid);dd,pp,_,_=pay(a,tt,'printer','card');ss=sql('SELECT * FROM sales WHERE payment_key=?',[dd['payment_key']])[0];jobs=sql('SELECT * FROM fisitaap_print_jobs180 WHERE operation_key=?',['sale-'+str(ss['id'])]);assert len(jobs)==1 and jobs[0]['device_id']==did;payload=json.loads(jobs[0]['payload']);assert payload['width']==216 and payload['printer']['name']=='QA Carta' and not payload['drawer'];post(a,pp,dd);assert len(sql('SELECT id FROM fisitaap_print_jobs180 WHERE operation_key=?',['sale-'+str(ss['id'])]))==1
  # Second box has own non-cash ledger, not this first cashier's balance.
  tt,_=ticket(b,pid);dd,_,_,_=pay(b,tt,'none','sinpe');ss2=sql('SELECT id FROM sales WHERE payment_key=?',[dd['payment_key']])[0]['id'];assert sql('SELECT a.register_id_r18 FROM fisitaap_money_entries180 e JOIN fisitaap_money_accounts180 a ON a.id=e.account_id WHERE e.entity_type="sale" AND e.entity_id=?',[ss['id']])[0]['register_id_r18']==ra['id'];assert sql('SELECT a.register_id_r18 FROM fisitaap_money_entries180 e JOIN fisitaap_money_accounts180 a ON a.id=e.account_id WHERE e.entity_type="sale" AND e.entity_id=?',[ss2])[0]['register_id_r18']==rb['id']
  passed('Configured carta Windows printer is queued exactly once with carta dimensions; card/SINPE receipts accrue in each web register’s own ledger')
  post('r20owner','/admin/impresion',{**config,'printer_points[receipt][type]':'network','printer_points[receipt][host]':'192.168.1.50'});assert json.loads(sql('SELECT printer_points_json FROM branches WHERE id=301')[0]['printer_points_json'])['receipt']['type']=='usb'
  passed('Printer settings reject carta sent as ESC/POS thermal bytes instead of silently producing an incorrectly sized receipt')
  # Existing native register/zero rules take priority even while browser collection is enabled.
  native=tag+'native';un=staff(native);zero,zd,zr=device(tag+'zero',0);grant=bind(native,un,zero);assert not grant['cash'];assert not open_shift(native);assert not sql('SELECT id FROM fisitaap_registers180 WHERE web_user_r20=?',[un]);ntok,nd,nr=device(tag+'nativepos',750000+secrets.randbelow(10000));assert bind(native,un,ntok)['cash'];sn=open_shift(native);assert sn;opened.append((native,sn['id']));assert sn['register_id']==nr;close(native,sn['id'],100);opened.remove((native,sn['id']))
  passed('Enabling browser cash does not upgrade a signed native Caja0 grant, alter a paired register or bypass the actual installed-cashier rules')
  close(b,sb['id'],100);opened.remove((b,sb['id']));dclose=close(a,sa['id'],3100);opened.remove((a,sa['id']));post(a,'/admin/turnos',dclose);assert sql('SELECT status,expected_cash FROM pos_shifts WHERE id=?',[sa['id']])[0]=={'status':'closed','expected_cash':'3100.00'}
  assert 'Solo atención' in request(a,'/admin/ventas')[1];new=open_shift(a,0);assert new['register_id']==ra['id'];opened.append((a,new['id']));close(a,new['id'],0);opened.remove((a,new['id']))
  post('r20owner','/admin/configuracion',config if False else {**{k:tenant[k] or '' for k in ['name','email','phone','whatsapp','address','catalog_label','offline_message','tax_id']},'more_settings180':1,'cash_access_r20':'installed','payment_methods[]':['Efectivo'],'delivery_types[]':['pickup']});assert sql('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="web_cash_r20"')[0]['value']=='0'
  passed('Only opener closes with reconciled cash; close replay is safe, next own shift reuses its funds/register, and browser switches back to attention-only after closing or disabling')
 finally:
  for who,sid in opened:
   sql('UPDATE pos_tickets SET status="void" WHERE shift_id=? AND status NOT IN("paid","void","merged")',[sid]);close(who,sid,0)
  setting('0');sql('UPDATE tenants SET catalog_label=? WHERE id=101',[oldmode]);sql('UPDATE branches SET printer_points_json=? WHERE id=301',[oldprofiles])
  fields=['business_hours','map_embed','tax_id_required','facebook_url','instagram_url','tiktok_url','payment_methods','delivery_types','tax_included'];sql('UPDATE tenants SET '+','.join(k+'=?' for k in fields)+' WHERE id=101',[oldtenant[k] for k in fields]);sql('DELETE FROM fisitaap_r2_settings WHERE tenant_id=101 AND setting_key="ask_seller_r18"')
  if oldseller:sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(101,"ask_seller_r18",?)',[oldseller[0]['value']])
 out={'revision':'1.8.0-R21','passed':len(checks),'checks':checks,'environment':'Actual HTTP/CSRF/PHP8.3/MariaDB fictional fixtures, native proof transport simulated. No physical printer, bank or live hosting.'};(ROOT/'actualizaciones/1.8.0/QA-COBRO-R21.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
