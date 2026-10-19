"""Exercise live table buttons and protect a currently edited quantity from remote changes."""
import json,pathlib,re,secrets,sys
from playwright.sync_api import sync_playwright,expect
from browser_operations_r9 import login_page
from usability_r7 import login
sys.path.insert(0,'/workspace/fisitaap-env/tests')
from r3_helpers import request,csrf,sql

ROOT=pathlib.Path(__file__).resolve().parents[1];checks=[]
def post(path,data):return request('owner',path,{'csrf':csrf('owner','/admin/ventas'),**data})
def main():
    assert sql('SELECT DATABASE() db')[0]['db']=='fisitaap_r2_test';login('owner','owner@r2.invalid');sql('DELETE FROM rate_limits')
    tag='FLOOR9-'+secrets.token_hex(4);post('/admin/salones',{'action':'create_room','name':tag,'service_rate':0});room=sql('SELECT id FROM fisitaap_r2_rooms WHERE tenant_id=101 AND name=?',[tag])[0]['id'];post('/admin/salones?room='+str(room),{'action':'save_table','room_id':room,'name':'Mesa '+tag,'capacity':4,'shape':'square','x':150,'y':200});table=sql('SELECT id FROM restaurant_tables WHERE tenant_id=101 AND name=?',['Mesa '+tag])[0]['id']
    # Only the known fictional table is emptied before exercising its transition.
    sql('UPDATE pos_tickets SET status="void" WHERE table_id=? AND tenant_id=101 AND status NOT IN("paid","void","merged")',[table])
    with sync_playwright() as pw:
        browser=pw.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--no-proxy-server']);ctx,page=login_page(browser,'owner@r2.invalid');page.goto('http://localhost:18080/admin/mesas?view=basic&room='+str(room))
        button=page.locator('[data-basic-tables] [data-r3-table="'+str(table)+'"]');expect(button).to_have_attribute('data-state','free');navigations=[];page.on('framenavigated',lambda f:navigations.append(f.url) if f==page.main_frame else None)
        key=secrets.token_hex(16);post('/admin/ventas',{'action':'table','table_id':table,'open_key':key,'start_product':1,'product_id':501,'quantity':1});ticket=sql('SELECT id FROM pos_tickets WHERE open_key=?',[key])[0]['id']
        expect(button).to_have_attribute('data-state','busy',timeout=9000);button.click();expect(page.locator('#r3TableAccount')).to_be_visible();assert page.locator('#r3TableAccount').inner_text();assert not navigations
        checks.append('another seller opens a table: the existing floor button becomes occupied within polling, opens its refreshed account dialog and never navigates')
        sale=ctx.new_page();sale.goto('http://localhost:18080/admin/ventas?screen=order&ticket='+str(ticket));qty=sale.locator('.r3-quantity input[name=quantity]').first;expect(qty).to_have_value('1.000');qty.fill('4')
        rev=sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]['revision'];item=sql('SELECT id FROM pos_ticket_items WHERE ticket_id=?',[ticket])[0]['id'];post('/admin/ventas?screen=order&ticket='+str(ticket),{'action':'quantity','revision':rev,'item_id':item,'quantity':2})
        expect(sale.locator('.op180-live-status')).to_contain_text('se aplican al terminar',timeout=9000);expect(qty).to_have_value('4');qty.blur();expect(sale.locator('.r3-quantity input[name=quantity]').first).to_have_value('2.000',timeout=9000)
        sale.locator('[data-quantity-step="1"]').first.click();sale.wait_for_load_state();expect(sale.locator('.r3-quantity input[name=quantity]').first).to_have_value('3.000',timeout=9000)
        assert sql('SELECT quantity FROM pos_ticket_items WHERE id=?',[item])[0]['quantity']=='3.000'
        checks.append('live updates preserve an actively edited quantity, apply after blur, and refreshed plus buttons submit once with the current revision')
        browser.close()
    (ROOT/'actualizaciones/1.8.0/QA-MESAS-VIVO-R9.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'fictional real PHP/MariaDB/Chromium; no production'},indent=2)+'\n');print('PASS',len(checks),'live floor and edit-preservation groups')
if __name__=='__main__':main()
