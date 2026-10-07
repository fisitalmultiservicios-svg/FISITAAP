# Comprobaciones de FISITAAP Escritorio 1.7.3

## Comportamiento

El escritorio conserva el servidor de la caja local y añade una vista del sistema web real. Ambas vistas permanecen abiertas al cambiar de modo, conservando la sesión y el estado de cada pantalla. La web carga directamente como una página principal de navegador y mantiene las pantallas, permisos y controles del servidor; no se incrusta en un iframe.

Con el negocio conectado y la web disponible, el inicio selecciona Sistema completo. Sin conexión selecciona Caja local. Una pérdida posterior de conexión muestra un aviso y conserva la pantalla web, sin sustituir un cobro en curso. La recuperación de internet no desplaza una venta local. Volver al sistema completo comprueba primero las ventas locales pendientes e intenta sincronizarlas en el equipo central.

La dirección de la web y el número de operaciones pendientes se comparten con las otras cajas mediante la información de configuración del central, sin incluir tokens ni contraseñas. La sesión web utiliza un perfil de navegador independiente, sin acceso a las funciones nativas de impresión y configuración que usa la caja local. Se conservan las correcciones de 1.7.1 y 1.7.2.

## Verificación

- **29 pruebas Node correctas:** inicio con puerto de impresión ocupado, conexión, autenticación, control de origen, impresión sin duplicados, inventario de dos cajas, almacenamiento y sincronización, además de los nuevos modos y sus cambios.
- Inicio con internet muestra la URL de acceso del sistema completo; inicio sin internet conserva la caja local. Una instalación sin conectar mantiene la pantalla de conexión.
- Cortar internet conserva el estado de la página web. Recuperarlo mantiene la venta local. Cambiar de modo conserva las dos vistas, sin recargar una página que ya estaba abierta.
- Ventas pendientes y errores de sincronización conservan la caja local. Una elección posterior de Caja local cancela un cambio a la web que estuviera esperando la red.
- Las otras cajas obtienen la web de su equipo central y mantienen la misma dirección local. Los datos públicos de configuración no contienen el token ni los hashes de contraseñas.
- Permisos, navegación y ventanas de la web permanecen limitados al sitio configurado. La web no recibe el preload ni los métodos nativos del escritorio.
- **Electron 44.5.1 real en Linux, con pantalla virtual:** ejecuta el archivo real `main.js`, carga la copia PHP de la web dentro del programa, inicia sesión normalmente y comprueba el menú completo de productos, ventas, mesas, reportes y administración. El código web no tiene `fisitaapWindows`.
- La misma prueba de Electron simula un corte, confirma que la pantalla web no cambia, entra a Caja local mediante el botón real, ingresa un cajero y guarda una venta sin internet. Al recuperar la conexión, sincroniza esa venta una sola vez y vuelve a la sesión web anterior sin recargarla.
- Desde la pantalla web real de Impresión, el navegador integrado alcanza el servicio local de impresión mediante una solicitud autenticada. Los permisos de red local y de impresión se conceden únicamente al sitio configurado.
- Las pruebas de Chromium de cobros, recuperación de peticiones y segunda caja por red local también pasan.

El menú y acceso web de la prueba Electron utilizan la copia PHP aislada. La sincronización de esa prueba usa respuestas de un servicio de prueba; no escribe operaciones en producción. La instalación en Windows y la impresión física siguen comprobándose en las computadoras del negocio.

El instalador de Windows x64 se compara con los archivos de ejecución de esta entrega, declara la versión 1.7.3 y mantiene el identificador, el nombre y las carpetas del programa. No cambia el formato de almacenamiento de las ventas. La descarga publicada se verifica con SHA256.
