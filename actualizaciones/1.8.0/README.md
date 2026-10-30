# FISITAAP web 1.8.0-R19

[**Descargar actualización R19**](../../descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R19.zip) · [Instalar y usar](GUIA-CPANEL.md) · [Solicitudes y QA](INFORME-QA.md)

Acciones pequeñas visibles en Venta rápida, apertura/cierre directo, foco de búsqueda, FISIChat por encima de otros elementos, Usuarios separado de Control de personal, directorio actual de la tienda, navegador como Caja0 y atención de mesas en una página cómoda en celular.

Actualización acumulativa: conserva usuarios, contraseñas, expedientes, ventas y fondos de R18. Instala desde `/actualizador-180/index.php` como maestro, después de respaldar. Conserva `releases/1.8.0-r19`. Publicar el paquete no instala el hosting.

No necesita reinstalar Windows1.8.1/Android1.8.0. El directorio conectado utiliza su puente existente; offline conserva la autorización sincronizada. Las estimaciones laborales de R17 no cambian ni reciben certificación legal en esta revisión.

---

## Entregas anteriores

# FISITAAP web 1.8.0-R18 · Windows 1.8.1

[**Descargar actualización cPanel R18**](../../descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R18.zip) · [Guía](GUIA-CPANEL.md) · [Solicitudes y QA](INFORME-QA.md)

R18 mejora Venta rápida y Mesas en celular/tablet, hace visible Abrir turno, cancela únicamente cuentas vacías, incorpora Caja 0 de atención, fondos separados por caja, vendedor opcional y cierre exclusivo por el cajero que abrió turno. Agrega nombres rápidos, contraseñas desde cuatro caracteres, avisos del dueño en FISIChat y lecciones de Academiaap actualizadas.

Es acumulativa: conserva R17 y las mejoras anteriores. Respalda archivos/base, extrae en la raíz real del dominio y ejecuta `/actualizador-180/index.php` como maestro. Conserva `releases/1.8.0-r18`, config.php y uploads. Publicar archivos en GitHub no instala el hosting.

148 archivos funcionales con permisos 644/755. No se reconstruyeron instaladores Windows/Android: las funciones conectadas cargan desde la web actualizada. La venta sin internet existente conserva compatibilidad; el vendedor offline es su operador original. Los cálculos laborales de R17 siguen siendo estimaciones para revisar, sin certificación legal ni pagos automáticos.

---

## Entrega anterior

# FISITAAP web 1.8.0-R17 · Windows 1.8.1

R17 corrige accesos de clientes/cajeros, separa Catálogo y Menú, añade **Academiaap**, configurador pausado y **Control de personal**. Conserva las mejoras de FISIChat y el catálogo compacto de R16.

- [Descargar actualización cPanel R17](https://raw.githubusercontent.com/fisitalmultiservicios-svg/FISITAAP/main/descargas/1.8.0/FISITAAP-1.8.0-CPANEL-R17.zip)
- [Guía de instalación y uso](GUIA-CPANEL.md)
- [Repaso de todas las solicitudes y QA](INFORME-QA.md)
- [Capturas](https://github.com/fisitalmultiservicios-svg/FISITAAP/blob/main/descargas/1.8.0/VISTAS-R17.md)

El cliente nuevo usa su celular de ocho dígitos como usuario y contraseña inicial desde el catálogo de su tienda. Se conserva la contraseña que haya cambiado. Los clientes pueden retirarse del listado con motivo, permiso y conservación del historial; primero se resuelven documentos y deuda pendientes. Los cajeros pueden usar correo o alias asignado; los correos son únicos entre tiendas.

Catálogo no muestra cocina, mesas o comanda. Menú imprime al aceptar y prepara en Monitor. Pedidos mantiene la lista y asigna Express. Sin Entregas, el negocio coordina el domicilio directamente. Las direcciones nuevas guardan su zona de envío; el slider permite color del texto y el comprador usa su navegación de compra actual.

Academiaap guarda el progreso y abre la pantalla de práctica con retorno al curso. El configurador acompaña durante los primeros días y se puede pausar. Personal reúne expediente/acceso, horario, marcas, vacaciones y bases salariales. Marcar se muestra en Windows/Android instalado, valida el equipo y pide credenciales de la persona; necesita conexión.

**Los cálculos laborales son estimaciones para revisar**, con salarios, causa de salida y supuestos visibles. No generan finiquitos ni pagos. No se pudo verificar el texto oficial vigente de SCIJ/MTSS por bloqueo de red del entorno. Consulta los límites en la guía antes de liquidar.

Instalación: respalda archivos y base de datos, extrae el ZIP en la raíz real del dominio (`public_html`) y ejecuta **/actualizador-180/index.php** con sesión maestra. Extraer solamente no instala. Conserva **releases/1.8.0-r17**, config.php, uploads y versiones anteriores. No requiere SQL manual ni reinstalar Windows1.8.1/Android1.8.0.

El ZIP contiene **144 archivos funcionales** con permisos644/755. El esquema nuevo se crea durante la instalación explícita. Se preservan datos, activaciones por negocio y turnos. Las versiones R1…R16 siguen disponibles como historial. Publicar el paquete en GitHub no modifica el hosting.

Pruebas con PHP/MariaDB/Chromium reales y datos ficticios. Los equipos/impresoras físicos y el hosting necesitan verificación después de instalar. No se enviaron WhatsApp/correos ni se ejecutaron pagos reales. La presentación comercial de FISITAAP mantiene su diseño.
