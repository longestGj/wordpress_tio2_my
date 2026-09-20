<?php
/**
 * Template Name: Products Hub
 * Template Post Type: page
 */
defined('ABSPATH') || exit;
get_header();
?>
<main id="main" class="products-page" tabindex="-1" data-page-id="PRODUCT-000">
    <section class="shell section" data-module="products-shell">
        <h1><?php echo esc_html(tio2_products_field('hero.heading')); ?></h1>
    </section>
</main>
<?php get_footer(); ?>
