# FISITAAP ERP 4.40 — instalador para Windows

[Descargar instalador para Windows 10/11 de 64 bits](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/descargas-windows/FISITAAP-ERP-4.40-Instalador.exe)

Este directorio distribuye el instalador de la aplicación local Windows basado en el paquete oficial ERP 4.40 y los archivos de construcción proporcionados por su propietario. La rama `descargas-windows` es independiente de la rama principal del repositorio; su contenido no representa el código fuente de ERP 4.40.

## Instalación

1. Descargar y ejecutar `FISITAAP-ERP-4.40-Instalador.exe` en Windows 10/11 de 64 bits.
2. Mantener activada la opción de red local si otros equipos deben conectarse a esta computadora.
3. Abrir FISITAAP y completar la conexión con la empresa mediante el flujo vigente de autorización y código de tres dígitos. La primera conexión y descarga requieren internet.
4. Para dispositivos secundarios, usar `http://IP-DE-LA-PC:8020/app/` en la misma red. El equipo principal debe permanecer encendido.

El programa conserva la lógica actual del ERP para trabajo local, sincronización e impresión. Las impresoras usan los controladores instalados en Windows. El instalador no actualiza el servidor cPanel y no contiene datos de empresas ni credenciales de usuarios.

## Validación y límites

Se verificaron la integridad de los originales, 91 pruebas existentes y 7 pruebas del lanzador. El ejecutable compilado pasó su autoprueba y una prueba de arranque sin conexión bajo Wine. El asistente de instalación, los permisos de red y una impresora física aún requieren validación en un equipo Windows real antes de utilizarlo en producción.

El instalador y la aplicación no llevan firma Authenticode. La comprobación SHA-256 está en `SHA256SUMS.txt`.

Para actualizaciones automáticas del programa se debe usar el ejecutable de la aplicación y el manifiesto que espera el publicador de la nube. No se debe cargar este instalador en el lugar del ejecutable del actualizador.
