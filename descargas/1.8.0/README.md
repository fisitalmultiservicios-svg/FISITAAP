# Descargas FISITAAP · web 1.8.0-R17

[**Descargar actualización R17 para cPanel**](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R17.zip)

Accesos de cliente/cajero, archivo de clientes, direcciones con zona, separación Catálogo/Menú, entrega directa sin módulo, Academiaap, configurador pausado y Control de personal. Incluye FISIChat y la interfaz acumulada anterior.

- [Guía e instalación](GUIA-CPANEL-1.8.0.md)
- [Solicitudes revisadas y pruebas](INFORME-QA-1.8.0.md)
- [Capturas R17](VISTAS-R17.md)
- [Integridad de archivos](SHA256SUMS-1.8.0.txt)

Los cálculos laborales son estimaciones con bases y supuestos visibles; la vigencia del texto oficial no pudo verificarse desde este entorno. Marcar requiere conexión dentro de Windows/Android vinculado. Conserva Windows1.8.1; no hace falta reinstalarlo para esta entrega web.

Respalda, extrae en `public_html` y ejecuta `/actualizador-180/index.php` como maestro. La publicación de estos archivos no instala el hosting.

---

## Descargas y documentación de entregas anteriores

# FISITAAP web 1.8.0-R16 · Windows 1.8.1

R16 conecta el FISIChat del dueño a consultas y cambios reales de su propia tienda, añade tickets de soporte desde el chat de la web oficial y reduce un poco las tarjetas del catálogo online.

- [Descargar actualización cPanel R16](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R16.zip)
- [Guía de instalación y uso](GUIA-CPANEL-1.8.0.md)
- [Capturas de la actualización](https://github.com/fisitalmultiservicios-svg/FISITAAP/blob/main/descargas/1.8.0/VISTAS-R16.md)
- [Pruebas y alcance](INFORME-QA-1.8.0.md)

El dueño puede pedir ideas, análisis y propuestas de productos, categorías, clientes, cupones, zonas, proveedores, grupos de opcionales y textos/contacto de su web. **Confirmar cambio** guarda los valores revisados; nunca una decisión del modelo. Se validan la tienda, sucursal, permisos, módulos y vigencia. Repetir una confirmación no duplica el cambio y los registros editados desde otra pantalla necesitan una revisión nueva. Se conserva la conexión de IA existente. Crear producto tiene también un formulario dentro del chat.

El chat público orienta sobre FISITAAP sin herramientas de las tiendas. Cuando el problema continúa solicita contacto, tienda y detalles para crear un ticket. En el maestro se gestiona desde **Administración → Tickets de soporte**, con estados, notas y conversación. Los datos anónimos quedan identificados como declarados; una sesión dueña válida se vincula a su tienda desde el servidor. No se envían mensajes externos automáticamente.

El catálogo muestra tarjetas más pequeñas: 5/4/3/2 columnas según el ancho, imágenes de 155/120 px y menos espacio interno, conservando carrito, opciones y colores de la tienda. Se mantiene la estructura de R15 y la web oficial de presentación.

Para instalar: guarda respaldo de archivos y base de datos, extrae el ZIP en **/home/fisitaap/public_html** y abre **https://fisitaap.com/actualizador-180/index.php** con la sesión maestra. Pulsa **Instalar archivos**. Extraer solamente no instala la actualización. Conserva **releases/1.8.0-r16** y las versiones anteriores.

El ZIP incluye 139 archivos funcionales, permisos644/755 y tres tablas nuevas preparadas por el instalador. Conserva config.php, uploads, negocios, usuarios, ventas, turnos, activaciones y conexión de IA; no incluye datos ni claves privadas. Los ZIP R1…R15 permanecen históricos. No requiere SQL manual ni reinstalar Windows1.8.1/Android1.8.0.

Las pruebas usan PHP/MariaDB/Chromium con datos ficticios y un proveedor de IA simulado por TLS local. No conectan un modelo real, WhatsApp, pagos ni impresoras; los dispositivos físicos y la calidad del modelo configurado requieren revisión en tu instalación. Publicar estos archivos no ejecuta el actualizador en cPanel.
