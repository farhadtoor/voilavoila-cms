<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** Explicit maintenance only: no public request schedules work or contacts a service. */
final class Operations {
    public static function config(App $app): array {return self::normalize($app->state['settings']['operations']??[]);}
    public static function normalize(array $input): array {
        $out=[];foreach(['logs_days'=>[1,365,30],'backups_days'=>[1,365,30],'backups_keep'=>[3,30,3],'backup_hours'=>[1,168,24]] as $k=>[$min,$max,$default]) {
            $v=$input[$k]??$default;if(!is_int($v)&&(!is_string($v)||!ctype_digit($v)))throw new Failure('Durée de conservation invalide.');
            $v=(int)$v;if($v<$min||$v>$max)throw new Failure('Durée de conservation hors limites.');$out[$k]=$v;
        }
        foreach(['auto_backup','send_notifications'] as $k)$out[$k]=!empty($input[$k]);return $out;
    }
    public static function points(App $app): array {
        $files=array_values(array_filter(glob($app->store->dir.'/backups/*.php')?:[],fn($p)=>!is_link($p)&&preg_match('/^\d{8}-\d{6}-[a-f0-9]{8}\.php$/D',basename($p))));rsort($files);return $files;
    }
    public static function health(App $app): array {
        $start=microtime(true);$bytes=0;$files=0;$truncated=false;
        try {
            $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($app->store->dir,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST);
            foreach($iterator as $file){if($file->isLink())continue;if(++$files>5000||microtime(true)-$start>.2){$truncated=true;break;}if($file->isFile())$bytes+=$file->getSize();}
        }catch(\UnexpectedValueException){$truncated=true;}
        $points=self::points($app);$pending=['pending'=>0,'processing'=>0,'failed'=>0,'unknown'=>0];
        foreach($app->state['plugin_data']['forms']['rows']??[] as $r){$status=$r['notification']['status']??'';if(isset($pending[$status])&&($r['expires']??0)>time())$pending[$status]++;}
        $errorTail=Logger::read('error',true);$errors=substr_count($errorTail,'[ERROR]');
        return ['format'=>1,'cms_version'=>VV_VERSION,'hosting'=>Hosting::report($app->store->dir),'storage'=>['bytes'=>$bytes,'entries_examined'=>min($files,5000),'partial'=>$truncated],'backups'=>['count'=>count($points),'latest_at'=>$points?gmdate('c',filemtime($points[0])):null],'forms_notifications'=>$pending,'errors_in_recent_tail'=>$errors,'maintenance'=>$app->state['operations']['last_run']??null,'policy'=>self::config($app),'extra_plugin_storage'=>is_dir($app->store->dir.'/pass-native')||is_dir($app->store->dir.'/wp-quarantine'),'telemetry'=>false,'scope'=>'Local diagnostics. Storage scan is bounded; exports contain no paths, credentials, request text or log lines. Editorial backup excludes separate plugin databases and executable code.'];
    }
    private static function logs(App $app,int $days,float $deadline): int {
        $dir=$app->store->dir.'/logs';$changed=0;$lock=fopen($dir.'/rotation.lock','c+b');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))fclose($lock);return 0;}
        try {
            $threshold=gmdate('Y-m-d',time()-$days*86400);
            foreach(['access','error'] as $type)foreach(['','.1','.2','.3'] as $suffix) {
                if(microtime(true)>$deadline)return $changed;
                $path=$dir.'/'.$type.$suffix.'.txt';if(!is_file($path)||is_link($path))continue;
                $input=fopen($path,'rb');$tmp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';$output=fopen($tmp,'xb');chmod($tmp,0600);$removed=0;$count=0;$complete=true;
                try {
                    while(($line=fgets($input,4096))!==false){if(++$count%128===0&&microtime(true)>$deadline){$complete=false;break;}$date=substr($line,0,10);if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)&&$date<$threshold){$removed++;continue;}if(fwrite($output,$line)!==strlen($line))throw new Failure('Nettoyage du journal interrompu.',503);}
                    fflush($output);fclose($input);$input=null;fclose($output);$output=null;
                    if($complete&&$removed){if(!rename($tmp,$path))throw new Failure('Publication du journal impossible.',503);$changed+=$removed;}
                }finally{if(is_resource($input))fclose($input);if(is_resource($output))fclose($output);if(is_file($tmp))unlink($tmp);}
            }
        }finally{flock($lock,LOCK_UN);fclose($lock);}return $changed;
    }
    private static function backups(App $app,array $config): int {
        $removed=0;$files=self::points($app);
        foreach($files as $i=>$file){if($i<2)continue;if($i>=$config['backups_keep']||filemtime($file)<time()-$config['backups_days']*86400){if(unlink($file))$removed++;}}
        return $removed;
    }
    public static function run(App $app,int $budget=10,int $limit=3): array {
        require_once __DIR__.'/Tools.php';
        $budget=max(1,min(30,$budget));$limit=max(1,min(10,$limit));$start=microtime(true);$deadline=$start+$budget;
        $lock=fopen($app->store->dir.'/maintenance.lock','c+b');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))fclose($lock);throw new Failure('Maintenance déjà en cours.',409);}chmod($app->store->dir.'/maintenance.lock',0600);
        $result=['id'=>bin2hex(random_bytes(12)),'at'=>gmdate('c'),'budget_seconds'=>$budget,'counts'=>[],'errors'=>[]];$config=self::config($app);
        try {
            $tasks=[
                'expired_imports'=>fn()=>Migration::cleanup($app),
                'log_lines_removed'=>fn()=>self::logs($app,$config['logs_days'],$deadline),
                'backups_removed'=>fn()=>self::backups($app,$config),
                'forms_expired'=>function()use($app){return in_array('forms',$app->state['plugins'],true)&&class_exists(Forms::class)?Forms::purgeExpired($app):0;},
                'backup_created'=>function()use($app,$config){$points=self::points($app);if(!$config['auto_backup']||($points&&filemtime($points[0])>time()-$config['backup_hours']*3600))return 0;Tools::snapshot($app);return 1;},
            ];
            foreach($tasks as $id=>$task){if(microtime(true)>$deadline)break;try{$result['counts'][$id]=$task();}catch(\Throwable $e){$result['errors'][]=$id;Logger::error('maintenance_'.$id,$e->getMessage(),$e->getFile(),$e->getLine());}}
            $notifications=['accepted'=>0,'failed'=>0,'unknown'=>0];
            if($config['send_notifications']&&in_array('forms',$app->state['plugins'],true)&&class_exists(Forms::class))for($i=0;$i<$limit&&microtime(true)<$deadline;$i++){$one=Forms::drain($app,1);foreach($notifications as $k=>$_)$notifications[$k]+=$one[$k];if(array_sum($one)===0)break;}
            $result['notifications']=$notifications;$result['duration_ms']=(int)round((microtime(true)-$start)*1000);$result['budget_reached']=microtime(true)>=$deadline;$result['passed']=!$result['errors'];
            $app->store->change(function(&$s)use($result){$s['operations']['last_run']=$result;Store::log($s,'maintenance_completed',$result['passed']?'ok':'errors');});
            try{\vv_do('maintenance_completed_v1',$app,['version'=>1,'id'=>$result['id'],'at'=>$result['at'],'duration_ms'=>$result['duration_ms'],'counts'=>$result['counts']]);}catch(\Throwable $e){Logger::error('maintenance_observer',$e->getMessage(),$e->getFile(),$e->getLine());}
            return $result;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
