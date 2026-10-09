# Mudanza selectiva a cPanel · 1.7.9-R3 revisada

[Descargar sistema completo](FISITAAP-1.7.9-R3-NUEVO-CPANEL.zip) · [Guía paso a paso](GUIA-NUEVO-CPANEL.md)

Conserva La Ventanita y los dos demos desde un respaldo privado importado en la base nueva. Los demos funcionan en copias por visitante y se reinician al volver a entrar. El ZIP no incluye datos privados ni config.php. Ahora incluye optimización automática WebP, miniaturas del catálogo y un asistente por lotes para fotos anteriores.

[Comprobador opcional para el hosting anterior](FISITAAP-COMPROBAR-MUDANZA.zip) · [Sumas SHA256](SHA256SUMS.txt)

Si ya subiste el sistema, descarga [la corrección acumulada](FISITAAP-CORREGIR-MUDANZA-DEMOS.zip) y extráela en la raíz del sitio del **cPanel nuevo**, permitiendo reemplazar los cinco archivos incluidos. Corrige la relación antigua `driver_branches`, la apertura de demos en bases sin campos antiguos de impresión en `tenants`, el regreso «Elegir otra demo» y la entrada directa desde la portada. También reconoce los enlaces anteriores al selector y muestra el cupón correspondiente a cada tipo de demo. El comprobador mantiene obligatoria la configuración actual de impresoras en `branches`.

Si la preparación se detuvo en `driver_branches`, vuelve a abrir preparar-mudanza.php y confirma la preparación. Si ya preparaste la copia, vuelve a abrir comprobar-mudanza.php y prueba los demos, incluyendo los dos botones «Abrir demo» de la portada, «Elegir otra demo» y la entrada posterior. Cada nueva entrada comienza con una copia limpia. No necesitas importar la base otra vez ni reinstalar el sistema. El paquete completo también incorpora estas correcciones.

Instala únicamente en el servidor nuevo y sigue la guía; no ejecutes la limpieza en el hosting anterior. Los clientes Windows y Android siguen siendo [1.7.9](../1.7.9/README.md).

Validación: [instalación e importación](QA-MUDANZA.json), [filtrado y demos](QA-DEMOS.json), [pestañas en Chromium](QA-NAVEGADOR.json), [imágenes](QA-IMAGENES.json), [revisión adicional](QA-REVISION.json), [relaciones antiguas de repartidores](QA-DRIVER-BRANCHES.json), [compatibilidad de impresión y demos](QA-COMPATIBILIDAD-IMPRESION.json). Estas pruebas usan datos ficticios; no sustituyen la comparación de tu respaldo real ni las pruebas de impresión física.

Para reconstruir los ZIP desde estos archivos: `python qa/build_migration179.py` desde la raíz del repositorio. El script rechaza configuración privada y fotos de negocios dentro del paquete público.
