<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main" tabindex="-1">
<?php foreach(['hero','start-here','markets','products','applications','company','documents','resources','page-rfq'] as $module) get_template_part('template-parts/'.$module); ?>
</main>
<?php get_footer(); ?>
