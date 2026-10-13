"""Exercise money and stock together using real HTTP, isolated fictitious data."""
import hashlib,json,pathlib,re,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql,PASSWORD
from financial180 import api
checks=[]
def passed(message):checks.append(message);print('PASS',message,flush=True)
def post(path,data):return request('owner',path,{'csrf':csrf('owner',path),**data})
def main():
    token=secrets.token_hex(32);digest=hashlib.sha256(token.encode()).hexdigest()
    sql('INSERT INTO fisitaap_r2_devices(tenant_id,branch_id,name,token_hash) VALUES(101,301,"FINANCE TEST DEVICE",?)',[digest]);device=sql('SELECT id FROM fisitaap_r2_devices WHERE token_hash=?',[digest])[0]['id']
    sql('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,device_id,user_id) VALUES(101,301,"FINANCE TEST BOX",?,202)',[device])
    context=json.loads(re.search(r'window.FISITAAP180=(\{[^<]+\})</script>',request('owner','/admin/turnos')[1])[1])
    code,proof=api('web-session',{'nonce':context['nonce'],'user_id':202},token);assert code==200
    assert request('owner','/api/native/bind',{'csrf':csrf('owner','/admin/turnos'),'proof':proof['proof']})[0]==200
    opening=secrets.token_hex(16);post('/admin/turnos',{'action':'open','opening_key':opening,'opening_cash':20000});shift=sql('SELECT id FROM pos_shifts WHERE opening_key=?',[opening])[0]['id']
    sql('INSERT INTO suppliers(tenant_id,name,tax_id) VALUES(101,"QA SUPPLIER","3-101-987654")');supplier=sql('SELECT id FROM suppliers WHERE name="QA SUPPLIER" AND tenant_id=101')[0]['id']
    key=secrets.token_hex(16);draft={'action':'save-draft','purchase_key':key,'supplier_id':supplier,'invoice_number':'QA-'+key,'line[0][product_id]':501,'line[0][quantity]':2,'line[0][unit_cost]':100,'line[0][tax_rate]':13,'credit':'on','amount_paid':0,'due_date':'2026-11-01','payment_method':'Efectivo','account_id':0}
    before=sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=501')[0]['quantity']
    post('/admin/compras',draft);rows=sql('SELECT * FROM fisitaap_purchase_drafts180 WHERE operation_key=?',[key]);assert len(rows)==1
    did=rows[0]['id'];assert sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=501')[0]['quantity']==before
    assert not sql('SELECT id FROM purchases WHERE invoice_number=?',[draft['invoice_number']])
    passed('a purchase draft changes neither stock nor debt')
    post('/admin/compras',draft);assert len(sql('SELECT id FROM fisitaap_purchase_drafts180 WHERE operation_key=?',[key]))==1
    apply={'action':'apply','id':did,'revision':0,'confirm':'yes'}
    post('/admin/compras',apply);post('/admin/compras',apply)
    purchases=sql('SELECT * FROM purchases WHERE invoice_number=?',[draft['invoice_number']]);assert len(purchases)==1 and purchases[0]['total']=='226.00'
    assert float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=501')[0]['quantity'])==float(before)+2
    assert sql('SELECT cost FROM products WHERE id=501')[0]['cost']=='100.0000'
    debt=sql('SELECT * FROM accounts_payable WHERE purchase_id=?',[purchases[0]['id']])[0];assert debt['balance']=='226.00'
    passed('receiving a purchase updates stock, cost and payable exactly once')
    pkey=secrets.token_hex(16);pay={'id':debt['id'],'payment_key':pkey,'amount':40,'payment_method':'Efectivo','account_id':0,'reference':'QA-CASH'}
    post('/admin/cxp',pay);post('/admin/cxp',pay)
    assert sql('SELECT balance FROM accounts_payable WHERE id=?',[debt['id']])[0]['balance']=='186.00'
    assert len(sql('SELECT id FROM ap_payments WHERE payment_key=?',[pkey]))==1
    assert sql('SELECT SUM(amount) amount FROM fisitaap_money_entries180 WHERE shift_id=?',[shift])[0]['amount']=='-40.00'
    post('/admin/cxp',{**pay,'amount':90})
    assert sql('SELECT balance FROM accounts_payable WHERE id=?',[debt['id']])[0]['balance']=='186.00'
    passed('cash payable payments debit the assigned shift, reject changed retries and preserve balance')
    sql('INSERT INTO fisitaap_money_accounts180(tenant_id,name,balance) VALUES(101,"QA BANK",1000)');bank=sql('SELECT id FROM fisitaap_money_accounts180 WHERE name="QA BANK" AND tenant_id=101')[0]['id']
    bankpay={**pay,'payment_key':secrets.token_hex(16),'amount':186,'payment_method':'Transferencia','account_id':bank,'reference':'QA-BANK'}
    post('/admin/cxp',bankpay);post('/admin/cxp',bankpay)
    assert sql('SELECT balance,status FROM accounts_payable WHERE id=?',[debt['id']])[0]=={'balance':'0.00','status':'paid'}
    assert sql('SELECT balance FROM fisitaap_money_accounts180 WHERE id=?',[bank])[0]['balance']=='814.00'
    assert sql('SELECT SUM(amount) amount FROM fisitaap_money_entries180 WHERE shift_id=?',[shift])[0]['amount']=='-40.00'
    passed('an external payment reconciles its money account without touching cash')
    sql('INSERT INTO accounts_receivable(tenant_id,branch_id,customer_id,document_number,issue_date,due_date,amount,balance,status) VALUES(101,301,205,"QA-OVERDUE",CURDATE(),DATE_SUB(CURDATE(),INTERVAL 1 DAY),100,100,"pending")')
    ar=sql('SELECT id FROM accounts_receivable WHERE document_number="QA-OVERDUE" AND tenant_id=101')[0]['id'];sql('UPDATE customer_credit_profiles SET current_balance=100 WHERE tenant_id=101 AND user_id=205')
    assert 'QA-OVERDUE' in request('owner','/admin/cxc?status=overdue')[1]
    arpay={'id':ar,'payment_key':secrets.token_hex(16),'amount':50,'payment_method':'Efectivo','account_id':0,'reference':'QA-AR'}
    post('/admin/cxc',arpay);post('/admin/cxc',arpay)
    assert sql('SELECT balance,status FROM accounts_receivable WHERE id=?',[ar])[0]=={'balance':'50.00','status':'partial'}
    assert sql('SELECT current_balance FROM customer_credit_profiles WHERE tenant_id=101 AND user_id=205')[0]['current_balance']=='50.00'
    assert sql('SELECT SUM(amount) amount FROM fisitaap_money_entries180 WHERE shift_id=?',[shift])[0]['amount']=='10.00'
    beforeMoves=len(sql('SELECT id FROM fisitaap_money_entries180 WHERE tenant_id=101'))
    post('/admin/cxc',{**arpay,'payment_key':secrets.token_hex(16),'amount':999})
    assert len(sql('SELECT id FROM fisitaap_money_entries180 WHERE tenant_id=101'))==beforeMoves
    passed('overdue is calculated from real due date; receivable payments restore credit and overpayment rolls back')
    if request('ownerb','/owner-login')[0]==200:request('ownerb','/owner-login',{'csrf':csrf('ownerb','/owner-login'),'email':'ownerb@r2.invalid','password':PASSWORD})
    request('ownerb','/admin/cxc',{'csrf':csrf('ownerb','/admin/cxc'),**arpay,'payment_key':secrets.token_hex(16)})
    assert sql('SELECT balance FROM accounts_receivable WHERE id=?',[ar])[0]['balance']=='50.00'
    passed('cross-business debt references cannot receive payments')
    sql('UPDATE fisitaap_r2_devices SET token_hash=? WHERE id=?',[hashlib.sha256(secrets.token_bytes(32)).hexdigest(),device])
    post('/admin/cxc',{**arpay,'payment_key':secrets.token_hex(16),'amount':10})
    assert sql('SELECT balance FROM accounts_receivable WHERE id=?',[ar])[0]['balance']=='50.00'
    passed('rotating or revoking the device connection immediately invalidates its cash authorization')
    sql('UPDATE fisitaap_r2_devices SET token_hash=? WHERE id=?',[digest,device])
    post('/admin/turnos',{'action':'close','id':shift,'closing_key':secrets.token_hex(16),'denominations[20000]':1,'denominations[10]':1,'other_cash':0})
    closed=sql('SELECT status,expected_cash,closing_cash,difference_amount FROM pos_shifts WHERE id=?',[shift])[0]
    assert closed=={'status':'closed','expected_cash':'20010.00','closing_cash':'20010.00','difference_amount':'0.00'}
    passed('closing reconciles purchase payments and receivable cash into the denomination count')
    pathlib.Path('actualizaciones/1.8.0/QA-CUENTAS-COMPRAS.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated HTTP/CSRF and MariaDB, fictitious money and inventory'},indent=2)+'\n')
if __name__=='__main__':main()
