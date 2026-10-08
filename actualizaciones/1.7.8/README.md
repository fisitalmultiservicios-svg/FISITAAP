# Actualización coordinada 1.7.8

Incluye web 1.7.8, Windows 1.7.8 y Android 1.1.0. Sigue GUIA-ACTUALIZACION.txt; guarda respaldos y sincroniza antes de actualizar.

Cambios: recuperación del formulario cuando vence CSRF; estilos públicos R3 conservados; caché de recursos versionada; agregación del panel con menos recorridos de ventas; índices adicionales instalados por ruta autenticada; sesiones estrictas; impresión Windows mediante IPC restringido al sitio configurado; Android sin doble ruta de impresión y sondeo JSON correcto; elección explícita Android web directa o Windows central.

El ZIP web contiene todos los archivos PHP de app, todos los recursos assets e index.php, sin configuración, .htaccess, archivos subidos ni datos. Esta entrega requiere R1/R2/R3 previamente activos. No es un instalador de una base vacía.

La carpeta web contiene exactamente las fuentes empaquetadas para revisar cambios. Los instaladores se construyen con las fuentes desktop y android de este mismo commit.

No hay medición del hosting de producción ni certificación de cada impresora física. Android directo a web necesita internet para vender; sin internet sigue disponible la caja del Windows central si se configuró.
