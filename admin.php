<?php
declare(strict_types=1);
define('VV_ADMIN',true); require __DIR__.'/core/bootstrap.php'; require __DIR__.'/core/Tools.php';
use VV\Security; use VV\Content; use VV\Store; use VV\Failure; use VV\Tools;
if(!$vv->state['installed']) redirect(url('install.php'));
if(!Security::tls() && !Security::local()) fail_page('L’administration nécessite HTTPS.',403);
if(!Security::owner($vv->store)) redirect(url('login.php',['lang'=>lang()]));
$safe=val($_GET,'safe')==='1';
$vv->bootPlugins($safe);
$view=val($_GET,'view','dashboard');
$allowed=['dashboard','posts','pages','editor','media','themes','plugins','settings','security','tools'];
if(!in_array($view,$allowed,true) && !isset($vv->adminPages[$view])) $view='dashboard';
$error=''; $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    try {
        Security::post(); Security::requireOwner($vv->store);
        $action=val($_POST,'action'); $destination=admin_url($view); $message=t('Enregistré.','Saved.');
        switch($action) {
            case 'logout':
                $vv->store->audit('logout'); $_SESSION=[]; session_destroy();
                setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>base_path() ?: '/','secure'=>Security::tls(),'httponly'=>true,'samesite'=>'Strict']);
                redirect(url('login.php'));
            case 'content.save':
                $id=Content::save($vv->store,$_POST); $destination=admin_url('editor',['id'=>$id]); break;
            case 'content.duplicate':
                $id=val($_POST,'id'); $c=$vv->state['contents'][$id] ?? null; if(!$c) throw new Failure('Contenu introuvable.',404);
                $c['id']=''; $c['version']='0'; $c['status']='draft'; $c['slug'].='-copie-'.bin2hex(random_bytes(2)); $c['tags']=implode(',',$c['tags']);
                foreach(['pinned','menu'] as $k) if(!$c[$k]) unset($c[$k]);
                $id=Content::save($vv->store,$c); $destination=admin_url('editor',['id'=>$id]); break;
            case 'content.trash': case 'content.restore': case 'content.purge':
                $id=val($_POST,'id'); $version=(int)val($_POST,'version');
                if($action==='content.purge') { Security::reauth($vv->store,val($_POST,'current_password')); Tools::snapshot($vv); }
                $vv->store->change(function(&$s) use($id,$version,$action) {
                    if(!isset($s['contents'][$id])) throw new Failure('Contenu introuvable.',404);
                    $c=&$s['contents'][$id]; if($c['version']!==$version) throw new Failure('Contenu modifié. Rechargez la page.',409);
                    if($action==='content.purge') {
                        if($c['status']!=='trash') throw new Failure('Placez d’abord le contenu dans la corbeille.');
                        unset($s['contents'][$id]); if($s['settings']['home_page']===$id) $s['settings']['home_page']='';
                    } else { $c['status']=$action==='content.trash'?'trash':'draft'; $c['version']++; $c['updated_at']=gmdate('c'); }
                    Store::log($s,$action,$id);
                }); break;
            case 'content.revision':
                $id=val($_POST,'id'); $c=$vv->state['contents'][$id] ?? null; $n=(int)val($_POST,'revision','-1'); $r=$c['revisions'][$n] ?? null;
                if(!$c || !$r) throw new Failure('Révision introuvable.',404);
                if((int)val($_POST,'version')!==$c['version']) throw new Failure('Contenu modifié. Rechargez la page.',409);
                $r['id']=$id; $r['version']=(string)$c['version']; $r['tags']=implode(',',$r['tags']); $r['publish_at']=gmdate('Y-m-d\TH:i',$r['publish_at']);
                foreach(['pinned','menu'] as $k) if(!$r[$k]) unset($r[$k]);
                Content::save($vv->store,$r); $destination=admin_url('editor',['id'=>$id]); break;
            case 'media.upload':
                Content::upload($vv->store,$_FILES['file'] ?? [],isset($_POST['public']),val($_POST,'alt')); break;
            case 'media.update':
                $id=val($_POST,'id'); $alt=Content::text(val($_POST,'alt'),250); $public=isset($_POST['public']);
                $vv->store->change(function(&$s) use($id,$alt,$public) {
                    if(!isset($s['media'][$id])) throw new Failure('Média introuvable.',404);
                    if(!$public && (in_array($id,[$s['settings']['avatar'],$s['settings']['cover']],true) || array_filter($s['contents'],fn($c)=>$c['cover']===$id))) throw new Failure('Retirez d’abord cette image des couvertures et du profil.');
                    $s['media'][$id]['alt']=$alt; $s['media'][$id]['public']=$public; Store::log($s,'media_updated',$id);
                }); break;
            case 'media.delete':
                $id=val($_POST,'id'); Security::reauth($vv->store,val($_POST,'current_password'));
                $mediaToDelete=$vv->state['media'][$id] ?? null;
                $vv->store->change(function(&$s) use($id,$vv) {
                    if(!isset($s['media'][$id])) throw new Failure('Média introuvable.',404);
                    if(str_contains(json_encode([$s['settings'],$s['contents']]),$id)) throw new Failure('Ce média est utilisé par le profil, un contenu ou une révision. Retirez ses références avant suppression.');
                    unset($s['media'][$id]); Store::log($s,'media_deleted',$id);
                });
                if($mediaToDelete) @unlink($vv->store->mediaPath($mediaToDelete)); break;
            case 'logs.download':
                $logType=val($_POST,'type'); $logText=\VV\Logger::read($logType);
                header('Content-Type: text/plain; charset=utf-8'); header('Content-Disposition: attachment; filename="'.$logType.'.txt"');
                header('Content-Length: '.strlen($logText)); echo $logText; exit;
            case 'settings.save':
                $settings=Tools::settings($_POST,$vv->state['settings'],$vv->state['media']);
                $vv->store->change(function(&$s) use($settings) {
                    $home=$settings['home_page'];
                    if($home!=='' && (!isset($s['contents'][$home]) || $s['contents'][$home]['type']!=='page' || !Content::visible($s['contents'][$home]))) throw new Failure('La page d’accueil doit être une page publiée.');
                    $s['settings']=array_merge($settings,['theme'=>$s['settings']['theme']]); Store::log($s,'settings_saved');
                }); break;
            case 'theme.activate':
                $id=val($_POST,'id'); if(!isset($vv->packages('theme')[$id])) throw new Failure('Thème introuvable.');
                if(!\VV\App::compatible($vv->packages('theme')[$id])) throw new Failure('Version du thème incompatible avec ce CMS ou PHP.');
                $vv->store->change(function(&$s) use($id){$s['settings']['theme']=$id; Store::log($s,'theme_activated',$id);}); break;
            case 'plugin.toggle':
                $id=val($_POST,'id'); if(!isset($vv->packages('plugin')[$id])) throw new Failure('Extension introuvable.');
                if(!in_array($id,$vv->state['plugins'],true) && !\VV\App::compatible($vv->packages('plugin')[$id])) throw new Failure('Version de l’extension incompatible avec ce CMS ou PHP.');
                $vv->store->change(function(&$s) use($id) {
                    if(in_array($id,$s['plugins'],true)) \VV\SecondFactor::guardPlugin($id,$s);
                    if(in_array($id,$s['plugins'],true)) $s['plugins']=array_values(array_diff($s['plugins'],[$id])); else $s['plugins'][]=$id;
                    Store::log($s,'plugin_toggled',$id);
                }); break;
            case 'package.install':
                Security::reauth($vv->store,val($_POST,'current_password')); $type=val($_POST,'type');
                if(!in_array($type,['theme','plugin'],true)) throw new Failure('Type invalide.');
                Tools::installPackage($vv,$_FILES['package'] ?? [],$type); break;
            case 'package.delete':
                Security::reauth($vv->store,val($_POST,'current_password')); $type=val($_POST,'type'); $id=val($_POST,'id');
                if(!in_array($type,['theme','plugin'],true) || !isset($vv->packages($type)[$id]) || ($type==='theme' && $id==='profil-or')) throw new Failure('Module protégé ou introuvable.');
                $vv->store->change(function(&$s) use($type,$id) {
                    if($type==='plugin') \VV\SecondFactor::guardPlugin($id,$s);
                    if(($type==='theme' && $s['settings']['theme']===$id) || ($type==='plugin' && in_array($id,$s['plugins'],true))) throw new Failure('Désactivez d’abord ce module.');
                    Tools::removeTree(VV_ROOT.'/content/'.($type==='theme'?'themes':'plugins').'/'.$id);
                    Store::log($s,'package_deleted',$type.':'.$id);
                }); break;
            case 'owner.save':
                Security::reauth($vv->store,val($_POST,'current_password')); $pass=val($_POST,'password'); $email=val($_POST,'email');
                if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Failure('E-mail invalide.');
                if($pass!=='') { Security::password($pass); if($pass!==val($_POST,'password_confirm')) throw new Failure('Les mots de passe ne correspondent pas.'); }
                $recovery=isset($_POST['new_recovery'])?bin2hex(random_bytes(16)):''; $epoch=bin2hex(random_bytes(16));
                $vv->store->change(function(&$s) use($pass,$email,$recovery,$epoch){
                    $s['owner']['email']=$email; $s['owner']['epoch']=$epoch;
                    if($pass!=='') $s['owner']['hash']=Security::hash($pass);
                    if($recovery!=='') $s['owner']['recovery']=Security::hash($recovery);
                    Store::log($s,'owner_updated');
                });
                session_regenerate_id(true); $_SESSION['auth']['epoch']=$epoch; $_SESSION['csrf']=bin2hex(random_bytes(32));
                if($recovery) $_SESSION['recovery_once']=$recovery; break;
            case 'backup.export':
                Security::reauth($vv->store,val($_POST,'current_password'));
                $tmp=Tools::backup($vv); $vv->store->audit('backup_exported');
                header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="voilavoila-cms-'.gmdate('Y-m-d-His').'.zip"'); header('Content-Length: '.filesize($tmp));
                try { readfile($tmp); } finally { unlink($tmp); } exit;
            case 'backup.snapshot': Tools::snapshot($vv); $message=t('Point de restauration créé.','Restore point created.'); break;
            case 'backup.download':
                Security::reauth($vv->store,val($_POST,'current_password'));
                $id=val($_POST,'id'); if(!preg_match('/^\d{8}-\d{6}-[a-f0-9]{8}$/D',$id)) throw new Failure('Sauvegarde invalide.');
                $p=$vv->store->dir.'/backups/'.$id.'.php'; if(!is_file($p)) throw new Failure('Sauvegarde introuvable.',404);
                header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="cms-point-'.$id.'.zip"');
                $f=fopen($p,'rb'); fseek($f,strlen(Store::GUARD)); fpassthru($f); fclose($f); exit;
            case 'backup.restore':
                Security::reauth($vv->store,val($_POST,'current_password'));
                if(val($_POST,'confirm')!=='RESTAURER') throw new Failure('Saisissez RESTAURER pour remplacer les contenus actuels.');
                Tools::restore($vv,$_FILES['backup'] ?? []); $message=t('Sauvegarde restaurée. Le compte propriétaire est conservé.','Backup restored. The owner account was preserved.'); break;
            case 'wordpress.import':
                Security::reauth($vv->store,val($_POST,'current_password'));
                $count=Tools::wordpress($vv,$_FILES['wxr'] ?? [],isset($_POST['publish']),$_FILES['uploads'] ?? [],['source_url'=>val($_POST,'source_url'),'comments'=>isset($_POST['import_comments']),'public_media'=>isset($_POST['public_media'])]); $message=$count.' '.t('contenus importés.','items imported.'); break;
            case 'wordpress.report':
                $report=$vv->state['imports']['wordpress'] ?? null; if(!$report) throw new Failure('Aucun rapport WordPress.',404);
                header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="wordpress-import-report.json"');
                echo json_encode($report,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit;
            case 'editor.preview':
                header('Content-Type: application/json; charset=utf-8'); echo json_encode(['html'=>Content::html(val($_POST,'html'))],JSON_THROW_ON_ERROR); exit;
            case 'plugin.save':
                $page=$vv->adminPages[$view] ?? null; if(!$page || !$page['save']) throw new Failure('Action extension indisponible.');
                ($page['save'])($vv,$_POST); break;
            default: throw new Failure('Action inconnue.');
        }
        $_SESSION['flash']=$message; redirect($destination);
    } catch(Failure $err) {
        $error=$err->getMessage(); http_response_code($err->status);
        if($err->status>=500) \VV\Logger::error('admin_action',$error,$err->getFile(),$err->getLine());
        if(val($_POST,'action')==='editor.preview') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['error'=>$error]); exit; }
    }
}
$vv->refresh();
$labels=['dashboard'=>t('Vue d’ensemble','Overview'),'posts'=>t('Articles','Posts'),'pages'=>t('Pages','Pages'),'media'=>t('Médias','Media'),'themes'=>t('Thèmes','Themes'),'plugins'=>t('Extensions','Plugins'),'settings'=>t('Mon profil & site','My profile & site'),'security'=>t('Sécurité','Security'),'tools'=>t('Outils & sauvegardes','Tools & backups'),'editor'=>t('Éditeur','Editor')];
foreach($vv->adminPages as $k=>$p) $labels[$k]=$p['label'];
function action_form(string $action,string $label,array $fields=[],string $class='secondary',bool $reauth=false): string {
    $s='<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="action" value="'.e($action).'">';
    foreach($fields as $k=>$v) $s.='<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
    if($reauth) $s.='<label>'.e(t('Mot de passe actuel','Current password')).'<input name="current_password" type="password" required autocomplete="current-password"></label>';
    return $s.'<button type="submit" class="button '.$class.'">'.e($label).'</button></form>';
}
function media_select(string $name,string $selected,array $media): string {
    $html='<select name="'.e($name).'"><option value="">'.e(t('Aucune image','No image')).'</option>';
    foreach($media as $id=>$m) if($m['public'] && str_starts_with($m['mime'],'image/')) $html.='<option value="'.e($id).'" '.($selected===$id?'selected':'').'>'.e($m['name']).'</option>';
    return $html.'</select>';
}
function content_status(array $c): string { return match($c['status']){'published'=>$c['publish_at']>time()?t('Programmé','Scheduled'):t('Publié','Published'),'private'=>t('Privé','Private'),'trash'=>t('Corbeille','Trash'),default=>t('Brouillon','Draft')}; }
?><!doctype html><html lang="<?=e(lang())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=e(Security::csrf())?>"><title><?=e($labels[$view])?> · VoilaVoila CMS</title><link rel="stylesheet" href="<?=e(url('assets/admin.css'))?>"><script src="<?=e(url('assets/admin.js'))?>" defer></script></head><body class="admin-body" data-lang="<?=e(lang())?>"><a class="skip-link" href="#main"><?=e(t('Aller au contenu','Skip to content'))?></a><aside class="sidebar" id="sidebar"><a class="admin-brand" href="<?=e(admin_url())?>"><span class="brand-mark"><img src="<?=e(url('assets/voilavoila-logo.png'))?>" width="30" height="30" alt="VoilaVoila"></span><span>VoilaVoila<span class="brand-sub">CMS / PERSONAL SPACE</span></span></a><div class="workspace-id"><span class="status-dot"></span><div><strong><?=e($vv->state['settings']['title'])?></strong><small><?=e(t('Un seul propriétaire','Single owner'))?></small></div></div><span class="nav-label"><?=e(t('Votre espace','Your space'))?></span><nav aria-label="Administration"><?php $icons=['dashboard'=>'grid','posts'=>'article','pages'=>'file','media'=>'image','themes'=>'palette','plugins'=>'plug','settings'=>'user','security'=>'shield','tools'=>'box']; foreach($labels as $k=>$label): if($k==='editor')continue;?><a href="<?=e(admin_url($k))?>" <?=($view===$k || ($view==='editor' && $k===(val($_GET,'type')==='page'?'pages':'posts')))?'class="active" aria-current="page"':''?>><svg aria-hidden="true"><use href="<?=e(url('assets/icons.svg'))?>#<?=e($icons[$k] ?? 'spark')?>"></use></svg><?=e($label)?></a><?php endforeach;?></nav><div class="sidebar-bottom"><kbd>⌘ / Ctrl K</kbd><small>VoilaVoila CMS <?=VV_VERSION?></small><?=action_form('logout',t('Se déconnecter','Sign out'))?></div></aside><div class="admin-main"><header class="topbar"><button type="button" class="icon-button menu-toggle" aria-label="Menu" aria-controls="sidebar" aria-expanded="false"><svg><use href="<?=e(url('assets/icons.svg'))?>#menu"></use></svg></button><div class="breadcrumb">CMS <span>/</span> <?=e($labels[$view])?></div><div class="top-actions"><button class="search-launch secondary button" type="button"><svg><use href="<?=e(url('assets/icons.svg'))?>#search"></use></svg><span><?=e(t('Rechercher','Search'))?></span><kbd>⌘K</kbd></button><a class="lang-switch" href="<?=e(admin_url($view,array_merge(array_diff_key($_GET,['view'=>1,'lang'=>1]),['lang'=>lang()==='fr'?'en':'fr'])))?>"><?=lang()==='fr'?'EN':'FR'?></a><a class="button secondary" href="<?=e(url('index.php'))?>" target="_blank" rel="noopener"><?=e(t('Voir le site','View site'))?></a></div></header><main id="main" class="main-content"><div class="page-heading"><div><span class="eyebrow"><?=e(t('VOTRE ESPACE DE PUBLICATION','YOUR PUBLISHING SPACE'))?></span><h1><?=e($labels[$view])?><span class="gold">.</span></h1></div><?php if(in_array($view,['dashboard','posts','pages'],true)):?><a class="button primary" href="<?=e(admin_url('editor',['type'=>$view==='pages'?'page':'post']))?>">+ <?=e($view==='pages'?t('Nouvelle page','New page'):t('Nouvel article','New post'))?></a><?php endif;?></div>
<?php if($flash):?><div class="notice success" role="status"><?=e($flash)?></div><?php endif;?><?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif;?><?php if($safe):?><div class="notice"><?=e(t('Mode de dépannage : extensions suspendues pour cette requête.','Recovery mode: plugins skipped for this request.'))?></div><?php endif;?>
<?php if(isset($_SESSION['recovery_once'])): $code=$_SESSION['recovery_once']; unset($_SESSION['recovery_once']);?><div class="recovery-card"><strong><?=e(t('Conservez votre code de récupération hors ligne.','Keep your recovery code offline.'))?></strong><code><?=e($code)?></code><p><?=e(t('Il ne sera plus affiché. Il permet de réinitialiser votre accès et ne fonctionne qu’une fois.','It will not be shown again. It resets your access and works once.'))?></p></div><?php endif;?>
<?php require __DIR__.'/core/AdminViews.php'; ?>
</main><footer class="admin-footer"><?=vv_brand()?><span><?=e(t('Votre contenu vous appartient.','Your content is yours.'))?></span><span><?=e(t('Propriétaire unique','Single owner'))?> · <?=VV_VERSION?></span></footer></div><dialog id="command-palette"><form method="dialog"><button class="icon-button close-dialog" aria-label="<?=e(t('Fermer','Close'))?>">×</button></form><h2><?=e(t('Où souhaitez-vous aller ?','Where would you like to go?'))?></h2><label class="sr-only" for="command-input"><?=e(t('Rechercher','Search'))?></label><input id="command-input" type="search" placeholder="<?=e(t('Pages, articles, réglages…','Pages, posts, settings…'))?>" autocomplete="off"><div class="command-results"><?php foreach($labels as $k=>$label):if($k==='editor')continue;?><a href="<?=e(admin_url($k))?>"><?=e($label)?></a><?php endforeach;?><?php foreach(array_slice(array_reverse($vv->state['contents']),0,80) as $c):?><a href="<?=e(admin_url('editor',['id'=>$c['id']]))?>"><?=e(Content::field($c,'title'))?> <small><?=e($c['type'])?></small></a><?php endforeach;?></div><small>↑ ↓ · Entrée / Enter · Échap / Escape</small></dialog><div id="toast" role="status" aria-live="polite"></div></body></html>
