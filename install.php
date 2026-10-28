<?php
declare(strict_types=1);
define('VV_ADMIN',true);
require __DIR__.'/core/bootstrap.php';
use VV\Security; use VV\Failure; use VV\App; use VV\Store; use VV\Content;
if($vv->state['installed']) redirect(url('admin.php'));
if((getenv('VV_ALLOW_WEB_INSTALL')?:($_SERVER['VV_ALLOW_WEB_INSTALL']??''))!=='1') fail_page('Installation Web fermée. Utilisez la commande CLI install ou le profil serveur temporaire documenté.',404);
if(!Security::tls() && !Security::local()) fail_page('Ouvrez cette installation en HTTPS. Pour un essai local, utilisez localhost.',403);
$error='';
if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    try {
        Security::post(); $vv->store->rate('install',$_SERVER['REMOTE_ADDR'] ?? '',8,900);
        $key=trim(val($_POST,'setup_key')); $password=val($_POST,'password'); Security::password($password);
        $name=Content::text(val($_POST,'name')); $email=val($_POST,'email');
        if(!$name || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Failure('Renseignez un nom et un e-mail valides.');
        if($password!==val($_POST,'password_confirm')) throw new Failure('Les mots de passe ne correspondent pas.');
        $recovery=bin2hex(random_bytes(16));
        $vv->store->change(function(&$s) use($key,$name,$email,$password,$recovery) {
            if($s['installed']) throw new Failure('Le CMS est déjà installé.',409);
            if(!hash_equals($s['setup_hash'],hash('sha256',$key))) throw new Failure('Clé d’installation incorrecte.',403);
            $s=array_merge($s,App::seed($name,$email,$password)); $s['installed']=true;
            $s['owner']['recovery']=Security::hash($recovery); unset($s['setup_hash']); Store::log($s,'installed');
        });
        @unlink($vv->store->dir.'/setup-key.php');
        Security::login($vv->store,$password); $_SESSION['recovery_once']=$recovery;
        redirect(admin_url());
    } catch(Failure $e) { $error=$e->getMessage(); http_response_code($e->status); }
}
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installer · VoilaVoila CMS</title><link rel="stylesheet" href="<?=e(url('assets/admin.css',['v'=>VV_VERSION]))?>"></head><body class="auth-page"><main class="auth-card wide"><span class="brand-mark">V!</span><span class="eyebrow">VOILAVOILA CMS · <?=VV_VERSION?></span><h1>Votre espace.<br>Vos règles.</h1><p>Un propriétaire, un profil, tout l’essentiel pour publier.</p><?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><label>Clé d’installation<input name="setup_key" required autocomplete="off" maxlength="48"></label><p class="hint">Ouvrez <code>storage/setup-key.php</code> depuis votre accès SFTP et copiez la clé après la première ligne. Elle n’est jamais affichée sur le Web. En local : <code>php bin/console.php install-key</code>.</p><label>Nom du profil / du site<input name="name" required maxlength="200" value="<?=e(val($_POST,'name','Mon profil'))?>"></label><label>E-mail de gestion<input name="email" type="email" required maxlength="200" autocomplete="email" value="<?=e(val($_POST,'email'))?>"></label><div class="two"><label>Mot de passe<input name="password" type="password" required minlength="14" maxlength="72" autocomplete="new-password"></label><label>Confirmer<input name="password_confirm" type="password" required minlength="14" maxlength="72" autocomplete="new-password"></label></div><p class="hint">14 à 72 octets. Aucun compte public, aucune inscription.</p><button class="button primary" type="submit">Créer mon CMS</button></form><div class="footnote">PHP <?=e(PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION)?> · Fichiers locaux · Sans service externe</div></main></body></html>
