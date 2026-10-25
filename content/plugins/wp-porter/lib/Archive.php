<?php
declare(strict_types=1);
namespace VV\WPPorter;
defined('VV_ROOT') || exit;

final class PortError extends \RuntimeException {
    public function __construct(public string $fr, public string $en, public int $status=400) {
        parent::__construct(function_exists('lang') && \lang()==='fr' ? $fr : $en);
    }
}

/** In-memory, bounded ZIP inspection. Imported PHP is never loaded or executed. */
final class Archive {
    public const ZIP_MAX=12582912;
    public const EXPANDED_MAX=33554432;
    public array $files=[];
    public string $sha256;
    public string $root='';
    public array $ignored=[];

    public static function requirements(): array {
        $missing=[];
        if(PHP_VERSION_ID<80300) $missing[]='PHP 8.3+';
        foreach(['zip','tokenizer','mbstring','dom','fileinfo'] as $e) if(!extension_loaded($e)) $missing[]=$e;
        return $missing;
    }
    public function __construct(string $path) {
        if($missing=self::requirements()) throw new PortError('Prérequis manquants : '.implode(', ',$missing),'Missing requirements: '.implode(', ',$missing),503);
        if(!is_file($path) || !is_readable($path) || filesize($path)<1 || filesize($path)>self::ZIP_MAX) throw new PortError('ZIP limité à 12 Mio.','ZIP must be between 1 byte and 12 MiB.',413);
        $this->sha256=hash_file('sha256',$path);
        $zip=new \ZipArchive();
        if($zip->open($path)!==true) throw new PortError('ZIP illisible.','Cannot read ZIP.');
        try {
            if($zip->numFiles<1 || $zip->numFiles>1200) throw new PortError('ZIP limité à 1 200 entrées.','ZIP is limited to 1,200 entries.',413);
            $seen=[]; $rows=[]; $total=0;
            for($i=0;$i<$zip->numFiles;$i++) {
                $s=$zip->statIndex($i);
                if(!is_array($s)) throw new PortError('Entrée ZIP illisible.','Unreadable ZIP entry.');
                $n=$s['name'];
                if(!is_string($n) || !mb_check_encoding($n,'UTF-8') || strlen($n)>220 || $n==='' || preg_match('/[\x00-\x1f\x7f\\\\:]/u',$n) || str_starts_with($n,'/') || str_contains($n,'//') || preg_match('~(?:^|/)\.\.?(/|$)~',$n)) throw new PortError('Chemin ZIP refusé.','Unsafe ZIP path.');
                $key=mb_strtolower(rtrim($n,'/'),'UTF-8');
                if(isset($seen[$key])) throw new PortError('Chemins ZIP en double (casse comprise).','Duplicate ZIP path (case insensitive).');
                $seen[$key]=true;
                $ops=0; $attrs=0;
                if(!$zip->getExternalAttributesIndex($i,$ops,$attrs)) throw new PortError('Attributs ZIP illisibles.','Cannot inspect ZIP attributes.');
                $kind=($attrs>>16)&0170000;
                if($kind!==0 && !in_array($kind,[0100000,0040000],true)) throw new PortError('Liens et fichiers spéciaux refusés.','Links and special files are refused.');
                if(!empty($s['encryption_method'])) throw new PortError('ZIP chiffré refusé.','Encrypted ZIP entries are refused.');
                if($s['size']<0 || $s['size']>8388608 || ($total+=(int)$s['size'])>self::EXPANDED_MAX) throw new PortError('ZIP trop volumineux après décompression.','ZIP expanded size exceeds limits.',413);
                if($s['size']>65536 && $s['size']/max(1,(int)$s['comp_size'])>250) throw new PortError('Taux de compression excessif.','Excessive ZIP compression ratio.',413);
                if(str_ends_with($n,'/')) continue;
                if(str_starts_with($n,'__MACOSX/') || preg_match('~(?:^|/)(?:\.DS_Store|\._[^/]+)$~',$n)) { $this->ignored[]=$n; continue; }
                if(preg_match('~(?:^|/)\.env(?:$|\.(?!example$))~i',$n)) throw new PortError('Fichier .env privé refusé ; retirez-le de l’archive.','Private .env files are refused; remove them from the archive.');
                if(preg_match('~(?:^|/)\.~',$n)) {$this->ignored[]=$n;continue;}
                $bytes=$zip->getFromIndex($i);
                if(!is_string($bytes) || strlen($bytes)!==$s['size'] || sprintf('%u',crc32($bytes))!==sprintf('%u',$s['crc'])) throw new PortError('Intégrité ZIP incorrecte.','ZIP entry integrity check failed.');
                $rows[$n]=$bytes;
            }
            if(!$rows) throw new PortError('ZIP vide.','ZIP has no usable files.');
            $names=array_keys($rows); $first=explode('/',$names[0]);
            if(count($first)>1) {
                $prefix=$first[0].'/';
                if(!array_filter($names,fn($n)=>!str_starts_with($n,$prefix))) $this->root=$prefix;
            }
            foreach($rows as $n=>$bytes) $this->files[substr($n,strlen($this->root))]=$bytes;
        } finally { $zip->close(); }
    }
    public function get(string $path): ?string { return $this->files[$path] ?? null; }
    public static function clean(string $value,int $max=200): string {
        if(!mb_check_encoding($value,'UTF-8')) $value=mb_convert_encoding($value,'UTF-8','UTF-8');
        return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',strip_tags($value))),0,$max);
    }
    public static function header(string $bytes,string $field): string {
        $sample=substr($bytes,0,8192);
        $comments=[];
        if(preg_match('~^\s*(?:\xEF\xBB\xBF)?<\?php\b~i',$sample)) {
            foreach(token_get_all($sample) as $token) if(is_array($token) && in_array($token[0],[T_COMMENT,T_DOC_COMMENT],true)) $comments[]=$token[1];
        } else {
            preg_match_all('~/\*(.*?)\*/~s',$sample,$matches);$comments=$matches[1] ?? [];
        }
        foreach($comments as $comment) {
            $comment=preg_replace('~^/\*+|\*/$~','',$comment);
            if(preg_match('/^[\s\/*#]*'.preg_quote($field,'/').'\s*:\s*(.*?)\s*$/mi',$comment,$m)) return self::clean($m[1]);
        }
        return '';
    }
    public static function id(string $name): string {
        $s=function_exists('iconv')?iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name):$name;
        $s=strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',is_string($s)?$s:$name));
        $s=trim($s,'-'); if($s==='' || !preg_match('/^[a-z]/D',$s)) $s='module-'.$s;
        return 'ported-'.substr($s,0,36);
    }
}
