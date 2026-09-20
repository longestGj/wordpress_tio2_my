<?php defined('ABSPATH') || exit; ?>
<section class="hero" data-module="hero">
<div class="shell hero-shell">
<div class="hero-copy">
<p class="eyebrow"><?php echo esc_html(tio2_field('hero.eyebrow.1')); ?></p>
<h1><?php echo esc_html(tio2_field('hero.heading.1')); ?></h1>
<p><?php echo esc_html(tio2_field('hero.paragraph.1')); ?></p>
<div class="hero-actions"><a class="primary" href="<?php echo esc_url(tio2_field('hero.link.1')); ?>"><?php echo esc_html(tio2_field('hero.link-label.1')); ?></a><a class="secondary" href="<?php echo esc_url(tio2_field('hero.link.2')); ?>"><?php echo esc_html(tio2_field('hero.link-label.2')); ?></a></div>
</div>
<div class="hero-media"><img alt="<?php echo esc_attr(tio2_field('hero.image-alt')); ?>" decoding="async" fetchpriority="high" src="<?php echo esc_url(tio2_image_url('hero.image')); ?>"/></div>
</div>
</section>
