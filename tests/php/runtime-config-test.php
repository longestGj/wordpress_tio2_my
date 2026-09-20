<?php
// Pure runtime-identity test: the public URL must never follow the local request URL.
define('ABSPATH', __DIR__ . '/');
define('TIO2_PUBLIC_URL', 'https://tio2products.com');
function add_action($hook, $callback, $priority = 10) {}
function home_url($path = '/') { return 'http://127.0.0.1:8232' . $path; }

require __DIR__ . '/../../wp-content/themes/tio2-malaysia/inc/seo.php';

$count = 0;
function check($condition, $message) {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}

check(tio2_public_base_url() === 'https://tio2products.com/', 'formal public URL is stable');
check(home_url('/') === 'http://127.0.0.1:8232/', 'test proves the request origin is different');

foreach ([
    'http://tio2products.com',
    'https://tio2products.com/products/',
    'https://user:pass@tio2products.com',
    'https://tio2products.com?preview=1',
    'https://tio2products.com#fragment',
    'https://tio2malaysia.com',
] as $invalid) {
    try {
        tio2_validate_public_base_url($invalid);
        throw new RuntimeException('accepted invalid URL: ' . $invalid);
    } catch (InvalidArgumentException $expected) {
        $count++;
    }
}

echo "$count runtime identity assertions passed\n";
