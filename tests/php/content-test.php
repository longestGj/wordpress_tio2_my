<?php
// Pure technical-contract tests: no live WordPress, database or source approval files.
class WP_Error { public function __construct(public $code, public $message) {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
require __DIR__ . '/../../wp-content/plugins/tio2-content/includes/validation.php';
$schema = json_decode(file_get_contents(__DIR__ . '/../../wp-content/plugins/tio2-content/schema.json'), true);
$valid = json_decode(file_get_contents(__DIR__ . '/../../content/initial-home.json'), true);
$valid['fields']['hero.image'] = '42';
$media = fn($id) => $id === 42;
$count = 0;
function check($condition, $name) { global $count; if (!$condition) throw new RuntimeException($name); $count++; }
check(!is_wp_error(tio2_validate_content($valid, $schema, $media)), 'accept valid content');
$changed=$valid; $changed['fields']['hero.heading.1']='An editable heading';
check(tio2_validate_content($changed,$schema,$media)['fields']['hero.heading.1']==='An editable heading','ordinary text does not compare approval');
foreach ([
    ['site_scope','tio2-a'], ['site_scope',null], ['schema_version',2],
] as [$key,$value]) { $bad=$valid; $bad[$key]=$value; check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject identity '.$key); }
foreach (['', '   ', '<script>alert(1)</script>', "unsafe\x00text"] as $value) {
    $bad=$valid; $bad['fields']['hero.heading.1']=$value;
    check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject bad heading');
}
foreach (['javascript:alert(1)','//other.test/','https://other.test/','/../secret/','/bad?x=1',"/safe/\n"] as $value) {
    $bad=$valid; $bad['fields']['hero.link.1']=$value;
    check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject unsafe path');
}
$bad=$valid; $bad['fields']['unknown']='value'; check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject unknown field');
$bad=$valid; unset($bad['fields']['hero.heading.1']); check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject incomplete payload');
$bad=$valid; $bad['fields']['hero.image']='43'; check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject foreign media');
$bad=$valid; $bad['fields']['hero.image']='42foo'; check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject malformed media');
$bad=$valid; $bad['fields']['hero.heading.1']=['nested']; check(is_wp_error(tio2_validate_content($bad,$schema,$media)), 'reject array');
check($valid['fields']['hero.heading.1']==='Malaysia Titanium Dioxide for Industrial Buyers','validation never mutates original');
echo "$count content contract assertions passed\n";
