<?php
/** Validate structure and safety, never compare content with historical approval. */
function tio2_validate_content($candidate, array $schema, callable $media_check) {
    $fail = static fn($field) => new WP_Error('tio2_invalid_content', 'Invalid field: ' . $field . '. Previous content was preserved.');
    if (!is_array($candidate) || ($candidate['site_scope'] ?? null) !== 'tio2-my' || ($candidate['schema_version'] ?? null) !== 1) return $fail('site identity');
    if (array_diff(array_keys($candidate), ['site_scope','schema_version','fields'])) return $fail('unknown property');
    $fields = $candidate['fields'] ?? null;
    if (!is_array($fields) || count($fields) !== count($schema) || array_diff_key($fields,$schema) || array_diff_key($schema,$fields)) return $fail('incomplete fields');
    foreach ($schema as $key => $definition) {
        $value = $fields[$key];
        if (!is_string($value) || preg_match('//u', $value) !== 1 || strlen($value) > 12000 || preg_match('/[<>\x00-\x1f\x7f]/u', $value)) return $fail($key);
        if ($definition['type'] !== 'alt' && trim($value) === '') return $fail($key);
        if ($value !== trim($value)) return $fail($key);
        if ($definition['type'] === 'path' && !preg_match('~^(?:/(?:[a-zA-Z0-9_-]+/)*(?:#[a-zA-Z0-9_-]+)?|#[a-zA-Z0-9_-]+)$~D', $value)) return $fail($key);
        if ($definition['type'] === 'asset' && !in_array($value,$definition['choices'],true)) return $fail($key);
        if ($definition['type'] === 'image' && (!preg_match('/^[1-9][0-9]{0,9}$/D',$value) || !$media_check((int)$value))) return $fail($key);
    }
    return $candidate;
}
