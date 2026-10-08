<?php
declare(strict_types=1);
require_once __DIR__.'/floor_r3.php';
require_once __DIR__.'/sales_r3.php';
require_once __DIR__.'/official_r3.php';
require_once __DIR__.'/gifts_r3.php';
require_once __DIR__.'/printing_r3.php';
require_once __DIR__.'/checkout_r3.php';
require_once __DIR__.'/reports_r3.php';

function r3_active(App $app): bool { return $app->setting('restructure_r3_active','0') === '1'; }
function r3_json(array $value): string { return json_encode($value,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); }
function r3_icon(string $name): string
{
    $paths = [
        'search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/>',
        'cart'=>'<path d="M2 3h3l3 12h10l3-9H6M9 20h.01M18 20h.01"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
        'person'=>'<circle cx="12" cy="7" r="3"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/>',
        'table'=>'<path d="M3 8h18v6H3zM6 14v7M18 14v7M5 2v6M19 2v6"/>',
        'print'=>'<path d="M6 8V2h12v6M6 17H3V8h18v9h-3M6 14h12v8H6zM17 11h.01"/>',
        'cash'=>'<rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M5 8h.01M19 16h.01"/>',
        'card'=>'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20M6 16h4"/>',
        'transfer'=>'<path d="M3 7h18l-4-4M21 17H3l4 4"/>',
        'note'=>'<path d="M5 2h10l4 4v16H5zM14 2v5h5M8 11h8M8 15h8M8 19h5"/>',
        'edit'=>'<path d="m4 16 12-12 4 4L8 20H4zM13 7l4 4"/>',
        'trash'=>'<path d="M3 6h18M9 6V3h6v3M6 6l1 15h10l1-15M10 10v7M14 10v7"/>',
        'settings'=>'<path d="M4 7h16M4 17h16M8 3v8M16 13v8"/>',
        'plus'=>'<path d="M12 4v16M4 12h16"/>',
        'back'=>'<path d="M20 12H4l6-6M4 12l6 6"/>',
        'split'=>'<path d="M9 3v18M15 3v18M3 12h4M17 12h4"/>',
        'shop'=>'<path d="M3 9l3-6h12l3 6M4 9v12h16V9M9 21v-8h6v8M3 9h18"/>',
        'chef'=>'<path d="M6 11a4 4 0 0 1-1-8 5 5 0 0 1 10 0 4 4 0 0 1 3 8v10H6zM6 17h12"/>',
        'pin'=>'<path d="M19 9c0 6-7 12-7 12S5 15 5 9a7 7 0 0 1 14 0z"/><circle cx="12" cy="9" r="2"/>',
        'gift'=>'<path d="M3 9h18v5H3zM5 14v8h14v-8M12 9v13M12 9C2 8 6-2 12 9c6-11 10-1 0 0"/>',
        'check'=>'<path d="m4 12 5 5L20 6"/>',
        'bolt'=>'<path d="m14 2-10 12h7l-1 8L21 9h-8z"/>',
        'box'=>'<path d="m3 7 9-5 9 5v10l-9 5-9-5zM3 7l9 5 9-5M12 12v10M8 4l9 5"/>',
    ];
    return '<svg class="r3-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['box']).'</svg>';
}
function r3_shell_start(App $app,array $u,array $t,array $branch,string $title,bool $light=false): void
{
    $shift=$app->one('SELECT id FROM pos_shifts s WHERE tenant_id=? AND branch_id=? AND user_id=? AND status="open" AND NOT EXISTS(SELECT 1 FROM fisitaap_r2_shift_links l WHERE l.shift_id=s.id) ORDER BY id DESC LIMIT 1',[$t['id'],$branch['id'],$u['id']]);
    layout_start($title,$t,true);
    echo '<div class="r3-terminal'.($light?' r3-terminal-light':'').'"><header class="r3-topbar"><a class="r3-brand" href="'.url('admin').'">'.(!empty($t['logo'])?'<img src="'.e(public_media_url($t['logo'])).'" alt="'.e($t['name']).'">':'<span>'.e($t['name']).'</span>').'</a><h1>'.e($title).'</h1><div class="r3-header-right"><span>'.r3_icon('pin').e($branch['name']).'</span><span>'.r3_icon('shop').e($u['name']).'</span><span class="r3-status '.($shift?'free':'closed').'">● '.($shift?'Turno abierto':'Turno cerrado').'</span><a class="r3-button" href="'.url('admin/turnos').'">'.($shift?'Cerrar turno':'Abrir turno').'</a><a href="'.url('admin').'">'.r3_icon('person').'Rol: '.e(restructure_role_name_r1($app,$u)).'</a></div></header>';
    echo '<main class="r3-main">';render_flashes();
}
function r3_shell_end(): void { echo '</main></div>';layout_end(); }
function r3_pos_nav(array $t): void
{
    echo '<nav class="r3-pos-nav"><form method="post" action="'.url('admin/ventas').'">'.csrf_field().pos_hidden_148('action',($t['catalog_label']??'')==='Menú'?'quick':'catalog').pos_hidden_148('open_key',bin2hex(random_bytes(16))).'<button class="r3-button primary">'.r3_icon('bolt').'Venta rápida</button></form>';
    if(($t['catalog_label']??'')==='Menú') echo '<a class="r3-button" href="'.url('admin/ventas?screen=express').'">'.r3_icon('cart').'Express</a><a class="r3-button" href="'.url('admin/mesas').'">'.r3_icon('table').'Mesas</a>';
    echo '<a class="r3-button" href="'.url('admin/cobro').'">'.r3_icon('cash').'Cobrar cuentas</a><a class="r3-button" href="'.url('admin/ventas?waiting=1').'">'.r3_icon('note').'Ventas en espera</a><a class="r3-button" href="'.url('admin/ventas?history=1').'">'.r3_icon('clock').'Historial / Reimprimir</a></nav>';
}
function r3_business_user(App $app,string $section): array
{
    $u=require_login(['tenant_admin','manager','editor','kitchen','cashier']);
    $t=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$u['tenant_id']]);
    if(!$t)throw new DomainException('Negocio no disponible.');
    require_section_permission($u,$section);require_module_v12($app,$t,section_module_v12($section));
    return [$u,$t];
}
function r3_dispatch(App $app,string $path): void
{
    if(!r3_active($app))return;
    if($path===''){r3_official($app);exit;}
    if($path==='master/reportes'){r3_master_reports($app,require_login(['master']));exit;}
    if($path==='master/contenido'){r3_official_editor($app,require_login(['master']));exit;}
    if($path==='api/pos-quote-r3'){r3_quote_api($app);exit;}
    if($path==='admin/recibo'&&isset($_GET['precuenta'])){[$u,$t]=r3_business_user($app,'ventas');r3_prebill($app,$u,$t,(int)$_GET['precuenta']);exit;}
    if($path==='admin/reportes'){[$u,$t]=r3_business_user($app,'reportes');r3_reports($app,$u,$t);exit;}
    if(!in_array($path,['admin/ventas','admin/mesas','admin/cobro'],true))return;
    $section=substr($path,6);[$u,$t]=r3_business_user($app,$section);
    try {
        if($section==='mesas')r3_floor($app,$u,$t);
        elseif($section==='cobro')r3_collect($app,$u,$t);
        else r3_sales($app,$u,$t);
    }catch(Throwable $ex){http_response_code(422);simple_error('Revisa los datos',$ex instanceof PDOException?'No se pudo completar la operación. Recarga y revisa su estado.':$ex->getMessage());}
    exit;
}
