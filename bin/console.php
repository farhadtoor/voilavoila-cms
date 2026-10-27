<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
if(($argv[1]??'')==='benchmark'){require __DIR__.'/benchmark.php';exit;}
// Preflight also works before installation and never opens the real database.
if(($argv[1] ?? '')==='doctor') {
    define('VV_ROOT',dirname(__DIR__));
    require VV_ROOT.'/core/Hosting.php';
    $report=\VV\Hosting::report(getenv('VV_STORAGE_PATH') ?: VV_ROOT.'/storage');
    if(in_array('--json',$argv,true)) echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
    else {
        echo 'VoilaVoila CMS '.$report['cms_version'].' — PHP '.$report['php'].PHP_EOL;
        foreach($report['checks'] as $check) echo strtoupper($check['status']).' '.$check['label_en'].': '.$check['value'].PHP_EOL;
    }
    exit($report['passed']?0:1);
}
require dirname(__DIR__).'/core/bootstrap.php';
use VV\Security; use VV\Store;
$command=$argv[1] ?? 'help';
switch($command) {
    case 'health':
        $vv->bootPlugins();echo json_encode(\VV\Operations::health($vv),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;break;
    case 'maintenance:run':
        $vv->bootPlugins();$budget=10;$limit=3;
        foreach(array_slice($argv,2) as $argument){if(preg_match('/^--budget=(\d+)$/D',$argument,$m))$budget=(int)$m[1];elseif(preg_match('/^--limit=(\d+)$/D',$argument,$m))$limit=(int)$m[1];else throw new \VV\Failure('Use --budget=1..30 --limit=1..10.');}
        if(!$vv->state['installed'])throw new \VV\Failure('Install the CMS first.');
        $result=\VV\Operations::run($vv,$budget,$limit);echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;if(!$result['passed'])exit(1);break;
    case 'forms:notifications': case 'forms:purge':
        if(!in_array('forms',$vv->state['plugins'],true)) throw new \VV\Failure('Activate Forms first.');
        $vv->bootPlugins();
        if($command==='forms:purge') {
            if(!in_array('--confirm=PURGE',$argv,true)) throw new \VV\Failure('Use --confirm=PURGE.');
            echo \VV\Forms::purgeExpired($vv)." requests purged.\n";
        } else echo json_encode(\VV\Forms::drain($vv),JSON_THROW_ON_ERROR).PHP_EOL;
        break;
    case 'wp:quarantine:status': case 'wp:quarantine:prepare': case 'wp:plugin:wrap': case 'wp:theme:wrap':
        require_once VV_ROOT.'/content/plugins/wp-runtime/Runtime.php';
        if($command==='wp:quarantine:status') {
            $cfg=\VV\WordPress\Runtime::config($vv);
            echo json_encode(['enabled'=>\VV\WordPress\Runtime::enabled($vv),'public_url'=>$cfg['public_url'],'prepared'=>is_file($cfg['engine_root'].'/engine-config.php')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
        } elseif($command==='wp:quarantine:prepare') {
            $raw=stream_get_contents(STDIN,4097);if(strlen($raw)>4096)throw new \VV\Failure('Database input too large.');
            $db=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($db))throw new \VV\Failure('Database JSON required on stdin.');
            echo \VV\WordPress\Runtime::prepare($vv,$argv[2]??'',$db).PHP_EOL;
        } else echo \VV\WordPress\Runtime::wrap($vv,$argv[2]??'',$command==='wp:plugin:wrap'?'plugin':'theme').PHP_EOL;
        break;
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
    default: echo "VoilaVoila CMS\n\nphp bin/console.php health        Diagnostic local complet\nphp bin/console.php maintenance:run --budget=10 --limit=3  Maintenance/cron\nphp bin/console.php doctor        Diagnostic hébergement sans écriture\nphp bin/console.php doctor --json Rapport à partager sans secret\nphp bin/console.php forms:notifications  Traiter les notifications Forms\nphp bin/console.php forms:purge --confirm=PURGE  Purger les demandes expirées\nphp bin/console.php wp:quarantine:status  État du moteur facultatif\nphp bin/console.php wp:quarantine:prepare ZIP < private-db.json\nphp bin/console.php wp:plugin:wrap ZIP\nphp bin/console.php wp:theme:wrap ZIP\nphp bin/console.php install-key   Afficher la clé de première installation\nphp bin/console.php status        Vérifier la configuration\nphp bin/console.php reset-owner   Réinitialiser l’accès localement\nphp bin/console.php safe-mode     Désactiver les extensions et restaurer Profil Or\n";
}
