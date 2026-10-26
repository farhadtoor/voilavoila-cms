<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
require_once __DIR__.'/Admin.php';
$vv->adminPage('otp',t('Application OTP','Authenticator OTP'),[\VV\Authentication\OtpAdmin::class,'render'],[\VV\Authentication\OtpAdmin::class,'save']);
