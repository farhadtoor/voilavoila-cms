<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;
final class Failure extends \RuntimeException {
    public function __construct(string $message, public int $status=400) { parent::__construct($message); }
}
/** Stable state-array repository API. Database access is delegated to an interchangeable driver. */
final class Store {
    public const GUARD="<?php http_response_code(404); exit; ?>\n";
    private DatabaseDriver $database;
    private bool $transaction=false;
    private const COLUMNS=['id','type','slug','status','title_fr','title_en','excerpt_fr','excerpt_en','body_fr','body_en','publish_at','created_at','updated_at','cover','pinned','menu','sort_order','version','tags','extra'];
    public function __construct(public string $dir) {
        if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new Failure('Stockage indisponible.',503);
        if(!is_writable($dir)) throw new Failure('Le dossier storage doit être accessible en écriture.',503);
        foreach(['media','sessions','backups','legacy','logs'] as $part) if(!is_dir("$dir/$part") && !mkdir("$dir/$part",0700,true)) throw new Failure('Création du stockage impossible.',503);
        if(!is_file($dir.'/.htaccess') && file_put_contents($dir.'/.htaccess',"Require all denied\n<IfModule mod_php.c>\nphp_flag engine off\n</IfModule>\n")===false) throw new Failure('Protection du stockage impossible.',503);
        $lock=$this->lock(LOCK_EX);
        try {
            $this->database=Database::connect($dir);
            $this->database->begin(true); $this->transaction=true;
            if(!$this->database->initialized()) {
                if(is_file($dir.'/state.php')) {
                    $bytes=$this->privateRead($dir.'/state.php'); $s=json_decode($bytes,true,128,JSON_THROW_ON_ERROR);
                    if(!is_array($s) || ($s['schema'] ?? 0)!==1) throw new Failure('Stockage 1.0 invalide. Migration interrompue.',503);
                    foreach(['owner','settings','contents','media','plugins','plugin_data','audit','limits'] as $section) if(!is_array($s[$section] ?? null)) throw new Failure('Stockage 1.0 incomplet. Migration interrompue.',503);
                    if(!is_bool($s['installed'] ?? null) || !is_string($s['secret'] ?? null)) throw new Failure('État 1.0 invalide. Migration interrompue.',503);
                    if(!is_file($dir.'/legacy/state-v1-before-migration.php')) $this->privateWrite($dir.'/legacy/state-v1-before-migration.php',$bytes);
                    foreach($s['media'] as $id=>&$m) {
                        if(!preg_match('/^[a-f0-9]{24}$/D',(string)$id)) throw new Failure('Identifiant média hérité invalide.',503);
                        $original=$this->privateRead($dir.'/media/'.$id.'.php');
                        if(!hash_equals($m['sha256'],hash('sha256',$original))) throw new Failure('Média hérité incomplet. Migration interrompue.',503);
                        $m=Media::metadata($m,$original,true); $this->writeMedia($m,$original);
                    }
                    unset($m); $s['schema']=2;
                    $s['migration']=['from'=>'1.0','at'=>gmdate('c'),'legacy_sha256'=>hash('sha256',$bytes)];
                    self::log($s,'migrated_to_sqlite','Pages, articles, révisions et médias conservés');
                } else {
                    $key=bin2hex(random_bytes(24)); $this->privateWrite($dir.'/setup-key.php',$key);
                    $s=['schema'=>2,'installed'=>false,'secret'=>bin2hex(random_bytes(32)),'setup_hash'=>hash('sha256',$key),'owner'=>[],'settings'=>[],'contents'=>[],'media'=>[],'plugins'=>[],'plugin_data'=>[],'audit'=>[],'limits'=>[]];
                }
                $this->database->write([],$s);
            }
            $this->database->commit(); $this->transaction=false;
            if(isset($s['migration']) && $s['migration']['from']==='1.0') $this->archiveLegacyMedia($s['media']);
        } catch(\Throwable $err) {
            if($this->transaction) { try{$this->database->rollback();}catch(\Throwable){} $this->transaction=false; }
            if($err instanceof Failure) throw $err;
            Logger::error('sqlite_initialization',$err->getMessage(),$err->getFile(),$err->getLine());
            throw new Failure('Ouverture ou migration de la base impossible. Sources conservées. Consultez error.txt.',503);
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    public function databasePath(): string { return $this->database->location(); }
    public function databaseInfo(): array { return $this->database->info(); }
    private function archiveLegacyMedia(array $media): void {
        $dest=$this->dir.'/legacy/media';
        if(!is_dir($dest) && !@mkdir($dest,0700,true)) { Logger::error('migration_archive','Archivage des anciennes enveloppes média impossible'); return; }
        foreach($media as $id=>$m) {
            $source=$this->dir.'/media/'.$id.'.php';
            if(is_file($source) && !is_file($dest.'/'.$id.'.php') && !@rename($source,$dest.'/'.$id.'.php')) Logger::error('migration_archive','Ancienne enveloppe conservée : '.$id);
        }
    }
    private function lock(int $mode) {
        $f=fopen($this->dir.'/lock.php','c+b'); if(!$f || !flock($f,$mode)) throw new Failure('Verrouillage du stockage impossible.',503);
        if(fstat($f)['size']===0) { fwrite($f,self::GUARD); fflush($f); chmod($this->dir.'/lock.php',0600); } return $f;
    }
    public function read(): array {
        if($this->transaction) throw new Failure('Lecture imbriquée dans une transaction non autorisée.',500); $lock=$this->lock(LOCK_SH);
        try { $this->database->begin(false); $this->transaction=true; $s=$this->database->read(); $this->database->commit(); $this->transaction=false; return $s; }
        catch(\Throwable $e) { if($this->transaction) { $this->database->rollback(); $this->transaction=false; } throw $e; }
        finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    public function change(callable $fn): mixed {
        if($this->transaction) throw new Failure('Transaction imbriquée non autorisée.',500); $lock=$this->lock(LOCK_EX);
        try { $this->database->begin(true); $this->transaction=true; $s=$this->database->read(); $before=$s; $result=$fn($s); if($s!==$before) $this->database->write($before,$s); $this->database->commit(); $this->transaction=false; return $result; }
        catch(\Throwable $e) { if($this->transaction) { $this->database->rollback(); $this->transaction=false; } throw $e; }
        finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    private function atomicWrite(string $path,string $bytes,bool $guard): void {
        $tmp=dirname($path).'/tmp-'.bin2hex(random_bytes(12)).($guard?'.php':'.tmp'); $f=fopen($tmp,'xb'); if(!$f) throw new Failure('Écriture impossible.',503);
        try { chmod($tmp,0600); $body=($guard?self::GUARD:'').$bytes; $offset=0;
            while($offset<strlen($body)) { $n=fwrite($f,substr($body,$offset)); if(!$n) throw new Failure('Espace disque insuffisant.',503); $offset+=$n; }
            if(!fflush($f)) throw new Failure('Écriture interrompue.',503); if(function_exists('fsync')) fsync($f); fclose($f); $f=null;
            if(!rename($tmp,$path)) throw new Failure('Publication du fichier impossible.',503);
        } finally { if(is_resource($f)) fclose($f); if(is_file($tmp)) @unlink($tmp); }
    }
    public function privateWrite(string $path,string $bytes): void { $this->atomicWrite($path,$bytes,true); }
    public function privateRead(string $path): string {
        $bytes=file_get_contents($path); if(!is_string($bytes) || !str_starts_with($bytes,self::GUARD)) throw new Failure('Fichier privé invalide.',503); return substr($bytes,strlen(self::GUARD));
    }
    public function mediaPath(array $m): string {
        $name=$m['stored_name'] ?? ''; if(!is_string($name) || !preg_match('/^[a-f0-9]{24}\.(jpg|jpeg|png|webp|pdf|docx|zip|csv)$/D',$name) || !str_starts_with($name,($m['id'] ?? '').'.')) throw new Failure('Chemin média invalide.',503); return $this->dir.'/media/'.$name;
    }
    public function readMedia(array $m): string { $bytes=file_get_contents($this->mediaPath($m)); if(!is_string($bytes)) throw new Failure('Média illisible.',503); return $bytes; }
    public function writeMedia(array $m,string $bytes): void {
        $path=$this->mediaPath($m); if(is_file($path)) { if(!hash_equals(hash('sha256',$this->readMedia($m)),hash('sha256',$bytes))) throw new Failure('Conflit de média : fichier existant différent.',409); return; } $this->atomicWrite($path,$bytes,false);
    }
    public static function log(array &$s,string $event,string $detail=''): void { $s['audit'][]=['at'=>gmdate('c'),'event'=>$event,'detail'=>mb_substr(strip_tags($detail),0,200)]; $s['audit']=array_slice($s['audit'],-250); }
    public function audit(string $event,string $detail=''): void { $this->change(function(&$s)use($event,$detail){self::log($s,$event,$detail);}); }
    public function rate(string $kind,string $identity,int $max,int $window): void {
        $allowed=$this->change(function(&$s)use($kind,$identity,$max,$window) { $now=time(); $s['limits']=array_filter($s['limits'],fn($r)=>$r['end']>$now); if(count($s['limits'])>2000) return false;
            $key=hash_hmac('sha256',$kind.':'.$identity,$s['secret']); $r=$s['limits'][$key] ?? ['end'=>$now+$window,'n'=>0]; $r['n']++; $s['limits'][$key]=$r; return $r['n']<=$max; });
        if(!$allowed) throw new Failure('Trop de tentatives. Réessayez plus tard.',429);
    }
}
