"""Exercise the reported legacy branch/driver schema using disposable data only."""
import hashlib, json, zipfile
from migration179 import SERVER, STAGE, DELIVERY, php, digests, request
from migration_demos179 import sql, csrf


def evaluate(code):
    return json.loads(php(SERVER, "require '/var/www/html/app/bootstrap.php';require ROOT_PATH.'/app/data_tools179.php';" + code))


def main():
    checks = []
    baseline = digests(SERVER)
    assert sql('SELECT COUNT(*) n FROM fisitaap_demo_sessions')[0]['n'] == 0
    kept_branch = sql('SELECT id FROM branches WHERE tenant_id=101 ORDER BY id LIMIT 1')[0]['id']
    demo_branch = sql('SELECT id FROM branches WHERE tenant_id=901 ORDER BY id LIMIT 1')[0]['id']

    def passed(label):
        checks.append(label)
        print('PASS', label, flush=True)

    def extra_business():
        sql('INSERT INTO tenants(id,name,slug) VALUES(43001,"Legacy fixture removed business","qa-legacy-removed")')
        sql('INSERT INTO branches(id,tenant_id,name,slug) VALUES(43002,43001,"Removed branch","qa-legacy-branch")')

    try:
        sql('CREATE TABLE driver_branches (user_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(user_id,branch_id),CONSTRAINT fk_driver_branch_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_driver_branch_branch FOREIGN KEY(branch_id) REFERENCES branches(id) ON DELETE CASCADE) ENGINE=InnoDB')
        for identifier in [41001, 41002, 41003]:
            sql('INSERT INTO users(id,tenant_id,name,email,password_hash,role,is_active) SELECT ?,NULL,"Legacy driver fixture",?,password_hash,"driver",1 FROM users WHERE id=202', [identifier, str(identifier)+'@legacy.invalid'])
            sql('INSERT INTO driver_profiles(user_id) VALUES(?)', [identifier])
        extra_business()
        sql('INSERT INTO driver_branches(user_id,branch_id) VALUES(41001,?),(41001,43002),(41002,43002),(41003,?)', [kept_branch, demo_branch])
        config = STAGE/'config.php'
        original_config = config.read_text()
        try:
            config.write_text(original_config.replace('return [', "return ['migration_mode'=>true,", 1))
            status, body = request('master','/preparar-mudanza.php',{'csrf':csrf('master','/preparar-mudanza.php'),'database':'fisitaap_migration_test','confirmation':'SOLO LA VENTANITA Y DEMOS'})
            assert status == 200 and 'Preparación completada' in body, 'Authenticated preparation failed with legacy driver links.'
        finally:
            config.write_text(original_config)
        assert sql('SELECT user_id,branch_id FROM driver_branches ORDER BY user_id') == [{'user_id': 41001, 'branch_id': kept_branch}, {'user_id': 41003, 'branch_id': demo_branch}]
        assert sql('SELECT id FROM users WHERE id BETWEEN 41001 AND 41003 ORDER BY id') == [{'id':41001},{'id':41003}]
        assert sql('SELECT user_id FROM driver_profiles WHERE user_id BETWEEN 41001 AND 41003 ORDER BY user_id') == [{'user_id':41001},{'user_id':41003}]
        assert not sql('SELECT id FROM tenants WHERE id=43001')
        passed('authenticated preparation retains legacy links for selected branches and shared drivers/profiles; excluded links and unrelated driver disappear')
        after = digests(SERVER)
        evaluate("fm_filter($app,[101,901,902],true);echo json_encode(true);")
        assert digests(SERVER) == after
        passed('repeating preparation with legacy links preserves every retained row')

        # The new rule must also clean a demo-owned link without removing its shared driver.
        opened = evaluate("require ROOT_PATH.'/app/demo_sandbox.php';$token=demo_open($app,'restaurante','panel');$s=$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',[$token]);$b=$app->one('SELECT id FROM branches WHERE tenant_id=? ORDER BY id LIMIT 1',[$s['tenant_id']]);echo json_encode(['token'=>$token,'branch'=>$b['id']]);")
        sql('INSERT INTO driver_branches(user_id,branch_id) VALUES(41001,?)', [opened['branch']])
        evaluate("require ROOT_PATH.'/app/demo_sandbox.php';demo_discard($app,$app->one('SELECT * FROM fisitaap_demo_sessions WHERE token=?',['"+opened['token']+"']));echo json_encode(true);")
        assert digests(SERVER) == after
        passed('discarding a temporary demo removes its legacy branch link and preserves the shared real driver')

        extra_business()
        sql('INSERT INTO driver_branches(user_id,branch_id) VALUES(41001,43002)')
        sql('CREATE TABLE qa_migration_unknown (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB')
        sql('INSERT INTO qa_migration_unknown(id) VALUES(1)')
        before = digests(SERVER)
        result = evaluate("try{fm_filter($app,[101,901,902],true);$error='';}catch(Throwable $e){$error=$e->getMessage();}echo json_encode(['error'=>$error,'checks'=>(int)$app->one('SELECT @@FOREIGN_KEY_CHECKS n')['n']]);")
        assert 'qa_migration_unknown no tiene un alcance conocido' in result['error'] and result['checks'] == 1
        assert digests(SERVER) == before
        passed('another unknown table still blocks all deletion and leaves every row unchanged')
        sql('DROP TABLE qa_migration_unknown')

        evaluate("$app->db->exec('SET FOREIGN_KEY_CHECKS=0');try{$app->exec('INSERT INTO driver_branches(user_id,branch_id) VALUES(41009,?)',["+str(kept_branch)+"]);}finally{$app->db->exec('SET FOREIGN_KEY_CHECKS=1');}echo json_encode(true);")
        before = digests(SERVER)
        result = evaluate("try{fm_filter($app,[101,901,902],true);$error='';}catch(Throwable $e){$error=$e->getMessage();}echo json_encode(['error'=>$error,'checks'=>(int)$app->one('SELECT @@FOREIGN_KEY_CHECKS n')['n']]);")
        assert 'driver_branches → users' in result['error'] and result['checks'] == 1
        assert digests(SERVER) == before
        passed('a broken legacy user reference aborts and rolls back selective deletion')

        sql('ALTER TABLE driver_branches CHANGE COLUMN branch_id unexpected_branch_id BIGINT UNSIGNED NOT NULL')
        before = digests(SERVER)
        result = evaluate("try{fm_filter($app,[101,901,902],true);$error='';}catch(Throwable $e){$error=$e->getMessage();}echo json_encode(['error'=>$error,'checks'=>(int)$app->one('SELECT @@FOREIGN_KEY_CHECKS n')['n']]);")
        assert 'no corresponde a la relación revisada' in result['error'] and result['checks'] == 1
        assert digests(SERVER) == before
        passed('an unexpected legacy column layout is rejected before any deletion')
    finally:
        sql('DROP TABLE IF EXISTS qa_migration_unknown')
        sql('DROP TABLE IF EXISTS driver_branches')
        sql('DELETE FROM driver_profiles WHERE user_id BETWEEN 41001 AND 41009')
        sql('DELETE FROM users WHERE id BETWEEN 41001 AND 41009')
        sql('DELETE FROM branches WHERE id=43002')
        sql('DELETE FROM tenants WHERE id=43001')
    final = digests(SERVER)
    # Preparation intentionally clears temporary login/request counters, not business data.
    changes = {table: {'before': baseline.get(table,{}).get('count'), 'after': final.get(table,{}).get('count')} for table in set(baseline)|set(final) if table != 'rate_limits' and baseline.get(table) != final.get(table)}
    assert not changes, changes
    assert final['rate_limits']['count'] == 0
    passed('original business/configuration hashes remain unchanged; temporary rate-limit counters are cleared by preparation')
    with zipfile.ZipFile(DELIVERY/'FISITAAP-CORREGIR-PREPARACION-DRIVERS.zip') as archive:
        assert set(archive.namelist()) == {'app/data_tools179.php', 'app/demo_sandbox.php', 'app/branches_v1.php', 'app/official_r3.php', 'mudanza-manifest.php'}
        helper = archive.read('app/data_tools179.php')
        assert helper == (DELIVERY/'web/app/data_tools179.php').read_bytes()
        manifest = json.loads(archive.read('mudanza-manifest.php').decode().split("<<<'MIGRATION_MANIFEST'\n",1)[1].split('\nMIGRATION_MANIFEST',1)[0])
        assert manifest['files']['app/data_tools179.php'] == hashlib.sha256(helper).hexdigest()
    passed('cumulative corrective ZIP contains the verified helper/demo files and matching manifest, preserving private configuration and uploads')
    (DELIVERY/'QA-DRIVER-BRANCHES.json').write_text(json.dumps({'environment':'isolated PHP 8.3 / MariaDB 11.4; reported legacy columns and foreign keys, fake records only','passed':len(checks),'checks':checks},indent=2)+'\n')


if __name__ == '__main__':
    main()
