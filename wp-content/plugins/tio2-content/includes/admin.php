<?php
defined('ABSPATH') || exit;
add_action('admin_menu', static function() {
    add_menu_page('Site content','Site content','manage_options','tio2-content','tio2_admin_page','dashicons-layout',21);
    add_submenu_page('tio2-content','Products content','Products','manage_options','tio2-products','tio2_products_admin_page');
});
add_action('admin_enqueue_scripts', static function($hook) {
    if ($hook !== 'toplevel_page_tio2-content' && !str_ends_with($hook, '_page_tio2-products')) return;
    if ($hook === 'toplevel_page_tio2-content') wp_enqueue_media();
    wp_enqueue_script('tio2-content-admin',plugins_url('../assets/admin.js',__FILE__),[], '1.0.0',true);
});
add_action('admin_post_tio2_save', 'tio2_admin_save');
add_action('admin_post_tio2_products_save', 'tio2_products_admin_save');
function tio2_admin_save(): void {
    if (!current_user_can('manage_options')) wp_die('Not allowed.', 'Not allowed', ['response'=>403]);
    check_admin_referer('tio2_save_content');
    if (!tio2_site_ready()) wp_die('Site identity mismatch.', 'Invalid site', ['response'=>409]);
    $current=get_option('tio2_content');
    $revision=hash('sha256',wp_json_encode($current));
    if (!isset($_POST['revision']) || !is_string($_POST['revision']) || !hash_equals($revision,wp_unslash($_POST['revision']))) wp_die('Content changed in another session. Reload before editing.', 'Edit conflict', ['response'=>409]);
    $posted=isset($_POST['fields']) && is_array($_POST['fields']) ? wp_unslash($_POST['fields']) : [];
    // Field keys contain dots; PHP preserves them inside the fields[...] array.
    $candidate=['site_scope'=>'tio2-my','schema_version'=>1,'fields'=>$posted];
    $validated=tio2_validate_content($candidate,tio2_schema(),'tio2_owned_image');
    if (is_wp_error($validated)) wp_die(esc_html($validated->get_error_message()),'Invalid content',['response'=>422,'back_link'=>true]);
    update_option('tio2_content',$validated,false);
    wp_safe_redirect(admin_url('admin.php?page=tio2-content&saved=1'));
    exit;
}
function tio2_admin_page(): void {
    if (!current_user_can('manage_options')) return;
    $data=get_option('tio2_content');
    if (!is_array($data) || !tio2_site_ready()) { echo '<div class="notice notice-error"><p>Initialize this site before editing.</p></div>'; return; }
    $groups=[];
    foreach(tio2_schema() as $key=>$definition) $groups[$definition['group']][$key]=$definition;
    ?>
    <div class="wrap"><h1>Site content</h1>
    <p>Edit the homepage and shared navigation. The design stays fixed. Save once after editing, then preview the site. Content approval remains your editorial responsibility.</p>
    <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Content saved.</p></div><?php endif; ?>
    <p><a class="button" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener">Preview homepage</a></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="tio2_save">
      <input type="hidden" name="revision" value="<?php echo esc_attr(hash('sha256',wp_json_encode($data))); ?>">
      <?php wp_nonce_field('tio2_save_content'); ?>
      <?php foreach($groups as $group=>$items): ?>
      <details class="tio2-editor-group" style="background:white;border:1px solid #ccd0d4;padding:16px;margin:12px 0" <?php echo $group==='hero' ? 'open' : ''; ?>>
        <summary style="font-size:18px;font-weight:600;cursor:pointer"><?php echo esc_html(ucwords(str_replace('-',' ',$group))); ?></summary>
        <table class="form-table"><tbody>
        <?php foreach($items as $key=>$def): $id='field-'.str_replace('.','-',$key); $value=$data['fields'][$key] ?? ''; ?>
          <tr><th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html(ucwords($def['label'])); ?></label></th><td>
          <?php if($def['type']==='asset'): ?>
            <select id="<?php echo esc_attr($id); ?>" name="fields[<?php echo esc_attr($key); ?>]">
            <?php foreach($def['choices'] as $choice): ?><option <?php selected($value,$choice); ?> value="<?php echo esc_attr($choice); ?>"><?php echo esc_html(basename($choice)); ?></option><?php endforeach; ?>
            </select>
          <?php elseif($def['type']==='image'): ?>
            <input type="number" min="1" required id="<?php echo esc_attr($id); ?>" name="fields[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>">
            <button class="button tio2-media" type="button" data-target="<?php echo esc_attr($id); ?>">Choose image</button>
            <p><img data-preview="<?php echo esc_attr($id); ?>" src="<?php echo esc_url(wp_get_attachment_url((int)$value)); ?>" style="max-width:240px;height:auto" alt="Selected hero image"></p>
          <?php else: ?>
            <textarea style="width:min(100%,850px)" rows="<?php echo strlen($value)>120?3:1; ?>" id="<?php echo esc_attr($id); ?>" name="fields[<?php echo esc_attr($key); ?>]" <?php echo $def['type']!=='alt'?'required':''; ?> maxlength="12000"><?php echo esc_textarea($value); ?></textarea>
            <?php if($def['type']==='path'): ?><p class="description">Local path with a trailing slash, such as /products/. Changing a link does not create its destination.</p><?php endif; ?>
          <?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table>
      </details><?php endforeach; ?>
      <?php submit_button('Save site content'); ?>
    </form></div>
    <?php
}

function tio2_products_admin_save(): void {
    if (!current_user_can('manage_options')) wp_die('Not allowed.', 'Not allowed', ['response'=>403]);
    check_admin_referer('tio2_save_products_content');
    if (!tio2_site_ready()) wp_die('Site identity mismatch.', 'Invalid site', ['response'=>409]);
    $current = get_option(TIO2_PRODUCTS_OPTION);
    $revision = hash('sha256', wp_json_encode($current));
    if (!isset($_POST['products_revision']) || !is_string($_POST['products_revision']) || !hash_equals($revision, wp_unslash($_POST['products_revision']))) {
        wp_die('Products content changed in another session. Reload before editing.', 'Edit conflict', ['response'=>409]);
    }
    $posted = isset($_POST['products_fields']) && is_array($_POST['products_fields']) ? wp_unslash($_POST['products_fields']) : [];
    $candidate = ['site_scope'=>'tio2-my', 'schema_version'=>1, 'fields'=>$posted];
    $validated = tio2_validate_content($candidate, tio2_products_schema(), static fn() => false);
    if (is_wp_error($validated)) wp_die(esc_html($validated->get_error_message()), 'Invalid Products content', ['response'=>422, 'back_link'=>true]);
    update_option(TIO2_PRODUCTS_OPTION, $validated, false);
    wp_safe_redirect(admin_url('admin.php?page=tio2-products&saved=1'));
    exit;
}

function tio2_products_admin_page(): void {
    if (!current_user_can('manage_options')) return;
    $data = get_option(TIO2_PRODUCTS_OPTION);
    if (!is_array($data) || !tio2_site_ready()) {
        echo '<div class="notice notice-error"><p>Run the Products migration before editing.</p></div>';
        return;
    }
    $groups = [];
    foreach (tio2_products_schema() as $key => $definition) {
        $group = explode('.', $key, 2)[0];
        $groups[$group][$key] = $definition;
    }
    ?>
    <div class="wrap"><h1>Products content</h1>
    <p>Edit the PRODUCT-000 Hub copy and registered local paths. Route readiness still requires a matching published Malaysia page; editing a path never creates its destination.</p>
    <?php if (isset($_GET['saved'])): ?><div class="notice notice-success"><p>Products content saved.</p></div><?php endif; ?>
    <p><a class="button" href="/products/" target="_blank" rel="noopener">Preview Products</a></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="tio2_products_save">
      <input type="hidden" name="products_revision" value="<?php echo esc_attr(hash('sha256', wp_json_encode($data))); ?>">
      <?php wp_nonce_field('tio2_save_products_content'); ?>
      <?php foreach ($groups as $group => $items): ?>
      <details class="tio2-editor-group" style="background:white;border:1px solid #ccd0d4;padding:16px;margin:12px 0" <?php echo $group === 'hero' ? 'open' : ''; ?>>
        <summary style="font-size:18px;font-weight:600;cursor:pointer"><?php echo esc_html(ucwords(str_replace('-', ' ', $group))); ?></summary>
        <table class="form-table"><tbody>
        <?php foreach ($items as $key => $definition): $id = 'products-field-' . str_replace('.', '-', $key); $value = $data['fields'][$key] ?? ''; ?>
          <tr><th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html(ucwords(str_replace(['.', '-'], ' ', $key))); ?></label></th><td>
            <textarea style="width:min(100%,850px)" rows="<?php echo strlen($value) > 120 ? 3 : 1; ?>" id="<?php echo esc_attr($id); ?>" name="products_fields[<?php echo esc_attr($key); ?>]" required maxlength="12000"><?php echo esc_textarea($value); ?></textarea>
            <?php if ($definition['type'] === 'path'): ?><p class="description">Use a local path such as /products/m-350/ or a same-page fragment such as #grade-selector.</p><?php endif; ?>
          </td></tr>
        <?php endforeach; ?></tbody></table>
      </details><?php endforeach; ?>
      <?php submit_button('Save Products content'); ?>
    </form></div>
    <?php
}
