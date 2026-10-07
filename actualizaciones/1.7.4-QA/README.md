# FISITAAP 1.7.4-QA

Actualización acumulativa de la web y **Windows 1.7.4**, con correcciones de ventas, seguridad, sincronización, impresión y lógica de entregas. Incluye las entregas web anteriores R1, R2 y R3.

- [**Descargar ZIP para cPanel**](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/actualizaciones/1.7.4-QA/FISITAAP-1.7.4-QA-FULL-cPanel.zip)
- [**Descargar instalador Windows x64**](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/actualizaciones/1.7.4-QA/FISITAAP-Escritorio-1.7.4-Windows-x64.exe)
- [**Guía para subirlo e instalarlo**](GUIA-CPANEL.txt)
- [**Informe del QA y revisión de uso**](INFORME-QA.md)
- [Informe en PDF](INFORME-QA.pdf)

En cPanel, extrae el ZIP en la carpeta donde está el `index.php` de tu aplicación. Con la cuenta **maestro**, abre tu dirección seguida de `/actualizacion-fisitaap-r4/` y sigue la comprobación. Guarda previamente el respaldo completo de archivos y base de datos y detén las cajas mientras actualizas.

Windows se instala en las computadoras, en la misma carpeta de la versión anterior. Actualiza primero el central y después las cajas, tras terminar y sincronizar las ventas pendientes. Conserva sus datos locales.

**Android continúa con [APK 1.0.0](../android-1.0.0/README.md)**. Este QA agregó pruebas a sus fuentes, sin cambiar el funcionamiento del APK; no necesitas reinstalar Android por esta revisión.

Las pruebas finales disponibles pasaron. El informe distingue las pruebas cloud de la instalación en HostGator, dispositivos físicos, controladores e impresoras reales que siguen pendientes.

Para revisión técnica: [resultados](RESULTADOS-QA.json), [fuentes Windows](FUENTES-FISITAAP-Escritorio-1.7.4.zip), [fuentes Android con QA](FUENTES-FISITAAP-Android-1.0.0-QA.zip), [pruebas añadidas](../../qa/README.md) y [SHA256](SHA256SUMS.txt).
