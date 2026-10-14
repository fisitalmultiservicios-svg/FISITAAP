<?php
declare(strict_types=1);

function qa180_option_families(App $app,int $tenant):array {
    return $app->cached('option-families180:'.$tenant,function()use($app,$tenant){$map=[];foreach($app->all('SELECT g.id,m.legacy_family180 family,m.legacy_name180 name,m.legacy_min180 minimum,m.legacy_max180 maximum FROM product_option_groups g JOIN fisitaap_r2_modifiers m ON m.id=g.modifier_id180 AND m.tenant_id=g.tenant_id WHERE g.tenant_id=? AND g.is_active=1 AND m.is_active=1 AND m.legacy_family180 IS NOT NULL',[$tenant]) as $row)$map[(int)$row['id']]=$row;return $map;});
}
function qa180_compact_groups(App $app,int $tenant,array $groups):array {
    $families=qa180_option_families($app,$tenant);$out=[];
    foreach($groups as $group){$family=$families[(int)$group['id']]??null;if(!$family){$out['group'.$group['id']]=$group;continue;}
        $key='family'.$family['family'];if(!isset($out[$key])){$merged=$group;$merged['id']=-(int)$family['family'];$merged['name']=$family['name'];$merged['min']=$merged['min_select']=(int)$family['minimum'];$merged['max']=$merged['max_select']=(int)$family['maximum'];$merged['required']=$merged['is_required']=$family['minimum']>0;$merged['type']=$family['maximum']>1?'multiple':'single';$merged['options']=[];$out[$key]=$merged;}
        foreach($group['options']??[] as $option)$out[$key]['options'][]=$option;
    }return array_values($out);
}
function qa180_validate_families(App $app,int $tenant,int $product,array $selected):void {
    $families=qa180_option_families($app,$tenant);if(!$families)return;$rows=$app->all('SELECT g.id,o.id option_id FROM product_option_groups g JOIN product_options o ON o.group_id=g.id AND o.is_active=1 WHERE g.tenant_id=? AND g.product_id=? AND g.is_active=1',[$tenant,$product]);$count=[];$rules=[];
    foreach($rows as $row){$family=$families[(int)$row['id']]??null;if(!$family)continue;$fid=(int)$family['family'];$rules[$fid]=$family;$count[$fid]=($count[$fid]??0)+(in_array((int)$row['option_id'],$selected,true)?1:0);}
    foreach($rules as $fid=>$rule)if(($count[$fid]??0)<(int)$rule['minimum']||($count[$fid]??0)>(int)$rule['maximum'])throw new DomainException('Selecciona de '.$rule['minimum'].' a '.$rule['maximum'].' opciones de '.$rule['name'].'.');
}
function qa180_pos_options(App $app):never {
    [$user,$tenant]=qa180_business($app,'ventas');$id=(int)($_GET['product_id']??0);$product=$app->one('SELECT id,name FROM products WHERE id=? AND tenant_id=? AND status="active"',[$id,$tenant['id']]);if(!$product)json_response(['ok'=>false,'error'=>'Producto no disponible.'],404);
    $groups=[];foreach($app->all('SELECT g.id,g.name,g.type,g.is_required,g.min_select,g.max_select,o.id option_id,o.name option_name,o.price_delta FROM product_option_groups g LEFT JOIN product_options o ON o.group_id=g.id AND o.is_active=1 WHERE g.tenant_id=? AND g.product_id=? AND g.is_active=1 ORDER BY g.sort_order,g.id,o.sort_order,o.id',[$tenant['id'],$id]) as $row){$gid=(int)$row['id'];if(!isset($groups[$gid]))$groups[$gid]=['id'=>$gid,'name'=>$row['name'],'type'=>$row['type'],'is_required'=>(bool)$row['is_required'],'min_select'=>(int)$row['min_select'],'max_select'=>(int)$row['max_select'],'options'=>[]];if($row['option_id']!==null)$groups[$gid]['options'][]=['id'=>(int)$row['option_id'],'name'=>$row['option_name'],'price_delta'=>$row['price_delta']];}
    json_response(['ok'=>true,'product'=>$product,'groups'=>qa180_compact_groups($app,(int)$tenant['id'],$groups)]);
}

function qa180_modifier_sync(App $app,int $tenant):void {
    // Only configuration writes call this materializer. Public catalog/POS
    // reads use the ordinary indexed option tables without request-time DDL.
    $assigned='(SELECT a.tenant_id,a.modifier_id,a.product_id FROM fisitaap_modifier_products180 a UNION SELECT c.tenant_id,c.modifier_id,p.id FROM fisitaap_modifier_categories180 c JOIN products p ON p.tenant_id=c.tenant_id AND p.category_id=c.category_id)';
    $app->exec('UPDATE product_option_groups g JOIN fisitaap_r2_modifiers m ON m.id=g.modifier_id180 AND m.tenant_id=g.tenant_id LEFT JOIN '.$assigned.' a ON a.tenant_id=g.tenant_id AND a.modifier_id=m.id AND a.product_id=g.product_id SET g.is_active=IF(m.is_active=1 AND a.product_id IS NOT NULL,1,0),g.name=m.name,g.type="single",g.is_required=m.required,g.min_select=m.required,g.max_select=1 WHERE g.tenant_id=?',[$tenant]);
    $app->exec('INSERT INTO product_option_groups(tenant_id,product_id,name,type,is_required,min_select,max_select,is_active,modifier_id180) SELECT m.tenant_id,a.product_id,m.name,"single",m.required,m.required,1,1,m.id FROM '.$assigned.' a JOIN fisitaap_r2_modifiers m ON m.id=a.modifier_id AND m.tenant_id=a.tenant_id AND m.is_active=1 JOIN products p ON p.id=a.product_id AND p.tenant_id=a.tenant_id WHERE a.tenant_id=? ON DUPLICATE KEY UPDATE name=VALUES(name),type="single",is_required=VALUES(is_required),min_select=VALUES(min_select),max_select=1,is_active=1',[$tenant]);
    $app->exec('INSERT INTO fisitaap_r2_modifier_groups(modifier_id,product_id,group_id) SELECT modifier_id180,product_id,id FROM product_option_groups WHERE tenant_id=? AND modifier_id180 IS NOT NULL ON DUPLICATE KEY UPDATE group_id=VALUES(group_id)',[$tenant]);
    $app->exec('INSERT INTO product_options(group_id,name,price_delta,sort_order,is_active,modifier_option180) SELECT g.id,JSON_UNQUOTE(JSON_EXTRACT(m.choices_json,"$[0].name")),COALESCE(JSON_UNQUOTE(JSON_EXTRACT(m.choices_json,"$[0].price")),0),0,1,"one" FROM product_option_groups g JOIN fisitaap_r2_modifiers m ON m.id=g.modifier_id180 AND m.tenant_id=g.tenant_id WHERE g.tenant_id=? ON DUPLICATE KEY UPDATE name=VALUES(name),price_delta=VALUES(price_delta),is_active=1',[$tenant]);
}
function qa180_modifiers_upgrade(App $app,int $tenantId=0):void {
    if($tenantId){if((int)($app->one('SELECT modifiers_migrated FROM fisitaap_tenant_updates180 WHERE tenant_id=?',[$tenantId])['modifiers_migrated']??0)===1)return;}
    elseif($app->setting('modifiers180_migrated','0')==='1')return;
    $owns=!$app->db->inTransaction();if($owns)$app->db->beginTransaction();try{
        foreach($app->all('SELECT * FROM fisitaap_r2_modifiers'.($tenantId?' WHERE tenant_id=?':'').' ORDER BY id',$tenantId?[$tenantId]:[]) as $modifier){
            $choices=json_decode($modifier['choices_json'],true,64,JSON_THROW_ON_ERROR);if(!$choices)continue;
            $products=array_column($app->all('SELECT r.product_id FROM fisitaap_r2_modifier_groups r JOIN product_option_groups g ON g.id=r.group_id AND g.tenant_id=? WHERE r.modifier_id=? AND g.is_active=1',[$modifier['tenant_id'],$modifier['id']]),'product_id');
            $family=count($choices)>1?(int)$modifier['id']:null;$minimum=$modifier['required']?1:0;$maximum=$modifier['multiple']?count($choices):1;
            foreach($choices as $index=>$choice){
                $json=json_encode([$choice],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                if($index===0){$id=(int)$modifier['id'];$app->exec('UPDATE fisitaap_r2_modifiers SET name=?,choices_json=?,multiple=0 WHERE id=?',[$choice['name'],$json,$id]);$app->exec('UPDATE product_option_groups g JOIN fisitaap_r2_modifier_groups r ON r.group_id=g.id SET g.modifier_id180=? WHERE r.modifier_id=?',[$id,$id]);
                    foreach($app->all('SELECT g.id FROM product_option_groups g WHERE g.modifier_id180=? AND g.tenant_id=?',[$id,$modifier['tenant_id']]) as $group){$options=$app->all('SELECT id FROM product_options WHERE group_id=? ORDER BY sort_order,id',[$group['id']]);if($options){$app->exec('UPDATE product_options SET modifier_option180="one" WHERE id=?',[$options[0]['id']]);foreach(array_slice($options,1) as $old)$app->exec('UPDATE product_options SET is_active=0 WHERE id=?',[$old['id']]);}}
                }else{$app->exec('INSERT INTO fisitaap_r2_modifiers(tenant_id,kind,name,choices_json,required,multiple,is_active) VALUES(?,?,?,?,0,0,?)',[$modifier['tenant_id'],$modifier['kind'],$choice['name'],$json,$modifier['is_active']]);$id=(int)$app->db->lastInsertId();}
                if($family)$app->exec('UPDATE fisitaap_r2_modifiers SET required=0,legacy_family180=?,legacy_name180=?,legacy_min180=?,legacy_max180=? WHERE id=?',[$family,$modifier['name'],$minimum,$maximum,$id]);
                foreach($products as $pid)$app->exec('INSERT IGNORE INTO fisitaap_modifier_products180(tenant_id,modifier_id,product_id) VALUES(?,?,?)',[$modifier['tenant_id'],$id,$pid]);
            }
        }
        foreach($app->all('SELECT DISTINCT tenant_id FROM fisitaap_r2_modifiers'.($tenantId?' WHERE tenant_id=?':''),$tenantId?[$tenantId]:[]) as $tenant)qa180_modifier_sync($app,(int)$tenant['tenant_id']);
        if($tenantId)$app->exec('INSERT INTO fisitaap_tenant_updates180(tenant_id,modifiers_migrated) VALUES(?,1) ON DUPLICATE KEY UPDATE modifiers_migrated=1',[$tenantId]);
        else $app->exec('INSERT INTO settings(`key`,`value`) VALUES("modifiers180_migrated","1") ON DUPLICATE KEY UPDATE `value`="1"');
        if($owns)$app->db->commit();
    }catch(Throwable $error){if($owns&&$app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_modifier_assignment(App $app,array $user,array $tenant,bool $category):void {
    $entity=$category?'categories':'products';$section=$category?'categorias':'productos';$fk=$category?'category_id':'product_id';$table=$category?'fisitaap_modifier_categories180':'fisitaap_modifier_products180';$id=(int)($_GET['id']??$_POST['id']??0);
    $row=$app->one('SELECT id,name FROM '.$entity.' WHERE id=? AND tenant_id=?',[$id,$tenant['id']]);if(!$row)throw new DomainException('Registro no disponible.');
    if(is_post()){verify_csrf();try{qa180_permission($app,$user,$section,'write');$app->db->beginTransaction();$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
        $ids=array_unique(array_map('intval',(array)($_POST['modifiers']??[])));foreach($ids as $mid)if(!$app->one('SELECT id FROM fisitaap_r2_modifiers WHERE id=? AND tenant_id=? AND is_active=1',[$mid,$tenant['id']]))throw new DomainException('Extra no disponible para este negocio.');
        $app->exec('DELETE FROM '.$table.' WHERE tenant_id=? AND '.$fk.'=?',[$tenant['id'],$id]);foreach($ids as $mid)$app->exec('INSERT INTO '.$table.'(tenant_id,modifier_id,'.$fk.') VALUES(?,?,?)',[$tenant['id'],$mid,$id]);qa180_modifier_sync($app,(int)$tenant['id']);qa180_audit($app,$user,'modifier_assignment',$category?'category':'product',$id,'Asignó extras y opcionales a '.$row['name']);$app->db->commit();flash('success','Asignaciones guardadas.');
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();flash('error',$error instanceof PDOException?'No se guardaron las asignaciones.':$error->getMessage());}redirect(url('admin/'.($category?'categoria-extras':'producto-extras').'?id='.$id));}
    $selected=array_column($app->all('SELECT modifier_id FROM '.$table.' WHERE tenant_id=? AND '.$fk.'=?',[$tenant['id'],$id]),'modifier_id');
    admin_shell_start($row['name'],$user,$tenant,$section);echo '<section class="card admin-card"><nav class="qa180-tabs"><a class="btn btn-light" href="'.e(url('admin/'.$section.'?edit='.$id)).'">General</a><a class="btn" href="?id='.$id.'">Extras y opcionales</a></nav><p>Asignaciones manuales del producto se conservan al cambiar de categoría. Las heredadas se sustituyen por las de la nueva categoría. Si un extra es manual y heredado a la vez, aparece una sola vez.</p>'.r2_form('save',$id).'<div class="qa180-modifier-list">';
    foreach($app->all('SELECT id,name,kind,choices_json FROM fisitaap_r2_modifiers WHERE tenant_id=? AND is_active=1 ORDER BY kind,name',[$tenant['id']]) as $modifier){$choice=json_decode($modifier['choices_json'],true)[0]??[];echo '<label><input type="checkbox" name="modifiers[]" value="'.(int)$modifier['id'].'" '.(in_array($modifier['id'],$selected)?'checked':'').'> '.e($modifier['name']).' · '.($modifier['kind']==='extra'?money((float)($choice['price']??0)):'Opcional').'</label>';}
    echo '</div><button class="btn">Guardar asignaciones</button></form></section>';admin_shell_end();
}
function qa180_modifiers(App $app,array $user,array $tenant,string $kind):void {
    $section=$kind==='extra'?'extras':'opcionales';
    if(is_post()){verify_csrf();try{
        qa180_permission($app,$user,$section,($_POST['action']??'')==='delete'?'delete':'write');$id=(int)($_POST['id']??0);$app->db->beginTransaction();$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
        if($id&&!$app->one('SELECT id FROM fisitaap_r2_modifiers WHERE id=? AND tenant_id=? AND kind=?',[$id,$tenant['id'],$kind]))throw new DomainException('Elemento no disponible.');
        if(($_POST['action']??'')==='delete')$app->exec('UPDATE fisitaap_r2_modifiers SET is_active=0 WHERE id=? AND tenant_id=?',[$id,$tenant['id']]);
        else{
            $name=mb_substr(trim((string)($_POST['name']??'')),0,120);if($name==='')throw new DomainException('Escribe el nombre.');$price=$kind==='extra'?r2_number($_POST['price']??0):0;$required=isset($_POST['required'])?1:0;$json=json_encode([['name'=>$name,'price'=>$price]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if($id)$app->exec('UPDATE fisitaap_r2_modifiers SET name=?,choices_json=?,required=?,multiple=0,is_active=1 WHERE id=? AND tenant_id=?',[$name,$json,$required,$id,$tenant['id']]);else{$app->exec('INSERT INTO fisitaap_r2_modifiers(tenant_id,kind,name,choices_json,required,multiple) VALUES(?,?,?,?,?,0)',[$tenant['id'],$kind,$name,$json,$required]);$id=(int)$app->db->lastInsertId();}
            $pids=array_unique(array_filter(array_map('intval',(array)($_POST['products']??[]))));$cats=array_unique(array_filter(array_map('intval',(array)($_POST['categories']??[]))));
            foreach($pids as $pid)if(!$app->one('SELECT id FROM products WHERE id=? AND tenant_id=?',[$pid,$tenant['id']]))throw new DomainException('Producto no disponible.');foreach($cats as $cat)if(!$app->one('SELECT id FROM categories WHERE id=? AND tenant_id=?',[$cat,$tenant['id']]))throw new DomainException('Categoría no disponible.');
            $app->exec('DELETE FROM fisitaap_modifier_products180 WHERE tenant_id=? AND modifier_id=?',[$tenant['id'],$id]);$app->exec('DELETE FROM fisitaap_modifier_categories180 WHERE tenant_id=? AND modifier_id=?',[$tenant['id'],$id]);
            foreach($pids as $pid)$app->exec('INSERT INTO fisitaap_modifier_products180(tenant_id,modifier_id,product_id) VALUES(?,?,?)',[$tenant['id'],$id,$pid]);foreach($cats as $cat)$app->exec('INSERT INTO fisitaap_modifier_categories180(tenant_id,modifier_id,category_id) VALUES(?,?,?)',[$tenant['id'],$id,$cat]);
        }
        qa180_modifier_sync($app,(int)$tenant['id']);qa180_audit($app,$user,'modifier_save','modifier',$id,($_POST['action']??'')==='delete'?'Desactivó un extra u opcional':'Guardó '.$name.' y sus asignaciones');$app->db->commit();flash('success','Elemento guardado.');
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();flash('error',$error instanceof PDOException?'No se guardó el elemento.':$error->getMessage());}redirect(url('admin/'.$section));}
    $edit=$app->one('SELECT * FROM fisitaap_r2_modifiers WHERE id=? AND tenant_id=? AND kind=?',[(int)($_GET['edit']??0),$tenant['id'],$kind]);$choice=json_decode($edit['choices_json']??'[]',true)[0]??[];$assigned=$edit?array_column($app->all('SELECT product_id FROM fisitaap_modifier_products180 WHERE tenant_id=? AND modifier_id=?',[$tenant['id'],$edit['id']]),'product_id'):[];$categories=$edit?array_column($app->all('SELECT category_id FROM fisitaap_modifier_categories180 WHERE tenant_id=? AND modifier_id=?',[$tenant['id'],$edit['id']]),'category_id'):[];
    admin_shell_start($kind==='extra'?'Extras':'Opcionales',$user,$tenant,$section);echo '<div class="split"><section class="card admin-card"><h2>'.($edit?'Editar elemento':'Crear elemento').'</h2>'.r2_form('save',(int)($edit['id']??0)).field('Nombre','name',$edit['name']??'',true);if($kind==='extra')echo field('Precio adicional','price',$choice['price']??0,true,'number','.01');echo '<label><input type="checkbox" name="required" '.(!empty($edit['required'])?'checked':'').'> Selección obligatoria</label><h3>Asignar a productos</h3><label>Seleccionar productos<select class="input" name="products[]" multiple size="6" data-searchable-select>';
    foreach($app->all('SELECT id,name,sku FROM products WHERE tenant_id=? ORDER BY name',[$tenant['id']]) as $p)echo '<option value="'.(int)$p['id'].'" '.(in_array($p['id'],$assigned)?'selected':'').'>'.e($p['name'].' · '.$p['sku']).'</option>';echo '</select></label><h3>Asignar a categorías completas</h3><div class="qa180-modifier-list">';foreach($app->all('SELECT id,name FROM categories WHERE tenant_id=? ORDER BY name',[$tenant['id']]) as $cat)echo '<label><input type="checkbox" name="categories[]" value="'.(int)$cat['id'].'" '.(in_array($cat['id'],$categories)?'checked':'').'> '.e($cat['name']).'</label>';echo '</div><p>Al mover un producto, conserva sus asignaciones manuales y hereda las de la categoría nueva.</p><button class="btn">Guardar elemento</button></form></section><section class="card admin-card"><h2>Elementos creados</h2>';
    foreach($app->all('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=? AND kind=? AND is_active=1 ORDER BY name',[$tenant['id'],$kind]) as $modifier){$price=json_decode($modifier['choices_json'],true)[0]['price']??0;echo '<article class="r2-row"><strong>'.e($modifier['name']).'</strong><span>'.money((float)$price).'</span><a class="btn btn-light" href="?edit='.(int)$modifier['id'].'">Editar</a>'.r2_form('delete',(int)$modifier['id']).'<button class="btn btn-danger">Desactivar</button></form></article>';}
    echo '</section></div>';admin_shell_end();
}
