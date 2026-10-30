<?php
declare(strict_types=1);
require_once __DIR__.'/receipts_r20.php';

function r20_ready(App $app):bool {return $app->setting('checkout_r20_ready','0')==='1';}
function r20_schema(App $app):void {
    foreach(['fisitaap_registers180'=>['web_user_r20'=>'BIGINT UNSIGNED NULL'],'sales'=>['receipt_output_r20'=>'VARCHAR(24) NOT NULL DEFAULT "none"','receipt_width_r20'=>'INT NOT NULL DEFAULT 80']] as $table=>$columns)foreach($columns as $column=>$definition)if(!$app->one('SHOW COLUMNS FROM '.$table.' LIKE "'.$column.'"'))$app->db->exec('ALTER TABLE '.$table.' ADD '.$column.' '.$definition);
    if(!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="fisitaap_registers180" AND INDEX_NAME="web_cashier_r20"'))$app->db->exec('ALTER TABLE fisitaap_registers180 ADD UNIQUE KEY web_cashier_r20(tenant_id,branch_id,web_user_r20)');
    $app->exec('INSERT INTO settings(`key`,`value`) VALUES("checkout_r20_ready","1") ON DUPLICATE KEY UPDATE value="1"');
}
function r20_web_enabled(App $app,int $tenant):bool {return r20_ready($app)&&($app->one('SELECT value FROM fisitaap_r2_settings WHERE tenant_id=? AND setting_key="web_cash_r20"',[$tenant])['value']??'0')==='1';}
function r20_web_allowed(App $app,array $user):bool {
    if(!empty($_SESSION['native180'])||!empty($GLOBALS['demo_context'])||!r20_web_enabled($app,(int)$user['tenant_id']))return false;
    try{qa180_permission($app,$user,'ventas','charge');qa180_permission($app,$user,'turnos');return true;}catch(DomainException){return false;}
}
function r20_web_register(App $app,array $user):?array {
    if(!r20_web_allowed($app,$user))return null;
    $tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$user['tenant_id']]);if(!$tenant)return null;$branch=physical_branch_v14($app,$tenant);
    // A browser acquires money authority only for its own already opened shift.
    return $app->one('SELECT r.* FROM fisitaap_registers180 r JOIN pos_shifts s ON s.register_id=r.id AND s.tenant_id=r.tenant_id AND s.branch_id=r.branch_id AND s.user_id=r.web_user_r20 AND s.status="open" WHERE r.tenant_id=? AND r.branch_id=? AND r.web_user_r20=? AND r.user_id=? AND r.device_id IS NULL AND r.is_active=1 AND r.number_r18>0 ORDER BY s.id DESC LIMIT 1',[$user['tenant_id'],$branch['id'],$user['id'],$user['id']]);
}
function r20_open_web_register(App $app,array $user,array $branch):array {
    if(!$app->db->inTransaction())throw new LogicException('La caja web se asigna dentro de su apertura.');
    $tenant=$app->one('SELECT id FROM tenants WHERE id=? AND is_active=1 FOR UPDATE',[$user['tenant_id']]);if(!$tenant)throw new DomainException('La tienda está fuera de servicio.');
    if(!r20_web_allowed($app,$user))throw new DomainException('El cobro web está desactivado o tu usuario no tiene permiso de caja.');
    if(!$app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? AND is_active=1 FOR UPDATE',[$branch['id'],$user['tenant_id']]))throw new DomainException('Sucursal no disponible.');
    $register=$app->one('SELECT * FROM fisitaap_registers180 WHERE tenant_id=? AND branch_id=? AND web_user_r20=? FOR UPDATE',[$user['tenant_id'],$branch['id'],$user['id']]);
    if(!$register){$used=array_fill_keys(array_map('intval',array_column($app->all('SELECT number_r18 FROM fisitaap_registers180 WHERE tenant_id=? AND number_r18>0',[$user['tenant_id']]),'number_r18')),true);$n=1;while(isset($used[$n]))$n++;
        $app->exec('INSERT INTO fisitaap_registers180(tenant_id,branch_id,name,user_id,number_r18,web_user_r20) VALUES(?,?,?,?,?,?)',[$user['tenant_id'],$branch['id'],'Caja '.$n.' · Web · '.mb_substr($user['name'],0,60),$user['id'],$n,$user['id']]);$register=$app->one('SELECT * FROM fisitaap_registers180 WHERE id=?',[$app->db->lastInsertId()]);r18_ensure_funds($app,$tenant,$register);
    }
    if(!$register['is_active']||(int)$register['user_id']!==(int)$user['id']||$register['device_id']||r18_order_only($register))throw new DomainException('Tu caja web está desactivada o cambió. Revisa Equipos y cajas.');
    return $register;
}
function r20_web_bar(App $app,array $user):bool {
    if(!r20_web_allowed($app,$user)||r20_web_register($app,$user))return false;
    echo '<section class="r18-cash-bar r19-cash-bar" data-r18-cash-bar data-return-to="'.e(current_url_v1()).'"><div><strong>Web · Sin turno · Solo atención</strong><span>Para cobrar, abre tu turno. Se asignará la siguiente caja disponible a tu usuario.</span></div><a class="btn primary" data-r18-open-shift href="'.e(url('admin/turnos')).'">Abrir turno</a></section>';return true;
}
function r20_output(array $form):string {
    if(empty($form['print_receipt']))return 'none';$output=(string)($form['receipt_output_r20']??'printer');
    if(!in_array($output,['printer','letter_pdf','receipt_pdf'],true))throw new DomainException('Elige un formato de comprobante válido.');return $output;
}
function r20_checkout_controls(array $branch):string {
    $profile=printer_profile_148($branch);return '<div class="r20-checkout-actions"><button class="r3-button" data-confirm-payment data-receipt-action="0" type="submit">Solo cobrar</button><div><button class="r3-button green" data-confirm-payment data-receipt-action="1" type="submit">'.r3_icon('print').'Cobrar e imprimir</button><label>Salida del comprobante<select class="r3-input" name="receipt_output_r20"><option value="printer">Impresora configurada</option><option value="letter_pdf">Hoja carta · PDF</option><option value="receipt_pdf">Tipo recibo · PDF</option></select></label><small>'.e($profile['name']?:($profile['type']==='browser'?'Predeterminada: PDF':'Impresora configurada')).' · '.($profile['width']===216?'Carta':$profile['width'].' mm').'</small></div></div>'.pos_hidden_148('print_receipt',0);
}
function r20_dispatch(App $app,string $path):void {
    if(r20_ready($app)&&$path==='admin/comprobante-r20'){r20_receipt_page($app);exit;}
}
