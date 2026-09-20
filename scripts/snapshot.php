<?php
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local') throw new RuntimeException('Local CLI only');
$action=$args[0] ?? 'export';
if($action==='export') {
    echo wp_json_encode(['content'=>tio2_content(),'wordpress_version'=>get_bloginfo('version'),'theme'=>wp_get_theme()->get('Version'),'site_scope'=>TIO2_SITE_SCOPE],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
} elseif($action==='restore') {
    $path=$args[1] ?? '';
    $resolved=realpath($path);
    $allowed=$resolved && (str_starts_with($resolved,'/workspace/docs/verification/home/') || str_starts_with($resolved,'/workspace/.runtime/home-regression/'));
    if(!$allowed) throw new RuntimeException('Snapshot must be in an approved local evidence directory');
    $data=json_decode(file_get_contents($resolved),true,512,JSON_THROW_ON_ERROR);
    $valid=tio2_validate_content($data['content']??null,tio2_schema(),'tio2_owned_image');
    if(is_wp_error($valid)) throw new RuntimeException($valid->get_error_message());
    update_option('tio2_content',$valid,false);
    echo 'Restored content SHA256: '.hash('sha256',wp_json_encode(tio2_content()))."\n";
} else throw new RuntimeException('Unknown snapshot action');
