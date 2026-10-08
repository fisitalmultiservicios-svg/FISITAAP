<?php
declare(strict_types=1);

function r2_active(App $app): bool { return $app->setting('restructure_r2_active','0') === '1'; }
function r2_setting(App $app,int $tenant,string $key,string $default=''): string {
    $values=$app->cached('r2-settings:'.$tenant,function()use($app,$tenant){
        $out=[];foreach($app->all('SELECT setting_key,value FROM fisitaap_r2_settings WHERE tenant_id=?',[$tenant])as$row)$out[$row['setting_key']]=$row['value'];return $out;
    });
    return (string)($values[$key]??$default);
}
function r2_set(App $app,int $tenant,string $key,string $value): void {
    $app->exec('INSERT INTO fisitaap_r2_settings(tenant_id,setting_key,value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$tenant,$key,$value]);
}
function r2_number(mixed $value,float $min=0,float $max=999999999): float {
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value<$min || (float)$value>$max) throw new DomainException('Revisa los importes y las cantidades.');
    return (float)$value;
}
function r2_select(string $name,array $rows,mixed $selected=0,string $label='name',bool $empty=false): string {
    $out='<select class="input" name="'.e($name).'">'.($empty?'<option value="0">Selecciona</option>':'');
    foreach($rows as $row) $out.='<option value="'.(int)$row['id'].'" '.((string)$selected===(string)$row['id']?'selected':'').'>'.e($row[$label]).'</option>';
    return $out.'</select>';
}
function r2_form(string $action,int $id=0): string { return '<form method="post">'.csrf_field().pos_hidden_148('action',$action).pos_hidden_148('id',$id); }
function r2_redirect(string $section): never { redirect(url('admin/'.$section)); }

function r2_modifiers(App $app,array $u,array $t,string $kind): void {
    $section=$kind==='extra'?'extras':'opcionales';$title=$kind==='extra'?'Extras':'Opcionales';
    if(is_post()) { verify_csrf(); try {
        $id=(int)($_POST['id']??0);$old=$id?$app->one('SELECT * FROM fisitaap_r2_modifiers WHERE id=? AND tenant_id=? AND kind=?',[$id,$t['id'],$kind]):null;
        if($id&&!$old) throw new DomainException('Registro no disponible.');
        $app->db->beginTransaction();
        if(($_POST['action']??'')==='delete') {
            foreach($app->all('SELECT group_id FROM fisitaap_r2_modifier_groups WHERE modifier_id=?',[$id]) as $row) $app->exec('UPDATE product_option_groups SET is_active=0 WHERE id=? AND tenant_id=?',[$row['group_id'],$t['id']]);
            $app->exec('UPDATE fisitaap_r2_modifiers SET is_active=0 WHERE id=? AND tenant_id=?',[$id,$t['id']]);
        } else {
            $name=trim((string)($_POST['name']??''));if($name===''||mb_strlen($name)>120)throw new DomainException('Escribe un nombre de hasta 120 caracteres.');
            $choices=[];foreach(explode("\n",trim((string)($_POST['choices']??''))) as $line) {
                if(trim($line)==='')continue;[$label,$amount]=array_pad(explode('|',$line,2),2,'0');$label=trim($label);
                if($label===''||mb_strlen($label)>120)throw new DomainException('Revisa los nombres de las opciones.');
                $choices[]=['name'=>$label,'price'=>$kind==='extra'?r2_number(trim($amount)):0];
            }
            if(!$choices||count($choices)>20)throw new DomainException('Agrega entre 1 y 20 opciones.');
            $pids=array_values(array_unique(array_map('intval',(array)($_POST['products']??[]))));
            foreach($pids as $pid)if(!$app->one('SELECT id FROM products WHERE id=? AND tenant_id=?',[$pid,$t['id']]))throw new DomainException('Producto de otro negocio.');
            $required=isset($_POST['required'])?1:0;$multiple=isset($_POST['multiple'])?1:0;$json=json_encode($choices,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if($id)$app->exec('UPDATE fisitaap_r2_modifiers SET name=?,choices_json=?,required=?,multiple=?,is_active=1 WHERE id=? AND tenant_id=?',[$name,$json,$required,$multiple,$id,$t['id']]);
            else {$app->exec('INSERT INTO fisitaap_r2_modifiers(tenant_id,kind,name,choices_json,required,multiple) VALUES(?,?,?,?,?,?)',[$t['id'],$kind,$name,$json,$required,$multiple]);$id=(int)$app->db->lastInsertId();}
            foreach($app->all('SELECT * FROM fisitaap_r2_modifier_groups WHERE modifier_id=?',[$id]) as $row)if(!in_array((int)$row['product_id'],$pids,true))$app->exec('UPDATE product_option_groups SET is_active=0 WHERE id=? AND tenant_id=?',[$row['group_id'],$t['id']]);
            foreach($pids as $pid) {
                $managed=$app->one('SELECT group_id FROM fisitaap_r2_modifier_groups WHERE modifier_id=? AND product_id=?',[$id,$pid]);
                $gid=(int)($managed['group_id']??0);
                if(!$gid||!$app->one('SELECT id FROM product_option_groups WHERE id=? AND tenant_id=?',[$gid,$t['id']])) {
                    $app->exec('INSERT INTO product_option_groups(tenant_id,product_id,name,type,is_required,min_select,max_select) VALUES(?,?,?,?,?,?,?)',[$t['id'],$pid,$name,$multiple?'multiple':'single',$required,$required,$multiple?count($choices):1]);$gid=(int)$app->db->lastInsertId();
                    $app->exec('INSERT INTO fisitaap_r2_modifier_groups(modifier_id,product_id,group_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE group_id=VALUES(group_id)',[$id,$pid,$gid]);
                }
                $app->exec('UPDATE product_option_groups SET name=?,type=?,is_required=?,min_select=?,max_select=?,is_active=1 WHERE id=? AND tenant_id=?',[$name,$multiple?'multiple':'single',$required,$required,$multiple?count($choices):1,$gid,$t['id']]);
                $existing=$app->all('SELECT id FROM product_options WHERE group_id=? ORDER BY sort_order,id',[$gid]);
                foreach($choices as $i=>$choice) {
                    if(isset($existing[$i]))$app->exec('UPDATE product_options SET name=?,price_delta=?,sort_order=?,is_active=1 WHERE id=?',[$choice['name'],$choice['price'],$i,$existing[$i]['id']]);
                    else $app->exec('INSERT INTO product_options(group_id,name,price_delta,sort_order) VALUES(?,?,?,?)',[$gid,$choice['name'],$choice['price'],$i]);
                }
                foreach(array_slice($existing,count($choices)) as $row)$app->exec('UPDATE product_options SET is_active=0 WHERE id=?',[$row['id']]);
            }
        }
        audit($app,(int)$t['id'],(int)$u['id'],'r2_'.$section,'modifier',$id);$app->db->commit();flash('success','Guardado. Las selecciones aparecen en los productos asignados.');
    }catch(Throwable $ex){if($app->db->inTransaction())$app->db->rollBack();flash('error',$ex->getMessage());}r2_redirect($section); }
    $edit=$app->one('SELECT * FROM fisitaap_r2_modifiers WHERE id=? AND tenant_id=? AND kind=?',[(int)($_GET['edit']??0),$t['id'],$kind]);
    $assigned=$edit?array_column($app->all('SELECT m.product_id FROM fisitaap_r2_modifier_groups m JOIN product_option_groups g ON g.id=m.group_id WHERE m.modifier_id=? AND g.is_active=1',[$edit['id']]),'product_id'):[];
    $choices='';foreach(json_decode($edit['choices_json']??'[]',true) as $row)$choices.=$row['name'].($kind==='extra'?' | '.$row['price']:'')."\n";
    admin_shell_start($title,$u,$t,$section);echo '<div class="split"><section class="card admin-card"><h2>'.($edit?'Editar':'Crear').' '.$title.'</h2>'.r2_form('save',(int)($edit['id']??0)).field('Nombre del grupo','name',$edit['name']??'',true).'<label>Opciones: una por línea'.($kind==='extra'?' (nombre | precio)':' (sin costo adicional)').'<textarea class="input" name="choices" required rows="6" placeholder="'.($kind==='extra'?'Queso | 500':'Sin cebolla').'">'.e($choices).'</textarea></label><label><input type="checkbox" name="required" '.(!empty($edit['required'])?'checked':'').'> Selección obligatoria</label><label><input type="checkbox" name="multiple" '.(!empty($edit['multiple'])?'checked':'').'> Permitir varias opciones</label><h3>Asignar a productos</h3><div class="r2-checks">';
    foreach($app->all('SELECT id,name FROM products WHERE tenant_id=? ORDER BY name',[$t['id']]) as $p)echo '<label><input type="checkbox" name="products[]" value="'.(int)$p['id'].'" '.(in_array($p['id'],$assigned)?'checked':'').'> '.e($p['name']).'</label>';
    echo '</div><button class="btn">Guardar</button></form></section><section class="card admin-card"><h2>'.$title.' creados</h2>';
    foreach($app->all('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=? AND kind=? AND is_active=1 ORDER BY name',[$t['id'],$kind]) as $row)echo '<article class="r2-row"><strong>'.e($row['name']).'</strong><a class="btn btn-light" href="?edit='.(int)$row['id'].'">Editar</a>'.r2_form('delete',(int)$row['id']).'<button class="btn btn-danger">Desactivar</button></form></article>';
    echo '<p>Las opciones anteriores siguen disponibles en <a href="'.url('admin/opciones').'">Opciones anteriores</a>.</p></section></div>';admin_shell_end();
}

function r2_combos(App $app,array $u,array $t):void {
    if(is_post()){verify_csrf();try{
        $id=(int)($_POST['id']??0);$app->db->beginTransaction();
        if($id){$combo=$app->one('SELECT * FROM products WHERE id=? AND tenant_id=? AND product_type="combo" FOR UPDATE',[$id,$t['id']]);if(!$combo)throw new DomainException('Combo no disponible.');
            if($app->one('SELECT i.id FROM pos_ticket_items i JOIN pos_tickets p ON p.id=i.ticket_id WHERE i.product_id=? AND p.status NOT IN ("paid","void") LIMIT 1',[$id])||$app->one('SELECT i.id FROM order_items i JOIN orders o ON o.id=i.order_id WHERE i.product_id=? AND o.status NOT IN ("delivered","cancelled") LIMIT 1',[$id]))throw new DomainException('Cierra los pedidos abiertos de este combo antes de modificarlo.');}
        if(($_POST['action']??'')==='delete')$app->exec('UPDATE products SET status="hidden" WHERE id=? AND tenant_id=?',[$id,$t['id']]);
        else{
            $name=trim((string)($_POST['name']??''));if($name===''||mb_strlen($name)>190)throw new DomainException('Escribe un nombre.');$price=r2_number($_POST['price']??0);$tax=r2_number($_POST['tax_rate']??0,0,100);
            $comboCost=0.0;$components=[];foreach((array)($_POST['components']??[]) as $pid=>$qty)if((float)$qty>0){$p=$app->one('SELECT * FROM products WHERE id=? AND tenant_id=? AND product_type="simple"',[(int)$pid,$t['id']]);if(!$p)throw new DomainException('Los componentes deben ser productos simples de este negocio.');$components[(int)$pid]=r2_number($qty,.001,99999);$comboCost+=(float)$p['cost']*$components[(int)$pid];}
            if(!$components)throw new DomainException('Selecciona los productos del combo.');$cat=$app->one('SELECT id FROM categories WHERE tenant_id=? AND slug="combos"',[$t['id']]);if(!$cat){$app->exec('INSERT INTO categories(tenant_id,name,slug) VALUES(?,"Combos","combos")',[$t['id']]);$cat=['id'=>(int)$app->db->lastInsertId()];}
            if($id)$app->exec('UPDATE products SET name=?,price=?,tax_rate=?,description=?,category_id=?,status="active",track_inventory=1 WHERE id=? AND tenant_id=?',[$name,$price,$tax,trim((string)($_POST['description']??'')),$cat['id'],$id,$t['id']]);
            else{$app->exec('INSERT INTO products(tenant_id,category_id,name,slug,price,tax_rate,description,product_type,track_inventory) VALUES(?,?,?,?,?,?,?,"combo",1)',[$t['id'],$cat['id'],$name,slugify($name).'-'.bin2hex(random_bytes(3)),$price,$tax,trim((string)($_POST['description']??''))]);$id=(int)$app->db->lastInsertId();}
            $app->exec('UPDATE products SET cost=? WHERE id=? AND tenant_id=?',[round($comboCost,4),$id,$t['id']]);$image=upload_image('image');if($image)$app->exec('UPDATE products SET image=? WHERE id=? AND tenant_id=?',[$image,$id,$t['id']]);
            $app->exec('DELETE FROM fisitaap_r2_combo_items WHERE combo_id=?',[$id]);foreach($components as $pid=>$qty)$app->exec('INSERT INTO fisitaap_r2_combo_items(combo_id,product_id,quantity) VALUES(?,?,?)',[$id,$pid,$qty]);
        }
        audit($app,(int)$t['id'],(int)$u['id'],'r2_combo','product',$id);$app->db->commit();flash('success','Combo guardado. Su inventario se descuenta de los componentes.');
    }catch(Throwable $ex){if($app->db->inTransaction())$app->db->rollBack();flash('error',$ex->getMessage());}r2_redirect('combos');}
    $edit=$app->one('SELECT * FROM products WHERE id=? AND tenant_id=? AND product_type="combo"',[(int)($_GET['edit']??0),$t['id']]);$components=[];if($edit)foreach($app->all('SELECT * FROM fisitaap_r2_combo_items WHERE combo_id=?',[$edit['id']]) as $row)$components[$row['product_id']]=$row['quantity'];
    admin_shell_start('Combos',$u,$t,'combos');echo '<div class="split"><section class="card admin-card"><form method="post" enctype="multipart/form-data">'.csrf_field().pos_hidden_148('id',$edit['id']??0).field('Nombre','name',$edit['name']??'',true).field('Precio del combo','price',$edit['price']??0,true,'number','.01').field('Impuesto %','tax_rate',$edit['tax_rate']??0,true,'number','.01').field('Descripción','description',$edit['description']??'').'<label>Imagen<input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><h3>Productos incluidos</h3><p>Indica la cantidad de cada producto. Cero significa que no está incluido.</p>';
    foreach($app->all('SELECT id,name FROM products WHERE tenant_id=? AND product_type="simple" ORDER BY name',[$t['id']]) as $p)echo field($p['name'],'components['.$p['id'].']',$components[$p['id']]??0,false,'number','.001');
    echo '<button class="btn">Guardar combo</button></form></section><section class="card admin-card">';foreach($app->all('SELECT id,name,price,status FROM products WHERE tenant_id=? AND product_type="combo" ORDER BY name',[$t['id']]) as $p)echo '<article class="r2-row"><strong>'.e($p['name']).'</strong><span>'.money((float)$p['price']).'</span><a class="btn btn-light" href="?edit='.(int)$p['id'].'">Editar</a>'.r2_form('delete',(int)$p['id']).'<button class="btn btn-danger">Ocultar</button></form></article>';echo '</section></div>';admin_shell_end();
}

function r2_promotion(App $app,int $tenant,int $product):?array {
    $promotions=$app->cached('r2-promotions:'.$tenant,function()use($app,$tenant){
        $out=[];foreach($app->all('SELECT * FROM fisitaap_r2_promotions WHERE tenant_id=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY id DESC',[$tenant])as$row)if(!isset($out[$row['product_id']]))$out[$row['product_id']]=$row;return $out;
    });
    return $promotions[$product]??null;
}
function r2_line(App $app,array $t,array $p,float $qty,float $extra=0):array {
    $base=(float)($p['effective_sale_price']??$p['sale_price']??$p['effective_price']??$p['price']);
        $promo=r2_active($app)?r2_promotion($app,(int)$t['id'],(int)$p['id']):null;
    if($promo&&$promo['kind']==='special')$base=min($base,(float)$promo['value']);
    $recipe=['base'=>pos_cents_148($base),'extra'=>pos_cents_148($extra),'rate'=>(int)round((float)$p['tax_rate']*100),'included'=>!empty($t['tax_included']),'bogo'=>(bool)($promo&&$promo['kind']==='bogo')];
    return r2_recipe($recipe,$qty)+['recipe'=>$recipe];
}
function r2_recipe(array $r,float $qty):array {
    $paidQty=$r['bogo']?$qty-floor($qty/2):$qty;
    $gross=(int)round($r['base']*$paidQty+$r['extra']*$qty);
    $net=$r['included']?(int)round($gross/(1+$r['rate']/10000)):$gross;
    $tax=$r['included']?$gross-$net:(int)round($net*$r['rate']/10000);
    return ['price'=>$qty>0?round($net/100/$qty,6):0,'subtotal'=>$net/100,'tax'=>$tax/100,'bogo'=>$r['bogo']];
}
function r2_catalog_prices(App $app,array $t,array $products):array {
    if(!r2_active($app))return $products;
    foreach($products as &$p){$promo=r2_promotion($app,(int)$t['id'],(int)$p['id']);if(!$promo)continue;if($promo['kind']==='special'){$p['sale_price']=min((float)($p['sale_price']?:$p['price']),(float)$promo['value']);$p['effective_sale_price']=min((float)($p['effective_sale_price']??$p['sale_price']),(float)$promo['value']);}$p['badge']=$promo['kind']==='bogo'?'2 × 1':($p['badge']?:'Precio especial');}unset($p);return $products;
}
function r2_promotions(App $app,array $u,array $t):void {
    if(is_post()){verify_csrf();try{$id=(int)($_POST['id']??0);if($id&&!$app->one('SELECT id FROM fisitaap_r2_promotions WHERE id=? AND tenant_id=?',[$id,$t['id']]))throw new DomainException('Promoción no disponible.');
        if(($_POST['action']??'')==='delete')$app->exec('UPDATE fisitaap_r2_promotions SET is_active=0 WHERE id=? AND tenant_id=?',[$id,$t['id']]);
        else{$pid=(int)($_POST['product_id']??0);if(!$app->one('SELECT id FROM products WHERE id=? AND tenant_id=?',[$pid,$t['id']]))throw new DomainException('Producto no disponible.');$kind=($_POST['kind']??'')==='bogo'?'bogo':'special';$value=r2_number($_POST['value']??0);$dates=[];foreach(['starts_at','ends_at'] as $key){$date=trim((string)($_POST[$key]??''));if($date!==''&&strtotime($date)===false)throw new DomainException('Fecha inválida.');$dates[]=$date===''?null:date('Y-m-d H:i:s',strtotime($date));}if($dates[0]&&$dates[1]&&$dates[0]>$dates[1])throw new DomainException('El final debe ser posterior al inicio.');$app->exec('UPDATE fisitaap_r2_promotions SET is_active=0 WHERE tenant_id=? AND product_id=?',[$t['id'],$pid]);$app->exec('INSERT INTO fisitaap_r2_promotions(tenant_id,product_id,kind,value,starts_at,ends_at) VALUES(?,?,?,?,?,?)',[$t['id'],$pid,$kind,$value,...$dates]);}flash('success','Promoción guardada.');
    }catch(Throwable $ex){flash('error',$ex->getMessage());}r2_redirect('promociones');}
    admin_shell_start('Promociones',$u,$t,'promociones');echo '<p><a class="btn btn-light" href="'.url('admin/cupones').'">Administrar cupones</a></p><div class="split"><section class="card admin-card">'.r2_form('save').'<label>Producto'.r2_select('product_id',$app->all('SELECT id,name FROM products WHERE tenant_id=? ORDER BY name',[$t['id']])).'</label><label>Promoción<select class="input" name="kind"><option value="special">Precio especial</option><option value="bogo">2 × 1</option></select></label>'.field('Precio especial (no se usa en 2 × 1)','value',0,true,'number','.01').field('Inicio','starts_at','',false,'datetime-local').field('Fin','ends_at','',false,'datetime-local').'<p>En 2 × 1, cada dos unidades de la misma selección pagan una. Los extras se cobran en todas las unidades.</p><button class="btn">Guardar promoción</button></form></section><section class="card admin-card">';
    foreach($app->all('SELECT r.*,p.name FROM fisitaap_r2_promotions r JOIN products p ON p.id=r.product_id WHERE r.tenant_id=? AND r.is_active=1 ORDER BY r.id DESC',[$t['id']]) as $row)echo '<article class="r2-row"><strong>'.e($row['name']).'</strong><span>'.($row['kind']==='bogo'?'2 × 1':money((float)$row['value'])).'</span><small>'.e(($row['starts_at']?:'Ahora').' — '.($row['ends_at']?:'Sin vencimiento')).'</small>'.r2_form('delete',(int)$row['id']).'<button class="btn btn-danger">Desactivar</button></form></article>';echo '</section></div>';admin_shell_end();
}

function r2_stock_product(App $app,int $tenant,int $branch,int $product,float $delta,string $type,string $ref,?int $refId,?int $user,string $note,float $cost):bool {
    if(!r2_active($app))return false;
    $app->one('SELECT id FROM products WHERE id=? AND tenant_id=? FOR UPDATE',[$product,$tenant]);
    $items=$app->all('SELECT c.quantity,p.id,p.track_inventory,p.cost,p.name FROM fisitaap_r2_combo_items c JOIN products p ON p.id=c.product_id AND p.tenant_id=? JOIN products b ON b.id=c.combo_id AND b.tenant_id=p.tenant_id WHERE c.combo_id=? ORDER BY p.id',[$tenant,$product]);
    if(!$items)return false;
    foreach($items as $i)if($i['track_inventory'])stock_change_v14($app,$tenant,$branch,(int)$i['id'],$delta*(float)$i['quantity'],$type,$ref,$refId,$user,$note.' · '.$i['name'],(float)$i['cost']);
    return true;
}
