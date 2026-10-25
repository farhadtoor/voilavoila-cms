<?php
declare(strict_types=1);
namespace VV\WPPorter;
defined('VV_ROOT') || exit;

final class Fixtures {
    public static function all(): array {
        return [
            'plugin-static'=>['demo-static/demo.php'=><<<'WP'
<?php
/**
 * Plugin Name: Demo Static Shortcodes
 * Version: 1.2.3
 * Author: Example Author
 * License: MIT
 */
defined('ABSPATH') || exit;
function demo_hello() { return '<p>Hello from a native shortcode.</p>'; }
add_shortcode('hello', 'demo_hello');
add_shortcode('greeting', function() { return __('Bonjour depuis le shortcode.', 'demo'); });
add_action('wp_footer', function() { echo '<p>Native footer notice.</p>'; }, 12, 0);
function demo_append($content) { return $content . '<p>Content filter notice.</p>'; }
add_filter('the_content', 'demo_append', 10, 1);
WP],
            'theme-classic'=>[
                'demo-theme/style.css'=>"/*\nTheme Name: Demo Classic Theme\nVersion: 1.0.0\nAuthor: Example Author\nLicense: MIT\n*/\nbody{background:#12171d;color:#eee;font:17px/1.7 system-ui;margin:0}main,header,footer{max-width:960px;margin:auto;padding:24px}a{color:#dfbe81}article{border-bottom:1px solid #444;padding:20px 0}nav ul{display:flex;gap:20px;list-style:none;padding:0}",
                'demo-theme/functions.php'=><<<'WP'
<?php
function demo_theme_assets() { wp_enqueue_style('demo-theme', get_stylesheet_uri()); }
add_action('wp_enqueue_scripts', 'demo_theme_assets');
WP,
                'demo-theme/header.php'=><<<'WP'
<!doctype html><html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php bloginfo('name'); ?></title><?php wp_head(); ?></head><body <?php body_class(); ?>><header><a href="<?php echo home_url('/'); ?>"><?php bloginfo('name'); ?></a><p><?php bloginfo('description'); ?></p><?php wp_nav_menu(array('menu_class'=>'menu')); ?></header>
WP,
                'demo-theme/index.php'=><<<'WP'
<?php get_header(); ?><main><?php if(have_posts()): while(have_posts()): the_post(); ?><article <?php post_class(); ?> id="post-<?php the_ID(); ?>"><h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1><p><?php echo get_the_date('Y-m-d'); ?></p><?php the_content(); ?></article><?php endwhile; else: ?><p>No posts.</p><?php endif; ?></main><?php get_footer(); ?>
WP,
                'demo-theme/page.php'=><<<'WP'
<?php get_header(); ?><main><h1><?php the_title(); ?></h1><?php the_content(); ?></main><?php get_footer(); ?>
WP,
                'demo-theme/404.php'=><<<'WP'
<?php get_header(); ?><main><h1>404</h1><p>Page unavailable.</p></main><?php get_footer(); ?>
WP,
                'demo-theme/footer.php'=><<<'WP'
<footer><p>Classic theme converted to native templates.</p></footer><?php wp_footer(); ?></body></html>
WP],
            'plugin-dynamic'=>['business/business.php'=><<<'WP'
<?php
/* Plugin Name: Business Dependency Example
Version: 1.0.0
*/
defined('ABSPATH') || exit;
function business_orders() { global $wpdb; return $wpdb->get_results('SELECT * FROM orders'); }
add_action('init', 'business_orders');
WP],
            'theme-block'=>[
                'block-demo/style.css'=>"/* Theme Name: Block Theme Example\nVersion: 1.0.0\n*/",
                'block-demo/theme.json'=>'{"version":3,"settings":{"color":{"palette":[{"name":"Gold","slug":"gold","color":"#c9a963"}]}}}',
                'block-demo/templates/index.html'=>'<!-- wp:site-title /--><!-- wp:query --><!-- wp:post-template --><!-- wp:post-title /--><!-- wp:post-content /--><!-- /wp:post-template --><!-- /wp:query -->',
            ],
        ];
    }
    public static function zip(array $files,string $path): void {
        $z=new \ZipArchive();if($z->open($path,\ZipArchive::CREATE|\ZipArchive::EXCL)!==true) throw new \RuntimeException('Cannot create fixture ZIP.');
        foreach($files as $name=>$bytes) $z->addFromString($name,$bytes);
        $z->close();
    }
}
