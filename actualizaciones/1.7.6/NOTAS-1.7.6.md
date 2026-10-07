# Notas 1.7.6

La aplicación abría la sesión PHP y la mantenía ocupada durante toda la solicitud. En HostGator, una consulta lenta, reporte o llamada de FISIChat podía bloquear las demás acciones del mismo usuario. La corrección conserva una copia autenticada para la pantalla, libera la sesión durante lecturas largas y reabre cambios pequeños solo cuando todavía es seguro hacerlo.

También se evita consumir mensajes después de haber enviado HTML y se toma el código de vinculación antes de pintar la pantalla. Las ventas, cierres, permisos y protección contra CSRF conservan su flujo normal.

Validación aislada: 21 pruebas R2 y 9 pruebas R3, sin avisos PHP, además de instalación 1.7.6 en una copia limpia de 1.7.5 con comprobación de integridad y activación de la marca de rendimiento.
