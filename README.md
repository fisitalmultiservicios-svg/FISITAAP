# FISITAAP ERP 4.40.1 — instalador para Windows

[Descargar instalador 4.40.1 para Windows 10/11 de 64 bits](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/descargas-windows/FISITAAP-ERP-4.40.1-Instalador.exe)

Esta entrega parte del paquete oficial ERP 4.40 y agrega los ajustes solicitados por su propietario. La rama `descargas-windows` solo distribuye instaladores, es independiente de la rama principal y no representa el código fuente del ERP.

## Cambios de 4.40.1

- Catálogo web, Clientes web y Mi página se administran exclusivamente en cloud. Se ocultan del menú y del buscador local; un enlace guardado muestra un aviso para abrir la versión online. El servidor local también rechaza las operaciones directas de administración.
- FISITAAP abre en una ventana propia de Windows con Microsoft WebView2. El acceso directo del escritorio abre el programa y lleva su icono.
- El inicio de sesión local ofrece un selector de usuarios sincronizados de empresas vinculadas. No incluye cuentas bloqueadas, de soporte o de empresas no vinculadas. La contraseña, MFA, PIN y el primer ingreso con internet conservan sus reglas. Incluye actualizar lista e ingreso manual por correo.
- Se mantienen sincronización de productos, operación de pedidos web, caja, impresión y red local.

## Instalar o actualizar

1. Terminar las operaciones y cerrar las ventanas de FISITAAP.
2. Ejecutar el instalador 4.40.1 sobre la instalación actual. Los datos y la vinculación se conservan; no es necesario desinstalar ni borrar carpetas.
3. El asistente comprueba WebView2. Si falta, necesita internet para descargar el instalador oficial de Microsoft y verifica su firma antes de ejecutarlo.
4. Abrir «FISITAAP Local» desde el escritorio. La ventana propia puede pedir iniciar sesión nuevamente, porque usa un perfil distinto al navegador anterior. La vinculación existente permanece guardada.
5. Mantener activada la opción de red local si otros equipos deben conectarse. Esos equipos usan `http://IP-DE-LA-PC:8020/app/` en la misma red.

Al cerrar la ventana, el servidor sigue activo para los dispositivos secundarios. El icono del escritorio vuelve a abrirla. El PC principal y la red local deben permanecer encendidos. La primera vinculación y el primer ingreso de cada usuario necesitan internet conforme a las reglas del ERP.

Esta descarga no modifica cPanel y no contiene datos de empresas ni credenciales. Las secciones exclusivas de cloud se abren en la web con la cuenta correspondiente.

## Validación y límites

203 pruebas relevantes únicas aprobadas: bloqueo, selector, autenticación, sincronización, pedidos, menús, impresión simulada y lanzador. Se omitieron 16 casos que requieren Windows real. Los archivos originales recibidos permanecen intactos; los cambios están en una copia de trabajo.

El EXE Windows compilado pasó su autoprueba y 15 comprobaciones de arranque sin red externa bajo Wine, incluyendo bloqueo de administración web, selector, autenticación y agente de impresión. El código de la ventana compiló contra las bibliotecas reales de Microsoft WebView2.

La ventana gráfica, PowerShell, la instalación del Runtime WebView2, el asistente de instalación y una impresora física todavía requieren validación en un equipo Windows real. Estos resultados no son una ejecución de toda la suite del ERP ni una prueba con cuentas productivas.

El instalador y el programa no llevan firma Authenticode propia. Su integridad puede verificarse con `SHA256SUMS.txt`.

No se debe cargar el asistente de instalación en el lugar del ejecutable que usa el actualizador de la nube. Las actualizaciones del programa requieren que WebView2 ya esté instalado en los equipos.

## Versión anterior

[Instalador 4.40 original](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/descargas-windows/FISITAAP-ERP-4.40-Instalador.exe)
