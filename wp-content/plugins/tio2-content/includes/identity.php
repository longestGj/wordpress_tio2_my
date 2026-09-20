<?php
defined('ABSPATH') || exit;
/** Verification-only identity computed from the files actually loaded, not approval records. */
function tio2_runtime_artifact(): string {
    $files=[];
    $roots=[
        'wp-content/plugins/tio2-content'=>dirname(__DIR__),
        'wp-content/themes/tio2-malaysia'=>get_template_directory(),
    ];
    foreach($roots as $prefix=>$root) {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file) {
            if(!$file->isFile()) continue;
            $relative=$prefix.'/'.str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($root)+1));
            $files[$relative]=hash_file('sha256',$file->getPathname());
        }
    }
    ksort($files,SORT_STRING);
    $identity='';
    foreach($files as $path=>$hash) $identity.=$path."\0".$hash."\n";
    return 'wp-'.hash('sha256',$identity);
}
add_action('send_headers',static function() {
    if(tio2_site_ready()) header('X-Site-Scope: tio2-my');
});
add_action('wp_head',static function() {
    if(wp_get_environment_type()!=='local') return;
    echo '<meta name="tio2-artifact" content="'.esc_attr(tio2_runtime_artifact()).'">'.PHP_EOL;
    echo '<meta name="tio2-content-sha256" content="'.hash('sha256',wp_json_encode(tio2_content(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'">'.PHP_EOL;
},2);
