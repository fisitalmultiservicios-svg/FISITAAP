<?php
declare(strict_types=1);
require_once __DIR__.'/schema180.php';

function qa180_permission(App $app,array $user,string $section,string $action='write'):void {
    $fresh=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=? AND is_active=1',[$user['id'],$user['tenant_id']]);
    if(!$fresh)throw new DomainException('Tu acceso cambió. Inicia sesión nuevamente.');
    $allowed=restructure_can_r1($app,$fresh,$section,$action);
    $default=role_can($fresh['role'],r2_alias($section));
    if(in_array($action,['configure','credit','price'],true))$default=$default&&in_array($fresh['role'],['tenant_admin','manager'],true);
    if(in_array($action,['charge','credit_sale'],true))$default=$default&&in_array($fresh['role'],['tenant_admin','manager','cashier'],true);
    if($allowed===false||($allowed===null&&!$default))throw new DomainException('Tu rol no permite esta operación.');
}
function qa180_audit(App $app,array $user,string $action,string $entity,int $id,string $summary):void {
    $auditId=audit($app,(int)$user['tenant_id'],(int)$user['id'],$action,$entity,$id);
    $app->exec('UPDATE audit_logs SET summary=? WHERE id=?',[mb_substr($summary,0,500),$auditId]);
}
function qa180_prepare_native(App $app):void {
    if(!qa180_active($app)||!current_user()||!in_array(current_user()['role'],['tenant_admin','manager','editor','cashier','kitchen'],true))return;
    if(empty($_SESSION['native_nonce180']))session_change176(static function(){$_SESSION['native_nonce180']=bin2hex(random_bytes(32));});
}
function qa180_proof(App $app,array $device,array $data):array {
    $nonce=(string)($data['nonce']??'');$uid=(int)($data['user_id']??0);
    if(!preg_match('/^[a-f0-9]{64}$/D',$nonce))throw new DomainException('Solicitud de autorización inválida.');
    $user=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=? AND is_active=1 AND force_password_change=0',[$uid,$device['tenant_id']]);
    if(!$user)throw new DomainException('El usuario no pertenece al negocio de este equipo.');
    $cash=true;try{qa180_permission($app,$user,'ventas','charge');}catch(DomainException $error){$cash=false;qa180_permission($app,$user,'cocina','write');}
    $register=$app->one('SELECT * FROM fisitaap_registers180 WHERE device_id=? AND tenant_id=? AND branch_id=? AND is_active=1',[$device['id'],$device['tenant_id'],$device['branch_id']]);
    if(!$register||($cash&&$register['user_id']&&(int)$register['user_id']!==$uid&&$user['role']!=='tenant_admin'))throw new DomainException('El dueño debe asignar una caja y un cajero autorizado a este equipo.');
    $claims=['nonce'=>$nonce,'user'=>$uid,'tenant'=>(int)$device['tenant_id'],'device'=>(int)$device['id'],'token_hash'=>$device['token_hash'],'register'=>(int)$register['id'],'branch'=>(int)$device['branch_id'],'cash'=>$cash,'print'=>true,'expires'=>time()+120];
    $bound=false;if(!empty($data['confirm'])){$bound=(bool)$app->one('SELECT id FROM fisitaap_operations180 WHERE tenant_id=? AND user_id=? AND result_id=? AND operation_key=? AND payload_hash=? AND kind="native_binding" AND created_at>=DATE_SUB(NOW(),INTERVAL 2 MINUTE)',[$device['tenant_id'],$uid,$device['id'],substr(hash('sha256','native|'.$nonce),0,32),hash('sha256',$nonce)]);if(!$bound)throw new DomainException('Primero confirma el acceso del usuario en la web.');}
    $encoded=bin2hex(json_encode($claims,JSON_THROW_ON_ERROR));
    $owns=!$app->db->inTransaction();if($owns)$app->db->beginTransaction();
    try{$state=qa180_cash_state($app,$device,(array)($data['pending']??[]));$policy=primary179_policy($app,$device);$policy['version']=180;$policy['allowed']=$policy['allowed']&&$cash&&count(qa180_snapshot_users($app,$device))>0;if($owns)$app->db->commit();}
    catch(Throwable $error){if($owns&&$app->db->inTransaction())$app->db->rollBack();throw $error;}
    return ['ok'=>true,'proof'=>$encoded.'.'.hash_hmac('sha256',$encoded,$app->config['app_key']),'cash'=>$cash,'bound'=>$bound,'cash_state'=>$state,'offline_policy'=>$policy];
}
function qa180_bind(App $app):never {
    $user=require_login(['tenant_admin','manager','editor','cashier','kitchen']);verify_csrf();
    try{
        $parts=explode('.',(string)($_POST['proof']??''));
        if(count($parts)!==2||strlen($parts[0])>2000||!ctype_xdigit($parts[0])||!hash_equals(hash_hmac('sha256',$parts[0],$app->config['app_key']),$parts[1]))throw new DomainException('El equipo no confirmó su autorización.');
        $claims=json_decode((string)hex2bin($parts[0]),true,32,JSON_THROW_ON_ERROR);
        if($claims['expires']<time()||$claims['user']!==(int)$user['id']||$claims['tenant']!==(int)$user['tenant_id']||!hash_equals((string)($_SESSION['native_nonce180']??''),(string)$claims['nonce']))throw new DomainException('La autorización venció o pertenece a otra sesión.');
        $_SESSION['native180']=$claims+['session_expires'=>time()+900];
        $_SESSION['physical_branch'][(int)$user['tenant_id']]=(int)$claims['branch'];
        $register=qa180_native($app,$user,true,'print');$app->exec('INSERT INTO fisitaap_operations180(tenant_id,branch_id,user_id,operation_key,payload_hash,kind,result_id) VALUES(?,?,?,?,?,"native_binding",?) ON DUPLICATE KEY UPDATE result_id=VALUES(result_id),branch_id=VALUES(branch_id),created_at=NOW()',[$user['tenant_id'],$register['branch_id'],$user['id'],substr(hash('sha256','native|'.$claims['nonce']),0,32),hash('sha256',$claims['nonce']),$register['device_id']]);json_response(['ok'=>true,'cash'=>(bool)($claims['cash']??false),'register'=>$register['name'],'device_id'=>(int)$register['device_id'],'branch_id'=>(int)$register['branch_id']]);
    }catch(Throwable $error){unset($_SESSION['native180']);json_response(['ok'=>false,'error'=>$error instanceof PDOException?'No se pudo comprobar el equipo.':$error->getMessage()],403);}
}
function qa180_native(App $app,array $user,bool $required=false,string $capability='cash'):?array {
    // Public demos manipulate virtual money only. Real devices never inherit
    // this exemption: the authenticated disposable tenant must match exactly.
    if(!empty($GLOBALS['demo_context'])&&(int)$GLOBALS['demo_context']['tenant_id']===(int)$user['tenant_id']){
        $branch=physical_branch_v14($app,$app->one('SELECT * FROM tenants WHERE id=?',[$user['tenant_id']]));
        $register=$app->one('SELECT * FROM fisitaap_registers180 WHERE tenant_id=? AND branch_id=? AND device_id IS NULL AND is_active=1',[$user['tenant_id'],$branch['id']]);
        if(!$register){$app->exec('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name) VALUES(?,?,"Caja de demostración")',[$user['tenant_id'],$branch['id']]);$register=$app->one('SELECT * FROM fisitaap_registers180 WHERE id=?',[$app->db->lastInsertId()]);}
        return $register;
    }
    $grant=$_SESSION['native180']??null;$register=null;
    if($grant&&($grant[$capability]??false)&&($grant['session_expires']??0)>=time()&&($grant['user']??0)===(int)$user['id']&&($grant['tenant']??0)===(int)$user['tenant_id']){
        try{qa180_permission($app,$user,$capability==='cash'?'ventas':'cocina',$capability==='cash'?'charge':'write');}catch(DomainException $error){if($capability==='cash')return $required?throw $error:null;qa180_permission($app,$user,'ventas','read');}
        $register=$app->one('SELECT r.*,d.last_sync FROM fisitaap_registers180 r JOIN fisitaap_r2_devices d ON d.id=r.device_id AND d.tenant_id=r.tenant_id AND d.branch_id=r.branch_id WHERE r.id=? AND r.device_id=? AND r.tenant_id=? AND r.is_active=1 AND d.is_active=1 AND d.token_hash=? AND (?="print" OR r.user_id IS NULL OR r.user_id=? OR ?="tenant_admin")',[$grant['register'],$grant['device'],$user['tenant_id'],$grant['token_hash']??'',$capability,$user['id'],$user['role']]);
    }
    if(!$register&&$required)throw new DomainException('Abre esta operación en FISITAAP Windows o Android, desde un equipo y una caja autorizados.');
    return $register;
}
function qa180_cash_shift(App $app,array $user,array $branch,bool $lock=true):array {
    $register=qa180_native($app,$user,true);
    if((int)$register['branch_id']!==(int)$branch['id'])throw new DomainException('Esta caja está asignada a otra sucursal.');
    $shift=$app->one('SELECT * FROM pos_shifts WHERE tenant_id=? AND branch_id=? AND user_id=? AND register_id=? AND status="open" ORDER BY id DESC LIMIT 1'.($lock?' FOR UPDATE':''),[$user['tenant_id'],$branch['id'],$user['id'],$register['id']]);
    if(!$shift)throw new DomainException('Inicia un turno en la caja autorizada antes de mover dinero.');
    return $shift;
}
function qa180_money(App $app,array $user,array $branch,int $account,int $cents,string $key,string $kind,string $reason,string $reference='',string $entity='',?int $entityId=null):int {
    if(!$app->db->inTransaction())throw new LogicException('Los movimientos de dinero requieren transacción.');
    if(!$cents||abs($cents)>99999999900||!preg_match('/^[a-zA-Z0-9:_-]{16,100}$/D',$key)||trim($reason)==='')throw new DomainException('Revisa el monto, el motivo y la referencia de operación.');
    $hash=hash('sha256',json_encode([$account,$cents,$kind,$reason,$reference,$entity,$entityId],JSON_THROW_ON_ERROR));
    $prior=$app->one('SELECT id,payload_hash FROM fisitaap_money_entries180 WHERE tenant_id=? AND operation_key=?',[$user['tenant_id'],$key]);
    if($prior){if(!hash_equals($prior['payload_hash'],$hash))throw new DomainException('Esta referencia ya corresponde a otro movimiento.');return (int)$prior['id'];}
    $shift=null;$register=null;
    if($account===0){
        $shift=qa180_cash_shift($app,$user,$branch);$register=$shift['register_id'];
        if($cents<0&&qa180_cash_expected($app,$shift)+$cents<0)throw new DomainException('El efectivo disponible en esta caja es insuficiente.');
    }else{
        $money=$app->one('SELECT * FROM fisitaap_money_accounts180 WHERE id=? AND tenant_id=? AND is_active=1 FOR UPDATE',[$account,$user['tenant_id']]);
        if(!$money)throw new DomainException('La cuenta de dinero no está disponible para este negocio.');
        $app->exec('UPDATE fisitaap_money_accounts180 SET balance=balance+? WHERE id=? AND tenant_id=?',[$cents/100,$account,$user['tenant_id']]);
    }
    $app->exec('INSERT INTO fisitaap_money_entries180(tenant_id,branch_id,account_id,register_id,shift_id,user_id,operation_key,payload_hash,kind,amount,reason,reference,entity_type,entity_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$user['tenant_id'],$branch['id'],$account?:null,$register,$shift['id']??null,$user['id'],$key,$hash,$kind,$cents/100,mb_substr($reason,0,250),mb_substr($reference,0,150),$entity,$entityId]);
    return (int)$app->db->lastInsertId();
}
function qa180_cash_expected(App $app,array $shift):int {
    $sales=(float)$app->one('SELECT COALESCE(SUM(p.amount),0) total FROM pos_payments p JOIN sales s ON s.id=p.sale_id WHERE s.tenant_id=? AND s.shift_id=? AND s.status="completed" AND p.payment_method="Efectivo"',[$shift['tenant_id'],$shift['id']])['total'];
    $moves=(float)$app->one('SELECT COALESCE(SUM(amount),0) total FROM fisitaap_money_entries180 WHERE tenant_id=? AND shift_id=?',[$shift['tenant_id'],$shift['id']])['total'];
    return pos_cents_148($shift['opening_cash'])+(int)round(($sales+$moves)*100);
}
function qa180_pending_shift(App $app,array $shift):bool {
    // Include pre-update web tickets belonging to this cashier. Accounts from
    // other devices/cashiers do not block an independent register's closing.
    return (bool)$app->one('SELECT id FROM pos_tickets WHERE tenant_id=? AND branch_id=? AND status NOT IN("paid","void","merged") AND (shift_id=? OR (shift_id IS NULL AND opened_by=? AND source_order_id IS NULL)) AND (table_id IS NOT NULL OR paid_total>0 OR EXISTS(SELECT 1 FROM pos_ticket_items i WHERE i.ticket_id=pos_tickets.id)) LIMIT 1',[$shift['tenant_id'],$shift['branch_id'],$shift['id'],$shift['user_id']]);
}
