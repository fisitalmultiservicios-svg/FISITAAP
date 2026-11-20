# FISITAAP ERP 4.40.2 — instalador para Windows

[Descargar instalador 4.40.2 para Windows 10/11 de 64 bits](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/descargas-windows/FISITAAP-ERP-4.40.2-Instalador.exe)

La rama `descargas-windows` distribuye instaladores. Es independiente de la rama principal y no representa el código fuente del ERP. La base de esta entrega es el paquete oficial ERP 4.40 y los cambios autorizados por su propietario.

## Correcciones de 4.40.2

- Se corrige el aviso que seguía pidiendo recargar: en 4.40.1 el EXE tenía la versión nueva, pero la interfaz seguía indicando 4.40. Ahora servidor, interfaz y service worker indican 4.40.2 y el constructor impide empaquetar versiones distintas.
- La ventana se ejecuta en un programa Windows propio, con el icono e identidad de FISITAAP para ventana, barra de tareas y Alt+Tab. La interfaz ya no se aloja en PowerShell. El acceso del escritorio mantiene su icono de FISITAAP.
- La ventana retira el controlador de caché local antes de navegar y vuelve a cargar el programa. No se borran cookies, localStorage, IndexedDB, vinculación ni bases del ERP.

Se conservan los cambios de 4.40.1: selector de usuarios locales con contraseña/MFA/PIN según sus reglas, Catálogo web/Clientes web/Mi página exclusivos de cloud, operación de pedidos web, sincronización de productos, caja, impresión y red local.

## Actualizar

1. Termine las operaciones y cierre todas las ventanas de FISITAAP.
2. Ejecute este instalador sobre la instalación actual; conserve la opción de red local si otros equipos se conectan al PC. No hace falta desinstalar ni borrar carpetas.
3. Abra «FISITAAP Local» desde el acceso del escritorio. Se conservan los datos y la vinculación de la empresa.

El instalador verifica WebView2 y, si falta, necesita internet para instalar el componente oficial de Microsoft después de comprobar su firma. La ventana usa .NET Framework 4.6.2 o posterior, disponible en las versiones actuales de Windows 10/11. PowerShell se utiliza durante la instalación y para crear accesos, no como interfaz del programa.

Al cerrar la ventana, el servidor sigue activo para los dispositivos secundarios. El acceso del escritorio vuelve a abrirla. Los secundarios entran a `http://IP-DE-LA-PC:8020/app/`; el PC y la red deben permanecer encendidos. Esta descarga no modifica cPanel ni contiene datos de empresas o credenciales.

## Validación

52 pruebas de regresión aprobadas en esta corrección: versiones, bloqueo, selector, inicio de sesión, lanzador, menús e impresión simulada. El EXE Windows pasó su autoprueba y 17 verificaciones de arranque sin red externa bajo Wine, incluida la coincidencia de versiones del servidor, interfaz y service worker.

La ventana propia compiló contra el SDK real de WebView2. Se verificaron los nueve tamaños del icono de FISITAAP incrustados en su ejecutable, su tipo de aplicación gráfica x64 y la integridad de fuente, ejecutable y bibliotecas. La autoprueba del EXE verifica también los componentes empaquetados.

La comprobación visual de la ventana, barra de tareas, asistente, instalación del runtime y una impresora física todavía requieren Windows real. Estas pruebas no equivalen a ejecutar toda la suite del ERP ni a validar cuentas productivas.

El instalador y el programa no llevan firma Authenticode propia. Los hashes están en `SHA256SUMS.txt`.

No se debe cargar el asistente de instalación como ejecutable del actualizador de cloud. El publicador usa el programa y su manifiesto; los equipos necesitan WebView2 antes de habilitar actualizaciones automáticas.

## Versiones anteriores

Se conservan los archivos 4.40 y 4.40.1 para trazabilidad. Para el aviso de versión y el icono de PowerShell use 4.40.2.
