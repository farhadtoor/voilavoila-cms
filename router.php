<?php
// Router for the local PHP development server only.
if(PHP_SAPI!=='cli-server') { http_response_code(404); exit; }
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
$documentRoot=realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
if($documentRoot && str_starts_with(__DIR__.DIRECTORY_SEPARATOR,$documentRoot.DIRECTORY_SEPARATOR)) {
    $mount=str_replace('\\','/',substr(__DIR__,strlen($documentRoot)));
    if($mount!=='' && str_starts_with($path,$mount.'/')) $path=substr($path,strlen($mount));
}
if(preg_match('~^/content/plugins/wp-runtime/engine(?:/|$)~i',$path)) { http_response_code(404); exit; }
if(str_starts_with($path,'/compat/')) { $_SERVER['SCRIPT_NAME']=($mount ?? '').'/wp.php'; require __DIR__.'/wp.php'; exit; }
if(preg_match('~^/assets/(?:ui|mod|core|admin|media)/~',$path)) { $_SERVER['SCRIPT_NAME']=($mount ?? '').'/wp-assets.php'; require __DIR__.'/wp-assets.php'; exit; }
if(preg_match('~^/files(?:/|$)~i',$path) || preg_match('~(?:^|/)(?:\.|core/|storage/|bin/|sdk/|docs/|tests/|tools/)~i',$path) || preg_match('~^/content/(?:plugins|themes)/.*\.php$~i',$path) || preg_match('~\.(?:md|log|bak|yaml|command)$~i',$path)) { http_response_code(404); exit; }
if(!is_file(__DIR__.$path)&&!is_dir(__DIR__.$path)){$_SERVER['SCRIPT_NAME']=($mount??'').'/index.php';require __DIR__.'/index.php';return true;}
return false;
