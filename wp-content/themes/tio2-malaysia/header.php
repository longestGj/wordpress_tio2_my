<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="global-header">
  <div class="header-inner desktop-bar">
    <a class="logo" href="/" aria-label="TiO2 Malaysia home"><?php tio2_logo('header'); ?></a>
    <nav class="desktop-nav" aria-label="Primary navigation"><?php tio2_navigation(); ?></nav>
    <a class="header-rfq" href="<?php echo esc_url(tio2_field('header.nav.7.href')); ?>"<?php echo function_exists('tio2_is_rfq_page') && tio2_is_rfq_page() ? ' aria-current="page"' : ''; ?>><?php echo esc_html(tio2_field('header.nav.7.label')); ?></a>
  </div>
  <div class="header-inner mobile-bar">
    <a class="logo" href="/" aria-label="TiO2 Malaysia home"><?php tio2_logo('header'); ?></a>
    <a class="mobile-rfq" href="<?php echo esc_url(tio2_field('header.nav.7.href')); ?>" aria-label="<?php echo esc_attr(tio2_field('header.nav.7.label')); ?>"<?php echo function_exists('tio2_is_rfq_page') && tio2_is_rfq_page() ? ' aria-current="page"' : ''; ?>>RFQ</a>
    <button class="menu-button" type="button" aria-label="Open primary navigation" aria-expanded="false" aria-controls="mobile-menu">Menu</button>
  </div>
</header>
<dialog id="mobile-menu" class="mobile-menu" aria-label="Primary navigation menu">
  <div class="menu-topbar">
    <a class="logo" href="/" aria-label="TiO2 Malaysia home"><?php tio2_logo('header'); ?></a>
    <a class="mobile-rfq" href="<?php echo esc_url(tio2_field('header.nav.7.href')); ?>" aria-label="<?php echo esc_attr(tio2_field('header.nav.7.label')); ?>"<?php echo function_exists('tio2_is_rfq_page') && tio2_is_rfq_page() ? ' aria-current="page"' : ''; ?>>RFQ</a>
    <button class="menu-close" type="button" aria-label="Close primary navigation menu">Close</button>
  </div>
  <nav aria-label="Mobile navigation"><?php tio2_navigation(true); ?></nav>
</dialog>
