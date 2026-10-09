"""Image processing and batch authorization checks against disposable PHP fixture."""
import hashlib, io, json, re, struct, os
from pathlib import Path
from PIL import Image
from migration179 import STAGE, SERVER, DELIVERY, run, php, request, login, digests

checks=[]
def passed(label):
    checks.append(label);print('PASS',label,flush=True)
def evaluate(code):
    return json.loads(php(SERVER,"require '/var/www/html/app/bootstrap.php';require_once '/var/www/html/app/admin.php';"+code))
def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()

def main():
    config=STAGE/'config.php';original=config.read_text()
    folder=STAGE/'uploads/image-qa';folder.mkdir(exist_ok=True)
    run(['docker','exec',SERVER,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html/uploads/image-qa'])
    folder.chmod(0o755)
    # Highly detailed photo and transparent logo, not trivial solid-color compression.
    photo=Image.effect_noise((2400,1600),90).convert('RGB');photo.save(folder/'photo.jpg',quality=95)
    logo=Image.new('RGBA',(1200,600),(0,0,0,0));logo.paste((0,120,200,255),(150,100,1050,500));logo.save(folder/'logo.png')
    tiny=Image.new('RGB',(40,30),'red');tiny.save(folder/'tiny.jpg',quality=90)
    # JPEG orientation 6 without requiring PHP EXIF extension.
    payload=b'Exif\0\0'+b'II'+struct.pack('<H',42)+struct.pack('<I',8)+struct.pack('<H',1)+struct.pack('<HHI',274,3,1)+struct.pack('<H',6)+b'\0\0'+b'\0'*4
    raw=(folder/'tiny.jpg').read_bytes();(folder/'rotated.jpg').write_bytes(raw[:2]+b'\xff\xe1'+struct.pack('>H',len(payload)+2)+payload+raw[2:])
    for file in folder.iterdir():
        if file.is_file():file.chmod(0o644)
    baseline=digests(SERVER)
    result=evaluate("$p=ROOT_PATH.'/uploads/image-qa/photo.jpg';$url=store_uploaded_image_v12($p,'image/jpeg',filesize($p));$file=image_local_file($url);$i=getimagesize($file);$t=getimagesize($file.'.thumb.webp');echo json_encode(['url'=>$url,'width'=>$i[0],'height'=>$i[1],'mime'=>$i['mime'],'bytes'=>filesize($file),'thumb_width'=>$t[0],'thumb_bytes'=>filesize($file.'.thumb.webp')]);")
    assert result['mime']=='image/webp' and 720<=result['width']<=1440 and abs(result['height']/result['width']-2/3)<.002
    assert result['thumb_width']==480 and result['bytes']<(folder/'photo.jpg').stat().st_size*.5
    passed('detailed upload becomes smaller WebP within 1440px with adaptive sizing and a 480px thumbnail')
    transparency=evaluate("$p=ROOT_PATH.'/uploads/image-qa/logo.png';$url=store_uploaded_image_v12($p,'image/png',filesize($p));$i=imagecreatefromwebp(image_local_file($url));echo json_encode(['alpha'=>(imagecolorat($i,0,0)>>24)&127]);")
    assert transparency['alpha']==127
    passed('transparent logo retains its transparent background')
    rotation=evaluate("$p=ROOT_PATH.'/uploads/image-qa/rotated.jpg';$url=store_uploaded_image_v12($p,'image/jpeg',filesize($p));$i=getimagesize(image_local_file($url));echo json_encode([$i[0],$i[1]]);")
    assert rotation==[30,40]
    passed('phone JPEG orientation is honored even without the PHP EXIF extension; small images are not enlarged')
    reject=evaluate("$out=[];foreach(['image/jpeg','image/png'] as $mime){try{store_uploaded_image_v12(ROOT_PATH.'/uploads/image-qa/logo.png',$mime,$mime==='image/jpeg'?100:6000000);$out[]=false;}catch(RuntimeException $e){$out[]=true;}}$out[]=!image_memory_available(30000,30000);echo json_encode($out);")
    assert reject==[True,True,True]
    passed('invalid MIME, upload-size excess and unsafe decoded dimensions are rejected')
    p=folder/'photo.jpg';original_hash=sha(p)
    optimization=evaluate("$p=ROOT_PATH.'/uploads/image-qa/photo.jpg';$r=image_optimize_existing($p);$u=public_media_url('/uploads/image-qa/photo.jpg');echo json_encode(['result'=>$r,'full'=>$u,'thumb'=>media_thumbnail_url($u)]);")
    assert sha(p)==original_hash and '.optimized.webp' in optimization['full'] and '.thumb.webp' in optimization['thumb']
    assert digests(SERVER)==baseline
    passed('existing-photo optimization preserves original bytes and database rows; media URLs choose generated variants')
    files={str(p):sha(p) for p in folder.iterdir() if p.is_file()}
    evaluate("echo json_encode(image_optimize_existing(ROOT_PATH.'/uploads/image-qa/photo.jpg'));")
    assert files=={str(p):sha(p) for p in folder.iterdir() if p.is_file()}
    external=evaluate("echo json_encode([image_variant_url('https://external.invalid/uploads/image-qa/photo.jpg'),image_local_file('/uploads/../config.php')]);")
    assert external==['https://external.invalid/uploads/image-qa/photo.jpg',None]
    passed('repeated batches are idempotent; external images and traversal paths are not rewritten')
    archived=evaluate("require ROOT_PATH.'/app/migration_media.php';$paths=[];fm_media_paths('/uploads/image-qa/photo.jpg',$config,realpath(ROOT_PATH.'/uploads'),$paths);echo json_encode(array_keys($paths));")
    assert {'uploads/image-qa/photo.jpg','uploads/image-qa/photo.jpg.optimized.webp','uploads/image-qa/photo.jpg.thumb.webp'}<=set(archived)
    passed('selective media export also includes optimized versions and thumbnails')
    saved=evaluate("echo json_encode($app->one('SELECT id,image,updated_at FROM products WHERE tenant_id=101 AND status=\"active\" ORDER BY id LIMIT 1'));")
    try:
        evaluate("$app->exec('UPDATE products SET image=? WHERE id=?',['/uploads/image-qa/photo.jpg',"+str(saved['id'])+"]);echo json_encode(true);")
        status,html=request('anon','/laventanita/menu?sucursal=principal')
        assert status==200 and re.search(r'<img[^>]*class="product-img"[^>]*loading="lazy"[^>]*src="[^"]*photo.jpg.thumb.webp',html)
        binary=run(['docker','exec',SERVER,'curl','--noproxy','*','-fsS','http://localhost/uploads/image-qa/photo.jpg.thumb.webp'])
        with Image.open(io.BytesIO(binary)) as image:
            assert image.format=='WEBP' and image.width==480
    finally:
        evaluate("$app->exec('UPDATE products SET image=?,updated_at=? WHERE id=?',"+json.dumps([saved['image'],saved['updated_at'],saved['id']]).replace('null','null')+");echo json_encode(true);")
    current=digests(SERVER)
    assert current==baseline,[table for table in current if current[table]!=baseline[table]]
    passed('catalog cards actually render lazy thumbnails and Apache serves the 480px WebP file')
    run(['docker','exec',SERVER,'chown','-R','www-data:www-data','/var/www/html/uploads'])
    try:
        config.write_text(original.replace('return [',"return ['migration_mode'=>true,",1))
        assert request('owner','/optimizar-imagenes.php')[0]==403
        assert request('master','/optimizar-imagenes.php',{'csrf':'invalid','action':'start'})[0]==403
        status,body=request('master','/optimizar-imagenes.php');assert status==200
        def send(action):
            status,body=request('master','/optimizar-imagenes.php');token=re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
            return request('master','/optimizar-imagenes.php',{'csrf':token,'action':action})
        status,body=send('start');assert status==200
        for _ in range(20):
            if 'Procesar siguiente lote' not in body:break
            status,body=send('next');assert status==200
        assert 'Proceso terminado.' in body
        assert digests(SERVER)==baseline
        passed('master-only CSRF-protected batch tool completes small batches without database mutations')
    finally:
        run(['docker','exec',SERVER,'chown','-R',str(os.getuid())+':'+str(os.getgid()),'/var/www/html/uploads'])
        config.write_text(original)
        assert request('master','/optimizar-imagenes.php')[0]==403
    passed('batch tool is disabled immediately when migration mode is removed')
    report={'environment':'PHP 8.3/GD WebP on isolated fixture, generated detailed photo and transparent logo','passed':len(checks),'checks':checks,'example':{'original_photo_bytes':(folder/'photo.jpg').stat().st_size,'optimized_upload_bytes':result['bytes'],'thumbnail_bytes':result['thumb_bytes']}}
    (DELIVERY/'QA-IMAGENES.json').write_text(json.dumps(report,indent=2)+'\n')
if __name__=='__main__':main()
