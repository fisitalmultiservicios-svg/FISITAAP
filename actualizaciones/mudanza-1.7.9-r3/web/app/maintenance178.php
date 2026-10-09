<?php
declare(strict_types=1);

function maintenance178(App $app): void
{
    $user=require_login(['master']);
    if (!$app->one('SELECT id FROM users WHERE id=? AND role=? AND is_active=1',[(int)$user['id'],'master'])) {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }
    $message='';
    if (is_post()) {
        verify_csrf();
        if (($_POST['backup']??'')!=='yes') throw new RuntimeException('Confirma el respaldo antes de continuar.');
        $indexes=[
            ['sales','idx_sales_tenant_date178','tenant_id,created_at,status'],
            ['fisitaap_r2_shift_links','idx_shift_link_shift','shift_id'],
            ['fisitaap_r2_snapshots','idx_snapshot_device_created','device_id,created_at'],
        ];
        try {
            foreach ($indexes as [$table,$name,$columns]) {
                if (!$app->one('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?',[$table,$name])) {
                    $app->db->exec('ALTER TABLE '.$table.' ADD INDEX '.$name.' ('.$columns.')');
                }
            }
            $app->exec('INSERT INTO settings(`key`,`value`) VALUES("maintenance178_active","1") ON DUPLICATE KEY UPDATE `value`="1"');
            $message='Optimización de base de datos completada. Ya puedes reabrir las cajas.';
        } catch (PDOException $error) {
            error_log('FISITAAP maintenance178: '.$error->getCode());
            $message='No se pudo completar la optimización. Conserva el respaldo y solicita revisión del registro de errores de cPanel.';
        }
    }
    layout_start('Optimizar base de datos');
    echo '<main class="auth-page"><section class="card auth-card"><h1>Actualización 1.7.8</h1><p>Prepara índices para ventas, turnos y catálogos. Guarda respaldo y detén las cajas durante este paso.</p><p>'.e($message).'</p><form method="post">'.csrf_field().'<label><input type="checkbox" name="backup" value="yes" required> Guardé respaldo y detuve las cajas.</label><button class="btn btn-block">Completar actualización</button></form><p><a href="'.url('master').'">Volver al panel</a></p></section></main>';
    layout_end();
}
