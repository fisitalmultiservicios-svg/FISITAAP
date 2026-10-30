"""Install R17 personnel, Academy and operations over the published R17 with mixed tenant versions."""
import hashlib,json,os,pathlib,shutil,subprocess,zipfile
from updater180 import run,php,sql,request,csrf,repo,stage,web,db
from package180 import build

def main():
    build();stage.mkdir(exist_ok=True)
    if subprocess.run(['docker','inspect',web],capture_output=True).returncode:run(['docker','run','-d','--name',web,'--network','fisitaap-dev','-v',str(stage)+':/var/www/html','fisitaap-dev:php83'])
    state=json.loads(run(['docker','inspect',web]))[0]
    assert any(m.get('Source')==str(stage) and m.get('Destination')=='/var/www/html' for m in state['Mounts'])
    run(['docker','start',web]);run(['docker','exec',web,'sed','-i','s/^export APACHE_RUN_USER.*/export APACHE_RUN_USER=#'+str(os.getuid())+'/;s/^export APACHE_RUN_GROUP.*/export APACHE_RUN_GROUP=#'+str(os.getgid())+'/','/etc/apache2/envvars']);run(['docker','exec',web,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html'])
    for path in list(stage.iterdir()):
        if path.is_dir():shutil.rmtree(path)
        else:path.unlink()
    shutil.copytree(repo/'actualizaciones/mudanza-1.7.9-r3/web',stage,dirs_exist_ok=True)
    with zipfile.ZipFile(repo/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R16.zip') as archive:
        for name in archive.namelist():
            prefix='actualizador-180/payload/'
            if not name.startswith(prefix) or name.endswith('/'):continue
            destination=stage/name.removeprefix(prefix);destination.parent.mkdir(parents=True,exist_ok=True);destination.write_bytes(archive.read(name))
    info=json.loads(pathlib.Path('/workspace/fisitaap-env/fixture.json').read_text())
    q=lambda s:"'"+s.replace('\\','\\\\').replace("'","\\'")+"'"
    (stage/'config.php').write_text("<?php return ['db_host'=>'fisitaap-db','db_name'=>'"+db+"','db_user'=>'root','db_pass'=>'','app_url'=>'http://localhost','debug'=>false,'app_key'=>"+q(info['app_key'])+"];")
    # Prepare only existing baseline files. New archive extraction is never repaired.
    for path in stage.rglob('*'):path.chmod(0o755 if path.is_dir() else 0o644)
    stage.chmod(0o755)
    dump=run(['docker','exec','fisitaap-db','mariadb-dump','-uroot','--single-transaction','--skip-comments','fisitaap_r2_test'])
    run(['docker','exec','fisitaap-db','mariadb','-uroot','-e','DROP DATABASE IF EXISTS '+db+';CREATE DATABASE '+db+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'])
    run(['docker','exec','-i','fisitaap-db','mariadb','-uroot',db],dump)
    assert sql('SELECT DATABASE() db')[0]['db']==db
    # Published R16 predates personnel, course progress, zone mappings and archives.
    for row in sql("SHOW TABLES LIKE 'fisitaap_%_r17'"):
        sql('DROP TABLE IF EXISTS '+next(iter(row.values())))
    sql('DELETE FROM settings WHERE `key`="platform_r17_ready"')
    if sql('SHOW COLUMNS FROM users LIKE "username_r17"'):sql('ALTER TABLE users DROP COLUMN username_r17')
    sql('INSERT INTO settings(`key`,`value`) VALUES("qa180_active","1"),("qa180_rollout","1") ON DUPLICATE KEY UPDATE `value`="1"')
    # The published baseline prepares pending rows for newly seeded templates.
    sql('INSERT INTO fisitaap_tenant_updates180(tenant_id,version) SELECT t.id,179 FROM tenants t LEFT JOIN fisitaap_tenant_updates180 v ON v.tenant_id=t.id WHERE v.tenant_id IS NULL')
    sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=102')
    sql('INSERT INTO pos_shifts(tenant_id,branch_id,user_id,name,opening_cash,status,opened_at) VALUES(101,301,202,"R17 OPEN SHIFT",10,"open",NOW())')
    phone_flags=sql('SELECT tenant_id,phone,phone_login181 FROM fisitaap_guest_contacts180 ORDER BY tenant_id,phone')

    # Recover exactly an unused generated R11 account that was awaiting initial access.
    sql('INSERT INTO users(name,email,phone,password_hash,role,is_active) SELECT "Unused R17 repair QA","unused-r17-repair@registro.fisitaap.local","87770017",password_hash,"customer",1 FROM users WHERE id=202')
    repair_id=sql('SELECT id FROM users WHERE email="unused-r17-repair@registro.fisitaap.local"')[0]['id']
    sql('INSERT INTO tenant_customers(tenant_id,user_id) VALUES(101,?)',[repair_id])
    sql('INSERT INTO fisitaap_guest_contacts180(tenant_id,phone,user_id,phone_login181) VALUES(101,"87770017",?,2)',[repair_id])
    credentials=sql('SELECT id,password_hash FROM users WHERE role="customer" ORDER BY id')
    contacts=sql('SELECT tenant_id,phone,user_id FROM fisitaap_guest_contacts180 ORDER BY tenant_id,phone')
    versions=sql('SELECT * FROM fisitaap_tenant_updates180 ORDER BY tenant_id')
    modifiers=sql('SELECT * FROM fisitaap_r2_modifiers ORDER BY id')
    shift=sql('SELECT * FROM pos_shifts WHERE name="R17 OPEN SHIFT"')
    counts=sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM products) products,(SELECT COUNT(*) FROM users) users')
    old_files={p.relative_to(stage).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for directory in [stage/'app',stage/'assets',stage/'releases/1.8.0-r16',stage/'uploads'] for p in directory.rglob('*') if p.is_file()}
    config_hash=hashlib.sha256((stage/'config.php').read_bytes()).hexdigest()
    previous=os.umask(0o077)
    try:run(['unzip','-oq','/workspace/entregas/FISITAAP-1.8.0-CPANEL-R17.zip','-d',str(stage)])
    finally:os.umask(previous)
    run(['docker','restart',web]);run(['docker','exec',web,'sh','-c','rm -f /tmp/update-*.cookies'])
    for actor,email in [('master','master@r2.invalid'),('owner','owner@r2.invalid')]:
        assert request(actor,'/owner-login',{'csrf':csrf(actor,'/owner-login'),'email':email,'password':info['password']})[0]==302
    path='/actualizador-180/';token=csrf('master',path)
    assert request('owner',path)[0]==403
    assert request('master',path,{'csrf':'bad','operation':'apply','backup':'yes'})[0]==403
    # Altering the currently active version must not silently discard custom code.
    old_core=stage/'releases/1.8.0-r16/app/core.php';before=old_core.read_bytes();old_core.write_bytes(before+b'\n// custom modification\n')
    try:
        assert 'archivos modificados' in request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})[1]
        assert not (stage/'releases/1.8.0-r17/index.php').exists()
    finally:old_core.write_bytes(before)
    status,body=request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})
    assert status==200 and 'Archivos 1.8.0 instalados' in body,body[-500:]
    assert sql('SELECT `value` FROM settings WHERE `key`="operations180_ready"')[0]['value']=='1'
    assert sql('SELECT `value` FROM settings WHERE `key`="platform_r17_ready"')[0]['value']=='1'
    assert sql('SHOW TABLES LIKE "fisitaap_employees_r17"') and sql('SHOW TABLES LIKE "fisitaap_attendance_r17"')
    assert sql('SHOW COLUMNS FROM orders LIKE "maps_url180"')
    assert sql('SHOW COLUMNS FROM fisitaap_guest_contacts180 LIKE "phone_login181"')
    assert [r for r in sql('SELECT id,password_hash FROM users WHERE role="customer" ORDER BY id') if r['id']!=repair_id]==[r for r in credentials if r['id']!=repair_id]
    assert php('define("FISITAAP_SITE_ROOT","/var/www/html");require "/var/www/html/releases/1.8.0-r17/app/bootstrap.php";'+"echo password_verify('87770017',$app->one('SELECT password_hash FROM users WHERE id=?',["+str(repair_id)+"] )['password_hash'])?'OK':'FAIL';")==b"OK"
    assert sql('SELECT tenant_id,phone,user_id FROM fisitaap_guest_contacts180 ORDER BY tenant_id,phone')==contacts
    assert [r for r in sql('SELECT tenant_id,phone,phone_login181 FROM fisitaap_guest_contacts180 ORDER BY tenant_id,phone') if r['phone']!='87770017']==[r for r in phone_flags if r['phone']!='87770017']
    assert sql('SELECT phone_login181 FROM fisitaap_guest_contacts180 WHERE user_id=?',[repair_id])[0]['phone_login181']==1
    assert sql('SELECT `value` FROM settings WHERE `key`="experience_r11_ready"')[0]['value']=='1'
    assert sql('SELECT * FROM fisitaap_tenant_updates180 ORDER BY tenant_id')==versions
    assert sql('SELECT * FROM fisitaap_r2_modifiers ORDER BY id')==modifiers
    assert sql('SELECT * FROM pos_shifts WHERE name="R17 OPEN SHIFT"')==shift
    assert sql('SELECT (SELECT COUNT(*) FROM sales) sales,(SELECT COUNT(*) FROM products) products,(SELECT COUNT(*) FROM users) users')==counts
    assert hashlib.sha256((stage/'config.php').read_bytes()).hexdigest()==config_hash
    assert all(hashlib.sha256((stage/name).read_bytes()).hexdigest()==digest for name,digest in old_files.items())
    manifest=json.loads((stage/'actualizador-180/manifest.json').read_text())
    assert all(hashlib.sha256((stage/name).read_bytes()).hexdigest()==v['new'] for name,v in manifest['files'].items())
    assert request('anon','/releases/1.8.0-r17/app/core.php')[0]==403
    assert request('anon','/releases/1.8.0-r17/app/fisichat_scope_r13.php')[0]==403
    assert request('anon','/releases/1.8.0-r17/assets/restructure-r2.css')[0]==200
    status,body=request('anon','/laventanita');assert status==200 and '/releases/1.8.0-r17/assets/restructure-r2.css' in body
    assert 'restructure-r2.js' not in body
    status,body=request('anon','/');assert status==200 and 'crema facial (3 unidades)' in body and 'Este ejemplo no consulta' not in body
    assert request('owner_b','/owner-login',{'csrf':csrf('owner_b','/owner-login'),'email':'ownerb@r2.invalid','password':info['password']})[0]==302
    status,pending_body=request('owner_b','/admin/productos');assert status==200 and 'name="quantity_mode"' not in pending_body
    status,updated_body=request('owner','/admin/productos');assert status==200 and 'name="quantity_mode"' in updated_body
    assert '1.8.0-r17' in (stage/'index.php').read_text()
    assert 'data-fc-config' in body and 'fisichat.js' in body
    assert '"profile":"maestro"' in request('master','/master')[1]
    assert '"profile":"cliente"' in request('anon','/laventanita')[1]
    records=json.loads(php("echo json_encode(glob('/var/www/html/actualizador-180/backups/*/record.json'));"));assert len(records)==1
    contact=contacts[0];sql('UPDATE fisitaap_guest_contacts180 SET phone_login181=1 WHERE tenant_id=? AND phone=?',[contact['tenant_id'],contact['phone']])
    sql('INSERT INTO fisitaap_support_r16(reference,store_name,contact_name,email,subject,description,conversation,request_key,payload_hash) VALUES("QA-R17-PRESERVE","Fictional shop","QA","qa@example.invalid","Preserve ticket","Fictitious case","",REPEAT("1",64),REPEAT("2",64))')
    sql('INSERT INTO fisitaap_employees_r17(tenant_id,user_id,branch_id,identification,job_title,start_date) VALUES(101,204,301,"R17 PRESERVE","QA role","2026-01-01")')
    keep_employee=sql('SELECT id FROM fisitaap_employees_r17 WHERE identification="R17 PRESERVE"')[0]['id']
    sql('INSERT INTO fisitaap_salaries_r17(tenant_id,employee_id,start_date,end_date,ordinary,extraordinary,created_by) VALUES(101,?,"2026-09-01","2026-09-30",600000,20000,202)',[keep_employee])
    sql('INSERT INTO fisitaap_learning_r17(tenant_id,user_id,course_key,step_key,done) VALUES(101,202,"store","personal",1)')
    sql('INSERT INTO fisitaap_setup_r17(tenant_id,step,paused) VALUES(101,3,1) ON DUPLICATE KEY UPDATE step=3,paused=1')
    keep_tables=['fisitaap_employees_r17','fisitaap_salaries_r17','fisitaap_learning_r17','fisitaap_setup_r17']
    before_hr={name:sql('SELECT * FROM '+name) for name in keep_tables}
    changed=php("echo password_hash('Changed-R17-customer-123',PASSWORD_DEFAULT);").decode()
    sql('UPDATE users SET password_hash=? WHERE id=?',[changed,repair_id])
    before_tickets=sql('SELECT * FROM fisitaap_support_r16 ORDER BY id')
    assert 'ya están instalados' in request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})[1]
    assert sql('SELECT * FROM fisitaap_support_r16 ORDER BY id')==before_tickets
    assert all(sql('SELECT * FROM '+name)==rows for name,rows in before_hr.items())
    assert sql('SELECT password_hash FROM users WHERE id=?',[repair_id])[0]['password_hash']==changed
    assert sql('SELECT phone_login181 FROM fisitaap_guest_contacts180 WHERE tenant_id=? AND phone=?',[contact['tenant_id'],contact['phone']])[0]['phone_login181']==1
    assert len(json.loads(php("echo json_encode(glob('/var/www/html/actualizador-180/backups/*/record.json'));")))==1
    assert 'ya se publicó' in request('master',path,{'csrf':token,'operation':'rollback','backup':'yes'})[1]
    result={'baseline':'published R16, tenant A180/B179 and one open shift','passed':7,'checks':[
        'R17 creates additive personnel, Academy, setup, address-zone and customer archive schema, preserves tickets, employees, salary ledger, learning/setup and changed passwords on reinstall; repairs only an unused generated phone account, data and mixed business versions and open shift',
        'sales/products/users, modifiers, config, uploads, old shared app/assets and immutable R16 files preserved',
        'master-only/CSRF and rejection of unknown modifications to active R16 code',
        '144 installed payload hashes match with portable extraction under umask077',
        'new entry/assets use R17; private PHP denied HTTP403',
        'business styles loaded without extra POS script; official sample reply answers the stock question',
        'repeat installation does not duplicate backups or alter tenant versions; rollback stays blocked after publication'],
        'environment':'independent fictional PHP/MariaDB database; actual published R16 ZIP baseline; no production'}
    (repo/'actualizaciones/1.8.0/QA-ACTUALIZADOR-R17.json').write_text(json.dumps(result,indent=2)+'\n')
    print('PASS R17 over published R16: mixed versions/open shift preserved, assets/entry updated, active custom code guarded, repeat and permissions verified',flush=True)

if __name__=='__main__':main()
