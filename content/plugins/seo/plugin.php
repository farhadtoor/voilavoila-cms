<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
use VV\Content;use VV\Metadata;use VV\Failure;

vv_action('content_record_v1',function(array $record,?array $old,array $input):array {
    if(isset($input['seo_config'])) {
        $meta=[];foreach(['fr','en'] as $l){$meta['title_'.$l]=val($input,'seo_title_'.$l);$meta['description_'.$l]=val($input,'seo_description_'.$l);}
        $meta['exclude']=isset($input['seo_exclude']);$meta['image']=val($input,'seo_image');$record['seo']=Metadata::clean($meta);
    }elseif(is_array($input['seo']??null))$record['seo']=Metadata::clean($input['seo']);
    return $record;
});

vv_action('metadata_v1',function(array $data,$app,?array $current):array {
    $cfg=$app->state['plugin_data']['seo']??[];
    $home=val($_GET,'feed')!=='1'&&val($_GET,'q')===''&&(int)val($_GET,'page','1')===1;
    $meta=$current?($current['seo']??[]):($home?($cfg['home']??[]):[]);
    foreach(['title','description'] as $key){$v=($meta[$key.'_'.lang()]??'')?:($meta[$key.'_fr']??'')?:($meta[$key.'_en']??'');if($v!=='')$data[$key]=$v;}
    if(!empty($meta['exclude']))$data['noindex']=true;
    if(!empty($meta['image']))$data['image']=$meta['image'];
    $data['structured']=$cfg['structured']??true;return $data;
});
vv_action('editor_fields_v1',function($app,$content):void {
    $seo=$content['seo']??[];require __DIR__.'/fields.php';
});
vv_action('head',function($app):void {
    $full=Metadata::absolute($app,url('index.php',['cms'=>'rss','lang'=>lang()]));
    if($full!==''&&!$app->state['settings']['noindex'])echo '<link rel="alternate" type="application/rss+xml" title="RSS" href="'.e($full).'">';
});
vv_action('public_route',function($app):void {
    $route=val($_GET,'cms');if(!in_array($route,['sitemap','rss'],true))return;
    if(!$app->state['settings']['canonical']||$app->state['settings']['noindex']){http_response_code(404);exit;}
    header('Content-Type: application/xml; charset=utf-8');
    $xml=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_XML1,'UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    if($route==='sitemap'){
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        $emit=function(array $paths,string $updated='')use($app,$xml):void {
            foreach($paths as $l=>$path){echo '<url><loc>'.$xml(Metadata::absolute($app,$path)).'</loc>';if($updated!=='')echo '<lastmod>'.$xml(substr($updated,0,10)).'</lastmod>';
                foreach($paths as $alt=>$altPath)echo '<xhtml:link rel="alternate" hreflang="'.$alt.'" href="'.$xml(Metadata::absolute($app,$altPath)).'"/>';
                echo '</url>';}
        };
        $home=$app->state['plugin_data']['seo']['home']??[];
        if(empty($home['exclude']))$emit(['fr'=>url('index.php',['lang'=>'fr']),'en'=>url('index.php',['lang'=>'en'])]);
        $pages=max(1,(int)ceil(count($app->publicItems('post'))/$app->state['settings']['per_page']));
        for($p=1;$p<=$pages;$p++){$paths=[];foreach(['fr','en'] as $l)$paths[$l]=url('index.php',['feed'=>'1','lang'=>$l]+($p>1?['page'=>$p]:[]));$emit($paths);}
        foreach($app->state['contents'] as $c){if(!Content::visible($c)||!empty($c['seo']['exclude']))continue;$paths=[];
            foreach(['fr','en'] as $l)if(($c['title_'.$l]??'')!==''||($c['body_'.$l]??'')!=='')$paths[$l]=url('index.php',['p'=>$c['slug'],'lang'=>$l]);
            $emit($paths,$c['updated_at']);
        }echo '</urlset>';
    }else {
        echo '<rss version="2.0"><channel><title>'.$xml($app->state['settings']['title']).'</title><link>'.$xml($app->state['settings']['canonical']).'</link><description>'.$xml($app->state['settings']['tagline']).'</description><language>'.lang().'</language>';
        $posts=array_values(array_filter($app->publicItems('post'),fn($c)=>empty($c['seo']['exclude'])));
        foreach(array_slice($posts,0,30) as $c){$link=Metadata::absolute($app,Content::url($c));echo '<item><title>'.$xml(Content::field($c,'title')).'</title><link>'.$xml($link).'</link><guid isPermaLink="true">'.$xml($link).'</guid><pubDate>'.$xml(gmdate(DATE_RSS,$c['publish_at'])).'</pubDate><description>'.$xml(Content::field($c,'excerpt')?:Content::summary(Content::field($c,'body'),300)).'</description></item>';}
        echo '</channel></rss>';
    }exit;
});
$vv->adminPage('seo',t('Référencement SEO','SEO settings'),function($app):void {
    $cfg=$app->state['plugin_data']['seo']??[];$seo=$cfg['home']??[];require __DIR__.'/admin.php';
},function($app,array $input):void {
    $home=[];foreach(['fr','en'] as $l){$home['title_'.$l]=val($input,'seo_title_'.$l);$home['description_'.$l]=val($input,'seo_description_'.$l);}$home['exclude']=isset($input['seo_exclude']);$home['image']=val($input,'seo_image');
    $home=Metadata::clean($home);$cfg=['schema'=>1,'home'=>$home,'structured'=>isset($input['structured'])];
    $app->store->change(function(&$s)use($cfg){$s['plugin_data']['seo']=$cfg;VV\Store::log($s,'seo_settings_saved');});
});
