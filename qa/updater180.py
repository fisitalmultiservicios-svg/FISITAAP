"""Extracted ZIP installation and rollback, exclusively in a second fictional DB."""
import hashlib,json,os,pathlib,re,shutil,subprocess,sys,urllib.parse,zipfile
sys.path.insert(0,str(pathlib.Path(__file__).parent));from package180 import build
repo=pathlib.Path(__file__).resolve().parents[1];stage=pathlib.Path('/workspace/fisitaap-updater180-test');web='fisitaap-updater180-web';db='fisitaap_updater180_test';checks=[]
def run(args,data=None):return subprocess.run(args,input=data,capture_output=True,check=True).stdout
def php(code,data=None):return run(['docker','exec','-i',web,'php','-r',code],data)
def sql(q,args=()):return json.loads(php("require '/var/www/html/app/bootstrap.php';$d=json_decode(stream_get_contents(STDIN),true);$s=$app->db->prepare($d['sql']);$s->execute($d['args']);echo json_encode($s->fetchAll());",json.dumps({'sql':q,'args':list(args)}).encode()))
def request(actor,path,data=None):
    cmd=['docker','exec','-i',web,'curl','--noproxy','*','-sS','-b','/tmp/update-'+actor+'.cookies','-c','/tmp/update-'+actor+'.cookies','-w','\n%{http_code}'];payload=None
    if data is not None:cmd+=['--data-binary','@-'];payload=urllib.parse.urlencode(data).encode()
    body,status=run(cmd+['http://localhost'+path],payload).rsplit(b'\n',1);assert b'Fatal error' not in body;return int(status),body.decode()
def csrf(actor,path):
    status,body=request(actor,path);assert status==200,(path,status);return re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
def passed(x):checks.append(x);print('PASS',x,flush=True)
def main():
    build();stage.mkdir(exist_ok=True)
    if subprocess.run(['docker','inspect',web],capture_output=True).returncode:
        run(['docker','run','-d','--name',web,'--network','fisitaap-dev','-v',str(stage)+':/var/www/html','fisitaap-dev:php83'])
    else:
        container=json.loads(run(['docker','inspect',web]))[0];assert any(m.get('Source')==str(stage) and m.get('Destination')=='/var/www/html' for m in container['Mounts']);run(['docker','start',web])
    run(['docker','exec',web,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html'])
    stage.chmod(0o755)
    run(['docker','exec',web,'sed','-i','s/^export APACHE_RUN_USER.*/export APACHE_RUN_USER=#'+str(os.getuid())+'/;s/^export APACHE_RUN_GROUP.*/export APACHE_RUN_GROUP=#'+str(os.getgid())+'/','/etc/apache2/envvars'])
    info=json.loads(pathlib.Path('/workspace/fisitaap-env/fixture.json').read_text());password=info['password'];q=lambda s:"'"+s.replace('\\','\\\\').replace("'","\\'")+"'"
    config="<?php return ['db_host'=>'fisitaap-db','db_name'=>'"+db+"','db_user'=>'root','db_pass'=>'','app_url'=>'http://localhost','debug'=>false,'app_key'=>"+q(info['app_key'])+"];"
    dump=run(['docker','exec','fisitaap-db','mariadb-dump','-uroot','--single-transaction','--skip-comments','fisitaap_r2_test'])
    run(['docker','exec','fisitaap-db','mariadb','-uroot','-e','DROP DATABASE IF EXISTS '+db+';CREATE DATABASE '+db+' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;']);run(['docker','exec','-i','fisitaap-db','mariadb','-uroot',db],dump)
    for version in ['1.7.8','1.7.9-r2','mudanza-1.7.9-r3']:
        run(['docker','exec',web,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html'])
        for p in list(stage.iterdir()):
            if p.is_dir():shutil.rmtree(p)
            else:p.unlink()
        shutil.copytree(repo/'actualizaciones/mudanza-1.7.9-r3/web',stage,dirs_exist_ok=True);shutil.copytree(repo/'actualizaciones'/version/'web',stage,dirs_exist_ok=True);(stage/'config.php').write_text(config)
        with zipfile.ZipFile('/workspace/entregas/FISITAAP-1.8.0-CPANEL.zip') as archive:archive.extractall(stage)
        run(['docker','exec',web,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html']);run(['docker','exec',web,'chmod','-R','u+rwX,go+rX','/var/www/html']);run(['docker','exec',web,'chmod','755','/var/www/html']);run(['docker','restart',web])
        sql('UPDATE pos_shifts SET status="closed",closed_at=NOW() WHERE status="open"');sql('UPDATE pos_tickets SET status="void" WHERE status NOT IN("paid","void","merged")');sql('INSERT INTO settings(`key`,`value`) VALUES("qa180_active","0") ON DUPLICATE KEY UPDATE `value`="0"');run(['docker','exec',web,'sh','-c','rm -f /tmp/update-*.cookies'])
        for actor,email in [('master','master@r2.invalid'),('owner','owner@r2.invalid')]:assert request(actor,'/owner-login',{'csrf':csrf(actor,'/owner-login'),'email':email,'password':password})[0]==302
        path='/actualizador-180/';assert request('anon',path)[0]==302;assert request('owner',path)[0]==403
        for suffix in ['manifest.json','files.php','payload/app/schema180.php']:assert request('anon',path+suffix)[0]==403
        token=csrf('master',path);assert request('master',path,{'csrf':'','operation':'apply','backup':'yes'})[0]==403
        payload=stage/'actualizador-180/payload/app/schema180.php';saved=payload.read_bytes();payload.write_bytes(saved+b'\n// damaged\n');assert 'integridad' in request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})[1];assert not json.loads(php("echo json_encode(glob('/var/www/html/actualizador-180/backups/*/record.json'));"));payload.write_bytes(saved)
        core=stage/'app/core.php';before=core.read_bytes();core.write_bytes(before+b'\n// private modification\n');assert 'archivos modificados' in request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})[1];core.write_bytes(before)
        config_hash=hashlib.sha256((stage/'config.php').read_bytes()).hexdigest();status,body=request('master',path,{'csrf':token,'operation':'apply','backup':'yes'});assert status==200 and 'Archivos 1.8.0 instalados' in body,re.findall(r'<p class="notice error">(.*?)</p>',body)
        manifest=json.loads((stage/'actualizador-180/manifest.json').read_text());assert all(hashlib.sha256((stage/name).read_bytes()).hexdigest()==v['new'] for name,v in manifest['files'].items());assert hashlib.sha256((stage/'config.php').read_bytes()).hexdigest()==config_hash;assert sql('SELECT `value` FROM settings WHERE `key`="qa180_active"')[0]['value']=='0';assert request('owner','/admin')[0]==200
        records=json.loads(php("echo json_encode(glob('/var/www/html/actualizador-180/backups/*/record.json'));"));assert len(records)==1,records;private_status,private_body=request('anon',path+'backups/'+pathlib.Path(records[0]).parent.name+'/record.json');assert private_status==403,(private_status,private_body[:60])
        assert 'ya están instalados' in request('master',path,{'csrf':token,'operation':'apply','backup':'yes'})[1];assert len(json.loads(php("echo json_encode(glob('/var/www/html/actualizador-180/backups/*/record.json'));")))==1
        sql('UPDATE settings SET `value`="1" WHERE `key`="qa180_active"');assert 'ya se activó' in request('master',path,{'csrf':token,'operation':'rollback','backup':'yes'})[1];sql('UPDATE settings SET `value`="0" WHERE `key`="qa180_active"')
        assert 'anteriores restaurados' in request('master',path,{'csrf':token,'operation':'rollback','backup':'yes'})[1];assert core.read_bytes()==before;assert hashlib.sha256((stage/'config.php').read_bytes()).hexdigest()==config_hash
        passed(version+': extracted ZIP, master/CSRF, protected payload/backups, checksum and custom-file rejection, apply/retry, config preservation, rollback before activation and block after activation')
    pathlib.Path('actualizaciones/1.8.0/QA-ACTUALIZADOR.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'environment':'three prior file versions on independent fictional PHP/MariaDB database; no production'},indent=2)+'\n')
if __name__=='__main__':main()
