<?php
declare(strict_types=1);

function qa180_kpi_chart(string $title,array $rows):void {
    $max=max([1,...array_map('floatval',array_column($rows,'total'))]);
    echo '<section class="card admin-card"><h2>'.e($title).'</h2><div class="r2-chart" role="img" aria-label="'.e($title).'">';
    foreach($rows as $row)echo '<div class="r2-bar"><span>'.money((float)$row['total']).'</span><i style="height:'.max(1,(int)round((float)$row['total']/$max*180)).'px"></i><small>'.e($row['day']).'</small></div>';
    if(!$rows)echo '<p>No hay movimientos en este periodo.</p>';
    echo '</div></section>';
}
function qa180_kpis(App $app,array $user,array $tenant):void {
    [$from,$to]=valid_report_dates_v1();$until=date('Y-m-d',strtotime($to.' +1 day'));
    $branch=(int)($_GET['branch']??0);$branches=$app->all('SELECT id,name FROM branches WHERE tenant_id=? AND is_active=1 ORDER BY name',[$tenant['id']]);
    if($branch&&!in_array($branch,array_map('intval',array_column($branches,'id')),true))throw new DomainException('Sucursal no disponible.');
    $scope=$branch?' AND branch_id=?':'';$args=[$tenant['id'],$from,$until];if($branch)$args[]=$branch;
    $sales=$app->all('SELECT DATE(created_at) day,SUM(total) total,COUNT(*) count FROM sales WHERE tenant_id=? AND created_at>=? AND created_at<? AND status IN("completed","credit")'.$scope.' GROUP BY DATE(created_at) ORDER BY day',$args);
    $orders=$app->all('SELECT DATE(created_at) day,SUM(total) total,COUNT(*) count FROM orders WHERE tenant_id=? AND created_at>=? AND created_at<? AND status<>"cancelled"'.$scope.' GROUP BY DATE(created_at) ORDER BY day',$args);
    $total=array_sum(array_column($sales,'total'));$count=array_sum(array_column($sales,'count'));
    admin_shell_start('KPIs',$user,$tenant,'kpis');echo '<section class="card admin-card"><form method="get"><div class="split">'.field('Desde','from',$from,true,'date').field('Hasta','to',$to,true,'date').'</div><label>Sucursal<select name="branch" class="input"><option value="0">Todas</option>';
    foreach($branches as $row)echo '<option value="'.(int)$row['id'].'" '.($branch===(int)$row['id']?'selected':'').'>'.e($row['name']).'</option>';
    echo '</select></label><button class="btn">Consultar</button></form><p>Ventas muestra documentos cobrados o registrados a crédito. Pedidos web muestra solicitudes; sus importes se presentan aparte para evitar contarlos dos veces.</p></section><div class="grid grid-3">';
    foreach(['Ventas registradas'=>money((float)$total),'Operaciones de venta'=>(string)$count,'Ticket promedio'=>money($count?$total/$count:0),'Pedidos web'=>(string)array_sum(array_column($orders,'count')),'Importe solicitado en web'=>money((float)array_sum(array_column($orders,'total')))] as $label=>$value)echo '<article class="card stat"><small>'.e($label).'</small><h2>'.e($value).'</h2></article>';
    echo '</div>';qa180_kpi_chart('Ventas registradas por día',$sales);qa180_kpi_chart('Pedidos web por día',$orders);
    $qualified=$branch?' AND s.branch_id=?':'';
    $products=$app->all('SELECT i.product_name,SUM(i.quantity) quantity,SUM(i.line_subtotal) amount FROM sale_items i JOIN sales s ON s.id=i.sale_id WHERE s.tenant_id=? AND s.created_at>=? AND s.created_at<? AND s.status IN("completed","credit")'.$qualified.' GROUP BY i.product_name ORDER BY amount DESC LIMIT 20',$args);
    echo '<section class="card admin-card"><h2>Productos más vendidos</h2><div class="table-wrap"><table class="table"><tr><th>Producto</th><th>Cantidad</th><th>Neto</th></tr>';
    foreach($products as $row)echo '<tr><td>'.e($row['product_name']).'</td><td>'.e($row['quantity']).'</td><td>'.money((float)$row['amount']).'</td></tr>';
    echo '</table></div></section>';admin_shell_end();
}
