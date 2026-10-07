import pathlib,shutil,subprocess,json,secrets,urllib.parse,re,hashlib,os
ROOT=pathlib.Path('/workspace/fisitaap-r4-installer-test');SRC=pathlib.Path('/workspace/FISITAAP');WEB='fisitaap-r4-installer-web';db='fisitaap_r4_installer_test';password=secrets.token_urlsafe(20)
ROOT.mkdir(exist_ok=True);ROOT.chmod(0o755)
config="<?php return ['app_url'=>'http://localhost','app_key'=>'isolated-installer-test-key','db_host'=>'fisitaap-db','db_name'=>'"+db+"','db_user'=>'root','db_pass'=>'','debug'=>true];"
(ROOT/'config.php').write_text(config);(ROOT/'config.php').chmod(0o644)
exists=subprocess.run(['docker','inspect',WEB],capture_output=True).returncode==0
if not exists:subprocess.run(['docker','run','-d','--name',WEB,'--network','fisitaap-dev','-v',str(ROOT)+':/var/www/html','fisitaap-dev:php83'],check=True,capture_output=True)
subprocess.run(['docker','exec',WEB,'sed','-i','s/^export APACHE_RUN_USER.*/export APACHE_RUN_USER=#'+str(os.getuid())+'/;s/^export APACHE_RUN_GROUP.*/export APACHE_RUN_GROUP=#'+str(os.getgid())+'/','/etc/apache2/envvars'],check=True,capture_output=True)
BOOT="require '/var/www/html/app/bootstrap.php';"
def php(code,data=''):
 r=subprocess.run(['docker','exec','-i',WEB,'php','-r',code],input=data.encode(),capture_output=True)
 assert r.returncode==0,r.stderr.decode();return r.stdout.decode()
def sql(q,args=[]):return json.loads(php(BOOT+"$d=json_decode(stream_get_contents(STDIN),true);$s=$app->db->prepare($d['sql']);$s->execute($d['args']);echo json_encode($s->fetchAll());",json.dumps({'sql':q,'args':args})))
def request(actor,path,data=None):
 cmd=['docker','exec','-i',WEB,'curl','--noproxy','*','-sS','-b','/tmp/i-'+actor+'.cookies','-c','/tmp/i-'+actor+'.cookies','-w','\n%{http_code}'];body=b''
 if data is not None:cmd+=['--data-binary','@-'];body=urllib.parse.urlencode(data).encode()
 cmd+=['http://localhost'+path];r=subprocess.run(cmd,input=body,capture_output=True,check=True);out,status=r.stdout.rsplit(b'\n',1);out=out.decode();assert 'Fatal error' not in out,(path,out[-1200:]);return int(status),out
def record_names():return json.loads(php("echo json_encode(glob('/var/www/html/actualizacion-fisitaap-r4/backups/*/record.json'));"))
def csrf(actor,path):
 status,body=request(actor,path);assert status==200,(path,status,body[-1500:]);match=re.search(r'name="csrf" value="([a-f0-9]+)"',body);assert match;return match[1]
PATH='/actualizacion-fisitaap-r4/'
for version,base in [('original',pathlib.Path('/workspace/fisitaap-review')),('R1',pathlib.Path('/workspace/fisitaap-r1-baseline')),('R2',pathlib.Path('/workspace/fisitaap-r2-baseline')),('R3',pathlib.Path('/workspace/fisitaap-r3-baseline'))]:
 subprocess.run(['docker','exec',WEB,'rm','-rf','/var/www/html/actualizacion-fisitaap-r4'],check=True,capture_output=True)
 for name in ['app','assets','actualizacion-fisitaap-r4']:
  p=ROOT/name
  if p.exists():shutil.rmtree(p)
 for name in ['app','assets']:shutil.copytree(base/name,ROOT/name)
 for name in ['index.php','.htaccess']:
  (ROOT/name).unlink(missing_ok=True);shutil.copyfile(base/name if (base/name).exists() else SRC/name,ROOT/name)
 shutil.copytree(pathlib.Path('/workspace/fisitaap-r4-package-verification/actualizacion-fisitaap-r4'),ROOT/'actualizacion-fisitaap-r4')
 for p in ROOT.rglob('*'):
  if p.is_file():p.chmod(0o644)
  elif p.is_dir():p.chmod(0o755)
 subprocess.run(['docker','restart',WEB],check=True,capture_output=True)
 subprocess.run(['docker','exec',WEB,'sh','-c','rm -f /tmp/i-*.cookies'],check=True,capture_output=True)
 subprocess.run(['docker','exec','fisitaap-db','mariadb','-uroot','-e','DROP DATABASE IF EXISTS '+db+';CREATE DATABASE '+db+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'],check=True,capture_output=True)
 paths=[SRC/'database/schema.sql']+([SRC/'actualizacion-fisitaap/migration.sql'] if version in ['R1','R2','R3'] else [])
 for p in paths:subprocess.run(['docker','exec','-i','fisitaap-db','mariadb','-uroot',db],input=p.read_bytes(),capture_output=True,check=True)
 php(BOOT+"$p=json_decode(stream_get_contents(STDIN),true)['password'];$h=password_hash($p,PASSWORD_BCRYPT);$app->exec(\"INSERT INTO tenants(id,name,slug,physical_store_enabled) VALUES(101,'Instalación prueba','instalacion-prueba',1)\");$app->exec('INSERT INTO users(id,name,email,password_hash,role,tenant_id) VALUES(201,\"Maestro\",\"master@installer.invalid\",?,\"master\",NULL),(202,\"Dueño\",\"owner@installer.invalid\",?,\"tenant_admin\",101),(203,\"Caja\",\"cashier@installer.invalid\",?,\"cashier\",101)',[$h,$h,$h]);",json.dumps({'password':password}))
 if version in ['R1','R2','R3']:sql("INSERT INTO settings(`key`,`value`) VALUES('restructure_r1_active','1')");sql('INSERT INTO fisitaap_r1_roles(id,tenant_id,name) VALUES(1,101,"Anterior")');sql('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(1,"opciones","view"),(1,"opciones","write")');sql('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(101,203,1,"cashier")');sql('UPDATE users SET role="manager" WHERE id=203')
 if version in ['R2','R3']:
  php(BOOT+"require '/var/www/html/app/schema_r2.php';schema_r2($app);$app->exec(\"INSERT INTO settings(`key`,`value`) VALUES('restructure_r2_active','1')\");$app->exec(\"INSERT INTO branches(id,tenant_id,name,slug,is_default) VALUES(301,101,'Principal','principal',1)\");$app->exec(\"INSERT INTO fisitaap_r2_rooms(tenant_id,branch_id,name) VALUES(101,301,'Salón anterior')\");")
 if version=='R3':
  php(BOOT+"require '/var/www/html/app/schema_r3.php';schema_r3($app);$app->exec(\"INSERT INTO settings(`key`,`value`) VALUES('restructure_r3_active','1')\");")
 for actor in ['master','owner']:
  status,body=request(actor,'/owner-login',{'csrf':csrf(actor,'/owner-login'),'email':actor+'@installer.invalid','password':password});assert status==302,(status,body[-800:])
 assert request('anon',PATH)[0]==302
 assert request('owner',PATH)[0]==403
 for suffix in ['manifest.json','files.php','migration-r1.sql','payload/app/schema_r2.php']:assert request('anon',PATH+suffix)[0]==403,suffix
 token=csrf('master',PATH);assert request('master',PATH,{'csrf':'','operation':'apply','quiet_confirm':'yes'})[0]==403
 payload=ROOT/'actualizacion-fisitaap-r4/payload/app/schema_r3.php';saved=payload.read_bytes();payload.write_bytes(saved+b'\n// modified payload\n');status,body=request('master',PATH,{'csrf':token,'operation':'apply','quiet_confirm':'yes'});assert 'integridad' in body;assert not record_names();payload.write_bytes(saved)
 config_hash=hashlib.sha256((ROOT/'config.php').read_bytes()).hexdigest()
 old=hashlib.sha256((ROOT/'app/core.php').read_bytes()).hexdigest();(ROOT/'app/core.php').write_text((ROOT/'app/core.php').read_text()+'\n// changed independently\n')
 status,body=request('master',PATH,{'csrf':token,'operation':'apply','quiet_confirm':'yes'});assert 'Hay archivos diferentes' in body,body[-1800:];assert not record_names()
 shutil.copyfile(base/'app/core.php',ROOT/'app/core.php')
 status,body=request('master',PATH,{'csrf':token,'operation':'apply','quiet_confirm':'yes'});assert 'Actualización 1.7.4-QA instalada' in body,body[-1800:]
 assert sql('SELECT `value` FROM settings WHERE `key`="restructure_r3_active"')[0]['value']=='1'
 assert hashlib.sha256((ROOT/'config.php').read_bytes()).hexdigest()==config_hash
 assert sql('SELECT `value` FROM settings WHERE `key`="qa_174_active"')[0]['value']=='1'
 manifest=json.loads((ROOT/'actualizacion-fisitaap-r4/manifest.json').read_text())
 for name,info in manifest['files'].items():assert hashlib.sha256((ROOT/name).read_bytes()).hexdigest()==info['new'],name
 assert sql("SHOW COLUMNS FROM pos_tickets LIKE 'fulfillment_state'")
 assert sql("SHOW COLUMNS FROM sales LIKE 'payment_payload_hash'")
 assert request('owner','/admin/escritorio')[0]==200
 records=record_names();assert len(records)==1
 assert request('anon',PATH+'backups/'+pathlib.Path(records[0]).parent.name+'/record.json')[0]==403
 status,body=request('master',PATH,{'csrf':token,'operation':'apply','quiet_confirm':'yes'});assert 'ya está instalada' in body and len(record_names())==1
 if version in ['R1','R2','R3']:assert sql('SELECT role FROM users WHERE id=203')[0]['role']=='manager';assert sql('SELECT role_id FROM fisitaap_r1_user_roles WHERE user_id=203')[0]['role_id']==1;assert len(sql('SELECT * FROM fisitaap_r1_permissions WHERE section_key="extras"'))==2
 status,body=request('master',PATH,{'csrf':token,'operation':'rollback','quiet_confirm':'yes'});assert 'Archivos anteriores restaurados' in body,body[-1800:];assert hashlib.sha256((ROOT/'app/core.php').read_bytes()).hexdigest()==old;assert (ROOT/'app/restructure_r3.php').exists() == (version=='R3')
 assert sql('SELECT `value` FROM settings WHERE `key`="restructure_r3_active"')[0]['value']==('1' if version=='R3' else '0');assert sql('SELECT `value` FROM settings WHERE `key`="qa_174_active"')[0]['value']=='0'
 if version in ['R1','R2','R3']:assert sql('SELECT role_id FROM fisitaap_r1_user_roles WHERE user_id=203')[0]['role_id']==1;assert sql('SELECT role FROM users WHERE id=203')[0]['role']=='manager'
 status,body=request('master',PATH,{'csrf':token,'operation':'apply','quiet_confirm':'yes'});assert 'Actualización 1.7.4-QA instalada' in body,body[-1500:]
 sql('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(101,"table_view","graphic")')
 status,body=request('master',PATH,{'csrf':token,'operation':'rollback','quiet_confirm':'yes'});assert 'Ya se usaron funciones nuevas' in body and (ROOT/'app/restructure_r2.php').exists()
 print('PASS cumulative install from '+version+': authentication, integrity, secret folders, repeated apply, rollback, preserved roles and block after new data',flush=True)
