<?php
declare(strict_types=1);
namespace VV\WordPress;
defined('VV_ROOT') || exit;
/** Official offline input pinned at packaging time; not a signature service. */
final class Bundled {
    public const WORDPRESS_VERSION='7.1.3';
    public const ZIP_SHA256='eb58a90ed55147f90f7bd55d0d3334960a69b7e8a4cc142c8ddfea8965218227';
    public const VERIFICATION_SHA256='66a611dfbd6d7b64e5764c3cf6ca8e667f407290455a94aacf636aca0c8d825c';
    public static function verified(string $zip,string $manifest): bool {
        return hash_equals(self::ZIP_SHA256,hash_file('sha256',$zip)) && hash_equals(self::VERIFICATION_SHA256,hash_file('sha256',$manifest));
    }
}
