"""HTTP table/configuration regression cases, no real businesses or printers."""
import json,pathlib,secrets,sys
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql
checks=[]
def passed(message):checks.append(message);print('PASS',message,flush=True)
def post(path,data):return request('owner',path,{'csrf':csrf('owner',path),**data})
def main():
    tag=secrets.token_hex(4);post('/admin/salones',{'action':'create_room','name':'QA Salon '+tag,'service_rate':10})
    room=sql('SELECT id FROM fisitaap_r2_rooms WHERE tenant_id=101 AND name=?',['QA Salon '+tag])[0]['id'];tables=[]
    for label in ['A','B','C']:
        post('/admin/salones?room='+str(room),{'action':'save_table','room_id':room,'name':label+'-'+tag,'capacity':4,'shape':'square','service_enabled':'on'})
        tables.append(sql('SELECT id FROM restaurant_tables WHERE tenant_id=101 AND name=?',[label+'-'+tag])[0]['id'])
    runtime=request('owner','/admin/mesas?room='+str(room))[1]
    assert 'r3TableAccount' in runtime and 'Editar distribución' not in runtime and 'Añadir mesa' not in runtime and 'Crear salón' not in runtime
    assert 'data-layout-editor' in request('owner','/admin/salones?room='+str(room))[1]
    assert 'data-basic-edit' in request('owner','/admin/salones?room='+str(room)+'&view=basic')[1]
    passed('cashier runtime uses full floor/modal actions; configuration and basic ordering exist only in Salones y mesas')
    tickets=[]
    for table in tables[:2]:
        key=secrets.token_hex(16);post('/admin/ventas',{'action':'table','table_id':table,'open_key':key});ticket=sql('SELECT id FROM pos_tickets WHERE tenant_id=101 AND open_key=?',[key])[0]['id'];tickets.append(ticket)
        path='/admin/ventas?screen=order&ticket='+str(ticket);post(path,{'action':'add_item','revision':0,'product_id':501,'quantity':1});post(path,{'action':'send_kitchen','revision':1})
    original=sql('SELECT id,ticket_id,kitchen_status,sent_at FROM pos_ticket_items WHERE ticket_id IN (?,?) ORDER BY id',tickets)
    before=sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=501')[0]['quantity']
    post('/admin/salones?room='+str(room),{'action':'room','room_id':room,'name':'CHANGED','service_rate':0})
    assert sql('SELECT service_rate,name FROM fisitaap_r2_rooms WHERE id=?',[room])[0]=={'service_rate':'10.00','name':'QA Salon '+tag}
    status,_=post('/admin/mesas?room='+str(room),{'action':'room','room_id':room,'name':'CHANGED','service_rate':0});assert status==403
    passed('open accounts block service/layout edits and replaying a configuration POST on runtime is rejected')
    mainId,other=tickets;revision=sql('SELECT revision FROM pos_tickets WHERE id=?',[mainId])[0]['revision'];path='/admin/ventas?screen=order&ticket='+str(mainId)
    key=secrets.token_hex(16);join={'action':'join_tables','target_table':tables[1],'revision':revision,'operation_key':key};post(path,join);post(path,join)
    merged=sql('SELECT * FROM pos_tickets WHERE id=?',[mainId])[0];assert merged['total']=='2460.00' and merged['service_total']=='200.00'
    assert sql('SELECT status,merged_into FROM pos_tickets WHERE id=?',[other])[0]=={'status':'merged','merged_into':mainId}
    current=sql('SELECT id,kitchen_status,sent_at FROM pos_ticket_items WHERE ticket_id=? ORDER BY id',[mainId]);assert current==[{k:r[k] for k in ['id','kitchen_status','sent_at']} for r in original]
    assert sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=501')[0]['quantity']==before
    assert len(sql('SELECT id FROM fisitaap_operations180 WHERE tenant_id=101 AND operation_key=?',[key]))==1
    assert {r['table_id'] for r in sql('SELECT table_id FROM fisitaap_ticket_tables180 WHERE tenant_id=101 AND ticket_id=?',[mainId])}==set(tables[:2])
    passed('joining unpaid compatible tables keeps one principal bill, exact service, item identities and sent kitchen data without moving stock twice')
    post(path,{**join,'target_table':tables[2]});assert sql('SELECT revision FROM pos_tickets WHERE id=?',[mainId])[0]['revision']==merged['revision']
    joinedKey=secrets.token_hex(16);post('/admin/ventas',{'action':'table','table_id':tables[1],'open_key':joinedKey})
    assert not sql('SELECT id FROM pos_tickets WHERE open_key=?',[joinedKey])
    assert len(sql('SELECT id FROM pos_tickets WHERE tenant_id=101 AND table_id IN (?,?) AND status NOT IN("paid","void","merged")',tables[:2]))==1
    assert sql('SELECT status FROM restaurant_tables WHERE id IN (?,?) ORDER BY id',tables[:2])==[{'status':'occupied'},{'status':'occupied'}]
    passed('a changed operation key payload is rejected and opening either joined table returns the same bill')
    # Removing the joined group releases every seat and restores each reserved
    # item exactly once; no physical receipt or printer is involved.
    post(path,{'action':'void','revision':merged['revision']})
    assert sql('SELECT status FROM restaurant_tables WHERE id IN (?,?) ORDER BY id',tables[:2])==[{'status':'available'},{'status':'available'}]
    assert float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=501')[0]['quantity'])==float(before)+2
    post('/admin/salones?room='+str(room),{'action':'room_state','room_id':room,'is_active':0});assert sql('SELECT is_active FROM fisitaap_r2_rooms WHERE id=?',[room])[0]['is_active']==0
    key=secrets.token_hex(16);post('/admin/ventas',{'action':'table','table_id':tables[0],'open_key':key});assert not sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])
    passed('voiding a joined group releases all seats once and inactive rooms cannot open new accounts')
    pathlib.Path('actualizaciones/1.8.0/QA-MESAS.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'isolated HTTP/CSRF and MariaDB'},indent=2)+'\n')
if __name__=='__main__':main()
