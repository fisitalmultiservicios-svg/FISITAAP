# Comprobaciones de la revisión R2

PHP/MariaDB de laboratorio, datos de prueba aislados. Las impresoras se simulan; no se certifica hardware físico.

- Suites web R2 y R3: 21 y 9 comprobaciones respectivamente, sin fallos.
- Chromium con servidor PHP real: envío a cocina desde el formulario real sin navegar a la vista de comandas, cobro real y regreso a ventas, una sola venta registrada, reintento de impresora con los mismos IDs y copia explícita con ID nuevo.
- APK existente: se ejecutó el script real native-print.js sobre la pantalla web con el canal nativo simulado; la nueva ruta imprime sin otra pantalla ni recompilación.
- Pérdida simulada de respuesta DESPUÉS de que PHP registra el envío a cocina: queda pendiente de revisión en la interfaz y no se vuelve a enviar automáticamente.
- Cobro con impresión desmarcada: registra la venta y vuelve a ventas sin crear un trabajo de impresión.
- Panel con colores comerciales amarillo/fucsia: conserva la paleta común. Catálogo y landing mantienen esos colores y el logo comercial. Panel móvil de 390 píxeles sin desbordamiento horizontal; sin logo muestra el nombre del negocio.
- Comprobaciones existentes de impresión Windows: selección silenciosa, fallo sin impresora y fallo del controlador. El flujo antiguo de impresión conserva sus pruebas de rutas.
- ZIP: integridad, igualdad con fuentes, permisos 755/644, exclusión de configuración y datos; comprobación de sintaxis de todos los PHP y del controlador JavaScript.

RESULTADOS-UI.txt recoge la ejecución del navegador. Las imágenes incluidas muestran negocios y datos ficticios del laboratorio. Las pruebas de 1.7.9 ya publicadas siguen documentadas en su entrega; esta revisión no modifica el motor offline ni sus instaladores.
