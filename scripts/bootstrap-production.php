<?php
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'production') {
    throw new RuntimeException('Production WP-CLI only');
}

$version = getenv('TIO2_CONTENT_INIT_VERSION') ?: 'home-v1';
if (preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/', $version) !== 1) {
    throw new RuntimeException('Invalid content initialization version');
}
if (!defined('TIO2_SITE_SCOPE') || TIO2_SITE_SCOPE !== 'tio2-my') {
    throw new RuntimeException('Invalid site scope');
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
$activation = activate_plugin('tio2-content/tio2-content.php');
if (is_wp_error($activation)) throw new RuntimeException($activation->get_error_message());
if (!function_exists('tio2_validate_content')) {
    require_once WP_PLUGIN_DIR . '/tio2-content/tio2-content.php';
}

$theme = wp_get_theme('tio2-malaysia');
if (!$theme->exists()) throw new RuntimeException('Production theme is missing');
switch_theme('tio2-malaysia');

$existing_version = get_option('tio2_content_init_version', '');
$existing_content = get_option('tio2_content', null);
if ($existing_version !== '') {
    echo wp_json_encode(['content' => 'already-initialized', 'version' => $existing_version]) . "\n";
    return;
}
if (is_array($existing_content) && !empty($existing_content['fields'])) {
    if (!add_option('tio2_content_init_version', 'adopted-existing', '', false)) {
        throw new RuntimeException('Could not record adopted content');
    }
    echo wp_json_encode(['content' => 'preserved-existing', 'version' => 'adopted-existing']) . "\n";
    return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$attachment_id = 0;
$temporary = '';
try {
    $source = '/opt/tio2/content/media/hero.png';
    if (!is_file($source)) throw new RuntimeException('Production hero image is missing');
    $temporary = wp_tempnam('hero.png');
    if (!$temporary || !copy($source, $temporary)) throw new RuntimeException('Could not stage production hero image');

    $attachment_id = media_handle_sideload(
        ['name' => 'titanium-dioxide-material.png', 'tmp_name' => $temporary],
        0,
        'Titanium dioxide material'
    );
    $temporary = '';
    if (is_wp_error($attachment_id)) throw new RuntimeException($attachment_id->get_error_message());
    $attachment_id = (int)$attachment_id;
    update_post_meta($attachment_id, '_tio2_site_scope', 'tio2-my');
    update_post_meta($attachment_id, '_wp_attachment_image_alt', '');

    $content = json_decode(file_get_contents('/opt/tio2/content/initial-home.json'), true, 512, JSON_THROW_ON_ERROR);
    $content['fields']['hero.image'] = (string)$attachment_id;
    $valid = tio2_validate_content($content, tio2_schema(), 'tio2_owned_image');
    if (is_wp_error($valid)) throw new RuntimeException($valid->get_error_message());
    if (!update_option('tio2_content', $valid, false) && get_option('tio2_content') !== $valid) {
        throw new RuntimeException('Could not store initial site content');
    }
    if (!add_option('tio2_content_init_version', $version, '', false)) {
        throw new RuntimeException('Could not record content initialization version');
    }
} catch (Throwable $error) {
    if ($temporary !== '' && is_file($temporary)) @unlink($temporary);
    delete_option('tio2_content_init_version');
    delete_option('tio2_content');
    if ($attachment_id > 0) wp_delete_attachment($attachment_id, true);
    throw $error;
}

echo wp_json_encode([
    'content' => 'imported',
    'version' => $version,
    'hero_attachment' => $attachment_id,
    'field_count' => count($valid['fields']),
]) . "\n";
