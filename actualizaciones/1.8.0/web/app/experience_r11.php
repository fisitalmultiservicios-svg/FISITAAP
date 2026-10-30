<?php
declare(strict_types=1);

function r11_quantity_product(array $product):array {
    $unit=mb_strtolower(trim((string)($product['unit_label']??'unidad')));
    $individual=in_array($unit,['','unidad','unidades','ud','uds','u','pieza','piezas','unit','units','paquete','paquetes','caja','cajas','botella','botellas','bolsa','bolsas','lata','latas','docena','docenas','par','pares','porcion','porción','porciones'],true);
    $product['quantity_mode']=$individual?'unit':'bulk';
    if($individual){$product['min_qty']=1;$product['qty_step']=1;$product['decimals']=0;}
    elseif((int)($product['decimals']??0)===0){$product['decimals']=3;if((float)($product['qty_step']??1)===1.0)$product['qty_step']=.001;if((float)($product['min_qty']??1)===1.0)$product['min_qty']=.001;}
    if(!$individual){$product['decimals']=min(3,max(1,(int)$product['decimals']));$quantum=10**(-$product['decimals']);$product['min_qty']=max($quantum,ceil(((float)$product['min_qty']-.000000001)/$quantum)*$quantum);$product['qty_step']=max($quantum,ceil(((float)$product['qty_step']-.000000001)/$quantum)*$quantum);}
    return $product;
}
function r11_quantity(array $product,mixed $value,bool $zero=false):float {
    $p=r11_quantity_product($product);if(!is_scalar($value)||!is_numeric($value))throw new DomainException('Escribe una cantidad válida.');$n=(float)$value;
    if(!is_finite($n)||$n<0||$n>99999)throw new DomainException('Cantidad fuera de rango.');if($zero&&$n===0.0)return 0;
    if($p['quantity_mode']==='unit'&&abs($n-round($n))>.0000001)throw new DomainException('Los productos por unidad solo aceptan cantidades enteras.');
    $precision=min(3,max(0,(int)$p['decimals']));if(abs($n-round($n,$precision))>.0000001)throw new DomainException('La cantidad admite hasta '.$precision.' decimales.');
    $min=(float)$p['min_qty'];$step=(float)$p['qty_step'];if($n<$min||$step<=0||abs(($n-$min)/$step-round(($n-$min)/$step))>.0001)throw new DomainException('Revisa la cantidad mínima y su incremento.');return $n;
}
function r11_catalog_slides(App $app,array $tenant):array {
    $tid=(int)$tenant['id'];$slides=[];
    for($i=1;$i<=3;$i++){$suffix=$i===1?'':'_'.$i;$image=r2_setting($app,$tid,'catalog_intro_image'.$suffix);$title=r2_setting($app,$tid,'catalog_intro_title'.$suffix,$i===1?$tenant['catalog_label']:'');$body=r2_setting($app,$tid,'catalog_intro_text'.$suffix,$i===1?'Elige una categoría y agrega tus productos.':'');if($image!=='')$slides[]=['title'=>$title,'body'=>$body,'image'=>$image];}
    return $slides?:[['title'=>r2_setting($app,$tid,'catalog_intro_title',$tenant['catalog_label']),'body'=>r2_setting($app,$tid,'catalog_intro_text','Elige una categoría y agrega tus productos.'),'image'=>'']];
}
function r11_slider_editor(App $app,array $tenant,array $defaults):void {
    $tid=(int)$tenant['id'];echo '<p>Este slider es el banner que aparece debajo de la sucursal en tu catálogo. Carga hasta tres imágenes; se muestran sin capa de color.</p><label>Mostrar slider<select class="input" name="catalog_intro_mode">';foreach(['custom'=>'Usar estas tres imágenes','auto'=>'Conservar banners anteriores por sucursal','hidden'=>'Ocultar slider'] as $mode=>$text)echo '<option value="'.$mode.'" '.(r2_setting($app,$tid,'catalog_intro_mode','auto')===$mode?'selected':'').'>'.e($text).'</option>';echo '</select></label>'.field('Segundos entre imágenes','catalog_slide_seconds',r2_setting($app,$tid,'catalog_slide_seconds','5'),true,'number','1').'<small>De 2 a 60 segundos. Con una sola imagen no hay cambios automáticos.</small><div class="r11-slider-editor">';
    for($i=1;$i<=3;$i++){$suffix=$i===1?'':'_'.$i;$key='catalog_intro_image'.$suffix;$image=r2_setting($app,$tid,$key,$i===1?($defaults[$key]??''):'');echo '<fieldset><legend>Imagen '.$i.'</legend>'.($image?'<img class="r2-editor-image" src="'.e(public_media_url($image)).'" alt="Imagen '.$i.' actual">':'<p class="muted">Sin imagen cargada</p>').'<label>Seleccionar imagen '.$i.'<input class="input" type="file" name="'.$key.'" accept="image/jpeg,image/png,image/webp"></label>'.field('Título (opcional)','catalog_intro_title'.$suffix,r2_setting($app,$tid,'catalog_intro_title'.$suffix,$i===1?($defaults['catalog_intro_title']??$tenant['catalog_label']):'')).field('Texto (opcional)','catalog_intro_text'.$suffix,r2_setting($app,$tid,'catalog_intro_text'.$suffix,$i===1?($defaults['catalog_intro_text']??''):'' )).'<label><input type="checkbox" name="clear_'.$key.'"> Quitar imagen '.$i.'</label></fieldset>';}
    echo '</div>'.field('Color de títulos y textos del slider','catalog_text_color',r2_setting($app,$tid,'catalog_text_color','#ffffff'),true,'color').'<p>Los cambios se publican al pulsar Guardar diseño. Las imágenes se comprimen automáticamente.</p><a class="btn btn-light" href="'.e(url('admin/contenido-anterior')).'">Banners anteriores por sucursal</a>';
}

function r11_quantity_fields(array $product):string {
    $p=r11_quantity_product($product);$out='<label>Tipo de venta<select class="input" name="quantity_mode" data-quantity-mode><option value="unit" '.($p['quantity_mode']==='unit'?'selected':'').'>Por unidad · cantidades enteras</option><option value="bulk" '.($p['quantity_mode']==='bulk'?'selected':'').'>A granel · admite decimales</option></select></label>';return $out.field('Unidad de medida','unit_label',$product['unit_label']??'unidad',true).'<small>Unidad: 1, 2, 3. A granel: kg, g, litros o metros, según mínimo e incremento.</small>';
}
