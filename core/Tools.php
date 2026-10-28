<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class Tools {
    private static array $uploadReceipts=[];
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
        $p=$file['tmp_name']; if(filesize($p)<1 || filesize($p)>$max) throw new Failure('Archive trop volumineuse.',413);
        global $vv;
        $key=$p.'|'.hash_file('sha256',$p);
        if(isset($vv)&&$vv instanceof App&&!isset(self::$uploadReceipts[$key])) {
            $qid=Quarantine::capture($vv->store,$file,'import',$max);
            Quarantine::validate($vv->store,$qid,static fn($bytes)=>['bytes'=>strlen($bytes),'type'=>'offline-import']);
            Quarantine::approved($vv->store,$qid,'import');self::$uploadReceipts[$key]=$qid;
        }
        return $p;
    }
    public static function uploadReceipt(array $file): ?string {
        $path=$file['tmp_name']??'';
        return is_string($path)&&is_file($path)?(self::$uploadReceipts[$path.'|'.hash_file('sha256',$path)]??null):null;
    }
    /** Reject traversal, symlinks, duplicates, encrypted entries and decompression bombs before extraction. */
    public static function zipEntries(\ZipArchive $zip,int $maxFiles=2200,int $maxBytes=67108864,int $maxFileBytes=8388608): array {
        if($zip->numFiles<1 || $zip->numFiles>$maxFiles) throw new Failure('Nombre de fichiers dans le ZIP non autorisé.');
        $total=0; $names=[];
        for($i=0;$i<$zip->numFiles;$i++) {
            $s=$zip->statIndex($i); $n=$s['name'] ?? '';
            if(!$n || strlen($n)>220 || preg_match('/[\x00-\x1f\\\\:]/',$n) || str_starts_with($n,'/') || preg_match('~(?:^|/)\.\.?(/|$)~',$n) || str_contains($n,'//') || isset($names[strtolower($n)])) throw new Failure('Chemin de ZIP refusé.');
            $ops=0; $attrs=0; $zip->getExternalAttributesIndex($i,$ops,$attrs);
            if(!in_array((($attrs>>16)&0170000),[0,0100000,0040000],true) || !empty($s['encryption_method'])) throw new Failure('Entrée spéciale, lien symbolique ou entrée chiffrée refusée.');
            $total+=(int)$s['size'];
            if((int)$s['size']>$maxFileBytes || $total>$maxBytes || ((int)$s['size']>1048576 && (int)$s['size']>200*max(1,(int)$s['comp_size']))) throw new Failure('ZIP décompressé trop volumineux.',413);
            $names[strtolower($n)]=['name'=>$n,'index'=>$i,'size'=>$s['size']];
        }
        return $names;
    }
    public static function installPackage(App $app,array $file,string $type): string {
        return Packages::stage($app,$file,$type);
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
                    $data=['format'=>'voilavoila-cms','schema'=>2,'created_at'=>gmdate('c'),'version'=>VV_VERSION,'settings'=>$s['settings'],'contents'=>$s['contents'],'media'=>$s['media'],'plugin_data'=>$s['plugin_data'],'redirects'=>$s['redirects']??[]];
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
        $files=Operations::points($app); $keep=Operations::config($app)['backups_keep'];
        foreach(array_slice($files,$keep) as $p) unlink($p);
        return $id;
    }
    public static function restore(App $app,array $file): void {
        $id=Quarantine::capture($app->store,$file,'backup',64*1048576);
        Quarantine::validate($app->store,$id,static function($bytes):array {
            $tmp=tempnam(sys_get_temp_dir(),'vv-backup-review-');file_put_contents($tmp,$bytes);$zip=new \ZipArchive();
            try{if($zip->open($tmp)!==true)throw new Failure('Sauvegarde ZIP illisible.');$entries=self::zipEntries($zip);if(!isset($entries['data.json']))throw new Failure('data.json absent.');$data=json_decode($zip->getFromName('data.json'),true,80,JSON_THROW_ON_ERROR);if(($data['format']??'')!=='voilavoila-cms')throw new Failure('Format de sauvegarde incompatible.');return ['contents'=>count($data['contents']??[]),'media'=>count($data['media']??[])];}finally{try{$zip->close();}catch(\Throwable){}@unlink($tmp);}
        });
    }
    public static function restoreApproved(App $app,string $id): void {
        Quarantine::approved($app->store,$id,'backup');$bytes=Quarantine::bytes($app->store,$id);$tmp=tempnam(sys_get_temp_dir(),'vv-restore-');file_put_contents($tmp,$bytes);
        try{self::restorePath($app,$tmp);Quarantine::consume($app->store,$id);}finally{@unlink($tmp);}
    }
    private static function restorePath(App $app,string $p): void {
        self::zip(); $zip=new \ZipArchive();
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
                $entry=preg_match('/^'.preg_quote($id,'/').'\\.(jpg|jpeg|png|webp|pdf|docx|zip|csv)$/D',$stored)?'media/'.$stored:'media/'.$id.'.bin';
                if(!isset($entries[strtolower($entry)])) throw new Failure('Média manquant.');
                $bytes=$zip->getFromName($entry);
                if(!hash_equals(\val($m,'sha256'),hash('sha256',$bytes))) throw new Failure('Intégrité du média incorrecte.');
                $media[$id]=Media::metadata(['id'=>$id,'name'=>Content::text(\val($m,'name'),160),'public'=>!empty($m['public']),'alt'=>Content::text(\val($m,'alt'),250),'alt_en'=>Content::text(\val($m,'alt_en'),250),'created_at'=>Content::timestamp(\val($m,'created_at'))],$bytes,($data['schema']===1));
                foreach(['wp_key','wp_source','wp_id'] as $key) if(is_string($m[$key] ?? null) && strlen($m[$key])<=400) $media[$id][$key]=$m[$key];
                $mediaEntries[$id]=$entry;
            }
            foreach($data['contents'] as $id=>$c) {
                if(!is_string($id) || !preg_match('/^[a-f0-9]{24}$/D',$id) || !is_array($c)) throw new Failure('Contenu invalide.');
                $row=['id'=>$id,'type'=>in_array(\val($c,'type'),['post','page'],true)?$c['type']:'post','status'=>in_array(\val($c,'status'),['draft','published','private','trash'],true)?$c['status']:'draft','slug'=>Content::slug(\val($c,'slug')),'revisions'=>[],'version'=>1,'created_at'=>Content::timestamp(\val($c,'created_at')),'updated_at'=>Content::timestamp(\val($c,'updated_at')),'publish_at'=>max(1,(int)($c['publish_at'] ?? time())),'pinned'=>!empty($c['pinned']),'menu'=>!empty($c['menu']),'order'=>max(0,min(999,(int)($c['order'] ?? 10))),'cover'=>'','tags'=>[]];
                if(isset($ids[$row['slug']])) throw new Failure('Slugs en double dans la sauvegarde.'); $ids[$row['slug']]=true;
                foreach(['fr','en'] as $l) { $row['title_'.$l]=Content::text(\val($c,'title_'.$l)); $row['excerpt_'.$l]=Content::text(\val($c,'excerpt_'.$l),400); $row['body_'.$l]=Content::html(Content::relocateMedia(\val($c,'body_'.$l))); }
                if(preg_match('/^[a-f0-9]{64}$/D',\val($c,'wp_key'))) $row['wp_key']=$c['wp_key'];
                foreach(['wp_source','wp_id','wp_link'] as $key) if(is_string($c[$key] ?? null) && strlen($c[$key])<=1000) $row[$key]=$c[$key];
                if(isset($c['comments_open'])) $row['comments_open']=$c['comments_open']===true;
                if(is_array($c['seo']??null))$row['seo']=Metadata::clean($c['seo']);
                foreach(['fr','en']as$l)$row['nav_label_'.$l]=Content::text(\val($c,'nav_label_'.$l),80);
                foreach(array_slice(is_array($c['revisions']??null)?$c['revisions']:[],-15)as$revision)if(is_array($revision)){
                    $r=$row;unset($r['revisions']);
                    foreach(['fr','en']as$l){$r['title_'.$l]=Content::text(\val($revision,'title_'.$l));$r['excerpt_'.$l]=Content::text(\val($revision,'excerpt_'.$l),400);$r['body_'.$l]=Content::html(Content::relocateMedia(\val($revision,'body_'.$l)));$r['nav_label_'.$l]=Content::text(\val($revision,'nav_label_'.$l),80);}
                    $r['status']=in_array(\val($revision,'status'),['draft','published','private','trash'],true)?$revision['status']:'draft';$r['slug']=Content::slug(\val($revision,'slug'));$r['version']=max(1,(int)($revision['version']??1));$r['updated_at']=Content::timestamp(\val($revision,'updated_at'));$r['publish_at']=max(1,(int)($revision['publish_at']??time()));
                    $r['tags']=array_values(array_filter(array_map(fn($tag)=>is_string($tag)?Content::text($tag,40):'',array_slice(is_array($revision['tags']??null)?$revision['tags']:[],0,12))));$r['menu']=!empty($revision['menu']);$r['pinned']=!empty($revision['pinned']);$r['order']=max(0,min(999,(int)($revision['order']??10)));
                    $cover=\val($revision,'cover');$r['cover']=isset($media[$cover])&&$media[$cover]['public']&&str_starts_with($media[$cover]['mime'],'image/')?$cover:'';if(is_array($revision['seo']??null))$r['seo']=Metadata::clean($revision['seo']);$row['revisions'][]=$r;
                }

                foreach(array_slice(is_array($c['tags'] ?? null)?$c['tags']:[],0,12) as $tag) if(is_string($tag)) $row['tags'][]=Content::text($tag,40);
                $cover=\val($c,'cover'); if(isset($media[$cover]) && $media[$cover]['public'] && str_starts_with($media[$cover]['mime'],'image/')) $row['cover']=$cover;
                $contents[$id]=$row;
            }
            $settings=self::settings($data['settings'],$app->state['settings'],$media);
            $settings['operations']=Operations::normalize(is_array($data['settings']['operations']??null)?$data['settings']['operations']:[]);
            $theme=\val($data['settings'],'theme'); $settings['theme']=isset($app->packages('theme')[$theme])?$theme:'profil-or';
            if(!isset($contents[$settings['home_page']])) $settings['home_page']='';
            $data=\vv_filter('backup_restore_data_v1',$data);
            if(!is_array($data) || !is_array($data['plugin_data'] ?? null)) throw new Failure('Données d’extension invalides.');
            self::snapshot($app);
            $app->store->change(function(&$s) use($app,$zip,$media,$mediaEntries,$contents,$settings,$data) {
                foreach($media as $id=>$m) {
                    if(isset($s['media'][$id]) && !hash_equals($s['media'][$id]['sha256'],$m['sha256'])) throw new Failure('Conflit de média. Restaurez dans une installation séparée.',409);
                    $app->store->writeMedia($m,$zip->getFromName($mediaEntries[$id]));
                }
                $s['contents']=$contents; $s['media']=$media; $s['settings']=$settings; $s['plugin_data']=$data['plugin_data']; $s['redirects']=Redirects::validate(is_array($data['redirects']??null)?$data['redirects']:[],$contents); unset($s['imports']);
                Store::log($s,'backup_restored');
            });
        } catch(\JsonException $e) { throw new Failure('JSON de sauvegarde illisible.'); }
        finally { $zip->close(); }
    }
    public static function wordpress(App $app,array $file,bool $publish,array $uploads=[],array $options=[]): int {
        require_once __DIR__.'/EditorialImport.php';
        $report=EditorialImport::run($app,$file,$publish,$uploads,$options);
        return $report['imported'];
    }
}
