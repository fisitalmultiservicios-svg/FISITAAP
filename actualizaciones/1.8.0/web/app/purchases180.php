<?php
declare(strict_types=1);

function qa180_tax_id(string $value):string {return preg_replace('/[^0-9]/','',$value)??'';}
function qa180_supplier(App $app,array $tenant,array $input,?array $xml):int {
    $id=(int)($input['supplier_id']??0);$tax=qa180_tax_id((string)($xml['supplier_tax_id']??''));
    if($tax!==''){
        // Serialize canonical matching and creation across branches. Existing
        // identifiers retain their display formatting; comparison is normalized.
        $app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
        $found=null;foreach($app->all('SELECT id,tax_id FROM suppliers WHERE tenant_id=?',[$tenant['id']]) as $row)if(qa180_tax_id((string)$row['tax_id'])===$tax){$found=(int)$row['id'];break;}
        if($found){if($id&&$id!==$found)throw new DomainException('La cédula del XML pertenece a otro proveedor de esta lista. Selecciona el emisor detectado.');$id=$found;}
        elseif(!$id){if(empty($input['create_supplier']))throw new DomainException('No encontramos la cédula del emisor. Confirma «Crear proveedor del XML».');$name=mb_substr(trim((string)$xml['supplier']),0,190);if($name===''||strlen($tax)<5||strlen($tax)>20)throw new DomainException('Revisa el nombre y la cédula del emisor.');$app->exec('INSERT INTO suppliers(tenant_id,name,tax_id,payment_terms_days) VALUES(?,?,?,30)',[$tenant['id'],$name,$tax]);$id=(int)$app->db->lastInsertId();}
        else{$selected=$app->one('SELECT tax_id FROM suppliers WHERE id=? AND tenant_id=?',[$id,$tenant['id']]);if(!$selected||qa180_tax_id((string)$selected['tax_id'])!==$tax)throw new DomainException('La cédula del proveedor seleccionado no coincide con el XML.');}
    }
    if(!$app->one('SELECT id FROM suppliers WHERE id=? AND tenant_id=? AND is_active=1',[$id,$tenant['id']]))throw new DomainException('Selecciona un proveedor activo de este negocio.');
    return $id;
}
function qa180_purchase_data(App $app,array $tenant,array $input,?array $xml):array {
    $lines=[];$source=$xml?$xml['lines']:(array)($input['line']??[]);
    foreach($source as $i=>$row){
        $pid=(int)($xml?($input['mapping'][$i]??0):($row['product_id']??0));$new=!empty($input['new_product'][$i]);
        if(!$pid&&!$new&&!$xml)continue;
        if(!$pid&&!$new)throw new DomainException('Relaciona cada línea con un producto o marca «Crear producto».');
        $qty=r2_number($row['quantity']??0,.001,99999);$cost=r2_number($row['unit_cost']??0);$net=round($qty*$cost,2);$tax=$xml?r2_number($row['tax']??0):round($net*r2_number($row['tax_rate']??0,0,100)/100,2);
        $line=['product_id'=>$pid,'quantity'=>$qty,'unit_cost'=>$cost,'tax'=>$tax];
        if($new){$spec=(array)($input['new'][$i]??[]);$sku=mb_substr(trim((string)($spec['sku']??($row['sku']??''))),0,80);$name=mb_substr(trim((string)($spec['name']??($row['name']??''))),0,190);$category=(int)($spec['category_id']??0);
            if($sku===''||$name===''||!$app->one('SELECT id FROM categories WHERE id=? AND tenant_id=?',[$category,$tenant['id']]))throw new DomainException('Un producto nuevo necesita SKU, nombre y categoría de este negocio.');
            $line['product_id']=0;$line['new']=['sku'=>$sku,'name'=>$name,'category_id'=>$category,'price'=>r2_number($spec['price']??0),'tax_rate'=>r2_number($spec['tax_rate']??0,0,100),'initial_stock'=>r2_number($spec['initial_stock']??0,0,99999)];
        }elseif(!$app->one('SELECT id FROM products WHERE id=? AND tenant_id=? AND product_type="simple"',[$pid,$tenant['id']]))throw new DomainException('Producto de compra no disponible.');
        $lines[]=$line;
    }
    if(!$lines||count($lines)>200)throw new DomainException('Una compra necesita entre 1 y 200 productos.');
    $total=round(array_sum(array_map(fn($line)=>round($line['quantity']*$line['unit_cost'],2)+$line['tax'],$lines)),2);$credit=!empty($input['credit']);$paid=$credit?r2_number($input['amount_paid']??0,0,$total):$total;
    $due=(string)($input['due_date']??date('Y-m-d'));if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due)||date('Y-m-d',strtotime($due)?:0)!==$due)throw new DomainException('Fecha de vencimiento inválida.');
    $method=(string)($input['payment_method']??'Efectivo');$account=(int)($input['account_id']??0);
    if(!in_array($method,['Efectivo','Tarjeta','SINPE Móvil','Transferencia'],true)||($paid>0&&(($method==='Efectivo')!==($account===0))))throw new DomainException('Selecciona la caja para efectivo o una cuenta para los otros medios.');
    if($paid>0&&$account&&!$app->one('SELECT id FROM fisitaap_money_accounts180 WHERE id=? AND tenant_id=? AND is_active=1',[$account,$tenant['id']]))throw new DomainException('Cuenta de dinero no disponible.');
    return ['supplier_id'=>qa180_supplier($app,$tenant,$input,$xml),'lines'=>$lines,'invoice'=>mb_substr(trim((string)($xml['number']??($input['invoice_number']??''))),0,100),'total'=>$total,'paid'=>$paid,'credit'=>$credit,'due'=>$due,'method'=>$method,'account'=>$account,'reference'=>mb_substr(trim((string)($input['reference']??'')),0,150),'notes'=>mb_substr(trim((string)($input['notes']??'')),0,1000)];
}
function qa180_save_draft(App $app,array $user,array $tenant,array $branch,array $input,?array $xml):int {
    qa180_permission($app,$user,'compras','write');$key=(string)($input['purchase_key']??'');if(!preg_match('/^[a-f0-9]{32,64}$/D',$key))throw new DomainException('Recarga el formulario de compra.');
    $app->db->beginTransaction();try{
        $app->one('SELECT id FROM branches WHERE id=? AND tenant_id=? AND is_active=1 FOR UPDATE',[$branch['id'],$tenant['id']]);
        $old=$app->one('SELECT * FROM fisitaap_purchase_drafts180 WHERE tenant_id=? AND operation_key=? FOR UPDATE',[$tenant['id'],$key]);
        if($old&&($old['status']!=='draft'||(int)$old['branch_id']!==(int)$branch['id']))throw new DomainException('Este borrador ya fue aplicado o pertenece a otra sucursal.');
        $data=qa180_purchase_data($app,$tenant,$input,$xml);$json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if($old){
            if($old['data']===$json){$app->db->commit();return (int)$old['id'];}
            if((int)($input['revision']??-1)!==(int)$old['revision'])throw new DomainException('El borrador cambió en otra pantalla. Vuelve a abrirlo.');
            $app->exec('UPDATE fisitaap_purchase_drafts180 SET data=?,revision=revision+1 WHERE id=? AND tenant_id=?',[$json,$old['id'],$tenant['id']]);$id=(int)$old['id'];
        }else{$app->exec('INSERT INTO fisitaap_purchase_drafts180(tenant_id,branch_id,user_id,operation_key,data) VALUES(?,?,?,?,?)',[$tenant['id'],$branch['id'],$user['id'],$key,$json]);$id=(int)$app->db->lastInsertId();}
        qa180_audit($app,$user,'purchase_draft','purchase_draft',$id,'Guardó borrador de compra '.$data['invoice'].'; todavía no afecta inventario ni deudas');$app->db->commit();return $id;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_apply_purchase(App $app,array $user,array $tenant,int $draftId,int $revision):int {
    qa180_permission($app,$user,'compras','write');$app->db->beginTransaction();try{
        // Match the lock order of manual purchases and draft updates.
        $head=$app->one('SELECT branch_id FROM fisitaap_purchase_drafts180 WHERE id=? AND tenant_id=?',[$draftId,$tenant['id']]);if(!$head)throw new DomainException('Borrador no disponible.');
        $branch=$app->one('SELECT * FROM branches WHERE id=? AND tenant_id=? AND is_active=1 FOR UPDATE',[$head['branch_id'],$tenant['id']]);if(!$branch)throw new DomainException('Sucursal no disponible.');
        $draft=$app->one('SELECT * FROM fisitaap_purchase_drafts180 WHERE id=? AND tenant_id=? FOR UPDATE',[$draftId,$tenant['id']]);
        if($draft['status']==='applied'){$app->db->commit();return (int)$draft['purchase_id'];}
        if($draft['status']!=='draft'||(int)$draft['revision']!==$revision)throw new DomainException('El borrador cambió. Ábrelo y revisa la nueva versión antes de aplicar.');
        $data=json_decode($draft['data'],true,512,JSON_THROW_ON_ERROR);$created=[];$app->one('SELECT id FROM tenants WHERE id=? FOR UPDATE',[$tenant['id']]);
        foreach($data['lines'] as &$line)if(!empty($line['new'])){
            $spec=$line['new'];$sku=$spec['sku'];
            if(isset($created[$sku])){if($created[$sku]['spec']!==$spec)throw new DomainException('Dos líneas usan el mismo SKU con datos de producto diferentes.');$line['product_id']=$created[$sku]['id'];unset($line['new']);continue;}
            if($app->one('SELECT id FROM products WHERE tenant_id=? AND sku=?',[$tenant['id'],$sku]))throw new DomainException('El SKU '.$sku.' ya existe. Edita el borrador y enlaza su producto.');
            if(!$app->one('SELECT id FROM categories WHERE id=? AND tenant_id=?',[$spec['category_id'],$tenant['id']]))throw new DomainException('La categoría del producto nuevo ya no está disponible.');
            $app->exec('INSERT INTO products(tenant_id,category_id,name,slug,sku,price,cost,tax_rate,product_type,track_inventory,status) VALUES(?,?,?,?,?,?,?,?,"simple",1,"active")',[$tenant['id'],$spec['category_id'],$spec['name'],slugify($spec['name']).'-'.bin2hex(random_bytes(3)),$sku,$spec['price'],$line['unit_cost'],$spec['tax_rate']]);$pid=(int)$app->db->lastInsertId();
            $created[$sku]=['id'=>$pid,'spec'=>$spec];$line['product_id']=$pid;
            if($spec['initial_stock']>0)stock_change_v14($app,(int)$tenant['id'],(int)$branch['id'],$pid,(float)$spec['initial_stock'],'adjustment','purchase_draft',$draftId,(int)$user['id'],'Existencia inicial anterior a la compra',(float)$line['unit_cost']);
            unset($line['new']);
        }unset($line);
        $id=r2_save_purchase($app,$user,$tenant,$branch,(int)$data['supplier_id'],$data['lines'],$data['invoice'],$draft['operation_key'],(float)$data['paid'],$data['due'],$data['notes']);
        if($data['paid']>0){
            $movement=qa180_money($app,$user,$branch,(int)$data['account'],-pos_cents_148($data['paid']),'purchase:'.$draft['operation_key'],'purchase_payment','Abono inicial compra '.$data['invoice'],$data['reference'],'purchase',$id);
            $entry=$app->one('SELECT shift_id FROM fisitaap_money_entries180 WHERE id=? AND tenant_id=?',[$movement,$tenant['id']]);$a=$app->one('SELECT id FROM accounts_payable WHERE tenant_id=? AND purchase_id=?',[$tenant['id'],$id]);
            $paymentKey=substr(hash('sha256','purchase:'.$draft['operation_key']),0,32);$hash=hash('sha256',json_encode([$id,$data['paid'],$data['method'],$data['account'],$data['reference']],JSON_THROW_ON_ERROR));
            $app->exec('INSERT INTO ap_payments(payable_id,amount,payment_method,reference,notes,user_id,payment_key,account_id,shift_id,payload_hash) VALUES(?,?,?,?,?,?,?,?,?,?)',[$a['id'],$data['paid'],$data['method'],$data['reference'],'Abono inicial',$user['id'],$paymentKey,$data['account']?:null,$entry['shift_id'],$hash]);
        }
        $app->exec('UPDATE fisitaap_purchase_drafts180 SET status="applied",purchase_id=?,revision=revision+1 WHERE id=? AND tenant_id=?',[$id,$draftId,$tenant['id']]);$app->db->commit();return $id;
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function qa180_purchases(App $app,array $user,array $tenant):void {
    $branch=physical_branch_v14($app,$tenant);
    if(is_post()){verify_csrf();try{
        $action=(string)($_POST['action']??'');
        if($action==='preview'){
            qa180_permission($app,$user,'compras','write');if(($_FILES['xml']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new DomainException('Selecciona un XML de hasta 2 MB.');$raw=(string)file_get_contents($_FILES['xml']['tmp_name']);$preview=r2_xml_purchase($raw);$preview['key']=hash('sha256',$raw);$_SESSION['xml180'][$tenant['id']]=$preview;flash('success','XML leído. Revisa el emisor, los productos y los costos.');redirect(url('admin/compras?xml=1'));
        }
        if($action==='apply'){if(($_POST['confirm']??'')!=='yes')throw new DomainException('Confirma la revisión de la compra antes de recibir.');$id=qa180_apply_purchase($app,$user,$tenant,(int)($_POST['id']??0),(int)($_POST['revision']??-1));flash('success','Compra recibida. Inventario, pago y saldo por pagar se actualizaron juntos.');redirect(url('admin/compras?purchase='.$id));}
        if($action==='save-draft'){$xml=!empty($_POST['xml'])?($_SESSION['xml180'][$tenant['id']]??null):null;if(!empty($_POST['xml'])&&!$xml)throw new DomainException('Vuelve a cargar el XML.');$id=qa180_save_draft($app,$user,$tenant,$branch,$_POST,$xml);unset($_SESSION['xml180'][$tenant['id']]);flash('success','Borrador guardado. Aún no modifica el inventario ni genera deuda.');redirect(url('admin/compras?draft='.$id));}
        throw new DomainException('Acción de compra no disponible.');
    }catch(Throwable $error){flash('error',$error instanceof PDOException?'No se aplicó la compra. Revisa sus datos y el historial antes de repetirla.':$error->getMessage());redirect(url('admin/compras'.(!empty($_POST['id'])?'?draft='.(int)$_POST['id']:(!empty($_POST['xml'])?'?xml=1':''))));}}
    $products=$app->all('SELECT id,name,sku,cost FROM products WHERE tenant_id=? AND product_type="simple" ORDER BY name',[$tenant['id']]);$bySku=[];$byId=[];foreach($products as $product){if($product['sku']!=='')$bySku[$product['sku']]=$product;$byId[$product['id']]=$product;}
    $suppliers=$app->all('SELECT id,name,tax_id FROM suppliers WHERE tenant_id=? AND is_active=1 ORDER BY name',[$tenant['id']]);$categories=$app->all('SELECT id,name FROM categories WHERE tenant_id=? ORDER BY name',[$tenant['id']]);
    $draft=$app->one('SELECT * FROM fisitaap_purchase_drafts180 WHERE id=? AND tenant_id=? AND branch_id=?',[(int)($_GET['draft']??0),$tenant['id'],$branch['id']]);$data=$draft?json_decode($draft['data'],true):null;$xml=!empty($_GET['xml'])?($_SESSION['xml180'][$tenant['id']]??null):null;
    admin_shell_start('Compras',$user,$tenant,'compras');echo '<section class="card admin-card"><h2>Compra en '.e($branch['name']).'</h2><p>1. Revisa y guarda el borrador. 2. Confirma «Aplicar y recibir». Guardar no cambia existencias ni cuentas por pagar.</p><a class="btn btn-light" href="'.e(url('admin/compras')).'">Nueva manual</a><form method="post" enctype="multipart/form-data">'.csrf_field().pos_hidden_148('action','preview').'<label>Factura electrónica CRC 4.3 / 4.4<input class="input" type="file" name="xml" accept=".xml,application/xml,text/xml" required></label><button class="btn btn-light">Revisar XML</button></form></section>';
    if(!$draft||$draft['status']==='draft'){
        $supplier=(int)($data['supplier_id']??0);if($xml){$tax=qa180_tax_id((string)$xml['supplier_tax_id']);foreach($suppliers as $s)if($tax!==''&&qa180_tax_id((string)$s['tax_id'])===$tax)$supplier=(int)$s['id'];}
        echo '<section class="card admin-card"><h2>'.($draft?'Editar borrador #'.(int)$draft['id']:($xml?'Revisar XML':'Compra manual')).'</h2>'.r2_form('save-draft').pos_hidden_148('purchase_key',$draft['operation_key']??($xml['key']??bin2hex(random_bytes(16)))).pos_hidden_148('revision',$draft['revision']??0).pos_hidden_148('xml',$xml?1:0).'<label>Proveedor'.r2_select('supplier_id',$suppliers,$supplier,'name',true).'</label>';
        if($xml)echo '<p>Emisor: '.e($xml['supplier']).' · Cédula '.e($xml['supplier_tax_id']).' · '.e($xml['number']).'</p>'.(!$supplier?'<label><input type="checkbox" name="create_supplier"> Crear proveedor del XML usando esta cédula</label>':'');else echo field('Número de factura','invoice_number',$data['invoice']??'');
        $lines=$xml?$xml['lines']:($data['lines']??[['product_id'=>0,'quantity'=>1,'unit_cost'=>0,'tax_rate'=>0]]);
        echo '<div class="table-wrap"><table class="table"><tr><th>Producto / SKU</th><th>Enlace</th><th>Cantidad</th><th>Costo actual</th><th>Costo compra</th><th>Diferencia</th><th>Impuesto</th></tr><tbody data-purchase180-lines>';
        foreach($lines as $i=>$line){$pid=(int)($xml?($bySku[$line['sku']]['id']??0):($line['product_id']??0));$current=(float)($byId[$pid]['cost']??0);$cost=(float)$line['unit_cost'];$diff=$cost-$current;$new=$line['new']??[];
            echo '<tr><td>'.e($line['name']??($byId[$pid]['name']??'Producto')).'<small>'.e($line['sku']??($byId[$pid]['sku']??'')).'</small></td><td>'.r2_select($xml?'mapping['.$i.']':'line['.$i.'][product_id]',$products,$pid,'name',true).'</td><td>'.($xml?e($line['quantity']):'<input class="input" type="number" min=".001" step=".001" name="line['.$i.'][quantity]" value="'.e($line['quantity']).'">').'</td><td>'.money($current).'</td><td>'.($xml?money($cost):'<input class="input" type="number" min="0" step=".000001" name="line['.$i.'][unit_cost]" value="'.e($cost).'">').'</td><td class="'.($diff>0?'cost-up':($diff<0?'cost-down':'')).'">'.($diff>0?'+':'').money($diff).'</td><td>'.($xml?money((float)$line['tax']):'<input class="input" type="number" min="0" max="100" step=".01" name="line['.$i.'][tax_rate]" value="'.e($line['tax_rate']??(($line['quantity']*$cost)>0?round($line['tax']/($line['quantity']*$cost)*100,4):0)).'">').'</td></tr>';
            if($xml||$new)echo '<tr><td colspan="7"><details '.($new?'open':'').'><summary>Crear producto para esta línea</summary><label><input type="checkbox" name="new_product['.$i.']" '.($new?'checked':'').'> Crear al aplicar la compra</label><div class="qa180-filter">'.field('SKU','new['.$i.'][sku]',$new['sku']??($line['sku']??'')).field('Nombre','new['.$i.'][name]',$new['name']??($line['name']??'')).'<label>Categoría'.r2_select('new['.$i.'][category_id]',$categories,(int)($new['category_id']??0),'name',true).'</label>'.field('Precio de venta','new['.$i.'][price]',$new['price']??0,false,'number','.01').field('IVA de venta %','new['.$i.'][tax_rate]',$new['tax_rate']??13,false,'number','.01').field('Existencia anterior a esta compra','new['.$i.'][initial_stock]',$new['initial_stock']??0,false,'number','.001').'</div></details></td></tr>';
        }
        echo '</tbody></table></div>';if(!$xml)echo '<button class="btn btn-light" type="button" data-add-purchase180-line>Agregar línea</button>';
        echo '<label><input type="checkbox" name="credit" '.(($data['credit']??true)?'checked':'').'> Compra a crédito (sin marcar, se paga el total al recibir)</label><div class="qa180-filter">'.field('Abono inicial si es a crédito','amount_paid',$data['paid']??0,false,'number','.01').field('Fecha de vencimiento','due_date',$data['due']??date('Y-m-d',strtotime('+30 days')),true,'date').'<label>Medio de pago<select class="input" name="payment_method">';foreach(['Efectivo','Tarjeta','SINPE Móvil','Transferencia'] as $method)echo '<option '.(($data['method']??'Efectivo')===$method?'selected':'').'>'.e($method).'</option>';echo '</select></label></div>'.qa180_account_select($app,$tenant,(int)($data['account']??0)).field('Referencia','reference',$data['reference']??'').field('Notas','notes',$data['notes']??'').'<button class="btn">Guardar borrador</button></form></section>';
    }
    if($draft){echo '<section class="card admin-card"><h2>Borrador #'.(int)$draft['id'].' · '.money((float)$data['total']).'</h2><p>Abono '.money((float)$data['paid']).' · Saldo por pagar '.money((float)$data['total']-(float)$data['paid']).' · '.e($draft['status']==='applied'?'Aplicado':'Pendiente de aplicar').'</p>';if($draft['status']==='draft')echo r2_form('apply',(int)$draft['id']).pos_hidden_148('revision',$draft['revision']).'<label><input type="checkbox" name="confirm" value="yes" required> Revisé los productos, el costo y el pago. Recibir esta compra modifica inventario y cuentas.</label><button class="btn">Aplicar y recibir compra</button></form>';echo '</section>';}
    echo '<section class="card admin-card"><h2>Borradores de esta sucursal</h2>';foreach($app->all('SELECT id,data,status FROM fisitaap_purchase_drafts180 WHERE tenant_id=? AND branch_id=? AND status="draft" ORDER BY id DESC LIMIT 50',[$tenant['id'],$branch['id']]) as $row){$d=json_decode($row['data'],true);echo '<p><a href="?draft='.(int)$row['id'].'">#'.(int)$row['id'].' · '.e($d['invoice']).' · '.money((float)$d['total']).'</a></p>';}echo '</section>';
    [$from,$to]=valid_report_dates_v1();echo '<section class="card admin-card"><h2>Compras recibidas</h2><form method="get" class="qa180-filter">'.field('Desde','from',$from,true,'date').field('Hasta','to',$to,true,'date').'<button class="btn btn-light">Filtrar</button></form><div class="table-wrap"><table class="table"><tr><th>Fecha</th><th>Compra / factura</th><th>Proveedor</th><th>Total</th><th>Saldo</th></tr>';foreach($app->all('SELECT p.*,s.name FROM purchases p JOIN suppliers s ON s.id=p.supplier_id AND s.tenant_id=p.tenant_id WHERE p.tenant_id=? AND p.branch_id=? AND p.purchase_date>=? AND p.purchase_date<=? ORDER BY p.id DESC LIMIT 300',[$tenant['id'],$branch['id'],$from,$to]) as $row)echo '<tr><td>'.e($row['purchase_date']).'</td><td>'.e($row['purchase_number'].' / '.$row['invoice_number']).'</td><td>'.e($row['name']).'</td><td>'.money((float)$row['total']).'</td><td>'.money((float)$row['balance']).'</td></tr>';echo '</table></div></section>';admin_shell_end();
}
