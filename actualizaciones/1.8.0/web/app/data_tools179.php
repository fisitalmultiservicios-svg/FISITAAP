<?php
declare(strict_types=1);

// Tools for an imported migration copy and disposable demo tenants. Never run DDL
// or cleanup on ordinary business requests. Temporary keep sets contain PKs only.
function fm_id(string $value):string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$value)) throw new RuntimeException('Identificador de base de datos no compatible.');
    return '`'.$value.'`';
}
function fm_meta(App $app):array {
    $meta=[];
    foreach($app->all('SELECT t.TABLE_NAME,t.ENGINE,c.COLUMN_NAME,c.COLUMN_KEY,c.EXTRA FROM information_schema.TABLES t JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_TYPE="BASE TABLE" ORDER BY t.TABLE_NAME,c.ORDINAL_POSITION') as $r){
        $t=$r['TABLE_NAME'];fm_id($t);$meta[$t]['engine']=$r['ENGINE'];$meta[$t]['cols'][]=$r['COLUMN_NAME'];
        if($r['COLUMN_KEY']==='PRI')$meta[$t]['pk'][]=$r['COLUMN_NAME'];
        if(str_contains($r['EXTRA'],'auto_increment'))$meta[$t]['auto']=$r['COLUMN_NAME'];
    }
    foreach($meta as $t=>$m)if(empty($m['pk'])||$m['engine']!=='InnoDB')throw new RuntimeException('La tabla '.$t.' necesita revisión: se requiere InnoDB y clave primaria para hacer esta operación sin perder relaciones.');
    $refs=[];
    foreach($app->all('SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION') as $r){
        $key=$r['TABLE_NAME'].':'.$r['CONSTRAINT_NAME'];$refs[$key]['child']=$r['TABLE_NAME'];$refs[$key]['parent']=$r['REFERENCED_TABLE_NAME'];$refs[$key]['pairs'][$r['COLUMN_NAME']]=$r['REFERENCED_COLUMN_NAME'];
    }
    $manual=['tenant_id'=>'tenants','branch_id'=>'branches','user_id'=>'users','customer_id'=>'users','created_by'=>'users','opened_by'=>'users','closed_by'=>'users','author_id'=>'users','product_id'=>'products','linked_product_id'=>'products','combo_id'=>'products','category_id'=>'categories','table_id'=>'restaurant_tables','room_id'=>'fisitaap_r2_rooms','sector_id'=>'fisitaap_r3_sectors','modifier_id'=>'fisitaap_r2_modifiers','role_id'=>'fisitaap_r1_roles','device_id'=>'fisitaap_r2_devices','sale_id'=>'sales','order_id'=>'orders','shift_id'=>'pos_shifts','purchase_id'=>'purchases','reward_id'=>'loyalty_rewards','card_id'=>'fisitaap_r3_gift_cards'];
    foreach($meta as $table=>$m)foreach($m['cols'] as $col){
        $parent=$manual[$col]??null;
        if($col==='group_id')$parent=$table==='restaurant_tables'?'pos_table_groups':'product_option_groups';
        if(!$parent||!isset($meta[$parent])||$table===$parent)continue;
        $known=false;foreach($refs as $ref)if($ref['child']===$table&&isset($ref['pairs'][$col])){$known=true;break;}
        if(!$known)$refs['virtual:'.$table.':'.$col]=['child'=>$table,'parent'=>$parent,'pairs'=>[$col=>'id'],'virtual'=>true];
    }
    if(isset($meta['driver_branches'])){
        foreach(['branch_id'=>'branches','user_id'=>'users'] as $column=>$parent){
            $found=false;
            foreach($refs as $ref)if($ref['child']==='driver_branches'&&$ref['parent']===$parent&&$ref['pairs']===[$column=>'id']){$found=true;break;}
            if(!$found)throw new RuntimeException('La estructura de driver_branches no corresponde a la relación revisada entre users.id y branches.id. No se modificaron datos.');
        }
    }
    return ['tables'=>$meta,'refs'=>array_values($refs)];
}
function fm_match(string $a,string $b,array $pairs):string {
    return implode(' AND ',array_map(static fn($c,$p)=>$a.'.'.fm_id($c).'='.$b.'.'.fm_id($p),array_keys($pairs),array_values($pairs)));
}
function fm_pkmatch(string $a,string $b,array $pk):string{return fm_match($a,$b,array_combine($pk,$pk));}
function fm_sets(App $app,array $meta):array {
    $sets=[];$prefix='fm_'.bin2hex(random_bytes(4)).'_';
    try{foreach($meta['tables'] as $table=>$m){
        $temp=$prefix.count($sets);$pk=implode(',',array_map('fm_id',$m['pk']));$sets[$table]=$temp;
        $app->db->exec('CREATE TEMPORARY TABLE '.fm_id($temp).' ENGINE=InnoDB AS SELECT '.$pk.' FROM '.fm_id($table).' WHERE 1=0');
        $app->db->exec('ALTER TABLE '.fm_id($temp).' ADD PRIMARY KEY ('.$pk.')');
    }}catch(Throwable $error){fm_drop_sets($app,$sets);throw $error;}
    return $sets;
}
function fm_drop_sets(App $app,array $sets):void {
    if($sets)$app->db->exec('DROP TEMPORARY TABLE IF EXISTS '.implode(',',array_map('fm_id',array_values($sets))));
}
function fm_assert_delete_scope(App $app,array $meta,array $sets):void {
    // A retained record must never lose a parent during disposable demo cleanup.
    foreach($meta['refs'] as $ref){
        $child=$ref['child'];$parent=$ref['parent'];
        $sql='SELECT 1 FROM '.fm_id($child).' c JOIN '.fm_id($parent).' p ON '.fm_match('c','p',$ref['pairs']).' JOIN '.fm_id($sets[$parent]).' pk ON '.fm_pkmatch('p','pk',$meta['tables'][$parent]['pk']).' LEFT JOIN '.fm_id($sets[$child]).' ck ON '.fm_pkmatch('c','ck',$meta['tables'][$child]['pk']).' WHERE ck.'.fm_id($meta['tables'][$child]['pk'][0]).' IS NULL LIMIT 1';
        if($app->one($sql))throw new RuntimeException('La copia temporal tiene referencias desde otros datos. Se canceló su limpieza para conservar esas relaciones.');
    }
}
function fm_mark(App $app,array $meta,array $sets,string $table,string $where):int {
    $pk=implode(',',array_map(static fn($c)=>'b.'.fm_id($c),$meta['tables'][$table]['pk']));
    return $app->db->exec('INSERT IGNORE INTO '.fm_id($sets[$table]).' SELECT '.$pk.' FROM '.fm_id($table).' b WHERE '.$where);
}
function fm_related(App $app,array $meta,array $sets,array $ref,bool $up,string $extra='1=1'):int {
    $target=$up?$ref['parent']:$ref['child'];$other=$up?$ref['child']:$ref['parent'];
    $join=$up?fm_match('o','b',$ref['pairs']):fm_match('b','o',$ref['pairs']);
    return fm_mark($app,$meta,$sets,$target,'('.$extra.') AND EXISTS(SELECT 1 FROM '.fm_id($other).' o JOIN '.fm_id($sets[$other]).' k ON '.fm_pkmatch('o','k',$meta['tables'][$other]['pk']).' WHERE '.$join.')');
}
// Legacy driver links belong to a branch. Following user_id downward would also
// retain that shared driver's links to businesses excluded from the migration.
const FM_OWNERS=['branch_products'=>'branch_id','driver_branches'=>'branch_id','driver_profiles'=>'user_id','product_options'=>'group_id','order_items'=>'order_id','loyalty_ledger'=>'account_id','loyalty_rewards'=>'account_id','driver_affiliation_branches'=>'affiliation_id','sale_items'=>'sale_id','purchase_items'=>'purchase_id','ar_payments'=>'receivable_id','ap_payments'=>'payable_id','pos_ticket_items'=>'ticket_id','pos_payments'=>'ticket_id','fisitaap_r1_permissions'=>'role_id','fisitaap_r2_modifier_groups'=>'modifier_id','fisitaap_r2_combo_items'=>'combo_id','fisitaap_r2_floor'=>'table_id','fisitaap_r3_room_versions'=>'room_id','fisitaap_r2_snapshots'=>'device_id','fisitaap_r2_synced_sales'=>'device_id','fisitaap_r2_shift_links'=>'device_id','customer_addresses'=>'user_id','password_resets'=>'user_id','email_verifications'=>'user_id','plan_modules'=>'plan_id'];
function fm_keep(App $app,array $meta,array $sets,array $ids):void {
    $list=implode(',',array_map('intval',$ids));if(!$list)throw new RuntimeException('No se seleccionó ningún negocio.');
    foreach($meta['tables'] as $table=>$m){
        if($table==='tenants')$where='b.id IN('.$list.')';
        elseif($table==='users')$where='b.role="master" OR b.tenant_id IN('.$list.')';
        elseif(in_array('tenant_id',$m['cols'],true)){
            $where='b.tenant_id IN('.$list.')';
            if($table==='fisitaap_r2_settings')$where.=' OR b.tenant_id=0';
            if(in_array($table,['posts','contacts'],true))$where.=' OR b.tenant_id IS NULL';
            if(in_array($table,['audit_logs','fisichat_audit'],true))$where.=' OR (b.tenant_id IS NULL AND (b.user_id IS NULL OR b.user_id IN(SELECT id FROM users WHERE role="master")))';
        }elseif(in_array($table,['settings','plans','plan_modules'],true))$where='1=1';
        elseif(isset(FM_OWNERS[$table])||$table==='rate_limits')$where='1=0';
        else throw new RuntimeException('La tabla '.$table.' no tiene un alcance conocido. No se borró nada: solicita revisión de esta tabla antes de filtrar la copia.');
        fm_mark($app,$meta,$sets,$table,$where);
    }
    for($i=0;$i<=count($meta['tables']);$i++){
        $added=0;
        foreach($meta['refs'] as $ref){
            $parent=$ref['parent'];$m=$meta['tables'][$parent];
            $allowed=$parent==='tenants'?'b.id IN('.$list.')':(in_array('tenant_id',$m['cols'],true)?'(b.tenant_id IN('.$list.') OR b.tenant_id IS NULL)':'1=1');
            $added+=fm_related($app,$meta,$sets,$ref,true,$allowed);
            if((FM_OWNERS[$ref['child']]??'')===array_key_first($ref['pairs']))$added+=fm_related($app,$meta,$sets,$ref,false);
        }
        if(!$added)return;
    }
    throw new RuntimeException('No se pudo cerrar el conjunto de relaciones. No se modificaron datos.');
}
function fm_orphans(App $app,array $meta):void {
    foreach($meta['refs'] as $ref){
        $notnull=implode(' AND ',array_map(static fn($col)=>'c.'.fm_id($col).' IS NOT NULL'.(!empty($ref['virtual'])?' AND c.'.fm_id($col).'<>0':''),array_keys($ref['pairs'])));
        $sql='SELECT 1 FROM '.fm_id($ref['child']).' c WHERE '.$notnull.' AND NOT EXISTS(SELECT 1 FROM '.fm_id($ref['parent']).' p WHERE '.fm_match('c','p',$ref['pairs']).') LIMIT 1';
        if($app->one($sql))throw new RuntimeException('La relación '.$ref['child'].' → '.$ref['parent'].' necesita revisión. La limpieza se canceló y se conservaron los datos.');
    }
}
function fm_filter(App $app,array $ids,bool $activateDemos=false):array {
    $meta=fm_meta($app);$sets=fm_sets($app,$meta);$counts=[];
    $app->db->beginTransaction();
    try{
        fm_keep($app,$meta,$sets,$ids);
        $app->db->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach($meta['tables'] as $table=>$m){
            $counts[$table]=$app->db->exec('DELETE b FROM '.fm_id($table).' b LEFT JOIN '.fm_id($sets[$table]).' k ON '.fm_pkmatch('b','k',$m['pk']).' WHERE k.'.fm_id($m['pk'][0]).' IS NULL');
        }
        fm_orphans($app,$meta);
        if($activateDemos)$app->exec('INSERT INTO settings(`key`,`value`) VALUES("demo_sandbox_active","1") ON DUPLICATE KEY UPDATE `value`="1"');
        $app->db->exec('SET FOREIGN_KEY_CHECKS=1');$app->db->commit();return $counts;
    }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();$app->db->exec('SET FOREIGN_KEY_CHECKS=1');throw $e;}
    finally{fm_drop_sets($app,$sets);}
}
