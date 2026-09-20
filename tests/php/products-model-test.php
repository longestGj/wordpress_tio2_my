<?php
// Pure content-model test: no live WordPress, database or approval files.
define('ABSPATH', __DIR__ . '/');
class WP_Error { public function __construct(public $code, public $message) {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
function add_action($hook, $callback, $priority = 10) {}
function get_option($key) { return null; }

require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/validation.php';
require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/products.php';

$raw_schema = json_decode(file_get_contents(__DIR__ . '/../../wp-content/plugins/tio2-content/products-schema.json'), true, 512, JSON_THROW_ON_ERROR);
$schema = tio2_products_schema();
$defaults = json_decode(file_get_contents(__DIR__ . '/../../wp-content/plugins/tio2-content/products-defaults.json'), true, 512, JSON_THROW_ON_ERROR);
$count = 0;
function check($condition, $message) {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}

check($defaults['site_scope'] === 'tio2-my', 'site scope');
check($defaults['schema_version'] === 1, 'schema version');
check(array_keys($raw_schema) === array_keys($defaults['fields']), 'schema and defaults have one exact ordered field set');
check(count($schema) === 150, 'fixed field count');
check(!is_wp_error(tio2_validate_content($defaults, $schema, static fn() => false)), 'default payload validates');

$model = tio2_products_model($defaults);
$expected_grades = ['M-350','M-510','M-896','M-996','M-2196','M-895','M-200','M-108','M-210','M-340','M-886','M-52','M-2377','CR-901'];
$expected_summaries = [
    'Excellent hue and high gloss with strong hiding power.',
    'TMP/TME-free multi-application grade with high brightness and durability.',
    'Superior weather resistance with high gloss and excellent opacity for demanding exterior coatings.',
    'High-durability coatings grade with high opacity and good gloss.',
    'Highly durable coatings pigment with high opacity and easy dispersion.',
    'High-opacity, high-gloss coatings grade with good weather resistance.',
    'High-durability exterior plastics grade with strong anti-chalking performance.',
    'High-heat-stability plastics grade with low oil absorption and rapid dispersion.',
    'High hiding power and easy dispersion for polyolefin masterbatch.',
    'High whiteness with strong high-temperature anti-yellowing performance.',
    'Bright-white plastics grade with excellent dispersion and processability.',
    'Very high gloss, high opacity and low abrasivity for printing inks.',
    'High gloss and brightness with good opacity and easy dispersion.',
    'High-purity grade with low impurities and stable batch-to-batch quality.',
];
check(array_column($model['grades'], 'name') === $expected_grades, 'fourteen grades in exact order');
check(array_column($model['grades'], 'summary') === $expected_summaries, 'exact directory summaries');
check(array_values(array_map('count', $model['grade_groups'])) === [6,5,2,1], 'exact group sizes');
check(array_values(array_map('count', $model['applications'])) === [8,8,7,4,2,1], 'exact application sizes');
check(count($model['evaluation_steps']) === 5, 'five evaluation steps');
check(count($model['faqs']) === 5, 'five FAQs');
check($model['applications']['Specialty Materials'] === ['GRADE-CR901'], 'specialty relation is exact');
check(!str_contains(json_encode($model, JSON_THROW_ON_ERROR), 'Rubber'), 'Rubber is absent');

echo "$count products model assertions passed\n";
