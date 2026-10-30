<?php
declare(strict_types=1);
require_once __DIR__.'/users_r19.php';
const R19_DIRECTORY_PREFIX='d19ad19ad19ad19ad19ad19ad19ad19a';
function r19_ready(App $app):bool {return $app->setting('workflow_r19_ready','0')==='1';}
function r19_schema(App $app):void {
    $first=!r19_ready($app);
    if(!$app->one('SHOW COLUMNS FROM pos_tickets LIKE "attention_only_r19"'))$app->db->exec('ALTER TABLE pos_tickets ADD attention_only_r19 TINYINT NOT NULL DEFAULT 0');
    foreach(['name_r19'=>'VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL','email_r19'=>'VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL'] as $column=>$definition)if(!$app->one('SHOW COLUMNS FROM fisitaap_employees_r17 LIKE "'.$column.'"'))$app->db->exec('ALTER TABLE fisitaap_employees_r17 ADD '.$column.' '.$definition);
    $app->db->exec('ALTER TABLE fisitaap_employees_r17 MODIFY user_id BIGINT UNSIGNED NULL');
    $app->exec('UPDATE fisitaap_employees_r17 e JOIN users u ON u.id=e.user_id AND u.tenant_id=e.tenant_id SET e.name_r19=COALESCE(e.name_r19,u.name),e.email_r19=COALESCE(e.email_r19,u.email) WHERE e.name_r19 IS NULL');
    if($first)$app->exec('UPDATE fisitaap_setup_r17 SET step=step+1 WHERE step>=4');
    $app->exec('INSERT INTO settings(`key`,`value`) VALUES("workflow_r19_ready","1") ON DUPLICATE KEY UPDATE value="1"');
}
function r19_directory_nonce():string {
    if(!str_starts_with((string)($_SESSION['directory_nonce_r19']??''),R19_DIRECTORY_PREFIX))session_change176(static function(){$_SESSION['directory_nonce_r19']=R19_DIRECTORY_PREFIX.bin2hex(random_bytes(16));});return $_SESSION['directory_nonce_r19'];
}
function r19_directory_proof(App $app,array $device,array $data):array {
    $nonce=(string)($data['nonce']??'');if(!preg_match('/^'.R19_DIRECTORY_PREFIX.'[a-f0-9]{32}$/D',$nonce))throw new DomainException('Solicitud de usuarios inválida.');
    $claims=['purpose'=>'staff_directory_r19','nonce'=>$nonce,'tenant'=>(int)$device['tenant_id'],'device'=>(int)$device['id'],'token_hash'=>$device['token_hash'],'expires'=>time()+90];$encoded=bin2hex(json_encode($claims,JSON_THROW_ON_ERROR));
    // Directory transport deliberately has no cash/print/user authority and never replaces offline snapshots.
    return ['ok'=>true,'cash'=>false,'proof'=>$encoded.'.'.hash_hmac('sha256',$encoded,$app->config['app_key'])];
}
function r19_directory_api(App $app):never {
    verify_csrf();try{
        $parts=explode('.',(string)($_POST['proof']??''));if(count($parts)!==2||strlen($parts[0])>2000||!ctype_xdigit($parts[0])||!hash_equals(hash_hmac('sha256',$parts[0],$app->config['app_key']),$parts[1]))throw new DomainException('El equipo no confirmó su negocio.');$claim=json_decode((string)hex2bin($parts[0]),true,16,JSON_THROW_ON_ERROR);
        if(($claim['purpose']??'')!=='staff_directory_r19'||($claim['expires']??0)<time()||!hash_equals((string)($_SESSION['directory_nonce_r19']??''),(string)($claim['nonce']??'')))throw new DomainException('Recarga el acceso para consultar sus usuarios.');
        $device=$app->one('SELECT d.id,d.tenant_id,t.name FROM fisitaap_r2_devices d JOIN tenants t ON t.id=d.tenant_id WHERE d.id=? AND d.tenant_id=? AND d.token_hash=? AND d.is_active=1 AND t.is_active=1',[$claim['device'],$claim['tenant'],$claim['token_hash']]);if(!$device)throw new DomainException('El equipo o su negocio no están disponibles.');
        header('Cache-Control: private, no-store');json_response(['ok'=>true,'business'=>$device['name'],'users'=>r19_staff($app,(int)$device['tenant_id'])]);
    }catch(Throwable $e){json_response(['ok'=>false,'error'=>$e instanceof DomainException?$e->getMessage():'No se pudo consultar el negocio.'],403);}
}
function r19_cash_bar(App $app,array $actor,array $tenant,array $branch):void {
    $reg=qa180_native($app,$actor,false,'print');echo '<section class="r18-cash-bar r19-cash-bar" aria-label="Caja y turno" data-r18-cash-bar data-return-to="'.e(current_url_v1()).'">';
    if(!$reg||r18_order_only($reg)){echo '<div><strong>Caja 0 · '.(!$reg?'Navegador':'Solo atención').'</strong><span>Abre cuentas, carga productos y envía pedidos. El cobro se realiza en una caja numerada.</span></div>';}
    elseif(!qa180_native($app,$actor,false,'cash')){echo '<div><strong>Caja '.(int)($reg['number_r18']??$reg['id']).' · Solo atención para este usuario</strong><span>Tu acceso no tiene autorización de cobro en esta caja.</span></div>';}
    else{$shift=$app->one('SELECT s.*,u.name opened_name FROM pos_shifts s JOIN users u ON u.id=s.user_id WHERE s.tenant_id=? AND s.register_id=? AND s.status="open" ORDER BY s.id DESC LIMIT 1',[$tenant['id'],$reg['id']]);echo '<div><strong>Caja '.(int)($reg['number_r18']??$reg['id']).' · '.e($reg['name']).'</strong><span>'.($shift?'Turno de '.e($shift['opened_name']):'Sin turno abierto').'</span></div>';
        if(!$shift)echo '<a class="btn primary" data-r18-open-shift href="'.e(url('admin/turnos')).'">Abrir turno</a>';
        elseif((int)$shift['user_id']===(int)$actor['id'])echo '<button class="btn btn-light" type="button" data-dialog-open="r19CloseShift">Cerrar turno</button>';
        else echo '<span>Solo quien abrió el turno puede cobrar y cerrarlo.</span>';
    }echo '</section>';
    if(!empty($shift)&&(int)$shift['user_id']===(int)$actor['id']){
        echo '<dialog id="r19CloseShift" class="r3-dialog"><header><h2>Cerrar mi turno</h2><button class="btn btn-light" type="button" data-dialog-close>Volver</button></header><form method="post" action="'.e(url('admin/turnos')).'" data-cash-counter data-expected="'.qa180_cash_expected($app,$shift).'">'.csrf_field().pos_hidden_148('action','close').pos_hidden_148('id',$shift['id']).pos_hidden_148('closing_key',bin2hex(random_bytes(16))).pos_hidden_148('return_to_r18',current_url_v1()).'<p>Cuenta el efectivo de tu caja. Los pagos por tarjeta y SINPE se concilian por separado.</p><div class="qa180-coins">';foreach(qa180_denominations() as $value)echo '<label>'.money((float)$value).'<input class="input" name="denominations['.$value.']" type="number" value="0" min="0" max="999999" step="1" data-denomination="'.$value.'"></label>';echo '</div>'.field('Otro efectivo','other_cash',0,false,'number','.01').'<p>Contado: <strong data-counted></strong> · Diferencia: <strong data-difference></strong></p><button class="btn primary">Confirmar cierre de mi turno</button></form></dialog>';
    }
}
function r19_table_status(App $app,array $tenant,array $ticket):void {
    $pending=(int)$app->one('SELECT COUNT(*) n FROM pos_ticket_items WHERE ticket_id=? AND kitchen_status="pending"',[$ticket['id']])['n'];echo '<section class="r19-table-status" data-r19-table-status data-ticket="'.(int)$ticket['id'].'"><strong>Pedido de la mesa</strong><span data-r19-preparation>'.e(ui_label_qa($ticket['fulfillment180']??'new')).'</span><span data-r19-command-count>'.$pending.' por enviar a comanda</span><a class="btn btn-light" href="'.e(url('admin/cocina')).'">Ver preparación</a><a class="btn btn-light" href="'.e(url('admin/pedidos')).'">Seguimiento de pedidos</a></section>';
}
function r19_dispatch(App $app,string $path):void {
    if(!r19_ready($app))return;
    if($path==='api/staff-login-r19'){if(!is_post())json_response(['ok'=>false],405);r19_directory_api($app);}
    if(in_array($path,['admin/usuarios','admin/empleados'],true)){try{[$u,$t]=qa180_business($app,'usuarios');if($path==='admin/usuarios')r19_users_page($app,$u,$t);else r19_basic_employees($app,$u,$t);}catch(DomainException $e){http_response_code(422);simple_error('Revisa el acceso',$e->getMessage());}exit;}
    if($path==='api/staff-r18'){$u=require_login(['tenant_admin','manager','editor','cashier','kitchen']);header('Cache-Control: private, no-store');json_response(['ok'=>true,'users'=>r19_staff($app,(int)$u['tenant_id'])]);}
    if($path==='api/table-state-r19'){
        try{[$u,$t]=qa180_business($app,'ventas');$branch=physical_branch_v14($app,$t);$ticket=pos_ticket_get_148($app,$t,$branch,(int)($_GET['ticket']??0));if(!$ticket['table_id']||!r17_menu($t))throw new DomainException('Mesa no disponible.');$pending=(int)$app->one('SELECT COUNT(*) n FROM pos_ticket_items WHERE ticket_id=? AND kitchen_status="pending"',[$ticket['id']])['n'];header('Cache-Control: private, no-store');json_response(['ok'=>true,'status'=>$ticket['status'],'label'=>ui_label_qa($ticket['fulfillment180']??'new'),'pending'=>$pending,'revision'=>(int)$ticket['revision']]);}catch(Throwable $e){json_response(['ok'=>false],403);}
    }
}
