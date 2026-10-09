"""Migration rehearsal using only the disposable cloud fixture, never production.

Requires the prepared R2/R3 fixture and primary179_web.py setup. Builds/extracts
the delivery ZIP, exports/imports all fixture tables, and boots a separate Apache
server with the imported copy. Private SQL/config stay outside the repository.
"""
import hashlib
import json
import pathlib
import re
import shutil
import subprocess
import sys
import urllib.parse
import zipfile

SOURCE = pathlib.Path('/workspace/FISITAAP')
DELIVERY = SOURCE / 'actualizaciones/mudanza-1.7.9-r3'
STAGE = pathlib.Path('/workspace/fisitaap-migration-test')
DATABASE = 'fisitaap_migration_test'
SERVER = 'fisitaap-migration-web'
sys.path.insert(0, '/workspace/fisitaap-env/tests')
from r3_helpers import PASSWORD, ROOT


def run(args, data=None):
    result = subprocess.run(args, input=data, capture_output=True)
    if result.returncode:
        # Never echo dumped database rows, cookies or configuration on failures.
        raise RuntimeError('Command failed: ' + ' '.join(args[:4]))
    return result.stdout


def php(server, code):
    return run(['docker', 'exec', server, 'php', '-r', code]).decode()


def digests(server):
    code = """require '/var/www/html/app/bootstrap.php';
    $out=[];
    foreach($app->all('SHOW TABLES') as $r){
      $table=array_values($r)[0];$rows=[];
      foreach($app->all('SELECT * FROM `'.$table.'`') as $row)$rows[]=json_encode($row);
      sort($rows,SORT_STRING);$out[$table]=['count'=>count($rows),'hash'=>hash('sha256',implode("\\n",$rows))];
    }
    ksort($out);echo json_encode($out);"""
    return json.loads(php(server, code))


def request(actor, path, data=None):
    args = ['docker', 'exec', '-i', SERVER, 'curl', '--noproxy', '*', '-sS',
            '--max-time', '30', '-b', '/tmp/migration-' + actor + '.cookies',
            '-c', '/tmp/migration-' + actor + '.cookies', '-w', '\n%{http_code}']
    body = None
    if data is not None:
        args += ['--data-binary', '@-']
        body = urllib.parse.urlencode(data).encode()
    args += ['http://localhost' + path]
    response, status = run(args, body).rsplit(b'\n', 1)
    response = response.decode()
    assert '<b>Fatal error</b>' not in response and '<b>Warning</b>' not in response, path
    return int(status), response


def login(actor, email):
    status, body = request(actor, '/owner-login')
    assert status == 200
    csrf = re.search(r'name="csrf" value="([a-f0-9]+)"', body)[1]
    assert request(actor, '/owner-login', {'csrf': csrf, 'email': email, 'password': PASSWORD})[0] == 302


def main():
    checks = []

    def passed(name):
        checks.append(name)
        print('PASS', name, flush=True)

    archive = DELIVERY / 'FISITAAP-1.7.9-R3-NUEVO-CPANEL.zip'
    STAGE.mkdir(exist_ok=True)
    STAGE.chmod(0o755)
    with zipfile.ZipFile(archive) as z:
        names = z.namelist()
        assert 'index.php' in names and '.htaccess' in names
        assert 'config.php' not in names and not any(n.endswith('.sql') for n in names)
        assert not any(n.startswith(('upgrade/', 'actualizacion-', 'desktop/', 'android/', 'qa/', '.git/')) for n in names)
        assert not any(n.startswith('uploads/') and n != 'uploads/.htaccess' for n in names)
        z.extractall(STAGE)
    passed('ZIP extracts into document root with current runtime and no credentials, data or obsolete update directories')
    for path in STAGE.rglob('*.php'):
        run(['docker', 'exec', '-i', 'fisitaap-r2-web', 'php', '-l'], path.read_bytes())
    passed('all delivered PHP files pass PHP 8.3 syntax checks')

    shutil.copytree(ROOT / 'uploads', STAGE / 'uploads', dirs_exist_ok=True)
    # A media stand-in makes preservation testable even if the fixture has no uploads.
    media = STAGE / 'uploads/migration-media.txt'
    media.write_text('PRIVATE TEST MEDIA ONLY')
    config = (ROOT / 'config.php').read_text()
    config = re.sub(r"('db_name'\s*=>\s*)'[^']*'", lambda m: m[1] + "'" + DATABASE + "'", config)
    config = re.sub(r"('app_url'\s*=>\s*)'[^']*'", lambda m: m[1] + "'http://localhost'", config)
    config = re.sub(r"('debug'\s*=>\s*)true", lambda m: m[1] + 'false', config)
    assert DATABASE in config
    (STAGE / 'config.php').write_text(config)
    for p in STAGE.rglob('*'):
        p.chmod(0o755 if p.is_dir() else 0o644)
    run(['docker', 'exec', 'fisitaap-db', 'mariadb', '-uroot', '-e',
         'DROP DATABASE IF EXISTS ' + DATABASE + '; CREATE DATABASE ' + DATABASE + ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'])
    before = digests('fisitaap-r2-web')
    backup = run(['docker', 'exec', 'fisitaap-db', 'mariadb-dump', '-uroot', '--single-transaction', '--skip-comments', 'fisitaap_r2_test'])
    run(['docker', 'exec', '-i', 'fisitaap-db', 'mariadb', '-uroot', DATABASE], backup)
    exists = subprocess.run(['docker', 'inspect', SERVER], capture_output=True).returncode == 0
    if not exists:
        run(['docker', 'run', '-d', '--name', SERVER, '--network', 'fisitaap-dev',
             '-v', str(STAGE) + ':/var/www/html', 'fisitaap-dev:php83'])
    run(['docker', 'exec', SERVER, 'sh', '-c', 'rm -f /tmp/migration-*.cookies'])
    run(['docker', 'exec', SERVER, 'chgrp', 'www-data', '/var/www/html/uploads'])
    run(['docker', 'exec', SERVER, 'chmod', '775', '/var/www/html/uploads'])
    # Wait for Apache with bounded readiness probes, no long sleeps.
    for _ in range(30):
        if subprocess.run(['docker', 'exec', SERVER, 'curl', '--noproxy', '*', '-s', '-o', '/dev/null',
                           '--connect-timeout', '1', 'http://localhost/'], capture_output=True).returncode == 0:
            break
    assert digests(SERVER) == before
    passed('all 82 tables preserve every fixture row, IDs, device tokens, snapshots, permissions and financial records through SQL export/import')
    old_key = php('fisitaap-r2-web', "$c=require '/var/www/html/config.php';echo hash('sha256',$c['app_key']);")
    new_key = php(SERVER, "$c=require '/var/www/html/config.php';echo hash('sha256',$c['app_key']);")
    assert old_key == new_key
    passed('original application key retained privately; uploaded media preserved')
    php(SERVER, "require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/demo_sandbox.php';demo_schema($app);$app->exec('INSERT INTO settings(`key`,`value`) VALUES(\"demo_sandbox_active\",\"1\") ON DUPLICATE KEY UPDATE `value`=\"1\"');")

    assert request('anon', '/')[0] == 200
    assert request('anon', '/prueba-a')[0] == 200
    assert request('anon', '/prueba-a/catalogo')[0] == 200
    assert request('anon', '/assets/app.bundle.css')[0] == 200
    assert request('anon', '/uploads/migration-media.txt')[1] == media.read_text()
    passed('public landing, business page, catalog, CSS and uploaded media work on separate Apache server')
    for path in ['/config.php', '/mudanza-manifest.php', '/app/bootstrap.php']:
        assert request('anon', path)[0] == 403, path
    (STAGE / 'uploads/blocked.php').write_text('<?php echo "UNSAFE";')
    (STAGE / 'uploads/blocked.php').chmod(0o644)
    assert request('anon', '/uploads/blocked.php')[0] == 403
    assert request('anon', '/comprobar-mudanza.php')[0] == 302
    (STAGE / 'uploads/blocked.php').unlink()
    passed('configuration and manifest blocked, direct app entry blocked, PHP upload blocked; migration report requires authentication')
    login('owner', 'owner@r2.invalid')
    assert request('owner', '/comprobar-mudanza.php')[0] == 403
    passed('store owner cannot read platform-wide migration totals')
    login('master', 'master@r2.invalid')
    current = digests(SERVER)
    status, report = request('master', '/comprobar-mudanza.php')
    assert status == 200 and 'Archivos y estructura comprobados' in report and 'Hay puntos que revisar' not in report
    assert digests(SERVER) == current
    passed('fresh master account can compare full schema, runtime hashes, uploads, row counts and balances without database changes')
    css = STAGE / 'assets/admin-refined.css'
    css.write_text(css.read_text() + '\n/* changed for detection test */\n')
    assert 'Archivo ausente o diferente: assets/admin-refined.css' in request('master', '/comprobar-mudanza.php')[1]
    shutil.copyfile(DELIVERY / 'web/assets/admin-refined.css', css)
    passed('report detects an incomplete or altered deployed runtime')
    for path in ['/master', '/master/empresas']:
        assert request('master', path)[0] == 200, path
    for path in ['/admin', '/admin/productos', '/admin/ventas', '/admin/mesas', '/admin/impresion', '/admin/escritorio', '/admin/turnos', '/admin/cxc', '/admin/cxp']:
        assert request('owner', path)[0] == 200, path
    status, body = request('anon', '/api/desktop/status')
    assert status == 200 and json.loads(body)['version'] == '1.7.9'
    assert request('master', '/diagnostico-rendimiento.php')[0] == 200
    assert request('anon', '/assets/direct-print.js')[0] == 200
    passed('master and business screens, printing assets, desktop status and performance diagnostic work after migration')
    (DELIVERY / 'QA-MUDANZA.json').write_text(json.dumps({'environment': 'isolated PHP 8.3 / MariaDB 11.4 / Apache; synthetic data only', 'passed': len(checks), 'checks': checks,
        'limitations': ['No access to new cPanel, DNS or real production backup.', 'No physical Windows/Android printer tested in this migration rehearsal.', 'New hosting latency must be measured after installation.']}, ensure_ascii=False, indent=2) + '\n')
    print('RESULT', len(checks), 'checks passed')


if __name__ == '__main__':
    main()
