<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
vv_action('maintenance_completed_v1',function($app,array $event):void {
    $app->store->change(function(&$state)use($event){
        if(($state['plugin_data']['operations-example']['last']['id']??'')===$event['id'])return;
        $state['plugin_data']['operations-example']=['schema'=>1,'last'=>['id'=>$event['id'],'at'=>$event['at'],'duration_ms'=>$event['duration_ms']]];
    });
});
vv_action('admin_dashboard',function($app):void {
    $last=$app->state['plugin_data']['operations-example']['last']??null;
    echo '<section class="panel"><h2>'.e(t('Exemple SDK · Maintenance','SDK example · Maintenance')).'</h2><p>'.e($last?($last['at'].' / '.$last['duration_ms'].' ms'):t('Lancez une maintenance pour recevoir le premier événement.','Run maintenance to receive the first event.')).'</p></section>';
});
