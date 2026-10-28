<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

/** Extract this update OUTSIDE the live site, then run with --site=/absolute/site --confirm=METTRE-A-JOUR. */
$site='';$confirm=false;
foreach(array_slice($argv,1)as$arg){if(str_starts_with($arg,'--site='))$site=substr($arg,7);elseif($arg==='--confirm=METTRE-A-JOUR')$confirm=true;else{fwrite(STDERR,"Argument inconnu.\n");exit(1);}}
$site=realpath($site)?:'';
if(!$site||!$confirm||!is_file($site.'/core/bootstrap.php')||str_starts_with(__DIR__.'/', $site.'/')){fwrite(STDERR,"Extrayez la mise à jour hors du site. Usage : php APPLY-UPDATE.php --site=/chemin/absolu --confirm=METTRE-A-JOUR\n");exit(1);}
if(!class_exists(ZipArchive::class)){fwrite(STDERR,"L’extension PHP ZIP est requise.\n");exit(1);}
$info=json_decode(file_get_contents(__DIR__.'/UPDATE-INFO.json'),true,32,JSON_THROW_ON_ERROR);
if(($info['from']??'')!=='1.4.0'||($info['to']??'')!=='1.5.0')throw new RuntimeException('Mise à jour incompatible.');
$source=file_get_contents($site.'/core/bootstrap.php');
if(!preg_match("/define\('VV_VERSION',\s*'1\.4\.0'\)/",$source))throw new RuntimeException('La cible doit être exactement en version 1.4.0.');
foreach($info['files']as$name=>$hash){
    if(!is_string($name)||preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00:]~',$name)||str_starts_with($name,'/')||!is_file(__DIR__.'/payload/'.$name)||!hash_equals($hash,hash_file('sha256',__DIR__.'/payload/'.$name)))throw new RuntimeException('Charge utile manquante ou altérée.');
}
require $site.'/core/bootstrap.php';
if(!$vv->state['installed'])throw new RuntimeException('La cible doit être installée. Utilisez le ZIP complet pour une installation neuve.');
$private=realpath($vv->store->dir)?:$vv->store->dir;$stamp=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));$folder=$private.'/upgrade-backups';
if(!is_dir($folder)&&!mkdir($folder,0700,true))throw new RuntimeException('Dossier de sauvegarde indisponible.');
$lock=fopen($private.'/upgrade.lock','c+b');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Mise à jour déjà en cours.');chmod($private.'/upgrade.lock',0600);
$storeLock=null;$tmp='';$codeBefore=[];$changed=[];
try{
    // Public maintenance first, then the same write lock used by the 1.4 repository.
    $previousMaintenance=!empty($vv->state['settings']['maintenance']);
    $vv->store->change(static function(&$s):void{$s['settings']['maintenance']=true;});
    $storeLock=fopen($private.'/lock.php','c+b');if(!$storeLock||!flock($storeLock,LOCK_EX|LOCK_NB))throw new RuntimeException('Stockage occupé. Arrêtez les tâches et recommencez.');
    $tmp=tempnam(sys_get_temp_dir(),'vv-upgrade-');$zip=new ZipArchive();if($zip->open($tmp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Sauvegarde complète impossible.');
    $total=0;$count=0;$sources=[[$site,'site/',true],[$private,'storage/',false]];
    try{
        foreach($sources as[$base,$prefix,$isCode]){
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
            foreach($iterator as$f){
                $path=$f->getPathname();if($isCode&&($path===$private||str_starts_with($path,$private.'/')))continue;
                if(!$isCode&&(str_starts_with($path,$folder.'/')||in_array(basename($path),['upgrade.lock','lock.php'],true)))continue;
                if($f->isLink())throw new RuntimeException('Lien symbolique rencontré. Effectuez une sauvegarde externe avant mise à jour.');
                if(!$f->isFile())continue;$total+=$f->getSize();if(++$count>20000||$total>1073741824)throw new RuntimeException('Sauvegarde locale limitée à 1 Go et 20 000 fichiers. Utilisez une sauvegarde hébergeur.');
                if(!$zip->addFile($path,$prefix.substr($path,strlen($base)+1)))throw new RuntimeException('Sauvegarde de fichier interrompue.');
            }
        }
        $zip->addFromString('upgrade.json',json_encode(['from'=>'1.4.0','to'=>'1.5.0','created_at'=>gmdate('c'),'maintenance_before'=>$previousMaintenance],JSON_THROW_ON_ERROR));
    }finally{$zip->close();}
    // Guarded, private, streamed copy: backups are never placed in the public update folder.
    $backup=$folder.'/'.$stamp.'.php';$out=fopen($backup,'xb');if(!$out)throw new RuntimeException('Sauvegarde privée impossible.');chmod($backup,0600);fwrite($out,\VV\Store::GUARD);$in=fopen($tmp,'rb');stream_copy_to_stream($in,$out);fclose($in);fflush($out);if(function_exists('fsync'))fsync($out);fclose($out);unlink($tmp);$tmp='';
    foreach(array_unique(array_merge(array_keys($info['files']),$info['removed']))as$name){$path=$site.'/'.$name;$codeBefore[$name]=is_file($path)?file_get_contents($path):null;}
    try{
        foreach($info['files']as$name=>$hash){$path=$site.'/'.$name;if(!is_dir(dirname($path)))mkdir(dirname($path),0755,true);$temp=$path.'.update-'.$stamp;if(file_put_contents($temp,file_get_contents(__DIR__.'/payload/'.$name))===false)throw new RuntimeException('Écriture interrompue.');chmod($temp,0644);if(!rename($temp,$path))throw new RuntimeException('Publication interrompue.');$changed[]=$name;}
        foreach($info['removed']as$name){$path=$site.'/'.$name;if(is_file($path)&&!unlink($path))throw new RuntimeException('Retrait de l’ancien code impossible.');$changed[]=$name;}
        foreach(['content/plugins/wp-runtime','content/plugins/wp-porter']as$relative){$dir=$site.'/'.$relative;if(is_dir($dir)){$dirs=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($dirs as$d)if($d->isDir()&&!$d->isLink())@rmdir($d->getPathname());@rmdir($dir);}}
    }catch(Throwable$e){foreach(array_reverse($changed)as$name){$path=$site.'/'.$name;if($codeBefore[$name]===null)@unlink($path);else file_put_contents($path,$codeBefore[$name]);}throw $e;}
    echo "Mise à jour 1.4.0 → 1.5.0 appliquée.\nSauvegarde complète privée : ".$backup."\nLe site reste en maintenance. Vérifiez PHP, le cron, les modules et les en-têtes, puis désactivez la maintenance dans le CMS.\n";
}finally{if($tmp!=='')@unlink($tmp);if(is_resource($storeLock)){flock($storeLock,LOCK_UN);fclose($storeLock);}flock($lock,LOCK_UN);fclose($lock);}
