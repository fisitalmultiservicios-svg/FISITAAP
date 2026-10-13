"""Coupon admin, true server preview and once-per-account partial settlement."""
import hashlib,json,pathlib,re,secrets,subprocess,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from financial180 import api
checks=[]
def post(path,data):return request('owner',path,{'csrf':csrf('owner','/admin/turnos'),**data})
def passed(x):checks.append(x);print('PASS',x,flush=True)
def preview(code,items,subtotal=1):
 payload={'tenant':'laventanita','branch_id':301,'coupon':code,'items':items,'subtotal':subtotal,'csrf':csrf('owner','/admin/turnos')}
 p=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-owner.cookies','-H','Content-Type: application/json','--data-binary','@-','http://localhost/api/coupon-preview'],input=json.dumps(payload).encode(),capture_output=True,check=True);return json.loads(p.stdout)
def main():
 code='LIMIT-'+secrets.token_hex(3).upper();data={'code':code,'type':'percent','value':10,'min_order':500,'max_uses':1,'is_active':1,'products[]':501};assert post('/admin/cupones',data)[0]==302;coupon=sql('SELECT * FROM coupons WHERE code=?',[code])[0];assert json.loads(coupon['applicable_products'])==[501]
 out=preview(code,[{'id':501,'qty':1,'option_ids':[]}],subtotal=999999999);assert out['valid'] and out['discount']==100,out
 passed('the preview derives the eligible discount from real prices and ignores a forged browser subtotal')
 out=preview(code,[{'id':502,'qty':1,'option_ids':[]}]);assert not out['valid'];bad=code+'-BAD';post('/admin/cupones',{**data,'code':bad,'products[]':502});assert not sql('SELECT id FROM coupons WHERE code=?',[bad])
 passed('foreign business products cannot be used for coupon configuration or preview')
 token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"COUPON QA",?)',[digest]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"COUPON BOX",?,202)',[did]);_,body=request('owner','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token);assert post('/api/native/bind',{'proof':proof['proof']})[0]==200
 opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':0});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening])[0]['id'];key=secrets.token_hex(16);post('/admin/ventas',{'action':'quick','start_product':1,'product_id':501,'quantity':2,'open_key':key});ticket=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];item=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket])[0]['id']
 rev=lambda:sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]['revision'];post('/admin/ventas?screen=order&ticket='+str(ticket),{'action':'coupon','revision':rev(),'coupon_code':code});row=sql('SELECT coupon_id,discount_total,total FROM pos_tickets WHERE id=?',[ticket])[0];assert row['coupon_id']==coupon['id'] and row['discount_total']=='200.00' and row['total']=='2060.00',row;assert sql('SELECT uses_count FROM coupons WHERE id=?',[coupon['id']])[0]['uses_count']==0
 passed('POS applies the eligible server-calculated discount without consuming a use before payment')
 paidkey=secrets.token_hex(16);pay={'payment_key':paidkey,'revisions['+str(ticket)+']':rev(),'split_mode':'items','qty['+str(item)+']':1,'parts':2,'cash':1030};post('/admin/cobro?ticket='+str(ticket),pay);assert len(sql('SELECT id FROM sales WHERE payment_key=?',[paidkey]))==1;assert sql('SELECT uses_count FROM coupons WHERE id=?',[coupon['id']])[0]['uses_count']==1;post('/admin/cobro?ticket='+str(ticket),pay);assert sql('SELECT uses_count FROM coupons WHERE id=?',[coupon['id']])[0]['uses_count']==1
 # Finishing the already partially paid account remains valid after deactivation or exhausting the last use.
 sql('UPDATE coupons SET is_active=0 WHERE id=?',[coupon['id']]);pay={'payment_key':secrets.token_hex(16),'revisions['+str(ticket)+']':rev(),'split_mode':'all','parts':2,'cash':1030};post('/admin/cobro?ticket='+str(ticket),pay);assert sql('SELECT status FROM pos_tickets WHERE id=?',[ticket])[0]['status']=='paid';assert sql('SELECT uses_count FROM coupons WHERE id=?',[coupon['id']])[0]['uses_count']==1;assert len(sql('SELECT id FROM fisitaap_coupon_uses180 WHERE coupon_id=?',[coupon['id']]))==1
 passed('two partial payments and a retry consume one use; the remaining agreed balance survives coupon expiry/deactivation')
 assert not preview(code,[{'id':501,'qty':1,'option_ids':[]}])['valid']
 passed('new carts cannot reuse an exhausted or deactivated coupon')
 post('/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':2060});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed'
 # An eligible coupon must not spill onto an excluded item during partial settlement.
 mixedcode='ONLY-'+secrets.token_hex(3).upper();post('/admin/cupones',{'code':mixedcode,'type':'fixed','value':100,'min_order':0,'is_active':1,'products[]':501})
 sql('INSERT INTO products(tenant_id,category_id,name,slug,price,tax_rate,status) SELECT 101,category_id,?,?,200,0,"active" FROM products WHERE id=501',['Excluded '+mixedcode,mixedcode.lower()]);excluded=sql('SELECT MAX(id) id FROM products WHERE tenant_id=101')[0]['id']
 opening2=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening2,'opening_cash':0});shift2=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening2])[0]['id'];key2=secrets.token_hex(16);post('/admin/ventas',{'action':'quick','start_product':1,'product_id':501,'quantity':1,'open_key':key2});tid=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key2])[0]['id'];revision=lambda:sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision']
 post('/admin/ventas?screen=order&ticket='+str(tid),{'action':'add_item','product_id':excluded,'quantity':1,'revision':revision()});post('/admin/ventas?screen=order&ticket='+str(tid),{'action':'coupon','coupon_code':mixedcode,'revision':revision()});iid=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND product_id=?',[tid,excluded])[0]['id'];paykey=secrets.token_hex(16);post('/admin/cobro?ticket='+str(tid),{'payment_key':paykey,'revisions['+str(tid)+']':revision(),'split_mode':'items','parts':2,'qty['+str(iid)+']':1,'cash':200});assert sql('SELECT discount_total,total FROM sales WHERE payment_key=?',[paykey])[0]=={'discount_total':'0.00','total':'200.00'}
 paykey=secrets.token_hex(16);post('/admin/cobro?ticket='+str(tid),{'payment_key':paykey,'revisions['+str(tid)+']':revision(),'split_mode':'all','parts':2,'cash':1030});assert sql('SELECT discount_total,total FROM sales WHERE payment_key=?',[paykey])[0]=={'discount_total':'100.00','total':'1030.00'};assert sql('SELECT uses_count FROM coupons WHERE code=?',[mixedcode])[0]['uses_count']==1
 post('/admin/turnos',{'action':'close','id':shift2,'closing_key':secrets.token_hex(16),'other_cash':1230});assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift2])[0]['status']=='closed'
 passed('partial payment of an excluded article receives no coupon discount; the entire discount stays on the eligible item')
 # A real browser customer checkout must use the same scoped calculation and consume once.
 sql('UPDATE users SET phone="88888888" WHERE id=205')
 page=subprocess.run(['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','-c','/tmp/r3-buyer.cookies','http://localhost/login'],capture_output=True,check=True).stdout.decode();login_csrf=re.search(r'name="csrf" value="([a-f0-9]+)"',page)[1]
 import urllib.parse
 subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-buyer.cookies','-c','/tmp/r3-buyer.cookies','--data-binary','@-','http://localhost/login'],input=urllib.parse.urlencode({'csrf':login_csrf,'email':'customer@r2.invalid','password':PASSWORD}).encode(),capture_output=True,check=True)
 webcode='WEB-'+secrets.token_hex(3).upper();post('/admin/cupones',{'code':webcode,'type':'fixed','value':100,'min_order':500,'max_uses':1,'is_active':1,'products[]':501})
 payload={'tenant':'laventanita','branch_id':301,'delivery_type':'pickup','payment':'Efectivo','coupon':webcode,'checkout_key':secrets.token_hex(16),'items':[{'id':501,'qty':1,'option_ids':[]}],'csrf':csrf('buyer','/account')}
 def checkout(raw):
  process=subprocess.run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-buyer.cookies','-H','Content-Type: application/json','--data-binary','@-','http://localhost/api/checkout'],input=json.dumps(raw).encode(),capture_output=True,check=True);return json.loads(process.stdout)
 first=checkout(payload);assert first['ok'],first;order=first['order_id'];again=checkout(payload);assert again['ok'] and again['duplicate'] and again['order_id']==order
 c=sql('SELECT id,uses_count FROM coupons WHERE code=?',[webcode])[0];assert c['uses_count']==1 and len(sql('SELECT id FROM fisitaap_coupon_uses180 WHERE coupon_id=? AND order_id=?',[c['id'],order]))==1
 assert sql('SELECT discount_total,total FROM orders WHERE id=?',[order])[0]=={'discount_total':'100.00','total':'1030.00'}
 passed('a real customer web checkout records the eligible discount and its use once across retries')
 failed=checkout({**payload,'checkout_key':secrets.token_hex(16)});assert not failed['ok'] and sql('SELECT uses_count FROM coupons WHERE id=?',[c['id']])[0]['uses_count']==1
 passed('a new web order cannot take the already exhausted last coupon use')
 pathlib.Path('actualizaciones/1.8.0/QA-CUPONES.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated PHP/MariaDB HTTP; no production'},indent=2)+'\n')
if __name__=='__main__':main()
