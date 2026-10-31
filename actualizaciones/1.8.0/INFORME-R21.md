# Informe QA · FISITAAP web 1.8.0-R21

Fecha: 30 de octubre de 2026 (Costa Rica). Corrección acumulativa sobre R20; conserva Windows1.8.2 y Android1.8.0.

| Aviso del usuario | Corrección |
|---|---|
| Combo mostrado a ₡5.000 y agregado a ₡0 | Campo promocional legado 0/0.00 ya no anula el precio normal. Creación de combo inicializa sin promoción oculta. Tarjeta y línea usan una regla común, con precios/asignaciones de sucursal. Se conservan promociones explícitas, IVA y extras. |
| «No se recibió un comprobante» después de enviar comanda | La cola de comanda del servidor devuelve el pedido; el interceptor antiguo ya no exige un recibo de esa respuesta ni intenta imprimirlo de nuevo. Se comprobó que se guarda una vez y que la precuenta conserva su impresión nativa. |
| Desktop no ofrece justificación | Ventana HTML con textarea, mínimo 8 caracteres, Volver y Confirmar. Formularios heredados también reciben una ventana compatible con Electron. Motivo auditado, permisos verificados y restitución única de existencias. |

Se reproduce el flujo normal de crear en Combos con componentes y precio5000; se cubre además el campo sin usar en0/0.00. No se accedió a la base de producción para atribuirle una configuración concreta. Las líneas históricas se preservan; las erróneas anteriores requieren quitar/agregar de nuevo o cancelar justificadamente según su estado.

Validación: `QA-PRECIOS-R21.json` (8 grupos, HTTP/MariaDB y calculador local Node real), `QA-PANTALLAS-R21.json` (7 recorridos con puente instalado simulado, prompt/confirm bloqueados y 3 tamaños), `QA-COBRO-R21.json` (12 grupos de regresión de cobros/turnos/salidas), `QA-ACTUALIZADOR-R21.json` (R20→R21 en base separada), y `QA-SINTAXIS-R21.json` (ZIP exacto, 156 archivos, hashes, PHP/JS y permisos644/755).

No se modificaron precios de ventas cobradas, contraseñas ni fondos. No se hicieron impresiones físicas, cobros reales, llamadas de IA ni mensajes externos. No se reinstalaron binarios Windows/Android ni se desplegó en el hosting público. Para el modo local sin internet se debe renovar el catálogo por sincronización; sus operaciones pendientes conservan sus precios originales.
