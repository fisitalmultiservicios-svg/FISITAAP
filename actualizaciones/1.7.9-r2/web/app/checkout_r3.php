<?php
declare(strict_types=1);

function r3_checkout_identity(array $raw): array
{
    $key=(string)($raw['checkout_key']??'');
    if($key!==''&&!preg_match('/^[a-f0-9]{32}$/',$key))throw new DomainException('Recarga el carrito antes de enviar el pedido.');
    if(trim((string)($raw['gift_code']??''))!==''&&$key==='')throw new DomainException('Recarga el carrito para usar la tarjeta de regalo.');
    unset($raw['csrf'],$raw['checkout_key']);ksort($raw);
    return [$key,hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))];
}
function r3_checkout_previous(App $app,array $tenant,array $user,array $raw,bool $locked=false): void
{
    [$key,$hash]=r3_checkout_identity($raw);if($key==='')return;
    if($locked)$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
    $record=$app->one('SELECT * FROM fisitaap_r3_checkout_keys WHERE tenant_id=? AND user_id=? AND operation_key=?',[$tenant['id'],$user['id'],$key]);
    if(!$record)return;if(!hash_equals($record['payload_hash'],$hash))throw new DomainException('Este envío ya tiene un pedido con otros datos. Revisa tu cuenta antes de crear otro.');
    $order=$app->one('SELECT * FROM orders WHERE id=? AND tenant_id=? AND user_id=?',[$record['order_id'],$tenant['id'],$user['id']]);if(!$order)throw new DomainException('No se puede comprobar el pedido anterior.');
    $branch=$app->one('SELECT * FROM branches WHERE id=? AND tenant_id=?',[$order['branch_id'],$tenant['id']]);
    $message='PEDIDO #'.$order['order_number']."\nSucursal: ".$branch['name']."\nCliente: ".$order['customer_name']."\nTel: ".$order['customer_phone']."\n";
    foreach($app->all('SELECT * FROM order_items WHERE order_id=? ORDER BY id',[$order['id']]) as $line){$message.=$line['quantity'].' × '.$line['product_name'].' — '.money((float)$line['line_subtotal']+(float)$line['line_tax'])."\n";foreach(json_decode((string)$line['options_json'],true)?:[] as $option)$message.='  '.($option['group']??'').': '.$option['name']."\n";if($line['notes'])$message.='  Nota: '.$line['notes']."\n";}
    $message.='TOTAL: '.money((float)$order['total'])."\nPago: ".$order['payment_method'];$message.=r3_order_gift_summary($app,(int)$order['id'],(float)$order['total']);
    if($locked)$app->db->commit();$phone=preg_replace('/\D/','',(string)($branch['whatsapp']?:$tenant['whatsapp']));$demo=is_demo_tenant_v12($tenant);
    json_response(['ok'=>true,'duplicate'=>true,'order'=>$order['order_number'],'order_id'=>(int)$order['id'],'demo'=>$demo,'store'=>$tenant['name'].' · '.$branch['name'],'preview'=>$demo?$message:null,'whatsapp'=>$demo?null:'https://wa.me/'.$phone.'?text='.rawurlencode($message)]);
}
function r3_checkout_record(App $app,array $tenant,array $user,array $raw,int $order): void
{
    [$key,$hash]=r3_checkout_identity($raw);if($key==='')return;
    $app->exec('INSERT INTO fisitaap_r3_checkout_keys(tenant_id,user_id,operation_key,payload_hash,order_id) VALUES(?,?,?,?,?)',[$tenant['id'],$user['id'],$key,$hash,$order]);
}
function r3_order_gift_summary(App $app,int $order,float $total): string
{
    $payment=$app->one('SELECT COALESCE(SUM(-amount),0) amount FROM fisitaap_r3_gift_entries WHERE order_id=? AND kind="use"',[$order]);$amount=(float)$payment['amount'];
    return $amount>0?"\nTarjeta de regalo: ".money($amount)."\nRestante por pagar: ".money(max(0,$total-$amount)):'';
}
