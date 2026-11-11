# Actualización opcional Clínicas sobre FISITAAP ERP 4.22

Estado: candidato para pruebas de instalación y aceptación. No es una versión 4.23 publicada y no se ha instalado en cPanel. El alcance real y los pendientes están en `docs/CLINICAS_ALCANCE.md`.

## Qué contiene

Actualización diferencial sobre el ZIP oficial 4.22 con SHA-256 `a48406d04df00351d690bc4568e611b17449ff8a5035740cc0513fc0bacd20ad`. Las rutas `app/`, `tools/` y `tests/` se extraen directamente en la raíz Python existente. El manifiesto indica cada archivo nuevo o reemplazado y sus huellas antes/después. No contiene `data/`, bases, certificados, claves, `.env`, usuarios ficticios ni dependencias de desarrollo instaladas.

La versión del ERP continúa siendo 4.22. El módulo se identifica como `clinicas-1.0-rc1`; ese identificador describe este paquete, no una publicación o certificación sanitaria.

## Preparar e instalar primero en una copia de pruebas

1. En cPanel, anote la raíz REAL de la aplicación Python y el valor configurado de `FE_DATA_DIR`, si existe. La aplicación sigue usando `passenger_wsgi.py` y la entrada `application`. Mantenga su versión Python (mínimo 3.10; validación de este paquete en Python 3.12).
2. Detenga escrituras durante la copia de seguridad. Obtenga un respaldo GLOBAL consistente del ERP, incluyendo base central, bases por empresa y archivos persistentes. Descargue una copia fuera del servidor y compruebe que se puede abrir. Un ZIP suelto de archivos `.db` copiados durante escrituras WAL no sustituye un respaldo consistente.
3. Conserve las variables existentes de cPanel y los archivos `data/master.key` y `data/secret.key` donde existan. Guarde de forma segura las variables `FE_MASTER_KEY` y `FE_SECRET_KEY` si su instalación las usa. **No genere ni reemplace claves.** Los respaldos por empresa no incluyen las claves maestras del servidor.
4. Guarde también una copia de los archivos de código que el manifiesto va a sustituir. No borre `data/`, ni la raíz de la aplicación ni sus variables.
5. Prepare una instalación de pruebas separada, con su propio `FE_DATA_DIR` y base central; use únicamente datos ficticios o una copia autorizada y protegida. Desactive cola, cron, correo y conexiones de producción mientras prueba. No apunte simultáneamente pruebas y producción a los mismos archivos de datos.
6. Antes de extraer el paquete, suba aparte `MANIFIESTO_CLINICAS.json` y `tools/verificar_clinicas.py` (disponibles también dentro del ZIP). Desde la raíz de la aplicación y con su entorno virtual activo, ejecute:

   ```bash
   python tools/verificar_clinicas.py
   ```

   Debe mostrar cero conflictos. Si señala un archivo distinto, compare la personalización con el paquete antes de continuar. La herramienta solo lee código; no accede a datos ni a claves.
7. Extraiga el ZIP diferencial en la raíz de esa aplicación de pruebas. Debe quedar `app/clinicas.py`, sin una subcarpeta que envuelva todo el paquete. Use los permisos actuales del hosting; no aplique `chmod -R` sobre la instalación ni sus datos.
8. Repita el verificador. Los archivos del manifiesto deben aparecer actualizados. La aplicación usa las dependencias de `requirements.txt` de 4.22; no hay dependencias nuevas de producción. `pypdf` se usó únicamente para pruebas, y Playwright para verificar el navegador.
9. Reinicie la aplicación desde Setup Python App o con el mecanismo Passenger que ya use su instalación. La migración crea únicamente tablas `clinica_*` en cada base empresarial al abrirla por primera vez. No borra ni renombra tablas anteriores y no cambia claves, planes contratados, saldos o usuarios.
10. Ejecute la lista de aceptación de abajo. Tras aceptarla en pruebas y programar una ventana de actualización, repita respaldo, comprobación de archivos, extracción y reinicio en producción. Este trabajo no realizó esos pasos sobre su cPanel.

## Activación y permisos

Clínicas nace APAGADO, incluso en empresas sin plan, demos y paquetes de hotel con extras incluidos. No se añadieron precios ni se cambiaron tarifas.

En la consola de administración, abra la empresa → Plan y extras → Funciones en prueba y active **Módulo Clínicas (piloto sobre 4.22)**. Si el módulo está oculto para la empresa o para el usuario, debe habilitarlo también mediante los controles existentes de módulos. No edite JSON en la base de datos para activarlo.

Todos los usuarios de Clínicas necesitan MFA activo; el módulo no acepta la renuncia a MFA como sustituto. Los tokens generales de integración no acceden a expedientes.

El Administrador conserva sus permisos ERP y obtiene administración de pacientes/agenda y configuración clínica. Avanzado recibe pacientes/agenda. Ninguno recibe de forma automática permisos para leer o firmar contenido clínico. Cree roles propios en Configuración → Usuarios → Roles, sin cambiar los privilegios de roles existentes:

| Perfil orientativo | Permisos nuevos | Permisos ERP adicionales cuando proceda |
|---|---|---|
| Recepción | `clinicas` | Ninguno para pacientes/agenda |
| Profesional | `clinicas`, `clinicas_atender` | Ninguno para sus episodios |
| Supervisor clínico | Los anteriores y `clinicas_supervisar` | Ninguno |
| Salud mental | Atención y `clinicas_mental` | Supervisión solo si se autoriza |
| Laboratorio | `clinicas`, `clinicas_laboratorio` | Credencial profesional para firmar resultados |
| Farmacia | `clinicas`, `clinicas_farmacia` | `inventario`; credencial profesional vigente para dispensar |
| Caja / facturación | `clinicas` | `facturar` |
| Auditor | `clinicas`, `clinicas_auditar` | Ninguno |
| Configurador | `clinicas`, `clinicas_configurar` | Administración de usuarios según los permisos ya existentes |

Vincule cada profesional a un usuario MIEMBRO de la empresa y configure colegiado, vencimiento y horario. La comprobación de colegiación es administrativa; no consulta un colegio profesional. Un configurador crea consultorios/equipos y su sucursal.

## Flujo de aceptación

- Crear paciente provisional y otro identificado; verificar que el mismo documento normalizado no se duplique. Registrar consentimiento bajo custodia de la clínica y, cuando corresponda, representante.
- Confirmar una cita dentro del horario, comprobar rechazo de solapamientos, marcar llegada y abrir la atención. También se puede abrir atención sin cita.
- Firmar SOAP y signos vitales, cerrar la atención y añadir una corrección enlazada. Probar un usuario de recepción: no debe leer las notas.
- Verificar que notas y adjuntos marcados de salud mental solo estén disponibles con el permiso adicional, además de la asignación al episodio.
- Firmar una orden; pasar muestra por recolección, recepción y proceso; firmar resultado y documentar la notificación de un resultado crítico.
- Recibir productos en Compras/Inventario ANTES de identificar sus lotes. Usar una bodega de farmacia dedicada. Prescribir y dispensar parcialmente; comprobar FEFO, vencimiento, bloqueo, inventario y reintento sin duplicación.
- Registrar servicios NO inventariables como cargos. Abrir un borrador en Facturación, emitir con la configuración fiscal actual, comprobar la moneda y registrar el cobro por el flujo ERP. Para USD indique y revise el tipo de cambio del documento; no se crea un borrador USD con tasa 1. La farmacia no genera un cobro automático; no facture un medicamento dispensado por una vía que vuelva a descontar su inventario.
- Eliminar un borrador sin emitir: libera los cargos. Anular/rechazar el documento o acreditarlo totalmente: permite volver a facturar sus cargos. Una nota de crédito parcial no libera todo el servicio.
- Probar permisos sin MFA, empresa ajena, sucursal fija, módulo oculto, plan vencido y `FE_MODO_LOCAL=1`. RRHH, POS, hotel y sincronización deben conservar sus flujos existentes.
- Descargar expediente autorizado en JSON/PDF, descargar adjuntos y consultar auditoría. Crear un respaldo global y comprobar restauración en la misma empresa y con las mismas claves.

## Inventario y operación local

La identificación de lotes asigna existencias YA recibidas; no crea una segunda entrada. Una salida ERP en la nube no puede consumir la cantidad identificada en lotes por fuera del flujo de farmacia. Para corregir cantidades identificadas use el ajuste de lotes con motivo y después, si corresponde, el ajuste físico del ERP. La corrección de identificación no crea movimientos físicos por sí sola.

El expediente y los adjuntos NO se sincronizan a Windows; el módulo rechaza su uso en modo local. Los movimientos físicos y documentos de servicios utilizan la sincronización que ya tiene el ERP. Esta entrega no adapta las terminales offline para operar lotes: excluya las bodegas/productos de farmacia de ventas offline. Si otra terminal consume existencias, la dispensación en la nube verifica el stock real y rechaza lo insuficiente, pero la asignación de lotes requerirá conciliación manual.

## Rollback

1. Desactive Clínicas por empresa y detenga las escrituras durante el cambio. Conserve respaldo global y copia del código actualizado.
2. Restaure los archivos de código originales que fueron reemplazados. Retire únicamente archivos nuevos de este paquete si necesita un árbol idéntico al anterior; nunca retire datos o claves. Reinicie Passenger.
3. Las tablas clínicas pueden permanecer en las bases: 4.22 no las usa y no necesita borrarlas. **No elimine tablas clínicas para revertir código.**
4. Si ya hubo atención, dispensación o facturación, conserve el historial y los movimientos. Volver al código anterior no revierte actos clínicos ni dinero/inventario. Una restauración de datos requiere procedimiento separado y puede perder operaciones posteriores; no se ejecuta como parte de este paquete.
5. La restauración de expedientes en otra empresa o servidor con claves diferentes no está implementada. La renumeración de un respaldo con pacientes clínicos se rechaza para evitar datos ilegibles o atribuidos a otra empresa. Para recuperación use respaldo global, empresa original y claves originales.

No se modificaron credenciales, cron, cPanel ni servicios externos durante la preparación del paquete.
