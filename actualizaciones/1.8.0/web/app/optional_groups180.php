<?php
declare(strict_types=1);

function qa180_optional_members(App $app,int $tenant,int $id,bool $active=false):array {
    $row=$app->one('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=? AND id=? AND kind="optional"',[$tenant,$id]);
    if(!$row)throw new DomainException('Grupo no disponible para este negocio.');
    $family=(int)($row['legacy_family180']??0);
    return $app->all('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=? AND kind="optional" AND '.($family?'legacy_family180=?':'id=?').($active?' AND is_active=1':'').' ORDER BY id',[$tenant,$family?:$id]);
}
function qa180_optional_groups(App $app,array $user,array $tenant):void {
    $tid=(int)$tenant['id'];
    if(is_post()){
        verify_csrf();try{
            $deleting=($_POST['action']??'')==='delete';qa180_permission($app,$user,'opcionales',$deleting?'delete':'write');
            $id=(int)($_POST['id']??0);$app->db->beginTransaction();$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tid]);
            $members=$id?qa180_optional_members($app,$tid,$id):[];
            if($deleting){
                if(!$id)throw new DomainException('Selecciona un grupo.');
                foreach($members as $member)$app->exec('UPDATE fisitaap_r2_modifiers SET is_active=0 WHERE id=? AND tenant_id=?',[$member['id'],$tid]);
            }else{
                $name=trim((string)($_POST['group_name']??''));if($name===''||mb_strlen($name)>120)throw new DomainException('Escribe un nombre de grupo de hasta 120 caracteres, por ejemplo Tallas o Colores.');
                $duplicate=$app->one('SELECT id,legacy_family180 FROM fisitaap_r2_modifiers WHERE tenant_id=? AND kind="optional" AND is_active=1 AND legacy_name180=? LIMIT 1',[$tid,$name]);
                $family=$members?(int)($members[0]['legacy_family180']?:$members[0]['id']):0;
                if($duplicate&&(int)($duplicate['legacy_family180']?:$duplicate['id'])!==$family)throw new DomainException('Ya existe ese grupo. Usa Editar para agregar sus opciones.');
                $lines=$_POST['choices']??[];if(!is_array($lines)||count($lines)>50)throw new DomainException('El grupo permite hasta 50 opciones.');
                $known=array_column($members,null,'id');$choices=[];$names=[];$seen=[];
                foreach($lines as $line){
                    if(!is_array($line))throw new DomainException('Revisa las opciones.');$choice=trim((string)($line['name']??''));$cid=(int)($line['id']??0);
                    if($choice==='')continue;if(mb_strlen($choice)>120)throw new DomainException('Cada opción admite hasta 120 caracteres.');
                    if($cid&&(!isset($known[$cid])||isset($seen[$cid])))throw new DomainException('Una opción no pertenece a este grupo.');
                    $key=mb_strtolower($choice);if(isset($names[$key]))throw new DomainException('No repitas opciones dentro del mismo grupo.');$names[$key]=true;$seen[$cid?:-count($choices)-1]=true;$choices[]=[$cid,$choice];
                }
                if(!$choices)throw new DomainException('Agrega al menos una opción al grupo.');
                $minimum=isset($_POST['required'])?1:0;$multiple=isset($_POST['multiple']);$maximum=$multiple?count($choices):1;
                $pids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['products']??[])))));$cats=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['categories']??[])))));
                foreach($pids as $pid)if(!$app->one('SELECT id FROM products WHERE id=? AND tenant_id=?',[$pid,$tid]))throw new DomainException('Producto no disponible para este negocio.');
                foreach($cats as $cat)if(!$app->one('SELECT id FROM categories WHERE id=? AND tenant_id=?',[$cat,$tid]))throw new DomainException('Categoría no disponible para este negocio.');
                $retained=[];
                foreach($choices as [$cid,$choice]){
                    // Keep each option's identity: historical order snapshots and IDs survive edits.
                    $json=json_encode([['name'=>$choice,'price'=>0]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                    if($cid)$app->exec('UPDATE fisitaap_r2_modifiers SET name=?,choices_json=?,required=0,multiple=0,is_active=1 WHERE tenant_id=? AND id=?',[$choice,$json,$tid,$cid]);
                    else{$app->exec('INSERT INTO fisitaap_r2_modifiers(tenant_id,kind,name,choices_json,required,multiple) VALUES(?,"optional",?,?,0,0)',[$tid,$choice,$json]);$cid=(int)$app->db->lastInsertId();}
                    if(!$family)$family=$cid;
                    $app->exec('UPDATE fisitaap_r2_modifiers SET legacy_family180=?,legacy_name180=?,legacy_min180=?,legacy_max180=? WHERE tenant_id=? AND id=?',[$family,$name,$minimum,$maximum,$tid,$cid]);
                    foreach(['products'=>'product_id','categories'=>'category_id'] as $table=>$fk){$app->exec('DELETE FROM fisitaap_modifier_'.$table.'180 WHERE tenant_id=? AND modifier_id=?',[$tid,$cid]);foreach($table==='products'?$pids:$cats as $assigned)$app->exec('INSERT INTO fisitaap_modifier_'.$table.'180(tenant_id,modifier_id,'.$fk.') VALUES(?,?,?)',[$tid,$cid,$assigned]);}
                    $retained[]=$cid;
                }
                foreach($members as $member)if(!in_array((int)$member['id'],$retained,true))$app->exec('UPDATE fisitaap_r2_modifiers SET is_active=0 WHERE tenant_id=? AND id=?',[$tid,$member['id']]);
                $id=$family;
            }
            qa180_modifier_sync($app,$tid);qa180_audit($app,$user,'optional_group','modifier',$id,$deleting?'Desactivó un grupo de opcionales':'Guardó el grupo '.$name);$app->db->commit();flash('success','Grupo de opcionales guardado.');
        }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();flash('error',$error instanceof PDOException?'No se guardó el grupo.':$error->getMessage());}
        redirect(url('admin/opcionales'));
    }
    $members=!empty($_GET['edit'])?qa180_optional_members($app,$tid,(int)$_GET['edit']):[];$edit=$members[0]??null;$active=array_values(array_filter($members,fn($m)=>(bool)$m['is_active']));$ids=array_column($active,'id');$assigned=[];$categories=[];
    if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$assigned=array_column($app->all('SELECT DISTINCT product_id FROM fisitaap_modifier_products180 WHERE tenant_id=? AND modifier_id IN('.$marks.')',[$tid,...$ids]),'product_id');$categories=array_column($app->all('SELECT DISTINCT category_id FROM fisitaap_modifier_categories180 WHERE tenant_id=? AND modifier_id IN('.$marks.')',[$tid,...$ids]),'category_id');}
    admin_shell_start('Grupos de opcionales',$user,$tenant,'opcionales');
    echo '<div class="split"><section class="card admin-card"><h2>'.($edit?'Editar grupo':'Crear grupo de opcionales').'</h2><p>Por ejemplo: Tallas con S, M y L; Colores con Rojo y Azul. Las opciones no tienen costo adicional.</p>'.r2_form('save',(int)($edit['id']??0)).field('Nombre del grupo','group_name',$edit['legacy_name180']??'',true).'<h3>Opciones del grupo</h3><div data-optional180-lines>';
    if(!$active)$active=[['id'=>0,'name'=>''],['id'=>0,'name'=>'']];
    foreach($active as $i=>$member)echo '<div class="qa180-option-line" data-optional180-line><input type="hidden" name="choices['.$i.'][id]" value="'.(int)$member['id'].'"><label>Opción<input class="input" name="choices['.$i.'][name]" value="'.e($member['name']).'" maxlength="120" placeholder="Ej. S o Rojo"></label><button class="btn btn-light" type="button" data-optional180-remove>Quitar</button></div>';
    echo '</div><button class="btn btn-light" type="button" data-optional180-add>Agregar opción</button><p><label><input type="checkbox" name="required" '.(!empty($edit['legacy_min180'])||!empty($edit['required'])?'checked':'').'> Elegir al menos una opción es obligatorio</label></p><p><label><input type="checkbox" name="multiple" '.((int)($edit['legacy_max180']??1)>1?'checked':'').'> Permitir varias opciones del grupo</label></p><h3>Asignar a productos</h3><select class="input" name="products[]" multiple size="6" data-searchable-select aria-label="Productos del grupo">';
    foreach($app->all('SELECT id,name,sku FROM products WHERE tenant_id=? ORDER BY name',[$tid]) as $p)echo '<option value="'.(int)$p['id'].'" '.(in_array($p['id'],$assigned)?'selected':'').'>'.e($p['name'].' · '.$p['sku']).'</option>';
    echo '</select><h3>Asignar a categorías completas</h3><div class="qa180-modifier-list">';foreach($app->all('SELECT id,name FROM categories WHERE tenant_id=? ORDER BY name',[$tid]) as $cat)echo '<label><input type="checkbox" name="categories[]" value="'.(int)$cat['id'].'" '.(in_array($cat['id'],$categories)?'checked':'').'> '.e($cat['name']).'</label>';
    echo '</div><p>Las asignaciones manuales del producto se conservan al cambiar su categoría.</p><button class="btn">Guardar grupo</button></form></section><section class="card admin-card"><h2>Grupos creados</h2>';
    $groups=[];foreach($app->all('SELECT * FROM fisitaap_r2_modifiers WHERE tenant_id=? AND kind="optional" AND is_active=1 ORDER BY COALESCE(legacy_name180,name),id',[$tid]) as $member){$key=(int)($member['legacy_family180']?:$member['id']);$groups[$key][]=$member;}
    foreach($groups as $group){$row=$group[0];echo '<article class="qa180-optional-group"><h3>'.e($row['legacy_name180']??'Sin grupo').'</h3><p>'.e(implode(' · ',array_column($group,'name'))).'</p><p>'.((int)$row['legacy_min180']?'Obligatorio':'Opcional').' · '.((int)$row['legacy_max180']>1?'Varias selecciones':'Una selección').'</p><a class="btn btn-light" href="?edit='.(int)$row['id'].'">Editar</a> '.r2_form('delete',(int)$row['id']).'<button class="btn btn-danger">Desactivar grupo</button></form></article>';}
    if(!$groups)echo '<p>Crea tu primer grupo para organizar las opciones de tus productos.</p>';echo '</section></div>';admin_shell_end();
}
