<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;
final class Forms {
    public const MAX_FORMS=10,MAX_FIELDS=20,MAX_ROWS=500,MAX_BYTES=2097152;
    private static string $current='',$token='',$failedForm='';
    private static array $errors=[],$values=[],$rendered=[];
    public static function register(App $app): void {
        $app->store->change(function(&$s){self::put($s,self::data($s));}); $app->refresh();
        $app->adminPage('forms',\t('Formulaires','Forms'),[FormsAdmin::class,'render'],[FormsAdmin::class,'save']);
        foreach(self::data($app->state)['forms'] as $id=>$f) \vv_shortcode('form-'.$id,fn()=>self::render($app,$id,$app->currentContent() ?? []));
        \vv_action('public_route',[self::class,'route'],5);
        \vv_action('head',function(){if(self::$token!=='') echo '<link rel="stylesheet" href="'.\e(\url('content/plugins/forms/forms.css')).'">';});
        \vv_action('backup_restore_data_v1',function($data){
            if(isset($data['plugin_data']['forms'])) {
                $d=self::validate($data['plugin_data']['forms']);
                foreach($d['rows'] as &$r) if(in_array($r['notification']['status'],['pending','processing'],true)) {$r['notification']['status']='unknown';$r['notification']['lease']='';}
                unset($r);$d['settings']=['notify'=>false,'to'=>'','from'=>''];$data['plugin_data']['forms']=$d;
            } return $data;
        });
    }
    public static function emptyData(): array {return ['schema'=>1,'settings'=>['notify'=>false,'to'=>'','from'=>''],'forms'=>[],'rows'=>[],'receipts'=>[]];}
    public static function text(mixed $v,int $max,bool $required=false): string {
        if(!is_string($v)||!mb_check_encoding($v,'UTF-8')||mb_strlen($v)>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$v)) throw new Failure(\t('Texte invalide ou trop long.','Invalid or overlong text.'));
        $v=trim($v);if($required&&$v==='')throw new Failure(\t('Un champ obligatoire est vide.','A required field is empty.'));return $v;
    }
    public static function email(mixed $v,bool $required=false): string {
        $v=self::text($v,254,$required);if(($v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))||preg_match('/[\r\n]/',$v))throw new Failure(\t('Adresse e-mail invalide.','Invalid email address.'));return $v;
    }
    private static function integer(mixed $v,int $min,int $max): int {if(!is_int($v)||$v<$min||$v>$max)throw new Failure('Forms: entier hors limites / integer outside limits.');return $v;}
    public static function definition(array $f): array {
        $id=self::text($f['id']??'',25,true);$fields=$f['fields']??null;
        if(!preg_match('/^[a-z][a-z0-9-]{0,24}$/D',$id)||!is_bool($f['published']??null)||!is_array($fields)||!count($fields)||count($fields)>self::MAX_FIELDS)throw new Failure('Forms: définition ou nombre de champs invalide / invalid definition or field count.');
        $clean=[];$seen=[];
        foreach($fields as $field){
            if(!is_array($field))throw new Failure('Forms: champ invalide / invalid field.');
            $key=self::text($field['key']??'',30,true);$type=$field['type']??'';
            if(!preg_match('/^[a-z][a-z0-9_]{0,29}$/D',$key)||isset($seen[$key])||!in_array($type,['text','email','tel','textarea','select','checkbox'],true)||!is_bool($field['required']??null)||!is_array($field['choices']??null)||count($field['choices'])>30)throw new Failure('Forms: clé/type/choix invalide / invalid key/type/choices.');
            $seen[$key]=true;$choices=[];foreach($field['choices'] as $choice)$choices[]=self::text($choice,100,true);
            if(($type==='select'&&!count($choices))||count(array_unique($choices))!==count($choices))throw new Failure('Forms: choix vides ou dupliqués / empty or duplicate choices.');
            $clean[]=['key'=>$key,'type'=>$type,'label'=>self::text($field['label']??'',100,true),'help'=>self::text($field['help']??'',250),'required'=>$field['required'],'choices'=>$type==='select'?$choices:[]];
        }
        return ['id'=>$id,'name'=>self::text($f['name']??'',100,true),'published'=>$f['published'],'version'=>self::integer($f['version']??null,1,2147483647),'retention_days'=>self::integer($f['retention_days']??null,1,3650),'privacy'=>self::text($f['privacy']??'',1500,true),'confirmation'=>self::text($f['confirmation']??'',500,true),'fields'=>$clean];
    }
    public static function validateValues(array $f,array $input): array {
        $values=[];$errors=[];$keys=array_column($f['fields'],'key');
        foreach(array_keys($input) as $key)if(!in_array($key,$keys,true))$errors['_form']=\t('Champ inconnu. Rechargez le formulaire.','Unknown field. Reload the form.');
        foreach($f['fields'] as $field){
            $key=$field['key'];$v=$input[$key]??'';$max=match($field['type']){'textarea'=>2000,'email'=>254,'select'=>100,default=>200};
            try{
                $v=self::text($v,$max,$field['required']);
                if($v!==''){
                    if($field['type']==='email')$v=self::email($v,true);
                    if($field['type']==='tel'&&!preg_match('/^[0-9+(). \-]{3,40}$/D',$v))throw new Failure(\t('Numéro de téléphone invalide.','Invalid phone number.'));
                    if($field['type']==='select'&&!in_array($v,$field['choices'],true))throw new Failure(\t('Choisissez une valeur de la liste.','Choose a listed value.'));
                    if($field['type']==='checkbox'&&$v!=='yes')throw new Failure(\t('Case invalide.','Invalid checkbox.'));
                    if($field['type']!=='textarea'&&preg_match('/[\r\n]/',$v))throw new Failure(\t('Une seule ligne attendue.','One line expected.'));
                }$values[$key]=$v;
            }catch(Failure $e){$errors[$key]=$e->getMessage();$values[$key]=is_string($v)&&mb_check_encoding($v,'UTF-8')?mb_substr($v,0,$max):'';}
        }return [$values,$errors];
    }
    public static function data(array $s): array{return self::validate($s['plugin_data']['forms']??self::emptyData());}
    public static function validate(mixed $d): array {
        if(!is_array($d)||($d['schema']??null)!==1)throw new Failure('Forms: schéma incompatible, données conservées / unsupported schema, data retained.',409);
        foreach(['settings','forms','rows','receipts'] as $k)if(!is_array($d[$k]??null))throw new Failure('Forms: données incomplètes / incomplete data.');
        if(count($d['forms'])>self::MAX_FORMS||count($d['rows'])>self::MAX_ROWS||count($d['receipts'])>1000)throw new Failure('Forms: capacité dépassée / capacity exceeded.',413);
        $cfg=$d['settings'];if(!is_bool($cfg['notify']??null))throw new Failure('Forms: réglages invalides / invalid settings.');
        $out=self::emptyData();$out['settings']=['notify'=>$cfg['notify'],'to'=>self::email($cfg['to']??'',$cfg['notify']),'from'=>self::email($cfg['from']??'',$cfg['notify'])];
        foreach($d['forms'] as $id=>$f){if(!is_string($id)||!is_array($f)||$id!==($f['id']??null))throw new Failure('Forms: définition invalide / invalid definition.');$out['forms'][$id]=self::definition($f);}
        foreach($d['rows'] as $id=>$r){
            if(!is_string($id)||!preg_match('/^[a-f0-9]{24}$/D',$id)||!is_array($r)||($r['id']??'')!==$id||!is_array($r['form']??null)||!is_array($r['values']??null)||!preg_match('/^[a-f0-9]{24}$/D',\val($r,'content_id'))||!in_array($r['status']??'',['new','read','handled','spam'],true))throw new Failure('Forms: demande invalide / invalid request.');
            $f=self::definition($r['form']);[$values,$errors]=self::validateValues($f,$r['values']);if($errors||$values!==$r['values'])throw new Failure('Forms: valeurs invalides / invalid values.');
            $j=$r['notification']??[];if(!is_array($j)||!in_array($j['status']??'',['disabled','pending','processing','accepted','failed','unknown'],true)||!is_string($j['lease']??null)||($j['lease']!==''&&!preg_match('/^[a-f0-9]{32}$/D',$j['lease'])))throw new Failure('Forms: notification invalide / invalid notification.');
            $j=['status'=>$j['status'],'to'=>self::email($j['to']??'',$j['status']!=='disabled'),'from'=>self::email($j['from']??'',$j['status']!=='disabled'),'attempts'=>self::integer($j['attempts']??null,0,100),'reserved'=>self::integer($j['reserved']??null,0,4102444800),'lease'=>$j['lease']];
            $out['rows'][$id]=['id'=>$id,'form'=>$f,'content_id'=>$r['content_id'],'values'=>$values,'status'=>$r['status'],'created'=>self::integer($r['created']??null,1,4102444800),'expires'=>self::integer($r['expires']??null,1,4420000000),'version'=>self::integer($r['version']??null,1,2147483647),'notification'=>$j];
        }
        foreach($d['receipts'] as $key=>$expiry){if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new Failure('Forms: reçu invalide / invalid receipt.');$out['receipts'][$key]=self::integer($expiry,1,4102444800);}
        if(strlen(json_encode($out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))>self::MAX_BYTES)throw new Failure('Forms: limite de 2 Mio, exportez et purgez / 2 MiB limit, export and purge.',413);return $out;
    }
    public static function put(array &$s,array $d): void{$s['plugin_data']['forms']=self::validate($d);}
    private static function target(array $s): ?array {
        if(\val($_GET,'preview')!==''||\val($_GET,'q')!==''||\val($_GET,'feed')==='1'||\val($_GET,'cms')!=='')return null;
        $slug=\val($_GET,'p');if($slug!==''){foreach($s['contents'] as $c)if($c['slug']===$slug)return $c;return null;}
        return $s['contents'][$s['settings']['home_page']??'']??null;
    }
    private static function allowed(array $s,array $c,array $f): bool{return $f['published']&&Content::visible($c)&&empty($s['settings']['maintenance'])&&str_contains(Content::field($c,'body'),'[form-'.$f['id'].']');}
    private static function signature(array $s,array $f,array $c,string $payload): string{return hash_hmac('sha256',$f['id'].'|'.$f['version'].'|'.$c['id'].'|'.self::$token.'|'.$payload,$s['secret']);}
    public static function route(App $app): void {
        self::$current=self::$token=self::$failedForm='';self::$errors=self::$values=self::$rendered=[];
        $c=self::target($app->state);$d=self::data($app->state);
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&\val($_POST,'cms_action')==='forms.submit'){
            Security::guestPost($app->store,'forms',\val($_POST,'forms_token'));self::$token=\val($_POST,'forms_token');
            $fid=\val($_POST,'form_id');$f=$d['forms'][$fid]??null;
            if(!$f||!$c||$c['id']!==\val($_POST,'content_id')||!self::allowed($app->state,$c,$f))throw new Failure(\t('Formulaire indisponible.','Form unavailable.'),404);
            if(!empty($_FILES)||(int)($_SERVER['CONTENT_LENGTH']??0)>65536)throw new Failure('Envoi trop volumineux ou fichier refusé / Overlarge submission or file refused.',413);
            $nonce=\val($_POST,'submission');
            if(!preg_match('/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D',$nonce,$m)||(int)$m[1]>time()||(int)$m[1]<time()-7200||!hash_equals(self::signature($app->state,$f,$c,$m[1].'.'.$m[2]),$m[3]))throw new Failure(\t('Formulaire expiré ou modifié. Rechargez la page.','Expired or changed form. Reload the page.'),409);
            if(\val($_POST,'website')!=='')throw new Failure('Envoi refusé / Submission refused.',422);
            $raw=$_POST['fields']??[];if(!is_array($raw))throw new Failure('Champs invalides / Invalid fields.',422);
            [self::$values,self::$errors]=self::validateValues($f,$raw);self::$failedForm=$fid;
            if(!self::$errors){
                $app->store->rate('forms-ip',$_SERVER['REMOTE_ADDR']??'',5,900);$app->store->rate('forms-global','site',100,3600);
                try{
                    $receipt=hash('sha256',$nonce);
                    $result=$app->store->change(function(&$s)use($f,$c,$receipt){
                        $d=self::data($s);$live=$d['forms'][$f['id']]??null;$content=$s['contents'][$c['id']]??null;
                        if(!$live||!$content||$live['version']!==$f['version']||!self::allowed($s,$content,$live))throw new Failure('Formulaire modifié / Form changed.',409);
                        $d['receipts']=array_filter($d['receipts'],fn($expiry)=>$expiry>=time());if(isset($d['receipts'][$receipt]))return ['duplicate'=>true,'id'=>''];
                        if(count($d['rows'])>=self::MAX_ROWS||count($d['receipts'])>=1000)throw new Failure('Boîte de réception pleine / Inbox full.',503);
                        $id=bin2hex(random_bytes(12));$notify=$d['settings']['notify'];
                        $d['rows'][$id]=['id'=>$id,'form'=>$live,'content_id'=>$content['id'],'values'=>self::$values,'status'=>'new','created'=>time(),'expires'=>time()+86400*$live['retention_days'],'version'=>1,'notification'=>['status'=>$notify?'pending':'disabled','to'=>$notify?$d['settings']['to']:'','from'=>$notify?$d['settings']['from']:'','attempts'=>0,'reserved'=>0,'lease'=>'']];
                        $d['receipts'][$receipt]=time()+7200;self::put($s,$d);Store::log($s,'forms_received',$id);return ['duplicate'=>false,'id'=>$id];
                    });
                    if(!$result['duplicate'])try{\vv_do('forms_received_v1',['schema'=>1,'id'=>$result['id'],'form_id'=>$fid,'content_id'=>$c['id']]);}catch(\Throwable){Logger::error('forms_listener','Post-commit listener failed; request retained.');}
                    $stamp=(string)time();\redirect(Content::url($c).'&form_received='.rawurlencode($fid).'&form_receipt='.$stamp.'.'.self::signature($app->state,$f,$c,'receipt.'.$stamp).'#form-'.$fid);
                }catch(Failure $e){if(!in_array($e->status,[413,503],true))throw $e;self::$errors['_form']=\t('Enregistrement impossible. Demande non confirmée. Réessayez plus tard.','Unable to save. Request not confirmed. Try later.');http_response_code($e->status);}
            }else http_response_code(422);
        }
        if(!$c||!Content::visible($c))return;
        foreach($d['forms'] as $f)if(self::allowed($app->state,$c,$f)){self::$current=$c['id'];self::$token=Security::guestToken($app->store,'forms');break;}
    }
    public static function render(App $app,string $id,array $c): string {
        $f=self::data($app->state)['forms'][$id]??null;
        if(!$f||!$c||self::$current!==$c['id']||self::$token===''||isset(self::$rendered[$id])||!self::allowed($app->state,$c,$f))return '';
        self::$rendered[$id]=true;$payload=time().'.'.bin2hex(random_bytes(16));$nonce=$payload.'.'.self::signature($app->state,$f,$c,$payload);
        $errors=self::$failedForm===$id?self::$errors:[];$values=self::$failedForm===$id?self::$values:[];$success=false;
        if(\val($_GET,'form_received')===$id&&preg_match('/^(\d{10})\.([a-f0-9]{64})$/D',\val($_GET,'form_receipt'),$m)&&(int)$m[1]<=time()&&(int)$m[1]>=time()-300)$success=hash_equals(self::signature($app->state,$f,$c,'receipt.'.$m[1]),$m[2]);
        ob_start();require __DIR__.'/public.php';return ob_get_clean();
    }
    public static function drain(App $app,int $limit=10,?callable $transport=null): array {
        $counts=['accepted'=>0,'failed'=>0,'unknown'=>0];
        for($i=0;$i<max(1,min(20,$limit));$i++){
            $job=$app->store->change(function(&$s){
                $d=self::data($s);$picked=null;
                foreach($d['rows'] as &$r){$n=&$r['notification'];
                    if($n['status']==='processing'&&$n['reserved']<time()-300){$n['status']='unknown';$n['lease']='';$r['version']++;}
                    if($d['settings']['notify']&&!$picked&&$n['status']==='pending'&&$r['status']!=='spam'&&$r['expires']>time()){$n['status']='processing';$n['reserved']=time();$n['attempts']++;$n['lease']=bin2hex(random_bytes(16));$r['version']++;$picked=$r;}unset($n);
                }unset($r);self::put($s,$d);return $picked;
            });if(!$job)break;$result='unknown';
            try{
                $result=$transport?$transport($job):\vv_filter('forms_transport_v1',null,$job);
                if($result===null){$body='Request '.$job['id'].' — '.$job['form']['name']."\n\n";foreach($job['form']['fields'] as $field)$body.=$field['label'].': '.$job['values'][$field['key']]."\n";
                    $ok=function_exists('mail')&&@mail($job['notification']['to'],'VoilaVoila Forms — '.$job['id'],$body,['From'=>$job['notification']['from'],'Content-Type'=>'text/plain; charset=UTF-8']);$result=$ok?'accepted':'failed';}
                if(!in_array($result,['accepted','failed','unknown'],true))$result='unknown';
            }catch(\Throwable){$result='unknown';}
            $app->store->change(function(&$s)use($job,$result){$d=self::data($s);$n=$d['rows'][$job['id']]['notification']??null;if(!$n||$n['status']!=='processing'||!hash_equals($n['lease'],$job['notification']['lease']))return;
                $d['rows'][$job['id']]['notification']['status']=$result;$d['rows'][$job['id']]['notification']['lease']='';$d['rows'][$job['id']]['version']++;self::put($s,$d);Store::log($s,'forms_notification_'.$result,$job['id']);});$counts[$result]++;
        }return $counts;
    }
    public static function purgeExpired(App $app): int {return $app->store->change(function(&$s){$d=self::data($s);$before=count($d['rows']);$d['rows']=array_filter($d['rows'],fn($r)=>$r['expires']>time());$d['receipts']=array_filter($d['receipts'],fn($e)=>$e>=time());self::put($s,$d);Store::log($s,'forms_expired_purged');return $before-count($d['rows']);});}
    public static function csvCell(string $value): string{return preg_match('/^[\s\x00-\x1f]*[=+\-@]/u',$value)||preg_match('/^[\t\r\n]/',$value)?"'".$value:$value;}
}
