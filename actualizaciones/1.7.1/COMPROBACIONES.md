# Comprobaciones de FISITAAP Escritorio 1.7.1

Problema corregido: si otro proceso ocupa `127.0.0.1:18765`, el servicio opcional de impresión web no podía iniciarse y el programa cerraba también la caja.

## Pruebas automatizadas

`npm test`: **15 pruebas correctas**. Incluyen autenticación y restricciones de origen, impresión sin duplicados, inventario compartido entre dos cajas, recuperación de ventas tras reiniciar, sincronización y almacenamiento seguro ante fallos.

Las pruebas nuevas ocupan un puerto real con un servicio ajeno y comprueban que:

- El programa abre el servidor real de la caja y su pantalla sin mostrar un error fatal.
- La impresión local crea un PDF aunque la conexión web esté ocupada.
- La configuración existente de impresoras y el código de conexión permanecen intactos.
- El servicio ajeno sigue atendiendo y FISITAAP no lo cierra ni lo reemplaza.
- Reintentar mientras está ocupado mantiene la caja abierta; liberar el puerto permite recuperar la conexión web.
- Reenviar el mismo trabajo tras reconectar no produce otra impresión.
- La reconexión requiere la ventana local de configuración y conserva la autenticación del servicio web.
- Una segunda instancia sale antes de iniciar cualquier servidor.
- Los errores del servidor central y los errores distintos de un puerto ocupado siguen siendo visibles.

El inicio se comprueba con el archivo real `main.js`, el servidor real de la caja y el servicio real de impresión. Las funciones de ventana y protección de Windows se simulan en esta prueba, que se ejecuta en Linux.

Pruebas de navegador Chromium: edición y recuperación de cobros, una segunda caja por red local y configuración de impresoras con puerto ocupado, guardado y reconexión correctos, sin errores JavaScript.

## Instalador y datos

El instalador se genera para Windows x64. Se verifica que el paquete incorpora exactamente los archivos corregidos y declara la versión 1.7.1. El identificador de aplicación, el nombre del programa y las carpetas de instalación y datos mantienen los valores de 1.7.0. El instalador conserva los datos de usuario.

Esta entrega no modifica la web ni el ZIP de actualización para cPanel. No se ha ejecutado el instalador en un Windows real ni se han usado impresoras físicas en este entorno; esas comprobaciones se realizan en la computadora del negocio.
