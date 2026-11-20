# FISITAAP Android: terminal Windows 4.40.2

**Versión preliminar para conectar Android al programa Windows. Requiere la PC encendida y ambos equipos en la red local del negocio. No es el ERP Android independiente.**

[Descargar APK firmada](https://github.com/fisitalmultiservicios-svg/FISITAAP/raw/refs/heads/descargas-android/FISITAAP-Android-Terminal-4.40.2.apk)

Android 8 o posterior, con Android System WebView actualizado. Compilación y firma v2/v3 verificadas; 37 comprobaciones de direcciones/origen/descargas aprobadas. Todavía no se ha ejecutado en un teléfono, tablet ni emulador. La conexión, las pantallas y la impresión requieren validación en un dispositivo real antes del uso operativo.

## Instalación

1. Instale la APK; autorice la instalación desde la aplicación que la descargó si Android lo pide.
2. Abra FISITAAP Local Windows 4.40.2 o posterior con conexiones de red habilitadas.
3. En la PC, vaya a Configuración → Instalación local y busque «Dirección de esta computadora para las demás».
4. Conecte el Android al mismo Wi-Fi/red del negocio. En la APK escriba la dirección de esa PC, por ejemplo `192.168.1.50:8020`. Use la IP real del PC; `127.0.0.1` no sirve para conectarse desde otro equipo.
5. Toque «Conectar con mi PC» e inicie sesión con el usuario de la empresa.

## Funcionamiento

- Ventana e icono propios de FISITAAP, sin barra de navegador.
- Los datos, los permisos, los usuarios y la sincronización son los del Windows conectado. La APK no crea una copia independiente de la empresa ni otra terminal fiscal.
- Puede usarse sin internet mientras Windows y la red local sigan encendidos. Si se apaga la PC o se pierde la red local, no se puede seguir operando.
- La configuración de impresoras de Windows sigue siendo la del ERP. Incluye además acceso a impresión Android y apertura de documentos cuando el dispositivo disponga de esas aplicaciones. Estas funciones aún requieren prueba en equipo real.
- Catálogo web, Clientes web y Mi página siguen administrándose desde cloud según el bloqueo local autorizado.
- No necesita subir cambios a cPanel. No contiene contraseñas, bases de empresas ni claves privadas de firma.

## Estado del Android independiente

Todavía no hay APK autónoma que guarde una base local y sincronice sin depender de Windows. El entorno de construcción bloquea `chaquo.com`, repositorio oficial de las bibliotecas Python compiladas para Android. Después de habilitarlo, se deben verificar las dependencias nativas y desarrollar y probar el servidor local y el ciclo de vida Android. Esta terminal preliminar no sustituye ese trabajo.

Propiedad del producto: BRILLANTE AGENCIA 2.0. Esta rama contiene únicamente la descarga de la terminal Android y no modifica el código del ERP, producción ni el instalador Windows.
