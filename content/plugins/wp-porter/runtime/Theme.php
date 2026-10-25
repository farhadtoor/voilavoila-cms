<?php
declare(strict_types=1);
namespace VV\WPPorterRuntime;
defined('VV_ROOT') || exit;

/** Renders only generated JSON instructions. Never includes WordPress source. */
final class Theme {
    private int $cursor=0; private int $budget=50000; private int $depth=0;
    private ?array $current=null; private array $items=[]; private bool $head=false; private bool $foot=false;
    private array $program=[];
    public function __construct(private object $app,private array $context,private string $directory,private string $id) {
        $this->program=json_decode(file_get_contents($directory.'/templates.json'),true,64,JSON_THROW_ON_ERROR);
        $this->current=$context['current'] ?? null;
        $this->items=$this->current?[$this->current]:array_values($context['items'] ?? []);
    }
    public function render(): void {
        $p=$this->program['templates'];
        $kind=!empty($this->context['notFound'])?'404':($this->current?($this->current['type']==='page'?'page':'single'):'home');
        $choices=match($kind){'home'=>['front-page.php','home.php','index.php'],'single'=>['single.php','singular.php','index.php'],'page'=>['page.php','singular.php','index.php'],default=>['404.php','index.php']};
        $file='index.php';foreach($choices as $name) if(isset($p[$name])) {$file=$name;break;}
        $document=false;
        foreach($p as $nodes) if(str_contains(strtolower(json_encode($nodes,JSON_UNESCAPED_SLASHES)),'<html')) {$document=true;break;}
        if(!$document) {echo '<!doctype html><html lang="'.\e(\lang()).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';$this->head();echo '</head><body>';}
        $this->nodes($p[$file] ?? []);
        if(!$document) {$this->footer();echo '</body></html>';}
    }
    private function nodes(array $nodes): void {
        if(++$this->depth>32) throw new \RuntimeException('Ported theme template nesting limit.');
        try {foreach($nodes as $n) {
            if(--$this->budget<0) throw new \RuntimeException('Ported theme render budget exceeded.');
            switch($n['op']) {
                case 'text': $this->text($n['value']);break;
                case 'out': [$value,$safe]=$this->value($n['value']);echo $safe?(string)$value:\e($value);break;
                case 'part': $this->nodes($this->program['templates'][$n['target']] ?? []);break;
                case 'if': [$v]=$this->value($n['condition']);$this->nodes($v?$n['yes']:$n['no']);break;
                case 'loop':
                    while($this->cursor<count($this->items)) {
                        $this->current=$this->items[$this->cursor++];$this->nodes($n['body']);
                    } break;
                case 'native':$this->native($n['name'],$n['args']);break;
                default:throw new \RuntimeException('Unsupported generated instruction.');
            }
        }} finally {$this->depth--;}
    }
    private function text(string $text): void {
        // Static asset attributes are made relative to the native theme, not the page URL.
        $text=preg_replace_callback('~\b(src|href)=("|\x27)([^"\x27]+)\2~i',function($m) {
            $path=$m[3];return in_array($path,$this->program['assets'] ?? [],true)?$m[1].'='.$m[2].\e($this->asset($path)).$m[2]:$m[0];
        },$text);
        $text=preg_replace_callback('~</head\s*>~i',function($m){ob_start();$this->head();return ob_get_clean().$m[0];},$text);
        $text=preg_replace_callback('~</body\s*>~i',function($m){ob_start();$this->footer();return ob_get_clean().$m[0];},$text);
        echo $text;
    }
    private function asset(string $path=''): string {return \url('content/themes/'.$this->id.($path!==''?'/'.ltrim($path,'/') : ''));}
    private function head(): void {
        if($this->head) return;$this->head=true;
        echo '<meta name="generator" content="VoilaVoila CMS">';
        foreach($this->program['styles'] ?? ['style.css'] as $css) echo '<link rel="stylesheet" href="'.\e($this->asset($css)).'">';
        \vv_do('head',$this->app,$this->context['current'] ?? null);
    }
    private function footer(): void {if(!$this->foot) {$this->foot=true;\vv_do('footer',$this->app);}}
    private function value(array $n): array {
        if($n['op']==='literal') return [$n['value'],is_string($n['value'])];
        if($n['op']==='not') {[$v]=$this->value($n['value']);return [!$v,false];}
        if($n['op']==='concat') {
            $value='';foreach($n['parts'] as $part){[$v,$safe]=$this->value($part);$value.=$safe?(string)$v:\e($v);}return [$value,true];
        }
        if($n['op']!=='call') throw new \RuntimeException('Unsupported generated expression.');
        $name=$n['name'];$args=[];foreach($n['args'] as $arg) {[$v]=$this->value($arg);$args[]=$v;}
        $c=$this->current;
        if(in_array($name,['__','_e'],true)) return [(string)($args[0] ?? ''),true];
        if(in_array($name,['esc_html','esc_attr','esc_url','esc_html__','esc_attr__','esc_html_e','esc_attr_e'],true)) return [(string)($args[0] ?? ''),false];
        if(in_array($name,['get_bloginfo','bloginfo'],true)) {
            $field=(string)($args[0] ?? 'name');
            return [match($field){'name'=>$this->app->state['settings']['title'],'description'=>$this->app->state['settings']['tagline'],'charset'=>'UTF-8','language'=>\lang(),'url','wpurl'=>\url('index.php'),'stylesheet_url'=>$this->asset('style.css'),'template_url','stylesheet_directory','template_directory'=>$this->asset(),default=>''},false];
        }
        if(in_array($name,['get_the_title','the_title'],true)) {
            $title=$c?\VV\Content::field($c,'title'):'';
            if($name==='the_title' && $args) return [(string)($args[0] ?? '').\e($title).(string)($args[1] ?? ''),true];
            return [$title,false];
        }
        if(in_array($name,['get_the_content','the_content'],true)) return [$c?$this->app->body($c):'',true];
        if(in_array($name,['get_the_excerpt','the_excerpt'],true)) return [$c?\VV\Content::field($c,'excerpt'):'',false];
        if(in_array($name,['get_permalink','the_permalink'],true)) return [$c?\VV\Content::url($c):\url('index.php'),false];
        if(in_array($name,['get_the_date','the_date'],true)) return [$c?gmdate((string)($args[0] ?? 'Y-m-d'),(int)$c['publish_at']):'',false];
        if(in_array($name,['get_the_id','the_id'],true)) return [$c['id'] ?? '',false];
        if($name==='get_post_type') return [$c['type'] ?? 'post',false];
        if(in_array($name,['get_template_directory_uri','get_stylesheet_directory_uri'],true)) return [$this->asset(),false];
        if($name==='get_stylesheet_uri') return [$this->asset('style.css'),false];
        if($name==='get_theme_file_uri') return [$this->asset((string)($args[0] ?? '')),false];
        if(in_array($name,['home_url','site_url'],true)) return [\url('index.php'),false];
        if($name==='have_posts') return [$this->cursor<count($this->items),false];
        return [match($name){'is_home','is_front_page'=>!$this->context['current'] && empty($this->context['notFound']),'is_single'=>($this->context['current']['type'] ?? '')==='post','is_page'=>($this->context['current']['type'] ?? '')==='page','is_singular'=>!empty($this->context['current']),'is_404'=>!empty($this->context['notFound']),'is_search'=>!empty($this->context['query']),default=>false},false];
    }
    private function native(string $name,array $nodes): void {
        $args=[];foreach($nodes as $node){[$value]=$this->value($node);$args[]=$value;}
        if($name==='wp_head') {$this->head();return;}
        if($name==='wp_footer') {$this->footer();return;}
        if(in_array($name,['the_post','wp_body_open'],true)) return;
        if($name==='language_attributes') {echo 'lang="'.\e(\lang()).'"';return;}
        if(in_array($name,['body_class','post_class'],true)) {
            $base=$name==='body_class'?($this->context['current']?($this->context['current']['type'].' single'):'home blog'):'post hentry '.($this->current['type'] ?? 'post');
            $extra=$args[0] ?? '';if(is_array($extra)) $extra=implode(' ',$extra);
            echo 'class="'.\e($base.' '.preg_replace('/[^a-zA-Z0-9_ -]/','',(string)$extra)).'"';return;
        }
        if($name==='wp_nav_menu') {
            $options=is_array($args[0] ?? null)?$args[0]:[];
            $class=preg_replace('/[^a-zA-Z0-9_ -]/','',(string)($options['menu_class'] ?? 'menu'));
            $pages=array_filter($this->app->state['contents'],fn($c)=>$c['type']==='page' && !empty($c['menu']) && \VV\Content::visible($c));
            uasort($pages,fn($a,$b)=>($a['order']??10)<=>($b['order']??10));
            echo '<nav aria-label="'.\e(\t('Navigation','Navigation')).'"><ul class="'.\e($class).'">';
            foreach($pages as $c) echo '<li><a href="'.\e(\VV\Content::url($c)).'">'.\e(\VV\Content::field($c,'title')).'</a></li>';
            echo '</ul></nav>';return;
        }
        if($name==='get_search_form') echo '<form method="get" action="'.\e(\url('index.php')).'" class="search-form"><label>'.\e(\t('Rechercher','Search')).'<input name="q" value="'.\e($this->context['query'] ?? '').'"></label><input type="hidden" name="lang" value="'.\e(\lang()).'"><button>'.\e(\t('Rechercher','Search')).'</button></form>';
    }
}
