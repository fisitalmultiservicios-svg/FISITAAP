<?php
declare(strict_types=1);

function r18_owner_alerts(App $app,array $tenant,array $owner,bool $unread=true):array {
    if($owner['role']!=='tenant_admin'||(int)$owner['tenant_id']!==(int)$tenant['id'])return [];
    $alerts=[];$today=new DateTimeImmutable('today',new DateTimeZone('America/Costa_Rica'));
    $add=static function(string $key,string $type,string $title,string $body,string $route)use(&$alerts,$tenant):void{$id=hash('sha256',$tenant['id'].'|'.$key);$alerts[$id]=['id'=>$id,'type'=>$type,'title'=>$title,'body'=>$body,'url'=>url($route)];};
    if(module_enabled_v12($app,$tenant,'personnel')){
        foreach($app->all('SELECT e.*,u.name FROM fisitaap_employees_r17 e JOIN users u ON u.id=e.user_id WHERE e.tenant_id=? AND e.is_active=1 AND u.is_active=1 AND e.start_date<=CURDATE() AND (e.end_date IS NULL OR e.end_date>=CURDATE()) ORDER BY e.id LIMIT 500',[$tenant['id']]) as $e){
            if($e['birth_date']){$monthDay=substr($e['birth_date'],5);$candidate=$today->format('Y').'-'.$monthDay;if($monthDay==='02-29'&&!checkdate(2,29,(int)$today->format('Y')))$candidate=$today->format('Y').'-02-28';$birthday=hr17_date($candidate);if($birthday<$today){$year=(int)$today->format('Y')+1;$birthday=hr17_date($year.'-'.($monthDay==='02-29'&&!checkdate(2,29,$year)?'02-28':$monthDay));}$days=(int)$today->diff($birthday)->days;
                if($days<=7)$add('birthday-'.$e['id'].'-'.$birthday->format('Y'),'birthday',$days===0?'Hoy cumple años '.$e['name']:'Se acerca el cumpleaños de '.$e['name'],$days===0?'Puedes tener un detalle con esta persona de tu equipo.':'Cumple el '.$birthday->format('d/m').', dentro de '.$days.' días.','admin/personal?employee='.$e['id']);
            }
            if($e['end_date']){$end=hr17_date($e['end_date']);if($end<=$today->modify('+30 days'))$add('contract-'.$e['id'].'-'.$e['end_date'],'contract','Revisa el contrato de '.$e['name'],'La fecha de salida registrada es '.$end->format('d/m/Y').'. Revisa el expediente y las gestiones pendientes.','admin/personal?employee='.$e['id']);}
            try{$balance=hr17_leave_balance($app,$e,$today->format('Y-m-d'));if($balance['available']>=2*(int)$e['weekly_days']&&!$app->one('SELECT id FROM fisitaap_leave_r17 WHERE tenant_id=? AND employee_id=? AND status="assigned" AND end_date>=CURDATE() LIMIT 1',[$tenant['id'],$e['id']]))$add('leave-balance-'.$e['id'].'-'.$today->format('Y-m'),'vacation','Revisa las vacaciones de '.$e['name'],'Tiene un saldo estimado de '.$balance['available'].' días laborables y no hay vacaciones futuras asignadas. Revisa el saldo antes de programarlas.','admin/personal?employee='.$e['id'].'&tab=vacaciones');}catch(DomainException){}
        }
        foreach($app->all('SELECT v.id,v.start_date,v.end_date,v.employee_id,u.name FROM fisitaap_leave_r17 v JOIN fisitaap_employees_r17 e ON e.id=v.employee_id AND e.tenant_id=v.tenant_id JOIN users u ON u.id=e.user_id WHERE v.tenant_id=? AND v.status="assigned" AND v.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 14 DAY) AND e.is_active=1 ORDER BY v.start_date LIMIT 30',[$tenant['id']]) as $v)$add('vacation-'.$v['id'],'vacation','Se acercan las vacaciones de '.$v['name'],'Del '.hr17_date($v['start_date'])->format('d/m/Y').' al '.hr17_date($v['end_date'])->format('d/m/Y').'. Organiza la cobertura del equipo.','admin/personal?employee='.$v['employee_id'].'&tab=vacaciones');
    }
    if(module_enabled_v12($app,$tenant,'inventory')){$n=(int)$app->one('SELECT COUNT(*) n FROM product_inventory i JOIN products p ON p.id=i.product_id AND p.tenant_id=i.tenant_id WHERE i.tenant_id=? AND p.track_inventory=1 AND p.status<>"hidden" AND i.quantity<=GREATEST(i.reorder_point,0)',[$tenant['id']])['n'];if($n)$add('stock-'.$today->format('Y-m-d'),'inventory','Hay '.$n.' existencias por revisar','Algunos productos alcanzaron el mínimo o se agotaron. Consulta las sucursales antes de reponer.','admin/inventario');}
    if(module_enabled_v12($app,$tenant,'receivables')){$n=(int)$app->one('SELECT COUNT(*) n FROM accounts_receivable WHERE tenant_id=? AND balance>0 AND due_date<CURDATE()',[$tenant['id']])['n'];if($n)$add('debt-'.$today->format('Y-m-d'),'receivables','Tienes '.$n.' cuentas por cobrar vencidas','Revisa su estado y coordina los cobros pendientes.','admin/cxc?status=overdue');}
    if($unread){$read=array_fill_keys(array_column($app->all('SELECT alert_key FROM fisitaap_owner_alert_reads_r18 WHERE tenant_id=? AND user_id=?',[$tenant['id'],$owner['id']]),'alert_key'),true);$alerts=array_filter($alerts,static fn($a)=>!isset($read[$a['id']]));}
    return array_slice(array_values($alerts),0,20);
}
function r18_alert_api(App $app):never {
    $u=require_login(['tenant_admin']);$tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$u['tenant_id']]);if(!$tenant)json_response(['ok'=>false,'error'=>'Negocio no disponible.'],403);
    $alerts=r18_owner_alerts($app,$tenant,$u);
    if(is_post()){
        verify_csrf();$key=(string)($_POST['id']??'');if(!in_array($key,array_column($alerts,'id'),true))json_response(['ok'=>false,'error'=>'Este aviso ya no está disponible.'],422);
        $app->exec('INSERT IGNORE INTO fisitaap_owner_alert_reads_r18(tenant_id,user_id,alert_key) VALUES(?,?,?)',[$tenant['id'],$u['id'],$key]);$alerts=r18_owner_alerts($app,$tenant,$u);
    }
    header('Cache-Control: private, no-store');json_response(['ok'=>true,'alerts'=>$alerts]);
}
