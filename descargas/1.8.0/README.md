# FISITAAP web 1.8.0-R14 · Windows 1.8.1

R14 aplica al sistema real la mezcla aprobada de Línea y la identidad de FISITAAP. Renueva paneles, catálogo, acceso, cuenta de comprador y portal del motorizado. En Venta rápida la factura ocupa la izquierda con el 60 % del ancho y la carga rápida la derecha con el 40 %. En celular se alternan Facturación y Productos; en tableta la distribución se adapta al ancho disponible. Conserva el plano real de mesas, sus objetos y los procesos de venta. La web oficial de presentación mantiene su diseño y navegación.

- [Descargar actualización cPanel R14](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R14.zip)
- [Guía de instalación y uso](GUIA-CPANEL-1.8.0.md)
- [Ver capturas de celular y tableta](VISTAS-R14.md)
- [Pruebas y alcance](INFORME-QA-1.8.0.md)
- [Comprobación SHA256](SHA256SUMS-1.8.0.txt)
- [Machote Excel existente](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-Machote-Productos-R11.xlsx)

Guarda respaldo de archivos y base de datos. Extrae el ZIP en **/home/fisitaap/public_html** y, con tu sesión maestra, abre **https://fisitaap.com/actualizador-180/index.php**. Marca el respaldo guardado y pulsa **Instalar archivos**. La extracción por sí sola no cambia la versión activa. Conserva **releases/1.8.0-r14**; no borres releases anteriores mientras tengas que conservar sus respaldos.

La actualización conserva config.php, uploads, datos, turnos, permisos, activaciones y la conexión de IA. No importa SQL ni requiere reinstalar Windows 1.8.1 o Android 1.8.0. El nuevo diseño corresponde a la web; la caja local de Windows sin internet conserva la interfaz de su instalador. Los negocios que ya usan 1.8.0 reciben el diseño al instalar los archivos; los negocios antiguos todavía pendientes conservan su estado de activación.

El ZIP incluye 133 archivos funcionales, con permisos 644/755 y sin configuración privada, datos de negocios, contraseñas ni claves. El ZIP sin sufijo también corresponde a R14. Los ZIP R1…R13 permanecen como historial.

Se mantienen los grupos de opcionales, importación Excel, slider configurable, reglas de cantidades, flujo de pedidos/entregas, navegación agrupada y perfiles de FISIChat de las revisiones anteriores. Las operaciones monetarias siguen requiriendo una caja y equipo autorizados; el cambio visual no modifica estos permisos.

Las pruebas se ejecutan en PHP/MariaDB/Chromium con datos ficticios y tamaños de computadora, celular y tableta. No sustituyen las verificaciones en dispositivos físicos ni conectan impresoras, WhatsApp o pagos reales. La publicación en el repositorio entrega los archivos; **no instala automáticamente la actualización en el hosting**.
