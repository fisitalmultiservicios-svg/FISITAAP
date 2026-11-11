# FISITAAP ERP 4.22 — paquete Clínicas 1.0-rc1

Entrega preliminar del módulo Clínicas sobre la versión oficial 4.22. Esta rama contiene el ZIP diferencial y las guías de esta entrega; la aplicación se actualiza siguiendo las instrucciones incluidas. No es una versión 4.23 publicada ni una instalación en cPanel.

- Paquete: [FISITAAP_ERP_4.22_Clinicas_1.0-rc1_actualizacion.zip](FISITAAP_ERP_4.22_Clinicas_1.0-rc1_actualizacion.zip)
- [Instalación, activación y rollback](ACTUALIZAR_CLINICAS.md)
- [Alcance y matriz de los 51 requisitos](CLINICAS_ALCANCE.md)
- [Pruebas, resultados y límites](CLINICAS_VALIDACION.md)
- [Manifiesto de archivos](MANIFIESTO_CLINICAS.json)
- [SHA-256 del ZIP](SHA256SUMS.txt)

El paquete no incluye datos, bases, claves ni credenciales. Debe probarse primero sobre una copia aislada de la instalación 4.22, conservando datos y claves existentes. Incluye funcionalidades operativas y pendientes expresos del blueprint; no certifica integraciones sanitarias externas ni validez regulatoria.

Validación registrada: 1.226 pruebas de regresión aprobadas, 38 pruebas focalizadas finales, recorrido de navegador CRC/USD y ensayo de actualización mediante Passenger con datos ficticios. Las corridas se solapan; no se suman como pruebas distintas.

La fuente base es el ZIP oficial 4.22 con SHA-256 `a48406d04df00351d690bc4568e611b17449ff8a5035740cc0513fc0bacd20ad`. Esta entrega no mezcla fuentes ni arquitecturas históricas del repositorio.
