# FISITAAP 1.7.9-R2

Entrega web acumulativa: incorpora 1.7.9, R1 del activador y ajustes de impresión, apariencia y marca del negocio. No cambia la base de datos ni requiere recompilar Windows/Android 1.7.9.

La impresión instalada obtiene el comprobante por una petición autenticada al sitio y envía los trabajos mediante los puentes nativos existentes. No ejecuta los scripts de la respuesta ni incorpora el documento entero. Conserva los identificadores al reintentar y nunca repite automáticamente el POST de venta. Los fallos de transporte requieren revisar el estado; los fallos de impresora permiten reintentar solo el trabajo. Los navegadores sin puente conservan su flujo manual.

El estilo administrativo se carga únicamente en vistas administrativas. El controlador de impresión se carga únicamente en rutas del panel. La paleta pública de cada negocio permanece independiente. Se versionan los recursos para evitar que la caché conserve el diseño anterior.

Los logotipos de administración y terminal, cabecera y pie del catálogo pertenecen a la tienda. El pie público reserva la marca FISITAAP para la oferta de la plataforma. Si falta el logo se muestra el nombre, también en pantalla móvil.

Sigue LEEME.txt. Para una instalación aún no activada consulta también la guía 1.7.9. El ZIP excluye config.php, .htaccess, uploads y archivos SQL. web/ contiene exactamente las fuentes empaquetadas.
