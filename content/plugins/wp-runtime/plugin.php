<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
require_once __DIR__.'/FastCGI.php';
require_once __DIR__.'/Runtime.php';
require_once __DIR__.'/admin.php';
\VV\WordPress\Runtime::register($vv);
