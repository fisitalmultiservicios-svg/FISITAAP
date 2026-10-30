<?php
declare(strict_types=1);
require_once __DIR__.'/operaciones180.php';
require_once __DIR__.'/chat_actions_r16.php';
require_once __DIR__.'/support_r16.php';
require_once __DIR__.'/platform_r17.php';
require_once __DIR__.'/runtime_r18.php';
require_once __DIR__.'/runtime_r19.php';
require_once __DIR__.'/runtime_r20.php';

function qa180_active(App $app,?int $tenantId=null):bool {
    if($app->setting('qa180_active','0')!=='1')return false;
    if($app->setting('qa180_rollout','0')!=='1')return true;
    $tenantId??=(int)($GLOBALS['qa180_tenant_scope']??(current_user()['tenant_id']??0));
    if(!$tenantId)return true;
    $key=spl_object_id($app).':'.$tenantId;
    if(!array_key_exists($key,$GLOBALS['qa180_version_cache']??[])){
        $row=$app->one('SELECT version FROM fisitaap_tenant_updates180 WHERE tenant_id=?',[$tenantId]);
        $GLOBALS['qa180_version_cache'][$key]=(int)($row['version']??179);
    }
    return $GLOBALS['qa180_version_cache'][$key]>=180;
}
function schema180(App $app):void {
    // Run only during explicit activation, never on ordinary page requests.
    $tables=[
        'fisitaap_tenant_updates180'=>'tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,version INT UNSIGNED NOT NULL DEFAULT 179,modifiers_migrated TINYINT NOT NULL DEFAULT 0,activated_by BIGINT UNSIGNED NULL,activated_at DATETIME NULL',
        'fisitaap_print_jobs180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,device_id BIGINT UNSIGNED NULL,user_id BIGINT UNSIGNED NOT NULL,operation_key VARCHAR(200) NOT NULL,payload_hash CHAR(64) NOT NULL,point VARCHAR(30) NOT NULL,payload LONGTEXT NOT NULL,status VARCHAR(24) NOT NULL DEFAULT "queued",claim_token CHAR(32) NULL,lease_expires DATETIME NULL,attempts INT NOT NULL DEFAULT 0,error VARCHAR(500) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,completed_at DATETIME NULL,UNIQUE KEY operation(tenant_id,operation_key),INDEX queue(tenant_id,branch_id,device_id,status,id)',
        'fisitaap_registers180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,name VARCHAR(100) NOT NULL,device_id BIGINT UNSIGNED NULL,user_id BIGINT UNSIGNED NULL,is_active TINYINT NOT NULL DEFAULT 1,drawer_enabled TINYINT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY device(device_id),INDEX scope(tenant_id,branch_id,is_active)',
        'fisitaap_money_accounts180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,name VARCHAR(100) NOT NULL,kind VARCHAR(16) NOT NULL DEFAULT "bank",balance DECIMAL(15,2) NOT NULL DEFAULT 0,is_active TINYINT NOT NULL DEFAULT 1,UNIQUE KEY named(tenant_id,name)',
        'fisitaap_money_entries180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,account_id BIGINT UNSIGNED NULL,register_id BIGINT UNSIGNED NULL,shift_id BIGINT UNSIGNED NULL,user_id BIGINT UNSIGNED NOT NULL,operation_key VARCHAR(100) NOT NULL,payload_hash CHAR(64) NOT NULL,kind VARCHAR(32) NOT NULL,amount DECIMAL(15,2) NOT NULL,reason VARCHAR(250) NOT NULL,reference VARCHAR(150) NULL,entity_type VARCHAR(30) NULL,entity_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY operation(tenant_id,operation_key),INDEX cash(tenant_id,shift_id,created_at),INDEX account(tenant_id,account_id,created_at)',
        'fisitaap_purchase_drafts180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,operation_key VARCHAR(64) NOT NULL,data LONGTEXT NOT NULL,revision INT NOT NULL DEFAULT 0,status VARCHAR(20) NOT NULL DEFAULT "draft",purchase_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY once_per_tenant(tenant_id,operation_key),INDEX scope(tenant_id,branch_id,status)',
        'fisitaap_modifier_categories180'=>'tenant_id BIGINT UNSIGNED NOT NULL,modifier_id BIGINT UNSIGNED NOT NULL,category_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(modifier_id,category_id),INDEX scope(tenant_id,category_id)',
        'fisitaap_modifier_products180'=>'tenant_id BIGINT UNSIGNED NOT NULL,modifier_id BIGINT UNSIGNED NOT NULL,product_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(modifier_id,product_id),INDEX scope(tenant_id,product_id)',
        'fisitaap_ticket_tables180'=>'tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,ticket_id BIGINT UNSIGNED NOT NULL,table_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(ticket_id,table_id),INDEX table_scope(tenant_id,branch_id,table_id)',
        'fisitaap_coupon_uses180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,coupon_id BIGINT UNSIGNED NOT NULL,operation_key VARCHAR(80) NOT NULL,order_id BIGINT UNSIGNED NULL,sale_id BIGINT UNSIGNED NULL,ticket_id BIGINT UNSIGNED NULL,amount DECIMAL(15,2) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY once_per_coupon(tenant_id,coupon_id,operation_key)',
        'fisitaap_order_receipts180'=>'tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,order_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,ticket_id BIGINT UNSIGNED NOT NULL,received_by BIGINT UNSIGNED NULL,received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY ticket(ticket_id)',
        'fisitaap_catalog_blobs180'=>'hash CHAR(64) NOT NULL PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NULL,kind VARCHAR(20) NOT NULL,data LONGTEXT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX scope(tenant_id,branch_id,kind)',
        'fisitaap_operations180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,operation_key CHAR(32) NOT NULL,payload_hash CHAR(64) NOT NULL,kind VARCHAR(40) NOT NULL,result_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY operation(tenant_id,operation_key)',
    ];
    foreach($tables as $table=>$definition)$app->db->exec('CREATE TABLE IF NOT EXISTS '.$table.' ('.$definition.') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    foreach(array_keys($tables) as $table){$fk='fk180_'.substr(hash('sha256',$table),0,16);if(!$app->one('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$table,$fk]))$app->db->exec('ALTER TABLE '.$table.' ADD CONSTRAINT '.$fk.' FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE');}
    $columns=[
        'tenants'=>['tax_id'=>'VARCHAR(30) NULL','tax_id_required'=>'TINYINT NOT NULL DEFAULT 0'],
        'order_items'=>['pricing_json180'=>'JSON NULL','discount_cents180'=>'BIGINT NOT NULL DEFAULT 0'],
        'orders'=>['revision180'=>'INT UNSIGNED NOT NULL DEFAULT 0'],
        'branches'=>['web_order_acceptance'=>'TINYINT NOT NULL DEFAULT 1'],
        'pos_shifts'=>['register_id'=>'BIGINT UNSIGNED NULL','device_id'=>'BIGINT UNSIGNED NULL','opening_key'=>'CHAR(32) NULL','closing_key'=>'CHAR(32) NULL','closing_payload_hash'=>'CHAR(64) NULL','denominations_json'=>'TEXT NULL'],
        'pos_tickets'=>['shift_id'=>'BIGINT UNSIGNED NULL','register_id'=>'BIGINT UNSIGNED NULL','coupon_id'=>'BIGINT UNSIGNED NULL','merged_into'=>'BIGINT UNSIGNED NULL','start_payload_hash'=>'CHAR(64) NULL','source_order_id'=>'BIGINT UNSIGNED NULL','fulfillment180'=>'VARCHAR(30) NOT NULL DEFAULT \'new\'','service_adjustment180'=>'DECIMAL(15,2) NOT NULL DEFAULT 0'],
        'customer_credit_profiles'=>['is_blocked'=>'TINYINT NOT NULL DEFAULT 0','notes'=>'TEXT NULL'],
        'coupons'=>['applicable_products'=>'TEXT NULL','applicable_categories'=>'TEXT NULL'],
        'ar_payments'=>['account_id'=>'BIGINT UNSIGNED NULL','shift_id'=>'BIGINT UNSIGNED NULL','payload_hash'=>'CHAR(64) NULL'],
        'ap_payments'=>['account_id'=>'BIGINT UNSIGNED NULL','shift_id'=>'BIGINT UNSIGNED NULL','payload_hash'=>'CHAR(64) NULL'],
        'fisitaap_r2_purchase_keys'=>['payload_hash'=>'CHAR(64) NULL'],
        'product_option_groups'=>['modifier_id180'=>'BIGINT UNSIGNED NULL'],
        'product_options'=>['modifier_option180'=>'VARCHAR(12) NULL'],
        'fisitaap_r2_modifiers'=>['legacy_family180'=>'BIGINT UNSIGNED NULL','legacy_name180'=>'VARCHAR(120) NULL','legacy_min180'=>'INT NOT NULL DEFAULT 0','legacy_max180'=>'INT NOT NULL DEFAULT 1'],
        'audit_logs'=>['summary'=>'VARCHAR(500) NULL'],
        'fisitaap_r2_rooms'=>['is_active'=>'TINYINT NOT NULL DEFAULT 1'],
        'pos_ticket_items'=>['original_ticket_id180'=>'BIGINT UNSIGNED NULL'],
        'fisitaap_r2_snapshots'=>['catalog_hash180'=>'CHAR(64) NULL'],
    ];
    foreach($columns as $table=>$items){
        $known=array_column($app->all('SHOW COLUMNS FROM `'.$table.'`'),'Field');
        foreach($items as $column=>$definition)if(!in_array($column,$known,true))$app->db->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
    }
    foreach([
        ['pos_shifts','opening180','tenant_id,user_id,opening_key',true],
        ['pos_tickets','pending180','tenant_id,branch_id,shift_id,status',false],
        ['suppliers','tax180','tenant_id,tax_id',false],
        ['sales','period180','tenant_id,created_at',false],
        ['orders','period180','tenant_id,created_at',false],
        ['audit_logs','period180','tenant_id,created_at',false],
        ['fisitaap_money_entries180','period180','tenant_id,created_at',false],
        ['inventory_movements','period180','tenant_id,created_at',false],
        ['pos_shifts','period180','tenant_id,opened_at',false],
        ['pos_shifts','status180','tenant_id,status,branch_id',false],
        ['pos_tickets','migration180','tenant_id,status',false],
        ['delivery_jobs','period180','tenant_id,created_at',false],
        ['orders','kanban180','tenant_id,branch_id,status,created_at',false],
        ['product_option_groups','modifier180','tenant_id,product_id,modifier_id180',true],
        ['product_options','modifier_option180','group_id,modifier_option180',true],
        ['fisitaap_r2_snapshots','catalog180','device_id,catalog_hash180',true],
    ] as [$table,$name,$fields,$unique])if(!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$name]))$app->db->exec('ALTER TABLE '.$table.' ADD '.($unique?'UNIQUE ':'').'INDEX '.$name.' ('.$fields.')');
    foreach([
        ['fisitaap_modifier_products180','modifier_id','fisitaap_r2_modifiers','CASCADE'],
        ['fisitaap_modifier_products180','product_id','products','CASCADE'],
        ['fisitaap_modifier_categories180','modifier_id','fisitaap_r2_modifiers','CASCADE'],
        ['fisitaap_modifier_categories180','category_id','categories','CASCADE'],
        ['product_option_groups','modifier_id180','fisitaap_r2_modifiers','SET NULL'],
        ['fisitaap_ticket_tables180','ticket_id','pos_tickets','CASCADE'],
        ['fisitaap_ticket_tables180','table_id','restaurant_tables','CASCADE'],
    ] as [$table,$column,$parent,$onDelete]){
        $fk='fk180_'.substr(hash('sha256',$table.':'.$column),0,16);
        if(!$app->one('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$table,$fk]))$app->db->exec('ALTER TABLE '.$table.' ADD CONSTRAINT '.$fk.' FOREIGN KEY('.$column.') REFERENCES '.$parent.'(id) ON DELETE '.$onDelete);
    }
    if(function_exists('op180_schema'))op180_schema($app);
    fc16_schema($app);
    r17_schema($app);
    r18_schema($app);
    r19_schema($app);
    r20_schema($app);
}
