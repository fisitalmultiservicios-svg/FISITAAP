<?php
declare(strict_types=1);
require_once __DIR__.'/image_optimizer.php';

final class Database
{
    public static function connect(array $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
        $db = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // NOW()/CURDATE() must use the same clock as PHP and synchronized local sales.
        // A numeric offset works even when the hosting has no MySQL timezone tables.
        $db->exec('SET SESSION time_zone = '.$db->quote((new DateTimeImmutable())->format('P')));
        return $db;
    }
}

final class App
{
    private array $settingCache=[];
    private bool $settingsLoaded=false;
    private array $requestCache=[];
    public function __construct(public PDO $db, public array $config) {}

    public function one(string $sql, array $params = []): ?array
    {
        $timer=isset($GLOBALS['fisitaap_performance179'])?hrtime(true):null;
        $s = $this->db->prepare($sql); $s->execute($params);
        $row=$s->fetch() ?: null;
        if($timer!==null)$this->performance179($sql,$timer);
        return $row;
    }

    public function all(string $sql, array $params = []): array
    {
        $timer=isset($GLOBALS['fisitaap_performance179'])?hrtime(true):null;
        $s = $this->db->prepare($sql); $s->execute($params);
        $rows=$s->fetchAll();
        if($timer!==null)$this->performance179($sql,$timer);
        return $rows;
    }

    private function performance179(string $sql,int $start):void
    {
        $ms=(hrtime(true)-$start)/1e6;
        $p=&$GLOBALS['fisitaap_performance179'];$p['db']+=$ms;$p['count']++;
        preg_match('/\bFROM\s+`?([a-z0-9_]+)/i',$sql,$table);
        $caller=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3)[2]['function']??'page';
        $label=substr(preg_replace('/[^a-z0-9_ ]/i','',$caller.' '.($table[1]??'query')),0,70);
        $p['slow'][]=['ms'=>$ms,'label'=>$label];
        usort($p['slow'],static fn($a,$b)=>$b['ms']<=>$a['ms']);$p['slow']=array_slice($p['slow'],0,3);
    }

    public function exec(string $sql, array $params = []): bool
    {
        if(!empty($GLOBALS['demo_context'])&&preg_match('~^INSERT\s+INTO\s+`?users`?\s*\([^)]*\)\s*VALUES\s*\(NULL\s*,~i',$sql)){
            $sql=preg_replace_callback('~(VALUES\s*\()NULL\s*,~i',static fn($m)=>$m[1].(int)$GLOBALS['demo_context']['tenant_id'].',',$sql,1);
        }
        if(!empty($GLOBALS['demo_context'])&&preg_match('~^INSERT\s+INTO\s+`?users`?\s*\(([^)]*)\)\s*VALUES\s*\(~i',$sql,$newUser179)&&!preg_match('/\btenant_id\b/i',$newUser179[1])){
            $sql=preg_replace_callback('~^(INSERT\s+INTO\s+`?users`?\s*)\(([^)]*)\)(\s*VALUES\s*)\(~i',static fn($m)=>$m[1].'(tenant_id,'.$m[2].')'.$m[3].'('.(int)$GLOBALS['demo_context']['tenant_id'].',',$sql,1);
        }
        $s = $this->db->prepare($sql); $ok = $s->execute($params);
        if ($ok) {
            $this->requestCache=[];
            if (preg_match('~\b(?:INTO|UPDATE|FROM)\s+`?settings`?(?=\s|\(|$)~i', $sql)) {
                $this->settingCache=[]; $this->settingsLoaded=false;
            }
        }
        return $ok;
    }

    // Only this request and this connection: never share prices or permissions across users.
    public function cached(string $key, callable $load): mixed
    {
        if (!array_key_exists($key, $this->requestCache)) $this->requestCache[$key]=$load();
        return $this->requestCache[$key];
    }

    public function setting(string $key, string $fallback = ''): string
    {
        if(!$this->settingsLoaded){
            foreach($this->all('SELECT `key`,`value` FROM settings') as $row)$this->settingCache[(string)$row['key']]=(string)$row['value'];
            $this->settingsLoaded=true;
        }
        return array_key_exists($key,$this->settingCache)?$this->settingCache[$key]:$fallback;
    }
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function ui_label_qa(string $value):string{return ['new'=>'Nuevo','open'=>'Abierto','closed'=>'Cerrado','suspended'=>'En espera','payment'=>'En cobro','pending'=>'Pendiente','paid'=>'Pagado','partial'=>'Pago parcial','completed'=>'Completado','credit'=>'A crédito','confirmed'=>'Confirmado','preparing'=>'En preparación','ready'=>'Listo','available'=>'Disponible','assigned'=>'Asignado','arrived'=>'En el negocio','picked_up'=>'Recogido','in_transit'=>'En camino','delivered'=>'Entregado','issue'=>'Incidencia','cancelled'=>'Cancelado','expired'=>'Vencido','pending_store'=>'Esperando aprobación del negocio','pending_driver'=>'Esperando aceptación del motorizado','paused'=>'Pausado','rejected'=>'Rechazado','blocked'=>'Bloqueado','active'=>'Activo','hidden'=>'Oculto','sold_out'=>'Agotado','tenant_admin'=>'Dueño de tienda','manager'=>'Gestor','editor'=>'Editor','kitchen'=>'Cocina','cashier'=>'Cajero','published'=>'Publicado','draft'=>'Borrador','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb','Sun'=>'Dom'][$value]??$value;}
function money(float $value): string { return '₡' . number_format($value, 2, ',', ' '); }
function public_media_url(?string $value,?string $fallback=null):string {
    $fallback??=url('assets/product-placeholder.svg');$value=trim((string)$value);
    if($value==='')return $fallback;if(str_starts_with($value,'data:'))return $value;
    $parts=parse_url($value);if($parts===false)return $fallback;
    $relative=media_relative_path($value);
    if($relative===null)return isset($parts['host'])&&in_array(strtolower($parts['scheme']??'https'),['http','https'],true)?$value:$fallback;
    $root=realpath(ROOT_PATH);$file=realpath(ROOT_PATH.'/'.$relative);
    if($file===false||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file))return $fallback;
    $resolved=isset($parts['host'])?$value:url(implode('/',array_map('rawurlencode',explode('/',$relative))));
    if(!isset($parts['host'])){$resolved.=isset($parts['query'])?'?'.$parts['query']:'';$resolved.=isset($parts['fragment'])?'#'.$parts['fragment']:'';}
    return image_variant_url($resolved);
}
function order_item_details_html(array $item): string { $options=json_decode((string)($item['options_json']??''),true);$html='';if(is_array($options)&&$options){$html.='<small class="item-options">';foreach($options as $option)$html.='<span>'.e(($option['group']??$option['group_name']??'Opción').': '.($option['name']??'')).'</span>';$html.='</small>';}if(!empty($item['notes']))$html.='<small class="item-note">Nota: '.e($item['notes']).'</small>';return $html; }
function url(string $path = ''): string {
    global $config;$path=ltrim($path,'/');
    if(defined('FISITAAP_CODE_PREFIX')&&str_starts_with($path,'assets/'))
        return rtrim($GLOBALS['demo_base_url']??$config['app_url'],'/').'/'.FISITAAP_CODE_PREFIX.'/'.$path;
    return rtrim($config['app_url'],'/').'/'.$path;
}
function redirect(string $to): never { header('Location: ' . $to); exit; }
function json_response(array $data, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function request_path(): string { $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); return trim((string)$path, '/'); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function json_input_qa(int $limit = 2097152): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > $limit) json_response(['ok'=>false,'error'=>'La solicitud es demasiado grande.'], 413);
    try {
        if (!str_starts_with(ltrim($raw), '{')) throw new JsonException('Expected object');
        $data = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new JsonException('Expected object');
        return $data;
    } catch (JsonException) {
        json_response(['ok'=>false,'error'=>'Los datos enviados no son válidos. Recarga la pantalla.'], 400);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token)) $token = '';
    if (in_array(request_path(),['owner-login','login','register'],true)
        && ((string)$token === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token))) {
        flash('error','El formulario venció. Vuelve a ingresar tus datos.');
        redirect(url(request_path()));
    }
    if ((string)$token === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$token)) {
        if(function_exists('qa180_active')&&qa180_active($GLOBALS['app'])&&!str_starts_with(request_path(),'api/')&&!str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){
            flash('error','El formulario venció. Recarga la pantalla antes de continuar.');
            redirect(url(request_path()==='logout'||!current_user()?'owner-login':current_url_v1()));
        }
        json_response(['ok'=>false,'error'=>'Sesión vencida. Recarga la página.','redirect'=>url('owner-login')], 403);
    }
}
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function business_login_problem180(App $app,array $user):?string {
    if(!in_array($user['role']??'',['tenant_admin','manager','editor','kitchen','cashier'],true))return null;
    $id=(int)($user['tenant_id']??0);
    $tenant=$id?$app->cached('login-business180:'.$id,static fn()=>$app->one('SELECT id,is_active FROM tenants WHERE id=?',[$id])):null;
    if(!$tenant)return 'Esta cuenta no tiene un negocio válido asignado. Solicita a la cuenta maestra que revise su asociación. Puedes ingresar con otra cuenta.';
    return $tenant['is_active']?null:'Tienda fuera de servicio. El negocio de esta cuenta está suspendido. Solicita a la cuenta maestra que revise su estado. Puedes ingresar con otra cuenta.';
}
function reject_business_login180(string $message,string $route='owner-login'):never {
    $rotate=session_status()===PHP_SESSION_ACTIVE;
    session_change176(static function()use($message,$rotate):void {
        // Read-session reopenings suppress cookies: retaining their ID lets the
        // browser read the cleared session and message. Login rotates it again.
        $_SESSION=[];if($rotate&&session_status()===PHP_SESSION_ACTIVE)session_regenerate_id(true);
        $_SESSION['csrf']=bin2hex(random_bytes(32));$_SESSION['flash']=[['error',$message]];
    });
    if(str_starts_with(request_path(),'api/'))json_response(['ok'=>false,'error'=>$message,'redirect'=>url($route)],403);
    redirect(url($route));
}
function require_login(array $roles = []): array
{
    $u = current_user();
    if (!$u) {
        if(function_exists('qa180_active')&&qa180_active($GLOBALS['app'])){
            if(str_starts_with(request_path(),'api/'))json_response(['ok'=>false,'error'=>'Inicia sesión para continuar.','redirect'=>url('owner-login')],401);
            if(request_path()==='admin'||request_path()==='master'||str_starts_with(request_path(),'admin/')||str_starts_with(request_path(),'master/'))redirect(url('owner-login'));
        }
        redirect(url('login'));
    }
    if ($roles && !in_array($u['role'], $roles, true)) { http_response_code(403); exit('Acceso denegado'); }
    if($GLOBALS['app'] instanceof App&&($problem=business_login_problem180($GLOBALS['app'],$u)))reject_business_login180($problem);
    return $u;
}
function flash(string $type, string $message): void
{
    session_change176(function () use ($type, $message) { $_SESSION['flash'][] = [$type, $message]; });
}
function flashes(): array { return session_take176('flash') ?? []; }

function rate_limit(string $key, int $max = 8, int $seconds = 300): bool
{
    $now=time(); $bucket=$_SESSION['rate'][$key]??[];
    $bucket=array_values(array_filter($bucket, fn($t)=>$t>$now-$seconds));
    if(count($bucket)>=$max){$_SESSION['rate'][$key]=$bucket; return false;}
    $bucket[]=$now; $_SESSION['rate'][$key]=$bucket; return true;
}

function rate_limit_persistent(App $app, string $action, string $identity, int $max = 8, int $seconds = 300, int $blockSeconds = 900): bool
{
    try {
        $key = hash('sha256', $action.'|'.$identity.'|'.$app->config['app_key']);
        $row = $app->one('SELECT * FROM rate_limits WHERE bucket_key=?', [$key]);
        $now = time();
        if ($row && $row['blocked_until'] && strtotime($row['blocked_until']) > $now) return false;
        if (!$row || strtotime($row['window_started_at']) <= $now - $seconds) {
            $app->exec('INSERT INTO rate_limits(bucket_key,attempts,window_started_at,blocked_until) VALUES(?,1,NOW(),NULL) ON DUPLICATE KEY UPDATE attempts=1,window_started_at=NOW(),blocked_until=NULL', [$key]);
            return true;
        }
        $attempts = (int)$row['attempts'] + 1;
        $blocked = $attempts > $max ? date('Y-m-d H:i:s', $now + $blockSeconds) : null;
        $app->exec('UPDATE rate_limits SET attempts=?,blocked_until=? WHERE bucket_key=?', [$attempts,$blocked,$key]);
        return $blocked === null;
    } catch (Throwable) {
        return rate_limit($action.'|'.hash('sha256',$identity), $max, $seconds);
    }
}

function client_identity(string $extra = ''): string
{
    return ($_SERVER['REMOTE_ADDR'] ?? 'unknown').'|'.strtolower(trim($extra));
}

function catalog_segment(array $tenant): string
{
    return ($tenant['catalog_label'] ?? 'Catálogo') === 'Menú' ? 'menu' : 'catalogo';
}

function role_can(string $role, string $section): bool
{
    global $app;
    $sessionUser = current_user();
    if (function_exists('restructure_can_r1') && isset($app) && $sessionUser && $sessionUser['role'] === $role) {
        $custom = restructure_can_r1($app, $sessionUser, $section);
        if ($custom !== null) return $custom;
    }
    if (function_exists('r2_alias')) $section = r2_alias($section);
    $permissions = [
        'tenant_admin' => ['*'],
        'manager' => ['dashboard','fisichat','pedidos','cocina','repartidor','express','clientes','fidelizacion','contactos','reportes','auditoria','ventas','cobro','express-pendientes','turnos','inventario','compras','cxc','cxp','impresion','recibo'],
        'editor' => ['dashboard','fisichat','productos','opciones','categorias','contenido','blog','inventario','compras'],
        'kitchen' => ['dashboard','pedidos','cocina'],
        'cashier' => ['dashboard','fisichat','ventas','cobro','express-pendientes','clientes','turnos','impresion','recibo'],
        'driver' => ['repartidor','express'],
    ];
    return isset($permissions[$role]) && (in_array('*',$permissions[$role],true) || in_array($section,$permissions[$role],true));
}

function require_section_permission(array $user, string $section): void
{
    if (!role_can((string)$user['role'],$section)) {
        http_response_code(403);
        simple_error('Acceso restringido','Tu rol no tiene permiso para utilizar este módulo.');
        exit;
    }
}

function tenant_by_slug(App $app, string $slug): ?array
{
    if(!empty($GLOBALS['demo_context'])&&$slug!==$GLOBALS['demo_context']['slug'])return null;
    if(empty($GLOBALS['demo_context'])&&(preg_match('/^demo-(?:restaurante|tienda)-[a-f0-9]{32}$/D',$slug)||(is_post()&&in_array($slug,['restaurante-demo','tienda-demo'],true))))return null;
    return $app->one('SELECT * FROM tenants WHERE slug=? LIMIT 1', [$slug]);
}

function layout_start(string $title, ?array $tenant = null, bool $admin = false, bool $businessLanding = false): void
{
    if(function_exists('qa180_prepare_native'))qa180_prepare_native($GLOBALS['app']);
    global $app;$primary = $admin ? '#203f57' : ($tenant['primary_color'] ?? ($app instanceof App?$app->setting('platform_primary_color','#1f7a45'):'#1f7a45'));
    $accent = $admin ? '#dce7ee' : ($tenant['accent_color'] ?? ($app instanceof App?$app->setting('platform_accent_color','#dfe9c8'):'#dfe9c8'));
    $name = $tenant['name'] ?? ($app instanceof App?$app->setting('site_name','FISITAPP'):'FISITAPP');
    $cacheVersion=($app instanceof App?$app->setting('cache_version','1'):'1').'-180';
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    $bridgeConnect=in_array(request_path(),['admin/recibo','admin/impresion'],true)?' http://127.0.0.1:18765':'';
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; media-src 'self' https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-src 'self' https://www.google.com https://maps.google.com; connect-src 'self'{$bridgeConnect}");
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="'.e($primary).'"><title>'.e($title).' · '.e($name).'</title>'.(function_exists('seo_head_v12')&&$app instanceof App?seo_head_v12($app,$title,$tenant,$admin):'').'<link rel="manifest" href="'.url(($tenant? $tenant['slug'].'/':'').'manifest.webmanifest').'"><link rel="stylesheet" href="'.url('assets/app.bundle.css?v='.rawurlencode($cacheVersion)).'"><link rel="stylesheet" href="'.url('assets/v12.css?v='.rawurlencode($cacheVersion)).'">';
    if(!empty($GLOBALS['demo_context']))demo_header();
    require_once __DIR__.'/browser_migration179.php';
    browser_recovery179($GLOBALS['demo_base_url']??rtrim(url(),'/'));
    if(!preg_match('/^#[0-9a-f]{6}$/iD',$primary))$primary='#1f7a45';
    $rgb=sscanf($primary,'#%02x%02x%02x');$linear=array_map(static fn($v)=>($v/255<=.04045)?$v/255/12.92:pow(($v/255+.055)/1.055,2.4),$rgb);$luminance=.2126*$linear[0]+.7152*$linear[1]+.0722*$linear[2];$onPrimary=$luminance>.179?'#000000':'#ffffff';$brandInk=$luminance>.179?'#24313b':$primary;
    echo '<style>:root{--primary:'.e($primary).';--accent:'.e($accent).';--on-primary:'.$onPrimary.';--brand-ink:'.$brandInk.'}.store-name-brand>span{display:inline!important;font-size:16px;max-width:180px;overflow:hidden;text-overflow:ellipsis}</style>';
    if($admin)echo '<style id="admin-critical-v1451">.dash-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.dash-grid-main{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.8fr);gap:16px}.dash-grid-secondary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.dash-welcome{display:flex;justify-content:space-between;gap:20px}.dash-kpi,.dash-chart-card,.dash-side-card,.dash-mini-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px}.dash-kpi{padding:16px;display:flex;gap:12px}.dash-bars{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;height:240px;align-items:end}.dash-bar-track{height:170px;background:#eef1ed;border-radius:10px;position:relative;overflow:hidden}.dash-bar-track i{position:absolute;bottom:0;left:0;right:0;background:var(--primary);border-radius:10px}.dash-progress{height:6px;background:#eef1ed;border-radius:99px;overflow:hidden}.dash-progress i{display:block;height:100%;background:var(--primary)}@media(max-width:980px){.dash-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.dash-grid-main{grid-template-columns:1fr}}@media(max-width:700px){.dash-kpis,.dash-grid-secondary{grid-template-columns:1fr}.dash-welcome{flex-direction:column}}</style>';
    $path=request_path();
    // The business landing needs the shared R2 styles; its POS/designer script
    // remains limited to administration and the assistant.
    $r2Screen=$admin||$path==='asistente';
    $r3Screen=true; // Required by official landing and public tenant pages too.
    if(function_exists('r2_active') && r2_active($app)){
        if($r2Screen||$businessLanding)echo '<link rel="stylesheet" href="'.url('assets/restructure-r2.css?v='.rawurlencode($cacheVersion)).'">';
        if($r2Screen)echo '<script src="'.url('assets/restructure-r2.js?v='.rawurlencode($cacheVersion)).'" defer></script>';
    }
    if(function_exists('r3_active') && r3_active($app) && $r3Screen) echo '<link rel="stylesheet" href="'.url('assets/pdf-r3.css?v='.rawurlencode($cacheVersion)).'"><script src="'.url('assets/pdf-r3.js?v='.rawurlencode($cacheVersion)).'" defer></script>';
    if($admin)echo '<link rel="stylesheet" href="'.url('assets/admin-refined.css?v='.rawurlencode($cacheVersion)).'">';
    $sharedWelcome=$admin&&in_array($path,['admin','admin/bienvenida','master'],true)&&function_exists('qa180_active')&&qa180_active($app,0);
    if(function_exists('qa180_active')&&(qa180_active($app)||$sharedWelcome))echo '<link rel="stylesheet" href="'.url('assets/qa180.css?v=180').'">';
    echo '<link rel="stylesheet" href="'.url('assets/experience-r11.css?v=r11').'">';
    echo '</head><body class="'.($admin?'admin-body':'public-body').'">';
    if(!empty($GLOBALS['demo_context']))demo_banner();
}

function layout_end(): void
{
    if(function_exists('op180_ui')&&qa180_active($GLOBALS['app']))op180_ui($GLOBALS['app']);
    if(function_exists('qa180_active')&&qa180_active($GLOBALS['app']))echo '<script>window.FISITAAP180='.r3_json(qa180_ui_context($GLOBALS['app'])).'</script><script src="'.url('assets/qa180.js?v=180').'" defer></script>';
    if(in_array(explode('/',request_path())[0],['admin','master'],true))echo '<script src="'.url('assets/image-upload.js?v=1810').'" defer></script>';
    global $app;$cacheVersion=($app instanceof App?$app->setting('cache_version','1'):'1').'-180';echo '<script>window.FISITAPP={csrf:"'.e(csrf_token()).'",base:"'.e(url()).'",cacheVersion:"'.e($cacheVersion).'"}</script><script src="'.url('assets/app.js?v='.rawurlencode($cacheVersion)).'" defer></script>'.'<script src="'.url('assets/experience-r11.js?v=r11').'" defer></script>'.((request_path()==='admin'||str_starts_with(request_path(),'admin/'))?'<script src="'.url('assets/direct-print.js?v='.rawurlencode($cacheVersion)).'" defer></script>':'').'</body></html>';
}
