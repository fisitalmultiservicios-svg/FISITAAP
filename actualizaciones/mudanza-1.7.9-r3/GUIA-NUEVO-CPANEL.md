# FISITAAP: instalar en el nuevo cPanel

Esta entrega revisada contiene el sistema completo **1.7.9-R3**, con sus correcciones anteriores, y las herramientas para la mudanza selectiva. No necesitas instalar los ZIP anteriores. **Es para una carpeta nueva del nuevo hosting, no para limpiar el servidor actual.**

Conservaremos **La Ventanita**, los dos demos originales, la cuenta maestra y los usuarios, clientes y registros necesarios para esos negocios. La Ventanita conserva productos, ventas, inventario, saldos y vinculaciones de equipos. Los otros negocios se eliminan solamente de la copia nueva. Las configuraciones generales y planes necesarios también se conservan.

El ZIP público contiene código; **no contiene tu base real, config.php, contraseñas ni fotos subidas**. Primero importaremos tu respaldo privado y después seleccionaremos qué conservar. No publiques esos respaldos en GitHub ni los envíes por chat.

Esta guía supone que mantendrás **fisitaap.com**, instalado directamente en la raíz del dominio. Si cambias también de dominio o usas una subcarpeta, no cambies aún los DNS: esa variante requiere ajustar y verificar las direcciones.

## 1. Descargar y guardar

Descarga `FISITAAP-1.7.9-R3-NUEVO-CPANEL.zip`. Además, desde el cPanel anterior, guarda en tu computadora:

- `config.php`, de la carpeta donde está el sitio. Es privado.
- La carpeta completa `uploads`, comprimida en ZIP: fotos, logos y documentos.
- Un respaldo SQL completo de la base, con estructura y datos de todas las tablas.
- Un respaldo privado completo de los archivos del sitio, por si necesitas volver atrás.

Para identificar la base correcta, abre privadamente config.php y mira `db_name`. En phpMyAdmin selecciona esa base → Exportar → Personalizado → todas las tablas → SQL → estructura y datos → compresión gzip. No incluyas `CREATE DATABASE` ni `USE` con el nombre anterior. Si no sabes configurar eso, solicita al proveedor un respaldo SQL portable.

Comprime uploads desde el Administrador de archivos, descarga el ZIP y elimina únicamente el ZIP temporal del sitio anterior. Deja las imágenes originales donde están. Respalda también archivos personalizados de otras carpetas: el paquete no puede conocerlos.

## 2. Preparar el hosting nuevo, sin mover todavía el dominio

1. Pide la IP nueva y agrega fisitaap.com en **Dominios**. Anota su **raíz del documento**: puede ser public_html u otra carpeta.
2. Selecciona **PHP 8.3** para ese dominio. Solicita las extensiones PDO MySQL, mbstring, OpenSSL, cURL, fileinfo, GD, DOM/XML y Phar; OPcache debe estar activo si el proveedor lo permite. Necesitamos mod_rewrite y permisos para que .htaccess funcione.
3. En **Bases de datos MySQL**, crea una base nueva y un usuario con contraseña fuerte. Agrega ese usuario a esa base con **TODOS LOS PRIVILEGIOS**. Guarda los nombres completos con el prefijo de cPanel.
4. Pide al proveedor una forma de probar fisitaap.com en el servidor nuevo **antes del cambio de DNS**, con HTTPS válido. Puede ayudarte a apuntar temporalmente el dominio solo en tu computadora. Abrir la IP o una dirección `/~usuario/` no es una prueba equivalente.

Puedes enviarle esto:

> Migraré FISITAAP, una aplicación PHP propia. Necesito PHP 8.3 con PDO MySQL, mbstring, OpenSSL, cURL, fileinfo, GD, DOM/XML, Phar y OPcache; Apache con mod_rewrite y AllowOverride; MySQL 8 o MariaDB compatible con InnoDB y utf8mb4. Indiquen cómo probar fisitaap.com antes de mover DNS, con HTTPS válido, y los límites de CPU, memoria, procesos PHP y conexiones del plan. En el hosting anterior algunas respuestas tardaban 9–10 segundos aunque PHP reportaba 16–24 ms y SQL menos de 2 ms. Quiero verificar que no se repita esa espera.

## 3. Instalar archivos e importar la copia

Puedes practicar con un respaldo preliminar mientras funciona el hosting anterior. No vendas realmente ni conectes las cajas a esa copia de prueba.

1. En el Administrador de archivos nuevo, entra en la raíz del documento. Activa **Mostrar archivos ocultos**.
2. Sube el ZIP de esta entrega y pulsa **Extraer**. Deben quedar directamente `index.php`, `.htaccess`, `app` y `assets`, sin una carpeta extra alrededor.
3. Vuelve a seleccionar PHP 8.3 en cPanel después de extraer: cPanel puede necesitar añadir su configuración PHP al .htaccess.
4. Antes de subir config.php, abre `https://fisitaap.com/comprobar-mudanza.php` mediante la prueba del servidor nuevo. Debe decir **Falta config.php**. Si ves código PHP, pide al proveedor corregir PHP antes de subir tu configuración privada.
5. Sube y extrae el respaldo de uploads. Debe quedar `uploads/foto...`, no `uploads/uploads/foto...`. Conserva el .htaccess de protección de uploads que viene en esta entrega; si el respaldo lo reemplazó, recópialo desde el ZIP.
6. En phpMyAdmin nuevo, selecciona la base **nueva y vacía** → Importar → selecciona el SQL/gzip → Continuar. Espera la confirmación sin errores. Si se corta por tamaño o tiempo, pide al proveedor importar el archivo completo; no continúes con tablas incompletas.
7. Sube tu config.php original y edítalo **solo en el nuevo servidor**.

Cambia únicamente los valores de `db_host`, `db_name`, `db_user` y `db_pass` por los de la base nueva. Normalmente db_host es localhost, pero confírmalo con el proveedor. Mantén app_url como `https://fisitaap.com`, y debug en false. **Conserva app_key exactamente como estaba**: cambiar esa clave puede impedir recuperar configuraciones privadas cifradas. Conserva también las otras opciones originales.

Las carpetas suelen usar permisos 755 y archivos 644. PHP debe poder escribir en uploads. Si no puede, pide corregir propietario/permisos; no uses 777.

## 4. Conservar solo La Ventanita y demos

**Este paso elimina los otros negocios de la base nueva. Nunca lo hagas en el servidor anterior.** Conserva el respaldo SQL privado antes de empezar.

1. En config.php del servidor nuevo, agrega esta línea dentro del arreglo de configuración, antes del cierre `];`:

```php
'migration_mode' => true,
```

2. Inicia sesión con tu cuenta **maestra** en la copia nueva.
3. Abre `https://fisitaap.com/preparar-mudanza.php`.
4. Comprueba que identifica exactamente La Ventanita y los dos demos. Revisa la lista de negocios que desaparecerán y el nombre de la base: debe ser la base **nueva**.
5. Escribe el nombre completo de esa base y la frase **SOLO LA VENTANITA Y DEMOS** en sus campos. Pulsa el botón de preparación.
6. Espera el mensaje de éxito. No necesitas activar nuevamente 1.7.9 ni aplicar actualizadores viejos.

La herramienta rechaza negocios ambiguos, demos ausentes y estructuras que no puede filtrar con seguridad. Si informa un error, no borres tablas manualmente ni continúes el traslado: guarda el mensaje para revisión. La limpieza de datos usa una transacción para evitar dejar una eliminación a medias; la tabla auxiliar de demos puede quedar creada si falla la preparación.

**Corrección de driver_branches para instalaciones ya subidas:** algunas bases anteriores incluyen esta relación entre repartidores y sucursales, además de las afiliaciones actuales. Esta entrega la conserva por sucursal y mantiene los usuarios y perfiles asociados. Si tu preparación se detuvo diciendo que `driver_branches` no tiene un alcance conocido, descarga `FISITAAP-CORREGIR-MUDANZA-DEMOS.zip` y extráelo directamente en la raíz del sitio del **cPanel nuevo**, reemplazando sus cinco archivos: `app/data_tools179.php`, `app/demo_sandbox.php`, `app/branches_v1.php`, `app/official_r3.php` y `mudanza-manifest.php`. Vuelve a abrir preparar-mudanza.php, confirma el nombre de la base nueva y la frase, y repite la preparación. No borres tablas ni importes la base otra vez. El ZIP completo ya incorpora esta corrección. Si aparece otra tabla en el error, conserva el mensaje para revisar también su alcance.

## 5. Optimizar fotos y llevar solo las imágenes necesarias

### Optimizar las fotos anteriores

Después de preparar la base nueva, y **antes de descargar uploads necesarios**, abre con la cuenta maestra:

`https://fisitaap.com/optimizar-imagenes.php`

Mantén habilitado migration_mode durante este paso. Pulsa **Buscar imágenes para optimizar** y después **Procesar siguiente lote** hasta que indique **Proceso terminado**. Cada petición procesa hasta tres imágenes para evitar una carga grande en el hosting. Revisa las advertencias: una imagen rechazada conserva su original y necesita revisión aparte.

Se crean WebP y miniaturas sin cambiar los registros de la base ni borrar los originales. El sitio utiliza una versión generada cuando pesa menos. Por tanto, bajará el peso que descargan tus clientes, pero **conservar originales y derivados ocupa espacio adicional en el servidor**. No borres originales manualmente: sus rutas siguen siendo referencias en la base.

Las imágenes nuevas se optimizan automáticamente al subirlas: máximo de 1.440 píxeles en el lado mayor, reducción adicional si la foto sigue pesada, y miniatura de hasta 480 píxeles. Se conserva transparencia y se corrige la orientación JPEG del celular. No se agrandan fotos pequeñas. Los objetivos aproximados son 160 KB para la imagen principal y 40 KB para miniaturas; dependen de cada imagen y no son límites garantizados. Animaciones existentes se conservan sin aplanarlas; SVG y documentos no se convierten.

Las tarjetas de productos, categorías y miniaturas del catálogo utilizan versiones pequeñas y carga diferida. Las imágenes siguen siendo archivos estáticos con caché; no se convierten ni se procesan durante una visita normal.

### Descargar los archivos necesarios

Hazlo **antes de abrir pruebas de demo**, mientras preparar-mudanza.php está habilitado.

1. Después de preparar, pulsa **Descargar uploads necesarios**. Descarga un ZIP privado con los archivos locales referenciados por los datos conservados y la protección de uploads. Si incluye **FISITAAP-ARCHIVOS-FALTANTES.txt**, ábrelo y revisa esas referencias contra tu respaldo completo antes de sustituir o borrar carpetas. El archivo avisa de enlaces cuyos archivos locales no se encontraron; las imágenes externas no se descargan ni se convierten.
2. Conserva tu respaldo completo original en la computadora. En el servidor nuevo, renombra uploads como `uploads-respaldo`.
3. Sube el ZIP descargado y extráelo en la raíz del sitio. Creará la nueva carpeta uploads. Evita una carpeta adicional uploads/uploads.
4. Revisa logos, fotos de productos y documentos de La Ventanita y ambos demos. El selector solo conoce referencias en la base; archivos manuales o enlaces externos requieren revisión aparte.
5. Cuando todo esté comprobado y tengas el respaldo privado, elimina uploads-respaldo del servidor nuevo. No borres nada del servidor anterior.

Si falta Phar o falla la descarga por límites del hosting, conserva uploads completo temporalmente y solicita ayuda para seleccionar archivos. No pierdas imágenes por intentar reducir espacio antes de verificarlas.

## 6. Cómo funcionan ahora los demos

Cada entrada desde los botones **Abrir demo** de la portada o desde **Demos → Probar panel / Probar compra** crea una copia privada del demo original. El visitante puede vender y cambiar datos dentro de su prueba; esas modificaciones no afectan La Ventanita, el original ni a otro visitante. **Elegir otra demo** regresa al selector público; elegir nuevamente un demo crea una prueba limpia. Los enlaces antiguos al selector también regresan allí, aunque la sesión anterior haya vencido.

**Terminar y descartar** elimina esa copia inmediatamente. Una nueva entrada comienza desde el original, con inventario y configuración iniciales, sin las ventas de pruebas anteriores. Los correos se simulan; la vinculación de equipos y otras integraciones reales se restringen para que el demo no afecte equipos reales.

El acceso se guarda por pestaña. Al cerrar y volver a entrar desde Demos empieza una prueba nueva. El navegador no siempre avisa al servidor al cerrar; una copia abandonada vence tras **30 minutos sin uso**, con duración máxima de **4 horas**. Restaurar automáticamente una pestaña puede restaurar también su estado del navegador: para reiniciar de inmediato usa Terminar y descartar o vuelve a entrar desde el selector de demos.

En cPanel → **Trabajos cron**, programa la limpieza cada 10 minutos. Pide al proveedor la ruta exacta de PHP 8.3 y de tu carpeta del sitio. El comando debe ejecutar:

```text
/RUTA/DE/PHP83 /home/USUARIO_CPANEL/RAIZ_DEL_SITIO/app/demo_cleanup.php
```

Reemplaza las rutas con las reales, no copies literalmente los ejemplos. La limpieza también recoge algunas copias vencidas al entrar un nuevo visitante. No se añade esa limpieza a cada pantalla de La Ventanita. Hay un límite de 60 demos simultáneos para proteger el hosting compartido.

## 7. Revisar antes de abrir al público

- Accede con tu cuenta maestra y con el dueño de La Ventanita. Verifica productos, precios, inventario, usuarios, ventas anteriores, saldos y configuración de impresión.
- Abre `https://fisitaap.com/comprobar-mudanza.php`. Guarda el informe con Ctrl+P → Guardar como PDF. Revisa archivos, estructura, fotos y permisos.
- Opcionalmente, sube y extrae **FISITAAP-COMPROBAR-MUDANZA.zip** en el sitio anterior; abre el mismo comprobador con tu cuenta maestra y guarda su informe. Solo lee datos. Allí puede avisar que faltan archivos nuevos del demo: es esperable. No subas ni ejecutes el preparador en ese hosting.
- Después del filtrado, compara especialmente el apartado **La Ventanita** entre ambos informes. Sus cantidades y saldos deben coincidir. Los totales de toda la plataforma serán distintos porque eliminaste otros negocios.
- Prueba los dos demos con dos navegadores distintos: realiza una venta, sal del demo y entra otra vez; debe comenzar limpio. Comprueba que el otro visitante no recibió esos cambios.
- Prueba una venta controlada, sincronización del equipo principal y una impresión real en la copia preliminar. Esa copia no se usa para ventas reales y se reemplazará por el respaldo definitivo.
- Puedes usar diagnostico-rendimiento.php para medir el servidor nuevo. El código conserva los cambios R3, pero una mudanza no garantiza por sí sola la velocidad: también dependen los límites y la configuración del hosting.

El comprobador puede advertir cambios en .htaccess cuando cPanel añade reglas PHP propias. Haz revisar esas reglas por el proveedor; no borres su manejador PHP para forzar una coincidencia. El informe no sustituye las pruebas funcionales.

**Compatibilidad con campos antiguos de impresión:** si el informe solo dice que faltan los siete campos `receipt_*` de `tenants`, instala `FISITAAP-CORREGIR-MUDANZA-DEMOS.zip` en la raíz del sitio nuevo, reemplazando sus cinco archivos. La impresión actual se configura por sucursal, en `branches`; el comprobador actualizado sigue exigiendo esos campos allí. Los demos ya no intentan insertar columnas antiguas que no existan en `tenants`, y sus impresoras se desactivan dentro de cada copia de prueba. Vuelve a abrir comprobar-mudanza.php y prueba los demos. Esta corrección modifica código; no necesita importar la base ni repetir la preparación selectiva. Si el informe menciona otros campos o tablas, conserva esos mensajes para revisión.

## 8. Hacer el traslado definitivo

1. Coordina una pausa de ventas. Sincroniza **todas** las cajas Windows/Android y cierra turnos. No debe quedar ninguna venta local pendiente.
2. Impide nuevas operaciones en el servidor anterior, por ejemplo con **Privacidad del directorio** de cPanel coordinada con el proveedor. Cerrar solo el navegador no bloquea otras cajas.
3. Obtén un nuevo respaldo SQL y uploads después de esa pausa. Ese será el respaldo definitivo.
4. En el hosting nuevo usa una base nueva y vacía para ese respaldo definitivo. Cambia su nombre y credenciales en config.php, importa y repite la preparación selectiva y la revisión de imágenes. No mezcles los datos de prueba con los definitivos.
5. Compara nuevamente los datos de La Ventanita, revisa accesos y elimina de config.php la línea migration_mode. Elimina preparar-mudanza.php y optimizar-imagenes.php, los ZIP subidos y cualquier respaldo SQL público. Guarda tus respaldos en la computadora.
6. Cambia los DNS del dominio con ayuda del proveedor. Revisa A, AAAA y www para evitar que alguna dirección siga enviando al hosting viejo. Si cambias servidores de nombres, conserva también MX y TXT del correo.
7. Activa AutoSSL y verifica HTTPS válido. Mantén bloqueadas las escrituras en el servidor anterior durante la propagación.
8. Abre la web en varios dispositivos, inicia sesión y comprueba que todos llegan al nuevo servidor. Después reconecta las cajas y reabre ventas.

Si conservas dominio y las vinculaciones importadas, Windows/Android 1.7.9 no necesitan un instalador nuevo por esta mudanza. Los navegadores pueden pedir iniciar sesión otra vez. Comprueba la conexión y la autorización del equipo principal antes de vender sin internet; no vincules ni actives equipos adicionales a ciegas.

Elimina comprobar-mudanza.php y mudanza-manifest.php cuando termines de comparar; elimina también el comprobador temporal del hosting anterior. diagnostico-rendimiento.php es opcional y se puede retirar al terminar las mediciones. Mantén app/demo_cleanup.php: lo usa la tarea programada.

## 9. Qué no subir y cómo volver atrás

No necesitas carpetas de actualizaciones antiguas, upgrade, fisichat-upgrade, qa-upgrade, .git, node_modules, fuentes desktop/android, instaladores, registros ni SQL en la carpeta pública. No elimines archivos de app solo por llevar nombres de versiones anteriores: algunos siguen siendo parte del sistema actual.

Conserva el hosting anterior y los respaldos al menos una semana. Antes de realizar ventas nuevas puedes regresar el dominio al servidor anterior y retirar el bloqueo. **Después de vender en el servidor nuevo, regresar a una copia vieja perdería esas operaciones**: primero hay que trasladar los datos nuevos y sincronizar las cajas.

## Mejoras de la revisión del paquete

- Las fotos con parámetros, espacios, caracteres como ñ y direcciones con www conservan sus enlaces. La selección de imágenes reconoce también galerías JSON y fotos dentro de contenido HTML.
- El ZIP privado de imágenes incluye un aviso si hay archivos referenciados que faltan. Su descarga libera la sesión para que otras pantallas no tengan que esperar al procesamiento del ZIP.
- La limpieza del demo comprueba referencias desde otros datos antes de borrar y libera sus tablas auxiliares al terminar. Si existe una relación inesperada, conserva los datos para revisión.
- La selección de negocios y la activación de demos forman una sola transacción: si falla la activación, se revierten las eliminaciones.
- Los respaldos SQL comprimidos, archivos ocultos y archivos de configuración quedan protegidos por .htaccess. Los archivos necesarios para emitir el certificado SSL siguen accesibles.
- El comprobador verifica que GD tenga soporte WebP, además de estar instalado. Las instrucciones del preparador indican optimizar y descargar imágenes antes de probar demos.

## Validación de esta entrega

Probada en un servidor de laboratorio con PHP 8.3 y MariaDB 11.4, usando datos ficticios: importación completa, accesos, rutas y archivos; filtrado selectivo; conservación de registros financieros y equipos; ventas aisladas en demos, reinicio y limpieza. Además pasaron 10 comprobaciones de imágenes: compresión de una foto detallada, transparencias, orientación del celular, límites seguros, originales y base intactos, reinicio por lotes, exportación de variantes y control de acceso. Pasaron también las 28 comprobaciones anteriores: 10 de instalación/importación, 14 de filtrado/demos/imágenes y 4 de pestañas en Chromium con JavaScript real. Los informes QA están en esta carpeta del repositorio.

Pasaron otras 9 comprobaciones específicas de esta revisión, incluyendo los casos de fotos y reversión ante fallos: **47 comprobaciones de laboratorio en total**.

Las correcciones posteriores incluyen 8 comprobaciones de relaciones antiguas de repartidores y 8 de compatibilidad de impresión/demos, para **63 comprobaciones de laboratorio**. La segunda serie reproduce una base sin campos antiguos de impresión en `tenants`, prueba apertura y venta en demos, mantiene obligatorios los campos actuales de `branches` y restaura la copia de laboratorio al terminar.

No se ha instalado en tu nuevo cPanel ni se ha probado allí DNS, SSL, tus datos reales o impresoras físicas. Esas comprobaciones corresponden a los pasos anteriores.
