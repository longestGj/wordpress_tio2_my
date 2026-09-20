<?php
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local') throw new RuntimeException('Local CLI only');
$action=$args[0] ?? 'export';
if($action==='export') {
    echo wp_json_encode(['content'=>tio2_content(),'rfq_content'=>tio2_rfq_content(),'wordpress_version'=>get_bloginfo('version'),'theme'=>wp_get_theme()->get('Version'),'site_scope'=>TIO2_SITE_SCOPE],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
} elseif($action==='restore') {
    $path=$args[1] ?? '';
    $resolved=realpath($path);
    $allowed=['/workspace/.runtime/','/workspace/docs/verification/home/'];
    if(!$resolved || !array_filter($allowed,static fn($prefix)=>str_starts_with($resolved,$prefix))) throw new RuntimeException('Snapshot must be in an allowed local evidence directory');
    $data=json_decode(file_get_contents($resolved),true,512,JSON_THROW_ON_ERROR);
    $valid=tio2_validate_content($data['content']??null,tio2_schema(),'tio2_owned_image');
    if(is_wp_error($valid)) throw new RuntimeException($valid->get_error_message());
    $valid_rfq=tio2_validate_content($data['rfq_content']??null,tio2_rfq_schema(),static fn()=>false);
    if(is_wp_error($valid_rfq)) throw new RuntimeException($valid_rfq->get_error_message());
    update_option('tio2_content',$valid,false);
    update_option(TIO2_RFQ_OPTION,$valid_rfq,false);
    echo 'Restored content SHA256: '.hash('sha256',wp_json_encode(tio2_content()))."\n";
    echo 'Restored RFQ content SHA256: '.hash('sha256',wp_json_encode(tio2_rfq_content()))."\n";
} else throw new RuntimeException('Unknown snapshot action');
