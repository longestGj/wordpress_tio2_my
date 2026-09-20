<?php
// Pure validation/receiver contract test. Injected transports never use network or email.
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    public function __construct(public string $code, public string $message = '', public $data = null) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10): void {}
function get_option($key, $default = null) { return $default; }
function wp_json_encode($value, $flags = 0): string { return json_encode($value, $flags | JSON_THROW_ON_ERROR); }
function wp_get_environment_type(): string { return 'local'; }

require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/validation.php';
require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/rfq.php';
require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/rfq-submission.php';

$count = 0;
function check_rfq_submission($condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}

$content = tio2_rfq_default_content();
$valid = [
    'grade_id' => 'm-350',
    'application_id' => 'coatings',
    'quantity_mt' => '12.5',
    'destination_country' => ' Malaysia ',
    'destination_port_city' => 'Port Klang',
    'company_name' => 'Example Materials Sdn Bhd',
    'contact_name' => 'Buyer One',
    'business_email' => 'buyer@example.test',
    'phone_whatsapp' => '+60 12 345 6789',
    'website' => 'https://example.test/procurement',
    'additional_requirements' => 'Non-confidential context.',
];

$normalized = tio2_rfq_validate_submission($valid, $content);
check_rfq_submission(!is_wp_error($normalized), 'valid payload accepted');
check_rfq_submission($normalized['destination_country'] === 'Malaysia', 'text trimmed');
check_rfq_submission($normalized['phone_whatsapp'] === '+60 12 345 6789', 'international plus preserved');
check_rfq_submission($normalized['quantity_unit'] === 'MT', 'unit added server-side');
check_rfq_submission($normalized['site_scope'] === 'tio2-my' && $normalized['page_id'] === 'CONV-RFQ', 'internal identity added server-side');
check_rfq_submission($normalized['workflow_type'] === 'rfq' && $normalized['locale'] === 'en', 'workflow identity added server-side');

$cases = [
    ['grade_id', '', 'grade_id', 'Select a product or grade, or choose “Not sure / Need help.”'],
    ['application_id', 'invalid', 'application_id', 'Select an application.'],
    ['quantity_mt', '0', 'quantity_mt', 'Enter a quantity greater than 0.'],
    ['destination_country', '   ', 'destination_country', 'Enter a destination country.'],
    ['destination_country', str_repeat('界', 101), 'destination_country', 'Keep the destination country to 100 characters or fewer.'],
    ['destination_port_city', str_repeat('p', 121), 'destination_port_city', 'Keep the destination port or city to 120 characters or fewer.'],
    ['company_name', 'x', 'company_name', 'Enter your company name.'],
    ['company_name', str_repeat('c', 161), 'company_name', 'Keep your company name to 160 characters or fewer.'],
    ['contact_name', '', 'contact_name', 'Enter your name.'],
    ['contact_name', str_repeat('n', 101), 'contact_name', 'Keep your name to 100 characters or fewer.'],
    ['business_email', '', 'business_email', 'Enter your business email.'],
    ['business_email', 'not-an-email', 'business_email', 'Enter a business email in the format name@company.com.'],
    ['phone_whatsapp', str_repeat('1', 41), 'phone_whatsapp', 'Keep the phone or WhatsApp number to 40 characters or fewer.'],
    ['website', 'company.test/path', 'website', 'Enter a complete website address or remove this optional value.'],
    ['additional_requirements', str_repeat('a', 2001), 'additional_requirements', 'Keep additional requirements to 2,000 characters or fewer.'],
];
foreach ($cases as [$key, $value, $error_field, $message]) {
    $candidate = $valid;
    $candidate[$key] = $value;
    $result = tio2_rfq_validate_submission($candidate, $content);
    check_rfq_submission(is_wp_error($result), "invalid $key rejected");
    $field_errors = $result->get_error_data()['fieldErrors'] ?? [];
    check_rfq_submission($field_errors === [$error_field => $message], "exact $key error");
}

foreach ([
    $valid + ['unexpected' => 'value'],
    $valid + ['quantity_unit' => 'kg'],
] as $candidate) {
    $result = tio2_rfq_validate_submission($candidate, $content);
    check_rfq_submission(is_wp_error($result) && $result->get_error_code() === 'tio2_rfq_request', 'unexpected key rejected');
}
$array_value = $valid;
$array_value['company_name'] = ['Example'];
$result = tio2_rfq_validate_submission($array_value, $content);
check_rfq_submission(is_wp_error($result) && $result->get_error_code() === 'tio2_rfq_request', 'array for scalar rejected');

$known_error = ['status'=>422, 'body'=>['success'=>false, 'errors'=>['business_email'=>'invalid', 'provider_only'=>'ignore']]];
$classified = tio2_rfq_classify_receiver_result($known_error);
check_rfq_submission($classified === ['state'=>'validation_failed', 'fieldErrors'=>['business_email'=>'Enter a business email in the format name@company.com.']], 'known provider error mapped and unknown ignored');
check_rfq_submission(tio2_rfq_classify_receiver_result(['configured'=>false]) === ['state'=>'service_unavailable', 'fieldErrors'=>[]], 'configured unavailable');
check_rfq_submission(tio2_rfq_classify_receiver_result(['status'=>200, 'body'=>['success'=>true]]) === ['state'=>'receipt_confirmed', 'fieldErrors'=>[]], 'explicit true confirms receipt');

foreach ([
    ['status'=>200, 'body'=>['success'=>false]],
    ['status'=>200, 'body'=>['message'=>'Submitted successfully']],
    ['status'=>200, 'body'=>[]],
    ['status'=>200, 'body'=>null],
    ['status'=>200, 'body'=>'not-json'],
    ['status'=>500, 'body'=>['success'=>true]],
    ['transport_error'=>'timeout'],
    ['transport_error'=>'network'],
    ['transport_error'=>'parse'],
] as $ambiguous) {
    check_rfq_submission(tio2_rfq_classify_receiver_result($ambiguous) === ['state'=>'submission_unconfirmed', 'fieldErrors'=>[]], 'unconfirmed receiver outcome fails closed');
}

$transport_calls = 0;
$transport = static function(array $payload) use (&$transport_calls): array {
    $transport_calls++;
    return ['status'=>200, 'body'=>['success'=>true]];
};
$result = tio2_rfq_receiver_adapter($normalized, $transport);
check_rfq_submission($result['state'] === 'receipt_confirmed' && $transport_calls === 1, 'adapter calls injected transport once');
$throw_calls = 0;
$result = tio2_rfq_receiver_adapter($normalized, static function() use (&$throw_calls): array {
    $throw_calls++;
    throw new RuntimeException('network fixture');
});
check_rfq_submission($result === ['state'=>'submission_unconfirmed', 'fieldErrors'=>[]] && $throw_calls === 1, 'exception is unconfirmed without retry');
putenv('TIO2_RFQ_RECEIVER_MODE');
$result = tio2_rfq_receiver_adapter($normalized);
check_rfq_submission($result === ['state'=>'service_unavailable', 'fieldErrors'=>[]], 'unconfigured adapter fails closed without network');
check_rfq_submission(!str_contains(wp_json_encode($result), 'buyer@example.test'), 'public result contains no buyer value');

echo "$count RFQ validation and receiver assertions passed\n";
