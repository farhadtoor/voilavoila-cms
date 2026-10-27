<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** Approved local legacy URLs point directly to public native content IDs. */
final class Redirects {
    public const LIMIT=6000;
    public static function key(string $url): string {
        if(strlen($url)>2000 || str_starts_with($url,'//') || preg_match('~[\x00-\x20\x7f\\\\]|%2f|%5c|%00|%25~i',$url))throw new Failure('URL de redirection non sûre.');
        $p=parse_url($url);if(!$p||isset($p['user'])||isset($p['pass'])||isset($p['fragment']))throw new Failure('URL de redirection invalide.');
        if(isset($p['scheme'])&&!in_array(strtolower($p['scheme']),['http','https'],true))throw new Failure('Protocole de redirection invalide.');
        $path=rawurldecode($p['path']??'/');
        if(!mb_check_encoding($path,'UTF-8')||!str_starts_with($path,'/')||str_starts_with($path,'//')||preg_match('~[\x00-\x20\x7f\\\\?#]|(?:^|/)\.{1,2}(?:/|$)|//~',$path))throw new Failure('Chemin de redirection invalide.');
        $args=[];parse_str($p['query']??'',$args);$query=[];
        foreach($args as $k=>$v) {
            if($k==='lang'){if(!in_array($v,['fr','en'],true))throw new Failure('Langue de redirection invalide.');continue;}
            if(str_starts_with((string)$k,'utm_'))continue;
            if(!is_string($v)||!in_array($k,['p','page_id','name'],true))throw new Failure('Paramètres de redirection non pris en charge.');
            if($k!=='name'&&!preg_match('/^[1-9][0-9]{0,18}$/D',$v))throw new Failure('Identifiant WordPress invalide.');
            if($k==='name'&&(!preg_match('/^[\pL\pN_-]{1,200}$/uD',$v)))throw new Failure('Nom WordPress invalide.');
            $query[$k]=$v;
        }
        if(count($query)>1)throw new Failure('URL WordPress ambiguë.');
        $path=rtrim($path,'/')?:'/';ksort($query);
        if(in_array($path,['/',rtrim(\base_path(),'/')?:'/',\url('index.php')],true)&&!$query)throw new Failure('La page d’accueil ne peut pas être redirigée.');
        if(preg_match('~(?:^|/)(?:admin\.php|login\.php|install\.php|media\.php|wp\.php|wp-assets\.php|core|storage|bin|sdk|docs|tests|tools|assets|content)(?:/|$)~i',$path))throw new Failure('Ce chemin du CMS est réservé.');
        return $path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
    }
    public static function candidates(App $app): array {
        $out=[];
        foreach($app->state['contents'] as $c) {
            if(empty($c['wp_source'])||empty($c['wp_id'])||!Content::visible($c))continue;
            foreach([$c['wp_link']??'',rtrim($c['wp_source'],'/').'/?p='.$c['wp_id'],rtrim($c['wp_source'],'/').'/?page_id='.$c['wp_id']] as $from) {
                if($from==='')continue;
                try{$key=self::key($from);}catch(Failure){continue;}
                $out[$key]=['source'=>$key,'content_id'=>$c['id'],'language'=>'fr','enabled'=>true];
            }
        }
        if(count($out)>self::LIMIT)throw new Failure('Trop de règles de redirection.',413);
        return $out;
    }
    public static function validate(array $rows,array $contents): array {
        if(count($rows)>self::LIMIT)throw new Failure('Trop de redirections.',413);$out=[];$slugs=[];
        foreach($contents as $c)$slugs[$c['slug']]=true;
        foreach($rows as $r) {
            if(!is_array($r))throw new Failure('Règle de redirection invalide.');
            $source=self::key(\val($r,'source'));$id=\val($r,'content_id');
            if(!isset($contents[$id]))continue;
            $query=[];parse_str(parse_url($source,PHP_URL_QUERY)??'',$query);
            if(in_array(parse_url($source,PHP_URL_PATH),['/','/index.php',rtrim(\base_path(),'/'),\url('index.php')],true)&&isset($slugs[$query['p']??'']))throw new Failure('Une redirection masquerait un lien natif.',409);
            if(isset($out[$source])&&$out[$source]['content_id']!==$id)throw new Failure('Redirections en conflit.',409);
            $out[$source]=['source'=>$source,'content_id'=>$id,'language'=>\val($r,'language')==='en'?'en':'fr','enabled'=>!empty($r['enabled'])];
        }
        return $out;
    }
    public static function approve(App $app,string $expected): int {
        $candidates=self::candidates($app);
        return $app->store->change(function(&$s)use($candidates,$expected){if(!hash_equals(WordPress::fingerprint($s),$expected))throw new Failure('Les propositions ont changé. Rechargez et vérifiez les redirections.',409);$rows=$s['redirects']??[];foreach($candidates as $key=>$r){if(isset($rows[$key])&&$rows[$key]['content_id']!==$r['content_id'])throw new Failure('Une règle existante utilise déjà ce chemin.',409);$rows[$key]=$r;}$s['redirects']=self::validate($rows,$s['contents']);Store::log($s,'redirects_approved',(string)count($candidates));return count($candidates);});
    }
    public static function dispatch(App $app): void {
        if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true))return;
        foreach(['preview','passw','pass_api','vv_app','vv_asset','cms'] as $key)if(isset($_GET[$key]))return;
        $slug=\val($_GET,'p');if($slug!=='')foreach($app->state['contents'] as $c)if($c['slug']===$slug)return;
        try{$key=self::key($_SERVER['REQUEST_URI']??'/');}catch(Failure){return;}
        $keys=[$key];$base=\base_path();
        if($base!==''&&str_starts_with($key,$base.'/'))$keys[]=substr($key,strlen($base));
        if($base!==''&&str_starts_with($key,$base.'?'))$keys[]='/'.substr($key,strlen($base));
        foreach($keys as $candidate)if(str_starts_with($candidate,'/index.php?'))$keys[]='/'.substr($candidate,strlen('/index.php'));
        $rule=null;foreach($keys as $candidate)if(isset($app->state['redirects'][$candidate])){$rule=$app->state['redirects'][$candidate];break;}
        $content=$app->state['contents'][$rule['content_id']??'']??null;
        if(!$rule||empty($rule['enabled'])||!$content||!Content::visible($content))return;
        $language=in_array(\val($_GET,'lang'),['fr','en'],true)?\val($_GET,'lang'):$rule['language'];
        $target=\url('index.php',['p'=>$content['slug'],'lang'=>$language]);
        header('Cache-Control: private, no-store');header('Location: '.$target,true,301);exit;
    }
    public static function unmatchedPath(): bool {
        $path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
        return !in_array(rtrim($path,'/')?:'/',[rtrim(\base_path(),'/')?:'/',\url('index.php')],true);
    }
}
