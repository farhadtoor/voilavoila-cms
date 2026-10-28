<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** Native API 1 package lifecycle. No uploaded PHP is included, even during inspection. */
final class Packages {
    private static function temporary(string $bytes): string {
        $path=tempnam(sys_get_temp_dir(),'vv-package-');
        if($path===false || file_put_contents($path,$bytes)!==strlen($bytes)) throw new Failure('Écriture temporaire impossible.',503);
        chmod($path,0600);return $path;
    }
    private static function folder(string $type): string {
        if(!in_array($type,['plugin','theme'],true)) throw new Failure('Type de module invalide.');
        return $type==='plugin'?'plugins':'themes';
    }
    private static function inspect(string $bytes,string $type): array {
        require_once __DIR__.'/Tools.php';Tools::zip();$path=self::temporary($bytes);$zip=new \ZipArchive();
        $opened=false;try {
            if($zip->open($path)!==true) throw new Failure('ZIP illisible.');
            $opened=true;$entries=Tools::zipEntries($zip,400,20*1048576);$candidates=[];
            foreach($entries as $row)if($row['name']==='cms.json'||preg_match('~^[a-z][a-z0-9-]*/cms\.json$~D',$row['name']))$candidates[]=$row;
            if(count($candidates)!==1||$candidates[0]['size']>12000)throw new Failure('Un manifeste natif cms.json est requis.');
            $manifest=$candidates[0];$m=json_decode($zip->getFromIndex($manifest['index']),true);$id=$m['id']??'';
            if(!is_string($id)||!preg_match('/^[a-z][a-z0-9-]{1,50}$/D',$id)||($m['api']??0)!==1||($m['type']??'')!==$type||!is_string($m['name']??null)||!is_string($m['version']??null)||!preg_match('/^\d+\.\d+\.\d+$/D',$m['version']))throw new Failure('Manifeste natif incompatible.');
            if(in_array($id,['wp-runtime','wp-porter'],true))throw new Failure('Le moteur et le portage WordPress ne sont plus pris en charge.');
            if(!App::compatible($m))throw new Failure('Ce module demande une autre version du CMS ou de PHP.');
            $prefix=$manifest['name']==='cms.json'?'':dirname($manifest['name']).'/';$files=[];
            foreach($entries as $row){
                if(!str_starts_with($row['name'],$prefix))throw new Failure('Les fichiers doivent être dans un seul dossier.');
                $name=substr($row['name'],strlen($prefix));if($name===''||str_ends_with($name,'/'))continue;
                if(preg_match('~(?:^|/)\.~',$name)||!preg_match('/\.(php|json|css|js|svg|png|jpe?g|webp|woff2|md|txt)$/iD',$name))throw new Failure('Format interdit dans le module.');
                $body=$zip->getFromIndex($row['index']);if(!is_string($body)||strlen($body)!==$row['size'])throw new Failure('Archive incomplète.');
                if(str_ends_with(strtolower($name),'.php')){
                    if(!preg_match('/defined\s*\(\s*[\x27\x22]VV_ROOT[\x27\x22]\s*\)/',$body))throw new Failure('Chaque fichier PHP doit vérifier VV_ROOT.');
                    try{token_get_all($body,TOKEN_PARSE);}catch(\ParseError){throw new Failure('Syntaxe PHP invalide dans '.$name.'.');}
                }
                $files[$name]=$body;
            }
            if(!isset($files[$type==='plugin'?'plugin.php':'index.php'])||($type==='theme'&&!isset($files['style.css'])))throw new Failure('Point d’entrée natif absent.');
            return ['manifest'=>$m,'files'=>$files];
        }finally{if($opened)$zip->close();@unlink($path);}
    }
    private static function tree(string $directory): array {
        if(!is_dir($directory))return [];$files=[];$total=0;
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS)) as $f){
            if($f->isLink())throw new Failure('Lien symbolique refusé dans le module.',409);
            if(!$f->isFile())continue;
            $name=substr($f->getPathname(),strlen($directory)+1);
            if(++$total>400||$f->getSize()>8*1048576)throw new Failure('Module installé trop volumineux pour une mise à jour ZIP.',413);
            $files[$name]=file_get_contents($f->getPathname());
        }
        if(array_sum(array_map('strlen',$files))>20*1048576)throw new Failure('Module installé limité à 20 Mo.',413);
        ksort($files);return $files;
    }
    private static function fingerprint(array $files): string {ksort($files);$hashes=[];foreach($files as $name=>$body)$hashes[$name]=hash('sha256',$body);return hash('sha256',json_encode($hashes,JSON_THROW_ON_ERROR));}
    public static function stage(App $app,array $file,string $type): string {
        $folder=self::folder($type);$id=Quarantine::capture($app->store,$file,'package',12*1048576);
        Quarantine::validate($app->store,$id,function($bytes)use($folder,$type):array{
            $package=self::inspect($bytes,$type);$m=$package['manifest'];$target=VV_ROOT.'/content/'.$folder.'/'.$m['id'];$old=self::tree($target);
            $installed=$old?json_decode($old['cms.json']??'',true):null;
            if($old&&(!is_array($installed)||($installed['id']??'')!==$m['id']))throw new Failure('Le module installé ne peut pas être identifié.',409);
            if($installed&&!version_compare($m['version'],$installed['version'],'>'))throw new Failure('La nouvelle version doit être supérieure. Utilisez Retour arrière pour restaurer une version.',409);
            return ['manifest'=>$m,'file_count'=>count($package['files']),'operation'=>$installed?'update':'install','previous_version'=>$installed['version']??null,'baseline'=>self::fingerprint($old)];
        });return $id;
    }
    private static function inactive(array $state,string $type,string $id): void {
        if($type==='theme'&&$id==='profil-or'&&($state['settings']['theme']??'')==='profil-graphite')throw new Failure('Profil Graphite utilise Profil Or. Activez un thème indépendant avant cette mise à jour, ou mettez à jour le CMS complet.',409);
        if(($type==='plugin'&&in_array($id,$state['plugins'],true))||($type==='theme'&&($state['settings']['theme']??'')===$id))throw new Failure('Désactivez ce module avant de remplacer son code.',409);
    }
    private static function archive(Store $store,string $token,array $files,array $metadata): string {
        $dir=$store->dir.'/package-backups';if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new Failure('Sauvegarde du module impossible.',503);
        $tmp=tempnam(sys_get_temp_dir(),'vv-rollback-');$zip=new \ZipArchive();
        try{if($zip->open($tmp,\ZipArchive::OVERWRITE)!==true)throw new Failure('Sauvegarde du module impossible.',503);
            foreach($files as $name=>$body)$zip->addFromString('code/'.$name,$body);
            $zip->addFromString('rollback.json',json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));$zip->close();
            $bytes=file_get_contents($tmp);$store->privateWrite($dir.'/'.$token.'.php',$bytes);return hash('sha256',$bytes);
        }finally{@unlink($tmp);}
    }
    private static function swap(App $app,string $type,string $id,array $files,callable $commit): void {
        require_once __DIR__.'/Tools.php';$folder=VV_ROOT.'/content/'.self::folder($type);$token=bin2hex(random_bytes(10));$stage=$folder.'/.update-'.$token;$target=$folder.'/'.$id;$old=$folder.'/.previous-'.$token;
        if(!mkdir($stage,0700))throw new Failure('Dossier des modules non accessible en écriture.',503);
        $moved=false;$published=false;
        try{
            foreach($files as $name=>$bytes){
                if(str_starts_with($name,'/')||preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00]~',$name))throw new Failure('Chemin de module refusé.');
                $path=$stage.'/'.$name;if(!is_dir(dirname($path)))mkdir(dirname($path),0755,true);
                if(file_put_contents($path,$bytes)!==strlen($bytes))throw new Failure('Écriture du module interrompue.',503);chmod($path,0644);
            }
            chmod($stage,0755);
            if(is_dir($target)){if(!rename($target,$old))throw new Failure('Mise à jour impossible.',503);$moved=true;}
            if(!rename($stage,$target))throw new Failure('Publication du module impossible.',503);$published=true;
            if(function_exists('opcache_invalidate'))foreach(array_keys($files)as$name)if(str_ends_with(strtolower($name),'.php'))@opcache_invalidate($target.'/'.$name,true);
            try{$app->store->change($commit);}catch(\Throwable $e){Tools::removeTree($target);$published=false;if($moved){rename($old,$target);$moved=false;}if(function_exists('opcache_invalidate'))foreach(array_keys($files)as$name)if(str_ends_with(strtolower($name),'.php'))@opcache_invalidate($target.'/'.$name,true);throw $e;}
        }finally{
            if(is_dir($stage))Tools::removeTree($stage);
            if($moved&&is_dir($old)){if($published)Tools::removeTree($old);else rename($old,$target);}
        }
    }
    private static function lock(Store $store){$f=fopen($store->dir.'/packages.lock','c+b');if(!$f||!flock($f,LOCK_EX|LOCK_NB))throw new Failure('Une opération de module est déjà en cours.',409);chmod($store->dir.'/packages.lock',0600);return $f;}
    public static function apply(App $app,string $qid,bool $reviewed): string {
        if(!$reviewed)throw new Failure('Confirmez l’examen du code et du rapport.');
        $lock=self::lock($app->store);
        try{
            $row=Quarantine::approved($app->store,$qid,'package');$bytes=Quarantine::bytes($app->store,$qid);$m=$row['inspection']['manifest'];$type=$m['type'];$id=$m['id'];
            $package=self::inspect($bytes,$type);$old=self::tree(VV_ROOT.'/content/'.self::folder($type).'/'.$id);
            if(!hash_equals($row['inspection']['baseline'],self::fingerprint($old)))throw new Failure('Le code installé a changé. Recommencez le dépôt.',409);
            $state=$app->store->read();self::inactive($state,$type,$id);
            $token=bin2hex(random_bytes(12));$hash='';$snapshot=null;
            if($old){$snapshot=['type'=>$type,'id'=>$id,'manifest'=>json_decode($old['cms.json'],true),'had_plugin_data'=>array_key_exists($id,$state['plugin_data']),'plugin_data'=>$state['plugin_data'][$id]??null,'baseline'=>self::fingerprint($old)];$hash=self::archive($app->store,$token,$old,$snapshot);}
            self::swap($app,$type,$id,$package['files'],function(&$s)use($type,$id,$qid,$old,$token,$hash,$m,$package):void{
                self::inactive($s,$type,$id);
                if(($s['quarantine'][$qid]['status']??'')!=='ready')throw new Failure('Dépôt déjà traité.',409);
                if($old)$s['package_history'][$token]=['token'=>$token,'type'=>$type,'id'=>$id,'at'=>gmdate('c'),'from'=>$s['quarantine'][$qid]['inspection']['previous_version'],'to'=>$m['version'],'sha256'=>$hash,'installed_hash'=>self::fingerprint($package['files'])];
                $s['quarantine'][$qid]['status']='consumed';Store::log($s,$old?'package_updated':'package_installed',$type.':'.$id.':'.$m['version']);
            });
            Quarantine::consume($app->store,$qid);$app->refresh();return $id;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function rollback(App $app,string $token): void {
        if(!preg_match('/^[a-f0-9]{24}$/D',$token))throw new Failure('Point de retour invalide.');
        $lock=self::lock($app->store);$tmp='';
        try{
            $state=$app->store->read();$history=$state['package_history'][$token]??null;if(!$history)throw new Failure('Point de retour absent.',404);
            self::inactive($state,$history['type'],$history['id']);
            $current=self::tree(VV_ROOT.'/content/'.self::folder($history['type']).'/'.$history['id']);
            if(!hash_equals($history['installed_hash'],self::fingerprint($current)))throw new Failure('Le module a été modifié depuis la mise à jour. Retour automatique refusé.',409);
            $bytes=$app->store->privateRead($app->store->dir.'/package-backups/'.$token.'.php');if(!hash_equals($history['sha256'],hash('sha256',$bytes)))throw new Failure('Sauvegarde de module altérée.',409);
            $tmp=self::temporary($bytes);$zip=new \ZipArchive();if($zip->open($tmp)!==true)throw new Failure('Sauvegarde illisible.');$files=[];
            try{Tools::zipEntries($zip,401,32*1048576);$meta=json_decode($zip->getFromName('rollback.json'),true,64,JSON_THROW_ON_ERROR);for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i);if(str_starts_with($name,'code/'))$files[substr($name,5)]=$zip->getFromIndex($i); }}finally{$zip->close();}
            if(!hash_equals($meta['baseline'],self::fingerprint($files))||$meta['id']!==$history['id']||$meta['type']!==$history['type'])throw new Failure('Sauvegarde incohérente.',409);
            self::swap($app,$history['type'],$history['id'],$files,function(&$s)use($history,$meta,$token):void{
                self::inactive($s,$history['type'],$history['id']);
                if($meta['type']==='plugin'){if($meta['had_plugin_data'])$s['plugin_data'][$meta['id']]=$meta['plugin_data'];else unset($s['plugin_data'][$meta['id']]);}
                unset($s['package_history'][$token]);Store::log($s,'package_rolled_back',$history['id']);
            });@unlink($app->store->dir.'/package-backups/'.$token.'.php');$app->refresh();
        }finally{if($tmp!=='')@unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function cleanup(App $app): int {
        $expired=[];$app->store->change(function(&$s)use(&$expired):void{$rows=$s['package_history']??[];uasort($rows,fn($a,$b)=>strcmp($b['at'],$a['at']));foreach(array_values($rows) as $i=>$r)if($i>=10||strtotime($r['at'])<time()-30*86400){$expired[]=$r['token'];unset($s['package_history'][$r['token']]);}});
        foreach($expired as $token)@unlink($app->store->dir.'/package-backups/'.$token.'.php');return count($expired);
    }
}
