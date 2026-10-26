<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,4).'/core/bootstrap.php';
defined('VV_ROOT') || exit;
require_once dirname(__DIR__).'/FastCGI.php';require_once dirname(__DIR__).'/Runtime.php';
use VV\WordPress\Runtime;
$command=$argv[1]??'help';
switch($command){
    case 'status':$cfg=Runtime::config($vv);echo json_encode(['enabled'=>Runtime::enabled($vv),'public_url'=>$cfg['public_url'],'compatibility'=>$cfg['compatibility']],JSON_PRETTY_PRINT).PHP_EOL;break;
    case 'sync':echo json_encode(Runtime::sync($vv),JSON_PRETTY_PRINT).PHP_EOL;break;
    case 'refresh':if(!in_array('--confirm=REFRESH',$argv,true))throw new \VV\Failure('Use --confirm=REFRESH after coordinating a private pool restart.');Runtime::refresh($vv);echo "Bridge templates refreshed; restart the private pool.\n";break;
    case 'prepare':$raw=stream_get_contents(STDIN,4097);if(strlen($raw)>4096)throw new \VV\Failure('Database JSON exceeds bound.');$db=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($db))throw new \VV\Failure('Database JSON required on stdin.');echo Runtime::prepare($vv,$argv[2]??'',$db).PHP_EOL;break;
    case 'plugin':case 'theme':echo Runtime::wrap($vv,$argv[2]??'',$command).PHP_EOL;break;
    default:echo "WordPress Engine 0.2: status | sync | refresh --confirm=REFRESH | prepare ZIP < private-db.json | plugin ZIP | theme ZIP\n";
}
