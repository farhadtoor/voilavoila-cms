<?php
declare(strict_types=1);
namespace VV\Authentication;
use VV\{App,Store,Security,Failure};
defined('VV_ROOT') || exit;
require_once __DIR__.'/Totp.php';

/** Authentication operations are loaded by the core even in safe mode. */
final class OtpAuth {
    private static function key(Store $store,bool $create=false): string {
        if(!extension_loaded('openssl')||!in_array('aes-256-gcm',openssl_get_cipher_methods(),true))throw new Failure('OTP demande OpenSSL AES-256-GCM / OTP requires OpenSSL AES-256-GCM.',503);
        $dir=$store->dir.'/otp';$path=$dir.'/key.php';
        if(!is_file($path)){
            if(!$create)throw new Failure('Clé OTP privée manquante. Restaurez la sauvegarde complète ou récupérez par SSH / Missing private OTP key; restore full storage or recover through SSH.',503);
            if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new Failure('Stockage OTP indisponible / OTP storage unavailable.',503);
            // Exclusive creation prevents one enrollment request overwriting another key.
            $f=@fopen($path,'x+b');if($f){chmod($path,0600);$bytes=Store::GUARD.base64_encode(random_bytes(32));if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f)){fclose($f);throw new Failure('Écriture de clé OTP impossible / Cannot write OTP key.',503);}if(function_exists('fsync'))fsync($f);fclose($f);}
        }
        if(is_link($path)||!is_file($path)||filesize($path)>256)throw new Failure('Clé OTP privée invalide / Invalid OTP key file.',503);
        $raw=base64_decode($store->privateRead($path),true);if(!is_string($raw)||strlen($raw)!==32)throw new Failure('Clé OTP privée invalide / Invalid private OTP key.',503);return $raw;
    }
    public static function seal(Store $store,string $secret): string {
        $key=self::key($store,true);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($secret,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'voilavoila-otp-v1',16);
        if(!is_string($cipher)||strlen($tag)!==16)throw new Failure('Chiffrement OTP impossible / OTP encryption failed.',503);return base64_encode($iv.$tag.$cipher);
    }
    public static function open(Store $store,string $sealed): string {
        $bytes=base64_decode($sealed,true);if(!is_string($bytes)||strlen($bytes)<44||strlen($bytes)>256)throw new Failure('Secret OTP invalide / Invalid OTP secret.',503);
        $plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',self::key($store),OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16),'voilavoila-otp-v1');
        if(!is_string($plain))throw new Failure('Secret OTP illisible / Cannot decrypt OTP secret.',503);Totp::decode($plain);return $plain;
    }
    public static function factor(array $state): ?array {
        if(!isset($state['owner']['factor']))return null;$f=$state['owner']['factor'];
        if(!is_array($f)||($f['provider']??'')!=='otp'||($f['schema']??0)!==1||!preg_match('/^[a-f0-9]{32}$/D',$f['id']??'')||!is_string($f['sealed']??null)||!is_int($f['last_step']??null)||!is_array($f['recovery']??null)||count($f['recovery'])>10)throw new Failure('Configuration OTP invalide. Récupération SSH nécessaire / Invalid OTP configuration. SSH recovery required.',503);
        foreach($f['recovery'] as $hash)if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new Failure('Codes OTP invalides / Invalid OTP recovery codes.',503);return $f;
    }
    private static function normalizedCode(string $code): string {return strtoupper(str_replace(['-',' '],'',trim($code)));}
    public static function codes(Store $store): array {
        $plain=[];$hashes=[];$key=self::key($store,true);
        for($i=0;$i<10;$i++){$raw=strtoupper(bin2hex(random_bytes(16)));$plain[]=implode('-',str_split($raw,8));$hashes[]=hash_hmac('sha256','recovery|'.$raw,$key);}
        return [$plain,$hashes];
    }
    /** Must run inside the caller's Store::change transaction. */
    public static function consume(Store $store,array &$state,string $code,string $expectedId): string {
        $f=self::factor($state);if(!$f||!hash_equals($f['id'],$expectedId))throw new Failure('Configuration OTP modifiée / OTP configuration changed.',409);
        $step=Totp::match(self::open($store,$f['sealed']),trim($code),time(),$f['last_step']);
        if($step!==null){$state['owner']['factor']['last_step']=$step;return 'totp';}
        $code=self::normalizedCode($code);if(preg_match('/^[A-F0-9]{32}$/D',$code)){$hash=hash_hmac('sha256','recovery|'.$code,self::key($store));foreach($f['recovery'] as $n=>$candidate)if(hash_equals($candidate,$hash)){unset($f['recovery'][$n]);$state['owner']['factor']['recovery']=array_values($f['recovery']);return 'recovery';}}
        throw new Failure(\t('Code incorrect, expiré ou déjà utilisé.','Incorrect, expired or already used code.'),401);
    }
    public static function start(Store $store): never {
        $s=$store->read();$f=self::factor($s);if(!$f)throw new Failure('OTP non configuré / OTP is not configured.',409);
        unset($_SESSION['auth'],$_SESSION['pending_factor']);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));
        $_SESSION['pending_factor']=['expires'=>time()+300,'epoch'=>$s['owner']['epoch'],'factor_id'=>$f['id'],'ua'=>hash('sha256',$_SERVER['HTTP_USER_AGENT']??'')];
        $store->audit('otp_password_verified');\redirect(\url('login.php',['factor'=>'1','lang'=>\lang()]));
    }
    public static function handleLogin(App $app): never {
        $error='';$pending=$_SESSION['pending_factor']??[];$s=$app->store->read();$f=self::factor($s);
        $valid=is_array($pending)&&($pending['expires']??0)>=time()&&is_string($pending['epoch']??null)&&hash_equals($s['owner']['epoch'],$pending['epoch'])&&hash_equals($f['id'],$pending['factor_id']??'')&&hash_equals(hash('sha256',$_SERVER['HTTP_USER_AGENT']??''),$pending['ua']??'');
        if(!$valid){unset($_SESSION['pending_factor']);\redirect(\url('login.php',['lang'=>\lang()]));}
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST')try{
            Security::post();$app->store->rate('otp-ip',$_SERVER['REMOTE_ADDR']??'',5,300);$app->store->rate('otp-global','owner',20,300);
            $kind=$app->store->change(function(&$state)use($app,$pending){if(!hash_equals($state['owner']['epoch'],$pending['epoch']))throw new Failure('Session expirée / Session expired.',401);return self::consume($app->store,$state,\val($_POST,'otp'),$pending['factor_id']);});
            unset($_SESSION['pending_factor']);Security::establish($app->store,true);$app->store->audit('otp_login_'.$kind);\redirect(\admin_url());
        }catch(Failure $e){$error=$e->getMessage();http_response_code($e->status);}
        ?><!doctype html><html lang="<?=\e(\lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OTP · VoilaVoila CMS</title><link rel="stylesheet" href="<?=\e(\url('assets/admin.css'))?>"></head><body class="auth-page"><main class="auth-card"><h1><?=\e(\t('Vérifiez votre identité.','Verify your identity.'))?></h1><p><?=\e(\t('Code de votre application ou code de secours à usage unique.','Use your authenticator app or a single-use recovery code.'))?></p><?php if($error):?><p class="notice error" role="alert"><?=\e($error)?></p><?php endif;?><form method="post"><?=\csrf_field()?><label><?=\e(\t('Code OTP ou de secours','OTP or recovery code'))?><input name="otp" required autocomplete="one-time-code" inputmode="text" maxlength="40" autofocus></label><button class="button primary" type="submit"><?=\e(\t('Vérifier et ouvrir','Verify and sign in'))?></button></form><p><a href="<?=\e(\url('login.php',['lang'=>\lang()]))?>"><?=\e(\t('Recommencer la connexion','Restart sign-in'))?></a></p></main></body></html><?php exit;
    }
}
