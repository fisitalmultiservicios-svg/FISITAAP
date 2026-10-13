"""Create/start isolated development fixtures. No production configuration used."""
import json,os,pathlib,re,secrets,shutil,subprocess,sys,time
REPO=pathlib.Path(__file__).resolve().parents[1]
SOURCE=REPO/'actualizaciones/1.8.0/web'
ENV=pathlib.Path('/workspace/fisitaap-env')
STAGE=pathlib.Path('/workspace/fisitaap-r2-test')
def run(args,data=None):
    p=subprocess.run(args,input=data,capture_output=True)
    if p.returncode:raise RuntimeError('Setup failed: '+' '.join(args[:4])+'; '+p.stderr.decode(errors='replace')[:250])
    return p.stdout
def exists(name):return subprocess.run(['docker','inspect',name],capture_output=True).returncode==0
def main(reset=False):
    ENV.mkdir(exist_ok=True);(ENV/'tests').mkdir(exist_ok=True)
    if subprocess.run(['docker','network','inspect','fisitaap-dev'],capture_output=True).returncode:
        run(['docker','network','create','fisitaap-dev'])
    if not exists('fisitaap-db'):
        run(['docker','run','-d','--name','fisitaap-db','--network','fisitaap-dev','-e','MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=yes','mariadb:11.4@sha256:1292844148b311e4ed4300022a996d39083f415a963e970cf47cad1b3b18e3a6'])
    else:run(['docker','start','fisitaap-db'])
    for _ in range(40):
        if subprocess.run(['docker','exec','fisitaap-db','mariadb-admin','-uroot','ping'],capture_output=True).returncode==0:break
        time.sleep(.25)
    else:raise RuntimeError('Isolated database did not become ready')
    STAGE.mkdir(exist_ok=True);STAGE.chmod(0o755)
    shutil.copytree(SOURCE,STAGE,dirs_exist_ok=True)
    STAGE.chmod(0o755)
    state=ENV/'fixture.json'
    info=json.loads(state.read_text()) if state.exists() else {'password':secrets.token_hex(18),'app_key':secrets.token_hex(32),'web':'fisitaap-r2-web'}
    state.write_text(json.dumps(info));state.chmod(0o600)
    def q(s):return "'"+s.replace('\\','\\\\').replace("'","\\'")+"'"
    (STAGE/'config.php').write_text("<?php return ['db_host'=>'fisitaap-db','db_name'=>'fisitaap_r2_test','db_user'=>'root','db_pass'=>'','app_url'=>'http://localhost','debug'=>false,'app_key'=>"+q(info['app_key'])+"];")
    for p in STAGE.rglob('*'):p.chmod(0o755 if p.is_dir() else 0o644)
    if not exists('fisitaap-r2-web'):
        run(['docker','run','-d','--name','fisitaap-r2-web','--network','fisitaap-dev','-v',str(STAGE)+':/var/www/html','fisitaap-dev:php83'])
    else:run(['docker','start','fisitaap-r2-web'])
    run(['docker','exec','fisitaap-r2-web','chgrp','www-data','/var/www/html/uploads'])
    run(['docker','exec','fisitaap-r2-web','chmod','775','/var/www/html/uploads'])
    initialized=ENV/'initialized180'
    if reset or not initialized.exists():
        run(['docker','exec','fisitaap-db','mariadb','-uroot','-e','DROP DATABASE IF EXISTS fisitaap_r2_test;CREATE DATABASE fisitaap_r2_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'])
        run(['docker','exec','-i','fisitaap-db','mariadb','-uroot','fisitaap_r2_test'],(REPO/'qa/fixtures/base_schema.sql').read_bytes())
        migrations="""require '/var/www/html/app/bootstrap.php';require '/var/www/html/app/admin.php';require '/var/www/html/app/v1.php';require '/var/www/html/app/admin_v1.php';require '/var/www/html/app/branches_v1.php';require '/var/www/html/app/modules_v12.php';require '/var/www/html/app/physical_v14.php';require '/var/www/html/app/pos_v141.php';require '/var/www/html/app/manuals_v142.php';require '/var/www/html/app/fisichat_v143.php';require '/var/www/html/app/restructure_r1.php';require '/var/www/html/app/restructure_r2.php';require '/var/www/html/app/schema_r2.php';require '/var/www/html/app/schema_r3.php';schema_r2($app);schema_r3($app);"""
        run(['docker','exec','-i','fisitaap-db','mariadb','-uroot','fisitaap_r2_test'],(REPO/'actualizacion-fisitaap-r5/migration-r1.sql').read_bytes())
        run(['docker','exec','fisitaap-r2-web','php','-r',migrations])
        seed="""require '/var/www/html/app/bootstrap.php';$d=json_decode(stream_get_contents(STDIN),true);$hash=password_hash($d['password'],PASSWORD_DEFAULT);
        foreach([[101,'LA VENTANITA','laventanita','Menú'],[102,'Other QA','prueba-b','Catálogo'],[103,'Excluded QA','prueba-a','Catálogo']] as $t)$app->exec('INSERT INTO tenants(id,name,slug,catalog_label,physical_store_enabled) VALUES(?,?,?,?,1)',$t);
        foreach([[301,101,'Principal','principal',1],[302,102,'Other','other',1],[303,101,'Segunda','segunda',0],[304,103,'Excluded','excluded',1]] as $b)$app->exec('INSERT INTO branches(id,tenant_id,name,slug,is_default) VALUES(?,?,?,?,?)',$b);
        foreach([[201,null,'master','master@r2.invalid'],[202,101,'tenant_admin','owner@r2.invalid'],[203,102,'tenant_admin','ownerb@r2.invalid'],[204,101,'cashier','cashiera@r2.invalid'],[205,null,'customer','customer@r2.invalid'],[206,null,'customer','other.customer@r2.invalid'],[207,103,'tenant_admin','excluded@r2.invalid'],[208,101,'manager','manager@r2.invalid'],[209,101,'kitchen','kitchen@r2.invalid'],[210,null,'driver','driver@r2.invalid']] as [$id,$tenant,$role,$email])$app->exec('INSERT INTO users(id,tenant_id,name,email,password_hash,role,email_verified_at) VALUES(?,?,?,?,?,?,NOW())',[$id,$tenant,'QA '.$role,$email,$hash,$role]);
        foreach([[401,101,'Comida','comida'],[402,102,'Other','other'],[403,103,'Excluded','excluded']] as $c)$app->exec('INSERT INTO categories(id,tenant_id,name,slug) VALUES(?,?,?,?)',$c);
        foreach([[501,101,401,'Producto QA','product-qa','QA1',1000],[502,102,402,'Other QA','other-qa','QA2',800],[503,101,401,'Extra QA','extra-qa','QA3',200],[504,103,403,'Excluded QA','excluded-qa','QA4',500]] as $p)$app->exec('INSERT INTO products(id,tenant_id,category_id,name,slug,sku,price,cost,tax_rate,track_inventory) VALUES(?,?,?,?,?,?,?,100,13,1)',$p);
        foreach([[101,301,501],[101,301,503],[101,303,501],[102,302,502],[103,304,504]] as $s){$app->exec('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity) VALUES(?,?,?,100)',$s);$app->exec('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(?,?,1)',[$s[1],$s[2]]);}
        $app->exec('INSERT INTO fisitaap_r2_modifiers(id,tenant_id,kind,name,choices_json,required,multiple) VALUES(901,101,"optional","Preparación anterior",?,1,0)',[json_encode([['name'=>'Sin cebolla','price'=>0],['name'=>'Sin chile','price'=>0]])]);
        $app->exec('INSERT INTO product_option_groups(id,tenant_id,product_id,name,type,is_required,min_select,max_select) VALUES(1001,101,503,"Preparación anterior","single",1,1,1)');
        $app->exec('INSERT INTO product_options(id,group_id,name,price_delta,sort_order) VALUES(1101,1001,"Sin cebolla",0,0),(1102,1001,"Sin chile",0,1)');
        $app->exec('INSERT INTO fisitaap_r2_modifier_groups(modifier_id,product_id,group_id) VALUES(901,503,1001)');
        $app->exec('INSERT INTO tenant_customers(tenant_id,user_id) VALUES(101,205),(102,206)');
        $app->exec('INSERT INTO customer_credit_profiles(tenant_id,user_id,credit_enabled,credit_limit,payment_terms_days) VALUES(101,205,1,50000,30)');
        foreach(['restructure_r1_active','restructure_r2_active','restructure_r3_active','pos_148_active'] as $flag)$app->exec('INSERT INTO settings(`key`,`value`) VALUES(?,"1")',[$flag]);
        $app->exec('INSERT INTO settings(`key`,`value`) VALUES("cache_version","1"),("site_name","FISITAAP")');
        """
        run(['docker','exec','-i','fisitaap-r2-web','php','-r',seed],json.dumps(info).encode())
        initialized.write_text('schema and fictional data only\n')
    (pathlib.Path('/tmp')/'fisitaap-r2-e2e.json').write_text(json.dumps(info));(pathlib.Path('/tmp')/'fisitaap-r2-e2e.json').chmod(0o600)
    helper="""import json,pathlib,subprocess,re,urllib.parse
SOURCE=pathlib.Path('/workspace/FISITAAP/actualizaciones/1.8.0/web')
ROOT=pathlib.Path('/workspace/fisitaap-r2-test')
PASSWORD=json.loads(pathlib.Path('/workspace/fisitaap-env/fixture.json').read_text())['password']
def sql(query,args=()):
 code="require '/var/www/html/app/bootstrap.php';$d=json_decode(stream_get_contents(STDIN),true);$s=$app->db->prepare($d['sql']);$s->execute($d['args']);echo json_encode($s->fetchAll());"
 p=subprocess.run(['docker','exec','-i','fisitaap-r2-web','php','-r',code],input=json.dumps({'sql':query,'args':list(args)}).encode(),capture_output=True)
 if p.returncode:raise RuntimeError('Fixture SQL failed')
 return json.loads(p.stdout)
def request(actor,path,data=None):
 args=['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','--max-time','30','-b','/tmp/r3-'+actor+'.cookies','-c','/tmp/r3-'+actor+'.cookies','-w','\\n%{http_code}'];payload=None
 if data is not None:args+=['--data-binary','@-'];payload=urllib.parse.urlencode(data,doseq=True).encode()
 args+=['http://localhost'+path];p=subprocess.run(args,input=payload,capture_output=True)
 if p.returncode:raise RuntimeError('Fixture request failed')
 body,status=p.stdout.rsplit(b'\\n',1);return int(status),body.decode()
def csrf(actor,path):
 status,body=request(actor,path);assert status==200,(path,status)
 m=re.search(r'name="csrf" value="([a-f0-9]+)"',body) or re.search(r'csrf:"([a-f0-9]+)"',body);assert m
 return m[1]
"""
    (ENV/'tests/r3_helpers.py').write_text(helper)
    for actor,email in [('master','master@r2.invalid'),('owner','owner@r2.invalid')]:
        args=['docker','exec','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-'+actor+'.cookies','-c','/tmp/r3-'+actor+'.cookies','-w','\n%{http_code}','http://localhost/owner-login']
        body,status=run(args).decode().rsplit('\n',1)
        if status=='302':continue
        if status!='200':raise RuntimeError('Fixture login page failed with HTTP '+status)
        token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
        run(['docker','exec','-i','fisitaap-r2-web','curl','--noproxy','*','-sS','-b','/tmp/r3-'+actor+'.cookies','-c','/tmp/r3-'+actor+'.cookies','--data-binary','@-','http://localhost/owner-login'],__import__('urllib.parse',fromlist=['urlencode']).urlencode({'csrf':token,'email':email,'password':info['password']}).encode())
    print('Isolated PHP/Apache/MariaDB fixtures started; fake master/owner authentication verified.')
if __name__=='__main__':main('--reset' in sys.argv)
