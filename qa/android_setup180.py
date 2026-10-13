"""Install the pinned Android toolchain in writable caches, verifying archives."""
import hashlib,os,pathlib,subprocess,urllib.request,urllib.parse,zipfile
CACHE=pathlib.Path('/workspace/fisitaap-cache/android')
def download(url,target,algorithm,expected):
    if target.exists() and hashlib.new(algorithm,target.read_bytes()).hexdigest()==expected:return
    temp=target.with_suffix('.download');target.parent.mkdir(parents=True,exist_ok=True)
    with urllib.request.urlopen(url,timeout=60) as r,temp.open('wb') as f:
        while block:=r.read(1024*1024):f.write(block)
    if hashlib.new(algorithm,temp.read_bytes()).hexdigest()!=expected:
        temp.unlink();raise RuntimeError('Official Android/Gradle archive checksum does not match')
    temp.replace(target)
def main():
    jdk=CACHE/'jdk'
    if not (jdk/'bin/javac').exists():
        image='eclipse-temurin:21-jdk@sha256:3e3c176ffed168beb42c607be9bc1639b466cf00261a0fb04425562c9d0c5c2b'
        result=subprocess.run(['docker','pull',image],capture_output=True)
        if result.returncode:raise RuntimeError('Verified JDK image pull failed')
        created=subprocess.run(['docker','create',image],capture_output=True,text=True,check=True).stdout.strip()
        try:subprocess.run(['docker','cp',created+':/opt/java/openjdk',str(jdk)],capture_output=True,check=True)
        finally:subprocess.run(['docker','rm',created],capture_output=True)
    subprocess.run([str(jdk/'bin/javac'),'-version'],capture_output=True,check=True)
    sdk=CACHE/'sdk';sdk.mkdir(parents=True,exist_ok=True)
    archive=CACHE/'commandline-tools.zip'
    download('https://dl.google.com/android/repository/commandlinetools-linux-16111833_latest.zip',archive,'sha1','e025545c62a8e64c7559119566a569fb1dec5f60')
    tools=sdk/'cmdline-tools/latest'
    if not (tools/'bin/sdkmanager').exists():
        tools.mkdir(parents=True,exist_ok=True)
        with zipfile.ZipFile(archive) as z:
            for entry in z.infolist():
                rel=pathlib.PurePosixPath(entry.filename).relative_to('cmdline-tools')
                target=tools/pathlib.Path(rel)
                if '..' in rel.parts:raise RuntimeError('Unsafe SDK archive path')
                if entry.is_dir():target.mkdir(parents=True,exist_ok=True)
                else:target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(z.read(entry));target.chmod(0o755 if 'bin' in rel.parts else 0o644)
    gradle=CACHE/'gradle.zip';download('https://services.gradle.org/distributions/gradle-8.13-bin.zip',gradle,'sha256','20f1b1176237254a6fc204d8434196fa11a4cfb387567519c61556e8710aed78')
    if not (CACHE/'gradle-8.13/bin/gradle').exists():
        with zipfile.ZipFile(gradle) as z:z.extractall(CACHE)
        (CACHE/'gradle-8.13/bin/gradle').chmod(0o755)
    env=dict(os.environ,ANDROID_HOME=str(sdk),ANDROID_USER_HOME=str(CACHE/'user'),GRADLE_USER_HOME=str(CACHE/'gradle'),JAVA_HOME=str(jdk),JAVA_TOOL_OPTIONS='-Djavax.net.ssl.trustStore=/etc/ssl/certs/java/cacerts')
    manager=[str(tools/'bin/sdkmanager'),'--sdk_root='+str(sdk)]
    proxy=urllib.parse.urlparse(env.get('HTTPS_PROXY',''))
    if proxy.hostname:manager+=['--proxy=http','--proxy_host='+proxy.hostname,'--proxy_port='+str(proxy.port)]
    licenses=subprocess.run(manager+['--licenses'],input=('y\n'*50).encode(),env=env,capture_output=True)
    if licenses.returncode:raise RuntimeError('SDK license setup failed: '+licenses.stderr.decode(errors='replace')[-500:])
    result=subprocess.run(manager+['platforms;android-35','build-tools;35.0.0','platform-tools'],env=env,capture_output=True)
    if result.returncode:raise RuntimeError('Verified SDK package install failed: '+result.stderr.decode(errors='replace')[-500:])
    print('Verified Android commandline tools, SDK 35, build tools 35.0.0 and pinned Gradle 8.13 installed.')
if __name__=='__main__':main()
