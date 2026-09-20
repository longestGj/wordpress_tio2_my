<?php
// Pure RFQ model test: no database, runtime receiver, or approval-file dependency.
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    public function __construct(public string $code, public string $message) {}
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10): void {}
function get_option($key, $default = null) { return $default; }

require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/validation.php';
require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/rfq.php';

$count = 0;
function check_rfq_model($condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}

$raw_schema = json_decode(file_get_contents(__DIR__ . '/../../wp-content/plugins/tio2-content/rfq-schema.json'), true, 512, JSON_THROW_ON_ERROR);
$defaults = tio2_rfq_default_content();
$schema = tio2_rfq_schema();
check_rfq_model($defaults['site_scope'] === 'tio2-my', 'site scope');
check_rfq_model($defaults['schema_version'] === 1, 'schema version');
check_rfq_model(array_keys($raw_schema) === array_keys($defaults['fields']), 'schema and defaults have one exact ordered field set');
check_rfq_model(!is_wp_error(tio2_validate_content($defaults, $schema, static fn() => false)), 'defaults validate');

$contract = tio2_rfq_form_contract($defaults);
$controls = $contract['controls'];
check_rfq_model(array_column($controls, 'name') === [
    'grade_id',
    'application_id',
    'quantity_mt',
    'destination_country',
    'destination_port_city',
    'company_name',
    'contact_name',
    'business_email',
    'phone_whatsapp',
    'website',
    'additional_requirements',
], 'exact eleven editable controls in semantic order');
check_rfq_model($contract['quantity_unit'] === ['value' => 'MT', 'label' => 'Metric tonnes (MT)', 'editable' => false], 'fixed MT semantic value is not editable');

$by_name = array_column($controls, null, 'name');
check_rfq_model(array_values(array_filter(array_column($controls, 'required'))) === [true, true, true, true, true, true, true], 'exact seven required controls');
check_rfq_model($by_name['destination_country']['placeholder'] === 'Enter the destination country', 'exact Destination Country placeholder');
check_rfq_model($by_name['destination_country']['maxlength'] === 100, 'country limit');
check_rfq_model($by_name['destination_port_city']['maxlength'] === 120, 'port/city limit');
check_rfq_model($by_name['company_name']['minlength'] === 2 && $by_name['company_name']['maxlength'] === 160, 'company limits');
check_rfq_model($by_name['contact_name']['minlength'] === 2 && $by_name['contact_name']['maxlength'] === 100, 'contact limits');
check_rfq_model($by_name['business_email']['maxlength'] === 254, 'email limit');
check_rfq_model($by_name['phone_whatsapp']['maxlength'] === 40, 'phone limit');
check_rfq_model($by_name['website']['maxlength'] === 2048, 'website limit');
check_rfq_model($by_name['additional_requirements']['maxlength'] === 2000, 'additional requirements limit');

check_rfq_model($by_name['grade_id']['options'] === [
    'M-350', 'M-510', 'M-896', 'M-996', 'M-2196', 'M-895', 'M-200',
    'M-108', 'M-210', 'M-340', 'M-886', 'M-52', 'M-2377', 'CR-901',
    'Not sure / Need help',
], 'fourteen grades plus unknown option in exact order');
check_rfq_model($by_name['application_id']['options'] === [
    'Coatings', 'Plastics', 'Masterbatch', 'Printing Inks', 'Paper',
    'Specialty Materials', 'Other / Not sure',
], 'six applications plus other in exact order');

check_rfq_model($by_name['destination_port_city']['helper'] === 'Add this only if it is already known.', 'port helper');
check_rfq_model($by_name['business_email']['helper'] === 'Use the business email where we can respond to this request.', 'email helper');
check_rfq_model($by_name['additional_requirements']['helper'] === 'Add any non-confidential specification, packaging, schedule, document or other context that may help us review the request.', 'additional requirements helper');

$expected_errors = [
    'grade_required' => 'Select a product or grade, or choose “Not sure / Need help.”',
    'application_required' => 'Select an application.',
    'quantity_invalid' => 'Enter a quantity greater than 0.',
    'destination_country_required' => 'Enter a destination country.',
    'destination_country_long' => 'Keep the destination country to 100 characters or fewer.',
    'destination_port_city_long' => 'Keep the destination port or city to 120 characters or fewer.',
    'company_name_required' => 'Enter your company name.',
    'company_name_long' => 'Keep your company name to 160 characters or fewer.',
    'contact_name_required' => 'Enter your name.',
    'contact_name_long' => 'Keep your name to 100 characters or fewer.',
    'business_email_required' => 'Enter your business email.',
    'business_email_invalid' => 'Enter a business email in the format name@company.com.',
    'phone_whatsapp_long' => 'Keep the phone or WhatsApp number to 40 characters or fewer.',
    'website_invalid' => 'Enter a complete website address or remove this optional value.',
    'additional_requirements_long' => 'Keep additional requirements to 2,000 characters or fewer.',
];
check_rfq_model($contract['errors'] === $expected_errors, 'all fifteen exact errors');

$fields = $defaults['fields'];
check_rfq_model($fields['hero.eyebrow'] === 'B2B QUOTATION REQUEST', 'eyebrow');
check_rfq_model($fields['hero.heading'] === 'Request a Titanium Dioxide Quote', 'heading');
check_rfq_model($fields['hero.body'] === 'Tell us the product, application, quantity and destination you are evaluating. Our team will review your requirements and prepare the appropriate commercial response.', 'hero body');
check_rfq_model($fields['form.heading'] === 'Quotation request details', 'form heading');
check_rfq_model($fields['submit.normal'] === 'REQUEST QUOTE' && $fields['submit.pending'] === 'SUBMITTING…', 'submit states');
check_rfq_model($fields['failure.heading'] === 'Something went wrong while submitting your request.' && $fields['failure.action'] === 'TRY AGAIN', 'failure state');
check_rfq_model($fields['success.heading'] === 'Thank you. We’ve received your quotation request.', 'success state');
check_rfq_model($fields['unavailable.heading'] === 'The quotation request form is temporarily unavailable.', 'unavailable state');
check_rfq_model($fields['privacy.path'] === '/privacy-policy/', 'privacy route');
check_rfq_model($fields['other.sample-path'] === '/request-sample/' && $fields['other.documents-path'] === '/request-documents/', 'external routes');
check_rfq_model($fields['seo.title'] === 'Request a Titanium Dioxide Quote | TiO2 Malaysia', 'SEO title');
check_rfq_model($fields['seo.description'] === 'Request a titanium dioxide quotation from TiO2 Malaysia by providing your grade, application, quantity in metric tonnes and destination for review.', 'SEO description');

echo "$count RFQ model assertions passed\n";
