<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
foreach(['Archive','Syntax','Compiler','Analyzer','Generator','Jobs'] as $vvPorterClass) require_once __DIR__.'/lib/'.$vvPorterClass.'.php';
unset($vvPorterClass);

$vv->adminPage('wp-porter',t('Portage WordPress','WordPress porting'),static function($app) {
    if(version_compare(VV_VERSION,'1.1.0','<')) {echo '<section class="panel"><p>'.e(t('Passez à VoilaVoila CMS 1.1.0 ou supérieur.','Upgrade to VoilaVoila CMS 1.1.0 or later.')).'</p></section>';return;}
    echo '<link rel="stylesheet" href="'.e(url('content/plugins/wp-porter/porter.css')).'"><div class="porter-screen">';
    $missing=\VV\WPPorter\Archive::requirements();
    echo '<section class="panel"><span class="eyebrow">WORDPRESS → VOILAVOILA</span><h2>'.e(t('Analyser et préparer un portage','Analyze and prepare a native port')).'</h2><p>'.e(t('Importez un ZIP de thème ou d’extension WordPress. Les cas pris en charge donnent un module natif ; les autres donnent un projet à finaliser et un rapport. Le code importé n’est jamais exécuté par l’analyseur.','Upload a WordPress theme or plugin ZIP. Supported cases produce a native module; other cases produce a porting draft and a report. Imported code is never executed by the analyzer.')).'</p></section>';
    if($missing) {echo '<section class="panel"><h3>'.e(t('Prérequis manquants','Missing requirements')).'</h3><p>'.e(implode(', ',$missing)).'</p></section></div>';return;}
    echo '<section class="panel"><h3>'.e(t('1. Analyser un ZIP','1. Analyze a ZIP')).'</h3><form method="post" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="plugin.save"><input type="hidden" name="porter_op" value="analyze"><input type="hidden" name="MAX_FILE_SIZE" value="12582912">';
    echo '<label>'.e(t('Archive WordPress (12 Mio maximum)','WordPress archive (12 MiB maximum)')).'<input type="file" name="wp_zip" accept=".zip,application/zip" required></label>';
    echo '<div class="two"><label>'.e(t('Type','Type')).'<select name="source_type"><option value="auto">'.e(t('Détection automatique','Automatic detection')).'</option><option value="theme">'.e(t('Thème','Theme')).'</option><option value="plugin">'.e(t('Extension','Plugin')).'</option></select></label><label>'.e(t('Identifiant natif (facultatif)','Native ID (optional)')).'<input name="native_id" maxlength="51" pattern="[a-z][a-z0-9-]{1,50}" placeholder="ported-mon-module"></label></div>';
    echo '<label>'.e(t('Nom du module natif (facultatif)','Native module name (optional)')).'<input name="native_name" maxlength="120"></label><button type="submit" class="button primary">'.e(t('Analyser et préparer','Analyze and prepare')).'</button></form><p class="hint">'.e(t('Thèmes classiques simples et shortcodes constants pris en charge. Gutenberg, WooCommerce, comptes, tables SQL, AJAX et fonctions métier demandent un portage. Aucun remplacement ou activation automatique.','Simple classic templates and constant shortcodes are supported. Gutenberg, WooCommerce, accounts, SQL tables, AJAX and business logic require porting. Nothing is replaced or activated automatically.')).'</p></section>';
    try {$jobs=(new \VV\WPPorter\Jobs($app->store))->all();}
    catch(\VV\WPPorter\PortError $e) {echo '<section class="panel"><p>'.e(t($e->fr,$e->en)).'</p></section></div>';return;}
    echo '<section class="panel"><h3>'.e(t('2. Rapports et téléchargements','2. Reports and downloads')).'</h3><p class="hint">'.e(t('Trois résultats privés au maximum, conservés 24 heures. Le nettoyage intervient à la prochaine ouverture de cet écran ou opération. Les références de code peuvent être privées.','At most three private results, retained for 24 hours. Cleanup runs on the next screen visit or operation. Source references may contain private code.')).'</p>';
    if(!$jobs) echo '<p>'.e(t('Aucune analyse pour le moment.','No analysis yet.')).'</p>';
    foreach($jobs as $id=>$record) {
        $r=$record['report'];$ready=$r['status']==='converted';
        echo '<details open><summary><strong>'.e($r['source']['name']).'</strong> · '.e($ready?t('Portage limité généré','Limited port generated'):t('Projet à finaliser','Porting draft')).'</summary>';
        echo '<p>'.e($r['source']['type'].' · '.$r['source']['version'].' · '.$r['summary']['blockers'].' '.t('points à porter','porting blockers').' · '.$r['summary']['native_features'].' '.t('fonctions natives','native features').' · '.$r['summary']['compiled_templates'].' '.t('modèles compilés','compiled templates')).'</p>';
        if(!$ready) echo '<p><strong>'.e(t('Ce projet ne reproduit pas les fonctionnalités WordPress d’origine. Son point d’entrée est un brouillon explicite.','This draft does not implement the original WordPress functionality. Its entry point is explicitly a draft.')).'</strong></p>';
        if($r['features']) {echo '<p>';foreach($r['features'] as $f) echo '<code>'.e($f['tag']).'</code> ';echo '</p>';}
        if($r['issues']) {echo '<ul>';foreach($r['issues'] as $i) echo '<li><strong>'.e($i['severity']).'</strong> '.e($i['file'].($i['line']?':'.$i['line']:'')).' — '.e(t($i['fr'],$i['en'])).'</li>';echo '</ul>';}
        if($r['apis']) {echo '<details><summary>'.e(t('Fonctions repérées et API native','Detected functions and native API')).'</summary><ul>';foreach($r['apis'] as $api) echo '<li><code>'.e($api['name']).'</code> × '.e($api['count']).' → '.e($api['native'] ?: t('Conception manuelle','Manual design')).'</li>';echo '</ul></details>';}
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="plugin.save"><input type="hidden" name="job_id" value="'.e($id).'"><label>'.e(t('Mot de passe actuel pour télécharger ou supprimer','Current password to download or delete')).'<input type="password" name="current_password" autocomplete="current-password" required></label><div class="button-row"><button name="porter_op" value="download_package" class="button primary">'.e($ready?t('Télécharger le ZIP natif','Download native ZIP'):t('Télécharger le projet à finaliser','Download porting draft')).'</button><button name="porter_op" value="download_report" class="button secondary">'.e(t('Rapport JSON','JSON report')).'</button><button name="porter_op" value="delete" class="button secondary">'.e(t('Supprimer ce résultat','Delete this result')).'</button></div></form></details><hr>';
    }
    echo '</section><section class="panel"><h3>'.e(t('3. Relire, tester, installer','3. Review, test, install')).'</h3><p>'.e(t('Le ZIP généré contient cms.json, les fichiers natifs et porting/REPORT.md avec les sources de référence en .txt. Sur une copie d’essai du CMS, installez le ZIP dans Thèmes ou Extensions. Le CMS le laisse inactif. Vérifiez le résultat avant de l’activer sur votre site. Aucune donnée WordPress n’est migrée.','The output ZIP contains cms.json, native files and porting/REPORT.md, with source references stored as .txt. Install it under Themes or Plugins on a test copy of the CMS; the installer leaves it inactive. Review the result before activating it on your site. No WordPress site data is migrated.')).'</p></section></div>';
},static function($app,$post) {
    try {
        if(version_compare(VV_VERSION,'1.1.0','<')) throw new \VV\WPPorter\PortError('CMS 1.1.0 minimum requis.','CMS 1.1.0 or later is required.',503);
        $op=val($post,'porter_op');$jobs=new \VV\WPPorter\Jobs($app->store);
        if($op==='analyze') {
            $file=$_FILES['wp_zip'] ?? [];
            if(($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || !is_string($file['name'] ?? null) || strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='zip') throw new \VV\WPPorter\PortError('Choisissez un ZIP valide (12 Mio maximum).','Choose a valid ZIP (12 MiB maximum).');
            $source=new \VV\WPPorter\Archive($file['tmp_name']);
            $analysis=\VV\WPPorter\Analyzer::analyze($source,val($post,'source_type','auto'));
            $id=trim(val($post,'native_id')) ?: $analysis['report']['target']['suggested_id'];
            $name=trim(val($post,'native_name')) ?: $analysis['report']['source']['name'];
            $result=\VV\WPPorter\Generator::build($source,$analysis,$id,$name);
            $jobs->save($result);$app->store->audit('wp_porter_analyzed',$result['report']['source']['type'].':'.$result['report']['status']);return;
        }
        if(!in_array($op,['download_package','download_report','delete'],true)) throw new \VV\WPPorter\PortError('Action de portage inconnue.','Unknown porting operation.');
        \VV\Security::reauth($app->store,val($post,'current_password'));
        $id=val($post,'job_id');
        if($op==='delete') {$jobs->delete($id);$app->store->audit('wp_porter_deleted');return;}
        $row=$jobs->read($id,$op==='download_package');
        $bytes=$op==='download_package'?$row['bytes']:\VV\WPPorter\Generator::json($row['report']);
        $filename=$op==='download_package'?$row['filename']:'wp-port-report-'.$id.'.json';
        header('Content-Type: '.($op==='download_package'?'application/zip':'application/json; charset=utf-8'));
        header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.strlen($bytes));
        echo $bytes;exit;
    } catch(\VV\WPPorter\PortError $e) {throw new \VV\Failure(t($e->fr,$e->en),$e->status);}
});
