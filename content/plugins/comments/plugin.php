<?php
declare(strict_types=1);
defined('VV_ROOT') || exit;
if(version_compare(VV_VERSION,'1.2.0','<')) throw new \VV\Failure('Commentaires nécessite VoilaVoila CMS 1.2.0 ou ultérieur.',503);
require_once __DIR__.'/Comments.php';
\VV\Comments::register($vv);
