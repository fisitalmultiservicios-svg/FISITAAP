# Descargas FISITAAP 1.8.0

- [Actualización cPanel R1](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R1.zip)
- [Instalador Windows x64](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Escritorio-1.8.0-Windows-x64.exe)
- [APK Android 8+](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-Android-1.8.0.apk)
- [Guía paso a paso](GUIA-CPANEL-1.8.0.md)
- [Informe de QA y límites de las pruebas](INFORME-QA-1.8.0.md)
- [Comprobantes SHA256](SHA256SUMS-1.8.0.txt)

En la vista del archivo también puedes pulsar **Download raw file**. Windows ocupa aproximadamente 145 MB y se almacena con Git LFS; descarga el archivo desde su enlace, sin copiar el texto del puntero LFS.

Primero respalda archivos y base de datos; sincroniza y cierra turnos. Instala los archivos web y actualiza las aplicaciones antes de activar, siguiendo la guía. Esta entrega no modifica automáticamente tu cPanel. Impresoras físicas, certificado y velocidad del hosting requieren comprobarse allí.

Las claves privadas de firma Android y config.php están excluidos de esta carpeta.

R1 corrige los permisos del empaquetado: archivos 644 y carpetas 755, con los mismos 109 archivos funcionales. El nombre original del ZIP también contiene la revisión corregida. Si ya extrajiste el anterior, sigue la guía para reextraer sobre actualizador-180 conservando sus respaldos y revisa los permisos que el extractor conserve.

[Diagnóstico de rutas R1](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/main/descargas/1.8.0/FISITAAP-180-COMPROBAR-RUTA-R1.zip): tres archivos nuevos de solo lectura; extraer junto al index.php principal y abrir comprobar-ruta-180.php. Eliminar los tres al finalizar.
