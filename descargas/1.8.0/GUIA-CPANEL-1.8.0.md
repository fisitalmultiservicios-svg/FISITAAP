# Instalar FISITAAP web 1.8.0-R22 y Windows 1.8.3

Actualización acumulativa sobre R21. Conserva negocios, usuarios, ventas, cuentas de dinero, productos, imágenes y configuración. No importes otra base SQL, no cambies `config.php` ni la clave de la aplicación.

## R22: Venta rápida en un marco fijo, mesas y carrito

1. Guarda un respaldo completo de archivos y base de datos fuera de `public_html`. Sincroniza las ventas pendientes de las aplicaciones instaladas y conserva sus respaldos locales.
2. Extrae **FISITAAP-1.8.0-CPANEL-R22.zip** en la raíz real del dominio, junto al `index.php` principal. Con sesión maestra abre `/actualizador-180/index.php`, verifica **R22** e instala. Conserva `releases/1.8.0-r22`, `config.php`, `uploads` y las versiones anteriores. Retira el actualizador al terminar la verificación.
3. En cada Windows instala **FISITAAP-Escritorio-1.8.3-Windows-x64.exe** sobre la instalación actual. Conserva la carpeta de datos del usuario; no desinstales ni borres ventas o accesos locales. Windows 1.8.3 es necesario para el nuevo control persistente de pantalla completa y el encuadre de Caja local. La web conectada recibe su diseño desde el ZIP.
4. **Venta rápida:** título, caja y accesos usan una cabecera compacta. La factura ocupa el panel izquierdo más grande; productos, búsqueda y categorías están a la derecha. Total y Cobrar permanecen en el marco visible, sin desplazar la página. Las listas largas de la cuenta y de productos se recorren dentro de sus paneles. Las categorías ocupan una fila horizontal. Agregar otro producto conserva búsqueda y posición de ambas listas.
5. **Celular:** Venta rápida conserva Facturación / Productos para aprovechar el ancho sin reducir todo a texto diminuto. Al atender una mesa en celular se mantiene la vista continua de productos y cuenta con accesos para saltar entre ambas. En desktop, la cuenta de la mesa usa el mismo marco fijo de factura y carga rápida, con sus botones de comanda y seguimiento. El plano gráfico también se encuadra en la altura disponible, mantiene sus proporciones y conserva los controles para acercar, alejar y ajustar.
6. **Cobrar:** la cabecera, Solo cobrar, Cobrar e imprimir y la salida elegida permanecen en la ventana. Solo se desplazan los datos del pago cuando no caben. Se conservan validación, permisos, caja/turno propios, comprobantes y protección contra cobros repetidos. La impresión física requiere la impresora configurada y su equipo disponible.
7. **Pantalla completa en Windows 1.8.3:** actívala con el botón ⛶ del sistema, el de la barra del programa o Vista → Pantalla completa / F11. Se conserva al cargar productos, cobrar, navegar entre Venta rápida y Mesas, cambiar entre sistema web y caja local, recargar y volver a abrir el programa. Se desactiva con el mismo control o F11. El programa guarda solo esa preferencia de pantalla en `pantalla.json`, sin cambiar la conexión del negocio.
8. **Pantalla completa en navegador:** usa **F11** para conservarla mientras navegas entre páginas. El botón dentro de la web usa la API del navegador; este puede salir de ese modo al navegar y exige una acción del usuario para volver a entrar. No se fuerzan permisos del navegador.
9. **Caja local Windows:** cliente, factura, búsqueda, categorías, total y Cobrar aprovechan la ventana; productos y líneas se desplazan por separado. Historial y herramientas de respaldos se abren en una ventana aparte con Volver a la venta. No se cambia el motor de cobro ni se borran operaciones pendientes.
10. **Carrito del catálogo:** solo al elegir **Delivery** aparecen dirección, direcciones guardadas del teléfono, zona y ubicación de Maps. En **Recoger en el negocio** y **Envío coordinado**, el bloque se oculta, se deshabilita y no se envía. Si vuelves a Delivery se conservan los datos que estabas llenando. Una consulta de direcciones que termine tarde no lo vuelve a mostrar en retiro. El servidor conserva las validaciones de dirección/zona para Delivery y descarta datos de entrega antiguos en otras opciones, sin generar otro domicilio ni cobrar una zona.

No se ha instalado automáticamente el hosting. Se verificaron pantallas y operaciones con datos ficticios, puente nativo simulado y motor local Node; no se probó un monitor Windows real ni una impresora física. El APK publicado permanece en 1.8.0: usa la web actualizada con conexión; no se compiló un APK nuevo. La página comercial de presentación de FISITAAP conserva su diseño.

## Historial de entregas anteriores

# Instalar FISITAAP 1.8.0-R21 y actualizar cada negocio

Esta entrega actualiza tu instalación existente y conserva negocios, usuarios, productos, ventas, imágenes y configuración. No importes otra base SQL ni cambies config.php.

R11 reúne el slider de tres imágenes con tiempo configurable, cantidades enteras por unidad y fracciones a granel, menús desplegables de paneles y tiendas en todos los tamaños y ajuste de Venta rápida/Mesas con zoom. Simplifica el checkout y abre WhatsApp antes de consultar el PIN. Agrega acceso de clientes por teléfono y mantiene las mejoras de imágenes, Excel, opcionales y alta de negocios de R10.

Esta entrega actualiza la web a **R21**. Conserva Windows **1.8.2** y APK **1.8.0**: estas correcciones del sistema conectado no necesitan reinstalarlos. No cambies app_key ni importes otra base SQL. No se ha generado un APK nuevo.

## R21: precio del combo, comanda y cancelación desktop

1. Guarda respaldo de archivos y base de datos fuera de `public_html`.
2. Extrae **FISITAAP-1.8.0-CPANEL-R21.zip** en la raíz real del dominio, junto al `index.php` principal.
3. Con sesión maestra abre `/actualizador-180/index.php`, verifica **R21** e instala. Conserva `releases/1.8.0-r21`, `config.php` y `uploads`; retira el actualizador al terminar.
4. Prueba una venta nueva con el combo: el precio normal de ₡5.000 ya no queda anulado por un campo promocional sin usar guardado como cero. Crear en Combos deja ese campo vacío. Las tarjetas y el cálculo usan las mismas reglas, incluidos los precios asignados a la sucursal. Las promociones reales, los extras y el IVA se conservan; una promoción explícita gratuita se muestra como tal y sigue siendo gratuita.
5. Las líneas guardadas antes de actualizar conservan su precio. Para una línea errónea en ₡0, quítala y agrégala de nuevo si aún no se envió; si necesitas cancelar la cuenta, usa **Cancelar**, escribe el motivo y confirma con un usuario autorizado. No se recalculan ventas cobradas ni pedidos históricos automáticamente.
6. **Enviar comanda** guarda el trabajo en la cola configurada y regresa al pedido. Ya no lo procesa además como si fuera un recibo, evitando el aviso falso «No se recibió un comprobante». Repetir sin artículos nuevos no crea una comanda nueva. **Precuenta** sigue enviando su documento estructurado a la impresora nativa.
7. **Cancelar** abre una ventana con un campo visible de **Justificación de la cancelación**, mínimo 8 caracteres, y botones Volver / Confirmar cancelación. Funciona en la aplicación instalada y en el navegador, sin depender de `prompt`/`confirm` nativos. El motivo queda en la bitácora. Los permisos y la protección de cuentas cobradas siguen siendo obligatorios; las existencias se restituyen una sola vez.
8. Si utilizarás la caja sin internet, sincroniza con el sistema actualizado antes de desconectarte para renovar el catálogo. Las ventas locales pendientes conservan sus precios y referencias; no borres los datos locales ni repitas un cobro ya registrado.

Esta entrega conserva los botones Solo cobrar / Cobrar e imprimir, PDF carta/recibo, la impresora configurada y los turnos propios por usuario de R20. La página comercial de presentación no cambia. No se instaló automáticamente el hosting ni se hicieron impresiones físicas durante QA.

### Historial R20

## R20: cobro, comprobantes y cajas web

1. Guarda un respaldo completo de archivos y base de datos fuera de `public_html`.
2. Extrae **FISITAAP-1.8.0-CPANEL-R20.zip** en la raíz real del dominio, junto al `index.php` principal.
3. Inicia sesión maestra, abre `/actualizador-180/index.php`, verifica R20 e instala los archivos. Conserva `releases/1.8.0-r20`, `config.php` y `uploads`. Retira el actualizador al terminar.
4. Para imprimir carta y usar las opciones del cobro local en Windows, instala **FISITAAP-Escritorio-1.8.2-Windows-x64.exe** sobre la instalación actual. Primero sincroniza las ventas pendientes y guarda un respaldo local. No desinstales ni borres datos de la aplicación.
5. En **Administración → Generales → Dónde se permite cobrar**, elige solo las aplicaciones instaladas o también navegador web. El valor inicial conserva solo aplicaciones instaladas. El permiso del rol sigue siendo obligatorio.
6. Si habilitas web, cada usuario pulsa **Abrir turno** y registra su fondo inicial. Se asigna la primera caja positiva libre del negocio, diferente de las existentes. Otra persona debe abrir su propio turno; mientras tanto solo atiende. El mismo usuario conserva su caja/fondos en turnos posteriores y pestañas distintas. Solo el responsable cierra su turno. Cierra los turnos web antes de desactivar el cobro web.
7. Al cobrar hay dos botones: **Solo cobrar** registra sin imprimir recibo; **Cobrar e imprimir** usa la salida seleccionada justo debajo: **Hoja carta · PDF**, **Tipo recibo · PDF** o **Impresora configurada**. En la web, F8 confirma Solo cobrar. La comanda conserva sus reglas de aceptación/envío; no es el recibo de cobro.
8. En **Administración → Impresión → Caja / recibo**, configura equipo, conexión e impresora. Papel ofrece 58 mm, 80 mm y **Hoja carta**. Carta física usa el controlador de Windows 1.8.2 y tamaño Letter; no se envían bytes ESC/POS a una impresora carta. Si cobras desde web o Android, asigna el Windows que tiene la impresora. Ese equipo debe estar abierto y conectado para procesar la cola. El sistema no cambia el destino de un trabajo ya guardado.
9. Los PDF de ventas conectadas se generan en el servidor sin depender de una impresora. La descarga comienza tras registrar la venta; queda un botón **Descargar PDF** por si el navegador la bloquea. Carta mide 612 × 792 puntos, y recibo respeta 58/80 mm (80 mm si el perfil es carta). Importes mostrados como CRC en PDF para compatibilidad con fuentes estándar. Sin gaveta ni trabajos físicos. Se conserva la salida elegida y las descargas no vuelven a cobrar.
10. En Windows local 1.8.2 también hay Solo cobrar / Cobrar e imprimir y la misma selección. Los PDF locales se guardan en el equipo: menú **Imprimir → Abrir comprobantes PDF**. Una confirmación repetida tras perder conexión conserva la selección y el identificador de la venta.

**Compatibilidad Android:** la web conectada muestra los nuevos controles y permite enviar el recibo a un Windows configurado. El APK 1.8.0 existente mantiene su pantalla local anterior; la nueva selección local y el PDF carta nativo quedaron preparados en código para la próxima compilación. No se compiló ni firmó un APK nuevo porque este entorno no dispone del SDK ni de la clave de firma de la aplicación. En un WebView que no descargue PDF, abre la web de la tienda en el navegador para descargarlo. No se modificó la firma existente.

Se probaron HTTP, turnos simultáneos, autoridad por usuario/tienda, reintentos, fondos separados, PDFs y pantallas adaptables. La impresión en una impresora física y la instalación en Windows/Android reales se verifican después de instalar; las pruebas usaron un negocio ficticio. No se instaló nada en cPanel automáticamente.

### Historial R19 (comportamiento de navegador ampliado por R20)

## R19: atención, usuarios y mesas

1. Guarda un respaldo completo de archivos y base de datos fuera de `public_html`.
2. Sube **FISITAAP-1.8.0-CPANEL-R19.zip** a la raíz real del dominio, normalmente `/home/tu_usuario/public_html`, y extráelo allí. La carpeta `actualizador-180` debe quedar dentro de `public_html`, junto al `index.php` que sirve el dominio.
3. En el navegador con sesión maestra abre `https://fisitaap.com/actualizador-180/index.php`. Comprueba **R19**, marca que guardaste respaldo y pulsa **Instalar archivos**. No importes SQL ni reemplaces `config.php`.
4. La aplicación usará `releases/1.8.0-r19`. Conserva esa carpeta. Los negocios anteriores que tengan pendiente activar 1.8.0 siguen haciéndolo desde Administración → Actualizar sistema; los nuevos empiezan actualizados.
5. Prueba Venta rápida, mesas, Caja 0/numerada, apertura/cierre con su cajero, fondos, vendedor, avisos y Academiaap. Retira el actualizador después de verificar. Publicar el ZIP en GitHub no instala el hosting.


### Cambios de uso en R19

- **Acciones visibles:** Nota, Descuento, Cancelar, Código, Guardar y Comanda se muestran como botones pequeños en una barra superior que permanece visible al desplazarte. Ya no existe Más acciones ni un menú que se corte en celular. Solo aparecen las funciones del tipo de negocio y de tu rol.
- **Búsqueda:** Venta rápida devuelve el foco a Buscar producto para la siguiente carga. No quita el foco al escribir cantidades, completar un diálogo o usar FISIChat. Conserva el filtro y la posición al agregar productos.
- **Navegador = Caja 0:** permite abrir y atender cuentas y enviar comandas; no cobra ni abre turnos de dinero. No necesita una caja física adicional. Las cuentas nuevas capturadas en el navegador no bloquean el cierre de un turno físico sin relación con ellas. Al cobrarlas, la caja numerada registra el cobro en su propio turno.
- **Mesas:** al tocar una mesa entras a productos, cuenta, comanda, precuenta y seguimiento. En celular se usa el desplazamiento normal de una misma página, con categorías en una fila y botones grandes para agregar. Enviar comanda conserva la cuenta abierta y permite agregar más consumos sin reenviar los anteriores.
- **FISIChat:** abre por encima de barras y menús. La conversación se desplaza y el cuadro para escribir permanece visible, también con teclado y pantalla horizontal. Se conservan las funciones y límites de cada asistente.
- **Usuarios:** abre primero la lista de usuarios existentes. Pulsa **Nuevo usuario** para crear o **Editar** en un registro. Selecciona un empleado ya registrado y define nombre rápido, correo único, contraseña, perfil y estado. No crea un segundo empleado. Cambiar el expediente no modifica contraseña ni nombre de acceso.
- **Control de personal:** registra el empleado antes de crear su acceso; no pide contraseña ni rol. Un empleado puede existir sin usuario. Conserva los expedientes, usuarios, claves y relaciones anteriores. Un acceso anterior sin expediente se muestra para vincularlo, sin borrarlo. El propietario conserva su acceso inicial y puede asociar un expediente para marcar.
- **Sin Personal avanzado:** Usuarios ofrece un registro básico de empleados para cumplir el paso anterior. Horarios, marcas, vacaciones y cálculos siguen dependiendo del módulo. Editar el registro básico no borra salarios ni saldos laborales anteriores.
- **Listas del negocio:** Marcar y los selectores usan los usuarios activos de la tienda. El acceso conectado en Windows/Android consulta al negocio autorizado y reemplaza la lista antigua de prueba, sin crear otra contraseña de escritorio. Mostrar un usuario no le concede permiso de cobrar. Sin conexión se conserva el último acceso autorizado; sincroniza antes de operar offline con cambios de usuarios.
- **Configurador y Academiaap:** ahora separan Empleado y Usuario. El avance pausado mantiene el paso equivalente al agregar Usuarios después de Control de personal.

### Venta rápida, mesas y turnos

- La factura ocupa el panel izquierdo más grande y la carga rápida el derecho. Venta rápida conserva **Facturación / Productos** en celular. Al atender una mesa, ambos sectores están en una sola página y los botones **Productos / Cuenta** llevan al sector sin ocultar el otro. Se conservan cliente, totales y botoneras; con poca altura o teclado abierto puedes desplazarte sin perderlos. El zoom y pantalla completa siguen disponibles.
- **Caja y turno** aparece en Venta rápida y Mesas. **Abrir turno** abre el fondo inicial sin abandonar la venta. **Cerrar turno** abre directamente el conteo. No se muestra Ver turnos en Venta rápida. Solo puede cerrar quien abrió ese turno; ni otro cajero ni el dueño lo cierran en su lugar.
- Al tocar una mesa normal se entra a su cuenta para cargar productos. **Volver a mesas** regresa al salón. En barra o si hay varias cuentas, primero se elige la cuenta. El plano conserva sus proporciones y permite zoom.
- Al retirar el último producto de una cuenta sin cobros, la cuenta se cancela y libera su mesa. La devolución de inventario ocurre una sola vez. **Un total de ₡0 por descuento del 100 % no cancela una cuenta que conserva productos.** Los pedidos web originales y cuentas con pagos no se modifican por esta regla.
- En **Equipos y cajas** asigna una caja por Windows/Android y un número: **0** solo abre y atiende cuentas; **1 o más** permite cobrar. No confundas el nombre libre con el número. Las cajas antiguas conservan la capacidad de cobro y reciben un número positivo. Cambiar un equipo/caja con turno abierto o principal offline exige sincronizar y cerrar/liberar antes.
- Los cobros se realizan únicamente en la caja instalada autorizada, con el usuario que abrió su turno. Caja 0 no abre turnos de dinero, no cobra y no puede ser principal de venta sin internet.
- Cada caja numerada tiene fondos propios. **Efectivo** se concilia en su turno; **Tarjeta, SINPE y Banco** tienen cuentas contables separadas por caja. Los cobros nuevos con Tarjeta/SINPE ingresan una vez en su cuenta. Estos ingresos no se suman al efectivo contado.
- Las cuentas y saldos anteriores se conservan como fondos administrativos generales: no se redistribuye dinero automáticamente. Desde **Cuentas de dinero** puedes crear otras cuentas y asignarlas a una caja. Una caja instalada solo ofrece sus fondos; el dueño desde el navegador administra las cuentas generales. Las cuentas desactivadas se conservan.
- En **Generales → Vendedor de la venta**, activa o desactiva **Preguntar vendedor al cobrar**. Si está activo, se pide elegir un colaborador de la tienda. El reporte de ventas distingue **cajero** y **vendedor**. El vendedor nunca cambia quién responde por el turno.
- Esta entrega actualiza el sistema web usado dentro de las aplicaciones instaladas existentes. No reconstruye ni firma instaladores. La venta offline anterior conserva compatibilidad y atribuye vendedor al operador original; la nueva pregunta de vendedor funciona en el cobro conectado. Marcas y avisos requieren conexión.

### Avisos de FISIChat para el propietario

El chat del dueño muestra cumpleaños hoy o en los próximos 7 días, vacaciones asignadas próximas, saldo estimado de vacaciones para revisar, contratos con salida próxima, existencias bajas y cuentas por cobrar vencidas, según los módulos activos. **Revisar** abre el registro; **Visto** oculta ese aviso para esa cuenta. También puedes pedir **Ver avisos de mi negocio** sin depender de la IA. Los datos de personal son solo para el dueño de esa tienda; no se muestran en el chat público ni al comprador y no se envían mensajes al empleado.

### Acceso y clientes

- Un cliente nuevo creado desde pedido, panel o caja usa su **celular de 8 dígitos como usuario y contraseña inicial**. Entra desde **Iniciar sesión de cliente** en el catálogo de su tienda. Si el mismo teléfono tiene cuentas en varias tiendas, usa siempre ese enlace de la tienda. Una contraseña que el cliente cambió se conserva.
- Se recupera el acceso inicial de registros automáticos antiguos que todavía no se usaron; no se cambian cuentas con acceso utilizado o recuperación completada.
- En **Clientes → Buscar → ficha → General → Eliminar cliente**, indica el motivo. Se retira del listado y del acceso de esa tienda conservando documentos e historial. Se bloquea si tiene deuda, ventas o pedidos abiertos. Requiere permiso de eliminación.
- Cada dirección nueva se asigna a una zona activa de esa tienda y puede guardar Google Maps. En checkout se pide dirección y zona; al elegir una dirección guardada se carga también su zona. Las direcciones antiguas sin zona requieren escogerla al usarlas.
- Los cajeros pueden ingresar por su correo o por el nombre rápido asignado en Usuarios, después de registrar al empleado en Control de personal. Los correos son únicos en toda FISITAAP; el nombre rápido es obligatorio al crear o editar un colaborador y es único dentro de su empresa. En el equipo instalado se elige en un desplegable; el acceso público con un nombre repetido entre negocios requiere el correo para evitar ambigüedad. Las contraseñas nuevas admiten 4 caracteres o más, incluidos cuatro dígitos. Las cuentas antiguas siguen conservando sus contraseñas y roles.

### Catálogo, menú y pedidos

- **Catálogo:** no muestra cocina, monitor de preparación, mesas, comanda ni sus destinos de impresión. Aceptar un pedido no genera comanda. Usa Pedidos para aceptarlo y registrar la entrega.
- **Menú:** Pedidos es un listado con acciones y bitácora. Aceptar imprime la comanda una sola vez. En **Monitor de preparación** pulsa Preparar y Preparado. La asignación de Express se realiza desde Pedidos.
- Sin **Entregas** (Express + Red de motorizados), se ocultan afiliados, invitaciones, seguimiento y asignación de motos. El checkout sigue admitiendo domicilio: la tienda coordina y confirma la entrega directamente. No se crean tareas de moto.
- Con Entregas, la tienda asigna el pedido preparado directamente a una moto o lo libera a afiliados. En Menú el monitor prepara; Pedidos asigna.
- Diseño web → Slider del catálogo permite **color de títulos y textos**, además de tres imágenes y tiempo configurable. El menú del comprador muestra acciones actuales de compra, sin los enlaces antiguos Inicio/Blog/Contacto.
- Los productos sin fotografía muestran un ícono neutro sobre blanco con «Solicita la imagen». Venta rápida distingue categorías, búsqueda y filtros sin resultados, y no muestra el acceso de cuentas de mesas en Catálogo.
- Al ingresar al sistema se muestra siempre **Bienvenida**. Resumen sigue disponible desde el menú.

### Academiaap y configurador

**Academiaap** reemplaza el acceso «Manual de uso». Tiene cursos por rol, lecciones actualizadas, progreso guardado por cuenta y botones **Ir a practicar**. Al abrir una pantalla desde una lección aparece **Volver al curso**.

Durante los primeros tres días desde el primer uso de la bienvenida aparece una invitación pequeña **CONFIGUREMOS FISITAAP JUNTOS**. El dueño puede seguir datos generales, sucursales, roles, empleados, usuarios, categorías, opcionales y extras, productos, diseño y equipo. Guarda en cada pantalla y vuelve al configurador para continuar. **Pausar y salir** conserva el paso. El botón **Configurador FISITAAP** del menú permite retomarlo después; los pasos ajenos al plan pueden omitirse.

### Control de personal

Administración → **Control de personal**. El módulo se controla en los planes del maestro. El dueño puede administrarlo; otros colaboradores necesitan permisos explícitos de Personal. No se habilita la consulta de expedientes a un cajero por defecto.

- **Expediente:** registra a la persona sin crear credenciales. El acceso se crea después en Usuarios; identificación, nombre, nacimiento, nacionalidad, estado civil, oficio, domicilio, contacto y emergencia; puesto, ingreso/salida, contrato, lugar/fecha, condiciones, salario bruto mensual equivalente y periodicidad; CCSS, referencia INS, cuenta y dependientes. Conserva los roles personalizados existentes. La ficha no sustituye un contrato firmado.
- **Horario:** días, entrada/salida y pausa remunerada o no. Valida jornadas ordinarias diurna/nocturna/mixta, descanso y días del expediente. Detecta por el horario los tramos nocturnos; jornadas excepcionales o regímenes especiales requieren revisión. Los avisos no imponen descuentos ni sanciones.
- **Marcas:** el botón superior **Marcar** aparece solo dentro de Windows/Android instalado con la interfaz nativa. El servidor exige equipo vinculado y sesión autorizada, y luego usuario/contraseña de la persona que marca. Consulta su último evento y propone Entrada/Pausa/Regreso/Salida. Guarda hora del servidor, sucursal y equipo. Repetir la misma solicitud no duplica; secuencias inválidas y marcas casi simultáneas se rechazan. Una jornada abierta más de 24 horas requiere corrección. **Necesita internet**; no inventa horas ni sincroniza marcas offline.
- **Bitácora:** conserva marcas originales. Una corrección de hora o marca omitida requiere justificación y registra al responsable. Valida el orden de eventos. Las horas mostradas son tramos cerrados sin pausas marcadas; el cálculo de horas extra o pausas pagadas requiere revisión.
- **Vacaciones:** saldo inicial con fecha y motivo de ajuste, devengo estimado, asignación por días del horario y cancelación justificada. Evita superposiciones y reservas que excedan saldo; excluye feriados nacionales estándar. Verifica traslados extraordinarios o regímenes especiales.
- **Salarios brutos:** registra periodos reales, salario ordinario y extraordinario. No admite periodos superpuestos ni futuros. Son bases de cálculo y no transferencias de dinero.
- **Cálculos:** aguinaldo proporcional, vacaciones y salida/despido con resultado guardado, fecha, base e hipótesis. El aguinaldo usa salarios del 1 de diciembre al 30 de noviembre / 12; cesantía y preaviso dependen de antigüedad y causa de salida. El cálculo estándar de salida admite contrato indefinido; plazo/obra exige revisión específica. Las vacaciones distinguen días laborables y días remunerados según la periodicidad y descanso; en mensual/quincenal dos semanas incluyen 14 días remunerados. Se puede indicar base y días verificados.

**Límite de los cálculos laborales:** son estimaciones para revisión de planilla, no finiquitos ni pagos automáticos. Las referencias usadas son Código de Trabajo (arts. 28–29, 136–139, 152–157) y Ley 2412. El acceso a SCIJ/MTSS fue bloqueado por la red de este entorno; no se pudo comprobar la vigencia del texto oficial. Verifica régimen, salarios, interrupciones, feriados y condiciones contractuales con MTSS o quien lleve la planilla antes de liquidar. No se implementan deducciones de planilla, cálculo fiscal, CCSS o mínimos salariales automáticos.

La presentación comercial de FISITAAP conserva su diseño. Promociones y Fidelización ordenan sus formularios, accesos y ayuda. Se mantiene la conexión de IA y las funciones R16.

## R16: FISIChat operativo, soporte y catálogo compacto

### Asistente del dueño: solo su tienda

FISIChat puede consultar información del negocio, aportar ideas y preparar cambios reales. Usa la conexión de IA existente. No cambia de tienda ni administra FISITAAP por cuenta del dueño.

Ejemplos:

- «Crea la categoría Bebidas frías».
- «Crea un café latte de ₡1.800 en Bebidas, con 20 unidades iniciales».
- «Cambia el precio de este producto a ₡2.000».
- «Crea un cupón BIENVENIDA de 10 % hasta el 31 de diciembre».
- «Crea el grupo Tallas con S, M y L y asígnalo a esta camiseta».
- «Mejora el texto de bienvenida de mi web y prepáralo para guardar».
- «Sugiere ideas para vender más con mis productos».

Si faltan datos, pregunta sólo los necesarios. Para modificar algo muestra una tarjeta con el cambio, los valores anteriores/nuevos y la tienda; la existencia inicial indica su sucursal. **Confirmar cambio** guarda; **Descartar** no aplica la propuesta. También puedes escribir «confirmo» mientras esa propuesta está pendiente. Una idea o una consulta no ejecuta escrituras. La tarjeta vence a los 15 minutos. Si el registro fue cambiado desde otra pantalla, se necesita revisar una propuesta nueva. Reintentar una confirmación ya procesada no crea otro registro.

Puede crear o editar productos, categorías, cupones, zonas y proveedores; registrar clientes por nombre/teléfono; crear grupos de opcionales y asignarlos; actualizar contacto, horario, colores y textos permitidos de su web. La disponibilidad depende de los módulos/permisos. Los grupos existentes se editan en Opcionales para conservar las identidades históricas de sus opciones. El chat no cobra, abre/cierra cajas, anula pedidos, cambia claves/permisos, activa planes ni modifica código. Para esas operaciones dirige a sus pantallas autorizadas.

**Crear producto** ofrece además un formulario dentro del chat: nombre, categoría, precio y descripción. Sigue mostrando una revisión antes de guardar y funciona aunque la IA no responda. La conversación natural con consultas/herramientas necesita la conexión de IA. Las creaciones y ediciones quedan en la bitácora. Los accesos antiguos de chat no pueden ejecutar cambios saltándose la revisión nueva.

### Web principal: soporte y tickets

El FISIChat de fisitaap.com mantiene el perfil comercial/técnico de FISITAAP, sin consultar datos privados de tiendas ni tener sus herramientas de modificación. Primero orienta sobre el problema. Si el usuario dice «sigue igual», «no funcionó» o pide un ticket, solicita:

- Nombre de la persona y de la tienda.
- Enlace público de la tienda, opcional y sin parámetros de acceso.
- Teléfono o correo para contactar.
- Asunto, descripción del problema y lo que intentó.

Al pulsar **Crear ticket** guarda el caso y devuelve una referencia **SOP-…**. Incluye los últimos mensajes pertinentes de la conversación pública. No pide contraseñas ni claves; omite patrones comunes de secretos en el caso guardado. Repetir el envío con la misma referencia no duplica el ticket. Hay límites de solicitudes para evitar envíos masivos. No se envían correos ni WhatsApp automáticamente.

Entra al maestro → **Administración → Tickets de soporte**. Puedes filtrar, abrir el detalle, ver contacto/tienda/conversación, cambiar entre Nuevo/En atención/Resuelto/Cerrado y añadir notas internas con su fecha. La lista recibe las actualizaciones del sistema. El asistente del maestro también consulta el resumen de tickets pendientes.

Si la solicitud proviene de una sesión dueña válida, la tienda se asocia desde su cuenta, sin aceptar otro negocio indicado en el formulario. Los datos de solicitudes anónimas se muestran como **declarados por el solicitante**, y deben verificarse antes de intervenir una cuenta.

### Productos más pequeños en el catálogo

Tarjetas más compactas: cinco columnas en pantallas amplias, cuatro en computadora mediana, tres en tablet y dos en celular. Las imágenes bajan a 155 px en pantallas amplias y 120 px en celular; se reducen espacios y títulos manteniendo el precio, cantidades y botones utilizables. Se conserva el color elegido por la tienda, sus fotografías, opciones y carrito. El cambio se limita al catálogo de las tiendas.

R16 prepara tres tablas nuevas de propuestas/tickets al ejecutar el instalador. No requiere importar SQL, volver a configurar la IA ni reinstalar Windows 1.8.1/Android 1.8.0. Conserva los datos y el diseño integrado de R15.

## Diseño R15: estructura del HTML aprobado

R15 corrige la diferencia visual de R14. Integra las plantillas y los estilos del HTML **Línea + FISITAAP** en la aplicación PHP real: cabecera, ruta de navegación, tarjetas, resumen, facturación y detalle de mesas. La información procede del negocio y respeta sus permisos; los valores del prototipo no se copian al sitio. **La web oficial de presentación conserva su diseño y navegación.**

- **Resumen:** cuatro indicadores, gráfico de ventas, pedidos que necesitan atención y accesos operativos. En el menú están separados **Resumen** y **Bienvenida**, donde permanece la imagen configurada por el maestro. En el maestro se muestran sus propios negocios, suscripciones, contactos y equipos.
- **Venta rápida:** factura amplia a la izquierda (60 %) y carga rápida a la derecha (40 %), como en el HTML. Las líneas muestran producto, cantidad, precio e importe; el total y el cobro quedan al pie. El nombre del producto abre sus opciones, precio autorizado y notas. El selector de cliente envía el formulario real. **Más acciones (⋯)** conserva Express, Mesas, cuentas pendientes e historial; **Más acciones** al pie conserva descuentos, notas y las demás operaciones. La pantalla vacía crea una cuenta al agregar el primer producto.
- **Celular:** pestañas **Facturación** y **Productos**. Las líneas y productos se desplazan dentro de su área; el total y **Cobrar** permanecen visibles, también con poco alto. El aviso de pedidos web sigue disponible. Se conserva el zoom del navegador.
- **Tableta:** dos columnas cuando el ancho lo permite; hasta 650 px se usan las pestañas. Las ventanas y formularios se limitan al ancho disponible.
- **Mesas:** el plano real está a la izquierda y el detalle de la mesa seleccionada a la derecha; en celular se apilan. No se reemplaza el salón por las mesas del ejemplo. Se conservan coordenadas, formas, sectores, objetos, cuentas y vista básica. +/− y 100 % controlan el plano. La selección también funciona con teclado. El detalle lateral sigue recibiendo actualizaciones automáticas cada dos segundos; no es una ventana que bloquee el refresco.
- **Catálogo:** banner con texto e imagen, categorías compactas y tarjetas de producto con el contenido guardado por la tienda. Se conserva su color primario, slider, carrito y acceso de clientes.
- **FISIChat:** el panel del negocio y el maestro usan la ventana de chat moderna con su perfil correspondiente. Se mantienen los límites de temas, permisos y conexión de IA de R13.

Se instala con el mismo actualizador, sobre R14 o versiones anteriores compatibles. Conserva config.php, uploads, negocios, usuarios, ventas, turnos, permisos y conexión de IA. No requiere importar SQL ni reinstalar Windows 1.8.1 o Android 1.8.0. El diseño se aplica a la aplicación web; la caja local de Windows sin internet conserva la interfaz de su instalador.

Descarga **FISITAAP-1.8.0-CPANEL-R15.zip**, extrae en **/home/fisitaap/public_html** y, con tu sesión maestra, abre **https://fisitaap.com/actualizador-180/index.php**. Marca el respaldo guardado y pulsa **Instalar archivos**. Conserva **releases/1.8.0-r16** y las versiones anteriores. La extracción del ZIP por sí sola no instala el diseño. No se dispone de acceso directo al cPanel desde esta entrega.

Revisa las capturas de comparación en **VISTAS-R15.md**. Las pruebas automatizadas usan PHP, MariaDB y Chromium con datos ficticios y tamaños de computadora, celular y tableta; la revisión en tus dispositivos físicos sigue siendo necesaria.

## Corrección R13: catálogo, menú interno y perfiles de FISIChat

El catálogo usa navegación de compra: Categorías y Ver carrito. El acceso de cliente sigue visible; al iniciar sesión aparecen Mis pedidos y Mi cuenta. Se retiran del menú del catálogo los enlaces antiguos Inicio/Catálogo/Blog/Contacto. La portada de cada negocio conserva su contenido y contacto.

Mesas, Venta rápida y Cobrar cuentas muestran el mismo menú agrupado del panel del negocio, con los mismos enlaces, permisos y módulos. Incluye Manual de uso y Cerrar sesión. Se abre con ☰ y se cierra desde ×, fuera del panel o con Escape. La web principal de FISITAAP conserva su navegación anterior, restaurada en R12.

FISIChat mantiene cuatro perfiles independientes: asesor comercial/soporte de FISITAAP en la web oficial; asistente de compras de la tienda en su catálogo; asistente operativo del negocio en el panel; y asistente de administración de la plataforma en el maestro. Las consultas ajenas al perfil se responden localmente con una orientación breve, sin llamar a la IA. No ayuda a crear/copiar sistemas ni inventa información del desarrollo interno. Las conversaciones anteriores se separan de los perfiles corregidos. Conserva la conexión de IA existente y los controles de permisos y confirmación para cambios.

## Corrección R12: menú de la web principal

R12 restaura el menú anterior de la portada oficial de FISITAAP: los enlaces Funciones, Cómo funciona, Demos y Preguntas vuelven a su posición original, junto al logo y Acceso negocios. El menú de esta portada no se transforma en desplegable. Los paneles y catálogos de las tiendas conservan el menú desplegable de R11.

Se instala con el mismo actualizador, tanto si ya instalaste R11 como si vienes de una versión anterior. No requiere importar SQL ni cambiar datos de clientes. Windows y Android conservan sus instaladores.

## Cambios de R11: slider, cantidades, pantallas y clientes

### Slider del catálogo: tres imágenes y segundos

Entra como dueño a **Administración → Diseño y página → Editar slider del catálogo (3 imágenes)**. Es el banner debajo de la sucursal que mostraba «Catálogo / Elige una categoría…».

1. En **Mostrar slider**, elige **Usar estas tres imágenes**.
2. Carga una foto en cada tarjeta **Imagen 1, Imagen 2 e Imagen 3**. Puedes dejar tarjetas vacías; sólo se muestran las que tienen imagen. Título y texto son opcionales.
3. Define **Segundos entre imágenes**, de **2 a 60**. Con una sola imagen no hay cambio automático.
4. Pulsa **Guardar diseño** y abre tu catálogo. Las fotos se comprimen automáticamente y se muestran sin capa de color.
5. **Conservar banners anteriores por sucursal** mantiene las promociones anteriores. **Ocultar slider** retira solamente ese bloque. No se borran fotos anteriores por cambiar el modo.

### Por unidad o a granel

En **Catálogo → Productos → Editar**, elige **Tipo de venta**:

- **Por unidad:** mínimo e incremento 1, decimales 0. Sólo se aceptan 1, 2, 3… unidades. También se reconocen piezas, cajas, paquetes y otras unidades contables.
- **A granel:** indica kg, g, litros, metros u otra medida; admite hasta 3 decimales según mínimo e incremento. Al elegirlo se propone mínimo/incremento 0,001 y 3 decimales. Puedes usar, por ejemplo, mínimo 0,25, incremento 0,25 y 2 decimales para vender desde 250 g usando kg.

Al pasar productos para cobrar también se respetan las unidades enteras; dividir el importe en partes iguales se conserva. La regla se comprueba en el servidor al agregar/editar en caja y al confirmar el catálogo; no depende solamente del campo visual. Los productos anteriores con etiqueta unidad y configuración fraccionaria empiezan a venderse con cantidades enteras. Sus pedidos y ventas anteriores conservan las cantidades registradas.

En el Excel, usa **unidad + decimales0 + mínimo1 + incremento1** para piezas. Para granel usa la medida correspondiente y las cantidades acordes. El machote incluye ambos ejemplos. Si el mínimo o incremento no cabe en la precisión elegida, el formulario lo adapta al siguiente valor representable.

**Windows:** después de instalar R11 en la web, conecta cada caja y sincroniza el catálogo antes de probar estas reglas sin internet. Windows 1.8.1 ya valida los mínimos/incrementos del catálogo descargado; no necesita reinstalarse. Una caja desconectada conserva su catálogo anterior hasta sincronizar. No borres ventas locales pendientes.

### Menú desplegable y pantalla completa

En maestro, dueño y catálogos de las tiendas, el menú comienza cerrado y se abre con **☰** tanto en computadora como en tableta o teléfono. Pulsa fuera, el botón de cerrar o Escape para ocultarlo. Venta rápida y Mesas también tienen su menú.

En **Venta rápida** y **Mesas**, el área de trabajo se ajusta a la pantalla disponible. **Ajustar** recupera la vista completa. **+ / −** amplían o reducen para leer mejor; al ampliar puedes desplazarte dentro de esa vista. **Pantalla completa** usa el permiso del navegador. El navegador conserva su propio zoom. Productos de acceso rápido y mesas extensas se dividen en páginas para mantener visibles los controles.

### Checkout simplificado y ubicación

Ya no aparecen Cédula, Programar pedido ni Buscar en Google Maps. El comprobante se muestra y es obligatorio únicamente cuando seleccionas SINPE. No verifica automáticamente el abono bancario.

Las direcciones guardadas se muestran después de detectar direcciones para el teléfono escrito. Un teléfono nuevo deja la dirección libre y registra la primera al confirmar. Al cambiar de teléfono se retiran las opciones anteriores para no enviar una dirección de otra persona.

**Usar mi ubicación actual** requiere HTTPS con certificado válido, permiso de Ubicación en el navegador y Ubicación/GPS activo en el dispositivo. Al obtenerla se llena el enlace y aparece **Ver ubicación cargada**. El texto explica si faltó permiso, el dispositivo no pudo localizarte o se agotó el tiempo. Puedes pegar manualmente un enlace de Maps si el dispositivo no ofrece ubicación; no se contrata una API externa.

### WhatsApp, acceso de clientes, trazabilidad y PIN

1. El cliente completa nombre, teléfono, entrega y pago y pulsa **Enviar pedido por WhatsApp**. Se prepara una ventana desde ese clic para que el navegador permita abrir WhatsApp al terminar el guardado. Si el navegador no permite ventanas, se usa la navegación disponible.
2. El número del negocio se normaliza: a un número local de ocho dígitos se añade 506. Debe ser un número que realmente utilice WhatsApp. La aplicación no puede comprobar su registro sin usar el servicio.
3. WhatsApp se abre con el pedido listo. **El comprador confirma Enviar dentro de WhatsApp.** La web guarda el pedido previamente; no envía un mensaje real en nombre del usuario ni espera cerrar una pantalla del PIN.
4. Para un **cliente nuevo creado en R11**, se prepara el usuario con su teléfono y la contraseña inicial con ese mismo teléfono de ocho dígitos. La sesión queda abierta después de guardar el pedido, antes de ir a WhatsApp.
5. Al volver, abre **☰ → Mis pedidos**. Allí aparecen el PIN y **Ver trazabilidad**. La confirmación de la compra deja también enlaces a Mis pedidos, seguimiento y WhatsApp; no abre un diálogo que interrumpa el envío.
6. El catálogo tiene **Iniciar sesión de cliente**. Usa teléfono y contraseña. Los clientes registrados anteriormente conservan su contraseña actual; también pueden entrar usando el teléfono relacionado con su negocio. Las contraseñas existentes no se restablecen al hacer un pedido.
7. **Mi cuenta → Cambiar mi contraseña** permite sustituir la clave inicial. Si ya se cambió, conocer el teléfono no inicia automáticamente esa cuenta al hacer un pedido; se pide su contraseña y el pedido conserva su enlace privado de seguimiento. Cambiar el teléfono en el perfil actualiza su identificador y conserva la contraseña que tenía.

La contraseña inicial igual al teléfono es fácil de conocer. Recomendamos cambiarla por una privada desde la cuenta. El acceso tiene límite de intentos. Las cuentas anteriores sin acceso configurado no se convierten automáticamente en cuentas con clave pública: conservan su contraseña y pueden conservar el seguimiento privado de cada pedido.

## Editar tu página y la portada del catálogo

Después de instalar R11, entra como dueño a **Administración → Diseño y página**: https://fisitaap.com/admin/contenido.

1. **Nosotros:** cambia la etiqueta que decía «NOSOTROS», título, presentación, las tres frases destacadas y el texto del botón. Una frase vacía se oculta. Los campos muestran los valores publicados, incluyendo los anteriores cuando correspondan.
2. **Contacto:** edita correo, teléfono, WhatsApp, dirección, horario, mapa y redes sociales. Son los mismos datos de Administración → Generales y el catálogo; guardar aquí actualiza ambos. Se requiere permiso de configuración, además del permiso para editar el contenido.
3. Para el mapa, abre Google Maps → Compartir → Insertar un mapa → Copiar HTML. Pega ese código en el campo Mapa de Google. No se permite insertar mapas de dominios ajenos.
4. **Portada del catálogo o menú:** elige **Usar estas tres imágenes**, cambia títulos, textos e imágenes y guarda. Se aplica a todas las sucursales. **Ocultar slider** retira solo el banner y mantiene categorías, productos y carrito.
5. **Conservar banners anteriores por sucursal** vuelve a usar las promociones que ya tenías, respetando la sucursal. **Banners anteriores por sucursal** abre la gestión de imágenes/promociones anteriores. Cambiar entre modos no elimina esos registros ni la portada personalizada guardada.
6. **Colores y botones:** el color principal se aplica a los botones para abrir catálogo/menú. El texto del botón se ajusta a negro o blanco para mantener contraste. El panel del dueño conserva su diseño neutral.
7. Pulsa **Guardar diseño**. La vista previa muestra lo publicado. Puedes abrir también **Abrir página completa** o **Abrir catálogo / menú**. La portada principal y la portada del catálogo se editan por separado.

## Crear productos con Excel y descargar el machote

1. En el panel del dueño abre **Catálogo → Productos → Crear productos desde Excel**. También puedes entrar a https://fisitaap.com/admin/productos-importar.
2. Pulsa **Descargar machote Excel**. El archivo FISITAAP-Machote-Productos.xlsx también está en la carpeta de descargas de esta entrega.
3. Completa únicamente la hoja **Productos**. Las hojas **Ejemplos** e **Instrucciones** sirven de ayuda y no se importan. Mantén los encabezados; SKU, nombre y precio son obligatorios. Conserva el formato Texto del SKU para no perder ceros iniciales. No uses fórmulas, símbolos de moneda ni separadores de miles.
4. Puedes cargar hasta **500 productos por archivo de 5 MB**, en .xlsx o CSV. Para archivos .xls antiguos, usa Guardar como → .xlsx. CSV admite UTF-8, coma o punto y coma. La lectura de .xlsx requiere las extensiones PHP zip y SimpleXML, usadas también por otras herramientas de la plataforma.
5. Elige la **sucursal para existencias iniciales**, selecciona el archivo y pulsa **Revisar archivo**. Revisa nombre, precio, impuesto, estado y cantidades. Las categorías que no existan se crearán. La primera revisión no guarda productos.
6. Si hay errores de filas o SKU existentes/repetidos, corrige el archivo y vuelve a cargarlo. Esta herramienta **crea productos nuevos**; no actualiza precios ni existencias de los productos anteriores.
7. Pulsa **Crear productos** tras revisar. Si falla una fila, no se crea ninguna. Repetir la misma confirmación no duplica la carga. La revisión vence en 20 minutos.
8. La existencia inicial se registra solamente en la sucursal elegida y genera su movimiento. En catálogo global, los productos pertenecen al negocio; en catálogo por sucursal se habilitan en la elegida. Imágenes, extras, grupos y proveedores se pueden completar después desde el producto.

Se necesita permiso para crear productos. Inventario y categorías nuevas requieren además sus permisos. Los permisos se verifican de nuevo al confirmar. Los valores predeterminados de las columnas opcionales están en Instrucciones del machote.

## Opcionales organizados por grupo

En **Catálogo → Opcionales**, escribe **Nombre del grupo**, por ejemplo **Tallas**, y agrega las opciones **S, M, L**. Crea otro grupo **Colores** con **Rojo, Azul**. Puedes indicar si elegir es obligatorio y si permite una o varias selecciones; asigna el grupo a productos o categorías completas.

En **Productos → Extras y opcionales** y en la asignación de categorías también puedes marcar el grupo completo. Las opciones tienen costo adicional cero; los extras con precio conservan su editor. Catálogo y caja piden las selecciones de cada grupo por separado. Editar o desactivar opciones no cambia los nombres/importes que ya quedaron guardados en ventas y pedidos anteriores.

## Negocios nuevos con la versión actual

Tras instalar y publicar R11, todo negocio creado desde **Maestro → Negocios** empieza con la versión actual, incluyendo modo Catálogo o Menú. Su dueño solo realiza el cambio normal de contraseña temporal; no necesita activar una actualización pendiente para empezar. La creación y la preparación se guardan juntas: si falla, no queda un negocio a medio preparar.

Los negocios anteriores que ya activaron 1.8.0 mantienen esa activación. Los negocios anteriores pendientes en 1.7.9 siguen eligiendo cuándo activar desde Administración → Actualizar sistema, porque pueden tener turnos y equipos pendientes. No se elige un equipo principal ni se abre un turno automáticamente.

## Fotos grandes y optimización

Selecciona JPG, PNG o WebP de hasta **25 MB por foto**. El navegador prepara la foto antes de enviarla y muestra el tamaño resultante; espera a que termine o pulsa Guardar y el formulario esperará. El servidor vuelve a validar y guardar WebP con miniatura. La reducción depende de cada imagen: en QA una foto de 11 MB llegó como 400 KB y quedó en 115 KB. Se conserva la transparencia y la orientación de fotos del teléfono. El archivo original de tu computadora no cambia.

La optimización reduce dimensiones/peso; las imágenes del sitio son estáticas. La validación de formato, dimensiones y memoria sigue activa. Ante un rechazo no se publica una imagen vacía ni se anuncia un guardado exitoso. Las imágenes ya publicadas se conservan hasta que elijas reemplazarlas o quitarlas.

La compresión previa permite trabajar normalmente con el límite PHP habitual. Si el hosting aún rechaza una carga, revisa en cPanel → MultiPHP INI Editor los límites efectivos. Para recibir originales sin compresión previa, usa upload_max_filesize=25M y post_max_size=50M; memory_limit=256M es recomendable. Los controles de seguridad de la aplicación siguen aplicándose. No reemplaces config.php ni la base de datos.

Hay dos pasos: tú publicas los archivos una vez en cPanel; después cada dueño de un negocio anterior pendiente activa los cambios de su negocio desde **Administración → Actualizar sistema**, cuando pueda. Los negocios pendientes conservan el funcionamiento compatible con 1.7.9. No es necesario cerrar los turnos de todos los clientes para publicar.

## Si el acceso te devuelve al error500

El error reproducido ocurre al mantener abierta una cuenta de negocio suspendido o sin negocio asociado: owner-login la devuelve a admin y admin fallaba con500. R9 muestra «Tienda fuera de servicio» para una cuenta de negocio suspendido, recupera el formulario y muestra la causa y permite entrar con otra cuenta. No cambia el estado de los negocios ni reasigna cuentas por su cuenta.

Después de guardar respaldo y extraer R13 en public_html, abre **https://fisitaap.com/volver-acceso-180.php** desde el navegador bloqueado. Pulsa **Volver al acceso**, ingresa con tu cuenta maestra y continúa con el actualizador indicado abajo. Este botón cierra únicamente la sesión de ese navegador mediante confirmación; no vacía ventas locales ni modifica negocios. Si el otro navegador ya permite entrar como maestro, puedes instalar desde él directamente.

En el panel maestro revisa **Negocios → La Ventanita**: si corresponde que opere, confirma que esté activa. Si falta la asociación de un usuario, revisa su negocio antes de modificarla; no elijas otro negocio para salir del paso. La comprobación **https://fisitaap.com/diagnostico-bienvenida-180.php** identifica por separado un negocio suspendido y una asociación inexistente.

## Imagen de bienvenida para todos los negocios

La bienvenida configurada por el maestro se muestra al abrir **Inicio → Bienvenida** o **/admin**, también en negocios que aún tienen pendiente activar 1.8.0. No se activa su actualización operativa por mostrar la imagen. El maestro puede ver la imagen actual en **Web oficial → Imagen de bienvenida**. Después de cambiarla, vuelve a abrir la bienvenida o recarga la página. La imagen que ya guardaste se conserva. La imagen llena el área restante bajo el encabezado y los botones, conservando su proporción. Para llenar el área puede recortar bordes; no necesitas volver a subirla por este ajuste.

Para cambiar esta pantalla, entra como maestro a **https://fisitaap.com/master/bienvenida**. También puedes usar **Cambiar imagen de bienvenida** desde el inicio del maestro. Elige un JPG, PNG o WebP dentro del tamaño que indica el formulario, deja desmarcada **Usar publicidad predeterminada**, pulsa **Guardar bienvenida** y comprueba **Imagen actual**. Después abre **Ver bienvenida publicada**. La imagen de **Diseño y página** pertenece a la portada pública y se configura por separado.

Si no se recibe el archivo, se supera el límite o la imagen no es válida, R9 explica el motivo y no confirma un guardado. Si no había una imagen personalizada, el editor lo indica. **Usar publicidad predeterminada** restaura intencionalmente el diseño original; no la marques al subir tu imagen.

## Pedidos de demostración y Express

En ambos demos puedes hacer un pedido, cerrar el ejemplo de WhatsApp y crear otro desde el mismo catálogo. Cada pedido confirmado deja listo un envío nuevo. Si falla una respuesta, el reintento conserva la referencia del pedido para evitar duplicados; no recargues para forzar un segundo cobro.

**Express pendientes** muestra los pedidos web publicados en Express y los creados en caja de la sucursal seleccionada. Los web conservan el estado de la entrega del motorizado y se gestionan desde **Gestionar Express**. El total del pedido y el pago al motorizado aparecen separados. La confirmación de entrega sigue exigiendo el PIN en el portal del motorizado. No se marca un pedido como cobrado por mostrarlo en esta lista.

**Historial de entregas** incluye entregas activas y finalizadas de todas las sucursales del negocio. Tiene filtro de estado en español y columna de sucursal. Una entrega finalizada desaparece de pendientes y permanece en el historial. La disponibilidad del motorizado y sus sucursales autorizadas siguen aplicándose.

## Pedidos, clientes y entregas · R9

1. En Venta rápida o Mesas y caja aparece **Pedidos web**, con contador. También puedes abrir Pedidos. Un pedido nuevo no imprime antes de aceptarlo, aunque la configuración anterior indicara recepción directa.
2. **Aceptar e imprimir comanda** abre una sola cuenta y envía una sola comanda a la impresora configurada. Después usa **Preparar** y **Preparado**. Cobro y entrega se registran por separado.
3. **Asignar a Express** permite liberar a los afiliados autorizados de la sucursal o elegir uno directamente. El motorizado debe tener acceso y no estar ocupado. El envío mantiene la cuenta original.
4. Para cancelar, el administrador o un rol con permiso explícito de eliminación debe escribir la justificación. Una cuenta con cobros no se anula como si estuviera sin pagar.
5. **Detalle y bitácora** muestra situación actual, ubicación, PIN y horas reales de los cambios. Desde allí tienda puede corregir dirección y resolver una incidencia indicando la solución. El historial de entregas lleva a este detalle. Los pedidos anteriores no tienen una cronología inventada.
6. El cliente proporciona nombre y teléfono. El sistema relaciona el contacto de ese negocio y ofrece sus direcciones guardadas; permite agregar otra y la primera queda principal. **No crea una contraseña con el teléfono ni inicia sesión en una cuenta existente.** Dirección principal significa preferida; no es obligatorio que la primera elegida sea la usada en cada pedido.
7. El campo de señas se sustituye por Google Maps. **Usar mi ubicación actual** solicita permiso del navegador y requiere HTTPS válido. **Buscar en Google Maps** abre el buscador; usa Compartir → Copiar enlace y pégalo. No requiere contratar una API de Google. Completa también una dirección escrita.
8. En Administración/Clientes, cada ficha permite varias direcciones y un enlace Maps por dirección. Las cuentas de cliente que ya tenían acceso conservan también sus direcciones.
9. Al elegir SINPE el comprobante JPG/PNG/WebP es obligatorio, máximo 6 MB. Se optimiza a WebP y se guarda de forma privada. El encargado puede revisarlo. Adjuntarlo no verifica el movimiento bancario ni marca la venta como cobrada.
10. Al terminar el pedido aparece el PIN y un enlace privado para conservarlo y consultar su avance. El PIN se comparte con el motorizado al recibir; un PIN incorrecto no finaliza la entrega. Conserva ese enlace.

## Afiliados, cortes y cuentas

En **Afiliados** configura el pago de próximos Express: todo el costo del envío o un porcentaje. Las condiciones se fijan al asignar/aceptar; cambiar el porcentaje no altera trabajos ya asignados. El saldo a pagar nace cuando el motorizado confirma la entrega con el PIN. El portal del motorizado muestra sus entregas y pagos.

Para pagar, prepara un corte seleccionando entregas pendientes y el origen del dinero. **Caja del turno** exige una caja nativa autorizada y turno abierto; descuenta efectivo de ese turno. Una cuenta bancaria u otra caja registrada descuenta su propio saldo. Reintentar el mismo corte no vuelve a pagarlo. El costo completo de delivery forma parte de lo cobrado al cliente; entra al efectivo del día cuando realmente se cobra en efectivo.

**Administración → Cuentas de dinero** ya existía y ahora distingue Caja principal, Caja chica, Banco/SINPE, Otra caja y Otra cuenta. Una cuenta nombrada no sustituye automáticamente al efectivo del turno. El saldo inicial debe corresponder a fondos reales.

Los trabajos históricos sin asiento de pago se identifican como anteriores al control de pagos, para revisión. No se convierten automáticamente en deudas nuevas ni se suponen pagados.

## Windows 1.8.1 y venta sin internet

1. Con internet, sincroniza y comprueba que no queden ventas pendientes antes de actualizar esa computadora. Cierra FISITAAP y ejecuta **FISITAAP-Escritorio-1.8.1-Windows-x64.exe** sobre la instalación existente. No desinstales ni borres los datos locales.
2. Abre la aplicación con internet e inicia sesión con el cajero autorizado. En Administración → Equipos y cajas, verifica que ese equipo sea el **principal único**, tenga su caja asignada y que el usuario tenga permisos para vender/abrir turno. No se elige un principal automáticamente.
3. La barra debe indicar **Caja local preparada**. Si falta preparar acceso, abre la web desde la aplicación, entra con el usuario aprobado y vuelve a comprobar. No desconectes el equipo mientras avisa que falta preparar el acceso.
4. Abre el turno correspondiente. Prueba primero una venta pequeña controlada y comprueba impresión. Si se pierde internet, usa **Caja local**. Solo el principal vende sin internet; los secundarios necesitan conexión.
5. Al regresar internet, sincroniza y verifica que la venta aparezca una sola vez. Las operaciones inciertas de cobro requieren revisar el resultado; no se fuerza un segundo cobro para resolverlas.

La corrección actualiza inmediatamente los usuarios aprobados al autorizar la sesión web en Windows. Antes, si se había vinculado el equipo sin cajero asignado, podía quedar una lista local vacía hasta una sincronización posterior y bloquear la venta sin internet.

## A. Guardar respaldo

1. En cPanel abre **Administrador de archivos → public_html**. En tu servidor la carpeta correcta es **/home/fisitaap/public_html**. La carpeta **/home/fisitaap** que contiene mail, .cpanel y public_html es un nivel más arriba: no extraigas allí la actualización.
2. Comprueba que dentro de public_html están el index.php del sitio y config.php. **Dominios → fisitaap.com → Raíz del documento** permite confirmar esta ubicación.
3. Guarda un respaldo de los archivos del sitio, incluyendo .htaccess: **Configuración → Mostrar archivos ocultos**. Descarga el respaldo a tu computadora o usa un respaldo completo de cPanel.
4. En **phpMyAdmin**, selecciona la base de FISITAAP → **Exportar → Rápido → SQL → Exportar**. Guarda el archivo junto al respaldo del sitio, fuera de public_html.

## B. Publicar los archivos en cPanel

5. Descarga **FISITAAP-1.8.0-CPANEL-R13.zip** de GitHub. El nombre FISITAAP-1.8.0-CPANEL.zip también contiene R13. Usa R13 para esta corrección, aunque ya hayas instalado R1, R2, R3, R4, R5, R6, R7, R8, R9, R10, R11 o R12.
6. En **public_html**, pulsa **Cargar** y sube R13. Selecciona el ZIP → **Extraer**. Debe aparecer **public_html/actualizador-180/index.php**. Permite reemplazar los archivos anteriores de actualizador-180; conserva cualquier carpeta de respaldo que ya exista dentro.
7. No muevas payload ni copies su contenido manualmente sobre el sitio. El actualizador comprueba los archivos y decide dónde instalarlos.
8. Revisa **PHP 8.2 o 8.3**, PDO MySQL, mbstring, SimpleXML/XML, cURL y GD. GD con WebP permite optimizar imágenes. El ZIP contiene archivos **644** y carpetas **755**. Si cPanel conserva permisos viejos, corrige únicamente los del actualizador a esos valores; nunca uses 777.
9. Abre **https://fisitaap.com/owner-login** e ingresa como **maestro**.
10. En ese mismo navegador abre **https://fisitaap.com/actualizador-180/index.php**.
11. Marca **Guardé respaldo de archivos y base de datos** y pulsa **Instalar archivos** una vez. Espera el mensaje **Archivos 1.8.0 instalados y comprobados. Actualización publicada…**.
12. Este paso instala los archivos y prepara las tablas, campos e índices compartidos. No activa todos los negocios ni cierra sus turnos. La preparación inicial puede producir una espera breve mientras la base agrega índices.
13. Si aparece **distinto**, se detectó un archivo modificado fuera de los paquetes conocidos. Guarda el respaldo y los nombres que señala; no fuerces la copia. Si falla la preparación de la base, conserva los archivos instalados y el respaldo, revisa el registro con soporte y repite después de corregir la causa. Repetir una instalación correcta no duplica datos ni activa negocios pendientes.
14. En el panel maestro, **Administración → Actualizar sistema**, puedes ver los negocios actualizados y pendientes. También abre **https://fisitaap.com/master/actualizar-sistema**. **Comprobar publicación** revisa la preparación; no activa clientes. Para ayudar a uno, selecciona únicamente ese negocio y confirma su preparación.

La aplicación nueva se instala en **public_html/releases/1.8.0-r16**. Se cambia el archivo de entrada al terminar de copiar: los archivos app/assets anteriores se conservan para que las solicitudes que ya estaban ejecutándose puedan terminar.

Si el negocio ya tenía 1.8.0 activada antes de R11, mantiene esa versión y el botón mostrará que está actualizado. No se retrocede a 1.7.9.

## C. Lo que hará cada dueño cuando pueda actualizar

15. El dueño entra a su panel → **Administración → Actualizar sistema**. Mientras esté pendiente puede seguir trabajando y volver después.
16. En **cada caja de su negocio**, pulsa **Sincronizar** y confirma que quedan **cero ventas pendientes**. Hazlo también en las sucursales. El servidor no puede comprobar una cola que siga guardada en un equipo sin conexión: el dueño confirma esa parte.
17. Cobra o cancela las cuentas abiertas y cierra los turnos de **ese negocio**. Un turno es la sesión de trabajo del cajero con su fondo y ventas; cerrarlo guarda su arqueo. No significa eliminar ventas ni borrar usuarios. Los demás negocios pueden mantener sus turnos abiertos.
18. Actualiza sus Windows a **FISITAAP-Escritorio-1.8.1-Windows-x64.exe**, con FISITAAP cerrado, sobre la instalación anterior. No desinstales ni borres sus datos locales. Requiere Windows 10 o posterior de 64 bits. Windows 1.8.1 sí se necesita para esta corrección de venta local.
19. Para un Android nuevo instala **FISITAAP-Android-1.8.0.apk**; requiere Android 8 o posterior. Autoriza instalar desde ese navegador cuando Android lo solicite. Si ya tiene nuestra versión 1.8.0 puede conservarla. Guarda el APK y el respaldo privado de su firma; la clave privada nunca va en cPanel ni GitHub.
20. Durante esta preparación, las cajas **de ese negocio** deben dejar de vender. Con todas sincronizadas, turnos cerrados y aplicaciones actualizadas, marca la confirmación y pulsa **Actualizar sistema**.
21. Espera **Actualización 1.8.0 activada para este negocio**. La configuración se convierte dentro de una transacción y solo se marca actualizada al terminar. El servidor vuelve a revisar turnos y cuentas; si queda alguno, lo indicará sin cerrar ni borrar nada por su cuenta.
22. Recarga el panel y sincroniza las aplicaciones para recibir la configuración nueva. Ya puedes abrir los nuevos turnos. Repetir el botón no vuelve a convertir los datos ni duplica operaciones.

El botón prepara y activa el negocio en la web. Los instaladores de Windows/Android se descargan desde esa pantalla y se ejecutan en sus equipos; el navegador no los instala automáticamente.

## D. Equipos e impresión después de activar

23. **Administración → Equipos y cajas**: revisa sucursal, caja y usuarios aprobados por equipo. El cajero usa su usuario y contraseña habituales.
24. Para un equipo nuevo, crea su código. En Windows usa **FISITAAP → Conectar este equipo al negocio**; en Android, **Conectar y autorizar equipo**. Dirección: **https://fisitaap.com**, número de equipo y código. No uses la dirección del catálogo. HTTPS debe tener certificado válido.
25. Elige **un único principal por negocio**, Windows o Android, y sincronízalo. Ese equipo puede buscar y vender sin internet; las demás cajas necesitan conexión. Para cambiarlo, sincroniza, cierra sus turnos y libéralo desde ese equipo primero.
26. Android puede autorizarse directamente en la web o descubrir la dirección web mediante el central Windows en la red local; se autoriza como equipo propio.
27. **Administración → Impresión**: configura recibos, cocina, bar, despacho y puntos adicionales, con sucursal, equipo, papel 58/80 mm y destino.
28. En Windows selecciona una impresora instalada o una térmica de red. Android utiliza térmicas de red compatibles con **ESC/POS**, con IP y puerto. El ancho 58/80 mm es el papel y no garantiza compatibilidad. También puedes elegir PDF.
29. Pulsa **Probar** en cada punto. La gaveta se habilita tanto en caja como en recibos; abre solo con efectivo en un recibo original, nunca con comandas o copias. No necesitas FISITAAP Print para estos envíos. Si queda **sin confirmar**, revisa papel/conexión antes de pedir una copia.

## E. Comprobar y retirar el instalador

30. Comprueba portada, acceso a negocios, La Ventanita y ambos demos, incluido **Otros demos**. Las demos usan copias independientes y dinero virtual; la impresión es simulada.
31. En el negocio actualizado prueba una venta, recibo/comanda, efectivo/vuelto y cierre. En el principal sincronizado, desconecta internet, vende con productos de prueba y reinicia: la venta debe seguir pendiente. Recupera internet, sincroniza y confirma que llegó una sola vez. Cierra el turno de prueba; no borres ventas en phpMyAdmin.
32. Confirma que una caja secundaria necesita conexión y que compras, cuentas, mesas, reportes y permisos funcionan según lo que use el negocio.
33. Si un navegador conserva un diseño anterior, recarga con **Ctrl+F5**. Los archivos nuevos se sirven desde una carpeta de versión propia.
34. Descarga y conserva **actualizador-180/backups** fuera del sitio. Cuando lo hayas comprobado, elimina **solo** el ZIP subido, la carpeta **actualizador-180**, **volver-acceso-180.php** y **diagnostico-bienvenida-180.php**. Los dueños podrán seguir actualizando después desde Administración.
35. **No borres releases ni releases/1.8.0-r16**: contienen la aplicación en uso. Conserva también app, assets, config.php y uploads. No elimines archivos de una versión anterior durante esta instalación.

## F. Comprobar FISIChat

36. En la portada pulsa **FISIChat**. Pregunta, por ejemplo, «Tengo un restaurante» y luego «¿Puedo vender sin internet?». El chat abre en la misma página. **Hablar con un asesor** usa el WhatsApp configurado de FISITAAP.
37. En La Ventanita pregunta por productos, horarios o contacto. Usa su logo y datos publicados. Un cliente que inició sesión puede consultar únicamente sus propios pedidos de esa tienda.
38. Como maestro, abre tu panel y pulsa **FISIChat**. Prueba «Resumen de FISITAAP», «Negocios vencidos», «Actualizaciones pendientes» y «Contactos nuevos». Esta conversación es privada y distinta del chat público.
39. Para cambiar un plan o vencimiento, pide «Cambiar un plan». Elige el negocio, escribe los datos y pulsa **Revisar cambio**. Comprueba los valores anteriores y nuevos. Solo **Confirmar cambio** los guarda y registra en la bitácora. Los módulos y estados del negocio siguen gestionándose desde sus pantallas.
40. **Nueva conversación** borra el historial del chat actual. El historial se conserva solo durante la sesión y se separa por perfil, tienda, sucursal y usuario.

El chat reutiliza la conexión de IA que ya tengas configurada en tu **config.php privado** (`openai_api_key`) o en la variable del servidor `OPENAI_API_KEY`. No reemplaza esa configuración ni incluye claves en el ZIP. Sin una clave válida ofrece una guía local de funciones y consultas limitadas a los datos autorizados; para conversación libre con IA se necesita esa conexión, internet y saldo del proveedor. Nunca envíes la clave por el chat ni subas config.php a GitHub.

## Volver atrás

Si falla la copia antes de preparar la base, el actualizador restaura los archivos que alcanzó a reemplazar. La opción **Restaurar antes de publicar** solo está disponible antes de esa preparación.

Después de publicar, la restauración de archivos antiguos se bloquea, incluso si un negocio sigue pendiente. Para volver atrás se debe revisar el respaldo completo y las operaciones posteriores; copiar archivos viejos por cuenta propia puede afectar ventas y cajas.

## Alcance de las pruebas

R13 verifica el catálogo, la navegación compartida de panel/terminal y los cuatro perfiles del chat con un proveedor simulado; conserva datos al instalar sobre R12. R12 verificó el menú oficial original en escritorio/tablet/móvil y la instalación sobre R11 publicado, conservando datos y menús de tiendas. Las pruebas de R11 se conservan como evidencia histórica: slider real de tres imágenes/tiempo configurable, unidad y granel en caja/catálogo, login/contraseñas por teléfono, menús, veinte vistas de terminal, checkout y PIN en móvil. La instalación se comprueba sobre R10 publicado con negocios179/180 y turno abierto. El paquete tiene131 archivos con permisos644/755. Las pruebas de fotos de11MB, orientación y compresión de R10 se conservan como evidencia histórica. El informe distingue las ejecuciones actuales. No se accedió al hosting ni se cambiaron los instaladores Windows/Android.
