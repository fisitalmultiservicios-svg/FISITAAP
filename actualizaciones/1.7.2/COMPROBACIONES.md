# Comprobaciones de FISITAAP Escritorio 1.7.2

El escritorio construye sus solicitudes añadiendo `/api/desktop/…` a la dirección de la aplicación. Al introducir una dirección de tienda como `/laventanita`, solicitaba `/laventanita/api/desktop/pair` y recibía HTML. El intento de leer esa página como datos mostraba un mensaje técnico.

La pantalla ahora explica de dónde copiar la Dirección web. Las respuestas que no contienen datos válidos muestran instrucciones legibles. Un intento incorrecto conserva los datos escritos en el formulario y la conexión existente. El botón muestra «Conectando…» y bloquea envíos simultáneos mientras se usa el código de una sola vez. La aplicación no cambia automáticamente de dirección ni reenvía el código a otras rutas.

## Validación

- **18 pruebas Node correctas**, incluyendo las 15 de 1.7.1 sobre inicio, impresión, autenticación, dos cajas, inventario, cobros y recuperación de operaciones.
- Servidor HTTP real de prueba: dirección de tienda devuelve HTML, aparece la ayuda, corregir la dirección permite conectar, y un error posterior conserva la configuración anterior. Las direcciones válidas que incluyen una carpeta siguen conservando esa carpeta.
- Respuestas HTML, vacías, incompletas o de formato incorrecto: no se guarda una conexión inválida ni aparecen mensajes del analizador de datos. Los errores válidos del servidor, como un código vencido, se conservan.
- Sincronización con respuesta HTML: ventas pendientes e inventario permanecen guardados. Tras recuperarse la web, la venta se recibe una sola vez.
- Navegador Chromium: error claro al introducir la dirección de tienda, conservación de los campos, corrección de la dirección, bloqueo del doble envío, ingreso del cajero y pruebas de cobro y dos cajas por red local.
- Copia PHP aislada de la web: `/api/desktop/pair` devuelve una respuesta JSON de método requerido; `/laventanita/api/desktop/pair` devuelve una página HTML con estado 404, reproduciendo la causa.

El instalador incorpora exactamente los archivos de ejecución corregidos, declara la versión 1.7.2 y mantiene el identificador, nombre y carpetas del programa. El formato de datos y el código de almacenamiento de ventas permanecen iguales.

No se usó el código de conexión de la captura ni se modificó el sitio de producción. Las solicitudes de lectura al dominio de producción no pudieron completarse desde este entorno por un bloqueo de la conexión de red. La conexión con HostGator, la instalación en Windows y las impresoras físicas se comprueban en la computadora del negocio.
