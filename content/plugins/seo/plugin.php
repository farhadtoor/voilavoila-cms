<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
$absolute=function($app,string $path): string {
    $canonical=$app->state['settings']['canonical']; if(!$canonical) return '';
    return $canonical.substr($path,strlen(base_path()));
};
vv_action('head',function($app,$current) use($absolute) {
    $s=$app->state['settings'];
    $path=$current?\VV\Content::url($current):url('index.php',['lang'=>lang()]); $full=$absolute($app,$path);
    // Private previews and missing pages must never advertise a public canonical.
    if(val($_GET,'preview')!=='' || http_response_code()===404) return;
    $title=$current?\VV\Content::field($current,'title'):$s['title'];
    if($full) echo '<link rel="canonical" href="'.e($full).'"><meta property="og:url" content="'.e($full).'">';
    echo '<meta property="og:title" content="'.e($title).'"><meta property="og:type" content="'.($current && $current['type']==='post'?'article':'website').'"><meta property="og:site_name" content="'.e($s['title']).'">';
    $cover=$current['cover'] ?? $s['cover']; if($cover && $full) echo '<meta property="og:image" content="'.e($absolute($app,url('media.php',['id'=>$cover]))).'">';
    if($s['canonical']) echo '<link rel="alternate" type="application/rss+xml" title="RSS" href="'.e($absolute($app,url('index.php',['cms'=>'rss']))).'">';
});
vv_action('public_route',function($app) use($absolute) {
    $route=val($_GET,'cms'); if(!in_array($route,['sitemap','rss'],true)) return;
    if(!$app->state['settings']['canonical'] || $app->state['settings']['noindex']) { http_response_code(404); exit; }
    header('Content-Type: application/xml; charset=utf-8');
    $xml=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES | ENT_XML1,'UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    if($route==='sitemap') {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.$xml($absolute($app,url('index.php'))).'</loc></url>';
        foreach($app->state['contents'] as $c) if(\VV\Content::visible($c)) echo '<url><loc>'.$xml($absolute($app,\VV\Content::url($c))).'</loc><lastmod>'.$xml(substr($c['updated_at'],0,10)).'</lastmod></url>';
        echo '</urlset>';
    } else {
        echo '<rss version="2.0"><channel><title>'.$xml($app->state['settings']['title']).'</title><link>'.$xml($app->state['settings']['canonical']).'</link><description>'.$xml($app->state['settings']['tagline']).'</description>';
        foreach(array_slice($app->publicItems('post'),0,30) as $c) {
            $link=$absolute($app,\VV\Content::url($c));
            echo '<item><title>'.$xml(\VV\Content::field($c,'title')).'</title><link>'.$xml($link).'</link><guid isPermaLink="true">'.$xml($link).'</guid><pubDate>'.$xml(gmdate(DATE_RSS,$c['publish_at'])).'</pubDate><description>'.$xml(\VV\Content::field($c,'excerpt') ?: \VV\Content::summary(\VV\Content::field($c,'body'),300)).'</description></item>';
        }
        echo '</channel></rss>';
    }
    exit;
});
