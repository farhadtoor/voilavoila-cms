<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;
require_once __DIR__.'/WordPress.php';

/** Owner-only, expiring WXR staging. Payloads remain PHP-guarded private bytes. */
final class Migration {
    private static function directory(App $app): string {
        $dir=$app->store->dir.'/import-previews';
        if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new Failure('Prévisualisation indisponible.',503);
        return $dir;
    }
    private static function path(App $app,string $token,string $part): string {
        if(!preg_match('/^[a-f0-9]{32}$/D',$token)||!in_array($part,['plan','wxr','uploads'],true))throw new Failure('Prévisualisation invalide.');
        return self::directory($app).'/'.$token.'.'.$part.'.php';
    }
    private static function lock(App $app) {
        $lock=fopen($app->store->dir.'/migration.lock','c+b');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new Failure('Un import est déjà en cours.',409);
        chmod($app->store->dir.'/migration.lock',0600);return $lock;
    }
    public static function cleanup(App $app,int $now=0): int {
        $now=$now?:time();$removed=0;$dir=self::directory($app);
        foreach(array_slice(glob($dir.'/*.plan.php')?:[],0,20) as $path) {
            if(!preg_match('/^([a-f0-9]{32})\.plan\.php$/D',basename($path),$m)||is_link($path))continue;
            try{$record=json_decode($app->store->privateRead($path),true,64,JSON_THROW_ON_ERROR);}catch(\Throwable){continue;}
            if(($record['expires']??PHP_INT_MAX)>$now)continue;
            self::remove($app,$m[1]);$removed++;
        }
        foreach(array_slice(glob($dir.'/*.php')?:[],0,60) as $path)if(!is_link($path)&&preg_match('/^([a-f0-9]{32})\.(wxr|uploads)\.php$/D',basename($path),$m)&&!is_file(self::path($app,$m[1],'plan'))&&filemtime($path)<$now-7200)unlink($path);
        return $removed;
    }
    private static function remove(App $app,string $token): void {
        foreach(['plan','wxr','uploads'] as $part){$p=self::path($app,$token,$part);if(is_file($p)&&!is_link($p))unlink($p);}
    }
    public static function prepare(App $app,array $wxr,array $uploads,bool $publish,array $options): string {
        $lock=self::lock($app);
        try {
            self::cleanup($app);
            $options=['source_url'=>\val($options,'source_url'),'comments'=>!empty($options['comments']),'public_media'=>!empty($options['public_media'])];
            $report=WordPress::run($app,$wxr,$publish,$uploads,$options+['preview'=>true]);
            $existing=glob(self::directory($app).'/*.plan.php')?:[];
            usort($existing,fn($a,$b)=>filemtime($a)<=>filemtime($b));
            while(count($existing)>=2){$old=array_shift($existing);if(preg_match('/^([a-f0-9]{32})\.plan\.php$/D',basename($old),$m))self::remove($app,$m[1]);}
            $token=bin2hex(random_bytes(16));$xml=file_get_contents(Tools::uploaded($wxr,8*1048576));$zip='';
            if(($uploads['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$zip=file_get_contents(Tools::uploaded($uploads,64*1048576));
            $record=['format'=>1,'created'=>time(),'expires'=>time()+3600,'owner_epoch'=>$app->state['owner']['epoch'],'publish'=>$publish,'options'=>$options,'xml_sha256'=>hash('sha256',$xml),'zip_sha256'=>hash('sha256',$zip),'report'=>$report];
            try {
                $app->store->privateWrite(self::path($app,$token,'wxr'),$xml);
                if($zip!=='')$app->store->privateWrite(self::path($app,$token,'uploads'),$zip);
                $app->store->privateWrite(self::path($app,$token,'plan'),json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            }catch(\Throwable $e){self::remove($app,$token);throw $e;}
            return $token;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function read(App $app,string $token): array {
        $p=self::path($app,$token,'plan');
        if(!is_file($p)||is_link($p))throw new Failure('Prévisualisation introuvable ou expirée.',410);
        $record=json_decode($app->store->privateRead($p),true,64,JSON_THROW_ON_ERROR);
        if(($record['format']??0)!==1||($record['expires']??0)<time()||!hash_equals($app->state['owner']['epoch'],$record['owner_epoch']??''))throw new Failure('Prévisualisation expirée. Recommencez l’analyse.',410);
        return $record;
    }
    public static function confirm(App $app,string $token): array {
        $lock=self::lock($app);
        try {
            $record=self::read($app,$token);
            if(!hash_equals($record['report']['state_fingerprint'],WordPress::fingerprint($app->store->read())))throw new Failure('Le site a changé. Relancez la prévisualisation.',409);
            $xml=$app->store->privateRead(self::path($app,$token,'wxr'));$zp=self::path($app,$token,'uploads');$zip=is_file($zp)?$app->store->privateRead($zp):'';
            if(!hash_equals($record['xml_sha256'],hash('sha256',$xml))||!hash_equals($record['zip_sha256'],hash('sha256',$zip)))throw new Failure('Intégrité de la prévisualisation incorrecte.',409);
            $report=WordPress::staged($app,$xml,$record['publish'],$zip,$record['options']+['expected_fingerprint'=>$record['report']['state_fingerprint']]);
            self::remove($app,$token);return $report;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
