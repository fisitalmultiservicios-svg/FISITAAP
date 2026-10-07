"""Deep QA against an isolated PHP/MariaDB fixture; never a production URL.

Requires a freshly reset R2 fixture and enabled R3 schema before each full run.
The isolated fixtures are created by fisitaap-env/tests/release_r2.py and release_r3.py.
No live credentials, mail provider or bank is used. Results contain case names only.
"""
import concurrent.futures
import csv
import hashlib
import io
import json
import os
import pathlib
import re
import secrets
import subprocess
import time
import traceback
import urllib.parse

FIXTURE = json.loads(pathlib.Path('/tmp/fisitaap-r2-e2e.json').read_text())
WEB = FIXTURE['web']
assert WEB == 'fisitaap-r2-web', 'Only the isolated QA container is allowed'
PASSWORD = FIXTURE['password']
BOOT = "require '/var/www/html/app/bootstrap.php';"
OUT = pathlib.Path(os.environ.get('FISITAAP_QA_OUT', '/workspace/fisitaap-qa/2026-10-07'))
OUT.mkdir(parents=True, exist_ok=True)
RESULTS = []
RUN = secrets.token_hex(4)

def php(code, data=''):
    result = subprocess.run(['docker', 'exec', '-i', WEB, 'php', '-r', code], input=data.encode(), capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stderr.decode()[:400])
    return result.stdout.decode()

def sql(query, args=None):
    code = BOOT + "$d=json_decode(stream_get_contents(STDIN),true);$s=$app->db->prepare($d['sql']);$s->execute($d['args']);echo json_encode($s->fetchAll());"
    return json.loads(php(code, json.dumps({'sql': query, 'args': args or []})))

def request(actor, path, data=None, raw=None, content_type='application/x-www-form-urlencoded', headers=False):
    command = ['docker', 'exec', '-i', WEB, 'curl', '--noproxy', '*', '-sS', '--max-time', '20',
               '-b', '/tmp/qa-' + RUN + '-' + actor + '.cookies', '-c', '/tmp/qa-' + RUN + '-' + actor + '.cookies', '-w', '\n%{http_code}']
    if headers:
        command.append('-i')
    payload = b''
    if data is not None or raw is not None:
        command += ['--data-binary', '@-', '-H', 'Content-Type: ' + content_type]
        payload = (raw if raw is not None else urllib.parse.urlencode(data, doseq=True)).encode()
    command.append('http://localhost' + path)
    response = subprocess.run(command, input=payload, capture_output=True, check=True).stdout
    body, status = response.rsplit(b'\n', 1)
    return int(status), body.decode()

def csrf(actor, path):
    status, body = request(actor, path)
    assert status == 200, f'GET {path}: {status}'
    token = re.search(r'name="csrf" value="([a-f0-9]+)"', body) or re.search(r'csrf:"([a-f0-9]+)"', body)
    assert token, 'CSRF field missing'
    return token[1]

def post(actor, path, data, headers=False):
    return request(actor, path, {'csrf': csrf(actor, path), **data}, headers=headers)

def login(actor, email):
    assert post(actor, '/owner-login', {'email': email, 'password': PASSWORD})[0] == 302
    target = '/master' if email.startswith('master@') else '/driver' if email.startswith('driver@') else '/admin'
    assert request(actor, target)[0] == 200, 'Login did not establish an authenticated session'

def case(name, callback):
    selected = os.environ.get('FISITAAP_QA_CASE')
    if selected and selected not in name:
        return
    started = time.monotonic()
    try:
        callback()
        result = {'case': name, 'status': 'passed'}
    except Exception as error:
        location = traceback.extract_tb(error.__traceback__)[-1]
        result = {'case': name, 'status': 'failed', 'detail': str(error)[:400] or type(error).__name__, 'line': location.lineno}
    result['seconds'] = round(time.monotonic() - started, 3)
    RESULTS.append(result)
    (OUT / 'web-deep.json').write_text(json.dumps(RESULTS, ensure_ascii=False, indent=2))
    print(result['status'].upper(), name, result.get('detail', ''), result.get('line', ''), flush=True)

def page(actor, path, expected=200):
    status, body = request(actor, path)
    assert status == expected, f'HTTP {status}, expected {expected}'
    assert not re.search(r'<b>(?:Fatal error|Warning|Notice)</b>|SQLSTATE\[', body), 'PHP/SQL error in response'

# Start independent runs with fresh counters only in the disposable QA database.
sql('DELETE FROM rate_limits')
login('owner', 'owner@r2.invalid')
login('ownerb', 'ownerb@r2.invalid')
login('master', 'master@r2.invalid')
login('cashier', 'cashiera@r2.invalid')
php(BOOT + "$d=json_decode(stream_get_contents(STDIN),true);$hash=password_hash($d['password'],PASSWORD_BCRYPT);foreach(['manager','editor','kitchen','driver'] as $i=>$role){$s=$app->db->prepare('INSERT INTO users(id,tenant_id,name,email,password_hash,role) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),is_active=1');$s->execute([2200+$i,$role==='driver'?null:101,'QA '.$role,$role.'@deep.invalid',$hash,$role]);}", json.dumps({'password': PASSWORD}))
for role in ['manager', 'editor', 'kitchen', 'driver']:
    login(role, role + '@deep.invalid')

def clock_consistency():
    clocks = json.loads(php(BOOT + "echo json_encode(['php'=>date('Y-m-d H:i:s'),'day'=>date('Y-m-d'),'sql'=>$app->one('SELECT NOW() value,CURDATE() day,@@session.time_zone zone')]);"))
    assert clocks['day'] == clocks['sql']['day'], 'Daily reports and the application use different calendar dates'
    from datetime import datetime
    delta = abs((datetime.fromisoformat(clocks['php']) - datetime.fromisoformat(clocks['sql']['value'])).total_seconds())
    assert delta <= 2 and clocks['sql']['zone'] == '-06:00', 'Database writes use a different clock from the Costa Rica application'
case('PHP and database clocks agree for sales, expiry and daily reports', clock_consistency)

owner_pages = ['', 'sucursales', 'pedidos', 'cocina', 'ventas', 'cobro', 'express-pendientes', 'turnos', 'inventario', 'compras', 'cxc', 'cxp', 'repartidor', 'express', 'productos', 'opciones', 'categorias', 'delivery', 'cupones', 'clientes', 'fidelizacion', 'contenido', 'blog', 'contactos', 'reportes', 'usuarios', 'auditoria', 'configuracion', 'impresion', 'roles', 'extras', 'opcionales', 'combos', 'promociones', 'clientes-crear', 'clientes-buscar', 'clientes-historial', 'credito', 'proveedores', 'kpis', 'mesas', 'afiliados', 'invitaciones', 'entregas-historial', 'escritorio']
for section in owner_pages:
    path = '/admin' + ('/' + section if section else '')
    case('owner screen ' + path, lambda path=path: page('owner', path))
for section in ['', 'empresas', 'planes', 'reportes', 'blog', 'auditoria', 'qa', 'cache', 'modules', 'configuracion', 'contenido', 'contenido-anterior']:
    path = '/master' + ('/' + section if section else '')
    case('master screen ' + path, lambda path=path: page('master', path))
for path in ['/', '/prueba-a', '/prueba-a/menu', '/prueba-a/blog', '/prueba-a/contacto', '/login', '/owner-login', '/register', '/driver-register', '/forgot-password', '/reset-password?token=invalid', '/verify-email?token=invalid', '/demo', '/robots.txt', '/sitemap.xml', '/manifest.webmanifest', '/prueba-a/manifest.webmanifest', '/sw.js']:
    case('public screen ' + path, lambda path=path: page('anon', path))
case('public help requires authenticated access', lambda: page('anon', '/ayuda', 302))
for actor, path, expected in [('anon', '/admin', 302), ('anon', '/master', 302), ('owner', '/master', 403), ('cashier', '/admin/usuarios', 403), ('editor', '/admin/usuarios', 403), ('kitchen', '/admin/compras', 403), ('driver', '/admin', 302), ('ownerb', '/admin/sucursal-detalle?branch_id=301', 422), ('ownerb', '/admin/compras', 403), ('anon', '/api/pos-options?product=502', 403), ('owner', '/api/desktop/snapshot', 405)]:
    case(f'role isolation {actor} {path}', lambda actor=actor, path=path, expected=expected: page(actor, path, expected))

def ticket():
    key = secrets.token_hex(16)
    post('owner', '/admin/ventas', {'action': 'quick', 'open_key': key})
    identifier = sql('SELECT id FROM pos_tickets WHERE open_key=?', [key])[0]['id']
    path = '/admin/ventas?screen=order&ticket=' + str(identifier)
    post('owner', path, {'action': 'add_item', 'revision': 0, 'product_id': 502, 'quantity': 1})
    return identifier, path

def payment_payload(identifier, key):
    revision = sql('SELECT revision FROM pos_tickets WHERE id=?', [identifier])[0]['revision']
    return {'payment_key': key, 'revisions[' + str(identifier) + ']': revision, 'customer_id': 0, 'split_mode': 'all', 'cash': 2000, 'card': 0, 'sinpe': 0, 'credit': 0}

def payment_key_scope():
    first, _ = ticket()
    second, _ = ticket()
    key = secrets.token_hex(16)
    first_path = '/admin/ventas?screen=checkout&ticket=' + str(first)
    post('owner', first_path, payment_payload(first, key))
    assert sql('SELECT status FROM pos_tickets WHERE id=?', [first])[0]['status'] == 'paid'
    second_path = '/admin/ventas?screen=checkout&ticket=' + str(second)
    status, body = post('owner', second_path, payment_payload(second, key), headers=True)
    assert status != 302 or 'Location: http://localhost/admin/recibo?venta=' not in body, 'A different account incorrectly returns the receipt of an old payment'
    assert sql('SELECT status FROM pos_tickets WHERE id=?', [second])[0]['status'] != 'paid'
case('POS idempotency key cannot confirm a different account', payment_key_scope)

def stock_pressure():
    identifiers = [ticket()[0] for _ in range(2)]
    # Both orders already reserve one unit. Reduce the remaining stock to one.
    before = sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity']
    sql('UPDATE product_inventory SET quantity=1 WHERE branch_id=301 AND product_id=502')
    try:
        login('owner2', 'owner@r2.invalid')
        tokens = [csrf(actor, '/admin/ventas') for actor in ['owner', 'owner2']]
        def add(index):
            actor = ['owner', 'owner2'][index]
            request(actor, '/admin/ventas?screen=order&ticket=' + str(identifiers[index]), {'csrf': tokens[index], 'action': 'add_item', 'revision': 1, 'product_id': 502, 'quantity': 1})
        with concurrent.futures.ThreadPoolExecutor(2) as workers:
            list(workers.map(add, range(2)))
        quantities = sql('SELECT SUM(quantity) amount FROM pos_ticket_items WHERE ticket_id IN (?,?)', identifiers)[0]['amount']
        assert float(quantities) == 3, 'Two requests oversold the last unit'
        assert float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity']) == 0
    finally:
        sql('UPDATE product_inventory SET quantity=? WHERE branch_id=301 AND product_id=502', [before])
case('PHP two simultaneous boxes reserve the last unit once', stock_pressure)

def payment_double_click():
    identifier, _ = ticket()
    payload = payment_payload(identifier, secrets.token_hex(16))
    path = '/admin/ventas?screen=checkout&ticket=' + str(identifier)
    actors = ['owner', 'owner2']
    tokens = [csrf(actor, '/admin/ventas') for actor in actors]
    with concurrent.futures.ThreadPoolExecutor(2) as workers:
        responses = list(workers.map(lambda i: request(actors[i], path, {'csrf': tokens[i], **payload}), range(2)))
    assert all(response[0] == 302 for response in responses)
    assert len(sql('SELECT id FROM sales WHERE payment_key=?', [payload['payment_key']])) == 1
case('PHP concurrent same payment creates a single sale', payment_double_click)

def invalid_json():
    for payload in ['42', '"wrong"', '{', 'null', '[]']:
        status, body = request('anon', '/api/checkout', raw=payload, content_type='application/json')
        assert status in [400, 403, 422], f'Invalid JSON {payload} returned HTTP {status}'
        assert '<b>Fatal error</b>' not in body, 'Uncaught type error on malformed input'
case('checkout handles malformed and scalar JSON', invalid_json)

def report_csv():
    sql('UPDATE users SET name=? WHERE id=206', ['=HYPERLINK("https://invalid.example","test")'])
    try:
        status, body = request('owner', '/admin/reportes?export=customers')
        assert status == 200
        assert "'=HYPERLINK" in body, 'Spreadsheet formula is executable'
    finally:
        sql('UPDATE users SET name="Cliente A" WHERE id=206')
case('CSV export protects spreadsheet formulas', report_csv)

def driver_terminal():
    uid = 2203
    sql('INSERT IGNORE INTO driver_profiles(user_id,vehicle_type,is_online) VALUES(?,"motorcycle",1)', [uid])
    oid = sql('SELECT id FROM orders WHERE tenant_id=101 AND status!="cancelled" LIMIT 1')[0]['id']
    # A completed fixture bypasses an external delivery but tests the actual guarded transition.
    sql('INSERT INTO delivery_jobs(tenant_id,branch_id,order_id,status,assigned_driver_id,delivery_pin_hash) VALUES(101,301,?,"delivered",?,?) ON DUPLICATE KEY UPDATE status="delivered",assigned_driver_id=VALUES(assigned_driver_id)', [oid, uid, '$2y$10$Ojn3fDPgGHi1K0kdQgfMJOVCnYZ3SGgyPKLYcs.rzGFVT/YGuwR0C'])
    job = sql('SELECT id FROM delivery_jobs WHERE order_id=? AND assigned_driver_id=?', [oid, uid])[0]['id']
    post('driver', '/driver', {'action': 'issue', 'job_id': job})
    assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job])[0]['status'] == 'delivered', 'A completed delivery can be reopened as an incident'
case('driver cannot reopen a completed delivery', driver_terminal)

def reset_concurrency():
    uid = 2202
    token = secrets.token_hex(32)
    sql('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))', [uid, hashlib.sha256(token.encode()).hexdigest()])
    path = '/reset-password?token=' + token
    actors = ['reset-a', 'reset-b']
    tokens = [csrf(actor, path) for actor in actors]
    # Block the user row so both requests must validate the reset token before either update completes.
    lock = subprocess.Popen(['docker', 'exec', '-i', 'fisitaap-db', 'mariadb', '-uroot', 'fisitaap_r2_test'], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    lock.stdin.write(f'BEGIN; SELECT id FROM users WHERE id={uid} FOR UPDATE; SELECT SLEEP(3); COMMIT;\n'.encode())
    lock.stdin.flush()
    time.sleep(.3)
    try:
        def reset(index):
            new_password = PASSWORD + str(index)
            return request(actors[index], path, {'csrf': tokens[index], 'token': token, 'password': new_password, 'confirm': new_password}, headers=True)
        with concurrent.futures.ThreadPoolExecutor(2) as workers:
            responses = list(workers.map(reset, range(2)))
        successful = sum('Location: http://localhost/login' in body for _, body in responses)
        assert successful == 1, f'One reset token changed the password {successful} times'
    finally:
        lock.stdin.close()
        lock.wait(timeout=10)
case('password reset token is atomic under concurrent use', reset_concurrency)

def session_revocation():
    sql('UPDATE users SET is_active=0 WHERE id=2201')
    try:
        assert request('editor', '/admin/productos')[0] == 302
    finally:
        sql('UPDATE users SET is_active=1 WHERE id=2201')
case('disabled staff loses an existing web session', session_revocation)

def express_lifecycle():
    sql('UPDATE users SET phone="88887777" WHERE id=206')
    key = secrets.token_hex(16)
    post('owner', '/admin/ventas?screen=express', {'action': 'express', 'open_key': key, 'customer_id': 206, 'delivery_address': 'Dirección ficticia QA'})
    identifier = sql('SELECT id FROM pos_tickets WHERE open_key=?', [key])[0]['id']
    path = '/admin/ventas?screen=order&ticket=' + str(identifier)
    post('owner', path, {'action': 'add_item', 'revision': 0, 'product_id': 502, 'quantity': 1})
    for state in ['preparing', 'ready', 'in_transit']:
        post('owner', '/admin/express-pendientes', {'ticket_id': identifier, 'state': state})
        assert sql('SELECT fulfillment_state FROM pos_tickets WHERE id=?', [identifier])[0]['fulfillment_state'] == state
    post('owner', '/admin/express-pendientes', {'ticket_id': identifier, 'state': 'delivered'})
    assert sql('SELECT fulfillment_state FROM pos_tickets WHERE id=?', [identifier])[0]['fulfillment_state'] == 'in_transit', 'Unpaid express was delivered'
    post('owner', '/admin/ventas?screen=checkout&ticket=' + str(identifier), payment_payload(identifier, secrets.token_hex(16)))
    post('owner', '/admin/express-pendientes', {'ticket_id': identifier, 'state': 'delivered'})
    assert sql('SELECT fulfillment_state,status FROM pos_tickets WHERE id=?', [identifier])[0] == {'fulfillment_state': 'delivered', 'status': 'paid'}
case('Express preparation, dispatch, blocked unpaid delivery, payment and delivery', express_lifecycle)

def master_business():
    slug = 'qa-' + secrets.token_hex(4)
    post('master', '/master/empresas', {'name': 'QA new business', 'slug': slug, 'whatsapp': '50688887777', 'catalog_label': 'Menú', 'physical_store_enabled': 1, 'admin_name': 'QA Owner', 'admin_email': slug + '@deep.invalid', 'admin_password': PASSWORD})
    business = sql('SELECT id FROM tenants WHERE slug=?', [slug])[0]['id']
    assert len(sql('SELECT id FROM branches WHERE tenant_id=? AND is_default=1', [business])) == 1
    user = sql('SELECT id,force_password_change FROM users WHERE tenant_id=?', [business])[0]
    assert user['force_password_change'] == 1
    post('master', '/master/planes', {'id': business, 'plan': 'Profesional', 'expires_at': '2027-12-31', 'is_active': 1})
    assert sql('SELECT expires_at FROM tenants WHERE id=?', [business])[0]['expires_at'] == '2027-12-31'
    post('master', '/master/empresas', {'action': 'toggle', 'id': business})
    _, body = request('anon', '/' + slug)
    assert 'cerrado' in body.lower() or 'temporalmente' in body.lower()
    post('master', '/master/empresas', {'action': 'toggle', 'id': business})
    page('anon', '/' + slug)
case('master creates a business, default branch, owner, subscription and suspension', master_business)

def branch_catalog():
    post('owner', '/admin/sucursales', {'action': 'mode', 'catalog_mode': 'branches'})
    try:
        post('owner', '/admin/sucursales', {'action': 'save_catalog', 'branch_id': 303, 'products[]': [502], 'price_override[502]': 777.77})
        assert float(sql('SELECT price_override FROM branch_products WHERE branch_id=303 AND product_id=502')[0]['price_override']) == 777.77
        before = sql('SELECT * FROM branch_products WHERE branch_id=302')
        post('owner', '/admin/sucursales', {'action': 'save_catalog', 'branch_id': 302, 'products[]': [502], 'price_override[502]': 1})
        assert sql('SELECT * FROM branch_products WHERE branch_id=302') == before
        post('owner', '/admin/sucursales', {'action': 'copy_catalog', 'source_branch': 303, 'target_branch': 301})
        assert float(sql('SELECT price_override FROM branch_products WHERE branch_id=301 AND product_id=502')[0]['price_override']) == 777.77
    finally:
        post('owner', '/admin/sucursales', {'action': 'mode', 'catalog_mode': 'global'})
case('branch catalog prices, copy and rejection of a foreign branch', branch_catalog)

def product_crud():
    sku = 'QA-' + secrets.token_hex(4)
    form = {'name': 'Producto QA café', 'slug': sku.lower(), 'sku': sku, 'category_id': 401, 'price': 250.55, 'tax_rate': 13, 'cost': 120, 'min_qty': .25, 'qty_step': .25, 'decimals': 2, 'unit_label': 'kg', 'track_inventory': 1, 'initial_stock': 5, 'branch_id': 301, 'status': 'active'}
    post('owner', '/admin/productos', form)
    product = sql('SELECT id,price FROM products WHERE sku=? AND tenant_id=101', [sku])[0]
    assert float(product['price']) == 250.55
    assert float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=?', [product['id']])[0]['quantity']) == 5
    post('owner', '/admin/productos', {**form, 'id': product['id'], 'price': 280.66})
    assert float(sql('SELECT price FROM products WHERE id=?', [product['id']])[0]['price']) == 280.66
    old = sql('SELECT name,price FROM products WHERE id=503')[0]
    post('owner', '/admin/productos', {**form, 'id': 503, 'price': 1})
    assert sql('SELECT name,price FROM products WHERE id=503')[0] == old
    post('owner', '/admin/productos', {**form, 'sku': sku + '-bad', 'category_id': 402})
    assert not sql('SELECT id FROM products WHERE sku=?', [sku + '-bad'])
    post('owner', '/admin/inventario', {'branch_id': 301, 'product_id': product['id'], 'quantity': .5, 'direction': 'out', 'unit_cost': 120, 'notes': 'QA ajuste fraccionario'})
    assert float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=?', [product['id']])[0]['quantity']) == 4.5
    assert sql('SELECT after_quantity FROM inventory_movements WHERE product_id=? ORDER BY id DESC LIMIT 1', [product['id']])[0]['after_quantity'] == '4.500'
case('product creation/edit, fractional inventory and foreign IDs', product_crud)

def gift_concurrency():
    key = secrets.token_hex(16)
    post('owner', '/admin/fidelizacion', {'action': 'gift_card', 'issue_key': key, 'customer_id': 206, 'gift_amount': 1000, 'gift_expires': '2027-10-06'})
    card = sql('SELECT id,code FROM fisitaap_r3_gift_cards WHERE issue_key=?', [key])[0]
    identifiers = [ticket()[0], ticket()[0]]
    actors = ['owner', 'owner2']
    tokens = [csrf(actor, '/admin/ventas') for actor in actors]
    def pay(index):
        data = payment_payload(identifiers[index], secrets.token_hex(16))
        data.update({'csrf': tokens[index], 'customer_id': 206, 'gift_code': card['code'], 'gift_amount': 800, 'cash': 443.01})
        return request(actors[index], '/admin/ventas?screen=checkout&ticket=' + str(identifiers[index]), data)
    with concurrent.futures.ThreadPoolExecutor(2) as workers:
        responses = list(workers.map(pay, range(2)))
    assert sum(status == 302 for status, _ in responses) == 1, 'Both boxes spent the same gift balance'
    assert sql('SELECT balance FROM fisitaap_r3_gift_cards WHERE id=?', [card['id']])[0]['balance'] == '200.00'
case('two web boxes cannot spend the same gift balance', gift_concurrency)

def invalid_payments():
    identifier, _ = ticket()
    path = '/admin/ventas?screen=checkout&ticket=' + str(identifier)
    for changes in [{'cash': -1}, {'cash': 'NaN'}, {'cash': 0}, {'card': 2000}, {'credit': 1243.01}, {'customer_id': 207}]:
        key = secrets.token_hex(16)
        post('owner', path, {**payment_payload(identifier, key), **changes})
        assert not sql('SELECT id FROM sales WHERE payment_key=?', [key]), 'Invalid payment was persisted'
    assert sql('SELECT status FROM pos_tickets WHERE id=?', [identifier])[0]['status'] != 'paid'
case('negative, incomplete, excessive, unauthorized credit and foreign customer payments', invalid_payments)

def order_cancel_stock():
    data = {'csrf': csrf('shopping', '/prueba-a/menu'), 'tenant': 'prueba-a', 'branch_id': 301, 'guest_name': 'Cliente compra QA', 'guest_phone': '88885555', 'checkout_key': secrets.token_hex(16), 'items': [{'id': 502, 'qty': 1, 'option_ids': []}], 'payment': 'Efectivo', 'delivery_type': 'pickup'}
    before = float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity'])
    status, text = request('shopping', '/api/checkout', raw=json.dumps(data), content_type='application/json')
    out = json.loads(text)
    assert status == 200 and out['ok']
    assert float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity']) == before - 1
    for _ in range(2):
        post('owner', '/admin/pedidos', {'action': 'status', 'id': out['order_id'], 'status': 'cancelled'})
    assert float(sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity']) == before
case('web order and repeated cancellation restore inventory once', order_cancel_stock)

def image_validation():
    result = php(BOOT + "require '/var/www/html/app/admin.php';$p=tempnam(sys_get_temp_dir(),'qaimg');file_put_contents($p,'<?php echo 1;');try{store_uploaded_image_v12($p,'image/png',20);echo 'accepted';}catch(Throwable $e){echo 'rejected';}finally{unlink($p);}")
    assert result == 'rejected'
case('uploaded code disguised as an image is rejected', image_validation)

def customer_search_xss():
    name = '<script>QA_SENTINEL</script>' + secrets.token_hex(4)
    post('owner', '/admin/clientes-crear', {'action': 'create', 'name': name, 'phone': '88886666'})
    _, body = request('owner', '/admin/clientes-buscar?q=' + urllib.parse.quote('QA_SENTINEL'))
    assert '<script>QA_SENTINEL</script>' not in body
    assert 'QA_SENTINEL' in body
    _, body = request('owner', '/admin/clientes-buscar?q=' + urllib.parse.quote("' OR 1=1 --"))
    assert 'SQLSTATE' not in body
case('customer creation/search escapes HTML and treats SQL text as text', customer_search_xss)

def quantity_precision():
    sku = 'QA-PRECISION-' + secrets.token_hex(4)
    form = {'name': sku, 'slug': sku.lower(), 'sku': sku, 'category_id': 401, 'price': 1000, 'tax_rate': 13, 'min_qty': .0001, 'qty_step': .0001, 'decimals': 4, 'unit_label': 'kg', 'status': 'active'}
    post('owner', '/admin/productos', form)
    assert not sql('SELECT id FROM products WHERE sku=?', [sku]), 'A quantity increment was silently rounded to zero in storage'
case('product quantities cannot exceed the precision of inventory storage', quantity_precision)

def temporary_password():
    email = 'temporary-' + secrets.token_hex(4) + '@deep.invalid'
    php(BOOT + "$d=json_decode(stream_get_contents(STDIN),true);$app->exec('INSERT INTO users(tenant_id,name,email,password_hash,role,force_password_change) VALUES(101,\"Temporary QA\",?,?,\"tenant_admin\",1)',[$d['email'],password_hash($d['password'],PASSWORD_BCRYPT)]);", json.dumps({'email': email, 'password': PASSWORD}))
    post('temporary', '/owner-login', {'email': email, 'password': PASSWORD})
    status, body = request('temporary', '/admin/productos', headers=True)
    assert status == 302 and 'Location: http://localhost/change-password' in body, 'Temporary password can access administration before being changed'
    post('temporary', '/change-password', {'password': PASSWORD + 'new', 'confirm': PASSWORD + 'new'})
    page('temporary', '/admin/productos')
case('temporary password must be changed before using business functions', temporary_password)

def waiting_sale():
    identifier, path = ticket()
    quantity = sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity']
    revision = sql('SELECT revision FROM pos_tickets WHERE id=?', [identifier])[0]['revision']
    post('owner', path, {'action': 'suspend', 'revision': revision})
    assert sql('SELECT status FROM pos_tickets WHERE id=?', [identifier])[0]['status'] == 'suspended'
    _, body = request('owner', '/admin/mesas')
    assert 'Ventas en espera' in body and 'Cobrar cuentas' in body
    _, body = request('owner', '/admin/ventas?waiting=1')
    assert 'ticket=' + str(identifier) + '"' in body and 'En espera' in body
    revision = sql('SELECT revision FROM pos_tickets WHERE id=?', [identifier])[0]['revision']
    post('owner', path, {'action': 'resume', 'revision': revision})
    assert sql('SELECT status FROM pos_tickets WHERE id=?', [identifier])[0]['status'] == 'open'
    assert sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity'] == quantity, 'Resuming a saved sale reserved its inventory twice'
case('saved sale is discoverable, resumes and keeps its inventory reservation', waiting_sale)

def delivery_workflow():
    email = 'driver-qa-' + secrets.token_hex(5) + '@deep.invalid'
    actor = 'driver-new'
    status, _ = post(actor, '/driver-register', {'name': 'Motorizado QA', 'email': email, 'phone': '88884444', 'password': PASSWORD, 'vehicle_type': 'motorcycle', 'identity_number': 'QA-0000'})
    assert status == 302
    uid = sql('SELECT id FROM users WHERE email=? AND role="driver"', [email])[0]['id']
    post(actor, '/owner-login', {'email': email, 'password': PASSWORD})
    page(actor, '/driver')
    post(actor, '/driver', {'action': 'profile', 'vehicle_type': 'bicycle', 'plate': '', 'identity_number': 'QA-0000', 'service_area': 'Zona de prueba', 'sinpe_phone': '88884444'})
    assert sql('SELECT vehicle_type FROM driver_profiles WHERE user_id=?', [uid])[0]['vehicle_type'] == 'bicycle'
    post(actor, '/driver', {'action': 'request_affiliation', 'store_code': 'prueba-a'})
    affiliation = sql('SELECT id,status FROM driver_affiliations WHERE user_id=? AND tenant_id=101', [uid])[0]
    assert affiliation['status'] == 'pending_store'
    post('owner', '/admin/express', {'action': 'affiliation_status', 'affiliation_id': affiliation['id'], 'status': 'active'})
    assert sql('SELECT status FROM driver_affiliations WHERE id=?', [affiliation['id']])[0]['status'] == 'active'
    post(actor, '/driver', {'action': 'toggle', 'online': 1})
    data = {'csrf': csrf('delivery-shopper', '/prueba-a/menu'), 'tenant': 'prueba-a', 'branch_id': 301, 'guest_name': 'Cliente entrega QA', 'guest_phone': '88883333', 'checkout_key': secrets.token_hex(16), 'items': [{'id': 502, 'qty': 1, 'option_ids': []}], 'payment': 'Efectivo', 'delivery_type': 'coordinated', 'address': 'Dirección ficticia QA'}
    status, text = request('delivery-shopper', '/api/checkout', raw=json.dumps(data), content_type='application/json')
    order = json.loads(text)
    assert status == 200 and order['ok']
    post('owner', '/admin/express', {'action': 'publish', 'order_id': order['order_id'], 'payout': 500})
    assert not sql('SELECT id FROM delivery_jobs WHERE order_id=?', [order['order_id']]), 'An unconfirmed order was published for delivery'
    for status in ['confirmed', 'preparing', 'ready']:
        post('owner', '/admin/pedidos', {'action': 'status', 'id': order['order_id'], 'status': status})
    post('owner', '/admin/express', {'action': 'publish', 'order_id': order['order_id'], 'payout': 500})
    _, body = request('owner', '/admin/express')
    pin = re.search(r'PIN de entrega: ([0-9]{4})', body)
    assert pin, 'The newly published delivery has no confirmation PIN'
    job = sql('SELECT id,status FROM delivery_jobs WHERE order_id=?', [order['order_id']])[0]
    assert job['status'] == 'available'
    original_hash = sql('SELECT delivery_pin_hash FROM delivery_jobs WHERE id=?', [job['id']])[0]['delivery_pin_hash']
    post('owner', '/admin/express', {'action': 'publish', 'order_id': order['order_id'], 'payout': 500})
    assert sql('SELECT delivery_pin_hash FROM delivery_jobs WHERE id=?', [job['id']])[0]['delivery_pin_hash'] == original_hash, 'Repeated publishing silently changed the PIN'
    request('ownerb', '/admin/express', {'csrf': csrf('ownerb', '/admin'), 'action': 'renew_pin', 'job_id': job['id']})
    assert sql('SELECT delivery_pin_hash FROM delivery_jobs WHERE id=?', [job['id']])[0]['delivery_pin_hash'] == original_hash
    post('owner', '/admin/express', {'action': 'renew_pin', 'job_id': job['id']})
    _, body = request('owner', '/admin/express')
    pin = re.search(r'PIN de entrega: ([0-9]{4})', body)
    assert pin and 'Compártelo con el cliente' in body
    assert sql('SELECT delivery_pin_hash FROM delivery_jobs WHERE id=?', [job['id']])[0]['delivery_pin_hash'] != original_hash
    post('driver', '/driver', {'action': 'accept', 'job_id': job['id']})
    assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job['id']])[0]['status'] == 'available', 'An unaffiliated driver accepted a delivery'
    post(actor, '/driver', {'action': 'accept', 'job_id': job['id']})
    assert int(sql('SELECT assigned_driver_id FROM delivery_jobs WHERE id=?', [job['id']])[0]['assigned_driver_id']) == int(uid)
    post(actor, '/driver', {'action': 'delivered', 'job_id': job['id'], 'delivery_pin': pin[1]})
    assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job['id']])[0]['status'] == 'assigned', 'Delivery skipped its required states'
    for action in ['arrived', 'picked_up', 'in_transit']:
        post(actor, '/driver', {'action': action, 'job_id': job['id']})
        assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job['id']])[0]['status'] == action
        post(actor, '/driver', {'action': 'issue', 'job_id': job['id']})
        assert sql('SELECT issue_previous_status FROM delivery_jobs WHERE id=?', [job['id']])[0]['issue_previous_status'] == action
        _, body = request(actor, '/driver')
        assert 'Contacta al negocio' in body and 'value="issue"' not in body
        request('ownerb', '/admin/express', {'csrf': csrf('ownerb', '/admin'), 'action': 'resolve_issue', 'job_id': job['id']})
        assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job['id']])[0]['status'] == 'issue'
        _, body = request('owner', '/admin/express')
        assert 'Resolver y continuar' in body
        post('owner', '/admin/express', {'action': 'resolve_issue', 'job_id': job['id']})
        assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job['id']])[0]['status'] == action
    wrong_pin = '0000' if pin[1] != '0000' else '9999'
    post(actor, '/driver', {'action': 'delivered', 'job_id': job['id'], 'delivery_pin': wrong_pin})
    assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job['id']])[0]['status'] == 'in_transit'
    for _ in range(2):
        post(actor, '/driver', {'action': 'delivered', 'job_id': job['id'], 'delivery_pin': pin[1]})
    assert sql('SELECT status FROM orders WHERE id=?', [order['order_id']])[0]['status'] == 'delivered'
    assert int(sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=?', [uid])[0]['completed_deliveries']) == 1
    assert len(sql('SELECT id FROM loyalty_ledger WHERE order_id=? AND event="earn"', [order['order_id']])) == 1, 'Fidelity points were lost or duplicated during delivery'
    post(actor, '/driver', {'action': 'issue', 'job_id': job['id']})
    assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job['id']])[0]['status'] == 'delivered'
    final_hash = sql('SELECT delivery_pin_hash FROM delivery_jobs WHERE id=?', [job['id']])[0]['delivery_pin_hash']
    post('owner', '/admin/express', {'action': 'publish', 'order_id': order['order_id'], 'payout': 500})
    post('owner', '/admin/express', {'action': 'renew_pin', 'job_id': job['id']})
    assert sql('SELECT delivery_pin_hash FROM delivery_jobs WHERE id=?', [job['id']])[0]['delivery_pin_hash'] == final_hash, 'A terminal delivery can be republished or its PIN changed'
case('driver registration, profile, affiliation, delivery states, PIN and duplicate delivery', delivery_workflow)

def published_delivery(actor):
    data = {'csrf': csrf(actor, '/prueba-a/menu'), 'tenant': 'prueba-a', 'branch_id': 301, 'guest_name': 'Cliente lógica QA', 'guest_phone': '88882222', 'checkout_key': secrets.token_hex(16), 'items': [{'id': 502, 'qty': 1, 'option_ids': []}], 'payment': 'Efectivo', 'delivery_type': 'coordinated', 'address': 'Dirección ficticia QA'}
    status, text = request(actor, '/api/checkout', raw=json.dumps(data), content_type='application/json')
    order = json.loads(text)
    assert status == 200 and order['ok']
    post('owner', '/admin/pedidos', {'action': 'status', 'id': order['order_id'], 'status': 'ready'})
    post('owner', '/admin/express', {'action': 'publish', 'order_id': order['order_id'], 'payout': 500})
    _, body = request('owner', '/admin/express')
    pin = re.search(r'PIN de entrega: ([0-9]{4})', body)[1]
    job = sql('SELECT id FROM delivery_jobs WHERE order_id=?', [order['order_id']])[0]['id']
    return order['order_id'], job, pin

def approved_driver():
    sql('INSERT INTO driver_affiliations(user_id,tenant_id,status,requested_by,all_branches) VALUES(2203,101,"active","driver",1) ON DUPLICATE KEY UPDATE status="active",all_branches=1')
    post('driver', '/driver', {'action': 'toggle', 'online': 1})

def cancelled_delivery():
    approved_driver()
    for phase in ['available', 'in_transit']:
        before = sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity']
        oid, job, pin = published_delivery('cancel-shopper-' + phase)
        if phase == 'in_transit':
            for action in ['accept', 'arrived', 'picked_up', 'in_transit']:
                post('driver', '/driver', {'action': action, 'job_id': job})
        post('owner', '/admin/pedidos', {'action': 'status', 'id': oid, 'status': 'cancelled'})
        assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job])[0]['status'] == 'cancelled', 'Cancelling the order left its delivery active or available'
        post('driver', '/driver', {'action': 'accept' if phase == 'available' else 'delivered', 'job_id': job, 'delivery_pin': pin})
        assert sql('SELECT status FROM orders WHERE id=?', [oid])[0]['status'] == 'cancelled'
        assert sql('SELECT quantity FROM product_inventory WHERE branch_id=301 AND product_id=502')[0]['quantity'] == before
    oid, job, pin = published_delivery('manual-delivery-shopper')
    post('driver', '/driver', {'action': 'accept', 'job_id': job})
    completed = int(sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=2203')[0]['completed_deliveries'])
    for _ in range(2):
        post('owner', '/admin/pedidos', {'action': 'status', 'id': oid, 'status': 'delivered'})
    assert sql('SELECT status FROM delivery_jobs WHERE id=?', [job])[0]['status'] == 'delivered', 'Manual completion left the driver blocked on an active delivery'
    post('driver', '/driver', {'action': 'delivered', 'job_id': job, 'delivery_pin': pin})
    assert int(sql('SELECT completed_deliveries FROM driver_profiles WHERE user_id=2203')[0]['completed_deliveries']) == completed + 1
case('cancelled web order stops delivery, releases driver and restores stock once', cancelled_delivery)

def driver_single_assignment():
    approved_driver()
    jobs = [published_delivery('assign-shopper-' + str(i))[1] for i in range(2)]
    try:
        login('assigner-2', 'owner@r2.invalid')
        actors = ['owner', 'assigner-2']
        tokens = [csrf(actor, '/admin/express') for actor in actors]
        with concurrent.futures.ThreadPoolExecutor(2) as workers:
            list(workers.map(lambda i: request(actors[i], '/admin/express', {'csrf': tokens[i], 'action': 'assign', 'job_id': jobs[i], 'driver_id': 2203}), range(2)))
        states = sql('SELECT status FROM delivery_jobs WHERE id IN (?,?) ORDER BY id', jobs)
        assert sorted(row['status'] for row in states) == ['assigned', 'available'], 'The same driver was assigned two simultaneous active deliveries'
    finally:
        # Release only the fictitious test assignments even on the original failing implementation.
        sql('UPDATE delivery_jobs SET status="cancelled" WHERE id IN (?,?)', jobs)
case('business cannot assign two simultaneous deliveries to one driver', driver_single_assignment)

for dataset in ['products', 'categories', 'options', 'combos', 'promotions', 'customers', 'sales', 'items', 'inventory', 'purchases', 'suppliers', 'shifts', 'cxc', 'cxp', 'delivery', 'loyalty', 'gifts', 'branches', 'users']:
    query = '?export=' + dataset if dataset in ['products', 'sales', 'items', 'inventory', 'purchases', 'cxc', 'cxp'] else '?dataset=' + dataset + '&csv=1'
    case('report export ' + dataset, lambda query=query: page('owner', '/admin/reportes' + query))

summary = {'passed': sum(r['status'] == 'passed' for r in RESULTS), 'failed': sum(r['status'] == 'failed' for r in RESULTS), 'cases': len(RESULTS)}
print('SUMMARY', json.dumps(summary), flush=True)
raise SystemExit(1 if summary['failed'] else 0)
