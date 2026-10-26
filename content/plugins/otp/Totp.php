<?php
declare(strict_types=1);
namespace VV\Authentication;
defined('VV_ROOT') || exit;

/** RFC 4226/6238. Deployment profile: SHA-1, six digits, 30 seconds. */
final class Totp {
    public static function base32(string $bytes): string {
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$out='';$buffer=0;$bits=0;
        for($i=0;$i<strlen($bytes);$i++){$buffer=($buffer<<8)|ord($bytes[$i]);$bits+=8;while($bits>=5){$bits-=5;$out.=$alphabet[($buffer>>$bits)&31];}$buffer&=(1<<$bits)-1;}
        if($bits)$out.=$alphabet[($buffer<<(5-$bits))&31];return $out;
    }
    public static function decode(string $text): string {
        if(!preg_match('/^[A-Z2-7]{16,128}$/D',$text))throw new \VV\Failure('Clé TOTP invalide / Invalid TOTP key.',503);
        $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$out='';$buffer=0;$bits=0;
        for($i=0;$i<strlen($text);$i++){$buffer=($buffer<<5)|strpos($alphabet,$text[$i]);$bits+=5;if($bits>=8){$bits-=8;$out.=chr(($buffer>>$bits)&255);$buffer&=(1<<$bits)-1;}}
        if($bits&&$buffer!==0)throw new \VV\Failure('Clé TOTP non canonique / Non-canonical TOTP key.',503);return $out;
    }
    public static function hotp(string $key,int $counter,int $digits=6,string $algorithm='sha1'): string {
        if($counter<0||!in_array($digits,[6,8],true)||!in_array($algorithm,['sha1','sha256','sha512'],true))throw new \InvalidArgumentException('Invalid HOTP profile.');
        $bytes=pack('NN',intdiv($counter,4294967296),$counter%4294967296);$hash=hash_hmac($algorithm,$bytes,$key,true);$offset=ord($hash[strlen($hash)-1])&15;
        $number=((ord($hash[$offset])&127)<<24)|(ord($hash[$offset+1])<<16)|(ord($hash[$offset+2])<<8)|ord($hash[$offset+3]);return str_pad((string)($number%(10**$digits)),$digits,'0',STR_PAD_LEFT);
    }
    public static function at(string $secret,int $time,int $digits=6,string $algorithm='sha1'): string {return self::hotp(self::decode($secret),intdiv($time,30),$digits,$algorithm);}
    public static function match(string $secret,string $code,int $time,int $last=-1): ?int {
        if(!preg_match('/^[0-9]{6}$/D',$code))return null;$now=intdiv($time,30);$key=self::decode($secret);$matched=null;
        foreach([$now,$now-1,$now+1] as $step)if($step>=0&&$step>$last&&hash_equals(self::hotp($key,$step),$code))$matched=$step;return $matched;
    }
    public static function uri(string $secret,string $account): string {
        self::decode($secret);$issuer='VoilaVoila CMS';$account=str_replace(':',' ',mb_substr($account,0,100));
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account).'?'.http_build_query(['secret'=>$secret,'issuer'=>$issuer,'algorithm'=>'SHA1','digits'=>6,'period'=>30],'','&',PHP_QUERY_RFC3986);
    }
}
