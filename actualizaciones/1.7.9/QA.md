# Validación 1.7.9

Laboratorio PHP/MariaDB aislado; ningún cambio de datos en producción.

- Web: 130 comprobaciones generales sin fallos; suites R2 (21) y R3 (9).
- Windows: 34 pruebas del servidor local, incluyendo permiso del principal, denegación a secundarios de red y liberación fallida persistente.
- Motor local: seis pruebas profundas, incluyendo concurrencia de inventario y 1.500 comparaciones de totales PHP/JavaScript.
- Android: 13 pruebas Java sin fallos; almacenamiento SQLite, revisión atómica, migración no destructiva, conservación ante fallo, rasterización y transporte de impresión.
- Integración con PHP real y navegador usando los recursos Android: equipo no autorizado rechazado, selección, venta sin red, persistencia al reabrir y sincronización repetida que importa una sola venta. La prueba simula el puente de almacenamiento/impresión; las pruebas Java verifican las operaciones nativas por separado.
- Reglas web: selección concurrente de un solo principal entre sucursales, prohibición de sustitución/revocación, liberación con turno abierto rechazada, permisos maestra/dueño, importación de nuevas ventas no autorizadas rechazada. Reintentos de ventas ya importadas se reconocen sin duplicarlas.
- Android release 1.7.9 (10709): compilación y lint, cero errores de lint y 14 advertencias. Firma verificada; actualización conserva la identidad de firma anterior.

RESULTADOS-WEB.json contiene los resultados de la suite web general. El JSON completo del estado Android se cifra en SQLite; no se afirma que cada venta esté normalizada en tablas independientes. El contador de pendientes evita descifrar todo el historial en cada sondeo.

Límites: no hay medición del hosting HostGator, ejecución en un Windows físico, emulador Android completo ni certificación de impresoras físicas. AndroidKeyStore en equipo real requiere comprobación en el dispositivo. Se debe actualizar toda la flota antes de activar 1.7.9; snapshots antiguos sin la política se conservan únicamente para recuperar operaciones previas. No se permite relevo automático ante pérdida del principal.
