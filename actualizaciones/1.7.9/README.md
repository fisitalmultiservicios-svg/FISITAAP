# FISITAAP 1.7.9

Actualización acumulativa sobre la instalación R1/R2/R3 existente: incluye los cambios de 1.7.8 y un único equipo principal por negocio, Windows o Android, autorizado para buscar y vender sin internet. Las cajas secundarias usan el sistema web completo con conexión. Android ya no requiere un Windows central para su caja local.

La selección del principal se hace en Administración → Windows y cajas sin internet. El servidor valida autorización y generación al sincronizar; no permite sustituir un principal activo. Para cambiarlo, primero cerrar turnos, sincronizar y liberarlo desde el dispositivo anterior. Un intento fallido de liberación bloquea ventas locales hasta resolverlo.

Android comparte el motor de cálculo de Windows y guarda el estado local cifrado en SQLite con escrituras atómicas. La impresión nativa conserva las impresoras configuradas en Administración; recibos locales y web evitan el doble diálogo. La impresión general de documentos puede usar el diálogo del sistema.

Lee GUIA-ACTUALIZACION.txt antes de instalar. No es una instalación de base vacía. El ZIP incluye app, assets e index.php; excluye configuración, .htaccess, uploads y base de datos. No elimina datos existentes ni selecciona automáticamente un principal.

Ver QA.md para pruebas y límites. Las fuentes exactas del ZIP están en web/; instaladores construidos desde desktop/ y android/ de esta entrega.
