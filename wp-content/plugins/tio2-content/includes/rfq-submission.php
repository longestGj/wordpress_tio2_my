<?php
/** CONV-RFQ server validation, receiver classification, and same-origin controller. */
defined('ABSPATH') || (defined('WP_CLI') && WP_CLI) || exit;

function tio2_rfq_text($value, bool $multiline = false): ?string {
    if (!is_string($value) || preg_match('//u', $value) !== 1) return null;
    $value = $multiline ? str_replace(["\r\n", "\r"], "\n", $value) : $value;
    $value = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($value, false) : strip_tags($value);
    $pattern = $multiline ? '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u' : '/[\x00-\x1f\x7f]/u';
    return trim((string) preg_replace($pattern, '', $value));
}

function tio2_rfq_length(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function tio2_rfq_active_content(): array {
    if (function_exists('tio2_site_ready') && tio2_site_ready()) return tio2_rfq_content();
    return tio2_rfq_default_content();
}

function tio2_rfq_validate_submission(array $input, ?array $content = null) {
    $content ??= tio2_rfq_active_content();
    $contract = tio2_rfq_form_contract($content);
    $controls = array_column($contract['controls'], null, 'name');
    $allowed = array_keys($controls);
    if (array_diff(array_keys($input), $allowed)) {
        return new WP_Error('tio2_rfq_request', 'Unexpected request field.');
    }
    foreach ($allowed as $key) {
        if (isset($input[$key]) && !is_string($input[$key])) {
            return new WP_Error('tio2_rfq_request', 'Invalid request field type.');
        }
    }
    $values = [];
    foreach ($controls as $name => $definition) {
        $values[$name] = tio2_rfq_text($input[$name] ?? '', $definition['type'] === 'textarea');
        if ($values[$name] === null) return new WP_Error('tio2_rfq_request', 'Invalid request encoding.');
    }
    $errors = [];
    $messages = $contract['errors'];
    if (!in_array($values['grade_id'], $controls['grade_id']['option_values'], true)) {
        $errors['grade_id'] = $messages['grade_required'];
    }
    if (!in_array($values['application_id'], $controls['application_id']['option_values'], true)) {
        $errors['application_id'] = $messages['application_required'];
    }
    $quantity = filter_var($values['quantity_mt'], FILTER_VALIDATE_FLOAT);
    if ($quantity === false || !is_finite((float) $quantity) || (float) $quantity <= 0) {
        $errors['quantity_mt'] = $messages['quantity_invalid'];
    }
    $country_length = tio2_rfq_length($values['destination_country']);
    if ($country_length === 0) $errors['destination_country'] = $messages['destination_country_required'];
    elseif ($country_length > 100) $errors['destination_country'] = $messages['destination_country_long'];
    if (tio2_rfq_length($values['destination_port_city']) > 120) {
        $errors['destination_port_city'] = $messages['destination_port_city_long'];
    }
    $company_length = tio2_rfq_length($values['company_name']);
    if ($company_length < 2) $errors['company_name'] = $messages['company_name_required'];
    elseif ($company_length > 160) $errors['company_name'] = $messages['company_name_long'];
    $contact_length = tio2_rfq_length($values['contact_name']);
    if ($contact_length < 2) $errors['contact_name'] = $messages['contact_name_required'];
    elseif ($contact_length > 100) $errors['contact_name'] = $messages['contact_name_long'];
    $email_length = tio2_rfq_length($values['business_email']);
    if ($email_length === 0) $errors['business_email'] = $messages['business_email_required'];
    elseif ($email_length > 254 || filter_var($values['business_email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['business_email'] = $messages['business_email_invalid'];
    }
    if (tio2_rfq_length($values['phone_whatsapp']) > 40) {
        $errors['phone_whatsapp'] = $messages['phone_whatsapp_long'];
    }
    if ($values['website'] !== '') {
        $parts = parse_url($values['website']);
        $valid_website = is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
        if (tio2_rfq_length($values['website']) > 2048 || !$valid_website) {
            $errors['website'] = $messages['website_invalid'];
        }
    }
    if (tio2_rfq_length($values['additional_requirements']) > 2000) {
        $errors['additional_requirements'] = $messages['additional_requirements_long'];
    }
    if ($errors) {
        return new WP_Error('tio2_rfq_validation', 'RFQ validation failed.', ['fieldErrors'=>$errors]);
    }
    return $values + [
        'quantity_unit' => 'MT',
        'site_scope' => 'tio2-my',
        'page_id' => 'CONV-RFQ',
        'workflow_type' => 'rfq',
        'locale' => 'en',
    ];
}

function tio2_rfq_public_field_errors($raw_errors): array {
    if (!is_array($raw_errors)) return [];
    $messages = tio2_rfq_form_contract(tio2_rfq_active_content())['errors'];
    $map = [
        'grade_id'=>$messages['grade_required'],
        'application_id'=>$messages['application_required'],
        'quantity_mt'=>$messages['quantity_invalid'],
        'destination_country'=>$messages['destination_country_required'],
        'destination_port_city'=>$messages['destination_port_city_long'],
        'company_name'=>$messages['company_name_required'],
        'contact_name'=>$messages['contact_name_required'],
        'business_email'=>$messages['business_email_invalid'],
        'phone_whatsapp'=>$messages['phone_whatsapp_long'],
        'website'=>$messages['website_invalid'],
        'additional_requirements'=>$messages['additional_requirements_long'],
    ];
    $public = [];
    foreach ($raw_errors as $field => $ignored) {
        if (is_string($field) && isset($map[$field])) $public[$field] = $map[$field];
    }
    return $public;
}

function tio2_rfq_classify_receiver_result(array $response): array {
    $empty = ['state'=>'submission_unconfirmed', 'fieldErrors'=>[]];
    if (($response['configured'] ?? true) === false) return ['state'=>'service_unavailable', 'fieldErrors'=>[]];
    if (isset($response['transport_error'])) return $empty;
    $status = $response['status'] ?? null;
    $body = $response['body'] ?? null;
    if (!is_int($status) || !is_array($body)) return $empty;
    $field_errors = tio2_rfq_public_field_errors($body['errors'] ?? []);
    if ($field_errors) return ['state'=>'validation_failed', 'fieldErrors'=>$field_errors];
    if ($status >= 200 && $status < 300 && ($body['success'] ?? null) === true) {
        return ['state'=>'receipt_confirmed', 'fieldErrors'=>[]];
    }
    return $empty;
}

function tio2_rfq_receiver_adapter(array $payload, ?callable $transport = null): array {
    if ($transport === null) {
        $mode = strtolower(trim((string) getenv('TIO2_RFQ_RECEIVER_MODE')));
        if ($mode === 'fake' && function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local') {
            $outcome = (string) get_option('tio2_rfq_test_receiver_outcome', 'unavailable');
            if ($outcome === 'unavailable') {
                return tio2_rfq_classify_receiver_result(['configured'=>false]);
            }
            $transport = static function(array $ignored) use ($outcome): array {
                update_option(
                    'tio2_rfq_test_receiver_calls',
                    (int) get_option('tio2_rfq_test_receiver_calls', 0) + 1,
                    false
                );
                return match ($outcome) {
                    'success' => ['status'=>200, 'body'=>['success'=>true]],
                    'field_error' => ['status'=>422, 'body'=>[
                        'success'=>false,
                        'errors'=>['business_email'=>'Provider detail', 'provider_only'=>'Private detail'],
                    ]],
                    'message_only' => ['status'=>200, 'body'=>['message'=>'Accepted']],
                    'false_success' => ['status'=>200, 'body'=>['success'=>false]],
                    'empty' => ['status'=>200, 'body'=>[]],
                    'http_error' => ['status'=>500, 'body'=>['success'=>false]],
                    'malformed' => ['status'=>200, 'transport_error'=>'parse'],
                    'timeout' => ['transport_error'=>'timeout'],
                    default => ['transport_error'=>'network'],
                };
            };
        }
        $enabled = (string) getenv('TIO2_RFQ_RECEIVER_ENABLED') === '1';
        $key = trim((string) getenv('TIO2_RFQ_WEB3FORMS_ACCESS_KEY'));
        if ($transport === null && ($mode !== 'web3forms' || !$enabled || $key === '' || !function_exists('wp_get_environment_type') || wp_get_environment_type() !== 'production')) {
            return tio2_rfq_classify_receiver_result(['configured'=>false]);
        }
        if ($transport === null) $transport = static function(array $values) use ($key): array {
            if (!function_exists('wp_remote_post')) return ['transport_error'=>'unavailable'];
            $response = wp_remote_post('https://api.web3forms.com/submit', [
                'timeout' => 12,
                'redirection' => 0,
                'headers' => ['Accept'=>'application/json'],
                'body' => $values + ['access_key'=>$key],
            ]);
            if (is_wp_error($response)) return ['transport_error'=>'network'];
            $status = (int) wp_remote_retrieve_response_code($response);
            $raw = (string) wp_remote_retrieve_body($response);
            try { $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
            catch (Throwable $error) { return ['status'=>$status, 'transport_error'=>'parse']; }
            return ['status'=>$status, 'body'=>$body];
        };
    }
    try { $response = $transport($payload); }
    catch (Throwable $error) { return tio2_rfq_classify_receiver_result(['transport_error'=>'network']); }
    if (!is_array($response)) return tio2_rfq_classify_receiver_result(['transport_error'=>'parse']);
    return tio2_rfq_classify_receiver_result($response);
}

function tio2_rfq_public_response(string $state, array $field_errors, int $status): void {
    wp_send_json(['state'=>$state, 'fieldErrors'=>$field_errors], $status);
}

function tio2_rfq_same_origin(): bool {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? tio2_rfq_text(wp_unslash($_SERVER['HTTP_ORIGIN'])) : null;
    if ($origin === null || $origin === '') return false;
    $expected = wp_parse_url(home_url('/'));
    $actual = wp_parse_url($origin);
    foreach (['scheme', 'host', 'port'] as $part) {
        if (($expected[$part] ?? null) !== ($actual[$part] ?? null)) return false;
    }
    return true;
}

function tio2_rfq_rate_limited(): bool {
    $address = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
        ? $_SERVER['REMOTE_ADDR']
        : 'unknown';
    $key = 'tio2_rfq_' . substr(hash_hmac('sha256', $address, wp_salt('nonce')), 0, 40);
    $count = (int) get_transient($key);
    if ($count >= 5) return true;
    set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
    return false;
}

function tio2_rfq_submit_controller(): void {
    $unconfirmed = static fn(int $status) => tio2_rfq_public_response('submission_unconfirmed', [], $status);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $unconfirmed(405);
    if (!function_exists('tio2_site_ready') || !tio2_site_ready()) $unconfirmed(503);
    if (!tio2_rfq_same_origin()) $unconfirmed(403);
    if (!isset($_POST['nonce']) || !is_string($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'tio2_rfq_submit')) {
        $unconfirmed(403);
    }
    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($length > 32768) $unconfirmed(413);

    $allowed = array_merge(
        ['action', 'nonce', 'company_website'],
        array_column(tio2_rfq_form_contract(tio2_rfq_active_content())['controls'], 'name')
    );
    if (array_diff(array_keys($_POST), $allowed)) $unconfirmed(400);
    $honeypot = isset($_POST['company_website']) && is_string($_POST['company_website'])
        ? tio2_rfq_text(wp_unslash($_POST['company_website']))
        : null;
    if ($honeypot === null || $honeypot !== '') $unconfirmed(400);

    $input = [];
    foreach (array_column(tio2_rfq_form_contract(tio2_rfq_active_content())['controls'], 'name') as $name) {
        if (isset($_POST[$name])) $input[$name] = is_string($_POST[$name]) ? wp_unslash($_POST[$name]) : $_POST[$name];
    }
    $validated = tio2_rfq_validate_submission($input);
    if (is_wp_error($validated)) {
        if ($validated->get_error_code() === 'tio2_rfq_validation') {
            $data = $validated->get_error_data();
            tio2_rfq_public_response('validation_failed', $data['fieldErrors'] ?? [], 422);
        }
        $unconfirmed(400);
    }
    if (tio2_rfq_rate_limited()) $unconfirmed(429);

    $result = tio2_rfq_receiver_adapter($validated);
    $status = match ($result['state']) {
        'receipt_confirmed' => 200,
        'validation_failed' => 422,
        'service_unavailable' => 503,
        default => 502,
    };
    tio2_rfq_public_response($result['state'], $result['fieldErrors'], $status);
}

add_action('wp_ajax_nopriv_tio2_rfq_submit', 'tio2_rfq_submit_controller');
add_action('wp_ajax_tio2_rfq_submit', 'tio2_rfq_submit_controller');
