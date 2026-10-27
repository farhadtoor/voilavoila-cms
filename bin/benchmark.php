<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$count=500;$iterations=10;
foreach(array_slice($argv,2) as $arg){if(preg_match('/^--contents=(\d+)$/D',$arg,$m))$count=(int)$m[1];elseif(preg_match('/^--iterations=(\d+)$/D',$arg,$m))$iterations=(int)$m[1];else{fwrite(STDERR,"Use benchmark --contents=10..5000 --iterations=1..30\n");exit(1);}}
if($count<10||$count>5000||$iterations<1||$iterations>30){fwrite(STDERR,"Benchmark bounds exceeded.\n");exit(1);}
$temporary=sys_get_temp_dir().'/vv-benchmark-'.bin2hex(random_bytes(12));mkdir($temporary,0700);putenv('VV_STORAGE_PATH='.$temporary);
require dirname(__DIR__).'/core/bootstrap.php';require_once VV_ROOT.'/core/Tools.php';
try {
    $seed=\VV\App::seed('Synthetic benchmark','benchmark@example.test',bin2hex(random_bytes(24)));$sample=reset($seed['contents']);$seed['contents']=[];
    for($i=0;$i<$count;$i++){$id=bin2hex(random_bytes(12));$c=$sample;$c['id']=$id;$c['slug']='synthetic-'.$i;$c['title_fr']='Projet synthétique '.$i;$c['title_en']='Synthetic project '.$i;$c['body_fr']='<p>Un jeu de données créé uniquement pour cette mesure.</p>';$c['body_en']='<p>A dataset created only for this measurement.</p>';$c['pinned']=false;$seed['contents'][$id]=$c;}
    $vv->store->change(function(&$s)use($seed){$s=array_merge($s,$seed,['installed'=>true]);});unset($seed,$sample,$c);$vv->refresh();$vv->bootPlugins();
    $times=[];$bytes=0;
    for($i=0;$i<$iterations;$i++){$start=hrtime(true);$vv->refresh();$items=$vv->publicItems('post');$total=count($items);$items=array_slice($items,0,8);ob_start();$vv->template('home',['current'=>null,'notFound'=>false,'items'=>$items,'total'=>$total,'page'=>1,'per'=>8,'query'=>'','owner'=>false,'preview'=>'']);$bytes=strlen(ob_get_clean());$times[]=(hrtime(true)-$start)/1000000;}
    sort($times);$percentile=fn(float $p)=>round($times[min(count($times)-1,(int)ceil(count($times)*$p)-1)],3);
    echo json_encode(['format'=>1,'cms'=>VV_VERSION,'php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'contents'=>$count,'iterations'=>$iterations,'render_ms'=>['p50'=>$percentile(.5),'p95'=>$percentile(.95)],'peak_memory_bytes'=>memory_get_peak_usage(true),'database_bytes'=>filesize($temporary.'/cms.sqlite'),'html_bytes'=>$bytes,'scope'=>'Synthetic repeated local CLI database reads and native theme/metadata rendering. Runtime/OS caches are not flushed. No user site or remote service touched. Not a hosting latency promise.'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}finally{\VV\Tools::removeTree($temporary);}
