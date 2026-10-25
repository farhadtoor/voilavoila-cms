<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class Failure extends \RuntimeException {
    public function __construct(string $message, public int $status = 400) { parent::__construct($message); }
}

/** Single-writer, atomic flat-file database. Never evaluate stored data. */
final class Store {
    public const GUARD = "<?php http_response_code(404); exit; ?>\n";
    public function __construct(public string $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new Failure('Stockage indisponible.', 503);
        if (!is_writable($dir)) throw new Failure('Le dossier storage doit être accessible en écriture.', 503);
        foreach (['media', 'sessions', 'backups'] as $part) {
            if (!is_dir("$dir/$part") && !mkdir("$dir/$part", 0700, true)) throw new Failure('Création du stockage impossible.', 503);
        }
        $this->change(function (&$s) {
            if (!$s) {
                $key = bin2hex(random_bytes(24));
                $this->privateWrite($this->dir . '/setup-key.php', $key);
                $s = ['schema'=>1, 'installed'=>false, 'secret'=>bin2hex(random_bytes(32)),
                    'setup_hash'=>hash('sha256', $key), 'owner'=>[], 'settings'=>[], 'contents'=>[],
                    'media'=>[], 'plugins'=>[], 'plugin_data'=>[], 'audit'=>[], 'limits'=>[]];
            }
        });
    }
    public function privateWrite(string $path, string $bytes): void {
        $tmp = dirname($path) . '/tmp-' . bin2hex(random_bytes(12)) . '.php';
        $body = self::GUARD . $bytes;
        $f = fopen($tmp, 'xb');
        if (!$f) throw new Failure('Écriture impossible.', 503);
        try {
            chmod($tmp, 0600);
            $offset = 0;
            while ($offset < strlen($body)) {
                $n = fwrite($f, substr($body, $offset));
                if (!$n) throw new Failure('Espace disque insuffisant.', 503);
                $offset += $n;
            }
            if (!fflush($f)) throw new Failure('Écriture interrompue.', 503);
            if (function_exists('fsync')) fsync($f);
        } finally { fclose($f); }
        if (!rename($tmp, $path)) { @unlink($tmp); throw new Failure('Publication du fichier impossible.', 503); }
    }
    public function privateRead(string $path): string {
        $b = file_get_contents($path);
        if (!is_string($b) || !str_starts_with($b, self::GUARD)) throw new Failure('Fichier privé invalide.', 503);
        return substr($b, strlen(self::GUARD));
    }
    private function lock(int $mode) {
        $f = fopen($this->dir . '/lock.php', 'c+b');
        if (!$f || !flock($f, $mode)) throw new Failure('Verrouillage du stockage impossible.', 503);
        if (fstat($f)['size'] === 0) { fwrite($f, self::GUARD); fflush($f); chmod($this->dir . '/lock.php', 0600); }
        return $f;
    }
    private function readUnlocked(): array {
        $p = $this->dir . '/state.php';
        if (!is_file($p)) return [];
        try { $s = json_decode($this->privateRead($p), true, 128, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new Failure('Le stockage est illisible. Restaurez une sauvegarde.', 503); }
        if (!is_array($s) || ($s['schema'] ?? null) !== 1) throw new Failure('Version de données non prise en charge.', 503);
        return $s;
    }
    public function read(): array {
        $f = $this->lock(LOCK_SH);
        try { return $this->readUnlocked(); } finally { flock($f, LOCK_UN); fclose($f); }
    }
    public function change(callable $fn): mixed {
        $f = $this->lock(LOCK_EX);
        try {
            $s = $this->readUnlocked(); $before = $s;
            $result = $fn($s);
            if ($s !== $before) {
                $json = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                if (strlen($json) > 32 * 1024 * 1024) throw new Failure('Stockage éditorial limité à 32 Mo. Exportez des contenus.', 413);
                $this->privateWrite($this->dir . '/state.php', $json);
            }
            return $result;
        } finally { flock($f, LOCK_UN); fclose($f); }
    }
    public static function log(array &$s, string $event, string $detail = ''): void {
        $s['audit'][] = ['at'=>gmdate('c'), 'event'=>$event, 'detail'=>mb_substr(strip_tags($detail), 0, 200)];
        $s['audit'] = array_slice($s['audit'], -250);
    }
    public function audit(string $event, string $detail = ''): void {
        $this->change(function (&$s) use ($event, $detail) { self::log($s, $event, $detail); });
    }
    public function rate(string $kind, string $identity, int $max, int $window): void {
        $allowed = $this->change(function (&$s) use ($kind, $identity, $max, $window) {
            $now = time();
            $s['limits'] = array_filter($s['limits'], fn($r) => $r['end'] > $now);
            if (count($s['limits']) > 2000) return false;
            $key = hash_hmac('sha256', $kind . ':' . $identity, $s['secret']);
            $r = $s['limits'][$key] ?? ['end'=>$now+$window, 'n'=>0];
            $r['n']++; $s['limits'][$key] = $r;
            return $r['n'] <= $max;
        });
        if (!$allowed) throw new Failure('Trop de tentatives. Réessayez plus tard.', 429);
    }
}
