# FISITAAP Escritorio 1.7.1

Corrige el error **«listen EADDRINUSE: address already in use 127.0.0.1:18765»** que cerraba FISITAAP al abrirlo. La caja ahora abre y permite imprimir localmente aunque otra aplicación ocupe la conexión de impresión para la web. También evita iniciar servicios al abrir el programa por segunda vez.

[**Descargar instalador corregido para Windows de 64 bits**](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/actualizaciones/1.7.1/FISITAAP-Escritorio-1.7.1-Windows-x64.exe)

1. Cierra FISITAAP Escritorio en esa computadora.
2. Descarga y ejecuta el instalador nuevo. Usa la misma carpeta de instalación que antes.
3. Abre FISITAAP desde el acceso directo del escritorio.

Se conserva la carpeta de datos, con las ventas, los respaldos y la configuración. **Esta corrección se instala únicamente en Windows; no necesitas subir archivos a cPanel.** La actualización web sigue siendo [1.7.0-R3](../1.7.0-R3/README.md).

Si la conexión de impresión para la web sigue ocupada, puedes usar la caja y su impresión local. En **Imprimir → Configurar impresoras** se muestra el estado. Cuando cierres la aplicación que ocupa esa conexión, pulsa **Reintentar conexión de impresión**. FISITAAP conserva el puerto habitual y no cierra otras aplicaciones.

Los PDF locales se guardan en la carpeta `impresion` dentro de los datos del programa. Las impresoras físicas requieren su controlador instalado y su nombre seleccionado en la configuración.

Para revisar la entrega: [comprobaciones](COMPROBACIONES.md), [código fuente](FUENTES-FISITAAP-Escritorio-1.7.1.zip) y [sumas de verificación](SHA256SUMS.txt).
