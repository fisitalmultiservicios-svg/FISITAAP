"""Publish explicit reviewed public artifacts only; credentials never enter files/logs."""
import base64,hashlib,json,os,pathlib,subprocess,sys,urllib.parse
import requests
root=pathlib.Path(__file__).resolve().parents[1];delivery=pathlib.Path('/workspace/entregas');repo='fisitalmultiservicios-svg/FISITAAP';tag='v1.8.0'
names=['FISITAAP-1.8.0-CPANEL.zip','FISITAAP-Escritorio-1.8.0-Windows-x64.exe','FISITAAP-Android-1.8.0.apk','GUIA-CPANEL-1.8.0.md','INFORME-QA-1.8.0.md','SHA256SUMS-1.8.0.txt']
def main():
    token=os.environ.get('GH_TOKEN') or os.environ.get('GITHUB_TOKEN')
    if not token:raise RuntimeError('GitHub credential not available; artifacts remain local.')
    env=os.environ.copy()
    for key in ['GIT_TRACE','GIT_CURL_VERBOSE','GIT_TRACE_CURL']:env.pop(key,None)
    env.update(GIT_CONFIG_COUNT='1',GIT_CONFIG_KEY_0='http.https://github.com/.extraheader',GIT_CONFIG_VALUE_0='AUTHORIZATION: basic '+base64.b64encode(('x-access-token:'+token).encode()).decode())
    def git(args):
        result=subprocess.run(['git',*args],cwd=root,env=env,capture_output=True)
        if result.returncode:raise RuntimeError('Git operation failed: '+args[0]+'; no credential details printed')
        return result.stdout.decode().strip()
    head=git(['rev-parse','HEAD']);assert not git(['status','--porcelain']), 'Commit the reviewed files first'
    for name in names:
        path=delivery/name
        if not path.is_file() or path.is_symlink() or any(x in name.lower() for x in ['private','privada','jks','keystore','credentials','clave']):raise RuntimeError('Artifact is absent or private: '+name)
    git(['fetch','origin','main']);remote=git(['rev-parse','origin/main']);check=subprocess.run(['git','merge-base','--is-ancestor',remote,head],cwd=root,capture_output=True)
    git(['push','origin','HEAD:refs/heads/qa-1.8.0-en-proceso'])
    if check.returncode==0:git(['push','origin','HEAD:refs/heads/main']);print('Reviewed source pushed to main by fast-forward.',flush=True)
    else:print('Main advanced separately; source is preserved on the QA branch without replacing main.',flush=True)
    session=requests.Session();session.headers.update(Authorization='Bearer '+token,Accept='application/vnd.github+json',**{'X-GitHub-Api-Version':'2022-11-28'})
    api='https://api.github.com/repos/'+repo
    response=session.get(api+'/releases/tags/'+tag,timeout=30)
    if response.status_code==404:
        response=session.post(api+'/releases',json={'tag_name':tag,'target_commitish':head,'name':'FISITAAP 1.8.0 · QA manual y actualización completa','body':'Actualización acumulativa de cPanel, Windows x64 y Android 8+. Incluye correcciones del QA manual, control de caja/dispositivo, impresión directa, continuidad web/offline, catálogo, compras/XML, clientes, mesas, pedidos, reportes y rendimiento.\n\nGuarda respaldo completo, sincroniza/cierra turnos, instala los archivos y actualiza las aplicaciones antes de activar. Sigue GUIA-CPANEL-1.8.0.md. Los informes describen las pruebas con datos ficticios y los límites: impresoras/equipos/hosting reales requieren comprobación allí. La clave de firma Android se conserva privada y no se publica.','draft':True,'prerelease':False},timeout=30)
        if response.status_code!=201:raise RuntimeError('GitHub release creation failed with HTTP '+str(response.status_code))
    elif response.status_code!=200:raise RuntimeError('GitHub release lookup failed with HTTP '+str(response.status_code))
    release=response.json();rid=release['id'];upload=release['upload_url'].split('{',1)[0];existing={a['name']:a for a in release.get('assets',[])};assets=[]
    for name in names:
        path=delivery/name;digest=hashlib.sha256(path.read_bytes()).hexdigest();asset=existing.get(name)
        if asset:
            if asset.get('digest')!='sha256:'+digest:raise RuntimeError('Existing release artifact differs; it was not overwritten: '+name)
        else:
            print('Uploading',name,'('+str(path.stat().st_size)+' bytes)',flush=True)
            with path.open('rb') as file:response=session.post(upload,params={'name':name},headers={'Content-Type':'application/octet-stream'},data=file,timeout=(30,300))
            if response.status_code!=201:raise RuntimeError('GitHub artifact upload failed with HTTP '+str(response.status_code)+' for '+name)
            asset=response.json()
            if asset.get('digest')!='sha256:'+digest:raise RuntimeError('Uploaded artifact checksum mismatch: '+name)
        assets.append({'name':name,'sha256':digest,'url':asset['browser_download_url']});print('Verified',name,flush=True)
    if release['draft']:
        response=session.patch(api+'/releases/'+str(rid),json={'draft':False},timeout=30)
        if response.status_code!=200:raise RuntimeError('Publishing release failed with HTTP '+str(response.status_code))
        release=response.json()
    public={'source_commit':head,'release_url':release['html_url'],'assets':assets}
    (pathlib.Path('/workspace/fisitaap-env')/'published180.json').write_text(json.dumps(public,indent=2)+'\n')
    print(json.dumps(public,indent=2),flush=True)
if __name__=='__main__':main()
