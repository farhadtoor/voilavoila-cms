<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;
final class Failure extends \RuntimeException {
    public function __construct(string $message, public int $status=400) { parent::__construct($message); }
}
/** SQLite repository with a state-array API compatible with native extensions. */
final class Store {
    public const GUARD="<?php http_response_code(404); exit; ?>\n";
    private \PDO $db;
    private bool $transaction=false;
    private const COLUMNS=['id','type','slug','status','title_fr','title_en','excerpt_fr','excerpt_en','body_fr','body_en','publish_at','created_at','updated_at','cover','pinned','menu','sort_order','version','tags','extra'];
    public function __construct(public string $dir) {
        if(!extension_loaded('pdo_sqlite')) throw new Failure('Activez PHP pdo_sqlite pour utiliser le CMS.',503);
        if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new Failure('Stockage indisponible.',503);
        if(!is_writable($dir)) throw new Failure('Le dossier storage doit être accessible en écriture.',503);
        foreach(['media','sessions','backups','legacy','logs'] as $part) if(!is_dir("$dir/$part") && !mkdir("$dir/$part",0700,true)) throw new Failure('Création du stockage impossible.',503);
        if(!is_file($dir.'/.htaccess') && file_put_contents($dir.'/.htaccess',"Require all denied\n")===false) throw new Failure('Protection du stockage impossible.',503);
        $lock=$this->lock(LOCK_EX);
        try {
            $this->db=new \PDO('sqlite:'.$dir.'/cms.sqlite',null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_TIMEOUT=>5]);
            chmod($dir.'/cms.sqlite',0600);
            $this->db->exec('PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON; PRAGMA synchronous=FULL; PRAGMA journal_mode=DELETE');
            $version=(int)$this->db->query('PRAGMA user_version')->fetchColumn();
            if(!in_array($version,[0,1],true)) throw new Failure('Version de base SQLite non prise en charge.',503);
            $this->db->exec('BEGIN IMMEDIATE'); $this->transaction=true;
            if($version===0) $this->createSchema();
            if(!$this->db->query('SELECT COUNT(*) FROM cms_state')->fetchColumn()) {
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
                $this->writeState([],$s);
            }
            $this->db->exec('COMMIT'); $this->transaction=false;
            if(isset($s['migration']) && $s['migration']['from']==='1.0') $this->archiveLegacyMedia($s['media']);
        } catch(\Throwable $err) {
            if($this->transaction) { try{$this->db->exec('ROLLBACK');}catch(\Throwable){} $this->transaction=false; }
            if($err instanceof Failure) throw $err;
            Logger::error('sqlite_initialization',$err->getMessage(),$err->getFile(),$err->getLine());
            throw new Failure('Ouverture ou migration SQLite impossible. Sources conservées. Consultez error.txt.',503);
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    public function databasePath(): string { return $this->dir.'/cms.sqlite'; }
    private function archiveLegacyMedia(array $media): void {
        $dest=$this->dir.'/legacy/media';
        if(!is_dir($dest) && !@mkdir($dest,0700,true)) { Logger::error('migration_archive','Archivage des anciennes enveloppes média impossible'); return; }
        foreach($media as $id=>$m) {
            $source=$this->dir.'/media/'.$id.'.php';
            if(is_file($source) && !is_file($dest.'/'.$id.'.php') && !@rename($source,$dest.'/'.$id.'.php')) Logger::error('migration_archive','Ancienne enveloppe conservée : '.$id);
        }
    }
    private function createSchema(): void {
        $this->db->exec("CREATE TABLE cms_state (id INTEGER PRIMARY KEY CHECK(id=1), data TEXT NOT NULL);
          CREATE TABLE contents (id TEXT PRIMARY KEY,type TEXT NOT NULL CHECK(type IN ('post','page')),slug TEXT NOT NULL UNIQUE,status TEXT NOT NULL CHECK(status IN ('draft','published','private','trash')),
          title_fr TEXT NOT NULL,title_en TEXT NOT NULL,excerpt_fr TEXT NOT NULL,excerpt_en TEXT NOT NULL,body_fr TEXT NOT NULL,body_en TEXT NOT NULL,publish_at INTEGER NOT NULL,created_at TEXT NOT NULL,updated_at TEXT NOT NULL,cover TEXT NOT NULL,
          pinned INTEGER NOT NULL CHECK(pinned IN (0,1)),menu INTEGER NOT NULL CHECK(menu IN (0,1)),sort_order INTEGER NOT NULL,version INTEGER NOT NULL CHECK(version>=1),tags TEXT NOT NULL,extra TEXT NOT NULL);
          CREATE INDEX contents_publication ON contents(type,status,publish_at); CREATE INDEX contents_modified ON contents(updated_at);
          CREATE TABLE revisions (content_id TEXT NOT NULL REFERENCES contents(id) ON DELETE CASCADE,position INTEGER NOT NULL,data TEXT NOT NULL,PRIMARY KEY(content_id,position)); PRAGMA user_version=1;");
    }
    private function lock(int $mode) {
        $f=fopen($this->dir.'/lock.php','c+b'); if(!$f || !flock($f,$mode)) throw new Failure('Verrouillage du stockage impossible.',503);
        if(fstat($f)['size']===0) { fwrite($f,self::GUARD); fflush($f); chmod($this->dir.'/lock.php',0600); } return $f;
    }
    private function readState(): array {
        $raw=$this->db->query('SELECT data FROM cms_state WHERE id=1')->fetchColumn(); if($raw===false) return [];
        $s=json_decode($raw,true,128,JSON_THROW_ON_ERROR); $s['contents']=[];
        foreach($this->db->query('SELECT * FROM contents ORDER BY created_at,id') as $r) {
            $c=$r; unset($c['extra'],$c['sort_order']); $c['order']=(int)$r['sort_order']; $c['pinned']=(bool)$r['pinned']; $c['menu']=(bool)$r['menu'];
            $c['version']=(int)$r['version']; $c['publish_at']=(int)$r['publish_at']; $c['tags']=json_decode($r['tags'],true,32,JSON_THROW_ON_ERROR);
            $c=array_merge(json_decode($r['extra'],true,64,JSON_THROW_ON_ERROR),$c); $c['revisions']=[]; $s['contents'][$r['id']]=$c;
        }
        foreach($this->db->query('SELECT content_id,position,data FROM revisions ORDER BY content_id,position') as $r) $s['contents'][$r['content_id']]['revisions'][]=json_decode($r['data'],true,64,JSON_THROW_ON_ERROR);
        return $s;
    }
    private function writeState(array $before,array $s): void {
        $contents=$s['contents'] ?? []; unset($s['contents']); $s['schema']=2; $json=json_encode($s,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(strlen($json)>8*1024*1024) throw new Failure('Réglages et données des extensions limités à 8 Mo.',413);
        $this->db->prepare('INSERT INTO cms_state(id,data) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET data=excluded.data')->execute([$json]);
        $old=$before['contents'] ?? []; $changed=[]; foreach($contents as $id=>$c) if(!isset($old[$id]) || $old[$id]!==$c) $changed[$id]=$c;
        $delete=$this->db->prepare('DELETE FROM contents WHERE id=?');
        foreach($old as $id=>$c) if(!isset($contents[$id]) || isset($changed[$id])) $delete->execute([$id]);
        $insert=$this->db->prepare('INSERT INTO contents('.implode(',',self::COLUMNS).') VALUES('.implode(',',array_fill(0,count(self::COLUMNS),'?')).')');
        $rev=$this->db->prepare('INSERT INTO revisions(content_id,position,data) VALUES(?,?,?)');
        foreach($changed as $id=>$c) {
            $values=[]; $known=array_fill_keys(array_merge(self::COLUMNS,['order','revisions']),true);
            foreach(self::COLUMNS as $key) $values[]=match($key) {
                'pinned','menu'=>(int)!empty($c[$key]),'sort_order'=>(int)($c['order'] ?? 10),'version'=>(int)($c['version'] ?? 1),'publish_at'=>(int)($c['publish_at'] ?? time()),
                'tags'=>json_encode($c['tags'] ?? [],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'extra'=>json_encode(array_diff_key($c,$known),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),default=>(string)($c[$key] ?? '')};
            $insert->execute($values);
            foreach(array_values(array_slice($c['revisions'] ?? [],-15)) as $n=>$snapshot) $rev->execute([$id,$n,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        }
    }
    public function read(): array {
        if($this->transaction) throw new Failure('Lecture imbriquée dans une transaction non autorisée.',500); $lock=$this->lock(LOCK_SH);
        try { $this->db->exec('BEGIN'); $this->transaction=true; $s=$this->readState(); $this->db->exec('COMMIT'); $this->transaction=false; return $s; }
        catch(\Throwable $e) { if($this->transaction) { $this->db->exec('ROLLBACK'); $this->transaction=false; } throw $e; }
        finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    public function change(callable $fn): mixed {
        if($this->transaction) throw new Failure('Transaction imbriquée non autorisée.',500); $lock=$this->lock(LOCK_EX);
        try { $this->db->exec('BEGIN IMMEDIATE'); $this->transaction=true; $s=$this->readState(); $before=$s; $result=$fn($s); if($s!==$before) $this->writeState($before,$s); $this->db->exec('COMMIT'); $this->transaction=false; return $result; }
        catch(\Throwable $e) { if($this->transaction) { $this->db->exec('ROLLBACK'); $this->transaction=false; } throw $e; }
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
