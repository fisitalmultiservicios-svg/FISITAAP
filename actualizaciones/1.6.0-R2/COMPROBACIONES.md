# Alcance y comprobaciones de 1.6.0-R2

La entrega incluye la actualización acumulativa de la web y un programa Windows para venta rápida mediante un equipo central por sucursal. No modifica la configuración de producción ni incluye archivos subidos, base de datos de clientes, claves de conexión o respaldos del negocio.

| Área | Implementación |
|---|---|
| Menús y roles | Orden del PDF; módulos separados y permisos de consulta, modificación, eliminación, cobro, crédito y configuración. Conserva las asignaciones anteriores. |
| Web oficial y negocio | Presentación, imágenes, tres tarjetas, contacto, redes y edición desde maestro/dueño. Mantiene los banners anteriores. |
| Catálogo | Biblioteca de extras, opcionales gratis, combos a precio fijo, inventario por componentes, 2×1, precio especial, cupones y fidelización. |
| Clientes | Alta por nombre/teléfono, búsqueda, origen, compras, crédito y guardar acceso de un comprador nuevo. |
| Fidelización | Puntos/compras/monto, premios y tarjetas promocionales de un uso; ventas físicas acreditadas una vez y ventas Windows al sincronizar. Las tarjetas no tienen saldo recargable. |
| Compras | Proveedores, varias líneas manuales, vista previa XML de Costa Rica en CRC, asignación de productos, inventario y saldo por pagar. Rechaza XML con total incompatible, entidades o DTD. |
| Crédito y saldos | Cuentas por cobrar/pagar, estados de cuenta, abonos e idempotencia. |
| POS web | Venta rápida, espera, cancelación, cliente o consumidor final, Express, mesas, traslado de cuentas, división por productos o 2–20 partes iguales, pagos y reimpresión. |
| Mesas | Vista básica/gráfica, salones, posición y forma de mesas/barras, servicio configurable. El plano es funcional; no reproduce todos los adornos de las imágenes del PDF. |
| Turnos | Apertura/cierre, filtros, pagos, efectivo esperado y diferencia; cierre de turnos locales desde Windows. |
| Reportes | Reportes existentes, exportaciones, KPIs, gráficos y totales web/POS. |
| Motorizados | Afiliados, invitaciones que el motorizado acepta en su cuenta e historial. No dispara mensajes externos. |
| FISIChat | Perfiles vendedor/técnico, maestro, negocio y cliente; consultas autorizadas y orientación; conserva la herramienta existente del dueño. |
| Windows | Instalador por usuario, central local y otras cajas por LAN, búsqueda, opciones, ventas, turnos, comprobante, cola persistente y sincronización. |

## Pruebas realizadas

- 21 grupos de pruebas HTTP sobre datos ficticios en una base independiente: pantallas, negocios separados, extras, combos, promociones, cantidades, pagos y reintentos, tres pagos iguales con céntimos exactos, plano/servicio, compras/XML, clientes, permisos, crédito/abonos, tarjetas y fidelización física.
- Instalación acumulativa desde la copia original y desde R1: acceso maestro, CSRF, integridad, bloqueo de cambios desconocidos, carpetas privadas, instalación repetida, restauración, conservación de roles y bloqueo de restauración tras crear datos nuevos.
- 7 pruebas del servidor local: importes, opciones, inventario compartido por dos cajas, reintentos, reinicio, corte de internet, persistencia, fallo de disco, archivo corrupto y vencimiento de promociones.
- Integración real Node/PHP: dos cajas venden desconectadas; reinicio; envío y pérdida de respuesta después de recibir las ventas; reintento sin duplicados; inventario exacto, fidelización y cierres con diferencias cero; turno sin ventas.
- Navegador: conservación del formulario de cobro, recuperación de un pago guardado y de uno no enviado, y segunda caja sobre LAN sin `crypto.randomUUID()` de contexto seguro. Página del negocio, plano, compras, venta rápida, KPIs y FISIChat también comprobados.
- Sintaxis PHP/JavaScript, integridad del ZIP y hashes. El instalador Windows se construyó con Electron 44.5.1 y electron-builder 26.15.3. El contenido empaquetado coincide con los archivos de código probados; programa x64 y empaquetador NSIS. El desinstalador retira los archivos del programa y conserva los datos del usuario.

## Lo que requiere comprobación en el negocio

No se instaló en HostGator ni se ejecutó el instalador en un Windows real. Deben comprobarse la instalación, el firewall/red local y las impresoras concretas. El instalador no está firmado. No se realizaron llamadas a bancos, Hacienda, correo ni al proveedor de IA, ni se usaron claves de producción.

El trabajo sin internet requiere el central y la red local funcionando. La caja registra el método de pago, pero la autorización bancaria es externa. Mesas, pedidos web, compras, crédito y canjes permanecen en la web. El stock local y web puede diferir durante una desconexión; la importación conserva la venta y marca diferencias para revisar.

La restauración automática de archivos está prevista antes de usar los módulos nuevos. Después se requiere revisar el respaldo completo y las ventas pendientes. Las modificaciones de base de datos son aditivas y no eliminan registros del negocio.

Las fuentes del escritorio se entregan separadas en su ZIP: `npm ci --ignore-scripts`, `npm test` y `npm run build:win`. La guía para el usuario no necesita estos comandos.
