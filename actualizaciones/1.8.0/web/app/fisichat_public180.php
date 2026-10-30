<?php
declare(strict_types=1);
require_once __DIR__.'/fisichat_scope_r13.php';

function fc_chat_actor(App $app):?array {
    $id=(int)(current_user()['id']??0);if(!$id)return null;
    $key=spl_object_id($app).':'.$id;
    if(!array_key_exists($key,$GLOBALS['fc_actor']??[]))$GLOBALS['fc_actor'][$key]=$app->one('SELECT id,tenant_id,name,role,is_active,force_password_change FROM users WHERE id=? AND is_active=1',[$id]);
    return $GLOBALS['fc_actor'][$key];
}
function fc_knowledge():array {
    return [
        'overview'=>'FISITAAP reúne página del negocio, catálogo o menú, pedidos, POS, clientes, inventario, compras, cuentas, mesas, sucursales, reportes y permisos. Los módulos dependen de la configuración y plan.',
        'online'=>'El cliente elige productos y opciones, arma su pedido y selecciona la entrega y pago disponibles. El negocio recibe el resumen por WhatsApp y administra sus pedidos.',
        'offline'=>'Windows y Android muestran la web completa con conexión. Solo un principal por negocio, Windows o Android, puede buscar productos y vender sin internet. Las demás cajas necesitan conexión. Al regresar internet se sincronizan las ventas locales.',
        'printing'=>"Administración → Impresión configura sucursal, equipo y puntos de recibos/cocina/bar/despacho. Windows usa impresoras instaladas o de red. Android usa térmicas de red ESC/POS de 58/80 mm. Pulsa Probar en cada punto. La gaveta solo abre con efectivo en un recibo original autorizado. El flujo actual imprime desde Windows/Android; FISITAAP Print es una integración anterior.",
        'inventory'=>'Inventario por sucursal, mínimos y movimientos. Las compras en borrador no alteran existencias; recibirlas registra inventario/costo y CxP. XML se usa para importar compras de proveedores, no implica emisión electrónica a Hacienda.',
        'customers'=>'Clientes, crédito autorizado con límite/plazo, CxC/abonos, fidelización y recompensas. Ventas y pagos reales se registran desde cajas autorizadas.',
        'restaurant'=>'Menú con extras/variantes/combos, venta rápida, salones/mesas, cuentas, comandas por punto, cocina y pedidos para llevar o entregar.',
        'reports'=>'Reportes filtrados por fechas/sucursal y permisos, con exportación CSV. El dueño consulta datos reales dentro del panel, según los módulos habilitados.',
        'demos'=>'Demos independientes con datos/dinero virtuales. Se puede practicar; al cerrar sesión o abrir otra prueba se inicia desde el ejemplo original. Los envíos e impresiones reales se simulan.',
        'updates'=>'El maestro instala/publica archivos. Cada dueño activa en Administración → Actualizar sistema cuando sincronizó sus propias cajas, cerró sus turnos/cuentas y actualizó sus aplicaciones. Los otros negocios conservan su estado.',
        'access'=>'Acceso negocios permite entrar con correo y contraseña habituales. El chat público no recupera contraseñas ni revela cuentas. Si no puedes ingresar, confirma datos y mensaje de error y solicita ayuda humana; no compartas claves ni códigos.',
        'pricing'=>'No hay una tarifa comercial pública confirmada en este contexto. El asesor confirma módulos, precio, condiciones e instalación. Nunca inventar precios, descuentos, licencias ilimitadas ni promesas de tiempo.',
    ];
}
function fc_contact(App $app,?array $tenant=null):string {
    $raw=$tenant?($tenant['whatsapp']?:$tenant['phone']):$app->setting('master_whatsapp','86761771');
    $phone=preg_replace('/\D/','',(string)$raw);if(strlen($phone)===8)$phone='506'.$phone;
    return preg_match('/^\d{8,15}$/D',$phone)?'https://wa.me/'.$phone:'';
}
function fc_public_store_context(App $app,?array $tenant,?array $user,string $slug=''):array {
    $data=['perfil'=>'Cliente de una tienda','ayuda'=>'Consulta la información publicada de esta tienda. Para pedir, abre su catálogo, selecciona sucursal/productos/opciones y confirma entrega/pago.'];
    if(!$tenant)return $data;
    if(!$tenant['is_active'])throw new DomainException('Este negocio no está disponible.');
    $branches=$app->all('SELECT id,name,slug,address,phone,whatsapp FROM branches WHERE tenant_id=? AND is_active=1 ORDER BY is_default DESC,name',[$tenant['id']]);
    $branch=null;foreach($branches as $b)if($b['slug']===$slug||($slug===''&&count($branches)===1))$branch=$b;
    if($slug!==''&&!$branch)throw new DomainException('Selecciona una sucursal disponible de este negocio.');
    $data['negocio']=['nombre'=>$tenant['name'],'direccion'=>$tenant['address'],'telefono'=>$tenant['phone'],'horarios'=>$tenant['business_hours'],'catalogo'=>url($tenant['slug'].'/'.catalog_segment($tenant))];
    $data['sucursales']=array_map(static fn($b)=>array_intersect_key($b,array_flip(['name','slug','address','phone'])),$branches);
    if(count($branches)>1&&!$branch){$data['elige_sucursal']=true;return $data;}
    $bid=(int)($branch['id']??0);$data['sucursal']=$branch['name']??$tenant['name'];
    if(($tenant['catalog_mode']??'global')==='branches')$products=$app->all('SELECT p.id,p.name,p.status,p.description,COALESCE(bp.price_override,p.price) branch_price,COALESCE(bp.sale_price_override,p.sale_price) branch_sale_price FROM branch_products bp JOIN products p ON p.id=bp.product_id AND p.tenant_id=? WHERE bp.branch_id=? AND bp.is_active=1 AND p.status!="hidden" ORDER BY p.is_featured DESC,p.name LIMIT 40',[$tenant['id'],$bid]);
    else $products=$app->all('SELECT id,name,status,description,price branch_price,sale_price branch_sale_price FROM products WHERE tenant_id=? AND status!="hidden" ORDER BY is_featured DESC,name LIMIT 40',[$tenant['id']]);
    $data['productos']=[];foreach($products as $p){$price=(float)($p['branch_sale_price']?:$p['branch_price']);if(($promo=r2_active($app)?r2_promotion($app,(int)$tenant['id'],(int)$p['id']):null)&&$promo['kind']==='special')$price=min($price,(float)$promo['value']);$data['productos'][]=['nombre'=>$p['name'],'precio'=>$price,'descripcion'=>mb_substr((string)$p['description'],0,350),'estado'=>$p['status']==='sold_out'?'Agotado':'Publicado'];}
    $data['catalogo_parcial']=count($products)===40;
    if($user&&$user['role']==='customer')$data['mis_pedidos']=$app->all('SELECT order_number,status,total,created_at FROM orders WHERE tenant_id=? AND user_id=? ORDER BY id DESC LIMIT 5',[$tenant['id'],$user['id']]);
    return $data;
}
function fc_master_context(App $app,array $user):array {
    if($user['role']!=='master'||$user['force_password_change'])throw new DomainException('Ingresa con tu cuenta maestra y completa la actualización de contraseña si corresponde.');
    $real='slug NOT IN("tienda-demo","restaurante-demo") AND slug NOT REGEXP "^demo-(restaurante|tienda)-[0-9a-f]{32}$"';
    $today=(new DateTimeImmutable('today',new DateTimeZone('America/Costa_Rica')))->format('Y-m-d');$soon=date('Y-m-d',strtotime($today.' +14 days'));
    return ['perfil'=>'Asistente interno del administrador de FISITAAP','fecha'=>$today,
        'resumen'=>$app->one('SELECT COUNT(*) negocios,COALESCE(SUM(is_active),0) activos,COALESCE(SUM(expires_at<?),0) vencidos FROM tenants WHERE '.$real,[$today]),
        'negocios'=>$app->all('SELECT id,name,slug,plan,is_active,expires_at FROM tenants WHERE '.$real.' ORDER BY name LIMIT 100'),
        'vencimientos'=>$app->all('SELECT name,slug,plan,expires_at FROM tenants WHERE '.$real.' AND expires_at>=? AND expires_at<=? ORDER BY expires_at,name LIMIT 30',[$today,$soon]),
        'vencidos'=>$app->all('SELECT name,slug,plan,expires_at FROM tenants WHERE '.$real.' AND expires_at<? ORDER BY expires_at,name LIMIT 30',[$today]),
        'planes'=>$app->all('SELECT plan,COUNT(*) negocios FROM tenants WHERE '.$real.' GROUP BY plan ORDER BY negocios DESC,plan'),
        'actualizaciones'=>$app->all('SELECT t.name,COALESCE(v.version,179) version FROM tenants t LEFT JOIN fisitaap_tenant_updates180 v ON v.tenant_id=t.id WHERE t.'.$real.' ORDER BY t.name LIMIT 100'),
        'soporte'=>fc16_support_context($app),
        'contactos_pendientes'=>(int)$app->one('SELECT COUNT(*) total FROM contacts WHERE tenant_id IS NULL AND status="new"')['total'],
        'contactos_nuevos'=>$app->all('SELECT name,subject,created_at FROM contacts WHERE tenant_id IS NULL AND status="new" ORDER BY id DESC LIMIT 10'),
        'ayuda'=>'Eres el asistente personal del administrador, no un vendedor hacia él. Consulta los datos incluidos, prioriza vencimientos/contactos/actualizaciones y guía con pantallas reales del maestro. Puede revisar y confirmar cambios de nombre de plan/vencimiento con el formulario del chat. Planes y módulos tiene su configuración propia. Ninguna escritura se ejecuta por una respuesta de la IA. No afirmar cambios sin resultado confirmado. No tienes claves, contraseñas ni datos de pago.'];
}
function fc_scope(string $profile,?array $tenant,?array $user,string $branch=''):string {return hash('sha256',implode('|',['policy-r16',$profile,$tenant['id']??0,$user['id']??0,$user['role']??'public',$branch]));}
function fc_thread(string $scope):array {
    $row=$_SESSION['fc_threads'][$scope]??null;
    return is_array($row)&&($row['at']??0)>time()-1800?$row:['at'=>time(),'generation'=>bin2hex(random_bytes(8)),'history'=>[]];
}
function fc_save_thread(string $scope,array $row):void {
    $threads=$_SESSION['fc_threads']??[];$threads=array_filter($threads,static fn($x)=>($x['at']??0)>time()-1800);
    $row['at']=time();$threads[$scope]=$row;$_SESSION['fc_threads']=array_slice($threads,-12,null,true);
}
function fc_plan_intent(string $message):bool {
    return (bool)(preg_match('/\b(?:cambiar|cambia|ajustar|ajusta|renovar|renueva|modificar|modifica|editar|edita|actualizar|actualiza)\b/iu',$message)&&preg_match('/plan|venc|suscrip/iu',$message));
}
function fc_fallback(string $message,array $context,array $history,string $profile):string {
    $q=mb_strtolower($message);$previous='';foreach(array_reverse($history) as $h)if($h['role']==='user'){$previous=mb_strtolower($h['text']);break;}
    if(mb_strlen($q)<90&&preg_match('/^(si|sí|y |tengo |somos |una |un |tambien|también|cuanto|cuánto)/u',$q))$q.=' '.$previous;
    if($profile==='maestro'){
        if(preg_match('/soporte|ticket|incidencia/',$q)){
            $support=$context['soporte']??['pendientes'=>0,'tickets'=>[]];return 'Hay '.$support['pendientes'].' tickets de soporte pendientes. '.implode('; ',array_map(static fn($r)=>$r['reference'].' · '.$r['store_name'].' · '.$r['subject'],$support['tickets'])).'. Abre Administración → Tickets de soporte para ver los contactos y registrar seguimiento.';
        }
        if(fc_plan_intent($message))return 'Vamos a revisar el cambio. Elige el negocio y escribe el plan o vencimiento en el formulario. Te mostraré los valores actuales y los nuevos para que los confirmes antes de guardarlos. Los módulos se configuran en Planes y módulos.';
        if(preg_match('/actualiza|pendiente.*version|versi[oó]n/u',$q))return "Estado de actualización de los negocios:\n".implode("\n",array_map(static fn($r)=>$r['name'].': '.((int)$r['version']>=180?'1.8.0 activada':'Pendiente de activar 1.8.0'),$context['actualizaciones']))."\nPuedes revisar Administración → Actualizar sistema. Cada dueño prepara sus cajas y elige su momento.";
        if(preg_match('/vencid|ya venc|caduc/',$q))return $context['vencidos']?'Negocios con fecha vencida: '.implode('; ',array_map(static fn($r)=>$r['name'].' · '.$r['expires_at'].' · '.$r['plan'],$context['vencidos'])).'. La lista muestra hasta 30; el resumen cuenta todos. Puedes preparar una renovación desde el formulario del chat.':'No hay negocios con fecha de vencimiento anterior a hoy.';
        if(preg_match('/venc|renova/',$q))return $context['vencimientos']?'Vencimientos en los próximos 14 días: '.implode('; ',array_map(static fn($r)=>$r['name'].' · '.$r['expires_at'].' · '.$r['plan'],$context['vencimientos'])).'. Abre Planes y suscripciones para revisarlos, o prepara un cambio desde este chat.':'No hay vencimientos registrados en los próximos 14 días. El resumen también distingue negocios con fecha vencida; revisa Planes y suscripciones si quieres renovarlos.';
        if(preg_match('/contact|mensaje|consulta.*pendiente/',$q))return $context['contactos_nuevos']?'Últimos contactos nuevos: '.implode('; ',array_map(static fn($r)=>$r['name'].' · '.$r['subject'].' · '.$r['created_at'],$context['contactos_nuevos'])).'. Abre Web oficial → Contactos para leerlos y gestionar su estado.':'No hay contactos nuevos de la plataforma. Puedes revisar los leídos o resueltos en Web oficial → Contactos.';
        if(preg_match('/negocio|empresa|plan/',$q))return 'Negocios registrados: '.implode('; ',array_map(static fn($r)=>$r['name'].' · '.$r['plan'].' · '.($r['is_active']?'Activo':'Inactivo').' · vence '.($r['expires_at']?:'sin fecha'),$context['negocios'])).'. Las listas muestran hasta 100 negocios; el resumen cuenta todos.';
        if(preg_match('/clave|contrase|api.?key|config\.php/',$q))return 'Las claves y contraseñas no forman parte del contexto del chat. Puedo ayudarte a revisar negocios, planes, vencimientos, contactos y actualizaciones desde las pantallas del maestro.';
        $r=$context['resumen'];return 'Resumen de FISITAAP: '.$r['negocios'].' negocios reales, '.$r['activos'].' activos y '.$r['vencidos'].' con fecha de vencimiento anterior a hoy. Hay '.$context['contactos_pendientes'].' contactos nuevos de la plataforma. Podemos revisar los próximos vencimientos, el estado de las actualizaciones o preparar un cambio de plan. ¿Qué revisamos primero?';
    }
    if($profile==='dueno'){if(preg_match('/aviso|cumplea|vacacion|contrato|recordatorio/u',$q)&&array_key_exists('avisos_del_negocio',$context)){return $context['avisos_del_negocio']?"Avisos de tu negocio:\n".implode("\n",array_map(static fn($a)=>$a['title'].': '.$a['body'],$context['avisos_del_negocio']))."\nPulsa Revisar en los avisos del chat para abrir la pantalla correspondiente.":'No tienes avisos pendientes en este momento. Puedes revisar los expedientes y vacaciones en Control de personal.';}if(preg_match('/idea|sugier|vender mas|promocionar/',$q))return 'Para '.($context['negocio']??'tu tienda').', propongo destacar '.(!empty($context['productos'])?implode(', ',array_column(array_slice($context['productos'],0,3),'name')):'una selección de productos de tu catálogo').', revisar su margen antes de ofrecer descuentos y mejorar las descripciones con beneficios claros. Son ideas: no he cambiado datos. Podemos preparar un producto, cupón o texto concreto para revisarlo juntos.';if(preg_match('/venta|stock|inventario|existencia|impres|comanda|internet|offline|windows|android/',$q))return qa180_chat_answer($message,$context);return 'Soy el asistente de '.($context['negocio']??'tu tienda').'. Puedo analizar sus datos, aportar ideas y preparar productos, categorías, cupones, opcionales o cambios de sus textos y contacto. Los cambios se guardan después de que revisas y confirmas los valores. Si la IA no está disponible, puedes usar Crear producto en este chat.';}
    if($profile==='cliente'){
        if(preg_match('/pedido|entrega/',$q)&&isset($context['mis_pedidos']))return $context['mis_pedidos']?'Tus últimos pedidos en esta tienda: '.implode('; ',array_map(static fn($r)=>$r['order_number'].' · '.$r['status'].' · '.money((float)$r['total']),$context['mis_pedidos'])).'. Puedes ver el detalle en Mis pedidos.':'Todavía no hay pedidos registrados con tu cuenta en esta tienda. Puedes empezar desde el catálogo.';
        if(preg_match('/hora|direcci|ubic|tel[eé]f|contacto/u',$q)&&isset($context['negocio'])){$n=$context['negocio'];return $n['nombre'].'. '.($n['horarios']?'Horario: '.$n['horarios'].'. ':'').($n['direccion']?'Dirección: '.$n['direccion'].'. ':'').($n['telefono']?'Teléfono: '.$n['telefono'].'. ':'').'Puedes consultar al negocio con el botón de contacto.';}
        if(!empty($context['elige_sucursal']))return '¿En cuál sucursal deseas comprar? Están disponibles: '.implode(', ',array_column($context['sucursales'],'name')).'. Selecciónala en el catálogo para consultar sus productos y precios.';
        $matches=array_values(array_filter($context['productos']??[],static fn($p)=>mb_stripos($q,mb_strtolower($p['nombre']))!==false));
        if(($matches||preg_match('/producto|precio|men[uú]|venden|comprar|catalog|catálogo/u',$q))&&isset($context['productos']))return $context['productos']?'En '.$context['negocio']['nombre'].' puedes consultar: '.implode('; ',array_map(static fn($r)=>$r['nombre'].' · '.money((float)$r['precio']).' · '.$r['estado'],array_slice($matches?:$context['productos'],0,6))).'. Abre el catálogo para elegir opciones y armar tu pedido.':'No veo productos publicados en esta sucursal. Puedes consultar directamente al negocio con el botón de contacto.';
        return 'Te ayudo con el catálogo, cómo hacer un pedido, horarios y contacto de '.($context['negocio']['nombre']??'esta tienda').'. Para comprar, abre el catálogo, selecciona tus productos y confirma las opciones de entrega y pago. ¿Qué necesitas encontrar?';
    }
    $q=mb_strtolower($message);$k=fc_knowledge();
    if(preg_match('/mi venta|cu[aá]nto vend[ií]|clientes.*(dato|lista)|contrase|clave|datos privados/u',$q))return 'Esos datos se consultan al iniciar sesión en el panel de tu negocio, con los permisos correspondientes. Aquí puedo ayudarte con los pasos de uso, soporte y dudas sobre FISITAAP.';
    if(preg_match('/no imprime|no imprim|fall.*impres|impres.*fall/',$q))return "Revisemos la impresión:\n1. Abre FISITAAP en Windows o Android autorizado.\n2. Ve a Administración → Impresión y comprueba sucursal, equipo y punto.\n3. Pulsa Probar en el punto que falla. En Windows confirma el nombre de la impresora; en red confirma IP, puerto y que esté en la misma red.\n4. Revisa papel y conexión antes de pedir otra copia.\n¿Usas Windows o Android y falla el recibo, la comanda o ambos?";
    if(preg_match('/precio|cu[aá]nto (?:cuesta|vale|cobran)|costo|contratar|cotiz|plan/u',$q)){
        $known='';foreach(array_reverse($history) as $h)if($h['role']==='user'&&preg_match('/restaurante|soda|cafeter|tienda|ferreter|ropa/u',mb_strtolower($h['text']))){$known=mb_strtolower($h['text']);break;}
        return 'El asesor te confirma el precio, los módulos y las condiciones según lo que necesita tu negocio. '.($known!==''?'Para cotizar lo que me comentaste, ¿cuántas sucursales y cajas tienes?':'¿Qué tipo de negocio tienes y cuántas sucursales o cajas necesitas?').' Puedes escribirle con el botón Hablar con un asesor.';
    }
    foreach(['printing'=>'/imprim|impres|recibo|comanda|gaveta|fisitaap print/','offline'=>'/internet|offline|android|windows|principal|sin conex/','demos'=>'/demo|probar|prueba/','pricing'=>'/precio|cu[aá]nto (?:cuesta|vale|cobran)|costo|contratar|cotiz|plan/u','access'=>'/ingresar|iniciar sesi|acceso|no entra/','inventory'=>'/inventario|stock|compra|xml/','customers'=>'/cr[eé]dito|fideliza|cliente|abono/u','updates'=>'/actualiza|instala|cpanel/'] as $topic=>$pattern)if(preg_match($pattern,$q))return $k[$topic].($topic==='pricing'?' ¿Qué tipo de negocio tienes y cuántas sucursales o cajas necesitas?':' ¿Qué parte necesitas revisar?');
    if(preg_match('/restaurante|soda|cafeter|comida/',$q))return 'Para tu restaurante te convienen el menú con opciones y extras, POS, mesas y comandas de cocina/bar. También puedes gestionar pedidos para llevar o delivery. '. $k['offline'].' ¿Trabajas con mesas, solo para llevar o ambos?';
    if(preg_match('/tienda|ferreter|ropa|comercio/',$q))return 'Para tu negocio FISITAAP puede reunir catálogo, POS, inventario, compras y clientes. Si tienes varias sucursales, cada una mantiene su operación. Puedes probar el demo de tienda para ver el recorrido. ¿Tu prioridad es vender por internet, en caja o ambas?';
    return $k['overview'].' Puedes probar una tienda o restaurante antes de elegir. Te ayudo a encontrar los módulos que necesitas y a resolver dudas de uso. ¿Qué tipo de negocio tienes o qué necesitas resolver?';
}
function fc_plan_prepare(App $app,array $user,array $body):array {
    if($user['role']!=='master'||$user['force_password_change'])throw new DomainException('Solo la cuenta maestra puede preparar este cambio.');
    $slug=$body['business']??'';if(!is_string($slug))throw new DomainException('Selecciona el negocio.');
    $tenant=$app->one('SELECT id,name,plan,expires_at FROM tenants WHERE slug=? AND slug NOT IN("tienda-demo","restaurante-demo") AND slug NOT REGEXP "^demo-(restaurante|tienda)-[0-9a-f]{32}$"',[$slug]);if(!$tenant)throw new DomainException('Negocio no disponible.');
    $plan=$body['plan']??'';$expiry=$body['expires_at']??'';if(!is_string($plan)||!is_string($expiry))throw new DomainException('Revisa los datos del cambio.');
    $plan=trim($plan);if(mb_strlen($plan)>50)throw new DomainException('El nombre del plan admite hasta 50 caracteres.');
    if($plan===''&&$expiry===''&&empty($body['clear_expiry']))throw new DomainException('Escribe el nuevo plan o vencimiento.');
    if(!empty($body['clear_expiry'])&&$expiry!=='')throw new DomainException('Elige una fecha o Sin fecha de vencimiento.');
    if($expiry!==''){if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$expiry,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])||$expiry<(new DateTimeImmutable('today',new DateTimeZone('America/Costa_Rica')))->format('Y-m-d'))throw new DomainException('Elige una fecha de vencimiento válida desde hoy.');}
    $after=['plan'=>$plan!==''?$plan:$tenant['plan'],'expires_at'=>!empty($body['clear_expiry'])?null:($expiry!==''?$expiry:$tenant['expires_at'])];
    return ['token'=>bin2hex(random_bytes(16)),'expires'=>time()+600,'tenant_id'=>(int)$tenant['id'],'name'=>$tenant['name'],'before'=>['plan'=>$tenant['plan'],'expires_at'=>$tenant['expires_at']],'after'=>$after];
}
function fc_plan_confirm(App $app,array $user,array $proposal):string {
    if($proposal['expires']<time())throw new DomainException('La propuesta venció. Prepara el cambio otra vez.');
    qa180_update_lock($app,(int)$proposal['tenant_id']);$app->db->beginTransaction();try{
        if(!$app->one('SELECT id FROM users WHERE id=? AND role="master" AND is_active=1 AND force_password_change=0 FOR UPDATE',[$user['id']]))throw new DomainException('Tu acceso cambió. Inicia sesión con la cuenta maestra.');
        $row=$app->one('SELECT plan,expires_at FROM tenants WHERE id=? FOR UPDATE',[$proposal['tenant_id']]);if(!$row)throw new DomainException('Negocio no disponible.');
        if($row!==$proposal['after']){
            if($row!==$proposal['before'])throw new DomainException('El plan o vencimiento cambió después de esta propuesta. Revísalo y prepara una nueva.');
            $app->exec('UPDATE tenants SET plan=?,expires_at=? WHERE id=?',[$proposal['after']['plan'],$proposal['after']['expires_at'],$proposal['tenant_id']]);
            $id=audit($app,null,(int)$user['id'],'chat_master_plan','tenant',(int)$proposal['tenant_id']);
            $app->exec('UPDATE audit_logs SET summary=? WHERE id=?',['FISIChat: actualizó el plan y vencimiento de '.$proposal['name'],$id]);
        }
        $app->db->commit();return 'Cambio confirmado para '.$proposal['name'].': plan '.$proposal['after']['plan'].'; vencimiento '.($proposal['after']['expires_at']?:'sin fecha').'. Puedes revisarlo en Planes y suscripciones.';
    }catch(Throwable $error){if($app->db->inTransaction())$app->db->rollBack();throw $error;}
}
function fc_chat_api(App $app):never {
    header('Cache-Control: private, no-store');if(!is_post())json_response(['ok'=>false],405);
    $body=json_input_qa(16000);if(isset($body['csrf'])&&is_string($body['csrf']))$_POST['csrf']=$body['csrf'];verify_csrf();
    $scope=null;$ticket=null;
    try{
        $profile=$body['profile']??'tecnico';if($profile==='vendedor')$profile='tecnico';if(!is_string($profile)||!in_array($profile,['tecnico','cliente','dueno','maestro'],true))json_response(['ok'=>false,'error'=>'Perfil no disponible.'],422);
        $user=fc_chat_actor($app);$tenant=null;$slug=$body['tenant']??'';$branch=$body['branch']??'';
        if(!is_string($slug)||!is_string($branch))json_response(['ok'=>false,'error'=>'Contexto inválido.'],422);
        if($profile==='dueno'){if(!$user)throw new DomainException('Inicia sesión con tu cuenta del negocio.');$tenant=$app->one('SELECT * FROM tenants WHERE id=? AND is_active=1',[$user['tenant_id']]);}
        elseif($profile==='cliente'){if($slug==='')throw new DomainException('Selecciona la tienda para consultar su catálogo.');$tenant=tenant_by_slug($app,$slug);if(!$tenant||!$tenant['is_active'])throw new DomainException('Negocio no disponible.');}
        if($profile==='maestro'&&(!$user||$user['role']!=='master'||$user['force_password_change']))throw new DomainException('Ingresa con tu cuenta maestra para usar el asistente de administración.');
        if($profile==='dueno'){r2_chat_context($app,$profile,$tenant,$user);$activeBranch=fisichat_branch($app,$tenant,$user);$branch=(string)$activeBranch['id'];}
        $scope=fc_scope($profile,$tenant,$user,$branch);$thread=fc_thread($scope);$action=$body['action']??'message';
        if($action==='reset'){if($profile==='dueno'&&!empty($thread['store_proposal']['token'])&&fc16_ready($app))$app->exec('UPDATE fisitaap_chat_actions_r16 SET state="cancelled" WHERE token_hash=? AND tenant_id=? AND user_id=? AND state="prepared"',[hash('sha256',$thread['store_proposal']['token']),$tenant['id'],$user['id']]);$thread=['at'=>time(),'generation'=>bin2hex(random_bytes(8)),'history'=>[]];session_change176(static function()use($scope,$thread){fc_save_thread($scope,$thread);});json_response(['ok'=>true,'history'=>[]]);}
        if(!rate_limit_persistent($app,'fc_chat',client_identity((string)($user['id']??'public')),24,300))json_response(['ok'=>false,'error'=>'Has enviado varias consultas seguidas. Espera unos minutos.'],429);
        if($profile==='dueno'&&$action==='message'&&!empty($thread['store_proposal'])&&is_string($body['message']??null)&&preg_match('/^(?:si|confirmo|confirmar|confirmar cambio|hazlo|aplica el cambio|de acuerdo)$/u',fc_domain_text($body['message']))) {$action='confirm_store';$body['token']=$thread['store_proposal']['token'];}
        if(in_array($action,['prepare_store','confirm_store','discard_store'],true)){
            if($profile!=='dueno'||!$user||$user['role']!=='tenant_admin')throw new DomainException('Este cambio requiere la cuenta dueña de la tienda.');
            if($action==='prepare_store'){
                if(!is_array($body['operation']??null))throw new DomainException('Completa los datos de la propuesta.');
                $requestKey=fc16_text($body['request_id']??bin2hex(random_bytes(16)),64,true);if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$requestKey))throw new DomainException('Referencia inválida.');$proposal=fc16_prepare($app,$tenant,$user,$activeBranch,$body['operation'],$requestKey);$thread['store_proposal']=$proposal;session_change176(static function()use($scope,$thread){fc_save_thread($scope,$thread);});json_response(['ok'=>true,'reply'=>'Revisa la propuesta. Todavía no se ha guardado ningún cambio.','store_proposal'=>$proposal]);
            }
            $token=fc16_text($body['token']??'',64,true);fc16_owner($app,$user,$tenant,$activeBranch);
            if($action==='discard_store'){$app->exec('UPDATE fisitaap_chat_actions_r16 SET state="cancelled" WHERE token_hash=? AND tenant_id=? AND user_id=? AND branch_id=? AND state="prepared"',[hash('sha256',$token),$tenant['id'],$user['id'],$activeBranch['id']]);$result=['reply'=>'Propuesta descartada. No se aplicó el cambio.'];}
            else $result=fc16_confirm($app,$tenant,$user,$activeBranch,$token);
            unset($thread['store_proposal']);$thread['history'][]=['role'=>'assistant','text'=>$result['reply'],'in_scope'=>true];$thread['history']=array_slice($thread['history'],-12);session_change176(static function()use($scope,$thread){fc_save_thread($scope,$thread);});json_response(['ok'=>true,'confirmed'=>$action==='confirm_store']+$result);
        }
        if($action==='create_support'){
            if($profile!=='tecnico')throw new DomainException('Abre FISIChat en la web oficial para solicitar soporte de FISITAAP.');
            $result=fc16_support_create($app,$user,$body,$thread);$thread['history'][]=['role'=>'assistant','text'=>$result['reply'],'in_scope'=>true];$thread['history']=array_slice($thread['history'],-12);session_change176(static function()use($scope,$thread){fc_save_thread($scope,$thread);});json_response(['ok'=>true,'support_created'=>true]+$result);
        }
        if(in_array($action,['prepare_plan','confirm_plan'],true)){
            if($profile!=='maestro'||!$user||$user['role']!=='master')throw new DomainException('Esta operación requiere la cuenta maestra.');
            if($action==='prepare_plan'){$proposal=fc_plan_prepare($app,$user,$body);$thread['proposal']=$proposal;session_change176(static function()use($scope,$thread){fc_save_thread($scope,$thread);});json_response(['ok'=>true,'reply'=>'Revisa el cambio propuesto antes de confirmarlo.','proposal'=>$proposal]);}
            $proposal=$thread['proposal']??null;if(!$proposal||!is_string($body['token']??null)||!hash_equals($proposal['token'],$body['token']))throw new DomainException('Prepara y revisa el cambio antes de confirmarlo.');
            $reply=fc_plan_confirm($app,$user,$proposal);$thread['history'][]=['role'=>'assistant','text'=>$reply];$thread['history']=array_slice($thread['history'],-12);session_change176(static function()use($scope,$thread){fc_save_thread($scope,$thread);});json_response(['ok'=>true,'reply'=>$reply,'confirmed'=>true]);
        }
        if($action!=='message')json_response(['ok'=>false,'error'=>'Acción no disponible.'],422);
        $message=$body['message']??null;if(!is_string($message)||trim($message)===''||mb_strlen($message)>1500)json_response(['ok'=>false,'error'=>'Escribe una consulta de hasta 1500 caracteres.'],422);$message=trim($message);
        $ticket=$body['request_id']??bin2hex(random_bytes(16));if(!is_string($ticket)||!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$ticket))json_response(['ok'=>false,'error'=>'Solicitud inválida.'],422);
        $hash=hash('sha256',$message);$reserved=session_change176(static function()use($scope,$ticket,$hash){$t=fc_thread($scope);if(($t['last']['id']??'')===$ticket){if(!hash_equals($t['last']['hash'],$hash))throw new DomainException('La referencia corresponde a otra consulta.');return ['cached'=>$t['last']['result']];}if(($t['busy']['until']??0)>time())return ['contended'=>true];$t['busy']=['id'=>$ticket,'until'=>time()+70];fc_save_thread($scope,$t);return $t;});
        if(isset($reserved['cached']))json_response($reserved['cached']);if(isset($reserved['contended']))json_response(['ok'=>false,'error'=>'Todavía estoy respondiendo otra consulta de esta conversación. Espera un momento.'],409);
        if(!is_array($reserved))throw new DomainException('La sesión venció. Recarga la página.');
        $thread=$reserved;$context=$profile==='maestro'?fc_master_context($app,$user):r2_chat_context($app,$profile,$tenant,$user,$branch);if($profile==='dueno'&&r18_ready($app))$context['avisos_del_negocio']=r18_owner_alerts($app,$tenant,$user);
        $boundary=fc_domain_route($message,$profile,$context,$thread['history']);$inScope=$boundary===null;
        $manage=$inScope&&$profile==='maestro'&&fc_plan_intent($message);$supportOffer=$inScope&&$profile==='tecnico'&&fc16_support_offer($message,$thread['history']);$extra=[];
        session_release176();$cfg=fisichat_config($app);$engine='guide';$degraded=false;
        $reply=$boundary??fc_fallback($message,$context,array_values(array_filter($thread['history'],static fn($row)=>!empty($row['in_scope']))),$profile);$fallback=$reply;if(!$inScope)$engine='scope';
        if($supportOffer){$reply='Vamos a abrir un caso de soporte. Indica tu nombre, la tienda y su enlace, un teléfono o correo y qué falla. El formulario guardará esos datos junto con esta conversación para el equipo de FISITAAP. No compartas contraseñas ni claves.';}
        if($inScope&&$cfg['api_key']!==''&&!($profile==='dueno'&&preg_match('/aviso|cumplea|vacacion|contrato|recordatorio/iu',$message))&&!$manage&&!$supportOffer&&empty($GLOBALS['demo_context'])){
            try{
                if($profile==='dueno'){$extra=fc16_owner_reply($app,$tenant,$user,$activeBranch,$context,$thread['history'],$message,$cfg,$ticket,$fallback);$reply=$extra['reply'];unset($extra['reply']);}
                else {
                $instruction=fc_domain_instructions($profile,$context);
                if($profile!=='cliente')$instruction.=' Conocimiento confirmado de FISITAAP: '.json_encode(fc_knowledge(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                $input=array_map(static fn($h)=>['role'=>$h['role'],'content'=>$h['text']],array_values(array_filter($thread['history'],static fn($row)=>!empty($row['in_scope']))));$input[]=['role'=>'user','content'=>json_encode(['datos_autorizados'=>$context,'contacto'=>fc_contact($app,$profile==='cliente'?$tenant:null),'consulta'=>$message],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
                $result=fisichat_call($cfg,['model'=>$cfg['model'],'instructions'=>$instruction,'input'=>$input,'max_output_tokens'=>in_array($profile,['maestro','dueno'],true)?500:350,'store'=>false]);$reply=fc_domain_answer(fisichat_text($result),$profile,$context,$fallback);
                }
                $engine='ai';
            }catch(Throwable $error){fisichat_failure($error);$degraded=true;}
        }
        $result=['ok'=>true,'reply'=>$reply,'engine'=>$engine,'degraded'=>$degraded,'manage_plan'=>(bool)$manage,'support_offer'=>$supportOffer]+$extra;
        $saved=session_change176(static function()use($scope,$ticket,$hash,$message,$reply,$result,$thread,$inScope){$t=fc_thread($scope);if($t['generation']!==$thread['generation']||($t['busy']['id']??'')!==$ticket)return false;$t['history'][]=['role'=>'user','text'=>$message,'in_scope'=>$inScope];$t['history'][]=['role'=>'assistant','text'=>$reply,'in_scope'=>$inScope];$t['history']=array_slice($t['history'],-12);unset($t['busy']);$t['last']=['id'=>$ticket,'hash'=>$hash,'result'=>$result];if(isset($result['store_proposal']))$t['store_proposal']=$result['store_proposal'];fc_save_thread($scope,$t);return true;});
        if(!$saved)json_response(['ok'=>false,'error'=>'La conversación cambió o la sesión venció. Recarga para continuar.'],409);
        json_response($result);
    }catch(Throwable $error){if($scope&&$ticket)session_change176(static function()use($scope,$ticket){$t=fc_thread($scope);if(($t['busy']['id']??'')===$ticket){unset($t['busy']);fc_save_thread($scope,$t);}});if(!$error instanceof DomainException)fisichat_failure($error);json_response(['ok'=>false,'error'=>$error instanceof DomainException?$error->getMessage():'No pude completar la consulta. Intenta nuevamente.'], $error instanceof DomainException?403:503);}
}
function fc_chat_ui(App $app,string $profile,?array $tenant,?array $user,bool $full):void {
    if(!empty($GLOBALS['fc_rendered']))return;$GLOBALS['fc_rendered']=true;
    $master=$profile==='maestro';$store=$profile==='cliente'&&$tenant;$owner=$profile==='dueno';
    $branch=$owner&&$tenant&&$user?(string)(fisichat_branch($app,$tenant,$user)['id']??0):(($store&&is_string($_GET['sucursal']??null))?$_GET['sucursal']:'');
    $name=$master?'Tu asistente de administración':(($store||$owner)?$tenant['name']:'Asesor de FISITAAP');
    $welcome=$master?'Hola. Te ayudo a revisar negocios, planes, vencimientos, contactos y actualizaciones. También podemos preparar un cambio de plan y confirmarlo juntos.':($owner?'Hola. Soy el asistente de '.($tenant['name']??'tu tienda').'. Te ayudo a analizar sus datos, proponer ideas y preparar productos, cupones, opcionales o cambios para que los revises y confirmes.':($store?'Hola. Te ayudo con el catálogo, tus pedidos, horarios y contacto de '.$tenant['name'].'. ¿Qué necesitas?':'Hola. Soy FISIChat. Te ayudo a conocer FISITAAP, elegir funciones para tu negocio y resolver dudas de uso. ¿En qué te ayudo?'));
    $suggestions=$master?['Resumen de FISITAAP','Negocios por vencer','Actualizaciones pendientes','Cambiar un plan']:($owner?['Ver avisos de mi negocio','Ventas de este mes','Crear un producto','Ideas para vender más']:($store?['¿Qué productos venden?','¿Cómo hago un pedido?','Horarios y contacto']:['¿Qué es FISITAAP?','Quiero probar un demo','¿Puedo vender sin internet?','Necesito soporte de impresión']));
    $thread=fc_thread(fc_scope($profile,$tenant,$user,$branch));
    $cfg=['endpoint'=>url('api/fisichat-r2'),'profile'=>$profile,'tenant'=>$tenant['slug']??'','branch'=>$branch,'welcome'=>$welcome,'history'=>$thread['history'],'full'=>$full,'businesses'=>[],'store_proposal'=>$thread['store_proposal']??null,'can_manage_store'=>$owner&&$user&&$user['role']==='tenant_admin'&&fc16_ready($app)&&qa180_active($app,(int)$tenant['id']),'categories'=>[]];
    if($cfg['can_manage_store']&&r3_report_can($app,$user,$tenant,'categorias'))$cfg['categories']=$app->all('SELECT id,name FROM categories WHERE tenant_id=? AND is_active=1 ORDER BY name',[$tenant['id']]);
    if($owner&&r18_ready($app))$cfg['owner_alerts_endpoint']=url('api/owner-alerts-r18');
    if($master)$cfg['businesses']=$app->all('SELECT name,slug FROM tenants WHERE slug NOT IN("tienda-demo","restaurante-demo") AND slug NOT REGEXP "^demo-(restaurante|tienda)-[0-9a-f]{32}$" ORDER BY name LIMIT 100');
    $logo=($store||$owner)?($tenant['logo']??''):$app->setting('platform_logo',url('assets/fisitapp-logo.webp'));
    echo '<link rel="stylesheet" href="'.e(url('assets/fisichat.css?v=186')).'">';
    if(!$full)echo '<button class="fc-launch" data-fc-open type="button" aria-controls="fc-panel" aria-expanded="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 3v-3H3V6a2 2 0 0 1 2-2Z"/><path d="M7 9h10M7 13h6"/></svg><span>FISIChat</span></button>';
    $chatTag=$full?'section':'dialog';
    echo '<'.$chatTag.' id="fc-panel" class="fc-panel'.($full?' fc-full':'').'" data-fc '.($full?'':'hidden role="dialog" aria-modal="true"').' aria-labelledby="fc-title"><header class="fc-header"><div class="fc-avatar">'.($logo?'<img src="'.e(public_media_url($logo)).'" alt="'.e($store?$tenant['name']:'FISITAAP').'">':'<span>F</span>').'</div><div><h2 id="fc-title">FISIChat</h2><p>'.e($name).'</p></div><button class="fc-icon" data-fc-reset type="button" aria-label="Nueva conversación" title="Nueva conversación">↻</button>'.(!$full?'<button class="fc-icon" data-fc-close type="button" aria-label="Cerrar chat">×</button>':'').'</header><div class="fc-scroll" data-fc-scroll><div class="r18-chat-alerts" data-r18-alerts hidden></div><div class="fc-messages" data-fc-messages role="log" aria-live="polite" aria-relevant="additions"><p class="fc-day">Conversemos</p></div><div class="fc-suggestions" data-fc-suggestions>';foreach($suggestions as $text)echo '<button type="button" data-fc-suggest>'.e($text).'</button>';echo '</div>';
    if($master)echo '<div class="fc-actions" data-fc-plan hidden><label>Negocio<select data-fc-business aria-label="Negocio"></select></label><label>Nuevo plan<input data-fc-newplan maxlength="50" placeholder="Conservar actual"></label><label>Vencimiento<input data-fc-expiry type="date"></label><label class="fc-check"><input type="checkbox" data-fc-clearexpiry> Sin fecha de vencimiento</label><button type="button" data-fc-prepare>Revisar cambio</button></div><div class="fc-actions" data-fc-proposal hidden></div>';
    if($owner&&$cfg['can_manage_store'])echo '<div class="fc-action-bar"><button type="button" data-fc-create-product>Crear producto</button></div><form class="fc-actions fc-store-form" data-fc-product hidden><strong>Crear producto</strong><label>Nombre<input name="name" maxlength="120" required></label><label>Categoría<select name="category_id" required><option value="">Selecciona una categoría</option></select></label><label>Precio<input name="price" type="number" step=".01" min="0" required></label><label>Descripción<textarea name="description" maxlength="3000" rows="2"></textarea></label><div class="fc-hstack"><button type="submit">Revisar producto</button><button type="button" data-fc-cancel-product>Volver</button></div></form><div class="fc-actions" data-fc-store-proposal hidden></div>';
    if($profile==='tecnico')echo '<div class="fc-action-bar"><button type="button" data-fc-open-support>Solicitar soporte</button></div><form class="fc-actions fc-support-form" data-fc-support hidden><strong>Crear ticket de soporte</strong><p>Comparte un contacto para atender el caso. No incluyas contraseñas ni claves.</p><label>Tu nombre<input name="contact_name" maxlength="150" required autocomplete="name"></label><label>Nombre de la tienda<input name="store_name" maxlength="150" required></label><label>Enlace público de la tienda (opcional)<input name="store_url" type="url" maxlength="300" placeholder="https://fisitaap.com/tu-tienda"></label><label>Teléfono<input name="phone" maxlength="40" type="tel" autocomplete="tel"></label><label>Correo<input name="email" maxlength="190" type="email" autocomplete="email"></label><label>Asunto<input name="subject" maxlength="180" required></label><label>Qué falla y qué intentaste<textarea name="description" maxlength="4000" rows="3" required></textarea></label><div class="fc-hstack"><button type="submit">Crear ticket</button><button type="button" data-fc-cancel-support>Volver al chat</button></div></form>';
    echo '</div><p class="fc-status" data-fc-status role="status"></p><form class="fc-compose" data-fc-form><label class="fc-sr" for="fc-message">Tu mensaje</label><textarea id="fc-message" name="message" maxlength="1500" rows="1" placeholder="Escribe tu mensaje…" required></textarea><button type="submit" aria-label="Enviar mensaje"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Zm3 8h14"/></svg></button></form><footer class="fc-footer">';
    $contact=$master?url('master'):fc_contact($app,$store?$tenant:null);
    if($contact)echo '<a href="'.e($contact).'" '.($master?'':'target="_blank" rel="noopener"').'>'.($master?'Abrir panel maestro':($store?'Contactar tienda':'Hablar con un asesor')).' ↗</a>';
    echo '<span>'.(($master||$owner)?'Cambios con tu confirmación':'Te ayudamos paso a paso').'</span></footer><script type="application/json" data-fc-config>'.json_encode($cfg,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).'</script></'.$chatTag.'><script src="'.e(url('assets/fisichat.js?v=19')).'" defer></script>';
}
