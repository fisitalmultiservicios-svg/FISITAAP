<?php
declare(strict_types=1);

function r3_gift_issue(App $app,array $u,array $t): never
{
    verify_csrf();
    try {
        $cid=(int)($_POST['customer_id']??0);$amount=pos_cents_148(r2_number($_POST['gift_amount']??0,.01));$key=(string)($_POST['issue_key']??'');
        if(!preg_match('/^[a-f0-9]{32}$/',$key))throw new DomainException('Recarga el formulario para crear la tarjeta.');
        if(!$app->one('SELECT user_id FROM tenant_customers WHERE tenant_id=? AND user_id=?',[$t['id'],$cid]))throw new DomainException('Selecciona un cliente de este negocio.');
        $date=(string)($_POST['gift_expires']??'');if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||date('Y-m-d',strtotime($date))!==$date||$date<date('Y-m-d'))throw new DomainException('Revisa la fecha de vencimiento.');
        $app->db->beginTransaction();$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$t['id']]);
        if(!$app->one('SELECT id FROM fisitaap_r3_gift_cards WHERE tenant_id=? AND issue_key=?',[$t['id'],$key])) {
            $code='REG-'.strtoupper(bin2hex(random_bytes(8)));
            $app->exec('INSERT INTO fisitaap_r3_gift_cards(tenant_id,customer_id,code,issue_key,initial_amount,balance,expires_at,created_by) VALUES(?,?,?,?,?,?,?,?)',[$t['id'],$cid,$code,$key,$amount/100,$amount/100,$date.' 23:59:59',$u['id']]);$id=(int)$app->db->lastInsertId();
            $app->exec('INSERT INTO fisitaap_r3_gift_entries(tenant_id,card_id,operation_key,kind,amount,created_by) VALUES(?,?,?,"issue",?,?)',[$t['id'],$id,'issue-'.$key,$amount/100,$u['id']]);audit($app,(int)$t['id'],(int)$u['id'],'r3_gift_issue','gift_card',$id);
        }
        $app->db->commit();flash('success','Tarjeta de regalo creada. Conserva el saldo restante después de cada compra.');
    }catch(Throwable $ex){if($app->db->inTransaction())$app->db->rollBack();flash('error',$ex->getMessage());}
    redirect(url('admin/fidelizacion'));
}
function r3_gift_recharge(App $app,array $u,array $t): never
{
    verify_csrf();try {
        $id=(int)($_POST['card_id']??0);$amount=pos_cents_148(r2_number($_POST['gift_amount']??0,.01));$key=(string)($_POST['issue_key']??'');
        if(!preg_match('/^[a-f0-9]{32}$/',$key))throw new DomainException('Recarga antes de agregar saldo.');
        $app->db->beginTransaction();$card=$app->one('SELECT * FROM fisitaap_r3_gift_cards WHERE id=? AND tenant_id=? AND status="active" AND expires_at>=NOW() FOR UPDATE',[$id,$t['id']]);if(!$card)throw new DomainException('La tarjeta no está activa.');
        if(!$app->one('SELECT id FROM fisitaap_r3_gift_entries WHERE card_id=? AND operation_key=?',[$id,'recharge-'.$key])) {
            if(pos_cents_148($card['balance'])+$amount>99_999_999_900)throw new DomainException('El saldo supera el límite permitido.');
            $app->exec('UPDATE fisitaap_r3_gift_cards SET balance=balance+? WHERE id=?',[$amount/100,$id]);
            $app->exec('INSERT INTO fisitaap_r3_gift_entries(tenant_id,card_id,operation_key,kind,amount,created_by) VALUES(?,?,?,"recharge",?,?)',[$t['id'],$id,'recharge-'.$key,$amount/100,$u['id']]);audit($app,(int)$t['id'],(int)$u['id'],'r3_gift_recharge','gift_card',$id);
        }
        $app->db->commit();flash('success','Saldo agregado a la tarjeta de regalo.');
    }catch(Throwable $ex){if($app->db->inTransaction())$app->db->rollBack();flash('error',$ex->getMessage());}redirect(url('admin/fidelizacion'));
}
/** Must be called inside the sale/order transaction; the card remains locked until commit. */
function r3_gift_payment(App $app,int $tenant,int $customer,string $code,int $requested,int $maximum): array
{
    if(!$app->db->inTransaction())throw new LogicException('El canje requiere una transacción.');
    $code=strtoupper(trim($code));if(!preg_match('/^REG-[A-F0-9]{16}$/',$code))throw new DomainException('Código de tarjeta de regalo inválido.');
    $card=$app->one('SELECT * FROM fisitaap_r3_gift_cards WHERE tenant_id=? AND customer_id=? AND code=? FOR UPDATE',[$tenant,$customer,$code]);
    if(!$card||$card['status']!=='active'||strtotime($card['expires_at'])<time())throw new DomainException('La tarjeta no está disponible para este cliente y negocio.');
    $balance=pos_cents_148($card['balance']);$amount=$requested?:min($balance,$maximum);
    if($amount<=0||$amount>$balance||$amount>$maximum)throw new DomainException('El monto de regalo supera el saldo disponible o el cobro.');
    return ['card'=>$card,'amount'=>$amount];
}
function r3_gift_debit(App $app,array $gift,string $operation,?int $sale,?int $order,?int $actor): void
{
    $card=$gift['card'];$amount=$gift['amount'];
    if($app->one('SELECT id FROM fisitaap_r3_gift_entries WHERE card_id=? AND operation_key=?',[$card['id'],$operation]))return;
    $changed=$app->exec('UPDATE fisitaap_r3_gift_cards SET balance=balance-? WHERE id=? AND balance>=?',[$amount/100,$card['id'],$amount/100]);
    $app->exec('INSERT INTO fisitaap_r3_gift_entries(tenant_id,card_id,operation_key,kind,amount,sale_id,order_id,created_by) VALUES(?,?,?,"use",?,?,?,?)',[$card['tenant_id'],$card['id'],$operation,-$amount/100,$sale,$order,$actor]);
}
function r3_gift_form(App $app,array $t): void
{
    $customers=$app->all('SELECT u.id,u.name FROM tenant_customers c JOIN users u ON u.id=c.user_id WHERE c.tenant_id=? AND u.role="customer" AND u.is_active=1 ORDER BY u.name',[$t['id']]);
    echo '<section class="card admin-card"><h2>Tarjetas de regalo con saldo</h2><p>El cliente puede usar una parte de su tarjeta y conservar el resto. Se puede agregar saldo a las tarjetas activas.</p>'.r2_form('gift_card').pos_hidden_148('issue_key',bin2hex(random_bytes(16))).'<div class="split"><label>Cliente'.r2_select('customer_id',$customers,0,'name',true).'</label>'.field('Saldo inicial','gift_amount','',true,'number','.01').field('Vence','gift_expires',date('Y-m-d',strtotime('+1 year')),true,'date').'</div><button class="btn">Crear tarjeta</button></form><table class="table"><tr><th>Cliente / código</th><th>Saldo</th><th>Vence</th><th>Agregar saldo</th></tr>';
    foreach($app->all('SELECT g.*,u.name FROM fisitaap_r3_gift_cards g JOIN users u ON u.id=g.customer_id WHERE g.tenant_id=? ORDER BY g.id DESC LIMIT 200',[$t['id']]) as $card) {
        echo '<tr><td>'.e($card['name']).'<br><code>'.e($card['code']).'</code></td><td>'.money((float)$card['balance']).'</td><td>'.e($card['expires_at']).'</td><td>';
        if($card['status']==='active'&&strtotime($card['expires_at'])>=time())echo r2_form('gift_recharge').pos_hidden_148('card_id',$card['id']).pos_hidden_148('issue_key',bin2hex(random_bytes(16))).'<input class="input" name="gift_amount" type="number" min=".01" step=".01" required><button class="btn btn-light">Agregar saldo</button></form>';
        echo '</td></tr>';
    }echo '</table><details><summary>Historial de tarjetas</summary><table class="table"><tr><th>Fecha</th><th>Código</th><th>Movimiento</th><th>Monto</th></tr>';foreach($app->all('SELECT e.*,g.code FROM fisitaap_r3_gift_entries e JOIN fisitaap_r3_gift_cards g ON g.id=e.card_id WHERE e.tenant_id=? ORDER BY e.id DESC LIMIT 200',[$t['id']]) as $entry)echo '<tr><td>'.e($entry['created_at']).'</td><td>'.e($entry['code']).'</td><td>'.e(['issue'=>'Creación','recharge'=>'Saldo agregado','use'=>'Compra','refund'=>'Devolución'][$entry['kind']]??$entry['kind']).'</td><td>'.money((float)$entry['amount']).'</td></tr>';echo '</table></details></section>';
}
function r3_gift_customer(App $app,int $customer): string
{
    $html='<section class="loyalty-rewards"><h3>Mis tarjetas de regalo</h3><div class="grid grid-3">';
    foreach($app->all('SELECT g.*,t.name tenant_name FROM fisitaap_r3_gift_cards g JOIN tenants t ON t.id=g.tenant_id WHERE g.customer_id=? ORDER BY g.id DESC',[$customer]) as $card)$html.='<article class="loyalty-reward-ticket"><strong>'.money((float)$card['balance']).' disponibles</strong><small>'.e($card['tenant_name']).'</small><code>'.e($card['code']).'</code><small>Vence '.e(date('d/m/Y',strtotime($card['expires_at']))).'</small><p>Usa el código al comprar en esta tienda o preséntalo en caja.</p></article>';
    return $html.'</div></section>';
}
function r3_gift_refund_order(App $app,int $order): void
{
    $entries=$app->all('SELECT * FROM fisitaap_r3_gift_entries WHERE order_id=? AND kind="use"',[$order]);
    if(!$entries)return;$own=!$app->db->inTransaction();if($own)$app->db->beginTransaction();
    try{foreach($entries as $entry){$app->one('SELECT id FROM fisitaap_r3_gift_cards WHERE id=? FOR UPDATE',[$entry['card_id']]);$key='refund-order-'.$order;if($app->one('SELECT id FROM fisitaap_r3_gift_entries WHERE card_id=? AND operation_key=?',[$entry['card_id'],$key]))continue;$amount=-(float)$entry['amount'];$app->exec('UPDATE fisitaap_r3_gift_cards SET balance=balance+? WHERE id=?',[$amount,$entry['card_id']]);$app->exec('INSERT INTO fisitaap_r3_gift_entries(tenant_id,card_id,operation_key,kind,amount,order_id) VALUES(?,?,?,"refund",?,?)',[$entry['tenant_id'],$entry['card_id'],$key,$amount,$order]);}if($own)$app->db->commit();}catch(Throwable $ex){if($own&&$app->db->inTransaction())$app->db->rollBack();throw $ex;}
}
