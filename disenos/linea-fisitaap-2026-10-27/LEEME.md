# Línea · FISITAAP

Propuesta interactiva del 27 de octubre de 2026. Combina la claridad de **Línea** con la identidad actual de FISITAAP: azul profundo en títulos y elementos estructurales, verde en las acciones principales, marca visible y tarjetas blancas con bordes suaves.

## Abrir y comparar

Descomprime el ZIP y abre **index.html**. Todo está incluido en ese archivo: estilos, lógica, imágenes e iconos. No necesitas instalar dependencias. El menú de tres rayas permite recorrer las pantallas; los botones de la barra superior cambian entre Negocio, Maestro, Catálogo y Moto.

Puedes abrir `index.html#pos` para empezar en Venta rápida o `index.html#tables` para ir directamente a Mesas. Si un navegador administrado impide abrir archivos locales, sirve esta carpeta con `python -m http.server 8080 --bind 127.0.0.1` y abre `http://127.0.0.1:8080/`.

## Cambios de esta mezcla

- **Facturación a la izquierda y más grande:** ocupa el 60 % del espacio de trabajo en computadora y tableta. Incluye cliente, productos, cantidades, precio, importe y total. La carga rápida queda a la derecha con el 40 %: búsqueda, categorías y productos. El cobro permanece visible mientras la lista se desplaza dentro de su panel.
- **Celular:** la pestaña Facturación aparece primero; puedes alternar con Productos para aprovechar toda la pantalla. Al abrir Venta rápida se muestra la factura. Las categorías y cantidades conservan sus controles táctiles.
- **Mesas en vista gráfica:** plano del salón y la terraza con mesas, sillas, entrada, ventanas y vegetación. Los asientos distinguen las mesas disponibles de las ocupadas. Seleccionar una mesa abre su detalle y permite continuar o cobrar su cuenta. Puedes filtrar por salón y acercar o ajustar el plano. La selección también admite teclado con Tab y Enter.
- **Identidad actual:** se recuperan el logo, los azules profundos, el verde y el estilo familiar de FISIChat, con la distribución ligera y los espacios de Línea.
- **Resto del sistema:** mantiene el panel de negocio, maestro, catálogo, comprador y motorizado, así como los recorridos de ventas, pedidos, productos, entrega con PIN y cuentas de la primera exploración.

## Recorridos para probar

1. **Venta rápida:** abre el menú, elige Venta rápida y agrega productos desde la columna derecha. Cambia cantidades y cobra desde la columna izquierda. La venta modifica el stock, el saldo y los reportes de ejemplo. En celular alterna Facturación y Productos.
2. **Mesas:** selecciona una mesa libre en el plano y pulsa Abrir cuenta. Agrega productos. Vuelve a Mesas para consultar su consumo, continúa la cuenta o cóbrala para liberar la mesa.
3. **Pedido completo:** cambia a Catálogo, agrega un producto y crea un pedido con nombre y teléfono ficticios. El comprobante es obligatorio al elegir SINPE. En Negocio acepta, prepara y asigna el pedido; en Moto recógelo y confirma la entrega con el PIN mostrado en Mis pedidos del comprador.
4. **Maestro:** crea un negocio de ejemplo, modifica módulos y vuelve al negocio para ver los cambios de navegación.

La cuenta de comprador de ejemplo usa teléfono `88881111` y contraseña `88881111`. El asistente responde con reglas locales según el modo y puede consultar los datos de esta demostración.

## Alcance

Es un **prototipo funcional de UX con datos ficticios guardados en el navegador**. Su almacenamiento es independiente de las tres propuestas anteriores (`fisitaap-ux-20261027-fusion`). Puedes reiniciarlo desde Menú → Restablecer esta demostración. No uses datos personales reales.

No está conectado al servidor, pagos, WhatsApp, inteligencia artificial, impresoras de cocina ni equipos reales. La impresión usa el diálogo del navegador. La sincronización entre dispositivos y los servicios externos requieren la aplicación instalada. Los gráficos de evolución son ilustrativos; las ventas, importes y saldos de los recorridos se calculan con el estado local. La administración de combos y promociones, las actualizaciones y algunos ajustes representan sus recorridos sin reproducir todo el backend.

**La instalación actual, la web oficial de presentación y los tres diseños originales permanecen intactos.** Esta propuesta permite evaluar la mezcla antes de decidir su aplicación al sistema real.

## Verificación

`QA.json` registra los recorridos comprobados en Chromium sobre HTTP local: venta, stock, productos, importación CSV, roles, módulos, pedido, PIN, entrega y corte de motorizado; también la factura a la izquierda, sus proporciones, el cobro dentro de la pantalla, el plano SVG, filtros, selección con ratón/teclado y el recorrido completo de una mesa hasta su cobro. Las vistas se revisaron en tamaños de computadora, tableta y celular. No sustituye pruebas físicas en todos los dispositivos.

El ZIP incluye capturas de resumen, Venta rápida, Mesas, Maestro, Catálogo y vistas móviles. `SHA256SUMS.txt` permite comprobar los archivos.
