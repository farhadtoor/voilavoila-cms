<?php
declare(strict_types=1);
define('VV_WP_PROXY',true);
require __DIR__.'/core/bootstrap.php';
if(!$vv->state['installed']||!in_array('wp-runtime',$vv->state['plugins'],true)) {http_response_code(404);exit;}
require __DIR__.'/content/plugins/wp-runtime/FastCGI.php';
require __DIR__.'/content/plugins/wp-runtime/Runtime.php';
\VV\WordPress\Runtime::proxy($vv);
