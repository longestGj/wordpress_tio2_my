<?php
/** CONV-RFQ scoped content model. Submission and migration behavior are added separately. */
defined('ABSPATH') || (defined('WP_CLI') && WP_CLI) || exit;

const TIO2_RFQ_OPTION = 'tio2_rfq_content';
const TIO2_RFQ_MIGRATION_VERSION = 1;
const TIO2_RFQ_BACKUP_OPTION = 'tio2_rfq_migration_backup';
const TIO2_RFQ_VERSION_OPTION = 'tio2_rfq_migration_version';
const TIO2_RFQ_SUSPENDED_OPTION = 'tio2_rfq_migration_suspended';
const TIO2_RFQ_ERROR_OPTION = 'tio2_rfq_migration_error';

function tio2_rfq_schema(): array {
    static $schema;
    if ($schema !== null) return $schema;
    $raw = json_decode(file_get_contents(dirname(__DIR__) . '/rfq-schema.json'), true, 512, JSON_THROW_ON_ERROR);
    $schema = [];
    foreach ($raw as $key => $type) {
        if (!is_string($type) || !in_array($type, ['text', 'path'], true)) {
            throw new RuntimeException('Invalid RFQ schema definition.');
        }
        $schema[$key] = ['type' => $type];
    }
    return $schema;
}

function tio2_rfq_default_content(): array {
    static $defaults;
    return $defaults ??= json_decode(file_get_contents(dirname(__DIR__) . '/rfq-defaults.json'), true, 512, JSON_THROW_ON_ERROR);
}

function tio2_rfq_content(): array {
    if (!function_exists('tio2_site_ready') || !tio2_site_ready()) {
        throw new RuntimeException('Site identity is missing or invalid.');
    }
    $valid = tio2_validate_content(get_option(TIO2_RFQ_OPTION), tio2_rfq_schema(), static fn() => false);
    if (is_wp_error($valid)) throw new RuntimeException('RFQ content is unavailable.');
    return $valid;
}

function tio2_rfq_field(string $key): string {
    static $content;
    $content ??= tio2_rfq_content();
    if (!array_key_exists($key, $content['fields'])) throw new RuntimeException('Unknown RFQ content field.');
    return $content['fields'][$key];
}

function tio2_rfq_form_contract(?array $content = null): array {
    $content ??= tio2_rfq_content();
    $fields = $content['fields'];
    $grade_values = ['m-350','m-510','m-896','m-996','m-2196','m-895','m-200','m-108','m-210','m-340','m-886','m-52','m-2377','cr-901','not-sure-need-help'];
    $grade_labels = [];
    foreach (range(1, 15) as $number) $grade_labels[] = $fields["field.grade.option.$number"];
    $application_values = ['coatings','plastics','masterbatch','printing-inks','paper','specialty-materials','other-not-sure'];
    $application_labels = [];
    foreach (range(1, 7) as $number) $application_labels[] = $fields["field.application.option.$number"];
    $control = static fn(
        string $name,
        string $type,
        string $label,
        bool $required,
        int $maxlength = 0,
        int $minlength = 0,
        string $placeholder = '',
        string $helper = '',
        array $options = [],
        array $option_values = [],
    ): array => compact('name','type','label','required','maxlength','minlength','placeholder','helper','options','option_values');
    $controls = [
        $control('grade_id','select',$fields['field.grade.label'],true,0,0,$fields['field.grade.empty-option'],'',$grade_labels,$grade_values),
        $control('application_id','select',$fields['field.application.label'],true,0,0,$fields['field.application.empty-option'],'',$application_labels,$application_values),
        $control('quantity_mt','number',$fields['field.quantity.label'],true,0,0,$fields['field.quantity.placeholder']),
        $control('destination_country','text',$fields['field.destination-country.label'],true,100,1,$fields['field.destination-country.placeholder']),
        $control('destination_port_city','text',$fields['field.destination-port-city.label'],false,120,0,'',$fields['field.destination-port-city.helper']),
        $control('company_name','text',$fields['field.company-name.label'],true,160,2),
        $control('contact_name','text',$fields['field.contact-name.label'],true,100,2),
        $control('business_email','email',$fields['field.business-email.label'],true,254,0,$fields['field.business-email.placeholder'],$fields['field.business-email.helper']),
        $control('phone_whatsapp','tel',$fields['field.phone-whatsapp.label'],false,40),
        $control('website','url',$fields['field.website.label'],false,2048,0,$fields['field.website.placeholder']),
        $control('additional_requirements','textarea',$fields['field.additional-requirements.label'],false,2000,0,'',$fields['field.additional-requirements.helper']),
    ];
    $error_fields = [
        'grade_required' => 'grade-required',
        'application_required' => 'application-required',
        'quantity_invalid' => 'quantity-invalid',
        'destination_country_required' => 'destination-country-required',
        'destination_country_long' => 'destination-country-long',
        'destination_port_city_long' => 'destination-port-city-long',
        'company_name_required' => 'company-name-required',
        'company_name_long' => 'company-name-long',
        'contact_name_required' => 'contact-name-required',
        'contact_name_long' => 'contact-name-long',
        'business_email_required' => 'business-email-required',
        'business_email_invalid' => 'business-email-invalid',
        'phone_whatsapp_long' => 'phone-whatsapp-long',
        'website_invalid' => 'website-invalid',
        'additional_requirements_long' => 'additional-requirements-long',
    ];
    $errors = [];
    foreach ($error_fields as $key => $field_key) $errors[$key] = $fields['validation.error.' . $field_key];
    return [
        'controls' => $controls,
        'quantity_unit' => ['value'=>'MT', 'label'=>$fields['field.quantity.unit-label'], 'editable'=>false],
        'errors' => $errors,
    ];
}

function tio2_rfq_managed_page(): ?WP_Post {
    $page = get_page_by_path('request-a-quote', OBJECT, 'page');
    if (!$page) return null;
    $owned = get_post_meta($page->ID, '_tio2_managed_page', true) === '1'
        && get_post_meta($page->ID, '_tio2_page_id', true) === 'CONV-RFQ'
        && get_post_meta($page->ID, '_tio2_site_scope', true) === 'tio2-my';
    if (!$owned) throw new RuntimeException('The request-a-quote slug is already owned by another page.');
    return $page;
}

function tio2_rfq_assign_template(int $page_id): void {
    $template = get_stylesheet_directory() . '/page-request-a-quote.php';
    if (is_file($template)) update_post_meta($page_id, '_wp_page_template', 'page-request-a-quote.php');
    else delete_post_meta($page_id, '_wp_page_template');
}

function tio2_rfq_migration_status(): array {
    $page = null;
    try { $page = tio2_rfq_managed_page(); } catch (Throwable $error) {}
    return [
        'version' => (int) get_option(TIO2_RFQ_VERSION_OPTION, 0),
        'suspended' => (bool) get_option(TIO2_RFQ_SUSPENDED_OPTION, false),
        'content_present' => is_array(get_option(TIO2_RFQ_OPTION, null)),
        'page_id' => $page ? (int) $page->ID : 0,
        'page_status' => $page ? $page->post_status : null,
        'error' => (string) get_option(TIO2_RFQ_ERROR_OPTION, ''),
    ];
}

function tio2_rfq_migrate(): array {
    if (!function_exists('tio2_site_ready') || !tio2_site_ready()) {
        throw new RuntimeException('Site identity is missing or invalid.');
    }
    if ((bool) get_option(TIO2_RFQ_SUSPENDED_OPTION, false)) {
        throw new RuntimeException('RFQ migration is suspended. Run resume explicitly.');
    }
    $page = tio2_rfq_managed_page();
    $existing_content = get_option(TIO2_RFQ_OPTION, null);
    $installed_version = (int) get_option(TIO2_RFQ_VERSION_OPTION, 0);
    if ($installed_version === TIO2_RFQ_MIGRATION_VERSION && $page && is_array($existing_content)) {
        tio2_rfq_assign_template((int) $page->ID);
        return tio2_rfq_migration_status();
    }
    $backup = get_option(TIO2_RFQ_BACKUP_OPTION, null);
    if (!is_array($backup)) {
        $backup = [
            'content_existed' => is_array($existing_content),
            'content' => is_array($existing_content) ? $existing_content : null,
            'created_content' => false,
            'created_page_id' => 0,
        ];
        update_option(TIO2_RFQ_BACKUP_OPTION, $backup, false);
    }
    try {
        if (!is_array($existing_content)) {
            $defaults = tio2_rfq_default_content();
            $valid = tio2_validate_content($defaults, tio2_rfq_schema(), static fn() => false);
            if (is_wp_error($valid) || !add_option(TIO2_RFQ_OPTION, $valid, '', false)) {
                throw new RuntimeException('RFQ defaults could not be installed.');
            }
            $backup['created_content'] = true;
            update_option(TIO2_RFQ_BACKUP_OPTION, $backup, false);
        }
        if (!$page) {
            $page_id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Request a Quote',
                'post_name' => 'request-a-quote',
                'post_content' => '',
            ], true);
            if (is_wp_error($page_id)) throw new RuntimeException($page_id->get_error_message());
            $backup['created_page_id'] = (int) $page_id;
            update_option(TIO2_RFQ_BACKUP_OPTION, $backup, false);
            update_post_meta($page_id, '_tio2_page_id', 'CONV-RFQ');
            update_post_meta($page_id, '_tio2_site_scope', 'tio2-my');
            update_post_meta($page_id, '_tio2_managed_page', '1');
            tio2_rfq_assign_template((int) $page_id);
        }
        update_option(TIO2_RFQ_VERSION_OPTION, TIO2_RFQ_MIGRATION_VERSION, false);
        delete_option(TIO2_RFQ_ERROR_OPTION);
        flush_rewrite_rules(false);
        return tio2_rfq_migration_status();
    } catch (Throwable $error) {
        if (($backup['created_page_id'] ?? 0) > 0) wp_trash_post((int) $backup['created_page_id']);
        if (($backup['created_content'] ?? false) === true) delete_option(TIO2_RFQ_OPTION);
        update_option(TIO2_RFQ_ERROR_OPTION, $error->getMessage(), false);
        throw $error;
    }
}

function tio2_rfq_rollback(): array {
    $backup = get_option(TIO2_RFQ_BACKUP_OPTION, []);
    if (!is_array($backup)) $backup = [];
    $page_id = (int) ($backup['created_page_id'] ?? 0);
    if ($page_id && get_post_meta($page_id, '_tio2_managed_page', true) === '1') {
        wp_trash_post($page_id);
    }
    if (($backup['created_content'] ?? false) === true) delete_option(TIO2_RFQ_OPTION);
    elseif (($backup['content_existed'] ?? false) === true && is_array($backup['content'] ?? null)) {
        update_option(TIO2_RFQ_OPTION, $backup['content'], false);
    }
    delete_option(TIO2_RFQ_VERSION_OPTION);
    update_option(TIO2_RFQ_SUSPENDED_OPTION, 1, false);
    flush_rewrite_rules(false);
    return tio2_rfq_migration_status();
}

function tio2_rfq_resume(): array {
    delete_option(TIO2_RFQ_SUSPENDED_OPTION);
    delete_option(TIO2_RFQ_ERROR_OPTION);
    $backup = get_option(TIO2_RFQ_BACKUP_OPTION, []);
    $page_id = (int) ($backup['created_page_id'] ?? 0);
    if ($page_id) {
        $page = get_post($page_id);
        if ($page && $page->post_status === 'trash') wp_untrash_post($page_id);
        if ($page && get_post_status($page_id) !== 'publish') wp_update_post(['ID'=>$page_id, 'post_status'=>'publish']);
    }
    return tio2_rfq_migrate();
}

function tio2_rfq_maybe_migrate(): void {
    if ((bool) get_option(TIO2_RFQ_SUSPENDED_OPTION, false)) return;
    try { tio2_rfq_migrate(); }
    catch (Throwable $error) { update_option(TIO2_RFQ_ERROR_OPTION, $error->getMessage(), false); }
}

add_action('init', 'tio2_rfq_maybe_migrate', 1);
