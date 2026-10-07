# Notas 1.7.7

- El resumen del negocio pasa de siete consultas independientes a una sola ronda de base de datos para sus indicadores.
- Las hojas de estilo y scripts del POS gráfico se cargan solo en ventas, mesas, cobro, reportes y recibo.
- Se agregan índices para el enlace de turnos locales y para recuperar el último catálogo del equipo.
- Se conserva gzip y la caché de archivos estáticos con compatibilidad amplia en cPanel.

Validación aislada: 21 pruebas R2, 9 pruebas R3, resumen reducido de 14 a 8 consultas con una latencia simulada de 5 ms por consulta, instalación 1.7.7 desde una copia 1.7.6 y comprobación de integridad del ZIP.
