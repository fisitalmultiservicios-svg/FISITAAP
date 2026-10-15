# Descargas FISITAAP 1.8.0-R4

- [Actualización cPanel R4](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R4.zip)
- [Instalador Windows x64](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Escritorio-1.8.0-Windows-x64.exe)
- [APK Android 8+](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Android-1.8.0.apk)
- [Guía paso a paso](GUIA-CPANEL-1.8.0.md)
- [Informe de QA y límites de las pruebas](INFORME-QA-1.8.0.md)
- [Comprobantes SHA256](SHA256SUMS-1.8.0.txt)

En la vista del archivo también puedes pulsar **Download raw file**. Windows ocupa aproximadamente 145 MB y se almacena con Git LFS; descarga el archivo desde su enlace, sin copiar el texto del puntero LFS.

Primero guarda respaldo de archivos y base de datos. Extrae R4 en **/home/fisitaap/public_html** y abre **https://fisitaap.com/actualizador-180/index.php** como maestro. Publica los archivos; no es necesario cerrar los turnos de todos los clientes. Cada dueño elige su momento desde **Administración → Actualizar sistema**, sincroniza/cierra solo sus cajas y activa su negocio, siguiendo la guía. Esta entrega no modifica automáticamente tu cPanel. Impresoras físicas, certificado y velocidad del hosting requieren comprobarse allí.

R4 incluye el chat en la portada y tiendas y tu asistente privado en el maestro. Consulta negocios, vencimientos, contactos y actualizaciones reales; cambiar un plan o vencimiento requiere revisar y confirmar. Mantiene las correcciones anteriores de la portada y La Ventanita y el estado de activación de cada negocio. Reutiliza la IA configurada; sin esa conexión ofrece una guía local limitada.

Las claves privadas de firma Android y config.php están excluidos de esta carpeta.

R4 instala 115 archivos funcionales y conserva los permisos 644/755. La aplicación utiliza **releases/1.8.0-r4**, que debe conservarse; actualizador-180 puede retirarse después de descargar su respaldo. Los negocios pendientes conservan compatibilidad 1.7.9. Windows/APK conservan los instaladores 1.8.0 existentes. El ZIP de nombre original también contiene R4.

El paquete [R1 anterior](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R1.zip) y el [R2 anterior](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R2.zip) y el [R3 anterior](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R3.zip) se conservan como referencia; usa R4 para la actualización por negocio.

[Diagnóstico de rutas R1](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-180-COMPROBAR-RUTA-R1.zip): tres archivos nuevos de solo lectura; extraer junto al index.php principal y abrir comprobar-ruta-180.php. Eliminar los tres al finalizar.

## Comprobar un error500 en la bienvenida

[Descargar comprobación de bienvenida D1](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-180-COMPROBAR-BIENVENIDA-D1.zip). Extrae en /home/fisitaap/public_html y abre https://fisitaap.com/diagnostico-bienvenida-180.php con la cuenta dueña afectada o la maestra. Comparte una captura del resultado. Solo realiza comprobaciones protegidas y de lectura; no sustituye la aplicación ni identifica por sí solo la causa del hosting. Elimina el PHP al terminar la revisión.
