# FISITAAP 1.8.0-R9 · Windows 1.8.1

Esta entrega incorpora las 16 solicitudes de pedidos, clientes, Maps, SINPE, entregas, pagos y venta local. Conserva las mejoras anteriores de bienvenida, demos, impresión y FISIChat. Pedidos ahora es una lista con estado y acciones, según la solicitud más reciente.

- [ZIP para cPanel R9](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R9.zip)
- [Windows 1.8.1 x64](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Escritorio-1.8.1-Windows-x64.exe)
- [Android 1.8.0 existente](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Android-1.8.0.apk)
- [Guía de instalación](GUIA-CPANEL-1.8.0.md)
- [Informe de pruebas y revisión de los 16 puntos](INFORME-QA-1.8.0.md)
- [SHA256 de los archivos](SHA256SUMS-1.8.0.txt)

Instala primero R9 en **/home/fisitaap/public_html** y abre **https://fisitaap.com/actualizador-180/index.php** como maestro, con respaldo de archivos y BD. Después actualiza Windows sobre su instalación existente, sin desinstalar ni borrar datos. Si el negocio ya activó1.8.0, conserva esa activación. Los pendientes deciden cuándo activar desde Administración → Actualizar sistema.

Los clientes compran con nombre y teléfono, eligiendo o guardando direcciones. Esto no inicia sesión en cuentas existentes ni crea contraseñas con el teléfono. Google Maps permite obtener ubicación con permiso o buscar y pegar un enlace. SINPE exige comprobante privado; no verifica el abono bancario automáticamente. El pedido muestra su PIN y seguimiento privado.

Windows 1.8.1 corrige el acceso local que podía quedar vacío tras vincular antes de asignar cajero/caja. Comprueba **Caja local preparada** antes de desconectar. Solo el equipo principal autorizado vende sin internet. La prueba automatizada incluye venta, reinicio y sincronización única; queda por probar el instalador en Windows físico y las impresoras reales.

El ZIP contiene 122 archivos funcionales en **releases/1.8.0-r9**, con permisos644/755. No incluye config.php, BD, comprobantes, imágenes de negocios ni claves privadas. El ZIP sin sufijo también contieneR9. Conserva releases/1.8.0-r9; puedes retirar actualizador-180 al descargar su respaldo y completar las comprobaciones.

R1…R8, Windows 1.8.0 y el APK1.8.0 se conservan como archivos históricos. Esta entrega no instala nada en el hosting por sí sola. El informe especifica qué fue probado y qué requiere equipos físicos.
