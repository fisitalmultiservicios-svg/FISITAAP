"""Check request-cache freshness and batched catalog semantics in a disposable DB.

Requires fisitaap_performance_test, cloned from the isolated R2/R3 fixture.
Never configure this script with a production container or database.
"""
import subprocess

code = r'''
require '/var/www/html/app/bootstrap.php';
$config['db_name']='fisitaap_performance_test';$db=Database::connect($config);$app=new App($db,$config);
require '/var/www/html/app/admin.php';require '/var/www/html/app/v1.php';require '/var/www/html/app/admin_v1.php';require '/var/www/html/app/branches_v1.php';require '/var/www/html/app/modules_v12.php';require '/var/www/html/app/physical_v14.php';require '/var/www/html/app/pos_v141.php';require '/var/www/html/app/restructure_r1.php';require '/var/www/html/app/restructure_r2.php';
function check175(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);}
$db->beginTransaction();try{
 check175(r2_setting($app,101,'cache-test-175','first')==='first','Missing setting default');
 check175(r2_setting($app,101,'cache-test-175','second')==='second','Different fallback retained');
 r2_set($app,101,'cache-test-175','fresh');check175(r2_setting($app,101,'cache-test-175')==='fresh','Setting write invalidates cached map');
 check175(r2_setting($app,102,'cache-test-175','other')==='other','No settings shared across tenants');
 $app->exec('INSERT INTO settings(`key`,`value`) VALUES("cache-test-175","fresh")');check175($app->setting('cache-test-175')==='fresh','Global settings refresh');
 $app->exec('DELETE FROM settings WHERE `key`="cache-test-175"');check175($app->setting('cache-test-175','deleted')==='deleted','Deleted setting is not cached');
 echo "PASS settings writes, deletion, fallbacks and tenant isolation\n";

 $tenant=['id'=>101,'slug'=>'prueba-a','plan'=>'Profesional'];
 check175(module_enabled_v12($app,$tenant,'pos'),'Existing POS access');
 $app->exec('UPDATE tenant_modules SET enabled=0 WHERE tenant_id=101 AND module_key="pos"');check175(!module_enabled_v12($app,$tenant,'pos'),'Module revocation takes effect');
 $app->exec('UPDATE tenant_modules SET enabled=1 WHERE tenant_id=101 AND module_key="pos"');check175(module_enabled_v12($app,$tenant,'pos'),'Module restoration takes effect');
 $app->exec('INSERT INTO tenant_modules(tenant_id,module_key,enabled) VALUES(102,"pos",0) ON DUPLICATE KEY UPDATE enabled=0');check175(!module_enabled_v12($app,['id'=>102,'slug'=>'prueba-b','plan'=>'Profesional'],'pos'),'No module grant shared across tenants');
 echo "PASS module grants and revocations remain current and scoped\n";

 check175(r2_promotion($app,101,100000)===null,'No synthetic promotion initially');
 $app->exec('INSERT INTO fisitaap_r2_promotions(tenant_id,product_id,kind,value) VALUES(101,100000,"special",5)');$first=(int)$db->lastInsertId();
 $app->exec('INSERT INTO fisitaap_r2_promotions(tenant_id,product_id,kind,value) VALUES(101,100000,"bogo",0)');$last=(int)$db->lastInsertId();
 check175((int)r2_promotion($app,101,100000)['id']===$last,'Newest promotion selected');
 $app->exec('UPDATE fisitaap_r2_promotions SET is_active=0 WHERE id=?',[$last]);check175((int)r2_promotion($app,101,100000)['id']===$first,'Promotion change invalidates cache');
 $app->exec('UPDATE fisitaap_r2_promotions SET ends_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?',[$first]);check175(r2_promotion($app,101,100000)===null,'Expired promotion excluded');
 check175(r2_promotion($app,102,100000)===null,'Promotion tenant isolation');
 echo "PASS promotion priority, expiry, modifications and tenant isolation\n";

 $app->exec('UPDATE tenants SET catalog_mode="branches" WHERE id=101');$app->exec('DELETE FROM branch_products WHERE branch_id=301');
 $app->exec('INSERT INTO branch_products(branch_id,product_id,price_override,is_active) VALUES(301,100000,19.99,1),(301,100001,9.99,0),(301,100002,NULL,1),(301,503,1,1)');
 $app->exec('INSERT INTO product_option_groups(tenant_id,product_id,name,type,is_required,min_select,max_select) VALUES(101,100000,"Options QA","multiple",1,1,2)');$group=(int)$db->lastInsertId();
 $app->exec('INSERT INTO product_options(group_id,name,price_delta,is_active) VALUES(?,"Visible",.99,1),(?,"Hidden",999,0)',[$group,$group]);
 $app->exec('UPDATE products SET product_type="combo" WHERE id=100002');$app->exec('INSERT INTO fisitaap_r2_combo_items(combo_id,product_id,quantity) VALUES(100002,100000,2)');
 $snap=r2_snapshot($app,['id'=>100000,'tenant_id'=>101,'branch_id'=>301]);$products=array_column($snap['products'],null,'id');
 check175(array_keys($products)===[100000,100002],'Only active assigned products from this tenant');
 check175($products[100000]['price']===1999,'Branch price retained to the cent');
 check175(count($products[100000]['groups'])===1&&count($products[100000]['groups'][0]['options'])===1,'Active options only');
 check175($products[100000]['groups'][0]['options'][0]['price']===99&&$products[100000]['groups'][0]['min']===1&&$products[100000]['groups'][0]['max']===2,'Option prices and bounds');
 check175($products[100002]['components'][0]['id']===100000&&$products[100002]['components'][0]['quantity']===2000,'Combo components retained');
 echo "PASS batched branch catalogs, exact prices, options, combos and tenant isolation\n";
}finally{$db->rollBack();}
'''
result=subprocess.run(['docker','exec','fisitaap-r2-web','php','-r',code],capture_output=True,text=True)
print(result.stdout,end='')
if result.returncode:
    raise RuntimeError(result.stderr[:600])
