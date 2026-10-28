<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** Database contract v1: no PDO, SQL dialect or credentials escape this boundary. */
interface DatabaseDriver {
    public function initialized(): bool;
    public function begin(bool $write): void;
    public function commit(): void;
    public function rollback(): void;
    public function read(): array;
    public function write(array $before, array $state): void;
    public function location(): string;
    public function info(): array;
}

final class Database {
    public static function connect(string $directory): DatabaseDriver {
        $driver=getenv('VV_DB_DRIVER') ?: 'sqlite';
        if(!in_array($driver,['sqlite','mysql','pgsql'],true)) throw new Failure('VV_DB_DRIVER doit être sqlite, mysql ou pgsql.',503);
        return new PdoDatabase($directory,$driver);
    }
}

/** SQLite is the default. MySQL/PostgreSQL use a dedicated database, never a table prefix supplied by a request. */
final class PdoDatabase implements DatabaseDriver {
    private \PDO $db;
    private const COLUMNS=['id','type','slug','status','title_fr','title_en','excerpt_fr','excerpt_en','body_fr','body_en','publish_at','created_at','updated_at','cover','pinned','menu','sort_order','version','tags','extra'];
    public function __construct(private string $directory, private string $driver) {
        if(!in_array($driver,\PDO::getAvailableDrivers(),true)) throw new Failure('Activez le pilote PDO '.$driver.'.',503);
        $dsn=$driver==='sqlite'?'sqlite:'.$directory.'/cms.sqlite':(getenv('VV_DB_DSN') ?: '');
        if(!str_starts_with($dsn,$driver.':')) throw new Failure('DSN de base absent ou incompatible.',503);
        $options=[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC];
        if($driver==='mysql') $options[\PDO::ATTR_EMULATE_PREPARES]=false;
        $this->db=new \PDO($dsn,getenv('VV_DB_USER') ?: null,getenv('VV_DB_PASSWORD') ?: null,$options);
        if($driver==='sqlite') {
            chmod($directory.'/cms.sqlite',0600);
            $this->db->exec('PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON; PRAGMA synchronous=FULL; PRAGMA journal_mode=DELETE');
            $version=(int)$this->db->query('PRAGMA user_version')->fetchColumn();
            if(!in_array($version,[0,1],true)) throw new Failure('Schéma SQLite non pris en charge.',503);
            if($version===0) $this->schema();
        } else {
            if($driver==='mysql'){$this->db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_bin");$this->db->exec("SET time_zone='+00:00'");}else $this->db->exec("SET client_encoding TO 'UTF8'");
            $sql=$driver==='mysql'?"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='vv_schema'":"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=current_schema() AND table_name='vv_schema'";
            if(!(int)$this->db->query($sql)->fetchColumn()) {
                $tables=$driver==='mysql'?"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()":"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=current_schema()";
                if((int)$this->db->query($tables)->fetchColumn()>0)throw new Failure('Utilisez une base ou un schéma dédié vide.',503);
                $this->schema();
            }
            if((int)$this->db->query('SELECT version FROM vv_schema WHERE id=1')->fetchColumn()!==1) throw new Failure('Schéma de base non pris en charge.',503);
        }
    }
    private function schema(): void {
        $mysql=$this->driver==='mysql';
        $text=$mysql?'LONGTEXT':'TEXT'; $id=$mysql?'VARCHAR(24)':'TEXT'; $slug=$mysql?'VARCHAR(100)':'TEXT'; $short=$mysql?'VARCHAR(32)':'TEXT';
        $end=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin':'';
        $statements=[
            "CREATE TABLE IF NOT EXISTS cms_state (id INTEGER PRIMARY KEY CHECK(id=1), data $text NOT NULL)$end",
            "CREATE TABLE IF NOT EXISTS contents (id $id PRIMARY KEY,type VARCHAR(8) NOT NULL CHECK(type IN ('post','page')),slug $slug NOT NULL UNIQUE,status VARCHAR(12) NOT NULL CHECK(status IN ('draft','published','private','trash')),title_fr $text NOT NULL,title_en $text NOT NULL,excerpt_fr $text NOT NULL,excerpt_en $text NOT NULL,body_fr $text NOT NULL,body_en $text NOT NULL,publish_at BIGINT NOT NULL,created_at $short NOT NULL,updated_at $short NOT NULL,cover $id NOT NULL,pinned INTEGER NOT NULL CHECK(pinned IN (0,1)),menu INTEGER NOT NULL CHECK(menu IN (0,1)),sort_order INTEGER NOT NULL,version INTEGER NOT NULL CHECK(version>=1),tags $text NOT NULL,extra $text NOT NULL)$end",
            'CREATE INDEX contents_publication ON contents(type,status,publish_at)',
            'CREATE INDEX contents_modified ON contents(updated_at)',
            "CREATE TABLE IF NOT EXISTS revisions (content_id $id NOT NULL REFERENCES contents(id) ON DELETE CASCADE,position INTEGER NOT NULL,data $text NOT NULL,PRIMARY KEY(content_id,position)".($mysql?',FOREIGN KEY(content_id) REFERENCES contents(id) ON DELETE CASCADE':'').")$end",
        ];
        if($this->driver==='sqlite') $this->db->exec('BEGIN IMMEDIATE');
        try {
            foreach($statements as $sql) {
                try { $this->db->exec($sql); }
                catch(\PDOException $e) { if(!str_starts_with($sql,'CREATE INDEX') || !((string)$e->getCode()==='42P07'||($e->errorInfo[1]??0)===1061||str_contains($e->getMessage(),'already exists'))) throw $e; }
            }
            if($this->driver==='sqlite') { $this->db->exec('PRAGMA user_version=1'); $this->db->exec('COMMIT'); }
            else { $this->db->exec('CREATE TABLE IF NOT EXISTS vv_schema (id INTEGER PRIMARY KEY,version INTEGER NOT NULL)'.$end); $this->db->exec($mysql?'INSERT INTO vv_schema(id,version) VALUES(1,1) ON DUPLICATE KEY UPDATE version=1':'INSERT INTO vv_schema(id,version) VALUES(1,1) ON CONFLICT(id) DO NOTHING'); }
        } catch(\Throwable $e) { if($this->driver==='sqlite') $this->db->exec('ROLLBACK'); throw $e; }
    }
    public function initialized(): bool {return (int)$this->db->query('SELECT COUNT(*) FROM cms_state WHERE id=1')->fetchColumn()>0;}
    public function begin(bool $write): void {
        if($this->driver==='sqlite') $this->db->exec($write?'BEGIN IMMEDIATE':'BEGIN');
        else {
            if($this->driver==='mysql') $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->db->beginTransaction();
            if($write) $this->db->query('SELECT id FROM cms_state WHERE id=1 FOR UPDATE');
            elseif($this->driver==='pgsql') $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }
    public function commit(): void { $this->db->exec('COMMIT'); }
    public function rollback(): void { $this->db->exec('ROLLBACK'); }
    public function location(): string { return $this->driver==='sqlite'?$this->directory.'/cms.sqlite':$this->driver.' (configured server)'; }
    public function info(): array { return ['contract'=>1,'driver'=>$this->driver,'schema'=>1,'state_schema'=>2,'pdo'=>true,'separate_plugin_databases'=>true]; }
    public function read(): array {
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
    public function write(array $before,array $s): void {
        $contents=$s['contents'] ?? []; unset($s['contents']); $s['schema']=2; $json=json_encode($s,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(strlen($json)>8*1024*1024) throw new Failure('Réglages et données des extensions limités à 8 Mo.',413);
        $sql=$this->driver==='mysql'?'INSERT INTO cms_state(id,data) VALUES(1,?) ON DUPLICATE KEY UPDATE data=VALUES(data)':'INSERT INTO cms_state(id,data) VALUES(1,?) ON CONFLICT(id) DO UPDATE SET data=excluded.data';
        $this->db->prepare($sql)->execute([$json]);
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

}
