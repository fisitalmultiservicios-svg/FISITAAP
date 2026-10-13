<?php
declare(strict_types=1);

// Derivatives are generated on upload or explicitly in batches, never on page views.
function media_relative_path(string $value):?string {
    global $config;
    $parts=parse_url($value);if($parts===false)return null;
    if(isset($parts['scheme'])&&!in_array(strtolower($parts['scheme']),['http','https'],true))return null;
    if(isset($parts['user'])||isset($parts['pass']))return null;
    if(isset($parts['host'])){
        $own=parse_url((string)($GLOBALS['demo_base_url']??$config['app_url']??''),PHP_URL_HOST);
        if(preg_replace('/^www\./','',strtolower((string)$parts['host']))!==preg_replace('/^www\./','',strtolower((string)$own)))return null;
    }
    $path=rawurldecode((string)($parts['path']??''));
    $path=preg_replace('~^/demo/s/[a-f0-9]{32}/~','/',$path);
    $relative=ltrim($path,'/');
    if($relative===''||str_contains($relative,'..')||str_contains($relative,"\0")||str_contains($relative,'\\'))return null;
    return $relative;
}
function image_local_file(string $value):?string {
    $relative=media_relative_path($value);
    if($relative===null||!str_starts_with($relative,'uploads/'))return null;
    $root=realpath(ROOT_PATH.'/uploads');$file=ROOT_PATH.'/'.$relative;$real=realpath($file);
    return $root!==false&&$real!==false&&str_starts_with($real,$root.DIRECTORY_SEPARATOR)&&is_file($real)&&!is_link($file)?$real:null;
}
function image_variant_url(string $value,bool $thumbnail=false):string {
    $key=($thumbnail?'thumb:':'full:').$value;
    if(isset($GLOBALS['fisitaap_image_urls'][$key]))return $GLOBALS['fisitaap_image_urls'][$key];
    return $GLOBALS['fisitaap_image_urls'][$key]=image_variant_resolve($value,$thumbnail);
}
function image_variant_resolve(string $value,bool $thumbnail):string {
    $file=image_local_file($value);if($file===null)return $value;
    $original=str_ends_with($file,'.optimized.webp')?substr($file,0,-strlen('.optimized.webp')):$file;
    $candidate=$original.($thumbnail?'.thumb.webp':'.optimized.webp');
    if(!is_file($original)||!is_file($candidate)||is_link($candidate)||filemtime($candidate)<filemtime($original)||filesize($candidate)>=filesize($file))return $value;
    $parts=parse_url($value);$path=(string)($parts['path']??'');
    if(str_ends_with($path,'.optimized.webp'))$path=substr($path,0,-strlen('.optimized.webp'));
    $path.=($thumbnail?'.thumb.webp':'.optimized.webp');
    $prefix=isset($parts['host'])?(($parts['scheme']??'https').'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'')):'';
    return $prefix.$path.'?v='.filemtime($candidate);
}
function media_thumbnail_url(string $value):string{return image_variant_url($value,true);}
function image_memory_available(int $width,int $height):bool {
    $setting=trim((string)ini_get('memory_limit'));if($setting==='-1')return true;
    $limit=(int)$setting;$unit=strtolower(substr($setting,-1));
    $limit*=match($unit){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
    // GD source, rotation copy, resized canvas and decoder overhead.
    return $limit>0&&memory_get_usage(true)+$width*$height*12+24*1024*1024<$limit;
}
function image_orientation(string $file):int {
    if(function_exists('exif_read_data')){$data=@exif_read_data($file);return (int)($data['Orientation']??1);}
    // Read only bounded JPEG APP1 metadata when cPanel has no EXIF extension.
    $handle=fopen($file,'rb');if(!$handle)return 1;
    try{
        if(fread($handle,2)!=="\xff\xd8")return 1;
        for($segment=0;$segment<100;$segment++){
            $marker=fread($handle,2);if(strlen($marker)!==2||ord($marker[0])!==255)return 1;
            $type=ord($marker[1]);if($type===218||$type===217)return 1;
            $length=fread($handle,2);if(strlen($length)!==2)return 1;$size=unpack('n',$length)[1]-2;if($size<0||$size>65533)return 1;
            if($type!==225){fseek($handle,$size,SEEK_CUR);continue;}
            $data=fread($handle,$size);if(!str_starts_with($data,"Exif\0\0"))continue;
            $tiff=substr($data,6);$little=substr($tiff,0,2)==='II';if(!$little&&substr($tiff,0,2)!=='MM')return 1;
            $read=static function(int $offset,int $bytes)use($tiff,$little):?int {if($offset<0||$offset+$bytes>strlen($tiff))return null;return unpack($bytes===2?($little?'v':'n'):($little?'V':'N'),substr($tiff,$offset,$bytes))[1];};
            if($read(2,2)!==42)return 1;$offset=$read(4,4);if($offset===null)return 1;$count=$read($offset,2);if($count===null||$count>1024)return 1;
            for($i=0;$i<$count;$i++){$entry=$offset+2+$i*12;if($read($entry,2)===274&&$read($entry+2,2)===3&&$read($entry+4,4)===1){$orientation=$read($entry+8,2);return $orientation!==null&&$orientation>=1&&$orientation<=8?$orientation:1;}}
        }
    }finally{fclose($handle);}return 1;
}

function image_source(string $file):GdImage {
    $info=@getimagesize($file);
    if(!$info||!in_array($info['mime']??'',['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('La imagen no es JPG, PNG o WebP válida.');
    if($info[0]<1||$info[1]<1||$info[0]*$info[1]>30000000||!image_memory_available($info[0],$info[1]))throw new RuntimeException('La foto es demasiado grande para procesarla con seguridad. Reduce sus dimensiones y vuelve a subirla.');
    if(!function_exists('imagewebp'))throw new RuntimeException('El hosting debe activar GD con soporte WebP.');
    $source=match($info['mime']){'image/jpeg'=>@imagecreatefromjpeg($file),'image/png'=>@imagecreatefrompng($file),'image/webp'=>@imagecreatefromwebp($file)};
    if(!$source)throw new RuntimeException('No se pudo leer la imagen.');
    if($info['mime']==='image/jpeg'){
        $orientation=image_orientation($file);
        if(in_array($orientation,[2,4,5,7],true))imageflip($source,$orientation===4?IMG_FLIP_VERTICAL:IMG_FLIP_HORIZONTAL);
        $angle=match($orientation){3=>180,5,8=>90,6,7=>-90,default=>0};
        if($angle){$rotated=imagerotate($source,$angle,0);if($rotated){imagedestroy($source);$source=$rotated;}}
    }
    return $source;
}
function image_is_animated(string $file):bool {
    $handle=fopen($file,'rb');if(!$handle)return true;
    try{
        $header=fread($handle,12);
        if(substr($header,0,4)==='RIFF'&&substr($header,8,4)==='WEBP'){
            while(!feof($handle)){$chunk=fread($handle,8);if(strlen($chunk)<8)break;$type=substr($chunk,0,4);$size=unpack('V',substr($chunk,4,4))[1];if($type==='ANIM'||$type==='ANMF')return true;if($size>filesize($file))break;fseek($handle,$size+($size%2),SEEK_CUR);}
        }elseif(substr($header,0,8)==="\x89PNG\r\n\x1a\n"){
            fseek($handle,8);while(!feof($handle)){$chunk=fread($handle,8);if(strlen($chunk)<8)break;$size=unpack('N',substr($chunk,0,4))[1];$type=substr($chunk,4,4);if($type==='acTL')return true;if($type==='IDAT'||$size>filesize($file))break;fseek($handle,$size+4,SEEK_CUR);}
        }
    }finally{fclose($handle);}return false;
}
function image_write_webp(GdImage $source,string $destination,int $maximum,int $quality,int $target):void {
    $width=imagesx($source);$height=imagesy($source);$ratio=min(1,$maximum/max($width,$height));
    $w=max(1,(int)round($width*$ratio));$h=max(1,(int)round($height*$ratio));
    $canvas=imagecreatetruecolor($w,$h);imagealphablending($canvas,false);imagesavealpha($canvas,true);
    imagefill($canvas,0,0,imagecolorallocatealpha($canvas,0,0,0,127));imagecopyresampled($canvas,$source,0,0,0,0,$w,$h,$width,$height);
    $temp=$destination.'.tmp-'.bin2hex(random_bytes(6));
    try{
        foreach(array_unique([$quality,max(64,$quality-7),64]) as $q){
            if(!imagewebp($canvas,$temp,$q))throw new RuntimeException('No se pudo generar WebP.');
            clearstatcache(true,$temp);if(filesize($temp)<=$target)break;
        }
        if(filesize($temp)>$target&&$maximum>720&&max($w,$h)>720){image_write_webp($source,$destination,max(720,(int)floor($maximum*.75)),max(68,$quality-3),$target);return;}
        if(!is_file($temp)||filesize($temp)<1||!@getimagesize($temp))throw new RuntimeException('La imagen optimizada no es válida.');
        chmod($temp,0644);if(!rename($temp,$destination))throw new RuntimeException('No se pudo guardar la imagen optimizada.');
        unset($GLOBALS['fisitaap_image_urls']);
    }finally{imagedestroy($canvas);if(is_file($temp))unlink($temp);}
}
function image_optimize_existing(string $file):array {
    if(image_local_file('/'.ltrim(substr($file,strlen(ROOT_PATH)),'/'))!==$file)throw new RuntimeException('Ruta de imagen fuera de uploads.');
    $before=filesize($file);
    if(image_is_animated($file))return ['before'=>$before,'after'=>$before,'status'=>'animada: conservada'];
    $full=$file.'.optimized.webp';$thumb=$file.'.thumb.webp';
    if(is_file($full)&&is_file($thumb)&&filemtime($full)>=filemtime($file)&&filemtime($thumb)>=filemtime($file))return ['before'=>$before,'after'=>min($before,filesize($full)),'status'=>'ya optimizada'];
    $source=image_source($file);
    try{image_write_webp($source,$full,1440,78,160*1024);image_write_webp($source,$thumb,480,72,40*1024);}finally{imagedestroy($source);}
    return ['before'=>$before,'after'=>min($before,filesize($full)),'status'=>'optimizada'];
}
