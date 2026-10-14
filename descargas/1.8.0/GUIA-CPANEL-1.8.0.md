# Instalar FISITAAP 1.8.0-R3 y actualizar cada negocio

Esta entrega actualiza tu instalación existente y conserva negocios, usuarios, productos, ventas, imágenes y configuración. No importes otra base SQL ni cambies config.php.

R3 corrige el título tapado de la portada, restaura el diseño de La Ventanita y otras páginas de negocios, y cambia el ejemplo de FISIChat por una respuesta directa sobre productos con poco stock. Estas correcciones visuales se aplican al instalar los archivos, también para negocios pendientes. La elección individual de cuándo activar 1.8.0 se mantiene. Windows y Android conservan sus instaladores 1.8.0.

Hay dos pasos: tú publicas los archivos una vez en cPanel; después cada dueño activa los cambios de su negocio desde **Administración → Actualizar sistema**, cuando pueda. Los negocios pendientes conservan el funcionamiento compatible con 1.7.9. No es necesario cerrar los turnos de todos los clientes para publicar.

## A. Guardar respaldo

1. En cPanel abre **Administrador de archivos → public_html**. En tu servidor la carpeta correcta es **/home/fisitaap/public_html**. La carpeta **/home/fisitaap** que contiene mail, .cpanel y public_html es un nivel más arriba: no extraigas allí la actualización.
2. Comprueba que dentro de public_html están el index.php del sitio y config.php. **Dominios → fisitaap.com → Raíz del documento** permite confirmar esta ubicación.
3. Guarda un respaldo de los archivos del sitio, incluyendo .htaccess: **Configuración → Mostrar archivos ocultos**. Descarga el respaldo a tu computadora o usa un respaldo completo de cPanel.
4. En **phpMyAdmin**, selecciona la base de FISITAAP → **Exportar → Rápido → SQL → Exportar**. Guarda el archivo junto al respaldo del sitio, fuera de public_html.

## B. Publicar los archivos en cPanel

5. Descarga **FISITAAP-1.8.0-CPANEL-R3.zip** de GitHub. El nombre FISITAAP-1.8.0-CPANEL.zip también contiene R3. Usa R3 para esta corrección, aunque ya hayas instalado R1 o R2.
6. En **public_html**, pulsa **Cargar** y sube R3. Selecciona el ZIP → **Extraer**. Debe aparecer **public_html/actualizador-180/index.php**. Permite reemplazar los archivos anteriores de actualizador-180; conserva cualquier carpeta de respaldo que ya exista dentro.
7. No muevas payload ni copies su contenido manualmente sobre el sitio. El actualizador comprueba los archivos y decide dónde instalarlos.
8. Revisa **PHP 8.2 o 8.3**, PDO MySQL, mbstring, SimpleXML/XML y GD. GD con WebP permite optimizar imágenes. El ZIP contiene archivos **644** y carpetas **755**. Si cPanel conserva permisos viejos, corrige únicamente los del actualizador a esos valores; nunca uses 777.
9. Abre **https://fisitaap.com/owner-login** e ingresa como **maestro**.
10. En ese mismo navegador abre **https://fisitaap.com/actualizador-180/index.php**.
11. Marca **Guardé respaldo de archivos y base de datos** y pulsa **Instalar archivos** una vez. Espera el mensaje **Archivos 1.8.0 instalados y comprobados. Actualización publicada…**.
12. Este paso instala los archivos y prepara las tablas, campos e índices compartidos. No activa todos los negocios ni cierra sus turnos. La preparación inicial puede producir una espera breve mientras la base agrega índices.
13. Si aparece **distinto**, se detectó un archivo modificado fuera de los paquetes conocidos. Guarda el respaldo y los nombres que señala; no fuerces la copia. Si falla la preparación de la base, conserva los archivos instalados y el respaldo, revisa el registro con soporte y repite después de corregir la causa. Repetir una instalación correcta no duplica datos ni activa negocios pendientes.
14. En el panel maestro, **Administración → Actualizar sistema**, puedes ver los negocios actualizados y pendientes. También abre **https://fisitaap.com/master/actualizar-sistema**. **Comprobar publicación** revisa la preparación; no activa clientes. Para ayudar a uno, selecciona únicamente ese negocio y confirma su preparación.

La aplicación nueva se instala en **public_html/releases/1.8.0-r3**. Se cambia el archivo de entrada al terminar de copiar: los archivos app/assets anteriores se conservan para que las solicitudes que ya estaban ejecutándose puedan terminar.

Si el negocio ya tenía 1.8.0 activada antes de R3, mantiene esa versión y el botón mostrará que está actualizado. No se retrocede a 1.7.9.

## C. Lo que hará cada dueño cuando pueda actualizar

15. El dueño entra a su panel → **Administración → Actualizar sistema**. Mientras esté pendiente puede seguir trabajando y volver después.
16. En **cada caja de su negocio**, pulsa **Sincronizar** y confirma que quedan **cero ventas pendientes**. Hazlo también en las sucursales. El servidor no puede comprobar una cola que siga guardada en un equipo sin conexión: el dueño confirma esa parte.
17. Cobra o cancela las cuentas abiertas y cierra los turnos de **ese negocio**. Un turno es la sesión de trabajo del cajero con su fondo y ventas; cerrarlo guarda su arqueo. No significa eliminar ventas ni borrar usuarios. Los demás negocios pueden mantener sus turnos abiertos.
18. Actualiza sus Windows a **FISITAAP-Escritorio-1.8.0-Windows-x64.exe**, con FISITAAP cerrado, sobre la instalación anterior. No desinstales ni borres sus datos locales. Requiere Windows 10 o posterior de 64 bits. Si ya tienen 1.8.0 no necesitan reinstalarla por R3.
19. Para un Android nuevo instala **FISITAAP-Android-1.8.0.apk**; requiere Android 8 o posterior. Autoriza instalar desde ese navegador cuando Android lo solicite. Si ya tiene nuestra versión 1.8.0 puede conservarla. Guarda el APK y el respaldo privado de su firma; la clave privada nunca va en cPanel ni GitHub.
20. Durante esta preparación, las cajas **de ese negocio** deben dejar de vender. Con todas sincronizadas, turnos cerrados y aplicaciones actualizadas, marca la confirmación y pulsa **Actualizar sistema**.
21. Espera **Actualización 1.8.0 activada para este negocio**. La configuración se convierte dentro de una transacción y solo se marca actualizada al terminar. El servidor vuelve a revisar turnos y cuentas; si queda alguno, lo indicará sin cerrar ni borrar nada por su cuenta.
22. Recarga el panel y sincroniza las aplicaciones para recibir la configuración nueva. Ya puedes abrir los nuevos turnos. Repetir el botón no vuelve a convertir los datos ni duplica operaciones.

El botón prepara y activa el negocio en la web. Los instaladores de Windows/Android se descargan desde esa pantalla y se ejecutan en sus equipos; el navegador no los instala automáticamente.

## D. Equipos e impresión después de activar

23. **Administración → Equipos y cajas**: revisa sucursal, caja y usuarios aprobados por equipo. El cajero usa su usuario y contraseña habituales.
24. Para un equipo nuevo, crea su código. En Windows usa **FISITAAP → Conectar este equipo al negocio**; en Android, **Conectar y autorizar equipo**. Dirección: **https://fisitaap.com**, número de equipo y código. No uses la dirección del catálogo. HTTPS debe tener certificado válido.
25. Elige **un único principal por negocio**, Windows o Android, y sincronízalo. Ese equipo puede buscar y vender sin internet; las demás cajas necesitan conexión. Para cambiarlo, sincroniza, cierra sus turnos y libéralo desde ese equipo primero.
26. Android puede autorizarse directamente en la web o descubrir la dirección web mediante el central Windows en la red local; se autoriza como equipo propio.
27. **Administración → Impresión**: configura recibos, cocina, bar, despacho y puntos adicionales, con sucursal, equipo, papel 58/80 mm y destino.
28. En Windows selecciona una impresora instalada o una térmica de red. Android utiliza térmicas de red compatibles con **ESC/POS**, con IP y puerto. El ancho 58/80 mm es el papel y no garantiza compatibilidad. También puedes elegir PDF.
29. Pulsa **Probar** en cada punto. La gaveta se habilita tanto en caja como en recibos; abre solo con efectivo en un recibo original, nunca con comandas o copias. No necesitas FISITAAP Print para estos envíos. Si queda **sin confirmar**, revisa papel/conexión antes de pedir una copia.

## E. Comprobar y retirar el instalador

30. Comprueba portada, acceso a negocios, La Ventanita y ambos demos, incluido **Otros demos**. Las demos usan copias independientes y dinero virtual; la impresión es simulada.
31. En el negocio actualizado prueba una venta, recibo/comanda, efectivo/vuelto y cierre. En el principal sincronizado, desconecta internet, vende con productos de prueba y reinicia: la venta debe seguir pendiente. Recupera internet, sincroniza y confirma que llegó una sola vez. Cierra el turno de prueba; no borres ventas en phpMyAdmin.
32. Confirma que una caja secundaria necesita conexión y que compras, cuentas, mesas, reportes y permisos funcionan según lo que use el negocio.
33. Si un navegador conserva un diseño anterior, recarga con **Ctrl+F5**. Los archivos nuevos se sirven desde una carpeta de versión propia.
34. Descarga y conserva **actualizador-180/backups** fuera del sitio. Cuando lo hayas comprobado, elimina **solo** el ZIP subido y la carpeta **actualizador-180**. Los dueños podrán seguir actualizando después desde Administración.
35. **No borres releases ni releases/1.8.0-r3**: contienen la aplicación en uso. Conserva también app, assets, config.php y uploads. No elimines archivos de una versión anterior durante esta instalación.

## Volver atrás

Si falla la copia antes de preparar la base, el actualizador restaura los archivos que alcanzó a reemplazar. La opción **Restaurar antes de publicar** solo está disponible antes de esa preparación.

Después de publicar, la restauración de archivos antiguos se bloquea, incluso si un negocio sigue pendiente. Para volver atrás se debe revisar el respaldo completo y las operaciones posteriores; copiar archivos viejos por cuenta propia puede afectar ventas y cajas.

## Alcance de las pruebas

Se verificaron PHP/MariaDB reales, navegador, venta con cliente 1.7.9 junto a un negocio 1.8.0, actualización individual, sincronización repetida, aislamiento entre negocios, permisos del ZIP e instalación sobre copias ficticias. Windows y Android conservan los instaladores 1.8.0 ya construidos; R3 cambia la web y el proceso de actualización. La entrega no instala nada en tu cPanel. Deben comprobarse allí la velocidad del hosting, HTTPS y la impresión/gaveta con equipos físicos.
