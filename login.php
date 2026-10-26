<?php
declare(strict_types=1);
define('VV_ADMIN',true); require __DIR__.'/core/bootstrap.php';
use VV\Security; use VV\Failure; use VV\Store;
if(!$vv->state['installed']) redirect(url('install.php'));
if(!Security::tls() && !Security::local()) fail_page('La connexion nécessite HTTPS.',403);
if(Security::owner($vv->store)) redirect(admin_url());
\VV\SecondFactor::login($vv);
$error=''; $recover=val($_GET,'mode')==='recovery';
if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    try {
        Security::post();
        if($recover) {
            $vv->store->rate('recovery',$_SERVER['REMOTE_ADDR'] ?? '',5,3600);
            $vv->store->rate('recovery-global','owner',20,3600);
            $password=val($_POST,'password'); Security::password($password);
            if($password!==val($_POST,'password_confirm')) throw new Failure('Les mots de passe ne correspondent pas.');
            $newCode=bin2hex(random_bytes(16));
            $vv->store->change(function(&$s) use($password,$newCode) {
                if(!password_verify(trim(val($_POST,'recovery')),$s['owner']['recovery'])) throw new Failure('Récupération refusée.',403);
                $s['owner']['hash']=Security::hash($password); $s['owner']['epoch']=bin2hex(random_bytes(16)); $s['owner']['recovery']=Security::hash($newCode);
                Store::log($s,'owner_recovered');
            });
            $_SESSION['recovery_once']=$newCode; Security::establish($vv->store);
        } else Security::login($vv->store,val($_POST,'password'));
        redirect(admin_url());
    } catch(Failure $e) { $error=$e->getMessage(); http_response_code($e->status); }
}
?><!doctype html><html lang="<?=e(lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(t('Connexion','Sign in'))?> · VoilaVoila CMS</title><link rel="stylesheet" href="<?=e(url('assets/admin.css'))?>"></head><body class="auth-page"><main class="auth-card"><span class="brand-mark">V!</span><span class="eyebrow">VOILAVOILA CMS</span><h1><?=e($recover?t('Retrouver votre espace.','Recover your space.'):t('De retour chez vous.','Welcome back.'))?></h1><p><?=e(t('Votre administration personnelle.','Your personal administration.'))?></p><?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf_field()?><?php if($recover):?><label><?=e(t('Code de récupération','Recovery code'))?><input name="recovery" required autocomplete="off" maxlength="32"></label><?php endif;?><label><?=e($recover?t('Nouveau mot de passe','New password'):t('Mot de passe','Password'))?><input type="password" name="password" required autocomplete="<?=$recover?'new-password':'current-password'?>" maxlength="72" <?=$recover?'minlength="14"':''?>></label><?php if($recover):?><label><?=e(t('Confirmer','Confirm'))?><input type="password" name="password_confirm" required autocomplete="new-password" minlength="14" maxlength="72"></label><?php endif;?><button class="button primary" type="submit"><?=e(t('Ouvrir mon espace','Open my space'))?></button></form><div class="auth-links"><a href="<?=e(url('index.php'))?>"><?=e(t('Voir le site','View website'))?></a><a href="<?=e(url('login.php',['mode'=>$recover?'login':'recovery','lang'=>lang()]))?>"><?=e($recover?t('Connexion','Sign in'):t('Accès perdu ?','Lost access?'))?></a></div></main></body></html>
