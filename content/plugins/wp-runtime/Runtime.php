<?php
declare(strict_types=1);
namespace VV\WordPress;
use VV\{App,Store,Security,Tools,Failure,Logger};
defined('VV_ROOT') || exit;

/** Optional adapter. This class never loads WordPress PHP in the CMS process. */
final class Runtime {
    private const MAX_ZIP=47185920;
    public static function register(App $app): void {
        $app->adminPage('wp-runtime',\t('Thèmes et plugins WP','WP themes and plugins'),[RuntimeAdmin::class,'render'],[RuntimeAdmin::class,'save']);
        \vv_action('backup_restore_data_v1',function($data){if(isset($data['plugin_data']['wp-runtime']))$data['plugin_data']['wp-runtime']=['schema'=>1,'enabled'=>false,'home'=>false];return $data;});
        \vv_action('public_route',function(App $app){
            if(!empty($app->state['plugin_data']['wp-runtime']['home'])&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&!$_GET) {
                try{$cfg=self::config($app);if(self::enabled($app)){header('Location: '.$cfg['public_url'].'/',true,302);exit;}}
                catch(\Throwable){Logger::error('wp_home','Optional WordPress connector unavailable; native home retained.');}
            }
        },90);
    }
    public static function enabled(App $app): bool {return in_array('wp-runtime',$app->state['plugins'],true)&&($app->state['plugin_data']['wp-runtime']['enabled']??false)===true;}
    private static function beneath(string $path,string $root): bool {return $path===$root||str_starts_with($path,rtrim($root,'/').'/');}
    private static function absolute(string $path): string {
        if(!str_starts_with($path,'/')||str_contains($path,"\0")||preg_match('~(?:^|/)\.{1,2}(?:/|$)|//|\\\\~',$path))throw new Failure('An absolute private path is required.',503);
        $resolved=realpath($path);if($resolved!==false)return rtrim($resolved,'/');
        $parent=realpath(dirname($path));if($parent===false)throw new Failure('Private parent directory is missing.',503);return $parent.'/'.basename($path);
    }
    public static function origin(string $url,bool $allowQuery=false): string {
        $p=parse_url($url);if(!filter_var($url,FILTER_VALIDATE_URL)||!$p||!in_array($p['scheme']??'',['http','https'],true)||empty($p['host'])||(isset($p['user'])||isset($p['pass']))||(!$allowQuery&&(isset($p['query'])||isset($p['fragment']))))throw new Failure('Invalid connector URL.',503);
        return strtolower($p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.$p['port']:''));
    }
    private static function loopback(string $origin): bool {return in_array(parse_url($origin,PHP_URL_HOST),['127.0.0.1','localhost','[::1]'],true);}
    public static function config(App $app): array {
        $file=getenv('VV_WP_CONNECTOR')?:'';if($file==='')throw new Failure('Configure VV_WP_CONNECTOR outside the webroot first.',503);
        $file=self::absolute($file);$web=realpath($_SERVER['DOCUMENT_ROOT']??VV_ROOT)?:VV_ROOT;
        if(self::beneath($file,VV_ROOT)||self::beneath($file,$web)||!is_file($file)||is_link($file)||filesize($file)>1048576)throw new Failure('Connector must be a private guarded JSON file outside webroot.',503);
        try{$cfg=json_decode($app->store->privateRead($file),true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new Failure('Invalid private connector JSON.',503);}
        if(!is_array($cfg)||($cfg['format']??0)!==1||!is_string($cfg['secret']??null)||!preg_match('/^[a-f0-9]{64}$/D',$cfg['secret']))throw new Failure('Invalid connector format/secret.',503);
        foreach(['engine_root','public_url','socket'] as $k)if(!is_string($cfg[$k]??null))throw new Failure('Incomplete connector.',503);
        $cfg['engine_root']=self::absolute($cfg['engine_root']);
        foreach([VV_ROOT,$web,realpath($app->store->dir)?:$app->store->dir] as $private)if(self::beneath($cfg['engine_root'],$private)||self::beneath($private,$cfg['engine_root']))throw new Failure('WordPress and CMS storage require separate private directories.',503);
        $cfg['worker_root']=$cfg['worker_root']??$cfg['engine_root'];if(!is_string($cfg['worker_root'])||!str_starts_with($cfg['worker_root'],'/')||preg_match('~[\x00-\x20]|(?:^|/)\.\.(?:/|$)~',$cfg['worker_root']))throw new Failure('Invalid worker path.',503);
        $cfg['public_url']=rtrim($cfg['public_url'],'/');$cfg['origin']=self::origin($cfg['public_url']);
        if(parse_url($cfg['public_url'],PHP_URL_PATH)!=='/compat')throw new Failure('Runtime public_url must end in /compat.',503);
        $cfg['cms_origin']=rtrim(getenv('VV_CANONICAL_ORIGIN')?:'','/');
        if($cfg['cms_origin']===''||self::origin($cfg['cms_origin'])!==strtolower($cfg['cms_origin'])||parse_url($cfg['cms_origin'],PHP_URL_PATH))throw new Failure('Configure VV_CANONICAL_ORIGIN as the native CMS origin.',503);
        $dev=($cfg['transport']??'')==='loopback-development';
        if($dev){if(!self::loopback($cfg['origin'])||!self::loopback($cfg['cms_origin'])||$cfg['origin']===$cfg['cms_origin']||!preg_match('~^(?:tcp://127\.0\.0\.1:\d{2,5}|unix:///[^\x00-\x20]+)$~D',$cfg['socket']))throw new Failure('Development transport requires distinct loopback origins.',503);}
        elseif(!str_starts_with($cfg['origin'],'https://')||!str_starts_with($cfg['cms_origin'],'https://')||parse_url($cfg['origin'],PHP_URL_HOST)===parse_url($cfg['cms_origin'],PHP_URL_HOST)||!preg_match('~^unix:///[^\x00-\x20]+$~D',$cfg['socket']))throw new Failure('Production requires distinct HTTPS hostnames and a private Unix socket.',503);
        $cfg['cms_path']=$cfg['cms_path']??'';
        if(!is_string($cfg['cms_path'])||($cfg['cms_path']!==''&&(!preg_match('~^/[a-zA-Z0-9/_-]+$~D',$cfg['cms_path'])||str_ends_with($cfg['cms_path'],'/'))))throw new Failure('Invalid native CMS mount.',503);
        $cfg['compatibility']=($app->state['plugin_data']['wp-runtime']['compatibility']??false)===true;
        foreach(['browser_origins','redirect_origins'] as $kind){$items=$cfg[$kind]??[];if(!is_array($items)||count($items)>12)throw new Failure('Invalid compatibility origin list.',503);$cfg[$kind]=[];foreach($items as $item){if(!is_string($item)||!str_starts_with($item,'https://')||self::origin($item)!==$item||parse_url($item,PHP_URL_PATH))throw new Failure('Compatibility origins require exact HTTPS origins.',503);$cfg[$kind][]=$item;}}
        $cfg['cms_url']=$cfg['cms_origin'].$cfg['cms_path'];$cfg['policy']=self::policy($cfg['policy']??[]);return $cfg;
    }
    public static function policy(array $policy): array {
        if(array_diff(array_keys($policy),['ajax','rest']))throw new Failure('Unknown bridge policy section.');$out=['ajax'=>[],'rest'=>[]];
        foreach($out as $kind=>$_) {
            $rules=$policy[$kind]??[];if(!is_array($rules)||count($rules)>50)throw new Failure('Overlarge bridge policy.');
            foreach($rules as $route=>$rule){
                if(!is_string($route)||!preg_match($kind==='ajax'?'/^[a-zA-Z0-9_-]{1,80}$/D':'~^/[a-zA-Z0-9_-]+/[a-zA-Z0-9/_-]{1,160}$~D',$route)||!is_array($rule)||!preg_match('/^[a-z][a-z0-9-]{1,60}$/D',$rule['plugin']??''))throw new Failure('Invalid public bridge route.');
                $methods=$rule['methods']??[];$fields=$rule['fields']??[];
                if(!is_array($methods)||!$methods||array_diff($methods,['GET','POST'])||!is_array($fields)||count($fields)>30)throw new Failure('Invalid bridge method/fields.');
                foreach($fields as $name=>$type)if(!is_string($name)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,59}$/D',$name)||in_array($name,['action','rest_route','vv_guest'],true)||!in_array($type,['text','email','integer','boolean'],true))throw new Failure('Invalid bridge field rule.');
                sort($methods);ksort($fields);$out[$kind][$route]=['plugin'=>$rule['plugin'],'methods'=>array_values(array_unique($methods)),'fields'=>$fields];
            }ksort($out[$kind]);
        }return $out;
    }
    public static function prepare(App $app,string $zip,array $db): string {
        require_once VV_ROOT.'/core/Tools.php';$cfg=self::config($app);
        if(!is_file($zip)||filesize($zip)>self::MAX_ZIP)throw new Failure('Official WordPress ZIP required (maximum 45 MiB).',413);
        if(is_dir($cfg['engine_root'])&&count(scandir($cfg['engine_root']))>2)throw new Failure('Use an empty engine directory; existing engines are never overwritten.',409);
        $bundlePath=self::absolute($cfg['verification_bundle']??'');
        if(self::beneath($bundlePath,VV_ROOT)||self::beneath($bundlePath,$cfg['engine_root'])||is_link($bundlePath)||!is_file($bundlePath)||filesize($bundlePath)>1048576)throw new Failure('Private WordPress verification bundle required.',503);
        require_once __DIR__.'/Bundled.php';$pinned=Bundled::verified($zip,$bundlePath);
        $bundle=json_decode($app->store->privateRead($bundlePath),true,16,JSON_THROW_ON_ERROR);
        if(($bundle['format']??0)!==1||(!$pinned&&($bundle['verified_at']??0)<time()-86400)||($bundle['verified_at']??0)>time()+60||!hash_equals((string)($bundle['zip_sha256']??''),hash_file('sha256',$zip))||!preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$bundle['version']??'')||!is_array($bundle['checksums']??null))throw new Failure('Verification bundle expired or does not match ZIP.',409);
        $z=new \ZipArchive();if($z->open($zip)!==true)throw new Failure('Invalid WordPress ZIP.');
        try{$entries=Tools::zipEntries($z,8000,167772160,16777216);$seen=[];
            $files=[];foreach($entries as $name=>$info){$name=$info['name'];if(str_ends_with($name,'/'))continue;
                if(!str_starts_with($name,'wordpress/'))throw new Failure('Unexpected WordPress ZIP root.');$relative=substr($name,10);$bytes=$z->getFromName($name);
                if(!is_string($bytes)||!isset($bundle['checksums'][$relative])||!hash_equals($bundle['checksums'][$relative],md5($bytes)))throw new Failure('Official WordPress file checksum mismatch.');$files[$relative]=$bytes;$seen[$relative]=true;
            }
            if(array_diff_key($bundle['checksums'],$seen)||!preg_match('/\$wp_version\s*=\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]/',$files['wp-includes/version.php']??'',$m)||$m[1]!==$bundle['version'])throw new Failure('Incomplete official WordPress package.');
        }finally{$z->close();}
        foreach(['db_host','db_name','db_user','db_password'] as $k)if(!is_string($db[$k]??null)||$db[$k]===''||strlen($db[$k])>250||str_contains($db[$k],"\0"))throw new Failure('Provide dedicated MySQL/MariaDB credentials on stdin.');
        $prefix=$db['prefix']??('vvwp_'.bin2hex(random_bytes(4)).'_');if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/D',$prefix))throw new Failure('Invalid dedicated table prefix.');
        $engine=array_intersect_key($db,array_flip(['db_host','db_name','db_user','db_password']));
        $engine+=['secret'=>$cfg['secret'],'public_url'=>$cfg['public_url'],'cms_url'=>$cfg['cms_url'],'prefix'=>$prefix,'owner_login'=>'voilavoila_owner','outbound_hosts'=>[],'salts'=>[]];
        foreach(['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $key)$engine['salts'][$key]=bin2hex(random_bytes(48));
        $stage=dirname($cfg['engine_root']).'/.vv-wp-stage-'.bin2hex(random_bytes(8));if(!mkdir($stage,0750))throw new Failure('Cannot stage private WordPress.');
        try {
            foreach($files as $name=>$bytes){$dest=$stage.'/'.$name;if(!is_dir(dirname($dest)))mkdir(dirname($dest),0750,true);file_put_contents($dest,$bytes);chmod($dest,0640);}
            file_put_contents($stage.'/engine-config.php',"<?php\nreturn ".var_export($engine,true).";\n");chmod($stage.'/engine-config.php',0640);
            foreach(['Gateway','Bridge','Authorize','Console','CLI','Sync'] as $part){$dest=$stage.'/vv-'.$part.'.php';file_put_contents($dest,file_get_contents(__DIR__.'/engine/'.$part.'.template.txt'));chmod($dest,0640);}
            file_put_contents($stage.'/wp-config.php',file_get_contents(__DIR__.'/engine/wp-config.template.txt'));chmod($stage.'/wp-config.php',0640);
            mkdir($stage.'/wp-content/mu-plugins',0750,true);file_put_contents($stage.'/wp-content/mu-plugins/voilavoila.php',"<?php\nrequire dirname(__DIR__,2).'/vv-Bridge.php';\n");chmod($stage.'/wp-content/mu-plugins/voilavoila.php',0640);
            foreach(['vv-data','vv-manifests','wp-content/uploads'] as $dir)if(!is_dir($stage.'/'.$dir))mkdir($stage.'/'.$dir,0750,true);
            if(is_dir($cfg['engine_root']))rmdir($cfg['engine_root']);if(!rename($stage,$cfg['engine_root']))throw new Failure('Cannot publish private engine.');
        }catch(\Throwable $error){if(is_dir($stage))Tools::removeTree($stage);throw $error;}
        $app->store->change(function(&$s)use($bundle){$s['plugin_data']['wp-runtime']=['schema'=>1,'enabled'=>true,'home'=>false,'compatibility'=>false,'version'=>$bundle['version']];Store::log($s,'wp_engine_prepared',$bundle['version']);});$app->refresh();return $bundle['version'];
    }
    public static function wrap(App $app,string $zip,string $kind): string {
        require_once VV_ROOT.'/core/Tools.php';$cfg=self::config($app);
        if(!in_array($kind,['plugin','theme'],true)||!is_file($cfg['engine_root'].'/engine-config.php'))throw new Failure('Prepare the private engine first.');
        if(!is_file($zip)||filesize($zip)>104857600)throw new Failure('Trusted module ZIP limited to 100 MiB.',413);
        $z=new \ZipArchive();if($z->open($zip)!==true)throw new Failure('Invalid module ZIP.');$stage='';
        try {
            $entries=Tools::zipEntries($z,20000,402653184,16777216);$slug='';$files=[];$valid=false;
            foreach($entries as $info){$name=$info['name'];if(str_ends_with($name,'/'))continue;$parts=explode('/',$name);
                if(count($parts)<2||!preg_match('/^[a-z][a-z0-9-]{1,60}$/D',$parts[0])||($slug!==''&&$slug!==$parts[0])||preg_match('~(?:^|/)\.~',$name))throw new Failure('Use one lowercase module folder without hidden files.');
                $slug=$parts[0];$rel=implode('/',array_slice($parts,1));$files[$rel]=$info;
                if($kind==='plugin'&&!str_contains($rel,'/')&&str_ends_with($rel,'.php')){$header=$z->getFromIndex($info['index'],8192);if(is_string($header)&&preg_match('/Plugin Name\s*:/i',$header))$valid=true;}
            }
            if($kind==='theme')$valid=isset($files['style.css'])&&(isset($files['index.php'])||isset($files['templates/index.html']));
            if(!$valid)throw new Failure('No recognizable WordPress module header/template.');
            $dest=$cfg['engine_root'].'/wp-content/'.($kind==='theme'?'themes':'plugins').'/'.$slug;
            if(file_exists($dest)||is_link($dest))throw new Failure('Module already exists; no overwrite.',409);
            $stage=dirname($dest).'/.vv-stage-'.bin2hex(random_bytes(6));if(!mkdir($stage,0750))throw new Failure('Cannot stage module.');$hashes=[];
            // Stream one bounded entry at a time; WooCommerce exceeds 1,200 files.
            foreach($files as $name=>$info){$bytes=$z->getFromIndex($info['index']);if(!is_string($bytes)||strlen($bytes)!==$info['size'])throw new Failure('Unreadable module entry.');$file=$stage.'/'.$name;if(!is_dir(dirname($file)))mkdir(dirname($file),0750,true);if(file_put_contents($file,$bytes)!==strlen($bytes))throw new Failure('Incomplete module write.');chmod($file,0440);$hashes[$name]=hash('sha256',$bytes);unset($bytes);}
            if(!rename($stage,$dest))throw new Failure('Module publication failed.');$stage='';ksort($hashes);
            $manifest=['format'=>1,'kind'=>$kind,'slug'=>$slug,'files'=>$hashes,'policy'=>['ajax'=>(object)[],'rest'=>(object)[]]];
            file_put_contents($cfg['engine_root'].'/vv-manifests/'.$kind.'-'.$slug.'.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));chmod($cfg['engine_root'].'/vv-manifests/'.$kind.'-'.$slug.'.json',0640);
            $app->store->audit('wp_module_wrapped',$kind.':'.$slug);return $slug;
        } finally {$z->close();if($stage!==''&&is_dir($stage))Tools::removeTree($stage);}
    }
    public static function sync(App $app): array {
        $cfg=self::config($app);if(!self::enabled($app))throw new Failure('Enable the engine before sync.');$app->refresh();$items=[];
        foreach($app->state['contents'] as $content){if(!\VV\Content::visible($content))continue;
            $body=\VV\Content::field($content,'body');$body=preg_replace_callback('~\b(src|href)=("|\x27)(/[^"\x27]*)\2~i',fn($m)=>$m[1].'='.$m[2].$cfg['cms_origin'].$m[3].$m[2],$body);
            $items[]=['id'=>$content['id'],'type'=>$content['type'],'slug'=>$content['slug'],'title'=>\VV\Content::field($content,'title'),'body'=>$body,'excerpt'=>\VV\Content::field($content,'excerpt'),'published'=>$content['publish_at']];
        }
        if(count($items)>1000)throw new Failure('Native snapshot limit: 1000 visible contents.',413);
        $body=json_encode(['format'=>1,'source'=>$cfg['cms_url'],'site'=>['title'=>$app->state['settings']['title'],'tagline'=>$app->state['settings']['tagline']],'items'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $ctx=['time'=>time(),'nonce'=>bin2hex(random_bytes(16)),'method'=>'POST','uri'=>'/bridge-sync','owner'=>true,'login'=>false,'operation'=>'sync','public_url'=>$cfg['public_url'],'guest'=>'','body_sha256'=>hash('sha256',$body),'rule'=>null,'compatibility'=>$cfg['compatibility']];
        $encoded=base64_encode(json_encode($ctx,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$u=parse_url($cfg['origin']);
        $params=['GATEWAY_INTERFACE'=>'CGI/1.1','SERVER_PROTOCOL'=>'HTTP/1.1','REDIRECT_STATUS'=>'200','SCRIPT_FILENAME'=>rtrim($cfg['worker_root'],'/').'/vv-Gateway.php','SCRIPT_NAME'=>'/compat/vv-Gateway.php','DOCUMENT_ROOT'=>$cfg['worker_root'],'REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/compat/bridge-sync','QUERY_STRING'=>'','CONTENT_TYPE'=>'application/json','CONTENT_LENGTH'=>(string)strlen($body),'HTTP_HOST'=>$u['host'].(isset($u['port'])?':'.$u['port']:''),'SERVER_NAME'=>$u['host'],'SERVER_PORT'=>(string)($u['port']??443),'HTTPS'=>$u['scheme']==='https'?'on':'off','REMOTE_ADDR'=>'127.0.0.1','VV_CONTEXT'=>$encoded,'VV_SIGNATURE'=>hash_hmac('sha256',$encoded,$cfg['secret'])];
        $reply=(new FastCGI())->request($cfg['socket'],$params,$body);$result=json_decode($reply['body'],true,16);
        if($reply['status']!==200||!is_array($result)||!isset($result['created'],$result['updated'],$result['hidden']))throw new Failure('Native mirror synchronization failed; CMS source retained.',502);
        $app->store->audit('wp_native_synced',(string)count($items));return $result;
    }
    public static function refresh(App $app): void {
        $cfg=self::config($app);if(!is_file($cfg['engine_root'].'/engine-config.php'))throw new Failure('Existing engine required.');
        foreach(['Gateway','Bridge','Authorize','Console','CLI','Sync'] as $part){$path=$cfg['engine_root'].'/vv-'.$part.'.php';$tmp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';$bytes=file_get_contents(__DIR__.'/engine/'.$part.'.template.txt');if(file_put_contents($tmp,$bytes)!==strlen($bytes))throw new Failure('Worker template write failed.');chmod($tmp,0640);if(!rename($tmp,$path))throw new Failure('Worker template publication failed.');}
        $config=$cfg['engine_root'].'/wp-config.php';$bytes=file_get_contents(__DIR__.'/engine/wp-config.template.txt');$tmp=$config.'.'.bin2hex(random_bytes(6)).'.tmp';if(file_put_contents($tmp,$bytes)!==strlen($bytes))throw new Failure('Worker configuration template write failed.');chmod($tmp,0640);if(!rename($tmp,$config))throw new Failure('Worker configuration publication failed.');
        $app->store->audit('wp_worker_templates_refreshed');
    }
    public static function ticket(App $app,string $target): never {
        Security::requireOwner($app->store);$cfg=self::config($app);if(!self::enabled($app))throw new Failure('Enable the prepared engine first.');
        if(!in_array($target,['dashboard','themes','plugins','export','health'],true))throw new Failure('Invalid WordPress destination.');
        $dir=$app->store->dir.'/wp-bridge/tickets';if(!is_dir($dir))mkdir($dir,0700,true);$count=0;foreach(glob($dir.'/*.php') as $p){if(filemtime($p)<time()-120)@unlink($p);else$count++;}if($count>=100)throw new Failure('Too many pending WordPress tickets.',429);
        $token=bin2hex(random_bytes(32));$payload=['expires'=>time()+60,'epoch'=>$app->state['owner']['epoch'],'target'=>$target,'email'=>$app->state['owner']['email'],'title'=>$app->state['settings']['title'],'ua'=>hash('sha256',$_SERVER['HTTP_USER_AGENT']??'')];
        $app->store->privateWrite($dir.'/'.$token.'.php',json_encode($payload,JSON_THROW_ON_ERROR));
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'nonce-".Security::$nonce."'; form-action ".$cfg['origin']."; base-uri 'none'; frame-ancestors 'none'");header('Referrer-Policy: origin');header('Cache-Control: private, no-store');
        echo '<!doctype html><html lang="'.\e(\lang()).'"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>WordPress</title><link rel="stylesheet" href="'.\e(\url('assets/admin.css')).'"><main class="auth-card"><h1>WordPress</h1><form method="post" action="'.\e($cfg['public_url'].'/bridge-login').'"><input type="hidden" name="ticket" value="'.$token.'"><button class="button primary">'.\e(\t('Continuer','Continue')).'</button></form><script nonce="'.\e(Security::$nonce).'">document.forms[0].submit()</script></main></html>';exit;
    }
    private static function owner(App $app): bool {
        $token=$_COOKIE[Security::tls()?'__Secure-vvwp-owner':'vvwp-owner']??'';if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
        $file=$app->store->dir.'/wp-bridge/sessions/'.$token.'.php';if(!is_file($file)||is_link($file)||filesize($file)>4096)return false;
        try{$data=json_decode($app->store->privateRead($file),true,8,JSON_THROW_ON_ERROR);return ($data['expires']??0)>time()&&hash_equals($app->state['owner']['epoch'],$data['epoch']??'')&&hash_equals(hash('sha256',$_SERVER['HTTP_USER_AGENT']??''),$data['ua']??'');}catch(\Throwable){return false;}
    }
    private static function consume(App $app,array $cfg): array {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||($_SERVER['HTTP_ORIGIN']??'')!==$cfg['cms_origin']||!preg_match('/^[a-f0-9]{64}$/D',\val($_POST,'ticket')))throw new Failure('Invalid WordPress handoff.',403);
        $file=$app->store->dir.'/wp-bridge/tickets/'.\val($_POST,'ticket').'.php';if(!is_file($file)||is_link($file))throw new Failure('WordPress handoff expired.',403);$f=fopen($file,'r+b');if(!$f||!flock($f,LOCK_EX))throw new Failure('WordPress ticket unavailable.',409);
        try{$raw=stream_get_contents($f);$data=str_starts_with($raw,Store::GUARD)?json_decode(substr($raw,strlen(Store::GUARD)),true,8):null;
            if(!is_array($data)||($data['expires']??0)<time()||!hash_equals($app->state['owner']['epoch'],$data['epoch']??'')||!hash_equals(hash('sha256',$_SERVER['HTTP_USER_AGENT']??''),$data['ua']??''))throw new Failure('WordPress handoff expired.',403);ftruncate($f,0);fflush($f);
        }finally{flock($f,LOCK_UN);fclose($f);}unlink($file);
        $dir=$app->store->dir.'/wp-bridge/sessions';if(!is_dir($dir))mkdir($dir,0700,true);$n=0;foreach(glob($dir.'/*.php') as $p){if(filemtime($p)<time()-1800)@unlink($p);else$n++;}if($n>=100)throw new Failure('WordPress session capacity reached.',429);
        $token=bin2hex(random_bytes(32));$app->store->privateWrite($dir.'/'.$token.'.php',json_encode(['expires'=>time()+1800,'epoch'=>$data['epoch'],'ua'=>$data['ua']],JSON_THROW_ON_ERROR));
        setcookie(Security::tls()?'__Secure-vvwp-owner':'vvwp-owner',$token,['expires'=>time()+1800,'path'=>'/','secure'=>Security::tls(),'httponly'=>true,'samesite'=>'Strict']);return $data;
    }
    private static function host(array $cfg): void {if((Security::tls()?'https://':'http://').strtolower($_SERVER['HTTP_HOST']??'')!==$cfg['origin'])throw new Failure('WordPress origin unavailable.',404);}
    public static function publicRule(array $cfg,string $path,string $method,array $query,array $post): array {
        if(array_intersect(array_keys($query),array_keys($post)))throw new Failure('Conflicting bridge parameters.',403);
        $params=$query+$post;$kind='';$route='';
        if($path==='/wp-admin/admin-ajax.php'){$kind='ajax';$route=\val($params,'action');}
        elseif(str_starts_with($path,'/wp-json/')){$kind='rest';$route=substr($path,8);if(isset($params['rest_route']))throw new Failure('Conflicting REST route.',403);}
        elseif(isset($params['rest_route'])){$kind='rest';$route=\val($params,'rest_route');}
        $rule=$cfg['policy'][$kind][$route]??null;
        if(!$rule||!in_array($method,$rule['methods'],true))throw new Failure('Public WordPress action is not declared.',403);
        foreach($params as $name=>$value){if(in_array($name,['action','rest_route','vv_guest'],true))continue;$type=$rule['fields'][$name]??'';
            if(!is_string($value)||!mb_check_encoding($value,'UTF-8')||strlen($value)>2000||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)||!in_array($type,['text','email','integer','boolean'],true)||($type==='email'&&!filter_var($value,FILTER_VALIDATE_EMAIL))||($type==='integer'&&!preg_match('/^-?\d{1,10}$/D',$value))||($type==='boolean'&&!in_array($value,['0','1'],true)))throw new Failure('Undeclared or invalid bridge parameter.',403);
        }
        $manifest=$cfg['engine_root'].'/vv-manifests/plugin-'.$rule['plugin'].'.json';if(!is_file($manifest)||is_link($manifest)||filesize($manifest)>262144)throw new Failure('Missing installed plugin manifest.',403);
        $data=json_decode(file_get_contents($manifest),true,16,JSON_THROW_ON_ERROR);$installed=self::policy($data['policy']??[]);
        if(($installed[$kind][$route]??null)!==$rule||!is_array($data['files']??null)||count($data['files'])>1200)throw new Failure('Public policy differs from installed manifest.',403);
        $root=$cfg['engine_root'].'/wp-content/plugins/'.$rule['plugin'];
        foreach($data['files'] as $name=>$hash){$rel=Security::quarantinePath('/'.$name);$real=realpath($root.$rel);if(!$real||!self::beneath($real,$root)||is_link($root.$rel)||!is_file($real)||filesize($real)>8388608||!hash_equals($hash,hash_file('sha256',$real)))throw new Failure('Plugin integrity check failed.',403);}
        $inventory=[];$iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $entry){if($entry->isLink()||!$entry->isFile())throw new Failure('Plugin contains a linked/special entry.',403);$name=substr($entry->getPathname(),strlen($root)+1);$inventory[$name]=true;if(count($inventory)>1200)throw new Failure('Plugin file limit exceeded.',403);}
        if(array_diff_key($inventory,$data['files'])||array_diff_key($data['files'],$inventory))throw new Failure('Plugin inventory differs from manifest.',403);
        return ['kind'=>$kind,'route'=>$route,'plugin'=>$rule['plugin']];
    }
    public static function multipart(): array {
        $boundary='vv'.bin2hex(random_bytes(18));$body='';$parts=0;
        $add=function(string $name,string $value,?string $filename=null,string $mime='application/octet-stream')use(&$body,&$parts,$boundary){
            if(++$parts>200||!preg_match('/^[a-zA-Z0-9_\[\]-]{1,150}$/D',$name))throw new Failure('Invalid upload field.',413);
            $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"".$name.'"';if($filename!==null)$body.='; filename="'.str_replace(['"',"\r","\n",'\\'],['_','_','_','_'],$filename).'"';$body.="\r\n".($filename!==null?'Content-Type: '.$mime."\r\n":'')."\r\n".$value."\r\n";if(strlen($body)>8388608)throw new Failure('WordPress upload exceeds 8 MiB.',413);
        };
        $walk=function(array $data,string $prefix='',int $depth=0)use(&$walk,$add){if($depth>5)throw new Failure('Upload nesting too deep.',413);foreach($data as $key=>$value){$name=$prefix===''?(string)$key:$prefix.'['.$key.']';if(is_array($value))$walk($value,$name,$depth+1);elseif(is_string($value))$add($name,$value);else throw new Failure('Invalid upload value.');}};$walk($_POST);
        foreach($_FILES as $name=>$file){if(!is_array($file)||is_array($file['name']??null)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||filesize($file['tmp_name'])>8388608)throw new Failure('Invalid WordPress owner upload.',413);$add((string)$name,file_get_contents($file['tmp_name']),basename($file['name']),'application/octet-stream');}
        return [$body.'--'.$boundary."--\r\n",'multipart/form-data; boundary='.$boundary];
    }
    public static function proxy(App $app): never {
        $cfg=self::config($app);self::host($cfg);if(!self::enabled($app))throw new Failure('WordPress is disabled.',404);
        $path=Security::quarantinePath(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');if(!str_starts_with($path,'/compat/'))throw new Failure('Unknown WordPress route.',404);$path=substr($path,7);
        $login=$path==='/bridge-login';$ticket=$login?self::consume($app,$cfg):[];$owner=$login||self::owner($app);
        if(!empty($app->state['settings']['maintenance'])&&!$owner){header('Retry-After: 3600');throw new Failure('Maintenance.',503);}
        if(preg_match('~^/(?:wp-content/(?:themes|plugins|uploads)/|wp-includes/|wp-admin/(?:css|js|images)/)~',$path)&&!str_ends_with(strtolower($path),'.php')){self::asset($app,$path);}
        $method=$_SERVER['REQUEST_METHOD']??'GET';if(!$cfg['compatibility']&&!in_array($method,['GET','HEAD','POST'],true))throw new Failure('Method requires WordPress compatibility mode.',405);if(!in_array($method,['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'],true))throw new Failure('Unsupported bridge method.',405);
        if(preg_match('~(?:^|/)(?:\.|engine-config|vv-(?:Gateway|Bridge|Authorize|Console|CLI)|wp-config|xmlrpc|wp-cron)|^/(?:wp-admin/install\.php|wp-admin/setup-config\.php)~i',$path))throw new Failure('Protected WordPress route.',403);
        $dynamic=$method==='POST'||str_starts_with($path,'/wp-json/')||isset($_GET['rest_route'])||str_starts_with($path,'/wp-admin/')||$path==='/wp-login.php';
        $rule=null;$ctype=$_SERVER['CONTENT_TYPE']??'';
        if(!$owner&&$dynamic&&!$cfg['compatibility']){if($_FILES||($method==='POST'&&!str_starts_with($ctype,'application/x-www-form-urlencoded')))throw new Failure('Public bridge accepts only declared URL-encoded requests.',403);$rule=self::publicRule($cfg,$path,$method,$_GET,$_POST);if($method==='POST')Security::guestPost($app->store,'wp-bridge',\val($_POST,'vv_guest',$_SERVER['HTTP_X_VV_GUEST']??''));}
        if(!$owner&&($path==='/wp-login.php'||str_starts_with($path,'/wp-admin/')&&!in_array($path,$cfg['compatibility']?['/wp-admin/admin-ajax.php','/wp-admin/admin-post.php']:['/wp-admin/admin-ajax.php'],true)))throw new Failure('Owner handoff required.',403);
        if(!$owner)$app->store->rate('wp-public',$_SERVER['REMOTE_ADDR']??'',120,60);
        $guest=Security::guestToken($app->store,'wp-bridge');$body=$login?'':file_get_contents('php://input');if(!is_string($body))$body='';if(strlen($body)>8388608)throw new Failure('Bridge body exceeds 8 MiB.',413);
        if(!$login&&$method==='POST'&&str_starts_with($ctype,'multipart/form-data'))[$body,$ctype]=self::multipart();
        $context=['time'=>time(),'nonce'=>bin2hex(random_bytes(16)),'method'=>$login?'GET':$method,'uri'=>$login?'/':$path.(($_SERVER['QUERY_STRING']??'')!==''?'?'.$_SERVER['QUERY_STRING']:''),'owner'=>$owner,'login'=>$login,'target'=>$ticket['target']??'','email'=>$ticket['email']??'','title'=>$ticket['title']??'','public_url'=>$cfg['public_url'],'guest'=>$guest,'body_sha256'=>hash('sha256',$body),'rule'=>$rule,'compatibility'=>$cfg['compatibility']];
        $encoded=base64_encode(json_encode($context,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$u=parse_url($cfg['origin']);
        $params=['GATEWAY_INTERFACE'=>'CGI/1.1','SERVER_PROTOCOL'=>'HTTP/1.1','SERVER_SOFTWARE'=>'VoilaVoila','REDIRECT_STATUS'=>'200','SCRIPT_FILENAME'=>rtrim($cfg['worker_root'],'/').'/vv-Gateway.php','SCRIPT_NAME'=>'/compat/vv-Gateway.php','DOCUMENT_ROOT'=>$cfg['worker_root'],'REQUEST_METHOD'=>$context['method'],'REQUEST_URI'=>'/compat'.$context['uri'],'QUERY_STRING'=>$login?'':($_SERVER['QUERY_STRING']??''),'CONTENT_TYPE'=>$ctype,'CONTENT_LENGTH'=>(string)strlen($body),'SERVER_NAME'=>$u['host'],'SERVER_PORT'=>(string)($u['port']??($u['scheme']==='https'?443:80)),'HTTP_HOST'=>parse_url($cfg['origin'],PHP_URL_HOST).(isset($u['port'])?':'.$u['port']:''),'HTTPS'=>$u['scheme']==='https'?'on':'off','REMOTE_ADDR'=>$_SERVER['REMOTE_ADDR']??'127.0.0.1','VV_CONTEXT'=>$encoded,'VV_SIGNATURE'=>hash_hmac('sha256',$encoded,$cfg['secret'])];
        $cookies=[];foreach($_COOKIE as $name=>$value)if(is_string($name)&&is_string($value)&&preg_match('/^[A-Za-z0-9_-]{1,120}$/D',$name)&&!preg_match('/^(?:__Secure-)?(?:vvcms|vvwp-owner|vvguest-)/',$name)&&strlen($value)<=4096&&!preg_match('/[\x00-\x20\x7f;]/',$value))$cookies[]=$name.'='.$value;
        $params['HTTP_COOKIE']=implode('; ',array_slice($cookies,0,50));
        foreach(['HTTP_USER_AGENT','HTTP_ACCEPT','HTTP_ACCEPT_LANGUAGE','HTTP_ORIGIN','HTTP_REFERER','HTTP_X_WP_NONCE','HTTP_X_REQUESTED_WITH','HTTP_NONCE','HTTP_CART_TOKEN','HTTP_AUTHORIZATION','HTTP_STRIPE_SIGNATURE','HTTP_PAYPAL_TRANSMISSION_ID','HTTP_PAYPAL_TRANSMISSION_TIME','HTTP_PAYPAL_TRANSMISSION_SIG','HTTP_PAYPAL_CERT_URL','HTTP_PAYPAL_AUTH_ALGO'] as $key)if(is_string($_SERVER[$key]??null)&&strlen($_SERVER[$key])<=4096&&!preg_match('/[\x00-\x1f\x7f]/',$_SERVER[$key]))$params[$key]=$_SERVER[$key];
        $response=(new FastCGI())->request($cfg['socket'],$params,$body);self::compatHeaders($cfg);http_response_code($response['status']);
        foreach($response['headers'] as [$name,$value]) {
            if($name==='set-cookie'){if(preg_match('/^(?:__Secure-)?(?:vvcms|vvwp-owner|vvguest-)/i',$value))continue;$value=preg_replace('/;\s*domain\s*=\s*(?:"[^"]*"|[^;]*)/i','',$value);header('Set-Cookie: '.$value,false);}
            elseif($name==='location'){if(str_starts_with($value,'/compat/'))$value=$cfg['origin'].$value;if(!(self::origin($value,true)===$cfg['origin']&&str_starts_with($value,$cfg['public_url'].'/'))&&!in_array(self::origin($value,true),$cfg['redirect_origins'],true))throw new Failure('External WordPress redirect refused.',502);header('Location: '.$value,true,$response['status']);}
            elseif(in_array($name,['content-type','content-disposition','retry-after','allow','x-robots-tag','x-wp-total','x-wp-totalpages','x-wp-nonce','nonce','nonce-timestamp','nonce-user-id','cart-token','link'],true))header($name.': '.$value);
        }
        http_response_code($response['status']);if($method!=='HEAD')echo $response['body'];exit;
    }
    private static function compatHeaders(array $cfg): void {
        $extra=implode(' ',$cfg['browser_origins']);$cms=$cfg['cms_origin'];
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: $extra; style-src 'self' 'unsafe-inline' $extra; img-src 'self' data: blob: $cms $extra; font-src 'self' data: $extra; connect-src 'self' $extra; worker-src 'self' blob:; frame-src 'self' blob: $extra; frame-ancestors 'self'; form-action 'self' $extra; base-uri 'self'; object-src 'none'");header('X-Frame-Options: SAMEORIGIN');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
    }
    public static function asset(App $app,?string $traditional=null): never {
        $cfg=self::config($app);self::host($cfg);if(!self::enabled($app))throw new Failure('WordPress assets disabled.',404);$owner=self::owner($app);if(!empty($app->state['settings']['maintenance'])&&!$owner)throw new Failure('Maintenance.',503);
        $path=$traditional??Security::quarantinePath(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');$relative='';
        if($traditional!==null)$relative=ltrim($traditional,'/');
        elseif(preg_match('~^/assets/(ui|mod)/([a-f0-9]{20})/(.+)$~D',$path,$m)){$folder=$m[1]==='ui'?'themes':'plugins';$dirs=glob($cfg['engine_root'].'/wp-content/'.$folder.'/*',GLOB_ONLYDIR)?:[];if(count($dirs)>1000)throw new Failure('Too many modules.',503);foreach($dirs as $dir)if(hash_equals(substr(hash_hmac('sha256',$folder.'/'.basename($dir),$cfg['secret']),0,20),$m[2])){$relative='wp-content/'.$folder.'/'.basename($dir).'/'.$m[3];break;}}
        elseif(str_starts_with($path,'/assets/core/'))$relative='wp-includes/'.substr($path,13);
        elseif(str_starts_with($path,'/assets/media/'))$relative='wp-content/uploads/'.substr($path,14);
        elseif(str_starts_with($path,'/assets/admin/'))$relative='wp-admin/'.substr($path,14);
        Security::quarantinePath('/'.$relative);if($relative===''||!preg_match('~^(?:wp-content/(?:themes|plugins|uploads)/|wp-includes/|wp-admin/(?:css|js|images)/)~',$relative)||str_starts_with($relative,'wp-admin/')&&!$owner)throw new Failure('Asset unavailable.',404);
        $mime=['css'=>'text/css; charset=utf-8','js'=>'application/javascript; charset=utf-8','mjs'=>'application/javascript; charset=utf-8','wasm'=>'application/wasm','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','avif'=>'image/avif','gif'=>'image/gif','ico'=>'image/x-icon','svg'=>'image/svg+xml','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf','otf'=>'font/otf','pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','zip'=>'application/zip','csv'=>'text/csv; charset=utf-8'];$ext=strtolower(pathinfo($relative,PATHINFO_EXTENSION));
        if(!isset($mime[$ext]))throw new Failure('Static extension refused.',404);$root=$cfg['engine_root'];$file=$root;foreach(explode('/',$relative) as $part){$file.='/'.$part;if(is_link($file))throw new Failure('Linked asset refused.',404);}
        $real=realpath($file);if(!$real||!self::beneath($real,$root)||!is_file($real)||filesize($real)>16777216)throw new Failure('Asset unavailable.',404);
        if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true))throw new Failure('Asset method refused.',405);
        header('Content-Type: '.$mime[$ext]);header('Content-Length: '.filesize($real));header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
        if(in_array($ext,['js','mjs','wasm'],true)) header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-eval' blob:; connect-src 'self'"); else header("Content-Security-Policy: default-src 'none'; sandbox");if(in_array($ext,['pdf','docx','zip','csv'],true))header('Content-Disposition: attachment; filename="'.basename($real).'"');if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($real);exit;
    }
}
