# Corrección de rendimiento y conexión — FISITAAP 1.7.5

6 de octubre de 2026, Costa Rica.

Se investigó el reporte de lentitud tras la actualización 1.7.4 y el mensaje «Sin conexión con la web. Usa la caja local para buscar y vender».

La copia del catálogo hacía varias consultas por cada producto. En un catálogo aislado de 1.004 productos se midieron 3.027 consultas. Con una demora simulada de 8 milisegundos por consulta, la preparación tardó 25,558 segundos, más que los 20 segundos que esperaba la conexión inicial del escritorio. La nueva versión hace 17 consultas y tardó 0,163 segundos bajo la misma demora. Los datos del catálogo, incluyendo precios, opciones, componentes y existencias, coincidieron exactamente.

Los ajustes del diseño también se consultaban uno por uno, y los módulos repetían comprobaciones al construir los menús. Ahora se cargan por negocio durante una sola solicitud. La prueba de 50 ajustes pasó de 50 consultas a una; la revisión repetida de módulos pasó de 111 a dos. Al guardar cambios se invalida la caché correspondiente. Las contraseñas, precios y permisos no se comparten entre solicitudes ni entre negocios.

La comprobación anterior de Windows esperaba una respuesta de error HTTP 405 y vencía a los siete segundos. Un servidor lento o una página de error del alojamiento podía mostrar el sistema como desconectado. La versión nueva utiliza una respuesta JSON normal HTTP 200, espera hasta 12 segundos y conserva compatibilidad con versiones web anteriores. Estas comprobaciones públicas no crean archivos de sesión de usuario. La conexión y sincronización de catálogos y ventas dispone de hasta 60 segundos; sus fallos mantienen los datos locales y muestran un mensaje comprensible.

El instalador web es acumulativo y acepta las copias originales revisadas, R1, R2, R3 y QA 1.7.4. Comprueba los archivos, protege el respaldo y permite restaurar antes de usar la actualización. Para una QA 1.7.4 completa no repite las migraciones de tablas, pues el esquema es el mismo. No sustituye la configuración privada ni las imágenes. El instalador Windows mantiene el identificador y la carpeta de datos existentes; no exige volver a vincular el equipo.

Pruebas finales ejecutadas:

- 130 comprobaciones web ampliadas: todas pasaron.
- 45 pruebas Node de escritorio, conexión, impresión y caja local: todas pasaron. Se comprobó una respuesta saludable después de ocho segundos, compatibilidad con la web anterior y conservación de la caja local ante errores.
- Cuatro comprobaciones de actualización de cachés, permisos, promociones y catálogo por sucursal: todas pasaron. Se verificaron precios exactos, opciones activas, combos y separación entre negocios.
- Regresiones HTTP R2 y R3, conexión desde el navegador, tres flujos del navegador local y sincronización real PHP con dos cajas: pasaron. La sincronización verificó ventas guardadas, reinicio, respuesta perdida, inventario, notas, cierres y recepción sin duplicados.
- Instalación acumulativa desde cinco versiones: pasó en todos los casos, incluyendo integridad, permisos, instalación repetida, respaldo y restauración segura.
- El nuevo endpoint devolvió HTTP 200 y el anterior HTTP 405 sin cookies de sesión. El nuevo responde solo cuando las cajas web están habilitadas.
- El instalador Windows se compiló, pasó la comprobación NSIS y se extrajo. Sus 13 archivos de funcionamiento coinciden con los probados y su versión es 1.7.5.

Los resultados de tiempos corresponden a datos ficticios y a una demora controlada, no a una medición de HostGator. El proxy de este entorno no permitió consultar el dominio público, por lo que no se confirmó si estos problemas son la única causa de la lentitud del sitio. Las comprobaciones físicas de Windows, Android y las impresoras siguen teniendo el alcance descrito en el informe QA anterior. Android no cambió su APK por esta corrección.

Después de instalar, comprueba abrir el sistema completo, una venta con recibo y la sincronización de cualquier venta pendiente. Si continúa el fallo, conserva el mensaje exacto y la pantalla que tarda, junto con el respaldo. No desactives ni vuelvas a vincular un equipo con ventas pendientes.
