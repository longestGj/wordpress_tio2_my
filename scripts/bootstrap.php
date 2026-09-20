<?php
// Runs only through WP-CLI in the local project-owned environment.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') throw new RuntimeException('Local CLI only');
$marker=getenv('TIO2_BOOTSTRAP_MARKER') ?: '.runtime/bootstrap-complete';
if (str_starts_with($marker,'.runtime/')) $marker='/workspace/'.$marker;
if (!str_starts_with($marker,'/workspace/.runtime/') || is_file($marker)) throw new RuntimeException('Bootstrap already completed or marker is invalid');
update_option('blog_public',0);
update_option('permalink_structure','/%postname%/');
update_option('timezone_string','Asia/Shanghai');
update_option('default_comment_status','closed');
update_option('default_ping_status','closed');
foreach(get_posts(['post_type'=>['post','page'],'post_status'=>'any','numberposts'=>-1]) as $post) {
    // Only remove WordPress installer samples in an otherwise fresh installation.
    if (in_array($post->post_name,['hello-world','sample-page','privacy-policy'],true)) wp_delete_post($post->ID,true);
}
require_once ABSPATH.'wp-admin/includes/plugin.php';
activate_plugin('tio2-content/tio2-content.php');
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/media.php';
require_once ABSPATH.'wp-admin/includes/image.php';
$source='/workspace/content/media/hero.png';
$temporary=wp_tempnam('hero.png');
copy($source,$temporary);
$id=media_handle_sideload(['name'=>'titanium-dioxide-material.png','tmp_name'=>$temporary],0,'Titanium dioxide material');
if(is_wp_error($id)) throw new RuntimeException($id->get_error_message());
update_post_meta($id,'_tio2_site_scope','tio2-my');
update_post_meta($id,'_wp_attachment_image_alt','');
$content=json_decode(file_get_contents('/workspace/content/initial-home.json'),true,512,JSON_THROW_ON_ERROR);
$content['fields']['hero.image']=(string)$id;
$valid=tio2_validate_content($content,tio2_schema(),'tio2_owned_image');
if(is_wp_error($valid)) throw new RuntimeException($valid->get_error_message());
update_option('tio2_content',$valid,false);
flush_rewrite_rules();
echo wp_json_encode(['site_scope'=>'tio2-my','hero_attachment'=>$id,'field_count'=>count($valid['fields'])])."\n";
