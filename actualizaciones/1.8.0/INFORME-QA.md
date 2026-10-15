# Informe QA · FISITAAP 1.8.0-R5

## Acceso y bienvenida compartida · R5

Se reprodujo el defecto de R4: con una sesión de dueño y el negocio desactivado, /admin devuelve500 por una DomainException sin manejar; /owner-login detecta la sesión y vuelve a enviarla a /admin. En R5 se valida el negocio y se limpia esa sesión con un mensaje «Tienda fuera de servicio», conservando la posibilidad de entrar con otra cuenta. Al validar credenciales tampoco se crea una sesión operativa para un negocio suspendido o sin asociación. La cuenta maestra y los clientes globales no se vinculan por suposición a otro negocio. La redirección usa datos de cuenta actualizados. Un maestro que abre /admin es enviado a /master.

La sesión de lectura ya se había cerrado antes del control de acceso. La primera corrección rotaba un identificador sin poder enviar su cookie y perdía el mensaje. Se corrigió: cuando se reabre una lectura se conserva su identificador, se eliminan los datos de autorización y se renuevan CSRF/mensaje. Un acceso posterior válido sí rota la sesión. Las sesiones que siguen abiertas se rotan normalmente. La comparación de identidad del mecanismo176 impide sobrescribir una sesión rotada por otra petición.

Se reprodujo también que la imagen cargada por el maestro se veía en el maestro y negocios180, pero no en pendientes179: la ruta de bienvenida estaba detrás del control de activación individual. R5 aplica la bienvenida compartida cuando la plataforma está publicada, sin cambiar el estado operativo de los negocios. Carga los estilos de esa vista para los pendientes, sin activar su JavaScript de POS180. El editor maestro muestra la imagen actual. Dos cargas reales consecutivas generan URLs distintas y reemplazan la vista para todos al recargar.

- QA-ACCESO-R5.json:6 grupos HTTP, R4 reproducido y R5 corregido; recuperación antes de instalar, sesión/CSRF, maestro, asociación ausente, dueño/colaboradores/clientes y datos conservados.
- QA-BIENVENIDA-R5.json:5 grupos con carga multipart real, versiones179/180, vista previa, reemplazo de imágenes, Chromium390px, prohibición de edición por dueño y estados/ventas/turnos conservados.
- QA-ACTUALIZADOR-R5.json:7 grupos sobre R4 publicado con negocio180/179 y turno abierto, archivos/datos/config/uploads preservados, hashes y rechazo de cambios desconocidos.
- QA-PERMISOS-ZIP-R5.json:2 extracciones con umask077/ApacheUID33, sin reparar permisos después.
- QA-DIAGNOSTICO-BIENVENIDA-D2.json:5 grupos de autorización vigente, errores/fatales simulados, ocultación de claves y transacción de lectura que prohíbe escrituras.
- QA-SINTAXIS-R5.json: los 73 archivos PHP del ZIP pasan la comprobación de sintaxis con PHP 8.3, incluidos los dos auxiliares de la raíz.

Además se repitió qa/chat180.py sobre R5: sus 11 grupos HTTP de FISIChat pasaron. El resultado es idéntico al informe QA-FISICHAT-HTTP-R4.json conservado; no se volvió a ejecutar el proveedor de IA real ni las pruebas nativas.

Datos y equipos de prueba ficticios. No se cambió el hosting, el estado real de La Ventanita ni los instaladores nativos. El usuario confirmó que reactivar La Ventanita desde el maestro en otro navegador y cerrar la sesión restableció su acceso. La corrección evita el error técnico futuro; las suspensiones comerciales siguen siendo decisiones del maestro. Evidencia R4 y anterior permanece debajo y no se presenta como una nueva ejecución integral de todas las funciones.

## FISIChat público, tiendas y maestro · R4 · 14 de octubre de 2026

La portada deja de abrir un formulario aparte y dispone de un chat en la misma página, con burbujas, sugerencias, respuesta en curso, historial y contacto. Tiendas/landing usan su propia identidad. El maestro tiene un perfil administrativo privado, separado del asesor comercial aunque esté conectado al visitar la portada. El chat avanzado del dueño conserva sus herramientas anteriores.

El conocimiento comercial se limita a funciones actuales: un principal Windows o Android offline, impresión directa configurada en Administración, demos virtuales, módulos y activación por negocio. No se inventan precios; la cotización remite al contacto configurado. La conversación reutiliza la conexión de IA existente. Sin clave o ante un fallo se usa la guía local; esa guía no equivale a conversación libre con un modelo.

El maestro consulta cifras y listas acotadas de negocios reales, vencidos/próximos vencimientos, planes, contactos nuevos de la plataforma y estados de activación. Cambiar plan o vencimiento abre un formulario; preparar no modifica datos y confirmar exige una propuesta guardada en servidor, de diez minutos, y privilegio maestro vigente. Se compara el valor anterior para rechazar propuestas desactualizadas. La operación modifica solamente plan/vencimiento, queda auditada y admite repetición sin una segunda escritura/auditoría. No se envían contraseñas ni claves a la IA. La IA no tiene herramientas de escritura.

La conversación se almacena en sesión por perfil, negocio, usuario/rol y sucursal, por treinta minutos, con doce mensajes. El historial recibido del navegador no se acepta como contexto. Se conserva la solicitud para reintentar, se impide intercalar mensajes y una nueva generación al reiniciar evita que una respuesta tardía reponga el historial borrado. Los mensajes se dibujan como texto, con CSRF y límites de tamaño/frecuencia. La sesión se libera antes de esperar al proveedor.

Pruebas específicas de R4 en PHP/MariaDB y Chromium, siempre con registros ficticios:

- **QA-FISICHAT-HTTP-R4.json:** 11 grupos de comprobaciones de ventas/soporte, contexto, historial/reintentos, límites, precios publicados por sucursal, exclusión de productos ocultos, pedidos solo del cliente y tienda, contactos solo de la plataforma, contraseña obligatoria/privilegio revocado, vencimientos, propuesta/confirmación/repetición y cambios de otro administrador.
- **QA-FISICHAT-NAVEGADOR-R4.json:** 8 flujos a 1366/768/390/360px: respuestas reales del motor local, historial/reset, logo propio, pantalla completa, foco/Escape/ARIA, texto malicioso no ejecutado, respuesta HTTP no JSON sin perder el borrador, propuesta/confirmación del maestro y ajuste móvil. Sin errores JavaScript ni recursos faltantes.
- **QA-FISICHAT-PROVEEDOR-R4.json:** 7 grupos contra un proveedor local por TLS verificado, usando una clave ficticia. Comprueba petición de IA/contexto/historial, límite de tokens/store=false, datos privados del maestro y públicos de tienda, fallo 500 con guía, navegación con la misma sesión mientras tarda la IA, conflicto entre mensajes simultáneos y reset durante la espera. No prueba calidad, precios ni latencia de OpenAI real.
- **QA-ACTUALIZADOR-R4.json:** 7 grupos sobre R3 publicado, negocios 180/179 y turno abierto. Conserva estado/datos/config/uploads/código R3, rechaza cambios externos, verifica 115 hashes y entrada R4, UI pública/maestro, repetición y bloqueo de restauración después de publicación.
- **QA-PERMISOS-ZIP-R4.json:** 2 extracciones reales con umask 077/Apache UID33,115 hashes, 644/755, instalador accesible y payload privado403 sin reparar permisos.
- **QA-SINTAXIS-R4.json:** los 71 archivos PHP del paquete pasan sintaxis con PHP8.3 y el JavaScript nuevo pasa `node --check`.
- **QA-PUBLICO-R4.json:** regresión del ZIP exacto en portada y La Ventanita, nueve anchos, títulos/botones, diseño/logo, catálogo, negocio pendiente y tema administrativo.

R4 no modifica el motor, instaladores, impresión física ni cálculos de caja. Las pruebas históricas R2/R3 siguientes se conservan y no se presentan como una nueva ejecución completa. No se accedió al hosting ni se ejecutó IA con una clave productiva. El despliegue, velocidad del hosting y equipos físicos requieren comprobación allí. El manifest de R4 enumeraba 115 archivos funcionales en releases/1.8.0-r4; MANIFEST-ARCHIVOS.json corresponde ahora a los 115 archivos de R5.

## Corrección visual · R3 · 14 de octubre de 2026

Se reprodujeron ambos defectos en el navegador con el código R2. A 1317 px, el computador empezaba en y=211 mientras el título terminaba en y=241 y los botones en y=348: cubría texto y controles. El contenedor medía 385 px, pero la ilustración crecía a 554 px. En La Ventanita la navegación, presentación y tarjetas se mostraban como bloques sin diseño; el banner medía 0 px de altura mínima porque no se cargaba restructure-r2.css.

La ilustración ahora participa en una cuadrícula que reserva su altura real y conserva el límite de 940 px, con computador y teléfono alineados abajo. La página del negocio solicita explícitamente sus estilos compartidos (5.4 KB), conservando el JavaScript de POS/salones para las pantallas que lo necesitan. El ejemplo de FISIChat responde directamente: «Tienes dos productos con poco stock: crema facial (3 unidades) y botella térmica (8 unidades). Te recomiendo reponerlos esta semana».

QA-PUBLICO-R3.json verifica 36 vistas públicas en total: portada y La Ventanita en nueve anchos (1366, 1317, 1024, 900, 768, 621, 620, 390 y 360), tanto con código fuente como con el payload exacto publicado en releases/1.8.0-r3. Comprueba que la ilustración no cubra título/botones, que los botones se puedan pulsar, que la tienda tenga navegación/banner/tarjetas adaptados, que conserve su logo, que el catálogo abra, que no se agregue JavaScript de POS a la tienda y que el administrador conserve su tema. También se verifica el diseño de un negocio pendiente en 1.7.9. No hubo errores JavaScript, recursos faltantes ni desbordamiento horizontal. Las imágenes del laboratorio usan datos y un logo ficticios, restaurados al terminar; no se modificó el contenido de la tienda real.

QA-ACTUALIZADOR-R3.json prueba R3 sobre el ZIP R2 publicado con un negocio180, otro179 y un turno abierto. Conserva las versiones, turnos, modificadores, usuarios/productos/ventas, config, uploads y archivos R2; cambia la entrada al nuevo directorio al terminar la copia. Rechaza código R2 modificado fuera del paquete conocido. La repetición no duplica respaldos. QA-PERMISOS-ZIP-R3.json verifica la extracción sobre R2 y en carpeta nueva bajo umask 077, sin reparar permisos después, con Apache UID33 y payload protegido.

R3 no cambia cálculos, base de datos, turnos, impresión ni código nativo. Se ejecutaron pruebas centradas en las vistas y el empaquetado afectados. La evidencia de las 24 suites y las pruebas nativas siguientes corresponde a R2 y a los instaladores1.8.0 sin cambios; no se presenta como una nueva ejecución completa en R3. No se accedió a cPanel ni se probó un Brave físico.

## Corrección de permisos del ZIP · R1 · 14 de octubre de 2026

Se detectó después de la entrega que la umask 077 del entorno de construcción guardaba 600 en los archivos del ZIP y omitía entradas explícitas de carpetas. La prueba original de instalación normalizaba los permisos después de extraer y ocultó este defecto. El constructor ahora fija en el ZIP archivos 644 y carpetas 755, independientemente de la umask. Las pruebas de instalación extraen con unzip y dejan los permisos del ZIP intactos.

QA-PERMISOS-ZIP-R1.json documenta una prueba adicional con Apache ejecutado como www-data (UID 33): el paquete previo extraído bajo umask 022 produce carpetas 755 y archivos 600, como informó el usuario, e impide servir los archivos de prueba. R1 se extrae bajo umask 077 tanto sobre esa misma carpeta como en una carpeta nueva, sin chmod/chown posterior; permite abrir los tres con HTTP 200 y entrar al actualizador con HTTP 302 hacia el acceso. Manifest y payload siguen bloqueados con HTTP 403. Los 109 archivos funcionales del payload son idénticos por SHA256. Si ya existían carpetas con permisos incorrectos, algunos extractores conservan esos permisos y deben revisarse según la guía. Más tarde, las capturas del hosting mostraron que la extracción estaba en /home/fisitaap y el dominio sirve /home/fisitaap/public_html. La guía corrige esa ubicación. La comprobación del hosting se hizo mediante resultados/capturas del usuario, sin acceso remoto a cPanel.

Fecha: 14 de octubre de 2026; se conserva la evidencia inicial del 13 de octubre para el motor nativo sin cambios. Copias ficticias; no se modificó cPanel ni la base real.

## Actualización independiente · R2

Las 24 suites de regresión web/API/navegador se completaron con el código R2. Se conservaron los instaladores Windows/Android 1.8.0: no cambió su código ni firma en esta revisión.

QA-ACTUALIZACION-POR-NEGOCIO.json recoge diez comprobaciones: publicación con dos negocios y turnos abiertos; activación de A sin alterar B; restricción al dueño y CSRF; migración/repetición sin duplicados; snapshots por token A180/B179; una venta calculada por el JavaScript real del cliente 1.7.9, recibida y cerrada correctamente sin duplicar stock/efectivo; republicación que mantiene pendientes y negocios nuevos; activación posterior de B.

QA-ACTUALIZACION-UI.json verifica el menú/botón real de dueño y maestro en Chromium, estados pendiente/actualizado, diseño a 390 px, recursos desde releases/1.8.0-r2 y concurrencia: las lecturas y operaciones de B responden mientras A mantiene su bloqueo de actualización. Una mutación de A espera hasta el límite y recibe un mensaje para reintentar. No se retiene ese bloqueo durante consultas ni llamadas externas de chat.

QA-PERMISOS-ZIP-R2.json comprueba los 112 hashes y la extracción tanto encima de los archivos originales 600 como en una carpeta nueva, con umask 077, sin chmod/chown posterior. Apache UID33 puede entrar al actualizador y los archivos privados del payload quedan con HTTP403. QA-ACTUALIZADOR.json comprueba el paquete R2 sobre tres versiones anteriores y el R1 publicado ya activado, dejando un turno abierto durante la publicación. El caso R1 conserva los negocios en 1.8.0. Los archivos app/assets viejos no se sustituyen; la nueva entrada sirve código y recursos de su carpeta propia, protegida contra acceso directo a PHP.

No se obliga a cerrar todas las cajas para publicar. Al activar, se comprueban los turnos/cuentas registrados de ese negocio y el dueño confirma que sus equipos no tienen ventas locales pendientes y que actualizó sus aplicaciones. El servidor no puede garantizar el estado de una caja desconectada; la confirmación no se presenta como detección automática de su cola local. No se cierra ni elimina efectivo, ventas o turnos por cuenta propia.

## Resultados

- 24 suites de regresión web/API/navegador aprobadas, más las comprobaciones de actualización por negocio, interfaz, permisos e instalación descritas en los informes JSON.
- 57 pruebas Node aprobadas; 1.500 cálculos JavaScript/PHP iguales al céntimo, concurrencia y recuperación; controladores Windows sustituidos en pruebas.
- 16 pruebas Android JUnit aprobadas y lint debug/release; persistencia SQLite cifrada, migración, Unicode/PDF y receptores TCP de prueba para 58/80 mm.
- 84 páginas reales en Chromium a 1366/768/390/360 y 20 vistas adicionales de POS cargado, cobro, mesa ocupada, diseñador y KPIs: sin desbordamiento ni errores JavaScript. Acceso de siete roles.
- XML 4.4 cargado por multipart HTTP: proveedor por identificación normalizada, proveedor/producto nuevo, SKU existente, borrador, recepción repetida, XML cambiado de pestaña y rechazo de entidades externas.
- Dos usuarios, sesiones, equipos y cajas distintos cobrando simultáneamente: una venta y un pago; conflicto humano en la otra caja y arqueos separados.
- ZIP extraído e instalado desde archivos 1.7.8, 1.7.9-r2, mudanza 1.7.9-r3 y R1 1.8.0 ya activado; maestro/CSRF, hashes, rechazo de cambios desconocidos, respaldo protegido, repetición, publicación con turnos abiertos, entrada/recursos versionados y restauración solo antes de preparar/publicar la base.

## Rendimiento

Volumen ficticio: 1.008 productos, 1.002 clientes y 20 salones adicionales con 200 mesas, 60 sectores y 100 objetos. Tres muestras por ruta. Son tiempos del entorno de pruebas, no una promesa para el hosting.

| Pantalla | Consultas | Mediana PHP | Respuesta HTML |
| --- | ---: | ---: | ---: |
| /laventanita/catalogo | 11 | 12.2 ms | 9683 bytes |
| /admin/ventas | 18 | 42.8 ms | 73335 bytes |
| /admin/clientes | 10 | 26.7 ms | 85052 bytes |
| /admin/mesas?room=3 | 24 | 34.7 ms | 67005 bytes |
| /admin/reportes?dataset=sales&from=2020-01-01&to=2030-01-01 | 35 | 27.8 ms | 87155 bytes |
| /admin/reportes?dataset=inventory&from=2020-01-01&to=2030-01-01 | 35 | 32.9 ms | 97079 bytes |

Se separan los tiempos PHP de proceso/red; no se incluye el retraso de 500 ms del evento networkidle como tiempo de PHP. Reportes se midió en el rango 2020–2030. Con catálogo sin cambios, la sincronización entrega existencias y datos de caja en vez de descargar de nuevo productos/clientes. Los catálogos originales se conservan para ventas pendientes e historial.

## Migración y archivos

schema180.php agrega las tablas 180 de cajas, colas de impresión, dinero, borradores, asignaciones de modificadores, mesas vinculadas, usos de cupón, pedidos vinculados, listas compartidas y operaciones idempotentes, y los campos/índices de los módulos relacionados. Se ejecuta por publicación explícita, fuera de las consultas normales. Cada negocio tiene un estado independiente en fisitaap_tenant_updates180, consultado por clave primaria y almacenado en memoria durante la solicitud. La conversión de modificadores, cajas, mesas y roles se limita al negocio que activa y su versión cambia al final de la transacción. No hay DDL durante esa activación individual. Se conservan tablas y registros anteriores.

MANIFEST-ARCHIVOS.json enumera los 115 archivos funcionales instalados en R4 y su SHA-256. Native: desktop/ y android/ contienen el motor compartido, protección de vistas/credenciales, persistencia, impresión y recursos 1.8.0. El ZIP evita config.php, claves privadas, SQL y archivos cargados del negocio.

## Revisión de las solicitudes

REQUISITOS.json relaciona los 42 apartados del QA manual con evidencia. Se revisaron también: un solo principal Windows/Android sin internet, menús completos conectados, Android directo a web o central, impresión sin FISITAAP Print en el flujo normal, identidad de la tienda, estilo de administración uniforme, demos independientes, conservación de La Ventanita e imágenes optimizadas. Las referencias del PDF se compararon con venta/cobro/mesas/diseñador y con la solicitud posterior de bordes rectos.

La velocidad previa del hosting y el certificado del nuevo servidor no pueden verificarse con estos fixtures. FISIChat libera la sesión antes del llamado externo y usa la sucursal seleccionada; la espera/respuesta del proveedor de IA real queda sin prueba porque no se usó una clave productiva.

## Comprobación pendiente en tus equipos

- Ejecutar el instalador en Windows físico y el APK en Android físico; se construyeron y verificaron sus paquetes, no se ejecutaron en esos sistemas físicos.
- Probar papel, margen/corte y gaveta con la impresora/controlador configurados. El ancho 58/80 mm no garantiza compatibilidad ESC/POS por sí solo.
- Verificar HTTPS válido, permisos/propietario PHP, tiempo del hosting y actividad del negocio después de instalar.
- La firma APK v2 se verificó y su clave tiene respaldo privado separado. Windows no cuenta con certificado Authenticode de editor; el instalador no debe describirse como firmado por un editor verificado.

## Informes detallados

- [QA-ACTUALIZADOR.json](QA-ACTUALIZADOR.json): 4 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-ANDROID-UI.json](QA-ANDROID-UI.json): 7 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CATALOGO.json](QA-CATALOGO.json): 7 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-COBRO-SIN-RESPUESTA.json](QA-COBRO-SIN-RESPUESTA.json): 4 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CONFIGURACION.json](QA-CONFIGURACION.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CONTINUIDAD.json](QA-CONTINUIDAD.json): 9 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CUENTAS-COMPRAS.json](QA-CUENTAS-COMPRAS.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-CUPONES.json](QA-CUPONES.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-DEMOS.json](QA-DEMOS.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-DINERO.json](QA-DINERO.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-DOS-CAJAS.json](QA-DOS-CAJAS.json): 3 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-FIDELIZACION.json](QA-FIDELIZACION.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-FLUJOS-USO.json](QA-FLUJOS-USO.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-IMPRESION.json](QA-IMPRESION.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-MESAS.json](QA-MESAS.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-NAVEGADOR.json](QA-NAVEGADOR.json): 84 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-PANEL.json](QA-PANEL.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-PEDIDOS.json](QA-PEDIDOS.json): 10 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-PERMISOS.json](QA-PERMISOS.json): 8 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-POS.json](QA-POS.json): 7 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-RENDIMIENTO.json](QA-RENDIMIENTO.json): 6 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-REPORTES.json](QA-REPORTES.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-XML.json](QA-XML.json): 5 comprobaciones/vistas; alcance indicado en cada archivo.
- [QA-ACTIVACION-DEMOS.json](QA-ACTIVACION-DEMOS.json): 2 comprobaciones; alcance indicado en el archivo.
- [QA-SUCURSAL-NATIVA.json](QA-SUCURSAL-NATIVA.json): 1 comprobaciones; alcance indicado en el archivo.

- [QA-ACTUALIZACION-POR-NEGOCIO.json](QA-ACTUALIZACION-POR-NEGOCIO.json): 10 comprobaciones de actualización por negocio y versiones mixtas.
- [QA-ACTUALIZACION-UI.json](QA-ACTUALIZACION-UI.json): menú, estados, recursos y concurrencia por negocio.
- [QA-PERMISOS-ZIP-R2.json](QA-PERMISOS-ZIP-R2.json): dos extracciones sin reparar permisos y 112 hashes.

- [QA-PUBLICO-R3.json](QA-PUBLICO-R3.json): 36 vistas públicas, nueve anchos y comprobaciones de CTA, logo, catálogo, versión pendiente y tema administrativo.
- [QA-ACTUALIZADOR-R3.json](QA-ACTUALIZADOR-R3.json): instalación sobre R2 con estado mixto y turno abierto.
- [QA-PERMISOS-ZIP-R3.json](QA-PERMISOS-ZIP-R3.json): dos extracciones, 112 hashes y acceso Apache UID33.
