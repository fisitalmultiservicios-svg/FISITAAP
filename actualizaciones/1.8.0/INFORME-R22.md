# Informe QA · FISITAAP web 1.8.0-R22 / Windows 1.8.3

Fecha: 31 de octubre de 2026 (Costa Rica). Corrección acumulativa sobre R21.

| Solicitud | Resultado |
|---|---|
| Venta rápida encuadrada arriba en desktop y web | Marco de altura disponible, cabecera/caja compactas, factura mayor a la izquierda, listas internas independientes y total/Cobrar visibles. Categorías en una fila horizontal. Conserva el desplazamiento al agregar. |
| Mesas y cobro se pierden o no desplazan bien | La cuenta desktop comparte el marco; celular conserva atención continua. El cobro separa cabecera y confirmaciones del cuerpo desplazable. |
| Pantalla completa se desactiva al navegar | Windows 1.8.3 controla la ventana nativa, conserva la preferencia y permite salir con botón/F11. El navegador conserva su modo entre páginas con F11, según sus permisos. |
| Direcciones, zonas y Maps solo en Delivery | Bloque oculto/deshabilitado en retiro/coordinado; no se envían esos campos. Conserva los datos al volver, admite direcciones del teléfono y descarta datos antiguos fuera de Delivery en el servidor. |

Validación: `QA-PANTALLAS-R22.json` (8 recorridos, 22 casos de encuadre), `QA-MESAS-R22.json` (5 tamaños del plano gráfico), `QA-LOCAL-R22.json` (5 grupos con Node real y pantallas locales), `QA-DELIVERY-R22.json` (6 grupos de interfaz/HTTP), `QA-REGRESION-R22.json` (7 flujos de precio/comanda/precuenta/justificación), `QA-NATIVO-R22.json` (57 pruebas Node y payload del instalador), `QA-ACTUALIZADOR-R22.json` (R21→R22 en base separada) y `QA-SINTAXIS-R22.json` (ZIP final, 158 archivos y hashes/permisos).

Se usaron Chromium, PHP/MariaDB y datos ficticios; el puente instalado y la impresora se simularon. No hubo impresiones físicas, cobros reales, WhatsApp, IA externa ni despliegue en cPanel. No se probaron un escritorio Windows o dispositivo Android reales. El instalador Windows contiene los fuentes revisados; APK 1.8.0 se conserva. No cambian ventas anteriores, contraseñas, fondos ni la página comercial de presentación.
