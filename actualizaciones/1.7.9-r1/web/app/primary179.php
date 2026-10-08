<?php
declare(strict_types=1);

function primary179_active(App $app): bool { return $app->setting('primary179_active','0')==='1'; }
function primary179_policy(App $app,array $device,bool $lock=false): array {
    if (!primary179_active($app)) return ['version'=>179,'allowed'=>false,'generation'=>0];
    $row=$app->one('SELECT device_id,generation FROM fisitaap_primary179 WHERE tenant_id=?'.($lock?' FOR UPDATE':''),[$device['tenant_id']]);
    return ['version'=>179,'allowed'=>$row&&(int)$row['device_id']===(int)$device['id'],'generation'=>(int)($row['generation']??0)];
}
function primary179_assert_snapshot(App $app,array $device,array $snapshot): void {
    // Pre-1.7.9 outboxes remain recoverable. New catalogs must carry a current principal grant.
    if(($snapshot['offline_policy']['version']??0)!==179)return;
    $current=primary179_policy($app,$device,true);
    if(empty($snapshot['offline_policy']['allowed'])||!$current['allowed']||$current['generation']!==($snapshot['offline_policy']['generation']??0))throw new DomainException('Este catálogo no autoriza ventas sin internet: solo el equipo principal actual puede registrar operaciones nuevas.');
}
function primary179_select(App $app,array $tenant,int $id): void {
    if (!primary179_active($app)) throw new DomainException('Completa primero la actualización 1.7.9 como administrador maestro.');
    $app->db->beginTransaction();
    try {
        // The tenant row serializes both first assignment and replacement across all branches.
        $app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
        $old=$app->one('SELECT * FROM fisitaap_primary179 WHERE tenant_id=? FOR UPDATE',[$tenant['id']]);
        if ($old && $old['device_id'] && (int)$old['device_id']!==$id) throw new DomainException('Libera primero el equipo principal anterior desde su caja local, después de cerrar y sincronizar sus turnos.');
        $device=$app->one('SELECT id FROM fisitaap_r2_devices WHERE id=? AND tenant_id=? AND is_active=1 AND token_hash IS NOT NULL',[$id,$tenant['id']]);
        if (!$device) throw new DomainException('Conecta primero este equipo Windows o Android con su código.');
        if (!$old || !$old['device_id']) {
            $app->exec('INSERT INTO fisitaap_primary179(tenant_id,device_id,generation) VALUES(?,?,1) ON DUPLICATE KEY UPDATE device_id=VALUES(device_id),generation=generation+1',[$tenant['id'],$id]);
            audit($app,(int)$tenant['id'],(int)current_user()['id'],'primary179_assign','device',$id);
        }
        $app->db->commit();
    } catch(Throwable $e) { if($app->db->inTransaction())$app->db->rollBack();throw $e; }
}
function primary179_release(App $app,array $device): void {
    if (!primary179_active($app)) throw new DomainException('Actualiza primero la web a 1.7.9.');
    $app->db->beginTransaction();
    try {
        $app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$device['tenant_id']]);
        $row=$app->one('SELECT * FROM fisitaap_primary179 WHERE tenant_id=? FOR UPDATE',[$device['tenant_id']]);
        if ($row && (int)$row['device_id']===(int)$device['id']) {
            if ($app->one('SELECT l.shift_id FROM fisitaap_r2_shift_links l JOIN pos_shifts s ON s.id=l.shift_id WHERE l.device_id=? AND s.status="open" LIMIT 1',[$device['id']])) throw new DomainException('Cierra y sincroniza todos los turnos antes de liberar el equipo.');
            $app->exec('UPDATE fisitaap_primary179 SET device_id=NULL,generation=generation+1 WHERE tenant_id=?',[$device['tenant_id']]);
        }
        $app->db->commit();
    } catch(Throwable $e) {if($app->db->inTransaction())$app->db->rollBack();throw $e;}
}
function primary179_admin(App $app,array $tenant): void {
    echo '<section class="card admin-card"><h2>Equipo principal · Windows o Android</h2><p>Solo un equipo de todo el negocio puede vender sin internet. Las demás cajas venden por la web con conexión. Para cambiarlo, cierra y sincroniza sus turnos y pulsa Liberar equipo principal en su caja local. No desinstales una caja con ventas pendientes.</p>';
    if (!primary179_active($app)) { echo '<p>El administrador maestro debe completar /master/actualizar-179.</p></section>';return; }
    $row=$app->one('SELECT p.*,d.name FROM fisitaap_primary179 p LEFT JOIN fisitaap_r2_devices d ON d.id=p.device_id WHERE p.tenant_id=?',[$tenant['id']]);
    echo '<p>Principal actual: <strong>'.e($row['name']??'Ninguno').'</strong></p>';
    foreach($app->all('SELECT id,name,branch_id FROM fisitaap_r2_devices WHERE tenant_id=? AND is_active=1 AND token_hash IS NOT NULL',[$tenant['id']]) as $d) {
        if (!$row || !$row['device_id']) echo r2_form('primary',(int)$d['id']).pos_hidden_148('branch_id',$d['branch_id']).'<span>'.e($d['name']).'</span> <button class="btn">Elegir como principal</button></form>';
    }
    echo '<p>Después de elegirlo, pulsa Sincronizar en ese dispositivo para descargar su autorización. Una caja sin autorización nueva no puede cobrar localmente.</p></section>';
}
function maintenance179(App $app): void {
    $u=require_login(['master']);
    if(!$app->one('SELECT id FROM users WHERE id=? AND role="master" AND is_active=1',[$u['id']])) {http_response_code(403);exit('Acceso denegado.');}
    $message='';
    if(is_post()) {
        verify_csrf();
        if(($_POST['backup']??'')==='yes') {
            try {
            foreach([
                ['sales','idx_sales_tenant_date178','tenant_id,created_at,status'],
                ['fisitaap_r2_shift_links','idx_shift_link_shift','shift_id'],
                ['fisitaap_r2_snapshots','idx_snapshot_device_created','device_id,created_at'],
            ] as [$table,$index,$columns]) {
                if(!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$index]))$app->db->exec('ALTER TABLE '.$table.' ADD INDEX '.$index.' ('.$columns.')');
            }
            $app->db->exec('CREATE TABLE IF NOT EXISTS fisitaap_primary179 (tenant_id INT NOT NULL PRIMARY KEY, device_id INT NULL, generation INT NOT NULL DEFAULT 1) ENGINE=InnoDB');
            $app->exec('INSERT INTO settings(`key`,`value`) VALUES("maintenance178_active","1") ON DUPLICATE KEY UPDATE `value`="1"');
            $app->exec('INSERT INTO settings(`key`,`value`) VALUES("primary179_active","1") ON DUPLICATE KEY UPDATE `value`="1"');
            $message='1.7.9 activada. El dueño ya puede elegir un único equipo principal en Administración → Windows y cajas sin internet.';
            } catch (PDOException $error) {
                error_log('FISITAAP maintenance179: '.$error->getCode());
                $message='No se pudo completar la activación. Conserva el respaldo y revisa el registro de errores de cPanel. Puedes volver a intentarlo sin borrar datos.';
            }
        } else $message='Guarda y confirma el respaldo antes de continuar.';
    }
    layout_start('Actualización 1.7.9');
    $entry=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/index.php')),'/').'/activar-179.php';
    echo '<main class="auth-page"><section class="card auth-card"><h1>Activar 1.7.9</h1><p>Actualiza primero todas las cajas Windows y Android. Sincroniza y cierra turnos antes de activar esta regla. No elige automáticamente ningún principal.</p><p role="status">'.e($message).'</p><form method="post" action="'.e($entry).'">'.csrf_field().'<label style="display:flex;align-items:flex-start;gap:12px;margin-bottom:20px"><input type="checkbox" name="backup" value="yes" required><span>Guardé respaldo, sincronicé, cerré turnos y actualicé todas las cajas.</span></label><button type="submit" class="btn btn-block">Activar equipo principal único</button></form></section></main>';
    layout_end();
}
