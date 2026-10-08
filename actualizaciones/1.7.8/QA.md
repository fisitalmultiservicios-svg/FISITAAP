# Validación 1.7.8

Entorno aislado PHP/MariaDB, Chromium, pruebas Node y compilación Android. No se usó la base de producción.

- R2: 21 pruebas aprobadas (ventas, inventario, promociones, pagos, compras, permisos, regalos, fidelidad, turnos, conexión).
- R3: 9 pruebas aprobadas (mesas, servicio, notas, precuenta, pagos divididos y saldo de regalos).
- Web profunda: 130 comprobaciones aprobadas, cero fallos; pantallas, aislamiento de roles/negocios, cobros concurrentes, precisión de inventario, contraseña temporal/recuperación, imágenes maliciosas, entregas y 19 exportaciones. Evidencia: RESULTADOS-WEB.json.
- Navegador: portada/negocio/acceso, mesas y diseñador, venta rápida, opciones, cantidades, cobro dividido, recibo y ancho móvil aprobados sin errores JavaScript.
- Windows: 30 pruebas automatizadas aprobadas; almacenamiento durable, concurrencia entre cajas, fallos de disco, sincronización idempotente, sesión separada, permisos IPC y deduplicación de impresión.
- Caja local profunda: 6 pruebas aprobadas; 50 ventas concurrentes con 25 unidades, 125 ventas en lotes, carreras con descarga/emparejado y 1.500 cálculos comparados JavaScript/PHP al céntimo.
- Conectividad/driver Windows: 10 pruebas aprobadas; impresión silenciosa de 58/80 mm, dispositivo ausente y error del controlador, respuestas HTML y sondeo de conexión.
- Regresión 1.7.8: acceso vencido y token malformado vuelven al formulario; las mutaciones siguen rechazando falta de CSRF; mantenimiento solo maestro activo; índices repetibles; panel, sondeo Android y sintaxis PHP aprobados.
- Regresión de impresión en Chromium: un solo envío nativo automático en Windows, sin puente antiguo; Android conserva el control exclusivo; el navegador común no abre automáticamente su selector. ZIP completo extraído y regresiones repetidas correctamente. Fuentes del runtime Windows empaquetado verificadas contra el código entregado.
- Android: 9 pruebas aprobadas, compilación release y lint completados. Lint mantiene advertencias relacionadas con WebView/compatibilidad Android, no se presenta como cero advertencias. Firma APK verificada y conservada para actualización.

Pendiente de validar en el negocio: compatibilidad y corte de cada impresora física, instalación Windows real, conexión Android en su red, latencia con el volumen real de datos en HostGator. Los índices y el cambio de agregación reducen trabajo del servidor; no se promete una velocidad de producción que no se ha medido.

No se afirma una auditoría exhaustiva de cada función ni ausencia total de vulnerabilidades. Las pruebas cubren los flujos anteriores y las regresiones identificadas.
