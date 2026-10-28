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
        $store->change(function(&$s) use (&$c,$id,$version,$data) {
            $old=$s['contents'][$id] ?? null;
            if(($old['version'] ?? 0)!==$version) throw new Failure('Ce contenu a changé dans un autre onglet. Copiez votre texte puis rechargez.',409);
            foreach($s['contents'] as $other) if($other['id']!==$id && $other['slug']===$c['slug']) throw new Failure('Ce lien est déjà utilisé. Modifiez le slug.',409);
            if($c['cover']!=='' && (!isset($s['media'][$c['cover']]) || !str_starts_with($s['media'][$c['cover']]['mime'],'image/') || !$s['media'][$c['cover']]['public'])) throw new Failure('Choisissez une image publique comme couverture.');
            $revisions=$old['revisions'] ?? [];
            if($old) { unset($old['revisions']); $revisions[]=$old; }
            $c['revisions']=array_slice($revisions,-15); $c['version']=$version+1;
            foreach(['wp_key','wp_source','wp_id','wp_link','comments_open','seo','nav_label_fr','nav_label_en'] as $key) if(isset($old[$key])) $c[$key]=$old[$key];
            if(isset($data['comments_config'])) $c['comments_open']=isset($data['comments_open']);
            $c=\vv_filter('content_record_v1',$c,$old,$data);
            $c['created_at']=$old['created_at'] ?? gmdate('c'); $c['updated_at']=gmdate('c');
            $s['contents'][$id]=$c; Store::log($s,'content_saved',$c['title_fr'] ?: $c['title_en']);
        });
        return $id;
    }
    public static function upload(Store $store,array $file,bool $public,string $alt=''): string {
        return Media::upload($store,$file,$public,$alt);
    }
}
