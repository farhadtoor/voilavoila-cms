<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** CLI-only complete database transfer. Contains owner secrets; never an editorial/public export. */
final class DatabaseTransfer {
    public static function export(Store $store,string $path): void {
        if(PHP_SAPI!=='cli')throw new Failure('Commande locale uniquement.',403);
        if($path===''||file_exists($path)||is_link($path))throw new Failure('Choisissez un nouveau fichier privé.');
        $state=$store->read();$payload=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(strlen($payload)>64*1048576)throw new Failure('Transfert limité à 64 Mo de données structurées.',413);
        $bytes=json_encode(['format'=>'voilavoila-database','contract'=>1,'created_at'=>gmdate('c'),'sha256'=>hash('sha256',$payload),'payload'=>$payload],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $handle=fopen($path,'xb');if(!$handle)throw new Failure('Fichier de transfert non accessible.',503);
        try{chmod($path,0600);$body=Store::GUARD.$bytes;$offset=0;while($offset<strlen($body)){$n=fwrite($handle,substr($body,$offset));if(!$n)throw new Failure('Écriture du transfert interrompue.',503);$offset+=$n;}fflush($handle);if(function_exists('fsync'))fsync($handle);}catch(\Throwable $e){fclose($handle);@unlink($path);throw $e;}fclose($handle);
    }
    public static function import(Store $store,string $path): void {
        if(PHP_SAPI!=='cli')throw new Failure('Commande locale uniquement.',403);
        if(!is_file($path)||is_link($path)||filesize($path)>130*1048576)throw new Failure('Fichier de transfert invalide.');
        $record=json_decode($store->privateRead($path),true,128,JSON_THROW_ON_ERROR);
        if(($record['format']??'')!=='voilavoila-database'||($record['contract']??0)!==1||!is_string($record['payload']??null)||!hash_equals($record['sha256']??'',hash('sha256',$record['payload'])))throw new Failure('Transfert incomplet ou altéré.');
        $state=json_decode($record['payload'],true,128,JSON_THROW_ON_ERROR);
        foreach(['settings','owner','contents','media','plugins','plugin_data','audit','limits']as$key)if(!is_array($state[$key]??null))throw new Failure('Section de base manquante.');
        if(($state['schema']??0)!==2||!is_bool($state['installed']??null)||!preg_match('/^[a-f0-9]{64}$/D',$state['secret']??''))throw new Failure('État de base incompatible.');
        if(count($state['contents'])>100000||count($state['media'])>2000)throw new Failure('Transfert hors limites.',413);
        foreach($state['contents']as$id=>$row){
            if(!is_string($id)||!preg_match('/^[a-f0-9]{24}$/D',$id)||!is_array($row)||($row['id']??'')!==$id||!in_array($row['type']??'',['page','post'],true)||!in_array($row['status']??'',['draft','published','private','trash'],true)||!is_array($row['revisions']??null))throw new Failure('Contenu de transfert invalide.');
        }
        $store->change(static function(&$target)use($state):void {
            if(!empty($target['installed'])||!empty($target['contents'])||!empty($target['media']))throw new Failure('Import autorisé uniquement vers une base neuve et non installée.',409);
            $target=$state;
        });
        if($state['installed'])@unlink($store->dir.'/setup-key.php');
    }
}
