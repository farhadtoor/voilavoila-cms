<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/core/bootstrap.php';
use VV\Security; use VV\Store;
$command=$argv[1] ?? 'help';
switch($command) {
    case 'install-key':
        if($vv->state['installed']) { fwrite(STDERR,"Le CMS est déjà installé.\n"); exit(1); }
        echo 'Clé : '.$vv->store->privateRead($vv->store->dir.'/setup-key.php')."\n"; break;
    case 'reset-owner':
        if(!$vv->state['installed']) { fwrite(STDERR,"Installez d’abord le CMS.\n"); exit(1); }
        $tty=function_exists('stream_isatty') && stream_isatty(STDIN);
        if($tty && !function_exists('system')) { fwrite(STDERR,"Entrée masquée indisponible. Fournissez le mot de passe par une entrée standard non interactive.\n"); exit(1); }
        fwrite(STDERR,"Nouveau mot de passe (14 à 72 octets) : ");
        if($tty) system('stty -echo');
        try { $password=rtrim((string)fgets(STDIN),"\r\n"); } finally { if($tty) system('stty echo'); fwrite(STDERR,"\n"); }
        Security::password($password); $code=bin2hex(random_bytes(16));
        $vv->store->change(function(&$s) use($password,$code) {
            $s['owner']['hash']=Security::hash($password); $s['owner']['epoch']=bin2hex(random_bytes(16)); $s['owner']['recovery']=Security::hash($code); $s['limits']=[];
            Store::log($s,'owner_reset_cli');
        });
        echo "Accès réinitialisé. Toutes les sessions sont révoquées.\nNouveau code de récupération à conserver hors ligne : $code\n"; break;
    case 'safe-mode':
        $vv->store->change(function(&$s){$s['plugins']=[]; $s['settings']['theme']='profil-or'; Store::log($s,'safe_mode_cli');});
        echo "Extensions désactivées. Thème Profil Or restauré. Contenus conservés.\n"; break;
    case 'migrate':
        echo 'Stockage SQLite prêt : '.$vv->store->databasePath()."\nMigration automatique terminée ou déjà effectuée.\n"; break;
    case 'status':
        echo 'VoilaVoila CMS '.VV_VERSION."\nPHP ".PHP_VERSION."\n";
        echo 'SQLite : '.$vv->store->databasePath()."\n";
        echo 'Installé : '.($vv->state['installed']?'oui':'non')."\nContenus : ".count($vv->state['contents'])."\nMédias : ".count($vv->state['media'])."\n";
        foreach(['dom','mbstring','fileinfo','pdo_sqlite','gd','zip'] as $module) echo $module.' : '.(extension_loaded($module)?'OK':'ABSENT')."\n";
        break;
    default: echo "VoilaVoila CMS\n\nphp bin/console.php install-key   Afficher la clé de première installation\nphp bin/console.php status        Vérifier la configuration\nphp bin/console.php reset-owner   Réinitialiser l’accès localement\nphp bin/console.php safe-mode     Désactiver les extensions et restaurer Profil Or\n";
}
