<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** Read-only hosting preflight. No database, network request or probe file. */
final class Hosting {
    public static function bytes(string $value): int {
        $value=trim($value);
        if($value==='-1' || $value==='0') return -1;
        if(!preg_match('/^(\d+)([KMG]?)$/iD',$value,$m)) return 0;
        return (int)$m[1]*match(strtolower($m[2] ?? '')) {'k'=>1024,'m'=>1048576,'g'=>1073741824,default=>1};
    }
    public static function report(string $storage): array {
        $checks=[];
        $add=function(string $id,string $fr,string $en,string $status,string $value) use(&$checks): void {
            $checks[]=['id'=>$id,'label_fr'=>$fr,'label_en'=>$en,'status'=>$status,'value'=>$value];
        };
        $add('php','Version PHP','PHP version',PHP_VERSION_ID>=80300?'ok':'error',PHP_VERSION);
        foreach(['dom','mbstring','fileinfo','tokenizer','pdo_'.(getenv('VV_DB_DRIVER')?:'sqlite'),'gd','zip'] as $extension) {
            $ok=extension_loaded($extension);
            $add('extension_'.$extension,'Extension '.$extension,'Extension '.$extension,$ok?'ok':'error',$ok?'OK':'ABSENT');
        }
        $gd=function_exists('imagejpeg') && function_exists('imagepng') && function_exists('imagewebp');
        $add('gd_formats','Images JPEG/PNG/WebP','JPEG/PNG/WebP images',$gd?'ok':'error',$gd?'OK':'ABSENT');
        foreach(['memory_limit'=>256,'upload_max_filesize'=>64,'post_max_size'=>70] as $setting=>$mib) {
            $value=(string)ini_get($setting); $bytes=self::bytes($value);
            $add($setting,$setting,$setting,$bytes===-1 || $bytes>=$mib*1048576?'ok':'warning',$value.' / recommended '.$mib.'M');
        }
        $directory=is_dir($storage)?$storage:dirname($storage);
        $add('storage_writable','Stockage accessible en écriture','Storage writable',is_dir($directory) && is_writable($directory)?'ok':'error',is_dir($storage)?'existing directory':'parent directory');
        $add('http_private','Accès HTTP aux données privées','HTTP access to private data','manual','Verify 403/404 as a signed-out visitor; this report does not send HTTP requests.');
        $version='unknown';
        $source=@file_get_contents(VV_ROOT.'/core/bootstrap.php');
        if(is_string($source) && preg_match("/define\('VV_VERSION',\s*'([^']+)'\)/",$source,$match)) $version=$match[1];
        return ['cms_version'=>$version,'php'=>PHP_VERSION,'sapi'=>PHP_SAPI,
            'passed'=>!in_array('error',array_column($checks,'status'),true),'checks'=>$checks,
            'scope'=>'Read-only preflight; CLI permissions can differ from the web PHP process. No secrets, private paths or HTTP protection claims.'];
    }
}
