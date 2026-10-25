<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class App {
    public array $state; public array $hooks=[]; public array $shortcodes=[]; public array $adminPages=[];
    public function __construct(public Store $store) { $this->refresh(); }
    public function refresh(): void { $this->state=$this->store->read(); }
    public static function compatible(array $m): bool {
        foreach(['requires_cms'=>VV_VERSION,'requires_php'=>PHP_VERSION] as $key=>$actual) {
            if(isset($m[$key]) && (!is_string($m[$key]) || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$m[$key]) || version_compare($actual,$m[$key],'<'))) return false;
        }
        return true;
    }
    public function packages(string $type): array {
        $rows=[]; $folder=$type==='theme'?'themes':'plugins';
        foreach(glob(VV_ROOT.'/content/'.$folder.'/*/cms.json') as $file) {
            $m=json_decode(file_get_contents($file),true);
            $id=basename(dirname($file));
            if(is_array($m) && ($m['id'] ?? '')===$id && ($m['type'] ?? '')===$type && ($m['api'] ?? 0)===1 && preg_match('/^[a-z][a-z0-9-]{1,50}$/D',$id)) $rows[$id]=$m;
        }
        return $rows;
    }
    public function bootPlugins(bool $safe=false): void {
        if($safe) return;
        $vv=$this;
        $packages=$this->packages('plugin');
        foreach($this->state['plugins'] as $id) {
            if(!isset($packages[$id])) continue;
            if(!self::compatible($packages[$id])) {
                $this->store->change(function(&$s) use($id) { $s['plugins']=array_values(array_diff($s['plugins'],[$id])); Store::log($s,'plugin_incompatible',$id); });
                continue;
            }
            $hooks=$this->hooks; $short=$this->shortcodes; $pages=$this->adminPages;
            try { require VV_ROOT.'/content/plugins/'.$id.'/plugin.php'; }
            catch(\Throwable $err) {
                $this->hooks=$hooks; $this->shortcodes=$short; $this->adminPages=$pages;
                $this->store->change(function(&$s) use($id) { $s['plugins']=array_values(array_diff($s['plugins'],[$id])); Store::log($s,'plugin_disabled_error',$id); });
                Logger::error('plugin_'.$id,$err->getMessage(),$err->getFile(),$err->getLine());
            }
        }
        $this->refresh(); \vv_do('init',$this);
    }
    public function adminPage(string $id,string $label,callable $render,?callable $save=null): void {
        if(!preg_match('/^[a-z][a-z0-9-]{1,50}$/D',$id)) throw new Failure('Menu extension invalide.');
        $this->adminPages['plugin-'.$id]=['label'=>$label,'render'=>$render,'save'=>$save];
    }
    public function body(array $content): string {
        $html=Content::field($content,'body');
        $html=preg_replace_callback('/\[([a-z][a-z0-9_-]{0,30})\]/',function($m) { return isset($this->shortcodes[$m[1]]) ? ($this->shortcodes[$m[1]])() : \e($m[0]); },$html);
        return \vv_filter('content_html',$html,$content);
    }
    public function publicItems(string $type='post',string $search=''): array {
        $rows=array_filter($this->state['contents'],function($c) use($type,$search) {
            return $c['type']===$type && Content::visible($c) && ($search==='' || mb_stripos(strip_tags(implode(' ',[$c['title_fr'],$c['title_en'],$c['body_fr'],$c['body_en'],implode(' ',$c['tags'])])),$search)!==false);
        });
        usort($rows,fn($a,$b)=>(int)$b['pinned']<=>(int)$a['pinned'] ?: $b['publish_at']<=>$a['publish_at']); return $rows;
    }
    public function theme(): string {
        $id=$this->state['settings']['theme'] ?? 'profil-or';
        $m=$this->packages('theme')[$id] ?? null;
        return $m && self::compatible($m) ? $id : 'profil-or';
    }
    public function template(string $kind,array $context=[]): void {
        $folder=VV_ROOT.'/content/themes/'.$this->theme();
        $file=is_file($folder.'/'.$kind.'.php') ? $folder.'/'.$kind.'.php' : $folder.'/index.php';
        if(!is_file($file)) $file=VV_ROOT.'/content/themes/profil-or/index.php';
        $app=$this; extract($context,EXTR_SKIP); require $file;
    }
    public static function seed(string $name,string $email,string $password): array {
        $id=bin2hex(random_bytes(12)); $page=bin2hex(random_bytes(12)); $now=gmdate('c');
        $common=['status'=>'published','publish_at'=>time(),'created_at'=>$now,'updated_at'=>$now,'tags'=>[],'pinned'=>false,'menu'=>false,'order'=>10,'cover'=>'','version'=>1,'revisions'=>[],'excerpt_fr'=>'','excerpt_en'=>''];
        return ['owner'=>['email'=>$email,'hash'=>Security::hash($password),'epoch'=>bin2hex(random_bytes(16))],
            'settings'=>['title'=>$name,'tagline'=>'Un espace à votre image.','bio'=>'Présentez votre activité, partagez vos idées et publiez vos projets.','location'=>'','email_public'=>'','socials'=>'','language'=>'fr','theme'=>'profil-or','avatar'=>'','cover'=>'','canonical'=>'','footer'=>'Site créé avec VoilaVoila CMS','maintenance'=>false,'noindex'=>false,'home_page'=>'','per_page'=>8],
            'contents'=>[
                $id=>array_merge($common,['id'=>$id,'type'=>'post','slug'=>'bienvenue','title_fr'=>'Votre espace commence ici.','title_en'=>'Your space starts here.','body_fr'=>'<p>Bienvenue dans votre CMS personnel. Modifiez cette publication, ajoutez une image et partagez votre premier projet.</p><h2>Tout ce qui compte, à portée de main.</h2><p>Pages, articles, médias, thèmes et extensions : vous gardez le contrôle.</p>','body_en'=>'<p>Welcome to your personal CMS. Edit this post, upload an image and share your first project.</p><h2>Everything you need, close at hand.</h2><p>Pages, posts, media, themes and plugins: you stay in control.</p>','pinned'=>true,'tags'=>['CMS']]),
                $page=>array_merge($common,['id'=>$page,'type'=>'page','slug'=>'a-propos','title_fr'=>'À propos','title_en'=>'About','body_fr'=>'<h2>Une page à votre image.</h2><p>Présentez votre parcours, votre activité et les façons de vous contacter.</p>','body_en'=>'<h2>A page of your own.</h2><p>Introduce your background, your work and how to contact you.</p>','menu'=>true])],
            'plugins'=>['atelier','seo'], 'plugin_data'=>[]];
    }
}
