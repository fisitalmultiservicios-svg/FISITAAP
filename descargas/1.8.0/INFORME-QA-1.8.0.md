# Informe QA · FISITAAP 1.8.0

## Corrección de permisos del ZIP · R1 · 14 de octubre de 2026

Se detectó después de la entrega que la umask 077 del entorno de construcción guardaba 600 en los archivos del ZIP y omitía entradas explícitas de carpetas. La prueba original de instalación normalizaba los permisos después de extraer y ocultó este defecto. El constructor ahora fija en el ZIP archivos 644 y carpetas 755, independientemente de la umask. Las pruebas de instalación extraen con unzip y dejan los permisos del ZIP intactos.

QA-PERMISOS-ZIP-R1.json documenta una prueba adicional con Apache ejecutado como www-data (UID 33): el paquete previo extraído bajo umask 022 produce carpetas 755 y archivos 600, como informó el usuario, e impide servir los archivos de prueba. R1 se extrae bajo umask 077 tanto sobre esa misma carpeta como en una carpeta nueva, sin chmod/chown posterior; permite abrir los tres con HTTP 200 y entrar al actualizador con HTTP 302 hacia el acceso. Manifest y payload siguen bloqueados con HTTP 403. Los 109 archivos funcionales del payload son idénticos por SHA256. Si ya existían carpetas con permisos incorrectos, algunos extractores conservan esos permisos y deben revisarse según la guía. El 404 informado en el hosting todavía requiere confirmar permisos y ubicación allí; esta prueba no afirma haber reproducido su causa exacta.

Fecha: 13 de octubre de 2026. Copias ficticias; no se modificó cPanel ni la base real.

## Resultados

- 133 comprobaciones funcionales HTTP/API/uso/instalación, descritas en los informes JSON.
- 57 pruebas Node aprobadas; 1.500 cálculos JavaScript/PHP iguales al céntimo, concurrencia y recuperación; controladores Windows sustituidos en pruebas.
- 16 pruebas Android JUnit aprobadas y lint debug/release; persistencia SQLite cifrada, migración, Unicode/PDF y receptores TCP de prueba para 58/80 mm.
- 84 páginas reales en Chromium a 1366/768/390/360 y 20 vistas adicionales de POS cargado, cobro, mesa ocupada, diseñador y KPIs: sin desbordamiento ni errores JavaScript. Acceso de siete roles.
- XML 4.4 cargado por multipart HTTP: proveedor por identificación normalizada, proveedor/producto nuevo, SKU existente, borrador, recepción repetida, XML cambiado de pestaña y rechazo de entidades externas.
- Dos usuarios, sesiones, equipos y cajas distintos cobrando simultáneamente: una venta y un pago; conflicto humano en la otra caja y arqueos separados.
- ZIP extraído e instalado desde archivos 1.7.8, 1.7.9-r2 y mudanza 1.7.9-r3; maestro/CSRF, hashes, rechazo de cambios desconocidos, respaldo protegido, repetición y restauración antes de activar.

## Rendimiento

Volumen ficticio: 1.007 productos, 1.002 clientes y 20 salones adicionales con 200 mesas, 60 sectores y 100 objetos. Tres muestras por ruta. Son tiempos del entorno de pruebas, no una promesa para el hosting.

| Pantalla | Consultas | Mediana PHP | Respuesta HTML |
| --- | ---: | ---: | ---: |
| /laventanita/catalogo | 9 | 8.9 ms | 9683 bytes |
| /admin/ventas | 17 | 21.4 ms | 73364 bytes |
| /admin/clientes | 9 | 9.1 ms | 84953 bytes |
| /admin/mesas?room=3 | 23 | 15.6 ms | 67005 bytes |
| /admin/reportes?dataset=sales&from=2020-01-01&to=2030-01-01 | 34 | 12.9 ms | 86142 bytes |
| /admin/reportes?dataset=inventory&from=2020-01-01&to=2030-01-01 | 34 | 27.3 ms | 96980 bytes |

Se separan los tiempos PHP de proceso/red; no se incluye el retraso de 500 ms del evento networkidle como tiempo de PHP. Reportes se midió en el rango 2020–2030. Con catálogo sin cambios, la sincronización entrega existencias y datos de caja en vez de descargar de nuevo productos/clientes. Los catálogos originales se conservan para ventas pendientes e historial.

## Migración y archivos

schema180.php agrega las tablas 180 de cajas, colas de impresión, dinero, borradores, asignaciones de modificadores, mesas vinculadas, usos de cupón, pedidos vinculados, listas compartidas y operaciones idempotentes, y los campos/índices de los módulos relacionados. Se ejecuta por activación explícita, fuera de las consultas normales. La activación queda marcada después de preparar las migraciones. Se conservan tablas y registros anteriores.

MANIFEST-ARCHIVOS.json enumera los 109 archivos funcionales instalados y su SHA-256. Native: desktop/ y android/ contienen el motor compartido, protección de vistas/credenciales, persistencia, impresión y recursos 1.8.0. El ZIP evita config.php, claves privadas, SQL y archivos cargados del negocio.

## Revisión de las solicitudes

REQUISITOS.json relaciona los 42 apartados del QA manual con evidencia. Se revisaron también: un solo principal Windows/Android sin internet, menús completos conectados, Android directo a web o central, impresión sin FISITAAP Print en el flujo normal, identidad de la tienda, estilo de administración uniforme, demos independientes, conservación de La Ventanita e imágenes optimizadas. Las referencias del PDF se compararon con venta/cobro/mesas/diseñador y con la solicitud posterior de bordes rectos.

La velocidad previa del hosting y el certificado del nuevo servidor no pueden verificarse con estos fixtures. FISIChat libera la sesión antes del llamado externo y usa la sucursal seleccionada; la espera/respuesta del proveedor de IA real queda sin prueba porque no se usó una clave productiva.

## Comprobación pendiente en tus equipos

- Ejecutar el instalador en Windows físico y el APK en Android físico; se construyeron y verificaron sus paquetes, no se ejecutaron en esos sistemas físicos.
- Probar papel, margen/corte y gaveta con la impresora/controlador configurados. El ancho 58/80 mm no garantiza compatibilidad ESC/POS por sí solo.
- Verificar HTTPS válido, permisos/propietario PHP, tiempo del hosting y actividad del negocio después de instalar.
- La firma APK v2 se verificó y su clave tiene respaldo privado separado. Windows no cuenta con certificado Authenticode de editor; el instalador no debe describirse como firmado por un editor verificado.

## Informes detallados

- [QA-ACTUALIZADOR.json](QA-ACTUALIZADOR.json): 3 comprobaciones/vistas; alcance indicado en cada archivo.
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
