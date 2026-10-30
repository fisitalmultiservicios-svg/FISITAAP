<?php
declare(strict_types=1);
require_once __DIR__.'/owner_alerts_r18.php';

function r18_ready(App $app):bool {return $app->setting('runtime_r18_ready','0')==='1';}
function r18_schema(App $app):void {
    foreach([
        'fisitaap_registers180'=>['number_r18'=>'INT UNSIGNED NULL'],
        'fisitaap_money_accounts180'=>['register_id_r18'=>'BIGINT UNSIGNED NULL','channel_r18'=>'VARCHAR(16) NULL'],
        'sales'=>['seller_id_r18'=>'BIGINT UNSIGNED NULL'],
        'pos_tickets'=>['seller_id_r18'=>'BIGINT UNSIGNED NULL'],
    ] as $table=>$columns){$known=array_column($app->all('SHOW COLUMNS FROM '.$table),'Field');foreach($columns as $column=>$def)if(!in_array($column,$known,true))$app->db->exec('ALTER TABLE '.$table.' ADD '.$column.' '.$def);}
    r18_assign_register_numbers($app);
    if(!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND INDEX_NAME="tenant_username_r18"'))$app->db->exec('ALTER TABLE users ADD UNIQUE KEY tenant_username_r18(tenant_id,username_r17)');
    if($app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND INDEX_NAME="username_r17"'))$app->db->exec('ALTER TABLE users DROP INDEX username_r17');
    if(!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="fisitaap_money_accounts180" AND INDEX_NAME="register_channel_r18"'))$app->db->exec('ALTER TABLE fisitaap_money_accounts180 ADD UNIQUE KEY register_channel_r18(register_id_r18,channel_r18)');
    $app->db->exec('CREATE TABLE IF NOT EXISTS fisitaap_owner_alert_reads_r18(tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,alert_key CHAR(64) NOT NULL,read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(tenant_id,user_id,alert_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    foreach($app->all('SELECT r.*,t.id t_id FROM fisitaap_registers180 r JOIN tenants t ON t.id=r.tenant_id WHERE r.is_active=1 AND r.number_r18>0') as $register){$app->db->beginTransaction();try{r18_ensure_funds($app,['id'=>$register['tenant_id']],$register);$app->db->commit();}catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}}
    $app->exec('INSERT INTO settings(`key`,`value`) VALUES("runtime_r18_ready","1") ON DUPLICATE KEY UPDATE value="1"');
}
function r18_assign_register_numbers(App $app):void {
    foreach($app->all('SELECT DISTINCT tenant_id FROM fisitaap_registers180 WHERE number_r18 IS NULL') as $row){$owns=!$app->db->inTransaction();if($owns)$app->db->beginTransaction();try{$tid=(int)$row['tenant_id'];$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tid]);$used=array_fill_keys(array_map('intval',array_column($app->all('SELECT number_r18 FROM fisitaap_registers180 WHERE tenant_id=? AND number_r18 IS NOT NULL',[$tid]),'number_r18')),true);foreach($app->all('SELECT id FROM fisitaap_registers180 WHERE tenant_id=? AND number_r18 IS NULL ORDER BY id FOR UPDATE',[$tid]) as $r){$n=(int)$r['id'];while(isset($used[$n]))$n++;$used[$n]=true;$app->exec('UPDATE fisitaap_registers180 SET number_r18=? WHERE id=? AND tenant_id=?',[$n,$r['id'],$tid]);}if($owns)$app->db->commit();}catch(Throwable $e){if($owns&&$app->db->inTransaction())$app->db->rollBack();throw $e;}}
}
function r18_ensure_funds(App $app,array $tenant,array $register):void {
    foreach(['card','sinpe','bank'] as $channel)if(!$app->one('SELECT id FROM fisitaap_money_accounts180 WHERE tenant_id=? AND register_id_r18=? AND channel_r18=?',[$tenant['id'],$register['id'],$channel]))r18_register_account($app,$tenant,$register,$channel);
}
function r18_order_only(array $register):bool {return isset($register['number_r18'])&&(int)$register['number_r18']===0;}
function r18_register_number(App $app,array $tenant,int $id,mixed $raw):int {
    $value=(string)$raw;if(!preg_match('/^\d{1,6}$/D',$value))throw new DomainException('Escribe el número de caja: 0 solo atiende; 1 o más puede cobrar.');$n=(int)$value;
    if($n>0&&$app->one('SELECT id FROM fisitaap_registers180 WHERE tenant_id=? AND COALESCE(number_r18,id)=? AND id<>? AND is_active=1',[$tenant['id'],$n,$id]))throw new DomainException('Ya hay otra caja activa con ese número en este negocio.');return $n;
}
function r18_assert_collecting(array $register):void {if(r18_order_only($register))throw new DomainException('Caja 0 solo abre y atiende cuentas. Cobra desde una caja numerada con tu turno abierto.');}
function r18_staff(App $app,int $tenant):array {
    return $app->all('SELECT id,name,email,username_r17,role FROM users WHERE tenant_id=? AND is_active=1 AND role IN("tenant_admin","manager","editor","cashier","kitchen") ORDER BY COALESCE(NULLIF(username_r17,""),name),id',[$tenant]);
}
function r18_seller_prompt(App $app,array $tenant):bool {return r2_setting($app,(int)$tenant['id'],'ask_seller_r18','0')==='1';}
function r18_seller(App $app,array $tenant,array $actor,array $tickets,array $input):int {
    $existing=array_values(array_unique(array_map('intval',array_filter(array_column($tickets,'seller_id_r18')))));if(count($existing)>1)throw new DomainException('Estas cuentas pertenecen a vendedores diferentes. Cóbralas por separado.');
    $requested=(int)($input['seller_id_r18']??0);$prompt=r18_seller_prompt($app,$tenant);
    if(!$requested)$requested=$existing[0]??($prompt?0:(int)$actor['id']);
    if(!$requested)throw new DomainException('Selecciona el vendedor responsable de esta venta.');
    $seller=$app->one('SELECT id FROM users WHERE id=? AND tenant_id=? AND is_active=1 AND role IN("tenant_admin","manager","editor","cashier","kitchen")',[$requested,$tenant['id']]);if(!$seller)throw new DomainException('Selecciona un vendedor activo de este negocio.');
    foreach($tickets as $t)if((float)$t['paid_total']>0&&(int)($t['seller_id_r18']??0)>0&&(int)$t['seller_id_r18']!==$requested)throw new DomainException('Conserva el vendedor de la cuenta que ya tiene cobros.');
    return $requested;
}
function r18_seller_field(App $app,array $tenant,array $actor,array $tickets):string {
    $selected=(int)($tickets[0]['seller_id_r18']??0);if(!r18_seller_prompt($app,$tenant))return pos_hidden_148('seller_id_r18',$selected?:$actor['id']);
    $out='<fieldset class="r18-seller-choice"><legend>¿Quién realizó la venta?</legend><label>Vendedor<select class="input" name="seller_id_r18" required><option value="">Seleccionar vendedor</option>';
    foreach(r18_staff($app,(int)$tenant['id']) as $s)$out.='<option value="'.(int)$s['id'].'" '.($selected===(int)$s['id']?'selected':'').'>'.e(($s['username_r17']?:$s['name']).($s['username_r17']?' · '.$s['name']:'')).'</option>';
    return $out.'</select></label><small>El vendedor queda en la venta. El cobro y el turno pertenecen al cajero que inició sesión.</small></fieldset>';
}
function r18_register_account(App $app,array $tenant,array $register,string $channel):int {
    if(!$app->db->inTransaction())throw new LogicException('La cuenta de caja se crea dentro de la operación.');
    r18_assert_collecting($register);$label=['card'=>'Tarjeta','sinpe'=>'SINPE','bank'=>'Banco / transferencias'][$channel]??throw new DomainException('Tipo de cuenta no disponible.');
    $app->one('SELECT id FROM fisitaap_registers180 WHERE tenant_id=? AND id=? FOR UPDATE',[$tenant['id'],$register['id']]);$old=$app->one('SELECT id,is_active FROM fisitaap_money_accounts180 WHERE tenant_id=? AND register_id_r18=? AND channel_r18=?',[$tenant['id'],$register['id'],$channel]);
    if($old){if(!$old['is_active'])throw new DomainException('La cuenta '.$label.' de esta caja está desactivada. Revísala en Cuentas de dinero.');return (int)$old['id'];}
    $base=mb_substr('Caja '.(int)($register['number_r18']??$register['id']).' · '.$label.' · #'.$register['id'],0,85);$name=$base;$suffix=0;while($app->one('SELECT id FROM fisitaap_money_accounts180 WHERE tenant_id=? AND name=?',[$tenant['id'],$name]))$name=$base.' · R18 '.(++$suffix);$app->exec('INSERT INTO fisitaap_money_accounts180(tenant_id,name,kind,balance,is_active,register_id_r18,channel_r18) VALUES(?,?,"bank",0,1,?,?)',[$tenant['id'],$name,$register['id'],$channel]);return (int)$app->db->lastInsertId();
}
function r18_money_scope(App $app,array $user,array $account,string $kind):?array {
    $register=qa180_native($app,$user,false,'cash')??qa180_native($app,$user,false,'print');
    if($register){r18_assert_collecting($register);if((int)($account['register_id_r18']??0)!==(int)$register['id'])throw new DomainException('Esta cuenta de dinero no pertenece a la caja de este equipo.');return $register;}
    if(!in_array($user['role'],['tenant_admin','manager'],true))throw new DomainException('Usa una caja instalada y su cuenta de dinero.');
    if($kind!=='account_opening')qa180_permission($app,$user,'configuracion','configure');return null;
}
function r18_accounts(App $app,array $tenant):array {
    $user=current_user();$register=$user?qa180_native($app,$user,false,'print'):null;
    if($register){if(r18_order_only($register))return [];return $app->all('SELECT id,name FROM fisitaap_money_accounts180 WHERE tenant_id=? AND register_id_r18=? AND is_active=1 ORDER BY name',[$tenant['id'],$register['id']]);}
    if(!$user||!in_array($user['role'],['tenant_admin','manager'],true))return [];
    return $app->all('SELECT id,name FROM fisitaap_money_accounts180 WHERE tenant_id=? AND is_active=1 ORDER BY name',[$tenant['id']]);
}
function r18_sale_funds(App $app,array $tenant,array $user,array $branch,array $shift,int $sale,array $payments,int $seller):void {
    $register=$app->one('SELECT * FROM fisitaap_registers180 WHERE id=? AND tenant_id=?',[$shift['register_id'],$tenant['id']]);if(!$register)throw new DomainException('La caja del cobro ya no está disponible.');r18_assert_collecting($register);
    $app->exec('UPDATE sales SET seller_id_r18=? WHERE id=? AND tenant_id=?',[$seller,$sale,$tenant['id']]);
    foreach(['Tarjeta'=>'card','SINPE'=>'sinpe'] as $method=>$channel){$cents=(int)($payments[$method]??0);if($cents<=0)continue;$account=r18_register_account($app,$tenant,$register,$channel);$key='r18-sale-payment-'.$sale.'-'.$channel;$hash=hash('sha256',json_encode([$sale,$account,$cents,$register['id']],JSON_THROW_ON_ERROR));$prior=$app->one('SELECT id,payload_hash FROM fisitaap_money_entries180 WHERE tenant_id=? AND operation_key=?',[$tenant['id'],$key]);if($prior){if(!hash_equals($prior['payload_hash'],$hash))throw new DomainException('El ingreso de esta venta ya tiene otro contenido.');continue;}$app->one('SELECT id FROM fisitaap_money_accounts180 WHERE tenant_id=? AND id=? FOR UPDATE',[$tenant['id'],$account]);$app->exec('UPDATE fisitaap_money_accounts180 SET balance=balance+? WHERE id=? AND tenant_id=?',[$cents/100,$account,$tenant['id']]);$app->exec('INSERT INTO fisitaap_money_entries180(tenant_id,branch_id,account_id,register_id,shift_id,user_id,operation_key,payload_hash,kind,amount,reason,entity_type,entity_id) VALUES(?,?,?,?,?,?,?,?,"sale_receipt",?,? ,"sale",?)',[$tenant['id'],$branch['id'],$account,$register['id'],$shift['id'],$user['id'],$key,$hash,$cents/100,'Cobro '.$method.' de venta #'.$sale,$sale]);}
}
function r18_cancel_empty(App $app,array $tenant,array $user,array $ticket,string $action):bool {
    if(!in_array($action,['quantity','edit_item'],true)||(float)$ticket['paid_total']>0||!empty($ticket['source_order_id'])||in_array($ticket['status'],['paid','void','merged'],true))return false;
    if($app->one('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND quantity>0 LIMIT 1',[$ticket['id']]))return false;
    $fresh=$app->one('SELECT total,paid_total FROM pos_tickets WHERE id=? AND tenant_id=?',[$ticket['id'],$tenant['id']]);if(!$fresh||pos_cents_148($fresh['total'])!==0||pos_cents_148($fresh['paid_total'])!==0)return false;
    $app->exec('UPDATE pos_tickets SET status="void",fulfillment180="cancelled",revision=revision+1 WHERE id=? AND tenant_id=?',[$ticket['id'],$tenant['id']]);
    $tables=array_unique([(int)($ticket['table_id']??0),...array_map('intval',array_column($app->all('SELECT table_id FROM fisitaap_ticket_tables180 WHERE tenant_id=? AND ticket_id=?',[$tenant['id'],$ticket['id']]),'table_id'))]);foreach($tables as $table)if($table)pos_table_state_148($app,(int)$tenant['id'],$table);
    qa180_audit($app,$user,'auto_cancel_empty','pos_ticket',(int)$ticket['id'],'Canceló automáticamente la cuenta vacía al retirar el último producto.');return true;
}
function r18_cash_bar(App $app,array $user,array $tenant,array $branch):void {
    $reg=qa180_native($app,$user,false,'print');echo '<section class="r18-cash-bar" aria-label="Caja y turno" data-r18-cash-bar data-return-to="'.e(current_url_v1()).'">';
    if(!$reg){echo '<div><strong>Caja y turno</strong><span data-native-status>Abre el sistema instalado y vincula este equipo para cobrar.</span></div><a class="btn btn-light" href="'.e(url('admin/turnos')).'">Ver turnos</a>';}
    elseif(r18_order_only($reg)){echo '<div><strong>Caja 0 · Solo atención</strong><span>'.e($reg['name']).' · Abre y atiende cuentas; el cobro se realiza en una caja numerada.</span></div><a class="btn btn-light" href="'.e(url('admin/ventas?waiting=1')).'">Cuentas abiertas</a>';}
    else{$shift=$app->one('SELECT h.*,u.name opened_name FROM pos_shifts h JOIN users u ON u.id=h.user_id WHERE h.tenant_id=? AND h.register_id=? AND h.status="open" ORDER BY h.id DESC LIMIT 1',[$tenant['id'],$reg['id']]);echo '<div><strong>Caja '.(int)($reg['number_r18']??$reg['id']).' · '.e($reg['name']).'</strong><span>'.($shift?'Turno #'.$shift['id'].' de '.e($shift['opened_name']):'Sin turno abierto · '.e($user['name'])).'</span></div>';
        if(!$shift)echo '<a class="btn primary" data-r18-open-shift href="'.e(url('admin/turnos')).'">Abrir turno</a>';
        elseif((int)$shift['user_id']===(int)$user['id'])echo '<a class="btn btn-light" href="'.e(url('admin/turnos')).'">Mi turno / Cerrar</a><button class="btn btn-light" type="button" data-dialog-open="qa180Cash">Entrada / salida</button>';
        else echo '<span class="r18-cash-locked">El cobro pertenece al usuario que abrió este turno.</span>';
    }echo '</section>';if($reg&&!r18_order_only($reg)&&isset($shift)&&$shift&&(int)$shift['user_id']===(int)$user['id'])r18_cash_dialog($app,$user);
}
function r18_dispatch(App $app,string $path):void {
    if(!r18_ready($app))return;
    if($path==='api/staff-r18'){
        $u=require_login(['tenant_admin','manager','editor','cashier','kitchen']);$tenant=$app->one('SELECT id FROM tenants WHERE id=? AND is_active=1',[$u['tenant_id']]);if(!$tenant)json_response(['ok'=>false,'error'=>'Negocio no disponible.'],403);
        header('Cache-Control: private, no-store');json_response(['ok'=>true,'users'=>r18_staff($app,(int)$tenant['id'])]);
    }
    if($path==='api/owner-alerts-r18')r18_alert_api($app);
}
function r18_cash_dialog(App $app,array $user):void {
    echo '<dialog id="qa180Cash" class="r3-dialog"><h2>Movimiento de efectivo de mi caja</h2><form method="post" action="'.e(url('admin/caja-movimiento')).'">'.csrf_field().pos_hidden_148('operation_key',bin2hex(random_bytes(16))).'<label>Movimiento<select class="input" name="direction"><option value="in">Entrada</option><option value="out">Salida</option></select></label>'.field('Monto','amount','',true,'number','.01').field('Motivo','reason','',true).field('Referencia','reference','').'<label><input type="checkbox" name="confirmed" value="yes" required> Confirmo el movimiento en mi turno.</label><footer><button class="btn btn-light" type="button" data-dialog-close>Volver</button><button class="btn">Guardar</button></footer></form></dialog>';
}
