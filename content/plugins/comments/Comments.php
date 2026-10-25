<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** Plugin-owned data in SQLite cms_state.plugin_data.comments; no new accounts. */
final class Comments {
    private static string $current='';
    private static string $token='';
    public static function register(App $app): void {
        $app->adminPage('comments',\t('Commentaires','Comments'),[self::class,'admin'],[self::class,'save']);
        \vv_action('public_route',[self::class,'route']);
        \vv_action('head',function() { if(self::$current!=='') echo '<link rel="stylesheet" href="'.\e(\url('content/plugins/comments/comments.css')).'">'; });
        \vv_action('content_html',function($html,$c) use($app) { return $html.(($c['id'] ?? '')===self::$current?self::render($app,$c):''); },30);
        \vv_action('wordpress_import_state_v1',[self::class,'import'],10);
    }
    public static function data(array $s): array {
        $raw=$s['plugin_data']['comments'] ?? []; if(!is_array($raw)) $raw=[];
        $settings=is_array($raw['settings'] ?? null)?$raw['settings']:[];
        $rows=[];
        foreach(is_array($raw['rows'] ?? null)?$raw['rows']:[] as $id=>$r) {
            if(count($rows)>=1000) break;
            if(!is_string($id) || !preg_match('/^[a-f0-9]{24}$/D',$id) || !is_array($r) || !preg_match('/^[a-f0-9]{24}$/D',\val($r,'content_id')) || !in_array($r['status'] ?? '',['pending','approved','spam'],true)) continue;
            $r['id']=$id; $r['name']=Content::text(\val($r,'name'),80); $r['body']=self::plain(\val($r,'body'),3000); $r['created_at']=Content::text(\val($r,'created_at'),40); $r['version']=max(1,(int)($r['version'] ?? 1));
            if($r['name']!=='' && $r['body']!=='') $rows[$id]=$r;
        }
        return ['schema'=>1,'settings'=>['enabled'=>($settings['enabled'] ?? true)===true,'pages'=>($settings['pages'] ?? false)===true],'rows'=>$rows];
    }
    private static function plain(string $v,int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u','',strip_tags($v)) ?? ''),0,$max); }
    private static function allowed(array $s,array $c,bool $write=false): bool {
        $d=self::data($s);
        return $d['settings']['enabled'] && Content::visible($c) && empty($s['settings']['maintenance']) && ($c['type']==='post' || ($c['type']==='page' && $d['settings']['pages'])) && (!$write || ($c['comments_open'] ?? true));
    }
    private static function put(array &$s,array $d): void {
        if(count($d['rows'])>1000 || strlen(json_encode($d,JSON_THROW_ON_ERROR))>1048576) throw new Failure('Commentaires limités à 1 000 messages et 1 Mo. Exportez puis supprimez les anciens messages.',413);
        $s['plugin_data']['comments']=$d;
    }
    public static function route(App $app): void {
        self::$current=''; self::$token='';
        if(($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST' && \val($_POST,'cms_action')==='comment.submit') {
            Security::guestPost($app->store,'comments',\val($_POST,'comments_token'));
            $app->store->rate('comments-ip',$_SERVER['REMOTE_ADDR'] ?? '',5,900);
            $app->store->rate('comments-global','site',100,3600);
            if(\val($_POST,'website')!=='' || \val($_POST,'consent')!=='on') throw new Failure(\t('Vérifiez le formulaire et l’accord de publication.','Check the form and publication consent.'));
            $name=self::plain(\val($_POST,'name'),80); $body=self::plain(\val($_POST,'body'),3000); $id=\val($_POST,'content_id');
            if(!mb_check_encoding(\val($_POST,'name').\val($_POST,'body'),'UTF-8') || mb_strlen(\val($_POST,'name'))>80 || mb_strlen(\val($_POST,'body'))>3000 || mb_strlen($name)<2 || mb_strlen($body)<3) throw new Failure(\t('Nom : 2–80 caractères. Message : 3–3 000 caractères.','Name: 2–80 characters. Message: 3–3,000 characters.'));
            $target=$app->store->change(function(&$s) use($id,$name,$body) {
                $c=$s['contents'][$id] ?? null;
                if(!$c || !self::allowed($s,$c,true)) throw new Failure(\t('Commentaires fermés ou contenu indisponible.','Comments are closed or content is unavailable.'),404);
                $d=self::data($s);
                foreach($d['rows'] as $r) if($r['content_id']===$id && $r['name']===$name && $r['body']===$body) return Content::url($c);
                $cid=bin2hex(random_bytes(12));
                $d['rows'][$cid]=['id'=>$cid,'content_id'=>$id,'name'=>$name,'body'=>$body,'status'=>'pending','created_at'=>gmdate('c'),'version'=>1];
                self::put($s,$d); Store::log($s,'comment_received',$cid);
                return Content::url($c);
            });
            \redirect($target.'&comment=received#comments');
        }
        if(\val($_GET,'preview')!=='' || \val($_GET,'cms')!=='' || \val($_GET,'q')!=='' || \val($_GET,'feed')==='1') return;
        $slug=\val($_GET,'p'); $candidate=null;
        if($slug!=='') foreach($app->state['contents'] as $c) { if($c['slug']===$slug) { $candidate=$c; break; } }
        else $candidate=$app->state['contents'][$app->state['settings']['home_page'] ?? ''] ?? null;
        if(!$candidate || !self::allowed($app->state,$candidate)) return;
        self::$current=$candidate['id'];
        if(self::allowed($app->state,$candidate,true)) self::$token=Security::guestToken($app->store,'comments');
    }
    public static function render(App $app,array $c): string {
        $d=self::data($app->state);
        $rows=array_values(array_filter($d['rows'],fn($r)=>$r['content_id']===$c['id'] && $r['status']==='approved'));
        usort($rows,fn($a,$b)=>strcmp($a['created_at'],$b['created_at']) ?: strcmp($a['id'],$b['id']));
        $page=max(1,min((int)\val($_GET,'comments_page','1'),max(1,(int)ceil(count($rows)/20))));
        ob_start(); ?>
<section class="vv-comments" id="comments" aria-labelledby="comments-title">
<h2 id="comments-title"><?=\e(\t('Commentaires','Comments'))?> <span>(<?=count($rows)?>)</span></h2>
<?php if(\val($_GET,'comment')==='received'):?><p role="status"><?=\e(\t('Merci. Votre message sera visible après validation.','Thank you. Your message will appear after approval.'))?></p><?php endif;?>
<?php foreach(array_slice($rows,($page-1)*20,20) as $r):?>
<article class="vv-comment"><header><strong><?=\e($r['name'])?></strong><time datetime="<?=\e($r['created_at'])?>"><?=\e(substr($r['created_at'],0,10))?></time></header><p><?=nl2br(\e($r['body']))?></p></article>
<?php endforeach;?>
<?php if(count($rows)>20):?><nav aria-label="<?=\e(\t('Pages des commentaires','Comment pages'))?>"><?php if($page>1):?><a href="<?=\e(Content::url($c).'&comments_page='.($page-1).'#comments')?>"><?=\e(\t('Précédent','Previous'))?></a><?php endif;?> <span><?=$page?> / <?=(int)ceil(count($rows)/20)?></span> <?php if(count($rows)>$page*20):?><a href="<?=\e(Content::url($c).'&comments_page='.($page+1).'#comments')?>"><?=\e(\t('Suivant','Next'))?></a><?php endif;?></nav><?php endif;?>
<?php if(self::$token!=='' && self::allowed($app->state,$c,true)):?>
<form method="post" action="<?=\e(Content::url($c).'#comments')?>" class="vv-comment-form">
<input type="hidden" name="cms_action" value="comment.submit"><input type="hidden" name="content_id" value="<?=\e($c['id'])?>"><input type="hidden" name="comments_token" value="<?=\e(self::$token)?>">
<h3><?=\e(\t('Laisser un commentaire','Leave a comment'))?></h3>
<p><?=\e(\t('Votre nom et votre message seront publics après modération. Aucun e-mail ni compte demandé.','Your name and message will be public after moderation. No email or account required.'))?></p>
<label for="comment-name"><?=\e(\t('Nom ou pseudonyme','Name or nickname'))?></label><input id="comment-name" name="name" required minlength="2" maxlength="80" autocomplete="nickname">
<label for="comment-body"><?=\e(\t('Message','Message'))?></label><textarea id="comment-body" name="body" required minlength="3" maxlength="3000" rows="5"></textarea>
<div class="vv-comments-honey" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<label class="vv-comment-consent"><input type="checkbox" name="consent" required> <span><?=\e(\t('J’accepte la publication de mon pseudonyme et de mon message après validation.','I agree to publication of my nickname and message after approval.'))?></span></label>
<button type="submit"><?=\e(\t('Envoyer pour validation','Submit for approval'))?></button>
</form>
<?php else:?><p><?=\e(\t('Les nouveaux commentaires sont fermés.','New comments are closed.'))?></p><?php endif;?>
</section>
<?php return ob_get_clean();
    }
    public static function save(App $app,array $p): void {
        Security::requireOwner($app->store); $op=\val($p,'op');
        if($op==='export') {
            $d=self::data($app->store->read());
            header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="comments.json"');
            header('Cache-Control: private, no-store'); echo json_encode($d,JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR); exit;
        }
        if($op==='purge') { Security::reauth($app->store,\val($p,'current_password')); if(\val($p,'confirm')!=='PURGE') throw new Failure('Saisissez PURGE.'); }
        $app->store->change(function(&$s) use($op,$p) {
            $d=self::data($s);
            if($op==='settings') $d['settings']=['enabled'=>isset($p['enabled']),'pages'=>isset($p['pages'])];
            elseif($op==='purge') $d['rows']=[];
            elseif(in_array($op,['pending','approved','spam','delete'],true)) {
                $id=\val($p,'id'); $r=$d['rows'][$id] ?? null;
                if(!$r) throw new Failure('Commentaire introuvable.',404);
                if($r['version']!==(int)\val($p,'version')) throw new Failure('Message modifié. Rechargez la page.',409);
                if($op==='delete') unset($d['rows'][$id]); else { $d['rows'][$id]['status']=$op; $d['rows'][$id]['version']++; }
            } else throw new Failure('Action commentaire invalide.');
            self::put($s,$d); Store::log($s,'comments_'.$op,\val($p,'id'));
        });
    }
    public static function admin(App $app): void {
        $d=self::data($app->state); $status=\val($_GET,'status','pending');
        if(!in_array($status,['pending','approved','spam','all'],true)) $status='pending';
        $q=Content::text(\val($_GET,'q'),100);
        $rows=array_values(array_filter($d['rows'],fn($r)=>($status==='all' || $r['status']===$status) && ($q==='' || mb_stripos($r['name'].' '.$r['body'],$q)!==false)));
        usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']) ?: strcmp($b['id'],$a['id']));
        $page=max(1,(int)\val($_GET,'page','1'));
        $labels=['pending'=>\t('En attente','Pending'),'approved'=>\t('Approuvé','Approved'),'spam'=>\t('Indésirable','Spam'),'all'=>\t('Tous','All')]; ?>
<section class="panel"><h2><?=\e(\t('Publication après validation','Publish after approval'))?></h2><p><?=\e(\t('Les visiteurs ne créent aucun compte. Chaque nouveau commentaire attend votre validation.','Visitors do not create accounts. Every new comment requires your approval.'))?></p>
<form method="post"><?=\csrf_field()?><input type="hidden" name="action" value="plugin.save"><input type="hidden" name="op" value="settings"><label class="checkbox"><input type="checkbox" name="enabled" <?=$d['settings']['enabled']?'checked':''?>><?=\e(\t('Activer les commentaires sur les articles','Enable comments on posts'))?></label><label class="checkbox"><input type="checkbox" name="pages" <?=$d['settings']['pages']?'checked':''?>><?=\e(\t('Autoriser aussi les pages','Also allow pages'))?></label><button class="button primary"><?=\e(\t('Enregistrer','Save'))?></button></form></section>
<form method="get" class="filter-bar"><input type="hidden" name="view" value="plugin-comments"><input type="hidden" name="lang" value="<?=\e(\lang())?>"><label><?=\e(\t('Statut','Status'))?><select name="status"><?php foreach($labels as $k=>$v):?><option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=\e($v)?></option><?php endforeach;?></select></label><label><?=\e(\t('Recherche','Search'))?><input name="q" value="<?=\e($q)?>" maxlength="100"></label><button class="button secondary"><?=\e(\t('Filtrer','Filter'))?></button><span><?=count($rows)?> / <?=count($d['rows'])?></span></form>
<?php foreach(array_slice($rows,($page-1)*20,20) as $r): $c=$app->state['contents'][$r['content_id']] ?? null;?>
<article class="panel"><div class="panel-title"><h2><?=\e($r['name'])?></h2><span class="badge"><?=\e($labels[$r['status']])?></span></div><p><?=nl2br(\e($r['body']))?></p><p class="hint"><?=\e($r['created_at'])?> · <?php if($c):?><a href="<?=\e(\admin_url('editor',['id'=>$c['id']]))?>"><?=\e(Content::field($c,'title'))?></a><?php else:?><?=\e(\t('Contenu supprimé','Deleted content'))?><?php endif;?></p><div class="button-row">
<?php foreach(['approved'=>\t('Approuver','Approve'),'pending'=>\t('En attente','Pending'),'spam'=>\t('Indésirable','Spam'),'delete'=>\t('Supprimer','Delete')] as $op=>$label): if($op===$r['status']) continue;?>
<form method="post" class="inline"><?=\csrf_field()?><input type="hidden" name="action" value="plugin.save"><input type="hidden" name="op" value="<?=$op?>"><input type="hidden" name="id" value="<?=\e($r['id'])?>"><input type="hidden" name="version" value="<?=$r['version']?>"><button class="button <?=$op==='delete'?'danger':'secondary'?> small"><?=\e($label)?></button></form><?php endforeach;?></div></article>
<?php endforeach;?>
<div class="button-row"><?php if($page>1):?><a class="button secondary" href="<?=\e(\admin_url('plugin-comments',['status'=>$status,'q'=>$q,'page'=>$page-1]))?>"><?=\e(\t('Précédent','Previous'))?></a><?php endif;?><?php if(count($rows)>$page*20):?><a class="button secondary" href="<?=\e(\admin_url('plugin-comments',['status'=>$status,'q'=>$q,'page'=>$page+1]))?>"><?=\e(\t('Suivant','Next'))?></a><?php endif;?></div>
<section class="panel"><h2><?=\e(\t('Export et conservation','Export and retention'))?></h2><p><?=\e(\t('La désactivation et la suppression des fichiers du plugin conservent les messages. La sauvegarde portable les inclut. Les journaux d’accès peuvent conserver les IP séparément.','Deactivation and plugin-file removal retain messages. Portable backups include them. Access logs may retain IP addresses separately.'))?></p>
<form method="post"><?=\csrf_field()?><input type="hidden" name="action" value="plugin.save"><input type="hidden" name="op" value="export"><button class="button secondary"><?=\e(\t('Exporter les commentaires JSON','Export comments as JSON'))?></button></form>
<details><summary><?=\e(\t('Supprimer tous les commentaires','Delete all comments'))?></summary><form method="post"><?=\csrf_field()?><input type="hidden" name="action" value="plugin.save"><input type="hidden" name="op" value="purge"><label>PURGE<input name="confirm" required pattern="PURGE"></label><label><?=\e(\t('Mot de passe actuel','Current password'))?><input type="password" name="current_password" required autocomplete="current-password"></label><button class="button danger"><?=\e(\t('Supprimer définitivement','Delete permanently'))?></button></form></details></section>
<?php }
    /** Importer passes normalized WXR records inside its existing atomic transaction. */
    public static function import(array $s,array $event): array {
        if(empty($event['import_comments'])) return $s;
        $d=self::data($s); $known=[];
        foreach($d['rows'] as $r) if(isset($r['wp_key'])) $known[$r['wp_key']]=true;
        foreach($event['comments'] as $r) {
            $target=$event['post_map'][$r['post_id']] ?? '';
            if(!$target || isset($known[$r['wp_key']]) || !isset($s['contents'][$target])) continue;
            $name=self::plain($r['name'],80); $body=self::plain($r['body'],3000);
            if($name==='' || $body==='') continue;
            $id=bin2hex(random_bytes(12));
            $d['rows'][$id]=['id'=>$id,'content_id'=>$target,'name'=>$name,'body'=>$body,'status'=>'pending','created_at'=>$r['created_at'],'version'=>1,'wp_key'=>$r['wp_key']];
            $known[$r['wp_key']]=true;
        }
        self::put($s,$d); return $s;
    }
}
