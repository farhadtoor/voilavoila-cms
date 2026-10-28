<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

/** WXR is parsed as data. No network, WordPress runtime or serialized PHP. */
final class EditorialImport {
    public static function run(App $app,array $file,bool $publish,array $uploads=[],array $options=[]): array {
        $xml=file_get_contents(Tools::uploaded($file,8*1024*1024));
        $uploadPath=($uploads['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE?'':Tools::uploaded($uploads,64*1024*1024);
        return self::parse($app,$xml,$publish,$uploadPath,$options);
    }
    /** Internal staged bytes, never a path supplied by a web request. */
    public static function staged(App $app,string $xml,bool $publish,string $zipBytes,array $options): array {
        if(strlen($xml)>8*1048576||strlen($zipBytes)>64*1048576)throw new Failure('Import trop volumineux.',413);
        $tmp='';
        try { if($zipBytes!==''){$tmp=tempnam(sys_get_temp_dir(),'vv-import-');chmod($tmp,0600);if(file_put_contents($tmp,$zipBytes)!==strlen($zipBytes))throw new Failure('Copie de l’import impossible.',503);}return self::parse($app,$xml,$publish,$tmp,$options); }
        finally {if($tmp!=='')@unlink($tmp);}
    }
    public static function fingerprint(array $state): string {
        $contents=[];foreach($state['contents'] as $id=>$c)$contents[$id]=[$c['slug'],$c['version'],$c['status'],$c['publish_at'],$c['wp_key']??'',$c['wp_source']??'',$c['wp_id']??'',$c['wp_link']??''];
        $media=[];foreach($state['media'] as $id=>$m)$media[$id]=[$m['sha256'],$m['public'],$m['wp_key']??''];
        ksort($contents);ksort($media);return hash('sha256',json_encode([$contents,$media,$state['plugins']],JSON_THROW_ON_ERROR));
    }
    private static function parse(App $app,string $xml,bool $publish,string $uploadPath,array $options): array {
        $doc=Security::xmlDocument($xml,8*1024*1024); $xp=new \DOMXPath($doc); $ns='';
        foreach($doc->getElementsByTagName('channel')->item(0)?->childNodes ?? [] as $n) if($n instanceof \DOMElement && $n->localName==='wxr_version' && preg_match('~^http://wordpress.org/export/1\.[012]/$~D',$n->namespaceURI ?? '')) $ns=$n->namespaceURI;
        if(!$ns || !$doc->documentElement || $doc->documentElement->tagName!=='rss') throw new Failure('Choisissez un export WordPress WXR 1.0, 1.1 ou 1.2.');
        $xp->registerNamespace('wp',$ns); $xp->registerNamespace('content','http://purl.org/rss/1.0/modules/content/'); $xp->registerNamespace('excerpt','http://wordpress.org/export/1.2/excerpt/');
        $version=(string)$xp->evaluate('string(/rss/channel/wp:wxr_version)');
        if(!in_array($version,['1.0','1.1','1.2'],true)) throw new Failure('Version WXR non prise en charge.');
        $items=$xp->query('/rss/channel/item');
        if($items->length>2000) throw new Failure('Import limité à 2 000 éléments WXR.',413);
        $report=['format'=>'voilavoila-wordpress-import','version'=>1,'at'=>gmdate('c'),'wxr'=>$version,'source'=>'','imported'=>0,'duplicates'=>0,'media_imported'=>0,'media_reused'=>0,'comments_imported'=>0,'skipped'=>0,'warnings'=>[],'warning_count'=>0,'links'=>[]];
        $warn=function(string $text) use(&$report) { $report['warning_count']++; if(count($report['warnings'])<100) $report['warnings'][]=Content::text($text,300); };
        $source=\val($options,'source_url') ?: (string)$xp->evaluate('string(/rss/channel/wp:base_blog_url)') ?: (string)$xp->evaluate('string(/rss/channel/link)') ?: (string)$xp->evaluate('string(/rss/channel/wp:base_site_url)');
        if(!$source) foreach($items as $item) { $g=(string)$xp->evaluate('string(guid)',$item); if(preg_match('~^https?://~i',$g)) { $p=parse_url($g); $source=($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port'])?':'.$p['port']:''); break; } }
        $source=self::source($source); $report['source']=$source;
        if(!empty($options['comments']) && (!in_array('comments',$app->state['plugins'],true) || !isset($app->hooks['wordpress_import_state_v1']))) throw new Failure('Activez l’extension Commentaires avant d’importer les commentaires.');
        $posts=[]; $attachments=[]; $comments=[]; $seen=[]; $allIds=[]; $thumbnail=[]; $links=[]; $postPasswords=[];
        foreach($items as $item) {
            $get=fn($q)=>(string)$xp->evaluate('string('.$q.')',$item);
            $type=$get('wp:post_type'); if(!in_array($type,['post','page','attachment'],true)) { $report['skipped']++; continue; }
            $pid=$get('wp:post_id');
            if(!preg_match('/^[1-9][0-9]{0,18}$/D',$pid) || isset($allIds[$pid])) throw new Failure('Identifiant WordPress absent ou en double.');
            $allIds[$pid]=true; $key=hash('sha256',$source.'|'.$pid); $link=$get('link') ?: $get('guid');
            if($type==='attachment') {
                $relative=''; $alt='';
                foreach($xp->query('wp:postmeta',$item) as $meta) {
                    $mk=(string)$xp->evaluate('string(wp:meta_key)',$meta); $mv=(string)$xp->evaluate('string(wp:meta_value)',$meta);
                    if($mk==='_wp_attached_file') $relative=$mv;
                    if($mk==='_wp_attachment_image_alt') $alt=$mv;
                }
                $url=$get('wp:attachment_url') ?: $get('guid');
                if(!$relative) { $urlpath=parse_url($url,PHP_URL_PATH) ?: ''; $at=strpos($urlpath,'/uploads/'); if($at!==false) $relative=rawurldecode(substr($urlpath,$at+9)); }
                if(!self::relative($relative)) { $warn('Média ignoré : chemin uploads absent ou non sûr (ID '.$pid.').'); continue; }
                $attachments[$pid]=['path'=>$relative,'url'=>$url,'alt'=>$alt,'wp_key'=>$key,'created_at'=>self::date($get('wp:post_date_gmt'),$get('wp:post_date'),$warn)];
                continue;
            }
            $password=$get('wp:post_password'); $postPasswords[$pid]=$password!=='';
            $wpstatus=$get('wp:status'); $date=self::date($get('wp:post_date_gmt'),$get('wp:post_date'),$warn);
            $status=$password!=='' || $wpstatus==='private'?'private':($wpstatus==='trash'?'trash':($publish && in_array($wpstatus,['publish','future'],true)?'published':'draft'));
            $tags=[];
            foreach($xp->query('category',$item) as $cat) if(in_array($cat->getAttribute('domain'),['post_tag','category'],true)) { $tag=Content::text($cat->textContent,40); if($tag!=='') $tags[]=$tag; }
            $tags=array_values(array_unique($tags)); if(count($tags)>12) $warn('Mots-clés/catégories limités à 12 pour le contenu '.$pid.'.');
            $excerpt=''; foreach($item->childNodes as $n) if($n instanceof \DOMElement && $n->localName==='encoded' && str_contains($n->namespaceURI ?? '','/excerpt/')) $excerpt=$n->textContent;
            $body=$get('content:encoded');
            if(strlen($body)>200000) throw new Failure('Contenu WordPress limité à 200 Ko par publication.',413);
            if(preg_match('/\[(?!\/?(?:caption|gallery)\b)[a-z][a-z0-9_-]*(?:\s|\])/i',$body)) $warn('Shortcodes à vérifier dans le contenu '.$pid.'.');
            $posts[$pid]=['id'=>bin2hex(random_bytes(12)),'wp_key'=>$key,'wp_source'=>$source,'wp_id'=>$pid,'wp_link'=>Content::link($link),'wp_legacy_key'=>hash('sha256',$get('guid').'|'.$pid.'|'.$get('title')),'type'=>$type,'status'=>$status,'slug'=>Content::slug($get('wp:post_name') ?: $get('title')),'title_fr'=>Content::text($get('title')) ?: 'WordPress '.$pid,'title_en'=>'','excerpt_fr'=>Content::summary($excerpt,400),'excerpt_en'=>'','body_fr'=>$body,'body_en'=>'','publish_at'=>strtotime($date),'created_at'=>$date,'updated_at'=>self::date($get('wp:post_modified_gmt') ?: $get('wp:post_date_gmt'),$get('wp:post_modified') ?: $get('wp:post_date'),$warn),'cover'=>'','tags'=>array_slice($tags,0,12),'menu'=>false,'pinned'=>$get('wp:is_sticky')==='1','order'=>max(0,min(999,(int)$get('wp:menu_order'))),'version'=>1,'revisions'=>[],'comments_open'=>$get('wp:comment_status')!=='closed'];
            foreach($xp->query('wp:postmeta',$item) as $meta) if($xp->evaluate('string(wp:meta_key)',$meta)==='_thumbnail_id') $thumbnail[$pid]=(string)$xp->evaluate('string(wp:meta_value)',$meta);
            if($get('wp:post_parent')!=='0' && $get('wp:post_parent')!=='') $warn('Hiérarchie de page aplatie pour le contenu '.$pid.'.');
            if(!empty($options['comments'])) foreach($xp->query('wp:comment',$item) as $comment) {
                if(count($comments)>=1000) throw new Failure('Import limité à 1 000 commentaires.',413);
                $cg=fn($q)=>(string)$xp->evaluate('string('.$q.')',$comment);
                if(!in_array($cg('wp:comment_approved'),['0','1'],true) || !in_array($cg('wp:comment_type'),['','comment'],true)) continue;
                $commentId=$cg('wp:comment_id');
                if(!preg_match('/^[1-9][0-9]{0,18}$/D',$commentId)) { $warn('Commentaire sans identifiant ignoré.'); continue; }
                $comments[]=['post_id'=>$pid,'wp_key'=>hash('sha256',$source.'|comment|'.$commentId),'name'=>$cg('wp:comment_author'),'body'=>$cg('wp:comment_content'),'created_at'=>self::date($cg('wp:comment_date_gmt'),$cg('wp:comment_date'),$warn)];
            }
        }
        if(!$posts) throw new Failure('Cet export ne contient aucune page ou article importable.');
        $zip=null; $entries=[];
        if($uploadPath!=='') {
            Tools::zip(); $up=$uploadPath; $zip=new \ZipArchive();
            if($zip->open($up,\ZipArchive::RDONLY | \ZipArchive::CHECKCONS)!==true) throw new Failure('ZIP uploads illisible.');
            try { $entries=Tools::zipEntries($zip,2200,64*1024*1024); }
            catch(\Throwable $e) { $zip->close(); throw $e; }
        }
        $media=[]; $binaries=[];
        try {
            foreach($attachments as $pid=>$a) {
                if(!$zip) { $warn('Média '.$pid.' absent : joignez le ZIP uploads au même import.'); continue; }
                $entry=null; foreach(['','uploads/','wp-content/uploads/'] as $prefix) if(isset($entries[strtolower($prefix.$a['path'])])) { if($entry) throw new Failure('Chemin uploads ambigu.'); $entry=$entries[strtolower($prefix.$a['path'])]; }
                if(!$entry) { $warn('Média absent du ZIP : '.$a['path']); continue; }
                $bytes=$zip->getFromIndex($entry['index']); $stat=$zip->statIndex($entry['index']);
                if(!is_string($bytes) || strlen($bytes)!==$entry['size'] || hash('crc32b',$bytes)!==sprintf('%08x',$stat['crc'])) throw new Failure('Intégrité du média WordPress incorrecte.');
                try { $m=Media::metadata(['id'=>bin2hex(random_bytes(12)),'name'=>basename($a['path']),'public'=>!empty($options['public_media']),'alt'=>$a['alt'],'created_at'=>$a['created_at'],'wp_key'=>$a['wp_key'],'wp_source'=>$source,'wp_id'=>(string)$pid],$bytes); }
                catch(Failure $e) { $warn('Média '.$pid.' refusé : '.$e->getMessage()); continue; }
                $media[$pid]=$m; $binaries[$pid]=$bytes;
            }
        } finally { if($zip) $zip->close(); }
        if(!empty($options['preview']))return self::plan($app,$posts,$media,$report);
        Tools::snapshot($app); $written=[];
        try {
            return $app->store->change(function(&$s) use($app,$posts,$media,$binaries,$attachments,$thumbnail,$comments,$options,&$report,$warn,&$written,$source) {
                if(isset($options['expected_fingerprint'])&&!hash_equals($options['expected_fingerprint'],self::fingerprint($s)))throw new Failure('Le site a changé. Relancez la prévisualisation de l’import.',409);
                $postMap=[]; $mediaMap=[]; $slugs=[]; $known=[]; $knownMedia=[];
                foreach($s['contents'] as $c) { $slugs[$c['slug']]=true; if(isset($c['wp_key'])) $known[$c['wp_key']]=$c['id']; }
                foreach($s['media'] as $m) if(isset($m['wp_key'])) $knownMedia[$m['wp_key']]=$m;
                foreach($posts as $pid=>$c) {
                    $existing=$known[$c['wp_key']] ?? $known[$c['wp_legacy_key']] ?? '';
                    if($existing) {
                        $postMap[$pid]=$existing; $report['duplicates']++;
                        foreach(['wp_key','wp_source','wp_id','wp_link'] as $key) $s['contents'][$existing][$key]=$c[$key];
                        continue;
                    }
                    $base=$c['slug']; $n=2; while(isset($slugs[$c['slug']])) $c['slug']=substr($base,0,90).'-'.$n++;
                    $slugs[$c['slug']]=true; $postMap[$pid]=$c['id']; $posts[$pid]=$c; $known[$c['wp_key']]=$c['id'];
                }
                foreach($media as $pid=>$m) {
                    if(isset($knownMedia[$m['wp_key']])) {
                        $old=$knownMedia[$m['wp_key']];
                        if(!hash_equals($old['sha256'],$m['sha256'])) { $warn('Média WordPress modifié : ancien fichier conservé (ID '.$pid.').'); continue; }
                        $mediaMap[$pid]=$old; $report['media_reused']++; continue;
                    }
                    if(count($s['media'])>=2000) throw new Failure('Médiathèque limitée à 2 000 fichiers.',413);
                    $path=$app->store->mediaPath($m); $app->store->writeMedia($m,$binaries[$pid]); $written[]=$path;
                    $s['media'][$m['id']]=$m; $knownMedia[$m['wp_key']]=$m; $mediaMap[$pid]=$m; $report['media_imported']++;
                }
                $map=[];
                foreach($posts as $pid=>$c) {
                    $target=$s['contents'][$postMap[$pid]] ?? $c; $new=Content::url($target);
                    foreach([$c['wp_link'],$source.'/?p='.$pid,$source.'/?page_id='.$pid] as $old) if($old!=='') { $map[self::key($old)]=$new; $report['links'][]=['from'=>$old,'to'=>$new]; }
                }
                foreach($mediaMap as $pid=>$m) {
                    $old=$attachments[$pid]['url']; $new=\url('media.php',['id'=>$m['id']]);
                    if($old!=='') { $map[self::key($old)]=$new; $map[self::key(parse_url($old,PHP_URL_PATH) ?: '')]=$new; }
                    $map[self::key('/wp-content/uploads/'.$attachments[$pid]['path'])]=$new;
                }
                foreach($posts as $pid=>$c) {
                    $target=$postMap[$pid];
                    if(isset($s['contents'][$target])) continue; // Never overwrite local edits on repeat imports.
                    $c['body_fr']=Content::html(self::rewrite($c['body_fr'],$map,$mediaMap,$source,$warn,(string)$pid));
                    if(isset($thumbnail[$pid],$mediaMap[$thumbnail[$pid]])) {
                        $cover=$mediaMap[$thumbnail[$pid]];
                        if($cover['public'] && !Media::document($cover)) $c['cover']=$cover['id'];
                        else $warn('Couverture '.$pid.' laissée vide : son média est privé ou non image.');
                    }
                    unset($c['wp_legacy_key']); $s['contents'][$target]=$c; $report['imported']++;
                }
                if(count($s['contents'])>10000) throw new Failure('Site limité à 10 000 contenus.',413);
                $before=count($s['plugin_data']['comments']['rows'] ?? []);
                $s=\vv_filter('wordpress_import_state_v1',$s,['import_comments'=>!empty($options['comments']),'comments'=>$comments,'post_map'=>$postMap]);
                $report['comments_imported']=max(0,count($s['plugin_data']['comments']['rows'] ?? [])-$before);
                $report['note']='No remote downloads. Existing contents unchanged. Imported comments pending. URL map is informational; redirects are not installed.';
                $s['imports']['wordpress']=$report; Store::log($s,'wordpress_imported',(string)$report['imported']);
                return $report;
            });
        } catch(\Throwable $e) { foreach($written as $p) @unlink($p); throw $e; }
    }
    private static function plan(App $app,array $posts,array $media,array $report): array {
        $state=$app->store->read();$known=[];$slugs=[];$knownMedia=[];
        foreach($state['contents'] as $c){$slugs[$c['slug']]=true;if(isset($c['wp_key']))$known[$c['wp_key']]=$c;}
        foreach($state['media'] as $m)if(isset($m['wp_key']))$knownMedia[$m['wp_key']]=$m;
        $report['preview']=true;$report['state_fingerprint']=self::fingerprint($state);$report['planned']=[];$report['collisions']=0;
        foreach($posts as $pid=>$c){$existing=$known[$c['wp_key']]??$known[$c['wp_legacy_key']]??null;$slug=$existing['slug']??$c['slug'];$base=$slug;$n=2;
            if(!$existing)while(isset($slugs[$slug]))$slug=substr($base,0,90).'-'.$n++;
            $collision=!$existing&&$slug!==$c['slug'];$report['collisions']+=(int)$collision;$slugs[$slug]=true;
            $report['planned'][]=['wordpress_id'=>(string)$pid,'title'=>$c['title_fr'],'type'=>$c['type'],'status'=>$existing['status']??$c['status'],'action'=>$existing?'keep':'create','slug'=>$slug,'collision'=>$collision,'from'=>$c['wp_link'],'to'=>\url('index.php',['p'=>$slug,'lang'=>\lang()])];
            if($existing)$report['duplicates']++;else $report['imported']++;
        }
        foreach($media as $pid=>$m)if(isset($knownMedia[$m['wp_key']])){
            if(hash_equals($knownMedia[$m['wp_key']]['sha256'],$m['sha256']))$report['media_reused']++;
            else{$report['warning_count']++;if(count($report['warnings'])<100)$report['warnings'][]='Média WordPress modifié : ancien fichier conservé (ID '.$pid.').';}
        }else $report['media_imported']++;
        $report['note']='Preview only. No content, media, comments or redirect changes. Existing imported content will be preserved. Rules require a separate owner approval.';
        return $report;
    }
    private static function relative(string $p): bool { return $p!=='' && strlen($p)<=200 && !preg_match('~[\x00-\x1f\x7f\\\\:]|^/|(?:^|/)\.{1,2}(?:/|$)|//~',$p); }
    private static function source(string $url): string {
        $url=rtrim(trim($url),'/'); $p=parse_url($url);
        if(!$p || !in_array($p['scheme'] ?? '',['http','https'],true) || !filter_var($url,FILTER_VALIDATE_URL) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || strlen($url)>400) throw new Failure('URL du site WordPress absente ou invalide. Renseignez son URL source.');
        return strtolower($p['scheme']).'://'.strtolower($p['host']).(isset($p['port'])?':'.$p['port']:'').rtrim($p['path'] ?? '','/');
    }
    private static function key(string $url): string { return html_entity_decode(trim($url),ENT_QUOTES | ENT_HTML5,'UTF-8'); }
    private static function date(string $gmt,string $local,callable $warn): string {
        foreach([$gmt,$local] as $i=>$v) {
            if(!preg_match('/^[1-9][0-9]{3}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$v)) continue;
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$v,new \DateTimeZone('UTC'));
            if($date && $date->format('Y-m-d H:i:s')===$v) { if($i===1) $warn('Date GMT absente : date locale interprétée en UTC.'); return $date->format('c'); }
        }
        $warn('Date WordPress invalide : date de l’import utilisée.'); return gmdate('c');
    }
    private static function rewrite(string $html,array $map,array $media,string $source,callable $warn,string $pid): string {
        $html=preg_replace('~\[/?caption\b[^\]]*\]~i','',$html);
        $html=preg_replace_callback('~\[gallery\b[^\]]*ids=["\x27]([0-9, ]+)["\x27][^\]]*\]~i',function($m) use($media,$warn,$pid) {
            $out=''; foreach(explode(',',$m[1]) as $id) { $id=trim($id); if(isset($media[$id]) && !Media::document($media[$id])) $out.='<figure><img src="'.\e(\url('media.php',['id'=>$media[$id]['id']])).'" alt="'.\e($media[$id]['alt']).'"></figure>'; else $warn('Image de galerie absente dans le contenu '.$pid.'.'); } return $out;
        },$html);
        if(!preg_match('~<(?:p|div|h[1-6]|ul|ol|table|figure|blockquote|pre)\b~i',$html)) $html='<p>'.preg_replace('/\r?\n\r?\n/','</p><p>',$html).'</p>';
        $doc=new \DOMDocument('1.0','UTF-8'); $old=libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>',LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        foreach($doc->getElementsByTagName('*') as $node) {
            foreach(['href','src'] as $attr) if($node->hasAttribute($attr)) {
                $oldUrl=self::key($node->getAttribute($attr)); $resolved=$oldUrl;
                if(!str_starts_with($oldUrl,'/') && !preg_match('~^[a-z][a-z0-9+.-]*:|^#~i',$oldUrl)) $resolved=$source.'/'.ltrim($oldUrl,'/');
                $fragment=parse_url($resolved,PHP_URL_FRAGMENT);
                $lookup=explode('#',$resolved,2)[0];
                $target=$map[$oldUrl] ?? $map[$resolved] ?? $map[$lookup] ?? '';
                if(!$target && $attr==='src') {
                    $candidate=preg_replace('/-\d+x\d+(?=\.(?:jpe?g|png|webp)(?:\?|$))/i','',explode('?',$lookup,2)[0]);
                    $target=$map[$candidate] ?? '';
                }
                if($target && $attr==='href' && is_string($fragment) && $fragment!=='') $target.='#'.rawurlencode(rawurldecode($fragment));
                if($target) $node->setAttribute($attr,$target);
                elseif($attr==='src' && $node->tagName==='img') $warn('Image externe ou absente retirée du contenu '.$pid.'.');
                elseif($attr==='href' && str_starts_with($resolved,$source.'/')) $warn('Lien WordPress non remappé dans le contenu '.$pid.'.');
            }
        }
        $out=''; foreach($doc->getElementsByTagName('body')->item(0)?->childNodes ?? [] as $n) $out.=$doc->saveHTML($n);
        return $out;
    }
}
