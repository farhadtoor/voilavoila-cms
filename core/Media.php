<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;
final class Media {
    private const TYPES=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'];
    public static function metadata(array $m,string $bytes): array {
        if(strlen($bytes)<1 || strlen($bytes)>8*1024*1024) throw new Failure('Fichier limité à 8 Mo.',413);
        $id=$m['id'] ?? ''; if(!is_string($id) || !preg_match('/^[a-f0-9]{24}$/D',$id)) throw new Failure('Identifiant média invalide.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes); if(!in_array($mime,array_values(self::TYPES),true)) throw new Failure('Format de média non autorisé.');
        $name=Content::text(is_string($m['name'] ?? null)?$m['name']:'media',160); $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if((self::TYPES[$ext] ?? '')!==$mime) { $ext=match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>'pdf'}; $name=pathinfo($name,PATHINFO_FILENAME).'.'.$ext; }
        if($mime==='application/pdf') { if(!str_starts_with($bytes,'%PDF-')) throw new Failure('PDF invalide.'); }
        else { $info=@getimagesizefromstring($bytes); if(!$info || ($info['mime'] ?? '')!==$mime || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>8000000) throw new Failure('Image invalide ou trop grande : 8 mégapixels et 6 000 px maximum.'); }
        return array_merge($m,['id'=>$id,'name'=>$name,'mime'=>$mime,'extension'=>$ext,'stored_name'=>$id.'.'.$ext,'size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'public'=>!empty($m['public']),'alt'=>Content::text(is_string($m['alt'] ?? null)?$m['alt']:'',250),'created_at'=>is_string($m['created_at'] ?? null)?$m['created_at']:gmdate('c')]);
    }
    public static function upload(Store $store,array $file,bool $public,string $alt=''): string {
        if(($file['error'] ?? 1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) throw new Failure('Choisissez un fichier valide (8 Mo maximum).');
        $name=$file['name'] ?? ''; if(!is_string($name) || strlen($name)>240 || preg_match('/[\x00-\x1f\x7f\\\\\/]/',$name) || preg_match('/(?:^|\.)(php\d*|phtml|phar|html?|svg|js|htaccess)(?:\.|$)/i',$name)) throw new Failure('Nom ou extension de fichier refusé.');
        $bytes=file_get_contents($file['tmp_name']); $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION)); $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if((self::TYPES[$ext] ?? '')!==$mime) throw new Failure('Formats autorisés : JPG, JPEG, PNG, WebP et PDF.');
        $id=bin2hex(random_bytes(12)); $m=self::metadata(['id'=>$id,'name'=>$name,'alt'=>$alt,'public'=>$public],$bytes);
        if(str_starts_with($mime,'image/')) {
            if(!extension_loaded('gd')) throw new Failure('Activez PHP GD pour valider les images importées.',503);
            $info=getimagesizefromstring($bytes); $memory=ini_get('memory_limit'); $unit=strtolower(substr($memory,-1)); $limit=(int)$memory*match($unit){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
            if($limit>0 && memory_get_usage(true)+8*$info[0]*$info[1]+16*1024*1024>$limit) throw new Failure('Mémoire PHP insuffisante pour valider cette image.');
            $image=@imagecreatefromstring($bytes); if(!$image) throw new Failure('Image illisible.'); imagedestroy($image);
        }
        // Preserve the original bytes, extension, dimensions and metadata.
        $store->change(function(&$s)use($store,$id,$m,$bytes) { if(count($s['media'])>=2000) throw new Failure('Médiathèque limitée à 2 000 fichiers.',413); $store->writeMedia($m,$bytes); $s['media'][$id]=$m; Store::log($s,'media_uploaded',$m['name']); });
        return $id;
    }
}
