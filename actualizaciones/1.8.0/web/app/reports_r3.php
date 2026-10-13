<?php
declare(strict_types=1);
function r3_report_definitions(): array
{
    return [
      'categories'=>['Categorías','categorias','SELECT name nombre,is_active activo,sort_order orden FROM categories WHERE tenant_id=? ORDER BY name',false],
      'options'=>['Extras y opcionales','opciones','SELECT p.name producto,g.name grupo,o.name opcion,o.price_delta precio,g.is_required obligatorio,g.min_select minimo,g.max_select maximo,o.is_active activo FROM product_options o JOIN product_option_groups g ON g.id=o.group_id JOIN products p ON p.id=g.product_id WHERE g.tenant_id=? ORDER BY p.name,g.name,o.name',false],
      'combos'=>['Combos','combos','SELECT p.name combo,c.name componente,i.quantity cantidad,p.price precio FROM fisitaap_r2_combo_items i JOIN products p ON p.id=i.combo_id JOIN products c ON c.id=i.product_id WHERE p.tenant_id=? ORDER BY p.name,c.name',false],
      'promotions'=>['Promociones','promociones','SELECT p.name producto,r.kind tipo,r.value valor,r.starts_at inicio,r.ends_at fin,r.is_active activo FROM fisitaap_r2_promotions r JOIN products p ON p.id=r.product_id WHERE r.tenant_id=? ORDER BY r.id DESC',false],
      'customers'=>['Clientes y origen de registro','clientes','SELECT u.name nombre,u.email correo,u.phone telefono,u.created_at creacion,s.source origen,(SELECT COUNT(*) FROM orders o WHERE o.tenant_id=tc.tenant_id AND o.user_id=u.id AND o.status!="cancelled") pedidos_web,(SELECT COUNT(*) FROM sales v WHERE v.tenant_id=tc.tenant_id AND v.customer_id=u.id AND v.status="completed") ventas_fisicas FROM tenant_customers tc JOIN users u ON u.id=tc.user_id LEFT JOIN fisitaap_r2_customer_sources s ON s.tenant_id=tc.tenant_id AND s.user_id=u.id WHERE tc.tenant_id=? AND u.role="customer" ORDER BY u.name',false],
      'suppliers'=>['Proveedores','proveedores','SELECT name nombre,phone telefono,email correo,address direccion,is_active activo FROM suppliers WHERE tenant_id=? ORDER BY name',false],
      'shifts'=>['Turnos','turnos','SELECT s.opened_at apertura,s.closed_at cierre,b.name sucursal,u.name cajero,s.opening_cash fondo,s.closing_cash contado,s.expected_cash esperado,s.difference_amount diferencia,s.status estado FROM pos_shifts s JOIN branches b ON b.id=s.branch_id JOIN users u ON u.id=s.user_id WHERE s.tenant_id=? AND DATE(s.opened_at) BETWEEN ? AND ? ORDER BY s.id DESC',true],
      'delivery'=>['Entregas y motorizados','entregas-historial','SELECT o.order_number pedido,j.created_at fecha,u.name motorizado,j.status estado,j.payout pago FROM delivery_jobs j JOIN orders o ON o.id=j.order_id LEFT JOIN users u ON u.id=j.assigned_driver_id WHERE j.tenant_id=? AND DATE(j.created_at) BETWEEN ? AND ? ORDER BY j.id DESC',true],
      'loyalty'=>['Fidelización y premios','fidelizacion','SELECT u.name cliente,a.points_balance puntos,a.purchases_balance compras,a.spend_balance consumo,a.rewards_available premios,a.updated_at actualizado FROM loyalty_accounts a JOIN users u ON u.id=a.user_id WHERE a.tenant_id=? ORDER BY u.name',false],
      'gifts'=>['Tarjetas de regalo y saldo','fidelizacion','SELECT c.code codigo,u.name cliente,c.initial_amount monto_inicial,c.balance saldo,c.expires_at vencimiento,c.status estado FROM fisitaap_r3_gift_cards c JOIN users u ON u.id=c.customer_id WHERE c.tenant_id=? ORDER BY c.id DESC',false],
      'branches'=>['Sucursales','sucursales','SELECT name nombre,address direccion,phone telefono,is_active activo FROM branches WHERE tenant_id=? ORDER BY name',false],
      'users'=>['Usuarios y roles','usuarios','SELECT name nombre,email correo,phone telefono,role rol,is_active activo FROM users WHERE tenant_id=? ORDER BY name',false],
    ];
}
function r3_report_can(App $app,array $u,array $t,string $section): bool
{
    return restructure_can_r1($app,$u,$section,'view')!==false&&module_enabled_v12($app,$t,section_module_v12($section));
}
function r3_master_reports(App $app,array $u): void
{
    $cut=(string)($_GET['cut']??'');$month=(string)($_GET['month']??date('Y-m'));$year=(int)($_GET['year']??date('Y'));
    if($cut==='month'&&preg_match('/^(20[0-9]{2})-(0[1-9]|1[0-2])$/',$month)){$from=$month.'-01';$to=date('Y-m-t',strtotime($from));}
    elseif($cut==='year'&&$year>=2000&&$year<=2099){$from=$year.'-01-01';$to=$year.'-12-31';}
    else [$from,$to]=valid_report_dates_v1();
    $rows=$app->all('SELECT t.name empresa,t.plan,t.expires_at vencimiento,t.is_active activa,COALESCE(w.n,0) pedidos_web,COALESCE(w.total,0) ventas_web,COALESCE(p.n,0) ventas_caja,COALESCE(p.total,0) importe_caja,(COALESCE(w.total,0)+COALESCE(p.total,0)) total FROM tenants t LEFT JOIN (SELECT tenant_id,COUNT(*) n,SUM(total) total FROM orders WHERE status!="cancelled" AND DATE(created_at) BETWEEN ? AND ? GROUP BY tenant_id) w ON w.tenant_id=t.id LEFT JOIN (SELECT tenant_id,COUNT(*) n,SUM(total) total FROM sales WHERE status="completed" AND DATE(created_at) BETWEEN ? AND ? GROUP BY tenant_id) p ON p.tenant_id=t.id WHERE t.slug NOT IN ("tienda-demo","restaurante-demo") AND t.slug NOT REGEXP "^demo-(restaurante|tienda)-[0-9a-f]{32}$" ORDER BY total DESC,t.name',[$from,$to,$from,$to]);
    if(isset($_GET['csv']))csv_download_v1('corte-fisitaap-'.$from.'-'.$to.'.csv',['Empresa','Plan','Vence','Activa','Pedidos web','Ventas web','Ventas caja','Importe caja','Total'],$rows);
    admin_shell_start('Cortes mensuales y anuales',$u,null,'reportes');echo '<section class="card admin-card"><form method="get"><div class="split">'.field('Mes','month',$month,true,'month').field('Año','year',$year,true,'number','1').'</div><button class="btn" name="cut" value="month">Corte del mes</button> <button class="btn btn-light" name="cut" value="year">Corte del año</button></form><hr><form method="get"><div class="split">'.field('Desde','from',$from,true,'date').field('Hasta','to',$to,true,'date').'</div><button class="btn">Consultar periodo</button> <button class="btn btn-light" name="csv" value="1">Descargar CSV</button></form></section><section class="card admin-card"><h2>'.e($from).' a '.e($to).'</h2><p>Total ventas web y caja: <strong>'.money(array_sum(array_column($rows,'total'))).'</strong></p><div class="table-wrap"><table class="table"><tr><th>Negocio</th><th>Plan</th><th>Vence</th><th>Estado</th><th>Web</th><th>Caja</th><th>Total</th></tr>';
    foreach($rows as $r)echo '<tr><td>'.e($r['empresa']).'</td><td>'.e($r['plan']).'</td><td>'.e($r['vencimiento']??'').'</td><td>'.($r['activa']?'Activo':'Suspendido').'</td><td>'.(int)$r['pedidos_web'].' · '.money((float)$r['ventas_web']).'</td><td>'.(int)$r['ventas_caja'].' · '.money((float)$r['importe_caja']).'</td><td>'.money((float)$r['total']).'</td></tr>';echo '</table></div></section>';admin_shell_end();
}
function r3_reports(App $app,array $u,array $t): void
{
    [$from,$to]=valid_report_dates_v1();$definitions=r3_report_definitions();$key=(string)($_GET['dataset']??'');
    if(isset($_GET['export'])){
        $map=['products'=>'productos','customers'=>'clientes','sales'=>'ventas','items'=>'ventas','inventory'=>'inventario','purchases'=>'compras','cxc'=>'cxc','cxp'=>'cxp'];$section=$map[(string)$_GET['export']]??'';
        if($section===''||!r3_report_can($app,$u,$t,$section)){http_response_code(403);simple_error('Acceso no autorizado','Tu rol no permite consultar este reporte.');return;}
        tenant_reports_v14($app,$u,$t);return;
    }
    $allowed=[];foreach($definitions as $k=>$d)if(r3_report_can($app,$u,$t,$d[1]))$allowed[$k]=$d;
    $rows=[];$query=trim(mb_substr((string)($_GET['q']??''),0,150));$status=trim(mb_substr((string)($_GET['status']??''),0,80));
    if($key!==''){
        if(!isset($allowed[$key])){http_response_code(403);simple_error('Acceso no autorizado','Tu rol no permite consultar este reporte.');return;}
        $d=$allowed[$key];$rows=$app->all($d[2],$d[3]?[$t['id'],$from,$to]:[$t['id']]);$rows=array_values(array_filter($rows,static fn($row)=>($query===''||mb_stripos(implode(' ',array_map('strval',$row)),$query)!==false)&&($status===''||($row['estado']??'')===$status)));
    }
    $columns=$rows?array_keys($rows[0]):[];if(isset($_GET['csv']))csv_download_v1('fisitaap-'.$key.'-'.$from.'-'.$to.'.csv',$columns,$rows);
    admin_shell_start('Reportes de todos los módulos',$u,$t,'reportes');
    echo '<section class="card admin-card"><h2>Catálogo, clientes, administración y entregas</h2><form method="get">'.field('Desde','from',$from,true,'date').field('Hasta','to',$to,true,'date').'<label>Módulo<select class="input" name="dataset">';foreach($allowed as $k=>$d)echo '<option value="'.e($k).'" '.($k===$key?'selected':'').'>'.e($d[0]).'</option>';echo '</select></label>'.field('Buscar por documento, cliente, producto o sucursal','q',$query).field('Estado exacto (opcional)','status',$status).'<button class="btn">Consultar</button> <button class="btn btn-light" name="csv" value="1">Descargar CSV</button></form><p class="muted">El periodo filtra turnos y entregas. Los demás reportes muestran el estado actual.</p></section>';
    if($key!==''){echo '<section class="card admin-card"><h2>'.e($allowed[$key][0]).'</h2><p>'.count($rows).' registros</p><div class="table-wrap"><table class="table"><tr>';foreach($columns as $c)echo '<th>'.e(ucfirst(str_replace('_',' ',$c))).'</th>';echo '</tr>';foreach(array_slice($rows,0,500) as $row){echo '<tr>';foreach($row as $v)echo '<td>'.e((string)$v).'</td>';echo '</tr>';}echo '</table></div>';if(!$rows)echo '<p>No hay información con estos filtros.</p>';if(count($rows)>500)echo '<p>Se muestran 500 registros. El CSV incluye todos.</p>';echo '</section>';}
    echo '<section class="card admin-card"><h2>Productos, ventas, inventario, compras y cuentas</h2><form method="get">'.field('Desde','from',$from,true,'date').field('Hasta','to',$to,true,'date').'<label>Información<select class="input" name="export">';foreach(['products'=>['Productos','productos'],'sales'=>['Ventas web y físicas','ventas'],'items'=>['Ventas por artículo','ventas'],'inventory'=>['Inventario valorizado','inventario'],'purchases'=>['Compras','compras'],'cxc'=>['Cuentas por cobrar','cxc'],'cxp'=>['Cuentas por pagar','cxp']] as $k=>[$label,$section])if(r3_report_can($app,$u,$t,$section))echo '<option value="'.$k.'">'.$label.'</option>';echo '</select></label><button class="btn">Descargar CSV para Excel</button></form></section>';admin_shell_end();
}
