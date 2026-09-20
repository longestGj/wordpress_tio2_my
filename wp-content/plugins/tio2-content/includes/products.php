<?php
/** PRODUCT-000 content, relationships, route readiness, and managed-page migration. */
defined('ABSPATH') || (defined('WP_CLI') && WP_CLI) || exit;

const TIO2_PRODUCTS_MIGRATION_VERSION = 1;
const TIO2_PRODUCTS_OPTION = 'tio2_products_content';
const TIO2_PRODUCTS_BACKUP_OPTION = 'tio2_products_migration_backup';
const TIO2_PRODUCTS_VERSION_OPTION = 'tio2_products_migration_version';
const TIO2_PRODUCTS_SUSPENDED_OPTION = 'tio2_products_migration_suspended';
const TIO2_PRODUCTS_ERROR_OPTION = 'tio2_products_migration_error';

function tio2_products_schema(): array {
    static $schema;
    if ($schema !== null) return $schema;
    $raw = json_decode(file_get_contents(dirname(__DIR__) . '/products-schema.json'), true, 512, JSON_THROW_ON_ERROR);
    $schema = [];
    foreach ($raw as $key => $type) {
        if (!is_string($type) || !in_array($type, ['text', 'path'], true)) {
            throw new RuntimeException('Invalid Products schema definition.');
        }
        $schema[$key] = ['type' => $type];
    }
    return $schema;
}

function tio2_products_default_content(): array {
    static $defaults;
    return $defaults ??= json_decode(file_get_contents(dirname(__DIR__) . '/products-defaults.json'), true, 512, JSON_THROW_ON_ERROR);
}

function tio2_products_content(): array {
    if (!function_exists('tio2_site_ready') || !tio2_site_ready()) {
        throw new RuntimeException('Site identity is missing or invalid.');
    }
    $valid = tio2_validate_content(get_option(TIO2_PRODUCTS_OPTION), tio2_products_schema(), static fn() => false);
    if (is_wp_error($valid)) throw new RuntimeException('Products content is unavailable.');
    return $valid;
}

function tio2_products_field(string $key): string {
    // A Products save occurs in a separate authenticated request.
    static $content;
    $content ??= tio2_products_content();
    if (!array_key_exists($key, $content['fields'])) throw new RuntimeException('Unknown Products content field.');
    return $content['fields'][$key];
}

function tio2_products_model(array $content): array {
    $fields = $content['fields'];
    $grade_keys = [
        'GRADE-M350', 'GRADE-M510', 'GRADE-M896', 'GRADE-M996', 'GRADE-M2196', 'GRADE-M895',
        'GRADE-M200', 'GRADE-M108', 'GRADE-M210', 'GRADE-M340', 'GRADE-M886',
        'GRADE-M52', 'GRADE-M2377', 'GRADE-CR901',
    ];
    $grades = [];
    foreach ($grade_keys as $offset => $route_key) {
        $number = $offset + 1;
        $grades[] = [
            'route_key' => $route_key,
            'name' => $fields["directory.grade.$number.name"],
            'summary' => $fields["directory.grade.$number.summary"],
            'cta' => $fields["directory.grade.$number.cta"],
            'path' => $fields["directory.grade.$number.path"],
        ];
    }
    $grade_groups = [];
    foreach ([[1, 0, 6], [2, 6, 5], [3, 11, 2], [4, 13, 1]] as [$number, $start, $length]) {
        $grade_groups[$fields["directory.group.$number.label"]] = array_slice($grades, $start, $length);
    }
    $application_routes = [
        ['GRADE-M350','GRADE-M510','GRADE-M896','GRADE-M996','GRADE-M2196','GRADE-M895','GRADE-M52','GRADE-M2377'],
        ['GRADE-M350','GRADE-M510','GRADE-M200','GRADE-M108','GRADE-M210','GRADE-M340','GRADE-M886','GRADE-M2377'],
        ['GRADE-M510','GRADE-M200','GRADE-M108','GRADE-M210','GRADE-M340','GRADE-M886','GRADE-M2377'],
        ['GRADE-M350','GRADE-M510','GRADE-M52','GRADE-M2377'],
        ['GRADE-M350','GRADE-M2377'],
        ['GRADE-CR901'],
    ];
    $applications = [];
    foreach ($application_routes as $offset => $routes) {
        $applications[$fields['selector.application.' . ($offset + 1) . '.label']] = $routes;
    }
    $evaluation_steps = [];
    for ($number = 1; $number <= 5; $number++) {
        $evaluation_steps[] = [
            'title' => $fields["evaluation.step.$number.title"],
            'body' => $fields["evaluation.step.$number.body"],
        ];
    }
    $faqs = [];
    for ($number = 1; $number <= 5; $number++) {
        $faqs[] = [
            'question' => $fields["faq.item.$number.question"],
            'answer' => $fields["faq.item.$number.answer"],
        ];
    }
    return compact('grades', 'grade_groups', 'applications', 'evaluation_steps', 'faqs');
}

function tio2_route_registry(): array {
    $registry = [
        'PRODUCT-PROC-CL' => ['page_id' => 'PRODUCT-PROC-CL', 'path_field' => 'process.chloride.path', 'kind' => 'process'],
        'PRODUCT-PROC-SU' => ['page_id' => 'PRODUCT-PROC-SU', 'path_field' => 'process.sulfate.path', 'kind' => 'process'],
        'APP-000' => ['page_id' => 'APP-000', 'path_field' => 'support.card.1.path', 'kind' => 'support'],
        'DOC-000' => ['page_id' => 'DOC-000', 'path_field' => 'support.card.2.path', 'kind' => 'support'],
        'MARKET-000' => ['page_id' => 'MARKET-000', 'path_field' => 'support.card.3.path', 'kind' => 'support'],
    ];
    $grade_ids = ['M350','M510','M896','M996','M2196','M895','M200','M108','M210','M340','M886','M52','M2377','CR901'];
    foreach ($grade_ids as $offset => $id) {
        $key = 'GRADE-' . $id;
        $registry[$key] = ['page_id' => $key, 'path_field' => 'directory.grade.' . ($offset + 1) . '.path', 'kind' => 'grade'];
    }
    return $registry;
}

function tio2_resolve_route(string $route_key): ?array {
    $route = tio2_route_registry()[$route_key] ?? null;
    if ($route === null || !function_exists('get_page_by_path')) return null;
    $path = tio2_products_field($route['path_field']);
    $page = get_page_by_path(trim($path, '/'), OBJECT, 'page');
    if (!$page || $page->post_status !== 'publish') return null;
    if (get_post_meta($page->ID, '_tio2_page_id', true) !== $route['page_id']) return null;
    if (get_post_meta($page->ID, '_tio2_site_scope', true) !== 'tio2-my') return null;
    return $route + ['post_id' => (int) $page->ID, 'path' => $path, 'url' => tio2_public_base_url() . ltrim($path, '/')];
}

function tio2_products_managed_page(): ?WP_Post {
    $page = get_page_by_path('products', OBJECT, 'page');
    if (!$page) return null;
    $owned = get_post_meta($page->ID, '_tio2_managed_page', true) === '1'
        && get_post_meta($page->ID, '_tio2_page_id', true) === 'PRODUCT-000'
        && get_post_meta($page->ID, '_tio2_site_scope', true) === 'tio2-my';
    if (!$owned) throw new RuntimeException('The products slug is already owned by another page.');
    return $page;
}

function tio2_products_assign_template(int $page_id): void {
    $template = get_stylesheet_directory() . '/page-products.php';
    if (is_file($template)) update_post_meta($page_id, '_wp_page_template', 'page-products.php');
    else delete_post_meta($page_id, '_wp_page_template');
}

function tio2_products_migrate(): array {
    if (!tio2_site_ready()) throw new RuntimeException('Site identity is missing or invalid.');
    if ((bool) get_option(TIO2_PRODUCTS_SUSPENDED_OPTION, false)) {
        throw new RuntimeException('Products migration is suspended. Run resume explicitly.');
    }
    $page = tio2_products_managed_page();
    $existing_content = get_option(TIO2_PRODUCTS_OPTION, null);
    if ((int) get_option(TIO2_PRODUCTS_VERSION_OPTION, 0) === TIO2_PRODUCTS_MIGRATION_VERSION && $page && is_array($existing_content)) {
        tio2_products_assign_template((int) $page->ID);
        return tio2_products_migration_status();
    }
    $backup = get_option(TIO2_PRODUCTS_BACKUP_OPTION, null);
    if (!is_array($backup)) {
        $backup = [
            'content_existed' => is_array($existing_content),
            'content' => is_array($existing_content) ? $existing_content : null,
            'created_content' => false,
            'created_page_id' => 0,
        ];
        update_option(TIO2_PRODUCTS_BACKUP_OPTION, $backup, false);
    }
    try {
        if (!is_array($existing_content)) {
            $defaults = tio2_products_default_content();
            $valid = tio2_validate_content($defaults, tio2_products_schema(), static fn() => false);
            if (is_wp_error($valid) || !add_option(TIO2_PRODUCTS_OPTION, $valid, '', false)) {
                throw new RuntimeException('Products defaults could not be installed.');
            }
            $backup['created_content'] = true;
            update_option(TIO2_PRODUCTS_BACKUP_OPTION, $backup, false);
        }
        if (!$page) {
            $page_id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Products',
                'post_name' => 'products',
                'post_content' => '',
            ], true);
            if (is_wp_error($page_id)) throw new RuntimeException($page_id->get_error_message());
            $backup['created_page_id'] = (int) $page_id;
            update_option(TIO2_PRODUCTS_BACKUP_OPTION, $backup, false);
            update_post_meta($page_id, '_tio2_page_id', 'PRODUCT-000');
            update_post_meta($page_id, '_tio2_site_scope', 'tio2-my');
            update_post_meta($page_id, '_tio2_managed_page', '1');
            tio2_products_assign_template((int) $page_id);
        }
        update_option(TIO2_PRODUCTS_VERSION_OPTION, TIO2_PRODUCTS_MIGRATION_VERSION, false);
        delete_option(TIO2_PRODUCTS_ERROR_OPTION);
        flush_rewrite_rules(false);
        return tio2_products_migration_status();
    } catch (Throwable $error) {
        if ($backup['created_page_id']) wp_trash_post($backup['created_page_id']);
        if ($backup['created_content']) delete_option(TIO2_PRODUCTS_OPTION);
        update_option(TIO2_PRODUCTS_ERROR_OPTION, $error->getMessage(), false);
        throw $error;
    }
}

function tio2_products_rollback(): array {
    $backup = get_option(TIO2_PRODUCTS_BACKUP_OPTION, []);
    if (!is_array($backup)) $backup = [];
    $page_id = (int) ($backup['created_page_id'] ?? 0);
    if ($page_id && get_post_meta($page_id, '_tio2_managed_page', true) === '1') {
        wp_trash_post($page_id);
    }
    if (($backup['created_content'] ?? false) === true) delete_option(TIO2_PRODUCTS_OPTION);
    delete_option(TIO2_PRODUCTS_VERSION_OPTION);
    update_option(TIO2_PRODUCTS_SUSPENDED_OPTION, 1, false);
    flush_rewrite_rules(false);
    return tio2_products_migration_status();
}

function tio2_products_resume(): array {
    delete_option(TIO2_PRODUCTS_SUSPENDED_OPTION);
    delete_option(TIO2_PRODUCTS_ERROR_OPTION);
    $backup = get_option(TIO2_PRODUCTS_BACKUP_OPTION, []);
    $page_id = (int) ($backup['created_page_id'] ?? 0);
    if ($page_id) {
        $page = get_post($page_id);
        if ($page && $page->post_status === 'trash') wp_untrash_post($page_id);
        if ($page && get_post_status($page_id) !== 'publish') wp_update_post(['ID' => $page_id, 'post_status' => 'publish']);
    }
    return tio2_products_migrate();
}

function tio2_products_migration_status(): array {
    $page = null;
    try { $page = tio2_products_managed_page(); } catch (Throwable $error) {}
    return [
        'version' => (int) get_option(TIO2_PRODUCTS_VERSION_OPTION, 0),
        'suspended' => (bool) get_option(TIO2_PRODUCTS_SUSPENDED_OPTION, false),
        'content_present' => is_array(get_option(TIO2_PRODUCTS_OPTION, null)),
        'page_id' => $page ? (int) $page->ID : 0,
        'page_status' => $page ? $page->post_status : null,
        'error' => (string) get_option(TIO2_PRODUCTS_ERROR_OPTION, ''),
    ];
}

function tio2_products_maybe_migrate(): void {
    if ((bool) get_option(TIO2_PRODUCTS_SUSPENDED_OPTION, false)) return;
    try { tio2_products_migrate(); }
    catch (Throwable $error) { update_option(TIO2_PRODUCTS_ERROR_OPTION, $error->getMessage(), false); }
}

add_action('init', 'tio2_products_maybe_migrate', 1);
