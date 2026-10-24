<?php
declare(strict_types=1);

function qa180_import_columns():array {
    return ['sku','nombre','categoria','precio','costo','iva','control_inventario','existencia','stock_minimo','unidad','cantidad_minima','incremento','decimales','descripcion','estado'];
}
function qa180_import_xml(string $xml):SimpleXMLElement {
    if(strlen($xml)>4*1024*1024||preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i',$xml))throw new DomainException('El Excel contiene una estructura no admitida.');
    $before=libxml_use_internal_errors(true);try{$doc=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET|LIBXML_COMPACT);if(!$doc)throw new DomainException('No se pudo leer el Excel. Guarda una copia como .xlsx con el machote.');return $doc;}finally{libxml_clear_errors();libxml_use_internal_errors($before);}
}
function qa180_import_read(string $path,string $extension):array {
    if(filesize($path)>5*1024*1024)throw new DomainException('El archivo admite hasta 5 MB y 500 productos.');
    if($extension==='csv'){
        $text=file_get_contents($path);if(!mb_check_encoding($text,'UTF-8'))$text=mb_convert_encoding($text,'UTF-8','Windows-1252');$text=preg_replace('/^\xEF\xBB\xBF/','',$text);$stream=fopen('php://temp','w+');fwrite($stream,$text);rewind($stream);$first=strtok($text,"\r\n")?:'';$separator=substr_count($first,';')>substr_count($first,',')?';':',';$rows=[];
        try{while(($row=fgetcsv($stream,0,$separator,'"',''))!==false){if(count($rows)>=501)throw new DomainException('Carga hasta 500 productos por archivo.');if(count($row)>15)throw new DomainException('El archivo tiene columnas adicionales. Usa el machote.');$rows[]=$row;}}finally{fclose($stream);}return $rows;
    }
    if($extension!=='xlsx')throw new DomainException('Usa un archivo .xlsx o CSV. Los archivos .xls deben guardarse como .xlsx.');
    if(!class_exists('ZipArchive'))throw new DomainException('Activa la extensión PHP zip en cPanel para leer Excel, o carga el machote guardado como CSV.');
    $zip=new ZipArchive();if($zip->open($path)!==true)throw new DomainException('El archivo no es un Excel válido.');
    try{
        $total=0;if($zip->numFiles>200)throw new DomainException('Usa un Excel sencillo basado en el machote.');
        for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$total+=$stat['size'];if($total>20*1024*1024)throw new DomainException('El Excel descomprimido supera el límite. Divide la carga.');}
        $read=static function(string $name)use($zip):string{$stat=$zip->statName($name);if(!$stat||$stat['size']>4*1024*1024)throw new DomainException('Falta una hoja válida o supera el límite del Excel.');$value=$zip->getFromName($name);if($value===false)throw new DomainException('No se pudo leer una hoja del Excel.');return $value;};
        $book=qa180_import_xml($read('xl/workbook.xml'));$relation='';foreach($book->xpath('//*[local-name()="sheet"]') as $sheet)if(mb_strtolower((string)$sheet['name'])==='productos'){$attrs=$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$relation=(string)$attrs['id'];break;}
        if(!$relation)throw new DomainException('El Excel necesita la hoja Productos del machote.');
        $rels=qa180_import_xml($read('xl/_rels/workbook.xml.rels'));$target='';foreach($rels->xpath('//*[local-name()="Relationship"]') as $rel)if((string)$rel['Id']===$relation){if((string)$rel['TargetMode']==='External')throw new DomainException('La hoja debe estar dentro del archivo.');$target=(string)$rel['Target'];}
        $target=ltrim($target,'/');if(!str_starts_with($target,'xl/'))$target='xl/'.$target;if(!preg_match('~^xl/worksheets/[a-zA-Z0-9_-]+\.xml$~D',$target))throw new DomainException('La hoja Productos tiene una ruta no admitida.');
        $shared=[];if($zip->locateName('xl/sharedStrings.xml')!==false){$strings=qa180_import_xml($read('xl/sharedStrings.xml'));foreach($strings->xpath('//*[local-name()="si"]') as $si){$value='';foreach($si->xpath('.//*[local-name()="t"]') as $text)$value.=(string)$text;$shared[]=$value;}}
        $sheet=qa180_import_xml($read($target));$rows=[];$last=0;
        foreach($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row){
            $number=(int)$row['r'];if($number<1||$number>501||$number<=$last)throw new DomainException('El Excel admite la cabecera y hasta 500 filas.');while(count($rows)<$number-1)$rows[]=[];$last=$number;$values=[];
            foreach($row->xpath('./*[local-name()="c"]') as $cell){
                if($cell->xpath('./*[local-name()="f"]'))throw new DomainException('Fila '.$number.': reemplaza las fórmulas por sus valores antes de cargar.');
                if(!preg_match('/^([A-O])([0-9]+)$/D',(string)$cell['r'],$match)||(int)$match[2]!==$number)throw new DomainException('Usa las quince columnas del machote.');$column=ord($match[1])-65;if(isset($values[$column]))throw new DomainException('Hay celdas repetidas en el Excel.');
                $type=(string)$cell['t'];$v=$cell->xpath('./*[local-name()="v"]');$value=(string)($v[0]??'');
                if($type==='s'){if(!ctype_digit($value)||!array_key_exists((int)$value,$shared))throw new DomainException('Una celda de texto no es válida.');$value=$shared[(int)$value];}
                elseif($type==='inlineStr'){$value='';foreach($cell->xpath('./*[local-name()="is"]//*[local-name()="t"]') as $text)$value.=(string)$text;}
                elseif(!in_array($type,['','n','str','b'],true))throw new DomainException('Fila '.$number.': hay una celda con error.');
                if(mb_strlen($value)>2500)throw new DomainException('Fila '.$number.': una celda tiene demasiado texto.');$values[$column]=$value;
            }
            $rows[]=$values;
        }return $rows;
    }finally{$zip->close();}
}
function qa180_import_validate(App $app,array $user,array $tenant,array $rows):array {
    $columns=qa180_import_columns();$header=array_map(static fn($x)=>mb_strtolower(trim((string)$x)),$rows[0]??[]);if(array_values($header)!==$columns)throw new DomainException('La primera fila debe conservar los encabezados del machote, en el mismo orden.');
    $items=[];$errors=[];$seen=[];$skus=[];
    $number=static function(string $value,string $label,float $default,int $precision=2,float $max=1000000000):float{
        if($value==='')return $default;$value=str_replace(',','.',$value);if(!preg_match('/^\d+(?:\.\d+)?$/D',$value))throw new DomainException($label.': usa un número sin símbolos ni separadores de miles.');$n=(float)$value;if(!is_finite($n)||$n>$max||abs($n*10**$precision-round($n*10**$precision))>0.00001)throw new DomainException($label.': valor fuera de rango o con demasiados decimales.');return $n;
    };
    foreach(array_slice($rows,1) as $i=>$raw){
        $values=[];for($j=0;$j<count($columns);$j++)$values[$columns[$j]]=trim((string)($raw[$j]??''));if(!array_filter($values,static fn($v)=>$v!==''))continue;$line=$i+2;
        try{
            foreach($values as $value)if(mb_strlen($value)>2500||preg_match('/^[=+@]/',$value)||str_contains($value,"\0"))throw new DomainException('Usa texto o números, sin fórmulas.');
            foreach(['sku'=>80,'nombre'=>190,'categoria'=>120,'unidad'=>40,'descripcion'=>2000] as $key=>$length)if(mb_strlen($values[$key])>$length)throw new DomainException($key.': supera '.$length.' caracteres.');
            if($values['sku']===''||$values['nombre']===''||$values['precio']==='')throw new DomainException('Completa sku, nombre y precio.');
            $key=mb_strtolower($values['sku']);if(isset($seen[$key]))throw new DomainException('SKU repetido en este archivo.');$seen[$key]=true;
            $price=$number($values['precio'],'Precio',0);$cost=$number($values['costo'],'Costo',0,4);$tax=$number($values['iva'],'IVA',0,2,100);$stock=$number($values['existencia'],'Existencia',0,3,9999999);$minimum=$number($values['stock_minimo'],'Stock mínimo',0,3,9999999);$decimals=(int)$number($values['decimales'],'Decimales',0,0,3);$min=$number($values['cantidad_minima'],'Cantidad mínima',1,$decimals,9999999);$step=$number($values['incremento'],'Incremento',1,$decimals,9999999);if($min<=0||$step<=0)throw new DomainException('Cantidad mínima e incremento deben ser mayores que cero.');
            $control=mb_strtolower($values['control_inventario']);if(!in_array($control,['','si','sí','no','1','0'],true))throw new DomainException('Control de inventario: indica Si o No.');$tracked=in_array($control,['si','sí','1'],true);if(!$tracked&&($stock>0||$minimum>0))throw new DomainException('Activa control_inventario para registrar existencias o mínimos.');
            $status=mb_strtolower($values['estado']?:'activo');$statuses=['activo'=>'active','oculto'=>'hidden','agotado'=>'sold_out'];if(!isset($statuses[$status]))throw new DomainException('Estado: usa Activo, Oculto o Agotado.');
            $quantityProduct=r11_quantity_product(['unit_label'=>$values['unidad']?:'unidad','min_qty'=>$min,'qty_step'=>$step,'decimals'=>$decimals]);if($quantityProduct['quantity_mode']==='unit'&&($decimals!==0||$min!==1.0||$step!==1.0||abs($stock-round($stock))>.000001))throw new DomainException('Por unidad: decimales 0, mínimo e incremento 1 y existencia entera. Para granel indica kg, g, litros o metros.');$min=(float)$quantityProduct['min_qty'];$step=(float)$quantityProduct['qty_step'];$decimals=(int)$quantityProduct['decimals'];
            $items[]=['line'=>$line,...$values,'price'=>$price,'cost'=>$cost,'tax'=>$tax,'stock'=>$stock,'minimum'=>$minimum,'tracked'=>(int)$tracked,'min'=>$min,'step'=>$step,'decimals'=>$decimals,'status'=>$statuses[$status]];$skus[]=$values['sku'];
        }catch(DomainException $e){$errors[]='Fila '.$line.': '.$e->getMessage();}
    }
    if(!$items&&!$errors)$errors[]='Agrega al menos un producto en la hoja Productos.';
    if(count($items)>500)throw new DomainException('Carga hasta 500 productos por archivo.');
    if($skus){$select=implode(' UNION ALL ',array_fill(0,count($skus),'SELECT ? sku'));$existing=$app->all('SELECT DISTINCT imported.sku FROM ('.$select.') imported JOIN products p ON p.sku=imported.sku WHERE p.tenant_id=?',[...$skus,$tenant['id']]);$dup=array_fill_keys(array_map(fn($r)=>mb_strtolower($r['sku']),$existing),true);foreach($items as $item)if(isset($dup[mb_strtolower($item['sku'])]))$errors[]='Fila '.$item['line'].': el SKU '.$item['sku'].' ya existe. Esta carga crea productos nuevos.';
        $duplicates=$app->all('SELECT MIN(sku) sku FROM ('.$select.') imported GROUP BY sku HAVING COUNT(*)>1',$skus);if($duplicates)$errors[]='Hay códigos SKU equivalentes repetidos en el archivo.';}
    return [$items,$errors];
}
function qa180_import_permissions(App $app,array $user,array $tenant,array $items):void {
    qa180_permission($app,$user,'productos','write');$categories=array_column($app->all('SELECT name FROM categories WHERE tenant_id=?',[$tenant['id']]),'name');$known=array_fill_keys(array_map('mb_strtolower',$categories),true);
    $inventory=false;$newCategory=false;foreach($items as $item){$inventory=$inventory||(bool)$item['tracked'];$newCategory=$newCategory||($item['categoria']!==''&&!isset($known[mb_strtolower($item['categoria'])]));}
    if($inventory)qa180_permission($app,$user,'inventario','write');if($newCategory)qa180_permission($app,$user,'categorias','write');
}
function qa180_import_products(App $app,array $user,array $tenant):void {
    qa180_permission($app,$user,'productos','write');$tid=(int)$tenant['id'];$branches=$app->all('SELECT * FROM branches WHERE tenant_id=? AND is_active=1 ORDER BY is_default DESC,id',[$tid]);$error='';$issues=[];
    if(isset($_GET['machote'])){header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="FISITAAP-Machote-Productos.xlsx"');header('Cache-Control: private, no-store');readfile(CODE_PATH.'/assets/machote-productos.xlsx');return;}
    if(is_post()){
        verify_csrf();try{
            $action=(string)($_POST['action']??'preview');
            if($action==='preview'){
                session_change176(static function(){unset($_SESSION['product_import180']);});
                $branch=$app->one('SELECT * FROM branches WHERE id=? AND tenant_id=? AND is_active=1',[(int)($_POST['branch_id']??0),$tid]);if(!$branch)throw new DomainException('Elige una sucursal activa de este negocio.');
                $file=$_FILES['products_file']??null;if(!$file||(int)$file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new DomainException('No se recibió el archivo. Usa Excel/CSV de hasta 5 MB.');$rows=qa180_import_read($file['tmp_name'],strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)));[$items,$issues]=qa180_import_validate($app,$user,$tenant,$rows);
                if(!$issues){qa180_import_permissions($app,$user,$tenant,$items);session_change176(static function()use($tid,$user,$branch,$rows,$items){$_SESSION['product_import180']=['tenant'=>$tid,'user'=>(int)$user['id'],'branch'=>(int)$branch['id'],'rows'=>$rows,'items'=>$items,'key'=>bin2hex(random_bytes(16)),'expires'=>time()+1200];});}
            }elseif($action==='commit'){
                $draft=$_SESSION['product_import180']??[];if(($draft['tenant']??0)!==$tid||($draft['user']??0)!==(int)$user['id']||($draft['expires']??0)<time()||!hash_equals((string)($draft['key']??''),(string)($_POST['import_key']??'')))throw new DomainException('La revisión venció. Vuelve a cargar el archivo.');
                $app->db->beginTransaction();$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tid]);$hash=hash('sha256',json_encode([$draft['branch'],$draft['rows']],JSON_THROW_ON_ERROR));$prior=$app->one('SELECT * FROM fisitaap_operations180 WHERE tenant_id=? AND operation_key=?',[$tid,$draft['key']]);
                if($prior){if($prior['kind']!=='product_import'||!hash_equals($prior['payload_hash'],$hash))throw new DomainException('La referencia corresponde a otra carga.');$app->db->commit();flash('success','Esta carga ya fue creada. No se duplicaron productos.');redirect(url('admin/productos'));}
                $branch=$app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? AND is_active=1 FOR UPDATE',[$draft['branch'],$tid]);if(!$branch)throw new DomainException('La sucursal ya no está disponible.');[$items,$errors]=qa180_import_validate($app,$user,$tenant,$draft['rows']);if($errors)throw new DomainException(implode(' ',array_slice($errors,0,5)));qa180_import_permissions($app,$user,$tenant,$items);
                $categoryMap=[];foreach($app->all('SELECT id,name FROM categories WHERE tenant_id=?',[$tid]) as $c)$categoryMap[mb_strtolower($c['name'])]=(int)$c['id'];$first=0;
                foreach($items as $item){
                    $category=null;$key=mb_strtolower($item['categoria']);if($key!==''){$category=$categoryMap[$key]??null;if(!$category){$slug=mb_substr(slugify($item['categoria']),0,100)?:'categoria';$slug.='-'.bin2hex(random_bytes(4));$app->exec('INSERT INTO categories(tenant_id,name,slug) VALUES(?,?,?)',[$tid,$item['categoria'],$slug]);$category=(int)$app->db->lastInsertId();$categoryMap[$key]=$category;}}
                    $slug=(mb_substr(slugify($item['nombre']),0,160)?:'producto').'-'.bin2hex(random_bytes(6));$app->exec('INSERT INTO products(tenant_id,category_id,name,slug,sku,description,price,cost,tax_rate,unit_label,min_qty,qty_step,decimals,track_inventory,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tid,$category,$item['nombre'],$slug,$item['sku'],$item['descripcion'],$item['price'],$item['cost'],$item['tax'],$item['unidad']?:'unidad',$item['min'],$item['step'],$item['decimals'],$item['tracked'],$item['status']]);$pid=(int)$app->db->lastInsertId();$first=$first?:$pid;
                    $app->exec('INSERT INTO product_inventory(tenant_id,branch_id,product_id,track_stock,quantity,min_stock,avg_cost,last_cost) VALUES(?,?,?,?,?,?,?,?)',[$tid,$branch['id'],$pid,$item['tracked'],$item['stock'],$item['minimum'],$item['cost'],$item['cost']]);
                    if(($tenant['catalog_mode']??'global')==='branches')$app->exec('INSERT INTO branch_products(branch_id,product_id,is_active) VALUES(?,?,1)',[$branch['id'],$pid]);
                    if($item['stock']>0)$app->exec('INSERT INTO inventory_movements(tenant_id,branch_id,product_id,movement_type,quantity,before_quantity,after_quantity,unit_cost,reference_type,reference_id,notes,user_id) VALUES(?,?,?,"initial",?,0,?,?,"product",?,"Existencia inicial · Excel",?)',[$tid,$branch['id'],$pid,$item['stock'],$item['stock'],$item['cost'],$pid,$user['id']]);
                }
                qa180_modifier_sync($app,$tid);$app->exec('INSERT INTO fisitaap_operations180(tenant_id,branch_id,user_id,operation_key,payload_hash,kind,result_id) VALUES(?,?,?,?,?,"product_import",?)',[$tid,$branch['id'],$user['id'],$draft['key'],$hash,$first]);qa180_audit($app,$user,'product_import','product',$first,'Creó '.count($items).' productos desde Excel/CSV');$app->db->commit();session_change176(static function(){$_SESSION['product_import180']['committed']=true;});flash('success',count($items).' productos creados correctamente.');redirect(url('admin/productos?branch_id='.(int)$branch['id']));
            }else throw new DomainException('Acción no disponible.');
        }catch(Throwable $e){if($app->db->inTransaction())$app->db->rollBack();$error=$e instanceof PDOException?'No se creó ningún producto. Revisa la carga antes de reintentar.':$e->getMessage();}
    }
    $draft=$_SESSION['product_import180']??[];if(($draft['tenant']??0)!==$tid||($draft['user']??0)!==(int)$user['id']||($draft['expires']??0)<time()||!empty($draft['committed']))$draft=[];
    admin_shell_start('Crear productos desde Excel',$user,$tenant,'productos');echo '<section class="card admin-card"><h2>Preparar carga de productos</h2><p>1. Descarga el machote. 2. Completa la hoja Productos. 3. Revisa la carga antes de crear.</p><a class="btn btn-light" href="?machote=1">Descargar machote Excel</a> <a class="btn btn-light" href="'.e(url('admin/productos')).'">Volver a productos</a><p>Hasta 500 productos y 5 MB por archivo .xlsx o CSV. Conserva los encabezados. SKU, nombre y precio son obligatorios. Las otras columnas tienen valores predeterminados indicados en el machote. No modifica productos existentes.</p>';
    if($error)echo '<p class="alert alert-error" role="alert">'.e($error).'</p>';if($issues){echo '<div class="alert alert-error" role="alert"><p>No se creó ningún producto. Corrige estas filas y vuelve a cargar:</p><ul>';foreach(array_slice($issues,0,50) as $issue)echo '<li>'.e($issue).'</li>';echo '</ul></div>';}
    echo '<form method="post" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="preview"><label>Sucursal para existencias iniciales<select class="input" name="branch_id" required>';foreach($branches as $branch)echo '<option value="'.(int)$branch['id'].'">'.e($branch['name']).'</option>';echo '</select></label><label>Archivo de productos<input class="input" type="file" name="products_file" accept=".xlsx,.csv" required></label><button class="btn">Revisar archivo</button></form></section>';
    if($draft){$count=count($draft['items']);$branchName='';foreach($branches as $branch)if((int)$branch['id']===$draft['branch'])$branchName=$branch['name'];echo '<section class="card admin-card"><h2>Revisión: '.$count.' '.($count===1?'producto nuevo':'productos nuevos').'</h2><p>Sucursal: <strong>'.e($branchName).'</strong>. Las categorías faltantes se crearán. Las existencias se registran únicamente en esta sucursal; los productos siguen el modo de catálogo del negocio.</p><div class="table-wrap"><table class="table"><tr><th>Fila</th><th>SKU</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>IVA</th><th>Inventario</th><th>Existencia</th><th>Estado</th></tr>';foreach($draft['items'] as $item)echo '<tr><td>'.$item['line'].'</td><td>'.e($item['sku']).'</td><td>'.e($item['nombre']).'</td><td>'.e($item['categoria']).'</td><td>'.money($item['price']).'</td><td>'.e((string)$item['tax']).'%</td><td>'.($item['tracked']?'Sí':'No').'</td><td>'.e((string)$item['stock']).'</td><td>'.e($item['estado']?:'Activo').'</td></tr>';echo '</table></div><form method="post">'.csrf_field().'<input type="hidden" name="action" value="commit"><input type="hidden" name="import_key" value="'.e($draft['key']).'"><button class="btn">Crear '.$count.' '.($count===1?'producto':'productos').'</button></form><p>Si hay un error, no se guarda ninguna fila. La revisión vence en 20 minutos.</p></section>';}
    admin_shell_end();
}
