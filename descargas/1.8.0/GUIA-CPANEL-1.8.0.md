# Instalar FISITAAP 1.8.0-R10 y actualizar cada negocio

Esta entrega actualiza tu instalación existente y conserva negocios, usuarios, productos, ventas, imágenes y configuración. No importes otra base SQL ni cambies config.php.

R10 agrega edición de Nosotros, frases destacadas, contacto y portada del catálogo. Permite fotos de hasta 25 MB con compresión automática, quita la capa verde de la portada y hace que los botones del catálogo usen el color primario elegido. Agrega grupos de opcionales con nombre, carga de productos por Excel y alta de negocios con la versión vigente. Conserva las 16 mejoras operativas de R9 y las entregas anteriores.

Esta entrega actualiza solo la web. **Si ya instalaste Windows 1.8.1, no necesitas reinstalarlo.** Si todavía usas Windows 1.8.0, actualiza a 1.8.1 para la corrección de venta local incluida en R9. Android conserva 1.8.0. No cambies app_key de config.php.

## Editar tu página y la portada del catálogo

Después de instalar R10, entra como dueño a **Administración → Diseño y página**: https://fisitaap.com/admin/contenido.

1. **Nosotros:** cambia la etiqueta que decía «NOSOTROS», título, presentación, las tres frases destacadas y el texto del botón. Una frase vacía se oculta. Los campos muestran los valores publicados, incluyendo los anteriores cuando correspondan.
2. **Contacto:** edita correo, teléfono, WhatsApp, dirección, horario, mapa y redes sociales. Son los mismos datos de Administración → Generales y el catálogo; guardar aquí actualiza ambos. Se requiere permiso de configuración, además del permiso para editar el contenido.
3. Para el mapa, abre Google Maps → Compartir → Insertar un mapa → Copiar HTML. Pega ese código en el campo Mapa de Google. No se permite insertar mapas de dominios ajenos.
4. **Portada del catálogo o menú:** elige **Portada personalizada**, cambia título, texto e imagen y guarda. Se aplica a todas las sucursales. **Ocultar portada** retira solo el banner y mantiene categorías, productos y carrito.
5. **Conservar banners actuales** vuelve a usar las promociones que ya tenías, respetando la sucursal. **Editar banners por sucursal** abre la gestión de imágenes/promociones anteriores. Cambiar entre modos no elimina esos registros ni la portada personalizada guardada.
6. **Colores y botones:** el color principal se aplica a los botones para abrir catálogo/menú. El texto del botón se ajusta a negro o blanco para mantener contraste. El panel del dueño conserva su diseño neutral.
7. Pulsa **Guardar diseño**. La vista previa muestra lo publicado. Puedes abrir también **Abrir página completa** o **Abrir catálogo / menú**. La portada principal y la portada del catálogo se editan por separado.

## Crear productos con Excel y descargar el machote

1. En el panel del dueño abre **Catálogo → Productos → Crear productos desde Excel**. También puedes entrar a https://fisitaap.com/admin/productos-importar.
2. Pulsa **Descargar machote Excel**. El archivo FISITAAP-Machote-Productos.xlsx también está en la carpeta de descargas de esta entrega.
3. Completa únicamente la hoja **Productos**. Las hojas **Ejemplos** e **Instrucciones** sirven de ayuda y no se importan. Mantén los encabezados; SKU, nombre y precio son obligatorios. Conserva el formato Texto del SKU para no perder ceros iniciales. No uses fórmulas, símbolos de moneda ni separadores de miles.
4. Puedes cargar hasta **500 productos por archivo de 5 MB**, en .xlsx o CSV. Para archivos .xls antiguos, usa Guardar como → .xlsx. CSV admite UTF-8, coma o punto y coma. La lectura de .xlsx requiere las extensiones PHP zip y SimpleXML, usadas también por otras herramientas de la plataforma.
5. Elige la **sucursal para existencias iniciales**, selecciona el archivo y pulsa **Revisar archivo**. Revisa nombre, precio, impuesto, estado y cantidades. Las categorías que no existan se crearán. La primera revisión no guarda productos.
6. Si hay errores de filas o SKU existentes/repetidos, corrige el archivo y vuelve a cargarlo. Esta herramienta **crea productos nuevos**; no actualiza precios ni existencias de los productos anteriores.
7. Pulsa **Crear productos** tras revisar. Si falla una fila, no se crea ninguna. Repetir la misma confirmación no duplica la carga. La revisión vence en 20 minutos.
8. La existencia inicial se registra solamente en la sucursal elegida y genera su movimiento. En catálogo global, los productos pertenecen al negocio; en catálogo por sucursal se habilitan en la elegida. Imágenes, extras, grupos y proveedores se pueden completar después desde el producto.

Se necesita permiso para crear productos. Inventario y categorías nuevas requieren además sus permisos. Los permisos se verifican de nuevo al confirmar. Los valores predeterminados de las columnas opcionales están en Instrucciones del machote.

## Opcionales organizados por grupo

En **Catálogo → Opcionales**, escribe **Nombre del grupo**, por ejemplo **Tallas**, y agrega las opciones **S, M, L**. Crea otro grupo **Colores** con **Rojo, Azul**. Puedes indicar si elegir es obligatorio y si permite una o varias selecciones; asigna el grupo a productos o categorías completas.

En **Productos → Extras y opcionales** y en la asignación de categorías también puedes marcar el grupo completo. Las opciones tienen costo adicional cero; los extras con precio conservan su editor. Catálogo y caja piden las selecciones de cada grupo por separado. Editar o desactivar opciones no cambia los nombres/importes que ya quedaron guardados en ventas y pedidos anteriores.

## Negocios nuevos con la versión actual

Tras instalar y publicar R10, todo negocio creado desde **Maestro → Negocios** empieza con la versión actual, incluyendo modo Catálogo o Menú. Su dueño solo realiza el cambio normal de contraseña temporal; no necesita activar una actualización pendiente para empezar. La creación y la preparación se guardan juntas: si falla, no queda un negocio a medio preparar.

Los negocios anteriores que ya activaron 1.8.0 mantienen esa activación. Los negocios anteriores pendientes en 1.7.9 siguen eligiendo cuándo activar desde Administración → Actualizar sistema, porque pueden tener turnos y equipos pendientes. No se elige un equipo principal ni se abre un turno automáticamente.

## Fotos grandes y optimización

Selecciona JPG, PNG o WebP de hasta **25 MB por foto**. El navegador prepara la foto antes de enviarla y muestra el tamaño resultante; espera a que termine o pulsa Guardar y el formulario esperará. El servidor vuelve a validar y guardar WebP con miniatura. La reducción depende de cada imagen: en QA una foto de 11 MB llegó como 400 KB y quedó en 115 KB. Se conserva la transparencia y la orientación de fotos del teléfono. El archivo original de tu computadora no cambia.

La optimización reduce dimensiones/peso; las imágenes del sitio son estáticas. La validación de formato, dimensiones y memoria sigue activa. Ante un rechazo no se publica una imagen vacía ni se anuncia un guardado exitoso. Las imágenes ya publicadas se conservan hasta que elijas reemplazarlas o quitarlas.

La compresión previa permite trabajar normalmente con el límite PHP habitual. Si el hosting aún rechaza una carga, revisa en cPanel → MultiPHP INI Editor los límites efectivos. Para recibir originales sin compresión previa, usa upload_max_filesize=25M y post_max_size=50M; memory_limit=256M es recomendable. Los controles de seguridad de la aplicación siguen aplicándose. No reemplaces config.php ni la base de datos.

Hay dos pasos: tú publicas los archivos una vez en cPanel; después cada dueño de un negocio anterior pendiente activa los cambios de su negocio desde **Administración → Actualizar sistema**, cuando pueda. Los negocios pendientes conservan el funcionamiento compatible con 1.7.9. No es necesario cerrar los turnos de todos los clientes para publicar.

## Si el acceso te devuelve al error500

El error reproducido ocurre al mantener abierta una cuenta de negocio suspendido o sin negocio asociado: owner-login la devuelve a admin y admin fallaba con500. R9 muestra «Tienda fuera de servicio» para una cuenta de negocio suspendido, recupera el formulario y muestra la causa y permite entrar con otra cuenta. No cambia el estado de los negocios ni reasigna cuentas por su cuenta.

Después de guardar respaldo y extraer R10 en public_html, abre **https://fisitaap.com/volver-acceso-180.php** desde el navegador bloqueado. Pulsa **Volver al acceso**, ingresa con tu cuenta maestra y continúa con el actualizador indicado abajo. Este botón cierra únicamente la sesión de ese navegador mediante confirmación; no vacía ventas locales ni modifica negocios. Si el otro navegador ya permite entrar como maestro, puedes instalar desde él directamente.

En el panel maestro revisa **Negocios → La Ventanita**: si corresponde que opere, confirma que esté activa. Si falta la asociación de un usuario, revisa su negocio antes de modificarla; no elijas otro negocio para salir del paso. La comprobación **https://fisitaap.com/diagnostico-bienvenida-180.php** identifica por separado un negocio suspendido y una asociación inexistente.

## Imagen de bienvenida para todos los negocios

La bienvenida configurada por el maestro se muestra al abrir **Inicio → Bienvenida** o **/admin**, también en negocios que aún tienen pendiente activar 1.8.0. No se activa su actualización operativa por mostrar la imagen. El maestro puede ver la imagen actual en **Web oficial → Imagen de bienvenida**. Después de cambiarla, vuelve a abrir la bienvenida o recarga la página. La imagen que ya guardaste se conserva. La imagen llena el área restante bajo el encabezado y los botones, conservando su proporción. Para llenar el área puede recortar bordes; no necesitas volver a subirla por este ajuste.

Para cambiar esta pantalla, entra como maestro a **https://fisitaap.com/master/bienvenida**. También puedes usar **Cambiar imagen de bienvenida** desde el inicio del maestro. Elige un JPG, PNG o WebP dentro del tamaño que indica el formulario, deja desmarcada **Usar publicidad predeterminada**, pulsa **Guardar bienvenida** y comprueba **Imagen actual**. Después abre **Ver bienvenida publicada**. La imagen de **Diseño y página** pertenece a la portada pública y se configura por separado.

Si no se recibe el archivo, se supera el límite o la imagen no es válida, R9 explica el motivo y no confirma un guardado. Si no había una imagen personalizada, el editor lo indica. **Usar publicidad predeterminada** restaura intencionalmente el diseño original; no la marques al subir tu imagen.

## Pedidos de demostración y Express

En ambos demos puedes hacer un pedido, cerrar el ejemplo de WhatsApp y crear otro desde el mismo catálogo. Cada pedido confirmado deja listo un envío nuevo. Si falla una respuesta, el reintento conserva la referencia del pedido para evitar duplicados; no recargues para forzar un segundo cobro.

**Express pendientes** muestra los pedidos web publicados en Express y los creados en caja de la sucursal seleccionada. Los web conservan el estado de la entrega del motorizado y se gestionan desde **Gestionar Express**. El total del pedido y el pago al motorizado aparecen separados. La confirmación de entrega sigue exigiendo el PIN en el portal del motorizado. No se marca un pedido como cobrado por mostrarlo en esta lista.

**Historial de entregas** incluye entregas activas y finalizadas de todas las sucursales del negocio. Tiene filtro de estado en español y columna de sucursal. Una entrega finalizada desaparece de pendientes y permanece en el historial. La disponibilidad del motorizado y sus sucursales autorizadas siguen aplicándose.

## Pedidos, clientes y entregas · R9

1. En Venta rápida o Mesas y caja aparece **Pedidos web**, con contador. También puedes abrir Pedidos. Un pedido nuevo no imprime antes de aceptarlo, aunque la configuración anterior indicara recepción directa.
2. **Aceptar e imprimir comanda** abre una sola cuenta y envía una sola comanda a la impresora configurada. Después usa **Preparar** y **Preparado**. Cobro y entrega se registran por separado.
3. **Asignar a Express** permite liberar a los afiliados autorizados de la sucursal o elegir uno directamente. El motorizado debe tener acceso y no estar ocupado. El envío mantiene la cuenta original.
4. Para cancelar, el administrador o un rol con permiso explícito de eliminación debe escribir la justificación. Una cuenta con cobros no se anula como si estuviera sin pagar.
5. **Detalle y bitácora** muestra situación actual, ubicación, PIN y horas reales de los cambios. Desde allí tienda puede corregir dirección y resolver una incidencia indicando la solución. El historial de entregas lleva a este detalle. Los pedidos anteriores no tienen una cronología inventada.
6. El cliente proporciona nombre y teléfono. El sistema relaciona el contacto de ese negocio y ofrece sus direcciones guardadas; permite agregar otra y la primera queda principal. **No crea una contraseña con el teléfono ni inicia sesión en una cuenta existente.** Dirección principal significa preferida; no es obligatorio que la primera elegida sea la usada en cada pedido.
7. El campo de señas se sustituye por Google Maps. **Usar mi ubicación actual** solicita permiso del navegador y requiere HTTPS válido. **Buscar en Google Maps** abre el buscador; usa Compartir → Copiar enlace y pégalo. No requiere contratar una API de Google. Completa también una dirección escrita.
8. En Administración/Clientes, cada ficha permite varias direcciones y un enlace Maps por dirección. Las cuentas de cliente que ya tenían acceso conservan también sus direcciones.
9. Al elegir SINPE el comprobante JPG/PNG/WebP es obligatorio, máximo 6 MB. Se optimiza a WebP y se guarda de forma privada. El encargado puede revisarlo. Adjuntarlo no verifica el movimiento bancario ni marca la venta como cobrada.
10. Al terminar el pedido aparece el PIN y un enlace privado para conservarlo y consultar su avance. El PIN se comparte con el motorizado al recibir; un PIN incorrecto no finaliza la entrega. Conserva ese enlace.

## Afiliados, cortes y cuentas

En **Afiliados** configura el pago de próximos Express: todo el costo del envío o un porcentaje. Las condiciones se fijan al asignar/aceptar; cambiar el porcentaje no altera trabajos ya asignados. El saldo a pagar nace cuando el motorizado confirma la entrega con el PIN. El portal del motorizado muestra sus entregas y pagos.

Para pagar, prepara un corte seleccionando entregas pendientes y el origen del dinero. **Caja del turno** exige una caja nativa autorizada y turno abierto; descuenta efectivo de ese turno. Una cuenta bancaria u otra caja registrada descuenta su propio saldo. Reintentar el mismo corte no vuelve a pagarlo. El costo completo de delivery forma parte de lo cobrado al cliente; entra al efectivo del día cuando realmente se cobra en efectivo.

**Administración → Cuentas de dinero** ya existía y ahora distingue Caja principal, Caja chica, Banco/SINPE, Otra caja y Otra cuenta. Una cuenta nombrada no sustituye automáticamente al efectivo del turno. El saldo inicial debe corresponder a fondos reales.

Los trabajos históricos sin asiento de pago se identifican como anteriores al control de pagos, para revisión. No se convierten automáticamente en deudas nuevas ni se suponen pagados.

## Windows 1.8.1 y venta sin internet

1. Con internet, sincroniza y comprueba que no queden ventas pendientes antes de actualizar esa computadora. Cierra FISITAAP y ejecuta **FISITAAP-Escritorio-1.8.1-Windows-x64.exe** sobre la instalación existente. No desinstales ni borres los datos locales.
2. Abre la aplicación con internet e inicia sesión con el cajero autorizado. En Administración → Equipos y cajas, verifica que ese equipo sea el **principal único**, tenga su caja asignada y que el usuario tenga permisos para vender/abrir turno. No se elige un principal automáticamente.
3. La barra debe indicar **Caja local preparada**. Si falta preparar acceso, abre la web desde la aplicación, entra con el usuario aprobado y vuelve a comprobar. No desconectes el equipo mientras avisa que falta preparar el acceso.
4. Abre el turno correspondiente. Prueba primero una venta pequeña controlada y comprueba impresión. Si se pierde internet, usa **Caja local**. Solo el principal vende sin internet; los secundarios necesitan conexión.
5. Al regresar internet, sincroniza y verifica que la venta aparezca una sola vez. Las operaciones inciertas de cobro requieren revisar el resultado; no se fuerza un segundo cobro para resolverlas.

La corrección actualiza inmediatamente los usuarios aprobados al autorizar la sesión web en Windows. Antes, si se había vinculado el equipo sin cajero asignado, podía quedar una lista local vacía hasta una sincronización posterior y bloquear la venta sin internet.

## A. Guardar respaldo

1. En cPanel abre **Administrador de archivos → public_html**. En tu servidor la carpeta correcta es **/home/fisitaap/public_html**. La carpeta **/home/fisitaap** que contiene mail, .cpanel y public_html es un nivel más arriba: no extraigas allí la actualización.
2. Comprueba que dentro de public_html están el index.php del sitio y config.php. **Dominios → fisitaap.com → Raíz del documento** permite confirmar esta ubicación.
3. Guarda un respaldo de los archivos del sitio, incluyendo .htaccess: **Configuración → Mostrar archivos ocultos**. Descarga el respaldo a tu computadora o usa un respaldo completo de cPanel.
4. En **phpMyAdmin**, selecciona la base de FISITAAP → **Exportar → Rápido → SQL → Exportar**. Guarda el archivo junto al respaldo del sitio, fuera de public_html.

## B. Publicar los archivos en cPanel

5. Descarga **FISITAAP-1.8.0-CPANEL-R10.zip** de GitHub. El nombre FISITAAP-1.8.0-CPANEL.zip también contiene R10. Usa R10 para esta corrección, aunque ya hayas instalado R1, R2, R3, R4, R5, R6, R7, R8 o R9.
6. En **public_html**, pulsa **Cargar** y sube R10. Selecciona el ZIP → **Extraer**. Debe aparecer **public_html/actualizador-180/index.php**. Permite reemplazar los archivos anteriores de actualizador-180; conserva cualquier carpeta de respaldo que ya exista dentro.
7. No muevas payload ni copies su contenido manualmente sobre el sitio. El actualizador comprueba los archivos y decide dónde instalarlos.
8. Revisa **PHP 8.2 o 8.3**, PDO MySQL, mbstring, SimpleXML/XML, cURL y GD. GD con WebP permite optimizar imágenes. El ZIP contiene archivos **644** y carpetas **755**. Si cPanel conserva permisos viejos, corrige únicamente los del actualizador a esos valores; nunca uses 777.
9. Abre **https://fisitaap.com/owner-login** e ingresa como **maestro**.
10. En ese mismo navegador abre **https://fisitaap.com/actualizador-180/index.php**.
11. Marca **Guardé respaldo de archivos y base de datos** y pulsa **Instalar archivos** una vez. Espera el mensaje **Archivos 1.8.0 instalados y comprobados. Actualización publicada…**.
12. Este paso instala los archivos y prepara las tablas, campos e índices compartidos. No activa todos los negocios ni cierra sus turnos. La preparación inicial puede producir una espera breve mientras la base agrega índices.
13. Si aparece **distinto**, se detectó un archivo modificado fuera de los paquetes conocidos. Guarda el respaldo y los nombres que señala; no fuerces la copia. Si falla la preparación de la base, conserva los archivos instalados y el respaldo, revisa el registro con soporte y repite después de corregir la causa. Repetir una instalación correcta no duplica datos ni activa negocios pendientes.
14. En el panel maestro, **Administración → Actualizar sistema**, puedes ver los negocios actualizados y pendientes. También abre **https://fisitaap.com/master/actualizar-sistema**. **Comprobar publicación** revisa la preparación; no activa clientes. Para ayudar a uno, selecciona únicamente ese negocio y confirma su preparación.

La aplicación nueva se instala en **public_html/releases/1.8.0-r10**. Se cambia el archivo de entrada al terminar de copiar: los archivos app/assets anteriores se conservan para que las solicitudes que ya estaban ejecutándose puedan terminar.

Si el negocio ya tenía 1.8.0 activada antes de R10, mantiene esa versión y el botón mostrará que está actualizado. No se retrocede a 1.7.9.

## C. Lo que hará cada dueño cuando pueda actualizar

15. El dueño entra a su panel → **Administración → Actualizar sistema**. Mientras esté pendiente puede seguir trabajando y volver después.
16. En **cada caja de su negocio**, pulsa **Sincronizar** y confirma que quedan **cero ventas pendientes**. Hazlo también en las sucursales. El servidor no puede comprobar una cola que siga guardada en un equipo sin conexión: el dueño confirma esa parte.
17. Cobra o cancela las cuentas abiertas y cierra los turnos de **ese negocio**. Un turno es la sesión de trabajo del cajero con su fondo y ventas; cerrarlo guarda su arqueo. No significa eliminar ventas ni borrar usuarios. Los demás negocios pueden mantener sus turnos abiertos.
18. Actualiza sus Windows a **FISITAAP-Escritorio-1.8.1-Windows-x64.exe**, con FISITAAP cerrado, sobre la instalación anterior. No desinstales ni borres sus datos locales. Requiere Windows 10 o posterior de 64 bits. Windows 1.8.1 sí se necesita para esta corrección de venta local.
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
35. **No borres releases ni releases/1.8.0-r10**: contienen la aplicación en uso. Conserva también app, assets, config.php y uploads. No elimines archivos de una versión anterior durante esta instalación.

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

R10 se prueba con fotos reales ficticias mayores de 5 MB, compresión previa y en servidor, transparencia, orientación, límites seguros, editor, contacto, permisos, colores claros/oscuros, portada del catálogo, banners por sucursal, alta de negocios actualizados, grupos de opcionales y creación desde Excel con permisos y confirmación idempotente. La instalación se verifica sobre R9 publicado con negocios 179/180 y turno abierto. El paquete contiene 126 archivos con permisos644/755. El informe separa estas pruebas de las históricas. No se accede al hosting ni se prueba un nuevo instalador nativo; Windows/APK no cambian en R10.
