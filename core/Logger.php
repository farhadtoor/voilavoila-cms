<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;
/** Independent plain-text HTTP access and PHP/application error journals. */
final class Logger {
    private static ?string $dir=null;
    private static float $start=0;
    private static string $request='';
    private static bool $writing=false;
    public const MAX_BYTES=5242880;
    public static function init(string $storage): void {
        self::$start=microtime(true); self::$request=bin2hex(random_bytes(6)); $dir=$storage.'/logs';
        if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new Failure('Dossier des journaux inaccessible.',503);
        if(!is_writable($dir)) throw new Failure('Le dossier des journaux doit être accessible en écriture.',503);
        self::$dir=$dir;
        foreach(['access','error'] as $type) { $f=fopen($dir.'/'.$type.'.txt','ab'); if(!$f) throw new Failure('Création des journaux impossible.',503); fclose($f); chmod($dir.'/'.$type.'.txt',0600); }
        ini_set('log_errors','1'); ini_set('error_log',$dir.'/error.txt');
        set_error_handler(function(int $severity,string $message,string $file,int $line): bool {
            if(self::$writing) return false;
            if(!(error_reporting() & $severity)) return false;
            self::error('php_'.$severity,$message,$file,$line); return true;
        });
        register_shutdown_function(function(): void {
            $last=error_get_last();
            if($last && in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)) self::error('php_fatal',$last['message'],$last['file'],$last['line']);
            if(PHP_SAPI==='cli') return;
            $path=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
            self::append('access',gmdate('c').' request='.self::$request.' method='.self::clean($_SERVER['REQUEST_METHOD'] ?? 'GET',12).' path='.self::clean($path,350).' status='.(http_response_code() ?: 200).' ip='.self::clean($_SERVER['REMOTE_ADDR'] ?? '-',64).' duration_ms='.(int)round((microtime(true)-self::$start)*1000));
        });
    }
    private static function clean(string $value,int $max=2000): string { return substr(str_replace(["\r","\n","\t","\0"],['\\r','\\n','\\t',''],$value),0,$max); }
    private static function append(string $type,string $line): void {
        if(self::$writing) return;
        if(!self::$dir) { error_log('VV CMS '.$line); return; }
        self::$writing=true;
        $lock=@fopen(self::$dir.'/rotation.lock','c+b'); if(!$lock || !flock($lock,LOCK_EX)) { self::$writing=false; error_log('VV CMS: écriture du journal impossible'); return; }
        try {
            $path=self::$dir.'/'.$type.'.txt'; clearstatcache(true,$path);
            if(is_file($path) && filesize($path)>=self::MAX_BYTES) {
                if(is_file(self::$dir.'/'.$type.'.3.txt')) unlink(self::$dir.'/'.$type.'.3.txt');
                for($i=2;$i>=1;$i--) if(is_file(self::$dir.'/'.$type.'.'.$i.'.txt')) rename(self::$dir.'/'.$type.'.'.$i.'.txt',self::$dir.'/'.$type.'.'.($i+1).'.txt');
                rename($path,self::$dir.'/'.$type.'.1.txt');
            }
            if(file_put_contents($path,$line."\n",FILE_APPEND)===false) { error_log('VV CMS: journal inaccessible'); return; }
            chmod($path,0600);
        } finally { flock($lock,LOCK_UN); fclose($lock); self::$writing=false; }
    }
    public static function error(string $kind,string $message,string $file='',int $line=0): void {
        self::append('error',gmdate('c').' [ERROR] request='.self::$request.' kind='.self::clean($kind,60).' message='.self::clean($message).($file!==''?' source='.self::clean(basename($file),120).':'.$line:''));
    }
    public static function read(string $type,bool $tail=false): string {
        if(!in_array($type,['access','error'],true) || !self::$dir) throw new Failure('Journal invalide.');
        $lock=fopen(self::$dir.'/rotation.lock','c+b'); if(!$lock || !flock($lock,LOCK_SH)) throw new Failure('Journal momentanément indisponible.',503);
        try {
            $f=fopen(self::$dir.'/'.$type.'.txt','rb'); if(!$f) return '';
            try { $size=fstat($f)['size']; if($tail && $size>24000) { fseek($f,-24000,SEEK_END); fgets($f); } $text=stream_get_contents($f); return $tail?implode("\n",array_slice(explode("\n",rtrim($text)),-12)):$text; }
            finally { fclose($f); }
        } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
}
