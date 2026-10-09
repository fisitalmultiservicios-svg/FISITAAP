"""Regression checks for confirmed migration package review findings; fake data only."""
import io,json,os,pathlib,re,urllib.parse,zipfile
from migration179 import STAGE,SERVER,DELIVERY,run,php,request,digests
from migration_demos179 import sql,csrf

checks=[]
def passed(label):checks.append(label);print('PASS',label,flush=True)
def evaluate(code):return json.loads(php(SERVER,"require '/var/www/html/app/bootstrap.php';"+code))

def main():
    config=STAGE/'config.php';original=config.read_text()
    baseline=digests(SERVER)
    urls=evaluate("$p='/uploads/image-qa/photo.jpg';$full=public_media_url(url($p).'?rev=1');echo json_encode(['full'=>$full,'again'=>public_media_url($full),'thumb'=>media_thumbnail_url($full),'www'=>public_media_url('http://www.localhost'.$p.'?rev=1'),'data'=>public_media_url('data:image/png;base64,AA==')]);")
    assert '.optimized.webp' in urls['full'] and urls['full']==urls['again'] and '.thumb.webp' in urls['thumb']
    assert '.optimized.webp' in urls['www'] and urls['data']=='data:image/png;base64,AA=='
    passed('photo URLs with query strings, generated version parameters and www aliases remain valid')
    files={'Photo product.png':'logo.png','Foto ñ producto.png':'logo.png'}
    for target,source in files.items():
        (STAGE/'uploads/image-qa'/target).write_bytes((STAGE/'uploads/image-qa'/source).read_bytes())
        (STAGE/'uploads/image-qa'/target).chmod(0o644)
    media=evaluate("require ROOT_PATH.'/app/migration_media.php';$p=[];$missing=[];foreach(['/uploads/image-qa/Photo product.png','[\"/uploads/image-qa/Foto ñ producto.png\"]','<img src=\"/uploads/image-qa/Photo product.png?x=1\">','https://outside.invalid/uploads/private.png','/uploads/../config.php','/uploads/image-qa/not-found.png'] as $value)fm_media_paths($value,$config,realpath(ROOT_PATH.'/uploads'),$p,$missing);echo json_encode(['found'=>array_keys($p),'missing'=>array_keys($missing),'relative'=>public_media_url('/uploads/image-qa/Photo%20product.png?x=1')]);")
    assert {'uploads/image-qa/Photo product.png','uploads/image-qa/Foto ñ producto.png'}<=set(media['found'])
    assert media['missing']==['uploads/image-qa/not-found.png']
    assert 'Photo%20product.png?x=1' in media['relative']
    passed('raw/encoded spaces, Unicode, JSON galleries and quoted HTML image references export correctly; unsafe/external paths stay excluded')
    assert digests(SERVER)==baseline
    for path in ['.env.private','backup.sql.gz','Backup.SQL.GZ','database.tar.gz']:
        file=STAGE/path;file.write_text('DISPOSABLE PRIVATE FIXTURE');file.chmod(0o644)
        try:assert request('anon','/'+path)[0]==403,path
        finally:file.unlink()
    hidden=STAGE/'.git-review';hidden.mkdir(exist_ok=True);hidden.chmod(0o755);(hidden/'metadata.txt').write_text('PRIVATE FIXTURE');(hidden/'metadata.txt').chmod(0o644)
    assert request('anon','/.git-review/metadata.txt')[0]==403
    (hidden/'metadata.txt').unlink();hidden.rmdir()
    acme=STAGE/'.well-known/acme-challenge';acme.mkdir(parents=True,exist_ok=True)
    acme.chmod(0o755);acme.parent.chmod(0o755);(acme/'qa-token').write_text('ACME TEST');(acme/'qa-token').chmod(0o644)
    try:assert request('anon','/.well-known/acme-challenge/qa-token')==(200,'ACME TEST')
    finally:(acme/'qa-token').unlink()
    passed('compressed backups and hidden data are blocked while certificate challenge files remain accessible')
    temp=evaluate("require ROOT_PATH.'/app/data_tools179.php';$m=fm_meta($app);$s=fm_sets($app,$m);$name=reset($s);fm_drop_sets($app,$s);try{$app->db->query('SELECT * FROM '.fm_id($name));$gone=false;}catch(PDOException $e){$gone=true;}echo json_encode($gone);")
    assert temp is True
    passed('temporary migration tables are actually released on the database connection')
    # Create a fake cross-scope reference that must prevent a dangerous demo deletion.
    before_demo=digests(SERVER)
    opened=evaluate("require ROOT_PATH.'/app/demo_sandbox.php';$token=demo_open($app,'restaurante','panel');$s=$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',[$token]);$p=$app->one('SELECT id FROM products WHERE tenant_id=? ORDER BY id LIMIT 1',[$s['tenant_id']]);echo json_encode(['token'=>$token,'product'=>$p['id']]);")
    old=sql('SELECT linked_product_id FROM product_options WHERE id=1701')[0]['linked_product_id']
    try:
        sql('UPDATE product_options SET linked_product_id=? WHERE id=1701',[opened['product']])
        saved=digests(SERVER)
        blocked=evaluate("require ROOT_PATH.'/app/demo_sandbox.php';$s=$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',['"+opened['token']+"']);try{demo_discard($app,$s);$blocked=false;}catch(RuntimeException $e){$blocked=true;}echo json_encode(['blocked'=>$blocked,'checks'=>(int)$app->one('SELECT @@FOREIGN_KEY_CHECKS n')['n']]);")
        assert blocked=={'blocked':True,'checks':1} and digests(SERVER)==saved
    finally:
        sql('UPDATE product_options SET linked_product_id=? WHERE id=1701',[old])
        evaluate("require ROOT_PATH.'/app/demo_sandbox.php';$s=$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',['"+opened['token']+"']);if($s)demo_discard($app,$s);echo json_encode(true);")
    assert digests(SERVER)==before_demo
    passed('demo cleanup rejects references from another tenant without deleting rows and restores foreign-key checks')
    # File cleanup must not traverse a directory linked outside the temporary demo.
    opened=evaluate("require ROOT_PATH.'/app/demo_sandbox.php';$token=demo_open($app,'tienda','panel');echo json_encode($token);")
    outside=STAGE/'uploads/real-business-review';outside.mkdir(exist_ok=True);outside.chmod(0o755);sentinel=outside/'keep.txt';sentinel.write_text('KEEP REAL BUSINESS');sentinel.chmod(0o644)
    demo=STAGE/'uploads/demo'/opened;demo.parent.mkdir(exist_ok=True);demo.symlink_to(outside,target_is_directory=True)
    try:
        evaluate("require ROOT_PATH.'/app/demo_sandbox.php';demo_discard($app,$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',['"+opened+"']));echo json_encode(true);")
        assert sentinel.read_text()=='KEEP REAL BUSINESS'
    finally:demo.unlink();sentinel.unlink();outside.rmdir()
    passed('demo file cleanup does not follow a linked directory into another business')
    # A failing activation write must also roll back deletions from preparation.
    sql('INSERT INTO tenants(id,name,slug) VALUES(40001,"Review extra business","qa-review-extra")')
    original_rows=digests(SERVER)
    trigger="CREATE TRIGGER qa_review_activation BEFORE INSERT ON settings FOR EACH ROW BEGIN IF NEW.`key`='demo_sandbox_active' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Controlled fixture activation failure'; END IF; END"
    try:
        sql(trigger)
        rolled_back=evaluate("require ROOT_PATH.'/app/data_tools179.php';try{fm_filter($app,[101,901,902],true);$failed=false;}catch(PDOException $e){$failed=true;}echo json_encode(['failed'=>$failed,'checks'=>(int)$app->one('SELECT @@FOREIGN_KEY_CHECKS n')['n']]);")
        assert rolled_back=={'failed':True,'checks':1} and digests(SERVER)==original_rows
    finally:
        sql('DROP TRIGGER IF EXISTS qa_review_activation');sql('DELETE FROM tenants WHERE id=40001')
    passed('failed demo activation rolls back selective deletions in the same transaction')
    # Download the actual private archive through the authenticated web tool.
    evaluate("require ROOT_PATH.'/app/demo_sandbox.php';foreach($app->all('SELECT * FROM fisitaap_demo_sessions') as $s)demo_discard($app,$s);echo json_encode(true);")
    saved=sql('SELECT logo FROM tenants WHERE id=101')[0]['logo']
    try:
        config.write_text(original.replace('return [',"return ['migration_mode'=>true,",1))
        sql('UPDATE tenants SET logo=? WHERE id=101',['/uploads/image-qa/missing-business-logo.png'])
        before_archive=digests(SERVER)
        payload=urllib.parse.urlencode({'csrf':csrf('master','/preparar-mudanza.php'),'action':'media'}).encode()
        binary,status=run(['docker','exec','-i',SERVER,'curl','--noproxy','*','-sS','--max-time','30','-b','/tmp/migration-master.cookies','--data-binary','@-','-w','\n%{http_code}','http://localhost/preparar-mudanza.php'],payload).rsplit(b'\n',1)
        assert status==b'200'
        with zipfile.ZipFile(io.BytesIO(binary)) as archive:
            report=archive.read('FISITAAP-ARCHIVOS-FALTANTES.txt').decode()
            assert 'uploads/image-qa/missing-business-logo.png' in report
        assert digests(SERVER)==before_archive
    finally:
        sql('UPDATE tenants SET logo=? WHERE id=101',[saved]);config.write_text(original);request('master','/preparar-mudanza.php')
    passed('authenticated media ZIP includes a missing-files report and leaves database rows unchanged')
    code="require '/var/www/html/app/bootstrap.php';require ROOT_PATH.'/app/data_tools179.php';require ROOT_PATH.'/app/migration_media.php';$before=session_status();register_shutdown_function(static function()use($before){file_put_contents('/tmp/review-media-session.json',json_encode([$before,session_status()]));});fm_media_archive($app);"
    run(['docker','exec',SERVER,'php','-r',code])
    state=json.loads(run(['docker','exec',SERVER,'cat','/tmp/review-media-session.json']))
    assert state==[2,1]
    passed('media export releases the session before creating and streaming the archive')
    (DELIVERY/'QA-REVISION.json').write_text(json.dumps({'environment':'isolated PHP 8.3 / MariaDB 11.4; no production data','passed':len(checks),'checks':checks},indent=2)+'\n')
    run(['docker','exec',SERVER,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html/uploads'])
if __name__=='__main__':main()
