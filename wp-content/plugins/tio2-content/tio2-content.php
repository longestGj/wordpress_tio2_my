<?php
/**
 * Plugin Name: TiO2 Malaysia Content
 * Description: Structured editable content for the fixed Malaysia site design.
 * Version: 1.0.0
 * Requires PHP: 8.3
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/rfq.php';
require_once __DIR__ . '/includes/rfq-submission.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/identity.php';

function tio2_schema(): array {
    static $schema;
    return $schema ??= json_decode(file_get_contents(__DIR__ . '/schema.json'), true, 512, JSON_THROW_ON_ERROR);
}
function tio2_site_ready(): bool {
    return defined('TIO2_SITE_SCOPE') && TIO2_SITE_SCOPE === 'tio2-my';
}
function tio2_owned_image(int $id): bool {
    return tio2_site_ready() && wp_attachment_is_image($id) && get_post_meta($id,'_tio2_site_scope',true) === 'tio2-my';
}
function tio2_content(): array {
    if (!tio2_site_ready()) throw new RuntimeException('Site identity is missing or invalid.');
    $data = get_option('tio2_content');
    $valid = tio2_validate_content($data, tio2_schema(), 'tio2_owned_image');
    if (is_wp_error($valid)) throw new RuntimeException('Site content is unavailable.');
    return $valid;
}
function tio2_field(string $key): string {
    // One request cache; saves occur on a separate authenticated admin request.
    static $content;
    $content ??= tio2_content();
    if (!array_key_exists($key,$content['fields'])) throw new RuntimeException('Unknown content field.');
    return $content['fields'][$key];
}
function tio2_image_url(string $key): string {
    $id=(int)tio2_field($key);
    if (!tio2_owned_image($id)) throw new RuntimeException('Invalid image owner.');
    $url=wp_get_attachment_url($id);
    if (!$url) throw new RuntimeException('Image unavailable.');
    return $url;
}
add_action('add_attachment', static function($id) {
    if (tio2_site_ready()) update_post_meta($id,'_tio2_site_scope','tio2-my');
});
// Identity failures are explicit, not another site's fallback or a partial page.
add_action('template_redirect', static function() {
    try { tio2_content(); }
    catch (Throwable $error) { wp_die('Site content is temporarily unavailable.', 'Content unavailable', ['response'=>503]); }
});
