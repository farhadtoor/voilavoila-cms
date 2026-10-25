<?php
declare(strict_types=1);
require __DIR__.'/core/bootstrap.php';
use VV\Content; use VV\Security;
if(!$vv->state['installed']) redirect(url('install.php'));
$preview=val($_GET,'preview'); $owner=false;
if($preview!=='' || $vv->state['settings']['maintenance']) {
    Security::session($vv->store); $owner=Security::owner($vv->store);
    header('Cache-Control: private, no-store');
}
if($vv->state['settings']['maintenance'] && !$owner) {
    http_response_code(503); header('Retry-After: 3600'); header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="'.e(lang()).'"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>'.e($vv->state['settings']['title']).'</title><link rel="stylesheet" href="'.e(url('assets/admin.css')).'"><main class="auth-card"><span class="eyebrow">'.e($vv->state['settings']['title']).'</span><h1>'.e(t('On prépare la suite.','Something new is coming.')).'</h1><p>'.e(t('Le site revient bientôt.','The website will be back soon.')).'</p></main></html>'; exit;
}
$vv->bootPlugins();
vv_do('public_route',$vv);
$current=null; $notFound=false; $query=Content::text(val($_GET,'q'),120); $slug=val($_GET,'p');
if($preview!=='') {
    if($owner) $current=$vv->state['contents'][$preview] ?? null;
    if(!$current) $notFound=true;
    header('X-Robots-Tag: noindex, nofollow');
} elseif($slug!=='') {
    foreach($vv->state['contents'] as $c) if($c['slug']===$slug && Content::visible($c)) { $current=$c; break; }
    if(!$current) $notFound=true;
} elseif(!$query && val($_GET,'feed')!== '1' && $vv->state['settings']['home_page']!=='') {
    $home=$vv->state['contents'][$vv->state['settings']['home_page']] ?? null;
    if($home && Content::visible($home)) $current=$home;
}
if($notFound) http_response_code(404);
if($vv->state['settings']['noindex']) header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=utf-8');
$items=$vv->publicItems('post',$query); $total=count($items); $page=max(1,(int)val($_GET,'page','1')); $per=$vv->state['settings']['per_page']; $items=array_slice($items,($page-1)*$per,$per);
$vv->template($notFound?'404':($current?($current['type']==='page'?'page':'single'):'home'),compact('current','notFound','items','total','page','per','query','owner','preview'));
