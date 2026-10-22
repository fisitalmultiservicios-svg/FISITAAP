"""Server photo handling: large actual bytes, alpha, orientation and clear rejection."""
import json,pathlib,shutil,subprocess,sys
from PIL import Image
ROOT=pathlib.Path(__file__).resolve().parents[1];STAGE=pathlib.Path('/workspace/fisitaap-r2-test');folder=STAGE/'uploads/image-qa-r10';folder.mkdir(exist_ok=True);folder.chmod(0o755)
def php(code):
    p=subprocess.run(['docker','exec','-i','fisitaap-r2-web','php','-d','memory_limit=128M','-r',"require '/var/www/html/app/bootstrap.php';require_once '/var/www/html/app/admin.php';"+code],capture_output=True,check=True);return json.loads(p.stdout)
def main():
    checks=[];shutil.copyfile('/workspace/fisitaap-env/design-r10/large-photo.png',folder/'large.png')
    logo=Image.new('RGBA',(1200,600),(0,0,0,0));logo.paste((120,40,130,255),(150,100,1000,500));logo.save(folder/'logo.png');exif=Image.Exif();exif[274]=6;Image.new('RGB',(40,30),'red').save(folder/'rotated.jpg',exif=exif)
    with (folder/'oversize.png').open('wb') as file:file.write((folder/'large.png').read_bytes());file.truncate(26*1024*1024)
    for f in folder.iterdir():f.chmod(0o644)
    out=php("$p=ROOT_PATH.'/uploads/image-qa-r10/large.png';$u=store_uploaded_image_v12($p,'image/png',filesize($p));$f=image_local_file($u);echo json_encode(['bytes'=>filesize($f),'info'=>getimagesize($f)]);")
    stored_bytes=out['bytes'];assert out['info']['mime']=='image/webp' and out['bytes']<200*1024;checks.append('server directly accepts a valid11MB PNG under128MB PHP memory and stores a lightweight WebP, independently of browser preprocessing')
    out=php("$p=ROOT_PATH.'/uploads/image-qa-r10/logo.png';$u=store_uploaded_image_v12($p,'image/png',filesize($p));$i=imagecreatefromwebp(image_local_file($u));echo json_encode((imagecolorat($i,0,0)>>24)&127);")
    assert out==127;out=php("$p=ROOT_PATH.'/uploads/image-qa-r10/rotated.jpg';$u=store_uploaded_image_v12($p,'image/jpeg',filesize($p));$i=getimagesize(image_local_file($u));echo json_encode([$i[0],$i[1]]);");assert out==[30,40];checks.append('transparent logo retains alpha and rotated phone JPEG is oriented correctly without enlarging small images')
    out=php("$out=[];foreach([['oversize.png','image/png',1],['logo.png','image/jpeg',1]] as [$n,$m,$s]){try{store_uploaded_image_v12(ROOT_PATH.'/uploads/image-qa-r10/'.$n,$m,$s);$out[]=false;}catch(RuntimeException){$out[]=true;}}$_FILES['image']=['error'=>UPLOAD_ERR_INI_SIZE,'tmp_name'=>'','size'=>0];try{upload_image('image');$out[]=false;}catch(RuntimeException $e){$out[]=str_contains($e->getMessage(),'hosting');}$out[]=!image_memory_available(30000,30000);echo json_encode($out);")
    assert out==[True,True,True,True];checks.append('actual files over25MB cannot bypass the limit with a false size; MIME mismatch, unsafe dimensions and PHP upload-limit errors are rejected without silently announcing a save')
    (ROOT/'actualizaciones/1.8.0/QA-IMAGENES-R10.json').write_text(json.dumps({'passed':len(checks),'checks':checks,'stored_large_image_bytes':stored_bytes,'environment':'PHP8.3/GD/WebP, memory128MB, fictional fixture; transport/browser tested separately'},indent=2)+'\n');print('PASS',len(checks),'server image groups')
if __name__=='__main__':main()
