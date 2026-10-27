# FISITAAP · Código completo para QA externo

Fecha: **27 de octubre de 2026** · Web **1.8.0-R13** · Windows **1.8.1** · Android **1.8.0**.

Código de referencia: **29d5a9e11ee67239b37617096f23fd94b7f98eca**. Se incluyen todas las fuentes vigentes de la web y sus clientes nativos, no solamente los archivos que cambiaron en R13. Las rutas del repositorio se conservan para facilitar referencias de archivo y línea.

**Este ZIP es para revisión técnica. No se extrae en public_html como actualización.** El instalador web publicado se conserva como referencia en `descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R13.zip` dentro de este paquete.

## Qué contiene

- `actualizaciones/1.8.0/web/`: código PHP completo, estilos, JavaScript, imágenes de ejemplo, configuración Apache y herramientas auxiliares. El punto de entrada es `index.php`.
- `actualizaciones/1.8.0/updater/`, `recuperacion/`, `diagnostico-bienvenida/` y `diagnostico-ruta/`: fuentes del actualizador y herramientas de recuperación/diagnóstico. Algunos diagnósticos son históricos; su presencia no significa que se publiquen todos en R13.
- `desktop/`: fuentes completas del cliente Windows 1.8.1, archivo de dependencias fijadas, instalador NSIS y pruebas Node.
- `android/`: fuentes completas Android 1.8.0, Gradle wrapper, motor offline compartido y pruebas. La firma privada no forma parte del paquete.
- `qa/`: pruebas y scripts del repositorio, incluido el esquema base. Hay pruebas históricas y scripts que crean, reinician o modifican sus propios entornos ficticios.
- `actualizacion-fisitaap-r5/migration-r1.sql`: migración estructural de roles que usa el entorno histórico de pruebas.
- `auditoria/2026-10-27-r13/esquema-r13-sin-datos.sql`: referencia DDL de **102 tablas**, tomada del entorno ficticio actualizado; no tiene registros ni contadores AUTO_INCREMENT. No sustituye las migraciones PHP de la aplicación.
- `actualizaciones/1.8.0/INFORME-QA.md`, `GUIA-CPANEL.md` y `QA-*.json`: documentación y evidencia publicada. El informe distingue pruebas actuales e históricas.
- `MANIFEST-QA.json` y `SHA256SUMS-QA.txt`: inventario y hashes de todos los archivos del paquete, con su procedencia.

No se incluyen `.git`, credenciales de GitHub/IA/BD, configuración privada del hosting, claves de firma, respaldos, ventas/clientes reales, comprobantes privados, archivos de usuarios ni dependencias instaladas. `uploads/.htaccess` sí se incluye porque es código de protección de archivos. Los datos generados por las pruebas son ficticios, aunque algunos nombres de ejemplo coincidan con nombres conocidos.

## Alcance y límites de esta copia

La fuente corresponde al último código publicado en el repositorio. No es una descarga del cPanel: no permite verificar si el hosting tiene cambios manuales posteriores, qué archivos adicionales conserva, la configuración real de PHP/Apache, el certificado, tareas programadas, reglas del proveedor o el contenido de su BD. Esos puntos necesitan una revisión separada del despliegue real.

Las versiones antiguas completas y los instaladores binarios Windows/APK no se duplican aquí. Las pruebas de migración que necesitan ZIP anteriores pueden obtenerlos del mismo repositorio público. El código de Windows/Android y los contratos de intercambio sí están incluidos para revisar conexión, impresión, persistencia y venta sin red.

Las evidencias de R13 usan PHP/MariaDB y Chromium con cuentas ficticias y un proveedor IA TLS simulado. No certifican la calidad de un modelo real ni todas las combinaciones de teléfonos, impresoras o redes. El esquema DDL refleja el entorno de referencia, que conserva tablas de compatibilidad; no demuestra que todas ellas sean imprescindibles en cada instalación.

## Mapa del código

Todas las rutas PHP siguientes parten de `actualizaciones/1.8.0/web/`.

| Área | Archivos principales |
|---|---|
| Entrada, rutas y sesión | `index.php`, `app/bootstrap.php`, `app/core.php`, `app/session_reads176.php` |
| Autenticación, usuarios y permisos | `app/v1.php`, `app/restructure_r1.php`, `app/modules_v12.php`, `app/operations180.php` |
| Navegación y panel | `app/admin.php`, `app/panel180.php`, `app/restructure_r2.php`, `app/restructure_r3.php`, `assets/experience-r11.js`, `assets/experience-r11.css` |
| Web oficial y diseño de tiendas | `app/official_r3.php`, `app/catalog_r2.php`, `app/settings180.php`, `app/branches_v1.php` |
| Catálogo, carrito y checkout | `app/branches_v1.php`, `app/checkout_r3.php`, `app/commerce180.php`, `assets/app.js`, `assets/operations180.js` |
| Clientes por teléfono, direcciones y PIN | `app/customer_phone_r11.php`, `app/customers180.php`, `app/operaciones180.php`, `app/delivery180.php`, `app/v1.php` |
| POS, mesas y cobros | `app/pos_v141.php`, `app/pos_v148.php`, `app/sales_r3.php`, `app/floor_r3.php`, `app/pos180.php`, `app/cash180.php` |
| Cantidades y opcionales | `app/experience_r11.php`, `app/modifiers180.php`, `app/optional_groups180.php`, `app/physical_v14.php` |
| Compras, cuentas e informes | `app/purchases180.php`, `app/purchases_r2.php`, `app/finance180.php`, `app/accounts_r2.php`, `app/reports180.php`, `app/reports_r3.php` |
| Pedidos, Express, incidencias y pagos moto | `app/orders180.php`, `app/operaciones180.php`, `app/delivery180.php`, `app/live180.php` |
| Importación Excel/CSV e imágenes | `app/product_import180.php`, `app/image_optimizer.php`, `app/admin.php`, `assets/image-upload.js` |
| FISIChat y límites por perfil | `app/fisichat_scope_r13.php`, `app/fisichat_public180.php`, `app/chat_r2.php`, `app/fisichat_v143.php`, `assets/fisichat.js` |
| Demos aisladas | `app/demo_sandbox.php`, `app/demo_cleanup.php`, `app/browser_migration179.php` |
| Publicación y migraciones | `app/updates180.php`, `app/schema180.php`, `app/operaciones180.php`, `app/schema_r2.php`, `app/schema_r3.php` |
| Contratos nativos y principal único | `app/desktop_r2.php`, `app/devices180.php`, `app/offline180.php`, `app/primary179.php` |
| Impresión | `app/printing180.php`, `app/printing_r3.php`, `assets/direct-print.js`, `desktop/print-bridge.js`, clases Java de Android |

Windows usa además `desktop/server.js`, `local-store.js`, `workspace.js`, `main.js` y los preload. Android comparte el motor de ventas de Windows mediante `android/build-offline-assets.js`; conviene revisar tanto la fuente original como el código generado en `android/app/src/main/assets/offline/`.

## Preparar la revisión

Para una auditoría estática basta extraer el ZIP y revisar código, esquemas y manifiesto. Ninguna credencial real es necesaria.

Para QA ejecutable, prepara un entorno desechable con **PHP 8.2/8.3, PDO MySQL, mbstring, GD/WebP, DOM/SimpleXML, cURL, ZIP y EXIF**, Apache con rewrite/headers y MariaDB/MySQL compatible. Separa la BD y las carpetas de cualquier instalación real. El esquema DDL sin registros permite examinar relaciones e índices; por sí solo no crea usuarios, negocios, ajustes de activación ni datos para recorrer el sitio.

`auditoria/2026-10-27-r13/config.example.php` es una plantilla nueva para ese entorno: usa variables locales y exige una clave propia. No es la configuración del hosting. Para entender la preparación completa de fixtures, lee `qa/install_environment180.py`, `qa/setup180.py`, los módulos `schema_*` y `app/updates180.php` antes de ejecutar nada.

Las suites web originales conservan nombres de contenedor, puertos y rutas absolutas bajo `/workspace`. Algunas dependen de helpers que genera `qa/setup180.py` y de versiones anteriores no incluidas. Necesitan adaptar el entorno o reproducir esas rutas; **no se promete que toda la suite se ejecute con un único comando en otro equipo**. Varios scripts reinician bases de prueba o simulan cobros: inspecciona sus destinos antes de ejecutarlos.

Para revisar las pruebas de Windows:

```sh
cd desktop
npm ci
npm test
```

Para Android, instala primero las dependencias de `desktop` porque la tarea de assets comparte bcrypt y el motor Windows. Usa JDK/SDK compatibles con Gradle 8.13, Android Gradle Plugin 8.9.2 y SDK 35. La compilación debug no necesita la clave privada de la aplicación publicada:

```sh
cd android
./gradlew :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

En sistemas que no conserven el permiso del wrapper, usa `chmod +x gradlew` o `bash gradlew`. No se incluye una nueva firma release. Los README nativos contienen referencias históricas; las versiones y reglas vigentes se comprueban en `desktop/package.json`, `android/app/build.gradle`, el código y el informe R13.

## Lista para revisar todos los aspectos

1. **Identidad y sesión:** acceso maestro/dueño/roles/cliente/moto, negocio suspendido, revocación inmediata, cambio obligatorio de clave, recuperación, cookies, CSRF, cierre y fijación de sesión.
2. **Aislamiento:** cada SELECT/escritura debe quedar limitado al negocio, sucursal, usuario, equipo y rol autorizado. Intentar cambiar IDs de clientes, pedidos, recibos, comprobantes y dispositivos.
3. **Datos personales:** direcciones, teléfono, ubicación, historial, PIN y comprobantes. La contraseña inicial igual al teléfono y la consulta de direcciones por teléfono son comportamientos solicitados previamente: deben evaluarse expresamente por su debilidad y exposición, sin asumir que verificar el formato verifica la identidad.
4. **Catálogo y web:** navegación actual, logo de tienda, colores separados del panel, slider, publicación, variantes, stock, ramas por sucursal, accesibilidad, móviles, teclado, zoom y compatibilidad Safari/Brave/Chrome.
5. **Checkout:** precios recalculados en servidor, cantidades por unidad/granel, opciones obligatorias, descuentos, IVA, ubicación, prueba SINPE, teléfono/direcciones, reintentos, WhatsApp, sesión de comprador, seguimiento y PIN.
6. **POS y dinero:** aperturas/cierres, principal/cajas secundarias, cobros concurrentes, pagos divididos, redondeo, devoluciones/anulaciones, crédito, CxC/CxP, saldos, fuentes de fondos, cortes moto y bitácora. Buscar dobles escrituras y divergencias entre JS/PHP/motor local.
7. **Pedidos y Express:** aceptación, comanda única, estados/timestamps, asignación directa/pool, autorización para cancelar, justificación, PIN, incidencias y actualización entre usuarios.
8. **Sin internet:** autorización vigente del principal, catálogo descargado, pérdida de conexión, reinicio, persistencia, recuperación de archivo dañado, stock, múltiples ventanas/cajas, cambio de principal y sincronización idempotente sin duplicar ventas.
9. **FISIChat:** perfiles distintos, datos entregados al proveedor, instrucciones ajenas en mensajes/productos, cambio de perfil, historial, rechazo de asuntos ajenos, confirmaciones, herramientas anteriores y filtros de salida. Los filtros e instrucciones no son una prueba de que un modelo real nunca se desvíe.
10. **Archivos y red:** validación de bytes/MIME/dimensiones, optimización, comprobantes privados, Excel/CSV/XML, fórmulas y ZIP hostiles, traversals, SQLi/XSS, SSRF, redirecciones, enlaces Maps, impresoras y WebView/preload/orígenes confiables.
11. **BD y arquitectura:** claves foráneas, índices, tipos monetarios/cantidades, transacciones/bloqueos, idempotencia, tablas heredadas, módulos sin uso, límites y consistencia de migraciones. No eliminar tablas solo porque su nombre parezca antiguo.
12. **Rendimiento:** consultas repetidas, índices y planes SQL, polling de 2s, sesión PHP liberada durante IA, cargas/compresión, memoria de PHP, imágenes, frontend, caché y OPcache.
13. **Instalación y mantenimiento:** ZIP íntegro, permisos, archivos privados, raíz correcta, TLS, .htaccess, respaldo/recuperación, activación por negocio, alta de negocios nuevos, demo temporal y limpieza de archivos antiguos.
14. **Dependencias y builds:** lockfiles, licencias incluidas, bibliotecas, wrapper, firma/actualizaciones nativas, permisos Android, Electron, almacenamiento local y límites de la prueba de impresoras físicas.

## Entregar los resultados

Usa `auditoria/2026-10-27-r13/FORMATO-HALLAZGOS.md`. Para cada problema indica versión, archivo y línea, pasos, rol/negocio/sucursal, resultado esperado/obtenido, evidencia, impacto, propuesta y una comprobación que detecte la regresión. Separa fallos reproducidos, riesgos del diseño, documentación obsoleta y pruebas todavía no realizadas.
