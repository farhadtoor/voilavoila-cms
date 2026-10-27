<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** One metadata owner, including API 1 themes which already print head tags. */
final class Metadata {
    public static function clean(array $input): array {
        $out=[];foreach(['fr','en'] as $l){$out['title_'.$l]=Content::text(\val($input,'title_'.$l),200);$out['description_'.$l]=Content::text(\val($input,'description_'.$l),320);}
        $out['exclude']=!empty($input['exclude']);$image=\val($input,'image');$out['image']=preg_match('/^[a-f0-9]{24}$/D',$image)?$image:'';return $out;
    }
    public static function absolute(App $app,string $path): string {
        $base=$app->state['settings']['canonical']??'';
        if($base==='' || !str_starts_with($path,\base_path().'/'))return '';
        return $base.substr($path,strlen(\base_path()));
    }
    public static function path(?array $content,string $language): string {
        if($content)return \url('index.php',['p'=>$content['slug'],'lang'=>$language]);
        $args=['lang'=>$language];
        if(\val($_GET,'feed')==='1' || (int)\val($_GET,'page','1')>1)$args['feed']='1';
        if((int)\val($_GET,'page','1')>1)$args['page']=(int)\val($_GET,'page');
        return \url('index.php',$args);
    }
    public static function resolve(App $app,?array $content,array $context=[]): array {
        $s=$app->state['settings'];$preview=\val($_GET,'preview')!=='';
        $missing=!empty($context['notFound']) || http_response_code()===404;
        $search=\val($_GET,'q')!=='';
        $noindex=$preview || $missing || $search || !empty($s['noindex']) || ($content&&!Content::visible($content));
        $title=$context['theme_title']??($content?Content::field($content,'title').' · '.$s['title']:$s['title']);
        $description=$context['theme_description']??($content?(Content::field($content,'excerpt')?:Content::summary(Content::field($content,'body'),160)):$s['tagline']);
        $image=$app->state['media'][$content['cover']??$s['cover']]??null;
        $data=['title'=>$title,'description'=>$description,'noindex'=>$noindex,'type'=>$content&&$content['type']==='post'?'article':'website','image'=>$image&&$image['public']&&str_starts_with($image['mime'],'image/')?$image['id']:'','structured'=>false];
        $data=\vv_filter('metadata_v1',$data,$app,$content);
        $data['title']=Content::text(is_string($data['title']??null)?$data['title']:$title,300);
        $data['description']=Content::text(is_string($data['description']??null)?$data['description']:$description,400);
        $data['noindex']=$noindex || !empty($data['noindex']);
        $data['robots']=$data['noindex']?'noindex, '.($preview||$missing?'nofollow':'follow'):'index, follow';
        $data['canonical']=$data['noindex']?'':self::absolute($app,self::path($content,\lang()));
        $m=$app->state['media'][$data['image']??'']??null;
        $data['image']=$m&&$m['public']&&str_starts_with($m['mime'],'image/')?self::absolute($app,\url('media.php',['id'=>$m['id']])):'';
        $data['alternates']=[];
        if($data['canonical']!=='')foreach(['fr','en'] as $language) {
            if($content && ($content['title_'.$language]??'')==='' && ($content['body_'.$language]??'')==='')continue;
            $data['alternates'][$language]=self::absolute($app,self::path($content,$language));
        }
        return $data;
    }
    private static function attrs(string $tag): array {
        preg_match_all('~([a-zA-Z_:][a-zA-Z0-9_.:-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~s',$tag,$matches,PREG_SET_ORDER);
        $out=[];foreach($matches as $m)$out[strtolower($m[1])]=html_entity_decode(($m[2]??'')!==''?$m[2]:(($m[3]??'')!==''?$m[3]:($m[4]??'')),ENT_QUOTES|ENT_HTML5,'UTF-8');return $out;
    }
    private static function owned(string $name,array $attrs): bool {
        if($name==='meta')return in_array(strtolower($attrs['name']??''),['description','robots','twitter:card','twitter:title','twitter:description','twitter:image'],true) || in_array(strtolower($attrs['property']??''),['og:title','og:description','og:type','og:url','og:site_name','og:image','og:locale'],true);
        if($name==='link')return strtolower($attrs['rel']??'')==='canonical' || (strtolower($attrs['rel']??'')==='alternate'&&isset($attrs['hreflang'])) || parse_url($attrs['href']??'',PHP_URL_PATH)===\url('assets/brand.css');
        return false;
    }
    private static function head(App $app,?array $content,array $data): string {
        $h='<title>'.\e($data['title']).'</title><meta name="description" content="'.\e($data['description']).'"><meta name="robots" content="'.\e($data['robots']).'">';
        $h.='<link rel="stylesheet" href="'.\e(\url('assets/brand.css',['v'=>VV_VERSION])).'">';
        if($data['canonical']!=='')$h.='<link rel="canonical" href="'.\e($data['canonical']).'"><meta property="og:url" content="'.\e($data['canonical']).'">';
        foreach($data['alternates'] as $l=>$u)$h.='<link rel="alternate" hreflang="'.\e($l).'" href="'.\e($u).'">';
        foreach(['title'=>$data['title'],'description'=>$data['description'],'type'=>$data['type'],'site_name'=>$app->state['settings']['title'],'locale'=>\lang()==='en'?'en_GB':'fr_FR'] as $k=>$v)$h.='<meta property="og:'.$k.'" content="'.\e($v).'">';
        if($data['image']!=='')$h.='<meta property="og:image" content="'.\e($data['image']).'"><meta name="twitter:image" content="'.\e($data['image']).'">';
        $h.='<meta name="twitter:card" content="'.($data['image']!==''?'summary_large_image':'summary').'"><meta name="twitter:title" content="'.\e($data['title']).'"><meta name="twitter:description" content="'.\e($data['description']).'">';
        if(!empty($data['structured'])&&$data['canonical']!=='') {
            $schema=['@context'=>'https://schema.org','@type'=>$content&&$content['type']==='post'?'BlogPosting':'WebPage','headline'=>$data['title'],'description'=>$data['description'],'url'=>$data['canonical'],'inLanguage'=>\lang()];
            if($content){$schema['datePublished']=gmdate('c',$content['publish_at']);$schema['dateModified']=Content::timestamp($content['updated_at']);}
            if($data['image']!=='')$schema['image']=$data['image'];
            $h.='<script type="application/ld+json" nonce="'.\e(Security::$nonce).'" id="vv-seo-structured">'.json_encode($schema,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).'</script>';
        }
        return $h;
    }
    /** Lex only the head. Raw script/style bytes and the entire body are preserved. */
    public static function rewrite(string $html,App $app,?array $content,array $context=[]): string {
        $out='';$pos=0;$inside=false;
        while(($start=strpos($html,'<',$pos))!==false) {
            $out.=substr($html,$pos,$start-$pos);
            if(substr($html,$start,4)==='<!--'){$end=strpos($html,'-->',$start+4);if($end===false)return $html;$end+=3;$out.=substr($html,$start,$end-$start);$pos=$end;continue;}
            if(!preg_match('~\G<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~s',$html,$match,0,$start)){$out.='<';$pos=$start+1;continue;}
            $tag=$match[0];$name=strtolower($match[2]);$closing=$match[1]==='/';$end=$start+strlen($tag);
            if(!$closing&&in_array($name,['script','style','title','textarea','noscript','template'],true)) {
                if(!preg_match('~</'.$name.'\s*>~i',$html,$close,PREG_OFFSET_CAPTURE,$end))return $html;
                $finish=$close[0][1]+strlen($close[0][0]);
                if($inside&&$name==='title')$context['theme_title']??=html_entity_decode(strip_tags(substr($html,$end,$close[0][1]-$end)),ENT_QUOTES|ENT_HTML5,'UTF-8');
                elseif(!($inside&&$name==='script'&&(self::attrs($tag)['id']??'')==='vv-seo-structured'))$out.=substr($html,$start,$finish-$start);
                $pos=$finish;continue;
            }
            if($name==='head'&&!$closing)$inside=true;
            if($inside&&$name==='head'&&$closing) {
                $data=self::resolve($app,$content,$context);
                if($data['noindex'])header('X-Robots-Tag: '.$data['robots']);
                return $out.self::head($app,$content,$data).substr($html,$start);
            }
            $attrs=$inside?self::attrs($tag):[];
            if($inside&&$name==='meta'&&strtolower($attrs['name']??'')==='description')$context['theme_description']??=$attrs['content']??'';
            if(!$inside||!self::owned($name,$attrs))$out.=$tag;
            $pos=$end;
        }
        return $html;
    }
}
