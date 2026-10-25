<?php
declare(strict_types=1);
define('VV_ROOT', dirname(__DIR__));
define('VV_VERSION', '1.2.0');
if (PHP_VERSION_ID < 80300 || !extension_loaded('dom') || !extension_loaded('mbstring') || !extension_loaded('fileinfo') || !extension_loaded('pdo_sqlite')) {
    http_response_code(503); header('Content-Type: text/plain; charset=utf-8');
    $message='VoilaVoila CMS demande PHP 8.3+ et les extensions DOM, mbstring, fileinfo et pdo_sqlite. Consultez INSTALLATION.html.';
    if(PHP_SAPI==='cli') { fwrite(STDERR,$message.PHP_EOL); exit(1); }
    exit($message);
}
ini_set('display_errors','0'); error_reporting(E_ALL); date_default_timezone_set('UTC');
require __DIR__.'/Store.php';
require __DIR__.'/Logger.php';
require __DIR__.'/Media.php';
require __DIR__.'/Security.php';
require __DIR__.'/Content.php';
require __DIR__.'/App.php';

function val(array $from, string $key, string $default = ''): string { return isset($from[$key]) && is_string($from[$key]) ? $from[$key] : $default; }
function e(mixed $text): string { return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_path(): string { $b=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')); return $b==='/' || $b==='.' ? '' : rtrim($b,'/'); }
function url(string $file = '', array $args = []): string { return base_path().'/'.ltrim($file,'/') . ($args ? '?'.http_build_query($args,'','&',PHP_QUERY_RFC3986) : ''); }
function lang(): string { global $vv; return in_array(val($_GET,'lang'),['fr','en'],true) ? val($_GET,'lang') : ($vv->state['settings']['language'] ?? 'fr'); }
function t(string $fr, string $en): string { return lang()==='en' ? $en : $fr; }
function admin_url(string $view='dashboard', array $args=[]): string { return url('admin.php', array_merge(['view'=>$view,'lang'=>lang()],$args)); }
function redirect(string $target): never { header('Location: '.$target,true,303); exit; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(\VV\Security::csrf()).'">'; }
function fail_page(string $message, int $status=400): never {
    http_response_code($status); header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="'.e(url('assets/admin.css')).'"><title>VoilaVoila CMS</title><main class="auth-card"><span class="eyebrow">VOILAVOILA CMS</span><h1>Une étape à vérifier</h1><p>'.e($message).'</p><a class="button" href="'.e(url('admin.php')).'">Administration</a></main></html>'; exit;
}
set_exception_handler(function (Throwable $err) {
    if(PHP_SAPI==='cli') {
        if(!($err instanceof \VV\Failure) || $err->status>=500) \VV\Logger::error('cli',$err->getMessage(),$err->getFile(),$err->getLine());
        fwrite(STDERR,($err instanceof \VV\Failure?$err->getMessage():'Erreur du CMS. Consultez error.txt.').PHP_EOL); exit(1);
    }
    if ($err instanceof \VV\Failure) {
        if($err->status>=500) \VV\Logger::error('application',$err->getMessage(),$err->getFile(),$err->getLine());
        fail_page($err->getMessage(),$err->status);
    }
    \VV\Logger::error('exception',$err->getMessage(),$err->getFile(),$err->getLine());
    fail_page('Une erreur est survenue. Consultez le journal error.txt dans votre stockage privé.',500);
});
\VV\Security::headers(defined('VV_ADMIN'));
$vvStorage=getenv('VV_STORAGE_PATH') ?: VV_ROOT.'/storage';
\VV\Logger::init($vvStorage);
$vv = new \VV\App(new \VV\Store($vvStorage));
if (defined('VV_ADMIN')) \VV\Security::session($vv->store);

// Small, explicit extension API. PHP extensions are trusted server-side code.
function vv_action(string $name, callable $fn, int $priority=10): void { global $vv; $vv->hooks[$name][$priority][]=$fn; }
function vv_do(string $name, mixed ...$args): void { global $vv; $groups=$vv->hooks[$name] ?? []; ksort($groups); foreach($groups as $callbacks) foreach($callbacks as $fn) $fn(...$args); }
function vv_filter(string $name, mixed $value, mixed ...$args): mixed { global $vv; $groups=$vv->hooks[$name] ?? []; ksort($groups); foreach($groups as $callbacks) foreach($callbacks as $fn) $value=$fn($value,...$args); return $value; }
function vv_option(string $plugin, string $key, mixed $default=null): mixed { global $vv; return $vv->state['plugin_data'][$plugin][$key] ?? $default; }
function vv_shortcode(string $tag, callable $fn): void { global $vv; if(preg_match('/^[a-z][a-z0-9_-]{0,30}$/D',$tag)) $vv->shortcodes[$tag]=$fn; }
