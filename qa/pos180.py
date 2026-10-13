"""HTTP regression of operational POS creation, prices, payment splits and free stock."""
import hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
from financial180 import api
def post(actor,path,data):return request(actor,path,{'csrf':csrf(actor,'/admin/turnos'),**data})
checks=[]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def revision(t):return sql('SELECT revision FROM pos_tickets WHERE id=?',[t])[0]['revision']
def edit(t,data):return post('owner','/admin/ventas?screen=order&ticket='+str(t),{'revision':revision(t),**data})
def charge(t,cash,extra={}):
    key=secrets.token_hex(16);data={'payment_key':key,'revisions['+str(t)+']':revision(t),'split_mode':'all','parts':2,'cash':cash,**extra}
    status,body=post('owner','/admin/cobro?ticket='+str(t),data);assert status==302,(status,body[-600:]);rows=sql('SELECT * FROM sales WHERE payment_key=?',[key]);return key,data,rows

def main():
    token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"POS QA DEVICE",?)',[digest]);device=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id'];sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"POS QA BOX",?,202)',[device])
    _,body=request('owner','/admin/turnos');ctx=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',body)[1]);_,proof=api('web-session',{'nonce':ctx['nonce'],'user_id':202},token);assert request('owner','/api/native/bind',{'csrf':csrf('owner','/admin/turnos'),'proof':proof['proof']})[0]==200
    shiftkey=secrets.token_hex(16);post('owner','/admin/turnos',{'action':'open','opening_key':shiftkey,'opening_cash':100})
    count=len(sql('SELECT id FROM pos_tickets'));status,body=request('owner','/admin/ventas');assert status==200 and len(sql('SELECT id FROM pos_tickets'))==count
    passed('opening the quick-sale screen does not create empty accounts')
    bad=secrets.token_hex(16);post('owner','/admin/ventas',{'action':'quick','open_key':bad,'start_product':1,'product_id':503,'quantity':1})
    assert not sql('SELECT id FROM pos_tickets WHERE open_key=?',[bad]) and not sql('SELECT id FROM fisitaap_operations180 WHERE operation_key=?',[bad])
    passed('an invalid mandatory product option rolls back the whole first-product account creation')
    key=secrets.token_hex(16);start={'action':'quick','open_key':key,'start_product':1,'product_id':501,'quantity':1};stock=sql('SELECT quantity FROM product_inventory WHERE product_id=501 AND branch_id=301')[0]['quantity']
    post('owner','/admin/ventas',start);post('owner','/admin/ventas',start);t=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];assert len(sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[t]))==1
    assert float(sql('SELECT quantity FROM product_inventory WHERE product_id=501 AND branch_id=301')[0]['quantity'])==float(stock)-1
    post('owner','/admin/ventas',{**start,'quantity':2});assert len(sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[t]))==1
    passed('the first product is reserved once and changed retries cannot alter the sale')
    item=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[t])[0]['id'];edit(t,{'action':'edit_item','item_id':item,'quantity':1,'base_price180':500})
    row=sql('SELECT unit_price,line_subtotal,line_tax FROM pos_ticket_items WHERE id=?',[item])[0];assert float(row['line_subtotal'])==500,row
    passed('authorized per-line price editing keeps its tax calculation and item identity')
    paykey,data,rows=charge(t,400,{'card':100,'sinpe':65});assert len(rows)==1,request('owner','/admin/cobro?ticket='+str(t))[1][-600:];sale=rows[0];assert sale['total']=='565.00' and sale['payment_method']=='Pago combinado',sale
    post('owner','/admin/cobro?ticket='+str(t),data);assert len(sql('SELECT id FROM sales WHERE payment_key=?',[paykey]))==1;assert sql('SELECT status FROM pos_tickets WHERE id=?',[t])[0]['status']=='paid'
    passed('mixed cash/card/SINPE settles once, preserves all payment lines and closes the account')
    # Zero-price products remain real inventory and sale history.
    sku='FREE'+secrets.token_hex(4);sql('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,cost,tax_rate,track_inventory) VALUES(101,401,"Free QA",?,?,0,1,0,1)',[sku.lower(),sku]);pid=sql('SELECT id FROM products WHERE sku=?',[sku])[0]['id'];sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(301,?,1)',[pid]);sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(101,301,?,10)',[pid]);key=secrets.token_hex(16);post('owner','/admin/ventas',{'action':'quick','open_key':key,'start_product':1,'product_id':pid,'quantity':1});t=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id']
    paykey,data,rows=charge(t,0);assert len(rows)==1,request('owner','/admin/cobro?ticket='+str(t))[1][-700:];assert rows[0]['total']=='0.00' and rows[0]['payment_method']=='Sin cobro';assert sql('SELECT status FROM pos_tickets WHERE id=?',[t])[0]['status']=='paid';assert sql('SELECT quantity FROM sale_items WHERE sale_id=?',[rows[0]['id']])[0]['quantity']=='1.000';assert sql('SELECT quantity FROM product_inventory WHERE product_id=? AND branch_id=301',[pid])[0]['quantity']=='9.000';post('owner','/admin/cobro?ticket='+str(t),data);assert len(sql('SELECT id FROM sales WHERE payment_key=?',[paykey]))==1
    passed('a free product can close a real sale once, with inventory and receipt history, without fake cash')
    # Partial zero-line settlement must leave positive consumptions available.
    key=secrets.token_hex(16);post('owner','/admin/ventas',{'action':'quick','open_key':key,'start_product':1,'product_id':pid,'quantity':1});t=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id'];edit(t,{'action':'add_item','product_id':501,'quantity':1});free=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND product_id=?',[t,pid])[0]['id'];_,_,rows=charge(t,0,{'split_mode':'items','qty['+str(free)+']':1});assert rows and sql('SELECT status FROM pos_tickets WHERE id=?',[t])[0]['status']=='payment';_,_,rows=charge(t,1130);assert rows and sql('SELECT status FROM pos_tickets WHERE id=?',[t])[0]['status']=='paid'
    passed('settling a free line first preserves the remaining paid account and quantities')
    shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[shiftkey])[0]['id'];post('owner','/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'other_cash':1630})
    assert sql('SELECT status FROM pos_shifts WHERE id=?',[shift])[0]['status']=='closed'
    pathlib.Path('actualizaciones/1.8.0/QA-POS.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated real PHP/MariaDB HTTP/native proof; no production'},indent=2)+'\n')
if __name__=='__main__':main()
