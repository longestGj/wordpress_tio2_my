<?php
// Local verification only; callers must restore the evidence snapshot in finally.
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local') throw new RuntimeException('Local CLI only');
$mode=$args[0] ?? '';
$data=get_option('tio2_content');
if ($mode==='wrong-scope') $data['site_scope']='tio2-a';
elseif ($mode==='missing-content') $data=null;
elseif ($mode==='foreign-media') {
    update_post_meta((int)$data['fields']['hero.image'],'_tio2_site_scope','tio2-a');
} elseif ($mode==='restore-media') {
    update_post_meta((int)$data['fields']['hero.image'],'_tio2_site_scope','tio2-my');
} else throw new RuntimeException('Unknown negative test');
if(!in_array($mode,['foreign-media','restore-media'],true)) update_option('tio2_content',$data,false);
echo $mode." applied to isolated local test environment\n";
