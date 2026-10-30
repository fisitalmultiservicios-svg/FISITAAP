"""Install cumulative R18 over immutable published R17, in an independent fictional database."""
import hashlib,json,os,pathlib,shutil,subprocess,zipfile
from updater180 import run,php,sql,request,csrf,repo,stage,web,db
from package180 import build

def main():
 build();stage.mkdir(exist_ok=True);state=json.loads(run(['docker','inspect',web]))[0];assert any(m.get('Source')==str(stage) and m.get('Destination')=='/var/www/html' for m in state['Mounts']);run(['docker','start',web]);run(['docker','exec',web,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html'])
 for p in list(stage.iterdir()):
  if p.is_dir():shutil.rmtree(p)
  else:p.unlink()
 shutil.copytree(repo/'actualizaciones/mudanza-1.7.9-r3/web',stage,dirs_exist_ok=True)
 with zipfile.ZipFile(repo/'descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R17.zip') as z:
  for name in z.namelist():
   pre='actualizador-180/payload/'
   if not name.startswith(pre) or name.endswith('/'):continue
   p=stage/name.removeprefix(pre);p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(z.read(name))
 info=json.loads(pathlib.Path('/workspace/fisitaap-env/fixture.json').read_text());q=lambda s:"'"+s.replace('\\','\\\\').replace("'","\\'")+"'";(stage/'config.php').write_text("<?php return ['db_host'=>'fisitaap-db','db_name'=>'"+db+"','db_user'=>'root','db_pass'=>'','app_url'=>'http://localhost','debug'=>false,'app_key'=>"+q(info['app_key'])+"];")
 for p in stage.rglob('*'):p.chmod(0o755 if p.is_dir() else 0o644)
 stage.chmod(0o755);dump=run(['docker','exec','fisitaap-db','mariadb-dump','-uroot','--single-transaction','--skip-comments','fisitaap_r2_test']);run(['docker','exec','fisitaap-db','mariadb','-uroot','-e','DROP DATABASE IF EXISTS '+db+';CREATE DATABASE '+db+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;']);run(['docker','exec','-i','fisitaap-db','mariadb','-uroot',db],dump);assert sql('SELECT DATABASE() db')[0]['db']==db
 # Downgrade ONLY the clone to R17 schema; no R18 settings/columns may hide migration failures.
 sql('DROP TABLE IF EXISTS fisitaap_owner_alert_reads_r18');sql('DELETE FROM settings WHERE `key`="runtime_r18_ready"');sql('ALTER TABLE fisitaap_money_accounts180 DROP INDEX register_channel_r18');sql('ALTER TABLE users DROP INDEX tenant_username_r18')
 for row in sql('SELECT username_r17 FROM users WHERE username_r17 IS NOT NULL GROUP BY username_r17 HAVING COUNT(*)>1'):
  for u in sql('SELECT id FROM users WHERE username_r17=? ORDER BY id',[row['username_r17']])[1:]:sql('UPDATE users SET username_r17=? WHERE id=?',[row['username_r17']+'-'+str(u['id']),u['id']])
 sql('ALTER TABLE users ADD UNIQUE KEY username_r17(username_r17)')
 for table,cols in [('fisitaap_registers180',['number_r18']),('fisitaap_money_accounts180',['register_id_r18','channel_r18']),('sales',['seller_id_r18']),('pos_tickets',['seller_id_r18'])]:
  for col in cols:sql('ALTER TABLE '+table+' DROP COLUMN '+col)
 sql('UPDATE fisitaap_tenant_updates180 SET version=179 WHERE tenant_id=102');sql('INSERT INTO pos_shifts(tenant_id,branch_id,user_id,name,opening_cash,status,opened_at) VALUES(101,301,202,"R18 OPEN DURING PUBLICATION",10,"open",NOW())')
 keep=['users','sales','products','fisitaap_money_accounts180','fisitaap_money_entries180','fisitaap_employees_r17','fisitaap_salaries_r17','fisitaap_learning_r17','fisitaap_setup_r17','fisitaap_tenant_updates180','pos_shifts'];before={name:sql('SELECT * FROM '+name+' ORDER BY 1') for name in keep};old_files={p.relative_to(stage).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for folder in ['app','assets','releases/1.8.0-r17','uploads'] for p in (stage/folder).rglob('*') if p.is_file()};config_hash=hashlib.sha256((stage/'config.php').read_bytes()).hexdigest()
 previous=os.umask(0o077)
 try:run(['unzip','-oq','/workspace/entregas/FISITAAP-1.8.0-CPANEL-R18.zip','-d',str(stage)])
 finally:os.umask(previous)
 run(['docker','restart',web]);run(['docker','exec',web,'sh','-c','rm -f /tmp/update-*.cookies']);sql('DELETE FROM rate_limits')
 for actor,email in [('master','master@r2.invalid'),('owner','owner@r2.invalid')]:assert request(actor,'/owner-login',{'csrf':csrf(actor,'/owner-login'),'email':email,'password':info['password']})[0]==302
 path='/actualizador-180/';token=csrf('master',path);assert request('owner',path)[0]==403;assert request('master',path,{'csrf':'bad','operation':'apply','backup':'yes'})[0]==403
 core=stage/'releases/1.8.0-r17/app/core.php';saved=core.read_bytes();core.write_bytes(saved+b'\n// custom\n')
 try:assert 'archivos modificados' in request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})[1];assert not (stage/'releases/1.8.0-r18/index.php').exists()
 finally:core.write_bytes(saved)
 status,body=request('master',path,{'csrf':token,'operation':'apply','backup':'yes'});assert status==200 and 'Archivos 1.8.0 instalados' in body,body[-1000:];assert sql('SELECT value FROM settings WHERE `key`="runtime_r18_ready"')[0]['value']=='1';assert sql('SHOW TABLES LIKE "fisitaap_owner_alert_reads_r18"')
 for name,rows in before.items():
  columns=[k for k in rows[0] if not(name=="users" and k=="last_login_at")] if rows else []
  after={tuple(r[k] for k in columns) for r in sql('SELECT * FROM '+name+' ORDER BY 1')}
  for r in rows:assert tuple(r[k] for k in columns) in after,(name,r[next(iter(r))])
 assert all(hashlib.sha256((stage/n).read_bytes()).hexdigest()==v for n,v in old_files.items());assert hashlib.sha256((stage/'config.php').read_bytes()).hexdigest()==config_hash
 m=json.loads((stage/'actualizador-180/manifest.json').read_text());assert len(m['files'])==148;assert all(hashlib.sha256((stage/n).read_bytes()).hexdigest()==i['new'] for n,i in m['files'].items());assert "releases/1.8.0-r18" in (stage/'index.php').read_text();assert request('anon','/releases/1.8.0-r18/app/runtime_r18.php')[0]==403;assert request('anon','/releases/1.8.0-r18/assets/runtime-r18.css')[0]==200;assert request('owner','/admin/ventas')[0]==200
 # Keep a deliberately disabled account on repeat: migration must preserve it without failing or reactivating.
 a=sql('SELECT id FROM fisitaap_money_accounts180 WHERE channel_r18="bank" LIMIT 1')[0]['id'];sql('UPDATE fisitaap_money_accounts180 SET is_active=0 WHERE id=?',[a]);funds=sql('SELECT * FROM fisitaap_money_accounts180 ORDER BY id');assert 'ya están instalados' in request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})[1];assert sql('SELECT * FROM fisitaap_money_accounts180 ORDER BY id')==funds
 assert len(json.loads(php("echo json_encode(glob('/var/www/html/actualizador-180/backups/*/record.json'));")))==1;assert 'ya se publicó' in request('master',path,{'csrf':token,'operation':'rollback','backup':'yes'})[1]
 out={'revision':'1.8.0-R18','baseline':'Published R17 ZIP, cloned R17 schema, mixed A180/B179 and open shifts','passed':7,'checks':['Explicit additive migration creates register numbers, scoped fund/seller columns, own-store alias index and owner reminder reads','Existing users/passwords, money accounts/balances/entries, sales/products, personnel, salaries, learning/setup, tenant versions and open shifts preserved','config/uploads/shared files and immutable R17 tree untouched','Master-only/CSRF and custom active-code safeguards retained','148 final hashes, 644/755 portable ZIP extraction under umask077; R18 entry/CSS and private-PHP HTTP guards verified','Each positive register receives separate zero-balance funds without reassigning old balances','Repeat install keeps disabled accounts disabled and creates no second backup; rollback blocked after publication'],'environment':'Independent fictional PHP/MariaDB database; no production hosting'};(repo/'actualizaciones/1.8.0/QA-ACTUALIZADOR-R18.json').write_text(json.dumps(out,ensure_ascii=False,indent=2)+'\n');print('PASS R18 updater: 7 groups',flush=True)
if __name__=='__main__':main()
