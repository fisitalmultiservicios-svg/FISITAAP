"""Build first-install signed APK, keeping its key outside the public repository."""
import json,os,pathlib,secrets,subprocess,sys,zipfile
root=pathlib.Path(__file__).resolve().parents[1]
private=pathlib.Path('/workspace/fisitaap-env/android-signing');private.mkdir(exist_ok=True);private.chmod(0o700)
config=private/'credentials.json'
key=private/'fisitaap-release.jks'
if key.exists()!=config.exists():raise RuntimeError('El respaldo de firma está incompleto. Restaura ambos archivos; no se reemplazó la clave.')
if not key.exists() and os.environ.get('FISITAAP_CREATE_FIRST_ANDROID_KEY')!='yes':raise RuntimeError('Falta la clave Android existente. Restaura el respaldo privado para conservar la firma. Solo una primera instalación expresamente autorizada permite crear una clave nueva.')
if not config.exists():
    config.write_text(json.dumps({'store_password':secrets.token_urlsafe(32),'key_password':secrets.token_urlsafe(32)}));config.chmod(0o600)
credentials=json.loads(config.read_text())
env=os.environ.copy();env.update(FISITAAP_ANDROID_KEYSTORE=str(key),FISITAAP_ANDROID_STORE_PASSWORD=credentials['store_password'],FISITAAP_ANDROID_KEY_PASSWORD=credentials['key_password'])
if not key.exists():
    subprocess.run(['/workspace/fisitaap-cache/android/jdk/bin/keytool','-genkeypair','-keystore',str(key),'-storetype','JKS','-storepass:env','FISITAAP_ANDROID_STORE_PASSWORD','-keypass:env','FISITAAP_ANDROID_KEY_PASSWORD','-alias','fisitaap','-keyalg','RSA','-keysize','4096','-validity','10000','-dname','CN=FISITAAP, O=FISITAL, C=CR'],env=env,check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL);key.chmod(0o600)
subprocess.run([sys.executable,str(root/'qa/run_android180.py'),':app:testDebugUnitTest',':app:lintDebug',':app:assembleRelease',':app:lintRelease'],env=env,check=True)
subprocess.run(['/workspace/fisitaap-cache/android/sdk/build-tools/35.0.0/apksigner','verify','--verbose',str(root/'android/app/build/outputs/apk/release/app-release.apk')],check=True)
delivery=pathlib.Path('/workspace/entregas');delivery.mkdir(exist_ok=True)
backup=delivery/'FISITAAP-Android-CLAVE-PRIVADA-no-publicar.zip'
with zipfile.ZipFile(backup,'w',zipfile.ZIP_DEFLATED) as z:
    z.write(key,'fisitaap-release.jks');z.write(config,'credentials.json');z.writestr('LEEME.txt','Respaldo privado para firmar próximas actualizaciones Android. Guarda este ZIP en un lugar privado. Nunca lo subas a cPanel ni a GitHub. No hace falta usarlo para instalar el APK.\n')
backup.chmod(0o600)
print('Signed Android APK verified; private key backup saved separately, never in GitHub.')
