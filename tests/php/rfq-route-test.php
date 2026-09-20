<?php
// WordPress-loaded ownership and query-prefill contract.
$count = 0;
$check = static function($condition, string $message) use (&$count): void {
    if (!$condition) throw new RuntimeException($message);
    $count++;
};

$check(function_exists('tio2_rfq_page_is_owned'), 'route ownership helper exists');
$check(function_exists('tio2_rfq_prefill'), 'prefill helper exists');
$page = get_page_by_path('request-a-quote', OBJECT, 'page');
$check($page instanceof WP_Post, 'managed RFQ page exists');
$check(tio2_rfq_page_is_owned((int) $page->ID), 'managed Malaysia RFQ page owns route');
$check(!tio2_rfq_page_is_owned((int) get_option('page_on_front')), 'homepage cannot own RFQ route');
$check(get_page_template_slug((int) $page->ID) === 'page-request-a-quote.php', 'managed page uses RFQ template');

$valid = tio2_rfq_prefill([
    'grade_id'=>'m-2377',
    'application_id'=>'coatings',
    'destination_country'=>'Malaysia',
    'process_context'=>'sulfate',
]);
$check($valid === [
    'grade_id'=>'m-2377',
    'application_id'=>'coatings',
    'destination_country'=>'Malaysia',
    'additional_requirements'=>'Sulfate',
], 'approved prefill projects visibly and neutrally');

$invalid = tio2_rfq_prefill([
    'grade_id'=>['m-350'],
    'application_id'=>'stale',
    'destination_country'=>str_repeat('x', 101),
    'market_id'=>'european-union',
    'source_page_id'=>'PRODUCT-M2377',
    'process_context'=>'specialty-materials',
    'company_name'=>'Must not prefill',
]);
$check($invalid === [], 'arrays, stale values, broad regions, internal IDs, and buyer data fail closed');

echo "$count RFQ route ownership and prefill assertions passed\n";
