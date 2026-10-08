<?php
declare(strict_types=1);

function r3_print_points(App $app,array $t): array
{
    $points=['receipt'=>'Caja / recibo','kitchen'=>'Cocina','bar'=>'Barra','dispatch'=>'Despacho','copy'=>'Copia'];
    $extra=json_decode(r2_setting($app,(int)$t['id'],'printer_zones','{}'),true)?:[];
    foreach($extra as $key=>$label)if(preg_match('/^zone_[a-f0-9]{12}$/',(string)$key))$points[$key]=mb_substr((string)$label,0,80);
    return $points;
}
function r3_print_zone_save(App $app,array $t): never
{
    verify_csrf();$name=mb_substr(trim((string)($_POST['zone_name']??'')),0,80);
    if($name===''){flash('error','Escribe el nombre de la zona.');redirect(url('admin/impresion'));}
    $zones=json_decode(r2_setting($app,(int)$t['id'],'printer_zones','{}'),true)?:[];
    if(count($zones)>=30){flash('error','Ya hay 30 zonas adicionales.');redirect(url('admin/impresion'));}
    if(in_array($name,$zones,true)){flash('error','Esta zona ya existe.');redirect(url('admin/impresion'));}
    $zones['zone_'.substr(hash('sha256',$name),0,12)]=$name;r2_set($app,(int)$t['id'],'printer_zones',json_encode($zones,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    flash('success','Zona creada. Asigna su impresora en cada sucursal y su destino en las categorías.');redirect(url('admin/impresion'));
}

function r3_prebill(App $app,array $u,array $t,int $id): void
{
    $branch=pos_access_148($app,$u,$t);$ticket=pos_ticket_get_148($app,$t,$branch,$id);$profile=printer_profile_148($branch);
    $lines=[$t['name'],$branch['name'],'PRECUENTA · NO ES COMPROBANTE DE PAGO',$ticket['ticket_number'],date('d/m/Y H:i'),'--------------------------------'];
    foreach(pos_items_v141($app,$id) as $item){$lines[]=$item['quantity'].' × '.$item['product_name'].' '.money((float)$item['line_subtotal']+(float)$item['line_tax']);foreach(json_decode((string)$item['options_json'],true)?:[] as $o)$lines[]='  '.($o['group_name']??$o['group']??'').': '.$o['name'];if($item['notes'])$lines[]='  Nota: '.$item['notes'];}
    $lines[]='--------------------------------';foreach(['subtotal'=>'Subtotal','tax_total'=>'Impuestos','service_total'=>'Servicio','discount_total'=>'Descuento','total'=>'TOTAL','paid_total'=>'Pagado'] as $key=>$label)$lines[]=$label.' '.money((float)$ticket[$key]);$lines[]='PENDIENTE '.money(max(0,(float)$ticket['total']-(float)$ticket['paid_total']));if($ticket['notes'])$lines[]='Nota: '.$ticket['notes'];
    $payload=['id'=>'prebill-'.$t['id'].'-'.$id.'-'.$ticket['revision'],'document'=>'receipt','width'=>$profile['width'],'printer'=>$profile,'text'=>implode("\n",$lines)."\n\n"];
    layout_start('Precuenta',$t,true);echo '<main class="section container"><section class="card admin-card print-result-card"><div class="receipt-preview" id="thermalReceipt" style="--receipt-width:'.(int)$profile['width'].'mm">';foreach($lines as $line)echo '<div>'.e($line).'</div>';echo '</div><p><button class="btn" type="button" data-bridge-print>Imprimir precuenta</button> <button class="btn btn-light" type="button" onclick="window.print()">Imprimir desde navegador</button> <a class="btn btn-light" href="'.url('admin/mesas').'">Volver al salón</a></p><p data-print-status></p><script type="application/json" data-print-payload>'.r3_json($payload).'</script><div data-print-job data-token="'.e($branch['receipt_bridge_token']??'').'" data-auto="0"></div></section></main>';layout_end();
}
