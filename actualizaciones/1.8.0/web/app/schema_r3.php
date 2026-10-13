<?php
declare(strict_types=1);

/** Additive changes. Existing accounts, prices, roles and uploaded files are retained. */
function schema_r3(App $app): void
{
    $target=$app->one('SHOW COLUMNS FROM categories LIKE "printer_target"');
    if($target&&str_starts_with(strtolower($target['Type']),'enum('))$app->db->exec('ALTER TABLE categories MODIFY printer_target VARCHAR(32) NOT NULL DEFAULT "kitchen"');
    foreach ([
        'CREATE TABLE IF NOT EXISTS fisitaap_r3_checkout_keys (tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,operation_key CHAR(32) NOT NULL,payload_hash CHAR(64) NOT NULL,order_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(tenant_id,user_id,operation_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r3_room_versions (room_id BIGINT UNSIGNED PRIMARY KEY,revision INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r3_sectors (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,room_id BIGINT UNSIGNED NOT NULL,name VARCHAR(100) NOT NULL,color CHAR(7) NOT NULL DEFAULT "#e7f1ff",x INT NOT NULL DEFAULT 30,y INT NOT NULL DEFAULT 30,width INT NOT NULL DEFAULT 600,height INT NOT NULL DEFAULT 550,service_enabled TINYINT NOT NULL DEFAULT 0,service_rate DECIMAL(5,2) NOT NULL DEFAULT 10,INDEX scope(tenant_id,branch_id,room_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r3_objects (id CHAR(36) PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,room_id BIGINT UNSIGNED NOT NULL,kind VARCHAR(20) NOT NULL,label VARCHAR(120) NOT NULL DEFAULT "",x INT NOT NULL,y INT NOT NULL,width INT NOT NULL,height INT NOT NULL,rotation INT NOT NULL DEFAULT 0,INDEX scope(tenant_id,branch_id,room_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r3_table_details (table_id BIGINT UNSIGNED PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,sector_id BIGINT UNSIGNED NULL,rotation INT NOT NULL DEFAULT 0,basic_order INT NOT NULL DEFAULT 0,INDEX scope(tenant_id,branch_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r3_gift_cards (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,customer_id BIGINT UNSIGNED NOT NULL,code VARCHAR(32) NOT NULL,issue_key CHAR(32) NOT NULL,initial_amount DECIMAL(15,2) NOT NULL,balance DECIMAL(15,2) NOT NULL,expires_at DATETIME NOT NULL,status VARCHAR(16) NOT NULL DEFAULT "active",created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY issuance(tenant_id,issue_key),UNIQUE KEY gift_code(tenant_id,code),INDEX customer(tenant_id,customer_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        'CREATE TABLE IF NOT EXISTS fisitaap_r3_gift_entries (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,card_id BIGINT UNSIGNED NOT NULL,operation_key VARCHAR(80) NOT NULL,kind VARCHAR(16) NOT NULL,amount DECIMAL(15,2) NOT NULL,sale_id BIGINT UNSIGNED NULL,order_id BIGINT UNSIGNED NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY once_per_card(card_id,operation_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
    ] as $sql) $app->db->exec($sql);
    foreach (['pos_tickets'=>['notes'=>'TEXT NULL'], 'sale_items'=>['options_json'=>'TEXT NULL','notes'=>'VARCHAR(500) NULL']] as $table=>$columns) {
        $known = array_column($app->all('SHOW COLUMNS FROM `'.$table.'`'), 'Field');
        foreach ($columns as $name=>$definition) if (!in_array($name,$known,true)) $app->db->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$name.'` '.$definition);
    }
}
