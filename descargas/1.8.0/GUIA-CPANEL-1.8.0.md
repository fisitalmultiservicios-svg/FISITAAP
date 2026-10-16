# Instalar FISITAAP 1.8.0-R7 y actualizar cada negocio

Esta entrega actualiza tu instalación existente y conserva negocios, usuarios, productos, ventas, imágenes y configuración. No importes otra base SQL ni cambies config.php.

R7 incluye las correcciones anteriores y estos tres cambios: la bienvenida llena el área disponible; las demos permiten pedidos consecutivos sin recargar; Express pendientes incluye también los pedidos web de la sucursal y usa los mismos estados en español que el historial. El historial identifica cada sucursal y el portal del motorizado muestra el número de pedido.

R7 comprueba la recepción y el guardado de la imagen de bienvenida: ya no anuncia un guardado si no recibió el archivo. Indica el límite real del servidor y conserva la bienvenida anterior ante un rechazo. Incluye la corrección de R5 para el acceso de cuentas con negocio suspendido o sin asociación y las correcciones anteriores de la portada y La Ventanita. Incluye FISIChat con conversación en la portada y tiendas: diseño adaptable, respuestas, historial de sesión y contacto. En el panel maestro es tu asistente privado para revisar negocios, planes, vencimientos, contactos y actualizaciones, con revisión y confirmación para cambiar el plan o vencimiento de un negocio. El chat se aplica al instalar los archivos, también para negocios pendientes. La elección individual de cuándo activar 1.8.0 se mantiene. Windows y Android conservan sus instaladores 1.8.0.

Hay dos pasos: tú publicas los archivos una vez en cPanel; después cada dueño activa los cambios de su negocio desde **Administración → Actualizar sistema**, cuando pueda. Los negocios pendientes conservan el funcionamiento compatible con 1.7.9. No es necesario cerrar los turnos de todos los clientes para publicar.

## Si el acceso te devuelve al error500

El error reproducido ocurre al mantener abierta una cuenta de negocio suspendido o sin negocio asociado: owner-login la devuelve a admin y admin fallaba con500. R7 muestra «Tienda fuera de servicio» para una cuenta de negocio suspendido, recupera el formulario y muestra la causa y permite entrar con otra cuenta. No cambia el estado de los negocios ni reasigna cuentas por su cuenta.

Después de guardar respaldo y extraer R7 en public_html, abre **https://fisitaap.com/volver-acceso-180.php** desde el navegador bloqueado. Pulsa **Volver al acceso**, ingresa con tu cuenta maestra y continúa con el actualizador indicado abajo. Este botón cierra únicamente la sesión de ese navegador mediante confirmación; no vacía ventas locales ni modifica negocios. Si el otro navegador ya permite entrar como maestro, puedes instalar desde él directamente.

En el panel maestro revisa **Negocios → La Ventanita**: si corresponde que opere, confirma que esté activa. Si falta la asociación de un usuario, revisa su negocio antes de modificarla; no elijas otro negocio para salir del paso. La comprobación **https://fisitaap.com/diagnostico-bienvenida-180.php** identifica por separado un negocio suspendido y una asociación inexistente.

## Imagen de bienvenida para todos los negocios

La bienvenida configurada por el maestro se muestra al abrir **Inicio → Bienvenida** o **/admin**, también en negocios que aún tienen pendiente activar 1.8.0. No se activa su actualización operativa por mostrar la imagen. El maestro puede ver la imagen actual en **Web oficial → Imagen de bienvenida**. Después de cambiarla, vuelve a abrir la bienvenida o recarga la página. La imagen que ya guardaste se conserva. La imagen llena el área restante bajo el encabezado y los botones, conservando su proporción. Para llenar el área puede recortar bordes; no necesitas volver a subirla por este ajuste.

Para cambiar esta pantalla, entra como maestro a **https://fisitaap.com/master/bienvenida**. También puedes usar **Cambiar imagen de bienvenida** desde el inicio del maestro. Elige un JPG, PNG o WebP dentro del tamaño que indica el formulario, deja desmarcada **Usar publicidad predeterminada**, pulsa **Guardar bienvenida** y comprueba **Imagen actual**. Después abre **Ver bienvenida publicada**. La imagen de **Diseño y página** pertenece a la portada pública y se configura por separado.

Si no se recibe el archivo, se supera el límite o la imagen no es válida, R7 explica el motivo y no confirma un guardado. Si no había una imagen personalizada, el editor lo indica. **Usar publicidad predeterminada** restaura intencionalmente el diseño original; no la marques al subir tu imagen.

## Pedidos de demostración y Express

En ambos demos puedes hacer un pedido, cerrar el ejemplo de WhatsApp y crear otro desde el mismo catálogo. Cada pedido confirmado deja listo un envío nuevo. Si falla una respuesta, el reintento conserva la referencia del pedido para evitar duplicados; no recargues para forzar un segundo cobro.

**Express pendientes** muestra los pedidos web publicados en Express y los creados en caja de la sucursal seleccionada. Los web conservan el estado de la entrega del motorizado y se gestionan desde **Gestionar Express**. El total del pedido y el pago al motorizado aparecen separados. La confirmación de entrega sigue exigiendo el PIN en el portal del motorizado. No se marca un pedido como cobrado por mostrarlo en esta lista.

**Historial de entregas** incluye entregas activas y finalizadas de todas las sucursales del negocio. Tiene filtro de estado en español y columna de sucursal. Una entrega finalizada desaparece de pendientes y permanece en el historial. La disponibilidad del motorizado y sus sucursales autorizadas siguen aplicándose.

## A. Guardar respaldo

1. En cPanel abre **Administrador de archivos → public_html**. En tu servidor la carpeta correcta es **/home/fisitaap/public_html**. La carpeta **/home/fisitaap** que contiene mail, .cpanel y public_html es un nivel más arriba: no extraigas allí la actualización.
2. Comprueba que dentro de public_html están el index.php del sitio y config.php. **Dominios → fisitaap.com → Raíz del documento** permite confirmar esta ubicación.
3. Guarda un respaldo de los archivos del sitio, incluyendo .htaccess: **Configuración → Mostrar archivos ocultos**. Descarga el respaldo a tu computadora o usa un respaldo completo de cPanel.
4. En **phpMyAdmin**, selecciona la base de FISITAAP → **Exportar → Rápido → SQL → Exportar**. Guarda el archivo junto al respaldo del sitio, fuera de public_html.

## B. Publicar los archivos en cPanel

5. Descarga **FISITAAP-1.8.0-CPANEL-R7.zip** de GitHub. El nombre FISITAAP-1.8.0-CPANEL.zip también contiene R7. Usa R7 para esta corrección, aunque ya hayas instalado R1, R2, R3, R4, R5 o R6.
6. En **public_html**, pulsa **Cargar** y sube R7. Selecciona el ZIP → **Extraer**. Debe aparecer **public_html/actualizador-180/index.php**. Permite reemplazar los archivos anteriores de actualizador-180; conserva cualquier carpeta de respaldo que ya exista dentro.
7. No muevas payload ni copies su contenido manualmente sobre el sitio. El actualizador comprueba los archivos y decide dónde instalarlos.
8. Revisa **PHP 8.2 o 8.3**, PDO MySQL, mbstring, SimpleXML/XML, cURL y GD. GD con WebP permite optimizar imágenes. El ZIP contiene archivos **644** y carpetas **755**. Si cPanel conserva permisos viejos, corrige únicamente los del actualizador a esos valores; nunca uses 777.
9. Abre **https://fisitaap.com/owner-login** e ingresa como **maestro**.
10. En ese mismo navegador abre **https://fisitaap.com/actualizador-180/index.php**.
11. Marca **Guardé respaldo de archivos y base de datos** y pulsa **Instalar archivos** una vez. Espera el mensaje **Archivos 1.8.0 instalados y comprobados. Actualización publicada…**.
12. Este paso instala los archivos y prepara las tablas, campos e índices compartidos. No activa todos los negocios ni cierra sus turnos. La preparación inicial puede producir una espera breve mientras la base agrega índices.
13. Si aparece **distinto**, se detectó un archivo modificado fuera de los paquetes conocidos. Guarda el respaldo y los nombres que señala; no fuerces la copia. Si falla la preparación de la base, conserva los archivos instalados y el respaldo, revisa el registro con soporte y repite después de corregir la causa. Repetir una instalación correcta no duplica datos ni activa negocios pendientes.
14. En el panel maestro, **Administración → Actualizar sistema**, puedes ver los negocios actualizados y pendientes. También abre **https://fisitaap.com/master/actualizar-sistema**. **Comprobar publicación** revisa la preparación; no activa clientes. Para ayudar a uno, selecciona únicamente ese negocio y confirma su preparación.

La aplicación nueva se instala en **public_html/releases/1.8.0-r7**. Se cambia el archivo de entrada al terminar de copiar: los archivos app/assets anteriores se conservan para que las solicitudes que ya estaban ejecutándose puedan terminar.

Si el negocio ya tenía 1.8.0 activada antes de R7, mantiene esa versión y el botón mostrará que está actualizado. No se retrocede a 1.7.9.

## C. Lo que hará cada dueño cuando pueda actualizar

15. El dueño entra a su panel → **Administración → Actualizar sistema**. Mientras esté pendiente puede seguir trabajando y volver después.
16. En **cada caja de su negocio**, pulsa **Sincronizar** y confirma que quedan **cero ventas pendientes**. Hazlo también en las sucursales. El servidor no puede comprobar una cola que siga guardada en un equipo sin conexión: el dueño confirma esa parte.
17. Cobra o cancela las cuentas abiertas y cierra los turnos de **ese negocio**. Un turno es la sesión de trabajo del cajero con su fondo y ventas; cerrarlo guarda su arqueo. No significa eliminar ventas ni borrar usuarios. Los demás negocios pueden mantener sus turnos abiertos.
18. Actualiza sus Windows a **FISITAAP-Escritorio-1.8.0-Windows-x64.exe**, con FISITAAP cerrado, sobre la instalación anterior. No desinstales ni borres sus datos locales. Requiere Windows 10 o posterior de 64 bits. Si ya tienen 1.8.0 no necesitan reinstalarla por R7.
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
34. Descarga y conserva **actualizador-180/backups** fuera del sitio. Cuando lo hayas comprobado, elimina **solo** el ZIP subido, la carpeta **actualizador-180**, **volver-acceso-180.php** y **diagnostico-bienvenida-180.php**. Los dueños podrán seguir actualizando después desde Administración.
35. **No borres releases ni releases/1.8.0-r7**: contienen la aplicación en uso. Conserva también app, assets, config.php y uploads. No elimines archivos de una versión anterior durante esta instalación.

## F. Comprobar FISIChat

36. En la portada pulsa **FISIChat**. Pregunta, por ejemplo, «Tengo un restaurante» y luego «¿Puedo vender sin internet?». El chat abre en la misma página. **Hablar con un asesor** usa el WhatsApp configurado de FISITAAP.
37. En La Ventanita pregunta por productos, horarios o contacto. Usa su logo y datos publicados. Un cliente que inició sesión puede consultar únicamente sus propios pedidos de esa tienda.
38. Como maestro, abre tu panel y pulsa **FISIChat**. Prueba «Resumen de FISITAAP», «Negocios vencidos», «Actualizaciones pendientes» y «Contactos nuevos». Esta conversación es privada y distinta del chat público.
39. Para cambiar un plan o vencimiento, pide «Cambiar un plan». Elige el negocio, escribe los datos y pulsa **Revisar cambio**. Comprueba los valores anteriores y nuevos. Solo **Confirmar cambio** los guarda y registra en la bitácora. Los módulos y estados del negocio siguen gestionándose desde sus pantallas.
40. **Nueva conversación** borra el historial del chat actual. El historial se conserva solo durante la sesión y se separa por perfil, tienda, sucursal y usuario.

El chat reutiliza la conexión de IA que ya tengas configurada en tu **config.php privado** (`openai_api_key`) o en la variable del servidor `OPENAI_API_KEY`. No reemplaza esa configuración ni incluye claves en el ZIP. Sin una clave válida ofrece una guía local de funciones y consultas limitadas a los datos autorizados; para conversación libre con IA se necesita esa conexión, internet y saldo del proveedor. Nunca envíes la clave por el chat ni subas config.php a GitHub.

## Volver atrás

Si falla la copia antes de preparar la base, el actualizador restaura los archivos que alcanzó a reemplazar. La opción **Restaurar antes de publicar** solo está disponible antes de esa preparación.

Después de publicar, la restauración de archivos antiguos se bloquea, incluso si un negocio sigue pendiente. Para volver atrás se debe revisar el respaldo completo y las operaciones posteriores; copiar archivos viejos por cuenta propia puede afectar ventas y cajas.

## Alcance de las pruebas

R7 reprodujo el fallo de pedidos consecutivos y la omisión de Express web en el R6 publicado. Probó ambos demos en Chromium, reintentos tras perder la respuesta, validación sin perder carrito, doble envío, aislamiento y descarte. Probó el seguimiento de un Express con un motorizado ficticio hasta la entrega con PIN, filtros y separación por negocio/sucursal. La bienvenida llena el área restante en ocho vistas de maestro/negocio a 1366/900/768/390px. El ZIP conserva 644/755 y se comprobó su instalación sobre R6. La evidencia anterior de imágenes, activación, caja y aplicaciones se conserva en el informe; no se presenta como una nueva prueba integral. R7 no instala nada en tu cPanel ni cambia los instaladores Windows/Android. HTTPS, velocidad del hosting e impresión física deben comprobarse allí.
