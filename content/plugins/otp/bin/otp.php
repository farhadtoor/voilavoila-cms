<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,4).'/core/bootstrap.php';
defined('VV_ROOT') || exit;
$command=$argv[1]??'status';
if($command==='status'){echo json_encode(['enabled'=>isset($vv->state['owner']['factor']),'recovery_remaining'=>count($vv->state['owner']['factor']['recovery']??[])],JSON_PRETTY_PRINT).PHP_EOL;exit;}
if($command!=='disable'||!in_array('--confirm=DISABLE-OTP',$argv,true))throw new \VV\Failure('Use status or disable --confirm=DISABLE-OTP. Local server access is required.');
$vv->store->change(function(&$s){unset($s['owner']['factor']);$s['owner']['epoch']=bin2hex(random_bytes(16));\VV\Store::log($s,'otp_disabled_local');});
echo "OTP disabled locally; all owner and bridge sessions revoked.\n";
