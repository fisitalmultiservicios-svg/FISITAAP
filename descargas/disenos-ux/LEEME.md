# FISITAAP · Tres propuestas de interfaz

27 de octubre de 2026

Son tres prototipos HTML interactivos para comparar el diseño y los recorridos de FISITAAP antes de elegir una estética. Incluyen panel del negocio, panel maestro, catálogo con acceso de cliente y espacio del motorizado. **La web oficial de presentación y el código de la instalación actual permanecen intactos.**

| Opción | Aspecto | Composición |
| --- | --- | --- |
| **01 · Línea** | Blanco, gris suave y petróleo; tipografía limpia, bordes finos. | Resumen con gráfico amplio y pedidos a un lado; lectura clara y tranquila. |
| **02 · Estudio** | Marfil y tonos tierra; títulos con tipografía editorial. | Indicadores integrados, gráfico y actividad en paralelo, pedidos en una franja independiente. |
| **03 · Noche** | Grafito y acentos salvia; contraste suave. | Panel más compacto, indicadores integrados y gráfico de barras. |

Las tres tienen las mismas funciones de exploración. El menú lateral se abre con las tres rayas y se esconde al elegir una pantalla. Venta rápida usa el espacio disponible; en celular puedes alternar entre Productos y Ticket. Mesas, formularios, pedidos y catálogo se adaptan a computadora, tableta y celular.

## Abrir las propuestas

1. Descomprime todo el ZIP en una carpeta.
2. Abre **index.html** en un navegador y elige una opción.
3. Cambia entre **Negocio**, **Maestro**, **Catálogo** y **Moto** desde la barra superior.

También puedes abrir directamente `01-linea.html`, `02-estudio.html` o `03-noche.html`. Cada archivo incluye sus estilos, lógica, imágenes e iconos: no descarga bibliotecas, fuentes ni imágenes de otros servidores.

Si tu navegador corporativo bloquea archivos HTML locales, sirve únicamente esta carpeta con `python -m http.server 8080 --bind 127.0.0.1` y abre `http://127.0.0.1:8080/index.html`. Las comprobaciones automatizadas se realizaron de esta forma porque el navegador administrado del entorno bloqueaba `file://`.

## Recorrido recomendado

1. En **Negocio → Venta rápida**, agrega un café, cambia su cantidad y cobra. El comprobante, el inventario, la cuenta de dinero y el resumen reflejan la venta. Los productos por unidad rechazan fracciones; el café a granel admite cantidades decimales. Puedes probar efectivo, SINPE, tarjeta o crédito a un cliente con límite disponible.
2. En **Productos**, crea o edita un artículo. Prueba importar un CSV o descargar la plantilla que puedes completar con Excel. El importador valida todos los renglones antes de guardar y rechaza códigos SKU duplicados.
3. En **Catálogo**, agrega un producto y abre el carrito. Usa un nombre y teléfono ficticios de ocho dígitos. El comprobante aparece y es obligatorio si eliges SINPE. En Express puedes guardar una dirección y enlace de Google Maps. «Usar mi ubicación» depende del permiso de ubicación y de las capacidades del navegador.
4. Confirma el pedido. El comprador queda identificado en esta demostración y puede consultar **Mis pedidos**, su PIN y los eventos del pedido. También puedes entrar como cliente de ejemplo con teléfono `88881111` y contraseña `88881111`.
5. Vuelve a **Negocio → Pedidos**. Acepta el pedido para preparar su comanda, márcalo preparado y asígnalo directamente a un motorizado o déjalo disponible para los afiliados.
6. En **Moto**, acepta el Express, marca la recogida y confirma la entrega con el PIN del comprador. Un PIN incorrecto impide completar la entrega. El pedido y los saldos cambian en todos los modos de esa propuesta.
7. En **Negocio → Afiliados**, realiza el corte del motorizado y elige la cuenta de salida. Consulta después sus ganancias en Moto y los movimientos en Cuentas de dinero.
8. Prueba **Mesas**, abrir/cerrar turnos, abonos a cuentas por cobrar, recibir una compra, incidencias y la bitácora. En Reportes el período filtra tanto el detalle como los totales y la exportación.
9. En **Diseño del catálogo**, cambia el color primario, los textos o las tres imágenes del slider y su intervalo. Puedes subir imágenes: el navegador las reduce antes de guardarlas. El color del comercio se aplica a su catálogo y conserva la paleta del panel administrativo.
10. En **Maestro**, crea un negocio, edita planes y cambia módulos. Vuelve a Negocio para comprobar el cambio en el menú. El selector de rol permite comparar Administración y Vendedor.

## Qué funciona dentro del prototipo

- Navegación, búsqueda global, formularios con validación, altas, cambios y eliminación confirmada de registros de ejemplo.
- Venta rápida, stock, recibos, cobros, carrito, mesas, turnos, crédito, cuentas de dinero, compras y abonos.
- Pedidos, comprobantes SINPE, aceptación, preparación, asignación, entrega con PIN y trazabilidad.
- Saldos y cortes de afiliados, incidencias con justificación e historial.
- Clientes, categorías, grupos de opcionales, administración de combos y promociones, equipo, sucursales, planes, módulos y dispositivos de ejemplo.
- Configuración visual del catálogo, tres imágenes con intervalo, imágenes comprimidas y exportaciones CSV.
- FISIChat con respuestas locales según el contexto de negocio, maestro, comprador o motorizado. Puede consultar métricas del ejemplo y orientar por las pantallas.

Los datos son ficticios y se guardan en **localStorage**. Cada diseño tiene su propio espacio: `fisitaap-ux-20261027-linea`, `fisitaap-ux-20261027-estudio` y `fisitaap-ux-20261027-noche`. Recargar conserva los cambios; **Menú → Restablecer esta demostración** borra solamente los datos de esa opción. La persistencia depende de que el navegador permita almacenamiento local. No uses datos personales reales.

## Alcance de la demostración

Estos HTML permiten evaluar y operar los recorridos con datos locales. No constituyen una actualización instalada del servidor ni una copia del backend PHP. No envían pedidos reales, mensajes de WhatsApp, pagos, correos ni peticiones a inteligencia artificial. FISIChat usa reglas locales; la comprobación de actualizaciones y la vinculación de equipos representan sus recorridos. La impresión abre el diálogo del navegador, sin integración con impresoras de cocina.

Los gráficos de evolución y las comparaciones porcentuales usan series ilustrativas. Los totales, listas, ventas y movimientos se calculan a partir del estado local. El selector de sucursal del resumen filtra sus indicadores y pedidos; no reproduce almacenes separados por sucursal. Combos y promociones permiten explorar su administración; no implementan todas las reglas comerciales del sistema instalado. El canje de puntos muestra y registra el recorrido del cupón, sin un motor de promociones conectado al cobro.

Los cambios se comparten entre los cuatro modos del mismo HTML. No hay sincronización de datos entre equipos, navegadores o pestañas: eso requiere el servidor real. El acceso de cliente por teléfono representa el flujo solicitado para esta exploración y no está conectado a cuentas reales.

## Verificación y archivos

Se verificaron las tres propuestas en Chromium mediante un servidor HTTP local: venta y stock, recibo, validación de unidades, productos e importación, roles y módulos, pedido SINPE, sesión de comprador, PIN, aceptación/preparación/asignación/entrega, corte de motorizado, personalización y persistencia. También se revisaron **108 combinaciones de pantalla** en tamaños de escritorio, tableta y móvil, sin errores de JavaScript ni solicitudes externas durante esos recorridos. Esto no sustituye una prueba física en iPhone, Android o Windows instalado.

`QA.json` contiene el detalle de comprobaciones; `SHA256SUMS.txt`, las huellas de los archivos. Las imágenes `vista-*.png` y `movil-*.png` muestran las tres estéticas con el mismo conjunto inicial de datos.

El código editable de los prototipos está en `disenos/ux-2026-10-27/` del repositorio. `python disenos/ux-2026-10-27/build.py` reconstruye los HTML independientes y el ZIP descargable.
