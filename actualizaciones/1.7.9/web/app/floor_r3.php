<?php
declare(strict_types=1);

function r3_can_layout(App $app,array $u): bool
{
    return in_array($u['role'],['tenant_admin','manager'],true) && restructure_can_r1($app,$u,'mesas','configure')!==false;
}
function r3_floor_coordinate($value,int $min,int $max): int { return (int)r2_number($value,$min,$max); }
function r3_room(App $app,array $t,array $branch,int $id,bool $lock=false): array
{
    $room=$app->one('SELECT * FROM fisitaap_r2_rooms WHERE id=? AND tenant_id=? AND branch_id=?'.($lock?' FOR UPDATE':''),[$id,$t['id'],$branch['id']]);
    if(!$room)throw new DomainException('Salón no disponible en esta sucursal.');
    return $room;
}
function r3_floor_save(App $app,array $u,array $t,array $branch): void
{
    verify_csrf();if(!r3_can_layout($app,$u))throw new DomainException('No tienes permiso para configurar el salón.');
    $action=(string)($_POST['action']??'');$roomId=(int)($_POST['room_id']??0);
    $app->db->beginTransaction();
    try {
        if(in_array($action,['room','create_room'],true)) {
            $name=trim((string)($_POST['name']??''));if($name===''||mb_strlen($name)>120)throw new DomainException('Escribe el nombre del salón.');
            $rate=r2_number($_POST['service_rate']??0,0,30);
            if($roomId){r3_room($app,$t,$branch,$roomId,true);$app->exec('UPDATE fisitaap_r2_rooms SET name=?,service_rate=? WHERE id=?',[$name,$rate,$roomId]);}
            else {$app->exec('INSERT INTO fisitaap_r2_rooms(tenant_id,branch_id,name,service_rate) VALUES(?,?,?,?)',[$t['id'],$branch['id'],$name,$rate]);$roomId=(int)$app->db->lastInsertId();}
        } elseif($action==='view') {
            r2_set($app,(int)$t['id'],'table_view',($_POST['table_view']??'')==='graphic'?'graphic':'basic');
        } else {
            r3_room($app,$t,$branch,$roomId,true);
            $app->exec('INSERT IGNORE INTO fisitaap_r3_room_versions(room_id,revision) VALUES(?,0)',[$roomId]);
            $version=$app->one('SELECT revision FROM fisitaap_r3_room_versions WHERE room_id=? FOR UPDATE',[$roomId]);
            if($action==='layout'&&(int)($_POST['layout_revision']??-1)!==(int)$version['revision'])throw new DomainException('Otra persona cambió este plano. Recarga antes de guardar.');
            if($action==='save_sector') {
                $id=(int)($_POST['sector_id']??0);$name=trim((string)($_POST['name']??''));if($name===''||mb_strlen($name)>100)throw new DomainException('Escribe el nombre del sector.');
                $color=preg_match('/^#[a-f0-9]{6}$/i',(string)($_POST['color']??''))?$_POST['color']:'#e7f1ff';
                $x=r3_floor_coordinate($_POST['x']??30,0,950);$y=r3_floor_coordinate($_POST['y']??30,0,600);
                $width=r3_floor_coordinate($_POST['width']??500,50,1000-$x);$height=r3_floor_coordinate($_POST['height']??550,50,650-$y);
                $enabled=isset($_POST['service_enabled'])?1:0;$rate=r2_number($_POST['service_rate']??10,0,30);
                if($id){if(!$app->one('SELECT id FROM fisitaap_r3_sectors WHERE id=? AND tenant_id=? AND branch_id=? AND room_id=?',[$id,$t['id'],$branch['id'],$roomId]))throw new DomainException('Sector no disponible.');$app->exec('UPDATE fisitaap_r3_sectors SET name=?,color=?,x=?,y=?,width=?,height=?,service_enabled=?,service_rate=? WHERE id=?',[$name,$color,$x,$y,$width,$height,$enabled,$rate,$id]);}
                else $app->exec('INSERT INTO fisitaap_r3_sectors(tenant_id,branch_id,room_id,name,color,x,y,width,height,service_enabled,service_rate) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$t['id'],$branch['id'],$roomId,$name,$color,$x,$y,$width,$height,$enabled,$rate]);
            } elseif($action==='save_table') {
                $id=(int)($_POST['table_id']??0);$sector=(int)($_POST['sector_id']??0);$sectorRow=$sector?$app->one('SELECT * FROM fisitaap_r3_sectors WHERE id=? AND tenant_id=? AND branch_id=? AND room_id=?',[$sector,$t['id'],$branch['id'],$roomId]):null;
                if($sector&&!$sectorRow)throw new DomainException('Selecciona un sector de este salón.');
                $name=trim((string)($_POST['name']??''));if($name===''||mb_strlen($name)>80)throw new DomainException('Escribe el nombre o número de la mesa.');
                $shape=in_array($_POST['shape']??'',['square','round','rectangle','bar'],true)?$_POST['shape']:'square';$type=$shape==='bar'?'bar':'table';
                $capacity=r3_floor_coordinate($_POST['capacity']??4,1,30);$rotation=r3_floor_coordinate($_POST['rotation']??0,0,359);
                $service=$sectorRow?(int)$sectorRow['service_enabled']:(isset($_POST['service_enabled'])?1:0);
                if($id){$old=$app->one('SELECT * FROM restaurant_tables WHERE id=? AND tenant_id=? AND branch_id=? AND is_active=1 FOR UPDATE',[$id,$t['id'],$branch['id']]);if(!$old)throw new DomainException('Mesa no disponible.');if($old['space_type']!==$type&&$app->one('SELECT id FROM pos_tickets WHERE table_id=? AND status NOT IN ("paid","void")',[$id]))throw new DomainException('Cierra las cuentas antes de cambiar entre mesa y barra.');$app->exec('UPDATE restaurant_tables SET name=?,capacity=?,area=?,space_type=?,service_enabled=? WHERE id=?',[$name,$capacity,$sectorRow['name']??'Salón',$type,$service,$id]);}
                else {$app->exec('INSERT INTO restaurant_tables(tenant_id,branch_id,name,capacity,area,space_type,service_enabled) VALUES(?,?,?,?,?,?,?)',[$t['id'],$branch['id'],$name,$capacity,$sectorRow['name']??'Salón',$type,$service]);$id=(int)$app->db->lastInsertId();}
                $width=$shape==='bar'?220:($shape==='rectangle'?170:120);$height=$shape==='bar'?70:120;$x=r3_floor_coordinate($_POST['x']??60,0,1000-$width);$y=r3_floor_coordinate($_POST['y']??80,0,650-$height);$app->exec('INSERT INTO fisitaap_r2_floor(table_id,room_id,x,y,width,height,shape) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE room_id=VALUES(room_id),shape=VALUES(shape)',[$id,$roomId,$x,$y,$width,$height,$shape]);
                $app->exec('INSERT INTO fisitaap_r3_table_details(table_id,tenant_id,branch_id,sector_id,rotation) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE sector_id=VALUES(sector_id),rotation=VALUES(rotation)',[$id,$t['id'],$branch['id'],$sector?:null,$rotation]);
            } elseif($action==='delete_table') {
                $id=(int)($_POST['table_id']??0);if(!$app->one('SELECT r.id FROM restaurant_tables r JOIN fisitaap_r2_floor f ON f.table_id=r.id WHERE r.id=? AND r.tenant_id=? AND r.branch_id=? AND f.room_id=? FOR UPDATE',[$id,$t['id'],$branch['id'],$roomId]))throw new DomainException('Mesa no disponible.');
                if($app->one('SELECT id FROM pos_tickets WHERE table_id=? AND status NOT IN ("paid","void")',[$id]))throw new DomainException('La mesa tiene cuentas abiertas. Ciérralas antes de retirarla.');
                $app->exec('UPDATE restaurant_tables SET is_active=0 WHERE id=?',[$id]);
            } elseif($action==='delete_sector') {
                $id=(int)($_POST['sector_id']??0);if(!$app->one('SELECT id FROM fisitaap_r3_sectors WHERE id=? AND tenant_id=? AND branch_id=? AND room_id=?',[$id,$t['id'],$branch['id'],$roomId]))throw new DomainException('Sector no disponible.');
                if($app->one('SELECT table_id FROM fisitaap_r3_table_details d JOIN restaurant_tables r ON r.id=d.table_id WHERE d.sector_id=? AND r.is_active=1',[$id]))throw new DomainException('Asigna las mesas a otro sector antes de retirarlo.');$app->exec('DELETE FROM fisitaap_r3_sectors WHERE id=?',[$id]);
            } elseif($action==='layout') {
                $data=json_decode((string)($_POST['layout']??''),true,64,JSON_THROW_ON_ERROR);
                if(!is_array($data)||!isset($data['tables'],$data['objects'])||count($data['tables'])>300||count($data['objects'])>300)throw new DomainException('Plano inválido.');
                $seen=[];
                foreach($data['tables'] as $row) {
                    $id=(int)($row['id']??0);if(isset($seen[$id]))throw new DomainException('Mesa repetida en el plano.');$seen[$id]=true;
                    if(!$app->one('SELECT id FROM restaurant_tables WHERE id=? AND tenant_id=? AND branch_id=? AND is_active=1 FOR UPDATE',[$id,$t['id'],$branch['id']]))throw new DomainException('Una mesa no pertenece a esta sucursal.');
                    $x=r3_floor_coordinate($row['x']??0,0,950);$y=r3_floor_coordinate($row['y']??0,0,600);$w=r3_floor_coordinate($row['width']??120,50,1000-$x);$h=r3_floor_coordinate($row['height']??120,50,650-$y);
                    $shape=in_array($row['shape']??'',['square','round','rectangle','bar'],true)?$row['shape']:'square';
                    $sector=(int)($row['sector_id']??0);if($sector&&!$app->one('SELECT id FROM fisitaap_r3_sectors WHERE id=? AND tenant_id=? AND branch_id=? AND room_id=?',[$sector,$t['id'],$branch['id'],$roomId]))throw new DomainException('Sector inválido.');
                    $app->exec('INSERT INTO fisitaap_r2_floor(table_id,room_id,x,y,width,height,shape) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE room_id=VALUES(room_id),x=VALUES(x),y=VALUES(y),width=VALUES(width),height=VALUES(height),shape=VALUES(shape)',[$id,$roomId,$x,$y,$w,$h,$shape]);
                    $app->exec('INSERT INTO fisitaap_r3_table_details(table_id,tenant_id,branch_id,sector_id,rotation,basic_order) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE sector_id=VALUES(sector_id),rotation=VALUES(rotation),basic_order=VALUES(basic_order)',[$id,$t['id'],$branch['id'],$sector?:null,r3_floor_coordinate($row['rotation']??0,0,359),r3_floor_coordinate($row['basic_order']??0,0,300)]);
                }
                $seen=[];$normalized=[];
                foreach($data['objects'] as $row) {
                    $id=(string)($row['id']??'');if(!preg_match('/^[a-f0-9-]{36}$/i',$id)||isset($seen[$id]))throw new DomainException('Elemento de plano inválido.');$seen[$id]=true;
                    $kind=(string)($row['kind']??'');if(!in_array($kind,['wall','door','window','chair','sofa','plant','bar','divider','text'],true))throw new DomainException('Elemento no disponible.');
                    $old=$app->one('SELECT tenant_id,branch_id,room_id FROM fisitaap_r3_objects WHERE id=? FOR UPDATE',[$id]);if($old&&((int)$old['tenant_id']!==(int)$t['id']||(int)$old['branch_id']!==(int)$branch['id']||(int)$old['room_id']!==$roomId))throw new DomainException('El elemento pertenece a otro plano.');
                    $x=r3_floor_coordinate($row['x']??0,0,990);$y=r3_floor_coordinate($row['y']??0,0,640);$w=r3_floor_coordinate($row['width']??100,10,1000-$x);$h=r3_floor_coordinate($row['height']??30,10,650-$y);
                    $normalized[]=[$id,$t['id'],$branch['id'],$roomId,$kind,mb_substr(trim((string)($row['label']??'')),0,120),$x,$y,$w,$h,r3_floor_coordinate($row['rotation']??0,0,359)];
                }
                $app->exec('DELETE FROM fisitaap_r3_objects WHERE tenant_id=? AND branch_id=? AND room_id=?',[$t['id'],$branch['id'],$roomId]);
                foreach($normalized as $row)$app->exec('INSERT INTO fisitaap_r3_objects(id,tenant_id,branch_id,room_id,kind,label,x,y,width,height,rotation) VALUES(?,?,?,?,?,?,?,?,?,?,?)',$row);
            } else throw new DomainException('Acción no disponible.');
            $app->exec('UPDATE fisitaap_r3_room_versions SET revision=revision+1 WHERE room_id=?',[$roomId]);
        }
        $_POST['room_id']=$roomId;
        audit($app,(int)$t['id'],(int)$u['id'],'r3_floor_'.$action,'room',$roomId);$app->db->commit();
        flash('success','Diseño guardado. Las cuentas abiertas conservan sus cargos.');
    }catch(Throwable $ex){if($app->db->inTransaction())$app->db->rollBack();throw $ex;}
}
function r3_floor_tables(App $app,array $t,array $branch): array
{
    return $app->all('SELECT r.*,f.room_id,f.x,f.y,f.width,f.height,f.shape,d.sector_id,COALESCE(d.rotation,0) rotation,COALESCE(d.basic_order,r.sort_order) basic_order,(SELECT COUNT(*) FROM pos_tickets p WHERE p.table_id=r.id AND p.tenant_id=r.tenant_id AND p.status NOT IN ("paid","void")) open_count,(SELECT COALESCE(SUM(p.total-p.paid_total),0) FROM pos_tickets p WHERE p.table_id=r.id AND p.tenant_id=r.tenant_id AND p.status NOT IN ("paid","void")) balance,(SELECT MIN(p.created_at) FROM pos_tickets p WHERE p.table_id=r.id AND p.tenant_id=r.tenant_id AND p.status NOT IN ("paid","void")) opened_at,(SELECT COUNT(*) FROM pos_tickets p WHERE p.table_id=r.id AND p.status="payment") payment_count FROM restaurant_tables r LEFT JOIN fisitaap_r2_floor f ON f.table_id=r.id LEFT JOIN fisitaap_r3_table_details d ON d.table_id=r.id WHERE r.tenant_id=? AND r.branch_id=? AND r.is_active=1 ORDER BY basic_order,r.id',[$t['id'],$branch['id']]);
}
function r3_table_state(array $table): string { return (int)$table['payment_count']>0?'due':((int)$table['open_count']>0?'busy':'free'); }
function r3_table_minutes(array $table): int { return $table['opened_at']?max(0,(int)floor((time()-strtotime($table['opened_at']))/60)):0; }
function r3_furniture_defs(): string
{
    return '<defs><linearGradient id="r3-wood" x2=".8" y2="1"><stop stop-color="#dabd94"/><stop offset="1" stop-color="#a88052"/></linearGradient><linearGradient id="r3-marble" x2="1" y2="1"><stop stop-color="#f1f2ef"/><stop offset="1" stop-color="#c8d0d0"/></linearGradient><linearGradient id="r3-wall" x2="0" y2="1"><stop stop-color="#71818c"/><stop offset="1" stop-color="#374a59"/></linearGradient><filter id="r3-shadow" x="-30%" y="-30%" width="160%" height="160%"><feDropShadow dx="2" dy="4" stdDeviation="3" flood-opacity=".22"/></filter><pattern id="r3-dots" width="14" height="14" patternUnits="userSpaceOnUse"><circle cx="7" cy="7" r=".8" fill="#b5c5d8"/></pattern><pattern id="r3-grain" width="40" height="18" patternUnits="userSpaceOnUse"><path d="M0 3Q20 10 40 3M0 12Q25 5 40 12" stroke="#b28c60" stroke-width=".5" fill="none"/></pattern></defs>';
}
function r3_furniture(string $shape,int $capacity,string $state='free',string $label=''): string
{
    $color=['free'=>'#6f976a','busy'=>'#4a8bc9','due'=>'#d7a13e'][$state]??'#6f976a';$out='<g filter="url(#r3-shadow)">';
    $chairs=$shape==='bar'?max(2,min(6,$capacity)):max(2,min(8,$capacity));
    for($i=0;$i<$chairs;$i++) {
        $angle=360*$i/$chairs;$out.='<g transform="translate(60 60) rotate('.$angle.')"><rect x="-15" y="-53" width="30" height="28" rx="9" fill="'.$color.'" stroke="#3b5660" stroke-width="1.2"/><path d="M-15-46Q0-54 15-46" fill="none" stroke="#ffffff77" stroke-width="2"/><path d="M-12-27v7M12-27v7" stroke="#59616c" stroke-width="2"/></g>';
    }
    if($shape==='round')$out.='<circle cx="60" cy="60" r="38" fill="url(#r3-wood)" stroke="#9a774b"/><circle cx="60" cy="60" r="37" fill="url(#r3-grain)" opacity=".5"/>';
    else $out.='<rect x="24" y="24" width="72" height="72" rx="'.($shape==='bar'?3:7).'" fill="url(#'.($state==='busy'?'r3-marble':'r3-wood').')" stroke="#8a806b"/><path d="M30 40q25-15 60 5M30 60q30 10 60-6M28 85q30-20 65-10" fill="none" stroke="'.($state==='busy'?'#bdc7c6':'#b49770').'" stroke-width=".8"/>';
    $out.='<circle cx="60" cy="60" r="17" fill="#fffdf5"/><text x="60" y="65" font-size="13" font-weight="700" fill="#0a2547" text-anchor="middle">'.e($label).'</text></g>';
    return $out;
}
function r3_object_svg(array $o): string
{
    $w=(int)$o['width'];$h=(int)$o['height'];$kind=$o['kind'];$content='';
    if($kind==='wall')$content='<rect width="'.$w.'" height="'.$h.'" fill="url(#r3-wall)" stroke="#273a45" stroke-width="2"/><path d="M1 3H'.($w-1).'" stroke="#b4c0c6"/>';
    elseif($kind==='window')$content='<rect width="'.$w.'" height="'.$h.'" fill="#dbeaf1" stroke="#667784" stroke-width="2"/><path d="M0 '.($h/2).'H'.$w.'M'.($w/3).' 0V'.$h.'M'.($w*2/3).' 0V'.$h.'" stroke="#a2b2c0"/><path d="M3 3H'.($w-3).'" stroke="white" stroke-width="3"/>';
    elseif($kind==='door')$content='<path d="M0 '.$h.'V0M0 0H'.$w.'M'.$w.' 0V'.$h.'" fill="none" stroke="#536471" stroke-width="3"/><path d="M0 0Q'.$w.' 0 '.$w.' '.$h.'" fill="#ffffff88" stroke="#82909a" stroke-dasharray="5 3"/>';
    elseif($kind==='plant') {
        $content='<ellipse cx="'.($w/2).'" cy="'.($h/2).'" rx="'.($w*.27).'" ry="'.($h*.27).'" fill="#b99869"/>';
        for($i=0;$i<8;$i++)$content.='<ellipse transform="translate('.($w/2).' '.($h/2).') rotate('.($i*45).')" cx="0" cy="'.(-$h*.19).'" rx="'.($w*.13).'" ry="'.($h*.29).'" fill="'.($i%2?'#709546':'#8daf58').'" stroke="#56763a" stroke-width="1"/>';
        $content.='<circle cx="'.($w/2).'" cy="'.($h/2).'" r="'.($w*.11).'" fill="#68853c"/>';
    } elseif($kind==='text')$content='<text x="3" y="'.max(16,$h*.7).'" fill="#28435c" font-size="'.min(35,max(14,$h*.7)).'">'.e($o['label']).'</text>';
    elseif($kind==='bar'||$kind==='divider')$content='<rect width="'.$w.'" height="'.$h.'" rx="5" fill="url(#r3-wood)" stroke="#8a755a" stroke-width="2"/><rect x="4" y="4" width="'.max(1,$w-8).'" height="'.max(1,$h-8).'" fill="url(#r3-grain)"/>'.($kind==='divider'?'<path d="M5 '.($h/2).'H'.($w-5).'" stroke="#5d853f" stroke-width="'.max(6,$h*.4).'"/>':'');
    else $content='<rect width="'.$w.'" height="'.$h.'" rx="8" fill="#c2b8a5" stroke="#827c71"/><rect x="7" y="8" width="'.max(1,$w-14).'" height="'.max(1,$h-15).'" rx="5" fill="#e3dacc"/><path d="M6 '.($h-7).'H'.($w-6).'" stroke="#665f51" stroke-width="3"/>';
    return '<g data-layout-object="'.e($o['id']).'" transform="translate('.(int)$o['x'].' '.(int)$o['y'].') rotate('.(int)$o['rotation'].' '.($w/2).' '.($h/2).')"><g filter="url(#r3-shadow)">'.$content.'</g></g>';
}
function r3_default_objects(): array
{
    $rows=[['wall',15,15,970,12],['wall',15,15,12,620],['wall',973,15,12,620],['wall',15,623,370,12],['wall',565,623,420,12],['door',390,530,170,100],['window',210,15,200,12],['window',700,15,180,12],['plant',35,35,55,55],['plant',910,35,55,55],['plant',35,550,55,55],['plant',910,550,55,55]];$out=[];
    foreach($rows as [$kind,$x,$y,$w,$h]){$hex=bin2hex(random_bytes(16));$out[]=['id'=>substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20,12),'kind'=>$kind,'x'=>$x,'y'=>$y,'width'=>$w,'height'=>$h,'rotation'=>0,'label'=>''];}return $out;
}
function r3_floor(App $app,array $u,array $t): void
{
    $branch=pos_access_148($app,$u,$t);if(($t['catalog_label']??'')!=='Menú')throw new DomainException('Las mesas están disponibles para negocios tipo Menú.');
    $editor=!empty($_GET['design']);$canEdit=r3_can_layout($app,$u);
    if($editor&&!$canEdit)throw new DomainException('No tienes permiso para editar el plano.');
    if(is_post()){try{r3_floor_save($app,$u,$t,$branch);}catch(Throwable $ex){flash('error',$ex instanceof PDOException?'No se pudo guardar. Revisa los datos y vuelve a intentarlo.':$ex->getMessage());}redirect(url('admin/mesas?room='.(int)($_POST['room_id']??0).($editor?'&design=1':'')));}
    $rooms=$app->all('SELECT * FROM fisitaap_r2_rooms WHERE tenant_id=? AND branch_id=? ORDER BY id',[$t['id'],$branch['id']]);$roomId=(int)($_GET['room']??($rooms[0]['id']??0));$room=null;foreach($rooms as $r)if((int)$r['id']===$roomId)$room=$r;
    $view=($_GET['view']??r2_setting($app,(int)$t['id'],'table_view','basic'))==='graphic'?'graphic':'basic';
    $allTables=r3_floor_tables($app,$t,$branch);$tables=[];foreach($allTables as $i=>$table){if($table['room_id']&&(int)$table['room_id']!==$roomId)continue;$n=count($tables);$table['x']=$table['x']??(120+($n%3)*290);$table['y']=$table['y']??(80+intdiv($n,3)*260);$table['width']=$table['width']??120;$table['height']=$table['height']??120;$table['shape']=$table['shape']??($table['space_type']==='bar'?'bar':'round');$table['sector_id']=$table['sector_id']??0;$tables[]=$table;}
    $sectors=$room?$app->all('SELECT * FROM fisitaap_r3_sectors WHERE tenant_id=? AND branch_id=? AND room_id=? ORDER BY id',[$t['id'],$branch['id'],$roomId]):[];
    $objects=$room?$app->all('SELECT * FROM fisitaap_r3_objects WHERE tenant_id=? AND branch_id=? AND room_id=? ORDER BY id',[$t['id'],$branch['id'],$roomId]):[];if(!$objects)$objects=r3_default_objects();
    $version=$room?(int)($app->one('SELECT revision FROM fisitaap_r3_room_versions WHERE room_id=?',[$roomId])['revision']??0):0;
    r3_shell_start($app,$u,$t,$branch,$editor?'Diseñador de salones':'Mesas',$view==='graphic'||$editor);if(!$editor)r3_pos_nav($t);
    echo '<div class="r3-room-tabs">';foreach($rooms as $r)echo '<a class="r3-button '.((int)$r['id']===$roomId?'selected':'').'" href="'.url('admin/mesas?room='.(int)$r['id'].'&view='.$view.($editor?'&design=1':'')).'">'.r3_icon('table').e($r['name']).'</a>';
    if($canEdit)echo '<button class="r3-button dashed" type="button" data-dialog-open="r3Room">'.r3_icon('plus').'Crear salón</button>';
    if(!$editor){echo '<span class="r3-spacer"></span><a class="r3-button" href="'.url('admin/mesas?room='.$roomId.'&view='.($view==='graphic'?'basic':'graphic')).'">Vista '.($view==='graphic'?'básica':'gráfica').'</a>';if($canEdit)echo '<a class="r3-button" href="'.url('admin/mesas?room='.$roomId.'&design=1').'">'.r3_icon('edit').'Editar distribución</a><button class="r3-button" type="button" data-dialog-open="r3RoomSettings">'.r3_icon('settings').'Configurar salón</button>';}
    else echo '<span class="r3-spacer"></span><a class="r3-button" href="'.url('admin/mesas?room='.$roomId.'&view=graphic').'">Cancelar</a>';
    echo '</div>';
    if(!$room)echo '<section class="r3-empty"><h2>Cada mesa, a un toque</h2><p>'.($canEdit?'Crea el primer salón para organizar las mesas y sus sectores.':'El dueño debe crear el salón y sus mesas.').'</p></section>';
    else {
        echo '<div class="r3-room-heading"><h2>'.($editor?'Diseña tu salón':($view==='basic'?'Cada mesa, a un toque':e($room['name']))).'</h2><input class="r3-input" data-table-search placeholder="Buscar mesa o cliente…">'.($canEdit?'<button class="r3-button" type="button" data-dialog-open="r3Table">'.r3_icon('plus').'Añadir mesa</button>':'').'</div>';
        echo '<div class="r3-legend"><span class="free">● Disponible</span><span class="busy">● Ocupada</span><span class="due">● Por cobrar</span></div>';
        if($editor){echo '<div class="r3-designer"><aside class="r3-palette"><h3>Elementos</h3><small>Arrastra al plano</small><div>';foreach(['wall'=>'Pared','door'=>'Puerta','window'=>'Ventana','round'=>'Mesa redonda','square'=>'Mesa cuadrada','rectangle'=>'Mesa rectangular','chair'=>'Silla','sofa'=>'Sofá','plant'=>'Planta','bar'=>'Barra','divider'=>'Separador','text'=>'Texto'] as $kind=>$label)echo '<button type="button" draggable="true" data-palette="'.$kind.'"><svg viewBox="0 0 120 80">'.(in_array($kind,['round','square','rectangle'],true)?'<g transform="translate(20 -5) scale(.7)">'.r3_furniture($kind,4).'</g>':r3_object_svg(['id'=>'palette-'.$kind,'kind'=>$kind,'x'=>25,'y'=>15,'width'=>70,'height'=>45,'rotation'=>0,'label'=>$kind==='text'?'T':''])).'</svg><span>'.$label.'</span></button>';echo '</div></aside><section class="r3-design-center"><div class="r3-design-toolbar"><button class="r3-button" type="button" data-layout-undo>↶ Deshacer</button><button class="r3-button" type="button" data-layout-redo>↷ Rehacer</button><button class="r3-button" type="button" data-layout-grid>Cuadrícula</button><button class="r3-button" type="button" data-layout-snap>Ajustar</button><span class="r3-spacer"></span><button class="r3-button" type="button" data-layout-zoom="-1">−</button><span data-layout-scale>100%</span><button class="r3-button" type="button" data-layout-zoom="1">+</button></div>';}
        if($editor||$view==='graphic') {
            echo '<div class="r3-floor-canvas" data-r3-floor '.($editor?'data-layout-editor':'').'><svg viewBox="0 0 1000 680" role="img" aria-label="Plano de '.e($room['name']).'">'.r3_furniture_defs().'<rect width="1000" height="650" fill="#fbfdff"/><rect width="1000" height="650" fill="url(#r3-dots)" data-layout-grid-bg/><g data-layout-sectors>';
            foreach($sectors as $s)echo '<g data-layout-sector="'.(int)$s['id'].'"><rect x="'.(int)$s['x'].'" y="'.(int)$s['y'].'" width="'.(int)$s['width'].'" height="'.(int)$s['height'].'" fill="'.e($s['color']).'" fill-opacity=".6"/><text x="'.((int)$s['x']+12).'" y="'.((int)$s['y']+25).'" fill="#23425d" font-size="12">'.e($s['name']).' · '.($s['service_enabled']?'Servicio '.e($s['service_rate']).'% activo':'Sin servicio').'</text></g>';
            echo '</g><g data-layout-objects>';foreach($objects as $o)echo r3_object_svg($o);echo '</g><g data-layout-tables>';
            foreach($tables as $table){$state=r3_table_state($table);$w=(int)$table['width'];$h=(int)$table['height'];$label=preg_replace('/^mesa\s*/iu','',$table['name']);$label=mb_substr($label,0,8);echo '<g data-floor-table="'.(int)$table['id'].'" data-r3-table="'.(int)$table['id'].'" data-state="'.$state.'" data-search="'.e(mb_strtolower($table['name'])).'" transform="translate('.(int)$table['x'].' '.(int)$table['y'].')"><g transform="rotate('.(int)$table['rotation'].' '.($w/2).' '.($h/2).') scale('.($w/120).' '.($h/120).')">'.r3_furniture($table['shape'],(int)$table['capacity'],$state,$label).'</g><g transform="translate('.($w/2-60).' '.($h+5).')"><rect width="120" height="42" rx="9" fill="'.['free'=>'#def6e4','busy'=>'#e0eeff','due'=>'#ffedc5'][$state].'"/><text x="60" y="16" text-anchor="middle" font-size="12" font-weight="700" fill="#173c57">'.['free'=>'Disponible','busy'=>'Ocupada','due'=>'Por cobrar'][$state].'</text><text x="60" y="32" text-anchor="middle" font-size="10" fill="#34556b">'.($state==='free'?(int)$table['capacity'].' personas':e(money((float)$table['balance'])).' · '.r3_table_minutes($table).' min').'</text></g></g>';}
            echo '</g><g data-layout-selection hidden><rect fill="none" stroke="#1769ff" stroke-width="2" stroke-dasharray="4 2"/><rect data-layout-handle width="12" height="12" fill="#1769ff"/></g></svg></div>';
        } else {
            if($canEdit)echo '<div class="r3-order-banner" data-basic-edit-bar hidden>'.r3_icon('edit').'<div><strong>Editar orden de mesas</strong><small>Arrastra las tarjetas para cambiar su posición visual.</small></div><button class="r3-button" type="button" data-basic-cancel>Cancelar</button><button class="r3-button primary" type="button" data-basic-save>Guardar orden</button></div><button class="r3-button" type="button" data-basic-edit>Editar orden de mesas</button>';
            echo '<div class="r3-table-cards" data-basic-tables>';foreach($tables as $table){$state=r3_table_state($table);echo '<button class="r3-table-card" type="button" data-r3-table="'.(int)$table['id'].'" data-search="'.e(mb_strtolower($table['name'])).'" data-state="'.$state.'"><svg viewBox="0 0 160 100" aria-hidden="true">'.r3_furniture_defs().'<g transform="translate(35 0) scale(.8)">'.r3_furniture($table['shape'],(int)$table['capacity'],$state).'</g></svg><strong>'.e($table['name']).'</strong><span class="r3-status '.$state.'">● '.['free'=>'Disponible','busy'=>'Ocupada','due'=>'Por cobrar'][$state].'</span><small>'.r3_icon('person').(int)$table['capacity'].' personas</small><footer><span>'.r3_icon('cash').money((float)$table['balance']).'</span><span>'.r3_icon('clock').r3_table_minutes($table).' min</span></footer></button>';}echo '</div>';
        }
        if($editor){echo '</section><aside class="r3-designer-properties"><h3>Elemento seleccionado</h3><p data-layout-none>Selecciona una mesa o un elemento para moverlo, girarlo o cambiar su tamaño.</p><div data-layout-properties hidden>';foreach(['x'=>'Posición X','y'=>'Posición Y','width'=>'Ancho','height'=>'Alto','rotation'=>'Rotación °'] as $key=>$label)echo '<label>'.$label.'<input class="r3-input" type="number" min="0" max="'.($key==='rotation'?359:1000).'" data-layout-property="'.$key.'"></label>';echo '<label>Texto<input class="r3-input" maxlength="120" data-layout-property="label"></label><button class="r3-button danger" type="button" data-layout-delete>Retirar elemento</button></div><hr><button class="r3-button primary" type="button" data-dialog-open="r3Table">'.r3_icon('plus').'Nueva mesa</button><h3>Sectores</h3>';foreach($sectors as $s)echo '<button class="r3-sector-button" type="button" data-sector-edit="'.(int)$s['id'].'"><i style="background:'.e($s['color']).'"></i><span>'.e($s['name']).'<small>Servicio '.e($s['service_rate']).'% · '.($s['service_enabled']?'Activo':'Inactivo').'</small></span>'.r3_icon('edit').'</button>';echo '<button class="r3-button" type="button" data-sector-new>Crear sector</button><p class="r3-hint">El servicio se aplica a las mesas del sector al abrir una cuenta.</p><form method="post" data-layout-form>'.csrf_field().pos_hidden_148('action','layout').pos_hidden_148('room_id',$roomId).pos_hidden_148('layout_revision',$version).'<input type="hidden" name="layout"><button class="r3-button green" data-layout-save>Guardar diseño</button><p data-layout-message role="status"></p></form></aside></div>';}
        else echo '<p class="r3-hint">Toca una mesa para abrir sus detalles y acciones.</p><form method="post" data-layout-form hidden>'.csrf_field().pos_hidden_148('action','layout').pos_hidden_148('room_id',$roomId).pos_hidden_148('layout_revision',$version).'<input type="hidden" name="layout"></form>';
    }
    if($canEdit)r3_floor_dialogs($app,$t,$branch,$room,$sectors);
    echo '<script type="application/json" data-r3-layout-data>'.r3_json(['room_id'=>$roomId,'tables'=>$tables,'objects'=>$objects,'sectors'=>$sectors,'revision'=>$version,'canEdit'=>$canEdit,'editor'=>$editor]).'</script>';
    if(!$editor)r3_table_dialogs($app,$u,$t,$branch,$tables,$room);
    r3_shell_end();
}
function r3_floor_dialogs(App $app,array $t,array $branch,?array $room,array $sectors): void
{
    $roomId=(int)($room['id']??0);
    echo '<dialog id="r3Room" class="r3-dialog"><form method="post">'.csrf_field().pos_hidden_148('action','create_room').'<h2>Crear salón</h2>'.field('Nombre','name','',true).pos_hidden_148('service_rate',0).'<footer><button class="r3-button" type="button" data-dialog-close>Cancelar</button><button class="r3-button primary">Crear salón</button></footer></form></dialog>';
    if(!$room)return;
    echo '<dialog id="r3RoomSettings" class="r3-dialog"><h2>Configurar salón</h2><form method="post">'.csrf_field().pos_hidden_148('action','room').pos_hidden_148('room_id',$roomId).field('Nombre','name',$room['name'],true).field('Servicio % para mesas sin sector','service_rate',$room['service_rate'],true,'number','.01').'<button class="r3-button primary">Guardar salón</button></form><hr><form method="post">'.csrf_field().pos_hidden_148('action','view').pos_hidden_148('room_id',$roomId).'<label>Vista predeterminada<select class="r3-input" name="table_view"><option value="basic">Básica</option><option value="graphic" '.(r2_setting($app,(int)$t['id'],'table_view')==='graphic'?'selected':'').'>Gráfica</option></select></label><button class="r3-button">Guardar vista</button></form><button class="r3-button" type="button" data-dialog-close>Cerrar</button></dialog>';
    echo '<dialog id="r3Table" class="r3-dialog"><form method="post">'.csrf_field().pos_hidden_148('action','save_table').pos_hidden_148('room_id',$roomId).pos_hidden_148('table_id',0).pos_hidden_148('x',60).pos_hidden_148('y',80).'<h2>Nueva mesa</h2>'.field('Número o nombre','name','',true).'<label>Sector<select class="r3-input" name="sector_id"><option value="0">Sin sector</option>';foreach($sectors as $s)echo '<option value="'.(int)$s['id'].'">'.e($s['name']).'</option>';echo '</select></label>'.field('Capacidad (personas)','capacity',4,true,'number','1').'<label>Forma<select class="r3-input" name="shape"><option value="square">Cuadrada</option><option value="round">Redonda</option><option value="rectangle">Rectangular</option><option value="bar">Barra</option></select></label>'.field('Rotación °','rotation',0,true,'number','1').'<label><input type="checkbox" name="service_enabled"> Servicio activo si no tiene sector</label><footer><button class="r3-button danger" type="button" data-remove-table hidden>Retirar mesa</button><button class="r3-button" type="button" data-dialog-close>Cancelar</button><button class="r3-button primary">Guardar mesa</button></footer></form></dialog>';
    echo '<dialog id="r3Sector" class="r3-dialog"><form method="post">'.csrf_field().pos_hidden_148('action','save_sector').pos_hidden_148('room_id',$roomId).pos_hidden_148('sector_id',0).'<h2>Sector</h2>'.field('Nombre','name','Interior',true).field('Color','color','#e7f1ff',true,'color').'<div class="r3-fields">';foreach(['x'=>30,'y'=>30,'width'=>500,'height'=>550] as $k=>$v)echo field(['x'=>'Posición X','y'=>'Posición Y','width'=>'Ancho','height'=>'Alto'][$k],$k,$v,true,'number','1');echo '</div><label><input type="checkbox" name="service_enabled"> Aplicar servicio a las mesas del sector</label>'.field('Servicio %','service_rate',10,true,'number','.01').'<footer><button class="r3-button" type="button" data-dialog-close>Cancelar</button><button class="r3-button primary">Guardar sector</button></footer></form></dialog>';
}
function r3_sector_rate(App $app,int $table,bool $enabled): ?float
{
    $row=$app->one('SELECT s.service_enabled,s.service_rate FROM fisitaap_r3_table_details d JOIN fisitaap_r3_sectors s ON s.id=d.sector_id WHERE d.table_id=?',[$table]);
    return $row?($row['service_enabled']?(float)$row['service_rate']:0):null;
}
