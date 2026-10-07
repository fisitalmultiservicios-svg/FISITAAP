# Comprobaciones de FISITAAP Android 1.0.0

## Pasaron

- Compilación del APK release con Gradle 8.13, Android Gradle Plugin 8.9.2, SDK 35 y Build Tools 35.0.0.
- Seis pruebas JUnit: validación de IP privadas y orígenes; rechazo de credenciales y HTTP público; registro conservado tras reiniciar; envío incierto sin repetición; clics simultáneos; envío de la cola a un receptor TCP.
- Revisión Android Lint sin errores. Las advertencias restantes corresponden a textos en español sin recursos de traducción y al JavaScript necesario para la aplicación web.
- Pruebas de interfaz con el PHP real de FISITAAP 1.7.0-R3 y datos ficticios: inicio de sesión habitual y menú completo; recibo automático; repetición del identificador; copia con identificador nuevo; comandas de cocina y barra; venta local y botón de impresión. El transporte nativo se simuló en estas pruebas de navegador. No se llamó al puente Windows del puerto 18765.
- Firma RSA de 3072 bits y esquema APK v2, verificadas con `apksigner`.
- APK con identificador `com.fisitaap.android`, versión `1.0.0`, código `10000`, Android mínimo 8.0 (API 26) y destino API 35.
- El script de impresión incluido en el APK coincide con el código fuente. El APK release no contiene la autoridad de prueba, no declara configuración de confianza de desarrollo y no permite depuración WebView.
- Archivo de fuentes sin claves privadas, contraseñas, configuración de producción, dependencias descargadas ni carpetas de compilación.

## Prueba Android y límites

Se instalaron los APK debug y release en un emulador Android 15, y se comprobaron los controles nativos del debug. La prueba completa dentro del emulador no pudo terminar: su motor WebView 124 cerró el proceso de representación durante la emulación por software. También se observaron fallos de Bluetooth y un aviso de que la interfaz del sistema Android no respondía. No se considera aprobada una prueba completa de navegación e impresión en Android por ese resultado.

La aplicación gestiona el cierre de un motor WebView sin cerrar el programa ni borrar el registro de impresión. Informa que se debe revisar cualquier cobro sin confirmar antes de reabrir la pantalla.

No se ha probado una impresora física ni un teléfono o una tablet físicos en este entorno. El envío TCP de la cola se probó con un receptor de prueba; eso no verifica la salida de papel, el cortador ni todos los modelos ESC/POS. Al instalar, utiliza **Prueba de impresión** con cada modelo de 58 u 80 mm.

No se modificó ni se instaló nada en el alojamiento de producción. La caja local sigue dependiendo del equipo central Windows y de la red del negocio. Los permisos del menú web siguen dependiendo de cada cuenta.

## Firma y continuidad de las actualizaciones

Huella SHA256 del certificado de firma:

`cdae56893193f8b09bb52352fc58f4ee06c61a2dd049a6eb06a3e00e2fe72abf`

La clave privada se conserva fuera del repositorio. Las futuras actualizaciones deben usar la misma firma para poder instalarse sobre esta aplicación.
