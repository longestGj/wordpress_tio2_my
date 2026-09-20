<?php
defined('ABSPATH') || exit;
$hero = $args ?? [];
?>
<section class="product-root-hero" data-module="hero" aria-labelledby="page-title">
  <div class="shell product-root-hero-inner">
    <div class="product-hero-copy">
      <p class="eyebrow"><?php echo esc_html($hero['kicker']); ?></p>
      <h1 id="page-title"><?php echo esc_html($hero['heading']); ?></h1>
      <p class="product-hero-intro"><?php echo esc_html($hero['intro']); ?></p>
      <p><?php echo esc_html($hero['statement']); ?></p>
      <p class="product-small"><?php echo esc_html($hero['qualification']); ?></p>
      <div class="product-actions">
        <a class="primary" href="<?php echo esc_attr($hero['primary_path']); ?>"><?php echo esc_html($hero['primary_label']); ?></a>
        <a class="secondary" href="<?php echo esc_url($hero['secondary_path']); ?>"><?php echo esc_html($hero['secondary_label']); ?></a>
      </div>
    </div>
    <aside class="product-portfolio-summary" aria-labelledby="portfolio-summary-title">
      <h2 id="portfolio-summary-title"><?php echo esc_html($hero['summary_title']); ?></h2>
      <p><?php echo esc_html($hero['summary_body']); ?></p>
      <div class="product-summary-grid">
        <?php foreach ($hero['summary_items'] as $item): ?>
          <?php $parts = preg_split('/\s+/u', $item, 2); $number = count($parts) === 2 ? $parts[0] : ''; $label = count($parts) === 2 ? $parts[1] : $item; ?>
          <div><strong><?php echo esc_html($number); ?></strong><span><?php echo esc_html($label); ?></span></div>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
</section>
