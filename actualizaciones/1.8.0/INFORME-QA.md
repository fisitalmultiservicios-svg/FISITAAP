# Informe QA · FISITAAP web 1.8.0-R22 / Windows 1.8.3

Fecha: 31 de octubre de 2026 (Costa Rica). Corrección acumulativa sobre R21.

| Solicitud | Resultado |
|---|---|
| Venta rápida encuadrada arriba en desktop y web | Marco de altura disponible, cabecera/caja compactas, factura mayor a la izquierda, listas internas independientes y total/Cobrar visibles. Categorías en una fila horizontal. Conserva el desplazamiento al agregar. |
| Mesas y cobro se pierden o no desplazan bien | La cuenta desktop comparte el marco; celular conserva atención continua. El cobro separa cabecera y confirmaciones del cuerpo desplazable. |
| Pantalla completa se desactiva al navegar | Windows 1.8.3 controla la ventana nativa, conserva la preferencia y permite salir con botón/F11. El navegador conserva su modo entre páginas con F11, según sus permisos. |
| Direcciones, zonas y Maps solo en Delivery | Bloque oculto/deshabilitado en retiro/coordinado; no se envían esos campos. Conserva los datos al volver, admite direcciones del teléfono y descarta datos antiguos fuera de Delivery en el servidor. |

Validación: `QA-PANTALLAS-R22.json` (8 recorridos, 22 casos de encuadre), `QA-MESAS-R22.json` (5 tamaños del plano gráfico), `QA-LOCAL-R22.json` (5 grupos con Node real y pantallas locales), `QA-DELIVERY-R22.json` (6 grupos de interfaz/HTTP), `QA-REGRESION-R22.json` (7 flujos de precio/comanda/precuenta/justificación), `QA-NATIVO-R22.json` (57 pruebas Node y payload del instalador), `QA-ACTUALIZADOR-R22.json` (R21→R22 en base separada) y `QA-SINTAXIS-R22.json` (ZIP final, 158 archivos y hashes/permisos).

Se usaron Chromium, PHP/MariaDB y datos ficticios; el puente instalado y la impresora se simularon. No hubo impresiones físicas, cobros reales, WhatsApp, IA externa ni despliegue en cPanel. No se probaron un escritorio Windows o dispositivo Android reales. El instalador Windows contiene los fuentes revisados; APK 1.8.0 se conserva. No cambian ventas anteriores, contraseñas, fondos ni la página comercial de presentación.

---

# Informe QA · FISITAAP web 1.8.0-R21

Fecha: 30 de octubre de 2026 (Costa Rica). Corrección acumulativa sobre R20; conserva Windows1.8.2 y Android1.8.0.

| Aviso del usuario | Corrección |
|---|---|
| Combo mostrado a ₡5.000 y agregado a ₡0 | Campo promocional legado 0/0.00 ya no anula el precio normal. Creación de combo inicializa sin promoción oculta. Tarjeta y línea usan una regla común, con precios/asignaciones de sucursal. Se conservan promociones explícitas, IVA y extras. |
| «No se recibió un comprobante» después de enviar comanda | La cola de comanda del servidor devuelve el pedido; el interceptor antiguo ya no exige un recibo de esa respuesta ni intenta imprimirlo de nuevo. Se comprobó que se guarda una vez y que la precuenta conserva su impresión nativa. |
| Desktop no ofrece justificación | Ventana HTML con textarea, mínimo 8 caracteres, Volver y Confirmar. Formularios heredados también reciben una ventana compatible con Electron. Motivo auditado, permisos verificados y restitución única de existencias. |

Se reproduce el flujo normal de crear en Combos con componentes y precio5000; se cubre además el campo sin usar en0/0.00. No se accedió a la base de producción para atribuirle una configuración concreta. Las líneas históricas se preservan; las erróneas anteriores requieren quitar/agregar de nuevo o cancelar justificadamente según su estado.

Validación: `QA-PRECIOS-R21.json` (8 grupos, HTTP/MariaDB y calculador local Node real), `QA-PANTALLAS-R21.json` (7 recorridos con puente instalado simulado, prompt/confirm bloqueados y 3 tamaños), `QA-COBRO-R21.json` (12 grupos de regresión de cobros/turnos/salidas), `QA-ACTUALIZADOR-R21.json` (R20→R21 en base separada), y `QA-SINTAXIS-R21.json` (ZIP exacto, 156 archivos, hashes, PHP/JS y permisos644/755).

No se modificaron precios de ventas cobradas, contraseñas ni fondos. No se hicieron impresiones físicas, cobros reales, llamadas de IA ni mensajes externos. No se reinstalaron binarios Windows/Android ni se desplegó en el hosting público. Para el modo local sin internet se debe renovar el catálogo por sincronización; sus operaciones pendientes conservan sus precios originales.

---

## Historial de entregas anteriores

# Informe QA · FISITAAP web 1.8.0-R20 y Windows 1.8.2

Fecha: 30 de octubre de 2026 (Costa Rica). Acumulativo sobre R19. Presentación comercial principal sin cambios de diseño.

| Solicitud | Resultado |
|---|---|
| Botón Solo cobrar | Registra la venta sin encolar recibo. Se mantiene la trazabilidad y su comprobante consultable. |
| Botón Cobrar e imprimir | Registra una vez y usa la selección de salida debajo del botón. Ambos botones validan los mismos importes y vendedor. |
| Carta PDF / recibo PDF / impresora | PDFs reales en servidor; descarga automática y recuperación manual. La selección física usa el perfil y equipo configurados; no vuelve a imprimir al repetir un POST. |
| Impresora carta | Nuevo perfil Carta para recibo, Windows 1.8.2 usa controlador seleccionado y tamaño Letter. Rechaza carta por ESC/POS. No abre gaveta de térmica con carta. |
| Permitir cobro web opcional | Generales ofrece solo Windows/Android o también web. Predeterminado conserva solo instalados. Rol y tienda activos siguen siendo obligatorios. |
| Usuario web abre su caja | Caja numerada propia por usuario/sucursal, siguiente número libre en negocio. Asignación transaccional, sin duplicados con aperturas simultáneas. Solo crea caja al abrir realmente. |
| Otro usuario sin turno | Atiende ventas sin cobrar hasta abrir su propia caja/turno; IDs enviados por formulario no prestan autoridad ajena. |
| Fondos, cierres y reintentos | Fondos separados de efectivo/tarjeta/SINPE, solo el abridor cierra, turno siguiente conserva sus cuentas, desactivar web exige cerrar turnos web. Reintento no duplica cobro ni impresión. |
| Caja instalada / Caja0 | La habilitación web no convierte una autorización nativa de Caja0 en caja cobradora ni cambia la caja instalada. |
| Pantallas y manual | Acciones y selector accesibles en computadora/tablet/celular y poca altura. Academiaap, guía y versión anunciada actualizadas. |
| Windows local | 1.8.2 añade la misma selección y conserva elección al comprobar una venta pendiente de respuesta. No altera identificadores, sincronización ni datos guardados. |

Pruebas y resultados exactos se incluyen en los JSON R20 y en el cierre de entrega. No se realizaron cobros reales, transferencias, WhatsApp, llamadas de IA ni impresión física.

Limitaciones: el APK publicado sigue en 1.8.0 (sin nueva compilación/firma; SDK/clave no disponibles). Controles de web conectada se actualizan con cPanel; pantalla local nueva de Android y PDF carta nativo preparados en código requieren su siguiente compilación. Un WebView sin descarga debe usar el navegador para descargar PDF. Para carta física desde Android/web se configura un Windows 1.8.2 como destino. Binario Windows construido, pendiente de prueba en equipo/controlador físicos. Este trabajo no instala el hosting.

## Validación final

- `QA-COBRO-R20.json`: 12 grupos HTTP/CSRF/MariaDB: permisos, turno propio, numeración/fondos, aperturas concurrentes, cambio simultáneo de política, reintentos, PDFs e impresión configurada.
- `QA-PANTALLAS-R20.json`: 6 recorridos reales de web y 14 tamaños, incluidos tablet, celular, horizontal y poca altura; descargas verificadas y reenvío asíncrono del puente instalado conservando la elección.
- `QA-LOCAL-R20.json`: 4 grupos con motor Node real y Chromium; 5 tamaños, botones/salidas y pérdida de transporte con elección original conservada. Impresora simulada.
- `QA-CONTINUIDAD-R20.json`: 9 grupos de continuidad offline, catálogo compartido, adopción de turno, confirmación única y cierre conciliado.
- `QA-PEDIDOS-R20.json`: 3 grupos de Catálogo/Menú, cliente/teléfono/zona, aceptación/comanda una vez y monitor.
- `QA-PDF-R20.json`: 3 formatos largos, 240 líneas cada uno; Poppler valida tamaño carta/58/80 mm, contenido, acentos, saltos y paginación sin recortes.
- `QA-ACTUALIZADOR-R20.json`: 7 grupos, instalación real sobre ZIP R19 en base separada, conservando datos y permisos/CSRF, sin modificar config/uploads/código anterior y con reinstalación segura.
- `QA-SINTAXIS-R20.json`: 156 archivos de carga, 91 PHP funcionales, 95 PHP totales del ZIP, 16 JavaScript, hashes y guía coincidentes, permisos portables 644/755.
- `QA-NATIVO-R20.json`: 54 pruebas Node aprobadas; incluye contratos de driver Letter, PDF y cola duradera. Instalador Windows 1.8.2 construido; sus fuentes empaquetadas se compararon byte a byte con las revisadas. No sustituye una prueba física de Windows/controlador/impresora.

Los historiales, contraseñas, fondos y avances anteriores se conservan. Los datos de QA son ficticios. APK nativo nuevo pendiente, como se detalla arriba. No se instaló la actualización en el hosting público.

---

## Historial de entregas anteriores

# Informe QA · FISITAAP web 1.8.0-R19

Fecha: 30 de octubre de 2026 (Costa Rica). Actualización acumulativa sobre R18, con las solicitudes nuevas de atención, usuarios y mesas. La presentación comercial principal conserva su diseño.

| Solicitud | Resultado R19 |
|---|---|
| Venta rápida sin Ver turnos | Solo Abrir turno o Cerrar turno para la caja instalada y usuario autorizados. Cerrar abre su conteo directamente y vuelve a la venta. No ofrece turno de dinero a Caja0. |
| Quitar Más acciones | Acciones pequeñas visibles en una barra superior que permanece al desplazarse. No hay desplegable ni menú recortado. El refresco automático tampoco reinserta los botones viejos. |
| Prioridad de búsqueda | El foco vuelve a Buscar producto al preparar la venta o la siguiente carga; no interrumpe cantidades, formularios, diálogos ni FISIChat. Conserva búsqueda, categoría y posición al agregar y al enviar comanda. |
| Chat tapado por elementos | FISIChat abre en la capa modal del navegador por encima de barras y menús. Conversación desplazable y compositor fijo; ajuste a teclado, poca altura y pantalla horizontal. |
| Lista y edición de usuarios | Usuarios tiene su lista propia con Editar. El formulario nuevo aparece solo al pulsar Nuevo usuario. Conserva cuentas anteriores, incluso las pendientes de asociar a un empleado. |
| Selectores con usuarios del negocio | Marcar consulta y refresca la lista actual de usuarios activos de la misma tienda. El acceso conectado en la aplicación instalada obtiene un directorio autenticado por su equipo, aunque su caché solo tuviera el usuario de prueba antiguo. Sin hashes ni claves en la página. |
| Usuario requiere empleado | Control de personal registra la persona sin contraseña ni rol. Usuarios crea su acceso seleccionando un empleado propio existente. Impide empleado extranjero o un segundo usuario para la misma persona. El propietario conserva su cuenta inicial. |
| Separación de empleado y acceso | Editar expediente no cambia contraseña, correo de acceso, nombre rápido ni rol. Editar usuario conserva su expediente y permisos personalizados. Desactivar empleado retira su acceso. Registro básico disponible para planes sin Personal avanzado, sin borrar salarios/vacaciones previos. |
| Navegador como Caja0 | Identificación automática de atención: abre cuentas, carga y envía comandas; no cobra ni abre turnos de dinero. Sus cuentas nuevas no bloquean el cierre de un turno físico ajeno. El cobro posterior pertenece a la caja numerada y su cajero. |
| Más acciones cortado en celular | Eliminado: la barra visible se ajusta al ancho y sigue accesible en tamaños pequeños y poca altura. |
| Mesa con todo en un lugar | Cuenta, productos, nota, descuento, precuenta, envío a comanda y seguimiento en la misma vista. Estado actualizado cada 2 segundos. Tras enviar comanda se continúa sobre la misma cuenta, sin reenviar líneas anteriores. |
| Cargar productos en celular | Una página natural para productos y cuenta, sin dos paneles ocultos ni desplazamientos internos que atrapen el gesto. Categorías en una fila; botones Productos/Cuenta para ir al sector. No muestra cuentas ajenas a esa mesa. |
| Manuales y configurador | Academiaap y guía actualizadas. El configurador separa Empleados y Usuarios; una instalación conserva el paso equivalente del avance pausado y no lo mueve de nuevo al reinstalar. |

## Comprobaciones

- **8 grupos HTTP/PHP/MariaDB:** alta independiente de empleado, requisito para usuario, edición/login reales, ámbito por tienda, directorio de equipo con caché obsoleta, rechazo de usar ese permiso para cobrar, Caja0, enviar comanda y continuar, cierre propio y registro básico que preserva salarios/saldos.
- **9 recorridos de navegador y 51 combinaciones de pantalla:** 320–1440 px, tablet, horizontal, poca altura y teclado aproximado con viewport reducido. Comprueba botones fijos, búsqueda sin robo de foco, formularios Usuarios/Personal, mesa con envío y nueva carga conservando el filtro, apertura/cierre propios y chat por encima de la pantalla con compositor visible. La captura de la presentación oficial permanece idéntica.
- **9 grupos de continuidad offline:** catálogos compartidos, delta de existencias, turno web adoptado, ventas recibidas una vez, respuesta perdida y cierre conciliado. No se cambian los permisos offline del equipo para mostrar el directorio online.
- **3 grupos de pedidos:** separación Catálogo/Menú, aceptación con comanda una vez, Monitor, entrega directa, zonas, teléfono y color del catálogo conservados.
- **Motor nativo real de Windows, 1 contrato comprobado:** el puente existente transporta la consulta del directorio sin exponer token/hash, conceder cobro, reemplazar usuarios offline ni modificar ventas/turnos pendientes. No requiere recompilar el instalador.
- **7 grupos del actualizador sobre el ZIP R18 publicado:** esquema previo reconstruido en una base independiente; conserva contraseñas, usuarios, relaciones de empleado, salarios, progreso, fondos, ventas y turnos abiertos. Agrega identidad independiente de empleado y marca de atención. Conserva config/uploads/árbol R18, rechaza archivos activos desconocidos y verifica 152 hashes. Reinstalar conserva contactos vacíos, empleados sin usuario y cuentas desactivadas, sin crear usuarios ni otro respaldo.
- **Sintaxis/integridad:** 152 archivos funcionales; 89 PHP de payload, 93 PHP del ZIP y 15 JavaScript. Hashes y permisos explícitos644/755; extracción bajo umask077.

Las pruebas usan exclusivamente datos ficticios y el protocolo nativo simulado donde se indica. No se enviaron mensajes, correos, cobros bancarios ni impresiones físicas. Las capturas muestran la aplicación local de QA, no el hosting real.

## Instalar y alcance

Respalda archivos y base de datos. Extrae el ZIP en la raíz real del dominio (`public_html`) y ejecuta **/actualizador-180/index.php** como maestro. Conserva **releases/1.8.0-r19**, config.php y uploads. No importes SQL. La publicación en GitHub no instala el hosting.

Actualiza la web utilizada dentro de Windows1.8.1/Android1.8.0; no reconstruye ejecutables ni firma APK. El directorio conectado se actualiza desde el negocio autorizado; sin conexión se conserva el acceso previamente sincronizado. Las verificaciones físicas de Windows/Android/iOS, teclado y térmicas se hacen después de instalar. Las estimaciones laborales de R17 mantienen sus límites anteriores; esta entrega no cambia fórmulas ni certifica normativa.

ZIP: **FISITAAP-1.8.0-CPANEL-R19.zip** · 1,743,023 bytes.

SHA256: `1f8f13bb86727df27835894370680c37738828d0215c6852eadd690697bd8009`

---

## Informe de la entrega anterior

# Informe QA · FISITAAP web 1.8.0-R18

Fecha de entrega: 29 de octubre de 2026 (Costa Rica). Revisión acumulativa sobre R17. Se corrigieron las solicitudes nuevas y se conservan los archivos históricos.

| Solicitud | Resultado R18 |
|---|---|
| Venta rápida sin sectores ocultos | Altura calculada con los controles reales de la factura. Conserva encabezado, cliente, totales y botoneras. Factura izquierda más amplia; pestañas en teléfono y desplazamiento accesible con poca altura o teclado. |
| Cancelar una cuenta en ₡0 | Según la aclaración: solo al retirar el último producto de una cuenta sin cobros. Devuelve inventario una vez, libera mesas unidas y deja bitácora. Una cuenta con productos y descuento del 100 % sigue abierta. |
| Abrir turno en Venta rápida | Barra visible Caja y turno con apertura en ventana, fondo inicial y retorno a la venta. Controles del turno propio y entrada/salida de efectivo. |
| Usuarios en desplegables | Acceso del equipo instalado con nombres rápidos, selector de empleado al marcar, vendedor y asignaciones. El acceso público conserva correo/usuario escrito: no publica un directorio global de tiendas. |
| Nombre rápido de empleado | Obligatorio al crear/editar desde Personal o Usuarios; único dentro de la empresa. Correos únicos en toda la plataforma. Un alias ambiguo en el acceso público exige usar el correo; el selector instalado identifica su cuenta exacta. |
| Contraseñas desde 4 dígitos | Alta, restablecimiento y cambio admiten cuatro caracteres o más, incluidos cuatro dígitos. Conserva contraseñas existentes. Comprobado también el acceso temporal y cambio obligatorio. |
| Avisos del dueño en FISIChat | Cumpleaños hoy/próximos 7 días, vacaciones asignadas próximas, saldos estimados para revisar, salida de contrato próxima, existencias y cuentas vencidas según módulos. Revisar abre la pantalla; Visto persiste por propietario. Disponible sin IA y aislado por tienda. |
| Caja por dispositivo; Caja 0 | Un Windows/Android vinculado por caja. Número 0 abre/atiende cuentas sin turnos de dinero, cobros ni venta offline. Positivos cobran con su propio turno. Se rechazan números positivos activos duplicados. Cajas previas conservan cobro y reciben número positivo. |
| Vendedor de la venta | Switch en Generales; cuando está activo, selector obligatorio de colaborador de la misma tienda al cobrar conectado. Reporte distingue vendedor y cajero. El vendedor no cambia al responsable del turno. |
| Cierre exclusivo del turno | Solo el usuario que lo abrió puede cerrarlo en esa caja. El dueño tampoco cierra el turno de otra persona. Se conservan validaciones de cuentas pendientes, conteo y reintentos. |
| Fondos independientes | Efectivo por turno; Tarjeta/SINPE/Banco contables por caja. Cobros nuevos Tarjeta/SINPE acreditados una vez. No inflan efectivo. Selector de fondos limita cada equipo a su caja. Saldos anteriores no se redistribuyen. |
| Opcionales/extras antes del producto | Configurador reordenado: categorías, opcionales/extras y después productos. Conserva pausa y avance. |
| Menú en celular y Mesas | Plano con proporción original, zoom y opciones actuales. Tocar mesa normal crea o abre su cuenta real para cargar productos; Volver a mesas regresa al salón. Barra o varias cuentas conservan selección inicial. |
| Manuales y funciones nuevas | Academiaap incorpora cajas, fondos, vendedor, accesos, avisos, mesas y cuenta vacía; guía de instalación/uso actualizada. |

## Pruebas de esta entrega

- Operación: 8 grupos con HTTP, CSRF, PHP y MariaDB reales. Incluyen cuatro dígitos, alias entre tiendas, turnos, mesas unidas, descuento total, Caja 0, vendedor, fondos y avisos.
- Formularios: 4 grupos; switch real de Generales, selector instalado, alta de cajas con cuentas independientes y alta/cambio de contraseña desde Usuarios sin módulo Personal.
- Navegador: 7 recorridos y **120 combinaciones de pantalla**, de 320 a 1440 px, tablet, horizontal y poca altura. Comprueba que la factura no se desplaza dentro de un área recortada. La presentación oficial conserva su captura idéntica.
- Continuidad offline: 9 grupos; catálogo compartido estable con nombres repetidos, deltas de existencias, adopción del turno web, venta recibida una sola vez, reconocimiento de respuesta perdida y cierre conciliado con movimientos web.
- Pedidos: 3 grupos; Catálogo sin cocina/comanda, Menú imprime al aceptar una vez, Monitor prepara y Pedidos entrega; zonas, teléfono y diseño conservados.
- Actualizador: 7 grupos sobre el ZIP R17 publicado y esquema R17 en una base independiente. Conserva usuarios/contraseñas, saldos/movimientos, ventas/productos, Personal, Academiaap, configurador, versiones mixtas 179/180 y turnos abiertos. No altera config.php, uploads ni árbol R17. Rechaza código activo modificado; reintentar no duplica respaldo ni reactiva cuentas deshabilitadas.
- Sintaxis e integridad: 148 archivos funcionales, 87 PHP de payload, 91 PHP del ZIP y 14 JavaScript; hashes y permisos explícitos 644/755. Extracción comprobada con umask077.

Los archivos JSON de cada prueba acompañan la descarga. Las capturas usan registros ficticios. No se usaron negocios reales ni se enviaron mensajes, pagos bancarios o impresiones físicas.

## Instalación y alcance

Respalda archivos y base de datos, extrae el ZIP en la raíz real del dominio (`public_html`) y ejecuta **/actualizador-180/index.php** con sesión maestra. Extraer solo no instala. Conserva **releases/1.8.0-r18**. No importes SQL ni cambies config.php. La publicación del repositorio no instala el hosting.

Esta entrega actualiza la web que usan las aplicaciones instaladas existentes; no reconstruye ni firma ejecutables/APK. La nueva pregunta de vendedor funciona conectada; offline conserva el operador original para admitir las ventas pendientes anteriores. Marcas y avisos necesitan conexión. Se verificó el protocolo nativo con equipos ficticios; la comprobación final de Windows/Android/iOS e impresoras físicas se hace después de instalar. Los cálculos laborales de R17 mantienen su carácter de estimación, sin certificación legal ni pagos automáticos.

Archivo: **FISITAAP-1.8.0-CPANEL-R18.zip** · 1,724,350 bytes.

SHA256: `88192bbbaa904fe4c9cdda52ba8ef101e16ce64a483bc83d90c067ba096f9b04`

---

## Informe de la entrega anterior

# Informe QA · FISITAAP web 1.8.0-R17

Fecha de entrega: 29 de octubre de 2026 (Costa Rica). Actualización acumulativa sobre R16. Este informe distingue lo comprobado en el entorno local de lo que necesita revisión en el hosting o dispositivo físico.

| Solicitud | Resultado R17 |
|---|---|
| Cliente nuevo por celular/celular | Alta unificada en panel, caja y pedido web. Acceso inicial inmediato; se conserva contraseña cambiada. El catálogo ofrece acceso de su tienda y el acceso general detecta teléfonos ambiguos entre tiendas. |
| Eliminar clientes | Eliminación del listado mediante archivo por tienda, motivo y permiso; conserva historial y bloquea deuda o documentos abiertos. |
| Cajero con acceso asignado | Correo o alias único; creación/vinculación desde Personal y conservación de rol personalizado. Correo duplicado rechazado globalmente al crear/editar. |
| Comanda y cocina solo en Menú | Catálogo oculta y rechaza cocina/mesas/monitor; no encola comanda y no muestra sus destinos. Menú imprime al aceptar una vez. |
| Pedidos listado; Monitor prepara | Lista con estado, cuenta y bitácora. Preparar/Preparado se ejecutan en Monitor de Menú; asignar Express desde Pedidos. |
| Sin Entregas | Oculta y bloquea herramientas de motos. Domicilio sigue activo como entrega directa del negocio, sin tarea de motorizado. |
| Zona por dirección | Panel, cuenta, caja Express y checkout vinculan direcciones con zonas activas propias. Elegir dirección existente devuelve su zona. |
| Portada del catálogo | Color de texto configurable y validado; conserva slider y tiempo. |
| Menú antiguo del comprador | Navegación de compras independiente de la antigua navegación informativa. |
| Venta rápida e imagen faltante | Textos coherentes para categorías/búsqueda/sin resultados y accesos de Menú ocultos en Catálogo; ícono en blanco «Solicita la imagen». |
| Manuales y aprendizaje | Academiaap por rol, progreso persistente, práctica en pantalla real y botón para volver al curso. Lecciones de las funciones nuevas y operativas existentes. |
| Primeros tres días y pausa | Invitación desde primer uso; configurador por pasos persistente y acceso permanente para retomar. |
| Personal y usuarios | Expediente privado, contrato/datos, acceso del colaborador, horario, estado, bases salariales y permisos del módulo. |
| Marcar en instalado | Interfaz visible solo con puente Windows/Android; servidor exige vinculación real. Credenciales por persona, secuencia, hora servidor, idempotencia y correcciones justificadas conservando original. Requiere conexión. |
| Vacaciones/aguinaldo/despido | Estimaciones guardadas y desglosadas; salarios reales, causa/antigüedad, régimen de vacaciones, descansos y feriados estándar. No generan pago. Vigencia jurídica pendiente de verificación externa. |
| Bienvenida siempre | Entrada principal de maestro y negocio muestra Bienvenida; Resumen tiene enlace independiente. |
| Fidelización y promociones | Formularios y tarjetas ordenados, accesos entre beneficios y lección de ayuda. |

## Validación

- `QA-PLATAFORMA-R17.json`: HTTP/CSRF/PHP/MariaDB reales: accesos, contraseña cambiada, archivo del cliente, zonas, creación de cajero, correo repetido, aislamiento, horario, vacaciones y superposición, cálculos con bases conocidas, vinculación nativa, marcas/reintentos/secuencia, corrección con original intacto, progreso y pausa.
- `QA-PEDIDOS-R17.json`: checkout nuevo real, acceso automático, zona guardada y reintento; Catálogo sin comanda y entrega directa; Menú con una sola impresión al aceptar, preparación exclusivamente en Monitor y entrega desde Pedidos; color del slider y rechazo de inyección CSS.
- `QA-NAVEGADOR-R17.json`: cinco recorridos y 55 combinaciones de pantalla/tamaño de 320 a 1440px, sin desbordamiento horizontal ni errores JavaScript. Incluye práctica/retorno/progreso, invitación/pausa y formulario nativo de marca con credenciales reales de prueba.
- `QA-INTERFAZ-R17.json`: ocho recorridos y178 combinaciones. Venta real con autorización nativa, cantidades/cliente, cobro, inventario, cupón, recibo, plano de mesas/zoom, menú compartido y paneles. La captura de la presentación oficial permanece idéntica en el entorno de prueba.
- `QA-ACTUALIZADOR-R17.json`: instalación sobre el ZIP R16 publicado, en segunda base ficticia. Crea el esquema aditivo y conserva registros, config, uploads, versiones179/180 y un turno abierto. Maestro/CSRF, rechazo de código personalizado desconocido, rutas privadas, permisos y reintento. Recupera solo una cuenta automática sin uso y conserva la contraseña cambiada después; expedientes, salarios, progreso/configurador y tickets sobreviven a la repetición.
- `QA-SINTAXIS-R17.json`: 144 archivos funcionales,85 PHP de payload,89 PHP del ZIP y13 JavaScript. Manifiesto, guía, hashes y permisos644/755 del archivo final.

## Límites explícitos

Las pruebas usan datos ficticios y transporte nativo simulado, con servidor y base de datos reales. No se probaron impresoras ni equipos Windows/Android físicos en esta revisión. No hubo mensajes de WhatsApp, correos, pagos reales ni cambios de hosting.

Los cálculos laborales son estimaciones para revisión. El entorno bloqueó las fuentes oficiales SCIJ/MTSS; no se afirma validación jurídica vigente. Se referencia Código de Trabajo (28–29, 136–139, 152–157) y Ley 2412. Casos de plazo/obra, jornadas excepcionales, salarios incompletos, interrupciones, regímenes especiales o traslados de feriados necesitan revisión. No se automatizan planilla fiscal/CCSS, mínimos salariales, sanciones ni pagos. Marcar necesita conexión y no sustituye el registro de ventas offline.

El paquete se instala en cPanel por la cuenta maestra. Su publicación en el repositorio no actualiza automáticamente `fisitaap.com`.

---

# Informe QA · FISITAAP web 1.8.0-R16

Fecha: 29 de octubre de 2026. FISIChat operativo para el dueño, soporte público con tickets en el maestro y catálogo online más compacto. Se conserva la aplicación de R15 y la conexión privada de IA existente.

| Solicitud | Resultado |
|---|---|
| El asistente del dueño debe operar su tienda | La ventana moderna conecta con herramientas de consulta y preparación de cambios. Consulta su negocio, propone ideas y prepara categorías, productos, clientes, cupones, zonas, proveedores, grupos de opcionales y textos/contacto/colores. Muestra los valores de una propuesta y los guarda sólo tras confirmar. |
| Mantener sus límites | Tienda/sucursal fijadas por servidor; cuenta dueña activa, permisos y módulos comprobados nuevamente al confirmar. La IA no recibe SQL ni puede autoconfirmarse. No hay herramientas de cobro, apertura/cierre de caja, anulación, claves, permisos, planes o código para el dueño. Los accesos antiguos no ejecutan escrituras saltándose la revisión. |
| Evitar duplicados o sobrescribir cambios | Token ligado a cuenta/tienda/sucursal, vigencia15min, transacción, comparación del estado previo y bitácora. Confirmar otra vez devuelve el mismo resultado. Descartar, reiniciar la conversación o vencer la propuesta impide aplicarla. Si el registro fue editado desde otra pantalla se necesita una revisión nueva. |
| Soporte público sin datos sensibles | El asesor de la web oficial conserva contexto público y carece de herramientas del dueño. Una consulta sin resolver ofrece recopilar datos y crear un ticket. También existe Solicitar soporte. Los casos guardan contacto, tienda, problema y últimos mensajes pertinentes, con omisión de patrones comunes de secretos. No se envían mensajes externos. |
| Tickets en maestro | Administración → Tickets de soporte: filtros, detalle, contacto/tienda, conversación, estados y notas internas fechadas. Solicitudes anónimas quedan como declaradas; una cuenta dueña válida se asocia desde el servidor a su tienda. La lista participa en la actualización automática y el chat maestro consulta pendientes. |
| Productos un poco más pequeños | Imágenes de155px en pantallas amplias y120px en celular, menos espaciado y 5/4/3/2 columnas según ancho. Conserva color, cantidad, opciones, carrito y acceso de clientes. |

## Evidencia R16

- **QA-FISICHAT-R16.json:** seis grupos HTTP con PHP/MariaDB reales. La revisión no escribe, reintento de propuesta/confirmación no duplica y audita una vez; rechazo de otras cuentas/tiendas, montos inválidos, fracciones por unidad y campos no autorizados. Creación real de producto, publicación de sucursal e inventario inicial. Precios con conflicto de edición, expiración, descarte, reinicio y revocación de cuenta. Opcionales con identidad de grupo y asignación, cupón con fecha inclusiva y textos web con valor anterior. Soporte público, idempotencia, omisión de contraseña ficticia, contacto visible sólo en maestro, estado/historial y vínculo de dueño fijado por servidor.
- **QA-PROVEEDOR-FISICHAT-R16.json:** cinco grupos con Responses simulado por TLS verificado y clave ficticia. Una petición natural del dueño ejecuta una consulta real de categorías y obtiene una propuesta del servidor. El proveedor intentó autoconfirmar: no se escribió hasta confirmar desde el usuario. Las ideas no crean registros. Un intento de usar una herramienta antigua para abrir caja se rechaza. Se comprueban sustitución de un falso anuncio de escritura, rechazo local de cambio de instrucciones, soporte sin llamar al proveedor y chat público sin herramientas/contexto privado. La configuración original se restaura al terminar.
- **QA-NAVEGADOR-R16.json:** cuatro recorridos completos desde la ventana real: crear producto con revisión/confirmación móvil; escalar soporte y registrar caso; abrir datos y seguimiento en maestro; conservar revisión de planes del maestro en celular. Seis tamaños de catálogo (320,390,600,768,1024,1440px) con columnas e imágenes reducidas, carrito operativo y sin desbordamiento. Sin errores JavaScript.
- **QA-INTERFAZ-R16.json:** ocho recorridos y178 combinaciones de pantalla/ruta. Regresión de autorización nativa, turno, alta/repetición de productos, edición/cantidades/cliente, cobro e inventario, recibo, paneles, mesas, plano/zoom y menús. El sitio de presentación conserva la captura anterior fuera del chat. No se conectan impresoras físicas.
- **QA-ACTUALIZADOR-R16.json:** paquete instalado sobre el ZIP R15 publicado en una segunda base ficticia, sin las tablas R16 inicialmente. Crea las tablas de forma aditiva y conserva datos, contraseñas, config, imágenes, versiones, modificadores y turno abierto. Integridad, protección maestro/CSRF, rechazo de modificaciones de código desconocidas, permisos644/755 y repetición idempotente. Un ticket sembrado después de instalar sobrevive a la reinstalación.
- **QA-SINTAXIS-R16.json:** 139 archivos funcionales, 82 PHP de payload, 86 PHP del ZIP completo y12 JavaScript; hashes, guía y permisos verificados.

El paquete añade tres tablas de propuestas, tickets y seguimiento durante la instalación explícita. No se crea esquema en solicitudes ordinarias. Los negocios mantienen su activación180/179; los nuevos negocios siguen comenzando actualizados. No requiere SQL manual ni reinstalar Windows1.8.1/Android1.8.0.

Pruebas con datos ficticios en PHP/MariaDB/Chromium. El proveedor de IA fue simulado localmente; se verificaron las herramientas y controles, no la calidad/latencia de un modelo real. Las instrucciones y filtros acotan las respuestas sin garantizar todos los posibles textos de un modelo generativo. Dispositivos físicos quedan para revisión en la instalación. No hubo mensajes de WhatsApp, correos, pagos, impresoras ni modificaciones del hosting público. Publicar el ZIP en el repositorio no instala automáticamente el sitio.

Las siguientes secciones conservan evidencia histórica.

---

# Informe QA · FISITAAP web 1.8.0-R15

Fecha: 27 de octubre de 2026. Corrección de la diferencia entre R14 y el HTML aprobado **Línea + FISITAAP**. Esta revisión cambia la composición de las pantallas PHP reales y reutiliza los estilos de la propuesta. No sustituye los datos del sistema por los del prototipo.

| Solicitud | Resultado |
|---|---|
| Que coincida con la mezcla aprobada | Cabecera, ruta de navegación, resumen con cuatro indicadores, gráfico a la izquierda, pedidos a la derecha y actividad debajo. Los valores se consultan por negocio/sucursal. El maestro tiene sus propias métricas y accesos de plataforma. |
| Facturación amplia a la izquierda | Estructura de dos columnas 60/40, factura con Producto/Cantidad/Precio/Importe, cliente arriba, total y cobro abajo. Hasta 650 px usa Facturación/Productos; mantiene cobro y aviso de pedidos dentro de pantalla. Cantidades y selección de cliente siguen enviándose al backend real. Las monedas enteras se muestran sin ceros decimales; se conservan los centavos cuando existen. |
| Mesas gráficas | Plano real y detalle lateral persistente, apilados en celular. Conserva formas, coordenadas, objetos, sectores, cuentas y vista básica. El detalle se actualiza cada dos segundos sin bloquear el refresco y conserva la selección. |
| Mismo lenguaje visual en el sistema | Estilos compartidos y adaptación para maestro, negocio, comprador, motorizado y catálogo. El catálogo conserva color primario, slider, fotografías, carrito y acceso de cliente. Los menús muestran enlaces permitidos de la cuenta real; no se copian los cambios de identidad de la demo. |
| Mantener bienvenida y asistentes | Resumen y Bienvenida tienen accesos separados; se conserva la imagen guardada por el maestro. El negocio y el maestro usan la ventana moderna de FISIChat con sus perfiles y permisos del servidor. |
| Conservar la presentación oficial | La portada de presentación no carga el tema nuevo. Su captura coincide píxel por píxel con la referencia anterior en el entorno de prueba. |

## Evidencia R15

- **QA-REFERENCIA-VISUAL-R15.json:** nueve comparaciones del HTML original frente a PHP real: Resumen, Venta rápida y Mesas en 1440×1000, 768×1024 y 390×844. Comprueba posiciones y anchos de sus regiones principales; en facturación también la posición vertical. Reutilización exacta del CSS aprobado, salvo la regla de impresión exclusiva del modal de la demo. Capturas lado a lado en **VISTAS-R15.md**. Los datos de muestra se aplicaron temporalmente sólo al fixture ficticio y se restauraron al terminar.
- **QA-INTERFAZ-R15.json:** ocho recorridos y 178 combinaciones de ruta/pantalla. Anchos de 320 a 1366 px, orientaciones vertical/horizontal y ventanas móviles de 520 px de alto. Venta móvil inicial y repetida, teclado de cantidades, botones de incremento, edición de producto, selección de cliente, autorización nativa mediante el protocolo real, turno, cobro e inventario. Factura izquierda/más ancha, total y cobro visibles, ventanas, mesas, zoom y teclado. Sin errores JavaScript; recibo de impresión conserva papel blanco, monoespaciado y contenido visible, sin imprimir físicamente.
- **QA-NAVEGACION-R15.json:** 16 combinaciones de rol/ruta/pantalla. Dueño y cajero mantienen enlaces, módulos y permisos; apertura/cierre, Escape, manual, salida, acceso a Productos desde el menú de Mesas, catálogo/carrito y pedidos propios del comprador. La navegación original de la web oficial se conserva.
- **QA-COMPORTAMIENTO-R15.json:** el detalle lateral de mesa cambia por una actualización de otro actor, sin navegación ni recarga; mantiene la selección. Búsqueda de pantallas y apertura/cierre del chat. Perfiles de FISIChat seleccionados por servidor: dueño, maestro y cliente, sin llamadas externas de IA.
- **QA-ACTUALIZADOR-R15.json:** instalación real sobre el ZIP R14 publicado, en una segunda base ficticia con negocios179/180 y turno abierto. Conserva datos, contraseñas, contactos, permisos, modificadores, activaciones, config, imágenes, versiones anteriores y turno. Acceso maestro/CSRF, rechazo de código activo modificado, 137 archivos, extracción644/755 y repetición idempotente.
- **QA-SINTAXIS-R15.json:** 80 PHP funcionales, 84 PHP del ZIP completo, 12 JavaScript y 137 hashes verificados; guía idéntica al paquete y permisos explícitos644/755.

R15 no añade tablas ni columnas sobre R14. El paquete acumulativo se instala en **releases/1.8.0-r15** y conserva las revisiones anteriores. Windows1.8.1 y Android1.8.0 conservan sus instaladores; la caja local de Windows sin internet mantiene su interfaz existente.

La comparación visual verifica las regiones descritas y se acompaña de capturas inspeccionadas. No afirma identidad de todos los píxeles entre una demo y una instalación con otros datos, salón y permisos. Las funciones de cálculo, caja, impresión y pedidos continúan en el backend original. Las pruebas usan PHP/MariaDB/Chromium con datos ficticios; Safari/iPhone, tabletas Android y Windows físico requieren comprobación en los dispositivos. No se enviaron WhatsApp, pagos ni trabajos a impresoras reales.

El ZIP y las capturas se publican para instalación. No hay conexión directa al cPanel: publicar el repositorio no ejecuta el actualizador en fisitaap.com.

Las siguientes secciones conservan evidencia histórica de las entregas anteriores.

---

# Informe QA · FISITAAP web 1.8.0-R14

Fecha: 27 de octubre de 2026. Integración del diseño aprobado **Línea + FISITAAP** en la aplicación PHP real. Windows 1.8.1 y Android 1.8.0 conservan sus instaladores.

| Solicitud | Implementación y resultado |
|---|---|
| Aplicar la mezcla aprobada | Tema compartido para administración, maestro, acceso, cuenta del comprador, motorizado y páginas de negocios. Tarjetas blancas, bordes suaves, tipografía legible, azul profundo y verde; cada catálogo conserva sus colores guardados. |
| Facturación a la izquierda y más grande | Factura y cobro dentro de la columna izquierda del 60 %; carga rápida a la derecha del 40 %. Incluye la pantalla inicial, que conserva la apertura de cuenta únicamente al agregar el primer producto. |
| Celular y tableta | Distribución nativa que reemplaza el encogimiento de una pantalla de escritorio. Hasta 650 px se alternan Facturación/Productos; arriba se conservan las dos columnas. Precios, cantidades, aviso de pedidos y botón de cobro se comprueban dentro de sus áreas visibles. Formularios y ventanas limitados al ancho y alto disponibles. |
| Mesas gráficas | Conserva las coordenadas, sillas, formas, sectores y objetos reales. Ajuste/zoom del plano, vista básica, selección táctil y con teclado. La accesibilidad de las mesas se repone después de la actualización automática del estado. |
| Conservar procesos | Se mantienen autenticación, permisos, cajas autorizadas, turnos, cálculos, aceptación de pedidos, impresión y conexión de IA. El código de descuento se abre en una ventana desde la factura. |
| Conservar la presentación oficial | La portada oficial no carga los archivos R14; su captura coincide exactamente con la referencia R13 del fixture. Su navegación anterior permanece. |

## Evidencia actual

- **QA-INTERFAZ-R14.json:** 178 combinaciones de ruta y pantalla, con anchos de 320, 360, 390, 600, 768, 820, 844, 1024 y 1366 px y orientaciones vertical/horizontal, incluyendo ventanas móviles de alto reducido (520 px). Venta móvil inicial y repetida, autorización nativa con dispositivo ficticio mediante el protocolo real, apertura de turno, cobro real de prueba e inventario; proporción y ubicación de la factura, precios sin desplazamiento horizontal, total/cobro visibles, diálogos, mesas/zoom/teclado, negocio, maestro, comprador y motorizado. Cero errores de JavaScript en esos recorridos. Revisión adicional del recibo en modo impresión: papel blanco, tipografía monoespaciada y contenido visible, sin llamadas a impresoras. El color primario del catálogo coincide con su configuración guardada.
- **QA-NAVEGACION-R14.json:** 16 combinaciones adicionales de rol/ruta/pantalla; dueño y vendedor conservan los enlaces y permisos del menú agrupado en Mesas/Venta rápida/Cobro. Se comprueban apertura/cierre, Escape, manual, salida, navegación de compra, acceso del comprador y menú de la presentación oficial.
- **QA-ACTUALIZADOR-R14.json:** instalación del paquete sobre el ZIP R13 publicado en una segunda base de datos ficticia, con negocio actualizado/pendiente y un turno abierto. Conserva datos, contraseñas, configuración, imágenes, activaciones, código anterior y turno; comprueba maestro/CSRF, rechazo de cambios de código desconocidos, 133 archivos, permisos de extracción 644/755 y repetición idempotente.
- **QA-SINTAXIS-R14.json:** 79 PHP funcionales, 83 PHP del ZIP completo y 11 JavaScript comprobados. Integridad de 133 archivos, guía incluida idéntica a la fuente y permisos explícitos 644/755.

El instalador usa **releases/1.8.0-r14** y conserva las versiones anteriores. No añade tablas ni cambia las reglas comerciales respecto de R13; reutiliza la preparación idempotente de la instalación acumulativa. Los ZIP anteriores permanecen históricos.

## Alcance y comprobación final en el hosting

Pruebas en PHP/MariaDB/Chromium con datos ficticios. Los tamaños móviles y de tableta son viewports de navegador; quedan para comprobación física Safari/iPhone, Android/tabletas y Windows instalado. No se enviaron mensajes de WhatsApp, pagos externos ni trabajos a impresoras reales. La caja local de Windows sin internet conserva su interfaz existente. Los perfiles de FISIChat permanecen en el código de R13; no se repitieron sus pruebas de proveedor porque su lógica no cambió.

El diseño está integrado en los archivos y el actualizador. Publicar el ZIP en el repositorio no lo ejecuta en fisitaap.com; la instalación del hosting requiere extraerlo en public_html y ejecutar el actualizador como maestro. Esta entrega no dispone de una conexión directa al cPanel.

Las siguientes secciones conservan evidencia histórica de las revisiones anteriores.

---

# Informe QA · FISITAAP web 1.8.0-R13

Fecha: 24 de octubre de 2026. Correcciones del catálogo, navegación interna y especialización de FISIChat. Se conservan los instaladores Windows 1.8.1/Android 1.8.0 y el machote Excel R11.

| Solicitud | Resultado |
|---|---|
| Menú antiguo del catálogo | Se reemplaza por Categorías y Ver carrito. Acceso de cliente visible sin duplicarlo dentro del menú; tras iniciar sesión se ofrecen Mis pedidos/Mi cuenta. No aparecen Inicio/Catálogo/Blog/Contacto ni el listado de administración. Las acciones regresan a categorías y abren el carrito real, cerrando el menú y restaurando el estado del botón. |
| Menú de Mesas en celular | Mesas, Venta rápida y Cobrar cuentas reutilizan el mismo componente PHP agrupado del panel, con sus etiquetas, destinos, permisos y módulos. Se retira la lista JavaScript anterior. Incluye manual y cierre de sesión. Se conserva el ajuste/zoom de terminal y la navegación oficial restaurada en R12. |
| FISIChat fuera de función | Cuatro perfiles: comercial/soporte en la web oficial; compras/pedidos de esa tienda en el catálogo; operación del negocio en el panel; administración de FISITAAP en maestro. Filtro local previo al proveedor para consultas ajenas, creación/clonación de sistemas, cambio de instrucciones y preguntas no confirmadas del desarrollo interno. Instrucciones distintas por perfil y contexto permitido; los rechazos no se envían después como historial a la IA. Respuestas acotadas y filtro de salidas que derivan en creación de software. |
| Asistentes existentes | La misma política se aplica a las API anteriores del dueño y pública. Se conservan las herramientas autorizadas del dueño y la propuesta/confirmación de planes del maestro. Conversaciones previas quedan fuera del nuevo espacio de perfil. No se modifica ni publica la conexión privada de IA. |

## Evidencia actual

- QA-NAVEGACION-R13.json: cuatro grupos, 16 combinaciones de rol/ruta/tamaño; catálogo en 1366/900/768/390 px, acciones reales de categorías/carrito, acceso a pedidos propios, igualdad de enlaces agrupados entre panel y terminal para dueño/cajero, navegación real a Productos, cierre y Escape. Captura del menú móvil inspeccionada; sin errores JavaScript.
- QA-FISICHAT-PROVEEDOR-R13.json: 12 grupos con proveedor TLS simulado y clave ficticia. Las consultas de clonación, procedencia del desarrollo, instrucciones y redacción ajena se rechazan en los cuatro perfiles sin llamada externa. Comprueba contexto público/de tienda/maestro, historial filtrado, salida simulada fuera de función sustituida por guía, API anteriores, permisos y controles de sesión, concurrencia, reinicio y caída del proveedor.
- QA-FISICHAT-HTTP-R13.json: 11 grupos de regresión sin IA: contexto y precios por sucursal, productos ocultos, aislamiento entre clientes/tiendas, acceso y cambio obligatorio de clave, datos del maestro, confirmación/idempotencia de planes y límites de solicitudes.
- QA-ACTUALIZADOR-R13.json: instalación real sobre ZIP R12 publicado con negocios179/180 y turno abierto; preservación de datos, contraseñas, indicadores de acceso, config/uploads/código anterior, permisos maestro/CSRF, integridad y repetición idempotente.
- QA-SINTAXIS-R13.json: 79 PHP funcionales, 83 PHP del ZIP completo, 10 JavaScript, 131 hashes, permisos644/755 y guía idéntica al paquete.

R13 no añade columnas ni tablas respecto de R12. Se instala en releases/1.8.0-r13, conservando las versiones previas. Los indicadores/activaciones por negocio mantienen su estado.

Pruebas con PHP/MariaDB/Chromium y datos ficticios, incluyendo respuestas de IA simuladas. No se consultó un modelo real ni se midió su calidad/latencia; las instrucciones y filtros reducen desvíos y se verificaron los casos descritos, sin afirmar que un modelo generativo nunca se desvíe. No se accedió al hosting ni se enviaron mensajes. Los informes siguientes son evidencia histórica de entregas anteriores.

# Informe QA · FISITAAP web 1.8.0-R12

Corrección del menú de la portada oficial de FISITAAP. Se retira únicamente el JavaScript que lo convertía en desplegable y sus estilos específicos. La navegación recupera el diseño adaptable anterior: enlaces horizontales en escritorio y el comportamiento móvil previo. El logo y Acceso negocios conservan su posición. Los paneles, catálogos, Mesas y Venta rápida mantienen sus menús desplegables de R11.

- QA-MENU-R12.json: portada en 1366/900/768/390 px, encabezado sin desplegable añadido ni desborde horizontal, enlaces Funciones/Demos operativos y revisión de los menús de catálogo, administración y Venta rápida. Sin errores JavaScript. Capturas inspeccionadas en el entorno local.
- QA-ACTUALIZADOR-R12.json: instalación mediante el actualizador sobre el ZIP R11 publicado, con negocios 179/180 y turno abierto. Conserva datos, contraseñas, indicadores de acceso por teléfono, config.php, uploads y código R11. Comprueba maestro/CSRF, rechazo de modificaciones ajenas, 130 hashes, archivos privados protegidos y repetición idempotente.
- QA-SINTAXIS-R12.json: 78 PHP funcionales, 82 PHP del ZIP completo, 10 JavaScript, 130 hashes, guía idéntica y permisos 644/755.

El ZIP instala releases/1.8.0-r12 sin sobrescribir la versión R11 conservada. No cambia el esquema ni las reglas de negocio respecto de R11; reutiliza la preparación idempotente existente. Los instaladores Windows 1.8.1/Android 1.8.0 y el machote Excel R11 no cambian.

Pruebas en PHP/MariaDB/Chromium con datos ficticios; sin acceso al hosting ni mensajes externos. No se repitieron todos los flujos de R11, que quedan documentados a continuación como evidencia histórica.

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
