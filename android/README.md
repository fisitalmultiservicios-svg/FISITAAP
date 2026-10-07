# FISITAAP Android

Aplicación nativa para Android 8.0 o superior, con Android System WebView actualizado. Abre el sistema web completo, conserva la sesión y envía recibos y comandas a impresoras térmicas ESC/POS de red, de 58 u 80 mm. No necesita ejecutar FISITAAP Print en Windows para imprimir desde Android.

La caja sin internet utiliza el equipo central Windows existente: varias cajas comparten su catálogo, existencias, turnos y ventas. Android actúa como otra caja del local. El equipo central y el router deben permanecer encendidos; no se guarda una base de ventas independiente en el teléfono.

La aplicación conserva ambas pantallas al cambiar de modo. No cambia automáticamente de pantalla durante un cobro. Antes de regresar a la web comprueba las ventas pendientes del equipo central y solicita sincronizarlas. Los permisos del menú web siguen dependiendo de la cuenta habitual.

## Compilar

Usa Java 21, Android SDK 35, Build Tools 35.0.0 y el Gradle 8.13 del wrapper. Android Studio puede importar esta carpeta. Define el SDK mediante `ANDROID_HOME` o un archivo `local.properties` privado.

```sh
./gradlew :app:assembleDebug :app:testDebugUnitTest :app:lintDebug
```

Para generar una actualización instalable, conserva la misma clave de firma. La clave privada no se incluye en el repositorio ni en el ZIP de fuentes. Define estas variables mediante tu entorno privado, sin escribir las contraseñas en el código:

- `FISITAAP_ANDROID_KEYSTORE`: ruta al almacén de firma.
- `FISITAAP_ANDROID_STORE_PASSWORD`: contraseña del almacén.
- `FISITAAP_ANDROID_KEY_PASSWORD`: contraseña de la clave `fisitaap`.

```sh
./gradlew :app:assembleRelease :app:testReleaseUnitTest :app:lintRelease
```

Las dependencias son AndroidX WebKit 1.12.1 y JUnit 4.13.2 para las pruebas. No se necesita modificar cPanel. La integración usa el comprobante estructurado de FISITAAP 1.7.0-R3.

## Impresión y conexión

El puente nativo se expone únicamente al origen configurado y a la página principal. No se utiliza `addJavascriptInterface`. WebKit instala el código al comenzar el documento para sustituir la impresión del navegador y del puente Windows antes de la impresión automática.

La aplicación acepta HTTPS para la web y una IP privada literal por HTTP, con el puerto 18766, para el equipo central. La impresión TCP acepta IP privadas literales, sin resolver nombres externos. Una zona configurada como Red / IP en la web conserva su impresora y ancho de papel; la caja local usa la impresora predeterminada de Android.

Los recibos se envían como imágenes ESC/POS para conservar acentos y el símbolo ₡ sin depender de la tabla de caracteres del modelo. El corte se puede desactivar. El registro de cada envío se guarda antes de enviar bytes: un mismo identificador confirmado no vuelve a imprimir, y un envío incierto requiere una copia explícita. La confirmación indica envío por la red, no una comprobación física del papel.

`Imprimir página / guardar PDF` abre el servicio de impresión de Android para otros documentos. Puede requerir el complemento de impresión del fabricante.

Las pruebas de seguridad comprueban direcciones, contenido cambiado, conservación del registro tras reiniciar, clics simultáneos y envío a un receptor TCP. Las pruebas de integración utilizan datos ficticios y una autoridad de prueba opcional mediante `FISITAAP_ANDROID_TEST_OVERLAY`, aplicable exclusivamente al APK debug. El APK release no contiene esa autoridad ni permite depuración WebView.
