<?php
declare(strict_types=1);

function fm_media_paths(string $value,array $config,string $root,array &$paths):void {
    if(!str_contains($value,'uploads'))return;
    $value=html_entity_decode(str_replace('\\/','/',$value),ENT_QUOTES|ENT_HTML5,'UTF-8');
    preg_match_all('~(?:https?://[^\s"\'<>/]+/)?/?uploads/[a-zA-Z0-9%._/@()+-]+~',$value,$matches);
    foreach($matches[0] as $match){
        $host=parse_url($match,PHP_URL_HOST);
        if($host!==null&&preg_replace('/^www\./','',strtolower($host))!==preg_replace('/^www\./','',strtolower((string)parse_url($config['app_url'],PHP_URL_HOST))))continue;
        $relative=rawurldecode(ltrim((string)parse_url($match,PHP_URL_PATH),'/'));
        if(!str_starts_with($relative,'uploads/')||str_contains($relative,'..')||str_contains($relative,"\0")||str_contains($relative,'\\'))continue;
        $file=ROOT_PATH.'/'.$relative;$real=realpath($file);
        if($real===false){$relative=rtrim($relative,'.),;');$file=ROOT_PATH.'/'.$relative;$real=realpath($file);}
        if($real!==false&&str_starts_with($real,$root.DIRECTORY_SEPARATOR)&&is_file($real)&&!is_link($file))$paths[$relative]=$real;
    }
}
function fm_media_archive(App $app):never {
    if(!class_exists('PharData'))throw new RuntimeException('Activa la extensión Phar de PHP con ayuda del hosting para descargar las imágenes necesarias.');
    $root=realpath(ROOT_PATH.'/uploads');if($root===false)throw new RuntimeException('Falta uploads.');
    $paths=[];$meta=fm_meta($app);
    $buffered=$app->db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    $app->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    try{
        foreach($meta['tables'] as $table=>$m){
            $columns=implode(',',array_map('fm_id',$m['cols']));$s=$app->db->query('SELECT '.$columns.' FROM '.fm_id($table));
            while($row=$s->fetch())foreach($row as $value)if(is_string($value))fm_media_paths($value,$app->config,$root,$paths);
            $s->closeCursor();
        }
    }finally{$app->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);}
    // Protection file belongs to the clean deploy packet, not to business data.
    if(is_file(ROOT_PATH.'/uploads/.htaccess'))$paths['uploads/.htaccess']=ROOT_PATH.'/uploads/.htaccess';
    ksort($paths);$dir=sys_get_temp_dir().'/fisitaap-media-'.bin2hex(random_bytes(12));
    if(!mkdir($dir,0700))throw new RuntimeException('El servidor no pudo crear un archivo temporal privado.');
    $zip=$dir.'/FISITAAP-UPLOADS-NECESARIOS.zip';
    try{
        $archive=new PharData($zip,0,null,Phar::ZIP);
        foreach($paths as $relative=>$file)$archive->addFile($file,$relative);
        unset($archive);chmod($zip,0600);
        header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="FISITAAP-UPLOADS-NECESARIOS.zip"');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($zip));
        readfile($zip);
    }finally{if(is_file($zip))unlink($zip);rmdir($dir);}
    exit;
}
