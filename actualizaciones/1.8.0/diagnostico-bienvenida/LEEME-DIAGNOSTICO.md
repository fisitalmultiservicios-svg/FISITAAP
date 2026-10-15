# Comprobar el error de bienvenida

La captura de Métricas → Errores muestra avisos INFO de reinicio de PHP. No identifica la causa del error HTTP500. Este archivo realiza una comprobación aislada usando las funciones de la versión instalada y muestra los errores que encuentre.

1. Descarga FISITAAP-180-COMPROBAR-BIENVENIDA-D1.zip.
2. En cPanel → Administrador de archivos abre **/home/fisitaap/public_html**.
3. Sube y extrae el ZIP. Debe quedar **public_html/diagnostico-bienvenida-180.php**, junto al index.php principal.
4. Usa el navegador donde ya iniciaste sesión como dueño del negocio que falla. Si la sesión venció, entra en https://fisitaap.com/owner-login y después abre el enlace del paso siguiente. La cuenta maestra también puede comprobar su propia bienvenida.
5. Abre **https://fisitaap.com/diagnostico-bienvenida-180.php**.
6. Envía una captura completa de los resultados, especialmente cualquier apartado rojo. Si todo está verde, envía también esa captura: permite identificar la versión y el perfil comprobados.
7. Al terminar la revisión, elimina únicamente **diagnostico-bienvenida-180.php**, el ZIP y este LEEME.

No se ejecuta el actualizador. La prueba requiere cuenta maestra o dueña activa con contraseña vigente, abre una transacción SQL de solo lectura y no guarda cambios de sesión. Descarta el HTML generado: no carga la imagen ni ejecuta el chat en el navegador. No usa la IA. Muestra tipo de error, archivo y línea sin argumentos de las llamadas y oculta claves conocidas de la configuración. No imprime config.php, cookies, contraseñas ni datos de ventas.

Comprueba la resolución de imagen y la generación de la bienvenida/panel con las funciones de la versión activa; no ejecuta el enrutador original. Incluye el lector de avisos que el index.php normal declara fuera de las bibliotecas. Un resultado correcto no descarta un fallo del enrutamiento, otro usuario, caché de PHP o del hosting. El diagnóstico no constituye todavía una corrección de la causa del sitio.

Probado contra R4 publicado en PHP8.3/MariaDB con datos ficticios: autorización, errores y fallos fatales simulados, claves ocultas y prohibición de escrituras SQL. No se accedió al cPanel real. El ZIP tiene archivos644 y no sustituye ningún archivo funcional existente.
