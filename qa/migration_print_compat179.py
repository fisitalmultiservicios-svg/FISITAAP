"""Rehearse the cPanel report: legacy receipt fields absent from tenants.

Only the disposable migration database is changed. Its full synthetic dump is
kept in memory and restored in finally; the original fixture remains untouched.
"""
import json, re, zipfile
from migration179 import DATABASE, SERVER, DELIVERY, php, run, request, digests, login
from migration_demos179 import sql, csrf, post

LEGACY = ['receipt_width','receipt_printer_type','receipt_printer_name','receipt_printer_host','receipt_printer_port','receipt_autoprint','receipt_bridge_token']


def evaluate(code):
    return json.loads(php(SERVER, "require '/var/www/html/app/bootstrap.php';require ROOT_PATH.'/app/demo_sandbox.php';" + code))


def main():
    assert DATABASE == 'fisitaap_migration_test'
    assert sql('SELECT COUNT(*) n FROM fisitaap_demo_sessions')[0]['n'] == 0
    # Earlier image checks restore workspace ownership; prepare Apache's writable
    # upload root as migration179.py does before evaluating the schema report.
    run(['docker','exec',SERVER,'chgrp','www-data','/var/www/html/uploads'])
    run(['docker','exec',SERVER,'chmod','775','/var/www/html/uploads'])
    status, _ = request('master','/comprobar-mudanza.php')
    if status == 302:
        login('master', 'master@r2.invalid')
    else:
        assert status == 200
    original = digests(SERVER)
    source = digests('fisitaap-r2-web')
    backup = run(['docker','exec','fisitaap-db','mariadb-dump','-uroot','--single-transaction','--skip-comments',DATABASE])
    checks = []

    def passed(label):
        checks.append(label)
        print('PASS',label,flush=True)

    try:
        sql('ALTER TABLE tenants '+','.join('DROP COLUMN `'+column+'`' for column in LEGACY))
        missing_layout = digests(SERVER)
        status, report = request('master','/comprobar-mudanza.php')
        assert status == 200 and 'Archivos y estructura comprobados' in report and 'Faltan columnas en tenants' not in report
        assert digests(SERVER) == missing_layout
        passed('read-only checker accepts the reported tenant layout without adding columns or changing rows')

        # A demo must never carry an actual thermal printer destination into its copy.
        sql('UPDATE branches SET receipt_printer_type="network",receipt_printer_name="Demo fixture printer",receipt_printer_host="192.0.2.25",receipt_autoprint=1,receipt_bridge_token="SYNTHETIC_DEMO_ONLY",printer_points_json=? WHERE id=1301',[json.dumps({'receipt':{'type':'network','host':'192.0.2.25','autoprint':1}})])
        real_branch = sql('SELECT * FROM branches WHERE tenant_id=101 ORDER BY id')
        baseline = digests(SERVER)

        def opening(actor, kind, mode):
            status, body = request(actor,'/demo/iniciar',{'csrf':csrf(actor,'/demo'),'type':kind,'mode':mode})
            assert status == 200 and 'sessionStorage.setItem' in body, 'Opening demo failed in reported tenant layout.'
            return re.search(r'fisitaap-demo-tab",\s*"([a-f0-9]{32})"',body)[1]

        first = opening('print-compat-rest','restaurante','panel')
        second = opening('print-compat-shop','tienda','cliente')
        p1, p2 = '/demo/s/'+first, '/demo/s/'+second
        d1 = sql('SELECT tenant_id,owner_id,customer_id FROM fisitaap_demo_sessions WHERE token=?',[first])[0]
        d2 = sql('SELECT tenant_id FROM fisitaap_demo_sessions WHERE token=?',[second])[0]
        assert request('print-compat-rest',p1+'/admin')[0] == 200
        assert request('print-compat-shop',p2+'/demo-tienda-'+second+'/catalogo')[0] == 200
        passed('restaurant panel and store catalog demos both open through their public routes without legacy tenant fields')
        columns = [r['Field'] for r in sql('SHOW COLUMNS FROM tenants')]
        assert not set(LEGACY)&set(columns)
        for row in sql('SELECT receipt_printer_type,receipt_printer_name,receipt_printer_host,receipt_autoprint,receipt_bridge_token,printer_points_json FROM branches WHERE tenant_id IN(?,?)',[d1['tenant_id'],d2['tenant_id']]):
            assert row == {'receipt_printer_type':'browser','receipt_printer_name':None,'receipt_printer_host':None,'receipt_autoprint':0,'receipt_bridge_token':None,'printer_points_json':None}
        assert sql('SELECT * FROM branches WHERE tenant_id=101 ORDER BY id') == real_branch
        passed('demo printers are disabled/reset by branch while real printer configuration and the clean tenant structure remain unchanged')

        product = sql('SELECT id FROM products WHERE tenant_id=? AND sku="DEMO1"',[d1['tenant_id']])[0]['id']
        # Use the same real POS actions exercised by the regular demo integration suite.
        sale_path = p1+'/admin/ventas'
        assert post('print-compat-rest',p1+'/admin/turnos',{'action':'open','name':'Turno compatibilidad','opening_cash':'0'})[0] == 302
        assert post('print-compat-rest',sale_path,{'action':'quick','open_key':'f'*32})[0] == 302
        ticket = sql('SELECT id FROM pos_tickets WHERE tenant_id=? AND open_key=?',[d1['tenant_id'],'f'*32])[0]['id']
        assert post('print-compat-rest',sale_path+'?screen=order&ticket='+str(ticket),{'action':'add_item','revision':'0','product_id':product,'quantity':'1'})[0] == 302
        before_payment = sql('SELECT revision FROM pos_tickets WHERE id=?',[ticket])[0]
        charge_path = sale_path+'?screen=checkout&ticket='+str(ticket)
        payment = {'payment_key':'a'*32,'revisions['+str(ticket)+']':before_payment['revision'],'customer_id':d1['customer_id'],'split_mode':'all','cash':'5000','card':'0','sinpe':'0','credit':'0'}
        assert post('print-compat-rest',charge_path,payment)[0] == 302
        assert sql('SELECT status FROM pos_tickets WHERE id=?',[ticket]) == [{'status':'paid'}]
        assert sql('SELECT COUNT(*) n FROM sales WHERE tenant_id=?',[d1['tenant_id']])[0]['n'] == 1
        assert not sql('SELECT id FROM sales WHERE tenant_id=?',[d2['tenant_id']])
        passed('a real demo sale succeeds without legacy tenant fields and remains separate from the second demo')

        assert request('print-compat-rest',p1+'/logout',{'csrf':csrf('print-compat-rest',p1+'/admin')})[0] == 302
        assert request('print-compat-shop',p2+'/logout',{'csrf':csrf('print-compat-shop',p2+'/demo-tienda-'+second+'/catalogo')})[0] == 302
        after = digests(SERVER)
        assert {k:v for k,v in after.items() if k!='rate_limits'} == {k:v for k,v in baseline.items() if k!='rate_limits'}
        passed('discarding both demos restores baseline business rows, including sales, stock and printer settings; public request counters are allowed to change')

        sql('ALTER TABLE branches DROP COLUMN receipt_width')
        before_check = digests(SERVER)
        status, report = request('master','/comprobar-mudanza.php')
        assert status == 200 and 'Faltan columnas en branches: receipt_width' in report
        assert digests(SERVER) == before_check
        passed('checker still rejects a missing required branch printer field and remains read-only')

        with zipfile.ZipFile(DELIVERY/'FISITAAP-CORREGIR-MUDANZA-DEMOS.zip') as archive:
            assert set(archive.namelist()) == {'app/data_tools179.php','app/demo_sandbox.php','app/branches_v1.php','app/official_r3.php','app/core.php','app/browser_migration179.php','assets/app.js','index.php','comprobar-servidor.php','mudanza-manifest.php'}
            for name in archive.namelist():
                assert archive.read(name) == (DELIVERY/'web'/name).read_bytes()
        passed('cumulative corrective ZIP contains the reviewed demo/helper/manifest files and no private configuration or media')
    finally:
        run(['docker','exec','fisitaap-db','mariadb','-uroot','-e','DROP DATABASE IF EXISTS '+DATABASE+'; CREATE DATABASE '+DATABASE+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'])
        run(['docker','exec','-i','fisitaap-db','mariadb','-uroot',DATABASE],backup)
    assert digests(SERVER) == original and digests('fisitaap-r2-web') == source
    passed('disposable database is fully restored and the original laboratory fixture never changes')
    (DELIVERY/'QA-COMPATIBILIDAD-IMPRESION.json').write_text(json.dumps({'environment':'isolated PHP 8.3 / MariaDB 11.4; reported absent tenant fields, synthetic rows only','passed':len(checks),'checks':checks},indent=2)+'\n')


if __name__ == '__main__':
    main()
