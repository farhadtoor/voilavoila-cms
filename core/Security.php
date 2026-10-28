<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class PrivateSessions implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface {
    private mixed $handle = null;
    private string $id = '';
    public function __construct(private string $dir) {}
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool {
        if (is_resource($this->handle)) { flock($this->handle, LOCK_UN); fclose($this->handle); }
        $this->handle = null; return true;
    }
    private function path(string $id): string {
        if (!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D', $id)) throw new Failure('Session invalide.', 403);
        return $this->dir . '/' . $id . '.php';
    }
    public function read(string $id): string|false {
        $this->id = $id; $this->handle = fopen($this->path($id), 'c+b');
        if (!$this->handle || !flock($this->handle, LOCK_EX)) return false;
        chmod($this->path($id), 0600);
        $raw = stream_get_contents($this->handle);
        return str_starts_with($raw, Store::GUARD) ? substr($raw, strlen(Store::GUARD)) : '';
    }
    public function write(string $id, string $data): bool {
        if ($id !== $this->id || !is_resource($this->handle)) { $this->close(); $this->read($id); }
        rewind($this->handle);
        $body = Store::GUARD . $data;
        if (fwrite($this->handle, $body) !== strlen($body)) return false;
        return ftruncate($this->handle, strlen($body)) && fflush($this->handle);
    }
    public function destroy(string $id): bool { $this->close(); return !is_file($this->path($id)) || unlink($this->path($id)); }
    public function gc(int $max_lifetime): int|false {
        $n=0;
        foreach (glob($this->dir . '/*.php') as $p) if (filemtime($p) < time()-$max_lifetime) { if (@unlink($p)) $n++; }
        return $n;
    }
    public function validateId(string $id): bool { return is_file($this->path($id)) && filemtime($this->path($id)) > time()-43200; }
    public function updateTimestamp(string $id, string $data): bool { return touch($this->path($id)); }
}

final class Security {
    public static string $nonce;
    public static function nativeOrigin(): void {
        $expected=getenv('VV_CANONICAL_ORIGIN') ?: '';
        if($expected==='' || PHP_SAPI==='cli') return;
        if((self::tls()?'https://':'http://').strtolower($_SERVER['HTTP_HOST'] ?? '')!==strtolower(rtrim($expected,'/'))) throw new Failure('Origine indisponible / Origin unavailable.',404);
    }
    public static function quarantinePath(string $path): string {
        if(strlen($path)>2048 || preg_match('/%(?:00|2f|5c|25)/i',$path)) throw new Failure('Chemin refusé / Path refused.',403);
        $path=rawurldecode($path);
        if(!mb_check_encoding($path,'UTF-8') || preg_match('~[\x00-\x20\x7f\\\\]|(?:^|/)\.{1,2}(?:/|$)|//~',$path) || !str_starts_with($path,'/')) throw new Failure('Chemin refusé / Path refused.',403);
        return $path;
    }

    public static function local(): bool {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)
            && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/D', $_SERVER['HTTP_HOST'] ?? '') === 1;
    }
    public static function tls(): bool {
        if (($_SERVER['HTTPS'] ?? '') === 'on' || (string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
        $proxies = array_filter(explode(',', getenv('VV_TRUSTED_PROXIES') ?: ''));
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', $proxies, true) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
    public static function headers(bool $private = false): void {
        self::$nonce = base64_encode(random_bytes(18));
        header_remove('X-Powered-By');
        header("Content-Security-Policy: default-src 'none'; script-src 'self' 'nonce-" . self::$nonce . "'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'");
        header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        if (self::tls()) header('Strict-Transport-Security: max-age=31536000');
        if ($private) { header('Cache-Control: private, no-store, max-age=0'); header('X-Robots-Tag: noindex, nofollow'); }
    }
    public static function session(Store $store): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid','0'); ini_set('session.gc_maxlifetime','43200');
        session_set_save_handler(new PrivateSessions($store->dir . '/sessions'), true);
        session_name(self::tls() ? '__Secure-vvcms' : 'vvcms');
        session_set_cookie_params(['lifetime'=>0,'path'=>base_path() ?: '/', 'secure'=>self::tls(),'httponly'=>true,'samesite'=>'Strict']);
        session_start();
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function csrf(): string { return $_SESSION['csrf'] ?? ''; }
    public static function postOrigin(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new Failure('Méthode non autorisée.', 405);
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 70 * 1024 * 1024) throw new Failure('Envoi trop volumineux.', 413);
        if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') throw new Failure('Origine refusée.', 403);
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $o = parse_url($_SERVER['HTTP_ORIGIN']);
            $origin = strtolower(($o['host'] ?? '') . (isset($o['port']) ? ':' . $o['port'] : ''));
            $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
            if ($origin !== $host || ($o['scheme'] ?? '') !== (self::tls() ? 'https' : 'http')) throw new Failure('Origine refusée.', 403);
        }
    }
    public static function post(): void {
        self::postOrigin();
        $token = val($_POST, 'csrf');
        if (!$token || !hash_equals(self::csrf(), $token)) throw new Failure('Session expirée. Rechargez la page.', 403);
    }
    /** Separate signed double-submit token; anonymous forms never open an owner session. */
    public static function guestToken(Store $store,string $purpose): string {
        if(!self::tls() && !self::local()) return '';
        $name=(self::tls()?'__Secure-':'').'vvguest-'.substr(hash('sha256',\base_path().'|'.$purpose),0,12);
        $token=is_string($_COOKIE[$name] ?? null)?$_COOKIE[$name]:'';
        $secret=$store->read()['secret'];
        if(!self::validGuestToken($token,$purpose,$secret)) {
            $payload=time().'.'.bin2hex(random_bytes(24));
            $token=$payload.'.'.hash_hmac('sha256',$purpose.'|'.$payload,$secret);
            setcookie($name,$token,['expires'=>time()+7200,'path'=>\base_path() ?: '/','secure'=>self::tls(),'httponly'=>true,'samesite'=>'Strict']);
            $_COOKIE[$name]=$token;
        }
        header('Cache-Control: private, no-store');
        return $token;
    }
    private static function validGuestToken(string $token,string $purpose,string $secret): bool {
        if(!preg_match('/^(\d{10})\.([a-f0-9]{48})\.([a-f0-9]{64})$/D',$token,$m)) return false;
        return (int)$m[1]<=time() && (int)$m[1]>time()-7200 && hash_equals(hash_hmac('sha256',$purpose.'|'.$m[1].'.'.$m[2],$secret),$m[3]);
    }
    public static function guestPost(Store $store,string $purpose,string $token): void {
        self::postOrigin();
        if(!self::tls() && !self::local()) throw new Failure('HTTPS requis.',403);
        $name=(self::tls()?'__Secure-':'').'vvguest-'.substr(hash('sha256',\base_path().'|'.$purpose),0,12);
        $cookie=is_string($_COOKIE[$name] ?? null)?$_COOKIE[$name]:'';
        if(!$cookie || !hash_equals($cookie,$token) || !self::validGuestToken($token,$purpose,$store->read()['secret'])) throw new Failure('Formulaire expiré. Rechargez la page.',403);
    }
    public static function documentName(string $name): string {
        if($name==='' || strlen($name)>240 || !mb_check_encoding($name,'UTF-8') || preg_match('~[\x00-\x1f\x7f\\\\/<>:"|?*]~u',$name) || preg_match('/[\x{202a}-\x{202e}\x{2066}-\x{2069}]/u',$name)
            || preg_match('/(?:^|\.)(?:php\d*|phtml|pht|phar|html?|shtml|svg|m?js|cgi|exe|dll|com|bat|cmd|ps1|sh|bash|asp|aspx|jsp|htaccess)(?:\.|$)/i',$name) || $name[0]==='.' || trim($name)!==$name) throw new Failure('Nom ou extension de fichier refusé.');
        return $name;
    }
    /** Bounded in-memory inspection only; nothing is extracted or executed. */
    public static function documentArchive(string $bytes): array {
        if(!class_exists('ZipArchive')) throw new Failure('Activez PHP ZIP pour valider ce document.',503);
        if(!str_starts_with($bytes,"PK\x03\x04")) throw new Failure('Signature ZIP invalide.');
        $tmp=tempnam(sys_get_temp_dir(),'vv-document-');
        if($tmp===false) throw new Failure('Validation du document indisponible.',503);
        $zip=new \ZipArchive(); $opened=false;
        try {
            if(file_put_contents($tmp,$bytes)!==strlen($bytes)) throw new Failure('Validation du document impossible.',503);
            if($zip->open($tmp,\ZipArchive::RDONLY | \ZipArchive::CHECKCONS)!==true) throw new Failure('Archive ZIP invalide.');
            $opened=true;
            if($zip->numFiles<1 || $zip->numFiles>200) throw new Failure('Archive limitée à 200 entrées.');
            $total=0; $seen=[]; $rows=[];
            for($i=0;$i<$zip->numFiles;$i++) {
                $s=$zip->statIndex($i); $n=$s['name'] ?? ''; $key=mb_strtolower($n,'UTF-8');
                if(!$n || strlen($n)>220 || !mb_check_encoding($n,'UTF-8') || preg_match('~[\x00-\x1f\x7f\\\\:]|^/|(?:^|/)\.{1,2}(?:/|$)|//~',$n) || isset($seen[$key])) throw new Failure('Chemin ou doublon de ZIP refusé.');
                $seen[$key]=true; $ops=0; $attrs=0; $zip->getExternalAttributesIndex($i,$ops,$attrs); $kind=($attrs>>16)&0170000;
                if(!in_array($kind,[0,0100000,0040000],true) || !empty($s['encryption_method']) || !in_array($s['comp_method'] ?? 0,[0,8],true)) throw new Failure('Entrée spéciale, chiffrée ou compression non autorisée.');
                $size=(int)$s['size']; $total+=$size;
                if($size<0 || $size>8*1024*1024 || $total>32*1024*1024 || ($size>1048576 && $size>200*max(1,(int)$s['comp_size']))) throw new Failure('Archive décompressée trop volumineuse.',413);
                if(str_ends_with($n,'/')) { if($size!==0) throw new Failure('Dossier ZIP invalide.'); continue; }
                $body=$zip->getFromIndex($i);
                if(!is_string($body) || strlen($body)!==$size || hash('crc32b',$body)!==sprintf('%08x',$s['crc'])) throw new Failure('Intégrité ZIP incorrecte.');
                $rows[$n]=$body;
            }
            return $rows;
        } finally { if($opened) $zip->close(); @unlink($tmp); }
    }
    public static function xmlDocument(string $xml,int $max=1048576): \DOMDocument {
        if(strlen($xml)>$max || preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i',str_replace("\0",'',$xml))) throw new Failure('XML trop volumineux ou entités interdites.');
        $doc=new \DOMDocument(); $old=libxml_use_internal_errors(true);
        try { $ok=$doc->loadXML($xml,LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        if(!$ok || $doc->doctype) throw new Failure('Document XML invalide.');
        return $doc;
    }
    public static function password(string $password): void {
        if (strlen($password) < 14 || strlen($password) > 72 || str_contains($password, "\0"))
            throw new Failure('Choisissez un mot de passe de 14 à 72 octets.');
    }
    public static function hash(string $password): string {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }
    public static function owner(Store $store): bool {
        $s = $store->read(); $a = $_SESSION['auth'] ?? [];
        if (!$a || !($s['installed'] ?? false)) return false;
        if (($a['epoch'] ?? '') !== ($s['owner']['epoch'] ?? null) || ($a['last'] ?? 0) < time()-1800
            || ($a['born'] ?? 0) < time()-43200 || ($a['ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
            unset($_SESSION['auth']); return false;
        }
        if(!SecondFactor::session($s,$a)) { unset($_SESSION['auth']); return false; }
        $_SESSION['auth']['last'] = time(); return true;
    }
    public static function requireOwner(Store $store): void {
        if (!self::tls() && !self::local()) throw new Failure('L’administration nécessite HTTPS.', 403);
        if (!self::owner($store)) throw new Failure('Connexion requise.', 401);
    }
    public static function login(Store $store, string $password): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $store->rate('login-ip',$ip,10,900); $store->rate('login-global','owner',60,900);
        $s = $store->read();
        if (!password_verify($password, $s['owner']['hash'] ?? '')) { $store->audit('login_failed'); throw new Failure('Connexion refusée.', 401); }
        self::establish($store);
        $store->audit('login_success');
    }
    /** Call only after independently verifying the owner password or recovery secret. */
    public static function establish(Store $store,bool $factorVerified=false): void {
        if(!$factorVerified) SecondFactor::start($store);
        $s=$store->read();
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['auth'] = ['epoch'=>$s['owner']['epoch'], 'born'=>time(),'last'=>time(),'ua'=>hash('sha256',$_SERVER['HTTP_USER_AGENT'] ?? '')];
        if(isset($s['owner']['factor']['id'])) $_SESSION['auth']['factor_id']=$s['owner']['factor']['id'];
    }
    public static function reauth(Store $store, string $password): void {
        $store->rate('reauth',$_SERVER['REMOTE_ADDR'] ?? 'cli',10,900);
        if (!password_verify($password, $store->read()['owner']['hash'] ?? '')) throw new Failure('Mot de passe actuel incorrect.', 403);
    }
}
