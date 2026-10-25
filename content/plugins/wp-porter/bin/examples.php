<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
defined('VV_ROOT') || define('VV_ROOT',dirname(__DIR__));
require_once dirname(__DIR__).'/tests/Fixtures.php';
try {
    $dir=$argv[1] ?? '';
    if($dir==='' || file_exists($dir) || is_link($dir)) throw new RuntimeException('Usage: php bin/examples.php NEW_OUTPUT_DIRECTORY (must not already exist).');
    if(!mkdir($dir,0700,true)) throw new RuntimeException('Cannot create output directory.');
    foreach(\VV\WPPorter\Fixtures::all() as $name=>$files) \VV\WPPorter\Fixtures::zip($files,$dir.'/'.$name.'.zip');
    echo "Created four synthetic WordPress examples in: $dir\nNo code from those examples was executed.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
