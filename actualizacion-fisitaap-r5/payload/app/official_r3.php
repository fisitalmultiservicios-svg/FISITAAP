<?php
declare(strict_types=1);

function r3_official_fields(): array
{
    $fields=[
        'hero_title'=>['Título principal',"Vende en línea.\nGestiona en un solo lugar."],
        'hero_text'=>['Texto principal','Para tiendas, ferreterías, moda, tecnología, belleza y restaurantes.'],
        'hero_button'=>['Botón principal','Explorar FISITAAP'],
        'whatsapp_strip'=>['Franja bajo la presentación','El cliente elige. Tú recibes el pedido resumido por WhatsApp.'],
        'catalog_title'=>['Título del catálogo','Un catálogo que se adapta a lo que vendes.'],
        'catalog_text'=>['Texto del catálogo','Muestra tus productos con sus variantes y opciones, como tallas, colores o modelos.'],
        'tools_title'=>['Título de herramientas','Las herramientas que necesita tu comercio.'],
        'tools_text'=>['Texto de herramientas','Todo en un solo lugar, para que vendas y gestiones más fácil.'],
        'chat_title'=>['Título de FISIChat','FISI-CHAT, dentro de tu negocio.'],
        'chat_text'=>['Texto de FISIChat','Consulta información y recibe ayuda para gestionar productos, clientes y promociones.'],
        'steps_title'=>['Título del proceso','Así compra tu cliente.'],
        'steps_text'=>['Texto del proceso','Un proceso simple y rápido.'],
        'demo_title'=>['Título de demostraciones','Pruébalo con un negocio de ejemplo.'],
        'demo_text'=>['Texto de demostraciones','Explora estos catálogos de muestra y descubre todo lo que puedes hacer.'],
        'contact_title'=>['Título de contacto','Tu próximo pedido empieza aquí.'],
        'contact_text'=>['Texto de contacto','Digitaliza tu comercio con FISITAAP y empieza a vender hoy mismo.'],
        'footer_text'=>['Texto del pie','Plataforma de comercio para todo tipo de negocios.'],
    ];
    foreach(['Catálogo en línea','Pedidos por WhatsApp','Clientes y fidelización','Inventario','Reportes','Tienda física opcional'] as $i=>$title) {
        $bodies=['Muestra tus productos de forma atractiva y siempre disponible.','El cliente arma su pedido y lo recibes resumido en WhatsApp.','Guarda información de tus clientes y dales un mejor servicio.','Controla existencias y recibe alertas de stock.','Visualiza el rendimiento de tu negocio.','También puedes gestionar tus ventas en tu punto de venta.'];
        $fields['tool_'.($i+1).'_title']=['Herramienta '.($i+1).' · título',$title];$fields['tool_'.($i+1).'_text']=['Herramienta '.($i+1).' · texto',$bodies[$i]];
    }
    foreach([['¿Puedo personalizar mi catálogo?','Sí. Puedes editar colores, imágenes, textos, categorías, productos, extras y opcionales desde el panel de tu negocio.'],['¿Cómo llegan los pedidos?','El pedido queda registrado en la web y el cliente puede enviarte el resumen por WhatsApp. Tu equipo lo verá en la pantalla de pedidos.'],['¿Puedo gestionar sucursales?','Sí. Puedes definir direcciones, horarios, formas de pago y entrega, y configurar los productos de cada sucursal.']] as $i=>[$question,$answer]) {$fields['faq_'.($i+1).'_title']=['Pregunta '.($i+1),$question];$fields['faq_'.($i+1).'_text']=['Respuesta '.($i+1),$answer];}
    foreach([['Categorías','Organiza tus productos por tipo de negocio.'],['Variantes y extras','Tallas, colores, modelos y opciones adicionales.'],['Promociones','Crea ofertas y destaca tus productos.'],['Sucursales','Maneja un catálogo para una o varias sucursales.']] as $i=>[$title,$text]){$fields['feature_'.($i+1).'_title']=['Característica '.($i+1).' · título',$title];$fields['feature_'.($i+1).'_text']=['Característica '.($i+1).' · texto',$text];}
    foreach([['Explora el catálogo','Encuentra los productos que necesitas.'],['Arma su pedido','Selecciona las opciones y agrega al pedido.'],['Envía el resumen','Confirma su pedido y lo recibes por WhatsApp.']] as $i=>[$title,$text]){$fields['step_'.($i+1).'_title']=['Paso '.($i+1).' · título',$title];$fields['step_'.($i+1).'_text']=['Paso '.($i+1).' · texto',$text];}
    foreach([['Tienda general','Electrónica, moda, hogar, herramientas y más.'],['Restaurante','Menú en línea con categorías y opciones.']] as $i=>[$title,$text]){$fields['demo_'.($i+1).'_title']=['Demo '.($i+1).' · título',$title];$fields['demo_'.($i+1).'_text']=['Demo '.($i+1).' · texto',$text];}
    return $fields;
}
function r3_official_editor(App $app,array $u): void
{
    if(is_post()) {
        verify_csrf();try {
            foreach(r3_official_fields() as $key=>[$label,$default])r2_set($app,0,'official_'.$key,mb_substr(trim((string)($_POST[$key]??'')),0,3000));
            foreach(['hero_image','catalog_image','chat_image','demo_shop_image','demo_menu_image'] as $key){if(isset($_POST['clear_'.$key]))r2_set($app,0,'official_'.$key,'');elseif($image=upload_image($key))r2_set($app,0,'official_'.$key,$image);}
            flash('success','Web oficial guardada.');
        }catch(Throwable $ex){flash('error',$ex->getMessage());}redirect(url('master/contenido'));
    }
    admin_shell_start('Web oficial de FISITAAP',$u,null,'contenido');
    echo '<p><a class="btn" target="_blank" href="'.url().'">Ver web oficial</a> <a class="btn btn-light" href="'.url('master/configuracion').'">Logo, contacto y redes sociales</a></p><section class="card admin-card"><form method="post" enctype="multipart/form-data">'.csrf_field();
    foreach(r3_official_fields() as $key=>[$label,$default])echo '<label>'.e($label).'<textarea class="input" rows="2" name="'.e($key).'" maxlength="3000">'.e(r2_setting($app,0,'official_'.$key,$default)).'</textarea></label>';
    foreach(['hero_image'=>'Presentación: ilustración de computadora y teléfono','catalog_image'=>'Catálogo: imagen de variantes','chat_image'=>'FISIChat: imagen de ejemplo','demo_shop_image'=>'Demostración: tienda general','demo_menu_image'=>'Demostración: restaurante'] as $key=>$label) {
        $image=r2_setting($app,0,'official_'.$key);echo '<label>'.e($label).($image?'<img class="r2-editor-image" src="'.e(public_media_url($image)).'" alt="">':'').'<input class="input" type="file" name="'.$key.'" accept="image/jpeg,image/png,image/webp"></label><label><input type="checkbox" name="clear_'.$key.'"> Usar ilustración predeterminada</label>';
    }
    echo '<button class="btn">Guardar web oficial</button></form></section>';admin_shell_end();
}
function r3_market_card(string $image,string $name,string $price='₡5.000'): string
{
    return '<article class="r3-market-product"><img src="'.url('assets/demo/'.$image.'.webp').'" alt=""><strong>'.e($name).'</strong><small>'.e($price).'</small><span>+</span></article>';
}
function r3_market_devices(): string
{
    $html='<div class="r3-devices" aria-label="Ejemplo del panel y el catálogo de FISITAAP"><div class="r3-laptop"><div class="r3-laptop-screen"><div class="r3-mock-sidebar"><b>FISITAAP</b><span>▦ Productos</span><span>▤ Pedidos</span><span>♙ Clientes</span><span>▣ Inventario</span><span>▥ Reportes</span><span>⚙ Configuración</span></div><div class="r3-mock-dashboard"><header><b>Productos</b><span>Mi Negocio ▾</span></header><div class="r3-mock-search">⌕ Buscar producto… <b>Agregar producto</b></div><nav>Todos　Electrónica　Herramientas　Moda　Belleza</nav><table><thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Inventario</th><th>Estado</th></tr></thead><tbody>';
    foreach([['headphones','Audífonos inalámbricos','Electrónica','₡5.000','24'],['bottle','Botella térmica','Hogar','₡8.000','12'],['sneakers','Tenis deportivos','Moda','₡15.000','18'],['skincare','Crema facial','Belleza','₡4.200','30']] as [$image,$name,$category,$price,$stock])$html.='<tr><td><img src="'.url('assets/demo/'.$image.'.webp').'" alt="">'.e($name).'</td><td>'.e($category).'</td><td>'.$price.'</td><td>'.$stock.'</td><td><i>Activo</i></td></tr>';
    $html.='</tbody></table></div></div><div class="r3-laptop-base"></div></div><div class="r3-market-phone"><header>FISITAAP　⌕　♙　▢</header><nav>Todos　Moda　Tecnología</nav><div>'.r3_market_card('headphones','Audífonos').r3_market_card('bottle','Botella térmica').r3_market_card('sneakers','Tenis deportivos','₡15.000').r3_market_card('skincare','Crema facial','₡4.200').'</div></div></div>';
    return $html;
}
function r3_tool_preview(int $i): string
{
    if($i===1)return '<div class="r3-mini-products">'.r3_market_card('sneakers','Tenis').r3_market_card('headphones','Audífonos').r3_market_card('bottle','Botella').'</div>';
    if($i===2)return '<div class="r3-mini-whatsapp"><b>Nuevo pedido · FISITAAP</b><p>Cliente: Ana López<br>Productos:<br>• Tenis deportivos (Talla 38, blanco) ×1<br>• Audífonos inalámbricos ×1</p><strong>Total: ₡20.000</strong><small>10:24 a. m. ✓✓</small></div>';
    if($i===3)return '<div class="r3-mini-clients"><header>Clientes <b>Agregar cliente</b></header><p><i>A</i>Ana López <small>Cliente frecuente</small></p><p><i>C</i>Carlos Méndez <small>Cliente del negocio</small></p><p><i>M</i>María Torres <small>Cliente del negocio</small></p></div>';
    if($i===4)return '<table class="r3-mini-stock"><tr><th>Producto</th><th>Stock</th><th>Estado</th></tr><tr><td>Audífonos</td><td>24</td><td><i>En stock</i></td></tr><tr><td>Botella térmica</td><td>8</td><td><em>Stock bajo</em></td></tr><tr><td>Tenis</td><td>12</td><td><i>En stock</i></td></tr><tr><td>Crema facial</td><td>3</td><td><em>Stock bajo</em></td></tr></table>';
    if($i===5)return '<div class="r3-mini-report"><b>Pedidos por día</b><div class="r3-market-bars">'.implode('',array_map(fn($n)=>'<i style="height:'.$n.'%"></i>',[36,64,42,74,56,85,100])).'</div><hr><b>Productos más vendidos</b><p>1　Tenis deportivos　28<br>2　Audífonos　24<br>3　Botella térmica　16</p></div>';
    return '<div class="r3-mini-pos"><b>Punto de venta</b><small>⌕ Buscar producto…</small><p>Tenis deportivos　　₡15.000<br>Audífonos　　　　₡5.000</p><strong>Total　₡20.000</strong><span>Registrar venta</span></div>';
}
function r3_official(App $app): void
{
    $defaults=r3_official_fields();$value=fn($key)=>r2_setting($app,0,'official_'.$key,$defaults[$key][1]??'');
    $name=$app->setting('site_name','FISITAAP');$phone=preg_replace('/\D/','',(string)$app->setting('master_whatsapp'));$contact=$phone?'https://wa.me/'.$phone:url('owner-login');
    $hero=r2_setting($app,0,'official_hero_image');$catImage=r2_setting($app,0,'official_catalog_image');$chatImage=r2_setting($app,0,'official_chat_image');
    $logo=(string)$app->setting('platform_logo','');$brand=$logo?'<img src="'.e(public_media_url($logo)).'" alt="'.e($name).'">':e($name);
    layout_start($name);echo '<div class="r3-official"><header class="r3-official-nav"><a class="r3-official-brand" href="'.url().'">'.$brand.'</a><nav><a href="#funciones">Funciones</a><a href="#como-funciona">Cómo funciona</a><a href="#demos">Demos</a><a href="#preguntas">Preguntas</a></nav><a class="r3-market-button" href="'.url('owner-login').'">Acceso negocios →</a></header><main><section class="r3-official-hero"><h1>'.nl2br(e($value('hero_title'))).'</h1><p>'.e($value('hero_text')).'</p><div><a class="r3-market-button" href="#funciones">'.e($value('hero_button')).' →</a><a class="r3-market-button pale" href="#demos">Ver demos</a></div>'.($hero?'<img class="r3-devices-image" src="'.e(public_media_url($hero)).'" alt="Panel y catálogo de FISITAAP">':r3_market_devices()).'</section><div class="r3-whatsapp-strip"><span>◉</span><strong>'.e($value('whatsapp_strip')).'</strong>'.r3_icon('transfer').'</div><section class="r3-market-adapt"><div><h2>'.e($value('catalog_title')).'</h2><p>'.e($value('catalog_text')).'</p>'.($catImage?'<img class="r3-custom-marketing" src="'.e(public_media_url($catImage)).'" alt="Opciones del catálogo">':'<div class="r3-variant-cards"><article><img src="'.url('assets/demo/sneakers.webp').'" alt="Tenis"><strong>Tenis deportivos</strong><small>Talla</small><span>36　37　<b>38</b>　39　40</span><small>Color</small><span class="r3-color-options"><i></i><i></i><i></i><i></i></span></article><article><img src="'.url('assets/demo/headphones.webp').'" alt="Audífonos"><strong>Audífonos inalámbricos</strong><small>Color</small><span>Negro　Azul　Blanco</span><small>Modelo</small><span>Básico　Pro</span></article></div>').'</div><aside>';
    foreach([['box','Categorías','Organiza tus productos por tipo de negocio.'],['settings','Variantes y extras','Tallas, colores, modelos y opciones adicionales.'],['gift','Promociones','Crea ofertas y destaca tus productos.'],['shop','Sucursales','Maneja un catálogo para una o varias sucursales.']] as $i=>[$icon,$title,$text])echo '<article>'.r3_icon($icon).'<div><h3>'.e($value('feature_'.($i+1).'_title')).'</h3><p>'.e($value('feature_'.($i+1).'_text')).'</p></div></article>';echo '</aside></section><section id="funciones" class="r3-market-tools"><h2>'.e($value('tools_title')).'</h2><p>'.e($value('tools_text')).'</p><div>';
    foreach(['cart','transfer','person','box','clock','shop'] as $i=>$icon)echo '<article><header>'.r3_icon($icon).'<div><h3>'.e($value('tool_'.($i+1).'_title')).'</h3><p>'.e($value('tool_'.($i+1).'_text')).'</p></div></header>'.r3_tool_preview($i+1).'</article>';echo '</div></section><section class="r3-market-chat"><div><h2>'.e($value('chat_title')).'</h2><p>'.e($value('chat_text')).'</p><ul><li>Responde tus preguntas</li><li>Te ayuda a gestionar tu catálogo</li><li>Te da recomendaciones</li></ul><a href="'.url('asistente?perfil=vendedor').'">Conocer FISIChat →</a></div>'.($chatImage?'<img src="'.e(public_media_url($chatImage)).'" alt="FISIChat">':'<div class="r3-chat-laptop"><header>FISITAAP　　FISI-CHAT</header><p>Hola, ¿en qué puedo ayudarte?</p><b>¿Cuáles productos tienen poco stock?</b><article>Consulta las existencias de tu sucursal en Inventario. También puedes revisar los productos más vendidos en KPIs.</article><footer>Escribe tu mensaje…　➤</footer></div>').'</section><section id="como-funciona" class="r3-market-steps"><h2>'.e($value('steps_title')).'</h2><p>'.e($value('steps_text')).'</p><div>';
    foreach([['Explora el catálogo','Encuentra los productos que necesitas.'],['Arma su pedido','Selecciona las opciones y agrega al pedido.'],['Envía el resumen','Confirma su pedido y lo recibes por WhatsApp.']] as $i=>[$title,$text]){$title=$value('step_'.($i+1).'_title');$text=$value('step_'.($i+1).'_text');echo '<article><span class="r3-step-number">'.($i+1).'</span><div><h3>'.$title.'</h3><p>'.$text.'</p></div><div class="r3-step-phone">'.($i===0?r3_market_card('headphones','Audífonos'):($i===1?r3_market_card('sneakers','Tenis deportivos','₡15.000'):'<h4>Tu pedido</h4><p>Tenis deportivos　₡15.000<br>Audífonos　　　　₡5.000</p><b>Total　₡20.000</b>')).'<small>'.($i===2?'Enviar por WhatsApp':'Agregar al pedido').'</small></div></article>';}echo '</div></section><section id="demos" class="r3-market-demos"><h2>'.e($value('demo_title')).'</h2><p>'.e($value('demo_text')).'</p><div>';
    foreach([['tienda-demo','Tienda general','Electrónica, moda, hogar, herramientas y más.','demo_shop_image','slide-lifestyle'],['restaurante-demo','Restaurante','Menú en línea con categorías y opciones.','demo_menu_image','slide-food']] as $i=>[$slug,$title,$text,$key,$defaultImage]){$title=$value('demo_'.($i+1).'_title');$text=$value('demo_'.($i+1).'_text');$demo=tenant_by_slug($app,$slug);$image=r2_setting($app,0,'official_'.$key,url('assets/demo/'.$defaultImage.'.webp'));echo '<article style="background-image:linear-gradient(90deg,#eee9e8ee,#eee9e833),url(&quot;'.e(public_media_url($image)).'&quot;)"><h3>'.$title.'</h3><p>'.$text.'</p><a class="r3-market-button" href="'.($demo?url($slug.'/'.catalog_segment($demo)):url('demo')).'">Abrir demo →</a></article>';}
    echo '</div></section><section id="preguntas" class="r3-market-faq"><h2>Preguntas frecuentes.</h2>';for($i=1;$i<=3;$i++)echo '<details><summary>'.e($value('faq_'.$i.'_title')).'</summary><p>'.e($value('faq_'.$i.'_text')).'</p></details>';echo '</section>';
    $shops=$app->all('SELECT name,slug,logo FROM tenants WHERE is_active=1 AND is_listed=1 AND slug NOT IN ("tienda-demo","restaurante-demo") ORDER BY name LIMIT 100');
    if($shops){echo '<section class="r3-market-directory"><h2>Negocios afiliados</h2><div>';foreach($shops as $shop)echo '<a href="'.url($shop['slug']).'">'.($shop['logo']?'<img src="'.e(public_media_url($shop['logo'])).'" alt="">':'').e($shop['name']).'</a>';echo '</div></section>';}
    echo '<section class="r3-market-contact"><div><h2>'.e($value('contact_title')).'</h2><p>'.e($value('contact_text')).'</p></div><a class="r3-market-button" href="'.e($contact).'">◉ Hablemos por WhatsApp →</a></section></main><footer class="r3-official-footer"><strong>'.e($name).'</strong><span>'.e($value('footer_text')).'</span><a href="'.e($contact).'">Contacto</a>';foreach(['facebook_url'=>'Facebook','instagram_url'=>'Instagram'] as $key=>$label){$link=(string)$app->setting($key);if(preg_match('~^https?://~i',$link))echo '<a href="'.e($link).'" rel="noopener">'.$label.'</a>';}echo '<small>Creado por Fisital Multiservicios.</small></footer></div>';r2_chat_widget($app,null);layout_end();
}
