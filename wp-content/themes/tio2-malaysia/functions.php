<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/inc/seo.php';
add_action('after_setup_theme', static function() {
    add_theme_support('html5',['search-form','gallery','caption','style','script']);
});
add_filter('show_admin_bar','__return_false');
add_filter('wp_sitemaps_enabled', static fn() => wp_get_environment_type()==='production' && (bool)get_option('blog_public'));
add_action('wp_enqueue_scripts', static function() {
    $uri=get_template_directory_uri();
    wp_enqueue_style('tio2-design',$uri.'/assets/design.css',[],filemtime(__DIR__.'/assets/design.css'));
    wp_enqueue_style('tio2-site',$uri.'/assets/site.css',['tio2-design'],filemtime(__DIR__.'/assets/site.css'));
    wp_enqueue_script('tio2-site',$uri.'/assets/site.js',[],filemtime(__DIR__.'/assets/site.js'),['strategy'=>'defer','in_footer'=>true]);
    if (function_exists('tio2_is_rfq_page') && tio2_is_rfq_page()) {
        wp_enqueue_style('tio2-rfq',$uri.'/assets/rfq.css',['tio2-site'],filemtime(__DIR__.'/assets/rfq.css'));
        wp_enqueue_script('tio2-rfq',$uri.'/assets/rfq.js',[],filemtime(__DIR__.'/assets/rfq.js'),['strategy'=>'defer','in_footer'=>true]);
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
function tio2_navigation(bool $mobile=false): void {
    for($i=0;$i<($mobile?8:7);$i++) {
        $href=tio2_field("header.nav.$i.href");
        $current=(is_front_page() && $i===0) || (function_exists('tio2_is_rfq_page') && tio2_is_rfq_page() && $i===7);
        ?><a href="<?php echo esc_url($href); ?>"<?php echo $current ? ' aria-current="page"' : ''; ?><?php echo $i===7?' class="menu-rfq"':''; ?>><?php echo esc_html(tio2_field("header.nav.$i.label")); ?></a><?php
    }
}

function tio2_rfq_page_is_owned(int $page_id): bool {
    return $page_id > 0
        && get_post_type($page_id) === 'page'
        && get_post_status($page_id) === 'publish'
        && get_post_field('post_name', $page_id) === 'request-a-quote'
        && get_post_meta($page_id, '_tio2_managed_page', true) === '1'
        && get_post_meta($page_id, '_tio2_page_id', true) === 'CONV-RFQ'
        && get_post_meta($page_id, '_tio2_site_scope', true) === 'tio2-my';
}

function tio2_is_rfq_page(): bool {
    return is_page() && tio2_rfq_page_is_owned((int) get_queried_object_id());
}

function tio2_rfq_prefill(array $query): array {
    $contract=tio2_rfq_form_contract(tio2_rfq_content());
    $controls=array_column($contract['controls'],null,'name');
    $prefill=[];
    foreach(['grade_id','application_id'] as $name) {
        $value=$query[$name] ?? null;
        if (is_string($value)) {
            $value=tio2_rfq_text(wp_unslash($value));
            if ($value!==null && in_array($value,$controls[$name]['option_values'],true)) $prefill[$name]=$value;
        }
    }
    $country=$query['destination_country'] ?? null;
    if (is_string($country)) {
        $country=tio2_rfq_text(wp_unslash($country));
        $broad=['eu','european union','asean','europe','asia','southeast asia','asia pacific','apac','emea','mena','middle east','africa','african union','north america','south america','central america','latin america','caribbean','gcc','gulf cooperation council','mercosur','global','world','worldwide','international'];
        if ($country!==null && $country!=='' && tio2_rfq_length($country)<=100 && !in_array(strtolower($country),$broad,true)) {
            $prefill['destination_country']=$country;
        }
    }
    $process=$query['process_context'] ?? null;
    if (($prefill['grade_id'] ?? null)==='m-2377' && is_string($process) && strtolower(trim(wp_unslash($process)))==='sulfate') {
        $prefill['additional_requirements']='Sulfate';
    }
    return $prefill;
}
