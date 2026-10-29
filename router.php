<?php
// Local development only. Production must use the Apache/Nginx profiles.
if(PHP_SAPI!=='cli-server'){http_response_code(404);exit;}
$root=realpath($_SERVER['DOCUMENT_ROOT']??__DIR__);$mount=$root&&str_starts_with(__DIR__,$root.'/')?substr(__DIR__,strlen($root)):'';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/');
if($mount!==''&&str_starts_with($path,$mount.'/'))$path=substr($path,strlen($mount));
$deny=preg_match('~(?:^|/)\\.(?!well-known(?:/|$))|^/(?:core|storage|bin|tests|tools|sdk|docs|scripts|files|work)(?:/|$)|^/content/plugins/[^/]+/(?:bin|lib|engine|vendor|docs|tests|runtime)(?:/|$)~i',$path);
if($deny){http_response_code(404);exit;}
$entry=preg_match('~^/(index|admin|login|media)\\.php$~D',$path);
if($path==='/install.php')$entry=true;
if($entry)return false;
if(preg_match('~\\.(?:php[0-9]*|phtml|phar)(?:$|[/.])|\\.(?:json|lock|md|yml|yaml|txt|log|sql|sqlite[0-9]*|db|bak|old|orig|dist|ini|sh|command|py|cjs|zip|tar|gz|map|conf)$|^/(?:Dockerfile|LICENSE|NOTICE|Makefile|Vagrantfile|INSTALLATION\\.html)$~i',$path)){
 if(in_array($path,['/robots.txt','/security.txt','/.well-known/security.txt'],true)&&is_file(__DIR__.$path))return false;
 http_response_code(404);exit;
}
if(preg_match('~^/(?:assets|content/(?:plugins|themes)/[^/]+)/.*\\.(?:css|js|svg|png|jpe?g|gif|webp|ico|woff2?)$~i',$path)&&is_file(__DIR__.$path))return false;
$_SERVER['SCRIPT_NAME']=$mount.'/index.php';require __DIR__.'/index.php';return true;
