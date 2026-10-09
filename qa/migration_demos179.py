"""Selective migration and disposable demo integration tests on a fake local copy."""
import hashlib
import json
import pathlib
import re
import subprocess
import sys
import urllib.parse

sys.path.insert(0, str(pathlib.Path(__file__).parent))
from migration179 import SERVER, STAGE, DELIVERY, request, login, php, run, digests


def sql(query, args=()):
    code = "require '/var/www/html/app/bootstrap.php';$q=json_decode(stream_get_contents(STDIN),true);$s=$app->db->prepare($q['sql']);$s->execute($q['args']);echo json_encode($s->fetchAll());"
    return json.loads(run(['docker', 'exec', '-i', SERVER, 'php', '-r', code], json.dumps({'sql': query, 'args': list(args)}).encode()))


def csrf(actor, path):
    status, body = request(actor, path)
    assert status == 200, (path, status)
    return re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]


def post(actor, path, data):
    return request(actor, path, {'csrf': csrf(actor, path), **data})


def main():
    checks = []

    def passed(name):
        checks.append(name)
        print('PASS', name, flush=True)

    assert STAGE.name == 'fisitaap-migration-test' and SERVER == 'fisitaap-migration-web'
    config = STAGE / 'config.php'
    original_config = config.read_text()
    config.write_text(original_config.replace('return [', "return ['migration_mode'=>true,", 1))
    config.chmod(0o644)
    sql('UPDATE tenants SET name="LA VENTANITA",slug="laventanita" WHERE id=101')
    sql("INSERT INTO tenants(id,name,slug,physical_store_enabled,catalog_label,is_listed) VALUES(901,'Restaurante demo','restaurante-demo',1,'Menú',0),(902,'Tienda demo','tienda-demo',1,'Catálogo',0)")
    sql("INSERT INTO branches(id,tenant_id,name,slug,is_default) VALUES(1301,901,'Principal demo','principal-demo',1),(1302,902,'Tienda demo','tienda-demo',1),(1303,901,'Sucursal demo','sucursal-demo',0)")
    # Clone only fake fixture credentials, never production users.
    sql("INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) SELECT 1201,901,'Dueño demo','restaurante.demo@fisitaap.com',password_hash,'tenant_admin',1 FROM users WHERE id=202")
    sql("INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) SELECT 1202,902,'Dueño tienda','negocio.demo@fisitaap.com',password_hash,'tenant_admin',1 FROM users WHERE id=202")
    sql("INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) SELECT 1203,901,'Cajero demo','cajero.demo@fisitaap.invalid',password_hash,'cashier',1 FROM users WHERE id=202")
    sql("INSERT INTO categories(id,tenant_id,name,slug) VALUES(1401,901,'Demo comida','comida'),(1402,902,'Demo tienda','tienda')")
    sql("INSERT INTO products(id,tenant_id,category_id,name,slug,sku,price,tax_rate,track_inventory) VALUES(1501,901,1401,'Producto demo','producto-demo','DEMO1',1000,13,1),(1502,901,1401,'Extra demo','extra-demo','DEMO2',200,13,1),(1503,901,1401,'Combo demo','combo-demo','DEMO3',1800,13,1),(1504,902,1402,'Tienda artículo','tienda-articulo','DEMO4',2000,0,1)")
    sql('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(901,1301,1501,30),(901,1301,1502,20),(901,1301,1503,10),(902,1302,1504,15)')
    sql('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(1301,1501,1),(1301,1502,1),(1301,1503,1),(1302,1504,1)')
    sql("INSERT INTO product_option_groups(id,tenant_id,product_id,name,type) VALUES(1601,901,1501,'Extras','multiple')")
    sql("INSERT INTO product_options(id,group_id,name,price_delta,linked_product_id) VALUES(1701,1601,'Extra prueba',200,1502)")
    sql("INSERT INTO fisitaap_r2_modifiers(id,tenant_id,kind,name,choices_json) VALUES(1801,901,'extra','Extras','[]')")
    sql('INSERT INTO fisitaap_r2_modifier_groups(modifier_id,product_id,group_id) VALUES(1801,1501,1601)')
    sql('INSERT INTO fisitaap_r2_combo_items(combo_id,product_id,quantity) VALUES(1503,1501,1),(1503,1502,1)')
    sql("INSERT INTO fisitaap_r2_rooms(id,tenant_id,branch_id,name) VALUES(1901,901,1301,'Salón demo')")
    sql("INSERT INTO restaurant_tables(id,tenant_id,branch_id,name) VALUES(2001,901,1301,'Mesa demo')")
    sql('INSERT INTO fisitaap_r2_floor(table_id,room_id,x,y) VALUES(2001,1901,30,40)')
    sql('INSERT INTO fisitaap_r3_room_versions(room_id,revision) VALUES(1901,0)')
    sql("INSERT INTO fisitaap_r3_sectors(id,tenant_id,branch_id,room_id,name) VALUES(2101,901,1301,1901,'Sector demo')")
    sql('INSERT INTO fisitaap_r3_table_details(table_id,tenant_id,branch_id,sector_id) VALUES(2001,901,1301,2101)')
    sql("INSERT INTO fisitaap_r1_roles(id,tenant_id,name) VALUES(2201,901,'Cajero prueba')")
    sql("INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(2201,'ventas','view'),(2201,'ventas','create')")
    sql("INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(901,1203,2201,'cashier')")
    baseline = digests(SERVER)
    token = csrf('master', '/preparar-mudanza.php')
    assert request('owner', '/preparar-mudanza.php')[0] == 403
    assert request('master', '/preparar-mudanza.php', {'csrf': 'wrong', 'database': 'fisitaap_migration_test', 'confirmation': 'SOLO LA VENTANITA Y DEMOS'})[0] == 403
    assert digests(SERVER) == baseline
    passed('selective migration requires fresh master, local enable flag and CSRF; failed authorization changes no rows')
    status, body = request('master', '/preparar-mudanza.php', {'csrf': token, 'database': 'fisitaap_migration_test', 'confirmation': 'SOLO LA VENTANITA Y DEMOS'})
    assert status == 200 and 'Preparación completada' in body, body[-2200:]
    assert sql('SELECT slug FROM tenants ORDER BY id') == [{'slug': 'laventanita'}, {'slug': 'restaurante-demo'}, {'slug': 'tienda-demo'}]
    assert not sql('SELECT id FROM users WHERE id IN(203,207)')
    assert sql('SELECT id FROM users WHERE role="master"') == [{'id': 201}]
    after = digests(SERVER)
    # Tables whose fixtures belong entirely to La Ventanita preserve every byte.
    for table in ['sales', 'sale_items', 'purchases', 'purchase_items', 'accounts_receivable', 'ar_payments', 'accounts_payable', 'ap_payments', 'pos_tickets', 'pos_ticket_items', 'pos_payments', 'pos_shifts', 'fisitaap_r2_devices', 'fisitaap_r2_snapshots', 'fisitaap_r2_synced_sales', 'fisitaap_r2_shift_links', 'fisitaap_primary179']:
        assert baseline[table] == after[table], table
    passed('only La Ventanita and both templates remain; excluded owner/customer removed while all real fixture financial records and device data remain identical')
    assert request('master', '/preparar-mudanza.php', {'csrf': token, 'database': 'fisitaap_migration_test', 'confirmation': 'SOLO LA VENTANITA Y DEMOS'})[0] == 200
    assert digests(SERVER) == after
    passed('preparation can be repeated without duplicate records or changes to La Ventanita')
    # Download archive uses only retained local references, never all uploads.
    import io, zipfile, urllib.parse
    files = ['kept-fixture.png', 'demo-fixture.png', 'unused-fixture.png']
    for name in files:
        (STAGE / 'uploads' / name).write_bytes(b'private-media-fixture')
    sql('UPDATE tenants SET logo=? WHERE id=101', ['/uploads/kept-fixture.png'])
    sql('UPDATE tenants SET logo=? WHERE id=901', ['/uploads/demo-fixture.png'])
    unchanged = digests(SERVER)
    payload = urllib.parse.urlencode({'csrf': csrf('master', '/preparar-mudanza.php'), 'action': 'media'}).encode()
    response = run(['docker', 'exec', '-i', SERVER, 'curl', '--noproxy', '*', '-sS', '--max-time', '30', '-b', '/tmp/migration-master.cookies', '--data-binary', '@-', '-w', '\n%{http_code}', 'http://localhost/preparar-mudanza.php'], payload)
    binary, status = response.rsplit(b'\n', 1)
    assert status == b'200'
    with zipfile.ZipFile(io.BytesIO(binary)) as archive:
        names = set(archive.namelist())
        assert {'uploads/kept-fixture.png', 'uploads/demo-fixture.png', 'uploads/.htaccess'} <= names
        assert 'uploads/unused-fixture.png' not in names
        assert archive.read('uploads/kept-fixture.png') == b'private-media-fixture'
    assert digests(SERVER) == unchanged
    passed('private media ZIP includes retained references and protection, excludes unused files, and changes no database rows')
    config.write_text(original_config)
    assert request('master', '/preparar-mudanza.php')[0] == 403
    passed('migration tool is blocked again when local migration mode is removed')

    def opening(actor, kind='restaurante', mode='panel'):
        status, body = request(actor, '/demo/iniciar', {'csrf': csrf(actor, '/demo'), 'type': kind, 'mode': mode})
        assert status == 200 and 'sessionStorage.setItem' in body, body[-400:]
        return re.search(r'fisitaap-demo-tab",\s*"([a-f0-9]{32})"', body)[1]

    first = opening('visitor1')
    second = opening('visitor2')
    p1, p2 = '/demo/s/' + first, '/demo/s/' + second
    assert request('visitor2', p1 + '/admin')[0] == 403
    assert request('visitor1', p1 + '/admin')[0] == 200
    assert request('visitor2', p2 + '/admin')[0] == 200
    rows = sql('SELECT s.token,s.tenant_id,s.owner_id,s.customer_id,t.slug FROM fisitaap_demo_sessions s JOIN tenants t ON t.id=s.tenant_id ORDER BY s.created_at,s.token')
    by_token = {r['token']: r for r in rows}
    d1, d2 = by_token[first], by_token[second]
    assert d1['tenant_id'] != d2['tenant_id'] and d1['owner_id'] != d2['owner_id']
    product1 = sql('SELECT id,price FROM products WHERE tenant_id=? AND sku="DEMO1"', [d1['tenant_id']])[0]
    product2 = sql('SELECT id,price FROM products WHERE tenant_id=? AND sku="DEMO1"', [d2['tenant_id']])[0]
    assert product1['id'] != product2['id'] and product1['price'] == product2['price'] == '1000.00'
    # Linked option, room/table and permission graphs must point into each new copy.
    assert sql('SELECT p.tenant_id FROM product_options o JOIN product_option_groups g ON g.id=o.group_id JOIN products p ON p.id=o.linked_product_id WHERE g.tenant_id=?', [d1['tenant_id']]) == [{'tenant_id': d1['tenant_id']}]
    assert sql('SELECT r.tenant_id FROM fisitaap_r2_floor f JOIN restaurant_tables t ON t.id=f.table_id JOIN fisitaap_r2_rooms r ON r.id=f.room_id WHERE t.tenant_id=?', [d1['tenant_id']]) == [{'tenant_id': d1['tenant_id']}]
    assert sql('SELECT r.tenant_id FROM fisitaap_r1_user_roles a JOIN fisitaap_r1_roles r ON r.id=a.role_id WHERE a.tenant_id=?', [d1['tenant_id']]) == [{'tenant_id': d1['tenant_id']}]
    passed('two visitors have separate tenants, users, stock, linked options, rooms and roles; another browser cannot reuse a demo URL')
    for path in ['productos', 'ventas', 'mesas', 'turnos', 'clientes-crear', 'compras', 'cxc', 'cxp', 'contenido', 'roles', 'impresion']:
        assert request('visitor1', p1 + '/admin/' + path)[0] == 200, path
    assert request('visitor1', p1 + '/assets/app.bundle.css')[0] == 200
    assert request('visitor1', p1 + '/laventanita')[0] == 403
    assert request('visitor1', p1 + '/master')[0] == 403
    assert request('visitor1', p1 + '/api/desktop/status')[0] == 403
    assert request('owner', '/admin/productos')[0] == 200
    passed('demo menus and namespaced static assets work; real store, master and device pairing routes are inaccessible; normal owner session remains valid')
    # An actual POS sale, not a stub: namespace CSRF, shifts, ticket, item and payment.
    assert post('visitor1', p1 + '/admin/turnos', {'action': 'open', 'name': 'Turno demo', 'opening_cash': '0'})[0] == 302
    openkey = 'd' * 32
    assert post('visitor1', p1 + '/admin/ventas', {'action': 'quick', 'open_key': openkey})[0] == 302
    ticket = sql('SELECT id FROM pos_tickets WHERE tenant_id=? AND open_key=?', [d1['tenant_id'], openkey])[0]['id']
    orderpath = p1 + '/admin/ventas?screen=order&ticket=' + str(ticket)
    assert post('visitor1', orderpath, {'action': 'add_item', 'revision': '0', 'product_id': product1['id'], 'quantity': '1'})[0] == 302
    ticket_before = sql('SELECT * FROM pos_tickets WHERE id=?', [ticket])[0]
    assert float(ticket_before['total']) > 0
    charge = p1 + '/admin/ventas?screen=checkout&ticket=' + str(ticket)
    payment = {'payment_key': 'e' * 32, 'revisions[' + str(ticket) + ']': ticket_before['revision'], 'customer_id': d1['customer_id'], 'split_mode': 'all', 'cash': '5000', 'card': '0', 'sinpe': '0', 'credit': '0'}
    assert post('visitor1', charge, payment)[0] == 302
    assert sql('SELECT status FROM pos_tickets WHERE id=?', [ticket]) == [{'status': 'paid'}]
    assert sql('SELECT COUNT(*) n FROM sales WHERE tenant_id=?', [d1['tenant_id']])[0]['n'] == 1
    assert sql('SELECT quantity FROM product_inventory WHERE tenant_id=? AND product_id=?', [d1['tenant_id'], product1['id']])[0]['quantity'] == '29.000'
    assert sql('SELECT quantity FROM product_inventory WHERE tenant_id=? AND product_id=?', [d2['tenant_id'], product2['id']])[0]['quantity'] == '30.000'
    assert sql('SELECT quantity FROM product_inventory WHERE tenant_id=901 AND product_id=1501')[0]['quantity'] == '30.000'
    passed('real demo sale, payment and stock deduction are confined to one copy; second visitor and original template remain unchanged')
    assert post('visitor1', p1 + '/admin/clientes-crear', {'action': 'create', 'name': 'Cliente temporal', 'phone': '88887777'})[0] == 302
    assert sql('SELECT tenant_id FROM users WHERE name="Cliente temporal"') == [{'tenant_id': d1['tenant_id']}]
    passed('customers created in the demo are owned by its disposable copy, without persistent global accounts')
    frozen = digests(SERVER)
    status, body = request('visitor1', p1 + '/logout', {'csrf': csrf('visitor1', p1 + '/admin')})
    assert status == 302
    assert not sql('SELECT id FROM tenants WHERE id=?', [d1['tenant_id']])
    assert not sql('SELECT id FROM users WHERE tenant_id=?', [d1['tenant_id']])
    assert not sql('SELECT id FROM sales WHERE tenant_id=?', [d1['tenant_id']])
    assert request('visitor1', p1 + '/admin')[0] == 403
    assert request('visitor2', p2 + '/admin')[0] == 200
    passed('logout discards all demo users, sales, tickets and stock rows; old URL expires while other visitor stays active')
    third = opening('visitor1')
    p3 = '/demo/s/' + third
    assert request('visitor1', p3 + '/admin')[0] == 200
    d3 = sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?', [third])[0]
    assert sql('SELECT quantity FROM product_inventory i JOIN products p ON p.id=i.product_id WHERE i.tenant_id=? AND p.sku="DEMO1"', [d3['tenant_id']])[0]['quantity'] == '30.000'
    assert not sql('SELECT id FROM sales WHERE tenant_id=?', [d3['tenant_id']])
    passed('new entry restores template stock and empty demo transactions')
    shop = opening('visitor3', 'tienda', 'cliente')
    assert request('visitor3', '/demo/s/' + shop + '/demo-tienda-' + shop + '/catalogo')[0] == 200
    passed('general store demo supports the separate customer/catalog mode')
    sql('UPDATE fisitaap_demo_sessions SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE token IN(?,?)', [third, shop])
    assert request('visitor1', p3 + '/admin')[0] == 403
    output = run(['docker', 'exec', SERVER, 'php', '/var/www/html/app/demo_cleanup.php']).decode()
    assert '2 copias temporales eliminadas' in output
    assert not sql('SELECT token FROM fisitaap_demo_sessions WHERE token IN(?,?)', [third, shop])
    assert request('visitor2', p2 + '/admin')[0] == 200
    passed('idle expiry and CLI cleanup remove abandoned copies and leave active sessions intact')
    final = digests(SERVER)
    for table in ['sales', 'sale_items', 'purchases', 'purchase_items', 'accounts_receivable', 'ar_payments', 'accounts_payable', 'ap_payments', 'pos_tickets', 'pos_ticket_items', 'pos_payments', 'pos_shifts', 'fisitaap_r2_devices', 'fisitaap_r2_snapshots', 'fisitaap_r2_synced_sales', 'fisitaap_r2_shift_links', 'fisitaap_primary179']:
        assert final[table] == after[table], table
    assert len(sql('SELECT id FROM tenants WHERE slug NOT IN("restaurante-demo","tienda-demo") AND slug NOT REGEXP "^demo-(restaurante|tienda)-[0-9a-f]{32}$"')) == 1
    passed('La Ventanita financial/device hashes remain unchanged after all demo operations and cleanup; real directory has one business')
    (DELIVERY / 'QA-DEMOS.json').write_text(json.dumps({'environment': 'isolated imported fake database; PHP 8.3 / MariaDB 11.4', 'passed': len(checks), 'checks': checks}, ensure_ascii=False, indent=2) + '\n')
    print('RESULT', len(checks), 'selective migration and demo checks passed')


if __name__ == '__main__':
    main()
