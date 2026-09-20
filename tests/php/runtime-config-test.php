<?php
define('ABSPATH', __DIR__ . '/');
define('TIO2_RELEASE', '0123456789abcdef0123456789abcdef01234567');
putenv('TIO2_PUBLIC_URL=https://tio2products.com');

function add_action($hook, $callback, $priority = 10): void {}
function home_url($path = ''): string { return 'https://tio2products.com' . $path; }
function trailingslashit($value): string { return rtrim($value, '/') . '/'; }
function esc_url_raw($value): string { return $value; }
function wp_parse_url($value) { return parse_url($value); }
$environment_type = 'local';
$blog_public = true;
function wp_get_environment_type(): string { global $environment_type; return $environment_type; }
function get_option($name) { global $blog_public; return $name === 'blog_public' ? $blog_public : null; }

require dirname(__DIR__, 2) . '/wp-content/themes/tio2-malaysia/inc/seo.php';
require dirname(__DIR__, 2) . '/wp-content/plugins/tio2-content/includes/identity.php';

assert(tio2_public_base_url() === 'https://tio2products.com/');
assert(tio2_normalize_public_url('https://EXAMPLE.com/') === 'https://example.com/');
foreach (['', 'http://tio2products.com', 'https://user@example.com', 'https://example.com/path', 'https://example.com/?query=1'] as $invalid_url) {
    assert(tio2_normalize_public_url($invalid_url) === 'https://tio2products.com/');
}
assert(tio2_indexing_authorized() === false);
$environment_type = 'production';
assert(tio2_indexing_authorized() === false);
define('TIO2_INDEXING_AUTHORIZED', true);
assert(tio2_indexing_authorized() === true);
assert(tio2_normalize_release(TIO2_RELEASE) === TIO2_RELEASE);
foreach (['', 'abc', strtoupper(TIO2_RELEASE), '../' . TIO2_RELEASE, TIO2_RELEASE . ';id'] as $invalid) {
    assert(tio2_normalize_release($invalid) === '');
}

echo "runtime configuration contract passed\n";
