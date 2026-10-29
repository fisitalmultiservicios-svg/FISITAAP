<?php
declare(strict_types=1);

function fc16_schema(App $app):void {
    $app->db->exec('CREATE TABLE IF NOT EXISTS fisitaap_chat_actions_r16 (token_hash CHAR(64) PRIMARY KEY,tenant_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,branch_id BIGINT UNSIGNED NOT NULL,request_key CHAR(64) NOT NULL,operation LONGTEXT NOT NULL,before_hash CHAR(64) NOT NULL,state VARCHAR(16) NOT NULL DEFAULT "prepared",result LONGTEXT NULL,expires_at DATETIME NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY request_once(tenant_id,user_id,request_key),INDEX scope(tenant_id,user_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    fc16_support_schema($app);
    $app->exec('INSERT INTO settings(`key`,`value`) VALUES("fisichat_r16_ready","1") ON DUPLICATE KEY UPDATE `value`="1"');
}
function fc16_ready(App $app):bool {return $app->setting('fisichat_r16_ready','0')==='1';}
function fc16_json(mixed $value):string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function fc16_text(mixed $value,int $max=200,bool $required=false):string {
    if(!is_string($value)&&!is_numeric($value))throw new DomainException('Revisa el texto de los campos.');
    $text=trim((string)$value);if(($required&&$text==='')||mb_strlen($text)>$max)throw new DomainException('Completa el campo requerido y respeta su longitud máxima.');
    return $text;
}
function fc16_owner(App $app,array $user,array $tenant,array $branch,string $section='fisichat',string $action='view'):array {
    if(!fc16_ready($app)||!qa180_active($app,(int)$tenant['id']))throw new DomainException('Primero instala y activa la actualización de FISIChat para este negocio.');
    $fresh=$app->one('SELECT * FROM users WHERE id=? AND tenant_id=? AND role="tenant_admin" AND is_active=1 AND force_password_change=0',[$user['id'],$tenant['id']]);
    if(!$fresh||!$app->one('SELECT id FROM tenants WHERE id=? AND is_active=1',[$tenant['id']])||!$app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? AND is_active=1',[$branch['id']??0,$tenant['id']]))throw new DomainException('Esta acción requiere la cuenta dueña y una sucursal activa de su propio negocio.');
    qa180_permission($app,$fresh,'fisichat','view');qa180_permission($app,$fresh,$section,$action);require_module_v12($app,$tenant,section_module_v12($section));return $fresh;
}
function fc16_entities():array {
    return [
        'product'=>['table'=>'products','section'=>'productos','label'=>'Producto','link'=>'productos','fields'=>['name','category_id','price','sale_price','cost','tax_rate','description','status','unit_label','initial_stock']],
        'category'=>['table'=>'categories','section'=>'categorias','label'=>'Categoría','link'=>'categorias','fields'=>['name','sort_order','is_active']],
        'customer'=>['table'=>'users','section'=>'clientes','label'=>'Cliente','link'=>'clientes-buscar','fields'=>['name','phone']],
        'coupon'=>['table'=>'coupons','section'=>'cupones','label'=>'Cupón','link'=>'cupones','fields'=>['code','type','value','min_order','max_uses','starts_at','ends_at','is_active']],
        'delivery_zone'=>['table'=>'delivery_zones','section'=>'delivery','label'=>'Zona de envío','link'=>'delivery','fields'=>['name','price','min_order','is_active']],
        'supplier'=>['table'=>'suppliers','section'=>'proveedores','label'=>'Proveedor','link'=>'proveedores','fields'=>['name','code','tax_id','contact_name','phone','email','address','payment_terms_days','is_active']],
        'optional_group'=>['table'=>'fisitaap_r2_modifiers','section'=>'opcionales','label'=>'Grupo de opcionales','link'=>'opcionales','fields'=>['name','choices','required','multiple','product_ids','category_ids']],
        'tenant_settings'=>['table'=>'tenants','section'=>'configuracion','label'=>'Datos de la tienda','link'=>'configuracion','fields'=>['name','phone','whatsapp','email','address','business_hours','offline_message','primary_color','accent_color']],
        'website_text'=>['table'=>'fisitaap_r2_settings','section'=>'contenido','label'=>'Textos de la web','link'=>'contenido','fields'=>[]],
    ];
}
function fc16_snapshot(App $app,int $tid,string $entity,int $id,bool $lock=false):mixed {
    if($entity==='website_text')return array_column($app->all('SELECT setting_key,value FROM fisitaap_r2_settings WHERE tenant_id=? ORDER BY setting_key'.($lock?' FOR UPDATE':''),[$tid]),'value','setting_key');
    if($entity==='tenant_settings')return $app->one('SELECT * FROM tenants WHERE id=?'.($lock?' FOR UPDATE':''),[$tid]);
    if(!$id)return null;
    $cfg=fc16_entities()[$entity];
    if($entity==='customer')return $app->one('SELECT u.id,u.name,u.phone FROM users u JOIN tenant_customers c ON c.user_id=u.id AND c.tenant_id=? WHERE u.id=?'.($lock?' FOR UPDATE':''),[$tid,$id]);
    if($entity==='optional_group')return qa180_optional_members($app,$tid,$id);
    return $app->one('SELECT * FROM '.$cfg['table'].' WHERE tenant_id=? AND id=?'.($lock?' FOR UPDATE':''),[$tid,$id]);
}
function fc16_operation(App $app,array $tenant,array $user,array $branch,array $op):array {
    $entity=$op['entity']??'';$action=$op['action']??'';$id=$op['record_id']??0;$data=$op['data']??null;$all=fc16_entities();
    if(!is_string($entity)||!isset($all[$entity])||!in_array($action,['create','update','activate','deactivate'],true)||!is_array($data)||array_is_list($data)&&$data!==[])throw new DomainException('Ese cambio no está disponible en el asistente.');
    if(!is_int($id)&&!ctype_digit((string)$id))throw new DomainException('Selecciona un registro válido.');$id=(int)$id;
    $cfg=$all[$entity];fc16_owner($app,$user,$tenant,$branch,$cfg['section'],'write');$tid=(int)$tenant['id'];
    if(in_array($entity,['tenant_settings','website_text'],true)){if($action!=='update')throw new DomainException('Estos datos se actualizan; no se crean otras tiendas.');$id=$tid;}
    elseif(($action==='create'&&$id)||($action!=='create'&&!$id))throw new DomainException('Identifica el registro que quieres editar.');
    if($entity==='customer'&&$action!=='create')throw new DomainException('Desde este chat puedes registrar clientes; sus datos de acceso y direcciones se gestionan en su ficha.');
    if($entity==='optional_group'&&$action!=='create')throw new DomainException('Puedes crear el grupo desde el chat. Para conservar las identidades de opciones históricas, edita grupos existentes en Opcionales.');
    $before=fc16_snapshot($app,$tid,$entity,$id);if($action!=='create'&&$before===null)throw new DomainException('El registro no pertenece a tu tienda o ya no está disponible.');
    $allowed=$cfg['fields'];if($entity==='website_text')$allowed=array_merge(array_keys(r2_landing_fields()),['hero_button_label','catalog_button_label','catalog_intro_title','catalog_intro_text','catalog_intro_title_2','catalog_intro_text_2','catalog_intro_title_3','catalog_intro_text_3']);
    if(array_diff(array_keys($data),$allowed))throw new DomainException('La propuesta contiene campos que este asistente no puede modificar.');
    if(in_array($action,['activate','deactivate'],true)){
        if(!in_array($entity,['product','category','coupon','delivery_zone','supplier'],true))throw new DomainException('Esa activación se realiza desde su pantalla de administración.');
        qa180_permission($app,$user,$cfg['section'],$action==='deactivate'?'delete':'write');$data=$entity==='product'?['status'=>$action==='activate'?'active':'hidden']:['is_active'=>$action==='activate'?1:0];
    }
    if(!$data)throw new DomainException('Indica qué campos quieres cambiar.');$clean=[];
    foreach($data as $key=>$value){
        if(in_array($key,['choices','product_ids','category_ids'],true)){if(!is_array($value)||count($value)>50)throw new DomainException('Usa hasta 50 opciones o asignaciones.');$clean[$key]=$value;continue;}
        if(in_array($key,['price','sale_price','cost','tax_rate','value','min_order','initial_stock'],true)){$clean[$key]=r2_number($value,0,$key==='tax_rate'?100:999999999);continue;}
        if(in_array($key,['category_id','sort_order','max_uses','payment_terms_days'],true)){$clean[$key]=(int)r2_number($value,$key==='category_id'||$key==='max_uses'?1:0,100000000);if((float)$value!==floor((float)$value))throw new DomainException('Este campo requiere un número entero.');continue;}
        if(in_array($key,['is_active','required','multiple'],true)){if(!in_array($value,[true,false,1,0],true))throw new DomainException('Revisa la selección de activación.');$clean[$key]=$value?1:0;continue;}
        $clean[$key]=fc16_text($value,in_array($key,['description','address','offline_message'],true)||$entity==='website_text'?3000:200);
        if(in_array($key,['primary_color','accent_color'],true)&&!preg_match('/^#[a-f0-9]{6}$/iD',$clean[$key]))throw new DomainException('Elige un color hexadecimal válido.');
        if($key==='email'&&$clean[$key]!==''&&!filter_var($clean[$key],FILTER_VALIDATE_EMAIL))throw new DomainException('Escribe un correo válido.');
    }
    $required=match($entity){'product'=>['name','category_id','price'],'category','delivery_zone','supplier'=>['name'],'customer'=>['name','phone'],'coupon'=>['code','type','value'],'optional_group'=>['name','choices'],default=>[]};
    if($action==='create')foreach($required as $key)if(!array_key_exists($key,$clean)||$clean[$key]===''||$clean[$key]===[])throw new DomainException('Falta '.$key.' para preparar el cambio.');
    if(isset($clean['name'])&&($clean['name']===''||mb_strlen($clean['name'])>120))throw new DomainException('El nombre requiere entre 1 y 120 caracteres.');
    if($entity==='product'){
        if(isset($clean['category_id'])&&!$app->one('SELECT id FROM categories WHERE tenant_id=? AND id=? AND is_active=1',[$tid,$clean['category_id']]))throw new DomainException('Selecciona una categoría activa de tu tienda.');
        if(isset($clean['status'])&&!in_array($clean['status'],['active','hidden','sold_out'],true))throw new DomainException('Estado de producto no disponible.');
        if($action!=='create'&&(isset($clean['initial_stock'])||isset($clean['unit_label'])))throw new DomainException('El inventario y el cambio de unidad de productos existentes se gestionan en su pantalla para conservar sus movimientos.');
        if(isset($clean['price'])||isset($clean['sale_price'])||isset($clean['cost']))qa180_permission($app,$user,'productos','price');
        if(isset($clean['initial_stock'])){fc16_owner($app,$user,$tenant,$branch,'inventario','write');$q=r11_quantity_product(['unit_label'=>$clean['unit_label']??'unidad']);r11_quantity($q,$clean['initial_stock'],true);}
        if($action==='create')$clean+=['status'=>'active','unit_label'=>'unidad','tax_rate'=>0,'cost'=>0,'description'=>''];
    }
    if($entity==='coupon'){
        $v=$before?array_replace($before,$clean):$clean;
        if(!in_array($v['type']??'', ['percent','fixed'],true)||(float)($v['value']??0)<=0||(($v['type']??'')==='percent'&&(float)$v['value']>100))throw new DomainException('El cupón requiere monto positivo; el porcentaje no puede superar 100.');
        if(isset($clean['code'])){$clean['code']=mb_strtoupper($clean['code']);if(!preg_match('/^[A-Z0-9_-]{2,50}$/D',$clean['code']))throw new DomainException('El código de cupón usa de 2 a 50 letras, números, guion o guion bajo.');if($app->one('SELECT id FROM coupons WHERE tenant_id=? AND code=? AND id<>?',[$tid,$clean['code'],$id]))throw new DomainException('Ya existe un cupón con ese código.');}
        foreach(['starts_at','ends_at'] as $key)if(array_key_exists($key,$clean)){if($clean[$key]==='')$clean[$key]=null;else{$date=DateTimeImmutable::createFromFormat('!Y-m-d',$clean[$key]);if(!$date||$date->format('Y-m-d')!==$clean[$key])throw new DomainException('Usa una fecha válida AAAA-MM-DD.');$clean[$key].=$key==='ends_at'?' 23:59:59':' 00:00:00';}}
        $v=$before?array_replace($before,$clean):$clean;if(!empty($v['starts_at'])&&!empty($v['ends_at'])&&$v['starts_at']>$v['ends_at'])throw new DomainException('La fecha final debe ser posterior a la inicial.');
    }
    if($entity==='customer')$clean['phone']=op180_phone($clean['phone']);
    if($entity==='optional_group'){
        if($app->one('SELECT id FROM fisitaap_r2_modifiers WHERE tenant_id=? AND kind="optional" AND is_active=1 AND legacy_name180=?',[$tid,$clean['name']]))throw new DomainException('Ese grupo ya existe. Puedes editarlo en Opcionales.');
        $names=[];foreach($clean['choices'] as $choice){$choice=fc16_text($choice,120,true);$key=mb_strtolower($choice);if(isset($names[$key]))throw new DomainException('No repitas opciones dentro del grupo.');$names[$key]=$choice;}$clean['choices']=array_values($names);
        foreach(['product_ids'=>'products','category_ids'=>'categories'] as $key=>$table){$clean[$key]=array_values(array_unique(array_map('intval',$clean[$key]??[])));foreach($clean[$key] as $rid)if(!$app->one('SELECT id FROM '.$table.' WHERE id=? AND tenant_id=?',[$rid,$tid]))throw new DomainException('Una asignación no pertenece a tu tienda.');}
    }
    return ['entity'=>$entity,'action'=>$action,'record_id'=>$id,'data'=>$clean];
}
function fc16_change_view(array $op,mixed $before,string $token):array {
    $labels=['name'=>'Nombre','category_id'=>'Categoría','price'=>'Precio','sale_price'=>'Precio de oferta','cost'=>'Costo','tax_rate'=>'Impuesto (%)','description'=>'Descripción','status'=>'Estado','unit_label'=>'Unidad','initial_stock'=>'Existencia inicial','code'=>'Código','type'=>'Tipo','value'=>'Valor','min_order'=>'Compra mínima','max_uses'=>'Usos máximos','starts_at'=>'Desde','ends_at'=>'Hasta','is_active'=>'Activo','phone'=>'Teléfono','whatsapp'=>'WhatsApp','email'=>'Correo','address'=>'Dirección','business_hours'=>'Horario','offline_message'=>'Mensaje fuera de servicio','primary_color'=>'Color principal','accent_color'=>'Color secundario','choices'=>'Opciones','required'=>'Obligatorio','multiple'=>'Varias selecciones','product_ids'=>'Productos asignados','category_ids'=>'Categorías asignadas'];
    $fields=[];foreach($op['data'] as $key=>$value)$fields[]=['label'=>$labels[$key]??(r2_landing_fields()[$key]??$key),'before'=>is_array($before)?($before[$key]??null):null,'after'=>$value];
    return ['token'=>$token,'title'=>($op['action']==='create'?'Crear ':($op['action']==='deactivate'?'Desactivar ':'Actualizar ')).fc16_entities()[$op['entity']]['label'],'name'=>$op['data']['name']??$op['data']['code']??(is_array($before)?($before['name']??$before['code']??''):''),'fields'=>$fields,'expires_in'=>900];
}
function fc16_human_view(App $app,array $tenant,array $op,mixed $before,string $token,string $branchName=''):array {
    $view=fc16_change_view($op,$before,$token);$view['scope']='Solo para '.$tenant['name'].(isset($op['data']['initial_stock'])?' · Existencia inicial en '.$branchName:'');$keys=array_keys($op['data']);
    $labels=['sort_order'=>'Orden','printer_target'=>'Punto de impresión','contact_name'=>'Contacto','tax_id'=>'Identificación','payment_terms_days'=>'Plazo de pago (días)','catalog_intro_title'=>'Slider · título de imagen 1','catalog_intro_text'=>'Slider · texto de imagen 1','catalog_intro_title_2'=>'Slider · título de imagen 2','catalog_intro_text_2'=>'Slider · texto de imagen 2','catalog_intro_title_3'=>'Slider · título de imagen 3','catalog_intro_text_3'=>'Slider · texto de imagen 3'];
    foreach($view['fields'] as $i=>&$field){$key=$keys[$i];if(isset($labels[$key]))$field['label']=$labels[$key];
        foreach(['before','after'] as $side){$v=$field[$side];if($v===null)continue;
            if(in_array($key,['price','sale_price','cost','min_order'],true)||($key==='value'&&($op['data']['type']??($before['type']??''))==='fixed'))$field[$side]=money((float)$v);
            if($key==='category_id')$field[$side]=$app->one('SELECT name FROM categories WHERE tenant_id=? AND id=?',[$tenant['id'],(int)$v])['name']??'Categoría no disponible';
            if($key==='status')$field[$side]=['active'=>'Publicado','hidden'=>'Oculto','sold_out'=>'Agotado'][$v]??$v;
            if($key==='type')$field[$side]=['percent'=>'Porcentaje','fixed'=>'Monto'][$v]??$v;
            if(in_array($key,['is_active','required','multiple'],true))$field[$side]=$v?'Sí':'No';
            if(in_array($key,['product_ids','category_ids'],true)&&is_array($v)){$table=$key==='product_ids'?'products':'categories';$names=[];foreach($v as $id)$names[]=$app->one('SELECT name FROM '.$table.' WHERE tenant_id=? AND id=?',[$tenant['id'],(int)$id])['name']??'No disponible';$field[$side]=$names;}
        }
    }unset($field);return $view;
}
function fc16_prepare(App $app,array $tenant,array $user,array $branch,array $raw,string $requestKey):array {
    $op=fc16_operation($app,$tenant,$user,$branch,$raw);$before=fc16_snapshot($app,(int)$tenant['id'],$op['entity'],$op['record_id']);$token=hash_hmac('sha256','fc16:'.$tenant['id'].':'.$user['id'].':'.$branch['id'].':'.$requestKey,$app->config['app_key']);
    $prior=$app->one('SELECT operation,state,before_hash,expires_at<=NOW() expired FROM fisitaap_chat_actions_r16 WHERE token_hash=?',[hash('sha256',$token)]);if($prior){if($prior['state']!=='prepared'||$prior['expired']||!hash_equals($prior['operation'],fc16_json($op))||!hash_equals($prior['before_hash'],hash('sha256',fc16_json($before))))throw new DomainException('Esa propuesta cambió, venció o ya fue procesada. Solicita una revisión nueva.');return fc16_human_view($app,$tenant,$op,$before,$token,$branch['name']);}
    $app->exec('INSERT INTO fisitaap_chat_actions_r16(token_hash,tenant_id,user_id,branch_id,request_key,operation,before_hash,expires_at) VALUES(?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))',[hash('sha256',$token),$tenant['id'],$user['id'],$branch['id'],hash('sha256',$requestKey),fc16_json($op),hash('sha256',fc16_json($before))]);
    return fc16_human_view($app,$tenant,$op,$before,$token,$branch['name']);
}
function fc16_save(App $app,array $tenant,array $user,array $branch,array $op):array {
    $tid=(int)$tenant['id'];$entity=$op['entity'];$action=$op['action'];$id=$op['record_id'];$data=$op['data'];$cfg=fc16_entities()[$entity];$name=$data['name']??$data['code']??'';
    if($entity==='website_text'){foreach($data as $key=>$value)r2_set($app,$tid,$key,$value);$id=$tid;}
    elseif($entity==='optional_group'){
        $family=0;foreach($data['choices'] as $choice){$app->exec('INSERT INTO fisitaap_r2_modifiers(tenant_id,kind,name,choices_json,required,multiple,legacy_name180,legacy_min180,legacy_max180) VALUES(?,"optional",?,?,0,0,?,?,?)',[$tid,$choice,fc16_json([['name'=>$choice,'price'=>0]]),$data['name'],!empty($data['required'])?1:0,!empty($data['multiple'])?count($data['choices']):1]);$mid=(int)$app->db->lastInsertId();$family=$family?:$mid;$app->exec('UPDATE fisitaap_r2_modifiers SET legacy_family180=? WHERE id=? AND tenant_id=?',[$family,$mid,$tid]);foreach(['product_ids'=>['fisitaap_modifier_products180','product_id'],'category_ids'=>['fisitaap_modifier_categories180','category_id']] as $key=>[$table,$fk])foreach($data[$key] as $rid)$app->exec('INSERT INTO '.$table.'(tenant_id,modifier_id,'.$fk.') VALUES(?,?,?)',[$tid,$mid,$rid]);}$id=$family;qa180_modifier_sync($app,$tid);
    }elseif($entity==='customer'){$customer=op180_contact($app,$tenant,$data['name'],$data['phone']);$id=(int)$customer['id'];$name=$customer['name'];}
    elseif($action==='create'){
        $stock=$data['initial_stock']??null;unset($data['initial_stock']);
        if($entity==='product'||$entity==='category'){$base=slugify($data['name']);$slug=$base;$n=2;while($app->one('SELECT id FROM '.$cfg['table'].' WHERE tenant_id=? AND slug=?',[$tid,$slug]))$slug=$base.'-'.$n++;$data['slug']=$slug;}
        if($entity==='product'){$q=r11_quantity_product(['unit_label'=>$data['unit_label']]);$data+=['min_qty'=>$q['min_qty'],'qty_step'=>$q['qty_step'],'decimals'=>$q['decimals'],'track_inventory'=>$stock!==null?1:0];}
        $data=['tenant_id'=>$tid]+$data;$cols=array_keys($data);$app->exec('INSERT INTO '.$cfg['table'].' (`'.implode('`,`',$cols).'`) VALUES('.implode(',',array_fill(0,count($cols),'?')).')',array_values($data));$id=(int)$app->db->lastInsertId();
        if($entity==='product'){
            $app->exec('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(?,?,1)',[$branch['id'],$id]);
            if($stock!==null){$app->exec('INSERT INTO product_inventory(tenant_id,branch_id,product_id,quantity,track_stock,avg_cost,last_cost) VALUES(?,?,?,0,1,?,?)',[$tid,$branch['id'],$id,$data['cost'],$data['cost']]);if($stock>0)stock_change_v14($app,$tid,(int)$branch['id'],$id,$stock,'adjustment','fisichat',null,(int)$user['id'],'Existencia inicial confirmada en FISIChat',(float)$data['cost']);}
        }
    }else{
        $sets=[];foreach(array_keys($data) as $key)$sets[]='`'.$key.'`=?';$args=array_values($data);
        if($entity==='tenant_settings'){$args[]=$tid;$where='id=?';}else{$args[]=$id;$args[]=$tid;$where='id=? AND tenant_id=?';}
        $app->exec('UPDATE '.$cfg['table'].' SET '.implode(',',$sets).' WHERE '.$where,$args);if($entity==='product'&&isset($data['category_id']))qa180_modifier_sync($app,$tid);
    }
    qa180_audit($app,$user,'fisichat_'.$op['action'],$entity,$id,'FISIChat: cambio confirmado en '.$cfg['label'].($name!==''?' · '.$name:''));op180_touch($app,$tid);
    return ['id'=>$id,'entity'=>$entity,'reply'=>'Cambio guardado: '.$cfg['label'].($name!==''?' «'.$name.'»':'').'. Puedes revisarlo en '.$cfg['link'].'.','url'=>url('admin/'.$cfg['link'].($entity==='tenant_settings'||$entity==='website_text'?'':'?'.($entity==='customer'?'customer':'edit').'='.$id))];
}
function fc16_confirm(App $app,array $tenant,array $user,array $branch,string $token):array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new DomainException('Prepara y revisa el cambio antes de confirmarlo.');fc16_owner($app,$user,$tenant,$branch);
    $app->db->beginTransaction();try{
        $row=$app->one('SELECT *,expires_at<=NOW() expired FROM fisitaap_chat_actions_r16 WHERE token_hash=? AND tenant_id=? AND user_id=? AND branch_id=? FOR UPDATE',[hash('sha256',$token),$tenant['id'],$user['id'],$branch['id']]);if(!$row)throw new DomainException('La propuesta no pertenece a esta cuenta o sucursal.');
        if($row['state']==='applied'){$result=json_decode($row['result'],true,32,JSON_THROW_ON_ERROR);$app->db->commit();return $result;}
        if($row['state']!=='prepared'||(bool)$row['expired'])throw new DomainException('La propuesta venció o fue descartada. Solicita una nueva.');
        $op=json_decode($row['operation'],true,32,JSON_THROW_ON_ERROR);$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);$before=fc16_snapshot($app,(int)$tenant['id'],$op['entity'],(int)$op['record_id'],true);
        if(!hash_equals($row['before_hash'],hash('sha256',fc16_json($before))))throw new DomainException('El registro cambió después de la propuesta. Solicita una revisión nueva para conservar esos cambios.');
        // Revalidate permissions, foreign IDs and values immediately before the write.
        $raw=$op;if($op['entity']==='coupon')foreach(['starts_at','ends_at'] as $key)if(array_key_exists($key,$raw['data']))$raw['data'][$key]=empty($raw['data'][$key])?'':substr($raw['data'][$key],0,10);
        fc16_operation($app,$tenant,$user,$branch,$raw);$result=fc16_save($app,$tenant,$user,$branch,$op);$app->exec('UPDATE fisitaap_chat_actions_r16 SET state="applied",result=? WHERE token_hash=?',[fc16_json($result),$row['token_hash']]);$app->db->commit();return $result;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function fc16_tools(array $user):array {
    if(($user['role']??'')!=='tenant_admin')return [];$tools=array_slice(fisichat_tools($user),0,2);
    $tools[]=['type'=>'function','name'=>'prepare_store_change','description'=>'Prepara UN cambio de la tienda actual, sin ejecutarlo. El usuario revisa los valores y pulsa Confirmar cambio. Para crear productos necesitas nombre, categoría propia y precio; busca categorías con inspect_business. No uses campos ni IDs de otra tienda. Puedes crear categorías, productos, clientes, cupones, zonas, proveedores y grupos de opcionales; actualizar productos, cupones, contacto/colores y textos de su web. No cobra, cancela pedidos, cambia permisos, tiendas, claves ni configuraciones técnicas.','strict'=>false,'parameters'=>['type'=>'object','properties'=>['entity'=>['type'=>'string','enum'=>array_keys(fc16_entities())],'action'=>['type'=>'string','enum'=>['create','update','activate','deactivate']],'record_id'=>['type'=>'integer','minimum'=>0],'data'=>['type'=>'object','additionalProperties'=>true]],'required'=>['entity','action','record_id','data']]];return $tools;
}
function fc16_read(App $app,array $tenant,array $user,array $branch,string $name,array $a):array {
    $section='reportes';
    if($name==='inspect_business'){
        $resource=(string)($a['resource']??'');if($resource==='business_snapshot')return ['status'=>'ok','data'=>r2_chat_context($app,'dueno',$tenant,$user)];
        $section=['products'=>'productos','categories'=>'categorias','customers'=>'clientes','branches'=>'sucursales','staff'=>'usuarios','suppliers'=>'proveedores','coupons'=>'cupones','delivery_zones'=>'delivery','promotions'=>'contenido','loyalty_programs'=>'fidelizacion','recent_sales'=>'ventas','recent_orders'=>'pedidos','open_shifts'=>'turnos','open_tickets'=>'ventas','settings'=>'configuracion'][$resource]??'';
        if($section==='')throw new DomainException('Consulta no disponible.');
    }elseif($name==='analyze_business'){
        $kind=(string)($a['analysis']??'');$section=str_starts_with($kind,'inventory')?'inventario':(str_starts_with($kind,'orders')?'pedidos':(str_starts_with($kind,'purchases')?'compras':(['receivables_summary'=>'cxc','payables_summary'=>'cxp','delivery_summary'=>'repartidor','loyalty_summary'=>'fidelizacion','new_customers'=>'clientes','top_customers'=>'clientes'][$kind]??'ventas')));fc16_owner($app,$user,$tenant,$branch,'reportes');
    }else throw new DomainException('Herramienta no disponible.');
    fc16_owner($app,$user,$tenant,$branch,$section);return $name==='inspect_business'?fisichat_inspect($app,$tenant,$user,$branch,$a):fisichat_analyze($app,$tenant,$user,$branch,$a);
}
function fc16_owner_reply(App $app,array $tenant,array $user,array $branch,array $context,array $history,string $message,array $cfg,string $requestKey,string $fallback):array {
    $tools=fc16_ready($app)?fc16_tools($user):[];$input=array_map(static fn($h)=>['role'=>$h['role'],'content'=>$h['text']],array_values(array_filter($history,static fn($h)=>!empty($h['in_scope']))));
    $input[]=['role'=>'user','content'=>fc16_json(['datos_autorizados'=>$context,'consulta'=>$message])];
    $instruction=fc_domain_instructions('dueno',$context).' Tu mundo es solamente esta tienda. Propón ideas de ventas, organización, catálogo, atención y textos de su web basándote en sus productos; identifica las ideas como propuestas. No vendas FISITAAP al dueño ni hables de otras tiendas. '.fisichat_intent_guidance().' Fecha local: '.date('Y-m-d').'. Sucursal activa: '.$branch['name'].'. '.fisichat_owner_capabilities($app,$tenant).' Para consultas concretas usa inspect_business/analyze_business. Para crear o editar algo usa prepare_store_change: nunca ejecuta, solo prepara una revisión. No afirmes que guardaste cambios. El botón de confirmación del sistema es quien guarda. No pidas confirmed=true. Si faltan datos pregunta solo los faltantes. Una guía no prepara escrituras. Producto: name, category_id, price; opcionales: name, choices (lista de textos), required, multiple, product_ids/category_ids propios. Cupón: code, type percent/fixed, value; fechas AAAA-MM-DD. tenant_settings solo datos de contacto/horario y colores. website_text admite los nombres de campos del diseño (hero_title, hero_text, about_title, about_text, about_promise_1/2/3, catalog_intro_title/text y sufijos _2/_3). Nunca SQL, claves, otra tienda, permisos, usuarios administrativos, cobros, anular pedidos o movimientos de caja. Si no existe una herramienta segura, explica el límite y ofrece su pantalla real.';
    $deadline=microtime(true)+44;
    for($round=0;$round<4;$round++){
        $remaining=(int)floor($deadline-microtime(true));if($remaining<4)break;$cfg['timeout']=min(20,$remaining);
        $payload=['model'=>$cfg['model'],'instructions'=>$instruction,'input'=>$input,'max_output_tokens'=>800,'store'=>false];if($tools)$payload+=['tools'=>$tools,'tool_choice'=>'auto','parallel_tool_calls'=>false];
        $response=fisichat_call($cfg,$payload);$calls=array_values(array_filter($response['output']??[],static fn($item)=>($item['type']??'')==='function_call'));
        if(!$calls){$reply=fc_domain_answer(fisichat_text($response),'dueno',$context,$fallback);if(preg_match('/\b(?:he (?:creado|guardado|actualizado|modificado)|ya (?:cree|guarde|actualice)|creado correctamente|cambio (?:aplicado|guardado))\b/u',fc_domain_text($reply)))$reply='Para aplicar cambios primero preparo una propuesta con los valores exactos. Se guardan únicamente después de tu confirmación.';return ['reply'=>$reply];}
        foreach($response['output'] as $item)$input[]=$item;
        foreach(array_slice($calls,0,5) as $call){
            try{
                $raw=(string)($call['arguments']??'{}');if(strlen($raw)>16000)throw new DomainException('El cambio es demasiado grande. Divídelo en operaciones más pequeñas.');$args=json_decode($raw,true,24,JSON_THROW_ON_ERROR);if(!is_array($args))throw new DomainException('Argumentos no disponibles.');
                if(($call['name']??'')==='prepare_store_change'){$proposal=fc16_prepare($app,$tenant,$user,$branch,$args,$requestKey);return ['reply'=>'Preparé esta propuesta para tu tienda. Revisa los valores; todavía no he cambiado nada. Pulsa Confirmar cambio para guardarla.','store_proposal'=>$proposal];}
                $out=fc16_read($app,$tenant,$user,$branch,(string)($call['name']??''),$args);
            }catch(DomainException $error){$out=['status'=>'not_available','message'=>$error->getMessage()];}catch(Throwable $error){fisichat_failure($error);$out=['status'=>'error','message'=>'No se pudo completar esta herramienta. No se ha confirmado ningún cambio.'];}
            $input[]=['type'=>'function_call_output','call_id'=>$call['call_id']??'','output'=>fc16_json($out)];
        }
    }
    return ['reply'=>'No pude terminar esa consulta. Podemos dividirla en una operación concreta de tu tienda; no se ha confirmado ningún cambio.'];
}
