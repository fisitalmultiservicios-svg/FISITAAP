# FISITAAP QA para ejecucion en Codex

## Objetivo

Convertir el QA personal de FISITAAP en tareas tecnicas claras para que Codex corrija la plataforma sin adivinar intenciones. El trabajo debe cubrir los roles de maestro, dueno de negocio, cajero, cocina, repartidor y usuario final, manteniendo seguridad multiempresa, permisos por modulo, experiencia movil y rendimiento.

## Instruccion inicial para Codex

Trabaja sobre el proyecto FISITAAP. Lee primero la estructura actual, rutas y modulos antes de editar. No reescribas la plataforma completa. Implementa por bloques pequenos, con migraciones cuando falten campos, pruebas de regresion y verificacion visual en escritorio y movil.

Antes de terminar, entrega:

- Lista de cambios por modulo.
- Archivos modificados.
- Migraciones agregadas o ajustadas.
- Pruebas ejecutadas y resultado.
- Riesgos pendientes si algo requiere servidor real, impresora real, Android o Windows.
- Capturas o verificacion visual de las pantallas principales si el entorno lo permite.

## Reglas globales

1. Mantener aislamiento por `tenant_id`, `branch_id` y usuario. Ninguna consulta debe mezclar datos de otro negocio.
2. Todo formulario que escriba datos debe usar CSRF, permisos del rol, validacion de tenant y transaccion cuando afecte dinero, inventario, pedidos, mesas, turnos o cuentas.
3. Las operaciones de cobro, cierre, abonos, compras, XML, tarjetas de regalo y sincronizacion offline deben ser idempotentes con una llave de operacion para evitar duplicados por doble clic o reintento.
4. En operaciones concurrentes usar bloqueo de filas y revision de version donde aplique: mesa, ticket, turno, inventario, compra, CxC, CxP, tarjetas de regalo y sincronizacion offline.
5. Todo movimiento iniciado por usuario desde la interfaz debe quedar en bitacora con usuario, fecha, modulo, accion, entidad, id y resumen legible. No llenar la bitacora con procesos internos automaticos o consultas de solo lectura.
6. Las funciones que manipulan dinero fisico solo deben ejecutarse desde Desktop o Android autorizado: cobrar, abrir turno, cerrar turno, entrada de dinero, salida de dinero, abrir gaveta y pagos que resten de caja.
7. Desde web se puede preparar venta, cargar productos, abrir mesa o tomar pedido si el permiso lo permite, pero el cobro y movimientos de caja deben quedar bloqueados o redirigidos al dispositivo autorizado.
8. Todo cambio visual debe probarse en movil. Prioridad especial: ventas, cobro, mesas, disenador de salon, pedidos tipo Kanban, compras XML y web oficial.
9. No romper las mejoras de rendimiento ya trabajadas. Evitar consultas N+1 en catalogo, POS, reportes, clientes, opciones, combos, turnos y salones.
10. Si se toca un paquete de actualizacion, regenerar manifest, hashes y version. No dejar un ZIP o instalador con checksum desactualizado.

## Prioridades

| Prioridad | Bloque | Motivo |
| --- | --- | --- |
| P0 | Cobro, turnos, cajas, mesas abiertas y permisos de dinero | Riesgo directo de perdida de dinero o datos inconsistentes |
| P0 | Compras XML, inventario, CxP y CxC | Afecta stock y saldos contables |
| P1 | POS, venta rapida, mesa modal, pedidos Kanban y express | Flujo operativo diario |
| P1 | Desktop Android offline, equipos y cajas | Operacion en tienda fisica |
| P1 | Responsive de ventas, mesas y cobro | Uso real en telefono/tablet |
| P2 | Web oficial, demos, FISI-CHAT publico y landing | Conversion y presentacion comercial |
| P2 | Reportes, bitacora, contactos e impresion | Administracion y control |

## Web oficial y landing publica

### 1. Boton verde Explorar FISITAAP

Problema: en celular el boton principal verde de "Explorar FISITAAP" no responde bien visualmente o rompe el responsive.

Correccion esperada:

- Revisar el hero de la web oficial en desktop y movil.
- El boton debe verse completo, no salirse del ancho, no tapar texto y conservar buen tamano tactil.
- Si hay dos botones principales, deben apilarse en movil con separacion clara.
- Probar ancho aproximado de 360 px, 390 px, tablet y desktop.

Criterio de aceptacion:

- El CTA se lee completo.
- No hay scroll horizontal.
- El primer viewport muestra marca, mensaje principal, CTA y parte del contenido siguiente.

### 2. Segmento Un catalogo que se adapta

Problema: el ejemplo actual esta muy orientado a productos generales y no muestra comida.

Correccion esperada:

- Agregar ejemplo visual o textual de comida junto a moda, tecnologia u otros comercios.
- Mostrar que FISITAAP sirve para restaurantes, sodas, cafeterias, ferreterias, tiendas, belleza, servicios y comercios mixtos.
- Evitar que la landing parezca solo de comida o solo de tienda.

Criterio de aceptacion:

- El usuario entiende que puede vender productos, menu, combos, extras, variantes y servicios.

### 3. Eliminar frase Tienda fisica opcional

Problema: en herramientas se menciona "Tienda fisica opcional" y se quiere reemplazar por otra virtud sin enfatizarlo de esa forma.

Correccion esperada:

- Cambiar ese bloque por una virtud mas amplia, por ejemplo "Ventas en caja y sucursales", "Control operativo del negocio" o "Operacion en tienda y en linea".
- No usar la frase exacta "tienda fisica opcional".

Criterio de aceptacion:

- La web comunica valor sin sonar a modulo secundario.

### 4. FISI-CHAT publico

Problema: el ejemplo de FISI-CHAT manda al usuario a buscar por su cuenta en lugar de responder de forma real.

Correccion esperada:

- Ajustar el ejemplo, prompt o contenido demo para que FISI-CHAT responda directamente con una orientacion util.
- Si la IA no tiene datos reales, debe decir que no puede ver esa informacion y ofrecer pasos concretos dentro de FISITAAP.
- No debe responder "busca en..." como respuesta principal.

Criterio de aceptacion:

- En el demo publico, una pregunta comun recibe respuesta concreta, breve y accionable.

### 5. Demos y tienda LA VENTANITA

Problema: algunos demos usan formatos viejos y la tienda "LA VENTANITA" funciona o se ve mal.

Correccion esperada:

- Revisar plantillas/demo existentes y unificarlas al estilo actual.
- Corregir estetica, portada, catalogo, imagenes, colores, botones y flujo de carrito de "LA VENTANITA".
- Las futuras tiendas creadas deben usar la plantilla nueva, no estructuras anteriores.

Criterio de aceptacion:

- Los demos se sienten modernos, coherentes y funcionales.
- No hay mezcla visible entre disenos viejos y nuevos.

### 6. Eliminar blog

Problema: la seccion blog ya no debe existir.

Correccion esperada:

- Remover enlaces, rutas visibles, menu y pantallas de administracion relacionadas con blog.
- Si existen tablas historicas, no borrarlas sin migracion segura. Solo ocultar/desactivar el modulo salvo que se indique eliminar datos.

Criterio de aceptacion:

- Ningun rol ve blog en navegacion o administracion.

### 7. Contactos como bandeja de correo

Problema: "Otros recursos > Contactos" debe verse como bandeja de correo, no como tabla simple.

Correccion esperada:

- Crear vista tipo inbox: columna de mensajes, filtros por estado, busqueda, panel de lectura o modal.
- Estados: nuevo, leido, resuelto.
- Mostrar nombre, telefono, correo, asunto, fecha y mensaje completo.

Criterio de aceptacion:

- Se puede revisar y cambiar estado de contactos sin perder contexto.

## Inicio del panel despues de login

Problema: al abrir la app, la primera pantalla no debe ser dashboard. El dashboard debe vivir en KPIs.

Correccion esperada:

- Despues del login, mostrar una imagen o pantalla de bienvenida a pantalla completa.
- Esa imagen debe ser editable por usuario maestro.
- Por ahora usar una imagen default publicitaria de FISITAAP.
- Mover metricas, graficos y resumen numerico al modulo KPIs.

Criterio de aceptacion:

- El usuario entra a una pantalla limpia de bienvenida.
- KPIs conserva el dashboard y reportes resumidos.
- El maestro puede cambiar la imagen sin tocar codigo.

## Desktop Android offline equipos y cajas

### 1. Usuarios como select

Problema: en Desktop Android los usuarios no deben escribirse manualmente.

Correccion esperada:

- Mostrar un select o buscador de usuarios existentes del negocio enlazado.
- Solo incluir usuarios activos y autorizados para vender/cobrar segun permisos.
- Evitar duplicar usuarios locales si ya existen en la nube.

Criterio de aceptacion:

- El dueno elige el usuario desde una lista confiable.

### 2. Quitar boton caja local

Problema: el boton "caja local" no hace falta.

Correccion esperada:

- El modo offline debe activarse automaticamente cuando se pierde internet, dentro de las funciones permitidas offline.
- Mostrar un indicador claro de estado: conectado, sincronizando, sin internet, ventas pendientes.
- No obligar al usuario a entrar a un modo manual separado.

Criterio de aceptacion:

- Si se cae internet, Desktop Android sigue con las funciones offline permitidas sin boton especial.

### 3. Windows y cajas

Problema: el modulo Windows y cajas debe enfocarse en equipos, cajas y asignaciones, no en accesos locales antiguos.

Correccion esperada:

- Mantener registro de equipo/dispositivo.
- Crear y configurar cajas.
- Asignar caja a cajero o dispositivo.
- Mostrar solo equipos activos, ultima conexion/ultimo uso y caja asignada.
- El panel maestro debe poder ver esa misma informacion por empresa.
- Eliminar o esconder configuracion antigua de "acceso usuario local" si ya no aplica.

Criterio de aceptacion:

- El dueno entiende que equipo, caja y cajero son entidades separadas.
- Se puede ver que caja esta usando cada dispositivo.

## Venta rapida POS y cobro

### 1. Ventana de venta rapida

Problema: la venta rapida debe ser una pantalla operativa completa.

Correccion esperada:

- Agregar productos a una factura por buscador, codigo/SKU y botonera por categoria.
- Asignar cliente registrado o consumidor final.
- Crear cliente rapido si el permiso lo permite.
- Editar cantidad, precio permitido si aplica, extras/opcionales, nota y descuento.
- Eliminar lineas antes de cobrar.
- Boton cobrar visible.
- Boton iniciar turno o cerrar turno segun estado.
- Reimprimir ultimo recibo y buscar/reimprimir ventas anteriores.
- Mostrar estado de caja, usuario, sucursal, turno y dispositivo.
- Soportar efectivo, tarjeta, SINPE, credito autorizado, gift card y pago combinado.

Criterio de aceptacion:

- Un cajero puede vender sin salir de la pantalla principal.
- El cobro valida turno abierto, permisos y dispositivo autorizado.

### 2. Cierre de turno

Problema: al cerrar turno solo debe mostrarse contador de monedas y boton de cerrar.

Correccion esperada:

- En cierre de turno mostrar conteo de denominaciones, total contado y diferencia.
- Al cerrar, guardar detalle completo en modulo Turnos.
- Imprimir resumen de cierre en caja.
- Bloquear cierre si hay cuentas pendientes de cobrar, mesas cargadas o tickets abiertos asignados al turno/sucursal segun la regla operativa definida.

Criterio de aceptacion:

- El cajero no ve una pantalla compleja al cerrar.
- Administracion puede entrar luego al turno y ver detalle completo.

### 3. Entradas y salidas de dinero

Problema: se necesitan movimientos manuales de caja.

Correccion esperada:

- En ventas/caja agregar botones "Entrada de dinero" y "Salida de dinero".
- Pedir monto, motivo, referencia opcional y confirmacion.
- Registrar en turno y bitacora.
- Si se elige cuenta "Caja" en pagos de CxP o compras, debe restar al efectivo esperado de la caja activa del dispositivo.
- Esta funcion solo debe estar disponible en Desktop Android autorizado.

Criterio de aceptacion:

- El cierre de turno refleja ventas, entradas, salidas y pagos en efectivo aplicados desde caja.

### 4. Gaveta de dinero

Problema: se debe abrir gaveta al imprimir documentos que lo requieren.

Correccion esperada:

- Enviar senal de abrir gaveta al imprimir recibos, facturas o documentos configurados para caja.
- Debe depender del perfil de impresora/caja.
- No abrir gaveta para comandas de cocina o impresiones que no son de caja.

Criterio de aceptacion:

- La gaveta abre solo en documentos de cobro configurados.

## Mesas salones y disenador grafico

### 1. Runtime de mesas sin panel lateral

Problema: en la pantalla de mesas no debe haber espacio derecho permanente para manipular mesa. Las acciones deben abrir en modal al tocar la mesa.

Correccion esperada:

- La vista normal del salon debe ocupar la mayor cantidad de pantalla posible.
- Al tocar una mesa, abrir modal con acciones: abrir cuenta, ver cuenta, agregar productos, enviar comanda, pre-cuenta, cobrar, unir mesa, mover cuenta, cambiar cliente, notas y cerrar.
- No mostrar botones de editar distribucion o configurar salon en mesas ni venta rapida.
- La configuracion debe vivir solamente en el modulo "Salones y mesas".

Criterio de aceptacion:

- En tablet/movil se ve mas salon y menos panel administrativo.

### 2. Unir mesas

Problema: al entrar a una mesa debe existir boton para unir mesa o mesas.

Correccion esperada:

- Permitir unir mesas abiertas bajo reglas seguras: mismo tenant, sucursal, salon compatible y sin cobros parciales conflictivos.
- Registrar historial de union en bitacora.
- Actualizar estados visuales de las mesas unidas.
- Definir si el cobro queda como una cuenta unica o como cuentas relacionadas. Preferencia: mantener cuenta principal con referencias a mesas unidas para simplificar cobro.

Criterio de aceptacion:

- No se pierden productos, comandas, pagos parciales ni servicio.

### 3. Modulo salones y mesas

Problema: el modulo debe ser el area unica para configuracion y diseno.

Correccion esperada:

- Crear, editar, activar y desactivar salones.
- Crear sectores dentro del salon.
- Activar/desactivar servicio del 10 por ciento por sector.
- Crear mesa asignando numero/nombre, capacidad, forma y sector.
- Editar orden visual en vista basica.
- Editar plano grafico con paredes, puertas, ventanas, mesas, sillas, plantas, barras, separadores, sofas, texto y otros elementos necesarios.
- Guardar revision del plano para evitar que dos administradores se pisen cambios.

Criterio de aceptacion:

- El administrador puede disenar un salon completo desde un solo modulo.
- El cajero solo opera mesas, no configura layout.

## Extras opcionales combos promociones

### 1. Extras y opcionales individuales

Problema: los extras/opcionales deben crearse individualmente, no como grupos visibles para el usuario.

Correccion esperada:

- La interfaz debe permitir crear cada extra/opcional con nombre, tipo y precio si corresponde.
- Extras: nombre y precio.
- Opcionales: nombre y reglas si aplica, normalmente sin costo.
- Internamente se puede mantener una estructura de grupos si ya existe, pero la experiencia del dueno debe sentirse individual y simple.

Criterio de aceptacion:

- El dueno no debe escribir listas tipo "Queso | 500" para crear extras. Debe agregar elementos uno por uno con controles claros.

### 2. Tres formas de asignacion

Problema: extras y opcionales deben asignarse de tres maneras.

Correccion esperada:

- Desde el panel de extras/opcionales: buscar con select o buscador el producto al que se asignan.
- Desde el producto: pestana "Extras y opcionales" para elegirlos.
- Desde la categoria: asignar a toda la categoria.

Criterio de aceptacion:

- Si se asigna a categoria, los productos de esa categoria lo reciben.
- Si un producto se mueve de categoria, definir si hereda los extras de la nueva categoria o conserva asignaciones manuales. Codex debe documentar la regla elegida.

### 3. Combos

Problema: al crear combo debe existir boton para sumar productos.

Correccion esperada:

- Boton "Agregar producto al combo".
- Select/buscador de productos existentes.
- Campo cantidad por producto.
- Validar que no se agregue el mismo producto dos veces sin fusionarlo.
- Calcular costo estimado del combo segun componentes.

Criterio de aceptacion:

- Crear un combo no requiere editar una tabla grande de todos los productos.

### 4. Codigos de descuento

Problema: promociones necesita opcion de codigos de descuento.

Correccion esperada:

- Agregar cupon/codigo: codigo, tipo porcentaje o monto fijo, valor, fecha inicio/fin, limite de usos, minimo de compra, productos/categorias aplicables y estado activo.
- Validar codigo en catalogo web y POS si aplica.
- Registrar uso del codigo por pedido/venta para evitar doble uso.

Criterio de aceptacion:

- El descuento se calcula en servidor, no solo en frontend.

## Clientes credito fidelizacion CxC y CxP

### 1. Fidelizacion

Problema: se necesita manual claro y el modulo debe aparecer en bloque de clientes.

Correccion esperada:

- Mover o enlazar Fidelizacion dentro del bloque Clientes.
- Crear manual dentro del sistema: que es, como se activan reglas, como se acumulan puntos/premios, como se redimen y como se revisa historial.
- Agregar estados vacios y ejemplos.

Criterio de aceptacion:

- Un dueno nuevo puede configurar fidelizacion sin explicacion externa.

### 2. Credito de cliente

Problema: el credito se asigna desde busqueda de cliente y dentro del cliente hay pestanas.

Correccion esperada:

- En Clientes, buscar cliente.
- Al entrar al cliente mostrar pestanas: datos generales, credito, estado de cuenta, ventas/pedidos.
- En pestana credito activar/desactivar credito, limite, plazo, saldo actual, notas y bloqueo.
- En segmento Clientes > Credito mostrar solo clientes con credito activo o credito en curso.

Criterio de aceptacion:

- No se mezclan clientes sin credito en la vista de credito.

### 3. CxC y morosos

Problema: CxC debe permitir abonos y ver morosos.

Correccion esperada:

- CxC debe listar pendientes, parciales, vencidos y pagados.
- Agregar filtro "Morosos" basado en vencimiento y saldo mayor a cero.
- Aplicar abonos con metodo de pago, referencia y bitacora.
- Si el abono es efectivo y se hace desde caja autorizada, afecta turno/caja.

Criterio de aceptacion:

- El saldo del cliente y CxC quedan sincronizados.

### 4. CxP

Problema: en CxP se pueden hacer abonos a deudas.

Correccion esperada:

- Permitir abonos parciales o totales a cuentas por pagar.
- Si el pago sale de caja, restarlo de caja activa.
- Si sale de otra cuenta de dinero, registrarlo contra esa cuenta.

Criterio de aceptacion:

- Compra, CxP y cuenta de dinero quedan conciliadas.

## Compras XML inventario proveedores

### 1. Proveedor por cedula

Problema: al cargar XML el proveedor debe ligarse por cedula.

Correccion esperada:

- Extraer cedula del emisor del XML.
- Buscar proveedor por `tax_id`.
- Si existe, seleccionarlo automaticamente.
- Si no existe, preguntar si desea crearlo con los datos del XML.

Criterio de aceptacion:

- No se crean proveedores duplicados si la cedula ya existe.

### 2. Compra a credito y CxP

Problema: la compra debe poder marcarse a credito.

Correccion esperada:

- Agregar check "Compra a credito".
- Si se activa, mostrar vencimiento, monto abonado, cuenta de pago y observaciones.
- Crear CxP solo al aplicar compra.
- Si se guarda como borrador, no mover inventario ni CxP.

Criterio de aceptacion:

- El XML puede guardarse pendiente y luego aplicarse sin duplicar inventario.

### 3. Comparacion de precio

Problema: si el costo del XML cambia, debe avisar visualmente.

Correccion esperada:

- Si costo XML es mayor al costo actual, marcar celda en rojo.
- Si es menor, marcar en verde.
- Si es igual, sin alerta.
- Mostrar costo actual, costo XML y diferencia.

Criterio de aceptacion:

- El usuario detecta aumento o rebaja antes de aplicar inventario.

### 4. Enlace por SKU y productos nuevos

Problema: articulos XML se enlazan por SKU; si no calzan, el usuario debe poder crearlos o asignarlos.

Correccion esperada:

- Auto-mapear lineas por SKU.
- Si no hay match: mostrar boton "Crear producto" usando datos del XML y opcion "Asignar a existente".
- Validar categoria, impuesto, costo, precio sugerido, inventario inicial y codigo SKU.

Criterio de aceptacion:

- Ninguna linea queda aplicada sin producto valido.

## Pedidos cocina y express

### 1. Pantalla de pedidos tipo Kanban

Problema: la pantalla de pedidos debe usar modelo visual Kanban.

Correccion esperada:

- Columnas por estado: nuevo, aceptado, preparacion, listo, despacho/express, entregado, cancelado.
- Arrastrar o botones de cambio de estado segun permisos.
- Filtros por origen: catalogo web, mesa, venta rapida, express.
- Indicadores de tiempo, cliente, total, sucursal, metodo de entrega y pago.

Criterio de aceptacion:

- Cocina y administracion pueden leer prioridades rapidamente.

### 2. Pedidos desde catalogo

Problema: los pedidos de catalogo llegan a pedidos pero requieren boton "Recibir".

Correccion esperada:

- Pedido web entra como pendiente de recibir salvo configuracion contraria.
- Boton "Recibir" significa que ya se contacto o tramito con el cliente.
- Al recibir, imprimir comanda si aplica y crear cuenta abierta/pedido pendiente en caja.
- El pedido no debe duplicarse si se presiona dos veces.

Criterio de aceptacion:

- Pedido web aceptado aparece como cuenta pendiente de cobro o preparacion segun configuracion.

### 3. Configuracion de pedidos web

Problema: administracion debe elegir si pedidos web pasan directo a comanda o requieren aceptar.

Correccion esperada:

- Opcion en configuracion: "Pedidos web requieren aceptacion" o "Enviar directo a comanda".
- Si directo, imprimir/crear comanda y cuenta abierta automaticamente.
- Si estandar, esperar boton Recibir.

Criterio de aceptacion:

- La decision queda por negocio/sucursal y se respeta en pedidos nuevos.

### 4. Express

Problema: cuando un platillo esta listo deben aparecer acciones de express.

Correccion esperada:

- Al marcar listo, habilitar "Publicar express" y "Asignar express".
- Los pedidos publicados/asignados aparecen en lista de express pendientes.
- Historial de entregas muestra estado actualizado por caja o motorizado.

Criterio de aceptacion:

- El flujo de entrega se puede seguir desde listo hasta entregado.

## Administracion reportes impresion bitacora

### 1. Cedula de empresa

Problema: en administracion general falta cedula de la empresa.

Correccion esperada:

- Agregar campo cedula/juridica/fisica segun Costa Rica.
- Usar validacion flexible pero no permitir texto vacio si se marca como requerido para facturacion.
- Mostrarla donde corresponda: documentos, recibos o datos fiscales.

Criterio de aceptacion:

- La empresa puede guardar su identificacion legal.

### 2. Diseno web del negocio

Problema: el modulo de diseno web esta confuso.

Correccion esperada:

- Hacerlo mas grafico y sencillo.
- Separar por secciones: portada, nosotros, servicios/productos, catalogo/menu, contacto, colores, imagenes, botones.
- Agregar preview.
- Dar accesos al catalogo/menu desde menu principal y botones.

Criterio de aceptacion:

- Un dueno no tecnico puede editar la landing del negocio sin tocar campos confusos.

### 3. Impresion en administracion

Problema: el modulo de impresion necesita mejor aspecto visual.

Correccion esperada:

- Mostrar perfiles por punto de impresion: caja/recibo, cocina, barra, despacho, copia y zonas personalizadas.
- Mostrar estado de conexion Desktop/Android si existe.
- Agregar pruebas de impresion por perfil.
- Separar configuracion de gaveta.

Criterio de aceptacion:

- El dueno entiende que imprime cada area y puede probarlo.

### 4. Reportes

Problema: reportes deben dar data para todos los segmentos.

Correccion esperada:

- Revisar cobertura de reportes: productos, ventas, ventas por articulo, pedidos, mesas, turnos, caja, entradas/salidas, compras, inventario, proveedores, CxC, CxP, clientes, credito, fidelizacion, gift cards, entregas, usuarios, equipos, cajas, contactos y bitacora.
- Usar filtros por fecha, sucursal, usuario, estado y exportacion CSV.
- Evitar `DATE(columna)` en filtros grandes si hay indices disponibles; preferir rangos `>=` y `<`.

Criterio de aceptacion:

- Cada modulo operativo tiene al menos un reporte o exportacion util.

### 5. Auditoria a Bitacora

Problema: el modulo debe llamarse Bitacora y registrar movimientos front, no procesos backend internos.

Correccion esperada:

- Cambiar etiqueta visible de "Auditoria" a "Bitacora".
- Registrar acciones del usuario desde interfaz: crear, editar, eliminar/desactivar, cobrar, abonar, abrir/cerrar turno, imprimir, cambiar estado, aplicar compra, sincronizar venta local cuando represente accion de caja.
- No mostrar eventos tecnicos irrelevantes al dueno.

Criterio de aceptacion:

- La bitacora es legible para un dueno y sirve para control operativo.

## Seguridad y logica multi caja multi comanda

### Escenario requerido

El sistema puede tener varios sectores de comanda y varias cajas cobrando al mismo tiempo. Debe evitar duplicados, cobros cruzados, perdida de inventario y conflictos de mesas.

Correccion esperada:

- Cada comanda debe tener destino de impresion por categoria, zona o sector.
- Cada caja debe tener turno activo independiente por usuario/dispositivo/sucursal.
- El cobro debe bloquear el ticket con `FOR UPDATE` y validar revision antes de aplicar pago.
- Si otra caja cobro o modifico la cuenta, mostrar mensaje claro: "La cuenta cambio, recarga antes de cobrar".
- Los pagos parciales deben quedar trazables por ticket, venta, metodo, cajero y turno.
- No permitir cerrar turno si quedan tickets abiertos asociados a ese cajero/caja o reglas de sucursal definidas.
- No permitir cambiar mesa/sector/servicio si hay cuenta abierta que pueda quedar inconsistente.
- Reimpresion debe marcarse como copia o registrar evento en bitacora.

Criterio de aceptacion:

- Dos cajas no pueden cobrar dos veces la misma cuenta.
- Dos pantallas no pueden pisarse cambios sin aviso.
- Las comandas se imprimen en su sector correcto.

## Cierre de sesion

Problema: al cerrar sesion muchas veces carga mensaje `ok:false, error...`.

Correccion esperada:

- Revisar flujo de logout web, Desktop y Android.
- En Desktop Android, al cerrar sesion mostrar solo pantalla de inicio de sesion de la app, no la pagina publica de FISITAAP.
- Manejar respuestas JSON y HTML de forma consistente segun contexto.

Criterio de aceptacion:

- Cerrar sesion no muestra errores tecnicos al usuario.

## Pruebas obligatorias

### Roles

Probar minimo con:

- Maestro.
- Dueno de negocio.
- Manager.
- Cajero.
- Cocina.
- Repartidor.
- Usuario final.

### Flujos

1. Web oficial en desktop y movil.
2. Crear negocio demo y revisar que no use plantillas viejas.
3. Editar landing de negocio y entrar al catalogo/menu desde varios botones.
4. Crear extras, opcionales, asignarlos a producto y categoria.
5. Crear combo con buscador de productos.
6. Crear codigo de descuento y aplicarlo en pedido/venta.
7. Cargar XML con proveedor existente por cedula.
8. Cargar XML con proveedor nuevo y crear proveedor.
9. Cargar XML con producto por SKU, producto faltante, crear producto y asignar existente.
10. Guardar compra como borrador y confirmar que no mueve inventario.
11. Aplicar compra y confirmar inventario y CxP.
12. Abrir turno en Desktop Android.
13. Venta rapida con cliente, lineas editadas, descuento, cobro mixto y reimpresion.
14. Entrada y salida de dinero.
15. Cierre de turno con contador y bloqueo por cuentas abiertas.
16. Crear salon, sector con 10 por ciento activo, mesa y elementos graficos.
17. Abrir mesa, agregar productos, enviar comanda, unir mesas, precuenta y cobrar.
18. Pedido web en modo recibir manual.
19. Pedido web en modo comanda directa.
20. Platillo listo, publicar/asignar express y revisar historial.
21. CxC con moroso y abono.
22. CxP con abono desde caja y desde otra cuenta.
23. Reportes por cada modulo principal.
24. Bitacora con acciones de usuario.
25. Logout en web, Desktop y Android.

### Rendimiento

- Medir cantidad de consultas en catalogo con 1000 productos.
- Medir venta rapida con muchos productos y clientes.
- Medir pantalla de mesas con muchos salones, sectores, objetos y mesas.
- Medir reportes con rangos largos.
- Evitar que FISI-CHAT bloquee la sesion mientras espera IA.

## Notas tecnicas sobre lo que ya existe parcialmente

En el codigo actual hay piezas que parecen existir y deben reutilizarse o ajustarse, no duplicarse:

- Salones, sectores, objetos graficos, mesas y revision de plano.
- Servicio por sector y mesa.
- Cobro con revision de ticket, pagos parciales, pago combinado y credito.
- Turnos y cierres.
- Compras XML con lectura basica y mapeo por SKU.
- CxC y CxP con abonos.
- Impresoras por puntos.
- Desktop offline con equipos, snapshots y sincronizacion.
- Reportes ampliados.

Codex debe confirmar cada punto en el codigo antes de modificarlo. Si ya existe, mejorar UX, permisos, ubicacion o criterio de aceptacion. Si no existe, implementarlo de forma integrada con las tablas y patrones actuales.

## Resultado esperado

El resultado no debe ser solo visual. Debe dejar FISITAAP mas claro para vender, mas seguro para operar con varias cajas, mas usable para restaurante/tienda/servicios y mas consistente para el dueno del negocio.

