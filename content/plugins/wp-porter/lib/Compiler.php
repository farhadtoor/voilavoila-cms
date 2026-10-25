<?php
declare(strict_types=1);
namespace VV\WPPorter;
defined('VV_ROOT') || exit;

final class Compiler {
    public static function template(string $source): array {
        $s=new Syntax($source); $nodes=self::nodes($s,[],0);
        if(!$s->is(-1)) $s->bad('Unexpected template end');
        return $nodes;
    }
    private static function nodes(Syntax $s,array $stops,int $depth=0): array {
        if($depth>32) $s->bad('Template nesting limit');
        $out=[];
        while(!$s->is(-1)) {
            foreach($stops as $stop) if($s->is($stop)) return $out;
            $t=$s->peek();
            if($s->accept(T_OPEN_TAG) || $s->accept(T_CLOSE_TAG) || $s->accept(';')) continue;
            if($s->accept(T_INLINE_HTML)) { Syntax::safeHTML($t['text'],$t['line']);$out[]=['op'=>'text','value'=>$t['text']];continue; }
            if($s->guard()) continue;
            if($s->accept(T_OPEN_TAG_WITH_ECHO) || $s->accept(T_ECHO)) {
                $value=$s->expression(); self::checkLiteralHTML($value,$t['line']);
                $out[]=['op'=>'out','value'=>$value];$s->finish();continue;
            }
            if($s->accept(T_IF)) {
                $s->need('(');$condition=$s->expression();$s->need(')');
                $brace=$s->accept('{');if(!$brace) $s->need(':');
                $yes=self::nodes($s,$brace?['}']:[T_ELSE,T_ENDIF],$depth+1);
                if($brace) $s->need('}');
                $no=[];
                if($s->accept(T_ELSE)) {
                    if($brace) $s->need('{');else $s->need(':');
                    $no=self::nodes($s,$brace?['}']:[T_ENDIF],$depth+1);
                    if($brace) $s->need('}');
                }
                if(!$brace) {$s->need(T_ENDIF);$s->accept(';');}
                $out[]=['op'=>'if','condition'=>$condition,'yes'=>$yes,'no'=>$no];continue;
            }
            if($s->accept(T_WHILE)) {
                $s->need('(');$condition=$s->expression();$s->need(')');
                if($condition!==['op'=>'call','name'=>'have_posts','args'=>[]]) $s->bad('Only while(have_posts()) is supported');
                $brace=$s->accept('{');if(!$brace) $s->need(':');
                $body=self::nodes($s,$brace?['}']:[T_ENDWHILE],$depth+1);
                if($brace) $s->need('}');else {$s->need(T_ENDWHILE);$s->accept(';');}
                $out[]=['op'=>'loop','body'=>$body];continue;
            }
            if($s->accept(T_STRING)) {
                $name=strtolower($t['text']); $args=$s->arguments(); $s->finish();
                if(in_array($name,['get_header','get_footer','get_sidebar','get_template_part'],true)) {
                    $out[]=self::part($name,$args,$t['line']);continue;
                }
                if(in_array($name,['wp_head','wp_footer','wp_body_open','the_post','language_attributes','body_class','post_class','wp_nav_menu','get_search_form'],true)) {
                    if($name==='the_post' && $args) throw new Unsupported('the_post arguments',$t['line']);
                    if(in_array($name,['body_class','post_class','wp_nav_menu'],true) && $args && ($args[0]['op']!=='literal')) throw new Unsupported('Dynamic '.$name,$t['line']);
                    $out[]=['op'=>'native','name'=>$name,'args'=>$args];continue;
                }
                if(in_array($name,['bloginfo','the_title','the_content','the_excerpt','the_permalink','the_date','the_id','_e','esc_html_e','esc_attr_e'],true)) {
                    $node=['op'=>'call','name'=>$name,'args'=>$args];self::checkLiteralHTML($node,$t['line']);
                    $out[]=['op'=>'out','value'=>$node];continue;
                }
                throw new Unsupported($t['text'].'()',$t['line']);
            }
            $s->bad('PHP: '.substr($t['text'],0,70));
        }
        return $out;
    }
    private static function checkLiteralHTML(array $node,int $line): void {
        $value=Syntax::constant($node);if(is_array($value)) throw new Unsupported('Array output',$line);if(is_string($value)) Syntax::safeHTML($value,$line);
    }
    private static function part(string $name,array $args,int $line): array {
        foreach($args as $a) if($a['op']!=='literal' || !is_string($a['value'])) throw new Unsupported('Dynamic '.$name,$line);
        if($name==='get_template_part') {
            $slug=$args[0]['value'] ?? '';$suffix=$args[1]['value'] ?? '';
            $candidates=$suffix!==''?[$slug.'-'.$suffix.'.php',$slug.'.php']:[$slug.'.php'];
        } else {
            $slug=substr($name,4);$suffix=$args[0]['value'] ?? '';
            $candidates=$suffix!==''?[$slug.'-'.$suffix.'.php',$slug.'.php']:[$slug.'.php'];
        }
        foreach($candidates as $p) if(!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\.php$~D',$p)) throw new Unsupported('Template path',$line);
        return ['op'=>'part','candidates'=>$candidates,'line'=>$line];
    }
    /** Only constant callbacks and registrations are emitted as executable native code. */
    public static function plugin(string $source): array {
        $s=new Syntax($source);$functions=[];$registrations=[];
        while(!$s->is(-1)) {
            $t=$s->peek();
            if($s->accept(T_OPEN_TAG) || $s->accept(T_CLOSE_TAG) || $s->accept(';')) continue;
            if($s->is(T_INLINE_HTML) && trim($t['text'])==='') {$s->take();continue;}
            if($s->guard()) continue;
            if($s->accept(T_FUNCTION)) {
                $name=$s->need(T_STRING)['text'];
                if(isset($functions[strtolower($name)])) $s->bad('Duplicate callback');
                $functions[strtolower($name)]=self::constantBody($s);continue;
            }
            $name=strtolower($s->need(T_STRING)['text']);
            if(!in_array($name,['add_shortcode','add_action','add_filter'],true)) throw new Unsupported($name.'()',$t['line']);
            $s->need('(');$tag=$s->expression();$s->need(',');
            if($tag['op']!=='literal' || !is_string($tag['value'])) $s->bad('Dynamic registration name');
            if($s->accept(T_FUNCTION)) $callback=self::constantBody($s);
            else {
                $ref=$s->expression();
                if($ref['op']!=='literal' || !is_string($ref['value'])) $s->bad('Dynamic callback');
                $callback=['ref'=>strtolower($ref['value'])];
            }
            // Optional default priority / argument count; anything else needs an explicit port.
            $extra=[];while($s->accept(',')) $extra[]=$s->expression();
            $s->need(')');$s->finish();
            foreach($extra as $a) if($a['op']!=='literal' || !is_int($a['value'])) $s->bad('Dynamic priority');
            if($name==='add_shortcode' && ($extra || !preg_match('/^[a-z][a-z0-9_-]{0,30}$/D',$tag['value']))) throw new Unsupported('Shortcode arguments or name',$t['line']);
            if($name==='add_action' && $tag['value']!=='wp_footer') throw new Unsupported('Hook '.$tag['value'],$t['line']);
            if($name==='add_filter' && $tag['value']!=='the_content') throw new Unsupported('Filter '.$tag['value'],$t['line']);
            if(count($extra)>2 || isset($extra[1]) && $extra[1]['value']!==($name==='add_filter'?1:0)) throw new Unsupported('Callback arguments',$t['line']);
            $registrations[]=['kind'=>match($name){'add_shortcode'=>'shortcode','add_filter'=>'filter-content',default=>'footer'},'tag'=>$tag['value'],'callback'=>$callback,'priority'=>$extra[0]['value'] ?? 10,'line'=>$t['line']];
        }
        if(!$registrations) throw new Unsupported('No supported shortcode/footer registration',1);
        $features=[];$tags=[];
        foreach($registrations as $r) {
            $callback=$r['callback'];
            if(isset($callback['ref'])) {
                if(!isset($functions[$callback['ref']])) throw new Unsupported('Callback '.$callback['ref'].' not found',$r['line']);
                $callback=$functions[$callback['ref']];
            }
            $required=$r['kind']==='footer'?'echo':'return';
            if($callback['mode']!==$required) throw new Unsupported('Callback must '.$required.' a constant',$r['line']);
            if($callback['parameters']!==($r['kind']==='filter-content'?1:0)) throw new Unsupported('Callback parameter count',$r['line']);
            if($r['kind']==='shortcode' && isset($tags[$r['tag']])) throw new Unsupported('Duplicate shortcode',$r['line']);
            $tags[$r['tag']]=true;
            $features[]=['kind'=>$r['kind'],'tag'=>$r['tag'],'html'=>$callback['html'],'parts'=>$callback['parts'],'priority'=>$r['priority']];
        }
        return $features;
    }
    private static function constantBody(Syntax $s): array {
        $s->need('(');$parameter='';if($s->is(T_VARIABLE)) $parameter=$s->take()['text'];$s->need(')');$s->need('{');
        $mode=$s->accept(T_RETURN)?'return':($s->accept(T_ECHO)?'echo':'');
        if($mode==='') $s->bad('Callback must return/echo one constant string');
        $line=$s->peek()['line'];$parts=[];$slots=0;$html='';
        do {
            if($s->is(T_VARIABLE)) {
                $var=$s->take()['text'];if($parameter==='' || $var!==$parameter || ++$slots>1) throw new Unsupported('Dynamic callback variable',$line);
                $parts[]=['slot'=>true];
            } else {
                $value=Syntax::constant($s->term());
                if(!is_string($value)) throw new Unsupported('Dynamic callback output',$line);
                Syntax::safeHTML($value,$line);$parts[]=['text'=>$value];$html.=$value;
            }
        } while($s->accept('.'));
        $s->need(';');$s->need('}');
        return ['mode'=>$mode,'html'=>$html,'parts'=>$parts,'parameters'=>$parameter===''?0:1];
    }
    public static function resolve(array &$program,array $files): void {
        $edges=[];
        foreach($program['templates'] as $name=>&$nodes) {
            $edges[$name]=[];
            self::resolveNodes($nodes,$program['templates'],$edges[$name]);
        } unset($nodes);
        $visited=[];$active=[];
        $visit=function(string $name) use(&$visit,&$visited,&$active,$edges) {
            if(isset($active[$name])) throw new Unsupported('Recursive template include: '.$name,1);
            if(isset($visited[$name])) return;
            $active[$name]=true;foreach($edges[$name] ?? [] as $next) $visit($next);
            unset($active[$name]);$visited[$name]=true;
        };
        foreach(array_keys($edges) as $name) $visit($name);
    }
    private static function resolveNodes(array &$nodes,array $templates,array &$edges): void {
        foreach($nodes as &$n) {
            if($n['op']==='part') {
                $target='';foreach($n['candidates'] as $p) if(isset($templates[$p])) {$target=$p;break;}
                if($target==='') throw new Unsupported('Missing or unsupported template: '.implode(', ',$n['candidates']),$n['line']);
                $n=['op'=>'part','target'=>$target];$edges[]=$target;
            }
            if($n['op']==='if') {self::resolveNodes($n['yes'],$templates,$edges);self::resolveNodes($n['no'],$templates,$edges);}
            if($n['op']==='loop') self::resolveNodes($n['body'],$templates,$edges);
        } unset($n);
    }
}
