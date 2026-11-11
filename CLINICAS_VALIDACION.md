# Validación de Clínicas sobre FISITAAP ERP 4.22

RESULTADO: VALIDADO EN ENTORNO AISLADO

Alcance de esta expresión: comportamiento de software, conservación de una base de pruebas y empaquetado. No significa instalación en cPanel, aceptación de Hacienda, integración sanitaria externa ni validación clínica/regulatoria de los protocolos.

## Estado localizado y base

Las rutas de continuación indicadas originalmente no estaban presentes. El propietario confirmó iniciar el trabajo nuevo a partir de 4.22. Se extrajo el ZIP oficial en una carpeta intacta y se trabajó en otra copia. El archivo oficial contiene 580 archivos y su SHA-256 es `a48406d04df00351d690bc4568e611b17449ff8a5035740cc0513fc0bacd20ad`, idéntico al informe adjunto. No había repositorio Git ni commit para comparar; no se asumió un HEAD remoto ni equivalencia con los archivos instalados en cPanel.

La copia oficial siguió idéntica en los 580 archivos después del trabajo. No se recuperó código PHP ni la implementación anterior no conservada. No se modificó producción ni se accedió a credenciales reales.

## Cambios mínimos realizados y archivos

- `app/clinicas.py`, `app/clinicas_modelos.py`: rutas, esquemas, validaciones, permisos clínicos, cifrado, flujos y 13 tablas por empresa.
- `app/main.py`, `app/db.py`: router, registro del esquema y enlaces de cargos en las operaciones existentes de borradores/documentos.
- `app/auth.py`, `app/planes.py`: permisos adicionales; función opcional por empresa apagada por defecto, sin precios nuevos ni cambios de roles asignados.
- `app/inventario.py`: evita que otra salida en la nube consuma stock identificado en lotes. Sin lotes identificados mantiene las reglas anteriores, incluida la existencia negativa donde el ERP la permita.
- `app/respaldo.py`: rechaza renumerar un respaldo con pacientes clínicos a otra empresa, porque los datos cifrados y el historial requieren conservar empresa y claves.
- `app/static/js/app.js`, `menu.js`, `pages/clinicas/index.js`: acceso y pantallas dentro del frontend Vue actual, sin compilación ni rediseño general.
- `app/static/js/pages/ventas/factura-form.js`: conserva la referencia de origen clínico al emitir un borrador; las facturas normales conservan su flujo.
- `tests/test_clinicas.py`: pruebas nuevas; tres pruebas existentes de planes/permisos se ajustaron para reflejar la activación expresa y la ausencia deliberada de acceso clínico automático de los roles Administrador/Avanzado.
- `tools/construir_clinicas.py`, `tools/verificar_clinicas.py`: paquete diferencial con base oficial comprobada y verificación de archivos antes/después, sin importar la aplicación ni abrir datos persistentes.
- Documentación y `NOVEDADES.txt`: ampliación opcional; el ERP mantiene versión 4.22. El manifiesto contiene la lista exacta de archivos y huellas.

## Pruebas ejecutadas y resultados

Datos de prueba aislados mediante `tests/conftest.py`, con directorios temporales para central, empresas y claves. Los ensayos de navegador/actualización utilizaron empresas ficticias y carpetas separadas; cola, cron y servicios productivos no se habilitaron.

| Validación | Resultado |
|---|---|
| Sintaxis de los archivos Python | 251 archivos, sin errores de AST. |
| Sintaxis JavaScript con Node | 209 archivos, sin errores. |
| Regresión focal inicial de 4.22 intacta | 149 aprobadas. |
| Regresión general del servidor con Clínicas | **1.226 aprobadas, cero fallos**; cuatro advertencias de deprecación heredadas. |
| Regresión de las correcciones de inventario/permisos y Clínicas | 79 aprobadas. |
| Pruebas finales de Clínicas y tipo de cambio | **38 aprobadas, cero fallos**. Cubren el ajuste final para exigir/conservar un tipo de cambio explícito en borradores USD y rechazar fechas/horarios ambiguos. |
| Navegador Chromium/Playwright | Alta, consentimiento, episodio, SOAP, corrección, cierre, PDF, facturas internas CRC y USD desde la pantalla ERP (conservando 510 CRC/USD en el caso de prueba), escritorio/móvil y ausencia de datos clínicos en CacheStorage. Sin errores JavaScript en el recorrido completado. |
| Actualización sobre copia oficial con datos ficticios | Entrada `passenger_wsgi.application` respondió **200 OK**; sesión anterior, claves, membresía, contacto, precio y existencias conservados; 13 tablas clínicas aditivas. |
| Dependencias instaladas | `pip check`: sin incompatibilidades detectadas. |

Las pruebas cubren también: usuario sin permiso/MFA, token general denegado, cambio de empresa, base ajena, credencial vencida, sucursal fija, consentimiento revocado, versión de ficha/cita/lote desactualizada, notas y adjuntos protegidos, correcciones inmutables, muestras y resultados críticos, citas/dispensación concurrentes, receta anulada/corregida, FEFO, stock insuficiente, lotes vencidos/bloqueados, protección frente a salidas por otra vía, anulación de cargo, eliminación/emisión/anulación de borrador, documento interno CRC/USD, rechazo de FE activa sin requisitos fiscales, preservación de claves y exportación PDF/JSON.

Comandos principales, desde una copia de pruebas con dependencias instaladas:

```bash
python -m pytest -q --ignore=tests/test_agente.py --ignore=tests/test_49_local_programa.py
python -m pytest -q tests/test_clinicas.py tests/test_418_tipo_cambio.py
```

La regresión general y la corrida focal final son evidencias complementarias; no se suman como si fueran pruebas distintas. `pypdf` fue una dependencia adicional de pruebas, y Playwright/Chromium se usaron para el navegador. No se agregaron dependencias a `requirements.txt` de producción.

## Límites comprobados de la suite oficial

También se ejecutó la suite extensa sobre 4.22 intacta para distinguir defectos nuevos de archivos ausentes de la distribución. Con `test_agente.py` excluido por faltar `agente/agente.py`, produjo **1.207 aprobadas, 16 omitidas, 6 fallos y 12 errores**. Los seis fallos y doce errores corresponden a `test_49_local_programa.py`, que requiere archivos `local/local.py`, `local/README.md`, `local/requisitos-construccion.txt` y `agente/agente.py` ausentes del ZIP oficial cPanel.

La corrida del servidor excluye esos dos archivos de pruebas por esa causa explícita. No se crearon sustitutos ni se presenta el instalador Windows como validado. Las pruebas de sincronización de servidor y sus controles de 4.22 sí se incluyeron en la regresión general. No se modificó la lógica de sincronización para distribuir expedientes al modo local.

## Entorno y reproducción del paquete

Python 3.12.14. Dependencias de validación: FastAPI 0.162.4, SQLModel 0.0.49, SQLAlchemy 2.1.8, Pydantic 2.14.3, cryptography 50.0.2, a2wsgi 1.10.10, reportlab 5.1.0 y pytest 9.2.0. Se respetaron los rangos de `requirements.txt` 4.22. Esto no comprueba cada combinación de versiones admitida por esos rangos.

```bash
python tools/construir_clinicas.py --base-zip '/ruta/FISITAAP ERP - V4.22.zip'
```

El constructor comprueba la huella de la base, los cambios fuera de su lista, sintaxis Python, las rutas exactas y CRC del ZIP. No usa `--datos`. Se entrega un ZIP diferencial: debe aplicarse sobre 4.22, no sobre una carpeta vacía.

El constructor oficial `tools/build_zip.py` exige un `CHANGELOG.txt` interno que no viaja en el ZIP original. Por eso se añadió un constructor específico para esta actualización, conservando el empaquetador original sin cambios y sin inventar un historial de versiones publicado.

## Riesgos y aspectos no comprobados

- Servidor cPanel concreto, versión de sus dependencias, permisos efectivos del hosting, cron y módulos personalizados instalados allí. Ejecutar preflight y aceptación en una copia antes de actualizar producción.
- Credenciales y operación real de Hacienda, pagos, correo, servicios externos clínicos y terminales Windows. No hubo envíos reales ni se simuló su aceptación.
- Habilitación sanitaria, reglas por especialidad, medicamentos controlados, validez jurídica de consentimientos/firma, nomenclaturas clínicas, escalas y protocolos. El paquete no certifica esos aspectos.
- Carga real, concurrencia entre varios procesos Passenger con cientos de usuarios, volumen hospitalario o respaldo de muchos adjuntos. Las pruebas concurrentes usan solicitudes paralelas contra SQLite, no una prueba de carga del hosting.
- Lotes desde terminales offline, cobro/devolución de farmacia y portabilidad de expedientes a otra empresa o clave. Son límites expresos del alcance; revisar `CLINICAS_ALCANCE.md`.

Despliegue y rollback están en `ACTUALIZAR_CLINICAS.md`. El ZIP no contiene datos persistentes ni instala nada automáticamente.
