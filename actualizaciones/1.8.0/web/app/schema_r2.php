<?php
declare(strict_types=1);

// Additive migration, also supports installations whose original schema predates POS 1.4.8.
function schema_r2(App $app): void
{
    $sql = [
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_gift_keys (tenant_id BIGINT UNSIGNED NOT NULL,issue_key CHAR(32) NOT NULL,reward_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(tenant_id,issue_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS pos_table_groups (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,service_enabled TINYINT NOT NULL DEFAULT 0, UNIQUE KEY scope_name(tenant_id,branch_id,name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_settings (tenant_id BIGINT UNSIGNED NOT NULL,setting_key VARCHAR(80) NOT NULL,value LONGTEXT NOT NULL,PRIMARY KEY(tenant_id,setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_modifiers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,kind VARCHAR(12) NOT NULL,name VARCHAR(120) NOT NULL,choices_json TEXT NOT NULL,required TINYINT NOT NULL DEFAULT 0,multiple TINYINT NOT NULL DEFAULT 0,is_active TINYINT NOT NULL DEFAULT 1,INDEX scope(tenant_id,kind)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_modifier_groups (modifier_id BIGINT UNSIGNED NOT NULL,product_id BIGINT UNSIGNED NOT NULL,group_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(modifier_id,product_id),UNIQUE KEY managed_group(group_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_combo_items (combo_id BIGINT UNSIGNED NOT NULL,product_id BIGINT UNSIGNED NOT NULL,quantity DECIMAL(12,3) NOT NULL,PRIMARY KEY(combo_id,product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_promotions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,product_id BIGINT UNSIGNED NOT NULL,kind VARCHAR(20) NOT NULL,value DECIMAL(15,2) NOT NULL DEFAULT 0,starts_at DATETIME NULL,ends_at DATETIME NULL,is_active TINYINT NOT NULL DEFAULT 1,INDEX scope(tenant_id,product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_customer_sources (tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,source VARCHAR(30) NOT NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(tenant_id,user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_purchase_keys (tenant_id BIGINT UNSIGNED NOT NULL,purchase_key VARCHAR(100) NOT NULL,purchase_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(tenant_id,purchase_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_rooms (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,width INT NOT NULL DEFAULT 1000,height INT NOT NULL DEFAULT 650,service_rate DECIMAL(5,2) NOT NULL DEFAULT 0,UNIQUE KEY scope_name(tenant_id,branch_id,name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_floor (table_id BIGINT UNSIGNED PRIMARY KEY,room_id BIGINT UNSIGNED NOT NULL,x INT NOT NULL DEFAULT 30,y INT NOT NULL DEFAULT 30,width INT NOT NULL DEFAULT 100,height INT NOT NULL DEFAULT 85,shape VARCHAR(20) NOT NULL DEFAULT "square") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_devices (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,name VARCHAR(120) NOT NULL,pair_hash CHAR(64) NULL,pair_expires DATETIME NULL,token_hash CHAR(64) NULL,is_active TINYINT NOT NULL DEFAULT 1,last_sync DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX scope(tenant_id,branch_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_offline_users (tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,password_hash VARCHAR(255) NOT NULL,is_active TINYINT NOT NULL DEFAULT 1,PRIMARY KEY(branch_id,user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_snapshots (id CHAR(32) PRIMARY KEY,device_id BIGINT UNSIGNED NOT NULL,data LONGTEXT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX device(device_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_synced_sales (device_id BIGINT UNSIGNED NOT NULL,operation_id CHAR(36) NOT NULL,payload_hash CHAR(64) NOT NULL,sale_id BIGINT UNSIGNED NOT NULL,register_name VARCHAR(80) NOT NULL,conflicts TEXT NULL,local_date DATETIME NOT NULL,PRIMARY KEY(device_id,operation_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r2_shift_links (device_id BIGINT UNSIGNED NOT NULL,shift_uuid CHAR(36) NOT NULL,shift_id BIGINT UNSIGNED NOT NULL,opening_hash CHAR(64) NULL,close_hash CHAR(64) NULL,PRIMARY KEY(device_id,shift_uuid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ];
    foreach ($sql as $statement) $app->db->exec($statement);
    $columns = [
        'restaurant_tables'=>['space_type'=>"VARCHAR(20) NOT NULL DEFAULT 'table'",'group_id'=>'BIGINT UNSIGNED NULL','service_enabled'=>'TINYINT NOT NULL DEFAULT 0'],
        'pos_tickets'=>['display_label'=>'VARCHAR(150) NULL','service_rate'=>'DECIMAL(5,2) NOT NULL DEFAULT 0','service_total'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','open_key'=>'CHAR(32) NULL','revision'=>'INT NOT NULL DEFAULT 0','delivery_address'=>'TEXT NULL','fulfillment_state'=>"VARCHAR(20) NOT NULL DEFAULT 'pending'"],
        'pos_ticket_items'=>['paid_quantity'=>'DECIMAL(12,3) NOT NULL DEFAULT 0','paid_net'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','paid_tax'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','paid_service'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','paid_discount'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','pricing_json'=>'TEXT NULL'],
        'loyalty_ledger'=>['sale_id'=>'BIGINT UNSIGNED NULL'],
        'delivery_jobs'=>['issue_previous_status'=>'VARCHAR(20) NULL'],
        'ar_payments'=>['payment_key'=>'CHAR(32) NULL'],
        'ap_payments'=>['payment_key'=>'CHAR(32) NULL'],
        'sales'=>['service_total'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','payment_key'=>'CHAR(32) NULL','kitchen_jobs_json'=>'LONGTEXT NULL','payment_payload_hash'=>'CHAR(64) NULL'],
        'branches'=>['receipt_width'=>"VARCHAR(2) NOT NULL DEFAULT '80'",'receipt_printer_type'=>"VARCHAR(20) NOT NULL DEFAULT 'browser'",'receipt_printer_name'=>'VARCHAR(150) NULL','receipt_printer_host'=>'VARCHAR(150) NULL','receipt_printer_port'=>'INT NOT NULL DEFAULT 9100','receipt_autoprint'=>'TINYINT NOT NULL DEFAULT 0','receipt_bridge_token'=>'VARCHAR(128) NULL','printer_points_json'=>'LONGTEXT NULL','business_hours'=>'TEXT NULL','payment_methods'=>'LONGTEXT NULL','delivery_types'=>'LONGTEXT NULL'],
    ];
    foreach ($columns as $table=>$items) {
        $known = array_column($app->all('SHOW COLUMNS FROM `'.$table.'`'), 'Field');
        foreach ($items as $name=>$definition) if (!in_array($name,$known,true)) $app->db->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$name.'` '.$definition);
    }
    foreach (['loyalty_ledger'=>['uq_r2_loyalty_sale','sale_id,event'],'ar_payments'=>['uq_r2_ar_payment','receivable_id,payment_key'],'ap_payments'=>['uq_r2_ap_payment','payable_id,payment_key'],'pos_tickets'=>['uq_r2_open','tenant_id,open_key'],'sales'=>['uq_r2_payment','tenant_id,created_by,payment_key']] as $table=>[$name,$columns]) {
        if (!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$name])) $app->db->exec('ALTER TABLE `'.$table.'` ADD UNIQUE KEY `'.$name.'` ('.$columns.')');
    }
}
