<?php
declare(strict_types=1);
namespace VV\WPPorter;
defined('VV_ROOT') || exit;

/** Three private, guarded outputs, retained for at most 24 hours on next access. */
final class Jobs {
    private const TTL=86400;
    public function __construct(private object $store) {}
    private function base(): string {return $this->store->dir.'/wp-porter';}
    private function lock(callable $fn): mixed {
        $base=$this->base();
        if(is_link($base)) throw new PortError('Stockage de portage invalide.','Invalid porting storage.',503);
        if(!is_dir($base) && !mkdir($base,0700)) throw new PortError('Stockage de portage indisponible.','Cannot create porting storage.',503);
        $path=$base.'/lock.php';
        if(is_link($path)) throw new PortError('Verrou invalide.','Invalid lock.',503);
        $f=fopen($path,'c+b');
        if(!$f || !flock($f,LOCK_EX)) throw new PortError('Verrouillage indisponible.','Cannot lock porting storage.',503);
        try {
            if(fstat($f)['size']===0) {fwrite($f,\VV\Store::GUARD);fflush($f);chmod($path,0600);}
            return $fn();
        } finally {flock($f,LOCK_UN);fclose($f);}
    }
    private function rows(): array {
        $rows=[];
        foreach(glob($this->base().'/job-*/report.php') as $path) {
            $id=substr(basename(dirname($path)),4);
            if(!preg_match('/^[a-f0-9]{24}$/D',$id) || is_link(dirname($path)) || is_link($path)) continue;
            try {$r=json_decode($this->store->privateRead($path),true,64,JSON_THROW_ON_ERROR);}
            catch(\Throwable) {continue;}
            if(!is_array($r) || !is_int($r['saved_at'] ?? null)) continue;
            $rows[$id]=$r;
        }
        uasort($rows,fn($a,$b)=>($b['saved_microseconds'] ?? $b['saved_at']*1000000)<=>($a['saved_microseconds'] ?? $a['saved_at']*1000000));return $rows;
    }
    private function prune(int $keep=3): void {
        $kept=0;
        foreach($this->rows() as $id=>$row) {
            if($row['saved_at']<time()-self::TTL || $kept>=$keep) $this->remove($id);
            else $kept++;
        }
    }
    private function remove(string $id): void {
        $dir=$this->base().'/job-'.$id;
        foreach(['report.php','package.php'] as $file) if(is_file($dir.'/'.$file) && !is_link($dir.'/'.$file)) unlink($dir.'/'.$file);
        if(is_dir($dir) && !is_link($dir)) rmdir($dir);
    }
    public function all(): array {return $this->lock(function(){$this->prune();return $this->rows();});}
    public function save(array $result): string {
        return $this->lock(function()use($result) {
            $this->prune();$id=bin2hex(random_bytes(12));$dir=$this->base().'/job-'.$id;
            if(!mkdir($dir,0700)) throw new PortError('Création du résultat impossible.','Cannot create result directory.',503);
            try {
                $this->store->privateWrite($dir.'/package.php',$result['bytes']);
                $record=['saved_at'=>time(),'saved_microseconds'=>(int)(microtime(true)*1000000),'filename'=>$result['filename'],'report'=>$result['report']];
                $this->store->privateWrite($dir.'/report.php',Generator::json($record));
                $this->prune(3);
                return $id;
            } catch(\Throwable $e) {$this->remove($id);throw $e;}
        });
    }
    public function read(string $id,bool $package=false): array {
        if(!preg_match('/^[a-f0-9]{24}$/D',$id)) throw new PortError('Résultat invalide.','Invalid result ID.');
        return $this->lock(function()use($id,$package) {
            $this->prune();$rows=$this->rows();
            if(!isset($rows[$id])) throw new PortError('Résultat expiré ou introuvable.','Result expired or not found.',404);
            $row=$rows[$id];
            if($package) {
                $path=$this->base().'/job-'.$id.'/package.php';
                if(is_link($path)) throw new PortError('Résultat invalide.','Invalid result file.',503);
                $row['bytes']=$this->store->privateRead($path);
            }
            return $row;
        });
    }
    public function delete(string $id): void {
        if(!preg_match('/^[a-f0-9]{24}$/D',$id)) throw new PortError('Résultat invalide.','Invalid result ID.');
        $this->lock(function()use($id){$this->remove($id);});
    }
}
