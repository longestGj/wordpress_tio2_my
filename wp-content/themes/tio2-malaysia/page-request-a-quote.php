<?php
defined('ABSPATH') || exit;
if (!tio2_rfq_page_is_owned((int) get_queried_object_id())) {
    status_header(404);
    get_header();
    ?><main id="main" class="shell section" tabindex="-1"><h1>Page not found</h1><p><a href="/">Return to the homepage</a></p></main><?php
    get_footer();
    return;
}
$content=tio2_rfq_content();
$contract=tio2_rfq_form_contract($content);
$controls=array_column($contract['controls'],null,'name');
$prefill=tio2_rfq_prefill($_GET);
$groups=[
    'requirement'=>['grade_id','application_id','quantity_mt','destination_country','destination_port_city'],
    'company'=>['company_name','contact_name','business_email','phone_whatsapp','website'],
    'additional'=>['additional_requirements'],
];

$render_control=static function(array $control) use ($prefill,$contract): void {
    $name=$control['name'];
    $id='rfq-field-'.$name;
    $helper_id=$id.'-helper';
    $error_id=$id.'-error';
    $described=[];
    if ($control['helper']!=='') $described[]=$helper_id;
    $described[]=$error_id;
    $value=$prefill[$name] ?? '';
    ?>
    <div class="rfq-field rfq-field-<?php echo esc_attr($name); ?>">
      <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($control['label']); ?><?php if($control['required']): ?> <span aria-hidden="true">*</span><?php endif; ?></label>
      <?php if($control['type']==='select'): ?>
        <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" aria-describedby="<?php echo esc_attr(implode(' ',$described)); ?>"<?php echo $control['required']?' required':''; ?>>
          <option value=""><?php echo esc_html($control['placeholder']); ?></option>
          <?php foreach($control['option_values'] as $index=>$option_value): ?><option value="<?php echo esc_attr($option_value); ?>"<?php selected($value,$option_value); ?>><?php echo esc_html($control['options'][$index]); ?></option><?php endforeach; ?>
        </select>
      <?php elseif($control['type']==='textarea'): ?>
        <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" maxlength="<?php echo (int)$control['maxlength']; ?>" aria-describedby="<?php echo esc_attr(implode(' ',$described)); ?>"><?php echo esc_textarea($value); ?></textarea>
      <?php else: ?>
        <div class="<?php echo $name==='quantity_mt'?'rfq-quantity':''; ?>">
          <input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" type="<?php echo esc_attr($control['type']); ?>" value="<?php echo esc_attr($value); ?>"<?php echo $control['required']?' required':''; ?><?php echo $control['maxlength']?' maxlength="'.(int)$control['maxlength'].'"':''; ?><?php echo $control['minlength']?' minlength="'.(int)$control['minlength'].'"':''; ?><?php echo $control['placeholder']!==''?' placeholder="'.esc_attr($control['placeholder']).'"':''; ?><?php echo $control['type']==='number'?' min="0" step="any" inputmode="decimal"':''; ?> aria-describedby="<?php echo esc_attr(implode(' ',$described)); ?>">
          <?php if($name==='quantity_mt'): ?><span class="rfq-unit" data-quantity-unit><?php echo esc_html($contract['quantity_unit']['label']); ?></span><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if($control['helper']!==''): ?><p class="field-helper" id="<?php echo esc_attr($helper_id); ?>"><?php echo esc_html($control['helper']); ?></p><?php endif; ?>
      <p class="field-error" id="<?php echo esc_attr($error_id); ?>" hidden></p>
    </div>
    <?php
};
get_header();
?>
<main id="main" class="rfq-main" tabindex="-1">
  <section class="rfq-hero" data-module="rfq-hero">
    <div class="shell">
      <nav class="rfq-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo esc_url(tio2_rfq_field('breadcrumb.home-path')); ?>"><?php echo esc_html(tio2_rfq_field('breadcrumb.home-label')); ?></a><span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html(tio2_rfq_field('breadcrumb.current-label')); ?></span></nav>
      <p class="eyebrow"><?php echo esc_html(tio2_rfq_field('hero.eyebrow')); ?></p>
      <h1><?php echo esc_html(tio2_rfq_field('hero.heading')); ?></h1>
      <p><?php echo esc_html(tio2_rfq_field('hero.body')); ?></p>
    </div>
  </section>
  <section class="rfq-form-section" data-module="rfq-form" aria-labelledby="rfq-form-heading">
    <div class="shell">
      <div class="rfq-form-surface">
        <h2 id="rfq-form-heading"><?php echo esc_html(tio2_rfq_field('form.heading')); ?></h2>
        <p><?php echo esc_html(tio2_rfq_field('form.intro')); ?></p>
        <div class="validation-summary" tabindex="-1" role="alert" hidden>
          <h3><?php echo esc_html(tio2_rfq_field('validation.summary.heading')); ?></h3>
          <p><?php echo esc_html(tio2_rfq_field('validation.summary.body')); ?></p>
          <ul></ul>
        </div>
        <form id="rfq-form" action="<?php echo esc_url(wp_make_link_relative(admin_url('admin-ajax.php'))); ?>" method="post" novalidate>
          <input type="hidden" name="action" value="tio2_rfq_submit">
          <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('tio2_rfq_submit')); ?>">
          <input type="hidden" name="company_website" value="">
          <?php foreach($groups as $group=>$names): ?>
            <fieldset class="rfq-group rfq-group-<?php echo esc_attr($group); ?>">
              <legend><?php echo esc_html(tio2_rfq_field('group.'.$group)); ?></legend>
              <div class="rfq-field-grid"><?php foreach($names as $name) $render_control($controls[$name]); ?></div>
            </fieldset>
          <?php endforeach; ?>
          <p class="rfq-privacy"><?php echo esc_html(tio2_rfq_field('privacy.before')); ?> <a href="<?php echo esc_url(tio2_rfq_field('privacy.path')); ?>"><?php echo esc_html(tio2_rfq_field('privacy.link-label')); ?></a><?php echo esc_html(tio2_rfq_field('privacy.after')); ?></p>
          <button class="rfq-submit" type="submit"><?php echo esc_html(tio2_rfq_field('submit.normal')); ?></button>
        </form>
        <div class="rfq-state" role="status" tabindex="-1" hidden></div>
        <template id="rfq-failure"><h3><?php echo esc_html(tio2_rfq_field('failure.heading')); ?></h3><p><?php echo esc_html(tio2_rfq_field('failure.body')); ?></p><button type="button"><?php echo esc_html(tio2_rfq_field('failure.action')); ?></button></template>
        <template id="rfq-success"><h3><?php echo esc_html(tio2_rfq_field('success.heading')); ?></h3><p><?php echo esc_html(tio2_rfq_field('success.body')); ?></p></template>
        <template id="rfq-unavailable"><h3><?php echo esc_html(tio2_rfq_field('unavailable.heading')); ?></h3><p><?php echo esc_html(tio2_rfq_field('unavailable.body')); ?></p></template>
        <script type="application/json" id="rfq-config"><?php echo wp_json_encode(['errors'=>$contract['errors'],'normal'=>tio2_rfq_field('submit.normal'),'pending'=>tio2_rfq_field('submit.pending')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?></script>
      </div>
    </div>
  </section>
  <section class="rfq-other" data-module="rfq-other" aria-labelledby="rfq-other-heading">
    <div class="shell">
      <h2 id="rfq-other-heading"><?php echo esc_html(tio2_rfq_field('other.heading')); ?></h2>
      <p><?php echo esc_html(tio2_rfq_field('other.intro')); ?></p>
      <div class="rfq-other-links"><a href="<?php echo esc_url(tio2_rfq_field('other.sample-path')); ?>"><?php echo esc_html(tio2_rfq_field('other.sample-label')); ?></a><a href="<?php echo esc_url(tio2_rfq_field('other.documents-path')); ?>"><?php echo esc_html(tio2_rfq_field('other.documents-label')); ?></a></div>
    </div>
  </section>
</main>
<?php get_footer(); ?>
