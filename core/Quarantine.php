<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** Private inert envelopes. Uploads are never public media or executable packages before approval. */
final class Quarantine {
    private const MAX_PENDING=100;
    private const MAX_BYTES=134217728;
    public static function directory(Store $store): string {
        $dir=$store->dir.'/quarantine';
        if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new Failure('Quarantaine indisponible.',503);
        if(!is_file($dir.'/.htaccess')) file_put_contents($dir.'/.htaccess',"Require all denied\n");
        return $dir;
    }
    private static function path(Store $store,string $id): string {
        if(!preg_match('/^[a-f0-9]{24}$/D',$id)) throw new Failure('Identifiant de quarantaine invalide.');
        return self::directory($store).'/'.$id.'.php';
    }
    public static function capture(Store $store,array $file,string $purpose,int $max): string {
        if(!in_array($purpose,['media','package','backup','import'],true)) throw new Failure('Type de dépôt invalide.');
        if(($file['error']??1)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null) || !is_uploaded_file($file['tmp_name'])) throw new Failure('Envoi incomplet. Vérifiez les limites PHP.');
        $size=filesize($file['tmp_name']);
        if($size===false || $size<1 || $size>$max) throw new Failure('Fichier trop volumineux ou vide.',413);
        $bytes=file_get_contents($file['tmp_name']);
        if(!is_string($bytes) || strlen($bytes)!==$size) throw new Failure('Envoi illisible.',503);
        $name=Content::text(is_string($file['name']??null)?basename(str_replace('\\','/',$file['name'])):'fichier',180);
        $id=bin2hex(random_bytes(12)); $path=self::path($store,$id);
        $store->privateWrite($path,$bytes);
        try {
            $store->change(function(&$state)use($id,$name,$size,$purpose,$bytes):void {
                $rows=$state['quarantine']??[];
                $pending=array_filter($rows,fn($r)=>!in_array($r['status'],['consumed','rejected'],true));
                if(count($pending)>=self::MAX_PENDING || array_sum(array_column($pending,'size'))+$size>self::MAX_BYTES) throw new Failure('Quarantaine pleine. Examinez ou supprimez les dépôts en attente.',413);
                if(count($rows)>=500) throw new Failure('Nettoyez la quarantaine via la maintenance.',413);
                $state['quarantine'][$id]=['id'=>$id,'purpose'=>$purpose,'name'=>$name,'size'=>$size,'sha256'=>hash('sha256',$bytes),'status'=>'pending','scan'=>'not_run','created_at'=>gmdate('c'),'expires'=>time()+604800];
                Store::log($state,'quarantine_received',$purpose.':'.$id);
            });
        } catch(\Throwable $e) { @unlink($path); throw $e; }
        return $id;
    }
    public static function row(Store $store,string $id): array {
        self::path($store,$id); $row=$store->read()['quarantine'][$id]??null;
        if(!$row || $row['expires']<time()) throw new Failure('Dépôt absent ou expiré.',404);
        return $row;
    }
    public static function bytes(Store $store,string $id): string {
        $row=self::row($store,$id); $bytes=$store->privateRead(self::path($store,$id));
        if(!hash_equals($row['sha256'],hash('sha256',$bytes))) throw new Failure('Le dépôt a changé. Libération refusée.',409);
        return $bytes;
    }
    private static function scan(string $bytes): string {
        $socket=getenv('VV_CLAMAV_SOCKET') ?: '';
        if($socket==='') return 'not_configured';
        if(str_contains($socket,"\0"))return 'unavailable';
        if(str_starts_with($socket,'/'))$address='unix://'.$socket;
        elseif(preg_match('~^tcp://127\.0\.0\.1:[1-9][0-9]{0,4}$~D',$socket))$address=$socket;
        else return 'unavailable';
        $stream=@stream_socket_client($address,$errno,$error,3);
        if(!$stream) return 'unavailable';
        stream_set_timeout($stream,5); $deadline=microtime(true)+10;
        $write=static function(string $data)use($stream,$deadline):void {
            while($data!=='') {
                if(microtime(true)>$deadline) throw new \RuntimeException('Scanner timeout.');
                $n=fwrite($stream,$data); if(!$n) throw new \RuntimeException('Scanner write failed.'); $data=substr($data,$n);
            }
        };
        try {
            $write("zINSTREAM\0");
            for($offset=0;$offset<strlen($bytes);$offset+=8192) { $part=substr($bytes,$offset,8192); $write(pack('N',strlen($part)).$part); }
            $write(pack('N',0)); $response=stream_get_line($stream,4096,"\0");
            if(!is_string($response)) return 'unavailable';
            if(str_ends_with(trim($response),' FOUND')) return 'infected';
            return preg_match('/^stream: OK\s*$/D',$response)?'clean':'unavailable';
        } catch(\Throwable) { return 'unavailable'; }
        finally { fclose($stream); }
    }
    public static function validate(Store $store,string $id,callable $validator): array {
        $row=self::row($store,$id);
        if(in_array($row['status'],['consumed','rejected'],true)) throw new Failure('Dépôt déjà traité.',409);
        try {
            $bytes=self::bytes($store,$id); $scan=self::scan($bytes);
            if($scan==='infected') throw new Failure('Antivirus : fichier infecté. Dépôt bloqué.');
            if($scan==='unavailable') throw new Failure('Antivirus configuré mais indisponible. Dépôt bloqué.',503);
            if($scan==='not_configured' && getenv('VV_QUARANTINE_REQUIRE_AV')==='1') throw new Failure('Un antivirus est requis avant toute libération.',503);
            $result=$validator($bytes,$row);
            $store->change(function(&$state)use($id,$scan,$result):void {
                if(in_array($state['quarantine'][$id]['status']??'',['consumed','rejected'],true)) throw new Failure('Dépôt déjà traité.',409);
                $state['quarantine'][$id]['status']='ready'; $state['quarantine'][$id]['scan']=$scan;
                $state['quarantine'][$id]['inspection']=$result; unset($state['quarantine'][$id]['error']);
            });
            return $result;
        } catch(\Throwable $e) {
            $store->change(function(&$state)use($id,$e,&$scan):void {
                if(isset($state['quarantine'][$id]) && !in_array($state['quarantine'][$id]['status'],['consumed','rejected'],true)) {
                    $state['quarantine'][$id]['status']='blocked'; $state['quarantine'][$id]['scan']=$scan??'not_run';
                    $state['quarantine'][$id]['error']=$e instanceof Failure?$e->getMessage():'Validation interrompue.';
                }
            });
            throw $e;
        }
    }
    public static function approved(Store $store,string $id,string $purpose): array {
        $row=self::row($store,$id);
        if($row['purpose']!==$purpose || $row['status']!=='ready') throw new Failure('Ce dépôt ne peut pas être libéré.',409);
        if(!in_array($row['scan'],['clean','not_configured'],true) || ($row['scan']!=='clean' && getenv('VV_QUARANTINE_REQUIRE_AV')==='1')) throw new Failure('Analyse antivirus requise.',409);
        // Policy may have changed since receipt: configured scanner is always consulted again.
        if((getenv('VV_CLAMAV_SOCKET')?:'')!=='' && self::scan(self::bytes($store,$id))!=='clean') throw new Failure('Nouvelle analyse antivirus non concluante.',409);
        self::bytes($store,$id);
        return $row;
    }
    public static function consume(Store $store,string $id): void {
        $store->change(function(&$state)use($id):void { if(isset($state['quarantine'][$id])){$state['quarantine'][$id]['status']='consumed';$state['quarantine'][$id]['expires']=time()+86400;} });
        @unlink(self::path($store,$id));
    }
    public static function reject(Store $store,string $id): void {
        self::row($store,$id);
        $store->change(function(&$state)use($id):void {unset($state['quarantine'][$id]);Store::log($state,'quarantine_rejected',$id);});
        @unlink(self::path($store,$id));
    }
    public static function cleanup(App $app): int {
        $expired=[];
        $app->store->change(function(&$state)use(&$expired):void {foreach($state['quarantine']??[] as $id=>$row)if($row['expires']<=time()){$expired[]=$id;unset($state['quarantine'][$id]);}});
        foreach($expired as $id)@unlink(self::path($app->store,$id));
        // Failed DB writes or interrupted receipt can leave an inert orphan envelope.
        foreach(glob(self::directory($app->store).'/*.php')?:[] as $path)if(filemtime($path)<time()-604800 && !isset($app->store->read()['quarantine'][basename($path,'.php')]))@unlink($path);
        return count($expired);
    }
}
