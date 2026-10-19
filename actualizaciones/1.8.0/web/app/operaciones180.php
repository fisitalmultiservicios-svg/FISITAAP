<?php
declare(strict_types=1);
require_once __DIR__.'/commerce180.php';
require_once __DIR__.'/delivery180.php';
require_once __DIR__.'/live180.php';

function op180_ready(App $app,?int $tenant=null):bool {return $app->setting('operations180_ready','0')==='1'&&qa180_active($app,$tenant);}
function op180_schema(App $app):void {
    $tables=[
        'fisitaap_live180'=>'tenant_id BIGINT UNSIGNED PRIMARY KEY,revision BIGINT UNSIGNED NOT NULL DEFAULT 1',
        'fisitaap_order_events180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,order_id BIGINT UNSIGNED NOT NULL,status VARCHAR(32) NOT NULL,message VARCHAR(500) NOT NULL,user_id BIGINT UNSIGNED NULL,created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),INDEX timeline(tenant_id,order_id,id)',
        'fisitaap_guest_contacts180'=>'tenant_id BIGINT UNSIGNED NOT NULL,phone VARCHAR(16) NOT NULL,user_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(tenant_id,phone),INDEX customer(user_id)',
        'fisitaap_order_uploads180'=>'token_hash CHAR(64) PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,browser_hash CHAR(64) NOT NULL,operation_key CHAR(32) NOT NULL,path VARCHAR(250) NOT NULL,order_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX cleanup(order_id,created_at),INDEX scope(tenant_id,operation_key)',
        'fisitaap_driver_earnings180'=>'job_id BIGINT UNSIGNED PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,delivery_fee DECIMAL(15,2) NOT NULL,payout DECIMAL(15,2) NOT NULL,settlement_id BIGINT UNSIGNED NULL,earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX pending(tenant_id,user_id,settlement_id,job_id)',
        'fisitaap_driver_settlements180'=>'id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,paid_by BIGINT UNSIGNED NOT NULL,operation_key CHAR(32) NOT NULL,payload_hash CHAR(64) NOT NULL,amount DECIMAL(15,2) NOT NULL,account_id BIGINT UNSIGNED NULL,movement_id BIGINT UNSIGNED NOT NULL,reference VARCHAR(150) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY once_only(tenant_id,operation_key),INDEX driver_history(tenant_id,user_id,id)',
    ];
    foreach($tables as $table=>$definition){
        $app->db->exec('CREATE TABLE IF NOT EXISTS '.$table.' ('.$definition.') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $parent='tenants';
        $column=$parent==='users'?'user_id':'tenant_id';$fk='fkop180_'.substr(hash('sha256',$table),0,14);
        if(!$app->one('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?',[$table,$fk]))$app->db->exec('ALTER TABLE '.$table.' ADD CONSTRAINT '.$fk.' FOREIGN KEY('.$column.') REFERENCES '.$parent.'(id) ON DELETE CASCADE');
    }
    foreach([
        'orders'=>['maps_url180'=>'VARCHAR(1500) NULL','pin_cipher180'=>'VARCHAR(250) NULL','cancel_reason180'=>'VARCHAR(500) NULL'],
        'customer_addresses'=>['maps_url180'=>'VARCHAR(1500) NULL'],
        'pos_tickets'=>['delivery_fee180'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','maps_url180'=>'VARCHAR(1500) NULL'],
        'delivery_jobs'=>['billing180'=>'TINYINT NOT NULL DEFAULT 0','delivery_fee180'=>'DECIMAL(15,2) NOT NULL DEFAULT 0','payout_percent180'=>'DECIMAL(7,2) NULL'],
        'driver_affiliations'=>['payout_mode180'=>'VARCHAR(16) NOT NULL DEFAULT \'all\'','payout_percent180'=>'DECIMAL(7,2) NOT NULL DEFAULT 100'],
    ] as $table=>$columns){$known=array_column($app->all('SHOW COLUMNS FROM '.$table),'Field');foreach($columns as $column=>$type)if(!in_array($column,$known,true))$app->db->exec('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$type);}
    $app->exec('INSERT INTO settings(`key`,`value`) VALUES("operations180_ready","1") ON DUPLICATE KEY UPDATE value="1"');
}
function op180_touch(App $app,int $tenant):void {
    if($tenant&&op180_ready($app))$app->exec('INSERT INTO fisitaap_live180(tenant_id,revision) VALUES(?,1) ON DUPLICATE KEY UPDATE revision=revision+1',[$tenant]);
}
function op180_event(App $app,array $order,string $status,string $message,?int $actor=null):void {
    if(!op180_ready($app))return;
    $app->exec('INSERT INTO fisitaap_order_events180(tenant_id,order_id,status,message,user_id) VALUES(?,?,?,?,?)',[$order['tenant_id'],$order['id'],$status,mb_substr($message,0,500),$actor]);op180_touch($app,(int)$order['tenant_id']);
}
function op180_can_cancel(App $app,array $user,string $section='pedidos'):bool {
    $fresh=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=? AND is_active=1 AND force_password_change=0',[$user['id'],$user['tenant_id']]);
    if(!$fresh)return false;if($fresh['role']==='tenant_admin')return true;
    return restructure_can_r1($app,$fresh,$section,'delete')===true;
}
function op180_require_cancel(App $app,array $order,int $actor):void {
    if(!op180_ready($app))return;
    $u=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=?',[$actor,$order['tenant_id']]);
    if(!$u||!op180_can_cancel($app,$u,($_POST['action']??'')==='void'?'ventas':'pedidos'))throw new DomainException('Solo el administrador o un rol con permiso explícito puede cancelar pedidos.');
    $reason=mb_substr(trim((string)($_POST['cancel_reason']??'')),0,500);
    if(mb_strlen($reason)<8)throw new DomainException('Escribe la justificación de la cancelación (al menos 8 caracteres).');
    $app->exec('UPDATE orders SET cancel_reason180=? WHERE id=? AND tenant_id=?',[$reason,$order['id'],$order['tenant_id']]);
}
