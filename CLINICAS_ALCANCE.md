# Clínicas 1.0-rc1 sobre ERP 4.22: alcance verificable

Inicio nuevo autorizado el 11 de noviembre de 2026. Se comprobó que las carpetas del trabajo anterior no estaban conservadas; el propietario indicó iniciar desde 4.22. Se usaron únicamente el ZIP oficial, AGENTS.md, el informe técnico adjunto y el PDF clínico como referencia funcional. No se portó arquitectura PHP, módulos, estilos ni requisitos del FISITAAP anterior.

Esta entrega es software ejecutable con pruebas locales, no una instalación en cPanel. Tampoco representa toda la plataforma SaaS/HIS investigada en el PDF. La matriz contiene sus 51 identificadores originales; no se atribuye cobertura a las 60 agrupaciones y 12 mejoras de una propuesta anterior que no estuvo disponible en este inicio nuevo.

## Qué se puede operar

Pacientes y consentimiento administrativo; agenda semanal por profesional con un recurso opcional; recepción; episodios asignados; SOAP, alergias, problemas, signos, notas, registros dentales básicos y notas separadas de salud mental; recetas internas, correcciones y anulación; órdenes, estados de muestra y resultados; documentos cifrados; identificación de lotes sobre inventario recibido y dispensación FEFO; cargos de servicios y su paso a borradores del ERP; exportación autorizada JSON/PDF, resumen diario y auditoría.

Los mismos controles se aplican en API aunque se invoque sin la pantalla. Una empresa utiliza su base física y su clave ya existente. Los datos personales y clínicos están cifrados dentro de SQLite; las referencias operativas (identificadores de paciente/episodio, fechas, estados y profesionales) no lo están. Un respaldo global conserva el conjunto y las claves según el mecanismo existente.

El acceso al expediente depende de la asignación al profesional, o de un permiso explícito de supervisión, además de empresa, sucursal fija y MFA. Un profesional con permiso de atender puede abrir un nuevo episodio para establecer una atención y queda auditado. No se implementa un motor completo de finalidad, guardias, consentimiento granular o acceso de emergencia.

La firma es atribución al usuario autenticado, con fecha y contenido conservados. No es firma digital certificada de Costa Rica, receta electrónica nacional ni comprobación de habilitación ante colegios profesionales.

## Matriz de los 51 requisitos del PDF

**Operativo** indica un flujo implementado y probado dentro del alcance descrito. **Parcial** identifica los límites de una función que sí tiene código. **ERP existente** identifica reutilización de 4.22, sin presentar esa capacidad como un desarrollo nuevo. **Pendiente** indica ausencia de implementación o integración; no se simula su funcionamiento.

| ID | Requisito | Estado en este paquete y límite |
|---|---|---|
| PAT-01 | Índice de pacientes | Operativo: registro cifrado, búsqueda, provisional/identificado, activo/inactivo y versión de ficha. |
| PAT-02 | Duplicados/fusión | Parcial: documento normalizado único por empresa; sin detección difusa ni fusión/reversión. |
| PAT-03 | Representantes/tutores | Parcial: dato del representante; sin verificación jurídica ni autorizaciones por representante. |
| SCH-01 | Agenda multiprofesional | Operativo: horario semanal, duración, conflicto de profesional/paciente y credencial vigente para la cita. Sin excepciones recurrentes/feriados/buffers. |
| SCH-02 | Agenda por sala/equipo | Parcial: un recurso exclusivo por cita, sucursal e inactivación; sin asignación simultánea de varios equipos. |
| SCH-03 | Autoagenda | Pendiente: no existe ruta pública para reservar. |
| SCH-04 | Lista de espera | Pendiente. |
| SCH-05 | Sobrecupos gobernados | Pendiente: los solapamientos se rechazan; no hay excepción de sobrecupo. |
| ADM-01 | Check-in/check-out | Parcial: llegada y cierre de episodio/cita; no hay checkout administrativo ni cierre financiero obligatorio. |
| ADM-02 | Cola clínica | Operativo: agenda diaria por estados, llegadas y atendidas. Sin reglas automáticas de triage. |
| EHR-01 | Expediente longitudinal | Parcial: episodios y entradas autorizadas por paciente; sin vista longitudinal compartida fuera de asignación/supervisión. |
| EHR-02 | SOAP/formularios | Operativo: SOAP con cuatro campos exigidos y tipos de registro definidos; sin diseñador de formularios. |
| EHR-03 | Alergias/problemas | Parcial: registro longitudinal firmado; sin listas activas reconciliadas ni alertas clínicas automáticas. |
| EHR-04 | Signos vitales | Operativo: unidades indicadas y validación de formato/rangos de entrada. No interpreta ni diagnostica resultados. |
| EHR-05 | Consentimientos | Parcial: estado administrativo, representante, historial y bloqueo de firma sin aceptación. La evidencia y validez del consentimiento quedan bajo custodia de la clínica; no hay firma del paciente ni consentimientos por procedimiento. |
| EHR-06 | Firma/versionado | Parcial: atribución autenticada, registros inmutables, correcciones enlazadas y versión de ficha; sin firma certificada ni sellado externo. |
| EHR-07 | Documentos/anexos | Operativo: PDF/JPG/PNG reconocidos por contenido, máximo 5 MB, cifrado, cuota por empresa y descarga autenticada; etiqueta adicional para salud mental. No incluye DICOM ni visor diagnóstico. |
| MED-01 | Prescripción | Parcial: receta interna, revisión humana declarada, vencimiento, dispensación parcial, correcciones y anulación; sin formularios oficiales ni medicamentos controlados regulados. |
| MED-02 | Interacciones/alergias | Pendiente como motor farmacológico: se exige revisión explícita del profesional, pero no se consulta una base de interacciones. |
| MED-03 | Receta electrónica nacional | Pendiente: depende de integración/autorizaciones por país. |
| LAB-01 | Órdenes | Operativo: órdenes de laboratorio, imagen y procedimientos atribuidas al profesional. |
| LAB-02 | Muestras | Parcial: recolectada/recibida/rechazada/en proceso/completada y resultado crítico con notificación registrada; sin cadena de custodia, analitos ni códigos de barras. |
| LAB-03 | LIS | Pendiente: no hay conector LIS ni importación automatizada de resultados. |
| IMG-01 | Órdenes de imagen | Operativo: orden interna y resultado escrito. No realiza adquisición ni interpretación de imágenes. |
| IMG-02 | PACS/DICOM | Pendiente. |
| DEN-01 | Odontograma | Parcial: hallazgos por diente FDI permanente/temporal; no hay odontograma gráfico ni codificación completa de superficies. |
| DEN-02 | Periodontograma | Parcial: observaciones por diente; sin medición estructurada de seis sitios, gráficas ni cálculos periodontales. |
| DEN-03 | Tratamiento por fases | Parcial: objetivo/fases/evolución como entrada firmada; sin motor de presupuestos/aceptación por fase. |
| PSY-01 | Notas protegidas | Operativo: permiso específico adicional, relación de atención, cifrado y etiqueta de adjuntos. Sin plantillas/escalas psicométricas validadas. |
| PHA-01 | Dispensación | Parcial: receta interna, pendiente por cadena de correcciones, FEFO y salida atómica ERP. Sin regencia electrónica, regulación de controlados, devolución o cobro automático de medicamentos. |
| INV-01 | Lote/caducidad | Parcial: identificación de stock ya recibido, estado, vencimiento, conciliación motivada y trazabilidad a receta/movimiento; sin compras por lote, devoluciones, recalls o destrucción completa. |
| INV-02 | FEFO | Operativo: solo lotes disponibles no vencidos; menor vencimiento primero, incluso en dispensación concurrente. El costeo sigue siendo el método existente del ERP. |
| INV-03 | Cadena de frío | Pendiente: no se reciben sensores ni se validan temperaturas de transporte. |
| INV-04 | Serialización | Pendiente: sin GS1 ni trazabilidad individual por unidad. |
| FIN-01 | Caja/pagos | ERP existente: se mantienen cobros, bancos y caja. El módulo no crea una caja paralela. |
| FIN-02 | Facturación | ERP existente + cargos clínicos: reserva de servicios en borradores, emisión y liberación por eliminación/anulación/rechazo/acreditación total. |
| FIN-03 | Factura fiscal | ERP existente: FE v4.4 e internos según configuración. Pruebas locales no equivalen a aceptación real de Hacienda. |
| INS-01 | Seguros/convenios | Pendiente: sin elegibilidad, tarifas de aseguradoras, preautorizaciones ni reclamos. |
| ACC-01 | Contabilidad mayor | ERP existente: no se duplica el libro mayor ni se modifican sus reglas. |
| CRM-01 | Portal del paciente | Pendiente: no se publican expedientes ni accesos públicos de pacientes. |
| CRM-02 | Notificaciones | Pendiente para citas/seguimientos clínicos. Los correos financieros existentes del ERP siguen su flujo. |
| TEL-01 | Telemedicina | Pendiente: sin videollamada, sala ni consentimiento de teleconsulta. |
| HR-01 | Profesionales/credenciales | Parcial: usuario miembro, especialidad, colegiado, vigencia y bloqueo de firma/agenda; sin consulta a colegios ni integración nueva con planillas. RRHH 4.22 se conserva. |
| AST-01 | Activos/mantenimiento | ERP existente para activos; los recursos clínicos básicos no agregan esterilización, autoclaves o mantenimiento biomédico. |
| QMS-01 | Incidentes/calidad | Pendiente para el dominio clínico. |
| INT-01 | API REST/FHIR | Parcial: API REST autenticada propia del ERP; no es un servidor FHIR y no declara conformidad HL7. |
| SEC-01 | RBAC/ABAC | Parcial: permisos, empresa física, asignación, sucursal y salud mental; no hay motor ABAC completo por finalidad/guardias. |
| SEC-02 | Auditoría inmutable | Parcial: append-only en API, triggers SQL y cadena HMAC; un administrador del almacenamiento puede retirar las protecciones. No hay custodia externa WORM. |
| SEC-03 | MFA/SSO | Parcial: MFA existente obligatorio y sin renuncia en Clínicas; no se añade SSO/IdP. |
| BI-01 | Indicadores | Parcial: citas, llegadas, atendidas, cancelaciones y ausencias diarias. Los reportes financieros existentes permanecen; sin analítica clínica completa. |
| BI-02 | Data warehouse | Pendiente. |

## Límites de operación que deben comprobarse antes del uso clínico

- **Habilitación y contenido clínico:** permisos y fechas son controles de software; la clínica debe validar usuarios, colegiación, formularios, uso de consentimiento, prescripción y protocolos. El programa no proporciona consejo clínico ni validación automática de dosis.
- **Farmacia:** requiere acceso a todas las sucursales en esta entrega; los usuarios con sucursal fija no dispensan ni administran lotes. Use bodega dedicada y operación en nube. Las terminales Windows no reciben lotes ni expedientes. No existe dispensación offline.
- **Cobro de medicamentos:** la salida de dispensación está implementada, pero no su factura automática. El flujo de cargos admite servicios no inventariables para evitar una segunda salida de medicamentos. No opere venta minorista de farmacia como un flujo end-to-end completo sin implementar y aceptar su cobro/reverso.
- **Borradores de servicios:** no se permiten cambios de líneas, precio, moneda o cliente en un borrador clínico reservado. Para modificar cargos, elimine el borrador, anule los cargos incorrectos con motivo y cree los correctos. Esto evita dar por facturado un cargo que fue quitado del formulario.
- **Moneda:** cada cargo conserva moneda y precio del catálogo al crearse. Se generan borradores separados para CRC y USD; no se suman monedas. El borrador USD exige un tipo de cambio explícito mayor que 1, visible y conservado al abrir Facturación. Las exoneraciones e impuestos los verifica el ERP al emitir.
- **Seguros, portal, autoagenda, FHIR, LIS, PACS, telemedicina y receta nacional:** no están conectados. Ningún indicador de esta entrega debe interpretarse como una integración externa validada.
- **Búsqueda:** devuelve hasta 100 pacientes por página; el servidor examina como máximo 5.000 IDs desde el cursor para una búsqueda cifrada. Es una limitación para catálogos grandes; no se instala un índice de nombres en claro.
- **Retención:** no hay borrado clínico desde la API. Las correcciones/anulaciones conservan historia. Políticas de retención, custodia externa y exportación completa con todos los binarios requieren desarrollo/validación adicional.
- **Recuperación:** conservar empresa y claves originales. No se soporta renumerar o trasladar un respaldo clínico a otra empresa/clave. La protección añadida rechaza esa operación antes de modificar el archivo.

## Impacto y conservación de 4.22

No se cambia el tipo de negocio de ninguna empresa, los planes, precios, roles asignados, contraseñas, claves, datos RRHH ni configuración de Hacienda. Se añaden permisos que los roles propios pueden recibir explícitamente. Clínicas es una función opcional por empresa y su permiso puede ocultarse mediante los controles existentes.

La única restricción añadida al inventario general afecta stock que se haya identificado expresamente en lotes clínicos. El stock no identificado conserva el flujo anterior. Las salidas de lote y movimientos físicos se confirman en una sola transacción. Los cargos se enlazan/liberan en el mismo flujo de borradores/documentos existente; los eventos clínicos de cobro no incluyen diagnósticos.

Para pruebas y resultados reales consulte `docs/CLINICAS_VALIDACION.md`, incluido al cerrar el paquete. Los pasos de instalación y rollback están en `ACTUALIZAR_CLINICAS.md`.
