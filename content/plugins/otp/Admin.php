<?php
declare(strict_types=1);
namespace VV\Authentication;
use VV\{App,Security,Failure,Store};
defined('VV_ROOT') || exit;
require_once __DIR__.'/Auth.php';
final class OtpAdmin {
    public static function save(App $app,array $p): void {
        Security::requireOwner($app->store);Security::reauth($app->store,\val($p,'current_password'));$op=\val($p,'op');$app->refresh();$factor=OtpAuth::factor($app->state);
        if($op==='begin'){
            if($factor)throw new Failure('OTP déjà activé / OTP already enabled.',409);
            $secret=Totp::base32(random_bytes(20));$_SESSION['otp_enroll']=['sealed'=>OtpAuth::seal($app->store,$secret),'expires'=>time()+600,'epoch'=>$app->state['owner']['epoch']];$app->store->audit('otp_enrollment_started');return;
        }
        if($op==='cancel'){unset($_SESSION['otp_enroll']);return;}
        if($op==='enable'){
            if($factor)throw new Failure('OTP déjà activé / OTP already enabled.',409);$pending=$_SESSION['otp_enroll']??[];
            if(($pending['expires']??0)<time()||!hash_equals($app->state['owner']['epoch'],$pending['epoch']??''))throw new Failure('Configuration expirée. Recommencez / Enrollment expired. Start again.',409);
            $secret=OtpAuth::open($app->store,$pending['sealed']);$step=Totp::match($secret,trim(\val($p,'otp')),time());if($step===null)throw new Failure('Code OTP incorrect / Incorrect OTP code.',401);
            if(!isset($p['ack']))throw new Failure('Confirmez la sauvegarde des codes de secours / Confirm recovery code storage.');
            [$codes,$hashes]=OtpAuth::codes($app->store);$id=bin2hex(random_bytes(16));
            $app->store->change(function(&$s)use($pending,$step,$hashes,$id){if(isset($s['owner']['factor'])||!hash_equals($s['owner']['epoch'],$pending['epoch']))throw new Failure('Configuration modifiée / Configuration changed.',409);
                $s['owner']['factor']=['provider'=>'otp','schema'=>1,'id'=>$id,'sealed'=>$pending['sealed'],'last_step'=>$step,'recovery'=>$hashes,'created_at'=>gmdate('c')];$s['owner']['epoch']=bin2hex(random_bytes(16));Store::log($s,'otp_enabled');});
            unset($_SESSION['otp_enroll']);$_SESSION['otp_codes_once']=$codes;Security::establish($app->store,true);return;
        }
        if(!in_array($op,['disable','codes'],true)||!$factor)throw new Failure('Action OTP indisponible / OTP action unavailable.');
        $app->store->rate('otp-management',$_SERVER['REMOTE_ADDR']??'cli',5,300);
        [$codes,$hashes]=$op==='codes'?OtpAuth::codes($app->store):[[],[]];
        $app->store->change(function(&$s)use($app,$p,$factor,$op,$hashes){OtpAuth::consume($app->store,$s,\val($p,'otp'),$factor['id']);
            if($op==='disable'){if(\val($p,'confirm')!=='DISABLE')throw new Failure('Saisissez DISABLE / Enter DISABLE.');unset($s['owner']['factor']);}
            else{$s['owner']['factor']['recovery']=$hashes;$s['owner']['factor']['id']=bin2hex(random_bytes(16));}
            $s['owner']['epoch']=bin2hex(random_bytes(16));Store::log($s,'otp_'.$op);
        });
        unset($_SESSION['otp_enroll'],$_SESSION['otp_codes_once']);if($op==='codes')$_SESSION['otp_codes_once']=$codes;Security::establish($app->store,true);
    }
    private static function start(string $op): string{return '<form method="post">'.\csrf_field().'<input type="hidden" name="action" value="plugin.save"><input type="hidden" name="op" value="'.\e($op).'">';}
    private static function password(): string{return '<label>'.\e(\t('Mot de passe actuel','Current password')).'<input type="password" name="current_password" required autocomplete="current-password"></label>';}
    public static function render(App $app): void {
        header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');$factor=OtpAuth::factor($app->state);$pending=$_SESSION['otp_enroll']??null;
        $codes=$_SESSION['otp_codes_once']??[];unset($_SESSION['otp_codes_once']);
        ?><link rel="stylesheet" href="<?=\e(\url('content/plugins/otp/otp.css'))?>"><section class="panel"><h2><?=\e(\t('Authentification par application','Authenticator app security'))?></h2><p><?=\e(\t('Ajoutez un code TOTP à six chiffres après votre mot de passe. Le QR code est généré ici, sans service externe. Utilisez une application compatible TOTP, par exemple Aegis, 2FAS, Google Authenticator ou Microsoft Authenticator.','Add a six-digit TOTP after your password. The QR code is generated locally without an external service. Use a TOTP app such as Aegis, 2FAS, Google Authenticator or Microsoft Authenticator.'))?></p><p><strong><?=\e($factor?\t('Activé','Enabled'):\t('Non activé','Not enabled'))?></strong><?php if($factor):?> · <?=count($factor['recovery'])?> <?=\e(\t('codes de secours restants','recovery codes remaining'))?><?php endif;?></p></section>
        <?php if($codes):?><section class="panel otp-recovery"><h2><?=\e(\t('Codes de secours — affichage unique','Recovery codes — shown once'))?></h2><p><?=\e(\t('Conservez-les hors ligne, séparément du téléphone. Chaque code remplace une seule vérification OTP et nécessite toujours votre mot de passe. Un rafraîchissement masque cette liste.','Keep these offline, separately from your phone. Each code replaces one OTP check and still requires your password. Refreshing hides this list.'))?></p><pre id="otp-recovery-codes"><?=\e(implode("\n",$codes))?></pre></section><?php endif;?>
        <?php if(!$factor):?>
        <?php if(!$pending||($pending['expires']??0)<time()):?><section class="panel"><?=self::start('begin')?><?=self::password()?><button class="button primary"><?=\e(\t('Configurer mon application','Set up my authenticator'))?></button></form></section>
        <?php else:$secret=OtpAuth::open($app->store,$pending['sealed']);$uri=Totp::uri($secret,$app->state['owner']['email']);?>
        <section class="panel"><h2><?=\e(\t('Scanner puis confirmer','Scan and confirm'))?></h2><canvas id="otp-qr" data-uri="<?=\e($uri)?>" aria-label="<?=\e(\t('QR code de configuration TOTP','TOTP setup QR code'))?>"></canvas><p><?=\e(\t('Ou saisissez cette clé dans votre application :','Or enter this key in your authenticator:'))?></p><code class="otp-secret"><?=\e($secret)?></code><p><?=\e(\t('Clé temporaire pendant 10 minutes. L’OTP ne s’active qu’après confirmation d’un code valide. Les codes de secours s’affichent ensuite une seule fois.','Setup expires in 10 minutes. OTP is enabled only after confirming a valid code. Recovery codes are then shown once.'))?></p><?=self::start('enable')?><label><?=\e(\t('Code actuel de l’application','Current authenticator code'))?><input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label><label class="checkbox"><input type="checkbox" name="ack" required><?=\e(\t('Je conserverai les codes de secours hors ligne.','I will store recovery codes offline.'))?></label><?=self::password()?><button class="button primary"><?=\e(\t('Confirmer et activer','Confirm and enable'))?></button></form><?=self::start('cancel')?><?=self::password()?><button class="button secondary"><?=\e(\t('Annuler','Cancel'))?></button></form></section><script src="<?=\e(\url('content/plugins/otp/vendor/qrcodegen.js'))?>" defer></script><script src="<?=\e(\url('content/plugins/otp/otp.js'))?>" defer></script>
        <?php endif;?>
        <?php else:?>
        <section class="panel"><h2><?=\e(\t('Renouveler les codes de secours','Replace recovery codes'))?></h2><p><?=\e(\t('Cette opération invalide tous les anciens codes de secours et les autres sessions. Un code TOTP ne peut pas être utilisé deux fois ; attendez le suivant si nécessaire.','This invalidates all previous recovery codes and other sessions. A TOTP cannot be used twice; wait for the next code if necessary.'))?></p><?=self::start('codes')?><label><?=\e(\t('Code OTP actuel ou code de secours','Current OTP or recovery code'))?><input name="otp" required autocomplete="one-time-code" maxlength="40"></label><?=self::password()?><button class="button primary"><?=\e(\t('Générer de nouveaux codes','Generate replacement codes'))?></button></form></section>
        <details class="panel"><summary><?=\e(\t('Désactiver l’OTP','Disable OTP'))?></summary><?=self::start('disable')?><label><?=\e(\t('Code OTP actuel ou code de secours','Current OTP or recovery code'))?><input name="otp" required autocomplete="one-time-code" maxlength="40"></label><label>DISABLE<input name="confirm" pattern="DISABLE" required></label><?=self::password()?><button class="button danger"><?=\e(\t('Désactiver','Disable'))?></button></form></details>
        <?php endif;?>
        <section class="panel"><h2><?=\e(\t('Récupération et sauvegarde','Recovery and backup'))?></h2><p><?=\e(\t('L’export éditorial ne contient ni la clé OTP ni ses codes. Une sauvegarde complète doit aussi protéger tout le stockage privé, dont otp/key.php. Si le téléphone et les codes sont perdus, utilisez la commande locale SSH documentée. La désactivation de l’extension seule ne supprime pas l’obligation OTP.','Editorial exports contain neither the OTP secret nor recovery codes. Full backups must protect complete private storage including otp/key.php. If both phone and recovery codes are lost, use the documented local SSH command. Plugin deactivation alone does not remove OTP enforcement.'))?></p><code>php content/plugins/otp/bin/otp.php disable --confirm=DISABLE-OTP</code></section>
        <?php
    }
}
