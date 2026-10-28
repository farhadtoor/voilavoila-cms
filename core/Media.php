<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class Media {
    private const TYPES=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','zip'=>'application/zip','csv'=>'text/csv'];
    public static function document(array $m): bool { return !str_starts_with($m['mime'] ?? '','image/'); }
    private static function csv(string $bytes): void {
        if(!mb_check_encoding($bytes,'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$bytes) || preg_match('/<\?(?:php|=)|<\s*(?:script|html|svg|iframe|!doctype)\b/i',$bytes)) throw new Failure('CSV UTF-8 textuel requis, sans contenu actif.');
        // Original formulae remain data; opening untrusted CSV in a spreadsheet needs care.
        $lines=preg_split('/\r\n|\n|\r/',$bytes);
        if(count($lines)>100000) throw new Failure('CSV limité à 100 000 lignes.',413);
        foreach($lines as $line) if(strlen($line)>65536) throw new Failure('Ligne CSV trop longue.',413);
    }
    private static function archive(string $bytes,bool $docx): void {
        $rows=Security::documentArchive($bytes);
        if($docx) {
            foreach(['[Content_Types].xml','_rels/.rels','word/document.xml'] as $n) if(!isset($rows[$n])) throw new Failure('Structure DOCX incomplète.');
            $types=Security::xmlDocument($rows['[Content_Types].xml']); $main=false;
            if($types->documentElement?->localName!=='Types' || $types->documentElement?->namespaceURI!=='http://schemas.openxmlformats.org/package/2006/content-types') throw new Failure('Types de contenu DOCX invalides.');
            foreach($types->getElementsByTagName('*') as $node) {
                $type=strtolower($node->getAttribute('ContentType'));
                if(preg_match('/macro|vba|activex|oleobject/',$type)) throw new Failure('Macros et objets actifs interdits.');
                if($node->localName==='Override' && $node->getAttribute('PartName')==='/word/document.xml' && $type==='application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml') $main=true;
            }
            if(!$main) throw new Failure('Type du document DOCX invalide.');
            foreach($rows as $n=>$body) {
                if(preg_match('~(?:^|/)(?:embeddings|activex|customui)(?:/|$)|vba|\.bin$~i',$n) || preg_match('~(?:^|/)\.(?!rels$)~',$n)) throw new Failure('Objet incorporé ou fichier caché interdit dans le DOCX.');
                $ext=strtolower(pathinfo($n,PATHINFO_EXTENSION));
                if(in_array($ext,['xml','rels'],true)) {
                    $xml=Security::xmlDocument($body);
                    if($xml->getElementsByTagNameNS('*','altChunk')->length) throw new Failure('Contenu actif externe interdit dans le DOCX.');
                    foreach($xml->getElementsByTagNameNS('*','instrText') as $field) if(preg_match('/\b(?:DDE(?:AUTO)?|INCLUDETEXT|INCLUDEPICTURE|DATABASE|LINK)\b/i',$field->textContent)) throw new Failure('Champ actif interdit dans le DOCX.');
                    foreach($xml->getElementsByTagNameNS('*','fldSimple') as $field) foreach($field->attributes as $attribute) if($attribute->localName==='instr' && preg_match('/\b(?:DDE(?:AUTO)?|INCLUDETEXT|INCLUDEPICTURE|DATABASE|LINK)\b/i',$attribute->value)) throw new Failure('Champ actif interdit dans le DOCX.');
                    if($ext==='rels') {
                        if($xml->documentElement?->localName!=='Relationships' || $xml->documentElement?->namespaceURI!=='http://schemas.openxmlformats.org/package/2006/relationships') throw new Failure('Relations DOCX invalides.');
                        foreach($xml->getElementsByTagNameNS('*','Relationship') as $rel) {
                        $target=$rel->getAttribute('Target');
                        if(strcasecmp($rel->getAttribute('TargetMode'),'External')===0 || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)|[\x00-\x20\\\\]~i',$target)) throw new Failure('Relation externe interdite dans le DOCX.');
                        $part=rawurldecode(explode('#',$target,2)[0]); $base=str_contains($n,'/_rels/')?substr($n,0,strpos($n,'/_rels/')+1):'';
                        $parts=[];
                        foreach(explode('/',str_starts_with($part,'/')?ltrim($part,'/'):$base.$part) as $segment) {
                            if($segment==='' || $segment==='.') continue;
                            if($segment==='..') { if(!$parts) throw new Failure('Relation DOCX hors archive.'); array_pop($parts); }
                            else $parts[]=$segment;
                        }
                        if(!$parts || !isset($rows[implode('/',$parts)])) throw new Failure('Cible de relation DOCX absente.');
                        }
                    }
                    if($n==='word/document.xml' && ($xml->documentElement?->localName!=='document' || !in_array($xml->documentElement?->namespaceURI,['http://schemas.openxmlformats.org/wordprocessingml/2006/main','http://purl.oclc.org/ooxml/wordprocessingml/main'],true))) throw new Failure('Corps DOCX invalide.');
                } elseif(in_array($ext,['png','jpg','jpeg','webp'],true)) self::metadata(['id'=>str_repeat('a',24),'name'=>basename($n)],$body);
                else throw new Failure('Format incorporé non autorisé dans le DOCX.');
            }
        } else {
            foreach($rows as $n=>$body) {
                if(preg_match('~(?:^|/)\.~',$n)) throw new Failure('Fichier caché interdit dans le ZIP.');
                Security::documentName(basename($n)); $ext=strtolower(pathinfo($n,PATHINFO_EXTENSION));
                if(in_array($ext,['jpg','jpeg','png','webp','pdf','csv'],true)) self::metadata(['id'=>str_repeat('b',24),'name'=>basename($n)],$body);
                elseif(in_array($ext,['txt','md'],true)) { if(!mb_check_encoding($body,'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$body)) throw new Failure('Texte UTF-8 requis dans le ZIP.'); }
                else throw new Failure('ZIP de documents uniquement : images, PDF, CSV, TXT et MD. Archives imbriquées et programmes refusés.');
            }
            if(!$rows) throw new Failure('Archive ZIP vide.');
        }
    }
    public static function metadata(array $m,string $bytes,bool $legacy=false): array {
        if(strlen($bytes)<1 || strlen($bytes)>8*1024*1024) throw new Failure('Fichier limité à 8 Mo.',413);
        $id=$m['id'] ?? ''; if(!is_string($id) || !preg_match('/^[a-f0-9]{24}$/D',$id)) throw new Failure('Identifiant média invalide.');
        if(preg_match('/<\\?(?:php|=)/i',$bytes))throw new Failure('Code exécutable interdit dans un média.');
        $name=Security::documentName(is_string($m['name'] ?? null)?$m['name']:'media');
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION)); $detected=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if($legacy && (self::TYPES[$ext] ?? '')!==$detected) {
            $ext=match($detected){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf',default=>''};
            if(!$ext) throw new Failure('Format de média hérité non autorisé.');
            $name=pathinfo($name,PATHINFO_FILENAME).'.'.$ext;
        }
        $mime=self::TYPES[$ext] ?? ''; if(!$mime) throw new Failure('Formats autorisés : JPG, JPEG, PNG, WebP, PDF, DOCX, ZIP et CSV.');
        $accepted=match($ext){'docx'=>[self::TYPES['docx'],'application/zip','application/x-zip-compressed'],'zip'=>['application/zip','application/x-zip-compressed'],'csv'=>['text/plain','text/csv','application/csv'],default=>[$mime]};
        if(!in_array($detected,$accepted,true)) throw new Failure('Extension et type MIME du fichier incompatibles.');
        if($ext==='docx' || $ext==='zip') self::archive($bytes,$ext==='docx');
        elseif($ext==='csv') self::csv($bytes);
        elseif($ext==='pdf') {
            if(!preg_match('/^%PDF-[12]\\.[0-9]/',$bytes)||!str_contains(substr($bytes,-2048),'%%EOF'))throw new Failure('Structure PDF invalide.');
            $names=preg_replace_callback('/#([a-fA-F0-9]{2})/',fn($m)=>chr(hexdec($m[1])),$bytes);
            if(preg_match('~/(?:JavaScript|JS|OpenAction|AA|Launch|EmbeddedFiles|RichMedia|XFA)\\b~i',$names))throw new Failure('Actions, scripts ou pièces jointes PDF interdits.');
        }
        else {
            $info=@getimagesizefromstring($bytes);
            if(!$info || ($info['mime'] ?? '')!==$mime || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>8000000) throw new Failure('Image invalide ou trop grande : 8 mégapixels et 6 000 px maximum.');
            if(!extension_loaded('gd')) throw new Failure('Activez PHP GD pour valider les images.',503);
            $memory=ini_get('memory_limit'); $limit=(int)$memory*match(strtolower(substr($memory,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
            if($limit>0 && memory_get_usage(true)+8*$info[0]*$info[1]+16*1024*1024>$limit) throw new Failure('Mémoire PHP insuffisante pour valider cette image.');
            $image=@imagecreatefromstring($bytes); if(!$image) throw new Failure('Image illisible.'); unset($image);
        }
        // Preserve original bytes and extension; shorten only very long display names.
        if(mb_strlen($name)>160) $name=mb_substr(pathinfo($name,PATHINFO_FILENAME),0,150).'.'.$ext;
        return array_merge($m,['id'=>$id,'name'=>$name,'mime'=>$mime,'extension'=>$ext,'stored_name'=>$id.'.'.$ext,'size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'public'=>!empty($m['public']),'alt'=>Content::text(is_string($m['alt'] ?? null)?$m['alt']:'',250),'created_at'=>is_string($m['created_at'] ?? null)?$m['created_at']:gmdate('c')]);
    }
    public static function upload(Store $store,array $file,bool $public,string $alt=''): string {
        $id=Quarantine::capture($store,$file,'media',8*1048576);
        Quarantine::validate($store,$id,static function(string $bytes,array $row)use($id,$public,$alt):array {
            return self::metadata(['id'=>$id,'name'=>$row['name'],'alt'=>$alt,'public'=>$public],$bytes);
        });
        return $id;
    }
    public static function release(Store $store,string $id,bool $reviewed): void {
        if(!$reviewed)throw new Failure('Confirmez l’examen du fichier avant sa libération.');
        $row=Quarantine::approved($store,$id,'media');$bytes=Quarantine::bytes($store,$id);
        $m=self::metadata($row['inspection'],$bytes);$m['approval']=['at'=>gmdate('c'),'scan'=>$row['scan']];
        $store->change(static function(&$s)use($store,$id,$m,$bytes):void {
            if(($s['quarantine'][$id]['status']??'')!=='ready')throw new Failure('Dépôt déjà traité.',409);
            if(count($s['media'])>=2000)throw new Failure('Médiathèque limitée à 2 000 fichiers.',413);
            $store->writeMedia($m,$bytes);$s['media'][$id]=$m;$s['quarantine'][$id]['status']='consumed';Store::log($s,'media_released',$id);
        });
        Quarantine::consume($store,$id);
    }
}
