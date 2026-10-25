<?php
// Router for the local PHP development server only.
if(PHP_SAPI!=='cli-server') { http_response_code(404); exit; }
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if(preg_match('~(?:^|/)(?:\.|core/|storage/|bin/|sdk/|docs/|tests/|tools/)~',$path) || preg_match('~^/content/(?:plugins|themes)/.*\.php$~i',$path) || preg_match('~\.(?:md|log|bak|yaml|command)$~i',$path)) { http_response_code(404); exit; }
return false;
