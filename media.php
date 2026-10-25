<?php
declare(strict_types=1);
require __DIR__.'/core/bootstrap.php';
$id=val($_GET,'id'); $m=$vv->state['media'][$id] ?? null;
if(!$m || !preg_match('/^[a-f0-9]{24}$/D',$id)) { http_response_code(404); exit; }
if(!$m['public']) {
    \VV\Security::session($vv->store);
    if(!\VV\Security::owner($vv->store)) { http_response_code(404); exit; }
}
$path=$vv->store->dir.'/media/'.$id.'.php';
if(!is_file($path)) { http_response_code(404); exit; }
header("Content-Security-Policy: sandbox; default-src 'none'");
header('Content-Type: '.$m['mime']); header('Content-Length: '.$m['size']);
header('Cache-Control: '.($m['public']?'public, max-age=300':'private, no-store'));
$attachment=$m['mime']==='application/pdf' || val($_GET,'download')==='1';
header('Content-Disposition: '.($attachment?'attachment':'inline')."; filename=\"media-".$id.($m['mime']==='application/pdf'?'.pdf':'.png')."\"; filename*=UTF-8''".rawurlencode($m['name']));
$f=fopen($path,'rb'); fseek($f,strlen(\VV\Store::GUARD)); fpassthru($f); fclose($f);
