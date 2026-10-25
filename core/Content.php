<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class Content {
    public static function text(string $value, int $max=200): string { return mb_substr(trim(strip_tags($value)),0,$max); }
    public static function summary(string $html,int $max=260): string {
        $plain=strip_tags(preg_replace('~</?(?:p|h[1-6]|li|div|br|blockquote|figure)[^>]*>~i',' ',$html));
        return mb_substr(trim(preg_replace('/\s+/u',' ',html_entity_decode($plain,ENT_QUOTES | ENT_HTML5,'UTF-8'))),0,$max);
    }
    public static function timestamp(string $value): string { $time=strtotime($value); return $time===false?gmdate('c'):gmdate('c',$time); }
    public static function relocateMedia(string $html): string {
        // A portable backup can move between a root installation and a subdirectory.
        return preg_replace_callback('~(?:https?://[^\s\x22\x27<>]+)?(?:/[a-zA-Z0-9_%.-]+)*/media\.php\?id=([a-f0-9]{24})(?:&amp;[^\x22\x27<>\s]*)?~',fn($m)=>\url('media.php',['id'=>$m[1]]),$html);
    }
    public static function slug(string $value): string {
        $value=mb_strtolower($value);
        $v=function_exists('iconv')?iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value):strtr($value,['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','œ'=>'oe','æ'=>'ae']);
        return trim(substr(preg_replace('/[^a-z0-9]+/','-',strtolower($v===false?$value:$v)),0,100),'-') ?: 'publication';
    }
    public static function link(string $url): string {
        $url=trim(html_entity_decode($url,ENT_QUOTES | ENT_HTML5,'UTF-8'));
        if (!$url || preg_match('/[\x00-\x20\x7f\\\\]/',$url) || str_starts_with($url,'//')) return '';
        if (str_starts_with($url,'#') || (str_starts_with($url,'/') && !str_starts_with($url,'//'))) return $url;
        $scheme=strtolower(parse_url($url,PHP_URL_SCHEME) ?: '');
        if (in_array($scheme,['https','http'],true) && filter_var($url,FILTER_VALIDATE_URL) && !parse_url($url,PHP_URL_USER)) return $url;
        if (in_array($scheme,['mailto','tel'],true) && strlen($url)<300) return $url;
        return '';
    }
    public static function imageUrl(string $value): string {
        $p=parse_url($value); if (!$p || isset($p['host']) || isset($p['scheme'])) return '';
        if (($p['path'] ?? '') !== \url('media.php')) return '';
        parse_str($p['query'] ?? '',$q);
        return isset($q['id']) && is_string($q['id']) && preg_match('/^[a-f0-9]{24}$/D',$q['id']) ? \url('media.php',['id'=>$q['id']]) : '';
    }
    /** Allow-list sanitizer: stored HTML never contains script, CSS, event handlers or remote images. */
    public static function html(string $html): string {
        if (strlen($html)>200000) throw new Failure('Contenu limité à 200 Ko.',413);
        $doc=new \DOMDocument('1.0','UTF-8'); $before=libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>',LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($before); }
        $allowed=['p','br','h1','h2','h3','h4','strong','b','em','i','u','s','ul','ol','li','blockquote','pre','code','hr','a','img','figure','figcaption','table','thead','tbody','tr','th','td','div','span'];
        $drop=['script','style','iframe','object','embed','svg','math','template','form','input','button','select','textarea','noscript','link','meta','base','audio','video'];
        $walk=function(\DOMNode $parent) use (&$walk,$allowed,$drop) {
            foreach(iterator_to_array($parent->childNodes) as $node) {
                if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction) { $parent->removeChild($node); continue; }
                if (!($node instanceof \DOMElement)) continue;
                $tag=strtolower($node->tagName);
                if (in_array($tag,$drop,true)) { $parent->removeChild($node); continue; }
                $walk($node);
                if (!in_array($tag,$allowed,true)) { while($node->firstChild) $parent->insertBefore($node->firstChild,$node); $parent->removeChild($node); continue; }
                $href=$node->getAttribute('href'); $src=$node->getAttribute('src'); $alt=$node->getAttribute('alt'); $title=$node->getAttribute('title');
                while($node->attributes->length) $node->removeAttributeNode($node->attributes->item(0));
                if ($title!=='') $node->setAttribute('title',self::text($title,250));
                if ($tag==='a' && ($safe=self::link($href))) { $node->setAttribute('href',$safe); $node->setAttribute('rel','noopener noreferrer'); }
                if ($tag==='img') {
                    $safe=self::imageUrl($src);
                    if (!$safe) { $parent->removeChild($node); continue; }
                    $node->setAttribute('src',$safe); $node->setAttribute('alt',self::text($alt,250)); $node->setAttribute('loading','lazy');
                }
            }
        };
        $body=$doc->getElementsByTagName('body')->item(0); if(!$body) return '';
        $walk($body); $out=''; foreach($body->childNodes as $node) $out.=$doc->saveHTML($node); return $out;
    }
    public static function visible(array $c): bool { return ($c['status'] ?? '')==='published' && ($c['publish_at'] ?? 0)<=time(); }
    public static function field(array $c,string $key): string { return (string)(($c[$key.'_'.\lang()] ?? '') ?: ($c[$key.'_fr'] ?? '') ?: ($c[$key.'_en'] ?? '')); }
    public static function url(array $c): string { return \url('index.php',['p'=>$c['slug'],'lang'=>\lang()]); }
    public static function save(Store $store,array $data): string {
        $id=\val($data,'id') ?: bin2hex(random_bytes(12));
        if(!preg_match('/^[a-f0-9]{24}$/D',$id)) throw new Failure('Identifiant invalide.');
        $type=\val($data,'type'); $status=\val($data,'status','draft');
        if(!in_array($type,['page','post'],true) || !in_array($status,['draft','published','private','trash'],true)) throw new Failure('Type ou statut invalide.');
        $c=['id'=>$id,'type'=>$type,'status'=>$status];
        foreach(['fr','en'] as $l) {
            $c['title_'.$l]=self::text(\val($data,'title_'.$l));
            $c['excerpt_'.$l]=self::text(\val($data,'excerpt_'.$l),400);
            $c['body_'.$l]=self::html(\val($data,'body_'.$l));
        }
        if(!$c['title_fr'] && !$c['title_en']) throw new Failure('Ajoutez un titre.');
        $c['slug']=self::slug(\val($data,'slug') ?: $c['title_fr'] ?: $c['title_en']);
        $c['tags']=array_values(array_unique(array_filter(array_map(fn($s)=>self::text($s,40),explode(',',\val($data,'tags'))))));
        $c['tags']=array_slice($c['tags'],0,12); $c['pinned']=isset($data['pinned']); $c['menu']=isset($data['menu']);
        $c['order']=max(0,min(999,(int)\val($data,'order','10'))); $c['cover']=\val($data,'cover');
        $date=\val($data,'publish_at');
        $c['publish_at']=$date!=='' ? (strtotime($date.' UTC') ?: 0) : time();
        if($c['publish_at']<1) throw new Failure('Date de publication invalide.');
        $version=(int)\val($data,'version','0');
        $store->change(function(&$s) use (&$c,$id,$version) {
            $old=$s['contents'][$id] ?? null;
            if(($old['version'] ?? 0)!==$version) throw new Failure('Ce contenu a changé dans un autre onglet. Copiez votre texte puis rechargez.',409);
            foreach($s['contents'] as $other) if($other['id']!==$id && $other['slug']===$c['slug']) throw new Failure('Ce lien est déjà utilisé. Modifiez le slug.',409);
            if($c['cover']!=='' && (!isset($s['media'][$c['cover']]) || !str_starts_with($s['media'][$c['cover']]['mime'],'image/') || !$s['media'][$c['cover']]['public'])) throw new Failure('Choisissez une image publique comme couverture.');
            $revisions=$old['revisions'] ?? [];
            if($old) { unset($old['revisions']); $revisions[]=$old; }
            $c['revisions']=array_slice($revisions,-15); $c['version']=$version+1;
            $c['created_at']=$old['created_at'] ?? gmdate('c'); $c['updated_at']=gmdate('c');
            $s['contents'][$id]=$c; Store::log($s,'content_saved',$c['title_fr'] ?: $c['title_en']);
        });
        return $id;
    }
    public static function upload(Store $store,array $file,bool $public,string $alt=''): string {
        if(($file['error'] ?? 1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) throw new Failure('Choisissez un fichier valide (8 Mo maximum).');
        $bytes=file_get_contents($file['tmp_name']); $name=self::text($file['name'] ?? 'media',160);
        if(strlen($bytes)<1 || strlen($bytes)>8*1024*1024) throw new Failure('Fichier limité à 8 Mo.',413);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes); $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        $types=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'];
        if(($types[$ext] ?? '')!==$mime || preg_match('/(?:^|\.)(php\d*|phtml|phar|html?|svg|js|htaccess)(?:\.|$)/i',$name)) throw new Failure('Formats autorisés : JPG, PNG, WebP et PDF.');
        if($mime!=='application/pdf') {
            if(!extension_loaded('gd')) throw new Failure('Activez PHP GD pour importer les images.',503);
            $info=@getimagesizefromstring($bytes);
            if(!$info || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>8000000) throw new Failure('Image trop grande : 8 mégapixels et 6 000 px maximum.');
            $memory=ini_get('memory_limit'); $unit=strtolower(substr($memory,-1));
            $limit=(int)$memory * match($unit){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
            if($limit>0 && memory_get_usage(true)+16*$info[0]*$info[1]+16*1024*1024>$limit) throw new Failure('Mémoire PHP insuffisante : réduisez les dimensions de l’image.');
            $src=@imagecreatefromstring($bytes); if(!$src) throw new Failure('Image illisible.');
            $ratio=min(1,2400/max($info[0],$info[1])); $w=max(1,(int)round($info[0]*$ratio)); $h=max(1,(int)round($info[1]*$ratio));
            $dst=imagecreatetruecolor($w,$h); imagealphablending($dst,false); imagesavealpha($dst,true);
            imagefill($dst,0,0,imagecolorallocatealpha($dst,0,0,0,127));
            imagecopyresampled($dst,$src,0,0,0,0,$w,$h,$info[0],$info[1]);
            ob_start(); $ok=imagepng($dst,null,6); $bytes=ob_get_clean(); imagedestroy($src); imagedestroy($dst);
            if(!$ok || strlen($bytes)>8*1024*1024) throw new Failure('Image trop volumineuse après traitement.');
            $mime='image/png'; $name=pathinfo($name,PATHINFO_FILENAME).'.png';
        } elseif(!str_starts_with($bytes,'%PDF-')) throw new Failure('PDF invalide.');
        $id=bin2hex(random_bytes(12));
        $store->privateWrite($store->dir.'/media/'.$id.'.php',$bytes);
        try {
            $store->change(function(&$s) use($id,$name,$mime,$bytes,$public,$alt) {
                if(count($s['media'])>=2000) throw new Failure('Médiathèque limitée à 2 000 fichiers.',413);
                $s['media'][$id]=['id'=>$id,'name'=>$name,'mime'=>$mime,'size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'public'=>$public,'alt'=>self::text($alt,250),'created_at'=>gmdate('c')];
                Store::log($s,'media_uploaded',$name);
            });
        } catch(\Throwable $e) { @unlink($store->dir.'/media/'.$id.'.php'); throw $e; }
        return $id;
    }
}
