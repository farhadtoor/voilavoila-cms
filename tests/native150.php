<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$temporary=sys_get_temp_dir().'/vv-native-150-'.bin2hex(random_bytes(8));mkdir($temporary,0700,true);
putenv('VV_STORAGE_PATH='.$temporary.'/source');putenv('VV_DB_DRIVER=sqlite');$_SERVER['SCRIPT_NAME']='/index.php';
require dirname(__DIR__).'/core/bootstrap.php';require_once VV_ROOT.'/core/Tools.php';require_once VV_ROOT.'/core/DatabaseTransfer.php';
$checks=0;
$check=static function(bool $value,string $name)use(&$checks):void{if(!$value)throw new RuntimeException($name);$checks++;echo 'PASS '.$name.PHP_EOL;};
$refused=static function(callable $fn,int $status=400):bool{try{$fn();return false;}catch(\VV\Failure $e){return $e->status===$status;}};
try{
    $check($vv->store->databaseInfo()['driver']==='sqlite','default SQLite driver contract');
    $vv->store->change(static function(&$s):void{$s=array_merge($s,\VV\App::seed('Test natif','owner@example.test','Native_Test_2026!'));$s['installed']=true;});$vv->refresh();$vv->bootPlugins();
    $before=$vv->store->read();
    try{$vv->store->change(static function(&$s):void{$s['settings']['title']='rollback';throw new RuntimeException('expected');});}catch(RuntimeException $e){if($e->getMessage()!=='expected')throw $e;}
    $check($before===$vv->store->read(),'atomic state rollback');
    $check($refused(fn()=>$vv->store->change(fn(&$s)=>$vv->store->change(fn(&$inner)=>null)),500),'nested transactions rejected');
    $id=\VV\Content::save($vv->store,['type'=>'page','status'=>'published','title_fr'=>'Édition 🧭','title_en'=>'Editing','body_fr'=>'<p onclick="x()">Texte</p><script>x()</script>','body_en'=>'<p>Text</p>','version'=>'0','menu'=>'1','order'=>'1']);
    $state=$vv->store->read();$c=$state['contents'][$id];
    $check(!str_contains($c['body_fr'],'onclick')&&!str_contains($c['body_fr'],'script'),'editorial HTML sanitized');
    \VV\Navigation::save($vv->store,['navigation'=>[$id=>['version'=>(string)$c['version'],'menu'=>'1','order'=>'0','label_fr'=>'Découvrir','label_en'=>'Discover']]]);
    $c=$vv->store->read()['contents'][$id];
    $check($c['nav_label_en']==='Discover'&&$c['order']===0,'bilingual navigation metadata');
    $check($refused(fn()=>\VV\Navigation::save($vv->store,['navigation'=>[$id=>['version'=>'1','order'=>'2']]]),409),'stale navigation changes rejected');
    \VV\Content::save($vv->store,['id'=>$id,'version'=>(string)$c['version'],'type'=>'page','status'=>'published','title_fr'=>'Révision','title_en'=>'Revision','body_fr'=>'<p>Révisé</p>','slug'=>$c['slug'],'menu'=>'1','order'=>'0']);
    $c=$vv->store->read()['contents'][$id];$check($c['nav_label_en']==='Discover'&&count($c['revisions'])>0,'editorial update preserves native extras and revisions');
    $bytes=file_get_contents(VV_ROOT.'/assets/voilavoila-logo.png');$m=\VV\Media::metadata(['id'=>str_repeat('a',24),'name'=>'logo.png'],$bytes);
    $vv->store->writeMedia($m,$bytes);$check(hash_equals($m['sha256'],hash('sha256',$vv->store->readMedia($m))),'original image bytes preserved');
    $check($refused(fn()=>\VV\Media::metadata(['id'=>str_repeat('b',24),'name'=>'active.pdf'],"%PDF-1.4\n<< /J#61vaScript (x) >>\n%%EOF")),'active PDF refused');
    $check($refused(fn()=>\VV\Media::metadata(['id'=>str_repeat('b',24),'name'=>'bad.png'],'<?php echo 1;')),'active media payload refused');
    $check($refused(fn()=>\VV\Quarantine::capture($vv->store,['error'=>0,'tmp_name'=>VV_ROOT.'/assets/voilavoila-logo.png','name'=>'image.png'],'media',8388608)),'ordinary local files cannot impersonate HTTP uploads');
    $transfer=$temporary.'/database.php';\VV\DatabaseTransfer::export($vv->store,$transfer);$target=new \VV\Store($temporary.'/target');\VV\DatabaseTransfer::import($target,$transfer);
    $check($target->read()===$vv->store->read(),'private full database transfer');
    $check($refused(fn()=>\VV\DatabaseTransfer::import($target,$transfer),409),'database transfer refuses overwrite');
    $zipPath=$temporary.'/bad.zip';$zip=new ZipArchive();$zip->open($zipPath,ZipArchive::CREATE);$zip->addFromString('../escape.php','bad');$zip->close();$zip->open($zipPath);
    $check($refused(fn()=>\VV\Tools::zipEntries($zip)),'archive traversal rejected');$zip->close();
    $check(!is_file(VV_ROOT.'/wp.php')&&!is_file(VV_ROOT.'/wp-assets.php')&&!is_dir(VV_ROOT.'/content/plugins/wp-runtime')&&!is_dir(VV_ROOT.'/content/plugins/wp-porter'),'retired engine absent');
    echo 'ALL PASSED '.$checks.PHP_EOL;
}finally{\VV\Tools::removeTree($temporary);}
