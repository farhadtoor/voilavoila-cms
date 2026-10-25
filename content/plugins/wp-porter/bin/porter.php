<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
defined('VV_ROOT') || define('VV_ROOT',dirname(__DIR__));
foreach(['Archive','Syntax','Compiler','Analyzer','Generator'] as $class) require_once dirname(__DIR__).'/lib/'.$class.'.php';
use VV\WPPorter\Archive;
use VV\WPPorter\Analyzer;
use VV\WPPorter\Generator;

try {
    $args=$argv;array_shift($args);$command=array_shift($args) ?? 'help';
    if(in_array($command,['help','--help','-h'],true)) {
        echo "WordPress Porter 1.0.0 — local static analysis; no WordPress code execution.\n\nphp bin/porter.php analyze source.zip [report.json] [--type auto|theme|plugin]\nphp bin/porter.php convert source.zip output.zip [--id native-id] [--name 'Native name'] [--type auto|theme|plugin]\n\nNo source changes, CMS initialization, installation or activation. Existing output files are refused.\n";exit;
    }
    $options=['type'=>'auto'];$paths=[];
    while($args) {
        $arg=array_shift($args);
        if(str_starts_with($arg,'--')) {
            $key=substr($arg,2);if(!in_array($key,['type','id','name'],true) || !$args) throw new RuntimeException('Invalid option: '.$arg);
            $options[$key]=array_shift($args);
        } else $paths[]=$arg;
    }
    if(!in_array($command,['analyze','analyse','convert'],true) || !isset($paths[0]) || count($paths)>2) throw new RuntimeException('Use --help for command syntax.');
    $source=new Archive($paths[0]);$analysis=Analyzer::analyze($source,$options['type']);
    if($command==='convert') {
        if(!isset($paths[1]) || strtolower(pathinfo($paths[1],PATHINFO_EXTENSION))!=='zip') throw new RuntimeException('A new .zip output path is required.');
        $result=Generator::build($source,$analysis,$options['id'] ?? $analysis['report']['target']['suggested_id'],$options['name'] ?? $analysis['report']['source']['name']);
        $bytes=$result['bytes'];$output=$paths[1];
    } else {$bytes=Generator::json($analysis['report']);$output=$paths[1] ?? '';}
    if($output==='') {echo $bytes;exit;}
    if(file_exists($output) || is_link($output)) throw new RuntimeException('Output already exists; nothing was overwritten.');
    $f=fopen($output,'xb');if(!$f) throw new RuntimeException('Cannot create output file.');chmod($output,0600);
    try {$offset=0;while($offset<strlen($bytes)) {$n=fwrite($f,substr($bytes,$offset));if(!$n) throw new RuntimeException('Output write failed.');$offset+=$n;}}
    finally {fclose($f);}
    echo 'Created: '.$output."\nStatus: ".$analysis['report']['status']."\nNo installation or activation performed.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
