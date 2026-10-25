<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
defined('VV_ROOT') || define('VV_ROOT',dirname(__DIR__));
foreach(['Archive','Syntax','Compiler','Analyzer','Generator'] as $class) require_once dirname(__DIR__).'/lib/'.$class.'.php';
require __DIR__.'/Fixtures.php';
use VV\WPPorter\{Archive,Analyzer,Generator,Fixtures,PortError,Compiler,Unsupported};
$root=sys_get_temp_dir().'/vv-porter-test-'.bin2hex(random_bytes(8));mkdir($root,0700);
$checks=[];
function check_porter(string $name,bool $condition): void {global $checks;if(!$condition) throw new RuntimeException('FAIL '.$name);$checks[]=$name;echo 'PASS '.$name."\n";}
function reject_porter(string $name,callable $fn): void {try {$fn();}catch(PortError|Unsupported $e){check_porter($name,true);return;}check_porter($name,false);}
function cleanup_porter(string $dir): void {foreach(new FilesystemIterator($dir,FilesystemIterator::SKIP_DOTS) as $f) {if($f->isDir() && !$f->isLink()) cleanup_porter($f->getPathname());else unlink($f->getPathname());}rmdir($dir);}
try {
    foreach(Fixtures::all() as $name=>$files) Fixtures::zip($files,$root.'/'.$name.'.zip');
    $source=new Archive($root.'/plugin-static.zip');$hash=hash_file('sha256',$root.'/plugin-static.zip');$a=Analyzer::analyze($source);
    check_porter('plugin headers and source fingerprint',$a['report']['source']['name']==='Demo Static Shortcodes' && $a['report']['source']['archive_sha256']===$hash);
    check_porter('constant named and anonymous shortcodes convert',$a['report']['status']==='converted' && count($a['program']['features'])===4);
    check_porter('simple content filter maps to native slots',$a['program']['features'][3]['kind']==='filter-content' && !empty($a['program']['features'][3]['parts'][0]['slot']));
    check_porter('footer hook maps to native behavior',$a['program']['features'][2]['kind']==='footer' && $a['program']['features'][2]['priority']===12);
    $result=Generator::build($source,$a,'ported-demo','Demo');$out=$root.'/native.zip';file_put_contents($out,$result['bytes']);
    check_porter('source archive not changed',hash_file('sha256',$root.'/plugin-static.zip')===$hash);
    $z=new ZipArchive();$z->open($out);$manifest=json_decode($z->getFromName('ported-demo/cms.json'),true);
    check_porter('native API 1 manifest',$manifest['api']===1 && $manifest['id']==='ported-demo' && $manifest['porting']['status']==='converted');
    $entry=$z->getFromName('ported-demo/plugin.php');
    check_porter('generated entry uses native shortcodes and HTML sanitizer',str_contains($entry,'vv_shortcode') && str_contains($entry,'Content::html') && !str_contains($entry,'add_shortcode'));
    $inert=[];for($i=0;$i<$z->numFiles;$i++)if(str_contains($z->getNameIndex($i),'/porting/source/'))$inert[]=$z->getNameIndex($i);
    check_porter('original code is inert txt',count($inert)===1 && str_ends_with($inert[0],'.txt'));
    check_porter('original reference is byte exact',$z->getFromName($inert[0])===$source->files['demo.php']);
    check_porter('report records reference mapping',count($result['report']['references']['included'])===1);
    $z->close();
    $theme=new Archive($root.'/theme-classic.zip');$ta=Analyzer::analyze($theme);
    check_porter('classic theme compiles',$ta['report']['status']==='converted');
    check_porter('templates include loop and native context',count($ta['program']['templates'])===5 && str_contains(json_encode($ta['program']), '"op":"loop"'));
    $tr=Generator::build($theme,$ta,'ported-theme','Theme');file_put_contents($root.'/theme-native.zip',$tr['bytes']);$z->open($root.'/theme-native.zip');
    check_porter('theme entry interprets generated data',str_contains($z->getFromName('ported-theme/index.php'),'WPPorterGenerated') && $z->locateName('ported-theme/templates.json')!==false);
    check_porter('theme CSS preserved',$z->getFromName('ported-theme/style.css')===$theme->files['style.css']);
    check_porter('functions.php is not executable in native output',$z->locateName('ported-theme/functions.php')===false);$z->close();
    $braces=Compiler::template('<?php if(have_posts()){while(have_posts()){the_post();the_title();}}else{echo "Empty";}');
    check_porter('brace control flow compiles',$braces[0]['op']==='if' && $braces[0]['yes'][0]['op']==='loop');
    $dynamic=new Archive($root.'/plugin-dynamic.zip');$da=Analyzer::analyze($dynamic);
    check_porter('database/business plugin is a draft',$da['report']['status']==='manual' && in_array('wordpress_database',$da['report']['capabilities'],true));
    $dr=Generator::build($dynamic,$da,'ported-business','Business');file_put_contents($root.'/draft.zip',$dr['bytes']);$z->open($root.'/draft.zip');
    check_porter('draft never calls original code',!str_contains($z->getFromName('ported-business/plugin.php'),'$wpdb') && str_contains($z->getFromName('ported-business/plugin.php'),'Draft only'));$z->close();
    $block=Analyzer::analyze(new Archive($root.'/theme-block.zip'));
    check_porter('Gutenberg explicitly requires manual port',$block['report']['status']==='manual' && in_array('block_theme',array_column($block['report']['issues'],'code'),true));
    $marker=$root.'/must-not-exist';$malicious="<?php /* Plugin Name: Source Execution Probe */ file_put_contents(".var_export($marker,true).",'EXECUTED');";
    Fixtures::zip(['probe/probe.php'=>$malicious],$root.'/probe.zip');$probe=Analyzer::analyze(new Archive($root.'/probe.zip'));
    Generator::build(new Archive($root.'/probe.zip'),$probe,'ported-probe','Probe');
    check_porter('analysis and generation never execute PHP',!file_exists($marker) && $probe['report']['status']==='manual');
    foreach(['../escape.php','/absolute.php','folder/../escape.php','folder\\evil.php','folder/.env'] as $index=>$unsafe) {
        Fixtures::zip([$unsafe=>'x'],$root.'/unsafe'.$index.'.zip');reject_porter('reject ZIP path '.$index,fn()=>new Archive($root.'/unsafe'.$index.'.zip'));
    }
    Fixtures::zip(['plug/demo.php'=>'a','plug/DEMO.php'=>'b'],$root.'/dupe.zip');reject_porter('reject case insensitive duplicate paths',fn()=>new Archive($root.'/dupe.zip'));
    $sym=$root.'/symlink.zip';Fixtures::zip(['link'=>'/etc/passwd'],$sym);$z->open($sym);$z->setExternalAttributesName('link',ZipArchive::OPSYS_UNIX,0120777<<16);$z->close();reject_porter('reject ZIP symlink',fn()=>new Archive($sym));
    Fixtures::zip(['big.txt'=>str_repeat('a',1000000)],$root.'/ratio.zip');reject_porter('reject extreme compression ratio',fn()=>new Archive($root.'/ratio.zip'));
    Fixtures::zip(['none/readme.txt'=>'nothing to convert'],$root.'/empty.zip');reject_porter('reject non WordPress package',fn()=>Analyzer::analyze(new Archive($root.'/empty.zip')));
    $child=Fixtures::all()['theme-classic'];$child['demo-theme/style.css'].="\n/* Template: parent-theme */\n";Fixtures::zip($child,$root.'/child.zip');
    check_porter('child themes need a parent port',Analyzer::analyze(new Archive($root.'/child.zip'))['report']['status']==='manual');
    $huge="<?php /* Plugin Name: Large PHP */ /*".base64_encode(random_bytes(210000))."*/";Fixtures::zip(['large/main.php'=>$huge],$root.'/large.zip');
    check_porter('oversized PHP becomes manual without tokenizing',Analyzer::analyze(new Archive($root.'/large.zip'))['report']['status']==='manual');
    reject_porter('refuse executable expressions',fn()=>Compiler::template('<?php echo system("id");'));
    reject_porter('refuse inline scripts',fn()=>Compiler::template('<script>alert(1)</script>'));
    reject_porter('refuse encoded active URLs',fn()=>Compiler::template('<a href="java&#10;script&#58;alert(1)">Link</a>'));
    reject_porter('refuse meta redirects',fn()=>Compiler::template('<meta http-equiv="refresh" content="0;url=https://example.test">'));
    reject_porter('refuse active HTML callback',fn()=>Compiler::plugin('<?php add_shortcode("x",function(){return "<img onerror=x()>";});'));
    reject_porter('refuse deep expression nesting',fn()=>Compiler::template('<?php echo '.str_repeat('!',80).'true;'));
    $cycle=Fixtures::all()['theme-classic'];$cycle['demo-theme/footer.php']='<?php get_header(); ?>';$cycle['demo-theme/header.php']='<?php get_footer(); ?>';Fixtures::zip($cycle,$root.'/cycle.zip');
    check_porter('recursive template graph becomes manual',Analyzer::analyze(new Archive($root.'/cycle.zip'))['report']['status']==='manual');
    reject_porter('reject native path traversal ID',fn()=>Generator::build($source,$a,'../bad','Name'));
    $meta=Fixtures::all()['plugin-static'];$meta['demo-static/.github/workflows/ci.yml']='name: source metadata';Fixtures::zip($meta,$root.'/metadata.zip');
    check_porter('repository metadata is ignored without copying',Analyzer::analyze(new Archive($root.'/metadata.zip'))['report']['status']==='converted');
    check_porter('strict_types preamble is supported',count(Compiler::plugin('<?php declare(strict_types=1); add_shortcode("strict",function(){return "OK";});'))===1);
    $crcPath=$root.'/crc.zip';Fixtures::zip(['crc.txt'=>'unique-content-for-crc-check'],$crcPath);$z->open($crcPath);$z->setCompressionName('crc.txt',ZipArchive::CM_STORE);$z->close();
    $raw=file_get_contents($crcPath);file_put_contents($crcPath,str_replace('unique-content-for-crc-check','UNIQUE-content-for-crc-check',$raw));reject_porter('reject corrupt entry CRC',fn()=>new Archive($crcPath));
    if(ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256,true)) {
        $encrypted=$root.'/encrypted.zip';Fixtures::zip(['secret.txt'=>'encrypted'],$encrypted);$z->open($encrypted);$z->setEncryptionName('secret.txt',ZipArchive::EM_AES_256,'test-fixture');$z->close();
        reject_porter('reject encrypted ZIP entries',fn()=>new Archive($encrypted));
    }
    check_porter('report preserves issue locations',isset($da['report']['issues'][0]['file'],$da['report']['issues'][0]['line']));
    $summary=['date'=>gmdate('c'),'runtime'=>'PHP '.PHP_VERSION,'checks'=>count($checks),'passed'=>$checks];
    if(isset($argv[1])) file_put_contents($argv[1],Generator::json($summary));
    echo 'ALL PASSED: '.count($checks)."\n";
} finally {cleanup_porter($root);}
