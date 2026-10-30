<?php
declare(strict_types=1);

function r20_receipt_lines(App $app,array $tenant,array $branch,array $sale):array {
    $lines=[$tenant['name'],$branch['name']];if(!empty($tenant['tax_id']))$lines[]='Identificación: '.$tenant['tax_id'];
    $lines[]='RECIBO · '.$sale['sale_number'];$lines[]=date('d/m/Y H:i',strtotime($sale['created_at']));$lines[]='Cliente: '.$sale['customer_name'];$lines[]=str_repeat('-',40);
    foreach($app->all('SELECT * FROM sale_items WHERE sale_id=? ORDER BY id',[$sale['id']]) as $item){$lines[]=$item['quantity'].' × '.$item['product_name'].'  '.money((float)$item['line_subtotal']+(float)$item['line_tax']);foreach(json_decode((string)$item['options_json'],true)?:[] as $option)$lines[]='  '.$option['name'];if($item['notes'])$lines[]='  Nota: '.$item['notes'];}
    $lines[]=str_repeat('-',40);foreach(['subtotal'=>'Subtotal','tax_total'=>'Impuestos','service_total'=>'Servicio','discount_total'=>'Descuento','total'=>'TOTAL','change_due'=>'Vuelto'] as $field=>$label)$lines[]=$label.'  '.money((float)$sale[$field]);$lines[]='Pago: '.$sale['payment_method'];$lines[]='Gracias por su compra.';return $lines;
}
// Self-contained PDF with real page dimensions, wrapping and pagination; no remote service.
// Standard PDF fonts use WinAnsi; CRC is the unambiguous representation of ₡ in this file.
function r20_pdf(array $source,bool $letter,int $width=80):string {
    $width=in_array($width,[58,80],true)?$width:80;$w=$letter?612:$width/25.4*72;$h=$letter?792:842;$margin=$letter?42:10;$font=$letter?11:8;$leading=$letter?16:11;$columns=max(20,(int)floor(($w-2*$margin)/($font*.6)));$lines=[];
    foreach($source as $line){$encoded=iconv('UTF-8','Windows-1252//TRANSLIT',str_replace(['₡',"\r",'…'],['CRC ', '', '...'],(string)$line));$encoded=preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/','',$encoded?:'');foreach(explode("\n",wordwrap($encoded,$columns,"\n",true)) as $wrapped)$lines[]=$wrapped;}
    $perPage=(int)floor(($h-2*$margin)/$leading);$pages=array_chunk($lines?:[''],$perPage);$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>''];$kids=[];
    foreach($pages as $index=>$page){$pid=4+$index*2;$cid=$pid+1;$kids[]=$pid.' 0 R';$pageHeight=$letter?$h:min($h,max(100,count($page)*$leading+2*$margin));$stream="BT\n/F1 $font Tf\n$leading TL\n1 0 0 1 $margin ".($pageHeight-$margin-$font)." Tm\n";foreach($page as $line)$stream.='('.str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line).") Tj\nT*\n";$stream.="ET\n";
        $objects[$pid]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.round($w,4).' '.round($pageHeight,4).'] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$cid.' 0 R >>';$objects[$cid]='<< /Length '.strlen($stream).">>\nstream\n".$stream.'endstream';
    }
    $objects[2]='<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>';ksort($objects);$pdf="%PDF-1.4\n%FISITAAP\n";$offsets=[0];foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";foreach($offsets as $id=>$offset)if($id)$pdf.=sprintf("%010d 00000 n \n",$offset);return $pdf.'trailer << /Size '.(count($objects)+1).' /Root 1 0 R >>' ."\nstartxref\n".$xref."\n%%EOF\n";
}
function r20_receipt_page(App $app):void {
    [$user,$tenant]=qa180_business($app,'ventas');$branch=physical_branch_v14($app,$tenant);$sale=$app->one('SELECT * FROM sales WHERE id=? AND tenant_id=? AND branch_id=? AND status="completed"',[(int)($_GET['venta']??0),$tenant['id'],$branch['id']]);if(!$sale){http_response_code(404);simple_error('Comprobante no disponible','Revisa la venta y la sucursal.');return;}
    $output=$sale['receipt_output_r20'];$letter=$output==='letter_pdf'||($output==='printer'&&(int)$sale['receipt_width_r20']===216);$width=(int)$sale['receipt_width_r20'];$lines=r20_receipt_lines($app,$tenant,$branch,$sale);header('Cache-Control: private, no-store');
    if(isset($_GET['pdf'])){header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="Recibo-'.(int)$sale['id'].($letter?'-carta':'-recibo').'.pdf"');echo r20_pdf($lines,$letter,$width);return;}
    admin_shell_start('Comprobante',$user,$tenant,'ventas');$href=url('admin/comprobante-r20?venta='.(int)$sale['id'].'&pdf=1');echo '<section class="card admin-card r20-receipt-result"><h2>Venta registrada</h2><p>Comprobante '.($letter?'en hoja carta':'tipo recibo').' · '.e($sale['sale_number']).'</p><a class="btn primary" href="'.e($href).'" download data-r20-pdf-download>Descargar PDF</a> <a class="btn" href="'.e(url('admin/ventas')).'">Continuar vendiendo</a><p>Si la descarga no comienza automáticamente, usa Descargar PDF. La venta ya está cobrada.</p><pre>'.e(implode("\n",$lines)).'</pre></section>';admin_shell_end();
}
