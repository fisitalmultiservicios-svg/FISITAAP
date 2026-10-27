# FISITAAP web 1.8.0-R15 · Windows 1.8.1

R15 corrige la diferencia entre R14 y el HTML aprobado **Línea + FISITAAP**. Reutiliza los estilos de esa propuesta y cambia la estructura PHP: cabecera, resumen con gráfico/pedidos, factura amplia a la izquierda y mesas con detalle lateral. Los datos, controles, permisos y procesos pertenecen a la aplicación real.

- [Descargar actualización cPanel R15](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R15.zip)
- [Comparar el HTML con las pantallas reales](https://github.com/fisitalmultiservicios-svg/FISITAAP/blob/main/descargas/1.8.0/VISTAS-R15.md)
- [Guía de instalación](GUIA-CPANEL-1.8.0.md)
- [Pruebas y alcance](INFORME-QA-1.8.0.md)

Guarda respaldo de archivos y base de datos. Extrae el ZIP en **/home/fisitaap/public_html** y, como maestro, abre **https://fisitaap.com/actualizador-180/index.php**. Pulsa **Instalar archivos** tras confirmar tu respaldo. Extraer el ZIP solamente no cambia la versión activa. Conserva **releases/1.8.0-r15** y las versiones anteriores.

La web oficial de presentación permanece igual. Las tiendas conservan sus colores, imágenes y plano real. En celular se alternan Facturación/Productos con el total y cobro visibles; en tablet se aprovechan las dos columnas. Resumen y Bienvenida son accesos separados, por lo que se conserva la imagen de bienvenida configurada por el maestro.

El paquete incluye 137 archivos funcionales con permisos 644/755. Conserva configuración, imágenes, usuarios, ventas, turnos, activaciones y conexión de IA; no contiene config.php, datos ni claves privadas. Los ZIP R1…R14 permanecen como historial. La versión sin sufijo también corresponde a R15.

No se requiere reinstalar Windows 1.8.1 ni Android 1.8.0. El nuevo diseño corresponde a la aplicación web; la caja local de Windows sin internet mantiene la interfaz de su instalador. Las operaciones de efectivo conservan la autorización de equipo, caja y turno.

Las pruebas usan PHP/MariaDB/Chromium con datos ficticios. No envían WhatsApp ni pagos reales, no conectan impresoras y no sustituyen la revisión en dispositivos físicos. Publicar el ZIP en el repositorio no lo instala automáticamente en el hosting.
