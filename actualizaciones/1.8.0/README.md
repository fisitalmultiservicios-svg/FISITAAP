# FISITAAP web 1.8.0-R11 · Windows 1.8.1

R11 agrega un slider de tres imágenes con tiempo configurable, reglas de unidad/granel, menús desplegables y ajuste de Venta rápida/Mesas con zoom. Simplifica el checkout y abre WhatsApp sin un diálogo del PIN. Los clientes nuevos reciben acceso por teléfono; Mis pedidos muestra PIN y trazabilidad. Conserva Excel, grupos de opcionales, imágenes optimizadas y alta de negocios con la versión actual.

- [Descargar ZIP cPanel R11](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R11.zip)
- [Machote Excel R11](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-Machote-Productos-R11.xlsx)
- [Guía de instalación y uso](GUIA-CPANEL.md)
- [Revisión de solicitudes y evidencia QA](INFORME-QA.md)
- [Comprobación SHA256](../../descargas/1.8.0/SHA256SUMS-1.8.0.txt)
- [Windows 1.8.1 existente](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Escritorio-1.8.1-Windows-x64.exe)
- [Android 1.8.0 existente](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Android-1.8.0.apk)

Guarda respaldo de archivos y BD. Extrae el ZIP en **/home/fisitaap/public_html** y abre **https://fisitaap.com/actualizador-180/index.php** como maestro. La actualización conserva config.php, imágenes, ventas, turnos y activaciones. Añade una columna de acceso a clientes; no importa otra base SQL. Los negocios nuevos siguen iniciando actualizados; los anteriores pendientes deciden cuándo activar.

R11 actualiza la web. Si ya usas Windows 1.8.1, sincroniza su catálogo con internet para adoptar las reglas de cantidades antes de vender sin red; no necesitas reinstalarlo. Los binarios Windows/Android no cambiaron. No borres ventas locales pendientes.

Para los compradores nuevos el usuario y la contraseña inicial son el teléfono. La clave es fácil de conocer: se ofrece Cambiar mi contraseña. Las cuentas existentes conservan su clave; conocer su teléfono no la restablece ni inicia esa cuenta al crear otro pedido. El comprador confirma el envío dentro de WhatsApp; la web abre el mensaje preparado y guarda previamente la solicitud.

El ZIP contiene130 archivos funcionales en **releases/1.8.0-r11**, con permisos644/755 y sin configuración privada, datos, claves ni imágenes de negocios. Conserva esa carpeta. Después de completar las pruebas y descargar su respaldo, puedes retirar actualizador-180. El ZIP sin sufijo también contieneR11. Los ZIP R1…R10 permanecen históricos.

Pruebas con PHP/MariaDB/Chromium y datos ficticios; WhatsApp y ubicación fueron simulados y no se enviaron mensajes. El informe diferencia pruebas actuales de las históricas y de las verificaciones físicas pendientes. Publicar estos archivos no instala nada automáticamente en tu hosting.
