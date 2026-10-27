<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$fixture=json_decode(file_get_contents($argv[1]),true,16,JSON_THROW_ON_ERROR);$_SERVER['SCRIPT_NAME']=(parse_url($fixture['base'],PHP_URL_PATH)?:'').'/index.php';$_GET=[];
require $fixture['root'].'/core/bootstrap.php';$vv->bootPlugins();$checks=[];
$check=function(string $name,bool $condition)use(&$checks):void{$checks[]=['name'=>$name,'ok'=>$condition];echo($condition?'PASS ':'FAIL ').$name.PHP_EOL;if(!$condition)throw new RuntimeException($name);};
$script='<script nonce="sample">const raw="<title>Été & Noël</title></head><meta name=robots content=noindex>";</script>';
$body='<body><p>Été, cœur, <strong>création</strong> &amp; projets.</p><script>const example="<title>body</title>";</script></body></html>';
$html='<!doctype html><html><head><TITLE>Ancien titre</TITLE>'.$script.'<meta name="description" content="Ancienne"><meta name="description" content="Doublon"><link rel="canonical" href="https://other.example">'.'</head>'.$body;
$out=\VV\Metadata::rewrite($html,$vv,null);
$check('head normalization preserves exact raw script Unicode and fake tags',str_contains($out,$script));
$check('head normalization preserves the entire body byte for byte',substr($out,strpos($out,'<body>'))===$body);
$check('only one real description survives normalization',substr_count(str_replace($script,'',$out),'name="description"')===1);
foreach(['//evil.example/path','https://user:pass@evil.example/x','/old/%2f../admin.php','/storage/secrets','/?next=https://evil.example','/?p[]=77','/'] as $url){$rejected=false;try{\VV\Redirects::key($url);}catch(\VV\Failure){$rejected=true;}$check('unsafe legacy redirect source refused: '.preg_replace('~https?://[^/]+~','https://example',$url),$rejected);}
$check('equivalent trailing slash paths normalize identically',\VV\Redirects::key('/ancien-article/')===\VV\Redirects::key('/ancien-article'));
$data=\VV\Forms::data($vv->state);$row=reset($data['rows']);$check('actual HTTP form submission reaches the notification outbox',is_array($row)&&$row['notification']['status']==='pending');
$expired=$row;$expired['id']=bin2hex(random_bytes(12));$expired['created']=time()-3*86400;$expired['expires']=time()-86400;
$vv->store->change(function(&$s)use($expired){$s['plugin_data']['forms']['rows'][$expired['id']]=$expired;$s['settings']['operations']['send_notifications']=true;});$vv->refresh();$calls=0;
vv_action('forms_transport_v1',function($prior,array $job)use(&$calls){$calls++;return 'accepted';});
$run=\VV\Operations::run($vv,10,1);$vv->refresh();
$check('maintenance purges expired Forms data through the Forms owner',$run['counts']['forms_expired']===1&&!isset($vv->state['plugin_data']['forms']['rows'][$expired['id']]));
$check('maintenance drains one opted-in notification outside the transaction',$calls===1&&$run['notifications']['accepted']===1);
$check('SDK event consumer writes after the core commit',($vv->state['plugin_data']['operations-example']['last']['id']??'')===$run['id']);
$again=\VV\Operations::run($vv,10,1);$vv->refresh();$check('repeating maintenance does not resend an accepted request',$calls===1&&$again['notifications']['accepted']===0);
$pending=$row;$pending['id']=bin2hex(random_bytes(12));$pending['notification']['status']='processing';$pending['notification']['reserved']=time()-600;$pending['notification']['attempts']=1;$pending['notification']['lease']=bin2hex(random_bytes(16));
$vv->store->change(function(&$s)use($pending){$s['plugin_data']['forms']['rows'][$pending['id']]=$pending;});$vv->refresh();\VV\Operations::run($vv,10,1);$vv->refresh();
$check('expired processing lease becomes unknown without a blind resend',$calls===1&&$vv->state['plugin_data']['forms']['rows'][$pending['id']]['notification']['status']==='unknown');
$record=reset($vv->state['contents']);$before=$vv->store->read();
vv_action('content_record_v1',function(){throw new \VV\Failure('Synthetic rollback');},99);
try{\VV\Content::save($vv->store,['id'=>$record['id'],'version'=>(string)$record['version'],'type'=>$record['type'],'status'=>'published','slug'=>$record['slug'],'title_fr'=>'Must not commit','body_fr'=>'<p>Test</p>']);}catch(\VV\Failure){}
$check('content metadata failure rolls back content and revisions together',$before['contents']===$vv->store->read()['contents']);
file_put_contents($fixture['protocol_report'],json_encode(['passed'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
