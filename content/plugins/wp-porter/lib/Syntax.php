<?php
declare(strict_types=1);
namespace VV\WPPorter;
defined('VV_ROOT') || exit;

final class Unsupported extends \RuntimeException {
    public function __construct(public string $detail,public int $sourceLine) { parent::__construct($detail); }
}

/** Tiny restricted parser. It produces data; no PHP is evaluated. */
final class Syntax {
    public array $tokens=[]; public int $at=0; private int $expressionDepth=0; private int $atomDepth=0;
    public const CALLS=['__','_e','esc_html','esc_attr','esc_url','esc_html__','esc_attr__','esc_html_e','esc_attr_e','get_bloginfo','bloginfo','get_the_title','the_title','get_the_content','the_content','get_the_excerpt','the_excerpt','get_permalink','the_permalink','get_the_date','the_date','get_the_id','the_id','get_post_type','get_template_directory_uri','get_stylesheet_directory_uri','get_stylesheet_uri','get_theme_file_uri','home_url','site_url','is_home','is_front_page','is_single','is_page','is_singular','is_404','is_search','have_posts'];
    public function __construct(string $code) {
        if(strlen($code)>262144) throw new Unsupported('PHP analysis limit: 256 KiB per file',1);
        if(!mb_check_encoding($code,'UTF-8')) throw new Unsupported('UTF-8',1);
        $line=1;
        try {$rawTokens=token_get_all($code,TOKEN_PARSE);}
        catch(\CompileError $e) {throw new Unsupported('PHP syntax: '.$e->getMessage(),$e->getLine());}
        foreach($rawTokens as $t) {
            if(is_array($t)) { [$id,$text,$start]=$t; $line=$start; }
            else { $id=0; $text=$t; $start=$line; }
            if(!in_array($id,[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) $this->tokens[]=['id'=>$id,'text'=>$text,'line'=>$start];
            if(count($this->tokens)>30000) throw new Unsupported('PHP analysis limit: 30,000 significant tokens',1);
            $line=$start+substr_count($text,"\n");
        }
    }
    public function peek(int $offset=0): array { return $this->tokens[$this->at+$offset] ?? ['id'=>-1,'text'=>'','line'=>$this->tokens?end($this->tokens)['line']:1]; }
    public function take(): array { $t=$this->peek(); if($t['id']!==-1) $this->at++; return $t; }
    public function is(string|int $value): bool { $t=$this->peek(); return is_int($value)?$t['id']===$value:$t['text']===$value; }
    public function accept(string|int $value): bool { if($this->is($value)) {$this->take();return true;} return false; }
    public function need(string|int $value): array { if(!$this->is($value)) $this->bad('Expected '.(is_int($value)?token_name($value):$value)); return $this->take(); }
    public function bad(string $detail): never { throw new Unsupported($detail,$this->peek()['line']); }
    public static function string(string $raw): string {
        $inner=substr($raw,1,-1);
        if($raw[0]==="'") return str_replace(["\\\\","\\'"],["\\","'"],$inner);
        $inner=preg_replace_callback('~\\\\u\{([0-9a-fA-F]{1,6})\}~',function($m){$n=hexdec($m[1]);if($n>0x10ffff || ($n>=0xd800 && $n<=0xdfff)) throw new Unsupported('Unicode escape',1);return mb_chr($n,'UTF-8');},$inner);
        return preg_replace_callback('~\\\\(?:[nrtvef\\\\$\x22]|x[0-9a-fA-F]{1,2}|[0-7]{1,3})~',fn($m)=>stripcslashes($m[0]),$inner);
    }
    public function expression(): array {
        if(++$this->expressionDepth>32) throw new Unsupported('Expression nesting limit', $this->peek()['line']);
        try {
        $parts=[$this->atom()];
        while($this->accept('.')) $parts[]=$this->atom();
        return count($parts)===1?$parts[0]:['op'=>'concat','parts'=>$parts];
        } finally {$this->expressionDepth--;}
    }
    private function atom(): array {
        if(++$this->atomDepth>32) throw new Unsupported('Expression nesting limit',$this->peek()['line']);
        try {
        $t=$this->peek();
        if($this->accept('!')) return ['op'=>'not','value'=>$this->atom()];
        if($this->accept('(')) { $n=$this->expression();$this->need(')');return $n; }
        if($this->accept(T_CONSTANT_ENCAPSED_STRING)) return ['op'=>'literal','value'=>self::string($t['text'])];
        if($this->accept(T_LNUMBER)) return ['op'=>'literal','value'=>(int)$t['text']];
        if($this->accept(T_ARRAY)) { $this->need('(');return $this->literalArray(')'); }
        if($this->accept('[')) return $this->literalArray(']');
        if($this->accept(T_STRING)) {
            $name=strtolower($t['text']);
            if(in_array($name,['true','false','null'],true) && !$this->is('(')) return ['op'=>'literal','value'=>match($name){'true'=>true,'false'=>false,default=>null}];
            if(!in_array($name,self::CALLS,true)) throw new Unsupported($t['text'].'()',$t['line']);
            $args=$this->arguments();
            return ['op'=>'call','name'=>$name,'args'=>$args];
        }
        $this->bad('Expression: '.substr($t['text'],0,60));
        } finally {$this->atomDepth--;}
    }
    private function literalArray(string $end): array {
        $out=[];
        while(!$this->is($end)) {
            $key=$this->expression();
            if($key['op']!=='literal') $this->bad('Dynamic array');
            if($this->accept(T_DOUBLE_ARROW)) {
                $value=$this->expression(); if($value['op']!=='literal') $this->bad('Dynamic array value');
                if(!is_string($key['value']) && !is_int($key['value'])) $this->bad('Invalid array key');
                $out[$key['value']]=$value['value'];
            } else $out[]=$key['value'];
            if(!$this->accept(',')) break;
        }
        $this->need($end);return ['op'=>'literal','value'=>$out];
    }
    public function arguments(): array {
        $this->need('('); $args=[];
        while(!$this->is(')')) { $args[]=$this->expression();if(!$this->accept(',')) break; }
        $this->need(')');return $args;
    }
    public function term(): array {return $this->atom();}
    public function finish(): void { if(!$this->accept(';') && !$this->is(T_CLOSE_TAG) && !$this->is(-1)) $this->bad('Statement terminator'); }
    public function guard(): bool {
        $save=$this->at;
        // Recognize only conventional ABSPATH guards, never arbitrary source conditions.
        $flat=''; for($i=0;$i<18;$i++) $flat.=$this->peek($i)['text'];
        $patterns=["~^declare\\(strict_types=[01]\\);~i","~^defined\\(['\"]ABSPATH['\"]\\)\\|\\|(?:exit|die)(?:\\(\\))?;~i","~^if\\(!defined\\(['\"]ABSPATH['\"]\\)\\)(?:\\{)?(?:exit|die)(?:\\(\\))?;(?:\\})?~i"];
        foreach($patterns as $pattern) if(preg_match($pattern,$flat,$m)) {
            $consumed='';while(strlen($consumed)<strlen($m[0])) $consumed.=$this->take()['text'];
            if($consumed===$m[0]) return true; $this->at=$save;
        }
        return false;
    }
    public static function constant(array $node): mixed {
        if($node['op']==='literal') return $node['value'];
        if($node['op']==='concat') {
            $value='';foreach($node['parts'] as $p) { $s=self::constant($p);if(!is_scalar($s)) return null;$value.=(string)$s; }return $value;
        }
        if($node['op']==='call' && in_array($node['name'],['__','_e','esc_html__','esc_attr__','esc_html_e','esc_attr_e','esc_html','esc_attr','esc_url'],true)) {
            $v=self::constant($node['args'][0] ?? ['op'=>'literal','value'=>null]);
            if(!is_string($v)) return null;
            return str_starts_with($node['name'],'esc_')?htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'):$v;
        }
        return null;
    }
    public static function safeHTML(string $html,int $line=1): void {
        $decoded=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
        $decoded=preg_replace('/[\x00-\x1f\x7f]/','',$decoded);
        if(preg_match('~<\s*(?:script|iframe|object|embed|base|foreignObject)\b|\bon[a-z]+\s*=|(?:javascript|vbscript)\s*:|<\s*meta\b[^>]*\bhttp-equiv\s*=\s*[\x27\x22]?refresh\b~i',$decoded)) throw new Unsupported('Active HTML / HTML actif',$line);
    }
}
