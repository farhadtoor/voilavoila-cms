<?php
// Router for the local PHP development server only.
if(PHP_SAPI!=='cli-server') { http_response_code(404); exit; }
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
$documentRoot=realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
if($documentRoot && str_starts_with(__DIR__.DIRECTORY_SEPARATOR,$documentRoot.DIRECTORY_SEPARATOR)) {
    $mount=str_replace('\\','/',substr(__DIR__,strlen($documentRoot)));
    if($mount!=='' && str_starts_with($path,$mount.'/')) $path=substr($path,strlen($mount));
}
if(preg_match('~^/files(?:/|$)~i',$path) || preg_match('~(?:^|/)(?:\.|core/|storage/|bin/|sdk/|docs/|tests/|tools/)~',$path) || preg_match('~^/content/(?:plugins|themes)/.*\.php$~i',$path) || preg_match('~\.(?:md|log|bak|yaml|command)$~i',$path)) { http_response_code(404); exit; }
return false;
