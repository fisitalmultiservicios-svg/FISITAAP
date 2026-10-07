# Instalar FISITAAP en Android

[**Descargar FISITAAP Android 1.0.0 — instalador APK**](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/actualizaciones/android-1.0.0/FISITAAP-Android-1.0.0.apk)

Funciona en teléfonos y tablets con Android 8.0 o superior y Android System WebView actualizado. Abre el mismo sistema web y usa tu cuenta y tus permisos habituales. Android imprime directamente por la red del negocio: para estos recibos no necesitas FISITAAP Print.

## Instalar y conectar

1. Abre el enlace desde el teléfono o la tablet Android y descarga el APK.
2. Abre el archivo. Si Android lo solicita, permite instalar aplicaciones desde el navegador que usaste. Pulsa **Instalar**.
3. Abre **FISITAAP Android**. En **Dirección principal de la web** escribe `https://fisitaap.com`. Usa la dirección principal, sin agregar el nombre del negocio.
4. Para imprimir, escribe la **IP de la impresora**, por ejemplo `192.168.1.50`, y su puerto: normalmente `9100`. La IP real la obtienes de la configuración o la hoja de prueba de la impresora.
5. Elige papel de **58 u 80 mm**. Puedes desactivar el corte si la impresora no tiene cortador. Pulsa **Prueba de impresión**, revisa el papel y guarda los ajustes.
6. Ingresa con el mismo correo y contraseña de la web. Tendrás el menú completo que corresponde a tu usuario.

El teléfono o la tablet y las impresoras deben estar conectados a la misma red del negocio. Las impresoras deben aceptar **ESC/POS por TCP/IP**. El ancho de papel no garantiza que un modelo acepte ese protocolo. Esta entrega imprime por red; no incluye conexión directa por Bluetooth ni USB.

No tienes que subir archivos nuevos a cPanel si ya instalaste la actualización completa **1.7.0-R3**. El APK se instala en Android. Tampoco necesitas llenar un token de FISITAAP Print para imprimir desde esta aplicación.

## Varias impresoras

En **Administración → Impresoras y recibos**, configura el punto **Caja**, **Cocina**, **Barra**, **Despacho** o la zona correspondiente:

- Tipo: **Red / IP**.
- La IP de esa impresora y su puerto, normalmente `9100`.
- Papel: **58 u 80 mm**, según esa impresora.

Android respeta la impresora y el papel que asignes a cada punto en la web. La impresora de los ajustes de Android sirve como predeterminada cuando el documento no tiene una impresora de red asignada. La caja local utiliza esa impresora predeterminada.

## Vender sin internet

Android puede ser otra caja del equipo central Windows que ya usas:

1. Deja abierto FISITAAP Escritorio en el equipo central, ya conectado al negocio y con el catálogo descargado.
2. En su **Caja local**, consulta la dirección que aparece en **Otras cajas y respaldos**, por ejemplo `http://192.168.1.20:18766`.
3. En Android abre **⋮ → Ajustes de conexión e impresora** y copia esa dirección en **Equipo central**.
4. Pulsa **Caja local**. Selecciona el cajero autorizado, escribe su contraseña local y dale un nombre distinto a esta caja, por ejemplo **Tablet 1**.

Si se corta internet, podrás buscar productos, abrir turno, vender e imprimir mediante el equipo central. El router, el equipo central y las impresoras deben seguir encendidos y conectados. Android no sustituye al equipo central ni ofrece ventas sin conexión cuando el teléfono está aislado de la red del negocio.

Las ventas quedan guardadas en el equipo central. Cuando regrese internet, pulsa **Sincronizar** en Caja local. Si quedan ventas pendientes o el equipo central no responde, la aplicación solicita resolverlas antes de regresar al sistema completo. Al cambiar de modo, termina el cobro actual; las dos pantallas conservan su estado mientras la aplicación sigue abierta.

## Recibos y otros documentos

Los botones del recibo indican **Imprimir desde Android**. La impresión automática del sistema también se envía desde Android. El programa guarda un registro de los envíos para evitar repetir un comprobante al pulsar dos veces.

Si un envío no queda confirmado, revisa la impresora y el papel antes de pulsar **Reimprimir copia**. Un envío confirmado por la red no permite comprobar físicamente que el papel salió.

Para imprimir otros documentos o guardar un PDF, abre **⋮ → Imprimir página / guardar PDF**. Se abrirá el servicio de impresión de Android; según el modelo, puede necesitar el complemento del fabricante.

[Código fuente y pruebas](FUENTES-FISITAAP-Android-1.0.0.zip) · [Comprobaciones realizadas](COMPROBACIONES.md) · [SHA256 de los archivos](SHA256SUMS.txt)
