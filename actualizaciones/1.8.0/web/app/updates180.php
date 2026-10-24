<?php
declare(strict_types=1);
require_once __DIR__.'/schema180.php';

function qa180_scope(App $app,int $tenant):void {
    $GLOBALS['qa180_tenant_scope']=$tenant;
    unset($GLOBALS['qa180_version_cache'][spl_object_id($app).':'.$tenant]);
}
function qa180_update_lock(App $app,int $tenant):void {
    $key=spl_object_id($app).':'.$tenant;
    if(isset($GLOBALS['qa180_update_locks'][$key]))return;
    $name='fisitaap:update180:'.$tenant;
    if((int)($app->one('SELECT GET_LOCK(?,5) locked',[$name])['locked']??0)!==1)
        throw new DomainException('Este negocio está terminando otra operación. Vuelve a intentar en unos segundos.');
    $GLOBALS['qa180_update_locks'][$key]=true;
    register_shutdown_function(static function()use($app,$name):void{
        try{$app->one('SELECT RELEASE_LOCK(?) released',[$name]);}catch(Throwable $ignored){}
    });
}
function qa180_request_context(App $app,string $path):void {
    $user=current_user();$tenant=(int)($user['tenant_id']??0);
    $first=explode('/',$path)[0];
    if($first!==''&&!in_array($first,['admin','master','api','login','owner-login','logout','account','demo','driver'],true)){
        $store=tenant_by_slug($app,$first);if($store)$tenant=(int)$store['id'];
    }
    if(in_array($path,['api/checkout','api/coupon-preview'],true)){
        $data=$_POST;
        if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json')){
            $raw=file_get_contents('php://input');if(strlen($raw)>2*1024*1024)json_response(['ok'=>false,'error'=>'El pedido es demasiado grande.'],413);
            $data=json_decode($raw,true)?:[];
            if(is_array($data)&&is_string($data['csrf']??null)&&empty($_POST['csrf']))$_POST['csrf']=$data['csrf'];
        }
        $slug=$data['tenant']??null;if(is_string($slug)&&strlen($slug)<=180){$store=tenant_by_slug($app,$slug);if($store)$tenant=(int)$store['id'];}
    }
    qa180_scope($app,$tenant);
    // Never hold a business update lock during reads or an external chat call.
    $section=explode('/',$path)[1]??'';
    $mutatingAdmin=str_starts_with($path,'admin/')&&in_array($section,['ventas','cobro','turnos','compras','cxc','cxp','extras','opcionales','combos','promociones','credito','mesas','salones','roles','escritorio','actualizar-sistema','fidelizacion'],true);
    if(is_post()&&$tenant&&($mutatingAdmin||$path==='api/checkout')){
        verify_csrf();
        qa180_update_lock($app,$tenant);qa180_scope($app,$tenant);
    }
}
function qa180_publish(App $app):void {
    if((int)($app->one('SELECT GET_LOCK("fisitaap:publish180",5) locked')['locked']??0)!==1)
        throw new DomainException('Otra publicación está en curso. Vuelve a intentar en unos segundos.');
    try{
        $prior=$app->one('SELECT `value` FROM settings WHERE `key`="qa180_active"');
        $rollout=$app->one('SELECT `value` FROM settings WHERE `key`="qa180_rollout"');
        $alreadyGlobal=($prior['value']??'0')==='1'&&($rollout['value']??'0')!=='1';
        $version=$alreadyGlobal?180:179;
        $migrated=$app->one('SELECT `value` FROM settings WHERE `key`="modifiers180_migrated"');
        schema180($app); // Additive preparation; no ordinary request performs DDL.
        $app->db->beginTransaction();
        $app->exec('INSERT IGNORE INTO fisitaap_tenant_updates180(tenant_id,version,modifiers_migrated) SELECT id,?,? FROM tenants',[$version,(int)(($migrated['value']??'0')==='1'&&($rollout['value']??'0')!=='1')]);
        $app->exec('INSERT INTO settings(`key`,`value`) VALUES("qa180_active","1"),("qa180_rollout","1") ON DUPLICATE KEY UPDATE `value`="1"');
        if(($rollout['value']??'0')!=='1'&&current_user()){
            $ip=hash('sha256',($_SERVER['REMOTE_ADDR']??'').$app->config['app_key']);
            $app->exec('INSERT INTO audit_logs(tenant_id,user_id,action,entity_type,entity_id,ip_hash,summary) VALUES(NULL,?,"publish180","configuration",NULL,?,"Publicó la actualización 1.8.0 para activación por negocio")',[(int)current_user()['id'],$ip]);
        }
        $app->db->commit();
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
    finally{$app->one('SELECT RELEASE_LOCK("fisitaap:publish180") released');}
}
function qa180_update_blockers(App $app,int $tenant):array {
    $out=[];
    foreach($app->all('SELECT s.id,s.name,b.name branch,u.name cashier FROM pos_shifts s LEFT JOIN branches b ON b.id=s.branch_id LEFT JOIN users u ON u.id=s.user_id WHERE s.tenant_id=? AND s.status="open" ORDER BY s.opened_at',[$tenant]) as $row)
        $out[]='Turno abierto: '.($row['name']?:'#'.$row['id']).' · '.($row['branch']??'Sucursal').' · '.($row['cashier']??'Cajero');
    $tickets=$app->one('SELECT COUNT(*) total FROM pos_tickets t WHERE t.tenant_id=? AND t.status NOT IN("paid","void","merged") AND (t.table_id IS NOT NULL OR t.paid_total>0 OR EXISTS(SELECT 1 FROM pos_ticket_items i WHERE i.ticket_id=t.id))',[$tenant]);
    if((int)$tickets['total'])$out[]=(int)$tickets['total'].' cuenta(s) con productos, mesa o pagos pendientes. Cobra o cancela esas cuentas.';
    return $out;
}
function qa180_initialize_new_tenant(App $app,int $tenant,array $actor):void {
    // Called only inside tenant creation, before its transaction commits.
    // Existing businesses retain their chosen activation date.
    if($app->setting('qa180_active','0')!=='1')return;
    if(!$app->db->inTransaction())throw new LogicException('La preparación del negocio requiere su transacción de creación.');
    $app->exec('INSERT INTO fisitaap_tenant_updates180(tenant_id,version) VALUES(?,179)',[$tenant]);
    qa180_modifiers_upgrade($app,$tenant);
    qa180_register_upgrade($app,$tenant);
    qa180_floor_upgrade($app,$tenant);
    qa180_roles_upgrade($app,$tenant);
    $app->exec('UPDATE fisitaap_tenant_updates180 SET version=180,modifiers_migrated=1,activated_by=?,activated_at=NOW() WHERE tenant_id=?',[$actor['id'],$tenant]);
    unset($GLOBALS['qa180_version_cache'][spl_object_id($app).':'.$tenant]);
}
function qa180_upgrade_tenant(App $app,int $tenant,array $actor):void {
    if($app->setting('qa180_rollout','0')!=='1')throw new DomainException('El administrador de la plataforma todavía debe preparar la publicación.');
    qa180_update_lock($app,$tenant);qa180_scope($app,$tenant);
    if(qa180_active($app,$tenant))return;
    $app->db->beginTransaction();try{
        $store=$app->one('SELECT id FROM tenants WHERE id=? AND is_active=1 FOR UPDATE',[$tenant]);if(!$store)throw new DomainException('Negocio no disponible.');
        $app->all('SELECT id FROM branches WHERE tenant_id=? ORDER BY id FOR UPDATE',[$tenant]);
        $blockers=qa180_update_blockers($app,$tenant);if($blockers)throw new DomainException(implode(' · ',$blockers));
        qa180_modifiers_upgrade($app,$tenant);
        qa180_register_upgrade($app,$tenant);
        qa180_floor_upgrade($app,$tenant);
        qa180_roles_upgrade($app,$tenant);
        $app->exec('INSERT INTO fisitaap_tenant_updates180(tenant_id,version,modifiers_migrated,activated_by,activated_at) VALUES(?,180,1,?,NOW()) ON DUPLICATE KEY UPDATE version=180,modifiers_migrated=1,activated_by=VALUES(activated_by),activated_at=VALUES(activated_at)',[$tenant,$actor['id']]);
        audit($app,$tenant,(int)$actor['id'],'update180','tenant',$tenant);
        $app->db->commit();qa180_scope($app,$tenant);
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_updates_page(App $app,bool $master):void {
    $user=require_login($master?['master']:['tenant_admin']);
    $actor=$app->one('SELECT * FROM users WHERE id=? AND is_active=1 AND role=?',[$user['id'],$master?'master':'tenant_admin']);
    if(!$actor){http_response_code(403);simple_error('Acceso no autorizado','El dueño administra las actualizaciones de su negocio.');return;}
    $tenant=$master?null:$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$actor['tenant_id']]);
    if(!$master&&!$tenant){http_response_code(403);return;}
    $message='';$error='';
    if(is_post()){
        verify_csrf();try{
            $action=(string)($_POST['action']??'');
            if($master&&$action==='publish'){
                if(($_POST['backup']??'')!=='yes')throw new DomainException('Confirma que guardaste el respaldo completo.');
                qa180_publish($app);$message='Actualización 1.8.0 publicada. Cada negocio puede activarla desde Administración → Actualizar sistema cuando esté listo.';
            }elseif($action==='activate'){
                if(($_POST['ready']??'')!=='yes')throw new DomainException('Confirma que sincronizaste, cerraste los turnos y actualizaste las aplicaciones de este negocio.');
                $id=$master?(int)($_POST['tenant_id']??0):(int)$tenant['id'];
                if(!$id)throw new DomainException('Selecciona el negocio que vas a actualizar.');
                qa180_upgrade_tenant($app,$id,$actor);$message='Actualización 1.8.0 activada para este negocio. Los demás negocios conservan su estado.';
            }else throw new DomainException('Acción no disponible.');
        }catch(Throwable $failure){$error=$failure instanceof PDOException?'No se completó la preparación. Conserva el respaldo y revisa el registro del hosting antes de repetir.':$failure->getMessage();}
    }
    $ready=$app->one('SELECT `value` FROM settings WHERE `key`="qa180_rollout"');$published=($ready['value']??'0')==='1';
    admin_shell_start('Actualizar sistema',$user,$tenant,'actualizar-sistema');
    echo '<section class="card admin-card"><h1>Actualizar sistema</h1><p>Versión disponible: <strong>1.8.0-R11 · Windows 1.8.1</strong>. Los negocios nuevos comienzan actualizados. Los negocios anteriores pendientes eligen su momento para activar los cambios.</p>';
    if($message)echo '<p class="alert alert-success" role="status">'.e($message).'</p>';
    if($error)echo '<p class="alert alert-error" role="alert">'.e($error).'</p>';
    if($master){
        echo '<h2>Publicación de la plataforma</h2><p>Prepara las tablas y los índices necesarios. Los negocios pendientes continúan con el funcionamiento anterior y sus turnos abiertos. Guarda respaldo antes de publicar.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="publish"><label><input type="checkbox" name="backup" value="yes" required> Guardé respaldo de archivos y base de datos.</label><button class="btn">'.($published?'Comprobar publicación':'Publicar actualización').'</button></form>';
        if($published){
            $demoTable=$app->one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="fisitaap_demo_sessions"');
            $real=$demoTable?' AND NOT EXISTS(SELECT 1 FROM fisitaap_demo_sessions d WHERE d.tenant_id=t.id)':'';
            echo '<h2>Estado de los negocios</h2><div class="table-wrap"><table class="table"><tr><th>Negocio</th><th>Versión</th><th>Actualizado</th></tr>';
            foreach($app->all('SELECT t.id,t.name,COALESCE(v.version,179) version,v.activated_at FROM tenants t LEFT JOIN fisitaap_tenant_updates180 v ON v.tenant_id=t.id WHERE t.is_active=1'.$real.' ORDER BY t.name') as $row)
                echo '<tr><td>'.e($row['name']).'</td><td>'.((int)$row['version']>=180?'1.8.0':'Pendiente · 1.7.9').'</td><td>'.e($row['activated_at']??'A elección del dueño').'</td></tr>';
            echo '</table></div><h2>Ayudar a un negocio</h2><form method="post">'.csrf_field().'<input type="hidden" name="action" value="activate"><label>Negocio<select class="input" name="tenant_id" required><option value="">Selecciona</option>';
            foreach($app->all('SELECT id,name FROM tenants WHERE is_active=1 ORDER BY name') as $row)echo '<option value="'.(int)$row['id'].'">'.e($row['name']).'</option>';
            echo '</select></label><label><input type="checkbox" name="ready" value="yes" required> Este negocio sincronizó sus cajas, cerró sus turnos y actualizó Windows/Android.</label><button class="btn">Actualizar el negocio seleccionado</button></form>';
        }
    }elseif(!$published)echo '<p>La publicación todavía está en preparación. El administrador de la plataforma debe completarla.</p>';
    elseif(qa180_active($app,(int)$tenant['id']))echo '<p class="alert alert-success">Tu negocio ya está actualizado a 1.8.0.</p><p><a class="btn btn-light" href="https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Escritorio-1.8.1-Windows-x64.exe">Descargar Windows 1.8.1</a></p><p>Actualiza Windows sobre la instalación actual y prepara el acceso local antes de desconectarte. Conserva los datos locales.</p>';
    else{
        $blockers=qa180_update_blockers($app,(int)$tenant['id']);
        echo '<p>Tu negocio continúa en 1.7.9. Puedes seguir trabajando y volver aquí cuando puedas actualizar.</p><p><a class="btn btn-light" href="https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Escritorio-1.8.1-Windows-x64.exe" target="_blank" rel="noopener">Descargar Windows</a> <a class="btn btn-light" href="https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Android-1.8.0.apk" target="_blank" rel="noopener">Descargar Android</a></p><ol><li>Sincroniza cada caja y confirma que no quedan ventas pendientes.</li><li>Cobra o cancela las cuentas abiertas y cierra los turnos de todas las sucursales de este negocio.</li><li>Instala Windows 1.8.1 y Android 1.8.0 conservando sus datos.</li></ol>';
        if($blockers){echo '<h2>Pendiente en este negocio</h2><ul>';foreach($blockers as $line)echo '<li>'.e($line).'</li>';echo '</ul><a class="btn btn-light" href="'.e(url('admin/turnos')).'">Abrir turnos</a>';}
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="activate"><label><input type="checkbox" name="ready" value="yes" required> Todas las cajas de mi negocio están sincronizadas, sin ventas pendientes, con sus turnos cerrados y sus aplicaciones actualizadas.</label><button class="btn" '.($blockers?'disabled':'').'>Actualizar sistema</button></form>';
    }
    echo '</section>';admin_shell_end();
}
