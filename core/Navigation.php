<?php
declare(strict_types=1);
namespace VV;
defined('VV_ROOT') || exit;

final class Navigation {
    public static function items(App $app): array {
        $rows=array_filter($app->state['contents'],fn($c)=>$c['type']==='page'&&!empty($c['menu'])&&Content::visible($c));
        uasort($rows,fn($a,$b)=>$a['order']<=>$b['order']?:strcmp($a['id'],$b['id']));return array_values($rows);
    }
    public static function label(array $content): string {return Content::field($content,'nav_label')?:Content::field($content,'title');}
    public static function save(Store $store,array $input): void {
        $rows=$input['navigation']??[];if(!is_array($rows)||count($rows)>1000)throw new Failure('Navigation invalide.');
        $store->change(static function(&$s)use($rows):void {
            foreach($rows as $id=>$row){
                if(!is_array($row)||!isset($s['contents'][$id])||$s['contents'][$id]['type']!=='page')throw new Failure('Page de navigation absente.',409);
                $c=&$s['contents'][$id];if((string)$c['version']!==\val($row,'version'))throw new Failure('Une page a changé. Rechargez la navigation.',409);
                if(!preg_match('/^\d{1,3}$/D',\val($row,'order')))throw new Failure('Ordre compris entre 0 et 999.');
                $c['menu']=isset($row['menu']);$c['order']=(int)$row['order'];
                foreach(['fr','en']as$l)$c['nav_label_'.$l]=Content::text(\val($row,'label_'.$l),80);
                $c['version']++;$c['updated_at']=gmdate('c');unset($c);
            }
            Store::log($s,'navigation_saved');
        });
    }
}
