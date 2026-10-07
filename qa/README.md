# Pruebas ampliadas de FISITAAP

Estas pruebas son para revisión técnica en una **copia desechable**, con datos ficticios. No deben apuntarse al hosting del negocio. La [entrega QA](../actualizaciones/1.7.4-QA/README.md) incluye las correcciones comprobadas.

| Archivo | Cobertura |
|---|---|
| `web_deep.py` | Pantallas, permisos, HTTP/SQL real, dos cajas, dos cobros, saldo de regalos, recuperación de contraseña, clientes, precisión, entregas y 19 exportaciones. |
| `local_deep.test.js` | Concurrencia de caja local, lotes, prioridad de turnos, carreras y 1.500 cálculos comparados contra PHP. |
| `windows_print.test.js` | Contrato de impresión silenciosa, nombre del controlador y tratamiento de errores. Usa un sustituto del controlador. |
| `electron_print.js` | Generación real de PDF mediante Electron; necesita Electron y una pantalla, real o virtual. |
| `usability_browser.py` | Navegación de 12 pantallas en tres tamaños y observaciones de etiquetas/controles. |
| `installer_cumulative.py` | ZIP extraído, original/R1/R2/R3, integridad, CSRF, acceso, instalación repetida y restauración segura. |
| `../android/app/src/test/java/com/fisitaap/android/PrinterIntegrationTest.java` | Gráficos Android reales con Robolectric API 35 y TCP de prueba para ESC/POS 58/80 mm. |

## Requisitos de las pruebas web y acumulativas

Los guiones HTTP utilizan el entorno aislado preparado durante la incorporación cloud: Docker, MariaDB de prueba, `fisitaap-r2-web`, las copias de los baselines y `/tmp/fisitaap-r2-e2e.json` con credenciales ficticias generadas. **No son una herramienta para probar producción cambiando una URL.** `web_deep.py` exige el nombre del contenedor de prueba y modifica únicamente sus datos de prueba.

El fixture R2 inicializa catálogo, permisos, stock y usuarios; el fixture R3 habilita salones y regalos. Se ejecutan antes de `web_deep.py`, en ese orden. Reinicializa el fixture para una regresión completa. `FISITAAP_QA_CASE` selecciona por nombre una comprobación para investigar un fallo; no equivale a ejecutar toda la suite. `FISITAAP_QA_OUT` cambia el directorio de resultados.

Las suites de navegador requieren Python Playwright y Chromium. `usability_browser.py` usa el proxy privado en `172.18.0.1:18445` y restringe sus destinos a ese fixture. Las pruebas de sincronización PHP y del navegador con canal Android simulado usan fixtures independientes del controlador físico.

Para las pruebas Node, extrae las fuentes Windows para tener `desktop/`, instala con `npm ci` dentro de esa carpeta y ejecuta desde la raíz:

```sh
node --test qa/local_deep.test.js qa/windows_print.test.js
```

La comparación JavaScript/PHP del primer guion también necesita el contenedor PHP de prueba indicado en el archivo. Las 29 pruebas existentes se ejecutan con `npm test` dentro de `desktop/`.

Para Android, con JDK 17 o superior, SDK 35 y Gradle disponible:

```sh
cd android
./gradlew :app:testDebugUnitTest :app:lintDebug
```

Robolectric descarga su SDK de prueba de Maven Central y utiliza un perfil bajo el directorio de caché de Gradle. Las pruebas de red usan una IP privada del equipo y un puerto asignado al receptor de prueba, sin contactar impresoras del negocio.

## Lectura de resultados

Los resultados publicados contienen nombres de comprobaciones y métricas; no contraseñas, códigos de conexión ni datos de clientes. Una suite de pantallas no sustituye un flujo completo ni una prueba de dispositivo físico. El [informe](../actualizaciones/1.7.4-QA/INFORME-QA.md) detalla estas diferencias y los pendientes de uso.
