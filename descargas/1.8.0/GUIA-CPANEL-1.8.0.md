# Instalar FISITAAP 1.8.0

Esta entrega actualiza tu FISITAAP existente. Conserva tus negocios, usuarios, productos, ventas, imágenes y configuración de base de datos. No es una instalación en una base vacía. No importes otro SQL ni cambies las cuatro claves de config.php.

## 1. Preparar el respaldo

1. En cada caja instalada pulsa **Sincronizar**. Confirma que no quedan ventas pendientes.
2. Cobra o cancela las cuentas abiertas y cierra los turnos. Cierra FISITAAP Windows. Detén las ventas durante la actualización.
3. En cPanel abre **Administrador de archivos** y entra a la carpeta del sitio. Es la carpeta donde están juntos **index.php**, **config.php**, **app**, **assets** y **uploads**. Si tienes dudas, **Dominios → fisitaap.com → Raíz del documento** indica su ubicación. Puede ser public_html o la carpeta propia del dominio.
4. Selecciona los archivos del sitio, incluyendo **.htaccess** (Configuración → Mostrar archivos ocultos), comprímelos y descarga ese respaldo a tu computadora. También puedes usar un respaldo completo de cPanel.
5. En **phpMyAdmin**, selecciona la base de FISITAAP → **Exportar → Rápido → SQL → Exportar**. Guarda ese archivo junto al respaldo del sitio.
6. Conserva ambos respaldos fuera de public_html. No borres app, assets, config.php ni uploads.

## 2. Subir el actualizador

7. Descarga **FISITAAP-1.8.0-CPANEL-R1.zip** de la carpeta de descargas de GitHub. R1 corrige los permisos del ZIP: archivos 644 y carpetas 755. El nombre original FISITAAP-1.8.0-CPANEL.zip también contiene el paquete corregido.
8. En el Administrador de archivos, dentro de la carpeta del sitio, pulsa **Cargar** y sube ese ZIP.
9. Selecciona el ZIP → **Extraer**. Debe aparecer una carpeta llamada **actualizador-180**, al lado de index.php. Dentro están index.php, manifest.json, files.php y payload. No muevas payload ni copies su contenido manualmente encima del sitio.

Si ya extrajiste el ZIP anterior, extrae R1 en el mismo lugar y permite reemplazar los archivos de actualizador-180. Conserva la carpeta y cualquier respaldo que contenga. Comprueba en la columna Permisos que actualizador-180 tenga 755 y su index.php/.htaccess tengan 644. Si cPanel conserva los permisos anteriores, usa Cambiar permisos únicamente sobre los archivos/carpetas del actualizador: 644 para archivos y 755 para carpetas; nunca 777. R1 contiene los mismos 109 archivos funcionales de la entrega; esta corrección es de empaquetado.
10. Confirma en cPanel que el dominio usa **PHP 8.2 o 8.3**, con PDO MySQL, mbstring, SimpleXML/XML y GD. GD con WebP permite optimizar imágenes. No uses permisos 777 en el sitio: las carpetas suelen ser 755 y los archivos 644, con el propietario correcto del hosting.
11. Abre **https://fisitaap.com/owner-login** e ingresa con tu cuenta **maestro**.
12. Abre **https://fisitaap.com/actualizador-180/** en ese mismo navegador.
13. Marca que guardaste respaldo y detuviste todas las cajas. Pulsa **Instalar archivos** una vez. Espera el mensaje **Archivos 1.8.0 instalados y comprobados**.
14. Si aparece **distinto**, el actualizador encontró un archivo cambiado fuera de los paquetes conocidos y se detuvo. Conserva el respaldo y comparte los nombres que señala. Si muestra un problema de permisos, corrige el propietario/permisos desde cPanel o con el soporte del hosting; no fuerces la copia.

Este paso comprueba los archivos y respalda los que reemplaza. La base de datos todavía no cambia. Repetirlo con los mismos archivos no duplica el respaldo.

## 3. Actualizar Windows y preparar Android

15. En cada Windows descarga **FISITAAP-Escritorio-1.8.0-Windows-x64.exe** y ejecútalo con FISITAAP cerrado. Instala sobre la ubicación anterior. No desinstales ni borres los datos de FISITAAP: ahí se guardan las ventas locales. Requiere Windows de 64 bits compatible con Electron 44 (Windows 10 o posterior).
16. Para tu primer Android descarga **FISITAAP-Android-1.8.0.apk** en el teléfono/tablet. Abre el archivo y permite instalar desde ese navegador cuando Android lo solicite. Requiere **Android 8 o posterior**. Guarda el APK para futuras instalaciones. La clave privada de firma se conserva aparte; nunca se sube a cPanel ni a GitHub.
17. Mantén las cajas detenidas hasta activar los cambios. El dominio debe abrir con HTTPS válido y sin aviso de certificado. Windows y Android no omiten la comprobación de certificados. Si el hosting todavía usa un certificado autofirmado, pide al proveedor completar SSL antes de conectar las aplicaciones.

## 4. Activar la actualización

18. Con la cuenta maestro abre **https://fisitaap.com/activar-180.php**.
19. Marca que guardaste respaldos, sincronizaste/cerraste turnos y actualizaste las aplicaciones. Pulsa **Aplicar actualización**.
20. Espera **Actualización 1.8.0 activada**. Este paso agrega los campos, tablas e índices necesarios y migra las configuraciones existentes. No elimina las tablas históricas ni las ventas anteriores. Puede repetirse si la preparación se interrumpe: la activación solo queda marcada después de completar la preparación.
21. Si informa turnos o cuentas pendientes, ciérralos de forma normal y repite la activación. No borres registros en phpMyAdmin para saltar la comprobación.

## 5. Asignar equipos, usuarios e impresoras

22. Entra como dueño de La Ventanita → **Administración → Equipos y cajas**. Revisa la sucursal, caja y cajero de cada equipo. El cajero usa su usuario y contraseña habituales; ya no se crea otra contraseña local.
23. Para un equipo nuevo crea un código de conexión. En Windows usa **FISITAAP → Conectar este equipo al negocio**; en Android usa **Conectar y autorizar equipo**. Escribe la dirección principal **https://fisitaap.com**, el número de equipo y el código. No escribas la dirección del catálogo del negocio.
24. Elige **un único equipo principal por negocio**, Windows o Android, y sincronízalo. Ese equipo puede buscar productos y vender sin internet. Las demás cajas usan el sistema completo con conexión. Para cambiar el principal, primero sincroniza/cierra sus turnos y libéralo desde ese equipo.
25. Android puede conectarse directamente a la web. También puede descubrir la dirección web del central Windows en la red del local, pero se autoriza como equipo propio: no copia las credenciales del central.
26. En **Administración → Impresión** configura cada punto: **recibos, cocina, bar, despacho** y los adicionales que ocupes. Elige sucursal, equipo, papel 58/80 mm y tipo de impresión.
27. Windows permite seleccionar el nombre exacto de una impresora instalada en Windows o una térmica de red. Android utiliza térmicas de red compatibles con **ESC/POS**, con IP y puerto del local; 58/80 mm describe el papel y no garantiza por sí solo compatibilidad de la impresora. También puedes elegir PDF.
28. Usa **Probar** en cada punto. La gaveta requiere habilitarla tanto en la caja como en el punto de recibos. Solo se abre con un recibo original que tenga efectivo, nunca con comandas o copias. No necesitas FISITAAP Print para estos envíos.
29. Si un envío queda **sin confirmar**, revisa el papel y la conexión antes de pedir otra copia. La aplicación conserva la venta y evita reenviarla automáticamente a otra impresora.

## 6. Comprobar antes de volver a vender

30. Abre portada, acceso a negocios, La Ventanita y ambos demos. Comprueba imágenes, carrito, permisos y **Otros demos**. Las demos trabajan en copias independientes con dinero virtual; sus impresiones se simulan.
31. Desde una caja autorizada abre un turno pequeño, prepara una venta de prueba, imprime recibo y comanda en sus puntos, y comprueba efectivo/vuelto/cierre. Prueba los métodos que usa el negocio, incluida una combinación si la ocupas.
32. En el principal, desconecta internet después de sincronizar, busca un producto y guarda una venta de prueba. Reinicia la aplicación: la venta debe seguir pendiente. Recupera internet, sincroniza y confirma que llegó **una sola vez**. Cierra el turno. Usa productos/dinero de prueba o registra correctamente el ejercicio; no borres ventas desde la base.
33. Revisa que una caja secundaria quede bloqueada para vender sin internet. Comprueba compras, cuentas por cobrar/pagar, mesas, reportes y permisos de tu equipo según lo que uses.
34. Si un navegador conserva un diseño viejo, recarga con **Ctrl+F5**. Los archivos nuevos tienen una versión de caché distinta y se retiran los antiguos cachés de FISITAAP.
35. Cuando todo esté comprobado, descarga/conserva el respaldo de actualizador-180 fuera del sitio y elimina del servidor **solo** el ZIP de actualización y la carpeta **actualizador-180**. La aplicación funciona con app y assets instalados; no depende de esa carpeta. Conserva tus respaldos hasta comprobar el uso normal del negocio.

## Volver atrás

**Antes de activar:** el actualizador ofrece **Restaurar antes de activar**. Comprueba que los archivos y su respaldo no cambiaron y restaura la versión previa sin cambiar la base.

**Después de activar o registrar operaciones:** esa restauración se bloquea. Se necesita revisar el respaldo completo y las operaciones posteriores para conservar ventas, inventario y cajas. No vuelvas a copiar archivos antiguos sobre la nueva base por tu cuenta.

## Alcance de las pruebas

Se probaron código, API PHP/MariaDB, navegador, almacenamiento local, cálculos, firma APK, impresión con receptores/controladores de prueba e instalación/restauración en copias ficticias. La entrega no significa que ya se instalaron cambios en tu cPanel. La velocidad del hosting, certificados, controladores Windows y salida de papel/impulso de gaveta en impresoras físicas deben comprobarse allí. No se usaron datos ni claves del negocio durante estas pruebas.
