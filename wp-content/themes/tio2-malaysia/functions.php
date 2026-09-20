<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/inc/seo.php';
add_action('after_setup_theme', static function() {
    add_theme_support('html5',['search-form','gallery','caption','style','script']);
});
add_filter('show_admin_bar','__return_false');
add_filter('wp_sitemaps_enabled', 'tio2_indexing_authorized');
add_action('wp_enqueue_scripts', static function() {
    $uri=get_template_directory_uri();
    wp_enqueue_style('tio2-design',$uri.'/assets/design.css',[],filemtime(__DIR__.'/assets/design.css'));
    wp_enqueue_style('tio2-site',$uri.'/assets/site.css',['tio2-design'],filemtime(__DIR__.'/assets/site.css'));
    wp_enqueue_script('tio2-site',$uri.'/assets/site.js',[],filemtime(__DIR__.'/assets/site.js'),['strategy'=>'defer','in_footer'=>true]);
    if (tio2_current_page_id() === 'PRODUCT-000') {
        wp_enqueue_style('tio2-products',$uri.'/assets/products.css',['tio2-site'],filemtime(__DIR__.'/assets/products.css'));
        wp_enqueue_script('tio2-products',$uri.'/assets/products.js',['tio2-site'],filemtime(__DIR__.'/assets/products.js'),['strategy'=>'defer','in_footer'=>true]);
    }
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('global-styles');
});
remove_action('wp_head','rel_canonical');
remove_action('wp_head','wp_robots',1);
remove_action('wp_head','wp_generator');
remove_action('wp_head','print_emoji_detection_script',7);
remove_action('wp_print_styles','print_emoji_styles');
remove_action('wp_enqueue_scripts','wp_enqueue_emoji_styles');

function tio2_logo(string $placement): void {
    $asset=tio2_field($placement.'.logo');
    ?><img src="<?php echo esc_url(get_template_directory_uri().'/'.$asset); ?>" width="180" height="60" alt="TiO2 Malaysia"><?php
}
function tio2_current_page_id(): string {
    if (is_front_page()) return 'HOME-001';
    if (!is_page()) return '';
    return (string) get_post_meta(get_queried_object_id(), '_tio2_page_id', true);
}
function tio2_navigation(bool $mobile=false): void {
    $current = tio2_current_page_id();
    for($i=0;$i<($mobile?8:7);$i++) {
        $href=tio2_field("header.nav.$i.href");
        $is_current = ($current === 'HOME-001' && $i === 0) || ($current === 'PRODUCT-000' && $i === 2);
        ?><a href="<?php echo esc_url($href); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?><?php echo $i===7?' class="menu-rfq"':''; ?>><?php echo esc_html(tio2_field("header.nav.$i.label")); ?></a><?php
    }
}
