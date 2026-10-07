<?php
declare(strict_types=1);

// Sales workspace and settlement. All monetary allocation is performed in cents.
function pos_cents_148($value):int {
    if(!is_numeric($value)||!is_finite((float)$value)||(float)$value<0||(float)$value>999999999)throw new RuntimeException('Monto inválido.');
    return (int)round((float)$value*100);
}
function pos_payment_identity_qa(array $ids,array $form): string {
    unset($form['csrf'],$form['payment_key'],$form['print_receipt']);
    $canonical=static function(array $values)use(&$canonical):array {
        ksort($values,SORT_STRING);
        foreach($values as &$value)if(is_array($value))$value=$canonical($value);
        unset($value);return $values;
    };
    return hash('sha256',json_encode(['tickets'=>$ids,'form'=>$canonical($form)],JSON_THROW_ON_ERROR));
}
function pos_allocate_148(int $total,array $weights):array {
    $sum=array_sum($weights);$out=array_fill_keys(array_keys($weights),0);if($total===0)return $out;
    if($total<0||$sum<=0)throw new RuntimeException('No es posible distribuir este monto.');
    $remainders=[];$used=0;foreach($weights as $key=>$weight){$exact=$total*$weight/$sum;$out[$key]=(int)floor($exact);$used+=$out[$key];$remainders[$key]=$exact-$out[$key];}
    arsort($remainders,SORT_NUMERIC);foreach($remainders as $key=>$unused){if($used>=$total)break;$out[$key]++;$used++;}return $out;
}

/**
 * Returns the printer profile assigned to a document point in the active
 * branch.  The legacy receipt_* columns remain the fallback so an existing
 * installation keeps working until the owner assigns individual points.
 */
function printer_profile_148(array $branch,string $point='receipt'):array {
    $allowed=['receipt','kitchen','bar','dispatch','copy'];$configured=json_decode((string)($branch['printer_points_json']??''),true)?:[];foreach(array_keys($configured) as $key)if(preg_match('/^zone_[a-f0-9]{12}$/',(string)$key))$allowed[]=$key;
    if(!in_array($point,$allowed,true))$point='receipt';
    $legacy=[
        'width'=>(string)($branch['receipt_width']??'80'),
        'type'=>(string)($branch['receipt_printer_type']??'browser'),
        'name'=>(string)($branch['receipt_printer_name']??''),
        'host'=>(string)($branch['receipt_printer_host']??''),
        'port'=>(int)($branch['receipt_printer_port']??9100),
        'autoprint'=>(int)($branch['receipt_autoprint']??0),
    ];
    $profiles=json_decode((string)($branch['printer_points_json']??''),true);
    $profile=is_array($profiles)&&is_array($profiles[$point]??null)?$profiles[$point]:[];
    $width=in_array((string)($profile['width']??''),['58','80'],true)?(string)$profile['width']:$legacy['width'];
    $type=in_array((string)($profile['type']??''),['browser','network','usb','bluetooth'],true)?(string)$profile['type']:$legacy['type'];
    return [
        'point'=>$point,
        'width'=>in_array($width,['58','80'],true)?(int)$width:80,
        'type'=>$type,
        'name'=>mb_substr(trim((string)($profile['name']??$legacy['name'])),0,150),
        'host'=>mb_substr(trim((string)($profile['host']??$legacy['host'])),0,150),
        'port'=>max(1,min(65535,(int)($profile['port']??$legacy['port']))),
        'autoprint'=>array_key_exists('autoprint',$profile)?(!empty($profile['autoprint'])?1:0):$legacy['autoprint'],
    ];
}

function printer_profiles_148(array $branch):array {
    $profiles=[];foreach(array_unique(array_merge(['receipt','kitchen','bar','dispatch','copy'],array_filter(array_keys(json_decode((string)($branch['printer_points_json']??''),true)?:[]),fn($k)=>preg_match('/^zone_[a-f0-9]{12}$/',(string)$k)))) as $point)$profiles[$point]=printer_profile_148($branch,$point);return $profiles;
}
function pos_access_148(App $app,array $user,array $tenant,bool $charge=false):array {
    if(!physical_store_v14($tenant))throw new RuntimeException('Tienda física está desactivada.');
    require_section_permission($user,$charge?(($_GET['screen']??'')==='checkout'?'ventas':'cobro'):'ventas');require_module_v12($app,$tenant,'pos');
    $branch=physical_branch_v14($app,$tenant);if(!$branch)throw new RuntimeException('Selecciona una sucursal activa.');return $branch;
}
function pos_ticket_get_148(App $app,array $tenant,array $branch,int $id,bool $lock=false):array {
    $t=$app->one('SELECT * FROM pos_tickets WHERE id=? AND tenant_id=? AND branch_id=?'.($lock?' FOR UPDATE':''),[$id,$tenant['id'],$branch['id']]);
    if(!$t)throw new RuntimeException('La cuenta no está disponible en esta sucursal.');return $t;
}
function pos_customer_148(App $app,array $tenant,int $id):array {
    if($id===0&&function_exists('r2_active')&&r2_active($app))return r2_final_customer($app,(int)$tenant['id']);
    $c=$app->one('SELECT u.id,u.name,u.phone FROM users u JOIN tenant_customers tc ON tc.user_id=u.id WHERE tc.tenant_id=? AND u.id=? AND u.role="customer" AND u.is_active=1',[$tenant['id'],$id]);
    if(!$c)throw new RuntimeException('Selecciona un cliente registrado en este negocio.');return $c;
}
function pos_create_customer_148(App $app,array $tenant,array $user):int {
    $name=trim((string)($_POST['new_name']??''));$phone=preg_replace('/\D/','',(string)($_POST['new_phone']??''));$email=strtolower(trim((string)($_POST['new_email']??'')));$identity=trim((string)($_POST['new_identity']??''));
    if($name===''||mb_strlen($name)>150||!preg_match('/^(506)?[0-9]{8}$/',$phone))throw new RuntimeException('Escribe el nombre y un teléfono de Costa Rica de 8 dígitos.');
    if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Revisa el correo.');
    $existing=$app->one('SELECT u.id FROM users u JOIN tenant_customers tc ON tc.user_id=u.id WHERE tc.tenant_id=? AND (REPLACE(REPLACE(u.phone,"-","")," ","")=? OR (u.email=? AND ?<>"")) LIMIT 1',[$tenant['id'],$phone,$email,$email]);
    if($existing)throw new RuntimeException('Ya existe un cliente con esos datos en tu tienda. Selecciónalo en la lista.');
    if($email!==''&&$app->one('SELECT id FROM users WHERE email=?',[$email]))throw new RuntimeException('No se puede usar ese correo para crear un cliente. Usa la cuenta ya vinculada al negocio u otro correo.');
    if($email==='')$email='pos-'.bin2hex(random_bytes(16)).'@registro.fisitaap.local';
    $app->exec('INSERT INTO users(name,email,phone,password_hash,role,is_active) VALUES(?,?,?,?,"customer",1)',[$name,$email,$phone,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
    $id=(int)$app->db->lastInsertId();$app->exec('INSERT INTO tenant_customers(tenant_id,user_id) VALUES(?,?)',[$tenant['id'],$id]);
    $app->exec('INSERT INTO customer_credit_profiles(tenant_id,user_id,notes) VALUES(?,?,?)',[$tenant['id'],$id,$identity!==''?'Identificación: '.mb_substr($identity,0,80):'']);
    if(function_exists('r2_active')&&r2_active($app))$app->exec('INSERT IGNORE INTO fisitaap_r2_customer_sources(tenant_id,user_id,source,created_by) VALUES(?,?,"caja",?)',[$tenant['id'],$id,$user['id']]);audit($app,(int)$tenant['id'],(int)$user['id'],'pos_customer_created','user',$id);return $id;
}
function pos_table_state_148(App $app,int $tenant,int $table):void {
    $count=$app->one('SELECT COUNT(*) n FROM pos_tickets WHERE tenant_id=? AND table_id=? AND status NOT IN ("paid","void")',[$tenant,$table]);
    $app->exec('UPDATE restaurant_tables SET status=? WHERE tenant_id=? AND id=?',[(int)$count['n']?'occupied':'available',$tenant,$table]);
}
function pos_recalculate_148(App $app,int $id):array {
    $t=$app->one('SELECT * FROM pos_tickets WHERE id=?',[$id]);if(!$t)throw new RuntimeException('Cuenta inexistente.');
    if(in_array($t['status'],['paid','void'],true))return $t;
    $s=$app->one('SELECT COALESCE(SUM(line_subtotal),0) net,COALESCE(SUM(line_tax),0) tax FROM pos_ticket_items WHERE ticket_id=?',[$id]);
    $net=pos_cents_148($s['net']);$tax=pos_cents_148($s['tax']);$discount=pos_cents_148($t['discount_total']);$service=(int)round($net*(float)$t['service_rate']/100);
    if($discount>$net)throw new RuntimeException('El descuento supera el subtotal.');
    $app->exec('UPDATE pos_tickets SET subtotal=?,tax_total=?,service_total=?,total=? WHERE id=?',[$net/100,$tax/100,$service/100,($net+$tax+$service-$discount)/100,$id]);
    return (array)$app->one('SELECT * FROM pos_tickets WHERE id=?',[$id]);
}
function pos_open_148(App $app,array $tenant,array $user,array $branch,string $type,?int $tableId=null):int {
    if(!in_array($type,['table','quick','express','catalog'],true))throw new RuntimeException('Tipo de venta inválido.');
    $key=(string)($_POST['open_key']??'');if(!preg_match('/^[a-f0-9]{32}$/',$key))throw new RuntimeException('Recarga la ventana para iniciar la cuenta.');
    $app->db->beginTransaction();try{
        $old=$app->one('SELECT id FROM pos_tickets WHERE tenant_id=? AND open_key=?',[$tenant['id'],$key]);if($old){$app->db->commit();return (int)$old['id'];}
        $label='';$rate=0;$customer=null;$address='';
        if($tableId){$table=$app->one('SELECT * FROM restaurant_tables WHERE id=? AND tenant_id=? AND branch_id=? AND is_active=1 FOR UPDATE',[$tableId,$tenant['id'],$branch['id']]);if(!$table)throw new RuntimeException('Mesa no disponible.');
            if($table['space_type']==='bar'){$label=trim((string)($_POST['diner_name']??''));if($label===''||mb_strlen($label)>150)throw new RuntimeException('Escribe el nombre del comensal de barra.');}
            else{$old=$app->one('SELECT id FROM pos_tickets WHERE table_id=? AND tenant_id=? AND status NOT IN ("paid","void") ORDER BY id LIMIT 1',[$tableId,$tenant['id']]);if($old){$app->db->commit();return (int)$old['id'];}$label='';}
            $rate=function_exists('r2_active')&&r2_active($app)?r2_table_rate($app,(int)$table['id'],(bool)$table['service_enabled']):((int)$table['service_enabled']?10:0);
        }
        if($type==='express'){$customer=!empty($_POST['create_customer'])?pos_create_customer_148($app,$tenant,$user):(int)($_POST['customer_id']??0);$expressCustomer=pos_customer_148($app,$tenant,$customer);if(!$expressCustomer['phone'])throw new RuntimeException('Selecciona o crea un cliente con teléfono para Express.');$customer=(int)$expressCustomer['id'];$address=trim((string)($_POST['delivery_address']??''));if($address===''||mb_strlen($address)>1000)throw new RuntimeException('Escribe la dirección y señas de entrega.');}
        $app->exec('INSERT INTO pos_tickets(tenant_id,branch_id,table_id,customer_id,display_label,ticket_number,sale_type,opened_by,service_rate,open_key,delivery_address) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$tenant['id'],$branch['id'],$tableId,$customer,$label?:null,physical_number_v14('CM'),$type,$user['id'],$rate,$key,$address]);$id=(int)$app->db->lastInsertId();if($tableId)pos_table_state_148($app,(int)$tenant['id'],$tableId);
        audit($app,(int)$tenant['id'],(int)$user['id'],'pos_open_'.$type,'pos_ticket',$id);$app->db->commit();return $id;
    }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}
}
function pos_edit_148(App $app,array $tenant,array $user,array $branch,int $id):void {
    verify_csrf();$action=(string)($_POST['action']??'');$app->db->beginTransaction();try{
        $t=pos_ticket_get_148($app,$tenant,$branch,$id,true);
        if(in_array($t['status'],['paid','void'],true))throw new RuntimeException('Esta cuenta ya está cerrada.');
        if((int)($_POST['revision']??-1)!==(int)$t['revision'])throw new RuntimeException('La cuenta cambió. Recarga y revisa los datos.');
        if($t['sale_type']==='express')pos_customer_148($app,$tenant,(int)$t['customer_id']);
        if((float)$t['paid_total']>0&&in_array($action,['add_item','quantity','edit_item','void','discount'],true))throw new RuntimeException('Esta cuenta tiene pagos. Completa el cobro; registra nuevos consumos en otra cuenta.');
        if($action==='add_item'){
            $p=$app->one('SELECT * FROM products WHERE id=? AND tenant_id=? AND status="active" FOR UPDATE',[(int)($_POST['product_id']??0),$tenant['id']]);if(!$p)throw new RuntimeException('Producto no disponible.');
            $qty=(float)($_POST['quantity']??1);if(!is_finite($qty)||$qty<=0||$qty>99999||$qty<(float)$p['min_qty'])throw new RuntimeException('Cantidad inválida.');$qty=round($qty,3);if(abs(($qty-(float)$p['min_qty'])/(float)$p['qty_step']-round(($qty-(float)$p['min_qty'])/(float)$p['qty_step']))>.0001)throw new RuntimeException('Revisa el incremento de cantidad.');if($tenant['catalog_mode']==='branches'){$bp=$app->one('SELECT price_override,sale_price_override FROM branch_products WHERE branch_id=? AND product_id=? AND is_active=1',[$branch['id'],$p['id']]);if(!$bp)throw new RuntimeException('Producto no disponible en esta sucursal.');$p['price']=$bp['price_override']??$p['price'];$p['sale_price']=$bp['sale_price_override']??$p['sale_price'];}
            $o=pos_product_options_v141($app,(int)$tenant['id'],(int)$p['id'],array_values(array_unique(array_map('intval',(array)($_POST['option_ids']??[])))));
            $price=(float)($p['sale_price']?:$p['price'])+$o['price'];$net=round($price*$qty,2);$tax=round($net*(float)$p['tax_rate']/100,2);$pricing=null;if(function_exists('r2_active')&&r2_active($app)){$r2price=r2_line($app,$tenant,$p,$qty,$o['price']);$price=$r2price['price'];$net=$r2price['subtotal'];$tax=$r2price['tax'];$pricing=json_encode($r2price['recipe'],JSON_THROW_ON_ERROR);}$note=mb_substr(trim((string)($_POST['notes']??'')),0,500);
            if($p['track_inventory'])stock_change_v14($app,(int)$tenant['id'],(int)$branch['id'],(int)$p['id'],-$qty,'pos_reserve','pos_ticket',$id,(int)$user['id'],'Reserva '.$t['ticket_number'],(float)$p['cost']);
            $app->exec('INSERT INTO pos_ticket_items(ticket_id,product_id,product_name,quantity,unit_price,unit_cost,tax_rate,line_subtotal,line_tax,options_json,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$id,$p['id'],$p['name'],$qty,$price,$p['cost'],$p['tax_rate'],$net,$tax,$o['json'],$note]);if($pricing!==null)$app->exec('UPDATE pos_ticket_items SET pricing_json=? WHERE id=?',[$pricing,$app->db->lastInsertId()]);
        }elseif($action==='quantity'){
            $i=$app->one('SELECT i.*,p.track_inventory,p.min_qty,p.qty_step FROM pos_ticket_items i LEFT JOIN products p ON p.id=i.product_id WHERE i.id=? AND i.ticket_id=? FOR UPDATE',[(int)$_POST['item_id'],$id]);
            if(!$i||$i['kitchen_status']!=='pending')throw new RuntimeException('Solo puedes modificar artículos sin enviar a cocina.');$qty=(float)($_POST['quantity']??-1);if(!is_finite($qty)||$qty<0||$qty>99999)throw new RuntimeException('Cantidad inválida.');$qty=round($qty,3);if($qty>0&&($qty<(float)$i['min_qty']||abs(($qty-(float)$i['min_qty'])/(float)$i['qty_step']-round(($qty-(float)$i['min_qty'])/(float)$i['qty_step']))>.0001))throw new RuntimeException('Revisa la cantidad mínima y su incremento.');
            if($i['track_inventory'])stock_change_v14($app,(int)$tenant['id'],(int)$branch['id'],(int)$i['product_id'],(float)$i['quantity']-$qty,'pos_adjust','pos_ticket',$id,(int)$user['id'],'Ajuste '.$t['ticket_number'],(float)$i['unit_cost']);
            if($qty===0.0)$app->exec('DELETE FROM pos_ticket_items WHERE id=?',[$i['id']]);else{$net=round($qty*(float)$i['unit_price'],2);$tax=round($net*(float)$i['tax_rate']/100,2);$price=(float)$i['unit_price'];if(!empty($i['pricing_json'])){$priced=r2_recipe(json_decode($i['pricing_json'],true,32,JSON_THROW_ON_ERROR),$qty);$net=$priced['subtotal'];$tax=$priced['tax'];$price=$priced['price'];}$app->exec('UPDATE pos_ticket_items SET quantity=?,line_subtotal=?,line_tax=?,unit_price=? WHERE id=?',[$qty,$net,$tax,$price,$i['id']]);}
        }elseif($action==='prebill'&&function_exists('r3_active')&&r3_active($app)) {
            $app->exec('UPDATE pos_tickets SET status="payment" WHERE id=?',[$id]);
        }elseif($action==='notes'&&function_exists('r3_active')&&r3_active($app)) {
            $app->exec('UPDATE pos_tickets SET notes=? WHERE id=?',[mb_substr(trim((string)($_POST['notes']??'')),0,1000),$id]);
        }elseif($action==='edit_item'&&function_exists('r3_active')&&r3_active($app)) {
            $i=$app->one('SELECT i.*,p.track_inventory,p.min_qty,p.qty_step FROM pos_ticket_items i JOIN products p ON p.id=i.product_id WHERE i.id=? AND i.ticket_id=? FOR UPDATE',[(int)($_POST['item_id']??0),$id]);
            if(!$i||$i['kitchen_status']!=='pending')throw new DomainException('Solo puedes editar productos sin enviar a cocina.');
            $qty=r2_number($_POST['quantity']??0,(float)$i['min_qty'],99999);$qty=round($qty,3);
            if(abs(($qty-(float)$i['min_qty'])/(float)$i['qty_step']-round(($qty-(float)$i['min_qty'])/(float)$i['qty_step']))>.0001)throw new DomainException('Revisa el incremento de cantidad.');
            $o=pos_product_options_v141($app,(int)$tenant['id'],(int)$i['product_id'],array_values(array_unique(array_map('intval',(array)($_POST['option_ids']??[])))));
            $oldExtras=0;foreach(json_decode((string)$i['options_json'],true)?:[] as $oldOption)$oldExtras+=(float)($oldOption['price_delta']??0);
            $recipe=!empty($i['pricing_json'])?json_decode($i['pricing_json'],true,32,JSON_THROW_ON_ERROR):['base'=>pos_cents_148(max(0,(float)$i['unit_price']-$oldExtras)),'extra'=>0,'rate'=>(int)round((float)$i['tax_rate']*100),'included'=>false,'bogo'=>false];
            $recipe['extra']=pos_cents_148($o['price']);$priced=r2_recipe($recipe,$qty);
            if($i['track_inventory'])stock_change_v14($app,(int)$tenant['id'],(int)$branch['id'],(int)$i['product_id'],(float)$i['quantity']-$qty,'pos_adjust','pos_ticket',$id,(int)$user['id'],'Edición '.$t['ticket_number'],(float)$i['unit_cost']);
            $app->exec('UPDATE pos_ticket_items SET quantity=?,unit_price=?,line_subtotal=?,line_tax=?,options_json=?,notes=?,pricing_json=? WHERE id=?',[$qty,$priced['price'],$priced['subtotal'],$priced['tax'],$o['json'],mb_substr(trim((string)($_POST['notes']??'')),0,500),json_encode($recipe,JSON_THROW_ON_ERROR),$i['id']]);
        }elseif($action==='customer'){$cid=!empty($_POST['create_customer'])?pos_create_customer_148($app,$tenant,$user):(int)($_POST['customer_id']??0);pos_customer_148($app,$tenant,$cid);$app->exec('UPDATE pos_tickets SET customer_id=? WHERE id=?',[$cid,$id]);
        }elseif($action==='send_kitchen'){
            $ids=array_column($app->all('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND kitchen_status="pending" FOR UPDATE',[$id]),'id');if(!$ids)throw new RuntimeException('No hay artículos nuevos para cocina.');
            $app->exec('UPDATE pos_ticket_items SET kitchen_status="sent",sent_at=NOW() WHERE ticket_id=? AND kitchen_status="pending"',[$id]);
        }elseif($action==='discount'){$d=pos_cents_148($_POST['discount']??0);if($d>pos_cents_148($t['subtotal']))throw new RuntimeException('El descuento supera el subtotal.');$app->exec('UPDATE pos_tickets SET discount_total=? WHERE id=?',[$d/100,$id]);
        }elseif($action==='suspend'){$app->exec('UPDATE pos_tickets SET status="suspended" WHERE id=?',[$id]);
        }elseif($action==='resume'||$action==='save'){$app->exec('UPDATE pos_tickets SET status="open" WHERE id=?',[$id]);
        }elseif($action==='void'){
            foreach($app->all('SELECT i.*,p.track_inventory FROM pos_ticket_items i LEFT JOIN products p ON p.id=i.product_id WHERE i.ticket_id=?',[$id]) as $i)if($i['track_inventory'])stock_change_v14($app,(int)$tenant['id'],(int)$branch['id'],(int)$i['product_id'],(float)$i['quantity'],'pos_release','pos_void',$id,(int)$user['id'],'Anulación '.$t['ticket_number'],(float)$i['unit_cost']);
            $app->exec('UPDATE pos_tickets SET status="void" WHERE id=?',[$id]);
        }else throw new RuntimeException('Acción no disponible.');
        pos_recalculate_148($app,$id);$app->exec('UPDATE pos_tickets SET revision=revision+1 WHERE id=?',[$id]);if($t['table_id'])pos_table_state_148($app,(int)$tenant['id'],(int)$t['table_id']);audit($app,(int)$tenant['id'],(int)$user['id'],'pos_'.$action,'pos_ticket',$id);$app->db->commit();
        if($action==='prebill')redirect(url('admin/recibo?precuenta='.$id));
        if($action==='send_kitchen')redirect(url('admin/recibo?comanda='.$id.'&auto=1&items='.implode(',',$ids)));
        if(in_array($action,['void','suspend'],true))redirect(url('admin/ventas'));flash('success','Cuenta guardada.');
    }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();flash('error',$e->getMessage());}
    redirect(url('admin/ventas?screen=order&ticket='.$id));
}
// Pure, deterministic quote reused by the payment transaction. No client totals are trusted.
function pos_quote_148(array $tickets,array $items,string $mode,array $quantities,int $parts):array {
    if(!in_array($mode,['all','items','equal'],true))throw new RuntimeException('Forma de división inválida.');
    if($mode==='equal'&&(count($tickets)!==1||$parts<2||$parts>20))throw new RuntimeException('Divide una cuenta entre 2 y 20 partes.');
    $lines=[];$gross=[];$remaining=[];$ticketMap=[];
    foreach($tickets as $t){$ticketMap[(int)$t['id']]=$t;if((int)$t['split_parts_paid']>0&&$mode!=='equal')throw new RuntimeException('Continúa cobrando las partes iguales de esta cuenta.');
        $own=array_filter($items,fn($i)=>(int)$i['ticket_id']===(int)$t['id']);$weights=[];foreach($own as $i)$weights[$i['id']]=pos_cents_148($i['line_subtotal']);
        $services=pos_allocate_148(pos_cents_148($t['service_total']),$weights);$discounts=pos_allocate_148(pos_cents_148($t['discount_total']),$weights);
        foreach($own as $i){$key=(int)$i['id'];$q=max(0,round((float)$i['quantity']-(float)$i['paid_quantity'],3));
            $components=['net'=>max(0,pos_cents_148($i['line_subtotal'])-pos_cents_148($i['paid_net'])),'tax'=>max(0,pos_cents_148($i['line_tax'])-pos_cents_148($i['paid_tax'])),'service'=>max(0,$services[$key]-pos_cents_148($i['paid_service'])),'discount'=>max(0,$discounts[$key]-pos_cents_148($i['paid_discount']))];
            $g=$components['net']+$components['tax']+$components['service']-$components['discount'];if($g<=0||$q<=0)continue;
            $remaining[$key]=['item'=>$i,'qty'=>$q]+$components;$gross[$key]=$g;
        }
    }
    if(!$remaining)throw new RuntimeException('No hay consumo pendiente de cobro.');
    $equalTarget=null;if($mode==='equal'){foreach($remaining as $r)if($r['qty']<($parts-(int)$tickets[0]['split_parts_paid'])*.001)throw new RuntimeException('Este consumo fraccionario debe cobrarse completo o por productos.');$t=$tickets[0];if((int)$t['split_parts']&&$parts!==(int)$t['split_parts'])throw new RuntimeException('Conserva el número de partes configurado.');$left=$parts-(int)$t['split_parts_paid'];if($left<=0)throw new RuntimeException('Todas las partes fueron cobradas.');$equalTarget=$left===1?array_sum($gross):((int)$t['split_parts']?pos_cents_148($t['split_part_amount']):(int)floor(array_sum($gross)/$parts));if($equalTarget<=0)throw new RuntimeException('El saldo es demasiado pequeño para esta división.');$targets=pos_allocate_148($equalTarget,$gross);}
    foreach($remaining as $key=>$r){
        $qty=$mode==='items'?(float)($quantities[$key]??0):$r['qty'];if(!is_finite($qty)||$qty<0||$qty>$r['qty']+.00001)throw new RuntimeException('Revisa la cantidad seleccionada.');$qty=round($qty,3);if($mode==='items'&&$qty===0.0)continue;
        $ratio=$mode==='equal'?$targets[$key]/$gross[$key]:$qty/$r['qty'];if($ratio<=0)continue;if($mode==='equal')$qty=$ratio>=1?$r['qty']:round($r['qty']*$ratio,3);
        $line=['item'=>$r['item'],'qty'=>$qty];foreach(['net','tax','service','discount'] as $c)$line[$c]=$ratio>=1?$r[$c]:(int)round($r[$c]*$ratio);
        if($mode==='equal'){$allocated=pos_allocate_148($targets[$key],['net'=>max(0,$r['net']-$r['discount']),'tax'=>$r['tax'],'service'=>$r['service']]);$line['discount']=$ratio>=1?$r['discount']:(int)floor($r['discount']*$ratio);$line['net']=$allocated['net']+$line['discount'];$line['tax']=$allocated['tax'];$line['service']=$allocated['service'];}
        $line['total']=$line['net']+$line['tax']+$line['service']-$line['discount'];if($line['total']<=0)continue;$lines[]=$line;
    }
    if(!$lines)throw new RuntimeException('Selecciona productos pendientes de cobro.');
    return ['lines'=>$lines,'total'=>array_sum(array_column($lines,'total')),'net'=>array_sum(array_column($lines,'net')),'tax'=>array_sum(array_column($lines,'tax')),'service'=>array_sum(array_column($lines,'service')),'discount'=>array_sum(array_column($lines,'discount')),'equal_part'=>$equalTarget];
}
function pos_settle_148(App $app,array $tenant,array $user,array $branch,array $ids):int {
    verify_csrf();$key=(string)($_POST['payment_key']??'');if(!preg_match('/^[a-f0-9]{32}$/',$key))throw new RuntimeException('Recarga el cobro.');sort($ids,SORT_NUMERIC);
    $identity=pos_payment_identity_qa($ids,$_POST);
    $app->db->beginTransaction();try{
        $tickets=[];$items=[];foreach($ids as $id)$tickets[]=pos_ticket_get_148($app,$tenant,$branch,$id,true);
        $prior=$app->one('SELECT id,payment_payload_hash FROM sales WHERE tenant_id=? AND created_by=? AND payment_key=?',[$tenant['id'],$user['id'],$key]);if($prior){
            $oldIds=array_map('intval',array_column($app->all('SELECT DISTINCT ticket_id FROM pos_payments WHERE sale_id=? ORDER BY ticket_id',[$prior['id']]),'ticket_id'));
            if(($prior['payment_payload_hash']!==null&&!hash_equals($prior['payment_payload_hash'],$identity))||($prior['payment_payload_hash']===null&&$oldIds!==$ids))throw new RuntimeException('Este cobro ya corresponde a otra cuenta o a otros montos. Revisa el recibo anterior antes de crear un cobro nuevo.');
            $app->db->commit();return (int)$prior['id'];
        }
        if(count($tickets)>1){$table=(int)$tickets[0]['table_id'];if(!$table)throw new RuntimeException('Selecciona cuentas de la misma mesa o barra.');foreach($tickets as $t)if((int)$t['table_id']!==$table)throw new RuntimeException('No se pueden mezclar mesas distintas.');}
        foreach($tickets as &$t){if(in_array($t['status'],['paid','void'],true))throw new RuntimeException('Una cuenta ya se cerró.');if((int)($_POST['revisions'][$t['id']]??-1)!==(int)$t['revision'])throw new RuntimeException('La cuenta cambió. Revisa el saldo actualizado antes de cobrar.');$t=pos_recalculate_148($app,(int)$t['id']);$items=array_merge($items,$app->all('SELECT i.*,p.sku FROM pos_ticket_items i LEFT JOIN products p ON p.id=i.product_id WHERE ticket_id=? ORDER BY i.id FOR UPDATE',[$t['id']]));}unset($t);
        $cid=!empty($_POST['create_customer'])?pos_create_customer_148($app,$tenant,$user):(int)($_POST['customer_id']??0);$customer=pos_customer_148($app,$tenant,$cid);$cid=(int)$customer['id'];
        $mode=(string)($_POST['split_mode']??'all');$parts=(int)($_POST['parts']??2);$quote=pos_quote_148($tickets,$items,$mode,(array)($_POST['qty']??[]),$parts);
        $payments=[];foreach(['card'=>'Tarjeta','sinpe'=>'SINPE','credit'=>'Crédito'] as $k=>$label){$v=pos_cents_148($_POST[$k]??0);if($v)$payments[$label]=$v;}
        $gift=null;if(function_exists('r3_active')&&r3_active($app)&&trim((string)($_POST['gift_code']??''))!==''){ $gift=r3_gift_payment($app,(int)$tenant['id'],$cid,(string)$_POST['gift_code'],pos_cents_148($_POST['gift_amount']??0),$quote['total']);$payments['Tarjeta de regalo']=$gift['amount']; }
        $noncash=array_sum($payments);if($noncash>$quote['total'])throw new RuntimeException('Tarjeta, SINPE y crédito superan este cobro.');$cash=pos_cents_148($_POST['cash']??0);$cashApplied=$quote['total']-$noncash;if($cash<$cashApplied)throw new RuntimeException('Falta completar el pago.');if($cashApplied)$payments['Efectivo']=$cashApplied;$change=$cash-$cashApplied;
        $credit=$payments['Crédito']??0;$profile=$app->one('SELECT * FROM customer_credit_profiles WHERE tenant_id=? AND user_id=? FOR UPDATE',[$tenant['id'],$cid]);if($credit&&(!$profile||!$profile['credit_enabled']||pos_cents_148($profile['credit_limit'])-pos_cents_148($profile['current_balance'])<$credit))throw new RuntimeException('Crédito no autorizado o disponible insuficiente.');
        $shift=$app->one('SELECT * FROM pos_shifts s WHERE tenant_id=? AND branch_id=? AND user_id=? AND status="open" '.(function_exists('r2_active')&&r2_active($app)?'AND NOT EXISTS(SELECT 1 FROM fisitaap_r2_shift_links l WHERE l.shift_id=s.id) ':'').'ORDER BY id DESC LIMIT 1 FOR UPDATE',[$tenant['id'],$branch['id'],$user['id']]);if(!$shift)throw new RuntimeException('Abre tu turno de caja en Turnos antes de cobrar.');
        $number=physical_number_v14('V');$labels=implode(', ',array_map(fn($t)=>$t['ticket_number'].(!empty($t['display_label'])?' · '.$t['display_label']:''),$tickets));
        $app->exec('INSERT INTO sales(tenant_id,branch_id,shift_id,customer_id,sale_number,sale_type,customer_name,payment_method,subtotal,tax_total,service_total,discount_total,total,amount_paid,change_due,status,notes,created_by,payment_key) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"completed",?,?,?)',[$tenant['id'],$branch['id'],$shift['id'],$cid,$number,$tickets[0]['sale_type'],$customer['name'],count($payments)>1?'Pago combinado':array_key_first($payments),$quote['net']/100,$quote['tax']/100,$quote['service']/100,$quote['discount']/100,$quote['total']/100,$quote['total']/100,$change/100,'Cobro de '.$labels,$user['id'],$key]);$saleId=(int)$app->db->lastInsertId();$perTicket=[];
        $app->exec('UPDATE sales SET payment_payload_hash=? WHERE id=?',[$identity,$saleId]);
        if($gift)r3_gift_debit($app,$gift,'sale-'.$key,$saleId,null,(int)$user['id']);
        foreach($quote['lines'] as $l){$i=$l['item'];$tid=(int)$i['ticket_id'];$perTicket[$tid]=($perTicket[$tid]??0)+$l['total'];$name=$i['product_name'];foreach($tickets as $t)if((int)$t['id']===$tid&&!empty($t['display_label']))$name=mb_substr($name.' · '.$t['display_label'],0,190);
            $app->exec('INSERT INTO sale_items(sale_id,product_id,product_name,sku,quantity,unit_price,unit_cost,tax_rate,line_subtotal,line_tax) VALUES(?,?,?,?,?,?,?,?,?,?)',[$saleId,$i['product_id'],$name,$i['sku'],$l['qty'],$i['unit_price'],$i['unit_cost'],$i['tax_rate'],$l['net']/100,$l['tax']/100]);
            if(function_exists('r3_active')&&r3_active($app))$app->exec('UPDATE sale_items SET options_json=?,notes=? WHERE id=?',[$i['options_json'],$i['notes'],$app->db->lastInsertId()]);
            $app->exec('UPDATE pos_ticket_items SET paid_quantity=LEAST(quantity,paid_quantity+?),paid_net=paid_net+?,paid_tax=paid_tax+?,paid_service=paid_service+?,paid_discount=paid_discount+? WHERE id=?',[$l['qty'],$l['net']/100,$l['tax']/100,$l['service']/100,$l['discount']/100,$i['id']]);
        }
        $unallocated=$perTicket;foreach($payments as $method=>$amount){$alloc=pos_allocate_148($amount,$unallocated);foreach($alloc as $tid=>$value)$unallocated[$tid]-=$value;foreach($alloc as $tid=>$value)if($value)$app->exec('INSERT INTO pos_payments(ticket_id,sale_id,customer_id,payment_method,amount,reference,received_by) VALUES(?,?,?,?,?,?,?)',[$tid,$saleId,$cid,$method,$value/100,mb_substr(trim((string)($_POST['payment_reference']??'')),0,150),$user['id']]);}
        if($credit){$app->exec('UPDATE customer_credit_profiles SET current_balance=current_balance+? WHERE tenant_id=? AND user_id=?',[$credit/100,$tenant['id'],$cid]);$due=date('Y-m-d',strtotime('+'.max(1,(int)$profile['payment_terms_days']).' days'));$app->exec('INSERT INTO accounts_receivable(tenant_id,branch_id,sale_id,customer_id,document_number,customer_name,issue_date,due_date,amount,balance,status) VALUES(?,?,?,?,?,?,CURDATE(),?,?,?,"pending")',[$tenant['id'],$branch['id'],$saleId,$cid,$number,$customer['name'],$due,$credit/100,$credit/100]);}
        foreach($tickets as $t){$tid=(int)$t['id'];$amount=$perTicket[$tid]??0;if(!$amount)continue;$paid=pos_cents_148($t['paid_total'])+$amount;$complete=$paid>=pos_cents_148($t['total']);if($paid>pos_cents_148($t['total']))throw new RuntimeException('El cobro supera el saldo.');
            $app->exec('UPDATE pos_tickets SET paid_total=?,status=?,revision=revision+1,split_parts=?,split_parts_paid=?,split_part_amount=? WHERE id=?',[$paid/100,$complete?'paid':'payment',$mode==='equal'?$parts:$t['split_parts'],$mode==='equal'?(int)$t['split_parts_paid']+1:$t['split_parts_paid'],$mode==='equal'&&!(int)$t['split_parts']?$quote['equal_part']/100:$t['split_part_amount'],$tid]);
            if($complete)$app->exec('UPDATE pos_ticket_items SET paid_quantity=quantity WHERE ticket_id=?',[$tid]);if($t['table_id'])pos_table_state_148($app,(int)$tenant['id'],(int)$t['table_id']);
        }
        $jobs=[];if(($tenant['catalog_label']??'')==='Menú')foreach($tickets as $t){$pending=array_column($app->all('SELECT id FROM pos_ticket_items WHERE ticket_id=? AND kitchen_status="pending"',[$t['id']]),'id');if($pending){$app->exec('UPDATE pos_ticket_items SET kitchen_status="sent",sent_at=NOW() WHERE ticket_id=? AND kitchen_status="pending"',[$t['id']]);$jobs[]=['ticket_id'=>(int)$t['id'],'items'=>$pending];}}
        $app->exec('UPDATE sales SET kitchen_jobs_json=? WHERE id=?',[json_encode($jobs),$saleId]);
        if(function_exists('r2_active')&&r2_active($app))r2_loyalty_sale($app,$saleId);audit($app,(int)$tenant['id'],(int)$user['id'],'pos_payment','sale',$saleId);$app->db->commit();return $saleId;
    }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();throw $e;}
}

function pos_assets_148():void {
    echo '<link rel="stylesheet" href="'.e(url('assets/pos-v148.css?v=148pos5')).'"><script defer src="'.e(url('assets/pos-v148.js?v=160r2')).'"></script>';
}
function pos_hidden_148(string $name,$value):string{return '<input type="hidden" name="'.e($name).'" value="'.e((string)$value).'">';}
function pos_customers_ui_148(App $app,array $tenant,int $selected=0):void {
    $customers=$app->all('SELECT u.id,u.name,u.phone,COALESCE(c.credit_enabled,0) credit_enabled,COALESCE(c.credit_limit-c.current_balance,0) credit_available FROM tenant_customers tc JOIN users u ON u.id=tc.user_id LEFT JOIN customer_credit_profiles c ON c.tenant_id=tc.tenant_id AND c.user_id=tc.user_id WHERE tc.tenant_id=? AND u.role="customer" AND u.is_active=1 ORDER BY u.name',[$tenant['id']]);
    echo '<div class="p148-customer"><label>Buscar cliente<input class="input" type="search" data-customer-search placeholder="Nombre o teléfono"></label><label>Cliente registrado<select class="input" name="customer_id" required><option value="0">Consumidor final</option>';
    foreach($customers as $c)echo '<option value="'.(int)$c['id'].'" '.($selected===(int)$c['id']?'selected':'').'>'.e($c['name'].' · '.$c['phone'].($c['credit_enabled']?' · Crédito disponible '.money(max(0,(float)$c['credit_available'])):'')).'</option>';
    echo '</select></label><label class="p148-new-toggle"><input type="checkbox" name="create_customer" value="1" data-new-customer> + Crear cliente</label><div class="p148-new-fields" hidden><label>Nombre completo<input class="input" name="new_name" maxlength="150" data-new-required></label><label>Teléfono<input class="input" name="new_phone" inputmode="tel" maxlength="16" data-new-required></label><label>Identificación<input class="input" name="new_identity" maxlength="80"></label><label>Correo (opcional)<input class="input" type="email" name="new_email" maxlength="190"></label></div></div>';
}
function pos_table_config_148(App $app,array $tenant,array $user,array $branch):void {
    if(!in_array($user['role'],['tenant_admin','manager'],true))throw new RuntimeException('Solo administración puede configurar mesas.');verify_csrf();$action=(string)$_POST['action'];
    $name=trim((string)($_POST['name']??''));if($name===''||mb_strlen($name)>80)throw new RuntimeException('Escribe un nombre de hasta 80 caracteres.');
    if($action==='save_group'){$app->exec('INSERT INTO pos_table_groups(tenant_id,branch_id,name,service_enabled) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE service_enabled=VALUES(service_enabled)',[$tenant['id'],$branch['id'],$name,isset($_POST['service_enabled'])?1:0]);}
    else{$gid=(int)($_POST['group_id']??0);$group=$gid?$app->one('SELECT * FROM pos_table_groups WHERE id=? AND tenant_id=? AND branch_id=?',[$gid,$tenant['id'],$branch['id']]):null;if($gid&&!$group)throw new RuntimeException('Grupo no disponible.');$type=($_POST['space_type']??'table')==='bar'?'bar':'table';$capacity=max(1,min(999,(int)($_POST['capacity']??4)));$service=isset($_POST['service_enabled'])?1:0;$id=(int)($_POST['table_id']??0);
        if($id){$table=$app->one('SELECT * FROM restaurant_tables WHERE id=? AND tenant_id=? AND branch_id=?',[$id,$tenant['id'],$branch['id']]);if(!$table)throw new RuntimeException('Mesa no disponible.');if($table['space_type']!==$type&&$app->one('SELECT id FROM pos_tickets WHERE table_id=? AND status NOT IN ("paid","void") LIMIT 1',[$id]))throw new RuntimeException('Cierra las cuentas antes de cambiar el tipo de espacio.');$app->exec('UPDATE restaurant_tables SET name=?,capacity=?,area=?,space_type=?,group_id=?,service_enabled=? WHERE id=? AND tenant_id=? AND branch_id=?',[$name,$capacity,$group['name']??'Salón',$type,$gid?:null,$service,$id,$tenant['id'],$branch['id']]);}
        else{$app->exec('INSERT INTO restaurant_tables(tenant_id,branch_id,name,capacity,area,space_type,group_id,service_enabled) VALUES(?,?,?,?,?,?,?,?)',[$tenant['id'],$branch['id'],$name,$capacity,$group['name']??'Salón',$type,$gid?:null,$service]);$id=(int)$app->db->lastInsertId();}
        audit($app,(int)$tenant['id'],(int)$user['id'],'pos_table_config','restaurant_table',$id);
    }
}
function pos_table_form_148(array $groups,?array $table=null):void {
    echo '<form method="post" class="p148-table-form">'.csrf_field().pos_hidden_148('action','save_table').pos_hidden_148('table_id',$table['id']??0).'<label>Nombre<input class="input" name="name" required maxlength="80" value="'.e($table['name']??'').'"></label><label>Tipo de espacio<select class="input" name="space_type"><option value="table">Mesa</option><option value="bar" '.(($table['space_type']??'')==='bar'?'selected':'').'>Barra · cuentas por comensal</option></select></label><label>Capacidad<input class="input" type="number" name="capacity" min="1" max="999" value="'.(int)($table['capacity']??4).'"></label><label>Grupo<select class="input" name="group_id" data-table-group><option value="0" data-service="0">Sin grupo</option>';
    foreach($groups as $g)echo '<option value="'.(int)$g['id'].'" data-service="'.(int)$g['service_enabled'].'" '.((int)($table['group_id']??0)===(int)$g['id']?'selected':'').'>'.e($g['name']).'</option>';
    echo '</select></label><label><input type="checkbox" name="service_enabled" '.(!empty($table['service_enabled'])?'checked':'').'> Aplicar cargo del 10 %</label><small>El grupo propone el valor inicial. Puedes cambiarlo aquí. Solo afecta cuentas nuevas.</small><button class="btn">Guardar '.($table?'cambios':'mesa / barra').'</button></form>';
}
function pos_launch_148(App $app,array $user,array $tenant):void {
    $branch=pos_access_148($app,$user,$tenant);if(function_exists('r2_active')&&r2_active($app)&&!is_post()){if(!empty($_GET['history'])){r2_sales_history($app,$user,$tenant);return;}if(($tenant['catalog_label']??'')==='Menú'&&r2_setting($app,(int)$tenant['id'],'table_view')==='graphic'&&empty($_GET['basic'])&&empty($_GET['screen'])&&empty($_GET['table'])){r2_floor($app,$user,$tenant);return;}}$menu=($tenant['catalog_label']??'Catálogo')==='Menú';$screen=(string)($_GET['screen']??'');$ticketId=(int)($_GET['ticket']??0);
    if($ticketId&&$screen==='checkout'){pos_collect_148($app,$user,$tenant,[$ticketId]);return;}
    if($ticketId&&$screen==='order'){pos_workspace_148($app,$user,$tenant,$ticketId);return;}
    if(is_post()){verify_csrf();try{$action=(string)($_POST['action']??'');if(in_array($action,['save_table','save_group'],true)){if(!$menu)throw new RuntimeException('Mesas requiere modo Menú.');pos_table_config_148($app,$tenant,$user,$branch);flash('success','Configuración guardada. Las cuentas abiertas mantienen su cargo original.');}
        elseif(in_array($action,['table','quick','express','catalog'],true)){if(!$menu&&$action!=='catalog')throw new RuntimeException('Tipo de venta no disponible.');$id=pos_open_148($app,$tenant,$user,$branch,$action,$action==='table'?(int)$_POST['table_id']:null);redirect(url('admin/ventas?screen=order&ticket='.$id));}
        else throw new RuntimeException('Acción inválida.');
    }catch(Throwable $e){flash('error',$e->getMessage());}redirect(url('admin/ventas'.($screen==='express'?'?screen=express':'')));}
    admin_shell_start($menu?'Ventas · Mesas, venta rápida y Express':'Ventas · Caja',$user,$tenant,'ventas');pos_assets_148();
    echo '<div class="p148-toolbar"><span>Sucursal: '.e($branch['name']).'</span>';
    foreach($menu?['quick'=>'Venta rápida']:['catalog'=>'Nueva venta'] as $type=>$label)echo '<form method="post">'.csrf_field().pos_hidden_148('action',$type).pos_hidden_148('open_key',bin2hex(random_bytes(16))).'<button class="btn">'.$label.'</button></form>';
    if($menu)echo '<a class="btn" href="'.url('admin/ventas?screen=express').'">Express</a><a class="btn btn-light" href="'.url('admin/ventas').'">Mesas</a><a class="btn btn-light" href="'.url('admin/cobro').'">Cobro</a><a class="btn btn-light" href="'.url('admin/express-pendientes').'">Express pendientes</a>';echo '</div>';
    if($screen==='express'&&$menu){echo '<section class="card admin-card"><h2>Iniciar pedido Express</h2><p>Selecciona o crea el cliente antes de cargar productos.</p><form method="post">'.csrf_field().pos_hidden_148('action','express').pos_hidden_148('open_key',bin2hex(random_bytes(16)));pos_customers_ui_148($app,$tenant);echo '<label>Dirección y señas de entrega<textarea class="input" name="delivery_address" maxlength="1000" required></textarea></label><button class="btn">Guardar cliente y comenzar Express</button></form></section>';admin_shell_end();return;}
    $tableId=(int)($_GET['table']??0);
    if($menu&&$tableId){$table=$app->one('SELECT * FROM restaurant_tables WHERE id=? AND tenant_id=? AND branch_id=? AND is_active=1',[$tableId,$tenant['id'],$branch['id']]);if(!$table){echo '<p>Mesa no disponible.</p>';admin_shell_end();return;}
        $accounts=$app->all('SELECT t.*,u.name customer_name FROM pos_tickets t LEFT JOIN users u ON u.id=t.customer_id WHERE t.table_id=? AND t.tenant_id=? AND t.status NOT IN ("paid","void","suspended") ORDER BY t.created_at',[$tableId,$tenant['id']]);echo '<h2>'.e($table['name']).'</h2><div class="p148-account-grid">';foreach($accounts as $t)pos_account_card_148($t);echo '</div>';
        if($table['space_type']==='bar'||!$accounts)echo '<form method="post" class="card admin-card">'.csrf_field().pos_hidden_148('action','table').pos_hidden_148('table_id',$tableId).pos_hidden_148('open_key',bin2hex(random_bytes(16))).'<h3>'.($table['space_type']==='bar'?'Nuevo comensal':'Abrir cuenta').'</h3>'.($table['space_type']==='bar'?'<label>Nombre del comensal<input class="input" name="diner_name" maxlength="150" required placeholder="Ejemplo: Ana"></label>':'').'<p>Cargo del 10 %: '.($table['service_enabled']?'activado':'desactivado').'</p><button class="btn">Crear cuenta y cargar pedido</button></form>';admin_shell_end();return;
    }
    if($menu){$groups=$app->all('SELECT * FROM pos_table_groups WHERE tenant_id=? AND branch_id=? ORDER BY name',[$tenant['id'],$branch['id']]);$tables=$app->all('SELECT r.*,(SELECT COUNT(*) FROM pos_tickets t WHERE t.table_id=r.id AND t.tenant_id=r.tenant_id AND t.status NOT IN ("paid","void")) open_count FROM restaurant_tables r WHERE tenant_id=? AND branch_id=? AND is_active=1 ORDER BY area,sort_order,name',[$tenant['id'],$branch['id']]);$busy=count(array_filter($tables,fn($t)=>(int)$t['open_count']>0));
        echo '<section class="p148-room-heading"><div><h2>Mesas y barra</h2><p>Selecciona un espacio para abrir o continuar una cuenta.</p></div><div class="p148-room-summary"><span class="p148-status is-free">'.(count($tables)-$busy).' disponibles</span><span class="p148-status is-busy">'.$busy.' ocupadas</span></div></section><div class="p148-table-grid">';
        foreach($tables as $t)pos_table_card_148($t,$groups,$user);
        echo '</div>';
        if(in_array($user['role'],['tenant_admin','manager'],true)){echo '<details class="card admin-card"><summary>Crear mesa o barra</summary>';pos_table_form_148($groups);echo '</details><details class="card admin-card"><summary>Crear o configurar grupo</summary><form method="post">'.csrf_field().pos_hidden_148('action','save_group').'<label>Nombre del grupo<input class="input" name="name" maxlength="80" required></label><label><input type="checkbox" name="service_enabled"> Proponer cargo del 10 % en mesas nuevas</label><button class="btn">Guardar grupo</button><small>Usa el mismo nombre para cambiar el valor inicial de un grupo existente.</small></form></details>';}
    }
    echo '<h2>Cuentas abiertas y suspendidas</h2><div class="p148-account-grid">';foreach($app->all('SELECT t.*,u.name customer_name FROM pos_tickets t LEFT JOIN users u ON u.id=t.customer_id WHERE t.tenant_id=? AND t.branch_id=? AND t.table_id IS NULL AND t.status NOT IN ("paid","void","suspended") ORDER BY t.created_at DESC',[$tenant['id'],$branch['id']]) as $t)pos_account_card_148($t);echo '</div>';admin_shell_end();
}
function pos_table_card_148(array $t,array $groups,array $user):void {
    $occupied=(int)$t['open_count']>0;$bar=$t['space_type']==='bar';$count=(int)$t['open_count'];
    echo '<article class="p148-table-card '.($occupied?'is-busy':'is-free').'"><div class="p148-table-top"><span class="p148-space-type">'.($bar?'Barra':'Mesa').'</span><span class="p148-status '.($occupied?'is-busy':'is-free').'">'.($occupied?'Ocupada':'Disponible').'</span></div><h3>'.e($t['name']).'</h3><p class="p148-table-area">'.e($t['area']).'</p><div class="p148-table-facts"><span>'.($count?($count.' '.($count===1?'cuenta abierta':'cuentas abiertas')):'Sin cuentas abiertas').'</span><span>Cargo 10 %: '.($t['service_enabled']?'Sí':'No').'</span></div><a class="btn p148-table-enter '.($occupied?'':'btn-light').'" href="'.url('admin/ventas?table='.(int)$t['id']).'">'.($occupied?'Continuar cuenta':'Abrir '.($bar?'barra':'mesa')).'<span aria-hidden="true">→</span></a>';
    if(in_array($user['role'],['tenant_admin','manager'],true)){echo '<details class="p148-table-settings"><summary>Configurar '.($bar?'barra':'mesa').'</summary>';pos_table_form_148($groups,$t);echo '</details>';}
    echo '</article>';
}
function pos_account_card_148(array $t,bool $collect=false):void {
    $type=['express'=>'Express','quick'=>'Venta rápida','catalog'=>'Venta de catálogo','table'=>'Mesa'];echo '<article class="card admin-card"><span class="tag">'.e($type[$t['sale_type']]??$t['sale_type']).'</span><h3>'.e(($t['table_name']??'').' '.($t['display_label']?:$t['ticket_number'])).'</h3><p>'.e($t['customer_name']??'').'</p><strong>Pendiente '.money(max(0,(float)$t['total']-(float)$t['paid_total'])).'</strong><p>'.e($t['created_at']).'</p><a class="btn" href="'.url(($collect?'admin/cobro?ticket=':'admin/ventas?screen=order&ticket=').(int)$t['id']).'">'.($collect?'Seleccionar cuenta':'Abrir pedido').'</a></article>';
}
function pos_workspace_148(App $app,array $user,array $tenant,int $id):void {
    $branch=pos_access_148($app,$user,$tenant);$ticket=pos_ticket_get_148($app,$tenant,$branch,$id);if(function_exists('r2_active')&&r2_active($app)&&!in_array($ticket['status'],['paid','void'],true)){if(is_post()&&in_array($_POST['action']??'',['move_table','join_tables'],true))r2_ticket_move($app,$user,$tenant,$branch,$id);if(in_array($ticket['sale_type'],['quick','catalog'],true)){r2_quick_workspace($app,$user,$tenant,$id);return;}}if(is_post())pos_edit_148($app,$tenant,$user,$branch,$id);
    if(in_array($ticket['status'],['paid','void'],true)){admin_shell_start('Cuenta cerrada',$user,$tenant,'ventas');echo '<p>Esta cuenta está cerrada.</p><a class="btn" href="'.url('admin/ventas').'">Volver a ventas</a>';admin_shell_end();return;}
    $items=pos_items_v141($app,$id);$categoryId=(int)($_GET['category']??0);$search=trim((string)($_GET['q']??''));$hasProductFilter=$categoryId>0||$search!=='';$products=$hasProductFilter?pos_catalog_products_v141($app,$tenant,$branch):[];$categories=$app->all('SELECT id,name FROM categories WHERE tenant_id=? AND is_active=1 ORDER BY sort_order,name',[$tenant['id']]);$label=['express'=>'Express','quick'=>'Venta rápida','catalog'=>'Caja de cobro','table'=>'Cargar a mesa'];$menu=($tenant['catalog_label']??'Catálogo')==='Menú';$url=url('admin/ventas?screen=order&ticket='.$id);$meta=csrf_field().pos_hidden_148('revision',$ticket['revision']);
    admin_shell_start($label[$ticket['sale_type']].(!empty($ticket['display_label'])?' · '.$ticket['display_label']:''),$user,$tenant,'ventas');pos_assets_148();
    echo '<div class="p148-toolbar"><a class="btn btn-light" href="'.url('admin/ventas').'">← Ventas</a><span>'.e($branch['name']).'</span><span>Cajero: '.e($user['name']).'</span><span>'.e($ticket['ticket_number']).'</span></div><div class="p148-sale"><aside class="card p148-categories"><h3>Categorías</h3><p class="p148-filter-hint">Selecciona una categoría para ver sus productos.</p>';
    foreach($categories as $c)echo '<a class="btn '.($categoryId===(int)$c['id']?'':'btn-light').'" href="'.e($url.'&category='.(int)$c['id']).'">'.e($c['name']).'</a>';
    echo '<hr><button class="btn btn-light" type="button" data-focus-search>Buscar / escanear código</button></aside><section class="card p148-products"><form method="get" class="p148-search">'.pos_hidden_148('screen','order').pos_hidden_148('ticket',$id).'<input class="input" name="q" value="'.e($_GET['q']??'').'" placeholder="Buscar por nombre o código" aria-label="Buscar producto"><button class="btn">Buscar</button></form><div class="p148-product-grid">';
    foreach($products as $p)echo '<form class="pos-product p148-product" method="post" action="'.e($url).'">'.$meta.pos_hidden_148('action','add_item').pos_hidden_148('product_id',$p['id']).pos_hidden_148('quantity',max(1,(float)$p['min_qty'])).($p['image']?'<img src="'.e($p['image']).'" loading="lazy" alt="">':'<div class="p148-placeholder">▦</div>').'<strong>'.e($p['name']).'</strong><small>'.e($p['sku']??'').'</small><b>'.money((float)($p['sale_price']?:$p['price'])).'</b><button class="pos-add" aria-label="Agregar '.e($p['name']).'">+</button></form>';
    if(!$hasProductFilter)echo '<div class="p148-product-empty"><span class="p148-empty-symbol" aria-hidden="true">＋</span><strong>¿Qué vamos a agregar?</strong><span>Selecciona una categoría o busca por nombre, código o descripción.</span></div>';elseif(!$products)echo '<p>No hay productos con ese filtro.</p>';echo '</div></section><aside class="card p148-cart"><div class="p148-cart-heading"><h2>Pedido actual</h2><span>Revisa antes de cobrar</span></div><div class="p148-cart-lines">';
    foreach($items as $i){echo '<article class="p148-cart-line"><div><strong>'.e($i['product_name']).'</strong><small>'.e($i['notes']??'').'</small>';foreach(json_decode((string)$i['options_json'],true)?:[] as $o)echo '<small>'.e(($o['group_name']??'').': '.$o['name']).'</small>';echo '<small>'.e(['pending'=>'Sin enviar','sent'=>'Enviado a cocina','preparing'=>'Preparando','ready'=>'Listo'][$i['kitchen_status']]??$i['kitchen_status']).'</small></div><form method="post" action="'.e($url).'">'.$meta.pos_hidden_148('action','quantity').pos_hidden_148('item_id',$i['id']).'<input class="input" type="number" name="quantity" step="0.001" min="0" value="'.e($i['quantity']).'" aria-label="Cantidad"><button class="btn btn-light" '.($i['kitchen_status']!=='pending'?'disabled':'').'>Actualizar</button></form><b>'.money((float)$i['line_subtotal']+(float)$i['line_tax']).'</b></article>';}
    if(!$items)echo '<p class="p148-cart-empty">Tu pedido está vacío.<br>Agrega productos desde una categoría o el buscador.</p>';echo '</div><div class="p148-totals"><p>Subtotal <b>'.money((float)$ticket['subtotal']).'</b></p><p>Impuestos <b>'.money((float)$ticket['tax_total']).'</b></p><p>Servicio <b>'.money((float)$ticket['service_total']).'</b></p><p>Descuento <b>'.money((float)$ticket['discount_total']).'</b></p><p class="p148-total">Total <b>'.money((float)$ticket['total']).'</b></p><p>Saldo pendiente <b>'.money(max(0,(float)$ticket['total']-(float)$ticket['paid_total'])).'</b></p></div>';
    if($menu)echo '<form class="p148-send-action" method="post" action="'.e($url).'">'.$meta.pos_hidden_148('action','send_kitchen').'<button class="btn btn-light btn-block">Enviar a cocina</button></form>';
    if(role_can($user['role'],'cobro'))echo '<a class="btn btn-accent btn-block" href="'.url('admin/ventas?screen=checkout&ticket='.$id).'">Cobrar</a>';
    echo '<div class="p148-toolbar">';foreach(['save'=>'Guardar','suspend'=>'Suspender','void'=>'Cancelar'] as $a=>$l)echo '<form method="post" action="'.e($url).'" '.($a==='void'?'data-confirm="¿Anular la cuenta y liberar su inventario?"':'').'>'.$meta.pos_hidden_148('action',$a).'<button class="btn btn-light">'.$l.'</button></form>';echo '</div><details><summary>Aplicar descuento</summary><form method="post" action="'.e($url).'">'.$meta.pos_hidden_148('action','discount').'<label>Monto de descuento<input class="input" name="discount" type="number" min="0" step="0.01" value="'.e($ticket['discount_total']).'"></label><button class="btn">Guardar descuento</button></form></details></aside><section class="card p148-billing"><h2>Datos de facturación</h2><form method="post" action="'.e($url).'">'.$meta.pos_hidden_148('action','customer');pos_customers_ui_148($app,$tenant,(int)$ticket['customer_id']);echo '<button class="btn btn-light">Guardar cliente</button></form><p>Efectivo · Tarjeta · SINPE · Crédito autorizado. Elige los montos y consulta el vuelto al pulsar Cobrar.</p>'.($ticket['sale_type']==='express'?'<p><strong>Entrega:</strong> '.e($ticket['delivery_address']).'</p>':'').'</section></div>';if(function_exists('r2_active')&&r2_active($app)&&$menu){echo '<details class="card admin-card"><summary>Mover cuenta / unir mesas</summary><form method="post">'.$meta.'<label>Mesa destino'.r2_select('target_table',$app->all('SELECT id,name FROM restaurant_tables WHERE tenant_id=? AND branch_id=? AND is_active=1 ORDER BY name',[$tenant['id'],$branch['id']])).'</label><button class="btn btn-light" name="action" value="move_table">Mover esta cuenta</button><button class="btn btn-light" name="action" value="join_tables">Pasar todas las cuentas de esta mesa</button></form></details>';}admin_shell_end();
}
function pos_collect_148(App $app,array $user,array $tenant,array $preset=[]):void {
    $branch=pos_access_148($app,$user,$tenant,true);$ids=$preset?:array_values(array_unique(array_filter(array_map('intval',(array)($_GET['tickets']??[($_GET['ticket']??0)])))));sort($ids);if(count($ids)>20)throw new RuntimeException('Selecciona hasta 20 cuentas de la misma mesa.');
    if(is_post()){try{$sale=pos_settle_148($app,$tenant,$user,$branch,$ids);redirect(url('admin/recibo?venta='.$sale.'&auto=1'));}catch(Throwable $e){flash('error',$e->getMessage());}}
    admin_shell_start('Cobro · Cuentas abiertas',$user,$tenant,'cobro');pos_assets_148();echo '<div class="p148-toolbar"><a class="btn btn-light" href="'.url('admin/cobro').'">Cuentas abiertas</a><a class="btn btn-light" href="'.url('admin/ventas').'">Ventas</a><span>'.e($branch['name']).'</span></div>';
    if(!$ids){$rows=$app->all('SELECT t.*,r.name table_name,u.name customer_name FROM pos_tickets t LEFT JOIN restaurant_tables r ON r.id=t.table_id LEFT JOIN users u ON u.id=t.customer_id WHERE t.tenant_id=? AND t.branch_id=? AND t.status NOT IN ("paid","void","suspended") AND t.total>t.paid_total ORDER BY t.table_id,t.created_at',[$tenant['id'],$branch['id']]);echo '<div class="p148-account-grid">';foreach($rows as $t)pos_account_card_148($t,true);echo '</div>';if(!$rows)echo '<p>No hay cuentas pendientes.</p>';echo '<details class="card admin-card"><summary>Una persona paga varias cuentas de la misma mesa / barra</summary><form method="get">';foreach($rows as $t)if($t['table_id'])echo '<label class="p148-check"><input type="checkbox" name="tickets[]" value="'.(int)$t['id'].'">'.e($t['table_name'].' · '.($t['display_label']?:$t['ticket_number'])).'</label>';echo '<button class="btn">Elegir productos de estas cuentas</button></form></details>';admin_shell_end();return;}
    $tickets=[];$items=[];foreach($ids as $id){$t=pos_ticket_get_148($app,$tenant,$branch,$id);$tickets[]=$t;$items=array_merge($items,pos_items_v141($app,$id));}
    try{$full=pos_quote_148($tickets,$items,'all',[],2);}catch(Throwable $e){if(count($tickets)===1&&(int)$tickets[0]['split_parts_paid']>0)$full=pos_quote_148($tickets,$items,'equal',[],(int)$tickets[0]['split_parts']);else{echo '<p>'.e($e->getMessage()).'</p>';admin_shell_end();return;}}
    $json=json_encode(['tickets'=>$tickets,'items'=>$items],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE);
    echo '<form method="post" class="p148-payment" data-collect>'.csrf_field().pos_hidden_148('payment_key',bin2hex(random_bytes(16)));foreach($tickets as $t)echo pos_hidden_148('revisions['.$t['id'].']',$t['revision']);
    echo '<div class="p148-split"><section class="card admin-card"><h2>Consumo pendiente</h2><p>Selecciona cantidades para cobrarlas a este cliente.</p><button type="button" class="btn btn-light" data-select-all>Pasar todo →</button><div class="p148-pending">';
    foreach($items as $i){$q=max(0,round((float)$i['quantity']-(float)$i['paid_quantity'],3));if($q<=0)continue;$name='';foreach($tickets as $t)if((int)$t['id']===(int)$i['ticket_id'])$name=$t['display_label']?:$t['ticket_number'];echo '<article class="p148-pick" data-pick="'.(int)$i['id'].'">'.($i['image']?'<img src="'.e($i['image']).'" alt="">':'').'<div><strong>'.e($i['product_name']).'</strong><small>'.e($name).'</small><small>Pendiente: '.e((string)$q).'</small></div><label>A cobrar<input class="input" name="qty['.(int)$i['id'].']" type="number" min="0" max="'.e((string)$q).'" step="0.001" value="'.e((string)$q).'" data-pick-qty="'.(int)$i['id'].'"></label></article>';}
    echo '</div></section><section class="card admin-card"><h2>Productos a cobrar</h2><div data-selected-lines></div><fieldset><legend>Cómo dividir</legend>';foreach(['all'=>'Cuenta completa','items'=>'Elegir productos','equal'=>'Partes iguales'] as $m=>$label)echo '<label class="p148-check"><input type="radio" name="split_mode" value="'.$m.'" '.($m===((int)$tickets[0]['split_parts_paid']?'equal':'all')?'checked':'').' '.($m==='equal'&&count($tickets)>1?'disabled':'').'>'.$label.'</label>';echo '<label>Partes<select class="input" name="parts">';foreach(range(2,20) as $p)echo '<option '.((int)$tickets[0]['split_parts']===$p?'selected':'').'>'.$p.'</option>';echo '</select></label></fieldset><div class="p148-totals" data-quote></div><p data-payment-message role="status"></p></section></div><section class="card admin-card"><h2>Cliente y pago</h2>';pos_customers_ui_148($app,$tenant,(int)$tickets[0]['customer_id']);echo '<div class="p148-methods">';foreach(['cash'=>'Efectivo recibido','card'=>'Tarjeta','sinpe'=>'SINPE','credit'=>'Crédito autorizado'] as $k=>$v)echo '<label>'.$v.'<input class="input" type="number" name="'.$k.'" min="0" step="0.01" value="0"></label>';echo '</div><label>Referencia de pago (opcional)<input class="input" name="payment_reference" maxlength="150"></label><p>Puedes combinar métodos. El efectivo admite un monto superior y calcula el vuelto.</p><button class="btn btn-accent btn-block" data-confirm-payment>Confirmar cobro</button></section></form><script type="application/json" data-collect-data>'.$json.'</script>';admin_shell_end();
}
function pos_express_pending_148(App $app,array $user,array $tenant):void {
    $branch=pos_access_148($app,$user,$tenant);
    if(is_post()){verify_csrf();try{$id=(int)($_POST['ticket_id']??0);$app->db->beginTransaction();$t=pos_ticket_get_148($app,$tenant,$branch,$id,true);if($t['sale_type']!=='express'||$t['status']==='void')throw new RuntimeException('Express no disponible.');$state=(string)($_POST['state']??'');$next=['pending'=>'preparing','preparing'=>'ready','ready'=>'in_transit','in_transit'=>'delivered'];if(($next[$t['fulfillment_state']]??'')!==$state)throw new RuntimeException('El estado cambió. Recarga la lista.');if($state==='delivered'&&$t['status']!=='paid')throw new RuntimeException('Registra el cobro (o crédito autorizado) antes de confirmar la entrega.');$app->exec('UPDATE pos_tickets SET fulfillment_state=?,revision=revision+1 WHERE id=?',[$state,$id]);audit($app,(int)$tenant['id'],(int)$user['id'],'pos_express_'.$state,'pos_ticket',$id);$app->db->commit();}catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();flash('error',$e->getMessage());}redirect(url('admin/express-pendientes'));}
    $rows=$app->all('SELECT t.*,u.name customer_name,u.phone FROM pos_tickets t LEFT JOIN users u ON u.id=t.customer_id WHERE t.tenant_id=? AND t.branch_id=? AND t.sale_type="express" AND t.status<>"void" AND t.fulfillment_state<>"delivered" ORDER BY t.created_at',[$tenant['id'],$branch['id']]);admin_shell_start('Express pendientes de entrega',$user,$tenant,'express-pendientes');pos_assets_148();echo '<p>Seguimiento de Express creados en caja. El cobro y la entrega se registran por separado.</p><div class="p148-account-grid">';$states=['pending'=>['preparing','Iniciar preparación'],'preparing'=>['ready','Marcar listo'],'ready'=>['in_transit','Marcar en camino'],'in_transit'=>['delivered','Confirmar entrega']];foreach($rows as $t){echo '<article class="card admin-card"><h3>'.e($t['ticket_number'].' · '.$t['customer_name']).'</h3><p>'.e($t['phone']).'</p><p>'.e($t['delivery_address']).'</p><p>'.e(['pending'=>'Pendiente','preparing'=>'Preparando','ready'=>'Listo','in_transit'=>'En camino'][$t['fulfillment_state']]??$t['fulfillment_state']).' · '.($t['status']==='paid'?'Pagado':'Pendiente de cobro').'</p><form method="post">'.csrf_field().pos_hidden_148('ticket_id',$t['id']).pos_hidden_148('state',$states[$t['fulfillment_state']][0]).'<button class="btn">'.$states[$t['fulfillment_state']][1].'</button></form><a href="'.url('admin/recibo?comanda='.$t['id']).'">Reimprimir comanda</a>';if($t['status']!=='paid')echo '<p><a href="'.url('admin/ventas?screen=order&ticket='.$t['id']).'">Abrir pedido / cobrar</a></p>';echo '</article>';}echo '</div>';if(!$rows)echo '<p>No hay Express pendientes.</p>';admin_shell_end();
}

function pos_charge_comandas_148(App $app,array $tenant,array $sale):void {
    foreach(json_decode((string)($sale['kitchen_jobs_json']??''),true)?:[] as $job){
        $t=$app->one('SELECT t.*,r.name table_name FROM pos_tickets t LEFT JOIN restaurant_tables r ON r.id=t.table_id WHERE t.id=? AND t.tenant_id=?',[(int)$job['ticket_id'],$tenant['id']]);if(!$t)continue;$branch=$app->one('SELECT * FROM branches WHERE id=? AND tenant_id=?',[$t['branch_id'],$tenant['id']]);if(!$branch)continue;
        $groups=[];foreach($app->all('SELECT i.*,COALESCE(c.printer_target,"kitchen") printer_target FROM pos_ticket_items i LEFT JOIN products p ON p.id=i.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE i.ticket_id=?',[$t['id']]) as $i)if(in_array((int)$i['id'],array_map('intval',$job['items']),true))$groups[$i['printer_target']][]=$i;
        foreach($groups as $target=>$items){$profile=printer_profile_148($branch,$target);$lines=[$tenant['name'],'COMANDA · '.strtoupper($target),$t['ticket_number'],trim(($t['table_name']??$t['sale_type']).' · '.($t['display_label']??''))];foreach($items as $i){$lines[]=$i['quantity'].' x '.$i['product_name'];foreach(json_decode((string)$i['options_json'],true)?:[] as $o)$lines[]='  '.($o['group_name']??'').': '.$o['name'];if($i['notes'])$lines[]='  Nota: '.$i['notes'];}$text=implode("\n",$lines);
            $payload=['id'=>'tenant-'.$tenant['id'].'-sale-'.$sale['id'].'-kitchen-'.$t['id'].'-'.$target,'document'=>$target,'width'=>$profile['width'],'printer'=>$profile,'text'=>$text];
            echo '<section class="card admin-card print-result-card"><h2>Comanda · '.e($target).'</h2><pre class="receipt-preview">'.e($text).'</pre><p data-print-status></p><button class="btn" data-bridge-print>Enviar comanda a Print</button><script type="application/json" data-print-payload>'.json_encode($payload,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><div data-print-job data-token="'.e($sale['receipt_bridge_token']??'').'" data-auto="'.(!empty($_GET['auto'])&&!empty($profile['autoprint'])?'1':'0').'"></div></section>';
        }
    }
}
