"""Run Android validation with the pinned verified toolchain and system trust."""
import os,pathlib,subprocess,sys,urllib.parse
root=pathlib.Path(__file__).resolve().parents[1]
cache=pathlib.Path('/workspace/fisitaap-cache/android')
env=os.environ.copy();env.update(JAVA_HOME=str(cache/'jdk'),ANDROID_HOME=str(cache/'sdk'),ANDROID_USER_HOME=str(cache/'user'),GRADLE_USER_HOME=str(cache/'gradle'),JAVA_TOOL_OPTIONS='-Djavax.net.ssl.trustStore=/etc/ssl/certs/java/cacerts')
args=[str(cache/'gradle-8.13/bin/gradle'),'--no-daemon','--max-workers=2']
proxy=urllib.parse.urlparse(env.get('HTTPS_PROXY',''))
if proxy.hostname:
    for protocol in ['http','https']:args.extend(['-D'+protocol+'.proxyHost='+proxy.hostname,'-D'+protocol+'.proxyPort='+str(proxy.port or 80)])
args+=sys.argv[1:] or [':app:testDebugUnitTest',':app:lintDebug']
result=subprocess.run(args,cwd=root/'android',env=env)
sys.exit(result.returncode)
