<?php
declare(strict_types=1);

function op180_payout(array $job,array $affiliation):int {
    if(empty($job['billing180']))return pos_cents_148($job['payout']);
    $percent=$affiliation['payout_mode180']==='percent'?(float)$affiliation['payout_percent180']:100;
    return (int)round(pos_cents_148($job['delivery_fee180'])*$percent/100);
}
function op180_assign_terms(App $app,int $tenant,int $driver,int $job):void {
    if(!op180_ready($app))return;
    $j=$app->one('SELECT * FROM delivery_jobs WHERE id=? AND tenant_id=?',[$job,$tenant]);$a=$app->one('SELECT * FROM driver_affiliations WHERE tenant_id=? AND user_id=? AND status="active"',[$tenant,$driver]);
    if(!$j||!$a)throw new DomainException('Afiliación no disponible.');
    if($j['billing180'])$app->exec('UPDATE delivery_jobs SET payout=?,payout_percent180=? WHERE id=?',[op180_payout($j,$a)/100,$a['payout_mode180']==='percent'?$a['payout_percent180']:100,$job]);
}
function op180_earn(App $app,array $job):void {
    if(!op180_ready($app)||empty($job['assigned_driver_id']))return;
    $app->exec('INSERT IGNORE INTO fisitaap_driver_earnings180(job_id,tenant_id,branch_id,user_id,delivery_fee,payout) VALUES(?,?,?,?,?,?)',[$job['id'],$job['tenant_id'],$job['branch_id'],$job['assigned_driver_id'],$job['billing180']?$job['delivery_fee180']:0,$job['payout']]);
    op180_touch($app,(int)$job['tenant_id']);
}
function op180_settlement(App $app,array $user,array $tenant,array $input):int {
    qa180_permission($app,$user,'express','configure');qa180_update_lock($app,(int)$tenant['id']);$driver=(int)($input['driver_id']??0);$key=(string)($input['operation_key']??'');$account=(int)($input['account_id']??0);$reference=mb_substr(trim((string)($input['reference']??'')),0,150);
    $ids=array_values(array_unique(array_filter(array_map('intval',(array)($input['jobs']??[])))));sort($ids);
    if(!preg_match('/^[a-f0-9]{32}$/D',$key)||!$ids||count($ids)>200||$reference==='')throw new DomainException('Selecciona las entregas e indica la referencia del pago.');
    $hash=hash('sha256',json_encode([$driver,$account,$reference,$ids],JSON_THROW_ON_ERROR));$app->db->beginTransaction();
    try{
        $app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
        $old=$app->one('SELECT id,payload_hash FROM fisitaap_driver_settlements180 WHERE tenant_id=? AND operation_key=?',[$tenant['id'],$key]);
        if($old){if(!hash_equals($old['payload_hash'],$hash))throw new DomainException('Ese corte ya corresponde a otros datos.');$app->db->commit();return (int)$old['id'];}
        $marks=implode(',',array_fill(0,count($ids),'?'));$rows=$app->all('SELECT * FROM fisitaap_driver_earnings180 WHERE tenant_id=? AND user_id=? AND job_id IN('.$marks.') ORDER BY job_id FOR UPDATE',array_merge([$tenant['id'],$driver],$ids));
        if(count($rows)!==count($ids))throw new DomainException('Una entrega no pertenece a este motorizado y negocio.');
        $amount=0;foreach($rows as $r){if($r['settlement_id'])throw new DomainException('Una entrega ya fue pagada. Recarga los saldos.');$amount+=pos_cents_148($r['payout']);}
        if($amount<=0)throw new DomainException('El corte no tiene un saldo positivo.');
        $branch=physical_branch_v14($app,$tenant);if(!$branch)throw new DomainException('Selecciona una sucursal.');
        $movement=qa180_money($app,$user,$branch,$account,-$amount,'driver:'.$key,'driver_settlement','Corte de pago a motorizado',$reference,'driver',$driver);
        $app->exec('INSERT INTO fisitaap_driver_settlements180(tenant_id,user_id,paid_by,operation_key,payload_hash,amount,account_id,movement_id,reference) VALUES(?,?,?,?,?,?,?,?,?)',[$tenant['id'],$driver,$user['id'],$key,$hash,$amount/100,$account?:null,$movement,$reference]);$id=(int)$app->db->lastInsertId();
        $app->exec('UPDATE fisitaap_driver_earnings180 SET settlement_id=? WHERE tenant_id=? AND user_id=? AND job_id IN('.$marks.')',array_merge([$id,$tenant['id'],$driver],$ids));
        qa180_audit($app,$user,'driver_settlement','driver_settlement',$id,'Pagó '.money($amount/100).' por '.count($ids).' entregas; '.$reference);$app->db->commit();return $id;
    }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}
}
function op180_affiliate_finance(App $app,array $user,array $tenant):void {
    if(is_post()&&in_array($_POST['action']??'',['payout_terms','settlement'],true)){
        verify_csrf();try{
            qa180_permission($app,$user,'express','configure');
            if($_POST['action']==='settlement'){op180_settlement($app,$user,$tenant,$_POST);flash('success','Corte pagado y descontado de la cuenta elegida.');}
            else{
                $mode=(string)($_POST['payout_mode']??'');$percent=r2_number($_POST['percent']??100,0,100);if(!in_array($mode,['all','percent'],true))throw new DomainException('Selecciona el pago completo o un porcentaje.');
                $app->db->beginTransaction();$a=$app->one('SELECT id FROM driver_affiliations WHERE id=? AND tenant_id=? FOR UPDATE',[(int)($_POST['affiliation_id']??0),$tenant['id']]);if(!$a)throw new DomainException('Afiliado no disponible.');
                $app->exec('UPDATE driver_affiliations SET payout_mode180=?,payout_percent180=? WHERE id=?',[$mode,$mode==='all'?100:$percent,$a['id']]);qa180_audit($app,$user,'driver_payout_terms','affiliation',(int)$a['id'],'Configuró el pago al motorizado: '.($mode==='all'?'100':$percent).'% del envío; aplica a nuevas asignaciones.');$app->db->commit();flash('success','Condiciones guardadas para próximas asignaciones.');
            }
        }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();flash('error',$e instanceof PDOException?'No se registró el pago. Revisa el corte antes de repetir.':$e->getMessage());}redirect(url('admin/afiliados'));
    }
    $may=true;try{qa180_permission($app,$user,'express','configure');}catch(DomainException){$may=false;}
    echo '<section class="card admin-card"><h2>Pagos y cortes de motorizados</h2><p>El envío cobrado al cliente forma parte de su venta y entra a caja según el medio con que se cobre. El saldo al motorizado se genera al entregar con PIN. Un corte registra su salida desde la cuenta elegida; no mueve dinero en el banco.</p></section><div data-live-region="affiliate-balances">';
    foreach($app->all('SELECT a.*,u.name FROM driver_affiliations a JOIN users u ON u.id=a.user_id WHERE a.tenant_id=? ORDER BY u.name',[$tenant['id']]) as $a){
        $pending=$app->all('SELECT e.*,o.order_number FROM fisitaap_driver_earnings180 e JOIN delivery_jobs j ON j.id=e.job_id JOIN orders o ON o.id=j.order_id WHERE e.tenant_id=? AND e.user_id=? AND e.settlement_id IS NULL ORDER BY e.job_id LIMIT 200',[$tenant['id'],$a['user_id']]);$total=array_sum(array_map(fn($e)=>pos_cents_148($e['payout']),$pending));
        echo '<section class="card admin-card"><h3>'.e($a['name']).'</h3><p>Saldo por pagar: <strong>'.money($total/100).'</strong></p>';
        if($may){echo '<details><summary>Configurar pago de próximos Express</summary>'.r2_form('payout_terms').pos_hidden_148('affiliation_id',$a['id']).'<label>Forma de cálculo<select class="input" name="payout_mode"><option value="all" '.($a['payout_mode180']==='all'?'selected':'').'>Todo el costo del envío</option><option value="percent" '.($a['payout_mode180']==='percent'?'selected':'').'>Un porcentaje del envío</option></select></label>'.field('Porcentaje para el motorizado','percent',$a['payout_percent180'],true,'number','.01').'<button class="btn btn-light">Guardar condiciones</button></form></details>';
            if($pending){echo '<details><summary>Preparar corte de pago</summary>'.r2_form('settlement').pos_hidden_148('driver_id',$a['user_id']).pos_hidden_148('operation_key',bin2hex(random_bytes(16)));foreach($pending as $e)echo '<label class="op180-check"><input type="checkbox" name="jobs[]" value="'.(int)$e['job_id'].'" checked> #'.e($e['order_number']).' · '.money((float)$e['payout']).' · '.e($e['earned_at']).'</label>';echo qa180_account_select($app,$tenant).field('Referencia / comprobante de pago','reference','',true).'<button class="btn">Registrar pago de las entregas seleccionadas</button></form></details>';}
        }echo '</section>';
    }
    echo '<section class="card admin-card"><h3>Cortes registrados</h3><div class="table-wrap"><table class="table"><tr><th>Fecha</th><th>Motorizado</th><th>Monto</th><th>Origen</th><th>Referencia</th></tr>';foreach($app->all('SELECT s.*,u.name,a.name account_name FROM fisitaap_driver_settlements180 s JOIN users u ON u.id=s.user_id LEFT JOIN fisitaap_money_accounts180 a ON a.id=s.account_id WHERE s.tenant_id=? ORDER BY s.id DESC LIMIT 100',[$tenant['id']]) as $s)echo '<tr><td>'.e($s['created_at']).'</td><td>'.e($s['name']).'</td><td>'.money((float)$s['amount']).'</td><td>'.e($s['account_name']?:'Caja del turno').'</td><td>'.e($s['reference']).'</td></tr>';echo '</table></div></section></div>';
}
function op180_driver_history(App $app,array $user):void {
    echo '<section class="card admin-card" data-live-region="driver-history"><h2>Mis entregas y pagos</h2><p>Las entregas confirmadas generan tu saldo. Aquí se conservan los cortes que registra cada negocio.</p><div class="table-wrap"><table class="table"><tr><th>Fecha</th><th>Negocio / pedido</th><th>Ganancia</th><th>Pago</th></tr>';
    foreach($app->all('SELECT j.id,COALESCE(e.earned_at,j.delivered_at,j.updated_at,j.created_at) earned_at,COALESCE(e.payout,j.payout) payout,e.job_id,e.settlement_id,j.status,t.name,o.order_number,s.created_at paid_at,s.reference FROM delivery_jobs j JOIN tenants t ON t.id=j.tenant_id JOIN orders o ON o.id=j.order_id LEFT JOIN fisitaap_driver_earnings180 e ON e.job_id=j.id LEFT JOIN fisitaap_driver_settlements180 s ON s.id=e.settlement_id AND s.tenant_id=e.tenant_id WHERE j.assigned_driver_id=? AND j.status IN("delivered","cancelled","expired") ORDER BY earned_at DESC,j.id DESC LIMIT 200',[$user['id']]) as $r)echo '<tr><td>'.e($r['earned_at']).'</td><td>'.e($r['name'].' · #'.$r['order_number']).'</td><td>'.money((float)$r['payout']).'</td><td>'.($r['paid_at']?'Pagado '.e($r['paid_at']).'<small>'.e($r['reference']).'</small>':($r['status']!=='delivered'?'Entrega cancelada / vencida':(!$r['job_id']?'Anterior al control de pagos; revisar':'Pendiente de pago'))).'</td></tr>';
    echo '</table></div></section>';
}
function op180_order_detail(App $app,bool $public=false):void {
    $id=(int)($_GET['id']??0);$order=$app->one('SELECT o.*,t.name tenant_name FROM orders o JOIN tenants t ON t.id=o.tenant_id WHERE o.id=?',[$id]);
    if($public){$token=(string)($_GET['token']??'');if(!$order||!hash_equals(hash_hmac('sha256','order:'.$id,$app->config['app_key']),$token)){http_response_code(404);simple_error('Pedido no disponible','Revisa tu enlace privado de seguimiento.');return;}header('Referrer-Policy: no-referrer');}
    else{[$user,$tenant]=qa180_business($app,'pedidos');if(!$order||(int)$order['tenant_id']!==(int)$tenant['id'])throw new DomainException('Pedido no disponible.');$branch=physical_branch_v14($app,$tenant);if((int)$order['branch_id']!==(int)$branch['id'])throw new DomainException('Selecciona la sucursal de este pedido.');}
    $job=$app->one('SELECT j.*,u.name driver FROM delivery_jobs j LEFT JOIN users u ON u.id=j.assigned_driver_id WHERE j.order_id=? AND j.tenant_id=?',[$id,$order['tenant_id']]);
    if(!$public&&is_post()){
        verify_csrf();try{qa180_permission($app,$user,'express','write');$note=mb_substr(trim((string)($_POST['resolution']??'')),0,500);if(mb_strlen($note)<8)throw new DomainException('Explica la corrección o solución (al menos 8 caracteres).');
            $app->db->beginTransaction();$locked=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$tenant['id']]);$j=$app->one('SELECT * FROM delivery_jobs WHERE order_id=? AND tenant_id=? FOR UPDATE',[$id,$tenant['id']]);
            if(($_POST['action']??'')==='resolve_issue'){
                if(!$j||$j['status']!=='issue')throw new DomainException('La incidencia ya cambió.');$next=$j['issue_previous_status']?:'assigned';if(!in_array($next,['assigned','arrived','picked_up','in_transit'],true))$next='assigned';
                $app->exec('UPDATE delivery_jobs SET status=?,issue_previous_status=NULL,updated_at=NOW() WHERE id=?',[$next,$j['id']]);op180_event($app,$locked,$next,'Incidencia resuelta por tienda: '.$note,(int)$user['id']);
            }elseif(($_POST['action']??'')==='correct_address'){
                if(in_array($locked['status'],['cancelled','delivered'],true))throw new DomainException('El pedido ya terminó.');$address=mb_substr(trim((string)($_POST['address']??'')),0,2000);$maps=op180_maps((string)($_POST['maps_url']??''));if($address==='')throw new DomainException('Escribe la dirección corregida.');
                $app->exec('UPDATE orders SET address=?,maps_url180=?,revision180=revision180+1 WHERE id=?',[$address,$maps?:null,$id]);$app->exec('UPDATE pos_tickets SET delivery_address=?,maps_url180=?,revision=revision+1 WHERE source_order_id=? AND tenant_id=?',[$address,$maps?:null,$id,$tenant['id']]);op180_event($app,$locked,'address_corrected','Dirección corregida por tienda: '.$note,(int)$user['id']);
            }else throw new DomainException('Acción no disponible.');qa180_audit($app,$user,'delivery_resolution','order',$id,$note);$app->db->commit();flash('success','Corrección registrada y disponible para el motorizado.');
        }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();flash('error',$e instanceof PDOException?'No se guardó la corrección.':$e->getMessage());}redirect(url('admin/pedido-detalle180?id='.$id));
    }
    if($public){layout_start('Seguimiento de tu pedido');echo '<main class="container section">';}else admin_shell_start('Pedido #'.$order['order_number'],$user,$tenant,'pedidos');
    echo '<section class="card admin-card" data-live-region="order-detail"><h2>'.e($order['tenant_name']).' · Pedido #'.e($order['order_number']).'</h2><p>Estado actual: <strong>'.e($job?(express_job_states_v12()[$job['status']]??$job['status']):(qa180_order_states()[$order['status']]??$order['status'])).'</strong></p><p>'.e($order['customer_name'].' · '.$order['customer_phone']).'</p><p>'.e($order['address']).'</p>';
    if($order['maps_url180'])echo '<a class="btn btn-light" href="'.e($order['maps_url180']).'" target="_blank" rel="noopener noreferrer">Abrir ubicación en Google Maps</a>';
    $pin=op180_pin($app,$order);if($pin&&$order['delivery_type']!=='pickup')echo '<div class="op180-pin"><small>PIN PARA RECIBIR EL PEDIDO</small><strong>'.e($pin).'</strong><p>Guárdalo y compártelo con el motorizado únicamente cuando recibas tu pedido.</p></div>';
    if(!$public&&preg_match('/sinpe/i',$order['payment_method']))echo '<p><a class="btn btn-light" target="_blank" href="'.e(url('api/order-proof180?order='.$id)).'">Ver comprobante SINPE</a></p>';
    if($job)echo '<p>Motorizado: '.e($job['driver']?:'Por asignar').'</p>';
    echo '<h3>Bitácora de tiempos</h3><ol class="op180-timeline">';$events=$app->all('SELECT e.*,u.name actor FROM fisitaap_order_events180 e LEFT JOIN users u ON u.id=e.user_id WHERE e.tenant_id=? AND e.order_id=? ORDER BY e.id',[$order['tenant_id'],$id]);
    if(!$events)echo '<li>Este pedido es anterior a la bitácora. Creado: '.e($order['created_at']).'. No se inventan tiempos anteriores.</li>';
    foreach($events as $i=>$event){$elapsed=isset($events[$i+1])?max(0,strtotime($events[$i+1]['created_at'])-strtotime($event['created_at'])):null;echo '<li><time>'.e($event['created_at']).'</time><strong>'.e($event['message']).'</strong>'.(!$public&&$event['actor']?'<small>'.e($event['actor']).'</small>':'').($elapsed!==null?'<small>'.(int)floor($elapsed/60).' min '.($elapsed%60).' s hasta el siguiente paso</small>':'').'</li>';}
    echo '</ol></section>';
    if(!$public&&!in_array($order['status'],['delivered','cancelled'],true)){try{qa180_permission($app,$user,'express','write');echo '<section class="card admin-card"><h3>Corregir entrega / incidencia</h3>';if($job&&$job['status']==='issue')echo r2_form('resolve_issue').field('Solución aplicada','resolution','',true).'<button class="btn">Resolver y continuar con el mismo motorizado</button></form>';echo '<details><summary>Corregir dirección de este pedido</summary>'.r2_form('correct_address').field('Dirección','address',$order['address'],true).field('Google Maps','maps_url',$order['maps_url180']??'').field('Motivo de la corrección','resolution','',true).'<button class="btn">Guardar corrección</button></form></details></section>';}catch(DomainException){}}
    if($public){echo '</main>';layout_end();}else admin_shell_end();
}
