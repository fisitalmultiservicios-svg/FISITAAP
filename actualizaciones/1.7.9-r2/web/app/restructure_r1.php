<?php
declare(strict_types=1);

const FISITAAP_RESTRUCTURE_R1 = '1.5.0-R1';

function restructure_active_r1(App $app): bool
{
    return $app->setting('restructure_r1_active', '0') === '1';
}

function restructure_sections_r1(): array
{
    return [
        'dashboard'=>'Resumen', 'categorias'=>'Categorías', 'productos'=>'Productos',
        'opciones'=>'Extras, opcionales y combos', 'cupones'=>'Promociones: cupones',
        'fidelizacion'=>'Fidelización', 'cocina'=>'Pantalla de pedidos', 'pedidos'=>'Historial de pedidos',
        'ventas'=>'POS / venta rápida', 'cobro'=>'Cobrar cuentas', 'express-pendientes'=>'Express pendientes',
        'turnos'=>'Turnos', 'clientes'=>'Clientes', 'inventario'=>'Inventario',
        'compras'=>'Compras y proveedores', 'cxc'=>'Cuentas por cobrar', 'cxp'=>'Cuentas por pagar',
        'reportes'=>'Reportes', 'configuracion'=>'Generales', 'sucursales'=>'Sucursales',
        'contenido'=>'Diseño y página del negocio', 'delivery'=>'Zonas de envío',
        'impresion'=>'Impresión', 'recibo'=>'Comprobantes', 'express'=>'Afiliados e invitaciones',
        'repartidor'=>'Seguimiento de entregas', 'blog'=>'Blog', 'contactos'=>'Contactos', 'auditoria'=>'Auditoría',
        'fisichat'=>'FISIChat',
    ] + (function_exists('r2_sections') ? r2_sections() : []);
}

function restructure_actions_r1(): array
{
    return ['view'=>'Consultar', 'write'=>'Crear y modificar', 'delete'=>'Eliminar / anular', 'charge'=>'Cobrar', 'credit'=>'Autorizar crédito', 'configure'=>'Configurar mesas'];
}

function restructure_available_actions_r1(string $section): array
{
    if ($section === 'credito') return ['view','credit'];
    if (in_array($section, ['dashboard','reportes','recibo','auditoria','fisichat','kpis','clientes-historial','entregas-historial'], true)) return ['view'];
    $actions = ['view', 'write'];
    if (in_array($section, ['categorias','productos','opciones','extras','opcionales','combos','promociones','proveedores','cupones','delivery','contenido','blog','ventas','pedidos','cocina'], true)) $actions[] = 'delete';
    if (in_array($section, ['ventas','cobro','express-pendientes'], true)) $actions[] = 'charge';
    if ($section === 'clientes') $actions[] = 'credit';
    if (in_array($section,['ventas','mesas'],true)) $actions[] = 'configure';
    return $actions;
}

function restructure_assignment_r1(App $app, array $user): ?array
{
    // Always scoped to the current business; no role or tenant identity comes from POST.
    return $app->one('SELECT r.id,r.name,r.is_active FROM fisitaap_r1_user_roles a JOIN fisitaap_r1_roles r ON r.id=a.role_id AND r.tenant_id=a.tenant_id WHERE a.user_id=? AND a.tenant_id=?', [(int)$user['id'], (int)($user['tenant_id']??0)]);
}

function restructure_can_r1(App $app, array $user, string $section, string $action='view'): ?bool
{
    if (!restructure_active_r1($app) || in_array($user['role'], ['master','tenant_admin','customer','driver'], true)) return null;
    static $cache = [];
    $cacheKey = (int)$user['id'].':'.(int)($user['tenant_id']??0);
    if (!array_key_exists($cacheKey, $cache)) {
        try {
            $role = restructure_assignment_r1($app, $user);
            if (!$role) $cache[$cacheKey] = null; // Preserve collaborators without a custom assignment.
            else {
                $permissions = [];
                if ($role['is_active']) {
                    foreach ($app->all('SELECT section_key,action_key FROM fisitaap_r1_permissions WHERE role_id=?', [(int)$role['id']]) as $row) $permissions[$row['section_key']][$row['action_key']] = true;
                }
                $cache[$cacheKey] = $permissions;
            }
        } catch (Throwable $e) {
            error_log('FISITAAP R1 permission lookup failed');
            $cache[$cacheKey] = []; // Fail closed if an activated custom permission system is unavailable.
        }
    }
    if ($cache[$cacheKey] === null) return null;
    $permissions = $cache[$cacheKey];
    return isset($permissions[$section]['view']) && isset($permissions[$section][$action]);
}

function restructure_role_name_r1(App $app, array $member): string
{
    if (restructure_active_r1($app) && !in_array($member['role'],['master','tenant_admin','customer','driver'],true)) {
        $assignment = restructure_assignment_r1($app,$member);
        if ($assignment) return $assignment['name'].(!$assignment['is_active']?' (suspendido)':'');
    }
    return ui_label_qa((string)$member['role']);
}

function restructure_home_r1(App $app, array $user): ?string
{
    if (!restructure_active_r1($app) || in_array($user['role'], ['master','tenant_admin','customer','driver'],true)) return null;
    if (!restructure_assignment_r1($app,$user)) return null;
    $tenant = $app->one('SELECT * FROM tenants WHERE id=?', [(int)$user['tenant_id']]);
    if (!$tenant) return url('admin');
    foreach (restructure_groups_r1($user,$tenant) as $links) foreach ($links as $section=>$label) {
        if ($section === 'roles' || $section === 'usuarios') continue;
        if (in_array($section,['cobro','express-pendientes'],true) && ($tenant['catalog_label']??'') !== 'Menú') continue;
        if (restructure_can_r1($app,$user,$section) === true && module_enabled_v12($app,$tenant,section_module_v12($section))) return url('admin'.($section==='dashboard'?'':'/'.$section));
    }
    return url('admin');
}

function restructure_groups_r1(array $user, ?array $tenant): array
{
    global $app;
    if(function_exists('r2_active') && r2_active($app)) return r2_groups($user,$tenant);
    if ($user['role'] === 'master') return [
        'Plataforma'=>['dashboard'=>'Resumen','empresas'=>'Negocios','planes'=>'Planes y suscripciones','modules'=>'Planes y módulos'],
        'Web oficial'=>['contenido'=>'Diseño y contenido','blog'=>'Blog'],
        'Analítica'=>['reportes'=>'Reportes'],
        'Administración'=>['auditoria'=>'Auditoría','configuracion'=>'Configuración','qa'=>'Comprobación del sitio','cache'=>'Caché'],
    ];
    $physical = $tenant && physical_store_v14($tenant);
    $groups = [
        'Inicio'=>['dashboard'=>'Resumen'],
        'Catálogo'=>['categorias'=>'Categorías','productos'=>'Productos','opciones'=>'Extras, opcionales y combos','cupones'=>'Promociones: cupones','fidelizacion'=>'Fidelización','cocina'=>'Pantalla de pedidos','pedidos'=>'Historial de pedidos'],
    ];
    if ($physical) $groups['Ventas'] = ['ventas'=>'POS / venta rápida','cobro'=>'Cobrar cuentas','express-pendientes'=>'Express pendientes','turnos'=>'Turnos'];
    $groups['Clientes'] = ['clientes'=>'Crear, buscar e historial','fidelizacion'=>'Fidelización'];
    if ($physical) {
        $groups['Clientes'] = ['clientes'=>'Clientes y crédito','cxc'=>'Cuentas por cobrar','fidelizacion'=>'Fidelización'];
        $groups['Compras'] = ['compras'=>'Compras y proveedores','cxp'=>'Cuentas por pagar','inventario'=>'Inventario'];
    }
    $groups['Analítica'] = ['reportes'=>'Reportes','dashboard'=>'Indicadores del negocio'];
    $groups['Administración'] = ['configuracion'=>'Generales','sucursales'=>'Sucursales','contenido'=>'Diseño y página','roles'=>'Roles y permisos','usuarios'=>'Usuarios','delivery'=>'Zonas de envío'];
    if ($physical) $groups['Administración']['impresion'] = 'Impresión';
    if ($physical) $groups['Entregas'] = ['express'=>'Afiliados e invitaciones','repartidor'=>'Seguimiento de entregas'];
    // Keep existing business content and audit reachable instead of dropping legacy features.
    $groups['Otros recursos'] = ['blog'=>'Blog','contactos'=>'Contactos','auditoria'=>'Auditoría'];
    return $groups;
}

function restructure_guard_r1(App $app, string $path): void
{
    if (!restructure_active_r1($app)) return;
    $user = current_user();
    if (!$user) return;
    // Refresh authentication so blocking a user or removing their access affects an open session.
    $fresh = $app->one('SELECT id,name,email,phone,role,tenant_id,is_active,force_password_change FROM users WHERE id=?', [(int)$user['id']]);
    if (!$fresh || !$fresh['is_active']) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirect(url('owner-login'));
    }
    $_SESSION['user'] = $fresh;
    $user = $fresh;
    if ($fresh['force_password_change'] && !in_array($path, ['change-password','logout'], true)) {
        if (str_starts_with($path, 'api/')) json_response(['ok'=>false,'error'=>'Cambia primero tu contraseña temporal.','redirect'=>url('change-password')],403);
        redirect(url('change-password'));
    }
    if ($path !== 'admin' && !str_starts_with($path, 'admin/') && !in_array($path, ['api/pos-options','api/fisichat'], true)) return;
    if (in_array($user['role'], ['master','tenant_admin','customer','driver'], true)) return;
    if ($path === 'api/fisichat') {
        // The existing assistant is able to mutate data using legacy role checks.
        // Custom roles must use the normal guarded screens until its individual tools have granular permissions.
        if (restructure_assignment_r1($app, $user)) json_response(['ok'=>false,'error'=>'Tu rol personalizado debe realizar las operaciones desde las pantallas autorizadas. FISIChat sigue disponible para el dueño.'],403);
        return;
    }
    $section = $path === 'api/pos-options' ? 'ventas' : (explode('/', $path)[1]??'dashboard');
    $action = 'view';
    if (is_post()) {
        $action = 'write';
        $operation = strtolower((string)($_POST['action']??''));
        if (str_contains($operation, 'delete') || str_contains($operation, 'remove') || str_contains($operation, 'eliminar')) $action = 'delete';
        if (in_array($section,['clientes','credito'],true) && $operation === 'credit') $action = 'credit';
        if ($section === 'mesas') $action = 'configure';
        if ($section === 'ventas' && $operation === 'quantity' && (float)($_POST['quantity']??-1) === 0.0) $action = 'delete';
        if ($section === 'ventas' && $operation === 'void') $action = 'delete';
        if ($section === 'ventas' && in_array($operation,['save_table','save_group'],true)) $action = 'configure';
        if (in_array($section,['pedidos','cocina'],true) && ($_POST['status']??'') === 'cancelled') $action = 'delete';
        if ($section === 'cobro' || ($section === 'ventas' && ($_GET['screen']??'') === 'checkout')) $action = 'charge';
        if (in_array($section, ['ventas','cobro'], true) && (float)($_POST['credit']??0)>0 && restructure_can_r1($app,$user,(function_exists('r2_active')&&r2_active($app)?'credito':'clientes'),'credit') === false) {
            http_response_code(403); simple_error('Crédito restringido','Tu rol no permite registrar ventas a crédito.'); exit;
        }
    }
    if (restructure_can_r1($app, $user, $section, $action) === false) {
        if ($path === 'api/pos-options') json_response(['ok'=>false,'error'=>'Tu rol no permite consultar productos de caja.'],403);
        http_response_code(403);
        simple_error('Acceso restringido', 'Tu rol no permite esta operación. Solicita acceso al dueño del negocio.');
        exit;
    }
}

function restructure_permissions_form_r1(array $permissions): void
{
    $labels = restructure_actions_r1();
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Módulo</th>';
    foreach ($labels as $label) echo '<th>'.e($label).'</th>';
    echo '</tr></thead><tbody>';
    foreach (restructure_sections_r1() as $section=>$label) {
        // FISIChat permissions in R2 only enable the read-only collaborator assistant.
        $available = restructure_available_actions_r1($section);
        echo '<tr><th scope="row">'.e($label).'</th>';
        foreach ($labels as $action=>$actionLabel) {
            echo '<td>';
            if (in_array($action, $available, true)) echo '<label><input type="checkbox" name="permissions['.e($section).'][]" value="'.e($action).'" '.(isset($permissions[$section][$action])?'checked':'').' aria-label="'.e($label.': '.$actionLabel).'"></label>';
            else echo '<span aria-hidden="true">—</span>';
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function restructure_roles_r1(App $app, array $user, array $tenant): void
{
    if ($user['role'] !== 'tenant_admin') {
        http_response_code(403); simple_error('Acceso restringido','Solo el dueño puede administrar roles y permisos.'); return;
    }
    require_module_v12($app, $tenant, 'team');
    if (is_post()) {
        $submittedToken = (string)($_POST['csrf']??'');
        if (!hash_equals((string)($_SESSION['csrf']??''), $submittedToken) || $submittedToken === '') json_response(['ok'=>false,'error'=>'Sesión vencida. Recarga la página.'],403);
        try {
            $action = (string)($_POST['action']??'save');
            $app->db->beginTransaction();
            if ($action === 'save') {
                $id = (int)($_POST['role_id']??0);
                $name = trim((string)($_POST['name']??''));
                if ($name === '' || mb_strlen($name)>100) throw new RuntimeException('Escribe un nombre de hasta 100 caracteres.');
                if ($id && !$app->one('SELECT id FROM fisitaap_r1_roles WHERE id=? AND tenant_id=? FOR UPDATE', [$id,$tenant['id']])) throw new RuntimeException('El rol no pertenece a este negocio.');
                if ($id) $app->exec('UPDATE fisitaap_r1_roles SET name=? WHERE id=? AND tenant_id=?', [$name,$id,$tenant['id']]);
                else {
                    $app->exec('INSERT INTO fisitaap_r1_roles(tenant_id,name) VALUES(?,?)', [$tenant['id'],$name]);
                    $id = (int)$app->db->lastInsertId();
                }
                $app->exec('DELETE FROM fisitaap_r1_permissions WHERE role_id=?', [$id]);
                $raw = (array)($_POST['permissions']??[]);
                foreach (restructure_sections_r1() as $section=>$label) {
                    if ($section === 'fisichat') continue;
                    $allowed = array_intersect(array_map('strval', (array)($raw[$section]??[])), restructure_available_actions_r1($section));
                    if (!$allowed) continue;
                    $allowed[] = 'view'; // A write permission always includes opening its screen.
                    foreach (array_unique($allowed) as $permission) $app->exec('INSERT INTO fisitaap_r1_permissions(role_id,section_key,action_key) VALUES(?,?,?)', [$id,$section,$permission]);
                }
                audit($app, (int)$tenant['id'], (int)$user['id'], 'save_custom_role', 'custom_role', $id);
                flash('success','Rol guardado. Los permisos nuevos se aplican en la siguiente solicitud del colaborador.');
            } elseif ($action === 'assign') {
                $memberId = (int)($_POST['user_id']??0);
                $roleId = (int)($_POST['role_id']??0);
                $member = $app->one("SELECT id,role FROM users WHERE id=? AND tenant_id=? AND role IN ('manager','editor','kitchen','cashier') FOR UPDATE", [$memberId,$tenant['id']]);
                if (!$member) throw new RuntimeException('Selecciona un colaborador de este negocio. El dueño conserva su acceso.');
                if ($roleId) {
                    if (!$app->one('SELECT id FROM fisitaap_r1_roles WHERE id=? AND tenant_id=? AND is_active=1', [$roleId,$tenant['id']])) throw new RuntimeException('Selecciona un rol activo de este negocio.');
                    // Keep the legacy profile so removing a custom assignment restores the original access.
                    $previous = $app->one('SELECT previous_role FROM fisitaap_r1_user_roles WHERE user_id=? AND tenant_id=?', [$memberId,$tenant['id']]);
                    $app->exec('INSERT INTO fisitaap_r1_user_roles(tenant_id,user_id,role_id,previous_role) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE role_id=VALUES(role_id)', [$tenant['id'],$memberId,$roleId,$previous['previous_role']??$member['role']]);
                    // Existing POS and credit functions support the manager profile; the custom matrix restricts it.
                    $app->exec("UPDATE users SET role='manager' WHERE id=? AND tenant_id=?", [$memberId,$tenant['id']]);
                } else {
                    $previous = $app->one('SELECT previous_role FROM fisitaap_r1_user_roles WHERE user_id=? AND tenant_id=?', [$memberId,$tenant['id']]);
                    if ($previous) $app->exec('UPDATE users SET role=? WHERE id=? AND tenant_id=?', [$previous['previous_role'],$memberId,$tenant['id']]);
                    $app->exec('DELETE FROM fisitaap_r1_user_roles WHERE user_id=? AND tenant_id=?', [$memberId,$tenant['id']]);
                }
                audit($app,(int)$tenant['id'],(int)$user['id'],'assign_custom_role','user',$memberId);
                flash('success','Acceso del colaborador actualizado.');
            } elseif ($action === 'toggle') {
                $id = (int)($_POST['role_id']??0);
                if (!$app->one('SELECT id FROM fisitaap_r1_roles WHERE id=? AND tenant_id=? FOR UPDATE', [$id,$tenant['id']])) throw new RuntimeException('Rol no encontrado en este negocio.');
                $app->exec('UPDATE fisitaap_r1_roles SET is_active=1-is_active WHERE id=? AND tenant_id=?', [$id,$tenant['id']]);
                audit($app,(int)$tenant['id'],(int)$user['id'],'toggle_custom_role','custom_role',$id);
                flash('success','Estado del rol actualizado. Un rol suspendido bloquea a sus colaboradores.');
            } else throw new RuntimeException('Operación desconocida.');
            $app->db->commit();
        } catch (Throwable $e) {
            if ($app->db->inTransaction()) $app->db->rollBack();
            flash('error', $e instanceof PDOException ? 'No se pudo guardar. Revisa si el nombre del rol ya existe.' : $e->getMessage());
        }
        redirect(url('admin/roles'));
    }
    $editId = (int)($_GET['edit']??0);
    $edit = $editId ? $app->one('SELECT * FROM fisitaap_r1_roles WHERE id=? AND tenant_id=?', [$editId,$tenant['id']]) : null;
    $permissions = [];
    if ($edit) foreach ($app->all('SELECT section_key,action_key FROM fisitaap_r1_permissions WHERE role_id=?', [$edit['id']]) as $p) $permissions[$p['section_key']][$p['action_key']] = true;
    $roles = $app->all('SELECT r.*,COUNT(a.user_id) members FROM fisitaap_r1_roles r LEFT JOIN fisitaap_r1_user_roles a ON a.role_id=r.id AND a.tenant_id=r.tenant_id WHERE r.tenant_id=? GROUP BY r.id ORDER BY r.name', [$tenant['id']]);
    $members = $app->all("SELECT u.id,u.name,u.role,a.role_id,r.name custom_name FROM users u LEFT JOIN fisitaap_r1_user_roles a ON a.user_id=u.id AND a.tenant_id=u.tenant_id LEFT JOIN fisitaap_r1_roles r ON r.id=a.role_id AND r.tenant_id=u.tenant_id WHERE u.tenant_id=? AND u.role IN ('manager','editor','kitchen','cashier') ORDER BY u.name", [$tenant['id']]);
    admin_shell_start('Roles y permisos',$user,$tenant,'roles');
    echo '<section class="card admin-card"><h2>'.($edit?'Editar rol':'Crear rol').'</h2><p>Marca lo que podrá hacer este rol. El dueño siempre conserva su acceso completo. Los módulos disponibles también dependen del plan y del modo de tienda.</p><form method="post">'.csrf_field().'<input type="hidden" name="action" value="save"><input type="hidden" name="role_id" value="'.(int)($edit['id']??0).'">'.field('Nombre del rol','name',$edit['name']??'',true);
    restructure_permissions_form_r1($permissions);
    echo '<p class="muted">Un rol personalizado utiliza las pantallas autorizadas; FISIChat permanece reservado al dueño en esta entrega.</p><button class="btn">Guardar rol</button> <a class="btn btn-light" href="'.url('admin/roles').'">Nuevo rol</a></form></section><section class="card admin-card" style="margin-top:20px"><h2>Roles del negocio</h2><div class="table-wrap"><table class="table"><tr><th>Rol</th><th>Colaboradores</th><th>Estado</th><th>Acciones</th></tr>';
    foreach ($roles as $r) echo '<tr><td>'.e($r['name']).'</td><td>'.(int)$r['members'].'</td><td>'.($r['is_active']?'Activo':'Suspendido').'</td><td><a class="btn btn-light" href="?edit='.(int)$r['id'].'">Editar</a><form method="post" class="inline-form">'.csrf_field().'<input type="hidden" name="action" value="toggle"><input type="hidden" name="role_id" value="'.(int)$r['id'].'"><button class="btn btn-light">'.($r['is_active']?'Suspender':'Activar').'</button></form></td></tr>';
    echo '</table></div></section><section class="card admin-card" style="margin-top:20px"><h2>Asignar roles a colaboradores</h2><p>Crea primero al colaborador desde <a href="'.url('admin/usuarios').'">Usuarios</a>. Para cambiar sus permisos, usa esta pantalla.</p>';
    if (!$members) echo '<p>No hay colaboradores todavía.</p>';
    foreach ($members as $m) {
        echo '<form method="post" class="branch-admin-row">'.csrf_field().'<input type="hidden" name="action" value="assign"><input type="hidden" name="user_id" value="'.(int)$m['id'].'"><label for="member-role-'.(int)$m['id'].'">'.e($m['name']).'</label><select class="input" id="member-role-'.(int)$m['id'].'" name="role_id"><option value="0">Usar perfil original</option>';
        foreach ($roles as $r) if ($r['is_active'] || (int)$m['role_id']===(int)$r['id']) echo '<option value="'.(int)$r['id'].'" '.((int)$m['role_id']===(int)$r['id']?'selected':'').' '.(!$r['is_active']?'disabled':'').'>'.e($r['name']).(!$r['is_active']?' (suspendido)':'').'</option>';
        echo '</select><button class="btn">Guardar asignación</button></form>';
    }
    echo '</section>'; admin_shell_end();
}

function restructure_dispatch_r1(App $app, string $path): void
{
    restructure_guard_r1($app, $path);
    session_read_route176($path);
    if ($path !== 'admin/roles' || !restructure_active_r1($app)) return;
    $user = require_login(['tenant_admin']);
    $tenant = $app->one('SELECT * FROM tenants WHERE id=?', [$user['tenant_id']]);
    if (!$tenant) { http_response_code(403); exit('Negocio no disponible.'); }
    restructure_roles_r1($app,$user,$tenant);
    exit;
}
