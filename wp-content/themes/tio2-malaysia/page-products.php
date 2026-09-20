<?php
/**
 * Template Name: Products Hub
 * Template Post Type: page
 */
defined('ABSPATH') || exit;
if (tio2_current_page_id() !== 'PRODUCT-000') {
    require get_index_template();
    return;
}
$content = tio2_products_content();
$fields = $content['fields'];
$model = tio2_products_model($content);
$grade_by_route = [];
foreach ($model['grades'] as $grade) $grade_by_route[$grade['route_key']] = $grade;
$resolved = [];
foreach (array_keys(tio2_route_registry()) as $route_key) $resolved[$route_key] = tio2_resolve_route($route_key);
$process_cards = [
    'PRODUCT-PROC-CL' => ['title' => $fields['process.chloride.title'], 'body' => $fields['process.chloride.body'], 'cta' => $fields['process.chloride.cta']],
    'PRODUCT-PROC-SU' => ['title' => $fields['process.sulfate.title'], 'body' => $fields['process.sulfate.body'], 'cta' => $fields['process.sulfate.cta']],
];
$support_cards = [
    'APP-000' => ['title' => $fields['support.card.1.title'], 'body' => $fields['support.card.1.body'], 'cta' => $fields['support.card.1.cta']],
    'DOC-000' => ['title' => $fields['support.card.2.title'], 'body' => $fields['support.card.2.body'], 'cta' => $fields['support.card.2.cta']],
    'MARKET-000' => ['title' => $fields['support.card.3.title'], 'body' => $fields['support.card.3.body'], 'cta' => $fields['support.card.3.cta']],
];
$ready_support = array_filter($support_cards, static fn($_card, $key) => $resolved[$key] !== null, ARRAY_FILTER_USE_BOTH);
$selector_applications = [];
foreach ($model['applications'] as $application => $route_keys) {
    $selector_applications[$application] = array_map(static function($route_key) use ($grade_by_route, $resolved) {
        return [
            'routeKey' => $route_key,
            'name' => $grade_by_route[$route_key]['name'],
            'url' => $resolved[$route_key]['path'] ?? null,
        ];
    }, $route_keys);
}
$selector_data = [
    'applications' => $selector_applications,
    'headingTemplate' => $fields['selector.result-heading-template'],
    'ctaLabel' => $fields['selector.result-cta-label'],
    'noResult' => $fields['selector.no-result'],
    'browseLabel' => $fields['directory.heading'],
    'failure' => $fields['selector.failure'],
    'explicitSelection' => false,
];
get_header();
?>
<main id="main" class="products-page" tabindex="-1" data-page-id="PRODUCT-000">
  <section class="product-breadcrumb shell" data-module="breadcrumb">
    <nav aria-label="Breadcrumb"><ol><li><a href="/"><?php echo esc_html($fields['breadcrumb.home-label']); ?></a></li><li aria-current="page"><?php echo esc_html($fields['breadcrumb.current-label']); ?></li></ol></nav>
  </section>
  <?php get_template_part('template-parts/root-page-hero', null, [
      'kicker' => $fields['hero.kicker'],
      'heading' => $fields['hero.heading'],
      'intro' => $fields['hero.intro'],
      'statement' => $fields['hero.rutile-statement'],
      'qualification' => $fields['hero.qualification'],
      'primary_label' => $fields['hero.primary-label'],
      'primary_path' => $fields['hero.primary-path'],
      'secondary_label' => $fields['hero.secondary-label'],
      'secondary_path' => $fields['route.rfq-path'],
      'summary_title' => $fields['hero.summary-title'],
      'summary_body' => $fields['hero.summary-body'],
      'summary_items' => array_map(static fn($number) => $fields["hero.summary-item.$number"], range(1, 4)),
  ]); ?>
  <section class="product-selector-band" id="grade-selector" data-module="selector" aria-labelledby="selector-heading">
    <div class="shell product-section">
      <p class="eyebrow"><?php echo esc_html($fields['selector.eyebrow']); ?></p>
      <h2 id="selector-heading"><?php echo esc_html($fields['selector.heading']); ?></h2>
      <p><?php echo esc_html($fields['selector.intro']); ?></p>
      <div class="product-selector">
        <div>
          <h3><?php echo esc_html($fields['selector.step-heading']); ?></h3>
          <div class="product-selector-options" role="group" aria-label="Application">
            <?php foreach (array_keys($model['applications']) as $offset => $application): ?>
              <button type="button" data-application="<?php echo esc_attr($application); ?>" aria-pressed="<?php echo $offset === 0 ? 'true' : 'false'; ?>"><?php echo esc_html($application); ?></button>
            <?php endforeach; ?>
            <button type="button" data-application="not-sure" aria-pressed="false"><?php echo esc_html($fields['selector.not-sure-label']); ?></button>
          </div>
        </div>
        <div class="product-selector-results">
          <h3 id="product-result-heading" aria-live="polite"><?php echo esc_html(str_replace('{count}', (string) count(reset($model['applications'])), $fields['selector.result-heading-template'])); ?></h3>
          <div id="product-results">
            <?php foreach (reset($model['applications']) as $route_key): $grade = $grade_by_route[$route_key]; ?>
              <div class="product-selector-result"><strong><?php echo esc_html($grade['name']); ?></strong>
                <?php if ($resolved[$route_key]): ?><a data-route-key="<?php echo esc_attr($route_key); ?>" aria-label="<?php echo esc_attr($grade['cta'] . ' ' . $grade['name']); ?>" href="<?php echo esc_url($resolved[$route_key]['path']); ?>"><?php echo esc_html($fields['selector.result-cta-label']); ?></a><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="product-small"><?php echo esc_html($fields['selector.disclaimer']); ?></p>
        </div>
      </div>
      <noscript><p><?php echo esc_html($fields['selector.failure']); ?></p></noscript>
      <script id="products-selector-data" type="application/json"><?php echo wp_json_encode($selector_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    </div>
  </section>
  <section class="shell product-section" id="process" data-module="process" aria-labelledby="process-heading">
    <h2 id="process-heading"><?php echo esc_html($fields['process.heading']); ?></h2>
    <p class="product-section-intro"><?php echo esc_html($fields['process.intro']); ?></p>
    <?php $ready_process = array_filter($process_cards, static fn($_card, $key) => $resolved[$key] !== null, ARRAY_FILTER_USE_BOTH); ?>
    <?php if ($ready_process): ?><div class="product-process-cards">
      <?php foreach ($ready_process as $route_key => $card): ?><article class="product-route-card">
        <h3><?php echo esc_html($card['title']); ?></h3><p><?php echo esc_html($card['body']); ?></p>
        <a data-route-key="<?php echo esc_attr($route_key); ?>" href="<?php echo esc_url($resolved[$route_key]['path']); ?>"><?php echo esc_html($card['cta']); ?></a>
      </article><?php endforeach; ?>
    </div><?php endif; ?>
    <div class="product-special-process"><strong><?php echo esc_html($fields['process.special.grade-label'] . ' · ' . $fields['process.special.process-label']); ?></strong>
      <?php if ($resolved['GRADE-CR901']): ?><a data-route-key="GRADE-CR901" aria-label="<?php echo esc_attr($fields['process.special.cta'] . ' ' . $fields['process.special.grade-label']); ?>" href="<?php echo esc_url($resolved['GRADE-CR901']['path']); ?>"><?php echo esc_html($fields['process.special.cta']); ?></a><?php endif; ?>
    </div>
  </section>
  <section class="product-directory-band" id="all-grades" data-module="directory" aria-labelledby="directory-heading">
    <div class="shell product-section">
      <h2 id="directory-heading"><?php echo esc_html($fields['directory.heading']); ?></h2>
      <p class="product-section-intro"><?php echo esc_html($fields['directory.intro']); ?></p>
      <div class="product-directory">
        <?php foreach ($model['grade_groups'] as $group_label => $grades): ?><article class="product-grade-group">
          <h3><?php echo esc_html($group_label); ?></h3>
          <?php foreach ($grades as $grade): ?><div class="product-grade-row" data-grade="<?php echo esc_attr($grade['name']); ?>">
            <strong class="product-grade-name"><?php echo esc_html($grade['name']); ?></strong><p><?php echo esc_html($grade['summary']); ?></p>
            <?php if ($resolved[$grade['route_key']]): ?><a data-route-key="<?php echo esc_attr($grade['route_key']); ?>" aria-label="<?php echo esc_attr($grade['cta'] . ' ' . $grade['name']); ?>" href="<?php echo esc_url($resolved[$grade['route_key']]['path']); ?>"><?php echo esc_html($grade['cta']); ?> <span aria-hidden="true">→</span></a><?php endif; ?>
          </div><?php endforeach; ?>
        </article><?php endforeach; ?>
      </div>
    </div>
  </section>
  <section class="shell product-section" id="evaluation" data-module="evaluation" aria-labelledby="evaluation-heading">
    <h2 id="evaluation-heading"><?php echo esc_html($fields['evaluation.heading']); ?></h2>
    <div class="product-evaluation-steps">
      <?php foreach ($model['evaluation_steps'] as $offset => $step): ?><article class="product-evaluation-step"><span aria-hidden="true"><?php echo esc_html(str_pad((string) ($offset + 1), 2, '0', STR_PAD_LEFT)); ?></span><h3><?php echo esc_html($step['title']); ?></h3><p><?php echo esc_html($step['body']); ?></p></article><?php endforeach; ?>
    </div>
  </section>
  <?php if ($ready_support): ?><section class="product-support-band" id="support" data-module="support" aria-labelledby="support-heading">
    <div class="shell product-section"><h2 id="support-heading"><?php echo esc_html($fields['support.heading']); ?></h2><div class="product-support-cards">
      <?php foreach ($ready_support as $route_key => $card): ?><article class="product-route-card"><h3><?php echo esc_html($card['title']); ?></h3><p><?php echo esc_html($card['body']); ?></p><a data-route-key="<?php echo esc_attr($route_key); ?>" href="<?php echo esc_url($resolved[$route_key]['path']); ?>"><?php echo esc_html($card['cta']); ?></a></article><?php endforeach; ?>
    </div></div>
  </section><?php endif; ?>
  <section class="shell product-section" id="faq" data-module="faq" aria-labelledby="faq-heading">
    <h2 id="faq-heading"><?php echo esc_html($fields['faq.heading']); ?></h2>
    <div class="product-faq-list">
      <?php foreach ($model['faqs'] as $offset => $faq): $answer_id = 'product-faq-answer-' . ($offset + 1); ?>
        <article class="product-faq-item"><h3><button type="button" aria-expanded="<?php echo $offset === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($answer_id); ?>"><?php echo esc_html($faq['question']); ?><span aria-hidden="true"><?php echo $offset === 0 ? '−' : '+'; ?></span></button></h3><div class="product-faq-answer" id="<?php echo esc_attr($answer_id); ?>"<?php echo $offset === 0 ? '' : ' hidden'; ?>><p><?php echo esc_html($faq['answer']); ?></p></div></article>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="product-final-rfq-band" data-module="final-rfq" aria-labelledby="final-rfq-heading">
    <div class="shell product-final-rfq"><div><h2 id="final-rfq-heading"><?php echo esc_html($fields['final-rfq.heading']); ?></h2><p><?php echo esc_html($fields['final-rfq.body']); ?></p><p class="product-small"><?php echo esc_html($fields['final-rfq.note']); ?></p></div><a class="primary" href="<?php echo esc_url($fields['route.rfq-path']); ?>"><?php echo esc_html($fields['final-rfq.cta']); ?></a></div>
  </section>
</main>
<?php get_footer(); ?>
