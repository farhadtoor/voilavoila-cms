<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class Tools {
    public static function settings(array $p,array $old,array $media): array {
        $s=$old;
        foreach(['title'=>200,'tagline'=>250,'bio'=>1500,'location'=>100,'footer'=>250,'socials'=>2000] as $k=>$len) $s[$k]=Content::text(\val($p,$k),$len);
        if(!$s['title']) throw new Failure('Le nom du site est requis.');
        $s['language']=\val($p,'language')==='en'?'en':'fr';
        $s['canonical']=rtrim(\val($p,'canonical'),'/');
        if($s['canonical']!=='' && (!filter_var($s['canonical'],FILTER_VALIDATE_URL) || parse_url($s['canonical'],PHP_URL_SCHEME)!=='https' || parse_url($s['canonical'],PHP_URL_QUERY)!==null || parse_url($s['canonical'],PHP_URL_FRAGMENT)!==null || parse_url($s['canonical'],PHP_URL_USER)!==null)) throw new Failure('L’URL canonique doit être une URL HTTPS sans paramètres.');
        $s['email_public']=\val($p,'email_public');
        if($s['email_public']!=='' && !filter_var($s['email_public'],FILTER_VALIDATE_EMAIL)) throw new Failure('E-mail public invalide.');
        foreach(['avatar','cover'] as $k) {
            $id=\val($p,$k);
            if($id!=='' && (!isset($media[$id]) || !$media[$id]['public'] || !str_starts_with($media[$id]['mime'],'image/'))) throw new Failure('Avatar et couverture doivent être des images publiques.');
            $s[$k]=$id;
        }
        $s['maintenance']=!empty($p['maintenance']); $s['noindex']=!empty($p['noindex']);
        $s['home_page']=\val($p,'home_page'); $s['per_page']=max(3,min(30,(int)\val($p,'per_page','8')));
        return $s;
    }
    public static function zip(): void { if(!class_exists('ZipArchive')) throw new Failure('Activez l’extension PHP ZIP pour cette opération.',503); }
    public static function uploaded(array $file,int $max): string {
        if(($file['error'] ?? 1)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) throw new Failure('Envoi incomplet. Vérifiez la limite upload_max_filesize de PHP.');
        $p=$file['tmp_name']; if(filesize($p)<1 || filesize($p)>$max) throw new Failure('Archive trop volumineuse.',413); return $p;
    }
    /** Reject traversal, symlinks, duplicates, encrypted entries and decompression bombs before extraction. */
    public static function zipEntries(\ZipArchive $zip,int $maxFiles=2200,int $maxBytes=67108864): array {
        if($zip->numFiles<1 || $zip->numFiles>$maxFiles) throw new Failure('Nombre de fichiers dans le ZIP non autorisé.');
        $total=0; $names=[];
        for($i=0;$i<$zip->numFiles;$i++) {
            $s=$zip->statIndex($i); $n=$s['name'] ?? '';
            if(!$n || strlen($n)>220 || preg_match('/[\x00-\x1f\\\\:]/',$n) || str_starts_with($n,'/') || preg_match('~(?:^|/)\.\.?(/|$)~',$n) || str_contains($n,'//') || isset($names[strtolower($n)])) throw new Failure('Chemin de ZIP refusé.');
            $ops=0; $attrs=0; $zip->getExternalAttributesIndex($i,$ops,$attrs);
            if((($attrs>>16)&0170000)===0120000 || !empty($s['encryption_method'])) throw new Failure('Lien symbolique ou entrée chiffrée refusée.');
            $total+=(int)$s['size'];
            if((int)$s['size']>8*1024*1024 || $total>$maxBytes) throw new Failure('ZIP décompressé trop volumineux.',413);
            $names[strtolower($n)]=['name'=>$n,'index'=>$i,'size'=>$s['size']];
        }
        return $names;
    }
    public static function installPackage(App $app,array $file,string $type): string {
        self::zip(); $path=self::uploaded($file,12*1024*1024); $zip=new \ZipArchive();
        if($zip->open($path)!==true) throw new Failure('ZIP illisible.');
        $stage='';
        try {
            $entries=self::zipEntries($zip,400,20*1024*1024); $candidates=[];
            foreach($entries as $row) if($row['name']==='cms.json' || preg_match('~^[a-z][a-z0-9-]*/cms\.json$~D',$row['name'])) $candidates[]=$row;
            if(count($candidates)!==1) throw new Failure('Ce ZIP doit contenir un manifeste cms.json pour VoilaVoila CMS. Les extensions WordPress doivent être portées.');
            $manifest=$candidates[0]; if($manifest['size']>12000) throw new Failure('Manifeste trop volumineux.');
            $m=json_decode($zip->getFromIndex($manifest['index']),true); $id=is_array($m)?($m['id'] ?? ''):'';
            if(!is_string($id) || !preg_match('/^[a-z][a-z0-9-]{1,50}$/D',$id) || ($m['api'] ?? 0)!==1 || ($m['type'] ?? '')!==$type || !is_string($m['name'] ?? null)) throw new Failure('Manifeste de module incompatible.');
            $folder=$type==='theme'?'themes':'plugins'; $target=VV_ROOT.'/content/'.$folder.'/'.$id;
            if(file_exists($target)) throw new Failure('Ce module existe déjà. Mettez à jour ses fichiers par SFTP après sauvegarde.',409);
            $prefix=$manifest['name']==='cms.json'?'':dirname($manifest['name']).'/';
            $stage=VV_ROOT.'/content/'.$folder.'/.install-'.bin2hex(random_bytes(10));
            if(!mkdir($stage,0700)) throw new Failure('Le dossier des modules doit être accessible en écriture.',503);
            file_put_contents($stage.'/.htaccess',"Require all denied\n");
            foreach($entries as $row) {
                $n=$row['name']; if(!str_starts_with($n,$prefix)) throw new Failure('Tous les fichiers doivent être dans le même dossier.');
                $rel=substr($n,strlen($prefix)); if($rel==='' || str_ends_with($rel,'/')) continue;
                if(preg_match('~(?:^|/)\.~',$rel) || !preg_match('/\.(php|json|css|js|png|jpe?g|webp|woff2|md|txt)$/i',$rel)) throw new Failure('Fichier non autorisé dans le module.');
                $to=$stage.'/'.$rel; if(!is_dir(dirname($to))) mkdir(dirname($to),0755,true);
                $bytes=$zip->getFromIndex($row['index']); if($bytes===false || strlen($bytes)!==$row['size']) throw new Failure('Archive incomplète.');
                if(file_put_contents($to,$bytes)!==strlen($bytes)) throw new Failure('Installation interrompue.',503);
                chmod($to,0644);
            }
            $entry=$type==='theme'?'index.php':'plugin.php';
            if(!is_file($stage.'/'.$entry)) throw new Failure('Point d’entrée '.$entry.' absent.');
            if($type==='theme' && !is_file($stage.'/style.css')) throw new Failure('Le thème doit fournir style.css.');
            // Stage denies requests; every installed PHP file must have a direct-access guard.
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage,\FilesystemIterator::SKIP_DOTS)) as $f) {
                if($f->getExtension()==='php' && !preg_match('/defined\s*\(\s*[\x27\x22]VV_ROOT[\x27\x22]\s*\)/',file_get_contents($f->getPathname()))) throw new Failure('Chaque fichier PHP doit vérifier defined("VV_ROOT").');
            }
            unlink($stage.'/.htaccess'); chmod($stage,0755);
            if(!rename($stage,$target)) throw new Failure('Installation impossible.',503); $stage='';
            $app->store->audit('package_installed',$type.':'.$id); return $id;
        } finally { $zip->close(); if($stage && is_dir($stage)) self::removeTree($stage); }
    }
    public static function removeTree(string $dir): void {
        if(is_link($dir)) { unlink($dir); return; }
        foreach(new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS) as $f) {
            if($f->isDir() && !$f->isLink()) self::removeTree($f->getPathname()); else unlink($f->getPathname());
        }
        rmdir($dir);
    }
    /** Portable content backup: no owner password, session, server secret or executable code. */
    public static function backup(App $app): string {
        self::zip(); $tmp=tempnam(sys_get_temp_dir(),'vvcms-');
        try {
            $app->store->change(function(&$s) use($app,$tmp) {
                $zip=new \ZipArchive(); if($zip->open($tmp,\ZipArchive::OVERWRITE)!==true) throw new Failure('Sauvegarde impossible.',503);
                try {
                    $data=['format'=>'voilavoila-cms','schema'=>2,'created_at'=>gmdate('c'),'version'=>VV_VERSION,'settings'=>$s['settings'],'contents'=>$s['contents'],'media'=>$s['media'],'plugin_data'=>$s['plugin_data']];
                    $json=json_encode($data,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    if(strlen($json)>8*1024*1024) throw new Failure('Export limité à 8 Mo de données éditoriales.',413);
                    $zip->addFromString('data.json',$json); $total=strlen($json);
                    foreach($s['media'] as $id=>$m) {
                        $bytes=$app->store->readMedia($m);
                        $total+=strlen($bytes); if($total>64*1024*1024) throw new Failure('Export complet limité à 64 Mo. Copiez storage par SFTP pour un site plus grand.',413);
                        if(!hash_equals($m['sha256'],hash('sha256',$bytes))) throw new Failure('Un média est incomplet. Sauvegarde interrompue.',503);
                        $zip->addFromString('media/'.$m['stored_name'],$bytes);
                    }
                } finally { $zip->close(); }
            });
            return $tmp;
        } catch(\Throwable $e) { @unlink($tmp); throw $e; }
    }
    public static function snapshot(App $app): string {
        $tmp=self::backup($app); $id=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
        try { $app->store->privateWrite($app->store->dir.'/backups/'.$id.'.php',file_get_contents($tmp)); } finally { unlink($tmp); }
        $files=glob($app->store->dir.'/backups/*.php'); sort($files);
        foreach(array_slice($files,0,max(0,count($files)-3)) as $p) unlink($p);
        return $id;
    }
    public static function restore(App $app,array $file): void {
        self::zip(); $p=self::uploaded($file,64*1024*1024); $zip=new \ZipArchive();
        if($zip->open($p)!==true) throw new Failure('Sauvegarde illisible.');
        try {
            $entries=self::zipEntries($zip); if(!isset($entries['data.json'])) throw new Failure('data.json absent.');
            $data=json_decode($zip->getFromName('data.json'),true,80,JSON_THROW_ON_ERROR);
            if(($data['format'] ?? '')!=='voilavoila-cms' || !in_array(($data['schema'] ?? 0),[1,2],true)) throw new Failure('Format de sauvegarde incompatible.');
            foreach(['contents','settings','media','plugin_data'] as $k) if(!is_array($data[$k] ?? null)) throw new Failure('Sauvegarde incomplète.');
            if(count($data['contents'])>10000 || count($data['media'])>2000) throw new Failure('Sauvegarde trop volumineuse.');
            $contents=[]; $media=[]; $ids=[]; $mediaEntries=[];
            foreach($data['media'] as $id=>$m) {
                if(!is_string($id) || !preg_match('/^[a-f0-9]{24}$/D',$id) || !is_array($m)) throw new Failure('Média invalide.');
                $stored=\val($m,'stored_name');
                $entry=preg_match('/^'.preg_quote($id,'/').'\\.(jpg|jpeg|png|webp|pdf)$/D',$stored)?'media/'.$stored:'media/'.$id.'.bin';
                if(!isset($entries[strtolower($entry)])) throw new Failure('Média manquant.');
                $bytes=$zip->getFromName($entry);
                if(!hash_equals(\val($m,'sha256'),hash('sha256',$bytes))) throw new Failure('Intégrité du média incorrecte.');
                $media[$id]=Media::metadata(['id'=>$id,'name'=>Content::text(\val($m,'name'),160),'public'=>!empty($m['public']),'alt'=>Content::text(\val($m,'alt'),250),'created_at'=>Content::timestamp(\val($m,'created_at'))],$bytes);
                $mediaEntries[$id]=$entry;
            }
            foreach($data['contents'] as $id=>$c) {
                if(!is_string($id) || !preg_match('/^[a-f0-9]{24}$/D',$id) || !is_array($c)) throw new Failure('Contenu invalide.');
                $row=['id'=>$id,'type'=>in_array(\val($c,'type'),['post','page'],true)?$c['type']:'post','status'=>in_array(\val($c,'status'),['draft','published','private','trash'],true)?$c['status']:'draft','slug'=>Content::slug(\val($c,'slug')),'revisions'=>[],'version'=>1,'created_at'=>Content::timestamp(\val($c,'created_at')),'updated_at'=>Content::timestamp(\val($c,'updated_at')),'publish_at'=>max(1,(int)($c['publish_at'] ?? time())),'pinned'=>!empty($c['pinned']),'menu'=>!empty($c['menu']),'order'=>max(0,min(999,(int)($c['order'] ?? 10))),'cover'=>'','tags'=>[]];
                if(isset($ids[$row['slug']])) throw new Failure('Slugs en double dans la sauvegarde.'); $ids[$row['slug']]=true;
                foreach(['fr','en'] as $l) { $row['title_'.$l]=Content::text(\val($c,'title_'.$l)); $row['excerpt_'.$l]=Content::text(\val($c,'excerpt_'.$l),400); $row['body_'.$l]=Content::html(Content::relocateMedia(\val($c,'body_'.$l))); }
                if(preg_match('/^[a-f0-9]{64}$/D',\val($c,'wp_key'))) $row['wp_key']=$c['wp_key'];
                foreach(array_slice(is_array($c['tags'] ?? null)?$c['tags']:[],0,12) as $tag) if(is_string($tag)) $row['tags'][]=Content::text($tag,40);
                $cover=\val($c,'cover'); if(isset($media[$cover]) && $media[$cover]['public'] && str_starts_with($media[$cover]['mime'],'image/')) $row['cover']=$cover;
                $contents[$id]=$row;
            }
            $settings=self::settings($data['settings'],$app->state['settings'],$media);
            $theme=\val($data['settings'],'theme'); $settings['theme']=isset($app->packages('theme')[$theme])?$theme:'profil-or';
            if(!isset($contents[$settings['home_page']])) $settings['home_page']='';
            self::snapshot($app);
            $app->store->change(function(&$s) use($app,$zip,$media,$mediaEntries,$contents,$settings,$data) {
                foreach($media as $id=>$m) {
                    if(isset($s['media'][$id]) && !hash_equals($s['media'][$id]['sha256'],$m['sha256'])) throw new Failure('Conflit de média. Restaurez dans une installation séparée.',409);
                    $app->store->writeMedia($m,$zip->getFromName($mediaEntries[$id]));
                }
                $s['contents']=$contents; $s['media']=$media; $s['settings']=$settings; $s['plugin_data']=$data['plugin_data'];
                Store::log($s,'backup_restored');
            });
        } catch(\JsonException $e) { throw new Failure('JSON de sauvegarde illisible.'); }
        finally { $zip->close(); }
    }
    public static function wordpress(App $app,array $file,bool $publish=false): int {
        $p=self::uploaded($file,8*1024*1024); $xml=file_get_contents($p);
        if(preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new Failure('Déclarations XML externes refusées.');
        $d=new \DOMDocument(); $old=libxml_use_internal_errors(true);
        try { $ok=$d->loadXML($xml,LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        if(!$ok) throw new Failure('Export WordPress XML illisible.');
        $xp=new \DOMXPath($d); $wp='http://wordpress.org/export/1.2/'; $xp->registerNamespace('wp',$wp); $xp->registerNamespace('content','http://purl.org/rss/1.0/modules/content/');
        if(!$xp->query('/rss/channel/wp:wxr_version')->length) throw new Failure('Choisissez un export WordPress WXR 1.2.');
        $items=$xp->query('/rss/channel/item'); if($items->length>2000) throw new Failure('Import limité à 2 000 éléments.');
        $rows=[];
        foreach($items as $item) {
            $get=fn($q)=>(string)$xp->evaluate('string('.$q.')',$item);
            $type=$get('wp:post_type'); if(!in_array($type,['post','page'],true)) continue;
            $source=$get('wp:post_id'); $key=hash('sha256',$get('guid').'|'.$source.'|'.$get('title'));
            $status=$get('wp:status'); $date=strtotime($get('wp:post_date_gmt').' UTC') ?: time();
            $rows[]=['id'=>bin2hex(random_bytes(12)),'wp_key'=>$key,'type'=>$type,'status'=>$publish && $status==='publish'?'published':'draft','slug'=>Content::slug($get('wp:post_name') ?: $get('title')),'title_fr'=>Content::text($get('title')),'title_en'=>'','excerpt_fr'=>'','excerpt_en'=>'','body_fr'=>Content::html($get('content:encoded')),'body_en'=>'','publish_at'=>$date,'created_at'=>gmdate('c'),'updated_at'=>gmdate('c'),'cover'=>'','tags'=>[],'menu'=>false,'pinned'=>false,'order'=>10,'version'=>1,'revisions'=>[]];
        }
        if(!$rows) throw new Failure('Cet export ne contient aucune page ou article importable.');
        self::snapshot($app);
        return $app->store->change(function(&$s) use($rows) {
            $slugs=[]; $keys=[]; foreach($s['contents'] as $c) { $slugs[$c['slug']]=true; if(isset($c['wp_key'])) $keys[$c['wp_key']]=true; }
            $count=0;
            foreach($rows as $c) {
                if(isset($keys[$c['wp_key']])) continue;
                $base=$c['slug']; $n=2; while(isset($slugs[$c['slug']])) $c['slug']=$base.'-'.$n++;
                $slugs[$c['slug']]=true; $keys[$c['wp_key']]=true; $s['contents'][$c['id']]=$c; $count++;
            }
            Store::log($s,'wordpress_imported',(string)$count); return $count;
        });
    }
}
