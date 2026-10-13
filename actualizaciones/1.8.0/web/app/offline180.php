<?php
declare(strict_types=1);

function qa180_uuid():string {
    $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);$hex=bin2hex($bytes);return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
}
function qa180_offline_register(App $app,array $device):?array {
    return $app->one('SELECT * FROM fisitaap_registers180 WHERE tenant_id=? AND branch_id=? AND device_id=? AND is_active=1',[$device['tenant_id'],$device['branch_id'],$device['id']]);
}
function qa180_cash_state(App $app,array $device,array $pending=[]):array {
    $register=qa180_offline_register($app,$device);$state=['version'=>180,'tenant_id'=>(int)$device['tenant_id'],'device_id'=>(int)$device['id'],'branch_id'=>(int)$device['branch_id'],'register'=>null,'shifts'=>[],'acknowledged'=>[]];if(!$register)return $state;
    $state['register']=['id'=>(int)$register['id'],'name'=>$register['name'],'user_id'=>$register['user_id']?(int)$register['user_id']:null,'device_id'=>(int)$device['id'],'branch_id'=>(int)$register['branch_id'],'drawer_enabled'=>(bool)$register['drawer_enabled']];
    $rows=$app->all('SELECT s.*,l.shift_uuid,(SELECT COALESCE(SUM(p.amount),0) FROM pos_payments p JOIN sales v ON v.id=p.sale_id WHERE v.shift_id=s.id AND v.tenant_id=s.tenant_id AND v.status="completed" AND p.payment_method="Efectivo") sales_cash,(SELECT COALESCE(SUM(e.amount),0) FROM fisitaap_money_entries180 e WHERE e.shift_id=s.id AND e.tenant_id=s.tenant_id) cash_moves,(SELECT COALESCE(MAX(v.id),0) FROM sales v WHERE v.shift_id=s.id AND v.tenant_id=s.tenant_id AND v.status="completed") confirmed_max FROM pos_shifts s JOIN fisitaap_r2_shift_links l ON l.shift_id=s.id AND l.device_id=? WHERE s.tenant_id=? AND s.branch_id=? AND s.register_id=? AND s.status="open" ORDER BY s.id',[$device['id'],$device['tenant_id'],$device['branch_id'],$register['id']]);
    foreach($rows as $shift)$state['shifts'][]=['id'=>$shift['shift_uuid'],'server_id'=>(int)$shift['id'],'user_id'=>(int)$shift['user_id'],'register'=>$register['name'],'opening_cash'=>pos_cents_148($shift['opening_cash']),'opened_at'=>date(DATE_ATOM,strtotime($shift['opened_at'])),'server_cash_net'=>(int)round(((float)$shift['sales_cash']+(float)$shift['cash_moves'])*100),'confirmed_max'=>(int)$shift['confirmed_max'],'pending_accounts'=>qa180_pending_shift($app,$shift)?1:0];
    $pending=array_slice(array_values(array_unique(array_filter($pending,fn($id)=>is_string($id)&&preg_match('/^[a-f0-9-]{36}$/D',$id)))),0,1000);
    if($pending){$marks=implode(',',array_fill(0,count($pending),'?'));foreach($app->all('SELECT x.operation_id id,x.sale_id FROM fisitaap_r2_synced_sales x JOIN sales s ON s.id=x.sale_id WHERE x.device_id=? AND s.tenant_id=? AND x.operation_id IN('.$marks.')',[$device['id'],$device['tenant_id'],...$pending]) as $sale)$state['acknowledged'][]=['id'=>$sale['id'],'sale_id'=>(int)$sale['sale_id']];}
    return $state;
}
function qa180_snapshot_users(App $app,array $device):array {
    $register=qa180_offline_register($app,$device);if(!$register)return [];$users=[];
    foreach($app->all('SELECT * FROM users WHERE tenant_id=? AND is_active=1 AND force_password_change=0 AND (id=? OR ?=0 OR role="tenant_admin") ORDER BY name',[$device['tenant_id'],(int)($register['user_id']??0),(int)($register['user_id']??0)]) as $user)
        if(r2_offline_allowed($app,$user)&&preg_match('/^\$2[aby]\$/',$user['password_hash'])){$permissions=[];foreach(['shift'=>['turnos','write'],'discount'=>['ventas','discount'],'create_customer'=>['clientes','write'],'release'=>['configuracion','configure']] as $key=>[$section,$action]){try{qa180_permission($app,$user,$section,$action);$permissions[$key]=true;}catch(DomainException){$permissions[$key]=false;}}$users[]=['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'password_hash'=>$user['password_hash'],'permissions'=>$permissions];}
    return $users;
}
function qa180_catalog_payload(array $snapshot):array {
    unset($snapshot['id'],$snapshot['generated_at'],$snapshot['cash_state']);
    foreach($snapshot['products'] as &$product){unset($product['stock']);foreach($product['components'] as &$component)unset($component['stock']);unset($component);}unset($product);
    return $snapshot;
}
function qa180_snapshot_save(App $app,array $device,array $snapshot):array {
    $snapshot['users']=qa180_snapshot_users($app,$device);$snapshot['cash_state']=qa180_cash_state($app,$device);$snapshot['offline_policy']['version']=180;
    $snapshot['offline_policy']['allowed']=$snapshot['offline_policy']['allowed']&&$snapshot['cash_state']['register']!==null&&count($snapshot['users'])>0;
    $canonical=qa180_catalog_payload($snapshot);$hash=hash('sha256',json_encode($canonical,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $old=$app->one('SELECT id FROM fisitaap_r2_snapshots WHERE device_id=? AND catalog_hash180=?',[$device['id'],$hash]);
    if($old){$snapshot['id']=$old['id'];return $snapshot;}
    $stored=$canonical+['id'=>$snapshot['id'],'generated_at'=>$snapshot['generated_at']];
    // Stock and cash counters are current response overlays. They do not
    // duplicate a complete immutable price/catalog copy for every sale.
    $stored=qa180_snapshot_pack($app,$stored);$app->exec('INSERT INTO fisitaap_r2_snapshots(id,device_id,data,catalog_hash180) VALUES(?,?,?,?)',[$snapshot['id'],$device['id'],json_encode($stored,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$hash]);return $snapshot;
}
function qa180_sync_shift(App $app,array $device,array $meta,array $snapshot):void {
    $uuid=(string)$meta['id'];$uid=(int)$meta['user_id'];$opening=(int)r2_number($meta['opening_cash']??0,0,99999999999);$opened=strtotime((string)($meta['opened_at']??''));
    if(!$opened||$opened>time()+300)throw new DomainException('Fecha de apertura inválida.');
    $hash=hash('sha256',json_encode([$uid,$meta['register'],$opening,$meta['opened_at']],JSON_THROW_ON_ERROR));
    $app->db->beginTransaction();
    try{
        $app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$device['branch_id'],$device['tenant_id']]);$app->one('SELECT id FROM fisitaap_r2_devices WHERE id=? FOR UPDATE',[$device['id']]);primary179_assert_snapshot($app,$device,$snapshot);
        $register=qa180_offline_register($app,$device);if(!$register||$register['name']!==$meta['register'])throw new DomainException('Esta operación pertenece a otra caja.');
        $link=$app->one('SELECT * FROM fisitaap_r2_shift_links WHERE device_id=? AND shift_uuid=?',[$device['id'],$uuid]);
        if($link){
            if($link['opening_hash']&&!hash_equals($link['opening_hash'],$hash))throw new DomainException('El turno ya corresponde a otra apertura.');
            $shift=$app->one('SELECT * FROM pos_shifts WHERE id=? AND tenant_id=? AND branch_id=? AND register_id=? AND device_id=? FOR UPDATE',[$link['shift_id'],$device['tenant_id'],$device['branch_id'],$register['id'],$device['id']]);
            if(!$shift||(int)$shift['user_id']!==$uid||pos_cents_148($shift['opening_cash'])!==$opening||strtotime($shift['opened_at'])!==$opened)throw new DomainException('El turno web y el local no coinciden. No se creó otro turno.');
            if(!$link['opening_hash'])$app->exec('UPDATE fisitaap_r2_shift_links SET opening_hash=? WHERE device_id=? AND shift_uuid=?',[$hash,$device['id'],$uuid]);
        }else{
            if($opened<strtotime($snapshot['generated_at'])-300)throw new DomainException('La apertura ocurrió antes de preparar esta caja.');
            $user=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=?',[$uid,$device['tenant_id']]);if(!$user||!r2_offline_allowed($app,$user)||($register['user_id']&&(int)$register['user_id']!==$uid&&$user['role']!=='tenant_admin'))throw new DomainException('Este cajero no puede abrir el turno de esta caja.');
            $app->one('SELECT id FROM fisitaap_registers180 WHERE id=? FOR UPDATE',[$register['id']]);if($app->one('SELECT id FROM pos_shifts WHERE tenant_id=? AND register_id=? AND status="open"',[$device['tenant_id'],$register['id']]))throw new DomainException('La caja ya tiene un turno abierto. Sincroniza el turno existente.');
            $app->exec('INSERT INTO pos_shifts(tenant_id,branch_id,user_id,name,opening_cash,status,opened_at,register_id,device_id,opening_key) VALUES(?,?,?,?,?,"open",?,?,?,?)',[$device['tenant_id'],$device['branch_id'],$uid,$register['name'],$opening/100,date('Y-m-d H:i:s',$opened),$register['id'],$device['id'],substr(hash('sha256',$device['id'].'|'.$uuid),0,32)]);
            $sid=(int)$app->db->lastInsertId();$app->exec('INSERT INTO fisitaap_r2_shift_links(device_id,shift_uuid,shift_id,opening_hash) VALUES(?,?,?,?)',[$device['id'],$uuid,$sid,$hash]);qa180_audit($app,$user,'shift_open','shift',$sid,'Apertura sin internet de '.$register['name']);
        }
        $app->db->commit();
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_web_payment_status(App $app,array $device,array $data):array {
    $rows=(array)($data['payments']??[]);if(count($rows)>100)throw new DomainException('Revisa hasta 100 intentos por lote.');$results=[];$app->db->beginTransaction();
    try{
        $app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? FOR UPDATE',[$device['branch_id'],$device['tenant_id']]);
        foreach($rows as $row){$key=(string)($row['key']??'');$uid=(int)($row['user_id']??0);if(!preg_match('/^[a-f0-9]{32}$/D',$key))throw new DomainException('Referencia de cobro inválida.');
            $register=qa180_offline_register($app,$device);$user=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=?',[$uid,$device['tenant_id']]);if(!$register||!$user||($register['user_id']&&(int)$register['user_id']!==$uid&&$user['role']!=='tenant_admin'))throw new DomainException('El intento pertenece a otra caja o usuario.');
            $sale=$app->one('SELECT s.id,h.device_id FROM sales s JOIN pos_shifts h ON h.id=s.shift_id WHERE s.tenant_id=? AND s.branch_id=? AND s.created_by=? AND s.payment_key=?',[$device['tenant_id'],$device['branch_id'],$uid,$key]);
            if($sale){if((int)$sale['device_id']!==(int)$device['id'])throw new DomainException('El cobro se registró desde otro equipo.');$results[]=['key'=>$key,'status'=>'received','sale_id'=>(int)$sale['id']];continue;}
            $operation=substr(hash('sha256','web-payment|'.$key),0,32);$prior=$app->one('SELECT * FROM fisitaap_operations180 WHERE tenant_id=? AND operation_key=?',[$device['tenant_id'],$operation]);
            if(!$prior){$app->exec('INSERT INTO fisitaap_operations180(tenant_id,branch_id,user_id,operation_key,payload_hash,kind,result_id) VALUES(?,?,?,?,?,"web_payment_cancelled",?)',[$device['tenant_id'],$device['branch_id'],$uid,$operation,hash('sha256',$key),$device['id']]);}
            elseif($prior['kind']!=='web_payment_cancelled'||(int)$prior['user_id']!==$uid||!hash_equals($prior['payload_hash'],hash('sha256',$key)))throw new DomainException('Esta referencia pertenece a otra operación.');
            // The same branch lock is acquired by settlement before checking this marker.
            // A delayed original POST can no longer charge after a definitive cancellation.
            $results[]=['key'=>$key,'status'=>'cancelled'];
        }
        $app->db->commit();return ['ok'=>true,'payments'=>$results];
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}

function qa180_snapshot_pack(App $app,array $snapshot):array {
    $refs=[];foreach(['products','customers'] as $kind){$raw=json_encode($snapshot[$kind],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$branch=$kind==='products'?(int)$snapshot['branch']['id']:null;$hash=hash('sha256',$snapshot['tenant']['id'].'|'.($branch??0).'|'.$kind.'|'.$raw);$app->exec('INSERT IGNORE INTO fisitaap_catalog_blobs180(hash,tenant_id,branch_id,kind,data) VALUES(?,?,?,?,?)',[$hash,$snapshot['tenant']['id'],$branch,$kind,$raw]);$refs[$kind]=$hash;unset($snapshot[$kind]);}$snapshot['blob_refs180']=$refs;return $snapshot;
}
function qa180_snapshot_data(App $app,string $text):array {
    $snapshot=json_decode($text,true,512,JSON_THROW_ON_ERROR);if(empty($snapshot['blob_refs180']))return $snapshot;
    foreach(['products','customers'] as $kind){$hash=(string)($snapshot['blob_refs180'][$kind]??'');if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new DomainException('El catálogo de esta operación necesita revisión. Se conservaron sus datos.');$tenant=(int)$snapshot['tenant']['id'];$branch=$kind==='products'?(int)$snapshot['branch']['id']:null;
        $snapshot[$kind]=$app->cached('catalog-blob180:'.$tenant.':'.($branch??0).':'.$kind.':'.$hash,function()use($app,$hash,$tenant,$branch,$kind){$row=$app->one('SELECT data FROM fisitaap_catalog_blobs180 WHERE hash=? AND tenant_id=? AND kind=? AND branch_id<=>?',[$hash,$tenant,$kind,$branch]);if(!$row||!hash_equals($hash,hash('sha256',$tenant.'|'.($branch??0).'|'.$kind.'|'.$row['data'])))throw new DomainException('Falta una versión del catálogo. Conserva el respaldo y las ventas pendientes.');return json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);});
    }unset($snapshot['blob_refs180']);return $snapshot;
}
