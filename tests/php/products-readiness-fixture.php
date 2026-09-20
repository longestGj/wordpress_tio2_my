<?php
// Local WP-CLI fixture for real PRODUCT-000 route states. It only mutates marked test pages.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || TIO2_SITE_SCOPE !== 'tio2-my') {
    throw new RuntimeException('Isolated local CLI only.');
}

$action = $args[0] ?? '';
$registry = tio2_route_registry();
$statuses = ['publish','draft','pending','private','future','trash'];

$fixture_pages = static function(?string $route_key = null) use ($statuses): array {
    $meta_query = [['key'=>'_tio2_test_fixture', 'value'=>'products-readiness']];
    if ($route_key !== null) $meta_query[] = ['key'=>'_tio2_test_route_key', 'value'=>$route_key];
    return get_posts([
        'post_type'=>'page', 'post_status'=>$statuses, 'numberposts'=>-1,
        'orderby'=>'ID', 'order'=>'ASC', 'meta_query'=>$meta_query,
    ]);
};
$remove = static function(array $pages): array {
    $removed = [];
    foreach ($pages as $page) {
        if (get_post_meta($page->ID, '_tio2_test_fixture', true) !== 'products-readiness') continue;
        wp_trash_post($page->ID);
        wp_delete_post($page->ID, true);
        $removed[] = (int) $page->ID;
    }
    flush_rewrite_rules(false);
    return $removed;
};

if ($action === 'managed-status') {
    $page = tio2_products_managed_page();
    $result = [
        'page_id'=>(int) $page->ID,
        'status'=>$page->post_status,
        'template'=>(string) get_post_meta($page->ID, '_wp_page_template', true),
    ];
} elseif ($action === 'purge-stale' || $action === 'cleanup') {
    $result = ['removed'=>$remove($fixture_pages())];
} elseif ($action === 'assert-clear') {
    $occupied = [];
    foreach ($registry as $key => $route) {
        $path = tio2_products_field($route['path_field']);
        if (get_page_by_path(trim($path, '/'), OBJECT, 'page')) $occupied[] = $key;
    }
    $result = ['occupied'=>$occupied];
} elseif ($action === 'create') {
    $route_key = $args[1] ?? '';
    $scope = $args[2] ?? '';
    if (!isset($registry[$route_key]) || !in_array($scope, ['tio2-my','tio2-a'], true)) throw new RuntimeException('Invalid fixture route or scope.');
    if ($fixture_pages($route_key)) throw new RuntimeException('Fixture route already exists.');
    $route = $registry[$route_key];
    $path = tio2_products_field($route['path_field']);
    if (get_page_by_path(trim($path, '/'), OBJECT, 'page')) throw new RuntimeException('Route path is already occupied.');
    $parts = explode('/', trim($path, '/'));
    $slug = array_pop($parts);
    $parent = 0;
    if ($parts) {
        $parent_page = get_page_by_path(implode('/', $parts), OBJECT, 'page');
        if (!$parent_page || get_post_meta($parent_page->ID, '_tio2_page_id', true) !== 'PRODUCT-000') throw new RuntimeException('Approved Products parent is unavailable.');
        $parent = (int) $parent_page->ID;
    }
    $page_id = wp_insert_post([
        'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Readiness fixture ' . $route_key,
        'post_name'=>$slug, 'post_parent'=>$parent, 'post_content'=>'',
    ], true);
    if (is_wp_error($page_id)) throw new RuntimeException($page_id->get_error_message());
    update_post_meta($page_id, '_tio2_page_id', $route['page_id']);
    update_post_meta($page_id, '_tio2_site_scope', $scope);
    update_post_meta($page_id, '_tio2_test_fixture', 'products-readiness');
    update_post_meta($page_id, '_tio2_test_route_key', $route_key);
    flush_rewrite_rules(false);
    $result = ['page_id'=>(int) $page_id, 'route_key'=>$route_key, 'scope'=>$scope, 'path'=>$path];
} elseif ($action === 'create-hub-clone') {
    if (get_page_by_path('products-clone', OBJECT, 'page')) throw new RuntimeException('Clone path is already occupied.');
    $page_id = wp_insert_post([
        'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Products clone fixture',
        'post_name'=>'products-clone', 'post_content'=>'',
    ], true);
    if (is_wp_error($page_id)) throw new RuntimeException($page_id->get_error_message());
    update_post_meta($page_id, '_tio2_page_id', 'PRODUCT-000');
    update_post_meta($page_id, '_tio2_site_scope', 'tio2-my');
    update_post_meta($page_id, '_tio2_managed_page', '1');
    update_post_meta($page_id, '_wp_page_template', 'page-products.php');
    update_post_meta($page_id, '_tio2_test_fixture', 'products-readiness');
    update_post_meta($page_id, '_tio2_test_route_key', 'PRODUCT-000-CLONE');
    flush_rewrite_rules(false);
    $result = ['page_id'=>(int) $page_id, 'path'=>'/products-clone/'];
} elseif ($action === 'delete') {
    $route_key = $args[1] ?? '';
    if (!isset($registry[$route_key])) throw new RuntimeException('Invalid fixture route.');
    $result = ['removed'=>$remove($fixture_pages($route_key))];
} else {
    throw new RuntimeException('Unknown readiness fixture action.');
}

echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
