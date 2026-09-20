<?php
// Real isolated WordPress route-readiness test. Every created page is deleted in finally.
require '/var/www/html/wp-load.php';
$test_project = (string) getenv('TIO2_TEST_PROJECT');
$isolated_project = $test_project === 'd32-product-000' || str_starts_with($test_project, 'tio2-ci-');
if (wp_get_environment_type() !== 'local' || TIO2_SITE_SCOPE !== 'tio2-my' || !$isolated_project) {
    throw new RuntimeException('Isolated Products test runtime only.');
}

$created = [];
$count = 0;
function check_route($condition, $message) {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}
function create_route_page(string $path, string $page_id, string $scope, array &$created): int {
    $parts = explode('/', trim($path, '/'));
    $slug = array_pop($parts);
    $parent = 0;
    if ($parts) {
        $parent_page = get_page_by_path(implode('/', $parts), OBJECT, 'page');
        if (!$parent_page) throw new RuntimeException('Missing route parent for ' . $path);
        $parent = (int) $parent_page->ID;
    }
    $id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Route fixture ' . $page_id,
        'post_name' => $slug,
        'post_parent' => $parent,
    ], true);
    if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
    $created[] = (int) $id;
    update_post_meta($id, '_tio2_page_id', $page_id);
    update_post_meta($id, '_tio2_site_scope', $scope);
    return (int) $id;
}

try {
    $registry = tio2_route_registry();
    check_route(count($registry) === 19, 'registry has fourteen grades, two processes, and three support routes');
    check_route(count(array_filter(array_keys($registry), static fn($key) => tio2_resolve_route($key) !== null)) === 0, 'initial state is zero ready');

    $application_path = tio2_products_field($registry['APP-000']['path_field']);
    $wrong_scope = create_route_page($application_path, 'APP-000', 'tio2-a', $created);
    check_route(tio2_resolve_route('APP-000') === null, 'wrong scope fails closed');
    wp_delete_post($wrong_scope, true);
    $created = array_values(array_diff($created, [$wrong_scope]));

    $wrong_id = create_route_page($application_path, 'APP-FOREIGN', 'tio2-my', $created);
    check_route(tio2_resolve_route('APP-000') === null, 'wrong Page ID fails closed');
    wp_delete_post($wrong_id, true);
    $created = array_values(array_diff($created, [$wrong_id]));

    $keys = array_keys($registry);
    foreach (array_slice($keys, 0, 3) as $key) {
        $route = $registry[$key];
        create_route_page(tio2_products_field($route['path_field']), $route['page_id'], 'tio2-my', $created);
    }
    check_route(count(array_filter($keys, static fn($key) => tio2_resolve_route($key) !== null)) === 3, 'partial readiness resolves only three routes');
    foreach (array_slice($keys, 3) as $key) {
        $route = $registry[$key];
        create_route_page(tio2_products_field($route['path_field']), $route['page_id'], 'tio2-my', $created);
    }
    $resolved = array_map('tio2_resolve_route', $keys);
    check_route(count(array_filter($resolved)) === 19, 'full readiness resolves every registered route');
    check_route(count(array_unique(array_column($resolved, 'url'))) === 19, 'full readiness never borrows another route');
    check_route(array_reduce($resolved, static fn($ok, $route) => $ok && str_starts_with($route['url'], 'https://tio2products.com/'), true), 'resolved URLs use formal public origin');
} finally {
    foreach (array_reverse($created) as $id) {
        if (get_post_meta($id, '_tio2_page_id', true)) wp_delete_post($id, true);
    }
    flush_rewrite_rules(false);
}

echo "$count route readiness assertions passed\n";
