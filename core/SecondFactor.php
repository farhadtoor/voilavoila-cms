<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** The enforcement hook is independent of plugin boot and activation choices. */
final class SecondFactor {
    public static function configured(array $s): bool {return isset($s['owner']['factor']);}
    private static function provider(array $s): string {
        $factor=$s['owner']['factor']??null;
        if(!is_array($factor)||($factor['provider']??'')!=='otp'||($factor['schema']??0)!==1)throw new Failure('Second facteur indisponible. Utilisez la récupération locale / Second factor unavailable. Use local recovery.',503);
        $file=VV_ROOT.'/content/plugins/otp/Auth.php';
        if(!is_file($file)||is_link($file))throw new Failure('Extension OTP manquante. Restaurez ses fichiers ou utilisez la récupération SSH / Missing OTP extension. Restore it or use SSH recovery.',503);
        require_once $file;return \VV\Authentication\OtpAuth::class;
    }
    public static function start(Store $store): void {
        $s=$store->read();if(self::configured($s)){self::provider($s)::start($store);}
    }
    public static function session(array $s,array $auth): bool {
        if(!self::configured($s))return true;self::provider($s);
        return is_string($auth['factor_id']??null)&&is_string($s['owner']['factor']['id']??null)&&hash_equals($s['owner']['factor']['id'],$auth['factor_id']);
    }
    public static function login(App $app): void {
        if(\val($_GET,'factor')!=='1')return;$s=$app->store->read();
        if(!self::configured($s)){unset($_SESSION['pending_factor']);\redirect(\url('login.php'));}
        self::provider($s)::handleLogin($app);
    }
    public static function guardPlugin(string $id,array $s): void {
        if(self::configured($s)&&($s['owner']['factor']['provider']??'')===$id)throw new Failure('Désactivez d’abord le second facteur dans son panneau / Disable the second factor in its panel first.',409);
    }
}
