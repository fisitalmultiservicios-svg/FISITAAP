<?php
declare(strict_types=1);

function qa180_account_select(App $app,array $tenant,int $selected=0):string {
    $html='<label>Origen / destino del dinero<select class="input" name="account_id"><option value="0">Efectivo de mi caja (Windows / Android)</option>';
    foreach($app->all('SELECT id,name FROM fisitaap_money_accounts180 WHERE tenant_id=? AND is_active=1 ORDER BY name',[$tenant['id']]) as $row)$html.='<option value="'.(int)$row['id'].'" '.($selected===(int)$row['id']?'selected':'').'>'.e($row['name']).'</option>';
    return $html.'</select></label><p class="muted">Tarjeta, SINPE o transferencia requieren una cuenta de dinero. El registro concilia tu contabilidad; no transfiere dinero en el banco.</p>';
}
function qa180_debt_payment(App $app,array $user,array $tenant,string $kind,array $input):int {
    qa180_permission($app,$user,$kind,'write');
    $ar=$kind==='cxc';$table=$ar?'accounts_receivable':'accounts_payable';$payments=$ar?'ar_payments':'ap_payments';$fk=$ar?'receivable_id':'payable_id';
    $id=(int)($input['id']??0);$key=(string)($input['payment_key']??'');$method=(string)($input['payment_method']??'');
    if(!preg_match('/^[a-f0-9]{32}$/D',$key)||!in_array($method,['Efectivo','Tarjeta','SINPE Móvil','Transferencia'],true))throw new DomainException('Revisa la forma de pago y recarga el estado de cuenta.');
    $amount=pos_cents_148(r2_number($input['amount']??0,.01));$account=(int)($input['account_id']??0);
    if(($method==='Efectivo')!==($account===0))throw new DomainException('Efectivo usa la caja; selecciona una cuenta de dinero para los otros medios.');
    $reference=mb_substr(trim((string)($input['reference']??'')),0,150);$notes=mb_substr(trim((string)($input['notes']??'')),0,500);
    $hash=hash('sha256',json_encode([$id,$amount,$method,$account,$reference,$notes],JSON_THROW_ON_ERROR));
    $app->db->beginTransaction();
    try{
        $a=$app->one('SELECT * FROM '.$table.' WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$tenant['id']]);
        if(!$a)throw new DomainException('La cuenta no pertenece a este negocio.');
        $branch=$app->one('SELECT * FROM branches WHERE id=? AND tenant_id=? AND is_active=1',[$a['branch_id'],$tenant['id']]);
        if(!$branch)throw new DomainException('La sucursal de esta cuenta no está disponible.');
        $prior=$app->one('SELECT id,payload_hash FROM '.$payments.' WHERE '.$fk.'=? AND payment_key=?',[$id,$key]);
        if($prior){if(!$prior['payload_hash']||!hash_equals($prior['payload_hash'],$hash))throw new DomainException('Esa referencia corresponde a otro abono.');$app->db->commit();return (int)$prior['id'];}
        $balance=pos_cents_148($a['balance']);if($amount>$balance)throw new DomainException('El abono supera el saldo pendiente.');
        $movement=qa180_money($app,$user,$branch,$account,$ar?$amount:-$amount,$kind.':'.$key,$kind.'_payment','Abono '.$a['document_number'],$reference,$kind,$id);
        $entry=$app->one('SELECT shift_id FROM fisitaap_money_entries180 WHERE id=? AND tenant_id=?',[$movement,$tenant['id']]);
        $app->exec('INSERT INTO '.$payments.'('.$fk.',amount,payment_method,reference,notes,user_id,payment_key,account_id,shift_id,payload_hash) VALUES(?,?,?,?,?,?,?,?,?,?)',[$id,$amount/100,$method,$reference,$notes,$user['id'],$key,$account?:null,$entry['shift_id'],$hash]);$paymentId=(int)$app->db->lastInsertId();
        $balance-=$amount;$app->exec('UPDATE '.$table.' SET balance=?,status=? WHERE id=? AND tenant_id=?',[$balance/100,$balance===0?'paid':'partial',$id,$tenant['id']]);
        if($ar&&$a['customer_id']){$app->one('SELECT user_id FROM customer_credit_profiles WHERE tenant_id=? AND user_id=? FOR UPDATE',[$tenant['id'],$a['customer_id']]);$app->exec('UPDATE customer_credit_profiles SET current_balance=GREATEST(0,current_balance-?) WHERE tenant_id=? AND user_id=?',[$amount/100,$tenant['id'],$a['customer_id']]);}
        elseif(!$ar&&$a['purchase_id'])$app->exec('UPDATE purchases SET balance=?,payment_status=? WHERE id=? AND tenant_id=?',[$balance/100,$balance===0?'paid':'partial',$a['purchase_id'],$tenant['id']]);
        qa180_audit($app,$user,$kind.'_payment',$kind,$id,($ar?'Recibió':'Pagó').' '.money($amount/100).' en '.$a['document_number'].' por '.$method.'; saldo '.money($balance/100));
        $app->db->commit();return $paymentId;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_accounts(App $app,array $user,array $tenant,string $kind):void {
    $ar=$kind==='cxc';$table=$ar?'accounts_receivable':'accounts_payable';$payments=$ar?'ar_payments':'ap_payments';$fk=$ar?'receivable_id':'payable_id';
    if(is_post()){verify_csrf();try{qa180_debt_payment($app,$user,$tenant,$kind,$_POST);flash('success','Abono registrado y conciliado.');}catch(Throwable $error){flash('error',$error instanceof PDOException?'No se registró el abono. Revisa el estado de cuenta antes de repetirlo.':$error->getMessage());}redirect(url('admin/'.$kind.'?detail='.(int)($_POST['id']??0)));}
    $q=mb_substr(trim((string)($_GET['q']??'')),0,100);$filter=(string)($_GET['status']??'');$branch=(int)($_GET['branch_id']??0);
    $where='a.tenant_id=?';$params=[$tenant['id']];
    $name=$ar?'COALESCE(p.name,a.customer_name)':'p.name';$join=$ar?'LEFT JOIN users p ON p.id=a.customer_id':'JOIN suppliers p ON p.id=a.supplier_id AND p.tenant_id=a.tenant_id';
    if($q!==''){$where.=' AND (a.document_number LIKE ? OR '.$name.' LIKE ?)';array_push($params,'%'.$q.'%','%'.$q.'%');}
    if($branch){$where.=' AND a.branch_id=?';$params[]=$branch;}
    if($filter==='overdue')$where.=' AND a.balance>0 AND a.due_date<CURDATE()';
    elseif($filter==='paid')$where.=' AND a.balance<=0';
    elseif($filter==='partial')$where.=' AND a.balance>0 AND a.balance<a.amount';
    elseif($filter==='pending')$where.=' AND a.balance>0 AND a.balance>=a.amount';
    $rows=$app->all('SELECT a.*,'.$name.' party FROM '.$table.' a '.$join.' WHERE '.$where.' ORDER BY a.due_date,a.id DESC LIMIT 400',$params);
    $total=$app->one('SELECT COALESCE(SUM(a.balance),0) amount FROM '.$table.' a '.$join.' WHERE '.$where,$params)['amount'];
    admin_shell_start($ar?'Cuentas por cobrar':'Cuentas por pagar',$user,$tenant,$kind);
    echo '<section class="card admin-card"><form method="get" class="qa180-filter">'.field('Buscar cliente, proveedor o documento','q',$q).'<label>Estado<select class="input" name="status"><option value="">Todos</option>';
    foreach(['pending'=>'Pendientes','partial'=>'Parciales','overdue'=>'Morosos / vencidos','paid'=>'Pagados'] as $key=>$label)echo '<option value="'.$key.'" '.($filter===$key?'selected':'').'>'.$label.'</option>';
    echo '</select></label><label>Sucursal'.r2_select('branch_id',$app->all('SELECT id,name FROM branches WHERE tenant_id=? ORDER BY name',[$tenant['id']]),$branch,'name',true).'</label><button class="btn">Filtrar</button></form><h2>Saldo pendiente '.money((float)$total).'</h2><div class="table-wrap"><table class="table"><tr><th>Documento</th><th>'.($ar?'Cliente':'Proveedor').'</th><th>Vence</th><th>Total</th><th>Saldo</th><th>Estado</th><th></th></tr>';
    foreach($rows as $row){$status=(float)$row['balance']<=0?'Pagado':($row['due_date']&&$row['due_date']<date('Y-m-d')?'Vencido':((float)$row['balance']<(float)$row['amount']?'Parcial':'Pendiente'));echo '<tr><td>'.e($row['document_number']).'</td><td>'.e($row['party']).'</td><td>'.e($row['due_date']).'</td><td>'.money((float)$row['amount']).'</td><td>'.money((float)$row['balance']).'</td><td>'.e($status).'</td><td><a class="btn btn-light" href="'.e(url('admin/'.$kind.'?detail='.(int)$row['id'])).'">Ver estado / abonar</a></td></tr>';}
    echo '</table></div><p>Se muestran hasta 400 documentos. Usa los filtros para reducir la lista.</p></section>';
    $id=(int)($_GET['detail']??0);$a=$app->one('SELECT * FROM '.$table.' WHERE id=? AND tenant_id=?',[$id,$tenant['id']]);
    if($a){echo '<section class="card admin-card"><h2>'.e($a['document_number']).' · saldo '.money((float)$a['balance']).'</h2><div class="table-wrap"><table class="table"><tr><th>Fecha</th><th>Medio</th><th>Cuenta</th><th>Referencia</th><th>Abono</th></tr>';
        foreach($app->all('SELECT p.*,m.name account_name FROM '.$payments.' p LEFT JOIN fisitaap_money_accounts180 m ON m.id=p.account_id AND m.tenant_id=? WHERE '.$fk.'=? ORDER BY p.id',[$tenant['id'],$id]) as $p)echo '<tr><td>'.e($p['created_at']).'</td><td>'.e($p['payment_method']).'</td><td>'.e($p['account_name']??'Caja').'</td><td>'.e($p['reference']).'</td><td>'.money((float)$p['amount']).'</td></tr>';
        echo '</table></div>';if((float)$a['balance']>0)echo r2_form('pay',$id).pos_hidden_148('payment_key',bin2hex(random_bytes(16))).field('Monto del abono','amount','',true,'number','.01').'<label>Medio<select class="input" name="payment_method"><option>Efectivo</option><option>Tarjeta</option><option>SINPE Móvil</option><option>Transferencia</option></select></label>'.qa180_account_select($app,$tenant).field('Referencia','reference','').field('Notas','notes','').'<button class="btn">Aplicar abono</button></form>';
        echo '</section>';}
    admin_shell_end();
}
function qa180_money_accounts(App $app,array $user,array $tenant):void {
    if(is_post()){verify_csrf();try{
        qa180_permission($app,$user,'configuracion','configure');$name=mb_substr(trim((string)($_POST['name']??'')),0,100);$id=(int)($_POST['id']??0);$kind=(string)($_POST['kind']??'bank');if(!in_array($kind,['principal','petty','bank','cash','other'],true))throw new DomainException('Elige el tipo de cuenta.');if($name==='')throw new DomainException('Escribe el nombre de la cuenta.');
        $app->db->beginTransaction();
        if($id){$old=$app->one('SELECT * FROM fisitaap_money_accounts180 WHERE id=? AND tenant_id=? FOR UPDATE',[$id,$tenant['id']]);if(!$old)throw new DomainException('Cuenta no disponible.');$app->exec('UPDATE fisitaap_money_accounts180 SET name=?,kind=?,is_active=? WHERE id=? AND tenant_id=?',[$name,$kind,isset($_POST['is_active'])?1:0,$id,$tenant['id']]);}
        else{$opening=r2_number($_POST['opening']??0);$app->exec('INSERT INTO fisitaap_money_accounts180(tenant_id,name,kind,balance) VALUES(?,?,?,0)',[$tenant['id'],$name,$kind]);$id=(int)$app->db->lastInsertId();if($opening>0)qa180_money($app,$user,physical_branch_v14($app,$tenant),$id,pos_cents_148($opening),'opening:'.bin2hex(random_bytes(16)),'account_opening','Saldo inicial de la cuenta');}
        qa180_audit($app,$user,'money_account','money_account',$id,'Configuró la cuenta de dinero '.$name);$app->db->commit();flash('success','Cuenta guardada.');
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();flash('error',$error instanceof PDOException?'No se guardó. Comprueba que no exista una cuenta con ese nombre.':$error->getMessage());}redirect(url('admin/cuentas-dinero'));}
    admin_shell_start('Cuentas de dinero',$user,$tenant,'configuracion');echo '<section class="card admin-card"><h2>Nueva cuenta de dinero</h2><p>Estas cuentas llevan el saldo contable de pagos y abonos externos. No son una conexión con tu banco. El saldo puede ser negativo si registras un pago sin haber registrado su ingreso.</p>'.r2_form('save').field('Nombre','name','',true).op180_account_kind().field('Saldo inicial','opening',0,true,'number','.01').'<button class="btn">Crear cuenta</button></form></section>';
    foreach($app->all('SELECT * FROM fisitaap_money_accounts180 WHERE tenant_id=? ORDER BY name',[$tenant['id']]) as $account)echo '<section class="card admin-card">'.r2_form('save',(int)$account['id']).field('Nombre','name',$account['name'],true).op180_account_kind($account['kind']).'<p>Saldo contable '.money((float)$account['balance']).'</p><label><input type="checkbox" name="is_active" '.($account['is_active']?'checked':'').'> Activa</label><button class="btn btn-light">Guardar</button></form></section>';
    echo '<section class="card admin-card"><h2>Últimos movimientos</h2><div class="table-wrap"><table class="table"><tr><th>Fecha</th><th>Cuenta</th><th>Motivo</th><th>Referencia</th><th>Monto</th></tr>';foreach($app->all('SELECT e.*,a.name FROM fisitaap_money_entries180 e JOIN fisitaap_money_accounts180 a ON a.id=e.account_id AND a.tenant_id=e.tenant_id WHERE e.tenant_id=? ORDER BY e.id DESC LIMIT 100',[$tenant['id']]) as $entry)echo '<tr><td>'.e($entry['created_at']).'</td><td>'.e($entry['name']).'</td><td>'.e($entry['reason']).'</td><td>'.e($entry['reference']).'</td><td>'.money((float)$entry['amount']).'</td></tr>';echo '</table></div></section>';admin_shell_end();
}
