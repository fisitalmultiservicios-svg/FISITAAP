# Informe QA · FISITAAP web 1.8.0-R11

Fecha: 23 de octubre de 2026. Windows 1.8.1 y Android 1.8.0 conservan sus binarios. R11 es una actualización web sobre R10, con una columna aditiva para diferenciar acceso de clientes nuevo/pendiente/anterior.

| Solicitud | Implementación |
|---|---|
| Editar el banner señalado | Tres tarjetas numeradas de imágenes, títulos/textos opcionales, tiempo de2 a60 segundos y modos personalizado/anterior/oculto en Diseño y página. Se conservan banners por sucursal y la compresión. |
| Unidad y granel | Selector explícito, enteros para unidades y hasta3 decimales para granel. Validación en servidor al agregar/editar POS y checkout web; catálogo visual, Excel y snapshot nativo consistentes. Ventas anteriores conservadas. |
| Menú en todas las pantallas | Drawer inicialmente cerrado en maestro/dueño/catálogo y navegación oficial; abre con☰ y cierra desde botón, fuera o Escape. Menú en terminal con rutas autorizadas. |
| Mesas y Venta rápida | Ajuste al área disponible y controles Ajustar/+/−/pantalla completa. Ambas vistas de mesas y venta vacía/cargada sin scroll exterior en20 combinaciones de ruta/tamaño. Acceso rápido/mesas paginan; al ampliar se permite desplazamiento interior. |
| Checkout más simple | Sin cédula, programación ni búsqueda de Maps; comprobante oculto excepto SINPE; direcciones sólo tras coincidencia del teléfono y limpieza al cambiarlo. |
| Ubicación actual | Solicitud con permiso, enlace exacto y vista previa; mensajes para HTTPS inválido, permiso bloqueado, ubicación no disponible y tiempo agotado. Se requiere soporte/configuración real del dispositivo. |
| Error de WhatsApp | Se corrige el número local sin506. Se reserva la ventana al clic y se abre el enlace tras guardar; una confirmación no modal deja seguimiento/WhatsApp disponibles. No se verifica que el número esté registrado en WhatsApp. |
| PIN y acceso | El PIN está en Mis pedidos y trazabilidad; no se interpone un diálogo antes de WhatsApp. Cuenta y sesión para nuevos compradores con usuario teléfono/clave inicial teléfono. Login de clientes visible en catálogo. |
| Contraseñas y cuentas anteriores | Nunca se reemplaza una contraseña existente por conocer un teléfono. Sólo los contactos nuevos marcados por R11 reciben la clave inicial al guardar su primer pedido. Clave cambiada no se restablece ni autoautentica. Cambio de teléfono actualiza el alias conservando contraseña. |

La clave inicial igual al teléfono es débil por ser un dato conocido; el formulario lo explica y ofrece cambio de contraseña. Esto no es verificación por SMS/WhatsApp ni autenticación con código. Las cuentas previas sin acceso configurado conservan su contraseña; conocer el número no reclama automáticamente su historial. El lookup de direcciones por teléfono conserva el comportamiento solicitado previamente.

## Evidencia R11

- QA-EXPERIENCIA-R11.json: 8 grupos HTTP/Chromium: enteros/fracciones, login por teléfono, conservación/cambio de claves y teléfonos, slider real con tres imágenes/2s, menús en cuatro tamaños, veinte vistas de terminal, checkout condicional/geolocalización y salto a WhatsApp simulado con sesión/PIN/trazabilidad. PIN visible en tarjeta adaptable sin ancho mínimo heredado.
- QA-EXCEL-R11.json: 5 grupos de descarga XLSX, creación atómica/reintento, rechazo de configuración fraccionaria por unidad, CSV, conflictos de SKU, permisos revocados y archivos malformados. Los casos válidos a granel mantienen0,25kg.
- QA-DIVISION-R11.json: API de cotización y preview JavaScript con metadatos SQL reales rechazan trasladar media unidad, permiten1unidad/0,125kg y conservan la división monetaria en3partes. Sin escrituras de ventas/cantidades al cotizar. La vista de venta reutiliza metadatos y evita consultar el producto por cada línea.
- QA-SNAPSHOT-R11.json: snapshot HTTP real y quote de Windows1.8.1 sin cambios. Rechaza1,5 unidades y acepta2 unidades y0,25kg. Los clientes deben sincronizar después de la instalación.
- QA-ACTUALIZADOR-R11.json: instalación sobre ZIP R10 publicado sin la columna nueva, con negocios179/180 y turno abierto. Migración aditiva, preservación de filas/config/uploads/código anterior, controles maestro/CSRF y reintento idempotente.
- QA-PERMISOS-ZIP-R11.json: extracción sobre R10 y en carpeta nueva bajo umask077/ApacheUID33, sin reparar después; archivos644/755 y PHP privado403.
- QA-SINTAXIS-R11.json:78PHP funcionales/82PHP del archivo completo,10JavaScript,130 hashes y guía exacta del ZIP.

El paquete conserva datos y no importa SQL ni reemplaza config.php. La publicación mantiene las activaciones por negocio; los negocios nuevos continúan iniciando actualizados como en R10. Los archivos se instalan en releases/1.8.0-r11.

No se modificó el hosting ni se enviaron mensajes. WhatsApp se interceptó en navegador; la geolocalización se simuló con permiso y con rechazo. No se validaron Windows/Android físicos, GPS real, certificado/latencia de cPanel, cuentas bancarias ni impresoras. El test nativo ejecutó la lógica de cantidades con un snapshot ficticio, sin presentar eso como una nueva prueba física de venta sin red.

Las secciones siguientes son evidencia histórica de R10 y anteriores.

# Informe QA · FISITAAP web 1.8.0-R10

Fecha: 21 de octubre de 2026. Mejoras del editor/imágenes de tienda, portada del catálogo, alta actualizada de negocios, grupos de opcionales y creación por Excel. Windows 1.8.1 y APK 1.8.0 no cambian en esta entrega.

| Solicitud | Implementación |
|---|---|
| Fotos mayores de 5 MB | Hasta 25 MB por imagen JPG/PNG/WebP en formularios del panel. Preparación automática antes de enviar y WebP/miniatura en servidor. Transparencia y orientación conservadas. |
| Editar el segmento de Nosotros | Etiqueta, título, presentación, las tres frases destacadas y texto del botón dentro de Diseño y página → Nosotros. Frase vacía se oculta. El editor recupera valores publicados y contenido anterior cuando corresponda. |
| Información de contacto | Diseño y página → Contacto contiene correo, teléfono, WhatsApp, dirección, horario, mapa y redes. Usa los mismos datos de Generales; no crea una segunda copia. Requiere permiso de configuración para editarlos. |
| Quitar la capa verde | Sin capa de color sobre la imagen principal ni sobre la portada del catálogo. Fondo sin imagen usa el color primario. Se retiran verdes fijos de los botones/insignias de la landing. |
| Botón del color primario | Los botones para abrir catálogo/menú usan el color elegido, con letras blancas o negras según contraste. El panel del dueño sigue neutral. Los textos de enlaces usan un tono legible si el primario es muy claro. |
| Editar el banner del catálogo | Nueva sección Portada del catálogo o menú, con título, texto, imagen y modos Personalizada/Ocultar/Conservar banners actuales. Los banners de sucursales siguen disponibles y no se borran. |
| Negocios nuevos actualizados | La creación prepara la versión 180 antes de confirmar la misma transacción, tanto Catálogo como Menú; no requiere activación manual. Ediciones/publicación respetan los negocios anteriores pendientes. |
| Grupos de opcionales | Nombre de grupo y opciones individuales, elección única/múltiple y obligatoria, asignación completa por producto/categoría. Reutiliza estructura existente; no añade tablas. IDs e historial preservados. |
| Creación desde Excel y machote | .xlsx real y CSV, machote con Productos vacío y Ejemplos/Instrucciones separados. Vista previa, hasta500 productos/5MB, validación de SKU/valores/permisos/sucursal, alta atómica y confirmación idempotente con movimiento inicial de inventario. |

La compresión se aplica a imágenes estáticas. No se guardan las copias originales pesadas de nuevas cargas. Los archivos ya publicados se conservan. La preparación del navegador reduce el tráfico y la memoria necesaria de PHP; la validación del servidor limita bytes reales, formatos, dimensiones y memoria. Un rechazo de PHP ya no se interpreta como ausencia de imagen/guardado sin cambios.

El formulario espera la preparación antes de enviarse. Si falla, conserva el formulario y la publicación anterior. Las imágenes no se regeneran durante las visitas del público. Una carga de 11.534.672 bytes llegó como aproximadamente 411 KB multipart y se guardó en 114.932 bytes con miniatura. Es una prueba deliberadamente detallada; los tamaños de otras imágenes dependen del contenido.

La portada personalizada del catálogo aplica a todas las sucursales. El modo automático restaura los banners existentes y su prioridad por sucursal. Ocultar elimina solo el banner, conservando categorías, productos y carrito. El encabezado automático sin imagen deja de usar el dibujo que decía FISITAPP. La vista de cada sucursal y los productos no se mezclan.

## Pruebas de R10

- QA-DISENO-R10.json: 5 grupos. Foto real de 11 MB enviada desde Chromium, guardado de Nosotros/contacto, aislamiento de negocio/datos fiscales, colores morado/amarillo y contraste,3 tamaños1366/768/390px, edición por roles y rechazo atómico de correo/mapa inválidos. Sin errores JavaScript.
- QA-IMAGENES-R10.json: 3 grupos. Compresión directa de 11 MB en PHP con memoria 128 MB, transparencia, orientación EXIF, límites por bytes reales y mensajes ante errores de recepción. No se desactivó la validación para admitir fotos grandes.
- QA-CATALOGO-R10.json: 3 grupos. Edición/publicación de la portada, imagen optimizada, ausencia de verde fijo y del placeholder de plataforma en ese bloque, ocultar sin perder carrito y restaurar varias promociones por sucursal. Modos inválidos rechazados y registros originales conservados.
- QA-ALTAS-OPCIONALES-R10.json: 6 grupos con alta Catálogo/Menú y cambio normal de contraseña, versión180 sin activación manual, preservación de179 pendientes, fallo de alta atómico, grupos Tallas/Colores, validación en caja, edición sin cambiar historial y roles/tres tamaños de navegador.
- QA-EXCEL-R10.json: 5 grupos con descarga del machote real, preview sin escrituras, alta y stock en sucursal, confirmación repetida, SKU conflictivo entre revisión/alta, CSV, archivos inválidos, fórmulas/DTD/enlaces externos/bomba ZIP/501filas, sucursal ajena y permisos revocados tras revisión.
- QA-NAVEGADOR-R10.json:regresión de checkout móvil con Maps/SINPE/PIN y recepción por vendedor, actualizaciones entre sesiones y tamaños de la lista de pedidos.
- QA-ACTUALIZADOR-R10.json: 7 grupos. Instalación sobre ZIP R9 publicado, negocios 179/180 y turno abierto, datos/config/uploads/código anterior conservados, permisos maestro/CSRF/integridad y reintento sin duplicar respaldo.
- QA-PERMISOS-ZIP-R10.json:extracción sobre R9 y carpeta nueva con umask077/ApacheUID33, permisos644/755 sin reparaciones posteriores y payload privado403.
- QA-SINTAXIS-R10.json:sintaxis del PHP/JavaScript del paquete final,126 hashes y guía idéntica al archivo de descarga.

No se cambian tablas, importan respaldos SQL ni reemplaza config.php. El ZIP usa releases/1.8.0-r10. Las imágenes y valores ya guardados permanecen. La publicación no activa automáticamente un negocio anterior179. Los negocios180 mantienen activación. Los negocios nuevos creados después de la instalación/publicación empiezan180 y no necesitan el botón de actualización.

Pruebas en PHP 8.3/GD/WebP, MariaDB y Chromium con datos ficticios. El mapa incrustado de Google se sustituyó por una respuesta ficticia en el test visual; no se probó el servicio externo real ni se modificó el hosting. No hubo una nueva validación física de Windows, Android o impresoras, cuyos binarios permanecen iguales. Para navegadores sin preparación previa se necesita que los límites efectivos de cPanel admitan el original; la guía explica los valores de PHP.

Las secciones siguientes son el historial de R9 y anteriores, no ejecuciones nuevas de toda la plataforma para R10.

# Informe QA · FISITAAP web 1.8.0-R9 / Windows 1.8.1

Fecha: 19 de octubre de2026. Revisión de las 16 solicitudes y sus aclaraciones. Datos ficticios, PHP 8.3/Apache/MariaDB 11.4, Chromium y Node. No hubo acceso al cPanel ni a datos de producción.

| Solicitud | Resultado y comprobación |
|---|---|
|1. Actualización automática | Consulta de revisión por negocio cada 2 s en pestañas visibles; trae fragmentos solo al cambiar. Vendedor, moto, seguimiento privado, mesas y POS actualizan sin navegación. Respeta campos y diálogos en edición. Sesiones/permisos vigentes se comprueban en servidor. |
|2. Señas y Google Maps | Se sustituye señas por enlace Maps; ubicación actual con permiso del navegador y búsqueda externa con enlace compartido. HTTPS y permiso requeridos para geolocalización; no se configura Places ni API de pago. URLs validadas, sin descargar enlaces desde servidor. |
|3. Nombre/teléfono/direcciones | Contacto por teléfono normalizado y negocio. Reutiliza contacto, ofrece varias direcciones y Maps; la primera queda principal. Dirección ajena rechazada. |
|4. Cancelación | Administrador o permiso explícito de eliminación, leído de nuevo en servidor, y justificación obligatoria. Usuario, razón y hora quedan en bitácora/auditoría. Conserva bloqueo si ya existen cobros. |
|5. Pedidos | Lista normal con filtros y estado actual, sin columnas Kanban. Diseño probado en1366/900/768/390px, desplazamiento de tabla contenido. |
|6. Venta local | Se reprodujo en código Windows 1.8.0 una lista de usuarios vacía al vincular antes de asignar caja/cajero. WebR9 devuelve lista aprobada bajo autorización nativa; Windows 1.8.1 la guarda antes de dar por autorizada la sesión. Indicador de preparación y botón Caja local. Regresión: venta sin red, reinicio y sincronización única. Conserva principal único. |
|7. SINPE | Imagen obligatoria tanto en formulario como servidor, ligada a navegador/negocio/intento. Reintento seguro. Optimización WebP y archivo privado; roles autorizados pueden verla. No confirma un abono bancario automáticamente. |
|8. Comanda al aceptar | Pedido nuevo no imprime automáticamente. Recepción por vendedor crea una cuenta/comanda una sola vez, aunque se reenvíe la aceptación. |
|9. Aviso POS | Botón Pedidos web con contador en Venta rápida/Mesas y caja; abre bandeja y permite aceptar. Se usa la cola de impresión directa existente. |
|10. Pagos moto | Tarifa completa o porcentaje fijado al asignar. Deuda solo tras entrega/PIN. Historial en moto; cortes seleccionan entregas y cuenta de salida, con reintentos sin doble pago. Prueba real ficticia: ventas con envío4260, corte efectivo250, caja4010; corte banco1000 desde3000 deja2000 y caja4010. Cierre con diferencia0. |
|11. Cuentas | Se extendió Administración → Cuentas de dinero existente: principal, chica, banco/SINPE, otra caja y otra cuenta. Efectivo de turno y saldo de cuenta nombrada son distintos. |
|12. Maps por dirección | Ficha de cliente permite varias direcciones, Maps individual y principal. Cuenta de cliente existente conserva direcciones y puede agregar Maps. |
|13. Acceso automático | Sustituido por tu aclaración: comprar relaciona/crea el contacto mediante teléfono; no crea contraseña con el teléfono ni inicia sesión en una cuenta existente. No concede acceso al historial por conocer un número. |
|14. Historial/bitácora | Situación actual y detalle con acciones y tiempos registrados realmente. Cambios nuevos generan eventos en la misma transacción. No inventa tiempos de pedidos anteriores. |
|15. PIN | Confirmación del pedido muestra PIN y enlace privado de seguimiento para conservarlo. PIN cifrado en BD con la clave existente; motorizado necesita el mismo PIN para entregar. Los PIN históricos con hash no se reemplazan al consultar. |
|16. Incidencias | Tienda abre detalle, corrige dirección/Maps con motivo y resuelve incidencia con solución; continúa con el mismo motorizado en el estado anterior. |

## Evidencia de esta entrega

- QA-OPERACIONES-R9.json:8 grupos HTTP reales con contactos, direcciones, comprobantes, recepción, permisos, incidencia, PIN, pagos y cuentas.
- QA-CAJA-MOTOS-R9.json:6 grupos de cobro, porcentaje/pago completo, fuente de fondos, permisos explícitos y cierre real ficticio.
- QA-NAVEGADOR-R9.json:5 grupos de geolocalización simulada, SINPE, bandeja POS, actualizaciones entre sesiones y4 tamaños de lista; sin errores JavaScript.
- QA-MESAS-VIVO-R9.json:mesa libre→ocupada y diálogo actualizado; cantidad en edición conservada, posterior actualización y botón cantidad funcional con revisión vigente.
- QA-DEMOS-R9.json:pedidos consecutivos SINPE en ambos demos, aislamiento de negocio y descarte de datos/comprobantes temporales. Las plantillas y La Ventanita ficticia permanecen intactas.
- QA-ACTUALIZADOR-R9.json:actualización desde el ZIP R8 publicado y su esquema anterior, con negocio 180/179 y turno abierto. Migración aditiva, estados/datos/código anterior/config conservados, reintento, control maestro/CSRF e integridad.
- QA-PERMISOS-ZIP-R9.json:extracción sobreR8 y carpeta nueva, umask077, ApacheUID33; permisos644/755 sin reparación posterior y archivos privados403.
- QA-SINTAXIS-R9.json:PHP y JavaScript del paquete final; hashes122 y ausencia de configuración/datos/claves privadas.
- QA-WINDOWS181-R9.json:59 pruebas Node, instalador generado y comparación de código empaquetado ASAR con fuente revisada. El fallo anterior se reprodujo contra server.js publicado deR8, antes de validar el arreglo.

La consulta periódica es ligera y evita renderizar toda la página si no hubo cambios. No es una garantía de latencia del hosting ni de entrega exactamente en2s si la red/servidor están lentos. En segundo plano se pausa y al volver se consulta.

## Instalación, conservación y límites

El ZIP tiene122 archivos funcionales en releases/1.8.0-r9. No reemplaza config.php, no importa otra BD ni borra datos de negocios. Actualiza primero webR9 y luego Windows 1.8.1 sobre la instalación actual. No cambies app_key ni borres datos locales. Los negocios 179 conservan compatibilidad hasta activación individual; los180 mantienen activación.

No se recalculan trabajos antiguos como nuevas deudas ni se inventan pagos históricos. El portal los identifica para revisión. El comprobante SINPE no es integración bancaria. Las cargas abandonadas sin pedido caducadas por más de 2 días se limpian en lotes al subir otro comprobante; las de pedidos se conservan. Las pruebas desechadas eliminan sus propios comprobantes.

No se probó el instalador en Windows físico, desconexión física, impresoras, cajón, Android físico, servicios externos reales ni rendimiento del cPanel. La prueba local de Windows ejecuta el servidor nativo con red simulada y persistencia real de archivos. El ejecutable conserva appID/directorio de datos; no tiene firma comercial Authenticode. Android no cambia y conserva el APK1.8.0 publicado. La entrega no despliega a producción por sí sola.

Las verificaciones siguientes corresponden a entregas anteriores y se conservan como historial; no se presentan como ejecutadas nuevamente paraR9.

# Informe QA · FISITAAP 1.8.0-R8

## Preparación y asignación de Express · R8

El tablero usa tres columnas adaptables, con contador, cliente, artículos, tiempo, total y acciones principales: Preparar, Preparado y Asignar a Express. Preparar recibe el pedido y comienza la preparación en una operación. Retiro/mesas/venta rápida usan Entregar. Más opciones conserva acceso a cuenta/cancelación. Los finalizados y los Express activos salen de preparación; estos últimos continúan en Express pendientes. El filtro se aplica antes del límite de 200 por origen y los artículos se obtienen por lote.

La asignación ofrece liberar a afiliados autorizados de la sucursal o seleccionar un afiliado directamente. Revalida permisos vigentes de pedidos/cocina y Express, módulos, negocio/sucursal, afiliación y ocupación del motorizado. El servidor realiza publicación y asignación en la transacción del pedido; un fallo directo no deja un trabajo liberado. Un reintento no cambia el motorizado, monto o PIN de una entrega activa. Se conserva la aceptación exclusiva y el PIN de confirmación del portal. Las demos no se conectan con motorizados reales.

Los Express de caja se vinculan a una solicitud de entrega y a su cuenta original mediante los campos/tablas existentes. Copian cliente, dirección, artículos e importes sin registrar otra venta, cobrar ni descontar inventario. Los cobros parciales permanecen en la misma cuenta. Una cancelación sin cobros devuelve la reserva POS una sola vez; con cobros se rechaza. El tablero y pendientes evitan duplicar la tarjeta vinculada. La referencia de entrega POS se excluye de solicitudes web/historial de compras web; sus cobros se conservan en ventas registradas.

Durante QA se detectó que el CAST numérico usado al inicializar order_sequences fallaba en SQL estricto después de crear una referencia POS-* aun existiendo la secuencia. Las tres rutas ahora convierten solo referencias numéricas dentro de CASE. Se prueba la creación de pedidos web tras las asignaciones de caja.

Evidencia nueva: QA-KANBAN-R8.json (14 grupos: flujo HTTP, transacciones, permisos, cobros, inventario y cinco tamaños de Chromium), QA-ACTUALIZADOR-R8.json (7 grupos de instalación sobre R7 con negocios 179/180 y turno abierto), QA-PERMISOS-ZIP-R8.json (2 extracciones reales 644/755 en Apache sin privilegios) y QA-SINTAXIS-R8.json (74 PHP del ZIP y JavaScript). Los resultados y cantidades están en los JSON de cada ejecución. El manifest actual enumera 116 archivos funcionales en releases/1.8.0-r8. No se accedió al hosting ni se probó impresión física; los instaladores nativos siguen en 1.8.0. Las verificaciones R7 y anteriores siguientes son históricas.

## Bienvenida, pedidos consecutivos y coherencia de Express · R7

Se reprodujeron sobre el ZIP R6 publicado los dos errores operativos: en el mismo catálogo de demo un segundo pedido igual devolvía el primero como duplicado y un tercero con cantidad distinta devolvía «otros datos»; al recargar se generaba otra referencia y desaparecía el síntoma. app.js ahora renueva checkout_key solo después de una respuesta de éxito. Los fallos de validación o comunicación conservan carrito y referencia, y un envío en curso bloquea otra solicitud. Se mantiene la idempotencia del servidor, sin quitar controles para permitir pedidos repetidos.

Express pendientes consultaba únicamente pos_tickets de tipo express, mientras historial/portal del motorizado consultaban delivery_jobs de pedidos web. Se reprodujo que el mismo Express web aparecía en las dos últimas vistas y faltaba en pendientes. La lista agrega esos trabajos activos de la sucursal seleccionada, con negocio y pedido comprobados en el join, estados compartidos en español y cantidades de pedido/pago al motorizado separadas. La adición es de lectura; no confirma cobros, asigna motorizados ni evita el PIN. Las tarjetas de caja mantienen sus acciones existentes. El historial especifica todas las sucursales, incluye activas/finalizadas, identifica la sucursal y ofrece un selector de estados reconocidos. El portal disponible agrega el número de pedido para reconocer la misma operación.

La bienvenida usa un contenedor de la altura visible, descontando encabezado, controles y márgenes internos. La imagen cubre el área con object-fit:cover, conservando proporción y pudiendo recortar bordes. Un caso a 900px reveló el display:block!important del menú móvil heredado; se resolvió dando altura explícita al contenido de la bienvenida sin modificar los otros paneles. La imagen guardada se conserva.

- QA-USABILIDAD-R7.json: siete grupos con R6 y payload R7. Pedidos consecutivos reales de ambos demos; pérdida de respuesta después de que el servidor guardó el pedido y reintento único; corrección de un formulario inválido sin perder carrito; un solo envío ante dos submits; descarte y plantillas/inventario conservados. Express web presente en pendientes/historial/motorizado, aislamiento de sucursal/negocio, filtro y estados; aceptación/recogida/ruta/PIN por HTTP real, PIN incorrecto rechazado y entrega contada una vez. Ocho vistas de bienvenida maestro/negocio179 en Chromium a1366/900/768/390px con imagen cargada, área restante cubierta y sin desbordamiento horizontal.
- QA-ACTUALIZADOR-R7.json: siete grupos de instalación sobre R6 publicado con negocios 180/179 y turno abierto,115 hashes y conservación de datos/config/uploads/código anterior; control maestro/CSRF/cambios desconocidos, repetición sin duplicados y restauración bloqueada tras publicar.
- QA-PERMISOS-ZIP-R7.json: dos extracciones sobre R6 y carpeta nueva con umask077/ApacheUID33, sin corregir permisos después de extraer.
- QA-SINTAXIS-R7.json: los73 PHP del paquete pasan sintaxis con PHP 8.3; app.js pasa node --check.

Datos, conductores y dinero ficticios. No se modificó el hosting. No cambia el código de aplicaciones Windows/Android ni las integraciones de IA/impresión, cuyas pruebas anteriores se conservan como evidencia histórica. No se presenta esta revisión como un QA nuevo de todas las funciones. No requiere importar una base distinta ni modificar config.php. Los estados operativos de caja ya existentes se conservaron y no se afirma una unificación de todo el modelo histórico de preparación/cobro.

## Carga de bienvenida y confirmación de guardado · R6

El usuario confirmó que usa Web oficial → Imagen de bienvenida y recibe «Bienvenida guardada», pero permanece el diseño predeterminado. Se reprodujo en el R5 publicado que una petición sin archivo y una carga multipart rechazada realmente por PHP reciben esa confirmación sin cambiar la imagen. upload_image devuelve null si no hay tmp_name antes de examinar el error; el editor anterior anunciaba éxito y registraba auditoría incluso así. El tamaño concreto y el rechazo de la carga productiva no fueron observados directamente: la prueba identifica una causa posible reproducida, sin atribuir al hosting los límites del laboratorio.

R6 valida los errores de carga dentro del editor de bienvenida, sin modificar el helper general usado por otros formularios. Distingue selección vacía, tamaño rechazado, recepción incompleta, formato inválido y conflicto entre subir/restaurar la predeterminada. Al exceder post_max_size PHP descarta campos y archivos: esa petición se rechaza sin escribir configuración, en lugar de anunciar guardado o presentar una falsa sesión vencida. Las cargas normales siguen exigiendo CSRF y cuenta maestra vigente. El formulario muestra el máximo efectivo (aplicación/PHP y margen multipart), la imagen actual o la ausencia de imagen personalizada. Los cambios se comprueban en base y se guardan junto a su auditoría en una transacción. Los rechazos no crean una auditoría de éxito. El inicio del maestro enlaza al editor y este a la bienvenida publicada; la imagen de la portada pública conserva su configuración independiente.

- QA-CARGA-BIENVENIDA-R6.json: ocho grupos contra R5 publicado y payload R6. Cargas multipart válidas y rechazadas, solicitudes reales de 6/17/25 MiB (límites de aplicación/PHP/POST), conservación de imagen/auditoría, dos imágenes optimizadas con URLs diferentes, maestros y negocios 179/180, permisos/CSRF, restablecimiento intencional y Chromium1366/390px con imagen realmente cargada y sin desbordamiento.
- QA-ACTUALIZADOR-R6.json: siete grupos de instalación sobre R5 publicado, con negocios 180/179 y turno abierto; conserva datos, configuración, imágenes y código anterior; verifica115 hashes, rechazo de modificaciones desconocidas, repetición sin duplicados y restauración bloqueada tras publicar.
- QA-PERMISOS-ZIP-R6.json: dos extracciones reales sobre R5 y en carpeta nueva con umask077/ApacheUID33, sin reparar permisos después de extraer.
- QA-SINTAXIS-R6.json: los73 PHP del ZIP pasan sintaxis con PHP 8.3.

Solo datos ficticios. Ventas, turnos, activaciones, versión operativa por negocio y configuración de portada se conservaron. R6 no cambia código nativo ni proveedor de IA: no se presentan como repetidas las pruebas históricas de esos componentes. No se accedió al cPanel ni se instaló el paquete en producción. Si un intento anterior no llegó a guardar una imagen, debe elegirse de nuevo después de instalar; no se inventa ni recupera un archivo que no fue recibido.

## Acceso y bienvenida compartida · R5

Se reprodujo el defecto de R4: con una sesión de dueño y el negocio desactivado, /admin devuelve500 por una DomainException sin manejar; /owner-login detecta la sesión y vuelve a enviarla a /admin. En R5 se valida el negocio y se limpia esa sesión con un mensaje «Tienda fuera de servicio», conservando la posibilidad de entrar con otra cuenta. Al validar credenciales tampoco se crea una sesión operativa para un negocio suspendido o sin asociación. La cuenta maestra y los clientes globales no se vinculan por suposición a otro negocio. La redirección usa datos de cuenta actualizados. Un maestro que abre /admin es enviado a /master.

La sesión de lectura ya se había cerrado antes del control de acceso. La primera corrección rotaba un identificador sin poder enviar su cookie y perdía el mensaje. Se corrigió: cuando se reabre una lectura se conserva su identificador, se eliminan los datos de autorización y se renuevan CSRF/mensaje. Un acceso posterior válido sí rota la sesión. Las sesiones que siguen abiertas se rotan normalmente. La comparación de identidad del mecanismo176 impide sobrescribir una sesión rotada por otra petición.

Se reprodujo también que la imagen cargada por el maestro se veía en el maestro y negocios180, pero no en pendientes179: la ruta de bienvenida estaba detrás del control de activación individual. R5 aplica la bienvenida compartida cuando la plataforma está publicada, sin cambiar el estado operativo de los negocios. Carga los estilos de esa vista para los pendientes, sin activar su JavaScript de POS180. El editor maestro muestra la imagen actual. Dos cargas reales consecutivas generan URLs distintas y reemplazan la vista para todos al recargar.

- QA-ACCESO-R5.json:6 grupos HTTP, R4 reproducido y R5 corregido; recuperación antes de instalar, sesión/CSRF, maestro, asociación ausente, dueño/colaboradores/clientes y datos conservados.
- QA-BIENVENIDA-R5.json:5 grupos con carga multipart real, versiones179/180, vista previa, reemplazo de imágenes, Chromium390px, prohibición de edición por dueño y estados/ventas/turnos conservados.
- QA-ACTUALIZADOR-R5.json:7 grupos sobre R4 publicado con negocio 180/179 y turno abierto, archivos/datos/config/uploads preservados, hashes y rechazo de cambios desconocidos.
- QA-PERMISOS-ZIP-R5.json:2 extracciones con umask077/ApacheUID33, sin reparar permisos después.
- QA-DIAGNOSTICO-BIENVENIDA-D2.json:5 grupos de autorización vigente, errores/fatales simulados, ocultación de claves y transacción de lectura que prohíbe escrituras.
- QA-SINTAXIS-R5.json: los 73 archivos PHP del ZIP pasan la comprobación de sintaxis con PHP 8.3, incluidos los dos auxiliares de la raíz.

Además se repitió qa/chat180.py sobre R5: sus 11 grupos HTTP de FISIChat pasaron. El resultado es idéntico al informe QA-FISICHAT-HTTP-R4.json conservado; no se volvió a ejecutar el proveedor de IA real ni las pruebas nativas.

Datos y equipos de prueba ficticios. No se cambió el hosting, el estado real de La Ventanita ni los instaladores nativos. El usuario confirmó que reactivar La Ventanita desde el maestro en otro navegador y cerrar la sesión restableció su acceso. La corrección evita el error técnico futuro; las suspensiones comerciales siguen siendo decisiones del maestro. Evidencia R4 y anterior permanece debajo y no se presenta como una nueva ejecución integral de todas las funciones.

## FISIChat público, tiendas y maestro · R4 · 14 de octubre de 2026

La portada deja de abrir un formulario aparte y dispone de un chat en la misma página, con burbujas, sugerencias, respuesta en curso, historial y contacto. Tiendas/landing usan su propia identidad. El maestro tiene un perfil administrativo privado, separado del asesor comercial aunque esté conectado al visitar la portada. El chat avanzado del dueño conserva sus herramientas anteriores.

El conocimiento comercial se limita a funciones actuales: un principal Windows o Android offline, impresión directa configurada en Administración, demos virtuales, módulos y activación por negocio. No se inventan precios; la cotización remite al contacto configurado. La conversación reutiliza la conexión de IA existente. Sin clave o ante un fallo se usa la guía local; esa guía no equivale a conversación libre con un modelo.

El maestro consulta cifras y listas acotadas de negocios reales, vencidos/próximos vencimientos, planes, contactos nuevos de la plataforma y estados de activación. Cambiar plan o vencimiento abre un formulario; preparar no modifica datos y confirmar exige una propuesta guardada en servidor, de diez minutos, y privilegio maestro vigente. Se compara el valor anterior para rechazar propuestas desactualizadas. La operación modifica solamente plan/vencimiento, queda auditada y admite repetición sin una segunda escritura/auditoría. No se envían contraseñas ni claves a la IA. La IA no tiene herramientas de escritura.

La conversación se almacena en sesión por perfil, negocio, usuario/rol y sucursal, por treinta minutos, con doce mensajes. El historial recibido del navegador no se acepta como contexto. Se conserva la solicitud para reintentar, se impide intercalar mensajes y una nueva generación al reiniciar evita que una respuesta tardía reponga el historial borrado. Los mensajes se dibujan como texto, con CSRF y límites de tamaño/frecuencia. La sesión se libera antes de esperar al proveedor.

Pruebas específicas de R4 en PHP/MariaDB y Chromium, siempre con registros ficticios:

- **QA-FISICHAT-HTTP-R4.json:** 11 grupos de comprobaciones de ventas/soporte, contexto, historial/reintentos, límites, precios publicados por sucursal, exclusión de productos ocultos, pedidos solo del cliente y tienda, contactos solo de la plataforma, contraseña obligatoria/privilegio revocado, vencimientos, propuesta/confirmación/repetición y cambios de otro administrador.
- **QA-FISICHAT-NAVEGADOR-R4.json:** 8 flujos a 1366/768/390/360px: respuestas reales del motor local, historial/reset, logo propio, pantalla completa, foco/Escape/ARIA, texto malicioso no ejecutado, respuesta HTTP no JSON sin perder el borrador, propuesta/confirmación del maestro y ajuste móvil. Sin errores JavaScript ni recursos faltantes.
- **QA-FISICHAT-PROVEEDOR-R4.json:** 7 grupos contra un proveedor local por TLS verificado, usando una clave ficticia. Comprueba petición de IA/contexto/historial, límite de tokens/store=false, datos privados del maestro y públicos de tienda, fallo 500 con guía, navegación con la misma sesión mientras tarda la IA, conflicto entre mensajes simultáneos y reset durante la espera. No prueba calidad, precios ni latencia de OpenAI real.
- **QA-ACTUALIZADOR-R4.json:** 7 grupos sobre R3 publicado, negocios 180/179 y turno abierto. Conserva estado/datos/config/uploads/código R3, rechaza cambios externos, verifica 115 hashes y entrada R4, UI pública/maestro, repetición y bloqueo de restauración después de publicación.
- **QA-PERMISOS-ZIP-R4.json:** 2 extracciones reales con umask 077/Apache UID33,115 hashes, 644/755, instalador accesible y payload privado403 sin reparar permisos.
- **QA-SINTAXIS-R4.json:** los 71 archivos PHP del paquete pasan sintaxis con PHP 8.3 y el JavaScript nuevo pasa `node --check`.
- **QA-PUBLICO-R4.json:** regresión del ZIP exacto en portada y La Ventanita, nueve anchos, títulos/botones, diseño/logo, catálogo, negocio pendiente y tema administrativo.

R4 no modifica el motor, instaladores, impresión física ni cálculos de caja. Las pruebas históricas R2/R3 siguientes se conservan y no se presentan como una nueva ejecución completa. No se accedió al hosting ni se ejecutó IA con una clave productiva. El despliegue, velocidad del hosting y equipos físicos requieren comprobación allí. El manifest de R4 enumeraba 115 archivos funcionales en releases/1.8.0-r4; MANIFEST-ARCHIVOS.json corresponde ahora a los 116 archivos de R8.

## Corrección visual · R3 · 14 de octubre de 2026

Se reprodujeron ambos defectos en el navegador con el código R2. A 1317 px, el computador empezaba en y=211 mientras el título terminaba en y=241 y los botones en y=348: cubría texto y controles. El contenedor medía 385 px, pero la ilustración crecía a 554 px. En La Ventanita la navegación, presentación y tarjetas se mostraban como bloques sin diseño; el banner medía 0 px de altura mínima porque no se cargaba restructure-r2.css.

La ilustración ahora participa en una cuadrícula que reserva su altura real y conserva el límite de 940 px, con computador y teléfono alineados abajo. La página del negocio solicita explícitamente sus estilos compartidos (5.4 KB), conservando el JavaScript de POS/salones para las pantallas que lo necesitan. El ejemplo de FISIChat responde directamente: «Tienes dos productos con poco stock: crema facial (3 unidades) y botella térmica (8 unidades). Te recomiendo reponerlos esta semana».

QA-PUBLICO-R3.json verifica 36 vistas públicas en total: portada y La Ventanita en nueve anchos (1366, 1317, 1024, 900, 768, 621, 620, 390 y 360), tanto con código fuente como con el payload exacto publicado en releases/1.8.0-r3. Comprueba que la ilustración no cubra título/botones, que los botones se puedan pulsar, que la tienda tenga navegación/banner/tarjetas adaptados, que conserve su logo, que el catálogo abra, que no se agregue JavaScript de POS a la tienda y que el administrador conserve su tema. También se verifica el diseño de un negocio pendiente en 1.7.9. No hubo errores JavaScript, recursos faltantes ni desbordamiento horizontal. Las imágenes del laboratorio usan datos y un logo ficticios, restaurados al terminar; no se modificó el contenido de la tienda real.

QA-ACTUALIZADOR-R3.json prueba R3 sobre el ZIP R2 publicado con un negocio180, otro179 y un turno abierto. Conserva las versiones, turnos, modificadores, usuarios/productos/ventas, config, uploads y archivos R2; cambia la entrada al nuevo directorio al terminar la copia. Rechaza código R2 modificado fuera del paquete conocido. La repetición no duplica respaldos. QA-PERMISOS-ZIP-R3.json verifica la extracción sobre R2 y en carpeta nueva bajo umask 077, sin reparar permisos después, con Apache UID33 y payload protegido.

R3 no cambia cálculos, base de datos, turnos, impresión ni código nativo. Se ejecutaron pruebas centradas en las vistas y el empaquetado afectados. La evidencia de las 24 suites y las pruebas nativas siguientes corresponde a R2 y a los instaladores1.8.0 sin cambios; no se presenta como una nueva ejecución completa en R3. No se accedió a cPanel ni se probó un Brave físico.

## Corrección de permisos del ZIP · R1 · 14 de octubre de 2026

Se detectó después de la entrega que la umask 077 del entorno de construcción guardaba 600 en los archivos del ZIP y omitía entradas explícitas de carpetas. La prueba original de instalación normalizaba los permisos después de extraer y ocultó este defecto. El constructor ahora fija en el ZIP archivos 644 y carpetas 755, independientemente de la umask. Las pruebas de instalación extraen con unzip y dejan los permisos del ZIP intactos.

QA-PERMISOS-ZIP-R1.json documenta una prueba adicional con Apache ejecutado como www-data (UID 33): el paquete previo extraído bajo umask 022 produce carpetas 755 y archivos 600, como informó el usuario, e impide servir los archivos de prueba. R1 se extrae bajo umask 077 tanto sobre esa misma carpeta como en una carpeta nueva, sin chmod/chown posterior; permite abrir los tres con HTTP 200 y entrar al actualizador con HTTP 302 hacia el acceso. Manifest y payload siguen bloqueados con HTTP 403. Los 109 archivos funcionales del payload son idénticos por SHA256. Si ya existían carpetas con permisos incorrectos, algunos extractores conservan esos permisos y deben revisarse según la guía. Más tarde, las capturas del hosting mostraron que la extracción estaba en /home/fisitaap y el dominio sirve /home/fisitaap/public_html. La guía corrige esa ubicación. La comprobación del hosting se hizo mediante resultados/capturas del usuario, sin acceso remoto a cPanel.

Fecha: 14 de octubre de 2026; se conserva la evidencia inicial del 13 de octubre para el motor nativo sin cambios. Copias ficticias; no se modificó cPanel ni la base real.

## Actualización independiente · R2

Las 24 suites de regresión web/API/navegador se completaron con el código R2. Se conservaron los instaladores Windows/Android 1.8.0: no cambió su código ni firma en esta revisión.

QA-ACTUALIZACION-POR-NEGOCIO.json recoge diez comprobaciones: publicación con dos negocios y turnos abiertos; activación de A sin alterar B; restricción al dueño y CSRF; migración/repetición sin duplicados; snapshots por token A180/B179; una venta calculada por el JavaScript real del cliente 1.7.9, recibida y cerrada correctamente sin duplicar stock/efectivo; republicación que mantiene pendientes y negocios nuevos; activación posterior de B.

QA-ACTUALIZACION-UI.json verifica el menú/botón real de dueño y maestro en Chromium, estados pendiente/actualizado, diseño a 390 px, recursos desde releases/1.8.0-r2 y concurrencia: las lecturas y operaciones de B responden mientras A mantiene su bloqueo de actualización. Una mutación de A espera hasta el límite y recibe un mensaje para reintentar. No se retiene ese bloqueo durante consultas ni llamadas externas de chat.

QA-PERMISOS-ZIP-R2.json comprueba los 112 hashes y la extracción tanto encima de los archivos originales 600 como en una carpeta nueva, con umask 077, sin chmod/chown posterior. Apache UID33 puede entrar al actualizador y los archivos privados del payload quedan con HTTP403. QA-ACTUALIZADOR.json comprueba el paquete R2 sobre tres versiones anteriores y el R1 publicado ya activado, dejando un turno abierto durante la publicación. El caso R1 conserva los negocios en 1.8.0. Los archivos app/assets viejos no se sustituyen; la nueva entrada sirve código y recursos de su carpeta propia, protegida contra acceso directo a PHP.

No se obliga a cerrar todas las cajas para publicar. Al activar, se comprueban los turnos/cuentas registrados de ese negocio y el dueño confirma que sus equipos no tienen ventas locales pendientes y que actualizó sus aplicaciones. El servidor no puede garantizar el estado de una caja desconectada; la confirmación no se presenta como detección automática de su cola local. No se cierra ni elimina efectivo, ventas o turnos por cuenta propia.

## Resultados

- 24 suites de regresión web/API/navegador aprobadas, más las comprobaciones de actualización por negocio, interfaz, permisos e instalación descritas en los informes JSON.
- 57 pruebas Node aprobadas; 1.500 cálculos JavaScript/PHP iguales al céntimo, concurrencia y recuperación; controladores Windows sustituidos en pruebas.
- 16 pruebas Android JUnit aprobadas y lint debug/release; persistencia SQLite cifrada, migración, Unicode/PDF y receptores TCP de prueba para 58/80 mm.
- 84 páginas reales en Chromium a 1366/768/390/360 y 20 vistas adicionales de POS cargado, cobro, mesa ocupada, diseñador y KPIs: sin desbordamiento ni errores JavaScript. Acceso de siete roles.
- XML 4.4 cargado por multipart HTTP: proveedor por identificación normalizada, proveedor/producto nuevo, SKU existente, borrador, recepción repetida, XML cambiado de pestaña y rechazo de entidades externas.
- Dos usuarios, sesiones, equipos y cajas distintos cobrando simultáneamente: una venta y un pago; conflicto humano en la otra caja y arqueos separados.
- ZIP extraído e instalado desde archivos 1.7.8, 1.7.9-r2, mudanza 1.7.9-r3 y R1 1.8.0 ya activado; maestro/CSRF, hashes, rechazo de cambios desconocidos, respaldo protegido, repetición, publicación con turnos abiertos, entrada/recursos versionados y restauración solo antes de preparar/publicar la base.

## Rendimiento

Volumen ficticio: 1.008 productos, 1.002 clientes y 20 salones adicionales con 200 mesas, 60 sectores y 100 objetos. Tres muestras por ruta. Son tiempos del entorno de pruebas, no una promesa para el hosting.

| Pantalla | Consultas | Mediana PHP | Respuesta HTML |
| --- | ---: | ---: | ---: |
| /laventanita/catalogo | 11 | 12.2 ms | 9683 bytes |
| /admin/ventas | 18 | 42.8 ms | 73335 bytes |
| /admin/clientes | 10 | 26.7 ms | 85052 bytes |
| /admin/mesas?room=3 | 24 | 34.7 ms | 67005 bytes |
| /admin/reportes?dataset=sales&from=2020-01-01&to=2030-01-01 | 35 | 27.8 ms | 87155 bytes |
| /admin/reportes?dataset=inventory&from=2020-01-01&to=2030-01-01 | 35 | 32.9 ms | 97079 bytes |

Se separan los tiempos PHP de proceso/red; no se incluye el retraso de 500 ms del evento networkidle como tiempo de PHP. Reportes se midió en el rango 2020–2030. Con catálogo sin cambios, la sincronización entrega existencias y datos de caja en vez de descargar de nuevo productos/clientes. Los catálogos originales se conservan para ventas pendientes e historial.

## Migración y archivos

schema180.php agrega las tablas 180 de cajas, colas de impresión, dinero, borradores, asignaciones de modificadores, mesas vinculadas, usos de cupón, pedidos vinculados, listas compartidas y operaciones idempotentes, y los campos/índices de los módulos relacionados. Se ejecuta por publicación explícita, fuera de las consultas normales. Cada negocio tiene un estado independiente en fisitaap_tenant_updates180, consultado por clave primaria y almacenado en memoria durante la solicitud. La conversión de modificadores, cajas, mesas y roles se limita al negocio que activa y su versión cambia al final de la transacción. No hay DDL durante esa activación individual. Se conservan tablas y registros anteriores.

El manifest histórico de R4 enumeraba 115 archivos; MANIFEST-ARCHIVOS.json corresponde a los 116 archivos de R8 y su SHA-256. Native: desktop/ y android/ contienen el motor compartido, protección de vistas/credenciales, persistencia, impresión y recursos 1.8.0. El ZIP evita config.php, claves privadas, SQL y archivos cargados del negocio.

## Revisión de las solicitudes

REQUISITOS.json relaciona los 42 apartados del QA manual con evidencia. Se revisaron también: un solo principal Windows/Android sin internet, menús completos conectados, Android directo a web o central, impresión sin FISITAAP Print en el flujo normal, identidad de la tienda, estilo de administración uniforme, demos independientes, conservación de La Ventanita e imágenes optimizadas. Las referencias del PDF se compararon con venta/cobro/mesas/diseñador y con la solicitud posterior de bordes rectos.

La velocidad previa del hosting y el certificado del nuevo servidor no pueden verificarse con estos fixtures. FISIChat libera la sesión antes del llamado externo y usa la sucursal seleccionada; la espera/respuesta del proveedor de IA real queda sin prueba porque no se usó una clave productiva.

## Comprobación pendiente en tus equipos

- Ejecutar el instalador en Windows físico y el APK en Android físico; se construyeron y verificaron sus paquetes, no se ejecutaron en esos sistemas físicos.
- Probar papel, margen/corte y gaveta con la impresora/controlador configurados. El ancho 58/80 mm no garantiza compatibilidad ESC/POS por sí solo.
- Verificar HTTPS válido, permisos/propietario PHP, tiempo del hosting y actividad del negocio después de instalar.
- La firma APK v2 se verificó y su clave tiene respaldo privado separado. Windows no cuenta con certificado Authenticode de editor; el instalador no debe describirse como firmado por un editor verificado.

## Informes detallados

- [QA-ACTUALIZADOR.json](QA-ACTUALIZADOR.json): 4 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-ANDROID-UI.json](QA-ANDROID-UI.json): 7 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CATALOGO.json](QA-CATALOGO.json): 7 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-COBRO-SIN-RESPUESTA.json](QA-COBRO-SIN-RESPUESTA.json): 4 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CONFIGURACION.json](QA-CONFIGURACION.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CONTINUIDAD.json](QA-CONTINUIDAD.json): 9 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CUENTAS-COMPRAS.json](QA-CUENTAS-COMPRAS.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CUPONES.json](QA-CUPONES.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-DEMOS.json](QA-DEMOS.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-DINERO.json](QA-DINERO.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-DOS-CAJAS.json](QA-DOS-CAJAS.json): 3 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-FIDELIZACION.json](QA-FIDELIZACION.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-FLUJOS-USO.json](QA-FLUJOS-USO.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-IMPRESION.json](QA-IMPRESION.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-MESAS.json](QA-MESAS.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-NAVEGADOR.json](QA-NAVEGADOR.json): 84 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-PANEL.json](QA-PANEL.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-PEDIDOS.json](QA-PEDIDOS.json): 10 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-PERMISOS.json](QA-PERMISOS.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-POS.json](QA-POS.json): 7 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-RENDIMIENTO.json](QA-RENDIMIENTO.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-REPORTES.json](QA-REPORTES.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-XML.json](QA-XML.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-ACTIVACION-DEMOS.json](QA-ACTIVACION-DEMOS.json): 2 comprobaciones; alcance indicado en el archivo.
- [QA-SUCURSAL-NATIVA.json](QA-SUCURSAL-NATIVA.json): 1 comprobaciones; alcance indicado en el archivo.

- [QA-ACTUALIZACION-POR-NEGOCIO.json](QA-ACTUALIZACION-POR-NEGOCIO.json): 10 comprobaciones de actualización por negocio y versiones mixtas.
- [QA-ACTUALIZACION-UI.json](QA-ACTUALIZACION-UI.json): menú, estados, recursos y concurrencia por negocio.
- [QA-PERMISOS-ZIP-R2.json](QA-PERMISOS-ZIP-R2.json): dos extracciones sin reparar permisos y 112 hashes.

- [QA-PUBLICO-R3.json](QA-PUBLICO-R3.json): 36 vistas públicas, nueve anchos y comprobaciones de CTA, logo, catálogo, versión pendiente y tema administrativo.
- [QA-ACTUALIZADOR-R3.json](QA-ACTUALIZADOR-R3.json): instalación sobre R2 con estado mixto y turno abierto.
- [QA-PERMISOS-ZIP-R3.json](QA-PERMISOS-ZIP-R3.json): dos extracciones, 112 hashes y acceso Apache UID33.
