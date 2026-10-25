<?php
declare(strict_types=1);
namespace VV\WPPorter;
defined('VV_ROOT') || exit;

final class Analyzer {
    public const MAP=[
        'add_shortcode'=>['vv_shortcode','Shortcodes sans attributs / Shortcodes without attributes'],
        'add_action'=>['vv_action','Les points d’accroche diffèrent / Hook names differ'],
        'add_filter'=>['vv_action + vv_filter','Réécrire le callback / Rewrite the callback'],
        'the_title'=>['VV\\Content::field($content, "title")','Titre FR/EN / FR/EN title'],
        'the_content'=>['$app->body($content)','HTML natif et shortcodes / Native HTML and shortcodes'],
        'the_excerpt'=>['VV\\Content::field($content, "excerpt")','Résumé / Excerpt'],
        'the_permalink'=>['VV\\Content::url($content)','Lien natif / Native link'],
        'have_posts'=>['$app->publicItems() / contexte $items','Boucle native / Native loop'],
        'get_option'=>['vv_option(module, key, default)','Options du module uniquement / Module options only'],
        'update_option'=>['$app->store->change(... plugin_data[id] ...)','Transaction native / Native transaction'],
        'add_menu_page'=>['$app->adminPage(...)','Formulaire POST + CSRF / POST + CSRF form'],
        'add_submenu_page'=>['$app->adminPage(...)','Pas de rôles multiples / No multiple roles'],
        'wp_create_nonce'=>['VV\\Security::csrf()','Jeton natif / Native token'],
        'check_admin_referer'=>['VV\\Security::post()','Le contrôleur central vérifie CSRF / Central controller checks CSRF'],
        'current_user_can'=>['VV\\Security::requireOwner(...)','Un propriétaire / One owner'],
        'is_user_logged_in'=>['VV\\Security::owner(...)','Aucun compte client / No customer accounts'],
        'wp_enqueue_style'=>['<link> vers une ressource locale / local asset','Styles statiques seulement / Static styles only'],
        'wp_enqueue_script'=>['<script src> local + API native','Porter les appels réseau et événements / Port networking and events'],
        'register_rest_route'=>['Contrôleur natif à concevoir / Design a native controller','Méthodes, droits, CSRF, validation / Methods, authorization, CSRF, validation'],
        'wp_schedule_event'=>['Tâche serveur explicite / Explicit server job','Pas de cron WordPress / No WordPress cron'],
        'wp_remote_get'=>['Client HTTP serveur à concevoir / Design a server HTTP client','Délais, hôtes autorisés, erreurs / Timeouts, allowed hosts, failures'],
        'wp_mail'=>['Transport mail à concevoir / Design an email transport','Le CMS ne fournit pas wp_mail / CMS does not provide wp_mail'],
        'register_block_type'=>['Thème/extension native à réécrire / Rewrite as native module','Aucun moteur Gutenberg / No Gutenberg runtime'],
        'register_post_type'=>['Pages/articles ou données d’extension / Pages/posts or plugin data','Le schéma natif ne fournit pas les CPT / No native CPT subsystem'],
        'register_activation_hook'=>['Initialisation native idempotente / Idempotent native initialization','Pas d’activation WordPress / No WordPress activation hook'],
    ];
    public static function issue(array &$r,string $code,string $fr,string $en,string $file='',int $line=0,string $severity='blocker'): void {
        $r['issue_totals'][$severity]=($r['issue_totals'][$severity] ?? 0)+1;
        if(count($r['issues'])<150) $r['issues'][]=compact('code','severity','file','line','fr','en');
    }
    public static function analyze(Archive $a,string $requested='auto'): array {
        $theme=isset($a->files['style.css'])?Archive::header($a->files['style.css'],'Theme Name'):'';
        $candidates=[];
        foreach($a->files as $file=>$code) if(!str_contains($file,'/') && preg_match('/\.php$/i',$file) && ($name=Archive::header($code,'Plugin Name'))!=='') $candidates[$file]=$name;
        if($requested==='auto') {
            if($theme!=='' && $candidates) throw new PortError('ZIP ambigu : choisissez thème ou extension.','Ambiguous ZIP: choose theme or plugin.');
            $type=$theme!==''?'theme':'plugin';
        } else $type=$requested;
        if(!in_array($type,['theme','plugin'],true)) throw new PortError('Type de module invalide.','Invalid module type.');
        if($type==='theme') {
            if($theme==='') throw new PortError('En-tête Theme Name introuvable dans style.css.','Theme Name header was not found in style.css.');
            $entry='style.css';$name=$theme;
        } else {
            if(count($candidates)!==1) throw new PortError('Le ZIP doit contenir une seule extension avec son en-tête Plugin Name à la racine.','ZIP must contain exactly one plugin with its Plugin Name header at the package root.');
            $entry=array_key_first($candidates);$name=$candidates[$entry];
        }
        $meta=$a->files[$entry];
        $r=['format'=>'voilavoila-wp-port-report','format_version'=>1,'porter_version'=>'1.0.0','created_at'=>gmdate('c'),
            'source'=>['type'=>$type,'name'=>$name,'version'=>Archive::header($meta,'Version'),'author'=>Archive::header($meta,'Author'),'license'=>Archive::header($meta,'License'),'entry'=>$entry,'archive_sha256'=>$a->sha256,'root'=>$a->root,'file_count'=>count($a->files)],
            'target'=>['cms'=>'VoilaVoila CMS','min_version'=>'1.1.0','api'=>1,'suggested_id'=>Archive::id($name)],
            'status'=>'manual','issues'=>[],'apis'=>[],'capabilities'=>[],'assets'=>[],'templates'=>[],'features'=>[],
            'guarantees'=>['source_executed'=>false,'source_modified'=>false,'remote_calls'=>false,'auto_installed'=>false,'auto_activated'=>false],
            'limits'=>['input_zip_bytes'=>Archive::ZIP_MAX,'input_expanded_bytes'=>Archive::EXPANDED_MAX,'native_package_files'=>400,'native_package_expanded_bytes'=>20971520]];
        $calls=[];$caps=[];
        foreach($a->files as $file=>$code) if(preg_match('/\.(php|inc|phtml)$/i',$file)) {
            try {self::inventory($code,$file,$calls,$caps);}
            catch(Unsupported $e) {self::issue($r,'analysis_limit',$e->detail,$e->detail,$file,$e->sourceLine);}
        }
        foreach($calls as $name=>$locations) $r['apis'][]=['name'=>$name,'count'=>$locations['count'],'locations'=>$locations['locations'],'native'=>self::MAP[$name][0] ?? '', 'note'=>self::MAP[$name][1] ?? ''];
        usort($r['apis'],fn($x,$y)=>$y['count']<=>$x['count'] ?: strcmp($x['name'],$y['name']));
        $r['capabilities']=array_keys($caps);
        if($a->ignored) self::issue($r,'ignored_metadata','Métadonnées et fichiers cachés ignorés.','Metadata and hidden files were ignored.','',0,'info');
        if(Archive::header($meta,'Requires Plugins')!=='') self::issue($r,'plugin_dependencies','L’extension déclare des dépendances WordPress.','Plugin declares WordPress plugin dependencies.',$entry);
        $program=['type'=>$type,'features'=>[],'templates'=>[],'styles'=>[],'assets'=>[]];
        if($type==='plugin') {
            try {$program['features']=Compiler::plugin($a->files[$entry]);}
            catch(Unsupported $e) {self::issue($r,'plugin_php','Code à porter : '.$e->detail,'Code requires porting: '.$e->detail,$entry,$e->sourceLine);}
            foreach($program['features'] as $f) $r['features'][]=['kind'=>$f['kind'],'tag'=>$f['tag']];
            if(!$program['features']) self::issue($r,'no_features','Aucune fonction native complète générée.','No complete native feature was generated.',$entry);
        } else {
            self::theme($a,$r,$program);
        }
        if(empty($r['issue_totals']['blocker'])) $r['status']='converted';
        $r['summary']=['blockers'=>$r['issue_totals']['blocker'] ?? 0,'warnings'=>$r['issue_totals']['warning'] ?? 0,'api_names'=>count($r['apis']),'native_features'=>count($r['features']),'compiled_templates'=>count($program['templates'])];
        return ['report'=>$r,'program'=>$program];
    }
    private static function inventory(string $code,string $file,array &$calls,array &$caps): void {
        if(!mb_check_encoding($code,'UTF-8')) {$caps['non_utf8_php']=true;return;}
        $s=new Syntax($code);
        foreach($s->tokens as $i=>$t) {
            $next=$s->tokens[$i+1] ?? [];$previous=$s->tokens[$i-1]['id'] ?? 0;
            if($t['id']===T_STRING && ($next['text'] ?? '')==='(' && !in_array($previous,[T_FUNCTION,T_OBJECT_OPERATOR,T_DOUBLE_COLON,T_NULLSAFE_OBJECT_OPERATOR,T_NEW],true)) {
                $name=strtolower($t['text']);
                if(!isset($calls[$name]) && count($calls)>=400) throw new Unsupported('API inventory limit: 400 names',$t['line']);
                $calls[$name] ??= ['count'=>0,'locations'=>[]];$calls[$name]['count']++;
                if(count($calls[$name]['locations'])<12) $calls[$name]['locations'][]=['file'=>$file,'line'=>$t['line']];
                foreach(['woocommerce'=>'woocommerce','wc_'=>'woocommerce','wp_remote_'=>'outbound_http','register_rest_route'=>'rest_api','wp_mail'=>'email','wp_schedule_'=>'cron','register_block_type'=>'blocks','add_menu_page'=>'admin_ui','wp_create_user'=>'accounts','wp_insert_user'=>'accounts','get_option'=>'wordpress_options','update_option'=>'wordpress_options','dbdelta'=>'database_schema'] as $needle=>$cap) if(str_starts_with($name,$needle)) $caps[$cap]=true;
            }
            if($t['id']===T_VARIABLE && $t['text']==='$wpdb') $caps['wordpress_database']=true;
            if($t['id']===T_VARIABLE && in_array($t['text'],['$_POST','$_GET','$_REQUEST','$_FILES','$_COOKIE','$_SESSION'],true)) $caps['direct_request_or_session_access']=true;
            if(in_array($t['id'],[T_INCLUDE,T_INCLUDE_ONCE,T_REQUIRE,T_REQUIRE_ONCE],true)) $caps['php_includes']=true;
            if($t['id']===T_EVAL) $caps['dynamic_php']=true;
            if($t['id']===T_CONSTANT_ENCAPSED_STRING && preg_match('/wp_ajax_|wp_rest|admin_post_/',Syntax::string($t['text']))) $caps['custom_endpoint']=true;
        }
    }
    private static function theme(Archive $a,array &$r,array &$p): void {
        if(Archive::header($a->files['style.css'],'Template')!=='') self::issue($r,'child_theme','Thème enfant : le thème parent doit aussi être porté.','Child theme: the parent theme must also be ported.','style.css');
        if(isset($a->files['templates/index.html']) || isset($a->files['block-templates/index.html'])) self::issue($r,'block_theme','Thème Gutenberg : les blocs et le moteur de rendu doivent être réécrits.','Block theme: block rendering and dynamic features require a native rewrite.','templates/index.html');
        if(!isset($a->files['index.php'])) self::issue($r,'no_classic_index','Aucun index.php classique à compiler.','No classic index.php template to compile.','index.php');
        foreach($a->files as $file=>$code) {
            if(preg_match('~^(?:index|home|front-page|single|singular|page|404|header(?:-[a-z0-9_-]+)?|footer(?:-[a-z0-9_-]+)?|sidebar(?:-[a-z0-9_-]+)?)\.php$~i',$file) || preg_match('~^(?:template-parts|parts)/[a-zA-Z0-9_/-]+\.php$~D',$file)) {
                try {$p['templates'][$file]=Compiler::template($code);$r['templates'][]=['file'=>$file,'status'=>'compiled'];}
                catch(Unsupported $e) {$r['templates'][]=['file'=>$file,'status'=>'manual'];self::issue($r,'template_php','Modèle à porter : '.$e->detail,'Template requires porting: '.$e->detail,$file,$e->sourceLine);}
            }
        }
        if($p['templates']) {
            try {Compiler::resolve($p,$a->files);}
            catch(Unsupported $e) {self::issue($r,'template_graph',$e->detail,$e->detail,'index.php',$e->sourceLine);}
        }
        if(isset($a->files['functions.php'])) {
            try {self::setup($a,$r,$p);}
            catch(Unsupported $e) {self::issue($r,'setup_limit',$e->detail,$e->detail,'functions.php',$e->sourceLine);}
        }
        $styles=['style.css'];foreach($p['styles'] as $s) $styles[]=$s;
        $p['styles']=array_values(array_unique($styles));
        foreach($a->files as $file=>$bytes) {
            $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
            if(!in_array($ext,['css','png','jpg','jpeg','webp','woff2'],true)) continue;
            $valid=true;
            if($ext==='css') $valid=mb_check_encoding($bytes,'UTF-8') && !str_contains($bytes,"\0");
            elseif($ext==='woff2') $valid=str_starts_with($bytes,'wOF2');
            else {
                $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                $valid=$mime===match($ext){'png'=>'image/png','jpg','jpeg'=>'image/jpeg','webp'=>'image/webp'};
            }
            if(!$valid) {self::issue($r,'invalid_asset','Ressource au format incohérent.','Asset format does not match its extension.',$file);continue;}
            $p['assets'][]=$file;$r['assets'][]=$file;
            if($ext==='css' && preg_match('~(?:url\(\s*[\x27\x22]?\s*(?:https?:)?//|@import\s+[\x27\x22](?:https?:)?//)~i',$bytes)) self::issue($r,'remote_css','Ressources CSS externes : contrôle manuel requis.','External CSS resources require manual review.',$file);
            if($ext==='css' && preg_match('~url\([^)]*\.(?:svg|gif|ttf|woff)(?:[?\x27\x22)]|$)~i',$bytes)) self::issue($r,'unsupported_asset','Le CSS utilise un format de ressource non pris en charge.','CSS references an unsupported asset format.',$file);
        }
        foreach($p['styles'] as $style) if(!in_array($style,$p['assets'],true)) self::issue($r,'missing_css','Feuille de style locale absente.','Local stylesheet is missing.',$style);
        foreach($a->files as $file=>$bytes) if(preg_match('/\.(php|html)$/i',$file) && preg_match('~\.(?:svg|gif|ttf|woff)[\x27\x22?)]~i',$bytes)) self::issue($r,'template_asset','Le modèle référence un format de ressource non repris.','Template references an asset format not copied by this version.',$file);
        self::issue($r,'theme_semantics','Le portage utilise les pages/articles, menus et textes du CMS ; il ne migre ni réglages, menus, widgets ou données WordPress.','The port uses CMS pages/posts, navigation and text; it does not migrate WordPress settings, menus, widgets or data.','',0,'warning');
        if(isset($a->files['theme.json'])) self::issue($r,'theme_json','theme.json est conservé comme référence, pas comme moteur de mise en page.','theme.json is retained as a reference, not used as a layout engine.','theme.json',0,'warning');
        self::walkExpressions($p['templates'],$a,$r);
    }
    private static function setup(Archive $a,array &$r,array &$p): void {
        $s=new Syntax($a->files['functions.php']);
        $allowed=['defined','function_exists','add_action','add_theme_support','register_nav_menus','register_nav_menu','load_theme_textdomain','get_template_directory','get_stylesheet_directory','get_template_directory_uri','get_stylesheet_directory_uri','get_stylesheet_uri','get_theme_file_uri','wp_enqueue_style','add_editor_style','wp_get_theme','get'];
        foreach($s->tokens as $i=>$t) {
            $previous=$s->tokens[$i-1]['id'] ?? 0;
            if(in_array($t['id'],[T_REQUIRE,T_REQUIRE_ONCE,T_INCLUDE,T_INCLUDE_ONCE,T_EVAL,T_NEW,T_VARIABLE],true)) {
                // Local variables in setup are common; globals/server inputs are not migrated.
                if($t['id']===T_VARIABLE && !in_array($t['text'],['$wpdb','$_POST','$_GET','$_REQUEST','$_FILES','$_COOKIE','$_SESSION'],true)) continue;
                self::issue($r,'theme_setup','Initialisation WordPress à réécrire : '.substr($t['text'],0,50),'WordPress setup requires rewriting: '.substr($t['text'],0,50),'functions.php',$t['line']);continue;
            }
            if($t['id']!==T_STRING || ($s->tokens[$i+1]['text'] ?? '')!=='(' || in_array($previous,[T_FUNCTION,T_OBJECT_OPERATOR,T_DOUBLE_COLON],true)) continue;
            $name=strtolower($t['text']);
            if(!in_array($name,$allowed,true)) {self::issue($r,'setup_api','Fonction d’initialisation non reprise : '.$name,'Setup function is not ported: '.$name,'functions.php',$t['line']);continue;}
            if($name==='add_action') {
                $s->at=$i+2;
                try {$hook=$s->expression();$value=Syntax::constant($hook);}catch(Unsupported) {$value=null;}
                if(!in_array($value,['after_setup_theme','wp_enqueue_scripts'],true)) self::issue($r,'setup_hook','Hook WordPress à porter.','WordPress setup hook requires porting.','functions.php',$t['line']);
            }
            if($name==='wp_enqueue_style') {
                $s->at=$i+2;
                try {$s->expression();$s->need(',');$href=$s->expression();$path=self::assetExpression($href);}
                catch(Unsupported) {$path=null;}
                if($path===null || !preg_match('~^[a-zA-Z0-9_./-]+\.css$~D',$path) || str_contains($path,'..')) self::issue($r,'enqueue_style','URL CSS calculée/externe à adapter.','Computed/external CSS URL requires adaptation.','functions.php',$t['line']);
                else $p['styles'][]=$path;
            }
        }
        self::issue($r,'setup_not_executed','functions.php n’est jamais exécuté ; les styles locaux identifiés sont chargés sans les conditions WordPress.','functions.php is never executed; identified local styles load without WordPress conditions.','functions.php',0,'warning');
    }
    private static function assetExpression(array $n): ?string {
        if($n['op']==='literal') return is_string($n['value'])?$n['value']:null;
        if($n['op']==='call') {
            if(in_array($n['name'],['get_template_directory_uri','get_stylesheet_directory_uri'],true)) return '';
            if($n['name']==='get_stylesheet_uri') return 'style.css';
            if($n['name']==='get_theme_file_uri') return (string)Syntax::constant($n['args'][0] ?? ['op'=>'literal','value'=>'']);
            return null;
        }
        if($n['op']==='concat') {$out='';foreach($n['parts'] as $part) {$v=self::assetExpression($part);if($v===null) return null;$out.=$v;}return ltrim($out,'/');}
        return null;
    }
    private static function walkExpressions(array $tree,Archive $a,array &$r): void {
        foreach($tree as $key=>$n) {
            if(!is_array($n)) continue;
            if(($n['op'] ?? '')==='literal' && is_string($n['value'])) {
                try {Syntax::safeHTML($n['value']);}
                catch(Unsupported $e) {self::issue($r,'active_expression','HTML actif dans une expression : adaptation manuelle.','Active HTML inside an expression requires manual adaptation.');}
            }
            if(($n['op'] ?? '')==='call') {
                $name=$n['name'];$args=$n['args'];
                $zero=['get_the_title','get_the_content','the_content','get_the_excerpt','the_excerpt','get_permalink','the_permalink','get_the_id','the_id','get_post_type','get_template_directory_uri','get_stylesheet_directory_uri','get_stylesheet_uri','have_posts','is_home','is_front_page','is_single','is_page','is_singular','is_404','is_search'];
                if(in_array($name,$zero,true) && $args) self::issue($r,'call_arguments','Arguments non pris en charge : '.$name,'Unsupported arguments: '.$name);
                if(in_array($name,['the_title'],true)) {
                    if(count($args)>2) self::issue($r,'title_arguments','Options the_title à adapter.','the_title options require adaptation.');
                    foreach($args as $arg) if(!is_string(Syntax::constant($arg))) self::issue($r,'title_wrapper','Encadrement de titre dynamique non pris en charge.','Dynamic title wrappers are unsupported.');
                }
                if(in_array($name,['get_the_date','the_date'],true) && (count($args)>1 || ($args && !is_string(Syntax::constant($args[0]))))) self::issue($r,'date_arguments','Format de date dynamique non pris en charge.','Dynamic date format is unsupported.');
                if(in_array($name,['home_url','site_url'],true) && (!in_array(Syntax::constant($args[0] ?? ['op'=>'literal','value'=>'']),['','/'],true) || count($args)>1)) self::issue($r,'wordpress_url','Une URL WordPress spécifique doit être adaptée.','A WordPress-specific URL requires adaptation.');
                if(in_array($name,['bloginfo','get_bloginfo'],true) && !in_array(Syntax::constant($args[0] ?? ['op'=>'literal','value'=>'name']),['name','description','charset','language','url','wpurl','stylesheet_url','template_url','stylesheet_directory','template_directory'],true)) self::issue($r,'bloginfo_field','Champ bloginfo non pris en charge.','Unsupported bloginfo field.');
                if($name==='get_theme_file_uri') {
                    $path=self::assetExpression($n);
                    if($path===null || !isset($a->files[$path]) || preg_match('~(?:^|/)\.\.?(/|$)|\.(?:php|html|js|svg|gif|woff|ttf)$~i',$path)) self::issue($r,'theme_uri','Ressource de thème absente ou non prise en charge.','Theme asset is missing or unsupported.');
                }
            }
            self::walkExpressions($n,$a,$r);
        }
    }
}
