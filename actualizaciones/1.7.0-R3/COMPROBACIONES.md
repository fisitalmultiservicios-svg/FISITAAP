# FISITAAP 1.7.0-R3: alcance y comprobaciones

Entrega acumulativa de R1, R2 y R3 para la aplicación existente en cPanel. La implementación toma como referencia las 18 páginas de REESTRUCTURACION.pdf. Las páginas públicas usan las imágenes, textos, logo y datos configurados por cada negocio; las mesas son elementos editables del plano.

| Página del PDF | Aplicación en esta entrega |
|---|---|
| 1 | Maestro, dueño, cliente y motorizado; negocios y planes, vencimientos, cortes mensuales/anuales con ventas web y de caja, usuarios, roles, pedidos y fidelización. |
| 2 | Web del negocio, catálogo, tienda física y los cuatro perfiles de FISIChat con acceso separado. |
| 3 | Página del negocio: navegación, presentación con imagen, nosotros, tres tarjetas, catálogo completo, WhatsApp, dirección, horario, mapa, redes y pie; edición por el dueño. |
| 4 | Orden de los módulos de catálogo, administración, clientes y analítica. |
| 5 | Web oficial azul: presentación con computadora/teléfono, franja de pedidos, variantes, seis herramientas, FISIChat, tres pasos, demostraciones, preguntas y contacto; textos e imágenes editables por el Maestro. |
| 6 | Orden de la tienda física con ventas, turnos, compras, crédito, impresión y delivery. |
| 7 | Categorías/productos, imágenes, SKU, cantidades, inventario, extras, opcionales gratuitos, combos por componentes, promociones, cupones, fidelización y pedidos. |
| 8 | Datos generales, sucursales/horarios/pagos/entrega, diseño, roles/usuarios, zonas, registro simple de clientes, origen, historial, reportes y KPIs. |
| 9 | Las herramientas de catálogo también funcionan para la tienda física. |
| 10 | Cobro en ventana: efectivo/tarjeta/SINPE/mixto/crédito/regalo, monto recibido, vuelto, referencia, resumen, impresión y división por productos o partes iguales. |
| 11 | Venta rápida con lista, búsqueda/SKU, cantidad, edición/eliminación, accesos por categoría, notas, descuento, total, cobro, clientes, espera, historial, recibos y turno. |
| 12 | Menú con mesas/Express/venta rápida; catálogo con venta rápida; permisos de caja y detalles del turno. |
| 13 | Mesas básicas con mobiliario, colores de estado, capacidad, saldo, tiempo y reordenación. |
| 14 | Cuenta al seleccionar mesa: artículos y preparación, opciones/notas, edición, resumen, servicio, cobro, división, comanda, precuenta, traslado, unión y reimpresión. |
| 15 | Diseñador: mesas, paredes, puertas, ventanas, sillas, sofá, plantas, barra, separador y texto; arrastre, tamaño, rotación, cuadrícula, ajuste, zoom, deshacer/rehacer y guardado; salones y sectores con servicio. |
| 16 | Vista gráfica del salón con paredes/mobiliario, estados de mesas, capacidad/saldo/tiempo, búsqueda por mesa o cliente y acceso a configuración. |
| 17 | Clientes, crédito, CXC/abonos, proveedores, compras de varias líneas, importación XML de Costa Rica, inventario y CXP. |
| 18 | Administración, impresoras por zona, zonas adicionales creadas por el negocio, categorías asignadas a impresoras, motorizados afiliados/invitados e historial. |

Las tarjetas con saldo y las cajas Windows amplían el alcance del PDF. Las tarjetas permiten pago parcial y recarga; la devolución de un pedido web cancelado se registra una sola vez. Windows usa un equipo central por sucursal y una red local compartida para buscar y vender sin internet, guardar las operaciones y sincronizar después.

## Validación

- 21 grupos HTTP de la entrega acumulativa: separación entre negocios/sucursales, módulos, opciones, precios, promociones, combos, inventario, cobros, céntimos exactos al dividir, compras/XML, registro de clientes, crédito y cuentas, permisos y fidelización.
- 9 grupos HTTP de R3: pantallas, plano y control de ediciones concurrentes, servicio por sector sin modificar cargos existentes, edición y notas, precuenta, cobro, saldo/recarga, pedido web idempotente y devolución al cancelar por la ruta real.
- Comprobación de 19 reportes, cortes mensuales/anuales Maestro, edición del contenido oficial, creación de una zona adicional y comanda real dirigida a su impresora de 58 mm; bloqueo del uso de tarjetas de otro cliente y de edición del salón por un cajero.
- Navegador Chromium: web oficial y FAQ, página del negocio, mesas básicas y gráficas, diálogo de mesa, diseñador, guardado/deshacer/rehacer, productos y cantidades, notas, cobro con tarjeta/efectivo, división, recibo y adaptación móvil. Se guardaron capturas con datos ficticios.
- 10 pruebas del servidor Windows y puente de impresión: precios/impuestos, inventario compartido, autenticación/CSRF/origen, cobro repetido, almacenamiento/reinicio, fallo de disco, promociones vencidas, rutas de impresoras, integridad de trabajos, impresión idempotente y rechazo de sitios externos.
- Dos cajas contra el servidor PHP real: ventas sin internet, reinicio, primera sincronización, respuesta perdida y reintento sin duplicados, inventario, notas/descuento, fidelización y turnos con diferencia cero.
- Navegador de escritorio: conserva cliente/pago al cambiar cantidades, recupera un pago con respuesta perdida, conserva una solicitud no enviada y vende desde otra caja por LAN sin exigir un contexto HTTPS local.
- Instalación desde original, R1 y R2: cuenta Maestro, CSRF, hashes, bloqueo de archivos desconocidos y payload modificado, protección HTTP de payload/backups/manifiesto, instalación repetida, restauración y conservación de roles/configuración; bloqueo de restauración después de cambiar datos.
- PHP/JavaScript, integridad del ZIP, manifiesto de archivos y sumas SHA256. Instalador Windows x64 generado con Electron 44.5.1 y electron-builder 26.15.3; el contenido app.asar coincide con las fuentes probadas, incluido el puente de impresión y su configuración.

## Comprobación en el negocio

La aplicación de producción en HostGator conserva su configuración y debe actualizarse siguiendo la guía. La instalación del .exe en Windows real, el acceso por firewall/LAN y las impresoras físicas se comprueban en el local. El instalador Windows no tiene firma digital.

El equipo central y el router deben estar encendidos para las cajas sin internet. El catálogo local debe descargarse previamente. Las ventas se conservan con sus precios; diferencias respecto de la web se marcan al sincronizar. La caja registra efectivo/tarjeta/SINPE; la autorización bancaria depende del método externo. Mesas, Express, compras, crédito, regalos, pedidos web y FISIChat requieren conexión a la web. Las respuestas de IA requieren configurar el proveedor de FISIChat; se comprobó la ayuda interna y los permisos con datos ficticios.

El ZIP de cPanel contiene los archivos acumulativos del programa y las migraciones. El instalador valida las copias revisadas y respalda los archivos reemplazados. Antes de usarlo se necesita una copia completa de archivos/base de datos; el respaldo automático del paquete cubre los archivos que sustituye. No contiene configuración privada, base de datos de clientes, fotos subidas ni respaldos del negocio.
