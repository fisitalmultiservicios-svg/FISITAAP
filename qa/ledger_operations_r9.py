"""R9 real cash/driver cuts, full fee versus percentage, POS fee and explicit role grants."""
import hashlib,json,pathlib,re,secrets,subprocess,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from usability_r7 import login
from financial180 import api
from operations_r9 import raw

ROOT=pathlib.Path(__file__).resolve().parents[1];tag='LEDGER9-'+secrets.token_hex(4);owner='ledger_'+tag;guest='ledger_guest_'+tag;driver='ledger_driver_'+tag;checks=[]
def passed(s):checks.append(s);print('PASS',s,flush=True)
def post(path,data,actor=owner):return request(actor,path,{'csrf':csrf(actor,'/driver' if actor==driver else '/admin/turnos'),**data})
def board(oid,action,**extra):return post('/admin/pedidos',{'origin':'web','id':oid,'revision':sql('SELECT revision180 FROM orders WHERE id=?',[oid])[0]['revision180'],'action':action,**extra})
def checkout(zone):
    data={'csrf':csrf(guest,'/laventanita/catalogo'),'tenant':'laventanita','branch_id':301,'guest_name':tag,'guest_phone':phone,'payment':'Efectivo','delivery_type':'delivery','zone_id':zone,'address':'Dirección ficticia contable','checkout_key':secrets.token_hex(16),'items':[{'id':501,'qty':1,'option_ids':[]}]}
    status,body=raw(guest,'/api/checkout',data);out=json.loads(body);assert status==200 and out['ok'],out;return out
def deliver(out,uid):
    oid=out['order_id'];board(oid,'prepare');board(oid,'prepared');board(oid,'dispatch',dispatch_mode='driver',driver_id=uid,payout=999999)
    j=sql('SELECT * FROM delivery_jobs WHERE order_id=?',[oid])[0]
    for action in ['arrived','picked_up','in_transit','delivered']:post('/driver',{'action':action,'job_id':j['id'],'delivery_pin':out['delivery_pin']},driver)
    assert sql('SELECT status FROM delivery_jobs WHERE id=?',[j['id']])[0]['status']=='delivered';return j['id']
def settle_order(oid):
    t=sql('SELECT t.*,o.user_id FROM pos_tickets t JOIN orders o ON o.id=t.source_order_id WHERE o.id=?',[oid])[0];key=secrets.token_hex(16)
    post('/admin/cobro?ticket='+str(t['id']),{'payment_key':key,'revisions['+str(t['id'])+']':t['revision'],'split_mode':'all','parts':2,'cash':t['total'],'customer_id':t['user_id']})
    sales=sql('SELECT * FROM sales WHERE payment_key=?',[key]);assert len(sales)==1 and sales[0]['total']=='2130.00';return t
phone='8'+str(secrets.randbelow(10000000)).zfill(7)
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';login(owner,'owner@r2.invalid');sql('DELETE FROM rate_limits')
    token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest();sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,?,?)',[tag,digest]);did=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id']
    sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,?,?,202)',[tag,did])
    context=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',request(owner,'/admin/turnos')[1])[1]);status,proof=api('web-session',{'nonce':context['nonce'],'user_id':202},token)
    assert status==200 and any(u['id']==202 and u['password_hash'] and u['permissions']['shift'] for u in proof['users180'])
    assert post('/api/native/bind',{'proof':proof['proof']})[0]==200
    opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':0});shift=sql('SELECT * FROM pos_shifts WHERE opening_key=?',[opening])[0]
    passed('real bearer-device web authorization includes only approved local users and current permissions for immediate native preparation; cash shift opens on its assigned register')
    sql('INSERT INTO delivery_zones(tenant_id,name,price,is_active) VALUES(101,?,1000,1)',[tag]);zone=sql('SELECT id FROM delivery_zones WHERE name=?',[tag])[0]['id']
    email=tag.lower()+'@invalid.test';sql('INSERT INTO users(name,email,password_hash,role,is_active) SELECT ?,?,password_hash,"driver",1 FROM users WHERE id=202',[tag,email]);uid=sql('SELECT id FROM users WHERE email=?',[email])[0]['id'];login(driver,email)
    sql('INSERT INTO driver_profiles(user_id,is_online) VALUES(?,1)',[uid]);sql('INSERT INTO driver_affiliations(user_id,tenant_id,status,all_branches,payout_mode180,payout_percent180) VALUES(?,101,"active",1,"percent",25)',[uid])
    a=checkout(zone);ja=deliver(a,uid);ta=settle_order(a['order_id']);assert sql('SELECT payout FROM delivery_jobs WHERE id=?',[ja])[0]['payout']=='250.00'
    sql('UPDATE driver_affiliations SET payout_mode180="all" WHERE tenant_id=101 AND user_id=?',[uid]);b=checkout(zone);jb=deliver(b,uid);tb=settle_order(b['order_id']);assert sql('SELECT payout FROM delivery_jobs WHERE id=?',[jb])[0]['payout']=='1000.00'
    assert sql('SELECT payout FROM delivery_jobs WHERE id=?',[ja])[0]['payout']=='250.00'
    expected=lambda: int(round(float(sql('SELECT COALESCE(SUM(p.amount),0) total FROM pos_payments p JOIN sales s ON s.id=p.sale_id WHERE s.shift_id=? AND p.payment_method="Efectivo"',[shift['id']])[0]['total'])*100))+int(round(float(sql('SELECT COALESCE(SUM(amount),0) total FROM fisitaap_money_entries180 WHERE shift_id=?',[shift['id']])[0]['total'])*100))
    assert expected()==426000
    passed('two actual cash sales include their entire delivery fee in daily cash; a 25% affiliate earns250 and an all-fee affiliate earns1000; changing terms never reprices an already assigned delivery')
    key=secrets.token_hex(16);cashcut={'action':'settlement','driver_id':uid,'jobs[]':ja,'account_id':0,'operation_key':key,'reference':'Pago efectivo QA'}
    for _ in range(2):post('/admin/afiliados',cashcut)
    assert expected()==401000 and sql('SELECT COUNT(*) n FROM fisitaap_driver_settlements180 WHERE operation_key=?',[key])[0]['n']==1
    post('/admin/cuentas-dinero',{'action':'save','name':tag+' BANK','kind':'bank','opening':3000});bank=sql('SELECT id FROM fisitaap_money_accounts180 WHERE name=?',[tag+' BANK'])[0]['id']
    post('/admin/afiliados',{'action':'settlement','driver_id':uid,'jobs[]':jb,'account_id':bank,'operation_key':secrets.token_hex(16),'reference':'Transferencia QA'})
    assert expected()==401000 and sql('SELECT balance FROM fisitaap_money_accounts180 WHERE id=?',[bank])[0]['balance']=='2000.00'
    passed('cash cut subtracts only from its actual native shift once; a bank cut subtracts only from the selected bank and leaves daily cash unchanged')
    # POS Express uses its own delivery fee and Maps without duplicating its original account.
    opened=secrets.token_hex(16);post('/admin/ventas',{'action':'express','open_key':opened,'customer_id':sql('SELECT user_id FROM orders WHERE id=?',[a['order_id']])[0]['user_id'],'delivery_address':'Dirección POS QA','maps_url':'https://www.google.com/maps?q=9.9,-84.1','delivery_fee':1000,'start_product':1,'product_id':501,'quantity':1})
    rows=sql('SELECT id FROM pos_tickets WHERE open_key=?',[opened]);assert rows,re.findall(r'<div class="alert[^>]*>(.*?)</div>',request(owner,'/admin/ventas')[1]);tid=rows[0]['id'];revision=lambda:sql('SELECT revision FROM pos_tickets WHERE id=?',[tid])[0]['revision']
    for action in ['prepare','prepared','dispatch']:post('/admin/pedidos',{'origin':'pos','id':tid,'revision':revision(),'action':action,'dispatch_mode':'pool','payout':123})
    pos=sql('SELECT * FROM pos_tickets WHERE id=?',[tid])[0];order=sql('SELECT * FROM orders WHERE id=?',[pos['source_order_id']])[0]
    assert pos['total']==order['total']=='2130.00' and order['delivery_total']=='1000.00' and order['subtotal']=='1000.00'
    assert sql('SELECT SUM(line_subtotal) n FROM order_items WHERE order_id=?',[order['id']])[0]['n']=='1000.00'
    post('/admin/ventas?screen=order&ticket='+str(tid),{'action':'void','revision':revision(),'cancel_reason':'Se anuló el pedido ficticio antes de cobrar'})
    assert sql('SELECT status FROM pos_tickets WHERE id=?',[tid])[0]['status']=='void'
    passed('POS Express fee and Maps survive delivery linking with exact subtotal/tax/delivery/total; its dedicated fee line is not duplicated and justified cancellation remains on the same account')
    # A custom cashier can cancel only after an explicit grant, which is read afresh.
    login('cashier','cashiera@r2.invalid');old=sql('SELECT * FROM fisitaap_r1_user_roles WHERE tenant_id=101 AND user_id=204')
    sql('INSERT INTO fisitaap_r1_roles(tenant_id,name) VALUES(101,?)',[tag]);role=sql('SELECT id FROM fisitaap_r1_roles WHERE name=?',[tag])[0]['id']
    for section,actions in {'pedidos':['view','write'],'ventas':['view','write','accept_orders']}.items():
        for action in actions:sql('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,?,?)',[role,section,action])
    sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,204,?,"cashier") ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)',[role])
    try:
        c=checkout(zone);oid=c['order_id'];data={'csrf':csrf('cashier','/admin/pedidos'),'origin':'web','id':oid,'revision':0,'action':'status','status':'cancelled','cancel_reason':'Motivo de anulación ficticio por rol'}
        request('cashier','/admin/pedidos',data);assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='new'
        sql('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,"pedidos","delete")',[role]);request('cashier','/admin/pedidos',data)
        assert sql('SELECT status FROM orders WHERE id=?',[oid])[0]['status']=='cancelled'
    finally:
        sql('DELETE FROM fisitaap_r1_user_roles WHERE tenant_id=101 AND user_id=204')
        if old:sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,204,?,?)',[old[0]['role_id'],old[0]['previous_role']])
        sql('DELETE FROM fisitaap_r1_permissions WHERE role_id=?',[role]);sql('DELETE FROM fisitaap_r1_roles WHERE id=?',[role])
    passed('custom-role cancellation is denied without delete, succeeds with explicit delete and justification, and permission changes take effect without another login')
    post('/admin/turnos',{'action':'close','id':shift['id'],'closing_key':secrets.token_hex(16),'other_cash':4010})
    closed=sql('SELECT * FROM pos_shifts WHERE id=?',[shift['id']])[0];assert closed['status']=='closed' and closed['expected_cash']=='4010.00' and closed['difference_amount']=='0.00'
    passed('real cash close reconciles full delivery receipts minus only the cash driver payout with zero difference')
    (ROOT/'actualizaciones/1.8.0/QA-CAJA-MOTOS-R9.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'real fictional PHP/MariaDB device API and money ledger; no actual banking, cash, printer or production'},indent=2)+'\n')

if __name__=='__main__':main()
