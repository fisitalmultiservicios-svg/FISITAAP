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
