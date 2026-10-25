<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
$vv->adminPage('pass',t('Connexion PASS','PASS connection'),function($app) {
    $d=$app->state['plugin_data']['pass-link'] ?? [];
    ?><section class="panel"><h2>PASS</h2><p><?=e(t('Affichez un lien vers votre billetterie PASS actuelle. Les événements, paiements, comptes clients et billets restent gérés par votre installation PASS.','Show a link to your current PASS ticketing site. Events, payments, customer accounts and tickets remain managed by your PASS installation.'))?></p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="plugin.save"><label><?=e(t('URL HTTPS de la billetterie','HTTPS ticketing URL'))?><input type="url" name="url" value="<?=e(val($d,'url'))?>" placeholder="https://voilavoila.fr/?passw=boutique" required></label><div class="two"><label><?=e(t('Libellé français','French label'))?><input name="label_fr" maxlength="80" value="<?=e(val($d,'label_fr','Voir les événements'))?>"></label><label><?=e(t('Libellé anglais','English label'))?><input name="label_en" maxlength="80" value="<?=e(val($d,'label_en','View events'))?>"></label></div><label class="checkbox"><input type="checkbox" name="profile" <?=!empty($d['profile'])?'checked':''?>><?=e(t('Afficher le bouton sur mon profil','Show the button on my profile'))?></label><button class="button primary"><?=e(t('Enregistrer la connexion','Save connection'))?></button></form><p class="hint"><?=e(t('Utilisez [pass] dans une page ou un article pour insérer ce bouton. Ce module ne déplace aucune donnée de la billetterie.','Use [pass] in a page or post to insert this button. This module does not move ticketing data.'))?></p></section><?php
},function($app,$p) {
    $url=\VV\Content::link(val($p,'url'));
    if(!str_starts_with($url,'https://')) throw new \VV\Failure(t('Indiquez une URL HTTPS valide.','Enter a valid HTTPS URL.'));
    $d=['url'=>$url,'label_fr'=>\VV\Content::text(val($p,'label_fr','Voir les événements'),80),'label_en'=>\VV\Content::text(val($p,'label_en','View events'),80),'profile'=>isset($p['profile'])];
    $app->store->change(function(&$s) use($d) {$s['plugin_data']['pass-link']=$d;\VV\Store::log($s,'pass_link_saved');});
});
$button=function() {
    $url=\VV\Content::link((string)vv_option('pass-link','url','')); if(!str_starts_with($url,'https://')) return '';
    $label=(string)vv_option('pass-link','label_'.lang(),t('Voir les événements','View events'));
    return '<a class="site-button pass-button" href="'.e($url).'" rel="noopener"><img src="'.e(url('assets/pass-logo.png')).'" alt="PASS">'.e($label).'</a>';
};
vv_shortcode('pass',$button);
vv_action('profile_after',function($app) use($button){if(vv_option('pass-link','profile',false))echo $button();});
